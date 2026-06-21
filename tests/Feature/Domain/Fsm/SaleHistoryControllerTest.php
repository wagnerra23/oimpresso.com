<?php

declare(strict_types=1);

use App\Domain\Fsm\Models\SaleProcess;
use App\Domain\Fsm\Models\SaleProcessStage;
use App\Domain\Fsm\Models\SaleStageAction;
use App\Domain\Fsm\Models\SaleStageHistory;
use App\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Scopes\ScopeByBusiness;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * US-SELL-035 — Endpoint /api/sells/{id}/history.
 *
 * Cobre:
 *   - autenticação obrigatória (401 se não logado)
 *   - permissão sale.history.view (403 se ausente)
 *   - multi-tenant isolation (transaction biz=2 não vaza pra session biz=1)
 *   - shape do payload (items array com action/from_stage/to_stage/payload)
 *   - ordenação desc por executed_at
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
        foreach (['role_has_permissions', 'model_has_roles', 'model_has_permissions', 'roles', 'permissions', 'users'] as $tbl) {
            Schema::dropIfExists($tbl);
        }
    }
});

function historyFakeSetup(int $bizId): array
{
    $process = SaleProcess::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => $bizId, 'key' => 'venda_padrao', 'name' => 'Venda Padrão',
        'default_for_contact_type' => 'any', 'active' => true,
    ]);
    $s1 = SaleProcessStage::create([
        'process_id' => $process->id, 'key' => 's1', 'name' => 'S1',
        'sort_order' => 0, 'is_initial' => true, 'color' => 'gray',
    ]);
    $s2 = SaleProcessStage::create([
        'process_id' => $process->id, 'key' => 's2', 'name' => 'S2',
        'sort_order' => 1, 'color' => 'blue',
    ]);
    $action = SaleStageAction::create([
        'stage_id' => $s1->id, 'key' => 'avancar', 'label' => 'Avançar',
        'target_stage_id' => $s2->id,
    ]);

    return compact('process', 's1', 's2', 'action');
}

function historyLogged(int $bizId, int $txId, int $actionId, int $fromId, int $toId, int $userId): SaleStageHistory
{
    return SaleStageHistory::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => $bizId,
        'transaction_id' => $txId,
        'action_id' => $actionId,
        'from_stage_id' => $fromId,
        'to_stage_id' => $toId,
        'user_id' => $userId,
        'payload_snapshot' => ['motivo' => 'test'],
        'executed_at' => now(),
    ]);
}

// ─── Specs ────────────────────────────────────────────────────────────────

it('1. sem autenticação retorna 401', function () {
    $response = $this->getJson('/api/sells/1/history');
    $response->assertUnauthorized();
});

it('2. user autenticado sem permission sale.history.view retorna 403', function () {
    $user = User::forceCreate(['username' => 'u1', 'password' => bcrypt('x'), 'business_id' => 1]);
    $this->actingAs($user);
    session(['user.business_id' => 1]);

    $response = $this->getJson('/api/sells/1/history');
    $response->assertForbidden();
});

it('3. retorna timeline ordenada desc com shape canônico', function () {
    Permission::create(['name' => 'sale.history.view', 'guard_name' => 'web']);
    Role::create(['name' => 'vendas.gerente', 'guard_name' => 'web'])
        ->givePermissionTo('sale.history.view');

    $user = User::forceCreate(['username' => 'u1', 'password' => bcrypt('x'), 'business_id' => 1]);
    $user->assignRole('vendas.gerente');
    $this->actingAs($user);
    session(['user.business_id' => 1]);

    ['s1' => $s1, 's2' => $s2, 'action' => $action] = historyFakeSetup(1);

    $h1 = historyLogged(1, 5000, $action->id, $s1->id, $s2->id, $user->id);

    $response = $this->getJson('/api/sells/5000/history');

    $response->assertOk()
        ->assertJsonStructure([
            'transaction_id',
            'count',
            'items' => [
                '*' => [
                    'id', 'executed_at',
                    'user' => ['id', 'name'],
                    'action' => ['key', 'label', 'has_side_effect', 'has_event'],
                    'from_stage' => ['key', 'name', 'color'],
                    'to_stage' => ['key', 'name', 'color'],
                    'payload',
                ],
            ],
        ])
        ->assertJsonPath('count', 1)
        ->assertJsonPath('items.0.action.key', 'avancar')
        ->assertJsonPath('items.0.from_stage.key', 's1')
        ->assertJsonPath('items.0.to_stage.key', 's2');
});

it('4. multi-tenant: history de biz=2 não aparece em session biz=1', function () {
    Permission::create(['name' => 'sale.history.view', 'guard_name' => 'web']);
    Role::create(['name' => 'vendas.gerente', 'guard_name' => 'web'])
        ->givePermissionTo('sale.history.view');

    $user = User::forceCreate(['username' => 'u1', 'password' => bcrypt('x'), 'business_id' => 1]);
    $user->assignRole('vendas.gerente');
    $this->actingAs($user);
    session(['user.business_id' => 1]);

    ['s1' => $s1, 's2' => $s2, 'action' => $action] = historyFakeSetup(2); // biz=2

    historyLogged(2, 5000, $action->id, $s1->id, $s2->id, $user->id);

    $response = $this->getJson('/api/sells/5000/history');

    $response->assertOk()
        ->assertJsonPath('count', 0)
        ->assertJsonPath('items', []);
});
