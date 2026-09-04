<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Services;

use App\Util\OtelHelper;
use App\Variation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Manufacturing\Entities\MfgRecipe;
use Modules\Manufacturing\Entities\MfgRecipeIngredient;

/**
 * RecipeBomService — leitura especializada de BOM (Bill of Materials) Manufacturing.
 *
 * Encapsula resolução do BOM e cálculo de custo via chain Variation→Product,
 * extraindo lógica antes embutida em RecipeController + ManufacturingUtil.
 *
 * Service thin: zero side-effect, apenas queries READ + cálculo determinístico.
 * Toda query respeita isolamento multi-tenant Tier 0 ({@see ADR 0093}) via
 * `products.business_id` (JOIN chain — Manufacturing legacy não tem global scope).
 *
 * NUNCA aceita biz=cliente real em smoke ({@see ADR 0101}) — usar biz=1 (Wagner).
 *
 * @see Modules\Manufacturing\Http\Controllers\RecipeController
 * @see Modules\Manufacturing\Utils\ManufacturingUtil
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */
class RecipeBomService
{
    /**
     * Resolve o BOM completo de uma recipe — ingredients ordenados + variation chain pre-carregada.
     *
     * Padrão de query usado em ProductionController e addIngredients(). Garante eager-load
     * mínimo necessário pra cálculo de custo (evita N+1).
     *
     * @param  int  $recipeId  ID da recipe (mfg_recipes.id)
     * @param  int  $businessId  Tenant — usado pra validar chain product.business_id
     * @return Collection<int, MfgRecipeIngredient>
     */
    public function resolveBom(int $recipeId, int $businessId): Collection
    {
        // D9.a OTel: span de leitura do BOM (hot-path RecipeController + ProductionController).
        // Zero-cost quando otel.enabled=false (default Hostinger).
        return OtelHelper::spanBiz('manufacturing.recipe.resolve_bom', function () use ($recipeId, $businessId) {
            // Confirma que a recipe pertence ao business via JOIN chain (multi-tenant Tier 0)
            $pertence = MfgRecipe::join('variations as v', 'mfg_recipes.variation_id', '=', 'v.id')
                ->join('products as p', 'v.product_id', '=', 'p.id')
                ->where('mfg_recipes.id', $recipeId)
                ->where('p.business_id', $businessId)
                ->exists();

            if (! $pertence) {
                return collect();
            }

            return MfgRecipeIngredient::where('mfg_recipe_id', $recipeId)
                ->with([
                    'variation',
                    'variation.product',
                    'variation.product.unit',
                    'variation.product_variation',
                    'sub_unit',
                    'ingredient_group',
                ])
                ->orderBy('sort_order', 'asc')
                ->get();
        }, [
            'module'    => 'Manufacturing',
            'recipe_id' => $recipeId,
        ]);
    }

    /**
     * Calcula custo total dinâmico de uma recipe — soma dos ingredientes × quantidade × multiplier
     * de sub-unidade + production cost (per_unit / percentage / fixed).
     *
     * Mantém paridade com `ManufacturingUtil::getRecipeTotal($row)` legacy — não muda valores,
     * só extrai pra Service testável isolado (D4.a ratio Service/Controller).
     *
     * @param  MfgRecipe  $recipe  Recipe com `ingredients` + `ingredients.variation` + `ingredients.sub_unit` pre-carregados
     * @return float Custo total em moeda base do business
     */
    public function calculateCost(MfgRecipe $recipe): float
    {
        $price = 0.0;

        foreach ($recipe->ingredients as $ingredient) {
            if (empty($ingredient->variation)) {
                continue;
            }

            $ingredientTotal = (float) $ingredient->variation->dpp_inc_tax * (float) $ingredient->quantity;

            if (! empty($ingredient->sub_unit)) {
                $multiplier = ! empty($ingredient->sub_unit->base_unit_multiplier)
                    ? (float) $ingredient->sub_unit->base_unit_multiplier
                    : 1.0;
                $ingredientTotal *= $multiplier;
            }

            $price += $ingredientTotal;
        }

        $productionCost = (float) ($recipe->extra_cost ?? 0);

        if ($recipe->production_cost_type === 'percentage') {
            $productionCost = ($price * (float) $recipe->extra_cost) / 100;
        } elseif ($recipe->production_cost_type === 'per_unit') {
            $productionCost = (float) $recipe->extra_cost * (float) $recipe->total_quantity;
        }

        return $price + $productionCost;
    }

