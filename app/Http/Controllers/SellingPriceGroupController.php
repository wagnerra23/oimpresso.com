<?php

namespace App\Http\Controllers;

use App\SellingPriceGroup;
use App\Utils\Util;
use App\Variation;
use App\VariationGroupPrice;
use DB;
use Excel;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Permission\Models\Permission;
use Yajra\DataTables\Facades\DataTables;

class SellingPriceGroupController extends Controller
{
    /**
     * All Utils instance.
     */
    protected $commonUtil;

    /**
     * Constructor
     *
     * @param  ProductUtils  $product
     * @return void
     */
    public function __construct(Util $commonUtil)
    {
        $this->commonUtil = $commonUtil;
    }

    /**
     * Display a listing of the resource.
     *
     * @return mixed Inertia (tela Produto/Cadastros) · JSON do DataTables · view clássica
     */
    public function index()
    {
        if (! auth()->user()->can('product.create')) {
            abort(403, 'Unauthorized action.');
        }

        // Playbook Produto · thread 03: a visita abre Produto/Cadastros nesta aba. Inertia manda
        // `X-Requested-With` junto do `X-Inertia` (§5 2026-09-08); `?classico=1` mantém a Blade e os modais.
        if (! request()->boolean('classico') && (! request()->ajax() || request()->header('X-Inertia'))) {
            return Inertia::render('Produto/Cadastros/Index', app(UnitController::class)->propsCadastros('grupos'));
        }

        if (request()->ajax()) {
            $business_id = request()->session()->get('user.business_id');

            $price_groups = SellingPriceGroup::where('business_id', $business_id)
                        ->select(['name', 'description', 'id', 'is_active']);

            return Datatables::of($price_groups)
                ->addColumn(
                    'action',
                    '<button data-href="{{action(\'App\Http\Controllers\SellingPriceGroupController@edit\', [$id])}}" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-primary btn-modal" data-container=".view_modal"><i class="glyphicon glyphicon-edit"></i> @lang("messages.edit")</button>
                        &nbsp;
                        <button data-href="{{action(\'App\Http\Controllers\SellingPriceGroupController@destroy\', [$id])}}" class="tw-dw-btn tw-dw-btn-outline tw-dw-btn-xs tw-dw-btn-error delete_spg_button"><i class="glyphicon glyphicon-trash"></i> @lang("messages.delete")</button>
                        &nbsp;
                        <button data-href="{{action(\'App\Http\Controllers\SellingPriceGroupController@activateDeactivate\', [$id])}}" class="tw-dw-btn tw-dw-btn-outline tw-dw-btn-xs  @if($is_active) tw-dw-btn-error @else tw-dw-btn-success @endif activate_deactivate_spg"><i class="fas fa-power-off"></i> @if($is_active) @lang("messages.deactivate") @else @lang("messages.activate") @endif</button>'
                )
                ->removeColumn('is_active')
                ->removeColumn('id')
                ->rawColumns([2])
                ->make(false);
        }

        return view('selling_price_group.index');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        if (! auth()->user()->can('product.create')) {
            abort(403, 'Unauthorized action.');
        }

        return view('selling_price_group.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if (! auth()->user()->can('product.create')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $input = $request->only(['name', 'description']);
            $business_id = $request->session()->get('user.business_id');
            $input['business_id'] = $business_id;

            $spg = SellingPriceGroup::create($input);

            //Create a new permission related to the created selling price group
            Permission::create(['name' => 'selling_price_group.'.$spg->id]);

            $output = ['success' => true,
                'data' => $spg,
                'msg' => __('lang_v1.added_success'),
            ];
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => false,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return $output;
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\SellingPriceGroup  $sellingPriceGroup
     * @return \Illuminate\Http\Response
     */
    public function show(SellingPriceGroup $sellingPriceGroup)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\SellingPriceGroup  $sellingPriceGroup
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        if (! auth()->user()->can('product.create')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            $business_id = request()->session()->get('user.business_id');
            $spg = SellingPriceGroup::where('business_id', $business_id)->find($id);

            return view('selling_price_group.edit')
                ->with(compact('spg'));
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\SellingPriceGroup  $sellingPriceGroup
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        if (! auth()->user()->can('product.update')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            try {
                $input = $request->only(['name', 'description']);
                $business_id = $request->session()->get('user.business_id');

                $spg = SellingPriceGroup::where('business_id', $business_id)->findOrFail($id);
                $spg->name = $input['name'];
                $spg->description = $input['description'];
                $spg->save();

                $output = ['success' => true,
                    'msg' => __('lang_v1.updated_success'),
                ];
            } catch (\Exception $e) {
                \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

                $output = ['success' => false,
                    'msg' => __('messages.something_went_wrong'),
                ];
            }

            return $output;
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\SellingPriceGroup  $sellingPriceGroup
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        if (! auth()->user()->can('product.create')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            try {
                $business_id = request()->user()->business_id;

                $spg = SellingPriceGroup::where('business_id', $business_id)->findOrFail($id);
                $spg->delete();

                $output = ['success' => true,
                    'msg' => __('lang_v1.deleted_success'),
                ];
            } catch (\Exception $e) {
                \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

                $output = ['success' => false,
                    'msg' => __('messages.something_went_wrong'),
                ];
            }

            return $output;
        }
    }

    /**
     * Show interface to download product price excel file.
     *
     * @return mixed Inertia (tela nova) · view clássica (?classico=1)
     */
    public function updateProductPrice(){
        if (! auth()->user()->can('product.update')) {
            abort(403, 'Unauthorized action.');
        }

        // Playbook Produto · thread 06: a tela vira Produto/AtualizarPreco/Index. Exportar e
        // importar seguem nas MESMAS rotas e no MESMO código (regra mestre de valor: esta thread
        // troca só a tela). `?classico=1` mantém a Blade.
        if (request()->boolean('classico')) {
            return view('selling_price_group.update_product_price');
        }

        $business_id = (int) request()->session()->get('user.business_id');

        return Inertia::render('Produto/AtualizarPreco/Index', [
            'grupos' => SellingPriceGroup::where('business_id', $business_id)->active()->orderBy('name')
                ->get(['id', 'name'])->map(fn ($g) => ['id' => (int) $g->id, 'nome' => (string) $g->name])->values()->all(),
            // Mesmo recorte do export(): variações de produto single/variable deste negócio.
            'total_linhas' => Inertia::defer(fn () => Variation::join('products as p', 'variations.product_id', '=', 'p.id')
                ->join('product_variations as pv', 'variations.product_variation_id', '=', 'pv.id')
                ->where('p.business_id', $business_id)->whereIn('p.type', ['single', 'variable'])->count()),
            // import() devolve o erro em `notification` (não em `status`), que o flash compartilhado não lê.
            'erro' => session('notification.msg'),
        ]);
    }

    /**
     * Exports selling price group prices for all the products in xls format
     *
     * @return \Illuminate\Http\Response
     */
    public function export()
    {
        // P0 2026-10-01: a planilha traz o preço de todo o catálogo. Mesmo gate da tela que a
        // oferece (updateProductPrice) e do import(). Antes não havia gate nenhum.
        if (! auth()->user()->can('product.update')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->user()->business_id;
        $price_groups = SellingPriceGroup::where('business_id', $business_id)->active()->get();

        $variations = Variation::join('products as p', 'variations.product_id', '=', 'p.id')
                            ->join('product_variations as pv', 'variations.product_variation_id', '=', 'pv.id')
                            ->where('p.business_id', $business_id)
                            ->whereIn('p.type', ['single', 'variable'])
                            ->select('sub_sku', 'p.name as product_name', 'variations.name as variation_name', 'p.type', 'variations.id', 'pv.name as product_variation_name', 'sell_price_inc_tax')
                            ->with(['group_prices'])
                            ->get();
        $export_data = [];
        foreach ($variations as $variation) {
            $temp = [];
            $temp['product'] = $variation->type == 'single' ? $variation->product_name : $variation->product_name.' - '.$variation->product_variation_name.' - '.$variation->variation_name;
            $temp['sku'] = $variation->sub_sku;
            $temp['Selling Price Including Tax'] = $variation->sell_price_inc_tax;

            foreach ($price_groups as $price_group) {
                $price_group_id = $price_group->id;
                $variation_pg = $variation->group_prices->filter(function ($item) use ($price_group_id) {
                    return $item->price_group_id == $price_group_id;
                });

                $temp[$price_group->name] = $variation_pg->isNotEmpty() ? $variation_pg->first()->price_inc_tax : '';
            }
            $export_data[] = $temp;
        }

        if (ob_get_contents()) {
            ob_end_clean();
        }
        ob_start();

        return collect($export_data)->downloadExcel(
            'product_prices.xlsx',
            null,
            true
        );
    }

    /**
     * Imports the uploaded file to database.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function import(Request $request)
    {
        // P0 2026-10-01: grava preço — mesmo gate da tela e do cadastro de produto. Antes, só a
        // conferência checava; o POST direto (Blade ou fora da tela) passava sem permissão.
        if (! auth()->user()->can('product.update')) {
            abort(403, 'Unauthorized action.');
        }

        // Playbook Produto · thread 06: conferência (dry-run). Desvio no topo; o corpo abaixo
        // segue intocado e é ele que a conferência executa, dentro de uma transação desfeita.
        if ($request->boolean('conferir')) {
            return $this->conferirPlanilha($request);
        }

        try {
            $notAllowed = $this->commonUtil->notAllowedInDemo();
            if (! empty($notAllowed)) {
                return $notAllowed;
            }

            //Set maximum php execution time
            ini_set('max_execution_time', 0);
            ini_set('memory_limit', -1);

            if ($request->hasFile('product_group_prices')) {
                $file = $request->file('product_group_prices');

                $parsed_array = Excel::toArray([], $file);

                $headers = $parsed_array[0][0];

                //Remove header row
                $imported_data = array_splice($parsed_array[0], 1);

                $business_id = request()->user()->business_id;
                $price_groups = SellingPriceGroup::where('business_id', $business_id)->active()->get();

                //Get price group names from headers
                $imported_pgs = [];
                foreach ($headers as $key => $value) {
                    if (! empty($value) && $key > 2) {
                        $imported_pgs[$key] = $value;
                    }
                }

                $error_msg = '';
                DB::beginTransaction();

                foreach ($imported_data as $key => $value) {
                    // P0 Tier 0 (2026-10-01): o SKU é procurado SÓ neste negócio. Antes a busca
                    // não filtrava business_id e a planilha gravava na variação de OUTRO negócio
                    // quando o sub_sku coincidia. SKU sem variação aqui cai no "não encontrado"
                    // abaixo (linha recusada com o SKU e a linha, nada gravado). `orderBy(id)` fixa
                    // a mesma variação que a conferência (fotoPrecos) mostra.
                    $variation = Variation::where('sub_sku', $value[1])
                                        ->whereIn('product_id', fn ($q) => $q->select('id')->from('products')->where('business_id', $business_id))
                                        ->orderBy('variations.id')
                                        ->first();
                    if (empty($variation)) {
                        $row = $key + 1;
                        $error_msg = __('lang_v1.product_not_found_exception', ['sku' => $value[1], 'row' => $row]);

                        throw new \Exception($error_msg);
                    }

                    //Check if product base price is changed
                    if($variation->sell_price_inc_tax != $value[2]){
                        //update price for base selling price, adjust default_sell_price, profit %
                        $variation->sell_price_inc_tax = $value[2];
                        $tax = $variation->product->product_tax()->get();
                        $tax_percent = !empty($tax) && !empty($tax->first()) ? $tax->first()->amount : 0;
                        $variation->default_sell_price = $this->commonUtil->calc_percentage_base($value[2], $tax_percent);
                        $variation->profit_percent = $this->commonUtil
                                        ->get_percent($variation->default_purchase_price, $variation->default_sell_price);
                        $variation->update();
                    }

                    //update selling price
                    foreach ($imported_pgs as $k => $v) {
                        $price_group = $price_groups->filter(function ($item) use ($v) {
                            return strtolower($item->name) == strtolower($v);
                        });

                        if ($price_group->isNotEmpty()) {
                            //Check if price is numeric
                            if (! is_null($value[$k]) && ! is_numeric($value[$k])) {
                                $row = $key + 1;
                                $error_msg = __('lang_v1.price_group_non_numeric_exception', ['row' => $row]);

                                throw new \Exception($error_msg);
                            }

                            if (! is_null($value[$k])) {
                                VariationGroupPrice::updateOrCreate(
                                    ['variation_id' => $variation->id,
                                        'price_group_id' => $price_group->first()->id,
                                    ],
                                    ['price_inc_tax' => $value[$k],
                                    ]
                                );
                            }
                        } else {
                            $row = $key + 1;
                            $error_msg = __('lang_v1.price_group_not_found_exception', ['pg' => $v, 'row' => $row]);

                            throw new \Exception($error_msg);
                        }
                    }
                }
                DB::commit();
            }
            $output = ['success' => 1,
                'msg' => __('lang_v1.product_prices_imported_successfully'),
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => 0,
                'msg' => $e->getMessage(),
            ];

            return redirect('update-product-price')->with('notification', $output);
        }

        return redirect('update-product-price')->with('status', $output);
    }

    /**
     * Conferência da planilha de preços (thread 06): roda o import() de verdade dentro de uma
     * transação e desfaz. Devolve antes→depois por SKU deste negócio, sem gravar nada.
     * Não reimplementa parse, arredondamento nem gravação: quem calcula é o import().
     */
    private function conferirPlanilha(Request $request)
    {
        if (! auth()->user()->can('product.update')) {
            abort(403, 'Unauthorized action.');
        }
        if (! $request->hasFile('product_group_prices')) {
            return response()->json(['ok' => false, 'msg' => 'Escolha a planilha exportada.', 'linhas' => [], 'alertas' => []], 422);
        }

        $business_id = (int) $request->session()->get('user.business_id');
        try {
            $planilha = Excel::toArray(new \stdClass(), $request->file('product_group_prices'))[0] ?? [];
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'msg' => 'Não consegui ler a planilha.', 'linhas' => [], 'alertas' => []], 422);
        }
        $skus = collect(array_slice($planilha, 1))->map(fn ($l) => (string) ($l[1] ?? ''))
            ->filter(fn ($s) => $s !== '')->unique()->values()->all();

        $request->request->remove('conferir');
        $request->query->remove('conferir');

        DB::beginTransaction();
        try {
            $antes = $this->fotoPrecos($business_id, $skus);
            $this->import($request);
            $depois = $this->fotoPrecos($business_id, $skus);
        } finally {
            DB::rollBack();
        }
        $status = (array) session()->pull('status', []);
        $notificacao = (array) session()->pull('notification', []);

        $linhas = [];
        foreach ($antes as $sku => $campos) {
            foreach ($campos['precos'] as $campo => $valor) {
                $novo = $depois[$sku]['precos'][$campo] ?? null;
                if ((string) $valor !== (string) $novo) {
                    $linhas[] = ['sku' => $sku, 'produto' => $campos['produto'], 'campo' => $campo, 'antes' => $valor, 'depois' => $novo];
                }
            }
        }

        // SKU que também existe em outro negócio. Desde 2026-10-01 o import() só grava no próprio
        // negócio; o alerta segue para o operador saber da colisão.
        $deFora = DB::table('variations as v')->join('products as p', 'v.product_id', '=', 'p.id')
            ->whereIn('v.sub_sku', $skus)->whereNull('v.deleted_at')->where('p.business_id', '!=', $business_id)
            ->distinct()->pluck('v.sub_sku')->map(fn ($s) => (string) $s)->all();
        $alertas = array_map(fn ($sku) => ['sku' => $sku, 'tipo' => isset($antes[$sku]) ? 'ambiguo' : 'outro_negocio'], $deFora);

        return response()->json([
            'ok' => ! empty($status['success']),
            'msg' => $notificacao['msg'] ?? ($status['msg'] ?? null),
            'linhas' => $linhas,
            'alertas' => array_values($alertas),
        ]);
    }

    /**
     * Preço de venda e preço por grupo ativo dos SKUs, só deste negócio (para a conferência).
     *
     * @return array<string, array{produto: string, precos: array<string, string|null>}>
     */
    private function fotoPrecos(int $business_id, array $skus): array
    {
        $grupos = SellingPriceGroup::where('business_id', $business_id)->active()->pluck('name', 'id');
        $variacoes = DB::table('variations as v')->join('products as p', 'v.product_id', '=', 'p.id')
            ->where('p.business_id', $business_id)->whereIn('v.sub_sku', $skus)->whereNull('v.deleted_at')
            ->orderBy('v.id')->get(['v.id', 'v.sub_sku', 'v.sell_price_inc_tax', 'p.name as produto']);
        $porGrupo = DB::table('variation_group_prices')->whereIn('variation_id', $variacoes->pluck('id'))
            ->get(['variation_id', 'price_group_id', 'price_inc_tax']);

        $foto = [];
        foreach ($variacoes as $v) {
            if (isset($foto[(string) $v->sub_sku])) {
                continue;
            }
            $precos = ['Preço de venda' => (string) $v->sell_price_inc_tax];
            foreach ($grupos as $id => $nome) {
                $gp = $porGrupo->first(fn ($r) => (int) $r->variation_id === (int) $v->id && (int) $r->price_group_id === (int) $id);
                $precos[(string) $nome] = $gp ? (string) $gp->price_inc_tax : null;
            }
            $foto[(string) $v->sub_sku] = ['produto' => (string) $v->produto, 'precos' => $precos];
        }

        return $foto;
    }

    /**
     * Activate/deactivate selling price group.
     */
    public function activateDeactivate($id)
    {
        if (! auth()->user()->can('product.create')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            $business_id = request()->session()->get('user.business_id');
            $spg = SellingPriceGroup::where('business_id', $business_id)->find($id);
            $spg->is_active = $spg->is_active == 1 ? 0 : 1;
            $spg->save();

            $output = ['success' => true,
                'msg' => __('lang_v1.updated_success'),
            ];

            return $output;
        }
    }
}
