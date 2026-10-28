<?php

declare(strict_types=1);

namespace App\Services\Sells;

use App\Transaction;
use Illuminate\Support\Facades\DB;

/**
 * Comissão por regra de uma venda gravada — US-COMM-015.
 *
 * Quem ganha: o vendedor da venda (`transactions.commission_agent`) e, quando a venda saiu de uma
 * OS, o técnico dela (`repair_job_sheets.service_staff`). Cada um é um PAPEL e só recebe por
 * regra do próprio papel.
 *
 * Qual regra vale, por linha: a mais específica. Produto vence categoria, categoria vence a
 * regra geral do vendedor; no mesmo escopo, a regra só daquela pessoa vence a regra de todos;
 * empatou, vale a mais nova. Linha sem regra não paga comissão por regra.
 *
 * Quanto: `percentual` sobre a base da linha (quantidade líquida de devolução × preço unitário
 * já com o desconto da linha, sem imposto) ou `valor` por unidade líquida. Arredonda por linha,
 * a 2 casas.
 *
 * Só calcula: não grava nada e não muda o relatório de comissão de hoje.
 */
final class ComissaoPorRegra
{
    private const PESO_ESCOPO = ['produto' => 3, 'categoria' => 2, 'vendedor' => 1];

    public static function ligada(): bool
    {
        return (bool) config('sells.comissao.por_regra', false);
    }

    /** @return array{total: float, beneficiarios: list<array<string,mixed>>} */
    public function daVenda(Transaction $venda): array
    {
        $biz = (int) $venda->business_id;
        $pessoas = array_filter([
            'vendedor' => $venda->commission_agent ? (int) $venda->commission_agent : null,
            'tecnico' => $this->tecnicoDaOs($biz, $venda->getAttribute('repair_job_sheet_id') ? (int) $venda->getAttribute('repair_job_sheet_id') : null),
        ]);

        // Regras e pessoas lidas pelo business da venda, não pela sessão (vale também em fila/CLI).
        $regras = DB::table('commission_rules')->where('business_id', $biz)->where('ativo', 1)->get();
        $linhas = DB::table('transaction_sell_lines as l')
            ->join('products as p', 'p.id', '=', 'l.product_id')
            ->where('l.transaction_id', $venda->id)->where('p.business_id', $biz)
            ->whereNull('l.parent_sell_line_id')
            ->orderBy('l.id')
            ->get(['l.id', 'l.product_id', 'p.category_id', 'l.quantity', 'l.quantity_returned', 'l.unit_price']);

        $beneficiarios = [];
        $total = 0.0;
        foreach ($pessoas as $papel => $userId) {
            if (! DB::table('users')->where('id', $userId)->where('business_id', $biz)->exists()) {
                continue;
            }
            $itens = [];
            $soma = 0.0;
            foreach ($linhas as $l) {
                $regra = $this->regraDaLinha($regras, $papel, $userId, (int) $l->product_id, $l->category_id ? (int) $l->category_id : null);
                if (! $regra) {
                    continue;
                }
                $unidades = (float) $l->quantity - (float) $l->quantity_returned;
                $base = round($unidades * (float) $l->unit_price, 2);
                $valor = $regra->tipo === 'valor'
                    ? round($unidades * (float) $regra->valor, 2)
                    : round($base * (float) $regra->valor / 100, 2);
                $itens[] = ['sell_line_id' => (int) $l->id, 'regra_id' => (int) $regra->id, 'base' => $base, 'comissao' => $valor];
                $soma += $valor;
            }
            $soma = round($soma, 2);
            $beneficiarios[] = ['papel' => $papel, 'user_id' => $userId, 'total' => $soma, 'linhas' => $itens];
            $total += $soma;
        }

        return ['total' => round($total, 2), 'beneficiarios' => $beneficiarios];
    }

    private function tecnicoDaOs(int $biz, ?int $jobSheetId): ?int
    {
        if (! $jobSheetId || ! \Illuminate\Support\Facades\Schema::hasTable('repair_job_sheets')) {
            return null;
        }
        $id = DB::table('repair_job_sheets')->where('id', $jobSheetId)->where('business_id', $biz)->value('service_staff');

        return $id ? (int) $id : null;
    }

    private function regraDaLinha($regras, string $papel, int $userId, int $productId, ?int $categoryId): ?object
    {
        $melhor = null;
        $melhorPeso = -1.0;
        foreach ($regras as $r) {
            if ($r->papel !== $papel || ($r->user_id !== null && (int) $r->user_id !== $userId)) {
                continue;
            }
            $casa = match ($r->escopo) {
                'produto' => (int) $r->product_id === $productId,
                'categoria' => $categoryId !== null && (int) $r->category_id === $categoryId,
                'vendedor' => true,
                default => false,
            };
            if (! $casa) {
                continue;
            }
            $peso = self::PESO_ESCOPO[$r->escopo] + ($r->user_id !== null ? 0.5 : 0);
            if ($peso > $melhorPeso || ($peso === $melhorPeso && (int) $r->id > (int) $melhor->id)) {
                $melhor = $r;
                $melhorPeso = $peso;
            }
        }

        return $melhor;
    }
}