    /**
     * Resolve unit cost (custo por unidade base) — útil pra previsão de preço de venda e relatórios.
     *
     * Wave 26 D9 — span observável separa cálculo unitário de calculateCost (callsite distinto).
     *
     * @param  MfgRecipe  $recipe
     * @return float Custo unitário; zero se `total_quantity` <= 0 (proteção div-by-zero)
     */
    public function calculateUnitCost(MfgRecipe $recipe): float
    {
        return OtelHelper::spanBiz('manufacturing.recipe.unit_cost', function () use ($recipe) {
            $total = $this->calculateCost($recipe);

            if ((float) $recipe->total_quantity <= 0) {
                return 0.0;
            }

            return $total / (float) $recipe->total_quantity;
        }, [
            'module'    => 'Manufacturing',
            'recipe_id' => $recipe->id,
        ]);
    }

    /**
     * Lista as recipes do business com o custo JÁ RECALCULADO na leitura — payload da tela
     * Inertia `Manufacturing/Recipes` (rota `/manufacturing/recipe`).
     *
     * Tier 0 ({@see ADR 0093}): Manufacturing legacy NÃO tem global scope. O isolamento é o
     * JOIN `mfg_recipes.variation_id -> variations.product_id -> products.business_id` — a
     * mesma cadeia do `RecipeController@index` e do `resolveBom()` acima. Sem ele, receita de
     * outro tenant aparece na lista.
     *
     * O custo NÃO sai de `mfg_recipes.ingredients_cost` (coluna que envelhece: o preço do
     * insumo muda sem passar pela receita). Sai de `calculateCost()`, que lê
     * `variations.dpp_inc_tax` de hoje — é o contrato "custo recalculado a cada leitura"
     * que a tela declara ao usuário.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listRecipesWithCost(int $businessId): array
    {
        return OtelHelper::spanBiz('manufacturing.recipe.list_with_cost', function () use ($businessId) {
            $recipes = MfgRecipe::query()
                ->join('variations as v', 'mfg_recipes.variation_id', '=', 'v.id')
                ->join('product_variations as pv', 'v.product_variation_id', '=', 'pv.id')
                ->join('products as p', 'v.product_id', '=', 'p.id')
                ->leftJoin('categories as c', 'p.category_id', '=', 'c.id')
                ->leftJoin('categories as sc', 'p.sub_category_id', '=', 'sc.id')
                ->leftJoin('units as u', 'p.unit_id', '=', 'u.id')
                ->where('p.business_id', $businessId)
                ->with([
                    'sub_unit',
                    'ingredients',
                    'ingredients.variation',
                    'ingredients.variation.product',
                    'ingredients.variation.product.unit',
                    'ingredients.sub_unit',
                    'ingredients.ingredient_group',
                ])
                ->select(
                    'mfg_recipes.*',
                    DB::raw('IF(p.type="variable", CONCAT(p.name, " - ", pv.name, " - ", v.name), p.name) as recipe_name'),
                    'v.sub_sku as recipe_sku',
                    'p.name as product_name',
                    'c.name as category',
                    'sc.name as sub_category',
                    'u.short_name as unit_name'
                )
                ->orderBy('p.name', 'asc')
                ->get();

            return $recipes->map(function (MfgRecipe $recipe) {
                return $this->presentRecipe($recipe);
            })->all();
        }, [
            'module' => 'Manufacturing',
        ]);
    }

    /**
     * Monta a linha da tela a partir da recipe — nome, cadeia de categoria, grupos de
     * ingredientes e o bloco de custo de §7 do handoff.
     *
     * Todo número exibido é derivado AQUI, no servidor. O cliente não recalcula nada: ele
     * formata. (§9 do handoff: "o protótipo calcula para dar retorno imediato; a autoridade
     * é o servidor".)
     *
     * @return array<string, mixed>
     */
    private function presentRecipe(MfgRecipe $recipe): array
    {
        $grupos = [];

        // `getRelation` em vez da propriedade mágica: a relação vem eager-loaded do
        // `listRecipesWithCost`, e o acesso explícito não depende de análise de magic
        // property (que o Larastan não resolve nesta Model — ver phpstan-baseline).
        $ingredients = $recipe->getRelation('ingredients')->sortBy('sort_order');

        foreach ($ingredients as $ingredient) {
            $variation = $ingredient->variation;

            $multiplier = ! empty($ingredient->sub_unit) && ! empty($ingredient->sub_unit->base_unit_multiplier)
                ? (float) $ingredient->sub_unit->base_unit_multiplier
                : 1.0;

            $precoUnit  = $variation ? (float) $variation->dpp_inc_tax : 0.0;
            $quantidade = (float) $ingredient->quantity;
            $unidadeBase = optional(optional(optional($variation)->product)->unit)->short_name ?? '';

            // Grupo pode ser nulo (ingrediente sem mfg_ingredient_group_id) — o legado
            // permite. Cai num balde "Sem grupo" em vez de sumir da ficha.
            $nomeGrupo = optional($ingredient->ingredient_group)->name ?: 'Sem grupo';

            $grupos[$nomeGrupo] ??= ['g' => $nomeGrupo, 'itens' => []];

            $grupos[$nomeGrupo]['itens'][] = [
                'id'             => (int) $ingredient->id,
                'nome'           => $variation && $variation->product ? $variation->product->name : '—',
                'sku'            => $variation ? $variation->sub_sku : '—',
                'quantidade'     => $quantidade,
                'unidade'        => optional($ingredient->sub_unit)->short_name ?: $unidadeBase,
                'unidade_base'   => $unidadeBase,
                'multiplicador'  => $multiplier,
                'custo_unitario' => $precoUnit,
                'subtotal'       => $quantidade * $precoUnit * $multiplier,
            ];
        }

        $grupos = array_values($grupos);

        foreach ($grupos as $i => $grupo) {
            $grupos[$i]['subtotal'] = array_sum(array_column($grupo['itens'], 'subtotal'));
        }

        $totalQuantity = (float) $recipe->total_quantity;
        $waste         = (float) ($recipe->waste_percent ?? 0);
        $finalPrice    = (float) ($recipe->final_price ?? 0);

        $ingredientes = array_sum(array_column($grupos, 'subtotal'));
        $custoTotal   = $this->calculateCost($recipe);
        $custoExtra   = $custoTotal - $ingredientes;

        // §7.3 — divisão por zero devolve 0, nunca NaN/Infinity.
        // §7.1 — custo unitário divide por total_quantity, NÃO pelo rendimento.
        $custoUnit = $totalQuantity > 0 ? $custoTotal / $totalQuantity : 0.0;
        $margem    = $finalPrice > 0 ? ($finalPrice - $custoUnit) / $finalPrice * 100 : 0.0;

        $subUnit = $recipe->relationLoaded('sub_unit') ? $recipe->getRelation('sub_unit') : null;

        return [
            'id'             => (int) $recipe->id,
            'variation_id'   => (int) $recipe->variation_id,
            // `getAttribute` explícito: estes seis são ALIASES do SELECT (`recipe_name`,
            // `recipe_sku`, `category`, `sub_category`, `unit_name`, `product_name`), não
            // colunas de `mfg_recipes` — o Larastan conhece o schema da tabela e acusa
            // propriedade mágica indefinida. Ler explícito diz o que de fato acontece.
            'name'           => $recipe->getAttribute('recipe_name') ?: ($recipe->getAttribute('product_name') ?: '—'),
            'sku'            => $recipe->getAttribute('recipe_sku') ?: '—',
            'cat'            => $recipe->getAttribute('category') ?: 'Sem categoria',
            'sub'            => $recipe->getAttribute('sub_category') ?: '—',
            'qtd'            => $totalQuantity,
            'un'             => $recipe->getAttribute('unit_name') ?: '',
            'waste'          => $waste,
            'extra'          => (float) ($recipe->extra_cost ?? 0),
            'custo_tipo'     => $recipe->production_cost_type ?: 'percentage',
            'venda'          => $finalPrice,
            'atualizado'     => optional($recipe->updated_at)->format('d/m/Y H:i'),
            'sub_un'         => $subUnit ? $subUnit->short_name : null,
            'sub_fator'      => $subUnit && ! empty($subUnit->base_unit_multiplier)
                ? (float) $subUnit->base_unit_multiplier
                : null,
            'grupos'         => $grupos,
            'n_ingredientes' => array_sum(array_map(function ($g) { return count($g['itens']); }, $grupos)),
            'custos'         => [
                'ingredientes' => $ingredientes,
                'extra'        => $custoExtra,
                'total'        => $custoTotal,
                // §16 — rendimento líquido = total_quantity − total_quantity × waste/100
                'qtd_liq'      => $totalQuantity - $totalQuantity * $waste / 100,
                'unit'         => $custoUnit,
                'margem'       => $margem,
            ],
        ];
    }

