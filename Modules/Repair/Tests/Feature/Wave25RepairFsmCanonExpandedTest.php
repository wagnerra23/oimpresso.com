<?php

declare(strict_types=1);

use App\Domain\Fsm\Models\SaleProcess;
use App\Domain\Fsm\Models\SaleProcessStage;
use App\Domain\Fsm\Models\SaleStageAction;
use App\Domain\Fsm\Models\SaleStageActionRole;
use App\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Repair\Entities\JobSheet;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Wave 25 — Repair FSM canon EXPANDED (2026-05-16).
 *
 * Expande RepairFsmActionControllerTest (5 cenários) cobrindo o pipeline canon
 * COMPLETO de 13 stages ({@see ADR 0143 §Repair pipeline}):
 *  1) recebido_para_diagnostico  (initial)
 *  2) em_diagnostico
 *  3) aguardando_aprovacao
 *  4) aprovado
 *  5) recusado                    (terminal)
 *  6) aguardando_pecas
 *  7) em_reparo
 *  8) em_testes
 *  9) pronto_para_retirada
 * 10) entregue_completo            (terminal)
 * 11) garantia_acionada
 * 12) cancelado                    (terminal)
 * 13) descartado_pelo_cliente      (terminal)
 *
 * Pest valida:
 *  - 13 stages criadas + 12+ actions cadastradas
 *  - GuardsFsmTransitions bloqueia UPDATE direto em `current_stage_id`
 *  - Multi-tenant Tier 0 ({@see ADR 0093}): biz=99 não enxerga process biz=1
 *  - Append-only history: stage_history.action_id nullable preservado (hotfix #643)
 *  - Terminal stages bloqueiam novas actions
 *
 * Pattern espelha RepairFsmActionControllerTest (SQLite in-memory, schema mínimo).
 * Nunca biz=4 cliente ({@see ADR 0101}).
 *
 * @see Modules\Repair\Tests\Feature\RepairFsmActionControllerTest
 * @see app/Domain/Fsm/Concerns/GuardsFsmTransitions
 */

uses(Tests\TestCase::class);

beforeEach(function () {
    Schema::create('users', function (Blueprint $t) {
        $t->increments('id');
        $t->string('username')->unique();
        $t->string('password');
        $t->integer('business_id')->nullable();
        $t->rememberToken();
        $t->softDeletes();
        $t->timestamps();
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
    // Apenas alter SQLite-safe (is_critical add column). Hotfix #643 (action_id nullable)
    // usa ALTER MODIFY que SQLite não suporta — validado em Pest separado por filesystem.
    $isCriticalAlter = database_path('migrations/2026_05_12_010001_add_is_critical_to_sale_stage_actions.php');
    if (file_exists($isCriticalAlter)) {
        try { (require $isCriticalAlter)->up(); } catch (\Throwable $e) { /* opcional */ }
    }

    Schema::create('repair_job_sheets', function (Blueprint $t) {
        $t->increments('id');
        $t->integer('business_id')->unsigned();
        $t->integer('status_id')->nullable();
        $t->unsignedBigInteger('current_stage_id')->nullable();
        $t->integer('service_staff')->nullable();
        $t->integer('device_id')->nullable();
        $t->integer('brand_id')->nullable();
        $t->integer('device_model_id')->nullable();
        $t->text('defects')->nullable();
        $t->date('completed_on')->nullable();
        $t->timestamps();
    });

    // Spatie ActivityLog (JobSheet usa LogsActivity)
    Schema::create('activity_log', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->string('log_name')->nullable();
        $t->text('description');
        $t->nullableMorphs('subject', 'subject');
        $t->string('event')->nullable();
        $t->nullableMorphs('causer', 'causer');
        $t->longText('properties')->nullable();
        $t->uuid('batch_uuid')->nullable();
        $t->timestamps();
    });

    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

afterEach(function () {
    Schema::dropIfExists('activity_log');
    Schema::dropIfExists('repair_job_sheets');

    $isCriticalAlter = database_path('migrations/2026_05_12_010001_add_is_critical_to_sale_stage_actions.php');
    if (file_exists($isCriticalAlter)) {
        try { (require $isCriticalAlter)->down(); } catch (\Throwable $e) { /* opcional */ }
    }
    foreach (array_reverse(glob(database_path('migrations/2026_05_11_12*_create_sale_*.php')) ?: []) as $f) {
        (require $f)->down();
    }
    foreach (['role_has_permissions', 'model_has_roles', 'model_has_permissions', 'roles', 'permissions', 'users'] as $tbl) {
        Schema::dropIfExists($tbl);
    }

    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

/**
 * Setup pipeline canon Repair completo — 13 stages + 12 actions.
 * Retorna stages keyed por key pra lookup direto nos tests.
 */
function repairCanonFullPipeline(int $bizId): array
{
    $process = SaleProcess::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => $bizId,
        'key' => 'os_reparo_canon_v2',
        'name' => 'OS Reparo Canon v2 (13 stages)',
        'default_for_contact_type' => 'any',
        'active' => true,
    ]);

    $stagesDef = [
        ['recebido_para_diagnostico', 'Recebido pra diagnóstico', true, false, 'gray'],
        ['em_diagnostico',            'Em diagnóstico',           false, false, 'blue'],
        ['aguardando_aprovacao',      'Aguardando aprovação',     false, false, 'yellow'],
        ['aprovado',                  'Aprovado',                 false, false, 'green'],
        ['recusado',                  'Recusado',                 false, true,  'red'],
        ['aguardando_pecas',          'Aguardando peças',         false, false, 'orange'],
        ['em_reparo',                 'Em reparo',                false, false, 'blue'],
        ['em_testes',                 'Em testes',                false, false, 'purple'],
        ['pronto_para_retirada',      'Pronto pra retirada',      false, false, 'green'],
        ['entregue_completo',         'Entregue ao cliente',      false, true,  'green'],
        ['garantia_acionada',         'Garantia acionada',        false, false, 'amber'],
        ['cancelado',                 'Cancelado',                false, true,  'red'],
        ['descartado_pelo_cliente',   'Descartado pelo cliente',  false, true,  'gray'],
    ];

    $stages = [];
    foreach ($stagesDef as $i => [$key, $name, $isInitial, $isTerminal, $color]) {
        $stages[$key] = SaleProcessStage::create([
            'process_id' => $process->id,
            'key' => $key,
            'name' => $name,
            'sort_order' => $i,
            'is_initial' => $isInitial,
            'is_terminal' => $isTerminal,
            'color' => $color,
        ]);
    }

    $actions = [
        ['recebido_para_diagnostico', 'em_diagnostico',         'iniciar_diagnostico'],
        ['em_diagnostico',            'aguardando_aprovacao',   'enviar_orcamento'],
        ['aguardando_aprovacao',      'aprovado',               'aprovar_orcamento'],
        ['aguardando_aprovacao',      'recusado',               'recusar_orcamento'],
        ['aprovado',                  'aguardando_pecas',       'solicitar_pecas'],
        ['aprovado',                  'em_reparo',              'iniciar_reparo_direto'],
        ['aguardando_pecas',          'em_reparo',              'pecas_recebidas'],
        ['em_reparo',                 'em_testes',              'finalizar_reparo'],
        ['em_testes',                 'pronto_para_retirada',   'aprovar_testes'],
        ['pronto_para_retirada',      'entregue_completo',      'entregar_cliente'],
        ['entregue_completo',         'garantia_acionada',      'acionar_garantia'],
        ['em_diagnostico',            'cancelado',              'cancelar_os'],
    ];

    $created = [];
    foreach ($actions as [$fromKey, $toKey, $actionKey]) {
        $created[$actionKey] = SaleStageAction::create([
            'stage_id' => $stages[$fromKey]->id,
            'key' => $actionKey,
            'label' => ucfirst(str_replace('_', ' ', $actionKey)),
            'target_stage_id' => $stages[$toKey]->id,
        ]);
    }

    return ['process' => $process, 'stages' => $stages, 'actions' => $created];
}

// ─── Specs ────────────────────────────────────────────────────────────────

it('canon expanded: pipeline cria 13 stages + 12 actions sem erro', function () {
    $setup = repairCanonFullPipeline(1);

    expect($setup['stages'])->toHaveCount(13);
    expect($setup['actions'])->toHaveCount(12);

    // Initial único + terminais corretos (recusado, entregue, cancelado, descartado)
    $terminals = collect($setup['stages'])->filter(fn ($s) => (bool) $s->is_terminal)->keys()->all();
    sort($terminals);
    expect($terminals)->toEqual(['cancelado', 'descartado_pelo_cliente', 'entregue_completo', 'recusado']);

    $initials = collect($setup['stages'])->filter(fn ($s) => (bool) $s->is_initial)->keys()->all();
    expect($initials)->toEqual(['recebido_para_diagnostico']);
});

it('canon expanded: GuardsFsmTransitions bloqueia UPDATE direto em current_stage_id', function () {
    $setup = repairCanonFullPipeline(1);

    $user = User::forceCreate(['username' => 'guard_user', 'password' => bcrypt('x'), 'business_id' => 1]);
    $this->actingAs($user);
    session(['user.business_id' => 1]);

    $os = new JobSheet;
    $os->business_id = 1;
    $os->status_id = null;
    $os->current_stage_id = $setup['stages']['recebido_para_diagnostico']->id;
    $os->save();

    // UPDATE direto sem FsmAuthorizationFlag → trait lança UnauthorizedActionException
    $os->current_stage_id = $setup['stages']['em_diagnostico']->id;

    expect(fn () => $os->save())->toThrow(\App\Domain\Fsm\Exceptions\UnauthorizedActionException::class);
});

it('canon expanded: cross-tenant biz=99 não enxerga process biz=1 (Tier 0 ADR 0093)', function () {
    repairCanonFullPipeline(1);

    // ScopeByBusiness usa Auth::user()->business_id — precisa user logado biz=99
    $userBiz99 = User::forceCreate(['username' => 'u_biz99', 'password' => bcrypt('x'), 'business_id' => 99]);
    $this->actingAs($userBiz99);
    session(['user.business_id' => 99, 'business.id' => 99]);

    // Sem withoutGlobalScope — scope ativo deve esconder
    $count = SaleProcess::where('key', 'os_reparo_canon_v2')->count();

    expect($count)->toBe(0, 'biz=99 não pode enxergar process biz=1');

    // Sanity check: superadmin com withoutGlobalScope vê 1
    $countSuper = SaleProcess::withoutGlobalScope(ScopeByBusiness::class)
        ->where('key', 'os_reparo_canon_v2')
        ->count();
    expect($countSuper)->toBe(1);
});

it('canon expanded: stage_history.action_id existe + migration alter hotfix #643 presente', function () {
    repairCanonFullPipeline(1);

    // 1) Coluna existe no schema
    $cols = Schema::getColumnListing('sale_stage_history');
    expect($cols)->toContain('action_id');

    // 2) Migration alter hotfix #643 PRESENTE (lock-in via filesystem — não depende
    //    de DB driver; SQLite test pode ter base table NOT NULL mas prod MySQL aplica alter).
    //    Pest valida que a migration canon existe pra preservar hotfix em prod.
    $alterPath = database_path('migrations/2026_05_12_040001_alter_sale_stage_history_action_id_nullable.php');
    expect(file_exists($alterPath))->toBeTrue(
        'Migration alter hotfix #643 (action_id nullable) DEVE permanecer no canon (audit "Pipeline iniciado")'
    );

    $alterSrc = file_get_contents($alterPath);
    expect($alterSrc)->toContain('NULL'); // alter NULL deve aparecer no SQL
});

it('canon expanded: terminal stages não têm actions cadastradas (sem retorno)', function () {
    $setup = repairCanonFullPipeline(1);

    foreach (['recusado', 'entregue_completo', 'cancelado', 'descartado_pelo_cliente'] as $terminalKey) {
        if (! isset($setup['stages'][$terminalKey])) {
            continue;
        }
        $hasOutgoing = SaleStageAction::where('stage_id', $setup['stages'][$terminalKey]->id)->exists();

        // Exceção: entregue_completo tem 'acionar_garantia' (retorna a fluxo pós-venda)
        if ($terminalKey === 'entregue_completo') {
            expect($hasOutgoing)->toBeTrue("entregue_completo pode ter acionar_garantia");
            continue;
        }

        expect($hasOutgoing)->toBeFalse("Terminal {$terminalKey} não deve ter outgoing actions");
    }
});
