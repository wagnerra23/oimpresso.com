<?php

declare(strict_types=1);

namespace Modules\Connector\Listeners;

use App\Events\SellCreatedOrModified;
use Illuminate\Support\Facades\Log;
use Modules\Connector\Services\WebhookSaida\DisparadorWebhookSaida;
use Throwable;

/**
 * Venda finalizada → webhook `venda.finalizada` para os destinos da empresa (US-CONN-021).
 *
 * `SellCreatedOrModified` dispara a cada gravação da venda; o evento sai UMA vez por venda e
 * destino (a venda editada depois de finalizada não repete o aviso). Só lê a venda: não muda
 * valor nem estoque.
 *
 * Roda dentro da gravação da venda: qualquer falha aqui é registrada e engolida — aviso para
 * sistema externo nunca derruba uma venda.
 */
class VendaFinalizadaParaWebhookSaida
{
    public function __construct(private DisparadorWebhookSaida $disparador)
    {
    }

    public function handle(SellCreatedOrModified $event): void
    {
        $tx = $event->transaction;
        if ($tx->type !== 'sell' || $tx->status !== 'final' || empty($tx->business_id)) {
            return;
        }

        try {
            $this->disparar($tx);
        } catch (Throwable $e) {
            Log::warning('webhook de saída: falha ao enfileirar venda.finalizada', [
                'business_id' => (int) $tx->business_id,
                'venda_id' => (int) $tx->id,
                'erro' => class_basename($e),
            ]);
        }
    }

    private function disparar($tx): void
    {
        $this->disparador->disparar(
            (int) $tx->business_id,
            DisparadorWebhookSaida::VENDA_FINALIZADA,
            (int) $tx->id,
            [
                'venda_id' => (int) $tx->id,
                'numero' => $tx->invoice_no,
                'data' => (string) $tx->transaction_date,
                'total' => (string) $tx->final_total,
                'situacao_pagamento' => $tx->payment_status,
                'local_id' => $tx->location_id !== null ? (int) $tx->location_id : null,
            ],
            umaVezPorRecurso: true,
        );
    }
}
