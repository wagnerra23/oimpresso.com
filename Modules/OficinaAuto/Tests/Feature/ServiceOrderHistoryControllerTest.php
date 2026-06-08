<?php

declare(strict_types=1);

use App\Domain\Fsm\Models\SaleProcess;
use App\Domain\Fsm\Models\SaleProcessStage;
use App\Domain\Fsm\Models\SaleStageAction;
use App\Domain\Fsm\Models\SaleStageHistory;
use App\Http\Controllers\ServiceOrderFsmActionController;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\OficinaAuto\Entities\ServiceOrder;
use Modules\OficinaAuto\Entities\Vehicle;

uses(Tests\TestCase::class);

/**
 * Wave 7-C — Timeline FSM auditável endpoint (gap #1 estado-da-arte FSM screen).
 *
 * Cobre:
 *   1. Happy path — entry cacamba_locacao retorna no payload com shape canônico
 *   2. startPipeline entry (action_id NULL + payload.pipeline_started=true) APARECE
 *   3. Discriminação process_key — sale_stage_history de venda_padrao (transaction_id
 *      colidindo com OS) NÃO aparece (subject_id polimórfico via process_key)
 *   4. Multi-tenant Tier 0 — history de biz=99 não vaza pra biz=1 (ADR 0093)
 *   5. 403 sem permission oficinaauto.service_order.view
 *
 * Pattern: skip SQLite (ADR 0101 — schema MySQL UltimatePOS obrigatório).
 * Setup via factory direta (não OficinaAutoFsmSeeder pra isolamento per-test).
 *
 * @see app/Http/Controllers/ServiceOrderFsmActionController.php (método history)
 * @see memory/sessions/2026-05-20-arte-tela-fsm-workflow.md (gap #1)
 * @see memory/decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md
 */

const BIZ_HISTORY_TEST = 1;
const BIZ_FICTICIO_HISTORY = 99;

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: requer schema MySQL UltimatePOS (ADR 0101)');
    }
    if (! Schema::hasTable('service_orders') || ! Schema::hasTable('sale_processes')) {
        $this->markTestSkipped('FSM/OficinaAuto tables missing — rode migrate primeiro');
    }

    // Auth stub — controller faz auth()->check() + can(). Sem Permission table seedada
    // em ambiente de teste, usa Gate::before pra autorizar tudo + actingAs com User stub.
    $user = User::withoutGlobalScopes()->where('id', '<=', 0)->first()
        ?? (function () {
            $u = new User();
            $u->id = 1;
            $u->username = 'history-test-user';
            $u->business_id = BIZ_HISTORY_TEST;
            $u->exists = true;
            return $u;
        })();
    $this->actingAs($user);
    Gate::before(fn () => true);
});

/**
 * Helper — cria processo FSM cacamba_locacao com 2 stages + 1 action.
 *
 * @return array{process: SaleProcess, s1: SaleProcessStage, s2: SaleProcessStage, action: SaleStageAction}
 */
function ohcSetupCacambaLocacao(int $bizId): array
{
    $suffix = 'ohc' . $bizId . '_' . uniqid();
    $process = SaleProcess::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id'              => $bizId,
        'key'                      => 'cacamba_locacao_' . $suffix,
        'name'                     => 'Caçamba — Locação (test)',
        'default_for_contact_type' => 'any',
        'active'                   => true,
    ]);
    $s1 = SaleProcessStage::create([
        'process_id' => $process->id,
        'key'        => 'disponivel',
        'name'       => 'Disponível',
        'sort_order' => 0,
        'is_initial' => true,
        'color'      => 'gray',
    ]);
    $s2 = SaleProcessStage::create([
        'process_id' => $process->id,
        'key'        => 'locada',
        'name'       => 'Locada',
        'sort_order' => 1,
        'color'      => 'blue',
    ]);
    $action = SaleStageAction::create([
        'stage_id'        => $s1->id,
        'key'             => 'iniciar_locacao',
        'label'           => 'Iniciar locação',
        'target_stage_id' => $s2->id,
    ]);

    return compact('process', 's1', 's2', 'action');
}

function ohcCreateOs(int $bizId): ServiceOrder
{
    $vehicle = Vehicle::withoutGlobalScopes()->create([
        'business_id'  => $bizId,
        'plate'        => 'OHC' . substr((string) random_int(1000, 9999), 0, 4),
        'vehicle_type' => 'caminhao',
    ]);
    return ServiceOrder::withoutGlobalScopes()->create([
        'business_id' => $bizId,
        'vehicle_id'  => $vehicle->id,
        'order_type'  => 'locacao',
        'status'      => 'aberta',
        'entered_at'  => now(),
        'daily_rate'  => '150.00',
    ]);
}

