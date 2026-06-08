<?php

namespace Modules\NFSe\Observers;

use App\Transaction;
use Illuminate\Support\Facades\Log;
use Modules\NFSe\Models\NfseEmissao;
use Modules\NFSe\Models\NfseProviderConfig;

/**
 * Cria rascunho de NFSe automaticamente quando um recurring invoice é gerado.
 *
 * O UPOS não dispara evento — usamos Observer no Transaction.
 * Critério: created + type=sell + recur_parent_id NOT NULL + status=final.
 *
 * O rascunho fica com status='rascunho' aguardando revisão do operador.
 * A emissão é manual (botão na tela NfseIndex) para evitar emissão automática
 * em ambiente homologação sem controle. US-NFSE-007.
 */
class TransactionNfseObserver
{
    public function created(Transaction $transaction): void
    {
        if (
            $transaction->type !== 'sell'
            || empty($transaction->recur_parent_id)
            || $transaction->status !== 'final'
        ) {
            return;
        }

        try {
            $config = NfseProviderConfig::where('business_id', $transaction->business_id)->first();

            if (! $config) {
                return;
            }

            $valorServicos = (float) ($transaction->final_total ?? 0);
            $aliquota      = (float) ($config->aliquota_iss ?? 0.05);

            NfseEmissao::create([
                'business_id'        => $transaction->business_id,
                'recurring_invoice_id' => $transaction->id,
                'status'             => 'rascunho',
                'competencia'        => $transaction->transaction_date,
                'tomador_nome'       => optional($transaction->contact)->name ?? 'Cliente recorrente',
                'tomador_cnpj'       => optional($transaction->contact)->tax_number,
                'tomador_email'      => optional($transaction->contact)->email,
                'descricao'          => 'Serviço recorrente — fatura #' . ($transaction->invoice_no ?? $transaction->id),
                'lc116_codigo'       => $config->lc116_codigo_default ?? '1.05',
                'valor_servicos'     => $valorServicos,
                'aliquota_iss'       => $aliquota,
                'valor_iss'          => round($valorServicos * $aliquota, 2),
                'iss_retido'         => false,
                'rps_numero'         => 'RASCUNHO-' . $transaction->id,
                'idempotency_key'    => 'recur-' . $transaction->id,
            ]);

            Log::channel('nfse')->info('Rascunho NFSe criado para recurring invoice', [
                'transaction_id' => $transaction->id,
                'business_id'    => $transaction->business_id,
            ]);
        } catch (\Throwable $e) {
            // Nunca bloqueia a geração do invoice — apenas loga
            Log::channel('nfse')->error('Erro ao criar rascunho NFSe para recurring', [
                'transaction_id' => $transaction->id,
                'error'          => $e->getMessage(),
            ]);
        }
    }
}
