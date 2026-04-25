<?php

namespace Modules\Financeiro\Jobs;

use App\Transaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Modules\Financeiro\Models\Concerns\BusinessScopeImpl;
use Modules\Financeiro\Models\Titulo;

/**
 * Cria fin_titulos automaticamente a partir de transactions (sells/purchases) com payment_status=due.
 *
 * Idempotência: UNIQUE (business_id, origem, origem_id, parcela_numero) — retry seguro.
 *
 * Dispara: TransactionObserver::created().
 *
 * R-FIN-003 (cria título a partir de venda) + R-FIN-004 (cria título a partir de compra).
 */
class CriarTituloDeVendaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(public int $transactionId)
    {
    }

    public function handle(): void
    {
        $tx = Transaction::find($this->transactionId);

        if (! $tx) {
            return;
        }

        $tipo = match ($tx->type) {
            'sell' => 'receber',
            'purchase' => 'pagar',
            default => null,
        };

        if (! $tipo) {
            return;
        }

        $origem = $tx->type === 'sell' ? 'venda' : 'compra';
        $valorTotal = (float) $tx->final_total;

        // Calcula valor aberto baseado em transaction_payments existentes
        $jaPago = (float) $tx->payment_lines()->sum('amount');
        $valorAberto = max(0, $valorTotal - $jaPago);

        if ($valorAberto <= 0) {
            return;
        }

        // Idempotência via firstOrCreate (UNIQUE protege contra double dispatch)
        Titulo::query()
            ->withoutGlobalScope(BusinessScopeImpl::class)
            ->updateOrCreate(
                [
                    'business_id' => $tx->business_id,
                    'origem' => $origem,
                    'origem_id' => $tx->id,
                    'parcela_numero' => null,
                ],
                [
                    'numero' => $this->proximoNumero($tx->business_id, $tipo),
                    'tipo' => $tipo,
                    'status' => $valorAberto >= $valorTotal ? 'aberto' : 'parcial',
                    'cliente_id' => $tx->contact_id,
                    'valor_total' => $valorTotal,
                    'valor_aberto' => $valorAberto,
                    'moeda' => 'BRL',
                    'emissao' => Carbon::parse($tx->transaction_date)->toDateString(),
                    'vencimento' => $this->calcularVencimento($tx),
                    'competencia_mes' => Carbon::parse($tx->transaction_date)->format('Y-m'),
                    'created_by' => $tx->created_by ?? 1,
                    'metadata' => [
                        'auto_created' => true,
                        'transaction_invoice_no' => $tx->invoice_no,
                    ],
                ]
            );
    }

    /**
     * Sequencial business-isolado. Lockado pra evitar dupla numeração.
     */
    private function proximoNumero(int $businessId, string $tipo): string
    {
        $prefix = $tipo === 'receber' ? 'R' : 'P';

        $ultimo = Titulo::query()
            ->withoutGlobalScope(BusinessScopeImpl::class)
            ->where('business_id', $businessId)
            ->where('tipo', $tipo)
            ->where('numero', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->value('numero');

        $seq = $ultimo ? ((int) preg_replace('/\D/', '', $ultimo)) + 1 : 1;

        return $prefix . str_pad((string) $seq, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Vencimento padrão: transaction.due_date se existir, senão emissão+30d.
     */
    private function calcularVencimento(Transaction $tx): string
    {
        if ($tx->due_date) {
            return Carbon::parse($tx->due_date)->toDateString();
        }

        return Carbon::parse($tx->transaction_date)->addDays(30)->toDateString();
    }
}
