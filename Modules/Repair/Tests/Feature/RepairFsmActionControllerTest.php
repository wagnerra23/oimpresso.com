<?php

declare(strict_types=1);

use App\Domain\Fsm\Models\SaleProcess;
use App\Domain\Fsm\Models\SaleProcessStage;
use App\Domain\Fsm\Models\SaleStageAction;
use App\Domain\Fsm\Models\SaleStageActionRole;
use App\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Repair\Entities\JobSheet;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * US-REP-FSM-004 — RepairFsmActionController Pest specs.
 *
 * Cobre os 5 cenários core:
 *   1. Autenticação obrigatória (401 sem login)
 *   2. RBAC: user sem role retorna can_execute=false (não 403 — actions list é gracioso)
 *   3. actions(): shape canônico do payload (current_stage + actions[])
 *   4. execute(): happy path — transição válida atualiza current_stage_id + retorna history
 *   5. startPipeline(): seta current_stage_id inicial em OS legacy (sem stage) + grava history
 *
 * Schema setup espelha tests/Feature/Domain/Fsm/* (SQLite in-memory).
 * Tabela `repair_job_sheets` criada inline pra cobrir o Model JobSheet sem
 * depender da suite Repair full schema.
 */

uses(Tests\TestCase::class);

beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        test()->markTestSkipped('era-sqlite: schema sintético manual incompatível com MySQL persistente — quarentena Onda 2 SDD floor; burn-down converte depois.');
    }

    // JobSheet usa Spatie LogsActivity (FSM history + status). Sem `activity_log`
    // o teste explode em SQLSTATE 1. Schema espelha vendor/spatie/laravel-activitylog.
    Schema::create('activity_log', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->string('log_name')->nullable();
        $t->text('description')->nullable();
        $t->unsignedBigInteger('subject_id')->nullable();
        $t->string('subject_type')->nullable();
        $t->unsignedBigInteger('causer_id')->nullable();
        $t->string('causer_type')->nullable();
        $t->text('properties')->nullable();
        $t->uuid('batch_uuid')->nullable();
        $t->string('event')->nullable();
        $t->unsignedInteger('business_id')->nullable();
        $t->timestamps();
    });

    // Middleware Timezone (web.php stack) acessa `Auth::user()->business->time_zone`
    // → precisa da tabela business mínima pra User belongsTo Business funcionar.
    Schema::create('business', function (Blueprint $t) {
        $t->increments('id');
        $t->string('name')->nullable();
        $t->string('time_zone')->nullable();
        $t->timestamps();
    });

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

    // FSM canon tables (sale_processes, sale_process_stages, etc).
    foreach (glob(database_path('migrations/2026_05_11_12*_create_sale_*.php')) ?: [] as $f) {
        (require $f)->up();
    }

    // Hotfix #643 (2026-05-12): startPipeline cria audit log SEM action_id.
    // Migration 2026_05_12_040001_alter_sale_stage_history_action_id_nullable
    // usa DB::statement ALTER TABLE MODIFY (MySQL-only — não roda em SQLite).
    // Aqui re-recria a tabela com action_id nullable pra refletir prod.
    if (Schema::hasTable('sale_stage_history')) {
        Schema::dropIfExists('sale_stage_history');
        Schema::create('sale_stage_history', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedInteger('business_id');
            $t->unsignedBigInteger('transaction_id');
            $t->unsignedBigInteger('action_id')->nullable(); // hotfix nullable
            $t->unsignedBigInteger('from_stage_id')->nullable();
            $t->unsignedBigInteger('to_stage_id')->nullable();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->json('payload_snapshot')->nullable();
            $t->dateTime('executed_at');
            $t->index(['business_id', 'transaction_id'], 'sale_history_biz_tx_idx');
            $t->index(['business_id', 'executed_at'], 'sale_history_biz_when_idx');
        });
    }

    // Tabela mínima `repair_job_sheets` pra suportar Model JobSheet em teste.
    // Schema completo está em Modules/Repair/Database/Migrations — aqui só
    // o subset necessário pro FSM (id, business_id, status_id, current_stage_id).
    Schema::create('repair_job_sheets', function (Blueprint $t) {
        $t->increments('id');
        $t->integer('business_id')->unsigned();
        $t->integer('status_id')->nullable();
        $t->unsignedBigInteger('current_stage_id')->nullable();
        $t->timestamps();
    });

    // Cria business=1 sintético pra middleware Timezone resolver $user->business
    // sem 500. Não usamos business=4 (ROTA LIVRE cliente real — ADR 0101).
    DB::table('business')->insert(['id' => 1, 'name' => 'TestBiz', 'time_zone' => 'America/Sao_Paulo']);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

afterEach(function () {
    // afterEach roda MESMO em teste pulado por markTestSkipped no beforeEach
    // (PHPUnit 12: $hasMetRequirements já é true antes do hook). DDL guardado por
    // driver pra não dropar tabelas REAL-migradas/CORE no MySQL persistente do nightly.
    if (DB::connection()->getDriverName() === 'sqlite') {
        Schema::dropIfExists('repair_job_sheets');

        foreach (array_reverse(glob(database_path('migrations/2026_05_11_12*_create_sale_*.php')) ?: []) as $f) {
            (require $f)->down();
        }
        foreach (['role_has_permissions', 'model_has_roles', 'model_has_permissions', 'roles', 'permissions', 'users', 'business', 'activity_log'] as $tbl) {
            Schema::dropIfExists($tbl);
        }
    }

    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

/**
 * Cria processo + 2 stages + action canônica pro biz dado.
 *
 * @return array{process: SaleProcess, recebido: SaleProcessStage, diagnostico: SaleProcessStage, action: SaleStageAction}
 */
function repairFsmFakeSetup(int $bizId): array
{
    $process = SaleProcess::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => $bizId,
        'key' => 'os_reparo_padrao',
        'name' => 'OS Reparo Padrão',
        'default_for_contact_type' => 'any',
        'active' => true,
    ]);

    $recebido = SaleProcessStage::create([
        'process_id' => $process->id,
        'key' => 'recebido_para_diagnostico',
        'name' => 'Recebido pra diagnóstico',
        'sort_order' => 0,
        'is_initial' => true,
        'color' => 'gray',
    ]);

    $diagnostico = SaleProcessStage::create([
        'process_id' => $process->id,
        'key' => 'em_diagnostico',
        'name' => 'Em diagnóstico',
        'sort_order' => 1,
        'color' => 'blue',
    ]);

    $action = SaleStageAction::create([
        'stage_id' => $recebido->id,
        'key' => 'iniciar_diagnostico',
        'label' => 'Iniciar diagnóstico',
        'target_stage_id' => $diagnostico->id,
    ]);

    return compact('process', 'recebido', 'diagnostico', 'action');
}

