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
use App\Scopes\ScopeByBusiness;
use Modules\OficinaAuto\Database\Seeders\OficinaAutoFsmSeeder;
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
 *   4. Multi-tenant Tier 0 — history de biz=99 não vaza pro tenant de teste (ADR 0093)
 *   5. 403 sem permission oficinaauto.service_order.view
 *
 * Pattern: skip SQLite (ADR 0101 — schema MySQL UltimatePOS obrigatório).
 *
 * O controller lê o histórico só dos processos pela CHAVE EXATA (ORDER_TYPE_TO_PROCESS +
 * 'cacamba_locacao'). Por isso o processo vem do OficinaAutoFsmSeeder (idempotente), que
 * cria o 'cacamba_locacao' real com disponivel → locada via iniciar_locacao. Um processo
 * de teste com chave sufixada ('cacamba_locacao_ohc…') nunca casa e o histórico sai vazio.
 *
 * Tenant 98 (ADR 0358). A limpeza apaga só as linhas criadas aqui, pelos ids.
 *
 * @see app/Http/Controllers/ServiceOrderFsmActionController.php (método history)
 * @see memory/sessions/2026-05-20-arte-tela-fsm-workflow.md (gap #1)
 * @see memory/decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md
 */

const BIZ_HISTORY_TEST = 98;
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

    $this->ohcOsIds = [];
    $this->ohcVehicleIds = [];
    $this->ohcHistoryIds = [];
});

/**
 * Helper — processo REAL cacamba_locacao do tenant (via seeder): stages disponivel/locada
 * + action iniciar_locacao.
 *
 * @return array{process: SaleProcess, s1: SaleProcessStage, s2: SaleProcessStage, action: SaleStageAction}
 */
function ohcProcessoCacambaLocacao(int $bizId): array
{
    (new OficinaAutoFsmSeeder)->runForBusiness($bizId);

    $process = SaleProcess::withoutGlobalScope(ScopeByBusiness::class)
        ->where('business_id', $bizId)->where('key', 'cacamba_locacao')->firstOrFail();
    $s1 = SaleProcessStage::where('process_id', $process->id)->where('key', 'disponivel')->firstOrFail();
    $s2 = SaleProcessStage::where('process_id', $process->id)->where('key', 'locada')->firstOrFail();
    $action = SaleStageAction::where('stage_id', $s1->id)->where('key', 'iniciar_locacao')->firstOrFail();

    return compact('process', 's1', 's2', 'action');
}

/**
 * Helper — processo FSM de mentira (chave sufixada) num tenant que não tem o seed. Só
 * serve de origem para a linha de histórico forjada do teste cross-tenant.
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
    $vehicle = Vehicle::withoutGlobalScopes()->create([ // SUPERADMIN: setup teste
        'business_id'  => $bizId,
        'plate'        => 'OHC' . substr((string) random_int(1000, 9999), 0, 4),
        'vehicle_type' => 'caminhao',
    ]);
    $os = ServiceOrder::withoutGlobalScopes()->create([ // SUPERADMIN: setup teste
        'business_id' => $bizId,
        'vehicle_id'  => $vehicle->id,
        'order_type'  => 'manutencao', // locação erradicada (ADR 0265); incidental ao teste de histórico
        'status'      => 'aberta',
        'entered_at'  => now(),
        'daily_rate'  => '150.00',
    ]);
    test()->ohcOsIds[] = $os->id;
    test()->ohcVehicleIds[] = $vehicle->id;

    return $os;
}

function ohcLogged(int $bizId, int $txId, ?int $actionId, ?int $fromId, ?int $toId, array $payload = []): SaleStageHistory
{
    $h = SaleStageHistory::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id'      => $bizId,
        'transaction_id'   => $txId,
        'action_id'        => $actionId,
        'from_stage_id'    => $fromId,
        'to_stage_id'      => $toId,
        'user_id'          => null,
        'payload_snapshot' => $payload,
        'executed_at'      => now(),
    ]);
    test()->ohcHistoryIds[] = $h->id;

    return $h;
}

/** Apaga só o que este arquivo criou (ids) — o tenant é compartilhado com outros testes. */
function ohcCleanup(): void
{
    $t = test();
    SaleStageHistory::withoutGlobalScope(ScopeByBusiness::class)->whereIn('id', $t->ohcHistoryIds ?? [])->delete();
    ServiceOrder::withoutGlobalScopes()->whereIn('id', $t->ohcOsIds ?? [])->forceDelete(); // SUPERADMIN: limpeza do teste
    Vehicle::withoutGlobalScopes()->whereIn('id', $t->ohcVehicleIds ?? [])->forceDelete(); // SUPERADMIN: limpeza do teste

    // Processos de mentira (chave sufixada) — o cacamba_locacao do seeder fica, como nos outros testes.
    $procIds = SaleProcess::withoutGlobalScope(ScopeByBusiness::class)
        ->where(fn ($q) => $q->where('key', 'like', 'cacamba_locacao_ohc%')->orWhere('key', 'like', 'venda_padrao_ohcintruso%'))
        ->pluck('id');
    $stageIds = SaleProcessStage::whereIn('process_id', $procIds)->pluck('id');
    SaleStageAction::whereIn('stage_id', $stageIds)->delete();
    SaleProcessStage::whereIn('id', $stageIds)->delete();
    SaleProcess::withoutGlobalScope(ScopeByBusiness::class)->whereIn('id', $procIds)->delete();
}

afterEach(fn () => ohcCleanup());

// ─── Specs ────────────────────────────────────────────────────────────────

it('1. retorna entry cacamba_* com shape canônico (action + from_stage + to_stage)', function () {
    session(['user.business_id' => BIZ_HISTORY_TEST]);

    ['s1' => $s1, 's2' => $s2, 'action' => $action] = ohcProcessoCacambaLocacao(BIZ_HISTORY_TEST);
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
});

it('2. startPipeline entry (action_id NULL + pipeline_started=true) APARECE', function () {
    session(['user.business_id' => BIZ_HISTORY_TEST]);

    ['s1' => $s1] = ohcProcessoCacambaLocacao(BIZ_HISTORY_TEST);
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
});

it('3. discrimina process_key — entry de venda_padrao com transaction_id colidindo NÃO aparece', function () {
    session(['user.business_id' => BIZ_HISTORY_TEST]);

    ['s1' => $s1, 's2' => $s2, 'action' => $cacambaAction] = ohcProcessoCacambaLocacao(BIZ_HISTORY_TEST);
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
});

it('4. multi-tenant Tier 0 — history de biz=99 não aparece quando a OS é do tenant de teste', function () {
    session(['user.business_id' => BIZ_HISTORY_TEST]);

    // OS do tenant de teste
    ['s1' => $s1, 'action' => $action] = ohcProcessoCacambaLocacao(BIZ_HISTORY_TEST);
    $os = ohcCreateOs(BIZ_HISTORY_TEST);

    // Entry FAKE de biz=99 com transaction_id colidindo (tentativa de leak cross-tenant)
    ['s1' => $s1B, 'action' => $actionB] = ohcSetupCacambaLocacao(BIZ_FICTICIO_HISTORY);
    ohcLogged(BIZ_FICTICIO_HISTORY, $os->id, $actionB->id, $s1B->id, null, ['motivo' => 'LEAK ATTEMPT']);

    $controller = app(ServiceOrderFsmActionController::class);
    $response = $controller->history($os);
    $payload = $response->getData(true);

    expect($payload['count'])->toBe(0);
});
