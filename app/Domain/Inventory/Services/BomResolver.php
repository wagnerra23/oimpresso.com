<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Services;

use App\Domain\Inventory\Models\ProductBom;
use App\Product;
use App\Variation;
use Illuminate\Support\Facades\Log;
use LogicException;
use Modules\Jana\Scopes\ScopeByBusiness;

/**
 * BomResolver — resolve Bill of Materials de um produto em lista plana de
 * componentes-folha pra reserva/consumo de estoque.
 *
 * Comportamento (US-INV-003 + SPEC §4.1 + §5.1):
 *
 *   - Produto SEM BOM → retorna 1 row {product_id, variation_id, qty} representando ele mesmo
 *   - Produto COM BOM em product_bom → resolve recursivamente até componentes-folha
 *   - Componente que é ele mesmo kit → recursão (multi-level, MAX_DEPTH=5)
 *   - Circular dependency detectada → LogicException
 *   - Fallback legacy: se product.type='combo' E variation.combo_variations preenchido
 *     E product_bom vazio → usa JSON legacy (compat UPos POS Blade, SPEC §9 + D1)
 *
 * NÃO toca estoque. Retorna apenas plano de resolução pra side-effects FSM.
 *
 * Multi-tenant Tier 0 (ADR 0093) — sempre filtra por business_id passado.
 */
class BomResolver
{
    /** Profundidade máxima de recursão pra detectar loops infinitos (kit-de-kit). */
    public const MAX_DEPTH = 5;

    /**
     * Resolve composição de um produto numa lista plana de componentes-folha.
     *
     * @param  int  $businessId  Tier 0 — escopo obrigatório
     * @param  int  $productId  Produto pai (kit ou simples)
     * @param  int|null  $variationId  Variação específica (se variable)
     * @param  float  $qtyParent  Multiplicador (ex: 3 kits = qty 3, cada componente × 3)
     * @return array<int,array{product_id:int,variation_id:?int,qty:float,from_legacy?:bool,bom_id?:int}>
     *
     * @throws LogicException Se recursão > MAX_DEPTH (circular dependency presumida)
     */
    public function resolve(
        int $businessId,
        int $productId,
        ?int $variationId = null,
        float $qtyParent = 1.0
    ): array {
        return $this->resolveRecursive(
            businessId: $businessId,
            productId: $productId,
            variationId: $variationId,
            qtyMultiplier: $qtyParent,
            depth: 0,
            visited: []
        );
    }

    /**
     * Núcleo recursivo. Mantém set de productIds visitados pra detectar ciclos
     * mesmo quando profundidade < MAX_DEPTH (caso degenerado: A→B→A com depth 2).
     *
     * @param  array<int,bool>  $visited  product_ids já visitados na cadeia (anti-ciclo)
     * @return array<int,array{product_id:int,variation_id:?int,qty:float,from_legacy?:bool,bom_id?:int}>
     */
    private function resolveRecursive(
        int $businessId,
        int $productId,
        ?int $variationId,
        float $qtyMultiplier,
        int $depth,
        array $visited
    ): array {
        if ($depth > self::MAX_DEPTH) {
            throw new LogicException(
                "BomResolver: profundidade máxima ({$depth} > " . self::MAX_DEPTH . ") excedida "
                . "no produto {$productId}. Possível dependência circular ou kit aninhado profundo demais."
            );
        }

        if (isset($visited[$productId])) {
            throw new LogicException(
                "BomResolver: dependência circular detectada — produto {$productId} já está na cadeia "
                . '(' . implode(' → ', array_keys($visited)) . " → {$productId})."
            );
        }
        $visited[$productId] = true;

        // 1) Buscar rows em product_bom (fonte canônica V2).
        //    withoutGlobalScope + where(business_id) explícito porque serviço pode
        //    rodar em job/CLI sem session — Tier 0 manual.
        //
        //    Pai pode ser definido sem variação (aplica a todas) OU per-variação.
        //    Se variationId vier: pega rows da variação específica + rows globais (NULL).
        //    Se variationId for null: pega só rows globais (NULL).
        $bomRows = ProductBom::withoutGlobalScope(ScopeByBusiness::class)
            ->where('business_id', $businessId)
            ->where('parent_product_id', $productId)
            ->where(function ($q) use ($variationId) {
                $q->whereNull('parent_variation_id');
                if ($variationId !== null) {
                    $q->orWhere('parent_variation_id', $variationId);
                }
            })
            ->orderBy('sort_order')
            ->get();

        // 2) Sem BOM canônico → tentar fallback legacy combo_variations.
        if ($bomRows->isEmpty()) {
            $legacy = $this->resolveLegacyCombo($businessId, $productId, $variationId, $qtyMultiplier);
            if ($legacy !== null) {
                return $legacy;
            }

            // Produto simples (sem BOM, sem combo legacy) → retorna ele mesmo.
            return [[
                'product_id' => $productId,
                'variation_id' => $variationId,
                'qty' => $qtyMultiplier,
            ]];
        }

        // 3) BOM canônico encontrado → resolver recursivamente cada componente.
        $resolved = [];
        foreach ($bomRows as $bom) {
            $componentQty = (float) $bom->qty_required * $qtyMultiplier;

            // Componente pode ele mesmo ser kit → recursão.
            $sub = $this->resolveRecursive(
                businessId: $businessId,
                productId: (int) $bom->component_product_id,
                variationId: $bom->component_variation_id !== null ? (int) $bom->component_variation_id : null,
                qtyMultiplier: $componentQty,
                depth: $depth + 1,
                visited: $visited
            );

            // Marca bom_id na folha imediata pra rastreabilidade (audit/UI).
            foreach ($sub as $row) {
                if (! isset($row['bom_id'])) {
                    $row['bom_id'] = (int) $bom->id;
                }
                $resolved[] = $row;
            }
        }

        return $resolved;
    }

