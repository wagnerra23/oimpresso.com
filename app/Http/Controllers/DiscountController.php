<?php

namespace App\Http\Controllers;

use App\Brands;
use App\BusinessLocation;
use App\Category;
use App\Discount;
use App\Http\Requests\SalvarDescontoRequest;
use App\SellingPriceGroup;
use App\Utils\Util;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Yajra\DataTables\Facades\DataTables;

class DiscountController extends Controller
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
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        // D1 ([W] 2026-10-02): ver a lista exige `discount.view`; quem pode gravar
        // (`discount.manage`) também vê — não faz sentido editar sem enxergar.
        if (! auth()->user()->can('discount.view') && ! auth()->user()->can('discount.manage')) {
            abort(403, 'Unauthorized action.');
        }

        // Tela React (thread 04 de Vendas, MWART). Vem ANTES do ajax(): o Inertia manda
        // X-Inertia E X-Requested-With, e o ramo ajax devolveria o JSON do DataTable.
        // Sem X-Inertia segue o Blade como fallback — o cutover F5 é humano.
        if (request()->header('X-Inertia')) {
            return $this->telaInertia();
        }

        if (request()->ajax()) {
            $business_id = request()->session()->get('user.business_id');

            $discounts = Discount::where('discounts.business_id', $business_id)
                        ->leftjoin('brands as b', 'discounts.brand_id', '=', 'b.id')
                        ->leftjoin('categories as c', 'discounts.category_id', '=', 'c.id')
                        ->leftjoin('business_locations as l', 'discounts.location_id', '=', 'l.id')
                        ->select(['discounts.id', 'discounts.name', 'starts_at', 'ends_at',
                            'priority', 'b.name as brand', 'c.name as category', 'l.name as location', 'discounts.is_active', 'discounts.discount_amount', 'discount_type', ])
                        ->with(['variations', 'variations.product', 'variations.product_variation']);

            return Datatables::of($discounts)
                ->addColumn(
                    'action',
                    '<button data-href="{{action(\'App\Http\Controllers\DiscountController@edit\', [$id])}}" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-primary btn-modal" data-container=".discount_modal"><i class="glyphicon glyphicon-edit"></i> @lang("messages.edit")</button>
                        &nbsp;
                        <button data-href="{{action(\'App\Http\Controllers\DiscountController@destroy\', [$id])}}" class="tw-dw-btn tw-dw-btn-outline tw-dw-btn-xs tw-dw-btn-error delete_discount_button"><i class="glyphicon glyphicon-trash"></i> @lang("messages.delete")</button>
                        @if($is_active != 1)
                            &nbsp;
                            <button data-href="{{action(\'App\Http\Controllers\DiscountController@activate\', [$id])}}" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-accent activate-discount"><i class="fa fa-circle-o"></i> @lang("lang_v1.reactivate")</button>
                        @endif
                        '
                )
                ->addColumn('row_select', function ($row) {
                    return  '<input type="checkbox" class="row-select" value="'.$row->id.'">';
                })
                ->addColumn('products', function ($row) {
                    $products = [];

                    foreach ($row->variations as $variation) {
                        $products[] = $variation->full_name;
                    }

                    return '<span class="label bg-primary">'.implode('</span>, <span class="label bg-primary">', $products).'</span>';
                })
                ->editColumn('name', function ($row) {
                    $name = $row->is_active != 1 ? $row->name.' <span class="label bg-yellow">'.__('lang_v1.inactive').'</sapn>' : $row->name;

                    return $name;
                })
                ->editColumn('starts_at', function ($row) {
                    $starts_at = ! empty($row->starts_at) ? $this->commonUtil->format_date($row->starts_at->toDateTimeString(), true) : '';

                    return $starts_at;
                })
                ->editColumn('ends_at', function ($row) {
                    $ends_at = ! empty($row->ends_at) ? $this->commonUtil->format_date($row->ends_at->toDateTimeString(), true) : '';

                    return $ends_at;
                })
                ->editColumn('discount_amount', '{{@num_format($discount_amount)}} @if($discount_type == "percentage") % @endif')
                ->rawColumns(['name', 'action', 'row_select', 'products'])
                ->make(true);
        }

        return view('discount.index');
    }

    /**
     * Props da tela `Discount/Index`. A lista e as opções do drawer vão em `Inertia::defer`
     * (consulta com eager-load das variações + 4 dropdowns). Tudo escopado pelo
     * business_id da sessão (ADR 0093) — o mesmo filtro do ramo ajax do Blade.
     */
    private function telaInertia()
    {
        $business_id = (int) request()->session()->get('user.business_id');

        return Inertia::render('Discount/Index', [
            'descontos' => Inertia::defer(fn () => $this->linhasDaTela($business_id)),
            'opcoes' => Inertia::defer(fn () => [
                'categorias' => Category::where('business_id', $business_id)
                    ->where('parent_id', 0)
                    ->pluck('name', 'id'),
                'marcas' => Brands::forDropdown($business_id),
                'locais' => BusinessLocation::forDropdown($business_id),
                'gruposPreco' => SellingPriceGroup::forDropdown($business_id),
            ]),
            // A gravação passa as datas pelo uf_date(), que lê o formato do NEGÓCIO —
            // a tela monta a string nesse formato, como o datetimepicker do Blade.
            'formatoData' => [
                'data' => (string) request()->session()->get('business.date_format', 'd/m/Y'),
                'hora' => (int) request()->session()->get('business.time_format', 24),
            ],
            // D1 [W] 2026-10-02: sem `discount.manage` os botões aparecem desabilitados com o motivo.
            'permissoes' => ['editar' => auth()->user()->can('discount.manage')],
            'urls' => [
                'salvar' => action([self::class, 'store']),
                'atualizar' => '/discount/{id}',
                'excluir' => '/discount/{id}',
                'reativar' => '/discount/activate/{id}',
                'desativarEmMassa' => action([self::class, 'massDeactivate']),
                'buscarProdutos' => '/purchases/get_products?check_enable_stock=false&only_variations=true',
            ],
        ]);
    }

    /**
     * Linhas da lista — mesma consulta do ramo ajax (mesmo where de business_id, mesmos joins),
     * em dados crus em vez de HTML do DataTable. Valores saem do banco sem conta nenhuma.
     *
     * @return array<int, array<string, mixed>>
     */
    private function linhasDaTela(int $business_id): array
    {
        $linhas = DB::table('discounts')
            ->where('discounts.business_id', $business_id)
            ->leftJoin('brands as b', 'discounts.brand_id', '=', 'b.id')
            ->leftJoin('categories as c', 'discounts.category_id', '=', 'c.id')
            ->leftJoin('business_locations as l', 'discounts.location_id', '=', 'l.id')
            ->select(['discounts.id', 'discounts.name', 'discounts.starts_at', 'discounts.ends_at',
                'discounts.priority', 'discounts.brand_id', 'discounts.category_id', 'discounts.location_id',
                'discounts.spg', 'discounts.applicable_in_cg', 'discounts.is_active',
                'discounts.discount_amount', 'discounts.discount_type',
                'b.name as brand', 'c.name as category', 'l.name as location', ])
            ->orderBy('discounts.priority')
            ->orderBy('discounts.id')
            ->get();

        $produtos = $this->produtosDosDescontos($linhas->pluck('id')->map(fn ($id) => (int) $id)->all());

        return $linhas->map(fn (object $d): array => [
            'id' => (int) $d->id,
            'nome' => (string) $d->name,
            'inicio' => $d->starts_at,
            'fim' => $d->ends_at,
            'prioridade' => $d->priority === null ? null : (int) $d->priority,
            'tipo' => $d->discount_type,
            'valor' => (string) $d->discount_amount,
            'marcaId' => $d->brand_id === null ? null : (int) $d->brand_id,
            'marca' => $d->brand,
            'categoriaId' => $d->category_id === null ? null : (int) $d->category_id,
            'categoria' => $d->category,
            'localId' => $d->location_id === null ? null : (int) $d->location_id,
            'local' => $d->location,
            'grupoPreco' => $d->spg,
            'aplicaEmGrupoCliente' => (int) $d->applicable_in_cg === 1,
            'ativo' => (int) $d->is_active === 1,
            'produtos' => $produtos[(int) $d->id] ?? [],
        ])->values()->all();
    }

    /**
     * Produtos (variações) de cada desconto, com o mesmo nome que `Variation::full_name`
     * monta: produto [- grupo - variação, se variável] (sub_sku). Os ids vêm de descontos
     * já filtrados pelo business_id.
     *
     * @param  array<int, int>  $ids
     * @return array<int, array<int, array{id: int, nome: string}>>
     */
    private function produtosDosDescontos(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $porDesconto = [];
        DB::table('discount_variations as dv')
            ->join('variations as v', 'v.id', '=', 'dv.variation_id')
            ->join('products as p', 'p.id', '=', 'v.product_id')
            ->leftJoin('product_variations as pv', 'pv.id', '=', 'v.product_variation_id')
            ->whereIn('dv.discount_id', $ids)
            ->select(['dv.discount_id', 'v.id', 'v.name as variacao', 'v.sub_sku',
                'p.name as produto', 'p.type', 'pv.name as grupo', ])
            ->orderBy('dv.discount_id')
            ->get()
            ->each(function (object $r) use (&$porDesconto) {
                $nome = (string) $r->produto;
                if ($r->type === 'variable') {
                    $nome .= ' - '.$r->grupo.' - '.$r->variacao;
                }
                $porDesconto[(int) $r->discount_id][] = ['id' => (int) $r->id, 'nome' => $nome.' ('.$r->sub_sku.')'];
            });

        return $porDesconto;
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        if (! auth()->user()->can('discount.manage')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        $categories = Category::where('business_id', $business_id)
                            ->where('parent_id', 0)
                            ->pluck('name', 'id');

        $brands = Brands::forDropdown($business_id);

        $locations = BusinessLocation::forDropdown($business_id);

        $price_groups = SellingPriceGroup::forDropdown($business_id);

        return view('discount.create')
                ->with(compact('categories', 'brands', 'locations', 'price_groups'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function store(SalvarDescontoRequest $request)
    {
        // Autorização (`discount.manage`) e montagem da linha moraram para o FormRequest.
        // A linha gravada é a mesma de antes — DescontoGravacaoContratoTest compara.
        try {
            $input = $request->dadosParaGravar($this->commonUtil);

            $business_id = $request->session()->get('user.business_id');
            $input['business_id'] = $business_id;

            $variation_ids = $request->variationIds();

            $discount = Discount::create($input);

            if (! empty($variation_ids)) {
                $discount->variations()->sync($variation_ids);
            }

            $output = ['success' => true,
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
     * Show the form for editing the specified resource.
     *
     * @param  \App\Discount  $discount
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        if (! auth()->user()->can('discount.manage')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            $business_id = request()->session()->get('user.business_id');

            $discount = Discount::where('business_id', $business_id)
                            ->with(['variations', 'variations.product', 'variations.product_variation'])
                            ->find($id);

            $starts_at = $this->commonUtil->format_date($discount->starts_at->toDateTimeString(), true);
            $ends_at = $this->commonUtil->format_date($discount->ends_at->toDateTimeString(), true);

            $categories = Category::where('business_id', $business_id)
                            ->where('parent_id', 0)
                            ->pluck('name', 'id');

            $brands = Brands::forDropdown($business_id);

            $locations = BusinessLocation::forDropdown($business_id);

            $variations = [];

            foreach ($discount->variations as $variation) {
                $variations[$variation->id] = $variation->full_name;
            }

            $price_groups = SellingPriceGroup::forDropdown($business_id);

            return view('discount.edit')
                ->with(compact('discount', 'starts_at', 'ends_at', 'brands', 'categories', 'locations', 'variations', 'price_groups'));
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function update(SalvarDescontoRequest $request, $id)
    {
        // Autorização (`discount.manage`) e montagem da linha no FormRequest — mesma linha de antes.
        if (request()->ajax()) {
            try {
                $input = $request->dadosParaGravar($this->commonUtil);

                $business_id = $request->session()->get('user.business_id');

                $variation_ids = $request->variationIds();

                $discount = Discount::where('business_id', $business_id)
                            ->find($id);

                $discount->update($input);

                $discount->variations()->sync($variation_ids);

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
     * @param  \App\Discount  $discount
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        if (! auth()->user()->can('discount.manage')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            try {
                $business_id = request()->user()->business_id;

                $discount = Discount::where('business_id', $business_id)->findOrFail($id);
                $discount->delete();

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
     * Mass deactivates discounts.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function massDeactivate(Request $request)
    {
        if (! auth()->user()->can('discount.manage')) {
            abort(403, 'Unauthorized action.');
        }
        try {
            if (! empty($request->input('selected_discounts'))) {
                $business_id = $request->session()->get('user.business_id');

                $selected_discounts = explode(',', $request->input('selected_discounts'));

                DB::beginTransaction();

                Discount::where('business_id', $business_id)
                            ->whereIn('id', $selected_discounts)
                            ->update(['is_active' => 0]);

                DB::commit();
            }

            $output = ['success' => 1,
                'msg' => __('lang_v1.deactivated_success'),
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return redirect()->back()->with(['status' => $output]);
    }

    /**
     * Activates the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function activate($id)
    {
        if (! auth()->user()->can('discount.manage')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            try {
                $business_id = request()->session()->get('user.business_id');
                Discount::where('id', $id)
                    ->where('business_id', $business_id)
                    ->update(['is_active' => 1]);

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
}