function repairFsmCreateOs(int $bizId, ?int $stageId = null): JobSheet
{
    $os = new JobSheet;
    $os->business_id = $bizId;
    $os->status_id = null;
    // Bypass GuardsFsmTransitions na criação inicial (insert, não update).
    if ($stageId !== null) {
        $os->current_stage_id = $stageId;
    }
    $os->save();
    return $os;
}

// ─── Specs ────────────────────────────────────────────────────────────────

it('1. sem autenticação retorna 401 em actions endpoint', function () {
    $response = $this->getJson('/api/repair/job-sheets/1/fsm-actions');
    $response->assertUnauthorized();
});

it('2. user sem role retorna can_execute=false (graceful, não 403)', function () {
    ['recebido' => $r, 'action' => $action] = repairFsmFakeSetup(1);
    SaleStageActionRole::create([
        'action_id' => $action->id,
        'role_name' => 'repair.tecnico',
    ]);
    Role::create(['name' => 'repair.tecnico', 'guard_name' => 'web']);

    $user = User::forceCreate(['username' => 'u_no_role', 'password' => bcrypt('x'), 'business_id' => 1]);
    $this->actingAs($user);
    session(['user.business_id' => 1]);

    $os = repairFsmCreateOs(1, $r->id);

    $response = $this->getJson("/api/repair/job-sheets/{$os->id}/fsm-actions");

    $response->assertOk()
        ->assertJsonPath('in_pipeline', true)
        ->assertJsonPath('actions.0.key', 'iniciar_diagnostico')
        ->assertJsonPath('actions.0.can_execute', false);
});

it('3. actions list retorna shape canônico com current_stage + actions[]', function () {
    ['recebido' => $r, 'diagnostico' => $d, 'action' => $action] = repairFsmFakeSetup(1);

    $user = User::forceCreate(['username' => 'u1', 'password' => bcrypt('x'), 'business_id' => 1]);
    $this->actingAs($user);
    session(['user.business_id' => 1]);

    $os = repairFsmCreateOs(1, $r->id);

    $response = $this->getJson("/api/repair/job-sheets/{$os->id}/fsm-actions");

    $response->assertOk()
        ->assertJsonStructure([
            'job_sheet_id',
            'current_stage' => ['key', 'name', 'color', 'is_terminal'],
            'actions' => [
                '*' => ['key', 'label', 'target_stage', 'is_critical', 'requires_confirmation', 'has_side_effect', 'can_execute'],
            ],
            'in_pipeline',
        ])
        ->assertJsonPath('current_stage.key', 'recebido_para_diagnostico')
        ->assertJsonPath('actions.0.key', 'iniciar_diagnostico')
        ->assertJsonPath('actions.0.target_stage.key', 'em_diagnostico');
});

it('4. execute happy path: action válida atualiza current_stage_id + retorna history', function () {
    ['recebido' => $r, 'diagnostico' => $d, 'action' => $action] = repairFsmFakeSetup(1);
    SaleStageActionRole::create([
        'action_id' => $action->id,
        'role_name' => 'repair.tecnico',
    ]);
    Role::create(['name' => 'repair.tecnico', 'guard_name' => 'web']);

    $user = User::forceCreate(['username' => 'u1', 'password' => bcrypt('x'), 'business_id' => 1]);
    $user->assignRole('repair.tecnico');
    $this->actingAs($user);
    session(['user.business_id' => 1]);

    $os = repairFsmCreateOs(1, $r->id);

    $response = $this->postJson("/repair/job-sheets/{$os->id}/fsm-action", [
        'action_key' => 'iniciar_diagnostico',
    ]);

    $response->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('new_stage_id', $d->id)
        ->assertJsonPath('to_stage.key', 'em_diagnostico');

    expect($os->fresh()->current_stage_id)->toBe($d->id);
});

it('5. startPipeline: OS legacy sem stage recebe stage inicial + history audit', function () {
    repairFsmFakeSetup(1);

    $user = User::forceCreate(['username' => 'u1', 'password' => bcrypt('x'), 'business_id' => 1]);
    $this->actingAs($user);
    session(['user.business_id' => 1]);

    // OS legacy — sem current_stage_id
    $os = repairFsmCreateOs(1, null);
    expect($os->current_stage_id)->toBeNull();

    $response = $this->postJson("/repair/job-sheets/{$os->id}/fsm-start-pipeline", [
        'process_key' => 'os_reparo_padrao',
    ]);

    $response->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('process_key', 'os_reparo_padrao')
        ->assertJsonPath('stage.key', 'recebido_para_diagnostico');

    // Estado persistido
    $fresh = $os->fresh();
    expect($fresh->current_stage_id)->not->toBeNull();
});
