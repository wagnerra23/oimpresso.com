<?php

declare(strict_types=1);

namespace Modules\OficinaAuto\Services;

use App\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Modules\OficinaAuto\Entities\ServiceOrder;

/**
 * Gera a Venda (Transaction derivada) a partir de uma OS — SINGLE SOURCE OF TRUTH
 * do auto-faturar OficinaAuto (ADR 0192). Usado por:
 *   - ServiceOrderObserver (automático quando a OS transiciona pra 'concluida')
 *   - botão "Gerar venda" no board producao-oficina (manual, lado da oficina)
 *
 * REGRA-MESTRE valor/estoque (Wagner 2026-06-05): este serviço NÃO calcula
 * nenhum valor novo — delega 100% aos accessors já testados da entity:
 *   - locação (order_type='locacao'):    valor_receber = daily_rate × dias_locacao
 *   - manutenção (order_type='manutencao'): total_items = Σ peças + mão-de-obra + serviços
 * (zero matemática de valor aqui → zero superfície pra o bug de inflação repetir.)
 *
 * IDEMPOTENTE (defesa-em-profundidade — nunca dupla-fatura):
 *   (a) skip se a OS já tem transaction_id linkado
 *   (b) skip se já existe Transaction com mesmo os_ref + business_id
 *
 * Multi-tenant Tier 0 (ADR 0093): herda business_id da OS; o global scope de
 * Transaction/ServiceOrder garante isolamento.
 *
 * @see memory/decisions/0192-auto-faturar-os-venda-jobsheet-observer.md
 * @see Modules/OficinaAuto/Observers/ServiceOrderObserver.php
 * @see Modules/OficinaAuto/Entities/ServiceOrder.php (accessors valor_receber / total_items)
 */
class FaturarServiceOrderService
{
    public const REASON_ALREADY_LINKED = 'already_linked';
    public const REASON_OS_REF_EXISTS  = 'os_ref_exists';
    public const REASON_NO_CONTACT     = 'no_contact';
    public const REASON_CREATED        = 'created';

    /**
     * Fatura a OS gerando (ou recuperando) a venda derivada.
     *
     * @return array{transaction: Transaction|null, created: bool, reason: string}
     */
    public function faturar(ServiceOrder $so): array
    {
        $osRef = $this->osRef($so);

        // Idempotência (a): OS já linkada a uma venda.
        if ($so->transaction_id !== null) {
            return [
                'transaction' => Transaction::find($so->transaction_id),
                'created'     => false,
                'reason'      => self::REASON_ALREADY_LINKED,
            ];
        }

        // Idempotência (b): defesa-em-profundidade via os_ref.
        $existing = Transaction::where('business_id', $so->business_id)
            ->where('os_ref', $osRef)
            ->first();

        if ($existing !== null) {
            return ['transaction' => $existing, 'created' => false, 'reason' => self::REASON_OS_REF_EXISTS];
        }

        // Resolve contact_id: prefere a OS · fallback dono do veículo. value() em
        // vez de $so->vehicle?->contact_id evita acesso a propriedade de relação
        // não-tipada (larastan) e respeita o global scope business_id do Vehicle.
        $contactId = $so->contact_id;
        if ($contactId === null) {
            // whereKey lida com vehicle_id null graciosamente (sem match → null).
            $contactId = \Modules\OficinaAuto\Entities\Vehicle::whereKey($so->vehicle_id)->value('contact_id');
        }
        if ($contactId === null) {
            Log::warning('FaturarServiceOrderService: sem contact_id resolvível (OS+Veículo NULL)', [
                'service_order_id' => $so->id,
                'vehicle_id'       => $so->vehicle_id,
            ]);

            return ['transaction' => null, 'created' => false, 'reason' => self::REASON_NO_CONTACT];
        }

        $finalTotal = $this->previewTotal($so);

        $tx = Transaction::create([
            'business_id'      => $so->business_id,
            'location_id'      => null,
            'contact_id'       => $contactId,
            'type'             => 'sell',
            'status'           => 'final',
            'sub_type'         => null,
            'payment_status'   => 'due',
            'source'           => 'oficina',
            'os_ref'           => $osRef,
            'final_total'      => $finalTotal,
            'total_before_tax' => $finalTotal,
            'transaction_date' => $so->delivered_at ?? $so->completed_at ?? Carbon::now(),
            'created_by'       => 1, // System default (NULL pode rejeitar constraint UPOS)
        ]);

        // Completa o 1-1 (ADR 0137). saveQuietly evita re-trigger do Observer.
        $so->transaction_id = $tx->id;
        $so->saveQuietly();

        Log::info('FaturarServiceOrderService: venda derivada criada', [
            'transaction_id'   => $tx->id,
            'os_ref'           => $osRef,
            'service_order_id' => $so->id,
            'business_id'      => $so->business_id,
            'contact_id'       => $contactId,
            'final_total'      => $finalTotal,
            'order_type'       => $so->order_type,
        ]);

        return ['transaction' => $tx, 'created' => true, 'reason' => self::REASON_CREATED];
    }

    /**
     * Prévia do valor da venda SEM criar nada — pro botão mostrar o total
     * ANTES de confirmar (regra-mestre: apresentar o impacto). Reusa os mesmos
     * accessors do faturamento real.
     */
    public function previewTotal(ServiceOrder $so): float
    {
        if ($so->order_type === 'locacao') {
            return (float) ($so->valor_receber ?? 0);
        }

        // manutenção: soma dos itens (0.0 se ainda não lançou peças — edita manual depois).
        return (float) $so->total_items;
    }

    private function osRef(ServiceOrder $so): string
    {
        return "SO-{$so->id}";
    }
}