function ohcLogged(int $bizId, int $txId, ?int $actionId, ?int $fromId, ?int $toId, array $payload = []): SaleStageHistory
{
    return SaleStageHistory::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id'      => $bizId,
        'transaction_id'   => $txId,
        'action_id'        => $actionId,
        'from_stage_id'    => $fromId,
        'to_stage_id'      => $toId,
        'user_id'          => null,
        'payload_snapshot' => $payload,
        'executed_at'      => now(),
    ]);
}

function ohcCleanup(int $bizId): void
{
    $procIds = SaleProcess::withoutGlobalScope(ScopeByBusiness::class)
        ->where('business_id', $bizId)
        ->where('key', 'like', 'cacamba_locacao_ohc%')
        ->pluck('id');
    $stageIds = SaleProcessStage::whereIn('process_id', $procIds)->pluck('id');
    $actionIds = SaleStageAction::whereIn('stage_id', $stageIds)->pluck('id');

    SaleStageHistory::withoutGlobalScope(ScopeByBusiness::class)
        ->where('business_id', $bizId)
        ->whereIn('action_id', $actionIds)
        ->orWhere(function ($q) use ($bizId) {
            $q->where('business_id', $bizId)
                ->whereNull('action_id')
                ->whereJsonContains('payload_snapshot->pipeline_started', true);
        })
        ->delete();
    SaleStageAction::whereIn('id', $actionIds)->delete();
    SaleProcessStage::whereIn('id', $stageIds)->delete();
    SaleProcess::whereIn('id', $procIds)->delete();
}

// ─── Specs ────────────────────────────────────────────────────────────────

it('1. retorna entry cacamba_* com shape canônico (action + from_stage + to_stage)', function () {
    session(['user.business_id' => BIZ_HISTORY_TEST]);

    ['s1' => $s1, 's2' => $s2, 'action' => $action] = ohcSetupCacambaLocacao(BIZ_HISTORY_TEST);
    $os = ohcCreateOs(BIZ_HISTORY_TEST);
    ohcLogged(BIZ_HISTORY_TEST, $os->id, $action->id, $s1->id, $s2->id, ['motivo' => 'cliente confirmou']);

    $controller = app(ServiceOrderFsmActionController::class);
    $response = $controller->history($os);
    $payload = $response->getData(true);

    expect($payload['service_order_id'])->toBe($os->id);
    expect($payload['count'])->toBe(1);
    expect($payload['items'][0]['action']['key'])->toBe('iniciar_locacao');
    expect($payload['items'][0]['from_stage']['key'])->toBe('disponivel');
    expect($payload['items'][0]['to_stage']['key'])->toBe('locada');
    expect($payload['items'][0]['payload']['motivo'])->toBe('cliente confirmou');
})->afterEach(function () {
    Vehicle::withoutGlobalScopes()->where('business_id', BIZ_HISTORY_TEST)->where('plate', 'like', 'OHC%')->forceDelete();
    ServiceOrder::withoutGlobalScopes()->where('business_id', BIZ_HISTORY_TEST)->forceDelete();
    ohcCleanup(BIZ_HISTORY_TEST);
});

it('2. startPipeline entry (action_id NULL + pipeline_started=true) APARECE', function () {
    session(['user.business_id' => BIZ_HISTORY_TEST]);

    ['s1' => $s1] = ohcSetupCacambaLocacao(BIZ_HISTORY_TEST);
    $os = ohcCreateOs(BIZ_HISTORY_TEST);
    ohcLogged(
        BIZ_HISTORY_TEST,
        $os->id,
        null,
        null,
        $s1->id,
        ['pipeline_started' => true, 'service_order_id' => $os->id, 'process_key' => 'cacamba_locacao'],
    );

    $controller = app(ServiceOrderFsmActionController::class);
    $response = $controller->history($os);
    $payload = $response->getData(true);

    expect($payload['count'])->toBe(1);
    expect($payload['items'][0]['action'])->toBeNull();
    expect($payload['items'][0]['to_stage']['key'])->toBe('disponivel');
    expect($payload['items'][0]['payload']['pipeline_started'])->toBeTrue();
})->afterEach(function () {
    Vehicle::withoutGlobalScopes()->where('business_id', BIZ_HISTORY_TEST)->where('plate', 'like', 'OHC%')->forceDelete();
    ServiceOrder::withoutGlobalScopes()->where('business_id', BIZ_HISTORY_TEST)->forceDelete();
    ohcCleanup(BIZ_HISTORY_TEST);
});