    /**
     * Conta receitas do business — usado só pra badge de navegação (§4.1 nav das telas v2),
     * NUNCA pra decisão de negócio. Mesma cadeia de tenant de `listRecipesWithCost`, mas sem
     * o `with()` pesado de ingredientes (a badge não precisa da ficha inteira).
     */
    public function countRecipes(int $businessId): int
    {
        return OtelHelper::spanBiz('manufacturing.recipe.count', function () use ($businessId) {
            return MfgRecipe::query()
                ->join('variations as v', 'mfg_recipes.variation_id', '=', 'v.id')
                ->join('products as p', 'v.product_id', '=', 'p.id')
                ->where('p.business_id', $businessId)
                ->count();
        }, [
            'module' => 'Manufacturing',
        ]);
    }

    /**
     * US-MANU-005 — impacto reverso de um insumo: quais receitas o consomem e o que acontece
     * com o custo unitário delas se o preço de compra variar `$variacaoPct`.
     *
     * É o método que o §18.3 do handoff declarou faltar ("`usosDoInsumo()` é cálculo novo;
     * precisa de um método no RecipeBomService com o JOIN de tenant e teste"). Leitura pura.
     *
     * **Reusa `calculateCost()`, não reimplementa.** A simulação troca o preço do insumo NA
     * CÓPIA em memória e chama a MESMA fórmula de novo. Difere do protótipo, que soma um delta
     * (`total + qtd × preço × pct`) — atalho que erra quando `production_cost_type` é
     * `percentage`, porque aí o custo extra também varia com o dos ingredientes. Desvio
     * declarado em `RUNBOOK-insumos.md §1`.
     *
     * Tier 0 ({@see ADR 0093}): só entram receitas cuja cadeia
     * `mfg_recipes → variations → products.business_id` bate com o tenant.
     *
     * @param  int  $variationId  o insumo (variations.id)
     * @param  int  $businessId  Tier 0 — nunca session() em Job
     * @param  float  $variacaoPct  variação simulada do preço, em % (ex: 10 = +10%)
     * @return array<int, array<string, mixed>>
     */
    public function usosDoInsumo(int $variationId, int $businessId, float $variacaoPct = 0.0): array
    {
        return OtelHelper::spanBiz('manufacturing.recipe.usos_do_insumo', function () use ($variationId, $businessId, $variacaoPct) {
            $linhas = [];

            foreach ($this->recipesDoTenantComIngredientes($businessId, $variationId) as $recipe) {
                $qtd = 0.0;
                $precoAtual = 0.0;
                $unidadeBase = '';

                foreach ($recipe->getRelation('ingredients') as $ingrediente) {
                    if ((int) $ingrediente->variation_id !== $variationId || empty($ingrediente->variation)) {
                        continue;
                    }

                    $multiplicador = ! empty($ingrediente->sub_unit) && ! empty($ingrediente->sub_unit->base_unit_multiplier)
                        ? (float) $ingrediente->sub_unit->base_unit_multiplier
                        : 1.0;

                    $qtd += (float) $ingrediente->quantity * $multiplicador;
                    $precoAtual = (float) $ingrediente->variation->dpp_inc_tax;
                    $unidadeBase = optional(optional($ingrediente->variation->product)->unit)->short_name ?? '';
                }

                if ($qtd <= 0) {
                    continue;
                }

                $custoAtual = $this->calculateCost($recipe);
                $custoSimulado = $this->calculateCostComPrecoSimulado($recipe, $variationId, $variacaoPct);

                $totalQuantity = (float) $recipe->total_quantity;
                $venda = (float) ($recipe->final_price ?? 0);

                $unitNovo = $totalQuantity > 0 ? $custoSimulado / $totalQuantity : 0.0;
                $peso = $custoAtual > 0 ? ($qtd * $precoAtual) / $custoAtual * 100 : 0.0;
                $margemNova = $venda > 0 ? ($venda - $unitNovo) / $venda * 100 : 0.0;

                $linhas[] = [
                    'recipe_id' => (int) $recipe->id,
                    'nome' => $recipe->getAttribute('recipe_name') ?: '—',
                    'sku' => $recipe->getAttribute('recipe_sku') ?: '—',
                    'qtd' => $qtd,
                    'unidade_base' => $unidadeBase,
                    'peso' => $peso,
                    'unit_atual' => $this->calculateUnitCost($recipe),
                    'unit_novo' => $unitNovo,
                    'margem_nova' => $margemNova,
                ];
            }

            usort($linhas, fn ($a, $b) => $b['peso'] <=> $a['peso']);

            return $linhas;
        }, [
            'module' => 'Manufacturing',
            'variation_id' => $variationId,
        ]);
    }

