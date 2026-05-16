<?php

declare(strict_types=1);

use App\Domain\Fsm\Exceptions\UnauthorizedActionException;
use App\Domain\Fsm\Support\FsmAuthorizationFlag;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Repair\Entities\JobSheet;
use Modules\Repair\Entities\RepairStatus;
use Modules\Repair\Services\KanbanProductionService;

/**
 * MultiTenantRepairTest — isolamento cross-tenant Tier 0 IRREVOGÁVEL.
 *
 * Cobre Repair (JobSheet + RepairStatus + KanbanProductionService) garantindo
 * que biz=1 (Wagner sandbox) nunca vê dado de biz=99 (tenant sintético).
 *
 * NUNCA usar biz=4 (ROTA LIVRE cliente real) — ADR 0101.
 *
 * Wave 13 (governance v3 D2 boost 15→18+): +3 cenários cobrindo
 * aggregates cross-tenant + FSM transition guard (ADR 0143).
 *
 * Refs:
 *   - ADR 0093 (Multi-tenant Tier 0)
 *   - ADR 0101 (Tests biz=1 nunca cliente)
 *   - ADR 0143 (FSM Pipeline canônico — GuardsFsmTransitions + FsmAuthorizationFlag)
 *   - memory/proibicoes.md §"Multi-tenant Tier 0 IRREVOGÁVEL"
 */

uses(Tests\TestCase::class);

beforeEach(function () {
    // SQLite guard — schema sintético só roda se driver in-memory.
    if (DB::connection()->getDriverName() !== 'sqlite') {
        test()->markTestSkipped('MultiTenantRepairTest exige SQLite in-memory.');
    }

    // Schema mínimo pra repair_job_sheets + repair_statuses sem depender da
    // suite Repair full migrations.
    Schema::create('repair_statuses', function (Blueprint $t) {
        $t->increments('id');
        $t->integer('business_id');
        $t->string('name');
        $t->integer('sort_order')->default(0);
        $t->boolean('is_completed_status')->default(false);
        $t->timestamps();
    });

    Schema::create('repair_job_sheets', function (Blueprint $t) {
        $t->increments('id');
        $t->integer('business_id');
        $t->integer('status_id')->nullable();
        $t->unsignedBigInteger('current_stage_id')->nullable();
        $t->string('job_sheet_no')->nullable();
        $t->string('serial_no')->nullable();
        $t->decimal('estimated_cost', 12, 2)->nullable();
        $t->timestamps();
    });

    // JobSheet + RepairStatus usam LogsActivity (Spatie) — tabela activity_log
    // é criada a cada save(). Schema mínimo replicado do canon
    // tests/Feature/Console/FsmBulkStartPipelineCommandTest.php.
    Schema::create('activity_log', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->string('log_name')->nullable()->index();
        $t->text('description');
        $t->unsignedBigInteger('subject_id')->nullable();
        $t->string('subject_type')->nullable();
        $t->unsignedBigInteger('causer_id')->nullable();
        $t->string('causer_type')->nullable();
        $t->string('causer_kind', 32)->nullable();
        $t->json('properties')->nullable();
        $t->uuid('batch_uuid')->nullable();
        $t->string('event')->nullable();
        $t->unsignedInteger('business_id')->nullable();
        $t->boolean('reverted_at')->nullable();
        $t->timestamps();
    });

    // Reset singleton FsmAuthorizationFlag entre cenários (per-request scope).
    FsmAuthorizationFlag::reset();
});

afterEach(function () {
    Schema::dropIfExists('activity_log');
    Schema::dropIfExists('repair_job_sheets');
    Schema::dropIfExists('repair_statuses');
});

test('JobSheet biz=1 não vaza pra query biz=99 (cross-tenant scope)', function () {
    JobSheet::create(['business_id' => 1, 'job_sheet_no' => 'OS-W-001', 'status_id' => null]);
    JobSheet::create(['business_id' => 1, 'job_sheet_no' => 'OS-W-002', 'status_id' => null]);
    JobSheet::create(['business_id' => 99, 'job_sheet_no' => 'OS-X-999', 'status_id' => null]);

    $biz1 = JobSheet::where('business_id', 1)->get();
    $biz99 = JobSheet::where('business_id', 99)->get();

    expect($biz1)->toHaveCount(2);
    expect($biz99)->toHaveCount(1);
    expect($biz1->pluck('job_sheet_no')->toArray())
        ->toBe(['OS-W-001', 'OS-W-002'])
        ->not->toContain('OS-X-999');
});

test('RepairStatus biz=1 e biz=99 são independentes (mesmo sort_order, IDs distintos)', function () {
    $sBiz1 = RepairStatus::create(['business_id' => 1, 'name' => 'Recepção', 'sort_order' => 1]);
    $sBiz99 = RepairStatus::create(['business_id' => 99, 'name' => 'Recepção', 'sort_order' => 1]);

    expect(RepairStatus::where('business_id', 1)->count())->toBe(1);
    expect(RepairStatus::where('business_id', 99)->count())->toBe(1);
    expect($sBiz1->id)->not->toBe($sBiz99->id);
});

