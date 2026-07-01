<?php

declare(strict_types=1);

// @covers-us US-SELL-012

use App\Business;
use App\Console\Commands\SeedDefaultFsmProcesses;
use App\Domain\Fsm\Models\SaleProcess;
use App\Domain\Fsm\Models\SaleProcessStage;
use App\Domain\Fsm\Models\SaleStageAction;
use App\Domain\Fsm\Models\SaleStageHistory;
use App\Domain\Fsm\Services\ExecuteStageActionService;
use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Scopes\ScopeByBusiness;
use Spatie\Permission\PermissionRegistrar;

/**
 * US-SELL-012 — Gate de emissão NFe por venda (ADR 0129 §Pivot 2026-05-10).
 *
 * "Venda sem nota é caminho feliz, não falha". Auto-emissão deixa de ser flag
 * global por business e vira opt-in por venda via processo escolhido.
 *
 * Default biz=1 (Wagner WR2 SC) · cross-tenant biz=99 (BusinessIdGuard).
 * NUNCA biz=4 — auto-mem feedback_test_business_id_1_nunca_4.
 *
 * Setup: cria tabela `transactions` INLINE (mínima — só id/business_id/process_id/
 * current_stage_id) pra não depender de schema core UPos completo. Na produção, a
 * migration `2026_05_11_160001_add_fsm_columns_to_transactions` adiciona as 2
 * colunas via ALTER TABLE.
 */
class FakeSaleSubject extends Model
{
    protected $table = 'transactions';

    protected $guarded = ['id'];

    public $timestamps = false;
}

beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        test()->markTestSkipped('era-sqlite: schema sintético manual incompatível com MySQL persistente — quarentena Onda 2 SDD floor; burn-down converte depois.');
    }

    Schema::create('users', function (Blueprint $t) {
        $t->increments('id');
        $t->string('username')->unique();
        $t->string('password');
        $t->integer('business_id')->nullable();
        $t->rememberToken();
        $t->softDeletes();
        $t->timestamps();
    });

    Schema::create('business', function (Blueprint $t) {
        $t->increments('id');
        $t->string('name');
        $t->timestamps();
    });

    // Tabela transactions inline (só colunas que o test precisa)
    Schema::create('transactions', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->unsignedInteger('business_id');
        $t->unsignedBigInteger('process_id')->nullable();
        $t->unsignedBigInteger('current_stage_id')->nullable();
        $t->index(['business_id', 'current_stage_id'], 'transactions_biz_stage_idx');
    });

    Schema::create('permissions', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->string('name');
        $t->string('guard_name');
        $t->timestamps();
        $t->unique(['name', 'guard_name']);
    });
    Schema::create('roles', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->string('name');
        $t->string('guard_name');
        $t->timestamps();
        $t->unique(['name', 'guard_name']);
    });
    Schema::create('model_has_permissions', function (Blueprint $t) {
        $t->unsignedBigInteger('permission_id');
        $t->string('model_type');
        $t->unsignedBigInteger('model_id');
        $t->primary(['permission_id', 'model_id', 'model_type'], 'mhp_pk');
    });
    Schema::create('model_has_roles', function (Blueprint $t) {
        $t->unsignedBigInteger('role_id');
        $t->string('model_type');
        $t->unsignedBigInteger('model_id');
        $t->primary(['role_id', 'model_id', 'model_type'], 'mhr_pk');
    });
    Schema::create('role_has_permissions', function (Blueprint $t) {
        $t->unsignedBigInteger('permission_id');
        $t->unsignedBigInteger('role_id');
        $t->primary(['permission_id', 'role_id']);
    });

    foreach (glob(database_path('migrations/2026_05_11_12*_create_sale_*.php')) ?: [] as $f) {
        (require $f)->up();
    }

    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

afterEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        foreach (array_reverse(glob(database_path('migrations/2026_05_11_12*_create_sale_*.php')) ?: []) as $f) {
            (require $f)->down();
        }
        foreach ([
            'role_has_permissions', 'model_has_roles', 'model_has_permissions',
            'roles', 'permissions', 'transactions', 'business', 'users',
        ] as $tbl) {
            Schema::dropIfExists($tbl);
        }
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function gateUser(int $bizId): User
{
    return User::forceCreate([
        'username' => 'u_' . $bizId . '_' . uniqid(),
        'password' => bcrypt('x'),
        'business_id' => $bizId,
    ]);
}

function gateCriarBusiness(int $id): void
{
    \DB::table('business')->insert(['id' => $id, 'name' => 'Biz ' . $id, 'created_at' => now(), 'updated_at' => now()]);
}

