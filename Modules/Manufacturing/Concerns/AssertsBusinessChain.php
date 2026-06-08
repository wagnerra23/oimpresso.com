<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Trait AssertsBusinessChain — Wave 18 D1 boost Manufacturing (2026-05-16).
 *
 * Trait helper IDEMPOTENTE pra Entities Manufacturing que NÃO podem usar
 * `HasBusinessScope` direto (tabelas `mfg_recipes` / `mfg_recipe_ingredients`
 * NÃO têm coluna `business_id` — isolamento via JOIN `products.business_id`).
 *
 * Em vez de tentar global scope (que daria "Unknown column"), oferece dois
 * helpers query-builder que fecham o gap multi-tenant Tier 0 ({@see ADR 0093}):
 *
 *   - `scopeForBusinessViaProductChain(Builder, int)` — JOIN canônico chain
 *     `variation → product → business_id` aplicável em qualquer `Model::query()`.
 *   - `belongsToBusinessChain(int, int)` — checagem direta de chain pra
 *     reaproveitamento em Services/Controllers.
 *
 * Pattern espelha `MfgRecipe::forDropdown($business_id)` mas tipa em trait
 * reutilizável, permitindo `MfgRecipe::query()->forBusinessViaProductChain(1)`
 * em vez de duplicar o JOIN.
 *
 * **Multi-tenant Tier 0 IRREVOGÁVEL** (ADR 0093) — toda leitura cross-business
 * é explicit-cast com comentário `// SUPERADMIN: <razão>` (jamais sem audit
 * trail).
 *
 * **Lifecycle Wave 18:**
 * - 2026-05-16 — Wave 18 SATURATION D1 (4/30 → 18/30): trait introduzido pra
 *   formalizar pattern adoptado ad-hoc desde Wave 13. Pest robusto valida
 *   forBusinessViaProductChain + belongsToBusinessChain via biz=1 vs biz=99
 *   ({@see ADR 0101} — nunca biz=cliente real).
 *
 * @see App\Concerns\HasBusinessScope (pra tabelas com business_id direto)
 * @see Modules\Manufacturing\Entities\MfgRecipe::forDropdown (pattern original)
 * @see Modules\Manufacturing\Tests\Feature\Wave18BusinessChainTraitTest
 */
trait AssertsBusinessChain
{
    /**
     * Scope query builder: filtra registros via chain `variation → product → business_id`.
     *
     * Permite `MfgRecipe::query()->forBusinessViaProductChain(1)->get()` em vez
     * de duplicar o JOIN — Manufacturing legacy não suporta global scope direto.
     *
     * @param  Builder  $query  Query builder em curso
     * @param  int  $businessId  Tier 0 — NUNCA omitir, NUNCA usar session() em Job
     * @return Builder
     */
    public function scopeForBusinessViaProductChain(Builder $query, int $businessId): Builder
    {
        $table = $this->getTable();
        $variationFk = property_exists($this, 'variationChainColumn')
            ? $this->variationChainColumn
            : 'variation_id';

        return $query->whereExists(function ($sub) use ($table, $variationFk, $businessId) {
            $sub->select(DB::raw(1))
                ->from('variations as bcv')
                ->join('products as bcp', 'bcv.product_id', '=', 'bcp.id')
                ->whereColumn('bcv.id', "{$table}.{$variationFk}")
                ->where('bcp.business_id', $businessId);
        });
    }

    /**
     * Checa se uma instância concreta pertence ao business via chain product.
     *
     * Útil em Services/Controllers que recebem $id externo (ex: rota param)
     * e precisam validar tenancy antes de operar. Centraliza o lookup chain
     * pra evitar bypass acidental.
     *
     * @param  int  $modelId  PK da Model (mfg_recipes.id, mfg_recipe_ingredients.id…)
     * @param  int  $businessId  Tier 0 — caller injeta (controller resolve via session())
     * @return bool true se chain válida; false caso contrário (NÃO pertence ou inexiste)
     */
    public function belongsToBusinessChain(int $modelId, int $businessId): bool
    {
        $table = $this->getTable();
        $variationFk = property_exists($this, 'variationChainColumn')
            ? $this->variationChainColumn
            : 'variation_id';

        // Se variation_id é direto na Model, JOIN simples chain.
        if ($this->hasDirectVariationColumn()) {
            return DB::table("{$table} as bct")
                ->join('variations as bcv', "bct.{$variationFk}", '=', 'bcv.id')
                ->join('products as bcp', 'bcv.product_id', '=', 'bcp.id')
                ->where('bct.id', $modelId)
                ->where('bcp.business_id', $businessId)
                ->exists();
        }

        // Senão (ex: MfgRecipeIngredient herda via parent recipe), chain dupla
        return DB::table("{$table} as bct")
            ->join('mfg_recipes as bcr', 'bct.mfg_recipe_id', '=', 'bcr.id')
            ->join('variations as bcv', 'bcr.variation_id', '=', 'bcv.id')
            ->join('products as bcp', 'bcv.product_id', '=', 'bcp.id')
            ->where('bct.id', $modelId)
            ->where('bcp.business_id', $businessId)
            ->exists();
    }

    /**
     * Detecta se a Model tem coluna `variation_id` direta (vs herdar via parent).
     *
     * Override em Models específicas se necessário (ex: MfgRecipeIngredient pode
     * declarar `protected $variationChainColumn = 'variation_id'` e ainda assim
     * usar chain via parent — depende do schema).
     */
    protected function hasDirectVariationColumn(): bool
    {
        return property_exists($this, 'hasOwnVariationChain')
            ? $this->hasOwnVariationChain
            : in_array($this->getTable(), ['mfg_recipes'], true);
    }
}
