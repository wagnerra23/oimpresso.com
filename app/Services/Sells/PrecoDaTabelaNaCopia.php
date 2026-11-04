<?php

declare(strict_types=1);

namespace App\Services\Sells;

use App\Transaction;
use App\Utils\ProductUtil;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * D-05 (decidida 2026-11-04, [W] por [F]) — a venda ou o orçamento duplicado sai com o preço da
 * tabela de hoje do cliente, não com o preço da origem.
 *
 * Regras, nesta ordem:
 *  1. A tabela do cliente é a própria (`contacts.selling_price_group_id`, mesma empresa, não
 *     excluída) ou, sem ela, a do grupo do cliente (`customer_groups.selling_price_group_id`).
 *     É a mesma conta da busca de clientes da venda (P-087, `ContactController::getCustomers`).
 *  2. Cliente sem tabela: nada muda, a cópia fica com o preço da origem.
 *  3. Item sem preço nessa tabela: fica com o preço da origem.
 *  4. Item com preço na tabela: troca o preço cheio da linha e mantém o desconto dela (fixo ou %).
 *  5. O total da cópia muda só pela diferença das linhas; frete, despesas, arredondamento e o
 *     desconto fixo do pedido ficam como estavam. Desconto % e imposto do pedido acompanham a nova
 *     base. ICMS-ST: recalculado pelo mesmo motor da venda quando a origem tinha ST.
 *
 * Quem mudou de preço a tela de edição descobre comparando com a origem
 * (`SellController::precosQueMudaramNaCopia`) — não há coluna nova.
 *
 * Mexe em VALOR (REGRA MESTRE): dupla prova em `SellsDuplicarContratoTest` UC-SDUP-08..10.
 */
final class PrecoDaTabelaNaCopia
{
    public function __construct(private readonly ProductUtil $productUtil) {}

    /** Tabela de preço de hoje do cliente da venda, ou null. Escopo da empresa (Tier 0). */
    public function tabelaDoCliente(int $businessId, ?int $contactId): ?int
    {
        if (! $contactId) {
            return null;
        }
        $q = DB::table('contacts')
            ->leftJoin('customer_groups as cg', function ($j) {
                $j->on('cg.id', '=', 'contacts.customer_group_id')->on('cg.business_id', '=', 'contacts.business_id');
            })
            ->where('contacts.business_id', $businessId)
            ->where('contacts.id', $contactId);

        $grupo = 'cg.selling_price_group_id';
        if (Schema::hasColumn('contacts', 'selling_price_group_id')) {
            $q->leftJoin('selling_price_groups as spg_cli', function ($j) {
                $j->on('spg_cli.id', '=', 'contacts.selling_price_group_id')
                    ->on('spg_cli.business_id', '=', 'contacts.business_id')
                    ->whereNull('spg_cli.deleted_at');
            });
            $grupo = 'COALESCE(spg_cli.id, cg.selling_price_group_id)';
        }
        $id = $q->value(DB::raw($grupo.' as tabela'));
        if (! $id) {
            return null;
        }

        // A tabela do grupo também tem de ser da empresa e estar ativa.
        $ok = DB::table('selling_price_groups')->where('id', $id)->where('business_id', $businessId)
            ->whereNull('deleted_at')->exists();

        return $ok ? (int) $id : null;
    }