    /**
     * Fallback de coexistência: produto UPos legacy type='combo' com
     * variations.combo_variations JSON preenchido E product_bom vazio.
     *
     * Retorna null se não-aplicável (produto não é combo OU JSON vazio).
     * Log info quando consumir fallback (rastrear migração V1 → V2).
     *
     * @return array<int,array{product_id:int,variation_id:?int,qty:float,from_legacy:bool}>|null
     */
    private function resolveLegacyCombo(
        int $businessId,
        int $productId,
        ?int $variationId,
        float $qtyMultiplier
    ): ?array {
        $product = Product::query()
            ->where('id', $productId)
            ->where('business_id', $businessId)
            ->first();

        if ($product === null || $product->type !== 'combo') {
            return null;
        }

        // Combo legacy guarda componentes em variations.combo_variations (1 variação per combo na prática).
        $variationQuery = Variation::query()->where('product_id', $productId);
        if ($variationId !== null) {
            $variationQuery->where('id', $variationId);
        }
        $variation = $variationQuery->first();

        if ($variation === null) {
            return null;
        }

        $combo = $variation->combo_variations; // já é array via cast Variation.php:25
        if (! is_array($combo) || empty($combo)) {
            return null;
        }

        Log::info('BomResolver: fallback combo_variations legacy acionado', [
            'business_id' => $businessId,
            'product_id' => $productId,
            'variation_id' => $variation->id,
            'components_count' => count($combo),
            'reason' => 'product_bom vazio + product.type=combo + combo_variations preenchido',
        ]);

        $resolved = [];
        foreach ($combo as $item) {
            // Formato legacy: {variation_id, quantity, unit_id}
            $compVariationId = isset($item['variation_id']) ? (int) $item['variation_id'] : null;
            $qty = (float) ($item['quantity'] ?? 0) * $qtyMultiplier;

            if ($compVariationId === null || $qty <= 0) {
                continue;
            }

            // Pra resolver product_id do componente, buscar variation (legacy não guarda product_id direto).
            $compVariation = Variation::query()->where('id', $compVariationId)->first();
            if ($compVariation === null) {
                continue;
            }

            $resolved[] = [
                'product_id' => (int) $compVariation->product_id,
                'variation_id' => $compVariationId,
                'qty' => $qty,
                'from_legacy' => true,
            ];
        }

        // Se JSON existia mas todas linhas inválidas → trata como sem BOM (produto simples).
        return empty($resolved) ? null : $resolved;
    }
}