function gateCriarVenda(int $bizId, ?int $processId, ?int $stageId): FakeSaleSubject
{
    $s = new FakeSaleSubject;
    $s->business_id = $bizId;
    $s->process_id = $processId;
    $s->current_stage_id = $stageId;
    $s->save();
    return $s;
}

function gateGetStage(int $businessId, string $processKey, string $stageKey): SaleProcessStage
{
    $process = SaleProcess::withoutGlobalScope(ScopeByBusiness::class)
        ->where('business_id', $businessId)
        ->where('key', $processKey)
        ->firstOrFail();

    return SaleProcessStage::where('process_id', $process->id)
        ->where('key', $stageKey)
        ->firstOrFail();
}

/**
 * Helper canônico (US-SELL-012): resolve processo default por Contact type
 * + business_id. Retorna `venda_sem_nota` quando Contact null/CF.
 */
function resolveDefaultProcessForContact(?string $contactType, int $businessId): ?SaleProcess
{
    $type = $contactType ?? 'cf';

    $proc = SaleProcess::withoutGlobalScope(ScopeByBusiness::class)
        ->where('business_id', $businessId)
        ->where('default_for_contact_type', $type)
        ->where('active', true)
        ->first();

    if ($proc) {
        return $proc;
    }

    // Fallback canônico: venda_sem_nota
    return SaleProcess::withoutGlobalScope(ScopeByBusiness::class)
        ->where('business_id', $businessId)
        ->where('key', 'venda_sem_nota')
        ->first();
}

it('1. SeedDefaultFsmProcesses --business=1 cria 3 processos com stages corretos e é idempotente', function () {
    gateCriarBusiness(1);

    $cmd = new SeedDefaultFsmProcesses;
    $cmd->seedForBusiness(1);

    $procs = SaleProcess::withoutGlobalScope(ScopeByBusiness::class)
        ->where('business_id', 1)
        ->orderBy('id')
        ->get();

    expect($procs)->toHaveCount(3);
    expect($procs->pluck('key')->all())->toEqualCanonicalizing([
        'venda_sem_nota', 'venda_com_nota_manual', 'venda_com_nota_auto',
    ]);

    // venda_sem_nota: 3 stages (rascunho, faturada, paga), SEM emitir_nfe
    $semNota = $procs->firstWhere('key', 'venda_sem_nota');
    $stagesSem = SaleProcessStage::where('process_id', $semNota->id)->get();
    expect($stagesSem)->toHaveCount(3);
    expect($stagesSem->pluck('key')->all())->toEqualCanonicalizing(['rascunho', 'faturada', 'paga']);
    expect($stagesSem->firstWhere('key', 'paga')->is_terminal)->toBeTrue();

    $allActionKeys = SaleStageAction::whereIn('stage_id', $stagesSem->pluck('id'))
        ->pluck('key')->all();
    expect($allActionKeys)->not->toContain('emitir_nfe');

    // venda_com_nota_manual: 5 stages, action emitir_nfe SEM event_class
    $manual = $procs->firstWhere('key', 'venda_com_nota_manual');
    $stagesManual = SaleProcessStage::where('process_id', $manual->id)->get();
    expect($stagesManual)->toHaveCount(5);

    $emitirManual = SaleStageAction::whereIn('stage_id', $stagesManual->pluck('id'))
        ->where('key', 'emitir_nfe')
        ->first();
    expect($emitirManual)->not->toBeNull();
    expect($emitirManual->event_class)->toBeNull();

    // venda_com_nota_auto: 5 stages, action emitir_nfe COM event_class
    $auto = $procs->firstWhere('key', 'venda_com_nota_auto');
    $stagesAuto = SaleProcessStage::where('process_id', $auto->id)->get();
    expect($stagesAuto)->toHaveCount(5);

    $emitirAuto = SaleStageAction::whereIn('stage_id', $stagesAuto->pluck('id'))
        ->where('key', 'emitir_nfe')
        ->first();
    expect($emitirAuto)->not->toBeNull();
    expect($emitirAuto->event_class)->not->toBeNull();
    expect($emitirAuto->event_class)->toContain('NFeEmissaoSolicitada');

    // Idempotência: 2ª chamada não duplica
    $cmd->seedForBusiness(1);
    $procs2 = SaleProcess::withoutGlobalScope(ScopeByBusiness::class)
        ->where('business_id', 1)
        ->get();
    expect($procs2)->toHaveCount(3);

    $stagesTotalAfter = SaleProcessStage::whereIn('process_id', $procs2->pluck('id'))->count();
    expect($stagesTotalAfter)->toBe(13); // 3 + 5 + 5
});