test('KanbanProductionService respeita scope ao receber Collection scopada por biz', function () {
    RepairStatus::create(['business_id' => 1, 'name' => 'Recepção', 'sort_order' => 1, 'is_completed_status' => false]);
    RepairStatus::create(['business_id' => 1, 'name' => 'Pronto', 'sort_order' => 99, 'is_completed_status' => true]);

    // biz=99 com 4 statuses — não deve ser misturado.
    RepairStatus::create(['business_id' => 99, 'name' => 'Triagem', 'sort_order' => 1, 'is_completed_status' => false]);
    RepairStatus::create(['business_id' => 99, 'name' => 'Aprovado', 'sort_order' => 2, 'is_completed_status' => false]);
    RepairStatus::create(['business_id' => 99, 'name' => 'Entregue', 'sort_order' => 3, 'is_completed_status' => true]);

    $statusesBiz1 = RepairStatus::where('business_id', 1)->orderBy('sort_order')->get();
    $service = new KanbanProductionService();
    $map = $service->mapStatusesToColumns($statusesBiz1);

    // Map só contém IDs de biz=1.
    $biz1Ids = $statusesBiz1->pluck('id')->toArray();
    expect(array_keys($map))->toEqualCanonicalizing($biz1Ids);

    // Pronto bate.
    $prontoBiz1 = $statusesBiz1->where('is_completed_status', true)->first();
    expect($map[$prontoBiz1->id])->toBe('pronto');
});

test('drag-and-drop move() bloqueia OS de outro tenant (find scopado)', function () {
    JobSheet::create(['business_id' => 1, 'job_sheet_no' => 'OS-W-100', 'status_id' => null]);
    $jsBiz99 = JobSheet::create(['business_id' => 99, 'job_sheet_no' => 'OS-X-999', 'status_id' => null]);

    // Simula query do Controller (where business_id antes do find).
    $jobSheetEncontrado = JobSheet::where('business_id', 1)->find($jsBiz99->id);

    expect($jobSheetEncontrado)->toBeNull(); // tenant errado → null, NUNCA carrega
});

test('findStatusForColumn não usa statuses de outro tenant (entrada já filtrada)', function () {
    // biz=1 sem nenhum status Pronto cadastrado.
    RepairStatus::create(['business_id' => 1, 'name' => 'Triagem', 'sort_order' => 1, 'is_completed_status' => false]);

    // biz=99 tem Pronto — não pode vazar pra busca biz=1.
    RepairStatus::create(['business_id' => 99, 'name' => 'Pronto', 'sort_order' => 99, 'is_completed_status' => true]);

    $statusesBiz1 = RepairStatus::where('business_id', 1)->orderBy('sort_order')->get();
    $service = new KanbanProductionService();

    $result = $service->findStatusForColumn($statusesBiz1, 'pronto');

    expect($result)->toBeNull(); // biz=1 não tem Pronto, busca não pode pegar o de biz=99
});

// ─── Wave 13 D2 boost: aggregates + FSM transition guard (ADR 0143) ──────────

test('JobSheet aggregate sum(estimated_cost) scopado não vaza valor de biz=99', function () {
    // biz=1: 2 OS, total R$ 350,00
    JobSheet::create(['business_id' => 1, 'job_sheet_no' => 'OS-W-200', 'estimated_cost' => 150.00]);
    JobSheet::create(['business_id' => 1, 'job_sheet_no' => 'OS-W-201', 'estimated_cost' => 200.00]);

    // biz=99: 1 OS, R$ 9999,99 (valor "marcador" pra detectar vazamento)
    JobSheet::create(['business_id' => 99, 'job_sheet_no' => 'OS-X-998', 'estimated_cost' => 9999.99]);

    // Aggregate Eloquent scopado por business_id — não pode pegar 9999.99 do biz=99
    $totalBiz1 = JobSheet::where('business_id', 1)->sum('estimated_cost');
    $totalBiz99 = JobSheet::where('business_id', 99)->sum('estimated_cost');

    expect((float) $totalBiz1)->toBe(350.00);
    expect((float) $totalBiz99)->toBe(9999.99);

    // Aggregate sem scope (sanity check — sem WHERE pega tudo, por isso scope é Tier 0)
    $totalGlobal = JobSheet::sum('estimated_cost');
    expect((float) $totalGlobal)->toBe(10349.99);
});

test('GuardsFsmTransitions bloqueia UPDATE direto em current_stage_id sem FsmAuthorizationFlag', function () {
    // OS criada com current_stage_id inicial (INSERT bypass — trait só guarda UPDATE)
    $os = new JobSheet;
    $os->business_id = 1;
    $os->job_sheet_no = 'OS-W-FSM-001';
    $os->current_stage_id = 10;
    $os->save();

    // Garante que flag está limpa (não autorizada)
    FsmAuthorizationFlag::reset();

    // Tentativa de UPDATE direto sem passar pelo ExecuteStageActionService — deve lançar
    $os->current_stage_id = 20;

    expect(fn () => $os->save())
        ->toThrow(UnauthorizedActionException::class, 'Mudança direta em current_stage_id proibida');

    // Estado original preservado no banco (rollback do save abortado)
    $fresh = JobSheet::find($os->id);
    expect($fresh->current_stage_id)->toBe(10);
});

test('FsmAuthorizationFlag::mark autoriza UPDATE consume-once + segunda transição re-exige flag', function () {
    $os = new JobSheet;
    $os->business_id = 1;
    $os->job_sheet_no = 'OS-W-FSM-002';
    $os->current_stage_id = 10;
    $os->save();

    // Primeira transição: mark + save = OK
    FsmAuthorizationFlag::mark(JobSheet::class, $os->id);
    $os->current_stage_id = 20;
    $os->save();

    expect($os->fresh()->current_stage_id)->toBe(20);

    // Segunda transição SEM mark — consume-once já zerou a flag, deve bloquear
    $os->current_stage_id = 30;

    expect(fn () => $os->save())
        ->toThrow(UnauthorizedActionException::class);

    // Estado preservado em stage 20 (último autorizado)
    expect(JobSheet::find($os->id)->current_stage_id)->toBe(20);
});
