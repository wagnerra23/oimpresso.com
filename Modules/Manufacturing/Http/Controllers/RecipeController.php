<?php

namespace Modules\Manufacturing\Http\Controllers;

use App\Utils\BusinessUtil;
use App\Utils\ModuleUtil;
use App\Utils\TransactionUtil;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Modules\Manufacturing\Concerns\LogsWithPiiRedactor;
use Modules\Manufacturing\Entities\MfgIngredientGroup;
use Modules\Manufacturing\Entities\MfgRecipe;
use Modules\Manufacturing\Entities\MfgRecipeIngredient;
use Modules\Manufacturing\Http\Requests\StoreRecipeRequest;
use Modules\Manufacturing\Services\RecipeBomService;
use Modules\Manufacturing\Utils\ManufacturingUtil;

class RecipeController extends Controller
{
    use LogsWithPiiRedactor; // D7.a Wave 17 — wrap Log::emergency com PiiRedactor
    /**
     * All Utils instance.
     */
    protected $moduleUtil;

    protected $mfgUtil;

    protected $businessUtil;

    protected $transactionUtil;

    protected $recipeBomService;

    /**
     * Constructor
     *
     * @param  ProductUtils  $product
     * @return void
     */
    public function __construct(
        ModuleUtil $moduleUtil,
        ManufacturingUtil $mfgUtil,
        BusinessUtil $businessUtil,
        TransactionUtil $transactionUtil,
        RecipeBomService $recipeBomService
    ) {
        $this->moduleUtil = $moduleUtil;
        $this->mfgUtil = $mfgUtil;
        $this->businessUtil = $businessUtil;
        $this->transactionUtil = $transactionUtil;
        $this->recipeBomService = $recipeBomService;
    }

