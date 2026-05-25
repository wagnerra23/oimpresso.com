<?php

declare(strict_types=1);

namespace Modules\OficinaAuto\Observers;

use App\Transaction;
use Carbon\Carbon;
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
 *   - manutenção (order_type='manutencao'): zero por default — Wagner edita manual depois
 *     (gap: V1 traz orçamento integrado · v0 mantém manual)
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

        // Só dispara em terminal de SUCESSO (`concluida`) — não em cancelada/recolhida.
        if ($so->status !== 'concluida') {
            return;
        }

        // Idempotência (a): skip se já tem transaction_id linkado.
        if ($so->transaction_id !== null) {
            return;
        }

        // Idempotência (b): defesa-em-profundidade via os_ref.
        $osRef = $this->buildOsRef($so);
        $exists = Transaction::where('business_id', $so->business_id)
            ->where('os_ref', $osRef)
            ->exists();

        if ($exists) {
            Log::info('ServiceOrderObserver: skip · Transaction derivada já existe', [
                'os_ref' => $osRef,
                'service_order_id' => $so->id,
                'business_id' => $so->business_id,
            ]);

            return;
        }

        // Resolve contact_id: prefere SO direto · fallback Vehicle owner.
        $contactId = $so->contact_id ?? $so->vehicle?->contact_id;
        if ($contactId === null) {
            Log::warning('ServiceOrderObserver: skip · sem contact_id resolvível (SO+Vehicle ambos NULL)', [
                'service_order_id' => $so->id,
                'vehicle_id' => $so->vehicle_id,
            ]);

            return;
        }

        // Compute final_total: locação usa accessor valor_receber · manutenção zero (manual depois).
        $finalTotal = $this->computeFinalTotal($so);

        try {
            $tx = Transaction::create([
                'business_id' => $so->business_id,
                'location_id' => null,
                'contact_id' => $contactId,
                'type' => 'sell',
                'status' => 'final',
                'sub_type' => null,
                'payment_status' => 'due',
                'source' => 'oficina',
                'os_ref' => $osRef,
                'final_total' => $finalTotal,
                'total_before_tax' => $finalTotal,
                'transaction_date' => $so->delivered_at ?? $so->completed_at ?? Carbon::now(),
                'created_by' => 1, // System default (NULL pode rejeitar UPOS constraint)
            ]);

            // Completa o 1-1 ADR 0137 §"Escopo arquitetural V0".
            $so->transaction_id = $tx->id;
            $so->saveQuietly(); // saveQuietly evita re-trigger Observer (infinite loop guard)

            Log::info('ServiceOrderObserver: Transaction derivada criada · transaction_id linkado', [
                'transaction_id' => $tx->id,
                'os_ref' => $osRef,
                'service_order_id' => $so->id,
                'business_id' => $so->business_id,
                'contact_id' => $contactId,
                'final_total' => $finalTotal,
                'order_type' => $so->order_type,
            ]);
        } catch (\Throwable $e) {
            // Não bloqueia transição status se Transaction create falhar.
            Log::error('ServiceOrderObserver: falha ao criar Transaction derivada', [
                'os_ref' => $osRef,
                'service_order_id' => $so->id,
                'business_id' => $so->business_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function buildOsRef(ServiceOrder $so): string
    {
        return "SO-{$so->id}";
    }

    private function computeFinalTotal(ServiceOrder $so): float
    {
        if ($so->order_type === 'locacao') {
            // Accessor `valor_receber` = daily_rate × dias_locacao (definido na entity)
            return (float) ($so->valor_receber ?? 0);
        }

        // order_type='manutencao' (default oficina automotiva): V0 sem orçamento auto.
        // Wagner edita Transaction depois. V1 (ADR futuro) traz orçamento integrado.
        return 0.0;
    }
}
