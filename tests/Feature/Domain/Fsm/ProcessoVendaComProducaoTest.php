<?php

declare(strict_types=1);

use App\Domain\Fsm\Exceptions\UnauthorizedActionException;
use App\Domain\Fsm\Models\SaleProcess;
use App\Domain\Fsm\Models\SaleProcessStage;
use App\Domain\Fsm\Models\SaleStageAction;
use App\Domain\Fsm\Models\SaleStageActionRole;
use App\Domain\Fsm\Services\ExecuteStageActionService;
use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Scopes\ScopeByBusiness;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * CU-04 (G5) — Processo "Venda Com Produção" canônico.
 *
 * Specs FAILING-FIRST:
 *   1. Seeder FsmProcessoVendaComProducaoSeeder ainda não existe
 *   2. 9 stages canônicos + 12 actions + roles ainda não cadastrados
 *
 * Pain point Wagner 2026-05-12:
 *   "produção iniciada sem pessoas ter autorizado"
 *
 * Pipeline canônico:
 *   quote_draft → quote_sent → quote_approved → in_production →
 *   ready_for_invoice → invoiced → paid → delivered → completed (terminal)
 *
 * Transições laterais: cancelar_venda (qualquer stage não-terminal) → cancelled
 *                      pausar (qualquer stage operacional) → on_hold
 *
 * Ver: memory/requisitos/Sells/CASOS-USO-PIPELINE-VENDAS.md §CU-04
 */

class ProducaoTestSubject extends Model
{
    protected $table = 'producao_test_subjects';

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
    Schema::create('producao_test_subjects', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->unsignedInteger('business_id');
        $t->unsignedBigInteger('current_stage_id')->nullable();
    });
    foreach (['permissions', 'roles'] as $tbl) {
        Schema::create($tbl, function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('name');
            $t->string('guard_name');
            $t->timestamps();
            $t->unique(['name', 'guard_name']);
        });
    }
    Schema::create('model_has_roles', function (Blueprint $t) {
        $t->unsignedBigInteger('role_id');
        $t->string('model_type');
        $t->unsignedBigInteger('model_id');
        $t->primary(['role_id', 'model_id', 'model_type'], 'mhr_pk');
    });
    Schema::create('model_has_permissions', function (Blueprint $t) {
        $t->unsignedBigInteger('permission_id');
        $t->string('model_type');
        $t->unsignedBigInteger('model_id');
        $t->primary(['permission_id', 'model_id', 'model_type'], 'mhp_pk');
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
        foreach (['role_has_permissions', 'model_has_roles', 'model_has_permissions', 'roles', 'permissions', 'producao_test_subjects', 'users'] as $tbl) {
            Schema::dropIfExists($tbl);
        }
    }
});

function rodarSeederProducao(int $bizId): void
{
    /** @var \Database\Seeders\FsmProcessoVendaComProducaoSeeder $seeder */
    $seeder = app('\Database\Seeders\FsmProcessoVendaComProducaoSeeder');
    $seeder->runForBusiness($bizId);
}

function producaoSubject(int $bizId, int $stageId): ProducaoTestSubject
{
    $s = new ProducaoTestSubject;
    $s->business_id = $bizId;
    $s->current_stage_id = $stageId;
    $s->save();
    return $s;
}

function producaoUser(int $bizId, array $roles = []): User
{
    $u = User::forceCreate([
        'username' => 'u' . $bizId . '_' . uniqid(),
        'password' => bcrypt('x'),
        'business_id' => $bizId,
    ]);
    foreach ($roles as $r) {
        Role::findOrCreate($r, 'web');
        $u->assignRole($r);
    }
    return $u;
}

// ─── Specs ────────────────────────────────────────────────────────────────

it('1. seeder cria processo Venda Com Produção com 9 stages canônicos', function () {
    rodarSeederProducao(1);

    $process = SaleProcess::withoutGlobalScope(ScopeByBusiness::class)
        ->where('business_id', 1)
        ->where('key', 'venda_com_producao')
        ->first();

    expect($process)->not->toBeNull();

    $stages = $process->stages()->orderBy('sort_order')->pluck('key')->all();
    expect($stages)->toBe([
        'quote_draft',
        'quote_sent',
        'quote_approved',
        'in_production',
        'ready_for_invoice',
        'invoiced',
        'paid',
        'delivered',
        'completed',
    ]);

    // 2 stages adicionais (terminais alternativos)
    $cancelled = $process->stages()->where('key', 'cancelled')->first();
    expect($cancelled)->not->toBeNull();
    expect($cancelled->is_terminal)->toBeTrue();
});

