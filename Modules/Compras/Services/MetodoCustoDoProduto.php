<?php

declare(strict_types=1);

namespace Modules\Compras\Services;

use App\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Compras\Entities\InsumoMetodoCusto;

/**
 * O método de custo de um produto inteiro, no cadastro (US-COM-024 · UC-FLX-38).
 *
 * O método mora por variação (`compras_insumo_metodo_custo`); o cadastro do produto escolhe um só
 * e aplica a todas as variações dele. Vale para qualquer item comprado, matéria-prima ou revenda:
 * o app não marca tipo de produto, e o custo da compra existe para os dois.
 *
 * Entra no formulário Blade pelos ganchos de módulo do core (`product_form_part` desenha,
 * `after_product_saved` grava, ver DataController). O `ProductController::update()` não muda.
 *
 * Tier 0 (ADR 0093): a empresa é a do produto, que o core já carregou com `business_id` da sessão.
 */
final class MetodoCustoDoProduto
{
    /** Campo do formulário. Ausente no envio = o cliente não gerencia o método, nada muda. */
    public const CAMPO = 'compras_metodo_custo';

    /** Variações do produto com métodos diferentes: o formulário mostra e, se ficar assim, não grava. */
    public const MISTO = 'misto';

    /** @return list<int> */
    private function variacoes(int $businessId, int $productId): array
    {
        return DB::table('variations as v')
            ->join('products as p', 'p.id', '=', 'v.product_id')
            ->where('p.business_id', $businessId)
            ->where('v.product_id', $productId)
            ->whereNull('v.deleted_at')
            ->pluck('v.id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * O método que o formulário mostra: `medio` ou `ultimo` quando todas as variações concordam,
     * `''` quando nenhuma tem método, `misto` quando divergem (ou só parte tem).
     */
    public function atual(int $businessId, int $productId): string
    {
        $variacoes = $this->variacoes($businessId, $productId);
        if ($variacoes === []) {
            return '';
        }

        $metodos = DB::table('compras_insumo_metodo_custo')
            ->where('business_id', $businessId)
            ->whereIn('variation_id', $variacoes)
            ->pluck('metodo', 'variation_id')
            ->all();

        if ($metodos === []) {
            return '';
        }
        $distintos = array_values(array_unique($metodos));

        return count($metodos) === count($variacoes) && count($distintos) === 1 ? $distintos[0] : self::MISTO;
    }

    /**
     * Grava o que veio do formulário. Não grava nada quando: o campo não veio, veio `misto`, veio
     * valor desconhecido, o produto é composição (o custo dela vem dos itens) ou o usuário não tem
     * `purchase.update`. `''` apaga o método das variações do produto.
     */
    public function gravarDoFormulario(Product $product, Request $request): void
    {
        if (! $request->has(self::CAMPO) || ! auth()->check() || ! auth()->user()->can('purchase.update')) {
            return;
        }
        $valor = (string) $request->input(self::CAMPO);
        if ($valor !== '' && ! in_array($valor, InsumoMetodoCusto::METODOS, true)) {
            return;
        }
        if ((string) $product->getAttribute('type') === 'combo') {
            return;
        }

        $businessId = (int) $product->business_id;
        $variacoes = $this->variacoes($businessId, (int) $product->id);
        if ($variacoes === []) {
            return;
        }

        if ($valor === '') {
            DB::table('compras_insumo_metodo_custo')
                ->where('business_id', $businessId)
                ->whereIn('variation_id', $variacoes)
                ->delete();

            return;
        }

        $agora = now();
        DB::table('compras_insumo_metodo_custo')->upsert(
            array_map(fn (int $v) => [
                'business_id' => $businessId, 'variation_id' => $v, 'metodo' => $valor,
                'created_at' => $agora, 'updated_at' => $agora,
            ], $variacoes),
            ['business_id', 'variation_id'],
            ['metodo', 'updated_at'],
        );
    }
}
