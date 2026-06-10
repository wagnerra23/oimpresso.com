<?php

declare(strict_types=1);

namespace Modules\OficinaAuto\Services;

use App\Domain\Fsm\Models\SaleProcess;
use App\Domain\Fsm\Models\SaleProcessStage;
use App\Domain\Fsm\Models\SaleStageHistory;
use App\Domain\Fsm\Support\FsmAuthorizationFlag;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\OficinaAuto\Entities\ServiceOrder;

/**
 * ServiceOrderPipelineStarter — coloca uma OS no pipeline FSM correto (ADR 0265).
 *
 * Lógica extraída de ServiceOrderFsmActionController::startPipeline pra ser
 * compartilhada com o auto-start do ServiceOrderController::store(): a OS nasce
 * JÁ no quadro (stage inicial `recepcao` do `oficina_mecanica_os`) em vez de
 * nascer com current_stage_id=null e depender de um clique manual que podia
 * cair no processo errado (causa raiz da OS-00004 órfã no pipeline de locação).
 *
 * Resolução order_type → process_key é o MAPA ÚNICO do domínio:
 *   - 'mecanica'   → 'oficina_mecanica_os' (fluxo real de reparo — [W] 2026-06-02)
 *   - 'manutencao' → 'cacamba_manutencao'  (legado preservado, não orfana OS antigas)
 *   - 'locacao' NÃO EXISTE (erradicado — ADR 0265; enum já é {manutencao, mecanica})
 *
 * Multi-tenant Tier 0 (ADR 0093): processo resolvido por business_id da própria OS.
 * Auditoria: grava entrada `pipeline_started` em sale_stage_history (append-only).
 *
 * @see app/Http/Controllers/ServiceOrderFsmActionController.php
 * @see Modules/OficinaAuto/Http/Controllers/ServiceOrderController.php (store auto-start)
 * @see memory/decisions/0265-oficina-reparo-erradica-locacao.md
 */
class ServiceOrderPipelineStarter
{
    /**
     * Map order_type → process_key cadastrado em OficinaAutoFsmSeeder.
     *
     * @var array<string, string>
     */
    public const ORDER_TYPE_TO_PROCESS = [
        'manutencao' => 'cacamba_manutencao',
        'mecanica'   => 'oficina_mecanica_os',
    ];

    /**
     * Resolve process_key a partir do order_type da OS (null se sem tipo).
     */
    public function resolveProcessKey(ServiceOrder $order): ?string
    {
        $orderType = $order->order_type ?? null;

        return self::ORDER_TYPE_TO_PROCESS[$orderType] ?? null;
    }

    /**
     * Inicia o pipeline FSM da OS no stage inicial do processo.
     *
     * @param  string|null  $processKey  override explícito (edge cases superadmin);
     *                                   default resolve via order_type.
     * @return SaleProcessStage stage inicial em que a OS entrou
     *
     * @throws \InvalidArgumentException quando não dá pra iniciar (sem order_type,
     *                                   já em pipeline, processo não cadastrado/sem inicial)
     */
    public function start(ServiceOrder $order, ?string $processKey = null, ?int $userId = null): SaleProcessStage
    {
        $processKey ??= $this->resolveProcessKey($order);

        if ($processKey === null) {
            throw new \InvalidArgumentException(
                'OS sem order_type definido — não foi possível inferir processo FSM. ' .
                'Informe process_key explicitamente.'
            );
        }

        if (($order->current_stage_id ?? null) !== null) {
            throw new \InvalidArgumentException(
                'OS já está em pipeline FSM (stage_id=' . $order->current_stage_id . ')'
            );
        }

        $businessId = (int) $order->business_id;

        $process = SaleProcess::withoutGlobalScope(ScopeByBusiness::class)
            ->where('business_id', $businessId)
            ->where('key', $processKey)
            ->where('active', true)
            ->first();

        if (! $process) {
            throw new \InvalidArgumentException(
                "Processo '{$processKey}' não cadastrado pro business {$businessId}. " .
                'Rode o seeder OficinaAutoFsmSeeder.'
            );
        }

        /** @var SaleProcessStage|null $stage relação stages() é HasMany sem generic — Larastan tipa Model */
        $stage = $process->stages()->where('is_initial', true)->first();

        if (! $stage) {
            throw new \InvalidArgumentException(
                "Processo '{$processKey}' não tem stage inicial cadastrado."
            );
        }

        // Marca flag autorizativa + atualiza current_stage_id (trava GuardsFsmTransitions)
        FsmAuthorizationFlag::mark($order::class, $order->getKey());
        $order->current_stage_id = $stage->id;
        $order->save();

        // Audit log: registra entrada no pipeline (append-only, ADR 0143)
        SaleStageHistory::withoutGlobalScope(ScopeByBusiness::class)->create([
            'business_id'      => $businessId,
            'transaction_id'   => $order->id,  // subject_id polimórfico — usa ID da OS
            'action_id'        => null,
            'from_stage_id'    => null,
            'to_stage_id'      => $stage->id,
            'user_id'          => $userId,
            'payload_snapshot' => [
                'pipeline_started' => true,
                'subject_type'     => ServiceOrder::class,
                'service_order_id' => $order->id,
                'process_key'      => $processKey,
                'order_type'       => $order->order_type ?? null,
            ],
            'executed_at' => now(),
        ]);

        return $stage;
    }
}
