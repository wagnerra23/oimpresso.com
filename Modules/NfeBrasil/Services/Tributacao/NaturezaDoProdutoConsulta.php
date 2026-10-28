<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Services\Tributacao;

use App\Contracts\Fiscal\NaturezaDoProduto;
use Illuminate\Support\Facades\DB;

/**
 * Natureza fiscal do produto para a consulta de produto (UC-FLX-31 · UC-PIDX-31).
 *
 * Lê `nfe_naturezas_produto`, a mesma tabela que a separação NF-e + NFS-e da venda usa
 * (SeparacaoNotasDaVenda): sem linha = mercadoria. Quem decide é o contador
 * (NaturezaFiscalService::decidir); aqui só se lê.
 *
 * Tier 0 (ADR 0093): sempre filtrado pela empresa recebida.
 */
final class NaturezaDoProdutoConsulta implements NaturezaDoProduto
{
    public function dosProdutos(int $businessId, array $productIds): array
    {
        if ($productIds === [] || ! NaturezaFiscalService::disponivel()) {
            return [];
        }

        $porProduto = [];
        foreach (DB::table('nfe_naturezas_produto')
            ->where('business_id', $businessId)
            ->whereIn('product_id', $productIds)
            ->get(['product_id', 'natureza', 'codigo_servico']) as $r) {
            $porProduto[(int) $r->product_id] = [
                'natureza' => (string) $r->natureza,
                'codigo_servico' => $r->codigo_servico === null || $r->codigo_servico === '' ? null : (string) $r->codigo_servico,
            ];
        }

        return $porProduto;
    }

    public function produtosCom(int $businessId, string $natureza): array
    {
        if (! in_array($natureza, ['servico', 'pendente'], true) || ! NaturezaFiscalService::disponivel()) {
            return [];
        }

        return array_values(array_map('intval', DB::table('nfe_naturezas_produto')
            ->where('business_id', $businessId)
            ->where('natureza', $natureza)
            ->pluck('product_id')
            ->all()));
    }
}
