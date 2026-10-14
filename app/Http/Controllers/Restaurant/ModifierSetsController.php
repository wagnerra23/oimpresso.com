<?php

namespace App\Http\Controllers\Restaurant;

use App\Product;
use App\Services\FeatureFlagService;
use App\Utils\ProductUtil;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ModifierSetsController extends Controller
{
    /**
     * Flag do caminho React (thread restaurante/playbook/02, F3). Convenção `useV2<Modulo><Tela>`.
     * Sem a chave no GrowthBook o FeatureFlagService cai no fallbackDefaults, que não a lista:
     * default OFF, a Blade segue servindo. Ligar é toggle no GrowthBook (flag:set --biz=1), não deploy.
     *
     * @see memory/requisitos/Restaurante/RUNBOOK-modificadores.md
     */
    private const FLAG_V2 = 'useV2RestauranteModificadores';

    /**
     * All Utils instance.
     */
    protected $productUtil;

    /**
     * Constructor
     *
     * @param  ProductUtils  $product
     * @return void
     */
    public function __construct(ProductUtil $productUtil)
    {
        $this->productUtil = $productUtil;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Contracts\View\View|\Inertia\Response
     */
    public function index()
    {
        // Mesmo predicado do `ProductController::index()` e do link no sidebar
        // (`AdminSidebarMenu` L906) — modificador É linha de `products` (`type = 'modifier'`),
        // e a lista principal desse mesmo dado já exige isto. Só `product.view` seria
        // MAIS ESTREITO que os dois: `product.view` e `product.create` são checkboxes
        // independentes na tela de papéis, e quem tem só `product.create` recebe o link
        // no menu, cria modificador (`create()`/`store()` L91/L107) e o botão Adicionar —
        // ficaria sem enxergar a lista que acabou de alimentar. É a classe A do `kb.ai`
        // (US-GOV-059): esconder de quem TEM a permissão o que o endpoint libera.
        if (! auth()->user()->can('product.view') && ! auth()->user()->can('product.create')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        // `?classico=1` mantém a Blade alcançável com a flag ligada: cadastrar, editar, ligar produtos e excluir seguem
        // nos modais dela.
        if (! request()->boolean('classico') && (! request()->ajax() || request()->inertia())
            && app(FeatureFlagService::class)->isOn(self::FLAG_V2, ['business_id' => $business_id])) {
            return Inertia::render('Restaurante/Modificadores/Index', [
                'conjuntos' => Inertia::defer(fn () => $this->conjuntosDoNegocio((int) $business_id)),
                'pode_gerenciar' => auth()->user()->can('product.update') || auth()->user()->can('product.delete')
                    || auth()->user()->can('product.create'),
            ]);
        }

        // `! inertia()`: o Inertia v3 manda `X-Requested-With` em toda visita; sem esta perna o partial reload
        // da prop adiada caía no JSON da DataTable (§5 2026-09-08, molde AccountController::index).
        if (request()->ajax() && ! request()->inertia()) {
            $modifer_set = Product::where('business_id', $business_id)
                            ->where('type', 'modifier')
                            ->with(['variations', 'modifier_products']);

            return \Datatables::of($modifer_set)
                ->addColumn(
                    'action',
                    '
                    @can("product.update")
                        <button type="button" data-href="{{action(\'App\Http\Controllers\Restaurant\ModifierSetsController@edit\', [$id])}}" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-primary edit_modifier_button" data-container=".modifier_modal"><i class="glyphicon glyphicon-edit"></i> @lang("messages.edit")</button>
                        &nbsp;
                        <button type="button" data-href="{{action(\'App\Http\Controllers\Restaurant\ProductModifierSetController@edit\', [$id])}}" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-info edit_modifier_button" data-container=".modifier_modal"><i class="fa fa-cubes"></i> @lang("restaurant.manage_products")</button>
                    &nbsp;
                    @endcan

                    @can("product.delete")
                        <button data-href="{{action(\'App\Http\Controllers\Restaurant\ModifierSetsController@destroy\', [$id])}}" class="tw-dw-btn tw-dw-btn-outline tw-dw-btn-xs tw-dw-btn-error delete_modifier_button"><i class="glyphicon glyphicon-trash"></i> @lang("messages.delete")</button>
                    @endcan
                    '
                )
                ->editColumn('modifier_products', function ($row) {
                    $products = [];
                    foreach ($row->modifier_products as $product) {
                        $products[] = $product->name;
                    }

                    return implode(',  ', $products);
                })
                ->editColumn('variations', function ($row) {
                    $modifiers = [];
                    foreach ($row->variations as $modifier) {
                        $modifiers[] = $modifier->name;
                    }

                    return implode(', ', $modifiers);
                })
                ->removeColumn('id')
                ->escapeColumns(['action'])
                ->make(true);
        }

        return view('restaurant.modifier_sets.index');
    }

    /**
     * Conjuntos de modificadores do negócio para a tela React: as mesmas colunas da DataTable (conjunto, nomes dos
     * modificadores, produtos ligados). Sem preço — a Blade não mostra, e exibir valor é decisão [W].
     *
     * O pivô `res_product_modifier_sets` não tem `business_id`: os produtos ligados são filtrados pelo negócio.
     *
     * @return list<array<string, mixed>>
     */
    private function conjuntosDoNegocio(int $business_id): array
    {
        $conjuntos = Product::where('business_id', $business_id)
            ->where('type', 'modifier')
            ->orderBy('name')
            ->get(['id', 'name']);
        $ids = $conjuntos->pluck('id')->all();

        // Consultas diretas no lugar do eager-load das relações `variations`/`modifier_products`: mesmo resultado, e
        // o Larastan não resolve essas relações do Product.
        $modificadores = DB::table('variations')
            ->whereIn('product_id', $ids)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get(['product_id', 'name'])
            ->groupBy('product_id');
        $produtos = DB::table('res_product_modifier_sets as pms')
            ->join('products as p', 'p.id', '=', 'pms.product_id')
            ->whereIn('pms.modifier_set_id', $ids)
            ->where('p.business_id', $business_id)
            ->orderBy('p.name')
            ->get(['pms.modifier_set_id', 'p.name'])
            ->groupBy('modifier_set_id');

        return $conjuntos
            ->map(fn (Product $c) => [
                'id' => $c->id,
                'nome' => $c->name,
                'modificadores' => collect($modificadores->get($c->id, []))->pluck('name')->values()->all(),
                'produtos' => collect($produtos->get($c->id, []))->pluck('name')->values()->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        if (! auth()->user()->can('product.create')) {
            abort(403, 'Unauthorized action.');
        }

        return view('restaurant.modifier_sets.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(Request $request)
    {
        try {
            if (! auth()->user()->can('product.create')) {
                abort(403, 'Unauthorized action.');
            }

            $input = $request->all();
            $business_id = $request->session()->get('user.business_id');
            $user_id = $request->session()->get('user.id');

            $modifer_set_data = [
                'name' => $input['name'],
                'type' => 'modifier',
                'sku' => ' ',
                'tax_type' => 'inclusive',
                'business_id' => $business_id,
                'created_by' => $user_id,
            ];

            DB::beginTransaction();
            $modifer_set = Product::create($modifer_set_data);

            $sku = $this->productUtil->generateProductSku($modifer_set->id);
            $modifer_set->sku = $sku;
            $modifer_set->save();

            $modifers = [];
            foreach ($input['modifier_name'] as $key => $value) {
                $modifers[] = [
                    'value' => $value,
                    'default_purchase_price' => $input['modifier_price'][$key],
                    'dpp_inc_tax' => $input['modifier_price'][$key],
                    'profit_percent' => 0,
                    'default_sell_price' => $input['modifier_price'][$key],
                    'sell_price_inc_tax' => $input['modifier_price'][$key],
                ];
            }

            $modifiers_data = [];
            $modifiers_data[] = [
                'name' => 'DUMMY',
                'variations' => $modifers,
            ];
            // with_out_variation is sku type of variation
            $this->productUtil->createVariableProductVariations($modifer_set->id, $modifiers_data, "with_out_variation",);

            DB::commit();

            $output = ['success' => 1, 'msg' => __('lang_v1.added_success')];
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => 0, 'msg' => __('messages.something_went_wrong')];
        }

        return $output;
    }

    /**
     * Show the specified resource.
     *
     * @return Response
     */
    public function show()
    {
        return view('restaurant.modifier_sets.show');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @return Response
     */
    public function edit($id, Request $request)
    {
        if (! auth()->user()->can('product.update')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $business_id = $request->session()->get('user.business_id');

            $modifer_set = Product::where('business_id', $business_id)
                            ->where('id', $id)
                            ->with(['variations'])
                            ->first();

            return view('restaurant.modifier_sets.edit')
                ->with(compact('modifer_set'));
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => 0, 'msg' => __('messages.something_went_wrong')];
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function update($id, Request $request)
    {
        if (! auth()->user()->can('product.update')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            DB::beginTransaction();

            $input = $request->all();
            $business_id = $request->session()->get('user.business_id');
            $user_id = $request->session()->get('user.id');

            $modifer_set = Product::where('business_id', $business_id)
                    ->where('id', $id)
                    ->where('type', 'modifier')
                    ->first();
            $modifer_set->update(['name' => $input['name']]);

            //Get the dummy product variation
            $product_variation = $modifer_set->product_variations()->first();

            $modifiers_data[$product_variation->id]['name'] = $product_variation->name;

            $variations_edit = [];
            $variations = [];

            //Set existing variations
            if (! empty($input['modifier_name_edit'])) {
                $modifier_name_edit = $input['modifier_name_edit'];
                $modifier_price_edit = $input['modifier_price_edit'];

                foreach ($modifier_name_edit as $key => $name) {
                    if (isset($modifier_price_edit[$key])) {
                        $variations_edit[$key]['value'] = $name;
                        $variations_edit[$key]['default_purchase_price'] = $modifier_price_edit[$key];
                        $variations_edit[$key]['dpp_inc_tax'] = $modifier_price_edit[$key];
                        $variations_edit[$key]['default_sell_price'] = $modifier_price_edit[$key];
                        $variations_edit[$key]['sell_price_inc_tax'] = $modifier_price_edit[$key];
                        $variations_edit[$key]['profit_percent'] = 0;
                    }
                }
            }
            //Set new variations
            if (! empty($input['modifier_name'])) {
                foreach ($input['modifier_name'] as $key => $value) {
                    $variations[] = [
                        'value' => $value,
                        'default_purchase_price' => $input['modifier_price'][$key],
                        'dpp_inc_tax' => $input['modifier_price'][$key],
                        'profit_percent' => 0,
                        'default_sell_price' => $input['modifier_price'][$key],
                        'sell_price_inc_tax' => $input['modifier_price'][$key],
                    ];
                }
            }

            //Update variations
            $modifiers_data[$product_variation->id]['variations_edit'] = $variations_edit;
            $modifiers_data[$product_variation->id]['variations'] = $variations;
            $this->productUtil->updateVariableProductVariations($modifer_set->id, $modifiers_data);

            DB::commit();

            $output = ['success' => 1, 'msg' => __('lang_v1.updated_success')];
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => 0, 'msg' => __('messages.something_went_wrong')];
        }

        return $output;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @return Response
     */
    public function destroy($id, Request $request)
    {
        if (! auth()->user()->can('product.delete')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            DB::beginTransaction();
            $business_id = $request->session()->get('user.business_id');

            Product::where('business_id', $business_id)
                ->where('type', 'modifier')
                ->where('id', $id)
                ->delete();

            DB::commit();

            $output = ['success' => 1, 'msg' => __('lang_v1.deleted_success')];
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => 0, 'msg' => __('messages.something_went_wrong')];
        }

        return $output;
    }
}
