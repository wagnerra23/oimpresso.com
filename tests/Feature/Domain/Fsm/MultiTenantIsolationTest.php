<?php

declare(strict_types=1);

use App\Domain\Fsm\Models\SaleProcess;
use App\Domain\Fsm\Models\SaleProcessStage;
use App\Domain\Fsm\Models\SaleStageAction;
use App\Domain\Fsm\Models\SaleStageActionRole;
use App\Domain\Fsm\Models\SaleStageHistory;
use App\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Scopes\ScopeByBusiness;
use Spatie\Permission\PermissionRegistrar;

/**
 * US-SELL-011 — Multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093) aplicado às tabelas FSM.
 *
 * Default biz=1 (Wagner WR2 SC); cross-tenant adversário = biz=99 (improvável existir).
 * NUNCA biz=4 — auto-mem feedback_test_business_id_1_nunca_4 + tests/Unit/BusinessIdGuardTest.
 */

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
        foreach (['role_has_permissions', 'model_has_roles', 'model_has_permissions', 'roles', 'permissions', 'users'] as $tbl) {
            Schema::dropIfExists($tbl);
        }
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function fsmCriarUser(int $bizId): User
{
    return User::forceCreate([
        'username' => 'u_biz' . $bizId . '_' . uniqid(),
        'password' => bcrypt('x'),
        'business_id' => $bizId,
    ]);
}

function fsmCriarProcess(int $bizId, string $key = 'venda_padrao'): SaleProcess
{
    return SaleProcess::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => $bizId,
        'key' => $key,
        'name' => 'Processo biz ' . $bizId,
        'default_for_contact_type' => 'any',
        'active' => true,
    ]);
}

it('SaleProcess: usuário biz=1 não vê processo biz=99', function () {
    $procA = fsmCriarProcess(1, 'pa');
    $procB = fsmCriarProcess(99, 'pb');

    $this->actingAs(fsmCriarUser(1));
    session(['user.business_id' => 1]);

    $ids = SaleProcess::all()->pluck('id');

    expect($ids)->toContain($procA->id)->not->toContain($procB->id);
});

it('SaleProcess: usuário biz=99 não vê processo biz=1', function () {
    $procA = fsmCriarProcess(1, 'pa2');
    $procB = fsmCriarProcess(99, 'pb2');

    $this->actingAs(fsmCriarUser(99));
    session(['user.business_id' => 99]);

    $ids = SaleProcess::all()->pluck('id');

    expect($ids)->toContain($procB->id)->not->toContain($procA->id);
});

it('SaleStageHistory respeita scope multi-tenant por business_id', function () {
    $proc1 = fsmCriarProcess(1, 'ph1');
    $proc99 = fsmCriarProcess(99, 'ph99');

    SaleStageHistory::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'transaction_id' => 100,
        'action_id' => 1,
    ]);
    SaleStageHistory::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 99,
        'transaction_id' => 200,
        'action_id' => 1,
    ]);

    $this->actingAs(fsmCriarUser(1));
    session(['user.business_id' => 1]);

    $rows = SaleStageHistory::all();

    expect($rows)->toHaveCount(1);
    expect($rows->first()->business_id)->toBe(1);
});

it('withoutGlobalScope é escape hatch consciente (superadmin/CLI)', function () {
    fsmCriarProcess(1, 'esc1');
    fsmCriarProcess(99, 'esc99');

    $this->actingAs(fsmCriarUser(1));
    session(['user.business_id' => 1]);

    $todos = SaleProcess::withoutGlobalScope(ScopeByBusiness::class)->get();

    expect($todos)->toHaveCount(2);
});

it('cadeia process → stages → actions → roles navegável via relacionamentos', function () {
    $proc = fsmCriarProcess(1, 'cadeia');

    $stage = SaleProcessStage::create([
        'process_id' => $proc->id,
        'key' => 'rascunho',
        'name' => 'Rascunho',
        'sort_order' => 0,
        'is_initial' => true,
    ]);

    $action = SaleStageAction::create([
        'stage_id' => $stage->id,
        'key' => 'faturar',
        'label' => 'Faturar',
        'requires_confirmation' => true,
    ]);

    SaleStageActionRole::create([
        'action_id' => $action->id,
        'role_name' => 'caixa',
    ]);

    $this->actingAs(fsmCriarUser(1));
    session(['user.business_id' => 1]);

    $reload = SaleProcess::with('stages.actions.roles')->find($proc->id);

    expect($reload->stages)->toHaveCount(1);
    expect($reload->stages->first()->actions)->toHaveCount(1);
    expect($reload->stages->first()->actions->first()->roles)->toHaveCount(1);
    expect($reload->stages->first()->actions->first()->roles->first()->role_name)->toBe('caixa');
    expect($reload->initialStage()->key)->toBe('rascunho');
});