it('2. venda com process venda_sem_nota: stage paga NÃO tem action emitir_nfe (gate bloqueia)', function () {
    gateCriarBusiness(1);
    (new SeedDefaultFsmProcesses)->seedForBusiness(1);

    $stagePaga = gateGetStage(1, 'venda_sem_nota', 'paga');
    $process = SaleProcess::withoutGlobalScope(ScopeByBusiness::class)
        ->where('business_id', 1)->where('key', 'venda_sem_nota')->first();

    $venda = gateCriarVenda(1, $process->id, $stagePaga->id);

    // Simula a lógica do gate FSM do listener:
    $action = SaleStageAction::where('stage_id', $venda->current_stage_id)
        ->where('key', 'emitir_nfe')
        ->first();

    // Sem action emitir_nfe → listener faz no-op silencioso (caminho feliz)
    expect($action)->toBeNull();
});

it('3. venda com process venda_com_nota_auto: stage paga TEM action emitir_nfe com event_class', function () {
    Event::fake();
    gateCriarBusiness(1);
    (new SeedDefaultFsmProcesses)->seedForBusiness(1);

    $stagePaga = gateGetStage(1, 'venda_com_nota_auto', 'paga');
    $process = SaleProcess::withoutGlobalScope(ScopeByBusiness::class)
        ->where('business_id', 1)->where('key', 'venda_com_nota_auto')->first();

    $venda = gateCriarVenda(1, $process->id, $stagePaga->id);

    $action = SaleStageAction::where('stage_id', $venda->current_stage_id)
        ->where('key', 'emitir_nfe')
        ->first();

    // Action existe → listener prossegue com emissão
    expect($action)->not->toBeNull();
    expect($action->event_class)->not->toBeNull();
    expect($action->target_stage_id)->not->toBeNull();
});

it('4. venda com process venda_com_nota_manual: action emitir_nfe existe mas SEM event_class (não auto-dispara)', function () {
    gateCriarBusiness(1);
    (new SeedDefaultFsmProcesses)->seedForBusiness(1);

    $stagePaga = gateGetStage(1, 'venda_com_nota_manual', 'paga');

    $action = SaleStageAction::where('stage_id', $stagePaga->id)
        ->where('key', 'emitir_nfe')
        ->first();

    expect($action)->not->toBeNull();
    expect($action->event_class)->toBeNull(); // sem auto-dispatch
    expect($action->requires_confirmation)->toBeTrue(); // UI pede confirmação
});

it('5. multi-tenant: processos seedados pra biz=1 não aparecem pra biz=99', function () {
    gateCriarBusiness(1);
    gateCriarBusiness(99);

    (new SeedDefaultFsmProcesses)->seedForBusiness(1);

    $this->actingAs(gateUser(99));
    session(['user.business_id' => 99]);

    // Com global scope ScopeByBusiness, biz=99 não vê processos biz=1
    $visiveis = SaleProcess::all();
    expect($visiveis)->toHaveCount(0);

    // Cross-check: sem scope, vê os 3 de biz=1
    $todos = SaleProcess::withoutGlobalScope(ScopeByBusiness::class)
        ->where('business_id', 1)->get();
    expect($todos)->toHaveCount(3);
});

it('6. resolveDefaultProcessForContact: CF→sem_nota · PJ→com_nota_manual · null→fallback sem_nota', function () {
    gateCriarBusiness(1);
    (new SeedDefaultFsmProcesses)->seedForBusiness(1);

    // CF (Consumidor Final)
    $procCf = resolveDefaultProcessForContact('cf', 1);
    expect($procCf)->not->toBeNull();
    expect($procCf->key)->toBe('venda_sem_nota');

    // PJ
    $procPj = resolveDefaultProcessForContact('pj', 1);
    expect($procPj)->not->toBeNull();
    expect($procPj->key)->toBe('venda_com_nota_manual');

    // Contact null → fallback canônico venda_sem_nota
    $procNull = resolveDefaultProcessForContact(null, 1);
    expect($procNull)->not->toBeNull();
    expect($procNull->key)->toBe('venda_sem_nota');

    // PF → não tem default explícito, fallback venda_sem_nota
    $procPf = resolveDefaultProcessForContact('pf', 1);
    expect($procPf)->not->toBeNull();
    expect($procPf->key)->toBe('venda_sem_nota');
});