    /**
     * US-MANU-005 — lista os INSUMOS do business com o resumo de uso: em quantas receitas cada
     * um entra e qual o maior peso dele no custo de uma receita.
     *
     * **Definição de "insumo" AQUI, e por que:** é toda variação que aparece como ingrediente
     * em alguma receita do tenant. O app não tem flag de "matéria-prima" no produto — o
     * protótipo tem uma lista `INSUMOS` curada, que não existe no dado real. Derivar dos
     * ingredientes é a única definição que o módulo consegue sustentar sem inventar. Efeito
     * colateral honesto: com esta derivação, o estado "sem receita" do protótipo NÃO ocorre
     * (todo item da lista tem ≥1 receita por construção). A tela mantém o caminho de render
     * desse estado, e o motivo está em `RUNBOOK-insumos.md §2`.
     *
     * Custo por receita é calculado UMA vez (não por insumo × receita).
     *
     * @return array<int, array<string, mixed>>
     */
    public function listInsumosComUso(int $businessId): array
    {
        return OtelHelper::spanBiz('manufacturing.recipe.list_insumos', function () use ($businessId) {
            $recipes = $this->recipesDoTenantComIngredientes($businessId);

            $acc = [];

            foreach ($recipes as $recipe) {
                $custoReceita = $this->calculateCost($recipe);

                // Consumo do insumo NESTA receita (linhas repetidas somam) — precisa fechar o
                // total antes de calcular o peso, senão insumo repetido pesa menos do que é.
                $porInsumo = [];

                foreach ($recipe->getRelation('ingredients') as $ingrediente) {
                    if (empty($ingrediente->variation)) {
                        continue;
                    }

                    $vid = (int) $ingrediente->variation_id;
                    $mult = ! empty($ingrediente->sub_unit) && ! empty($ingrediente->sub_unit->base_unit_multiplier)
                        ? (float) $ingrediente->sub_unit->base_unit_multiplier
                        : 1.0;

                    $porInsumo[$vid] ??= ['qtd' => 0.0, 'variacao' => $ingrediente->variation];
                    $porInsumo[$vid]['qtd'] += (float) $ingrediente->quantity * $mult;
                }

                foreach ($porInsumo as $vid => $dados) {
                    $variacao = $dados['variacao'];
                    $preco = (float) $variacao->dpp_inc_tax;
                    $peso = $custoReceita > 0 ? ($dados['qtd'] * $preco) / $custoReceita * 100 : 0.0;

                    // `??=` e não `if (! isset(...)) { ... }`: isto é INICIALIZAÇÃO de
                    // acumulador, não fallback de dado ausente. A `NoSilentFallbackRule`
                    // (ADR 0212) pede `Log::warning` no segundo caso — aqui logar seria
                    // ruído, porque dispararia a cada insumo novo do laço. O `??=` diz a
                    // intenção certa e é a mesma forma já usada no `$porInsumo` acima.
                    $acc[$vid] ??= [
                        'variation_id' => $vid,
                        'nome' => optional($variacao->product)->name ?: '—',
                        'sku' => $variacao->sub_sku ?: '—',
                        'custo' => $preco,
                        'unidade' => optional(optional($variacao->product)->unit)->short_name ?: '',
                        'estoque' => 0.0,
                        'n_receitas' => 0,
                        'maior_peso' => 0.0,
                    ];

                    $acc[$vid]['n_receitas']++;
                    $acc[$vid]['maior_peso'] = max($acc[$vid]['maior_peso'], $peso);
                }
            }

            if ($acc === []) {
                return [];
            }

            // Estoque: soma por variação em TODOS os locais do business (uma query só).
            $estoques = DB::table('variation_location_details')
                ->whereIn('variation_id', array_keys($acc))
                ->groupBy('variation_id')
                ->select('variation_id', DB::raw('SUM(qty_available) as total'))
                ->pluck('total', 'variation_id');

            foreach ($acc as $vid => $_) {
                $acc[$vid]['estoque'] = (float) ($estoques[$vid] ?? 0);
            }

            $linhas = array_values($acc);

            // Ordem do §4.4: mais usados primeiro; empate desempata pelo maior peso.
            usort($linhas, fn ($a, $b) => [$b['n_receitas'], $b['maior_peso']] <=> [$a['n_receitas'], $a['maior_peso']]);

            return $linhas;
        }, [
            'module' => 'Manufacturing',
        ]);
    }

