<?php

declare(strict_types=1);

use App\Domain\Fsm\Models\SaleProcess;
use App\Domain\Fsm\Models\SaleProcessStage;
use App\Domain\Fsm\Models\SaleStageAction;
use App\Domain\Fsm\Models\SaleStageActionRole;
use App\Domain\Fsm\Models\SaleStageHistory;
use App\Domain\Fsm\Services\ExecuteStageActionService;
use App\Domain\Fsm\Support\FsmAuthorizationFlag;
use App\Http\Controllers\ServiceOrderFsmActionController;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\OficinaAuto\Database\Seeders\OficinaAutoFsmSeeder;
use Modules\OficinaAuto\Entities\ServiceOrder;
use Modules\OficinaAuto\Entities\Vehicle;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

// Tests\TestCase já é aplicado globalmente em tests/Pest.php (uses(TestCase::class)->in('Feature')). NÃO redeclarar aqui — Pest 4 lança TestCaseAlreadyInUse.

/**
 * ServiceOrderFsmActionController — Wave 7-A wire-up FSM (espelha SaleFsmActionController).
 *
 * Cobertura:
 *  - actions() retorna actions disponíveis pra OS locação stage 'disponivel'
 *  - actions() retorna actions disponíveis pra OS manutenção stage 'em_servico'
 *  - actions() retorna in_pipeline=false pra OS sem current_stage_id
 *  - execute() action 'recolher' move stage + cria history entry
 *  - execute() permission denied retorna 403
 *  - execute() action invalid retorna 422
 *  - startPipeline() cria entry sale_stage_history "Pipeline iniciado"
 *  - startPipeline() recusa OS já em pipeline
 *  - Cross-tenant biz=99 NÃO acessa OS biz=1 (Tier 0 ADR 0093)
 *
 * Convenção: biz=1 default (Wagner WR2), biz=99 cross-tenant fictício (ADR 0101).
 *
 * @see app/Http/Controllers/ServiceOrderFsmActionController.php
 * @see app/Http/Controllers/SaleFsmActionController.php (pattern canônico)
 * @see Modules/OficinaAuto/Database/Seeders/OficinaAutoFsmSeeder.php
 * @see memory/decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md
 */

const BIZ_WAGNER_SOFAC = 1;
const BIZ_FICTICIO_SOFAC = 99;

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema FSM requer MySQL UltimatePOS (ADR 0101)');
    }

    if (! Schema::hasTable('service_orders')
        || ! Schema::hasTable('vehicles')
        || ! Schema::hasTable('sale_processes')
        || ! Schema::hasTable('sale_process_stages')
        || ! Schema::hasTable('sale_stage_actions')
        || ! Schema::hasTable('sale_stage_history')) {
        $this->markTestSkipped('Tabelas FSM/OficinaAuto ausentes — rode migrate canônico primeiro');
    }

    if (! Schema::hasColumn('service_orders', 'current_stage_id')) {
        $this->markTestSkipped(
            'Coluna service_orders.current_stage_id ausente — migration FSM dedicated pendente. ' .
            'Wire-up Wave 7-A precisa migration "add_fsm_columns_to_service_orders" pra ativar.'
        );
    }

    if (! Schema::hasTable('roles') || ! Schema::hasTable('permissions')) {
        $this->markTestSkipped('Spatie Permission tables ausentes — rode migrate primeiro');
    }
});

/**
 * Setup processo FSM + permissions pra business + retorna user com Spatie roles.
 *
 * Idempotente: usa OficinaAutoFsmSeeder (firstOrCreate em tudo).
 */