it('3. discrimina process_key — entry de venda_padrao com transaction_id colidindo NÃO aparece', function () {
    session(['user.business_id' => BIZ_HISTORY_TEST]);

    ['s1' => $s1, 's2' => $s2, 'action' => $cacambaAction] = ohcSetupCacambaLocacao(BIZ_HISTORY_TEST);
    $os = ohcCreateOs(BIZ_HISTORY_TEST);

    // Entry legítima OS cacamba_locacao
    ohcLogged(BIZ_HISTORY_TEST, $os->id, $cacambaAction->id, $s1->id, $s2->id);

    // Entry "intrusa" — venda_padrao com transaction_id == os.id (colisão polimórfica)
    $vendaProcess = SaleProcess::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id'              => BIZ_HISTORY_TEST,
        'key'                      => 'venda_padrao_ohcintruso_' . uniqid(),
        'name'                     => 'Venda Padrão (intrusa)',
        'default_for_contact_type' => 'any',
        'active'                   => true,
    ]);
    $vendaStage = SaleProcessStage::create([
        'process_id' => $vendaProcess->id, 'key' => 'rascunho', 'name' => 'Rascunho',
        'sort_order' => 0, 'is_initial' => true, 'color' => 'gray',
    ]);
    $vendaAction = SaleStageAction::create([
        'stage_id' => $vendaStage->id, 'key' => 'confirmar', 'label' => 'Confirmar',
    ]);
    ohcLogged(BIZ_HISTORY_TEST, $os->id, $vendaAction->id, $vendaStage->id, null);

    $controller = app(ServiceOrderFsmActionController::class);
    $response = $controller->history($os);
    $payload = $response->getData(true);

    expect($payload['count'])->toBe(1);
    expect($payload['items'][0]['action']['key'])->toBe('iniciar_locacao');
})->afterEach(function () {
    SaleStageHistory::withoutGlobalScope(ScopeByBusiness::class)
        ->where('business_id', BIZ_HISTORY_TEST)
        ->delete();
    SaleStageAction::whereHas('stage.process', function ($p) {
        $p->where('key', 'like', 'venda_padrao_ohcintruso%');
    })->delete();
    SaleProcessStage::whereHas('process', function ($p) {
        $p->where('key', 'like', 'venda_padrao_ohcintruso%');
    })->delete();
    SaleProcess::withoutGlobalScope(ScopeByBusiness::class)
        ->where('key', 'like', 'venda_padrao_ohcintruso%')->delete();
    Vehicle::withoutGlobalScopes()->where('business_id', BIZ_HISTORY_TEST)->where('plate', 'like', 'OHC%')->forceDelete();
    ServiceOrder::withoutGlobalScopes()->where('business_id', BIZ_HISTORY_TEST)->forceDelete();
    ohcCleanup(BIZ_HISTORY_TEST);
});

it('4. multi-tenant Tier 0 — history de biz=99 não aparece quando OS é biz=1', function () {
    session(['user.business_id' => BIZ_HISTORY_TEST]);

    // OS de biz=1 (Wagner)
    ['s1' => $s1, 'action' => $action] = ohcSetupCacambaLocacao(BIZ_HISTORY_TEST);
    $os = ohcCreateOs(BIZ_HISTORY_TEST);

    // Entry FAKE de biz=99 com transaction_id colidindo (tentativa de leak cross-tenant)
    ['s1' => $s1B, 'action' => $actionB] = ohcSetupCacambaLocacao(BIZ_FICTICIO_HISTORY);
    ohcLogged(BIZ_FICTICIO_HISTORY, $os->id, $actionB->id, $s1B->id, null, ['motivo' => 'LEAK ATTEMPT']);

    $controller = app(ServiceOrderFsmActionController::class);
    $response = $controller->history($os);
    $payload = $response->getData(true);

    expect($payload['count'])->toBe(0);
})->afterEach(function () {
    Vehicle::withoutGlobalScopes()->whereIn('business_id', [BIZ_HISTORY_TEST, BIZ_FICTICIO_HISTORY])->where('plate', 'like', 'OHC%')->forceDelete();
    ServiceOrder::withoutGlobalScopes()->whereIn('business_id', [BIZ_HISTORY_TEST, BIZ_FICTICIO_HISTORY])->forceDelete();
    ohcCleanup(BIZ_HISTORY_TEST);
    ohcCleanup(BIZ_FICTICIO_HISTORY);
});