    /**
     * Reprecifica a cópia já gravada (venda ou orçamento). Devolve as linhas que mudaram:
     * list<array{line_id:int, de:float, para:float}> com o preço unitário com imposto.
     */
    public function aplicar(Transaction $copia): array
    {
        $biz = (int) $copia->business_id;
        $tabela = $this->tabelaDoCliente($biz, $copia->contact_id ? (int) $copia->contact_id : null);
        if ($tabela === null) {
            return [];
        }

        $taxas = DB::table('tax_rates')->where('business_id', $biz)->pluck('amount', 'id');
        $mudou = [];
        $deltaBase = 0.0;

        foreach ($copia->sell_lines()->get() as $linha) {
            if (! empty($linha->parent_sell_line_id) || ($linha->children_type ?? '') === 'combo') {
                continue; // combo e modificador seguem a linha mãe como estavam
            }
            $preco = $this->productUtil->getVariationGroupPrice((int) $linha->variation_id, $tabela, $linha->tax_id);
            if ($preco['price_inc_tax'] === '' || $preco['price_inc_tax'] === null) {
                continue; // regra 3
            }
            $baseSemImposto = round((float) $preco['price_exc_tax'], 4);
            $desconto = (float) ($linha->line_discount_amount ?? 0);
            $unit = $baseSemImposto;
            if ($desconto > 0) {
                $unit = ($linha->line_discount_type ?? '') === 'percentage'
                    ? ((100 - $desconto) * $baseSemImposto) / 100
                    : $baseSemImposto - $desconto;
            }
            $aliquota = $linha->tax_id ? (float) ($taxas[$linha->tax_id] ?? 0) : 0.0;
            $itemTax = round($unit * $aliquota / 100, 4);
            $incTax = round($unit + $itemTax, 4);
            $antes = round((float) $linha->unit_price_inc_tax, 4);
            if (abs($incTax - $antes) < 0.00005) {
                continue;
            }

            DB::table('transaction_sell_lines')->where('id', $linha->id)->update([
                'unit_price_before_discount' => $baseSemImposto,
                'unit_price' => round($unit, 4),
                'item_tax' => $itemTax,
                'unit_price_inc_tax' => $incTax,
            ]);
            $deltaBase += ($incTax - $antes) * (float) $linha->quantity;
            $mudou[] = ['line_id' => (int) $linha->id, 'de' => $antes, 'para' => $incTax];
        }

        $this->ajustarTotais($copia, $tabela, round($deltaBase, 4), $taxas->all());

        return $mudou;
    }

    /** @param array<int,float|string> $taxas */
    private function ajustarTotais(Transaction $copia, int $tabela, float $deltaBase, array $taxas): void
    {
        $antes = [
            'base' => (float) $copia->total_before_tax,
            'desc' => $this->descontoDoPedido($copia, (float) $copia->total_before_tax),
        ];
        $novaBase = round($antes['base'] + $deltaBase, 4);
        $novoDesc = $this->descontoDoPedido($copia, $novaBase);
        $aliq = $copia->tax_id ? (float) ($taxas[$copia->tax_id] ?? 0) : 0.0;
        $impAntes = (float) ($copia->tax_amount ?? 0);
        $impNovo = $aliq > 0 ? round(($novaBase - $novoDesc) * $aliq / 100, 4) : $impAntes;

        $final = (float) $copia->final_total + $deltaBase - ($novoDesc - $antes['desc']) + ($impNovo - $impAntes);

        $copia->forceFill([
            'total_before_tax' => $novaBase,
            'tax_amount' => $impNovo,
            'final_total' => round($final, 4),
            'selling_price_group_id' => $tabela,
        ])->save();

        $stAntes = (float) ($copia->icms_st_valor ?? 0);
        if ($stAntes > 0 && $deltaBase != 0.0) {
            try {
                $stNovo = app(IcmsStDaVenda::class)->daVenda($copia->fresh());
                $copia->forceFill([
                    'final_total' => round((float) $copia->final_total - $stAntes + $stNovo, 4),
                    'icms_st_valor' => round($stNovo, 2),
                ])->save();
            } catch (\Throwable $e) {
                // O ST é recalculado e conferido de novo ao salvar o rascunho (conferirEGravar).
                Log::warning('D-05: ST da cópia não recalculado', ['transaction_id' => $copia->id, 'erro' => $e->getMessage()]);
            }
        }
    }

    private function descontoDoPedido(Transaction $t, float $base): float
    {
        $valor = (float) ($t->discount_amount ?? 0);

        return ($t->discount_type ?? '') === 'percentage' ? round($base * $valor / 100, 4) : $valor;
    }
}