    /**
     * Lista de receitas — a tela React `Manufacturing/Recipes`.
     *
     * @return \Inertia\Response
     */
    public function index(\Modules\Manufacturing\Services\ProductionService $productionService)
    {
        $business_id = request()->session()->get('user.business_id');
        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'manufacturing_module')) || ! auth()->user()->can('manufacturing.access_recipe')) {
            abort(403, 'Unauthorized action.');
        }

        // CUTOVER 2026-10-27 ([W]: "1 PR por tela, com cutover apagando a Blade"): a tela Blade
        // antiga, o `?legacy=1` e o ramo DataTables que a alimentava saíram. Este endereço só
        // serve a tela React.

        $settings = $this->mfgUtil->getSettings($business_id) ?: [];

        // Contadores das abas (§4.1) e do KPI 4 (§4.2). Duas leituras distintas de
        // propósito: a aba diz "quantas ordens existem" (all-time), o KPI diz
        // "quanto se produziu no mês".
        $ordens = $productionService->summary($business_id);
        $mes = $productionService->monthSummary($business_id);

        // §9 — custo recalculado NO SERVIDOR a cada leitura, a partir do
        // `variations.dpp_inc_tax` de hoje. O cliente formata, não calcula.
        $todas = $this->recipeBomService->listRecipesWithCost($business_id);

        // Desde 2026-10-02 a lista pagina AQUI (playbook ds-atomos D-GRADE · ADR de design 0412
        // item 2 do handoff da Fabricação), pra a tela usar o `shared/DataTable` como o resto do
        // app. Muda ONDE se filtra, ordena e corta — o custo de cada receita é o mesmo
        // `presentRecipe` de antes, calculado sobre a lista inteira.
        $filtros = [
            'q'    => trim((string) request()->query('q', '')) ?: null,
            'cat'  => trim((string) request()->query('cat', '')) ?: null,
            // `custo` (desperdício ≥ 8%) saiu — decisão [W] 2026-10-23 (D4): desperdício é somente na Produção.
            'kpi'  => request()->query('kpi') === 'margem' ? 'margem' : null,
            'sort' => in_array(request()->query('sort'), RecipeBomService::ORDENAVEIS, true) ? request()->query('sort') : 'name',
            'dir'  => request()->query('dir') === 'desc' ? 'desc' : 'asc',
        ];
        $filtradas = $this->recipeBomService->filtrarOrdenar($todas, $filtros);

        $porPagina = 10; // R-07 — 10 por página, como o protótipo
        $ultima = max(1, (int) ceil(count($filtradas) / $porPagina));
        // Filtro que encolhe a lista não deixa a pessoa numa página que não existe mais.
        $pagina = min(max(1, (int) request()->query('page', 1)), $ultima);
        $recipes = (new LengthAwarePaginator(
            array_slice($filtradas, ($pagina - 1) * $porPagina, $porPagina),
            count($filtradas),
            $porPagina,
            $pagina,
            ['path' => request()->url()]
        ))->withQueryString();

        return Inertia::render('Manufacturing/Recipes', [
            'recipes' => $recipes,
            'filtros' => $filtros,
            // KPIs sobre TODAS as receitas, nunca sobre a página nem o filtro (§4.2).
            'kpis' => $this->recipeBomService->kpis($todas),
            // Categorias na ordem de chegada (nome do produto) — a mesma do `Set` que o cliente montava.
            'categorias' => array_values(array_unique(array_map(fn ($r) => $r['cat'], $todas))),
            // R-08 — "Selecionar todas" marca as FILTRADAS, não só as da página: a tela precisa dos ids.
            'ids_filtrados' => array_map(fn ($r) => $r['id'], $filtradas),
            // Fichas das receitas marcadas em OUTRAS páginas — só calculado no reload parcial que
            // a impressão pede (`only: ['fichas']`). Sai da mesma lista já isolada por tenant.
            'fichas' => Inertia::optional(function () use ($todas) {
                $ids = array_map('intval', array_filter(explode(',', (string) request()->query('fichas', ''))));

                return array_values(array_filter($todas, fn ($r) => in_array($r['id'], $ids, true)));
            }),
            // "Copiar de outra receita" da janela Nova receita — só no reload parcial que a janela
            // pede ao abrir (`only: ['receitas_copia']`). Mesma lista do modal antigo, já da empresa.
            'receitas_copia' => Inertia::optional(fn () => MfgRecipe::forDropdown($business_id, false)
                ->map(fn ($nome, $id) => ['id' => (int) $id, 'nome' => (string) $nome])
                ->sortBy('nome', SORT_NATURAL | SORT_FLAG_CASE)
                ->values()),
            'permissions' => [
                'criar'  => auth()->user()->can('manufacturing.add_recipe'),
                'editar' => auth()->user()->can('manufacturing.edit_recipe'),
                'prod'   => auth()->user()->can('manufacturing.access_production'),
            ],
            'producao' => [
                'total'       => (int) $ordens['total_count'],
                'rascunhos'   => (int) $ordens['pending_count'],
                'mes_final'   => (int) $mes['final_count'],
                'mes_rascunho' => (int) $mes['draft_count'],
            ],
            'settings' => [
                // §16 — chave real do JSON `business.manufacturing_settings`.
                'enable_updating_product_price' => ! empty($settings['enable_updating_product_price']),
            ],
        ]);
    }

    /**
     * MWART US-MANU-005 (SPEC.md) — Insumos: impacto reverso + simulador de preço.
     * Rota ADITIVA `/manufacturing/v2/insumos`. 100% leitura.
     *
     * O `variacao_pct` do slider vem por query string e é CLAMPADO aqui (-30..60, passo 5 é
     * do front) — nunca confio no range que o cliente manda ({@see RUNBOOK-insumos.md}).
     */
    public function insumos(\Modules\Manufacturing\Services\ProductionService $productionService)
    {
        $business_id = request()->session()->get('user.business_id');
        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'manufacturing_module')) || ! auth()->user()->can('manufacturing.access_recipe')) {
            abort(403, 'Unauthorized action.');
        }

        $variationId = request()->integer('insumo') ?: null;

        // Clamp do intervalo do §4.4 (-30%..+60%). Valor fora da faixa é recortado, não aceito.
        $pct = (float) request()->input('variacao_pct', 10);
        $pct = max(-30.0, min(60.0, $pct));

        $ordens = $productionService->summary($business_id);

        return Inertia::render('Manufacturing/Insumos', [
            'insumos' => $this->recipeBomService->listInsumosComUso($business_id),
            'selecionado' => $variationId,
            'usos' => $variationId
                ? $this->recipeBomService->usosDoInsumo($variationId, $business_id, $pct)
                : [],
            'variacao_pct' => $pct,
            'metodo_custo' => $variationId ? $this->metodoCustoDoInsumo($business_id, $variationId) : null,
            'permissions' => [
                'prod' => auth()->user()->can('manufacturing.access_production'),
            ],
            'producao' => [
                'total' => (int) $ordens['total_count'],
                'rascunhos' => (int) $ordens['pending_count'],
            ],
            'recipes_count' => $this->recipeBomService->countRecipes($business_id),
        ]);
    }

    /**
     * US-COM-024 · UC-FLX-38 — o método de custo do insumo, para a gaveta de Insumos. O dado é do
     * módulo Compras e chega pela interface do núcleo; sem o módulo ligado a seção não aparece
     * (`null`). Gravar é `PUT /compras/insumos/{variation}/metodo-custo`, com `purchase.update`.
     *
     * @return array{atual: ?string, empresa: string, editavel: bool}|null
     */
    private function metodoCustoDoInsumo(int $business_id, int $variationId): ?array
    {
        if (! app()->bound(\App\Contracts\Compras\CustoDoInsumo::class)) {
            return null;
        }
        $custo = app(\App\Contracts\Compras\CustoDoInsumo::class);

        return [
            'atual' => $custo->metodoDoInsumo($business_id, $variationId),
            'empresa' => $custo->metodoDaEmpresa($business_id),
            'editavel' => auth()->user()->can('purchase.update'),
        ];
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(StoreRecipeRequest $request)
    {
        // Permissoes manufacturing_module + add_recipe validadas em StoreRecipeRequest::authorize().
        $business_id = request()->session()->get('user.business_id');

        try {
            // Tier 0 (ADR 0093): os ids do POST (produto, ingredientes, linhas) só valem se forem da
            // empresa da sessão — a validação (`exists:variations,id`) não olha a empresa. E tudo
            // grava junto: recusado um ingrediente no meio, o cabeçalho não fica pela metade.
            DB::beginTransaction();

            $input = $request->only(['variation_id', 'ingredients', 'total', 'instructions',
                'ingredients_cost', 'waste_percent', 'total_quantity', 'extra_cost', 'production_cost_type', ]);
            // `ingredients` é obrigatório desde o StoreRecipeRequest (§5 regra 1); o `if` fica pelo
            // caminho que não passa pelo FormRequest.
            if (! empty($input['ingredients'])) {
                $variation = $this->recipeBomService->variacaoDaEmpresa((int) $input['variation_id'], (int) $business_id, ['product']);

                $sub_unit_saida = ! empty($request->input('sub_unit_id')) ? (int) $request->input('sub_unit_id') : null;
                $this->exigirSubUnidadeDoProduto($sub_unit_saida, (int) $variation->product->unit_id, (int) $business_id, 'sub_unit_id');

                // `final_price` e `ingredients_cost` NÃO vêm do POST (handoff §9): o servidor
                // recalcula depois de gravar os ingredientes, logo abaixo. O 0 aqui só existe porque
                // `final_price` é NOT NULL sem default.
                $recipe = MfgRecipe::updateOrCreate(
                    [
                        'variation_id' => $input['variation_id'],
                    ],
                    [
                        'product_id' => $variation->product_id,
                        'final_price' => 0,
                        'ingredients_cost' => 0,
                        'waste_percent' => $this->moduleUtil->num_uf($input['waste_percent']),
                        'total_quantity' => $this->moduleUtil->num_uf($input['total_quantity']),
                        'extra_cost' => $this->moduleUtil->num_uf($input['extra_cost']),
                        'production_cost_type' => $input['production_cost_type'],
                        'instructions' => $input['instructions'],
                        'sub_unit_id' => $sub_unit_saida,
                    ]
                );

                $ingredients = [];
                $edited_ingredients = [];
                $ingredient_groups = $request->input('ingredient_groups');
                $ingredient_group_descriptions = $request->input('ingredient_group_description');
                $created_ig_groups = [];

                foreach ($input['ingredients'] as $key => $value) {
                    $variation = $this->recipeBomService->variacaoDaEmpresa((int) $value['ingredient_id'], (int) $business_id, ['product']);

                    if (! empty($value['ingredient_line_id'])) {
                        // Só linha DESTA receita — que já é da empresa (variacaoDaEmpresa acima).
                        $ingredient = MfgRecipeIngredient::where('mfg_recipe_id', $recipe->id)
                                                ->findOrFail($value['ingredient_line_id']);
                        $edited_ingredients[] = $ingredient->id;
                    } else {
                        $ingredient = new MfgRecipeIngredient(['variation_id' => $value['ingredient_id']]);
                    }

                    $ingredient->quantity = $this->moduleUtil->num_uf($value['quantity']);
                    $ingredient->waste_percent = $this->moduleUtil->num_uf($value['waste_percent']);
                    $ingredient->sort_order = $this->moduleUtil->num_uf($value['sort_order']);

                    // §9: a sub-unidade tem de ser uma das que a tela oferece para ESTE insumo — o
                    // `base_unit_multiplier` de outra unidade multiplicaria o custo por um número qualquer.
                    $sub_unit_linha = ! empty($value['sub_unit_id']) ? (int) $value['sub_unit_id'] : null;
                    $this->exigirSubUnidadeDoProduto($sub_unit_linha, (int) $variation->product->unit_id, (int) $business_id, "ingredients.{$key}.sub_unit_id");
                    $ingredient->sub_unit_id = ! empty($sub_unit_linha) && $sub_unit_linha != $variation->product->unit_id ? $sub_unit_linha : null;

                    //Set ingredient group
                    if (isset($value['ig_index'])) {
                        $ig_name = $ingredient_groups[$value['ig_index']];
                        $ig_description = $ingredient_group_descriptions[$value['ig_index']];

                        // Grupo que só ESTA receita usa → mantém e renomeia. Qualquer outro (o da
                        // receita copiada, o compartilhado por cópias antigas, o de outra empresa)
                        // → grupo novo. Antes, a cópia renomeava o grupo da original ([W] 2026-10-06).
                        $grupo_proprio = empty($value['mfg_ingredient_group_id']) ? null
                            : $this->recipeBomService->grupoProprioDaReceita((int) $value['mfg_ingredient_group_id'], (int) $recipe->id, (int) $business_id);

                        //Create ingredient group if not created already
                        if (! empty($created_ig_groups[$value['ig_index']])) {
                            $ingredient_group = $created_ig_groups[$value['ig_index']];
                        } elseif ($grupo_proprio === null) {
                            $ingredient_group = MfgIngredientGroup::create(
                                [
                                    'name' => $ig_name,
                                    'business_id' => $business_id,
                                    'description' => $ig_description,
                                ]
                            );
                        } else {
                            $ingredient_group = MfgIngredientGroup::where('business_id', $business_id)
                                                                ->findOrFail($grupo_proprio);
                            if ($ingredient_group->name != $ig_name || $ingredient_group->description != $ig_description) {
                                $ingredient_group->name = $ig_name;
                                $ingredient_group->description = $ig_description;
                                $ingredient_group->save();
                            }
                        }

                        $created_ig_groups[$value['ig_index']] = $ingredient_group;

                        $ingredient->mfg_ingredient_group_id = $ingredient_group->id;
                    }

                    $ingredients[] = $ingredient;
                }
                // A receita passa a ter exatamente as linhas do formulário. Antes isto só rodava
                // quando alguma linha antiga era mantida: trocar TODOS os ingredientes deixava os
                // antigos gravados junto com os novos (e somando no custo).
                MfgRecipeIngredient::where('mfg_recipe_id', $recipe->id)
                                            ->whereNotIn('id', $edited_ingredients)
                                            ->delete();

                $recipe->ingredients()->saveMany($ingredients);

                // Handoff §9: o custo gravado é o do servidor, com o preço de compra de hoje —
                // a mesma conta de `RecipeBomService::calculateCost`, que a tela de Receitas usa.
                $recipe->load(['ingredients.variation', 'ingredients.sub_unit']);
                $recipe->ingredients_cost = $this->recipeBomService->custoDosIngredientes($recipe);
                $recipe->final_price = $this->recipeBomService->calculateCost($recipe);
                $recipe->save();
            }
            DB::commit();

            $output = ['success' => 1,
                'msg' => __('lang_v1.added_success'),
            ];
        } catch (ValidationException $e) {
            // Sub-unidade recusada: volta ao formulário dizendo o porquê, sem gravar nada.
            DB::rollBack();
            session()->flash('status', ['success' => 0, 'msg' => collect($e->errors())->flatten()->first()]);

            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->logSafeEmergency('recipe', $e); // D7.a Wave 17 LGPD

            $output = ['success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return redirect()->action([\Modules\Manufacturing\Http\Controllers\RecipeController::class, 'index'])->with('status', $output);
    }

    /**
     * Sub-unidade aceita = uma das que a janela de ingredientes oferece para o produto
     * (`getSubUnits` da unidade dele, o mesmo do antigo `getIngredientRow`, apagado em 2026-10-27). Vazio é a unidade base.
     *
     * @throws ValidationException
     */
    private function exigirSubUnidadeDoProduto(?int $subUnitId, int $unitId, int $businessId, string $campo): void
    {
        if (empty($subUnitId) || $subUnitId === $unitId) {
            return;
        }

        if (! array_key_exists($subUnitId, $this->moduleUtil->getSubUnits($businessId, $unitId))) {
            throw ValidationException::withMessages([
                $campo => 'A unidade escolhida não é uma sub-unidade deste produto.',
            ]);
        }
    }

    /**
     * Editor de ingredientes — a tela React `Manufacturing/IngredientesEditor`.
     *
     * @return \Inertia\Response
     */
    public function addIngredients()
    {
        $business_id = request()->session()->get('user.business_id');

        // Desde o cutover de 2026-10-27 o editor React (US-MANU-006) é a única tela deste endereço.
        // `?tela=nova` segue aceito (links antigos) e não muda nada. Abrir pede `access_recipe`;
        // gravar continua exigindo `add_recipe` no `store()` (handoff §5 regra 3).
        return $this->telaEditor((int) $business_id);
    }

    /**
     * Retrieves selected recipe details for production.
     *
     * @return Response
     */
    public function getRecipeDetails()
    {
        $business_id = request()->session()->get('user.business_id');
        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'manufacturing_module')) || ! auth()->user()->can('manufacturing.access_recipe')) {
            abort(403, 'Unauthorized action.');
        }

        $variation_id = request()->input('variation_id');
        $location_id = request()->input('location_id');

        // Tier 0 (ADR 0093): antes buscava a receita só pelo variation_id, e quem tinha
        // access_recipe lia ingredientes e custo da receita de outra empresa (PARIDADE H2).
        // A receita só é achada pela cadeia variations → products da empresa da sessão; de
        // outra empresa (ou inexistente, que antes estourava 500 mais abaixo) é 404.
        // OrdemReceitaEmpresaTest.
        $recipe = MfgRecipe::query()
                        ->forBusinessViaProductChain((int) $business_id)
                        ->where('mfg_recipes.variation_id', $variation_id)
                        ->with(['variation', 'variation.product', 'variation.product.unit', 'sub_unit',
                            'ingredients' => function ($query) {
                                $query->orderBy('sort_order', 'asc');
                            }, ])
                        ->first();
        if ($recipe === null) {
            abort(404);
        }

        $ingredients_array = [];

        foreach ($recipe->ingredients as $ingredient) {
            $ingredient_quantity = $this->mfgUtil->calc_percentage($ingredient->quantity, $ingredient->waste_percent, $ingredient->quantity);
            $ingredients_array[$ingredient->variation_id] = [
                'quantity' => $ingredient_quantity,
                'sub_unit_id' => $ingredient->sub_unit_id,
                'waste_percent' => $ingredient->waste_percent,
            ];
        }
        $ingredients = $this->mfgUtil->getIngredientDetails($recipe, $business_id, $location_id);
        $business_details = $this->businessUtil->getDetails($business_id);
        $pos_settings = empty($business_details->pos_settings) ? $this->businessUtil->defaultPosSettings() : json_decode($business_details->pos_settings, true);

        $manufacturing_settings = $this->mfgUtil->getSettings($business_id);

        $ingredient_table = view('manufacturing::recipe.ingredients_for_production', compact('ingredients', 'recipe', 'pos_settings', 'manufacturing_settings'))->render();

        $sub_units = $this->moduleUtil->getSubUnits($business_id, $recipe->variation->product->unit->id);

        $unit_html = $recipe->variation->product->unit->short_name;
        $is_sub_unit = 0;
        $unit_name = $unit_html;
        if (! empty($sub_units)) {
            $unit_html = '<select name="sub_unit_id" class="form-control" id="sub_unit_id">';
            foreach ($sub_units as $key => $value) {
                $unit_html .= '<option value="'.$key.'" data-multiplier="'.$value['multiplier'].'" data-unit_name="'.$value['name'].'" ';
                if (! empty($recipe->sub_unit_id) && $recipe->sub_unit_id == $key) {
                    $unit_html .= ' selected ';
                    $unit_name = $value['name'];
                }
                $unit_html .= '>'.$value['name'].'</option>';
            }
            $unit_html .= '</select>';
            $is_sub_unit = 1;
        }

        return json_encode([
            'ingredient_table' => $ingredient_table,
            'recipe' => $recipe,
            'unit_html' => $unit_html,
            'is_sub_unit' => $is_sub_unit,
            'unit_name' => $unit_name,
        ]);
    }

    /**
     * Function to update variation prices from recipe unit price.
     *
     * @param  Request  $request
     * @return Response
     */
    public function updateRecipeProductPrices(Request $request)
    {
        $business_id = request()->session()->get('user.business_id');
        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'manufacturing_module')) || ! auth()->user()->can('manufacturing.add_recipe')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $recipe_ids = $request->input('recipe_ids');
            $unit_prices = $request->input('unit_prices');

            if (! empty($recipe_ids)) {
                $recipes = MfgRecipe::with(['variation', 'sub_unit', 'variation.product', 'variation.product.product_tax'])
                                ->whereIn('id', $recipe_ids)
                                ->get();

                DB::beginTransaction();
                foreach ($recipes as $recipe) {
                    $variation = $recipe->variation;
                    $unit_price = $unit_prices[$recipe->id];

                    //Calculate unit price in base unit
                    if (! empty($recipe->sub_unit->base_unit_multiplier)) {
                        $unit_price = $unit_price / $recipe->sub_unit->base_unit_multiplier;
                    }

                    $unit_price_exc_tax = $unit_price;

                    if (! empty($variation->product->product_tax)) {
                        $tax_percent = $variation->product->product_tax->amount;
                        $unit_price_exc_tax = $this->transactionUtil->calc_percentage_base($unit_price, $tax_percent);
                    }
                    $variation->default_purchase_price = $unit_price_exc_tax;
                    $variation->dpp_inc_tax = $unit_price;

                    //Keep sell price constant and change profit margin
                    $profit_margin = $this->transactionUtil->get_percent($unit_price, $variation->sell_price_inc_tax);
                    $sell_price_excluding_tax = $this->transactionUtil->calc_percentage($unit_price_exc_tax, $profit_margin, $unit_price_exc_tax);

                    $variation->default_sell_price = $sell_price_excluding_tax;
                    $variation->profit_percent = $profit_margin;
                    $variation->save();
                }
                $output = ['success' => 1,
                    'msg' => __('lang_v1.updated_succesfully'),
                ];
                DB::commit();
            }
        } catch (\Exception $e) {
            DB::rollBack();
            $this->logSafeEmergency('recipe', $e); // D7.a Wave 17 LGPD

            $output = ['success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return $output;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $business_id = request()->session()->get('user.business_id');
        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'manufacturing_module')) || ! auth()->user()->can('manufacturing.add_recipe')) {
            abort(403, 'Unauthorized action.');
        }

        // Tier 0 (ADR 0093): antes apagava pelo id de qualquer empresa. A receita só é achada pela
        // cadeia variations → products da empresa da sessão; de outra empresa (ou inexistente) é
        // 404. Os ingredientes saem junto pelo FK cascade. ExcluirReceitaEmpresaTest.
        $recipe = MfgRecipe::query()
                        ->forBusinessViaProductChain((int) $business_id)
                        ->where('mfg_recipes.id', $id)
                        ->first();
        if ($recipe === null) {
            abort(404);
        }

        try {
            $recipe->delete();

            $output = ['success' => 1,
                'msg' => __('lang_v1.deleted_success'),
            ];
        } catch (\Exception $e) {
            $this->logSafeEmergency('recipe', $e); // D7.a Wave 17 LGPD

            $output = ['success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return $output;
    }

    /**
     * Check if recipe exist.
     *
     * @param  int  $variation_id
     */
    public function isRecipeExist($variation_id)
    {
        // Tier 0 (ADR 0093): antes respondia por qualquer variação, de qualquer empresa.
        $business_id = (int) request()->session()->get('user.business_id');
        $exists = MfgRecipe::query()
                            ->forBusinessViaProductChain($business_id)
                            ->where('mfg_recipes.variation_id', $variation_id)
                            ->exists();

        $output = $exists ? 1 : 0;

        return $output;
    }

    /**
     * Busca de produto da janela "Nova receita" da tela React (US-MANU-006, decisões [W]
     * 2026-10-06). JSON: produto, Categoria/Subcategoria do produto (só para exibir) e a receita
     * que ele já tem, se tiver. A janela só LÊ; quem grava continua sendo o editor legado
     * (`/add-ingredient` → `store()`), como o charter da tela manda.
     */
    /**
     * Editor de ingredientes React (US-MANU-006, handoff Fabricação §5). Só monta a tela: os dados
     * saem de `RecipeBomService::editorDaReceita` e o salvar segue sendo o `store()`.
     */
    private function telaEditor(int $business_id): \Inertia\Response
    {
        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'manufacturing_module')) || ! auth()->user()->can('manufacturing.access_recipe')) {
            abort(403, 'Unauthorized action.');
        }

        $copiar = request()->query('copy_recipe_id');
        $dados = $this->recipeBomService->editorDaReceita(
            (int) request()->query('variation_id'),
            $business_id,
            ! empty($copiar) ? (int) $copiar : null
        );
        $settings = $this->mfgUtil->getSettings($business_id) ?: [];

        // Sem `Inertia::defer` de propósito: estes props SÃO o formulário. O `useForm` da tela copia
        // os props uma vez ao montar (handoff §5 regra 5 — a cópia); adiados, ele nasceria vazio.
        return Inertia::render('Manufacturing/IngredientesEditor', $dados + [
            // Regra 3: "editar" = poder GRAVAR, e quem grava é o `store()`, que exige `add_recipe`
            // (o `edit_recipe` não tem rota que o alcance — R-MANU-004).
            'perms' => ['editar' => auth()->user()->can('manufacturing.add_recipe')],
            // Regra 2: a mesma configuração trava a quantidade aqui e na ordem de produção.
            'travar_qtd' => ! empty($settings['disable_editing_ingredient_qty']),
        ]);
    }

    /** Busca de insumo do editor React — até 7 resultados (handoff §5). */
    public function insumosParaReceita(): JsonResponse
    {
        $business_id = (int) request()->session()->get('user.business_id');
        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'manufacturing_module')) || ! auth()->user()->can('manufacturing.add_recipe')) {
            abort(403, 'Unauthorized action.');
        }

        $termo = mb_substr(trim((string) request()->query('q', '')), 0, 80);

        return response()->json([
            'insumos' => $this->recipeBomService->buscarInsumos($termo, $business_id),
        ]);
    }

    public function produtosNovaReceita(): JsonResponse
    {
        $business_id = (int) request()->session()->get('user.business_id');
        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'manufacturing_module')) || ! auth()->user()->can('manufacturing.add_recipe')) {
            abort(403, 'Unauthorized action.');
        }

        $termo = mb_substr(trim((string) request()->query('q', '')), 0, 80);

        return response()->json([
            'produtos' => $this->recipeBomService->buscarProdutosParaReceita($termo, $business_id),
        ]);
    }
}
