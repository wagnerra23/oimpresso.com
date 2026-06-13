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
 * CU-02 (G3) — Action FSM crítica exige role obrigatória (fail-secure).
 *
 * Estado em prod (ADR 0143 marco 2026-05-12):
 *   - Migration `2026_05_12_010001_add_is_critical_to_sale_stage_actions.php`
 *   - Lógica em `ExecuteStageActionService::execute()` (linhas ~64-73)
 *   - Seeder `FsmProcessoVendaComProducaoSeeder` marca is_critical=true em
 *     cancelar_venda × 9 stages, iniciar_producao, reabrir_para_revisao,
 *     concluir_producao, faturar, etc — sempre com role default
 *     (`vendas.gerente#{biz}`, `producao.iniciar#{biz}`, ...).
 *
 * Pain point Wagner 2026-05-12 (resolvido):
 *   "Orçamento foi para estágio voltou sem ninguém ter autorizado"
 *
 * Bug raiz original: action sem role em sale_stage_action_roles era LIBERADA
 * pra qualquer user (silent bypass). Hoje action is_critical=true SEM role
 * lança UnauthorizedActionException com mensagem instrutiva apontando tabela.
 *
 * Ver: memory/requisitos/Sells/CASOS-USO-PIPELINE-VENDAS.md §CU-02 +
 *      memory/proibicoes.md §FSM Pipeline Canônico.
 */

class FsmCriticalTestSubject extends Model
{
    protected $table = 'fsm_critical_subjects';

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

    Schema::create('fsm_critical_subjects', function (Blueprint $t) {
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

    // SPEC: nova migration que adiciona is_critical (ainda não existe — falha aqui)
    $migration = database_path('migrations/2026_05_12_010001_add_is_critical_to_sale_stage_actions.php');
    if (file_exists($migration)) {
        (require $migration)->up();
    }

    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

afterEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        foreach (array_reverse(glob(database_path('migrations/2026_05_11_12*_create_sale_*.php')) ?: []) as $f) {
            (require $f)->down();
        }
        foreach (['role_has_permissions', 'model_has_roles', 'model_has_permissions', 'roles', 'permissions', 'fsm_critical_subjects', 'users'] as $tbl) {
            Schema::dropIfExists($tbl);
        }
    }
});

function fsmSetupCritical(int $bizId): array
{
    $process = SaleProcess::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => $bizId,
        'key' => 'venda_padrao',
        'name' => 'Venda Padrão',
        'default_for_contact_type' => 'any',
        'active' => true,
    ]);

    $aprovado = SaleProcessStage::create([
        'process_id' => $process->id,
        'key' => 'quote_approved', 'name' => 'Aprovado', 'sort_order' => 0, 'is_initial' => true,
    ]);
    $enviado = SaleProcessStage::create([
        'process_id' => $process->id,
        'key' => 'quote_sent', 'name' => 'Orçamento enviado', 'sort_order' => 1,
    ]);

    return compact('process', 'aprovado', 'enviado');
}

function fsmCriticalSubject(int $bizId, int $stageId): FsmCriticalTestSubject
{
    $s = new FsmCriticalTestSubject;
    $s->business_id = $bizId;
    $s->current_stage_id = $stageId;
    $s->save();
    return $s;
}

function fsmCriticalUser(int $bizId): User
{
    return User::forceCreate([
        'username' => 'u' . $bizId . '_' . uniqid(),
        'password' => bcrypt('x'),
        'business_id' => $bizId,
    ]);
}

// ─── Specs ────────────────────────────────────────────────────────────────

it('1. action is_critical=true SEM role cadastrada bloqueia execução (fail-secure)', function () {
    ['aprovado' => $a, 'enviado' => $e] = fsmSetupCritical(1);

    // Action crítica sem nenhuma role em sale_stage_action_roles
    SaleStageAction::create([
        'stage_id' => $a->id,
        'key' => 'reabrir_para_revisao',
        'label' => 'Reabrir para revisão',
        'target_stage_id' => $e->id,
        'is_critical' => true, // coluna criada por 2026_05_12_010001_add_is_critical_to_sale_stage_actions
    ]);

    $subject = fsmCriticalSubject(1, $a->id);

    expect(fn () => (new ExecuteStageActionService)->execute($subject, 'reabrir_para_revisao', fsmCriticalUser(1)))
        ->toThrow(UnauthorizedActionException::class, 'exige role configurada');

    // current_stage_id permanece — transação rollback
    expect($subject->fresh()->current_stage_id)->toBe($a->id);
});

