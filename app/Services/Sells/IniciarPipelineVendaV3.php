<?php

declare(strict_types=1);

namespace App\Services\Sells;

use App\Domain\Fsm\Models\SaleProcess;
use App\Domain\Fsm\Models\SaleStageHistory;
use App\Domain\Fsm\Services\InitialStageResolver;
use App\Domain\Fsm\Support\FsmAuthorizationFlag;
use App\Scopes\ScopeByBusiness;
use App\Transaction;
use Database\Seeders\FsmProcessoVendaComProducaoSeeder;

/**
 * Põe a venda gravada pela Venda V3 (`Sells/CreateV3.tsx`) no processo `venda_com_producao`
 * (UC-V400 / UC-V401 · ADR 0143).
 *
 * Só a V3 entra aqui: o `SellPosController@store` chama este serviço quando o payload traz
 * `origem_tela=venda_v3`. A venda pelo `/pos/create` (`Sells/Create.tsx`) continua nascendo
 * fora do pipeline, como sempre (Fronteira do `CreateV3.casos.md`).
 *
 * Mesmo caminho auditado do `SaleFsmActionController::startPipeline` e do
 * `IniciarPipelineMarketplace`: o estágio vem do `InitialStageResolver`, a escrita de
 * `current_stage_id` é autorizada por `FsmAuthorizationFlag::mark` (o trait
 * `GuardsFsmTransitions` barra qualquer outra) e o início fica em `sale_stage_history` sem ação.
 *
 * A empresa sem o processo ganha o processo na hora (seeder idempotente), como o marketplace
 * faz — senão a venda da V3 nasceria fora do pipeline em silêncio, que é o defeito consertado.
 */
class IniciarPipelineVendaV3
{
    public const ORIGEM = 'venda_v3';

    private const PROCESSO = 'venda_com_producao';

    public function __construct(private InitialStageResolver $resolver)
    {
    }

    public function iniciar(Transaction $venda, int $userId): void
    {
        if ($venda->current_stage_id !== null) {
            return;
        }

        $businessId = (int) $venda->business_id;
        $processo = $this->processo($businessId);
        if (! $processo) {
            (new FsmProcessoVendaComProducaoSeeder())->runForBusiness($businessId);
            $processo = $this->processo($businessId);
        }
        if (! $processo) {
            // O processo existe mas foi desativado pela empresa: é escolha dela, a venda segue
            // gravada sem pipeline, como o startPipeline recusaria.
            \Log::warning('Venda V3 sem pipeline: processo venda_com_producao inativo', [
                'business_id' => $businessId, 'transaction_id' => (int) $venda->id,
            ]);

            return;
        }

        $stageKey = $this->resolver->resolve($venda);
        $stageId = (int) $processo->stages()->where('key', $stageKey)->firstOrFail()->getKey();

        FsmAuthorizationFlag::mark($venda::class, $venda->getKey());
        $venda->current_stage_id = $stageId;
        $venda->save();

        SaleStageHistory::withoutGlobalScope(ScopeByBusiness::class)->create([
            'business_id' => $businessId,
            'transaction_id' => (int) $venda->id,
            'action_id' => null,
            'from_stage_id' => null,
            'to_stage_id' => $stageId,
            'user_id' => $userId,
            'payload_snapshot' => [
                'pipeline_started' => true,
                'process_key' => self::PROCESSO,
                'origem' => self::ORIGEM,
                'mapped_from' => "status={$venda->status} payment_status={$venda->payment_status} sub_status={$venda->sub_status}",
            ],
            'executed_at' => now(),
        ]);
    }

    private function processo(int $businessId): ?SaleProcess
    {
        return SaleProcess::withoutGlobalScope(ScopeByBusiness::class)
            ->where('business_id', $businessId)
            ->where('key', self::PROCESSO)
            ->where('active', true)
            ->first();
    }
}
