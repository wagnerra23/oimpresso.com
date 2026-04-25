<?php

namespace Modules\Financeiro\Services;

use App\Transaction;
use Illuminate\Support\Carbon;
use Modules\Financeiro\Models\Titulo;

/**
 * Sincroniza Titulo financeiro a partir de Transaction (core UltimatePOS).
 *
 * Origem suportadas no MVP:
 *  - 'venda'    — Transaction type='sell'   + payment_status in ('due','partial')
 *  - 'compra'   — Transaction type='purchase' (onda 2)
 *  - 'despesa'  — Transaction type='expense'  (onda 2)
 *
 * Idempotencia: UNIQUE (business_id, origem, origem_id, parcela_numero) em
 * fin_titulos. Re-chamadas updateam, nao duplicam (TECH-0001).
 */
class TituloAutoService
{
    /**
     * Cria ou atualiza Titulo a partir de venda. No-op se nao for venda
     * a prazo (paga = nao gera titulo no Financeiro).
     */
    public function sincronizarDeVenda(Transaction $tx): ?Titulo
    {
        if ($tx->type !== 'sell') {
            return null;
        }

        // Venda paga em dia (status='paid') nao gera titulo financeiro.
        if (! in_array($tx->payment_status, ['due', 'partial'], true)) {
            // Se titulo ja existir e venda virou 'paid', cancela ele.
            return $this->cancelarSeExistir($tx, motivo: 'venda quitada no caixa');
        }

        $valorPago = (float) ($tx->final_total - ($tx->total_remaining_amount ?? $tx->final_total));
        $valorAberto = (float) ($tx->total_remaining_amount ?? $tx->final_total);

        return Titulo::updateOrCreate(
            [
                'business_id' => $tx->business_id,
                'origem' => 'venda',
                'origem_id' => $tx->id,
                'parcela_numero' => null, // MVP nao parcelado
            ],
            [
                'numero' => (string) $tx->id,
                'tipo' => 'receber',
                'status' => $valorAberto > 0 ? ($valorPago > 0 ? 'parcial' : 'aberto') : 'quitado',
                'cliente_id' => $tx->contact_id,
                'valor_total' => $tx->final_total,
                'valor_aberto' => $valorAberto,
                'moeda' => 'BRL',
                'emissao' => Carbon::parse($tx->transaction_date)->toDateString(),
                'vencimento' => $this->calcularVencimento($tx),
                'competencia_mes' => Carbon::parse($tx->transaction_date)->format('Y-m'),
                'observacoes' => $tx->additional_notes,
                'created_by' => $tx->created_by,
            ]
        );
    }

    /**
     * Cancela titulo da venda se existir (nao deleta — append-only por
     * TECH-0002). No-op se nao existir.
     */
    public function cancelarSeExistir(Transaction $tx, string $motivo = ''): ?Titulo
    {
        $titulo = Titulo::where('business_id', $tx->business_id)
            ->where('origem', 'venda')
            ->where('origem_id', $tx->id)
            ->whereNull('parcela_numero')
            ->first();

        if (! $titulo) {
            return null;
        }

        $titulo->status = 'cancelado';
        $titulo->observacoes = trim(($titulo->observacoes ?? '')."\nCancelado: {$motivo}");
        $titulo->save();

        return $titulo;
    }

    /**
     * Vencimento do titulo: se transaction tem pay_term, soma a transaction_date.
     * Senao, padrao 30 dias apos emissao.
     */
    private function calcularVencimento(Transaction $tx): string
    {
        $base = Carbon::parse($tx->transaction_date);

        if (! empty($tx->pay_term_number) && ! empty($tx->pay_term_type)) {
            return ($tx->pay_term_type === 'months'
                ? $base->addMonths((int) $tx->pay_term_number)
                : $base->addDays((int) $tx->pay_term_number)
            )->toDateString();
        }

        return $base->copy()->addDays(30)->toDateString();
    }
}
