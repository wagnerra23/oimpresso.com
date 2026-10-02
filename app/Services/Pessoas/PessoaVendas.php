<?php

declare(strict_types=1);

namespace App\Services\Pessoas;

use Illuminate\Support\Facades\DB;

/**
 * Números de VENDA de uma pessoa: quantidade, soma, última compra e saldo em aberto.
 *
 * Dono único da fórmula. Antes ela vivia copiada em dois lugares — a aba IA do cadastro
 * (Modules/Crm ClienteIaController::calcularStatsCliente) e a API do app das lojas
 * (Api/App/PessoasController) — e a mesma pessoa só mostrava o mesmo número nos dois
 * enquanto alguém lembrasse de consertar as duas cópias (§5 2026-08-02).
 *
 * Regras (D8 do MAPA-DE-DADOS-v1, PRs #8479 e #8497):
 *  - venda = transactions.type 'sell' com status 'final' (rascunho/orçamento não contam);
 *  - saldo = final_total menos SUM(transaction_payments.amount), só nas vendas due/partial.
 *
 * Os números saem CRUS (sem arredondar, sem piso em zero): cada consumidor apresenta do
 * seu jeito. A aba IA aplica max(0, …); o app arredonda a 2 casas. Mudar isso muda valor
 * mostrado — é decisão [W] com antes→depois, não refatoração.
 *
 * Tier 0 (ADR 0093): business_id explícito em toda consulta.
 */
final class PessoaVendas
{
    /**
     * @return array{qtd: int, soma: float, ultima: ?string}
     */
    public static function resumo(int $bizId, int $contactId): array
    {
        $r = DB::table('transactions')
            ->where('business_id', $bizId)
            ->where('contact_id', $contactId)
            ->where('type', 'sell')
            ->where('status', 'final')
            ->selectRaw('COUNT(*) AS qtd, COALESCE(SUM(final_total), 0) AS soma, MAX(transaction_date) AS ultima')
            ->first();

        return [
            'qtd' => (int) ($r->qtd ?? 0),
            'soma' => (float) ($r->soma ?? 0),
            'ultima' => isset($r->ultima) ? (string) $r->ultima : null,
        ];
    }

    /**
     * Saldo em aberto por pessoa (só as que têm venda due/partial aparecem na chave).
     *
     * @param  array<int>  $contactIds
     * @return array<int, float>
     */
    public static function saldosAbertos(int $bizId, array $contactIds): array
    {
        if ($contactIds === []) {
            return [];
        }

        return DB::table('transactions as t')
            ->where('t.business_id', $bizId)
            ->whereIn('t.contact_id', $contactIds)
            ->where('t.type', 'sell')
            ->where('t.status', 'final')
            ->whereIn('t.payment_status', ['due', 'partial'])
            ->groupBy('t.contact_id')
            ->get([
                't.contact_id',
                DB::raw('SUM(t.final_total - (SELECT COALESCE(SUM(tp.amount), 0) FROM transaction_payments tp WHERE tp.transaction_id = t.id)) AS saldo'),
            ])
            ->mapWithKeys(fn ($r) => [(int) $r->contact_id => (float) $r->saldo])
            ->all();
    }
}