    /**
     * Custo total da receita COM o preço de um insumo alterado em `$variacaoPct`.
     *
     * Clona receita, ingredientes e a variação alvo (para não sujar o objeto do chamador),
     * aplica o preço novo e delega a `calculateCost()` — a fórmula continua sendo UMA só.
     */
    private function calculateCostComPrecoSimulado(MfgRecipe $recipe, int $variationId, float $variacaoPct): float
    {
        if ($variacaoPct == 0.0) {
            return $this->calculateCost($recipe);
        }

        $clone = clone $recipe;

        $ingredientes = $recipe->getRelation('ingredients')->map(function ($ingrediente) use ($variationId, $variacaoPct) {
            if ((int) $ingrediente->variation_id !== $variationId || empty($ingrediente->variation)) {
                return $ingrediente;
            }

            $copia = clone $ingrediente;
            $variacao = clone $ingrediente->variation;
            $variacao->dpp_inc_tax = (float) $ingrediente->variation->dpp_inc_tax * (1 + $variacaoPct / 100);
            $copia->setRelation('variation', $variacao);

            return $copia;
        });

        $clone->setRelation('ingredients', $ingredientes);

        return $this->calculateCost($clone);
    }

    /**
     * Receitas do tenant com o BOM carregado. Com `$comVariationId`, só as que usam aquele
     * insumo — evita carregar o catálogo inteiro pra montar um drawer.
     *
     * @return Collection<int, MfgRecipe>
     */
    private function recipesDoTenantComIngredientes(int $businessId, ?int $comVariationId = null): Collection
    {
        $query = MfgRecipe::query()
            ->join('variations as v', 'mfg_recipes.variation_id', '=', 'v.id')
            ->join('products as p', 'v.product_id', '=', 'p.id')
            ->where('p.business_id', $businessId)
            ->with([
                'ingredients',
                'ingredients.variation',
                'ingredients.variation.product',
                'ingredients.variation.product.unit',
                'ingredients.sub_unit',
            ])
            ->select('mfg_recipes.*', 'p.name as recipe_name', 'v.sub_sku as recipe_sku');

        if ($comVariationId !== null) {
            $query->whereExists(function ($sub) use ($comVariationId) {
                $sub->select(DB::raw(1))
                    ->from('mfg_recipe_ingredients as mri')
                    ->whereColumn('mri.mfg_recipe_id', 'mfg_recipes.id')
                    ->where('mri.variation_id', $comVariationId);
            });
        }

        return $query->get();
    }

    /**
     * Lista recipes do business em formato dropdown — wrapper sobre MfgRecipe::forDropdown()
     * com tipagem explícita pra DI em Controllers.
     *
     * Wave 27 — D9.a span observa hot-path do dropdown (forms Recipe + Production
     * carregam toda lista de receitas do business no select). Zero-cost OTel quando
     * `otel.enabled=false` (default Hostinger).
     *
     * @param  int  $businessId
     * @param  bool  $byVariationId  Se true, key = variation_id; senão key = recipe.id
     * @return Collection<string, string>
     */
    public function listForDropdown(int $businessId, bool $byVariationId = true): Collection
    {
        return OtelHelper::spanBiz('manufacturing.recipe.list_for_dropdown', function () use ($businessId, $byVariationId) {
            $recipes = MfgRecipe::forDropdown($businessId, $byVariationId);

            return collect($recipes->toArray());
        }, [
            'module'           => 'Manufacturing',
            'by_variation_id'  => $byVariationId,
        ]);
    }
}
