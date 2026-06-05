<?php

declare(strict_types=1);

namespace Modules\OficinaAuto\Observers;

use Illuminate\Support\Facades\Log;
use Modules\OficinaAuto\Entities\ServiceOrder;

/**
 * ServiceOrderObserver — Auto-faturar OS OficinaAuto → Venda derivada quando status terminal.
 *
 * Extensão da integração Vendas × Oficina (ADR 0192 · A1 KB-9.75) pra cobrir
 * `Modules\OficinaAuto\Entities\ServiceOrder` (vertical caçamba/automotivo polido
 * nível 9.5) além do `Modules\Repair\Entities\JobSheet` (Repair shared genérico).
 *
 * Trigger: hook `updated` quando `status` transiciona pra `'concluida'` (terminal
 * de sucesso). NÃO dispara em `'cancelada'` (sem cobrança) nem `'recolhida'`
 * (apenas movimentação física da caçamba sem venda automática).
 *
 * Cria Transaction (type=sell · source='oficina' · os_ref='SO-{id}') herdando
 * business_id da OS (multi-tenant Tier 0 ADR 0093 IRREVOGÁVEL). Atualiza
 * `service_orders.transaction_id` pra completar o 1-1 ADR 0137 §"Escopo arquitetural V0".
 *
 * Idempotência (defesa-em-profundidade):
 *   (a) skip se `$so->transaction_id !== null` (já tem venda derivada)
 *   (b) skip se Transaction::where('os_ref', "SO-{id}")->where('business_id', X) existe
 *
 * Cálculo `final_total`:
 *   - locação (order_type='locacao'): `daily_rate × dias_locacao` (accessor `valor_receber`)
 *   - manutenção (order_type='manutencao'): `sum(items.valor_total)` agregando peças +
 *     mão-de-obra + serviços terceiros via `ServiceOrder::items()` hasMany (US-OFICINA-027
 *     2026-05-26 · ADR 0194 §"Critério validação"). Backward compat: OS sem items lançados
 *     ainda gera Transaction com final_total=0 (Wagner edita manual — comportamento legacy).
 *
 * Distinção `os_ref` vs Repair JobSheet:
 *   - JobSheet (Repair shared): `os_ref="OS-{id}"`
 *   - ServiceOrder (OficinaAuto vertical): `os_ref="SO-{id}"`
 *   Permite frontend distinguir vertical OficinaAuto vs shared Repair se necessário.
 *
 * Registrado no boot do OficinaAutoServiceProvider:
 *   ServiceOrder::observe(ServiceOrderObserver::class);
 *
 * @see memory/decisions/0192-auto-faturar-os-venda-jobsheet-observer.md
 * @see memory/decisions/0137-modules-oficinaauto-qualificada.md
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see Modules/Repair/Observers/JobSheetObserver.php (pattern mãe)
 */
class ServiceOrderObserver
{
    public function updated(ServiceOrder $so): void
    {
        // Só age quando status mudou (filtragem cedo · skip noise).
        if (! $so->wasChanged('status')) {
            return;
        }

        // Wave 4.2 US-OFICINA-014 — Quando OS entra em `orcamento`, dispara Job que envia
        // link público + PIN via WhatsApp pra cliente aprovar/rejeitar. Best-effort: falha
        // de Job NÃO bloqueia fluxo da oficina. Service+Controller+Page já existem desde
        // PR antecedente (US-OFICINA-006); este hook completa o wire-up automático.
        // Idempotência interna do Job (cache key) protege contra trigger duplo.
        if ($so->status === 'orcamento') {
            \Modules\OficinaAuto\Jobs\EnviarLinkAprovacaoWhatsappJob::dispatch(
                (int) $so->business_id,
                (int) $so->id,
            );
            // continua processando — não bloqueia outras transições subsequentes
        }

        // Só dispara auto-faturar Transaction em terminal de SUCESSO (`concluida`).
        // NÃO em cancelada/recolhida/orcamento.
        if ($so->status !== 'concluida') {
            return;
        }

        // Auto-faturar OS → Venda: delega ao FaturarServiceOrderService — SINGLE
        // SOURCE OF TRUTH (mesmo serviço do botão "Gerar venda" do board
        // producao-oficina). O serviço trata idempotência (transaction_id / os_ref),
        // resolução de contact e o valor (accessors valor_receber/total_items —
        // regra-mestre valor/estoque: zero cálculo novo aqui).
        try {
            app(\Modules\OficinaAuto\Services\FaturarServiceOrderService::class)->faturar($so);
        } catch (\Throwable $e) {
            // Não bloqueia a transição de status se a criação da venda falhar.
            Log::error('ServiceOrderObserver: falha ao auto-faturar OS → venda', [
                'service_order_id' => $so->id,
                'business_id'      => $so->business_id,
                'error'            => $e->getMessage(),
            ]);
        }
    }
}
