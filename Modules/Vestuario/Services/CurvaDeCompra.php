<?php

declare(strict_types=1);

namespace Modules\Vestuario\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Sugestão de compra por tamanho pela curva de venda (US-VEST-031, decisão [W] 2026-10-26).
 *
 * A grade do vestuário mora nas variações do produto (UltimatePOS): cada variação é um tamanho, ou
 * uma combinação cor × tamanho, pelo nome ("M", "Azul - M"). A curva é a proporção vendida de cada
 * variação nos últimos DIAS dias:
 *   1. do próprio produto;
 *   2. sem venda no período (produto novo): da categoria do produto, casando as variações pelo NOME.
 * Vendido = quantidade − devolvida, em vendas finalizadas da empresa.
 *
 * Divisão do total pelo maior resto: cada variação recebe ⌊total × proporção⌋ e as peças que sobram
 * vão, uma a uma, para as maiores frações (empate: a que vendeu mais, depois a ordem do cadastro).
 * A soma sempre bate com o total. É só sugestão: este serviço não grava nada (pedido e estoque só
 * mudam quando quem compra confirma na tela da compra).
 *
 * Tier 0 (ADR 0093): produto, variações e vendas filtrados pelo `$businessId` recebido.
 * A curva do GradeCurvaService (proporção fixa por grade, em configuração) é outra coisa: aquela é
 * a grade padrão do cadastro; esta é a medida da venda real.
 */
final class CurvaDeCompra
{
    public const DIAS = 90;

    /**
     * @return array{base: ?string, motivo: ?string, dias: int, total: int,
     *               linhas: list<array{variation_id: int, nome: string, vendidas: float, proporcao_pct: float, sugerida: int}>}
     */
    public function sugerir(int $businessId, int $productId, int $total, ?Carbon $hoje = null): array
    {
        $hoje ??= Carbon::now();
        $vazio = fn (?string $motivo) => ['base' => null, 'motivo' => $motivo, 'dias' => self::DIAS, 'total' => $total, 'linhas' => []];

        $produto = DB::table('products')->where('business_id', $businessId)->where('id', $productId)->first(['id', 'category_id']);
        if ($produto === null) {
            return $vazio('produto_nao_encontrado');
        }
        $variacoes = DB::table('variations')->where('product_id', $productId)->whereNull('deleted_at')
            ->orderBy('id')->get(['id', 'name']);
        if ($variacoes->isEmpty()) {
            return $vazio('produto_sem_variacao');
        }

        $desde = $hoje->copy()->subDays(self::DIAS)->startOfDay();
        $porVariacao = $this->vendidoPor('tsl.variation_id', $businessId, $desde, $hoje, fn ($q) => $q->where('tsl.product_id', $productId));
        $base = 'produto';
        $vendidas = $variacoes->mapWithKeys(fn ($v) => [$v->id => (float) ($porVariacao[$v->id] ?? 0)]);

        if ($vendidas->sum() <= 0) {
            if ($produto->category_id === null) {
                return $vazio('sem_venda_no_produto');
            }
            $porNome = $this->vendidoPor('v.name', $businessId, $desde, $hoje, fn ($q) => $q
                ->where('p.category_id', $produto->category_id)->where('p.id', '!=', $productId));
            $porNome = collect($porNome)->mapWithKeys(fn ($q, $nome) => [mb_strtolower(trim((string) $nome)) => (float) $q]);
            $vendidas = $variacoes->mapWithKeys(fn ($v) => [$v->id => (float) ($porNome[mb_strtolower(trim((string) $v->name))] ?? 0)]);
            $base = 'categoria';
            if ($vendidas->sum() <= 0) {
                return $vazio('sem_venda_no_produto_nem_na_categoria');
            }
        }

        $soma = $vendidas->sum();
        $linhas = [];
        foreach ($variacoes as $ordem => $v) {
            $q = $vendidas[$v->id];
            $exato = $total * $q / $soma;
            $linhas[] = ['variation_id' => (int) $v->id, 'nome' => (string) $v->name, 'vendidas' => round($q, 4),
                'proporcao_pct' => round($q / $soma * 100, 1), 'sugerida' => (int) floor($exato),
                '_resto' => $exato - floor($exato), '_ordem' => $ordem];
        }
        $faltam = $total - array_sum(array_column($linhas, 'sugerida'));
        $ranking = $linhas;
        usort($ranking, fn ($a, $b) => [$b['_resto'], $b['vendidas'], $a['_ordem']] <=> [$a['_resto'], $a['vendidas'], $b['_ordem']]);
        foreach (array_slice($ranking, 0, max(0, $faltam)) as $r) {
            $linhas[$r['_ordem']]['sugerida']++;
        }

        return ['base' => $base, 'motivo' => null, 'dias' => self::DIAS, 'total' => $total,
            'linhas' => array_map(fn ($l) => array_diff_key($l, ['_resto' => 1, '_ordem' => 1]), $linhas)];
    }

    /** @return array<int|string, float> chave => vendido no período (quantidade − devolvida) */
    private function vendidoPor(string $chave, int $businessId, Carbon $desde, Carbon $ate, callable $filtro): array
    {
        $q = DB::table('transaction_sell_lines as tsl')
            ->join('transactions as t', 't.id', '=', 'tsl.transaction_id')
            ->join('variations as v', 'v.id', '=', 'tsl.variation_id')
            ->join('products as p', 'p.id', '=', 'tsl.product_id')
            ->where('t.business_id', $businessId)->where('p.business_id', $businessId)
            ->where('t.type', 'sell')->where('t.status', 'final')
            ->whereBetween('t.transaction_date', [$desde, $ate])
            ->whereNull('tsl.parent_sell_line_id');
        $filtro($q);

        return $q->groupBy($chave)->selectRaw($chave.' as chave, SUM(tsl.quantity - tsl.quantity_returned) as qtd')
            ->pluck('qtd', 'chave')->map(fn ($v) => (float) $v)->all();
    }
}