it('2. iniciar_producao exige role producao.iniciar', function () {
    rodarSeederProducao(1);

    $process = SaleProcess::withoutGlobalScope(ScopeByBusiness::class)
        ->where('business_id', 1)->where('key', 'venda_com_producao')->first();
    $quoteApproved = $process->stages()->where('key', 'quote_approved')->first();

    $subject = producaoSubject(1, $quoteApproved->id);

    // User sem role producao.iniciar
    $caixa = producaoUser(1, ['vendas.caixa']);

    expect(fn () => (new ExecuteStageActionService)->execute($subject, 'iniciar_producao', $caixa))
        ->toThrow(UnauthorizedActionException::class);

    expect($subject->fresh()->current_stage_id)->toBe($quoteApproved->id);
});

it('3. iniciar_producao com role correta avança pra in_production', function () {
    rodarSeederProducao(1);

    $process = SaleProcess::withoutGlobalScope(ScopeByBusiness::class)
        ->where('business_id', 1)->where('key', 'venda_com_producao')->first();
    $quoteApproved = $process->stages()->where('key', 'quote_approved')->first();
    $inProduction = $process->stages()->where('key', 'in_production')->first();

    $subject = producaoSubject(1, $quoteApproved->id);
    $producaoUser = producaoUser(1, ['producao.iniciar']);

    $history = (new ExecuteStageActionService)->execute($subject, 'iniciar_producao', $producaoUser);

    expect($history->from_stage_id)->toBe($quoteApproved->id);
    expect($history->to_stage_id)->toBe($inProduction->id);
    expect($subject->fresh()->current_stage_id)->toBe($inProduction->id);
});

it('4. fluxo feliz end-to-end: rascunho → entregue passa por 8 transições', function () {
    rodarSeederProducao(1);

    $process = SaleProcess::withoutGlobalScope(ScopeByBusiness::class)
        ->where('business_id', 1)->where('key', 'venda_com_producao')->first();

    $stages = $process->stages()->orderBy('sort_order')->get()->keyBy('key');
    $subject = producaoSubject(1, $stages['quote_draft']->id);

    $superuser = producaoUser(1, [
        'vendas.enviar', 'vendas.confirmar_aprovacao',
        'producao.iniciar', 'producao.concluir',
        'financeiro.faturar', 'fiscal.emitir',
        'financeiro.baixar', 'logistica.entregar',
    ]);

    $service = new ExecuteStageActionService;

    $service->execute($subject, 'enviar_orcamento', $superuser);
    expect($subject->fresh()->current_stage_id)->toBe($stages['quote_sent']->id);

    $service->execute($subject, 'cliente_aprovou', $superuser);
    expect($subject->fresh()->current_stage_id)->toBe($stages['quote_approved']->id);

    $service->execute($subject, 'iniciar_producao', $superuser);
    expect($subject->fresh()->current_stage_id)->toBe($stages['in_production']->id);

    $service->execute($subject, 'concluir_producao', $superuser);
    expect($subject->fresh()->current_stage_id)->toBe($stages['ready_for_invoice']->id);

    $service->execute($subject, 'faturar', $superuser);
    expect($subject->fresh()->current_stage_id)->toBe($stages['invoiced']->id);

    $service->execute($subject, 'marcar_pago', $superuser);
    expect($subject->fresh()->current_stage_id)->toBe($stages['paid']->id);

    $service->execute($subject, 'entregar', $superuser);
    expect($subject->fresh()->current_stage_id)->toBe($stages['delivered']->id);

    $service->execute($subject, 'concluir', $superuser);
    expect($subject->fresh()->current_stage_id)->toBe($stages['completed']->id);
});

it('5. cancelar_venda em qualquer stage não-terminal funciona', function () {
    rodarSeederProducao(1);

    $process = SaleProcess::withoutGlobalScope(ScopeByBusiness::class)
        ->where('business_id', 1)->where('key', 'venda_com_producao')->first();
    $stages = $process->stages()->orderBy('sort_order')->get()->keyBy('key');

    foreach (['quote_sent', 'quote_approved', 'in_production', 'invoiced'] as $stageKey) {
        $subject = producaoSubject(1, $stages[$stageKey]->id);
        $gerente = producaoUser(1, ['vendas.gerente']);

        (new ExecuteStageActionService)->execute($subject, 'cancelar_venda', $gerente, ['motivo' => 'Cliente desistiu']);

        expect($subject->fresh()->current_stage_id)->toBe($stages['cancelled']->id);
    }
});

it('6. multi-tenant: seeder pra biz=1 não vaza pra biz=99', function () {
    rodarSeederProducao(1);

    $biz99Processes = SaleProcess::withoutGlobalScope(ScopeByBusiness::class)
        ->where('business_id', 99)
        ->count();

    expect($biz99Processes)->toBe(0);
});

it('7. seeder é idempotente — rodar 2x não cria duplicatas', function () {
    rodarSeederProducao(1);
    rodarSeederProducao(1);

    $count = SaleProcess::withoutGlobalScope(ScopeByBusiness::class)
        ->where('business_id', 1)
        ->where('key', 'venda_com_producao')
        ->count();

    expect($count)->toBe(1);
});