function setupSofacBusiness(int $businessId): User
{
    // Seed processos FSM (cacamba_locacao + cacamba_manutencao)
    (new OficinaAutoFsmSeeder())->runForBusiness($businessId);

    // Cria/garante permissions Spatie pra OficinaAuto (idempotente)
    Permission::firstOrCreate(['name' => 'oficinaauto.service_order.view',   'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'oficinaauto.service_order.update', 'guard_name' => 'web']);

    // Cria user mínimo + atribui roles "mecanico#{biz}" e "gerente#{biz}" + permissions
    $user = User::create([
        'business_id' => $businessId,
        'first_name'  => 'Test',
        'surname'     => 'SOFAC',
        'username'    => 'test_sofac_'.$businessId.'_'.uniqid(),
        'email'       => 'sofac_'.$businessId.'_'.uniqid().'@test.local',
        'password'    => bcrypt('test12345'),
        'language'    => 'pt_BR',
    ]);

    $hasBusinessIdColumn = Schema::hasColumn('roles', 'business_id');
    $mecanicoRoleName = $hasBusinessIdColumn ? "mecanico#{$businessId}" : 'mecanico';
    $gerenteRoleName  = $hasBusinessIdColumn ? "gerente#{$businessId}"  : 'gerente';

    $mecanicoRole = Role::where('name', $mecanicoRoleName)->where('guard_name', 'web')->first();
    $gerenteRole  = Role::where('name', $gerenteRoleName)->where('guard_name', 'web')->first();

    if ($mecanicoRole) { $user->assignRole($mecanicoRole); }
    if ($gerenteRole)  { $user->assignRole($gerenteRole); }

    $user->givePermissionTo(['oficinaauto.service_order.view', 'oficinaauto.service_order.update']);

    return $user;
}

function makeVehicleSofac(string $plate, int $businessId): Vehicle
{
    return Vehicle::withoutGlobalScopes()->create([ // SUPERADMIN: setup teste
        'business_id'  => $businessId,
        'plate'        => $plate,
        'vehicle_type' => 'cacamba_estacionaria',
    ]);
}

function makeOrderSofac(int $businessId, int $vehicleId, string $orderType, ?int $stageId = null): ServiceOrder
{
    return ServiceOrder::withoutGlobalScopes()->create([ // SUPERADMIN: setup teste
        'business_id'      => $businessId,
        'vehicle_id'       => $vehicleId,
        'order_type'       => $orderType,
        'status'           => 'aberta',
        'entered_at'       => now(),
        'current_stage_id' => $stageId,
    ]);
}

function getStageSofac(int $businessId, string $processKey, string $stageKey): SaleProcessStage
{
    $process = SaleProcess::withoutGlobalScope(ScopeByBusiness::class)
        ->where('business_id', $businessId)
        ->where('key', $processKey)
        ->firstOrFail();

    return SaleProcessStage::where('process_id', $process->id)
        ->where('key', $stageKey)
        ->firstOrFail();
}

function cleanupSofac(int $businessId, string $platePrefix): void
{
    $vehicleIds = Vehicle::withoutGlobalScopes()
        ->where('plate', 'like', $platePrefix . '%')
        ->pluck('id')->toArray();

    if (! empty($vehicleIds)) {
        $orderIds = ServiceOrder::withoutGlobalScopes()
            ->whereIn('vehicle_id', $vehicleIds)
            ->pluck('id')->toArray();

        SaleStageHistory::withoutGlobalScope(ScopeByBusiness::class)
            ->where('business_id', $businessId)
            ->whereIn('transaction_id', $orderIds)
            ->delete();

        ServiceOrder::withoutGlobalScopes()
            ->whereIn('id', $orderIds)
            ->forceDelete();

        Vehicle::withoutGlobalScopes()->whereIn('id', $vehicleIds)->forceDelete();
    }

    User::where('business_id', $businessId)
        ->where('username', 'like', 'test_sofac_'.$businessId.'%')
        ->delete();
}

it('actions() retorna actions disponíveis pra OS locação stage disponivel', function () {
    $user = setupSofacBusiness(BIZ_WAGNER_SOFAC);
    $this->actingAs($user);
    session(['user.business_id' => BIZ_WAGNER_SOFAC]);

    $stage = getStageSofac(BIZ_WAGNER_SOFAC, 'cacamba_locacao', 'disponivel');
    $vehicle = makeVehicleSofac('SOFAC-A1', BIZ_WAGNER_SOFAC);
    $order = makeOrderSofac(BIZ_WAGNER_SOFAC, $vehicle->id, 'manutencao', $stage->id);

    $controller = app(ServiceOrderFsmActionController::class);
    $response = $controller->actions($order);
    $data = $response->getData(true);

    expect($data['service_order_id'])->toBe($order->id);
    // ADR 0265: 'locacao' não existe no mapa — order_type='manutencao' resolve
    // cacamba_manutencao (a OS está propositalmente num stage do processo legado,
    // estado de órfã; actions() lista pelas actions do STAGE atual mesmo assim).
    expect($data['process_key'])->toBe('cacamba_manutencao');
    expect($data['in_pipeline'])->toBeTrue();
    expect($data['current_stage']['key'])->toBe('disponivel');
    expect(collect($data['actions'])->pluck('key')->all())
        ->toContain('iniciar_locacao')
        ->toContain('enviar_manutencao');
})->afterEach(function () {
    cleanupSofac(BIZ_WAGNER_SOFAC, 'SOFAC-A');
});

it('actions() retorna actions disponíveis pra OS manutenção stage em_servico', function () {
    $user = setupSofacBusiness(BIZ_WAGNER_SOFAC);
    $this->actingAs($user);
    session(['user.business_id' => BIZ_WAGNER_SOFAC]);

    $stage = getStageSofac(BIZ_WAGNER_SOFAC, 'cacamba_manutencao', 'em_servico');
    $vehicle = makeVehicleSofac('SOFAC-B1', BIZ_WAGNER_SOFAC);
    $order = makeOrderSofac(BIZ_WAGNER_SOFAC, $vehicle->id, 'manutencao', $stage->id);

    $controller = app(ServiceOrderFsmActionController::class);
    $response = $controller->actions($order);
    $data = $response->getData(true);

    expect($data['process_key'])->toBe('cacamba_manutencao');
    expect($data['in_pipeline'])->toBeTrue();
    expect($data['current_stage']['key'])->toBe('em_servico');
    expect(collect($data['actions'])->pluck('key')->all())
        ->toContain('concluir')
        ->toContain('cancelar');
})->afterEach(function () {
    cleanupSofac(BIZ_WAGNER_SOFAC, 'SOFAC-B');
});

it('actions() retorna in_pipeline=false pra OS sem current_stage_id', function () {
    $user = setupSofacBusiness(BIZ_WAGNER_SOFAC);
    $this->actingAs($user);
    session(['user.business_id' => BIZ_WAGNER_SOFAC]);

    $vehicle = makeVehicleSofac('SOFAC-C1', BIZ_WAGNER_SOFAC);
    $order = makeOrderSofac(BIZ_WAGNER_SOFAC, $vehicle->id, 'manutencao', null);

    $controller = app(ServiceOrderFsmActionController::class);
    $response = $controller->actions($order);
    $data = $response->getData(true);

    expect($data['in_pipeline'])->toBeFalse();
    expect($data['current_stage'])->toBeNull();
    expect($data['actions'])->toBe([]);
    // ADR 0265: order_type='manutencao' → cacamba_manutencao (mapa ServiceOrderPipelineStarter)
    expect($data['process_key'])->toBe('cacamba_manutencao');
})->afterEach(function () {
    cleanupSofac(BIZ_WAGNER_SOFAC, 'SOFAC-C');
});

it('execute() action recolher move stage + cria history entry', function () {
    $user = setupSofacBusiness(BIZ_WAGNER_SOFAC);
    $this->actingAs($user);
    session(['user.business_id' => BIZ_WAGNER_SOFAC]);

    $stageLocada = getStageSofac(BIZ_WAGNER_SOFAC, 'cacamba_locacao', 'locada');
    $stageRecolhida = getStageSofac(BIZ_WAGNER_SOFAC, 'cacamba_locacao', 'recolhida');

    $vehicle = makeVehicleSofac('SOFAC-D1', BIZ_WAGNER_SOFAC);
    $order = makeOrderSofac(BIZ_WAGNER_SOFAC, $vehicle->id, 'manutencao', $stageLocada->id);

    $request = Request::create('/oficina-auto/service-orders/'.$order->id.'/fsm/execute', 'POST', [
        'action_key' => 'recolher',
    ]);

    $controller = app(ServiceOrderFsmActionController::class);
    $service = app(ExecuteStageActionService::class);
    $response = $controller->execute($request, $order, $service);
    $data = $response->getData(true);

    expect($response->getStatusCode())->toBe(200);
    expect($data['ok'])->toBeTrue();
    expect($data['new_stage_id'])->toBe($stageRecolhida->id);
    expect($data['to_stage']['key'])->toBe('recolhida');

    // History row foi criada
    $history = SaleStageHistory::withoutGlobalScope(ScopeByBusiness::class)
        ->where('id', $data['history_id'])
        ->first();
    expect($history)->not->toBeNull();
    expect($history->to_stage_id)->toBe($stageRecolhida->id);
    expect($history->business_id)->toBe(BIZ_WAGNER_SOFAC);
})->afterEach(function () {
    cleanupSofac(BIZ_WAGNER_SOFAC, 'SOFAC-D');
});

it('execute() permission denied retorna 403', function () {
    // User sem permission oficinaauto.service_order.update
    $user = User::create([
        'business_id' => BIZ_WAGNER_SOFAC,
        'first_name'  => 'NoPerm',
        'surname'     => 'SOFAC',
        'username'    => 'noperm_sofac_'.uniqid(),
        'email'       => 'noperm_sofac_'.uniqid().'@test.local',
        'password'    => bcrypt('test12345'),
        'language'    => 'pt_BR',
    ]);

    (new OficinaAutoFsmSeeder())->runForBusiness(BIZ_WAGNER_SOFAC);

    $this->actingAs($user);
    session(['user.business_id' => BIZ_WAGNER_SOFAC]);

    $stageLocada = getStageSofac(BIZ_WAGNER_SOFAC, 'cacamba_locacao', 'locada');
    $vehicle = makeVehicleSofac('SOFAC-E1', BIZ_WAGNER_SOFAC);
    $order = makeOrderSofac(BIZ_WAGNER_SOFAC, $vehicle->id, 'manutencao', $stageLocada->id);

    $request = Request::create('/oficina-auto/service-orders/'.$order->id.'/fsm/execute', 'POST', [
        'action_key' => 'recolher',
    ]);

    $controller = app(ServiceOrderFsmActionController::class);
    $service = app(ExecuteStageActionService::class);

    try {
        $controller->execute($request, $order, $service);
        $this->fail('Esperava abort 403, mas request passou');
    } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
        expect($e->getStatusCode())->toBe(403);
    }

    User::where('id', $user->id)->delete();
})->afterEach(function () {
    cleanupSofac(BIZ_WAGNER_SOFAC, 'SOFAC-E');
});

it('execute() action invalida retorna 422', function () {
    $user = setupSofacBusiness(BIZ_WAGNER_SOFAC);
    $this->actingAs($user);
    session(['user.business_id' => BIZ_WAGNER_SOFAC]);

    $stageDisponivel = getStageSofac(BIZ_WAGNER_SOFAC, 'cacamba_locacao', 'disponivel');
    $vehicle = makeVehicleSofac('SOFAC-F1', BIZ_WAGNER_SOFAC);
    $order = makeOrderSofac(BIZ_WAGNER_SOFAC, $vehicle->id, 'manutencao', $stageDisponivel->id);

    // 'concluir' não existe pro stage 'disponivel' do processo cacamba_locacao
    $request = Request::create('/oficina-auto/service-orders/'.$order->id.'/fsm/execute', 'POST', [
        'action_key' => 'action_inexistente_xpto',
    ]);

    $controller = app(ServiceOrderFsmActionController::class);
    $service = app(ExecuteStageActionService::class);
    $response = $controller->execute($request, $order, $service);

    expect($response->getStatusCode())->toBe(422);
    expect($response->getData(true))->toHaveKey('error');
})->afterEach(function () {
    cleanupSofac(BIZ_WAGNER_SOFAC, 'SOFAC-F');
});

it('startPipeline() cria entry sale_stage_history Pipeline iniciado', function () {
    $user = setupSofacBusiness(BIZ_WAGNER_SOFAC);
    $this->actingAs($user);
    session(['user.business_id' => BIZ_WAGNER_SOFAC]);

    $vehicle = makeVehicleSofac('SOFAC-G1', BIZ_WAGNER_SOFAC);
    $order = makeOrderSofac(BIZ_WAGNER_SOFAC, $vehicle->id, 'manutencao', null);

    $request = Request::create('/oficina-auto/service-orders/'.$order->id.'/fsm/start-pipeline', 'POST');

    $controller = app(ServiceOrderFsmActionController::class);
    $response = $controller->startPipeline($request, $order);
    $data = $response->getData(true);

    expect($response->getStatusCode())->toBe(200);
    expect($data['ok'])->toBeTrue();
    // ADR 0265: order_type='manutencao' → cacamba_manutencao (stage inicial 'aberta')
    expect($data['process_key'])->toBe('cacamba_manutencao');
    expect($data['stage']['key'])->toBe('aberta');

    // OS deve ter current_stage_id setado
    $orderFresh = ServiceOrder::withoutGlobalScopes()->find($order->id);
    expect($orderFresh->current_stage_id)->toBe($data['new_stage_id']);

    // History "Pipeline iniciado" deve existir
    $history = SaleStageHistory::withoutGlobalScope(ScopeByBusiness::class)
        ->where('business_id', BIZ_WAGNER_SOFAC)
        ->where('transaction_id', $order->id)
        ->whereNull('action_id')
        ->first();
    expect($history)->not->toBeNull();
    expect($history->payload_snapshot['pipeline_started'] ?? false)->toBeTrue();
    expect($history->payload_snapshot['process_key'] ?? null)->toBe('cacamba_manutencao');
})->afterEach(function () {
    cleanupSofac(BIZ_WAGNER_SOFAC, 'SOFAC-G');
});

it('startPipeline() recusa OS já em pipeline (422)', function () {
    $user = setupSofacBusiness(BIZ_WAGNER_SOFAC);
    $this->actingAs($user);
    session(['user.business_id' => BIZ_WAGNER_SOFAC]);

    $stageDisponivel = getStageSofac(BIZ_WAGNER_SOFAC, 'cacamba_locacao', 'disponivel');
    $vehicle = makeVehicleSofac('SOFAC-H1', BIZ_WAGNER_SOFAC);
    $order = makeOrderSofac(BIZ_WAGNER_SOFAC, $vehicle->id, 'manutencao', $stageDisponivel->id);

    $request = Request::create('/oficina-auto/service-orders/'.$order->id.'/fsm/start-pipeline', 'POST');

    $controller = app(ServiceOrderFsmActionController::class);
    $response = $controller->startPipeline($request, $order);

    expect($response->getStatusCode())->toBe(422);
    expect($response->getData(true)['error'])->toContain('já está em pipeline');
})->afterEach(function () {
    cleanupSofac(BIZ_WAGNER_SOFAC, 'SOFAC-H');
});

it('cross-tenant biz=99 NÃO acessa OS biz=1 (Tier 0 ADR 0093)', function () {
    // Setup biz=1 com OS
    setupSofacBusiness(BIZ_WAGNER_SOFAC);
    $stage = getStageSofac(BIZ_WAGNER_SOFAC, 'cacamba_locacao', 'disponivel');
    $vehicle = makeVehicleSofac('SOFAC-I1', BIZ_WAGNER_SOFAC);
    $orderBiz1 = makeOrderSofac(BIZ_WAGNER_SOFAC, $vehicle->id, 'manutencao', $stage->id);

    // Switch pra biz=99
    $userBiz99 = setupSofacBusiness(BIZ_FICTICIO_SOFAC);
    $this->actingAs($userBiz99);
    session(['user.business_id' => BIZ_FICTICIO_SOFAC]);

    // Tenta carregar OS de biz=1 sob session biz=99 — global scope filtra
    $resultado = ServiceOrder::where('id', $orderBiz1->id)->get();
    expect($resultado)->toHaveCount(0);

    // Mesmo se driblar global scope (SUPERADMIN), Service rejeita cross-tenant
    $orderForcedLoad = ServiceOrder::withoutGlobalScopes()->find($orderBiz1->id);
    expect($orderForcedLoad)->not->toBeNull();
    expect($orderForcedLoad->business_id)->toBe(BIZ_WAGNER_SOFAC);

    // Service::execute valida subject.business_id == process.business_id
    // Como biz=99 não tem o mesmo process_id+stage_id, vai cair em InvalidActionForCurrentStageException
    $service = app(ExecuteStageActionService::class);
    try {
        $service->execute($orderForcedLoad, 'iniciar_locacao', $userBiz99, []);
        $this->fail('Esperava bloqueio cross-tenant, mas service executou');
    } catch (\App\Domain\Fsm\Exceptions\UnauthorizedActionException|\App\Domain\Fsm\Exceptions\InvalidActionForCurrentStageException $e) {
        // OK — qualquer um dos dois é fail-secure aceitável
        expect(true)->toBeTrue();
    }
})->afterEach(function () {
    cleanupSofac(BIZ_WAGNER_SOFAC, 'SOFAC-I');
    cleanupSofac(BIZ_FICTICIO_SOFAC, 'SOFAC-I');
});