it('2. action is_critical=false sem role mantém comportamento aberto (back-compat)', function () {
    ['aprovado' => $a, 'enviado' => $e] = fsmSetupCritical(1);

    SaleStageAction::create([
        'stage_id' => $a->id,
        'key' => 'adicionar_observacao',
        'label' => 'Adicionar observação',
        'target_stage_id' => null,
        'is_critical' => false,
    ]);

    $subject = fsmCriticalSubject(1, $a->id);

    // Não deve lançar — caminho feliz pra actions não-críticas
    expect(fn () => (new ExecuteStageActionService)->execute($subject, 'adicionar_observacao', fsmCriticalUser(1)))
        ->not->toThrow(UnauthorizedActionException::class);
});

it('3. action is_critical=true COM role cadastrada exige user com role correta', function () {
    ['aprovado' => $a, 'enviado' => $e] = fsmSetupCritical(1);

    $action = SaleStageAction::create([
        'stage_id' => $a->id,
        'key' => 'reabrir_para_revisao',
        'label' => 'Reabrir',
        'target_stage_id' => $e->id,
        'is_critical' => true,
    ]);
    SaleStageActionRole::create(['action_id' => $action->id, 'role_name' => 'vendas.gerente']);
    Role::create(['name' => 'vendas.gerente', 'guard_name' => 'web']);

    $subject = fsmCriticalSubject(1, $a->id);
    $userSemRole = fsmCriticalUser(1); // não tem vendas.gerente

    expect(fn () => (new ExecuteStageActionService)->execute($subject, 'reabrir_para_revisao', $userSemRole))
        ->toThrow(UnauthorizedActionException::class);
});

it('4. action is_critical=true COM role + user COM role permite execução + loga history', function () {
    ['aprovado' => $a, 'enviado' => $e] = fsmSetupCritical(1);

    $action = SaleStageAction::create([
        'stage_id' => $a->id,
        'key' => 'reabrir_para_revisao',
        'label' => 'Reabrir',
        'target_stage_id' => $e->id,
        'is_critical' => true,
    ]);
    SaleStageActionRole::create(['action_id' => $action->id, 'role_name' => 'vendas.gerente']);
    Role::create(['name' => 'vendas.gerente', 'guard_name' => 'web']);

    $gerente = fsmCriticalUser(1);
    $gerente->assignRole('vendas.gerente');

    $subject = fsmCriticalSubject(1, $a->id);

    $history = (new ExecuteStageActionService)->execute($subject, 'reabrir_para_revisao', $gerente);

    expect($history->from_stage_id)->toBe($a->id);
    expect($history->to_stage_id)->toBe($e->id);
    expect($history->user_id)->toBe($gerente->id);
    expect($subject->fresh()->current_stage_id)->toBe($e->id);
});

it('5. mensagem da exception é instrutiva (cita action key + tabela + caminho fix)', function () {
    ['aprovado' => $a, 'enviado' => $e] = fsmSetupCritical(1);

    SaleStageAction::create([
        'stage_id' => $a->id,
        'key' => 'cancelar_venda',
        'label' => 'Cancelar venda',
        'target_stage_id' => $e->id,
        'is_critical' => true,
    ]);

    $subject = fsmCriticalSubject(1, $a->id);

    try {
        (new ExecuteStageActionService)->execute($subject, 'cancelar_venda', fsmCriticalUser(1));
        $this->fail('Esperava UnauthorizedActionException');
    } catch (UnauthorizedActionException $e) {
        expect($e->getMessage())
            ->toContain('cancelar_venda')                // action key
            ->toContain('crítica')                       // termo conceitual
            ->toContain('sale_stage_action_roles');      // tabela pra fix
    }
});

it('6. cross-tenant biz=99 vs subject biz=1 lança UnauthorizedActionException', function () {
    // biz=1 default smoke; biz=99 é convenção cross-tenant guard (ADR 0101 +
    // memory/feedback_test_biz_99_cross_tenant_convention.md). NUNCA biz=4
    // (cliente real ROTA LIVRE).
    ['aprovado' => $a, 'enviado' => $e] = fsmSetupCritical(99);

    $action = SaleStageAction::create([
        'stage_id' => $a->id,
        'key' => 'reabrir_para_revisao',
        'label' => 'Reabrir',
        'target_stage_id' => $e->id,
        'is_critical' => true,
    ]);
    SaleStageActionRole::create(['action_id' => $action->id, 'role_name' => 'vendas.gerente']);
    Role::create(['name' => 'vendas.gerente', 'guard_name' => 'web']);

    // Subject biz=1 (cross-tenant attempt: subject != process.business_id=99)
    $subject = fsmCriticalSubject(1, $a->id);
    $user = fsmCriticalUser(1);
    $user->assignRole('vendas.gerente');

    expect(fn () => (new ExecuteStageActionService)->execute($subject, 'reabrir_para_revisao', $user))
        ->toThrow(UnauthorizedActionException::class, 'Cross-tenant');
});
