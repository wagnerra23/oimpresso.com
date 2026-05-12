<?php

declare(strict_types=1);

namespace App\Domain\Fsm\Services;

use App\Transaction;

/**
 * Mapeia status legacy de Transaction pra stage FSM inicial apropriado.
 *
 * Centraliza a lógica usada por:
 * - SaleFsmActionController::startPipeline (1 venda via UI)
 * - FsmBulkStartPipelineCommand (lote via artisan)
 *
 * Mantém comportamento idêntico aos 2 callers anteriores.
 *
 * Mapping canônico:
 * - status=draft + sub_status=quotation → quote_sent
 * - status=draft + sub_status=null → quote_draft
 * - status=final + payment_status=paid → paid
 * - status=final + payment_status=partial → invoiced
 * - status=final + payment_status=due (default) → invoiced
 * - default → quote_draft
 */
class InitialStageResolver
{
    public function resolve(Transaction $venda): string
    {
        $status = $venda->status ?? 'final';
        $paymentStatus = $venda->payment_status ?? 'due';
        $subStatus = $venda->sub_status ?? null;

        if ($status === 'draft') {
            return $subStatus === 'quotation' ? 'quote_sent' : 'quote_draft';
        }

        if ($status === 'final') {
            return match ($paymentStatus) {
                'paid' => 'paid',
                'partial' => 'invoiced',
                default => 'invoiced',
            };
        }

        return 'quote_draft';
    }
}
