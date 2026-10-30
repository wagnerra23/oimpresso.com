<?php

declare(strict_types=1);

namespace App\Services\Sells;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Comissão do representante por cliente, liberada por parcela recebida — P-156 (Sabor Brasil).
 *
 * Como o Delphi faz (medido no `COMISSAO_FINANCEIRO`, 1.643 lançamentos): uma linha por parcela,
 * comissão = valor da parcela × % gravado na venda. A base é a parcela inteira, com ST, porque a
 * soma das parcelas é o total da nota (981 de 1.011 vendas). Frete: nenhuma venda medida tinha.
 *
 * Quem ganha: o representante da venda (`transactions.commission_agent`). Qual %: o congelado na
 * venda (`transactions.comissao_cliente_percentual`, trait `CongelaComissaoDoCliente`). Venda
 * sem esse % não entra: quem não usa a regra não vê nada mudar.
 *
 * Situação de cada parcela (`fin_titulos` da venda), na data `$hoje`:
 *   liberada = título quitado · vencida = não quitado e vencimento passou · aberta = o resto.
 * Venda sem título no Financeiro conta como uma parcela do total, liberada se a venda está paga.
 *
 * Só calcula: não grava, não mexe na regra da US-COMM-015 nem no relatório de comissão de hoje.
 */
final class ComissaoPorCliente
{
    /** @return list<array{representante_id:int, aberta:float, liberada:float, vencida:float, parcelas:list<array<string,mixed>>}> */
    public function doPeriodo(int $biz, string $inicio, string $fim, CarbonInterface $hoje, ?int $representanteId = null): array
    {
        $vendas = DB::table('transactions')
            ->where('business_id', $biz)->where('type', 'sell')->where('status', 'final')
            ->where('comissao_cliente_percentual', '>', 0)->whereNotNull('commission_agent')
            ->whereBetween(DB::raw('date(transaction_date)'), [$inicio, $fim])
            ->when($representanteId, fn ($q) => $q->where('commission_agent', $representanteId))
            ->orderBy('id')
            ->get(['id', 'invoice_no', 'commission_agent', 'comissao_cliente_percentual', 'final_total', 'payment_status', 'transaction_date']);

        $titulos = DB::table('fin_titulos')->where('business_id', $biz)->where('origem', 'venda')
            ->where('tipo', 'receber')->where('status', '<>', 'cancelado')->whereNull('deleted_at')
            ->whereIn('origem_id', $vendas->pluck('id'))
            ->orderBy('parcela_numero')->orderBy('id')
            ->get(['id', 'origem_id', 'parcela_numero', 'valor_total', 'vencimento', 'status'])
            ->groupBy('origem_id');

        $porRep = [];
        foreach ($vendas as $v) {
            $parcelas = $titulos->get($v->id) ?? collect([(object) [
                'id' => null, 'parcela_numero' => 1, 'valor_total' => $v->final_total,
                'vencimento' => substr((string) $v->transaction_date, 0, 10),
                'status' => $v->payment_status === 'paid' ? 'quitado' : 'aberto',
            ]]);
            $rep = (int) $v->commission_agent;
            $porRep[$rep] ??= ['representante_id' => $rep, 'aberta' => 0.0, 'liberada' => 0.0, 'vencida' => 0.0, 'parcelas' => []];
            foreach ($parcelas as $p) {
                $comissao = round((float) $p->valor_total * (float) $v->comissao_cliente_percentual / 100, 2);
                $situacao = $p->status === 'quitado' ? 'liberada'
                    : ((string) $p->vencimento < $hoje->toDateString() ? 'vencida' : 'aberta');
                $porRep[$rep][$situacao] = round($porRep[$rep][$situacao] + $comissao, 2);
                $porRep[$rep]['parcelas'][] = [
                    'venda_id' => (int) $v->id, 'invoice_no' => $v->invoice_no, 'titulo_id' => $p->id ? (int) $p->id : null,
                    'parcela' => (int) $p->parcela_numero, 'vencimento' => (string) $p->vencimento,
                    'base' => round((float) $p->valor_total, 2), 'percentual' => (float) $v->comissao_cliente_percentual,
                    'comissao' => $comissao, 'situacao' => $situacao,
                ];
            }
        }

        return array_values($porRep);
    }
}
