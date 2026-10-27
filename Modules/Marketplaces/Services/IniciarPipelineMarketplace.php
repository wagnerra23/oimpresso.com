<?php

declare(strict_types=1);

namespace Modules\Marketplaces\Services;

use App\Domain\Fsm\Models\SaleProcessStage;
use App\Domain\Fsm\Models\SaleStageHistory;
use App\Domain\Fsm\Support\FsmAuthorizationFlag;
use App\Scopes\ScopeByBusiness;
use App\Transaction;
use Modules\Marketplaces\Database\Seeders\FsmProcessoVendaMarketplaceSeeder;

/**
 * Põe a venda nascida de um pedido de marketplace no estágio inicial do processo
 * `venda_marketplace` (US-MKT-007 · ADR 0143).
 *
 * Mesmo caminho auditado do `SaleFsmActionController::startPipeline` e do
 * `fsm:bulk-start-pipeline`: `FsmAuthorizationFlag::mark` autoriza a escrita de
 * `current_stage_id` (o trait `GuardsFsmTransitions` barra qualquer outra) e a entrada fica em
 * `sale_stage_history` sem ação (início de pipeline). As transições seguintes passam pelo
 * `ExecuteStageActionService`.
 *
 * O processo é semeado na hora, idempotente, se a empresa ainda não o tiver.
 */
class IniciarPipelineMarketplace
{
    public function iniciar(Transaction $venda, int $userId, string $origem): void
    {
        $businessId = (int) $venda->business_id;
        if ($venda->current_stage_id !== null) {
            return;
        }

        $processo = (new FsmProcessoVendaMarketplaceSeeder())->runForBusiness($businessId);
        $inicial = SaleProcessStage::query()
            ->where('process_id', $processo->id)
            ->where('key', FsmProcessoVendaMarketplaceSeeder::INICIAL)
            ->firstOrFail();

        FsmAuthorizationFlag::mark($venda::class, $venda->getKey());
        $venda->current_stage_id = $inicial->id;
        $venda->save();

        SaleStageHistory::withoutGlobalScope(ScopeByBusiness::class)->create([
            'business_id' => $businessId,
            'transaction_id' => (int) $venda->id,
            'action_id' => null,
            'from_stage_id' => null,
            'to_stage_id' => (int) $inicial->id,
            'user_id' => $userId,
            'payload_snapshot' => [
                'pipeline_started' => true,
                'process_key' => FsmProcessoVendaMarketplaceSeeder::PROCESSO,
                'origem' => $origem,
            ],
            'executed_at' => now(),
        ]);
    }
}
