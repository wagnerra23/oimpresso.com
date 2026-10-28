<?php

namespace App\Http\Controllers;

use App\Brands;
use App\Business;
use App\BusinessLocation;
use App\Category;
use App\Exports\ProductsExport;
use App\Media;
use App\Product;
use App\ProductVariation;
use App\PurchaseLine;
use App\SellingPriceGroup;
use App\TaxRate;
use App\Unit;
use App\Utils\ModuleUtil;
use App\Utils\ProductUtil;
use App\Variation;
use App\VariationGroupPrice;
use App\VariationLocationDetails;
use App\VariationTemplate;
use App\Warranty;
use Excel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Yajra\DataTables\Facades\DataTables;
use App\Events\ProductsCreatedOrModified;
use App\TransactionSellLine;
use App\Services\FeatureFlagService;
use Inertia\Inertia;

class ProductController extends Controller
{
    /**
     * Chave por empresa (GrowthBook, default OFF): com ela ligada, /products/create abre o cadastro
     * React em toda entrada, inclusive o "Novo produto" da /products. [F] 2026-10-16.
     */
    private const FLAG_CADASTRO_REACT = 'useV2ProdutoCadastro';

    /** Peso líquido (kg) e dimensões da embalagem (cm) — UC-PCAD-16 e UC-PEDIT-13, CU-PROD-17. */
    private const PESO_E_DIMENSOES = ['peso_liquido', 'largura_cm', 'altura_cm', 'comprimento_cm'];

    /**
     * All Utils instance.
     */
    protected $productUtil;

    protected $moduleUtil;

    private $barcode_types;

    /**
     * Constructor
     *
     * @param  ProductUtils  $product
     * @return void
     */
    public function __construct(ProductUtil $productUtil, ModuleUtil $moduleUtil)
    {
        $this->productUtil = $productUtil;
        $this->moduleUtil = $moduleUtil;

        //barcode types
        $this->barcode_types = $this->productUtil->barcode_types();
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (! auth()->user()->can('product.view') && ! auth()->user()->can('product.create')) {
            abort(403, 'Unauthorized action.');
        }
        $business_id = request()->session()->get('user.business_id');
        $selling_price_group_count = SellingPriceGroup::countSellingPriceGroups($business_id);
        $is_woocommerce = $this->moduleUtil->isModuleInstalled('Woocommerce');

        // O menu abre a Page React; `?classico=1` mantém o Blade (molde `UnitController@index`).
        // Histórico: em 2026-10-07 o cutover pelo menu · thread 01 (#9094, D1 [W]) ligou a Page de
        // cards; em 2026-10-09 voltou ao Blade (#9117) porque ela cortava em 200 produtos, buscava só
        // no que carregou e não seguia o protótipo. Em 2026-10-10 a lista foi refeita no desenho do
        // protótipo (`produto-blade.jsx`, vista `lista`) sobre a MESMA consulta e busca da lista
        // Blade, paginada no servidor (#9119/#9124), e o menu volta a abri-la ([F], autorizado [W]).
        // A decisão vem ANTES do ramo `ajax()`: o Inertia manda `X-Requested-With` junto do
        // `X-Inertia`, e com o `ajax()` primeiro a visita Inertia recebia o JSON do DataTable
        // (§5 2026-09-08).
        $telaReact = ! request()->boolean('classico') && (! request()->ajax() || request()->header('X-Inertia'));

        if (! $telaReact && request()->ajax()) {
            $products = $this->consultaListaProdutos($business_id, [
                'location_id' => request()->get('location_id', null),
                'type' => request()->get('type', null),
                'category_id' => request()->get('category_id', null),
                'brand_id' => request()->get('brand_id', null),
                'unit_id' => request()->get('unit_id', null),
                'tax_id' => request()->get('tax_id', null),
                'active_state' => request()->get('active_state', null),
                'not_for_selling' => request()->get('not_for_selling', null) == 'true',
                'woocommerce_enabled' => request()->get('woocommerce_enabled', 0) == 1,
                'repair_model_id' => request()->get('repair_model_id'),
            ], $is_woocommerce);

            return Datatables::of($products)
                ->addColumn(
                    'product_locations',
                    function ($row) {
                        return $row->product_locations->implode('name', ', ');
                    }
                )
                ->editColumn('category', '{{$category}} @if(!empty($sub_category))<br/> -- {{$sub_category}}@endif')
                ->addColumn(
                    'action',
                    function ($row) use ($selling_price_group_count) {
                        $html =
                        '<div class="btn-group"><button type="button" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline  tw-dw-btn-info tw-w-max dropdown-toggle" data-toggle="dropdown" aria-expanded="false">'.__('messages.actions').'<span class="caret"></span><span class="sr-only">Toggle Dropdown</span></button><ul class="dropdown-menu dropdown-menu-left" role="menu"><li><a href="'.action([\App\Http\Controllers\LabelsController::class, 'show']).'?product_id='.$row->id.'" data-toggle="tooltip" title="'.__('lang_v1.label_help').'"><i class="fa fa-barcode"></i> '.__('barcode.labels').'</a></li>';

                        if (auth()->user()->can('product.view')) {
                            $html .=
                            '<li><a href="'.action([\App\Http\Controllers\ProductController::class, 'view'], [$row->id]).'" class="view-product"><i class="fa fa-eye"></i> '.__('messages.view').'</a></li>';
                        }

                        if (auth()->user()->can('product.update')) {
                            $html .=
                            '<li><a href="'.action([\App\Http\Controllers\ProductController::class, 'edit'], [$row->id]).'"><i class="glyphicon glyphicon-edit"></i> '.__('messages.edit').'</a></li>';
                        }

                        if (auth()->user()->can('product.delete')) {
                            $html .=
                            '<li><a href="'.action([\App\Http\Controllers\ProductController::class, 'destroy'], [$row->id]).'" class="delete-product"><i class="fa fa-trash"></i> '.__('messages.delete').'</a></li>';
                        }

                        if ($row->is_inactive == 1) {
                            $html .=
                            '<li><a href="'.action([\App\Http\Controllers\ProductController::class, 'activate'], [$row->id]).'" class="activate-product"><i class="fas fa-check-circle"></i> '.__('lang_v1.reactivate').'</a></li>';
                        }

                        $html .= '<li class="divider"></li>';

                        if ($row->enable_stock == 1 && auth()->user()->can('product.opening_stock')) {
                            $html .=
                            '<li><a href="#" data-href="'.action([\App\Http\Controllers\OpeningStockController::class, 'add'], ['product_id' => $row->id]).'" class="add-opening-stock"><i class="fa fa-database"></i> '.__('lang_v1.add_edit_opening_stock').'</a></li>';
                        }

                        if (auth()->user()->can('product.view')) {
                            $html .=
                            '<li><a href="'.action([\App\Http\Controllers\ProductController::class, 'productStockHistory'], [$row->id]).'"><i class="fas fa-history"></i> '.__('lang_v1.product_stock_history').'</a></li>';
                        }

                        if (auth()->user()->can('product.create')) {
                            if ($selling_price_group_count > 0) {
                                $html .=
                                '<li><a href="'.action([\App\Http\Controllers\ProductController::class, 'addSellingPrices'], [$row->id]).'"><i class="fas fa-money-bill-alt"></i> '.__('lang_v1.add_selling_price_group_prices').'</a></li>';
                            }

                            $html .=
                                '<li><a href="'.action([\App\Http\Controllers\ProductController::class, 'create'], ['d' => $row->id]).'"><i class="fa fa-copy"></i> '.__('lang_v1.duplicate_product').'</a></li>';
                        }

                        if (! empty($row->media->first())) {
                            $html .=
                                '<li><a href="'.$row->media->first()->display_url.'" download="'.$row->media->first()->display_name.'"><i class="fas fa-download"></i> '.__('lang_v1.product_brochure').'</a></li>';
                        }

                        $html .= '</ul></div>';

                        return $html;
                    }
                )
                ->editColumn('product', function ($row) use ($is_woocommerce) {
                    $product = $row->is_inactive == 1 ? $row->product.' <span class="label bg-gray">'.__('lang_v1.inactive').'</span>' : $row->product;

                    $product = $row->not_for_selling == 1 ? $product.' <span class="label bg-gray">'.__('lang_v1.not_for_selling').
                        '</span>' : $product;

                    if ($is_woocommerce && ! $row->woocommerce_disable_sync) {
                        $product = $product.'<br><i class="fab fa-wordpress"></i>';
                    }

                    return $product;
                })
                ->editColumn('image', function ($row) {
                    return '<div style="display: flex;"><img src="'.$row->image_url.'" alt="Product image" class="product-thumbnail-small"></div>';
                })
                ->editColumn('type', '@lang("lang_v1." . $type)')
                ->addColumn('mass_delete', function ($row) {
                    return  '<input type="checkbox" class="row-select" value="'.$row->id.'">';
                })
                ->editColumn('current_stock', function ($row) {
                    if ($row->enable_stock) {
                        $stock = $this->productUtil->num_f($row->current_stock, false, null, true);

                        return $stock.' '.$row->unit;
                    } else {
                        return '--';
                    }
                })
                ->addColumn(
                    'purchase_price',
                    '<div style="white-space: nowrap;">@format_currency($min_purchase_price) @if($max_purchase_price != $min_purchase_price && $type == "variable") -  @format_currency($max_purchase_price)@endif </div>'
                )
                ->addColumn(
                    'selling_price',
                    '<div style="white-space: nowrap;">@format_currency($min_price) @if($max_price != $min_price && $type == "variable") -  @format_currency($max_price)@endif </div>'
                )
                ->filterColumn('products.sku', function ($query, $keyword) {
                    $query->whereHas('variations', function ($q) use ($keyword) {
                        $q->where('sub_sku', 'like', "%{$keyword}%");
                    })
                    ->orWhere('products.sku', 'like', "%{$keyword}%");
                })
                ->setRowAttr([
                    'data-href' => function ($row) {
                        if (auth()->user()->can('product.view')) {
                            return  action([\App\Http\Controllers\ProductController::class, 'view'], [$row->id]);
                        } else {
                            return '';
                        }
                    }, ])
                ->rawColumns(['action', 'image', 'mass_delete', 'product', 'selling_price', 'purchase_price', 'category', 'current_stock'])
                ->make(true);
        }

        $rack_enabled = (request()->session()->get('business.enable_racks') || request()->session()->get('business.enable_row') || request()->session()->get('business.enable_position'));

        $categories = Category::forDropdown($business_id, 'product');

        $brands = Brands::forDropdown($business_id);

        $units = Unit::forDropdown($business_id);

        $tax_dropdown = TaxRate::forBusinessDropdown($business_id, false);
        $taxes = $tax_dropdown['tax_rates'];

        $business_locations = BusinessLocation::forDropdown($business_id);
        $business_locations->prepend(__('lang_v1.none'), 'none');

        if ($this->moduleUtil->isModuleInstalled('Manufacturing') && (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'manufacturing_module'))) {
            $show_manufacturing_data = true;
        } else {
            $show_manufacturing_data = false;
        }

        //list product screen filter from module
        $pos_module_data = $this->moduleUtil->getModuleData('get_filters_for_list_product_screen');

        $is_admin = $this->productUtil->is_admin(auth()->user());

        // Wave 2 B4 Produto (Agent W2-C 2026-05-15) — branch dual Inertia MWART (ADR 0104).
        // Page React no GET comum (o menu) e na visita Inertia; `?classico=1` abre o Blade (ver acima).
        // Tier 0 multi-tenant (ADR 0093): business_id passado explicitamente a cada builder.
        if ($telaReact) {
            // UC-PIDX-03 — o MESMO gate que o outro branch deste método aplica na Blade
            // (`product/index.blade.php:287,294`): preço de compra e de venda não viajam pra
            // quem não tem o direito de vê-los. Resolvido AQUI (uma vez) e passado ao builder,
            // como o `ProdutoUnificadoController::index` já faz (`:145-146`).
            $podeVerCusto = (bool) auth()->user()->can('view_purchase_price');
            $podeVerPreco = (bool) auth()->user()->can('access_default_selling_price');

            // Lista no desenho do protótipo (`produto-blade.jsx`, vista `lista`): a MESMA consulta
            // do DataTable do Blade (`consultaListaProdutos`), paginada no servidor.
            $filtrosLista = $this->filtrosListaProdutos();

            // "Exportar filtrados" (rodapé da lista): download, não página.
            if (request()->input('exportar') === 'csv') {
                return $this->exportarListaCsv($business_id, $filtrosLista, $podeVerCusto, $podeVerPreco, $is_woocommerce, collect($taxes)->isNotEmpty());
            }

            return Inertia::render('Produto/Index', [
                'filtros' => $filtrosLista,
                'lista' => Inertia::defer(fn () => $this->buildProdutoListaPaginada($business_id, $filtrosLista, $podeVerCusto, $podeVerPreco, $is_woocommerce)),
                // Aba "Relatório de estoque": só com `stock_report.view` (a aba Blade era `@can`). Aberta pela URL
                // (`aba=estoque`) chega deferida; fechada, só vem se a tela pedir (partial reload).
                'estoque' => auth()->user()->can('stock_report.view')
                    ? ($filtrosLista['aba'] === 'estoque'
                        ? Inertia::defer(fn () => $this->buildRelatorioEstoqueDaLista($business_id, $filtrosLista, (int) $show_manufacturing_data, request()->header('X-Produto-Recalcular') === '1'), 'estoque')
                        : Inertia::optional(fn () => $this->buildRelatorioEstoqueDaLista($business_id, $filtrosLista, (int) $show_manufacturing_data, request()->header('X-Produto-Recalcular') === '1')))
                    : null,
                // Painel de detalhe do produto (`?ver={id}`): aberto pela URL chega deferido; fechado, só vem
                // se a tela pedir (partial reload com `ver`). Sem `product.view` não há painel.
                'detalhe' => auth()->user()->can('product.view') && (int) request()->input('ver', 0) > 0
                    ? Inertia::defer(fn () => $this->buildProdutoDetalhe($business_id, (int) request()->input('ver')), 'detalhe')
                    : Inertia::optional(fn () => null),
                // Grupo próprio: os indicadores não seguram a lista (e vice-versa).
                'kpis' => Inertia::defer(fn () => $this->buildProdutoListaKpis($business_id, $is_woocommerce, request()->header('X-Produto-Recalcular') === '1'), 'kpis'),
                'opcoes' => $this->buildProdutoListaOpcoes($categories, $brands, $units, $taxes, $business_locations, $pos_module_data),
                'contexto' => [
                    'empresa' => (string) (request()->session()->get('business.name') ?? ''),
                    'grupos_de_preco' => (int) $selling_price_group_count,
                    'woocommerce' => (bool) $is_woocommerce,
                    // Aba Fiscal e "Dados fiscais" só em empresa que emite nota (UC-PIDX-17 e 25).
                    'emite_nota' => $this->moduloFiscalNoPacote($business_id),
                    'rotulos_campos' => $this->rotulosCamposProduto(),
                    // UC-PIDX-31: o filtro "Natureza fiscal" só existe com o módulo Fiscal.
                    'natureza_fiscal' => app()->bound(\App\Contracts\Fiscal\NaturezaDoProduto::class),
                    // Papel do usuário, como a linha de contexto do protótipo ("papel: Administrador").
                    // O nome do role no UltimatePOS carrega o sufixo `#<business_id>` — sai da tela.
                    'papel' => ($papel = auth()->user()->roles->pluck('name')->first()) ? preg_replace('/#\d+$/', '', (string) $papel) : null,
                ],
                'permissions' => [
                    'create' => auth()->user()->can('product.create'),
                    'update' => auth()->user()->can('product.update'),
                    'delete' => auth()->user()->can('product.delete'),
                    'opening_stock' => auth()->user()->can('product.opening_stock'),
                    'view' => auth()->user()->can('product.view'),
                    'stock_report' => auth()->user()->can('stock_report.view'),
                    // "Etiquetas" da barra de seleção abre a tela de etiquetas, que pede esta permissão.
                    'etiquetas' => auth()->user()->can('print_labels.access'),
                    // Sem `comprar`: a consulta não oferece compra (decisão [F] 2026-10-26, UC-PIDX-11).
                    // EAGER (não defer): são 2 booleanos já resolvidos — o `.tsx` aplica default
                    // fail-closed, então ausência da prop nunca vira permissão.
                    'view_purchase_price' => $podeVerCusto,
                    'access_default_selling_price' => $podeVerPreco,
                ],
            ]);
        }

        return view('product.index')
            ->with(compact(
                'rack_enabled',
                'categories',
                'brands',
                'units',
                'taxes',
                'business_locations',
                'show_manufacturing_data',
                'pos_module_data',
                'is_woocommerce',
                'is_admin'
            ));
    }

    /**
     * Consulta base da lista de produtos (`/products`) — a MESMA para o DataTable da lista
     * Blade e para a Page React `Produto/Index`. Extraída do ramo AJAX de `index()` sem mudar
     * nada: joins, locais permitidos do usuário, filtros e agregados (estoque somado nos locais
     * permitidos, preço de compra/venda mínimo e máximo das variações). Assim as duas telas
     * listam exatamente os mesmos produtos para os mesmos filtros.
     *
     * `$f` traz os filtros já normalizados: `not_for_selling` e `woocommerce_enabled` são
     * booleanos (o Blade manda `'true'` e `1`; a conversão fica em quem chama).
     * Tier 0 multi-tenant (ADR 0093): `business_id` explícito.
     */
    protected function consultaListaProdutos(int $business_id, array $f, bool $is_woocommerce)
    {
        $permitted_locations = auth()->user()->permitted_locations();
        //Filter by location
        $location_id = $f['location_id'] ?? null;

        $query = Product::with(['media'])
            ->leftJoin('brands', 'products.brand_id', '=', 'brands.id')
            ->join('units', 'products.unit_id', '=', 'units.id')
            ->leftJoin('categories as c1', 'products.category_id', '=', 'c1.id')
            ->leftJoin('categories as c2', 'products.sub_category_id', '=', 'c2.id')
            ->leftJoin('tax_rates', 'products.tax', '=', 'tax_rates.id')
            ->join('variations as v', 'v.product_id', '=', 'products.id')
            ->leftJoin('variation_location_details as vld', function ($join) use ($permitted_locations) {
                $join->on('vld.variation_id', '=', 'v.id');
                if ($permitted_locations != 'all') {
                    $join->whereIn('vld.location_id', $permitted_locations);
                }
            })
            ->whereNull('v.deleted_at')
            ->where('products.business_id', $business_id)
            ->where('products.type', '!=', 'modifier');

        if (! empty($location_id) && $location_id != 'none') {
            if ($permitted_locations == 'all' || in_array($location_id, $permitted_locations)) {
                $query->whereHas('product_locations', function ($query) use ($location_id) {
                    $query->where('product_locations.location_id', '=', $location_id);
                });
            }
        } elseif ($location_id == 'none') {
            $query->doesntHave('product_locations');
        } else {
            if ($permitted_locations != 'all') {
                $query->whereHas('product_locations', function ($query) use ($permitted_locations) {
                    $query->whereIn('product_locations.location_id', $permitted_locations);
                });
            } else {
                $query->with('product_locations');
            }
        }

        $products = $query->select(
            'products.id',
            'products.name as product',
            'products.type',
            'c1.name as category',
            'c2.name as sub_category',
            'units.actual_name as unit',
            'brands.name as brand',
            'tax_rates.name as tax',
            'products.sku',
            'products.image',
            'products.enable_stock',
            'products.is_inactive',
            'products.not_for_selling',
            'products.product_custom_field1', 'products.product_custom_field2', 'products.product_custom_field3', 'products.product_custom_field4', 'products.product_custom_field5', 'products.product_custom_field6',
            'products.product_custom_field7', 'products.product_custom_field8', 'products.product_custom_field9',
            'products.product_custom_field10', 'products.product_custom_field11', 'products.product_custom_field12',
            'products.product_custom_field13', 'products.product_custom_field14', 'products.product_custom_field15',
            'products.product_custom_field16', 'products.product_custom_field17', 'products.product_custom_field18', 
            'products.product_custom_field19', 'products.product_custom_field20',
            'products.alert_quantity',
            DB::raw('SUM(vld.qty_available) as current_stock'),
            DB::raw('MAX(v.sell_price_inc_tax) as max_price'),
            DB::raw('MIN(v.sell_price_inc_tax) as min_price'),
            DB::raw('MAX(v.dpp_inc_tax) as max_purchase_price'),
            DB::raw('MIN(v.dpp_inc_tax) as min_purchase_price')
            );

        //if woocomerce enabled add field to query
        if ($is_woocommerce) {
            $products->addSelect('woocommerce_disable_sync');
        }

        $products->groupBy('products.id');

        $type = $f['type'] ?? null;
        if (! empty($type)) {
            $products->where('products.type', $type);
        }

        $category_id = $f['category_id'] ?? null;
        if (! empty($category_id)) {
            $products->where('products.category_id', $category_id);
        }

        $brand_id = $f['brand_id'] ?? null;
        if (! empty($brand_id)) {
            $products->where('products.brand_id', $brand_id);
        }

        $unit_id = $f['unit_id'] ?? null;
        if (! empty($unit_id)) {
            $products->where('products.unit_id', $unit_id);
        }

        $tax_id = $f['tax_id'] ?? null;
        if (! empty($tax_id)) {
            $products->where('products.tax', $tax_id);
        }

        $active_state = $f['active_state'] ?? null;
        if ($active_state == 'active') {
            $products->Active();
        }
        if ($active_state == 'inactive') {
            $products->Inactive();
        }
        if (! empty($f['not_for_selling'])) {
            $products->ProductNotForSales();
        }

        if (! empty($f['woocommerce_enabled'])) {
            $products->where('products.woocommerce_disable_sync', 0);
        }

        if (! empty($f['repair_model_id'])) {
            $products->where('products.repair_model_id', $f['repair_model_id']);
        }

        // UC-PIDX-31: natureza fiscal decidida pelo contador. Produto sem decisão é mercadoria (é o que a
        // emissão faz). Sem o módulo Fiscal o filtro não aparece na tela e aqui não filtra.
        if (! empty($f['natureza_fiscal']) && app()->bound(\App\Contracts\Fiscal\NaturezaDoProduto::class)) {
            $natureza = app(\App\Contracts\Fiscal\NaturezaDoProduto::class);
            if ($f['natureza_fiscal'] === 'mercadoria') {
                $fora = array_merge($natureza->produtosCom($business_id, 'servico'), $natureza->produtosCom($business_id, 'pendente'));
                if ($fora !== []) {
                    $products->whereNotIn('products.id', $fora);
                }
            } else {
                $products->whereIn('products.id', $natureza->produtosCom($business_id, (string) $f['natureza_fiscal']) ?: [0]);
            }
        }

        return $products;
    }

    /**
     * Filtros da lista React. Mesmos nomes que o DataTable do Blade manda pra `consultaListaProdutos`
     * (`type`, `category_id`, `brand_id`, `unit_id`, `tax_id`, `location_id`, `active_state`,
     * `not_for_selling`, `woocommerce_enabled`, `repair_model_id`), mais busca (`q`), ordenação
     * (`sort`/`dir`) e paginação (`page`/`per_page`) — os nomes que o `shared/DataTable` usa.
     *
     * `per_page` aceita as MESMAS opções do DataTable (`public/js/common.js` aLengthMenu:
     * 25/50/100/200/500/1000/todas = -1) e o padrão é o MESMO do Blade: a configuração da empresa
     * `common_settings.default_datatable_page_entries`, 25 se vazia
     * (`layouts/partials/javascripts.blade.php`).
     */
    protected function filtrosListaProdutos(): array
    {
        $opcoesPorPagina = [25, 50, 100, 200, 500, 1000, -1];
        $common = request()->session()->get('business.common_settings') ?: [];
        $padrao = ! empty($common['default_datatable_page_entries']) ? (int) $common['default_datatable_page_entries'] : 25;
        if (! in_array($padrao, $opcoesPorPagina, true)) {
            $padrao = 25;
        }
        $porPagina = request()->has('per_page') ? (int) request()->input('per_page') : $padrao;
        if (! in_array($porPagina, $opcoesPorPagina, true)) {
            $porPagina = $padrao;
        }
        // A aba "Relatório de estoque" tem o próprio tamanho de página, com as MESMAS opções e o MESMO
        // padrão: na lista Blade as duas abas eram DataTables com o mesmo menu (`common.js` aLengthMenu).
        $estoquePorPagina = request()->has('estoque_por_pagina') ? (int) request()->input('estoque_por_pagina') : $padrao;
        if (! in_array($estoquePorPagina, $opcoesPorPagina, true)) {
            $estoquePorPagina = $padrao;
        }

        $texto = function (string $k) {
            $v = request()->input($k);

            return ($v === null || $v === '') ? null : (string) $v;
        };
        $estado = request()->input('active_state');

        return [
            'type' => $texto('type'),
            'category_id' => $texto('category_id'),
            'brand_id' => $texto('brand_id'),
            'unit_id' => $texto('unit_id'),
            'tax_id' => $texto('tax_id'),
            'location_id' => $texto('location_id'),
            'active_state' => in_array($estado, ['active', 'inactive'], true) ? $estado : null,
            'not_for_selling' => request()->boolean('not_for_selling'),
            'woocommerce_enabled' => request()->boolean('woocommerce_enabled'),
            'repair_model_id' => $texto('repair_model_id'),
            // UC-PIDX-31: natureza fiscal (módulo Fiscal). Só existe na lista React; o DataTable do Blade não manda.
            'natureza_fiscal' => in_array(request()->input('natureza_fiscal'), ['mercadoria', 'servico', 'pendente'], true) ? (string) request()->input('natureza_fiscal') : null,
            // Aba ativa (protótipo: "Todos os produtos" | "Relatório de estoque") e a página da aba de estoque.
            'aba' => request()->input('aba') === 'estoque' ? 'estoque' : 'lista',
            'estoque_pagina' => max(1, (int) request()->input('estoque_pagina', 1)),
            'estoque_por_pagina' => $estoquePorPagina,
            'q' => trim((string) request()->input('q', '')),
            'sort' => (string) request()->input('sort', 'produto'),
            'dir' => request()->input('dir') === 'desc' ? 'desc' : 'asc',
            'per_page' => $porPagina,
            'page' => max(1, (int) request()->input('page', 1)),
        ];
    }

    /**
     * Busca da lista React com a MESMA regra da busca do DataTable do Blade (medida em produção
     * 2026-10-10 e lida em `config/datatables.php` + `product/index.blade.php`):
     *   · cada PALAVRA precisa aparecer em alguma coluna (`multi_term`); maiúscula = minúscula;
     *   · colunas pesquisáveis: nome, SKU do produto, SKU da variação (`filterColumn('products.sku')`),
     *     tipo (valor interno), categoria, marca e campos personalizados 1-7;
     *   · NÃO entram: imagem, locais, imposto, unidade, preços e estoque. Medido no DataTable vivo
     *     em 2026-10-10: `products.image` é `searchable: false`, e `product_locations` aparece como
     *     pesquisável mas é coluna montada — buscar o nome do local não filtra (os acertos vêm da
     *     marca de mesmo nome).
     * Porcentagem e sublinhado digitados viram literais (no Blade eram curinga).
     */
    protected function aplicarBuscaListaProdutos($query, string $busca): void
    {
        $termos = preg_split('/\s+/u', $busca, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        foreach ($termos as $termo) {
            $like = '%' . addcslashes($termo, chr(92) . '%_') . '%';
            $query->where(function ($q) use ($like) {
                $q->where('products.name', 'like', $like)
                    ->orWhere('products.sku', 'like', $like)
                    ->orWhereHas('variations', function ($v) use ($like) {
                        $v->where('sub_sku', 'like', $like);
                    })
                    ->orWhere('products.type', 'like', $like)
                    ->orWhere('c1.name', 'like', $like)
                    ->orWhere('brands.name', 'like', $like);
                for ($i = 1; $i <= 7; $i++) {
                    $q->orWhere('products.product_custom_field' . $i, 'like', $like);
                }
            });
        }
    }

    /**
     * Painel de detalhe do produto (protótipo `produto-blade.jsx`, `DetalheDrawer`) com o conteúdo da
     * janela "Ver" da lista Blade (`ProductController::view` + `product/view-modal.blade.php`) e os
     * MESMOS gates dela:
     *   · preço de compra só com `view_purchase_price`; preço de venda só com
     *     `access_default_selling_price`; % de lucro só com as duas;
     *   · preço por grupo só dos grupos que o usuário pode ver (`selling_price_group.{id}`);
     *   · estoque por local só com `stock_report.view` (a Blade o carregava de `/reports/stock-report`,
     *     que pede isso) — linhas de `getProductStockDetails` + `linhaDoRelatorioEstoque`, as de
     *     Relatórios → Estoque; valores só com `view_product_stock_value`.
     *   · indicadores da aba Estoque (`estoque_totais`): a soma dessas linhas por
     *     `ProductUtil::somaDasLinhasDoEstoque`, a mesma do rodapé do relatório — nenhuma conta nova.
     * Divergência DELIBERADA, mais restrita: o total da composição pelo custo só vai com
     * `view_purchase_price` (a Blade mostrava a coluna a todos). A conta é a da Blade, sem mudança.
     * Cada item da composição traz as mesmas colunas de preço da Blade (`combo_product_details`), com
     * os mesmos gates das variações; `composicao_total_exc` é a soma dos totais dos itens (o
     * "Total líquido" do protótipo), só com `view_purchase_price`. Variações e itens trazem as
     * imagens da variação (`variation_images` da Blade), só as da própria empresa.
     * Dados fiscais: as colunas do próprio produto, inclusive a origem da mercadoria
     * (`products.origem_mercadoria`, por produto desde 2026-10-15).
     * Produto de outra empresa ⇒ null (Tier 0, ADR 0093).
     *
     * @return array<string, mixed>|null
     */
    protected function buildProdutoDetalhe(int $business_id, int $id): ?array
    {
        $produto = Product::where('business_id', $business_id)->find($id);
        if ($produto === null) {
            return null;
        }
        // Relações lidas por getRelationValue: o Larastan não enxerga variations/product_locations/warranty
        // no App\Product (já estão no baseline em outros pontos deste arquivo e a catraca não deixa crescer).
        $modelosVariacao = collect($produto->getRelationValue('variations'));
        // `type` também por getAttribute: o Larastan deduz o enum da migration original, sem 'combo'
        // (que entrou depois), e conclui que `=== 'combo'` é sempre falso — o banco tem as 4 opções.
        $tipo = (string) $produto->getAttribute('type');
        $marca = $produto->getRelationValue('brand');
        $unidade = $produto->getRelationValue('unit');
        $categoria = $produto->getRelationValue('category');
        $subcategoria = $produto->getRelationValue('sub_category');
        $imposto = $produto->getRelationValue('product_tax');
        $controlaEstoque = (bool) $produto->getAttribute('enable_stock');
        $alertaBruto = $produto->getAttribute('alert_quantity');
        $preparo = $produto->getAttribute('preparation_time_in_minutes');
        $garantia = $produto->getRelationValue('warranty');

        $u = auth()->user();
        $verCusto = (bool) $u->can('view_purchase_price');
        $verPreco = (bool) $u->can('access_default_selling_price');
        $grupos = SellingPriceGroup::where('business_id', $business_id)->active()->pluck('name', 'id')
            ->filter(fn ($nome, $gid) => $u->can('selling_price_group.' . $gid));

        // Variações: os campos de `single_product_details` / `variable_product_details`. Sem a permissão
        // a CHAVE não existe — ausência não é zero.
        // UC-PIDX-32 (UC-FLX-38): o método de custo só vale quando a empresa gravou a regra "Atualizar o
        // custo dos insumos" e ela dispara no Recebido — sem isso o custo é o último preço digitado.
        $metodoDaRegra = $verCusto ? $this->metodoDeCustoDaEmpresa($business_id) : null;

        $variacoes = [];
        foreach ($modelosVariacao as $v) {
            $porGrupo = $v->group_prices->keyBy('price_group_id');
            $linha = [
                'nome' => $tipo === 'variable'
                    ? trim(($v->product_variation->name ?? '') . ' - ' . $v->name, ' -')
                    : (string) $v->name,
                'sku' => (string) $v->sub_sku,
                'imagens' => $this->imagensDaVariacao($v, $business_id),
            ];
            if ($verCusto) {
                $linha['compra_exc'] = (float) $v->default_purchase_price;
                $linha['compra_inc'] = (float) $v->dpp_inc_tax;
                if ($metodoDaRegra !== null) {
                    $linha['custo_metodo'] = $this->metodoDeCustoDaVariacao($business_id, (int) $v->id, $metodoDaRegra);
                }
            }
            if ($verPreco) {
                if ($verCusto) {
                    $linha['lucro_percentual'] = (float) $v->profit_percent;
                }
                $linha['venda_exc'] = (float) $v->default_sell_price;
                $linha['venda_inc'] = (float) $v->sell_price_inc_tax;
            }
            $linha['grupos'] = $grupos->map(fn ($nome, $gid) => [
                'grupo' => (string) $nome,
                'preco' => isset($porGrupo[$gid]) ? (float) $porGrupo[$gid]->calculated_price : null,
            ])->values();

            $variacoes[] = $linha;
        }

        // Composição: `combo_product_details`. O total pelo custo é a conta da Blade
        // (custo × quantidade × multiplicador da unidade), só com `view_purchase_price`.
        $composicao = [];
        if ($tipo === 'combo' && $modelosVariacao->first() !== null) {
            foreach ($this->productUtil->__getComboProductDetails($modelosVariacao->first()->combo_variations ?? [], $business_id) as $c) {
                $var = $c['variation'];
                if ($var === null) {
                    continue;
                }
                $item = [
                    'nome' => $var->product->name . ($var->product->type === 'variable' ? ' - ' . $var->name : ''),
                    'sku' => (string) $var->sub_sku,
                    'quantidade' => (float) $c['quantity'],
                    'unidade' => (string) ($c['unit_name'] ?? ''),
                    'imagens' => $this->imagensDaVariacao($var, $business_id),
                ];
                if ($verCusto) {
                    $item['compra_exc'] = (float) $var->default_purchase_price;
                    $item['compra_inc'] = (float) $var->dpp_inc_tax;
                    $item['total_exc'] = (float) ($var->default_purchase_price * $c['quantity'] * $c['multiplier']);
                }
                if ($verPreco) {
                    if ($verCusto) {
                        $item['lucro_percentual'] = (float) $var->profit_percent;
                    }
                    $item['venda_exc'] = (float) $var->default_sell_price;
                    $item['venda_inc'] = (float) $var->sell_price_inc_tax;
                }
                $composicao[] = $item;
            }
        }
        // "Total líquido" do protótipo: a soma dos totais dos itens, a mesma conta de cada linha.
        $composicaoTotalExc = $verCusto && $tipo === 'combo'
            ? (float) array_sum(array_column($composicao, 'total_exc'))
            : null;

        $estoque = null;
        // Indicadores da aba Estoque (protótipo: estoque atual · valor pelo custo · valor pela venda):
        // a soma das linhas acima, pela mesma função do rodapé do relatório de estoque.
        $estoqueTotais = null;
        if ($controlaEstoque && $u->can('stock_report.view')) {
            $verValor = (bool) $u->can('view_product_stock_value');
            $estoque = collect(
                $this->productUtil
                    ->getProductStockDetails($business_id, $this->productUtil->filtrosDoEstoque(['product_id' => $id], 0), 'datatables')
                    ->orderBy('variations.sub_sku', 'asc')->orderBy('vld.location_id', 'asc')
                    ->toBase()->get()
            )->map(fn (\stdClass $r): array => $this->productUtil->linhaDoRelatorioEstoque($r, $verPreco, $verValor))->values();
            $estoqueTotais = $this->productUtil->somaDasLinhasDoEstoque($estoque, $verValor);
        }

        $rotulos = json_decode((string) request()->session()->get('business.custom_labels'), true);
        $rotulos = is_array($rotulos) && isset($rotulos['product']) && is_array($rotulos['product']) ? $rotulos['product'] : [];
        $campos = [];
        for ($i = 1; $i <= 20; $i++) {
            $valor = $produto->{'product_custom_field' . $i};
            if ($valor !== null && $valor !== '') {
                $campos[] = ['rotulo' => (string) ($rotulos['custom_field_' . $i] ?? ''), 'valor' => (string) $valor];
            }
        }

        return [
            'id' => (int) $produto->id,
            'nome' => (string) $produto->name,
            'sku' => (string) $produto->sku,
            'tipo' => $tipo,
            'inativo' => (bool) $produto->is_inactive,
            // Sem foto vai null (o painel desenha a caixa "Sem imagem" do protótipo): o acessor
            // `image_url` devolveria a imagem genérica /img/default.png — UC-PIDX-22.
            'imagem' => empty($produto->image) ? null : $produto->image_url,
            'dados' => [
                'codigo_de_barras' => (string) ($produto->barcode_type ?? ''),
                'unidade' => (string) ($unidade->actual_name ?? ''),
                'unidade_curta' => (string) ($unidade->short_name ?? ''),
                'marca' => (string) ($marca->name ?? ''),
                'categoria' => (string) ($categoria->name ?? ''),
                'subcategoria' => (string) ($subcategoria->name ?? ''),
                'imposto' => (string) ($imposto->name ?? ''),
                'tipo_imposto' => (string) ($produto->tax_type ?? ''),
                'peso' => (string) ($produto->weight ?? ''),
                'controla_estoque' => $controlaEstoque,
                'alerta' => $controlaEstoque && $alertaBruto !== null ? (float) $alertaBruto : null,
                'validade' => ! empty($produto->expiry_period) && ! empty($produto->expiry_period_type)
                    ? ['periodo' => (float) $produto->expiry_period, 'tipo' => (string) $produto->expiry_period_type]
                    : null,
                'locais' => collect($produto->getRelationValue('product_locations'))->pluck('name')->values(),
                'nao_para_venda' => (bool) $produto->not_for_selling,
                'garantia' => $garantia ? (string) $garantia->display_name : null,
                'tempo_preparo' => $preparo !== null ? (int) $preparo : null,
                'descricao' => trim(strip_tags((string) ($produto->product_description ?? ''))),
                'campos' => $campos,
            ],
            'variacoes' => $variacoes,
            'composicao' => $composicao,
            'composicao_total_exc' => $composicaoTotalExc,
            'estoque' => $estoque,
            'estoque_totais' => $estoqueTotais,
            'fiscal' => [
                'ncm' => (string) ($produto->ncm ?? ''),
                'cest' => (string) ($produto->cest ?? ''),
                'cfop_interno' => (string) ($produto->cfop_interno ?? ''),
                'cfop_externo' => (string) ($produto->cfop_externo ?? ''),
                'cst_csosn' => (string) ($produto->cst_csosn ?? ''),
                'cst_pis' => (string) ($produto->cst_pis ?? ''),
                'cst_cofins' => (string) ($produto->cst_cofins ?? ''),
                'cst_ipi' => (string) ($produto->cst_ipi ?? ''),
                // UC-PIDX-25. A origem do próprio produto (`products.origem_mercadoria`, tabela A da
                // NF-e), a mesma que as duas emissões usam no `orig` de cada item. Produto sem escolha
                // fica com o DEFAULT 0 da coluna.
                'origem' => (string) ($produto->origem_mercadoria ?? 0),
                'origem_por_produto' => true,
                'link_modulo' => $this->linkTributacaoDoProduto($business_id, $id),
                // UC-PIDX-31: null sem o módulo Fiscal. Sem decisão registrada é mercadoria.
                'natureza' => app()->bound(\App\Contracts\Fiscal\NaturezaDoProduto::class)
                    ? (app(\App\Contracts\Fiscal\NaturezaDoProduto::class)->dosProdutos($business_id, [$id])[$id] ?? ['natureza' => 'mercadoria', 'codigo_servico' => null])
                    : null,
            ],
            'prateleiras' => [
                'colunas' => [
                    'prateleira' => (bool) request()->session()->get('business.enable_racks'),
                    'fileira' => (bool) request()->session()->get('business.enable_row'),
                    'posicao' => (bool) request()->session()->get('business.enable_position'),
                ],
                'linhas' => collect($this->productUtil->getRackDetails($business_id, $id, true))->map(fn ($r) => [
                    'local' => (string) $r->name, 'prateleira' => (string) ($r->rack ?? ''), 'fileira' => (string) ($r->row ?? ''), 'posicao' => (string) ($r->position ?? ''),
                ])->values(),
            ],
            // UC-PIDX-29 (UC-FLX-07 · UC-FLX-08): o roteiro ligado ao produto, com o posto e o setor
            // de cada etapa. null sem o módulo Produção ou sem roteiro. Só leitura.
            'roteiro' => app()->bound(\App\Contracts\Producao\RoteiroDoProduto::class)
                ? app(\App\Contracts\Producao\RoteiroDoProduto::class)->doProduto($business_id, $id)
                : null,
        ] + ($verPreco ? ['precos_propostos' => $this->precosPropostosDoProduto($business_id, $id)] : [])
          + (($requisicao = $this->requisicoesAbertasDosProdutos($business_id, [$id])) !== null ? ['requisicao' => $requisicao[$id] ?? null] : []);
    }

    /**
     * UC-PIDX-30 (UC-FLX-19): a requisição de compra aberta (`ordered` ou `partial`) mais recente de cada
     * produto — a que o estoque mínimo cria sozinho (US-COM-027) ou uma feita à mão. Mesmas permissões da
     * lista de requisições: `purchase_requisition.view_all` vê todas; `view_own`, só as que a pessoa criou;
     * sem nenhuma das duas, null (a chave não vai para a tela). Só leitura; Tier 0: só a empresa.
     *
     * @param  list<int>  $ids
     * @return array<int, array{id: int, numero: string, data: string, fornecedor: ?string, url: string}>|null
     */
    protected function requisicoesAbertasDosProdutos(int $business_id, array $ids): ?array
    {
        $u = auth()->user();
        $todas = (bool) $u->can('purchase_requisition.view_all');
        if (! $todas && ! $u->can('purchase_requisition.view_own')) {
            return null;
        }
        if ($ids === []) {
            return [];
        }
        $consulta = DB::table('purchase_lines as pl')
            ->join('transactions as t', 't.id', '=', 'pl.transaction_id')
            ->leftJoin('contacts as c', fn ($j) => $j->on('c.id', '=', 't.contact_id')->where('c.business_id', $business_id))
            ->where('t.business_id', $business_id)
            ->where('t.type', 'purchase_requisition')
            ->whereIn('t.status', ['ordered', 'partial'])
            ->whereIn('pl.product_id', $ids);
        if (! $todas) {
            $consulta->where('t.created_by', $u->id);
        }
        $porProduto = [];
        // Ordem crescente: a mais recente de cada produto fica por último e vence.
        foreach ($consulta->orderBy('t.transaction_date')->orderBy('t.id')
            ->get(['pl.product_id', 't.id', 't.ref_no', 't.transaction_date', 'c.name', 'c.supplier_business_name']) as $r) {
            $fornecedor = trim((string) ($r->supplier_business_name ?: $r->name));
            $porProduto[(int) $r->product_id] = [
                'id' => (int) $r->id,
                'numero' => (string) $r->ref_no,
                'data' => substr((string) $r->transaction_date, 0, 10),
                'fornecedor' => $fornecedor === '' ? null : $fornecedor,
                'url' => '/purchase-requisition/' . (int) $r->id,
            ];
        }

        return $porProduto;
    }

    /**
     * Método da regra de custo da empresa (`medio`, `ultimo` ou `por_insumo`), só quando a regra está
     * gravada, ligada e dispara no Recebido — as mesmas condições em que o recebimento a aplica
     * (AplicarCustoDoRecebimento). Sem o módulo Compras, null.
     */
    protected function metodoDeCustoDaEmpresa(int $business_id): ?string
    {
        if (! app()->bound(\App\Contracts\Compras\CustoDoInsumo::class)) {
            return null;
        }
        $regra = app(\App\Domain\Fsm\Modelos\RegrasDaEmpresa::class)->custoGravado($business_id);
        if ($regra === null || empty($regra['ativo']) || ($regra['params']['etapa'] ?? null) !== 'recebido') {
            return null;
        }

        return (string) ($regra['params']['metodo'] ?? 'medio');
    }

    /**
     * UC-PIDX-32: o método que vale para a variação (o do insumo, quando a regra é "por insumo") e a data
     * da última compra recebida, que é quando o custo mudou pela última vez.
     *
     * @return array{metodo: ?string, ultima_compra_em: ?string}
     */
    protected function metodoDeCustoDaVariacao(int $business_id, int $variationId, string $metodoDaRegra): array
    {
        $custo = app(\App\Contracts\Compras\CustoDoInsumo::class);
        $metodo = $metodoDaRegra === 'por_insumo' ? $custo->metodoDoInsumo($business_id, $variationId) : $metodoDaRegra;
        if ($metodo === null) {
            return ['metodo' => null, 'ultima_compra_em' => null];
        }
        $r = $custo->naData($business_id, $variationId, now(), $metodo);

        return ['metodo' => $metodo, 'ultima_compra_em' => $r['ultima_compra_em'] === null ? null : substr((string) $r['ultima_compra_em'], 0, 10)];
    }

    /**
     * UC-PIDX-28 (UC-FLX-17): preços propostos pela compra recebida que aguardam aprovação. A chave só
     * vai para quem vê o preço de venda (gate da UC-PIDX-03). Só leitura: aprovar e recusar ficam no
     * detalhe da compra (US-COM-028) — a consulta não altera preço (charter, Automation Anti-hooks).
     * O link da compra só vai para quem pode abri-la (`purchase.view`).
     *
     * @return list<array<string, mixed>>
     */
    protected function precosPropostosDoProduto(int $business_id, int $id): array
    {
        if (! app()->bound(\App\Contracts\Compras\PropostasDePreco::class)) {
            return [];
        }
        $abreCompra = (bool) auth()->user()->can('purchase.view');
        $itens = app(\App\Contracts\Compras\PropostasDePreco::class)->pendentesDosProdutos($business_id, [$id])[$id] ?? [];

        return array_map(fn (array $p): array => $p + ['compra_url' => $abreCompra ? '/purchases/' . $p['compra_id'] : null], $itens);
    }

    /**
     * "Abrir no módulo Fiscal" da aba Fiscal (UC-PIDX-25): a Tributação do NfeBrasil, com este produto
     * já escolhido no simulador. Regra decidida por [F] em 2026-10-16: o botão aparece só com o
     * módulo Fiscal no pacote da empresa E para quem tem a permissão de acessar o módulo
     * (`fiscal.access`) — as mesmas duas checagens do menu Fiscal (Modules/Fiscal
     * DataController::modifyAdminMenu), incluindo o atalho do superadmin. Exige também
     * `nfe.tributacao.manage`, a permissão que o simulador cobra: sem ela a tela abriria e
     * recusaria a simulação. null quando qualquer uma falta, ou a rota não existe.
     */
    /**
     * Natureza fiscal do produto (mercadoria · serviço · pendente), decidida pelo contador na
     * Tributação — UC-PEDIT-14 · CU-PROD-19. Vem do módulo fiscal pelo gancho `getModuleData`,
     * sem importar o módulo (app/ não depende de Modules/). Sem o módulo ou sem decisão: null.
     *
     * @return array{natureza: string, codigoServico: string|null, link: string|null}|null
     */
    private function naturezaFiscalDoProduto(int $business_id, int $id): ?array
    {
        $respostas = $this->moduleUtil->getModuleData('produto_natureza_fiscal', ['business_id' => $business_id, 'product_id' => $id]);
        $dado = is_array($respostas) ? ($respostas['NfeBrasil'] ?? null) : null;
        if (! is_array($dado) || empty($dado['natureza'])) {
            return null;
        }

        return [
            'natureza' => (string) $dado['natureza'],
            'codigoServico' => isset($dado['codigo_servico']) ? (string) $dado['codigo_servico'] : null,
            'link' => $this->linkTributacaoDoProduto($business_id, $id),
        ];
    }

    /**
     * A empresa emite nota? O teste é ter o módulo Fiscal no pacote, o mesmo que já decidia o botão
     * "Abrir no módulo Fiscal"; o superadmin vale pelo módulo instalado. Decide a aba Fiscal do painel e o "Dados fiscais" do menu
     * da linha (decisão [F] 2026-10-26: muitas empresas vendem sem nota, e aí a aba só atrapalha).
     */
    private function moduloFiscalNoPacote(int $business_id): bool
    {
        return auth()->user()?->can('superadmin')
            ? (bool) $this->moduleUtil->isModuleInstalled('Fiscal')
            : (bool) $this->moduleUtil->hasThePermissionInSubscription($business_id, 'fiscal_module', 'superadmin_package');
    }

    private function linkTributacaoDoProduto(int $business_id, int $id): ?string
    {
        $usuario = auth()->user();
        if (! $usuario || ! \Illuminate\Support\Facades\Route::has('nfe-brasil.tributacao.index')) {
            return null;
        }
        $superadmin = $usuario->can('superadmin');
        $acessaModulo = $superadmin || $usuario->can('fiscal.access');
        if (! $this->moduloFiscalNoPacote($business_id) || ! $acessaModulo || ! $usuario->can('nfe.tributacao.manage')) {
            return null;
        }

        return route('nfe-brasil.tributacao.index', ['produto' => $id]).'#simulador';
    }

    /**
     * URLs das imagens da variação — `variation_images` da janela "Ver" da Blade
     * (`$variation->media`). Só as da própria empresa (ADR 0093).
     *
     * @return list<string>
     */
    private function imagensDaVariacao(Variation $variacao, int $business_id): array
    {
        return collect($variacao->getRelationValue('media'))
            ->filter(fn ($m) => (int) $m->business_id === $business_id)
            ->map(fn ($m): string => (string) $m->display_url)
            ->values()
            ->all();
    }

    /**
     * Aba "Relatório de estoque" da lista (protótipo `produto-blade.jsx`, vista `lista`,
     * `RelatorioEstoque`; a aba `#product_stock_report` da lista Blade). As linhas são as de
     * Relatórios → Estoque — `ProductUtil::getProductStockDetails` + `linhaDoRelatorioEstoque` —
     * com os filtros que a aba Blade mandava (`product/index.blade.php`, `stock_report_table`):
     * local, categoria, marca, unidade, tipo, situação, "não para venda" e modelo. Tamanho da página
     * em `estoque_por_pagina` (as opções da lista; -1 = todas as linhas), na ordem SKU e local (a da
     * tela de estoque). Os totais não dependem da página. A busca da lista não entra: a aba Blade
     * também não a usava.
     *
     * @param  array<string, mixed>  $f  `filtrosListaProdutos()`
     * @return array<string, mixed>
     */
    protected function buildRelatorioEstoqueDaLista(int $business_id, array $f, int $mfg, bool $recalcular): array
    {
        $entrada = array_filter([
            'location_id' => $f['location_id'],
            'category_id' => $f['category_id'],
            'brand_id' => $f['brand_id'],
            'unit_id' => $f['unit_id'],
            'type' => $f['type'],
            'active_state' => $f['active_state'],
            'repair_model_id' => $f['repair_model_id'],
            // `filtrosDoEstoque` espera a string 'true', como o DataTable mandava.
            'not_for_selling' => $f['not_for_selling'] ? 'true' : null,
        ], fn ($v) => $v !== null && $v !== '');
        $filtros = $this->productUtil->filtrosDoEstoque($entrada, $mfg);
        $verPreco = (bool) auth()->user()->can('access_default_selling_price');
        $verValor = (bool) auth()->user()->can('view_product_stock_value');

        $consulta = $this->productUtil->getProductStockDetails($business_id, $filtros, 'datatables')
            ->orderBy('variations.sub_sku', 'asc')->orderBy('vld.location_id', 'asc')
            ->toBase();
        // -1 = "Todos": uma página só, do tamanho do relatório (como `buildProdutoListaPaginada`).
        $porPagina = $f['estoque_por_pagina'] === -1
            ? max(1, (clone $consulta)->getCountForPagination())
            : $f['estoque_por_pagina'];
        $pagina = $consulta->paginate($porPagina, ['*'], 'estoque_pagina', $f['estoque_pagina']);

        return [
            'linhas' => collect($pagina->items())
                ->map(fn (\stdClass $r): array => $this->productUtil->linhaDoRelatorioEstoque($r, $verPreco, $verValor))
                ->values(),
            'totais' => $this->totaisDoRelatorioEstoque($business_id, $filtros, $verValor, $recalcular),
            'mostra_preco' => $verPreco,
            'mostra_valor' => $verValor,
            'paginacao' => [
                'atual' => $pagina->currentPage(), 'ultima' => $pagina->lastPage(), 'total' => $pagina->total(),
                'por_pagina' => $f['estoque_por_pagina'],
            ],
        ];
    }

    /**
     * Rodapé da aba: totais de TODAS as linhas do relatório com os filtros da tela (protótipo:
     * "Totais (todas as N linhas)"; a Blade somava só a página). Soma, linha a linha, o que
     * `linhaDoRelatorioEstoque` devolve — as MESMAS contas da tela de estoque, nenhuma nova. Sem
     * filtro, o "valor pelo custo" daqui é o indicador "Valor em estoque (custo)" do topo
     * (`valorDoEstoquePeloCusto`, decisão [F] 2026-10-10, opção B). Cache de 10 min por empresa,
     * locais permitidos, filtros e permissão de valor; o "Atualizado" refaz a conta.
     *
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    protected function totaisDoRelatorioEstoque(int $business_id, array $filtros, bool $verValor, bool $recalcular): array
    {
        $chave = 'produto:lista:estoque-totais:biz:' . $business_id
            . ':loc:' . md5((string) json_encode(auth()->user()->permitted_locations()))
            . ':f:' . md5((string) json_encode($filtros)) . ':v:' . (int) $verValor;
        if ($recalcular) {
            \Illuminate\Support\Facades\Cache::forget($chave);
        }

        return \Illuminate\Support\Facades\Cache::remember($chave, now()->addMinutes(10), function () use ($business_id, $filtros, $verValor) {
            // A soma mora em `ProductUtil::somaDasLinhasDoEstoque` (a mesma dos indicadores do painel de detalhe).
            $t = $this->productUtil->somaDasLinhasDoEstoque(
                $this->productUtil->getProductStockDetails($business_id, $filtros, 'datatables')->toBase()->cursor()
                    ->map(fn (\stdClass $r): array => $this->productUtil->linhaDoRelatorioEstoque($r, false, $verValor)),
                $verValor
            );
            $t['em'] = now()->toIso8601String();

            return $t;
        });
    }

    /**
     * Indicadores do topo da lista (protótipo `produto-blade.jsx`, vista `lista`, `kpi`) sobre o
     * catálogo INTEIRO — como no protótipo, não seguem os filtros da tela.
     *   · contagens: a MESMA consulta da lista (`consultaListaProdutos`) sem filtro — o total é o
     *     mesmo que a lista mostra sem filtro; "abaixo do alerta" é a regra da linha
     *     (`abaixo_do_alerta` em `buildProdutoListaPaginada`): estoque gerenciado, alerta definido e
     *     estoque ≤ alerta;
     *   · valor: ver `valorDoEstoquePeloCusto`. Só para quem pode ver o relatório E o valor do
     *     estoque (`stock_report.view` + `view_product_stock_value`, o gate do `getStockValue`).
     */
    protected function buildProdutoListaKpis(int $business_id, bool $is_woocommerce, bool $recalcular): array
    {
        $contagem = DB::query()
            ->fromSub($this->consultaListaProdutos($business_id, [], $is_woocommerce)->toBase(), 'l')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(l.type = 'variable') as variaveis")
            ->selectRaw("SUM(l.type = 'combo') as composicoes")
            ->selectRaw('SUM(l.is_inactive = 1) as inativos')
            ->selectRaw('SUM(l.enable_stock = 1 AND l.alert_quantity IS NOT NULL AND COALESCE(l.current_stock, 0) <= l.alert_quantity) as abaixo_do_alerta')
            ->first();

        $podeVerValor = auth()->user()->can('stock_report.view') && auth()->user()->can('view_product_stock_value');
        $valor = $podeVerValor ? $this->valorDoEstoquePeloCusto($business_id, $recalcular) : null;

        return [
            'total' => (int) ($contagem->total ?? 0),
            'variaveis' => (int) ($contagem->variaveis ?? 0),
            'composicoes' => (int) ($contagem->composicoes ?? 0),
            'abaixo_do_alerta' => (int) ($contagem->abaixo_do_alerta ?? 0),
            'inativos' => (int) ($contagem->inativos ?? 0),
            'valor_custo' => $valor === null ? null : $valor['valor'],
            'valor_calculado_em' => $valor === null ? null : $valor['em'],
        ];
    }

    /**
     * "Valor em estoque (custo)" = o total da coluna `stock_price` ("valor pelo preço de compra")
     * do relatório de estoque (`ProductUtil::getProductStockDetails`), todas as linhas e sem
     * filtros — o mesmo número que a aba "Relatório de estoque" soma. Decisão [F] 2026-10-10
     * (opção "B"; a opção "A" seria o `getOpeningClosingStock` do topo de Relatórios → Estoque,
     * que conta outro conjunto de compras e dá ~0,1% de diferença).
     *
     * A expressão NÃO é reescrita: usa-se a própria coluna `stock_price` da consulta do relatório,
     * só sem as outras subconsultas (vendido/transferido/ajustado), e soma-se no banco. Prova em
     * produção (biz 1, 2026-10-10): igual à soma linha a linha da consulta completa, diferença 0.
     * Cache de 10 minutos por empresa + locais permitidos (a consulta do relatório já filtra pelos
     * locais do usuário): numa empresa com ~12 mil linhas a conta é cara. O botão "Atualizado"
     * da tela manda `X-Produto-Recalcular: 1` e refaz a conta.
     */
    protected function valorDoEstoquePeloCusto(int $business_id, bool $recalcular): array
    {
        $chave = 'produto:lista:valor-estoque-custo:biz:' . $business_id
            . ':loc:' . md5((string) json_encode(auth()->user()->permitted_locations()));
        if ($recalcular) {
            \Illuminate\Support\Facades\Cache::forget($chave);
        }

        return \Illuminate\Support\Facades\Cache::remember($chave, now()->addMinutes(10), function () use ($business_id) {
            $base = $this->productUtil
                ->getProductStockDetails($business_id, ['not_for_selling' => 0, 'show_manufacturing_data' => 0], 'datatables')
                ->toBase();
            $gramatica = DB::getQueryGrammar();
            $coluna = collect($base->columns)->first(function ($c) use ($gramatica) {
                return $c instanceof \Illuminate\Database\Query\Expression
                    && preg_match('/\bas stock_price\s*$/', (string) $c->getValue($gramatica)) === 1;
            });
            if ($coluna === null) {
                throw new \LogicException('A coluna stock_price saiu do relatório de estoque (ProductUtil::getProductStockDetails).');
            }
            $base->columns = [$coluna];

            return [
                'valor' => (float) DB::query()->fromSub($base, 'e')->sum('e.stock_price'),
                'em' => now()->toIso8601String(),
            ];
        });
    }

    /**
     * "Exportar filtrados" da lista (protótipo `produto-blade.jsx`, rodapé, `exportarCsv`): as MESMAS
     * linhas da lista — `buildProdutoListaPaginada` com os filtros, a busca e a ordem da tela, todas
     * as páginas — em CSV para o Excel brasileiro (`;`, vírgula decimal, UTF-8 com BOM). Preço de
     * compra e de venda só com a permissão: sem ela a COLUNA não existe, como na lista. Nenhuma conta
     * nova — sai o que a linha da lista já traz.
     *
     * @param  array<string, mixed>  $f  `filtrosListaProdutos()`
     */
    protected function exportarListaCsv(int $business_id, array $f, bool $podeVerCusto, bool $podeVerPreco, bool $is_woocommerce, bool $temAliquota = true): \Illuminate\Http\Response
    {
        $linhas = $this->buildProdutoListaPaginada($business_id, array_merge($f, ['per_page' => -1, 'page' => 1]), $podeVerCusto, $podeVerPreco, $is_woocommerce)['data'];
        $rotulos = array_filter($this->rotulosCamposProduto());
        $tipos = ['single' => 'Único', 'variable' => 'Variável', 'combo' => 'Composição'];
        $dinheiro = fn ($v) => $v === null ? '' : number_format((float) $v, 2, ',', '');
        $quantidade = fn ($v) => $v === null ? '' : rtrim(rtrim(number_format((float) $v, 4, ',', ''), '0'), ',');

        // A coluna Imposto segue a da lista: só em empresa com alíquota cadastrada (decisão [F]
        // 2026-10-26). Sem ela a coluna sairia vazia em todas as linhas.
        $cabecalho = array_merge(['Produto', 'SKU', 'Tipo', 'Categoria', 'Subcategoria', 'Marca', 'Unidade'],
            $temAliquota ? ['Imposto'] : [],
            ['Locais', 'Estoque atual', 'Quantidade de alerta', 'Situação', 'Não para venda']);
        if ($podeVerCusto) {
            array_push($cabecalho, 'Preço de compra (mín.)', 'Preço de compra (máx.)');
        }
        if ($podeVerPreco) {
            array_push($cabecalho, 'Preço de venda (mín.)', 'Preço de venda (máx.)');
        }
        foreach ($rotulos as $rotulo) {
            $cabecalho[] = (string) $rotulo;
        }

        // Montado em memória (e não em stream): `index()` declara Illuminate\Http\Response.
        $saida = fopen('php://temp', 'r+');
        if ($saida === false) {
            abort(500, 'Não foi possível montar o arquivo.');
        }
        fwrite($saida, "\xEF\xBB\xBF");
        fputcsv($saida, $cabecalho, ';', '"', '');
        foreach ($linhas as $l) {
            $linha = array_merge([
                (string) $l['nome'], (string) $l['sku'], $tipos[$l['tipo']] ?? (string) $l['tipo'],
                (string) ($l['categoria'] ?? ''), (string) ($l['subcategoria'] ?? ''), (string) ($l['marca'] ?? ''),
                (string) ($l['unidade'] ?? ''),
            ], $temAliquota ? [(string) ($l['imposto'] ?? '')] : [], [
                implode(', ', $l['locais']),
                $l['controla_estoque'] ? $quantidade($l['estoque']) : '', $quantidade($l['alerta']),
                $l['inativo'] ? 'Inativo' : 'Ativo', $l['nao_para_venda'] ? 'Sim' : 'Não',
            ]);
            if ($podeVerCusto) {
                array_push($linha, $dinheiro($l['compra_min'] ?? null), $dinheiro($l['compra_max'] ?? null));
            }
            if ($podeVerPreco) {
                array_push($linha, $dinheiro($l['venda_min'] ?? null), $dinheiro($l['venda_max'] ?? null));
            }
            foreach (array_keys($rotulos) as $i) {
                $linha[] = (string) ($l['campos'][$i] ?? '');
            }
            fputcsv($saida, $linha, ';', '"', '');
        }
        rewind($saida);
        $conteudo = (string) stream_get_contents($saida);
        fclose($saida);

        return response($conteudo, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="produtos-' . now()->format('Y-m-d-His') . '.csv"',
        ]);
    }

    /**
     * Página da lista React: `consultaListaProdutos` + busca + ordenação + paginação no servidor,
     * no formato do paginador do Laravel.
     * Preço de compra/venda só viaja para quem pode vê-los (mesmo gate do Blade e do UC-PIDX-03):
     * sem a permissão a CHAVE não existe na linha, e a coluna também não ordena.
     */
    protected function buildProdutoListaPaginada(int $business_id, array $f, bool $podeVerCusto, bool $podeVerPreco, bool $is_woocommerce): array
    {
        $query = $this->consultaListaProdutos($business_id, $f, $is_woocommerce)
            ->with('product_locations')
            ->withCount('variations')
            ->addSelect('units.short_name as unidade_curta', 'products.enable_sr_no');
        $this->aplicarBuscaListaProdutos($query, $f['q']);

        $ordenaveis = [
            'produto' => 'products.name',
            'estoque' => 'current_stock',
            'tipo' => 'products.type',
            'categoria' => 'c1.name',
            'marca' => 'brands.name',
            'imposto' => 'tax_rates.name',
            'sku' => 'products.sku',
        ];
        if ($podeVerCusto) {
            $ordenaveis['compra'] = 'max_purchase_price';
        }
        if ($podeVerPreco) {
            $ordenaveis['venda'] = 'max_price';
        }
        $coluna = $ordenaveis[$f['sort']] ?? 'products.name';
        $query->orderBy($coluna, $f['dir']);
        if ($coluna !== 'products.name') {
            $query->orderBy('products.name');
        }
        $query->orderBy('products.id');

        $porPagina = $f['per_page'] === -1
            ? max(1, (clone $query)->toBase()->getCountForPagination())
            : $f['per_page'];
        $pagina = $query->paginate($porPagina, ['*'], 'page', $f['page'])->withQueryString();

        // UC-PIDX-28 (UC-FLX-17): preços propostos pela compra recebida, aguardando aprovação, numa
        // consulta só para a página. Só para quem vê o preço de venda; sem o módulo Compras, nada.
        $propostas = $podeVerPreco && app()->bound(\App\Contracts\Compras\PropostasDePreco::class)
            ? app(\App\Contracts\Compras\PropostasDePreco::class)->pendentesDosProdutos(
                $business_id,
                array_values(array_map('intval', $pagina->getCollection()->pluck('id')->all()))
            )
            : [];

        $idsDaPagina = array_values(array_map('intval', $pagina->getCollection()->pluck('id')->all()));
        // UC-PIDX-30: requisição de compra aberta (sem permissão de ver requisições a chave não vem).
        $requisicoes = $this->requisicoesAbertasDosProdutos($business_id, $idsDaPagina);

        // UC-PIDX-31: natureza fiscal (sem o módulo Fiscal a chave não vem).
        $naturezas = app()->bound(\App\Contracts\Fiscal\NaturezaDoProduto::class)
            ? app(\App\Contracts\Fiscal\NaturezaDoProduto::class)->dosProdutos($business_id, $idsDaPagina)
            : null;

        $pagina->through(function ($row) use ($podeVerCusto, $podeVerPreco, $is_woocommerce, $propostas, $requisicoes, $naturezas) {
            $controla = (bool) $row->enable_stock;
            $estoque = $controla ? (float) ($row->current_stock ?? 0) : null;
            $alerta = $row->alert_quantity === null ? null : (float) $row->alert_quantity;
            $anexo = $row->media->first();

            $linha = [
                'id' => (int) $row->id,
                'nome' => (string) $row->product,
                'sku' => (string) ($row->sku ?? ''),
                'tipo' => (string) $row->type,
                'categoria' => $row->category,
                'subcategoria' => $row->sub_category,
                'unidade' => $row->unit,
                'unidade_curta' => $row->unidade_curta,
                'marca' => $row->brand,
                'imposto' => $row->tax,
                // Sem foto vai null (a coluna mostra o marcador "IMG" do protótipo), não a imagem
                // genérica do acessor `image_url` — UC-PIDX-22.
                'imagem' => empty($row->image) ? null : $row->image_url,
                'controla_estoque' => $controla,
                'estoque' => $estoque,
                'alerta' => $alerta,
                'abaixo_do_alerta' => $estoque !== null && $alerta !== null && $estoque <= $alerta,
                'inativo' => (bool) $row->is_inactive,
                'nao_para_venda' => (bool) $row->not_for_selling,
                'serie' => (bool) $row->enable_sr_no,
                'locais' => $row->product_locations->pluck('name')->values()->all(),
                'variacoes' => (int) $row->variations_count,
                'campos' => [
                    1 => $row->product_custom_field1, 2 => $row->product_custom_field2,
                    3 => $row->product_custom_field3, 4 => $row->product_custom_field4,
                    5 => $row->product_custom_field5, 6 => $row->product_custom_field6,
                    7 => $row->product_custom_field7,
                ],
                'anexo' => $anexo ? ['url' => $anexo->display_url, 'nome' => $anexo->display_name] : null,
            ];
            if ($is_woocommerce) {
                $linha['woocommerce_sync'] = ! $row->woocommerce_disable_sync;
            }
            if ($requisicoes !== null) {
                $linha['requisicao'] = $requisicoes[(int) $row->id] ?? null;
            }
            if ($naturezas !== null) {
                $linha['natureza_fiscal'] = $naturezas[(int) $row->id]['natureza'] ?? 'mercadoria';
            }
            if ($podeVerCusto) {
                $linha['compra_min'] = (float) $row->min_purchase_price;
                $linha['compra_max'] = (float) $row->max_purchase_price;
            }
            if ($podeVerPreco) {
                $linha['venda_min'] = (float) $row->min_price;
                $linha['venda_max'] = (float) $row->max_price;
                $pendentes = $propostas[(int) $row->id] ?? [];
                $linha['preco_proposto'] = $pendentes === []
                    ? null
                    : ['quantidade' => count($pendentes), 'preco' => count($pendentes) === 1 ? $pendentes[0]['preco_proposto'] : null];
            }

            return $linha;
        });

        // Formato do paginador do Laravel (data, total, current_page, last_page, from, to, links)
        // — o `PaginatorShape` que o `shared/DataTable` consome. `per_page` devolve o que foi
        // pedido (-1 = todas), não o tamanho interno usado pra trazer tudo numa página.
        return array_merge($pagina->toArray(), ['per_page' => $f['per_page']]);
    }

    /**
     * Opções dos filtros — as MESMAS listas que o `index()` já monta para o Blade (categorias,
     * marcas, unidades, impostos, locais com "Nenhum") e o filtro de módulo "Modelo do
     * dispositivo", que só existe quando a assistência técnica está ligada na empresa
     * (`Repair\DataController::get_filters_for_list_product_screen`).
     */
    protected function buildProdutoListaOpcoes($categories, $brands, $units, $taxes, $business_locations, $pos_module_data): array
    {
        $lista = function ($fonte) {
            $out = [];
            foreach (collect($fonte)->all() as $id => $nome) {
                $out[] = ['id' => (string) $id, 'nome' => (string) $nome];
            }

            return $out;
        };

        $modelos = null;
        foreach ((array) $pos_module_data as $dado) {
            if (is_array($dado) && isset($dado['view_data']['device_models'])) {
                $modelos = $lista($dado['view_data']['device_models']);
            }
        }

        return [
            'categorias' => $lista($categories),
            'marcas' => $lista($brands),
            'unidades' => $lista($units),
            'impostos' => $lista($taxes),
            'locais' => $lista($business_locations),
            'modelos_dispositivo' => $modelos,
        ];
    }

    /**
     * Rótulos dos campos personalizados de produto definidos pela empresa
     * (Configurações → rótulos). No Blade a coluna só aparece quando o rótulo existe.
     */
    protected function rotulosCamposProduto(): array
    {
        $rotulos = json_decode((string) request()->session()->get('business.custom_labels'), true);
        $produto = is_array($rotulos) && isset($rotulos['product']) && is_array($rotulos['product']) ? $rotulos['product'] : [];
        $out = [];
        for ($i = 1; $i <= 7; $i++) {
            $out[$i] = ! empty($produto['custom_field_' . $i]) ? (string) $produto['custom_field_' . $i] : null;
        }

        return $out;
    }

    /**
     * O valor gravado dos 20 campos personalizados do produto (1 → 20). O Editar React devolve
     * todos no envio, porque o update() grava '' em quem não vem no request (UC-PEDIT-10).
     *
     * @return array<int, string>
     */
    protected function valoresCamposPersonalizados(Product $produto): array
    {
        $valores = [];
        for ($i = 1; $i <= 20; $i++) {
            $valores[$i] = (string) ($produto->{'product_custom_field' . $i} ?? '');
        }

        return $valores;
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

        $business_id = request()->session()->get('user.business_id');

        //Check if subscribed or not, then check for products quota
        if (! $this->moduleUtil->isSubscribed($business_id)) {
            return $this->moduleUtil->expiredResponse();
        } elseif (! $this->moduleUtil->isQuotaAvailable('products', $business_id)) {
            return $this->moduleUtil->quotaExpiredResponse('products', $business_id, action([\App\Http\Controllers\ProductController::class, 'index']));
        }

        $categories = Category::forDropdown($business_id, 'product');

        $brands = Brands::forDropdown($business_id);
        $units = Unit::forDropdown($business_id, true);

        $tax_dropdown = TaxRate::forBusinessDropdown($business_id, true, true);
        $taxes = $tax_dropdown['tax_rates'];
        $tax_attributes = $tax_dropdown['attributes'];

        $barcode_types = $this->barcode_types;
        $barcode_default = $this->productUtil->barcode_default();

        $default_profit_percent = request()->session()->get('business.default_profit_percent');

        //Get all business locations
        $business_locations = BusinessLocation::forDropdown($business_id);

        //Duplicate product
        $duplicate_product = null;
        $rack_details = null;

        $sub_categories = [];
        if (! empty(request()->input('d'))) {
            // Tier 0 (ADR 0093): duplicar produto de outro business → 404, não 500.
            // `find()` devolvia null pro id alheio e a linha seguinte acessava ->name (500).
            $duplicate_product = Product::where('business_id', $business_id)->findOrFail(request()->input('d'));
            $duplicate_product->name .= ' (copy)';

            if (! empty($duplicate_product->category_id)) {
                $sub_categories = Category::where('business_id', $business_id)
                        ->where('parent_id', $duplicate_product->category_id)
                        ->pluck('name', 'id')
                        ->toArray();
            }

            //Rack details
            if (! empty($duplicate_product->id)) {
                $rack_details = $this->productUtil->getRackDetails($business_id, $duplicate_product->id);
            }
        }

        $selling_price_group_count = SellingPriceGroup::countSellingPriceGroups($business_id);

        $module_form_parts = $this->moduleUtil->getModuleData('product_form_part');
        $product_types = $this->product_types();

        $common_settings = session()->get('business.common_settings');
        $warranties = Warranty::forDropdown($business_id);

        //product screen view from module
        $pos_module_data = $this->moduleUtil->getModuleData('get_product_screen_top_view');

        // Wave 2 B4 Produto (Agent W2-C 2026-05-15) — branch dual Inertia MWART (ADR 0104)
        // Visita Inertia abre sempre o React (uma visita Inertia não aceita HTML de volta).
        // GET de página inteira (o "Novo produto" da /products navega assim) abre o React só com a
        // chave useV2ProdutoCadastro ligada para a empresa; `?classico=1` mantém o Blade.
        // Chave desligada (o default): o Blade segue sendo o cadastro, como antes. [F] 2026-10-16.
        // ⚠️ Ligar para cliente exige antes o preço no formulário React (Create.casos.md §Pendência
        // de CONTRATO: a tela ainda não manda preço).
        $telaReact = request()->header('X-Inertia')
            || (! request()->boolean('classico')
                && app(FeatureFlagService::class)->isOn(self::FLAG_CADASTRO_REACT, ['business_id' => $business_id]));

        if ($telaReact) {
            return Inertia::render('Produto/Create', [
                'categories' => $categories,
                'brands' => $brands,
                'units' => $units,
                'taxes' => $taxes,
                'taxAttributes' => $tax_attributes,
                'barcodeTypes' => $barcode_types,
                'barcodeDefault' => $barcode_default,
                'defaultProfitPercent' => $default_profit_percent,
                'businessLocations' => $business_locations,
                'duplicateProduct' => $duplicate_product ? [
                    'id' => (int) $duplicate_product->id,
                    'name' => (string) $duplicate_product->name,
                    'brand_id' => $duplicate_product->brand_id,
                    'unit_id' => $duplicate_product->unit_id,
                    'category_id' => $duplicate_product->category_id,
                    'sub_category_id' => $duplicate_product->sub_category_id,
                    'tax' => $duplicate_product->tax,
                    'tax_type' => $duplicate_product->tax_type,
                    'origem_mercadoria' => (int) ($duplicate_product->origem_mercadoria ?? 0),
                    'ncm' => (string) ($duplicate_product->ncm ?? ''),
                    'cest' => (string) ($duplicate_product->cest ?? ''),
                    'peso_liquido' => $duplicate_product->peso_liquido ?? null,
                    'largura_cm' => $duplicate_product->largura_cm ?? null,
                    'altura_cm' => $duplicate_product->altura_cm ?? null,
                    'comprimento_cm' => $duplicate_product->comprimento_cm ?? null,
                    'barcode_type' => $duplicate_product->barcode_type,
                    'sku' => (string) ($duplicate_product->sku ?? ''),
                    'alert_quantity' => $duplicate_product->alert_quantity,
                    'weight' => $duplicate_product->weight,
                    'product_description' => $duplicate_product->product_description,
                    'type' => $duplicate_product->type,
                ] : null,
                'subCategories' => $sub_categories,
                'rackDetails' => $rack_details,
                'sellingPriceGroupCount' => (int) $selling_price_group_count,
                'productTypes' => $product_types,
                'warranties' => $warranties,
                'commonSettings' => $common_settings ?? [],
                'enableExpiry' => session('business.enable_product_expiry') == 1,
                // UC-PCAD-12: `add_expiry` (padrão da coluna) = a validade é lançada na compra; o Blade
                // esconde o campo e manda 12 meses. `add_manufacturing` = o campo aparece, vazio.
                'expiryType' => (string) session('business.expiry_type'),
                'enableLot' => (bool) ($common_settings['enable_lot_number'] ?? false),
                'enableRacks' => (bool) session('business.enable_racks'),
            ]);
        }

        return view('product.create')
            ->with(compact('categories', 'brands', 'units', 'taxes', 'barcode_types', 'default_profit_percent', 'tax_attributes', 'barcode_default', 'business_locations', 'duplicate_product', 'sub_categories', 'rack_details', 'selling_price_group_count', 'module_form_parts', 'product_types', 'common_settings', 'warranties', 'pos_module_data'));
    }

    private function product_types()
    {
        //Product types also includes modifier.
        return ['single' => __('lang_v1.single'),
            'variable' => __('lang_v1.variable'),
            'combo' => __('lang_v1.combo'),
        ];
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    /**
     * Origem da mercadoria — tabela A da NF-e, códigos 0 a 8 (AR-PROD-128).
     *
     * Fora do `try` de store()/update(): o `catch (\Exception)` deles engoliria a
     * ValidationException num redirect genérico. Ausente ou vazia ⇒ não valida e não grava.
     */
    protected function validarOrigemMercadoria(Request $request): void
    {
        $request->validate(
            ['origem_mercadoria' => ['sometimes', 'nullable', 'integer', 'between:0,8']],
            [
                'origem_mercadoria.integer' => 'A origem da mercadoria tem que ser um código de 0 a 8.',
                'origem_mercadoria.between' => 'A origem da mercadoria tem que ser um código de 0 a 8.',
            ],
        );
    }

    /**
     * NCM do produto — 8 dígitos, só números (UC-PCAD-13 e UC-PEDIT-11, CU-PROD-16).
     *
     * Fora do `try` de store() e de update(), pelo mesmo motivo da origem. Ausente ou vazio não valida e não
     * grava: o produto nasce com o DEFAULT '0' da coluna. Produto de serviço não tem NCM, então
     * o cadastro não pode exigi-lo (decisão [M] 2026-10-20).
     */
    protected function validarNcm(Request $request): void
    {
        $request->validate(
            ['ncm' => ['sometimes', 'nullable', 'digits:8']],
            ['ncm.digits' => 'O NCM tem 8 dígitos, só números.'],
        );
    }

    /**
     * CEST do produto — 7 dígitos, só números (Convênio ICMS 142/2018, cláusula sexta).
     * UC-PCAD-15 e UC-PEDIT-12, CU-PROD-16.
     *
     * Fora do `try`, como o NCM. Só a mercadoria com substituição tributária precisa do CEST, então
     * o cadastro não o exige: ausente ou vazio não valida.
     */
    protected function validarCest(Request $request): void
    {
        $request->validate(
            ['cest' => ['sometimes', 'nullable', 'digits:7']],
            ['cest.digits' => 'O CEST tem 7 dígitos, só números.'],
        );
    }

    /**
     * Peso líquido e dimensões: número de 0 a 99999, com ponto decimal (a tela manda assim).
     * Fora do `try`, como a origem. Ausente ou vazio não valida: os quatro são opcionais.
     */
    protected function validarPesoEDimensoes(Request $request): void
    {
        $regra = ['sometimes', 'nullable', 'numeric', 'min:0', 'max:99999'];
        $request->validate(
            array_fill_keys(self::PESO_E_DIMENSOES, $regra),
            [
                'peso_liquido.*' => 'O peso líquido tem que ser um número de 0 a 99999 kg.',
                'largura_cm.*' => 'A largura tem que ser um número de 0 a 99999 cm.',
                'altura_cm.*' => 'A altura tem que ser um número de 0 a 99999 cm.',
                'comprimento_cm.*' => 'O comprimento tem que ser um número de 0 a 99999 cm.',
            ],
        );
    }

    public function store(Request $request)
    {
        if (! auth()->user()->can('product.create')) {
            abort(403, 'Unauthorized action.');
        }

        // UC-PCAD-05 (Tier 0 multi-tenant · ADR 0093) — os FKs de insumo (categoria/marca/
        // unidade/garantia) só podem ser do business atual. Sem isto o `$request->only()`
        // abaixo gravava id ALHEIO (achado [M]/[F] no #4417, casos UC-PCAD-05; mesma família
        // do UC-PTAB-04/#4300). Validação ANTES do `try` — o `catch (\Exception)` engoliria a
        // ValidationException num redirect genérico. `! empty()` trata ''/null como AUSENTE
        // (produto sem categoria/marca é legítimo — não quebra o fluxo antigo).
        $uc_pcad_05_bid = $request->session()->get('user.business_id');
        $fk_scoped = [
            'category_id' => 'categories', 'sub_category_id' => 'categories',
            'brand_id' => 'brands', 'unit_id' => 'units', 'secondary_unit_id' => 'units',
            'warranty_id' => 'warranties',
        ];
        $fk_alheio = [];
        foreach ($fk_scoped as $field => $table) {
            $val = $request->input($field);
            if (! empty($val) && ! DB::table($table)->where('id', $val)->where('business_id', $uc_pcad_05_bid)->exists()) {
                $fk_alheio[$field] = "O {$field} selecionado não pertence a este negócio.";
            }
        }
        if (! empty($fk_alheio)) {
            throw \Illuminate\Validation\ValidationException::withMessages($fk_alheio);
        }

        $this->validarOrigemMercadoria($request);
        $this->validarNcm($request);
        $this->validarCest($request);
        $this->validarPesoEDimensoes($request);

        try {
            $business_id = $request->session()->get('user.business_id');
            $form_fields = ['name', 'brand_id', 'unit_id', 'category_id', 'tax', 'type', 'barcode_type', 'sku', 'alert_quantity', 'tax_type', 'weight', 'product_description', 'sub_unit_ids', 'preparation_time_in_minutes', 'product_custom_field1', 'product_custom_field2', 'product_custom_field3', 'product_custom_field4', 'product_custom_field5', 'product_custom_field6', 'product_custom_field7', 'product_custom_field8', 'product_custom_field9', 'product_custom_field10', 'product_custom_field11', 'product_custom_field12', 'product_custom_field13', 'product_custom_field14', 'product_custom_field15', 'product_custom_field16', 'product_custom_field17', 'product_custom_field18', 'product_custom_field19', 'product_custom_field20',];

            $module_form_fields = $this->moduleUtil->getModuleFormField('product_form_fields');
            if (! empty($module_form_fields)) {
                $form_fields = array_merge($form_fields, $module_form_fields);
            }

            $product_details = $request->only($form_fields);
            $product_details['business_id'] = $business_id;
            $product_details['created_by'] = $request->session()->get('user.id');

            $product_details['enable_stock'] = (! empty($request->input('enable_stock')) && $request->input('enable_stock') == 1) ? 1 : 0;
            $product_details['not_for_selling'] = (! empty($request->input('not_for_selling')) && $request->input('not_for_selling') == 1) ? 1 : 0;

            // Origem da mercadoria: só grava quando o formulário manda. O Blade não manda, e o
            // produto nasce com o DEFAULT 0 da coluna — a mesma origem que a nota usava antes.
            if ($request->filled('origem_mercadoria')) {
                $product_details['origem_mercadoria'] = (int) $request->input('origem_mercadoria');
            }

            // NCM (UC-PCAD-13): só grava quando o formulário manda. O Blade não manda, e vazio
            // deixa o DEFAULT '0' da coluna.
            if ($request->filled('ncm')) {
                $product_details['ncm'] = (string) $request->input('ncm');
            }

            // CEST (UC-PCAD-15): só grava quando vem cheio; vazio deixa a coluna nula.
            if ($request->filled('cest')) {
                $product_details['cest'] = (string) $request->input('cest');
            }

            // Peso líquido e dimensões (UC-PCAD-16): só grava o que vem preenchido; o resto fica nulo.
            foreach (self::PESO_E_DIMENSOES as $campo) {
                if ($request->filled($campo)) {
                    $product_details[$campo] = $request->input($campo);
                }
            }

            if (! empty($request->input('sub_category_id'))) {
                $product_details['sub_category_id'] = $request->input('sub_category_id');
            }

            if (! empty($request->input('secondary_unit_id'))) {
                $product_details['secondary_unit_id'] = $request->input('secondary_unit_id');
            }

            if (empty($product_details['sku'])) {
                $product_details['sku'] = ' ';
            }

            if (! empty($product_details['alert_quantity'])) {
                $product_details['alert_quantity'] = $this->productUtil->num_uf($product_details['alert_quantity']);
            }

            $expiry_enabled = $request->session()->get('business.enable_product_expiry');
            if (! empty($request->input('expiry_period_type')) && ! empty($request->input('expiry_period')) && ! empty($expiry_enabled) && ($product_details['enable_stock'] == 1)) {
                $product_details['expiry_period_type'] = $request->input('expiry_period_type');
                $product_details['expiry_period'] = $this->productUtil->num_uf($request->input('expiry_period'));
            }

            if (! empty($request->input('enable_sr_no')) && $request->input('enable_sr_no') == 1) {
                $product_details['enable_sr_no'] = 1;
            }

            //upload document
            $product_details['image'] = $this->productUtil->uploadFile($request, 'image', config('constants.product_img_path'), 'image');
            $common_settings = session()->get('business.common_settings');

            $product_details['warranty_id'] = ! empty($request->input('warranty_id')) ? $request->input('warranty_id') : null;

            DB::beginTransaction();

            $product = Product::create($product_details);

            event(new ProductsCreatedOrModified($product_details, 'added'));

            if (empty(trim($request->input('sku')))) {
                $sku = $this->productUtil->generateProductSku($product->id);
                $product->sku = $sku;
                $product->save();
            }

            //Add product locations
            $product_locations = $request->input('product_locations');
            if (! empty($product_locations)) {
                $product->product_locations()->sync($product_locations);
            }

            if ($product->type == 'single') {
                $this->productUtil->createSingleProductVariation($product->id, $product->sku, $request->input('single_dpp'), $request->input('single_dpp_inc_tax'), $request->input('profit_percent'), $request->input('single_dsp'), $request->input('single_dsp_inc_tax'));
            } elseif ($product->type == 'variable') {
                if (! empty($request->input('product_variation'))) {
                    $input_variations = $request->input('product_variation');
                    
                    $this->productUtil->createVariableProductVariations($product->id, $input_variations, $request->input('sku_type'));
                }
            } elseif ($product->type == 'combo') {

                //Create combo_variations array by combining variation_id and quantity.
                $combo_variations = [];
                if (! empty($request->input('composition_variation_id'))) {
                    $composition_variation_id = $request->input('composition_variation_id');
                    $quantity = $request->input('quantity');
                    $unit = $request->input('unit');

                    foreach ($composition_variation_id as $key => $value) {
                        $combo_variations[] = [
                            'variation_id' => $value,
                            'quantity' => $this->productUtil->num_uf($quantity[$key]),
                            'unit_id' => $unit[$key],
                        ];
                    }
                }

                $this->productUtil->createSingleProductVariation($product->id, $product->sku, $request->input('item_level_purchase_price_total'), $request->input('purchase_price_inc_tax'), $request->input('profit_percent'), $request->input('selling_price'), $request->input('selling_price_inc_tax'), $combo_variations);
            }

            //Add product racks details.
            $product_racks = $request->get('product_racks', null);
            if (! empty($product_racks)) {
                $this->productUtil->addRackDetails($business_id, $product->id, $product_racks);
            }

            //Set Module fields
            if (! empty($request->input('has_module_data'))) {
                $this->moduleUtil->getModuleData('after_product_saved', ['product' => $product, 'request' => $request]);
            }

            Media::uploadMedia($product->business_id, $product, $request, 'product_brochure', true);

            DB::commit();
            $output = ['success' => 1,
                'msg' => __('product.product_added_success'),
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ];

            return redirect('products')->with('status', $output);
        }

        if ($request->input('submit_type') == 'submit_n_add_opening_stock') {
            return redirect()->action([\App\Http\Controllers\OpeningStockController::class, 'add'],
                ['product_id' => $product->id]
            );
        } elseif ($request->input('submit_type') == 'submit_n_add_selling_prices') {
            return redirect()->action([\App\Http\Controllers\ProductController::class, 'addSellingPrices'],
                [$product->id]
            );
        } elseif ($request->input('submit_type') == 'save_n_add_another') {
            return redirect()->action([\App\Http\Controllers\ProductController::class, 'create']
            )->with('status', $output);
        }

        return redirect('products')->with('status', $output);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Product  $product
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        if (! auth()->user()->can('product.view')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        // Wave 2 B4 Produto (Agent W2-C 2026-05-15) — branch dual Inertia MWART (ADR 0104)
        if (request()->header('X-Inertia')) {
            $product = Product::where('business_id', $business_id)
                ->with(['variations', 'variations.product_variation', 'category', 'sub_category', 'brand', 'unit'])
                ->findOrFail($id);

            // Resolvido uma vez, fora da closure deferida (a permissão não muda entre variações).
            $podeVerCusto = (bool) auth()->user()->can('view_purchase_price');
            $podeVerPreco = (bool) auth()->user()->can('access_default_selling_price');

            return Inertia::render('Produto/Show', [
                'product' => [
                    'id' => (int) $product->id,
                    'name' => (string) $product->name,
                    'sku' => (string) ($product->sku ?? ''),
                    'type' => (string) $product->type,
                    'category' => $product->category?->name,
                    'subCategory' => $product->sub_category?->name,
                    'brand' => $product->brand?->name,
                    'unit' => $product->unit?->actual_name,
                    'enableStock' => (bool) $product->enable_stock,
                    'alertQuantity' => $product->alert_quantity ? (string) $product->alert_quantity : null,
                    'productDescription' => $product->product_description,
                    'image' => $product->image_url,
                ],
                'rackDetails' => Inertia::defer(fn () => $this->productUtil->getRackDetails($business_id, $id, true)),
                // UC-PSHOW-01 — a ficha Blade que o operador realmente abre (`view-modal.blade.php`
                // + os 3 partials de detalhe) envolve preço de compra e de venda em
                // `@can('view_purchase_price')` / `@can('access_default_selling_price')`, e o
                // Delphi faz o campo SUMIR (`AR-PROD-015`, `liedtCusto.Visible := GetPodeVerCustos`).
                // A chave NÃO é emitida: `0`/`null` afirmaria um valor que o usuário não pode ver.
                'variations' => Inertia::defer(function () use ($product, $podeVerCusto, $podeVerPreco) {
                    return $product->variations->map(function ($v) use ($podeVerCusto, $podeVerPreco) {
                        $variacao = [
                            'id' => (int) $v->id,
                            'name' => (string) $v->name,
                            'sku' => (string) ($v->sub_sku ?? ''),
                        ];

                        if ($podeVerCusto) {
                            $variacao['defaultPurchasePrice'] = (float) ($v->default_purchase_price ?? 0);
                        }
                        if ($podeVerPreco) {
                            $variacao['defaultSellPrice'] = (float) ($v->sell_price_inc_tax ?? 0);
                        }

                        return $variacao;
                    })->all();
                }),
                'permissions' => [
                    'update' => auth()->user()->can('product.update'),
                    'delete' => auth()->user()->can('product.delete'),
                    // EAGER: 2 booleanos já resolvidos. O `.tsx` aplica default fail-closed,
                    // então ausência da prop nunca vira permissão.
                    'view_purchase_price' => $podeVerCusto,
                    'access_default_selling_price' => $podeVerPreco,
                ],
            ]);
        }

        $details = $this->productUtil->getRackDetails($business_id, $id, true);

        return view('product.show')->with(compact('details'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        if (! auth()->user()->can('product.update')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');
        $categories = Category::forDropdown($business_id, 'product');
        $brands = Brands::forDropdown($business_id);

        $tax_dropdown = TaxRate::forBusinessDropdown($business_id, true, true);
        $taxes = $tax_dropdown['tax_rates'];
        $tax_attributes = $tax_dropdown['attributes'];

        $barcode_types = $this->barcode_types;

        $product = Product::where('business_id', $business_id)
                            ->with(['product_locations'])
                            ->where('id', $id)
                            ->firstOrFail();

        //Sub-category
        $sub_categories = [];
        $sub_categories = Category::where('business_id', $business_id)
                        ->where('parent_id', $product->category_id)
                        ->pluck('name', 'id')
                        ->toArray();
        $sub_categories = ['' => 'None'] + $sub_categories;

        $default_profit_percent = request()->session()->get('business.default_profit_percent');

        //Get units.
        $units = Unit::forDropdown($business_id, true);
        $sub_units = $this->productUtil->getSubUnits($business_id, $product->unit_id, true);

        //Get all business locations
        $business_locations = BusinessLocation::forDropdown($business_id);
        //Rack details
        $rack_details = $this->productUtil->getRackDetails($business_id, $id);

        $selling_price_group_count = SellingPriceGroup::countSellingPriceGroups($business_id);

        $module_form_parts = $this->moduleUtil->getModuleData('product_form_part');
        $product_types = $this->product_types();
        $common_settings = session()->get('business.common_settings');
        $warranties = Warranty::forDropdown($business_id);

        //product screen view from module
        $pos_module_data = $this->moduleUtil->getModuleData('get_product_screen_top_view');

        $alert_quantity = ! is_null($product->alert_quantity) ? $this->productUtil->num_f($product->alert_quantity, false, null, true) : null;

        // Wave 2 B4 Produto (Agent W2-C 2026-05-15) — branch dual Inertia MWART (ADR 0104)
        if (request()->header('X-Inertia')) {
            return Inertia::render('Produto/Edit', [
                'product' => [
                    'id' => (int) $product->id,
                    'name' => (string) $product->name,
                    'sku' => (string) ($product->sku ?? ''),
                    'type' => (string) $product->type,
                    'brandId' => $product->brand_id,
                    'unitId' => $product->unit_id,
                    'subUnitIds' => $product->sub_unit_ids,
                    'categoryId' => $product->category_id,
                    'subCategoryId' => $product->sub_category_id,
                    'tax' => $product->tax,
                    'taxType' => $product->tax_type ?? 'exclusive',
                    'origemMercadoria' => (int) ($product->origem_mercadoria ?? 0),
                    'ncm' => (string) ($product->ncm ?? ''),
                    'cest' => (string) ($product->cest ?? ''),
                    'pesoLiquido' => $product->peso_liquido ?? null,
                    'larguraCm' => $product->largura_cm ?? null,
                    'alturaCm' => $product->altura_cm ?? null,
                    'comprimentoCm' => $product->comprimento_cm ?? null,
                    // UC-PEDIT-14: natureza fiscal decidida pelo contador (null = sem decisão registrada).
                    'naturezaFiscal' => $this->naturezaFiscalDoProduto((int) $business_id, (int) $id),
                    'barcodeType' => (string) ($product->barcode_type ?? 'C128'),
                    'enableStock' => (bool) $product->enable_stock,
                    'alertQuantity' => $alert_quantity,
                    'weight' => $product->weight,
                    'productDescription' => $product->product_description,
                    'productLocations' => $product->product_locations->pluck('id')->all(),
                    'image' => $product->image_url,
                    'warrantyId' => $product->warranty_id,
                    // UC-PEDIT-10: o update() grava '' em todo campo personalizado que não vem no
                    // request e, com a empresa usando validade, zera a validade que não vem. A tela
                    // recebe o valor gravado dos 20 campos e da validade para devolvê-los no envio.
                    'camposPersonalizados' => $this->valoresCamposPersonalizados($product),
                    'expiryPeriod' => $product->expiry_period !== null ? (float) $product->expiry_period : null,
                    'expiryPeriodType' => $product->expiry_period_type,
                ],
                'categories' => $categories,
                'brands' => $brands,
                'units' => $units,
                'subUnits' => $sub_units,
                'taxes' => $taxes,
                'taxAttributes' => $tax_attributes,
                'barcodeTypes' => $barcode_types,
                'subCategories' => $sub_categories,
                'businessLocations' => $business_locations,
                'rackDetails' => $rack_details,
                'productTypes' => $product_types,
                'warranties' => $warranties,
                'permissions' => [
                    'opening_stock' => auth()->user()->can('product.opening_stock'),
                    'delete' => auth()->user()->can('product.delete'),
                ],
            ]);
        }

        return view('product.edit')
                ->with(compact('categories', 'brands', 'units', 'sub_units', 'taxes', 'tax_attributes', 'barcode_types', 'product', 'sub_categories', 'default_profit_percent', 'business_locations', 'rack_details', 'selling_price_group_count', 'module_form_parts', 'product_types', 'common_settings', 'warranties', 'pos_module_data', 'alert_quantity'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        if (! auth()->user()->can('product.update')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = $request->session()->get('user.business_id');

        // Multi-tenant Tier 0 (ADR 0093 · UC-PEDIT-03): guard de 404 FORA do try. Um 404 lançado
        // DENTRO do try seria engolido pelo catch (\Exception) abaixo → redirect success:0 = 302 (o
        // mesmo buraco cross-tenant do #4300, SellingPrices). Este firstOrFail propaga a
        // ModelNotFoundException pro handler → 404 REAL quando o id é de outro business. O edit()
        // (GET) já fazia isso (:872-875); só o update() ficou pra trás com first() → atribuição de
        // propriedade em null → 500 (ver resources/js/Pages/Produto/Edit.casos.md · UC-PEDIT-03).
        // Query separada DE PROPÓSITO — não atribui a $product pra NÃO narrowar o Product|null do
        // `$product = ...->first()` de baixo (preserva o phpstan-baseline; custo = 1 SELECT indexado
        // por (business_id, id) num fluxo raro de edição).
        Product::where('business_id', $business_id)
                ->where('id', $id)
                ->firstOrFail();

        $this->validarOrigemMercadoria($request);
        $this->validarNcm($request);
        $this->validarCest($request);
        $this->validarPesoEDimensoes($request);

        try {
            $product_details = $request->only(['name', 'brand_id', 'unit_id', 'category_id', 'tax', 'barcode_type', 'sku', 'alert_quantity', 'tax_type', 'weight', 'product_description', 'sub_unit_ids', 'preparation_time_in_minutes', 'product_custom_field1', 'product_custom_field2', 'product_custom_field3', 'product_custom_field4', 'product_custom_field5', 'product_custom_field6', 'product_custom_field7', 'product_custom_field8', 'product_custom_field9', 'product_custom_field10', 'product_custom_field11', 'product_custom_field12', 'product_custom_field13', 'product_custom_field14', 'product_custom_field15', 'product_custom_field16', 'product_custom_field17', 'product_custom_field18', 'product_custom_field19', 'product_custom_field20',]);

            DB::beginTransaction();

            $product = Product::where('business_id', $business_id)
                                ->where('id', $id)
                                ->with(['product_variations'])
                                ->first();

            $module_form_fields = $this->moduleUtil->getModuleFormField('product_form_fields');
            if (! empty($module_form_fields)) {
                foreach ($module_form_fields as $column) {
                    $product->$column = $request->input($column);
                }
            }

            $product->name = $product_details['name'];
            $product->brand_id = $product_details['brand_id'];
            $product->unit_id = $product_details['unit_id'];
            $product->category_id = $product_details['category_id'];
            $product->tax = $product_details['tax'];
            $product->barcode_type = $product_details['barcode_type'];
            $product->sku = $product_details['sku'];
            $product->alert_quantity = ! empty($product_details['alert_quantity']) ? $this->productUtil->num_uf($product_details['alert_quantity']) : $product_details['alert_quantity'];
            $product->tax_type = $product_details['tax_type'];
            $product->weight = $product_details['weight'];
            // Origem da mercadoria: só muda quando o formulário manda (o Blade não manda e não
            // pode zerar a origem que alguém escolheu na tela React).
            if ($request->filled('origem_mercadoria')) {
                $product->origem_mercadoria = (int) $request->input('origem_mercadoria');
            }
            // NCM (UC-PEDIT-11): só muda quando o campo vem. O Blade não manda, e a tela React só
            // manda quando a pessoa mexeu no NCM. Vazio é "sem NCM": volta ao '0' da coluna.
            // setAttribute: a coluna `ncm` só existe no dump do schema, não numa migration, e o
            // Larastan (que lê as migrations) não a enxerga como propriedade do model.
            if ($request->has('ncm')) {
                $product->setAttribute('ncm', $request->filled('ncm') ? (string) $request->input('ncm') : '0');
            }
            // CEST (UC-PEDIT-12): mesma regra do NCM. Vazio é "sem CEST": a coluna volta a nula.
            if ($request->has('cest')) {
                $product->setAttribute('cest', $request->filled('cest') ? (string) $request->input('cest') : null);
            }
            // Peso líquido e dimensões (UC-PEDIT-13): só mexe no campo que vem; vazio volta a nulo.
            // O Blade não manda nenhum dos quatro e não muda nada.
            foreach (self::PESO_E_DIMENSOES as $campo) {
                if ($request->has($campo)) {
                    $product->setAttribute($campo, $request->filled($campo) ? $request->input($campo) : null);
                }
            }
            $product->product_custom_field1 = $product_details['product_custom_field1'] ?? '';
            $product->product_custom_field2 = $product_details['product_custom_field2'] ?? '';
            $product->product_custom_field3 = $product_details['product_custom_field3'] ?? '';
            $product->product_custom_field4 = $product_details['product_custom_field4'] ?? '';
            $product->product_custom_field5 = $product_details['product_custom_field5'] ?? '';
            $product->product_custom_field6 = $product_details['product_custom_field6'] ?? '';
            $product->product_custom_field7 = $product_details['product_custom_field7'] ?? '';
            $product->product_custom_field8 = $product_details['product_custom_field8'] ?? '';
            $product->product_custom_field9 = $product_details['product_custom_field9'] ?? '';
            $product->product_custom_field10 = $product_details['product_custom_field10'] ?? '';
            $product->product_custom_field11 = $product_details['product_custom_field11'] ?? '';
            $product->product_custom_field12 = $product_details['product_custom_field12'] ?? '';
            $product->product_custom_field13 = $product_details['product_custom_field13'] ?? '';
            $product->product_custom_field14 = $product_details['product_custom_field14'] ?? '';
            $product->product_custom_field15 = $product_details['product_custom_field15'] ?? '';
            $product->product_custom_field16 = $product_details['product_custom_field16'] ?? '';
            $product->product_custom_field17 = $product_details['product_custom_field17'] ?? '';
            $product->product_custom_field18 = $product_details['product_custom_field18'] ?? '';
            $product->product_custom_field19 = $product_details['product_custom_field19'] ?? '';
            $product->product_custom_field20 = $product_details['product_custom_field20'] ?? '';

            $product->product_description = $product_details['product_description'];
            $product->sub_unit_ids = ! empty($product_details['sub_unit_ids']) ? $product_details['sub_unit_ids'] : null;
            // UC-PEDIT-06: a chave pode NÃO vir no payload — o `only()` acima só devolve o que o
            // request mandou, e a tela React envia 18 das 33+ chaves que este writer lê. O acesso
            // cru gerava `Undefined array key` → ErrorException → engolida pelo `catch (\Exception)`
            // do fim do método → redirect SEM gravar nada (falha silenciosa, pior que 500).
            // Ver resources/js/Pages/Produto/Edit.casos.md · UC-PEDIT-06.
            if (array_key_exists('preparation_time_in_minutes', $product_details)) {
                $product->preparation_time_in_minutes = $product_details['preparation_time_in_minutes'];
            }
            $product->warranty_id = ! empty($request->input('warranty_id')) ? $request->input('warranty_id') : null;
            $product->secondary_unit_id = ! empty($request->input('secondary_unit_id')) ? $request->input('secondary_unit_id') : null;

            // UC-PEDIT-05 `[V0]` — AR-PROD-051/056 + REGRA MESTRE (memory/proibicoes.md, eixo
            // ESTOQUE): editar a ficha do produto NÃO é o gesto que liga/desliga o controle de
            // estoque. Ausência da chave = "o formulário não declarou nada sobre isto" → PRESERVA.
            // O desligar continua possível porque o form DECLARA o valor: o Blade manda
            // `<input type="hidden" name="enable_stock" value="0">` antes do checkbox
            // (resources/views/product/edit.blade.php), então desmarcar envia 0 explícito.
            if ($request->has('enable_stock')) {
                $product->enable_stock = $request->input('enable_stock') == 1 ? 1 : 0;
            }

            // UC-PEDIT-07 — AR-PROD-003/042: ausência não é "desmarcar". Mesmo contrato do
            // enable_stock acima (hidden 0 no Blade declara o desligamento).
            if ($request->has('not_for_selling')) {
                $product->not_for_selling = $request->input('not_for_selling') == 1 ? 1 : 0;
            }

            if (! empty($request->input('sub_category_id'))) {
                $product->sub_category_id = $request->input('sub_category_id');
            } else {
                $product->sub_category_id = null;
            }

            $expiry_enabled = $request->session()->get('business.enable_product_expiry');
            if (! empty($expiry_enabled)) {
                if (! empty($request->input('expiry_period_type')) && ! empty($request->input('expiry_period')) && ($product->enable_stock == 1)) {
                    $product->expiry_period_type = $request->input('expiry_period_type');
                    $product->expiry_period = $this->productUtil->num_uf($request->input('expiry_period'));
                } else {
                    $product->expiry_period_type = null;
                    $product->expiry_period = null;
                }
            }

            // UC-PEDIT-07 — mesmo contrato do enable_stock/not_for_selling: ausência preserva,
            // hidden 0 do Blade declara o desligamento.
            if ($request->has('enable_sr_no')) {
                $product->enable_sr_no = $request->input('enable_sr_no') == 1 ? 1 : 0;
            }

            //upload document
            $file_name = $this->productUtil->uploadFile($request, 'image', config('constants.product_img_path'), 'image');
            if (! empty($file_name)) {

                //If previous image found then remove
                if (! empty($product->image_path) && file_exists($product->image_path)) {
                    unlink($product->image_path);
                }

                $product->image = $file_name;
                //If product image is updated update woocommerce media id
                if (! empty($product->woocommerce_media_id)) {
                    $product->woocommerce_media_id = null;
                }
            }

            $product->save();
            $product->touch();

            event(new ProductsCreatedOrModified($product, 'updated'));

            //Add product locations
            $product_locations = ! empty($request->input('product_locations')) ?
                                $request->input('product_locations') : [];

            $permitted_locations = auth()->user()->permitted_locations();
            //If not assigned location exists don't remove it
            if ($permitted_locations != 'all') {
                $existing_product_locations = $product->product_locations()->pluck('id');

                foreach ($existing_product_locations as $pl) {
                    if (! in_array($pl, $permitted_locations)) {
                        $product_locations[] = $pl;
                    }
                }
            }

            $product->product_locations()->sync($product_locations);

            if ($product->type == 'single') {
                $single_data = $request->only(['single_variation_id', 'single_dpp', 'single_dpp_inc_tax', 'single_dsp_inc_tax', 'profit_percent', 'single_dsp']);

                // UC-PEDIT-06 — 2ª instância da mesma classe do `preparation_time_in_minutes`:
                // o payload da tela React não manda `single_variation_id` nem os `single_*`, e o
                // acesso cru estourava `Undefined array key` -> ErrorException -> `catch (\Exception)`
                // -> `DB::rollBack()`: a edição INTEIRA sumia (inclusive o nome já gravado acima).
                // ⚠️ Estes campos são PREÇO/custo (REGRA MESTRE, eixo VALOR) — ausência NÃO pode
                // virar `num_uf(null)` = 0. Ausência = "o formulário não declarou" -> PRESERVA.
                // O Blade (product/edit.blade.php) manda os 6, então nada muda pra ele.
                if (array_key_exists('single_variation_id', $single_data)) {
                    $variation = Variation::find($single_data['single_variation_id']);

                    $variation->sub_sku = $product->sku;
                    if (array_key_exists('single_dpp', $single_data)) {
                        $variation->default_purchase_price = $this->productUtil->num_uf($single_data['single_dpp']);
                    }
                    if (array_key_exists('single_dpp_inc_tax', $single_data)) {
                        $variation->dpp_inc_tax = $this->productUtil->num_uf($single_data['single_dpp_inc_tax']);
                    }
                    if (array_key_exists('profit_percent', $single_data)) {
                        $variation->profit_percent = $this->productUtil->num_uf($single_data['profit_percent']);
                    }
                    if (array_key_exists('single_dsp', $single_data)) {
                        $variation->default_sell_price = $this->productUtil->num_uf($single_data['single_dsp']);
                    }
                    if (array_key_exists('single_dsp_inc_tax', $single_data)) {
                        $variation->sell_price_inc_tax = $this->productUtil->num_uf($single_data['single_dsp_inc_tax']);
                    }
                    $variation->save();

                    Media::uploadMedia($product->business_id, $variation, $request, 'variation_images');
                }
            } elseif ($product->type == 'variable') {
                //Update existing variations
                $input_variations_edit = $request->get('product_variation_edit');
                if (! empty($input_variations_edit)) {
                    $this->productUtil->updateVariableProductVariations($product->id, $input_variations_edit,$request->input('sku_type'));
                }

                //Add new variations created.
                $input_variations = $request->input('product_variation');
                if (! empty($input_variations)) {
                    $this->productUtil->createVariableProductVariations($product->id, $input_variations, $request->input('sku_type'));
                }
            } elseif ($product->type == 'combo') {

                //Create combo_variations array by combining variation_id and quantity.
                $combo_variations = [];
                if (! empty($request->input('composition_variation_id'))) {
                    $composition_variation_id = $request->input('composition_variation_id');
                    $quantity = $request->input('quantity');
                    $unit = $request->input('unit');

                    foreach ($composition_variation_id as $key => $value) {
                        $combo_variations[] = [
                            'variation_id' => $value,
                            'quantity' => $quantity[$key],
                            'unit_id' => $unit[$key],
                        ];
                    }
                }

                $variation = Variation::find($request->input('combo_variation_id'));
                $variation->sub_sku = $product->sku;
                $variation->default_purchase_price = $this->productUtil->num_uf($request->input('item_level_purchase_price_total'));
                $variation->dpp_inc_tax = $this->productUtil->num_uf($request->input('purchase_price_inc_tax'));
                $variation->profit_percent = $this->productUtil->num_uf($request->input('profit_percent'));
                $variation->default_sell_price = $this->productUtil->num_uf($request->input('selling_price'));
                $variation->sell_price_inc_tax = $this->productUtil->num_uf($request->input('selling_price_inc_tax'));
                $variation->combo_variations = $combo_variations;
                $variation->save();
            }

            //Add product racks details.
            $product_racks = $request->get('product_racks', null);
            if (! empty($product_racks)) {
                $this->productUtil->addRackDetails($business_id, $product->id, $product_racks);
            }

            $product_racks_update = $request->get('product_racks_update', null);
            if (! empty($product_racks_update)) {
                $this->productUtil->updateRackDetails($business_id, $product->id, $product_racks_update);
            }

            //Set Module fields
            if (! empty($request->input('has_module_data'))) {
                $this->moduleUtil->getModuleData('after_product_saved', ['product' => $product, 'request' => $request]);
            }

            Media::uploadMedia($product->business_id, $product, $request, 'product_brochure', true);

            DB::commit();
            $output = ['success' => 1,
                'msg' => __('product.product_updated_success'),
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => 0,
                'msg' => $e->getMessage(),
            ];
        }

        if ($request->input('submit_type') == 'update_n_edit_opening_stock') {
            return redirect()->action([\App\Http\Controllers\OpeningStockController::class, 'add'],
                ['product_id' => $product->id]
            );
        } elseif ($request->input('submit_type') == 'submit_n_add_selling_prices') {
            return redirect()->action([\App\Http\Controllers\ProductController::class, 'addSellingPrices'],
                [$product->id]
            );
        } elseif ($request->input('submit_type') == 'save_n_add_another') {
            return redirect()->action([\App\Http\Controllers\ProductController::class, 'create']
            )->with('status', $output);
        }

        return redirect('products')->with('status', $output);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Product  $product
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        if (! auth()->user()->can('product.delete')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            try {
                $business_id = request()->session()->get('user.business_id');

                $can_be_deleted = true;
                $error_msg = '';

                //Check if any purchase or transfer exists
                $count = PurchaseLine::join(
                    'transactions as T',
                    'purchase_lines.transaction_id',
                    '=',
                    'T.id'
                )
                                    ->whereIn('T.type', ['purchase'])
                                    ->where('T.business_id', $business_id)
                                    ->where('purchase_lines.product_id', $id)
                                    ->count();
                if ($count > 0) {
                    $can_be_deleted = false;
                    $error_msg = __('lang_v1.purchase_already_exist');
                } else {
                    //Check if any opening stock sold
                    $count = PurchaseLine::join(
                        'transactions as T',
                        'purchase_lines.transaction_id',
                        '=',
                        'T.id'
                     )
                                    ->where('T.type', 'opening_stock')
                                    ->where('T.business_id', $business_id)
                                    ->where('purchase_lines.product_id', $id)
                                    ->where('purchase_lines.quantity_sold', '>', 0)
                                    ->count();
                    if ($count > 0) {
                        $can_be_deleted = false;
                        $error_msg = __('lang_v1.opening_stock_sold');
                    } else {
                        //Check if any stock is adjusted
                        $count = PurchaseLine::join(
                            'transactions as T',
                            'purchase_lines.transaction_id',
                            '=',
                            'T.id'
                        )
                                    ->where('T.business_id', $business_id)
                                    ->where('purchase_lines.product_id', $id)
                                    ->where('purchase_lines.quantity_adjusted', '>', 0)
                                    ->count();
                        if ($count > 0) {
                            $can_be_deleted = false;
                            $error_msg = __('lang_v1.stock_adjusted');
                        }
                    }
                }

                $product = Product::where('id', $id)
                                ->where('business_id', $business_id)
                                ->with('variations')
                                ->first();

                // check for enable stock = 0 product
                if($product->enable_stock == 0){
                    $t_count = TransactionSellLine::join(
                        'transactions as T',
                        'transaction_sell_lines.transaction_id',
                        '=',
                        'T.id'
                    )
                        ->where('T.business_id', $business_id)
                        ->where('transaction_sell_lines.product_id', $id)
                        ->count();

                    if ($t_count > 0) {
                        $can_be_deleted = false;
                        $error_msg = "can't delete product exit in sell";
                    }
                }

                //Check if product is added as an ingredient of any recipe
                if ($this->moduleUtil->isModuleInstalled('Manufacturing')) {
                    $variation_ids = $product->variations->pluck('id');

                    $exists_as_ingredient = \Modules\Manufacturing\Entities\MfgRecipeIngredient::whereIn('variation_id', $variation_ids)
                        ->exists();
                    if ($exists_as_ingredient) {
                        $can_be_deleted = false;
                        $error_msg = __('manufacturing::lang.added_as_ingredient');
                    }
                }
            
                if ($can_be_deleted) {
                    if (! empty($product)) {
                        DB::beginTransaction();
                        //Delete variation location details
                        VariationLocationDetails::where('product_id', $id)
                                                ->delete();
                        $product->delete();
                        event(new ProductsCreatedOrModified($product, 'deleted'));
                        DB::commit();
                    }

                    $output = ['success' => true,
                        'msg' => __('lang_v1.product_delete_success'),
                    ];
                } else {
                    $output = ['success' => false,
                        'msg' => $error_msg,
                    ];
                }
            } catch (\Exception $e) {
                DB::rollBack();
                \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

                $output = ['success' => false,
                    'msg' => __('messages.something_went_wrong'),
                ];
            }

            return $output;
        }
    }

    /**
     * Get subcategories list for a category.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function getSubCategories(Request $request)
    {
        if (! empty($request->input('cat_id'))) {
            $category_id = $request->input('cat_id');
            $business_id = $request->session()->get('user.business_id');
            $sub_categories = Category::where('business_id', $business_id)
                        ->where('parent_id', $category_id)
                        ->select(['name', 'id'])
                        ->get();
            $html = '<option value="">None</option>';
            if (! empty($sub_categories)) {
                foreach ($sub_categories as $sub_category) {
                    $html .= '<option value="'.$sub_category->id.'">'.$sub_category->name.'</option>';
                }
            }
            echo $html;
            exit;
        }
    }

    /**
     * Get product form parts.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function getProductVariationFormPart(Request $request)
    {
        $business_id = $request->session()->get('user.business_id');
        $business = Business::findorfail($business_id);
        $profit_percent = $business->default_profit_percent;

        $action = $request->input('action');
        if ($request->input('action') == 'add') {
            if ($request->input('type') == 'single') {
                return view('product.partials.single_product_form_part')
                        ->with(['profit_percent' => $profit_percent]);
            } elseif ($request->input('type') == 'variable') {
                $variation_templates = VariationTemplate::where('business_id', $business_id)->pluck('name', 'id')->toArray();
                $variation_templates = ['' => __('messages.please_select')] + $variation_templates;

                return view('product.partials.variable_product_form_part')
                        ->with(compact('variation_templates', 'profit_percent', 'action'));
            } elseif ($request->input('type') == 'combo') {
                return view('product.partials.combo_product_form_part')
                ->with(compact('profit_percent', 'action'));
            }
        } elseif ($request->input('action') == 'edit' || $request->input('action') == 'duplicate') {
            $product_id = $request->input('product_id');
            $action = $request->input('action');
            if ($request->input('type') == 'single') {
                $product_deatails = ProductVariation::where('product_id', $product_id)
                    ->with(['variations', 'variations.media'])
                    ->first();

                return view('product.partials.edit_single_product_form_part')
                            ->with(compact('product_deatails', 'action'));
            } elseif ($request->input('type') == 'variable') {
                $product_variations = ProductVariation::where('product_id', $product_id)
                        ->with(['variations', 'variations.media'])
                        ->get();

                return view('product.partials.variable_product_form_part')
                        ->with(compact('product_variations', 'profit_percent', 'action'));
            } elseif ($request->input('type') == 'combo') {
                $product_deatails = ProductVariation::where('product_id', $product_id)
                    ->with(['variations', 'variations.media'])
                    ->first();
                $combo_variations = $this->productUtil->__getComboProductDetails($product_deatails['variations'][0]->combo_variations, $business_id);

                $variation_id = $product_deatails['variations'][0]->id;
                $profit_percent = $product_deatails['variations'][0]->profit_percent;

                return view('product.partials.combo_product_form_part')
                ->with(compact('combo_variations', 'profit_percent', 'action', 'variation_id'));
            }
        }
    }

    /**
     * Get product form parts.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function getVariationValueRow(Request $request)
    {
        $business_id = $request->session()->get('user.business_id');
        $business = Business::findorfail($business_id);
        $profit_percent = $business->default_profit_percent;

        $variation_index = $request->input('variation_row_index');
        $value_index = $request->input('value_index') + 1;

        $row_type = $request->input('row_type', 'add');

        return view('product.partials.variation_value_row')
                ->with(compact('profit_percent', 'variation_index', 'value_index', 'row_type'));
    }

    /**
     * Get product form parts.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function getProductVariationRow(Request $request)
    {
        $business_id = $request->session()->get('user.business_id');
        $business = Business::findorfail($business_id);
        $profit_percent = $business->default_profit_percent;

        $variation_templates = VariationTemplate::where('business_id', $business_id)
                                                ->pluck('name', 'id')->toArray();
        $variation_templates = ['' => __('messages.please_select')] + $variation_templates;

        $row_index = $request->input('row_index', 0);
        $action = $request->input('action');

        return view('product.partials.product_variation_row')
                    ->with(compact('variation_templates', 'row_index', 'action', 'profit_percent'));
    }

    /**
     * Get product form parts.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array<string, mixed> HTML da linha de variação + valores do modelo
     */
    public function getVariationTemplate(Request $request)
    {
        $business_id = $request->session()->get('user.business_id');
        $business = Business::findorfail($business_id);
        $profit_percent = $business->default_profit_percent;

        // Tier 0 (ADR 0093): modelo de variação de outro negócio = 404, nunca nome/valores alheios.
        $template = VariationTemplate::where('business_id', $business_id)
                                                ->where('id', $request->input('template_id'))
                                                ->with(['values'])
                                                ->firstOrFail();
        $row_index = $request->input('row_index');

        $values = [];
        foreach ($template->values as $v) {
            $values[] = [
                'id' => $v->id,
                'text' => $v->name,
            ];
        }

        return [
            'html' => view('product.partials.product_variation_template')
                    ->with(compact('template', 'row_index', 'profit_percent'))->render(),
            'values' => $values,
        ];
    }

    /**
     * Return the view for combo product row
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function getComboProductEntryRow(Request $request)
    {
        if (request()->ajax()) {
            $product_id = $request->input('product_id');
            $variation_id = $request->input('variation_id');
            $business_id = $request->session()->get('user.business_id');

            if (! empty($product_id)) {
                $product = Product::where('id', $product_id)
                        ->with(['unit'])
                        ->first();

                $query = Variation::where('product_id', $product_id)
                        ->with(['product_variation']);

                if ($variation_id !== '0') {
                    $query->where('id', $variation_id);
                }
                $variations = $query->get();

                $sub_units = $this->productUtil->getSubUnits($business_id, $product['unit']->id);

                return view('product.partials.combo_product_entry_row')
                ->with(compact('product', 'variations', 'sub_units'));
            }
        }
    }

    /**
     * Retrieves products list.
     *
     * @param  string  $q
     * @param  bool  $check_qty
     * @return JSON
     */
    public function getProducts()
    {
        if (request()->ajax()) {
            $search_term = request()->input('term', '');
            $location_id = request()->input('location_id', null);
            $check_qty = request()->input('check_qty', false);
            $price_group_id = request()->input('price_group', null);
            $business_id = request()->session()->get('user.business_id');
            $not_for_selling = request()->get('not_for_selling', null);
            $price_group_id = request()->input('price_group', '');
            $product_types = request()->get('product_types', []);

            $search_fields = request()->get('search_fields', ['name', 'sku']);
            if (in_array('sku', $search_fields)) {
                $search_fields[] = 'sub_sku';
            }

            $result = $this->productUtil->filterProduct($business_id, $search_term, $location_id, $not_for_selling, $price_group_id, $product_types, $search_fields, $check_qty);

            return json_encode($result);
        }
    }

    /**
     * Retrieves products list without variation list
     *
     * @param  string  $q
     * @param  bool  $check_qty
     * @return JSON
     */
    public function getProductsWithoutVariations()
    {
        if (request()->ajax()) {
            $term = request()->input('term', '');
            //$location_id = request()->input('location_id', '');

            //$check_qty = request()->input('check_qty', false);

            $business_id = request()->session()->get('user.business_id');

            $products = Product::join('variations', 'products.id', '=', 'variations.product_id')
                ->where('products.business_id', $business_id)
                ->where('products.type', '!=', 'modifier');

            //Include search
            if (! empty($term)) {
                $products->where(function ($query) use ($term) {
                    $query->where('products.name', 'like', '%'.$term.'%');
                    $query->orWhere('sku', 'like', '%'.$term.'%');
                    $query->orWhere('sub_sku', 'like', '%'.$term.'%');
                });
            }

            //Include check for quantity
            // if($check_qty){
            //     $products->where('VLD.qty_available', '>', 0);
            // }

            $products = $products->groupBy('products.id')
                ->select(
                    'products.id as product_id',
                    'products.name',
                    'products.type',
                    'products.enable_stock',
                    'products.sku',
                    'products.id as id',
                    DB::raw('CONCAT(products.name, " - ", products.sku) as text')
                )
                    ->orderBy('products.name')
                    ->get();

            return json_encode($products);
        }
    }

    /**
     * Checks if product sku already exists.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function checkProductSku(Request $request)
    {
        $business_id = $request->session()->get('user.business_id');
        $sku = $request->input('sku');
        $product_id = $request->input('product_id');

        //check in products table
        $query = Product::where('business_id', $business_id)
                        ->where('sku', $sku);
        if (! empty($product_id)) {
            $query->where('id', '!=', $product_id);
        }
        $count = $query->count();

        //check in variation table if $count = 0
        if ($count == 0) {
            $query2 = Variation::where('sub_sku', $sku)
                            ->join('products', 'variations.product_id', '=', 'products.id')
                            ->where('business_id', $business_id);

            if (! empty($product_id)) {
                $query2->where('product_id', '!=', $product_id);
            }

            if (! empty($request->input('variation_id'))) {
                $query2->where('variations.id', '!=', $request->input('variation_id'));
            }
            $count = $query2->count();
        }
        if ($count == 0) {
            echo 'true';
            exit;
        } else {
            echo 'false';
            exit;
        }
    }

    /**
     * Validates multiple variation skus
     */
    public function validateVaritionSkus(Request $request)
    {
        $business_id = $request->session()->get('user.business_id');
        $all_skus = $request->input('skus');

        $skus = [];
        foreach ($all_skus as $key => $value) {
            $skus[] = $value['sku'];
        }

        //check product table is sku present
        $product = Product::where('business_id', $business_id)
                        ->whereIn('sku', $skus)
                        ->first();

        if (! empty($product)) {
            return ['success' => 0, 'sku' => $product->sku];
        }

        foreach ($all_skus as $key => $value) {
            $query = Variation::where('sub_sku', $value['sku'])
                            ->join('products', 'variations.product_id', '=', 'products.id')
                            ->where('business_id', $business_id);

            if (! empty($value['variation_id'])) {
                $query->where('variations.id', '!=', $value['variation_id']);
            }
            $variation = $query->first();

            if (! empty($variation)) {
                return ['success' => 0, 'sku' => $variation->sub_sku];
            }
        }

        return ['success' => 1];
    }

    /**
     * Loads quick add product modal.
     *
     * @return \Illuminate\Http\Response
     */
    public function quickAdd()
    {
        if (! auth()->user()->can('product.create')) {
            abort(403, 'Unauthorized action.');
        }

        $product_name = ! empty(request()->input('product_name')) ? request()->input('product_name') : '';

        $product_for = ! empty(request()->input('product_for')) ? request()->input('product_for') : null;

        $business_id = request()->session()->get('user.business_id');
        $categories = Category::forDropdown($business_id, 'product');
        $brands = Brands::forDropdown($business_id);
        $units = Unit::forDropdown($business_id, true);

        $tax_dropdown = TaxRate::forBusinessDropdown($business_id, true, true);
        $taxes = $tax_dropdown['tax_rates'];
        $tax_attributes = $tax_dropdown['attributes'];

        $barcode_types = $this->barcode_types;

        $default_profit_percent = Business::where('id', $business_id)->value('default_profit_percent');

        $locations = BusinessLocation::forDropdown($business_id);

        $enable_expiry = request()->session()->get('business.enable_product_expiry');
        $enable_lot = request()->session()->get('business.enable_lot_number');

        $module_form_parts = $this->moduleUtil->getModuleData('product_form_part');

        //Get all business locations
        $business_locations = BusinessLocation::forDropdown($business_id);

        $common_settings = session()->get('business.common_settings');
        $warranties = Warranty::forDropdown($business_id);

        return view('product.partials.quick_add_product')
                ->with(compact('categories', 'brands', 'units', 'taxes', 'barcode_types', 'default_profit_percent', 'tax_attributes', 'product_name', 'locations', 'product_for', 'enable_expiry', 'enable_lot', 'module_form_parts', 'business_locations', 'common_settings', 'warranties'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function saveQuickProduct(Request $request)
    {
        if (! auth()->user()->can('product.create')) {
            abort(403, 'Unauthorized action.');
        }

        // UC-PQCK-02 (Tier 0 multi-tenant · ADR 0093) — MESMO guard do `store()` acima
        // (UC-PCAD-05), aplicado ao caminho que NÃO tinha nenhum: aqui o payload nasce de
        // `$request->only($form_fields)` e vai direto pro `Product::create`, então os FKs de
        // insumo chegavam CRUS. Os dropdowns da Blade já são escopados
        // (`Category::forDropdown($business_id)`) — o que faltava era o servidor não confiar
        // no que a UI mandou. Mesma família do UC-PTAB-04 (#4300), UC-PBULK-03 e UC-PBOM-02.
        //
        // Este caminho é chamado de 10 Blades (compra, pedido, PDV, venda) + 1 JS direto:
        // quem cadastra produto no meio de uma venda passa por AQUI.
        //
        // ANTES do `try` de propósito: o `catch (\Exception)` lá embaixo engoliria a
        // ValidationException e devolveria sucesso genérico (mesma razão escrita no `store()`).
        // `! empty()` trata ''/null como AUSENTE — produto sem categoria/marca segue legítimo.
        $uc_pqck_02_bid = $request->session()->get('user.business_id');
        $uc_pqck_02_fks = [
            'category_id' => 'categories', 'sub_category_id' => 'categories',
            'brand_id' => 'brands', 'unit_id' => 'units', 'warranty_id' => 'warranties',
        ];
        $uc_pqck_02_alheios = [];
        foreach ($uc_pqck_02_fks as $field => $table) {
            $val = $request->input($field);
            if (! empty($val) && ! DB::table($table)->where('id', $val)->where('business_id', $uc_pqck_02_bid)->exists()) {
                $uc_pqck_02_alheios[$field] = "O {$field} selecionado não pertence a este negócio.";
            }
        }
        if (! empty($uc_pqck_02_alheios)) {
            throw \Illuminate\Validation\ValidationException::withMessages($uc_pqck_02_alheios);
        }

        try {
            $business_id = $request->session()->get('user.business_id');
            $form_fields = ['name', 'brand_id', 'unit_id', 'category_id', 'tax', 'barcode_type', 'tax_type', 'sku',
                'alert_quantity', 'type', 'sub_unit_ids', 'sub_category_id', 'weight', 'product_description', 'product_custom_field1', 'product_custom_field2', 'product_custom_field3', 'product_custom_field4', 'product_custom_field5', 'product_custom_field6', 'product_custom_field7', 'product_custom_field8', 'product_custom_field9', 'product_custom_field10', 'product_custom_field11', 'product_custom_field12', 'product_custom_field13', 'product_custom_field14', 'product_custom_field15', 'product_custom_field16', 'product_custom_field17', 'product_custom_field18', 'product_custom_field19', 'product_custom_field20'];

            $module_form_fields = $this->moduleUtil->getModuleData('product_form_fields');
            if (! empty($module_form_fields)) {
                foreach ($module_form_fields as $key => $value) {
                    if (! empty($value) && is_array($value)) {
                        $form_fields = array_merge($form_fields, $value);
                    }
                }
            }
            $product_details = $request->only($form_fields);

            $product_details['type'] = empty($product_details['type']) ? 'single' : $product_details['type'];
            $product_details['business_id'] = $business_id;
            $product_details['created_by'] = $request->session()->get('user.id');
            if (! empty($request->input('enable_stock')) && $request->input('enable_stock') == 1) {
                $product_details['enable_stock'] = 1;
                //TODO: Save total qty
                //$product_details['total_qty_available'] = 0;
            }
            if (! empty($request->input('not_for_selling')) && $request->input('not_for_selling') == 1) {
                $product_details['not_for_selling'] = 1;
            }
            if (empty($product_details['sku'])) {
                $product_details['sku'] = ' ';
            }

            if (! empty($product_details['alert_quantity'])) {
                $product_details['alert_quantity'] = $this->productUtil->num_uf($product_details['alert_quantity']);
            }

            $expiry_enabled = $request->session()->get('business.enable_product_expiry');
            if (! empty($request->input('expiry_period_type')) && ! empty($request->input('expiry_period')) && ! empty($expiry_enabled)) {
                $product_details['expiry_period_type'] = $request->input('expiry_period_type');
                $product_details['expiry_period'] = $this->productUtil->num_uf($request->input('expiry_period'));
            }

            if (! empty($request->input('enable_sr_no')) && $request->input('enable_sr_no') == 1) {
                $product_details['enable_sr_no'] = 1;
            }

            $product_details['warranty_id'] = ! empty($request->input('warranty_id')) ? $request->input('warranty_id') : null;

            DB::beginTransaction();

            $product = Product::create($product_details);
            event(new ProductsCreatedOrModified($product_details, 'added'));

            if (empty(trim($request->input('sku')))) {
                $sku = $this->productUtil->generateProductSku($product->id);
                $product->sku = $sku;
                $product->save();
            }

            $this->productUtil->createSingleProductVariation(
                $product->id,
                $product->sku,
                $request->input('single_dpp'),
                $request->input('single_dpp_inc_tax'),
                $request->input('profit_percent'),
                $request->input('single_dsp'),
                $request->input('single_dsp_inc_tax')
            );

            if ($product->enable_stock == 1 && ! empty($request->input('opening_stock'))) {
                $user_id = $request->session()->get('user.id');

                $transaction_date = $request->session()->get('financial_year.start');
                $transaction_date = \Carbon::createFromFormat('Y-m-d', $transaction_date)->toDateTimeString();

                $this->productUtil->addSingleProductOpeningStock($business_id, $product, $request->input('opening_stock'), $transaction_date, $user_id);
            }

            //Add product locations
            $product_locations = $request->input('product_locations');
            if (! empty($product_locations)) {
                $product->product_locations()->sync($product_locations);
            }

            DB::commit();

            $output = ['success' => 1,
                'msg' => __('product.product_added_success'),
                'product' => $product,
                'variation' => $product->variations->first(),
                'locations' => $product_locations,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return $output;
    }

    /**
     * Cadastro rápido da lista de produtos (protótipo `produto-blade.jsx`, `ModalCadastroRapido`):
     * nome, SKU, unidade, categoria, preço de compra e de venda sem imposto — mais "Gerenciar
     * estoque" e os locais, por decisão [F] de 2026-10-12.
     *
     * NÃO grava nada por conta própria: monta o MESMO pedido que o formulário Blade
     * (`quick_add_product.blade.php`) mandaria e entrega ao `saveQuickProduct`, que é quem grava.
     * O que a tela Blade preenchia sozinha, este método preenche igual:
     *   · tipo `single`, código de barras `C128`, imposto de venda `exclusive`, sem imposto
     *     aplicável — os padrões do formulário Blade;
     *   · sem imposto, o preço com imposto é o próprio preço (o `__add_percent(preço, 0)` do Blade);
     *   · a margem é `Util::get_percent(compra, venda)` — a conta do `__get_rate` do Blade, com
     *     custo zero dando margem zero. Vai com 4 casas, que é o que a coluna guarda.
     * Locais: empresa com um local permitido vai para ele (o `$default_location` do Blade); com
     * mais de um, quem cadastra escolhe e ao menos um é obrigatório.
     *
     * Preços chegam como texto com vírgula decimal e SEM separador de milhar ("1234,56") — a tela
     * manda assim (`paraNumUf`). Qualquer outro formato é recusado antes de virar número: é o que
     * impede "12.125" de ser lido como doze mil.
     *
     * Só os campos desta janela seguem adiante: o pedido é trocado por uma lista fechada, para
     * ninguém enfiar por aqui `opening_stock`, `tax` ou outro campo que o `saveQuickProduct` lê.
     */
    public function cadastroRapido(Request $request)
    {
        if (! auth()->user()->can('product.create')) {
            abort(403, 'Unauthorized action.');
        }
        $business_id = (int) $request->session()->get('user.business_id');

        $preco = ['required', 'string', 'regex:/^\d{1,13}(,\d{1,4})?$/'];
        $dados = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:255'],
            'unit_id' => ['required', 'integer'],
            'category_id' => ['nullable', 'integer'],
            'compra' => $preco,
            'venda' => $preco,
            'enable_stock' => ['required', 'boolean'],
            'product_locations' => ['nullable', 'array'],
            'product_locations.*' => ['integer'],
        ], [
            'name.required' => 'Informe o nome do produto.',
            'unit_id.required' => 'Escolha a unidade.',
            'compra.required' => 'Informe o preço de compra.',
            'venda.required' => 'Informe o preço de venda.',
            'compra.regex' => 'Preço de compra inválido.',
            'venda.regex' => 'Preço de venda inválido.',
        ]);

        $sku = trim((string) ($dados['sku'] ?? ''));
        if ($sku !== '') {
            // A mesma checagem de `checkProductSku`, que o formulário Blade fazia antes de enviar.
            $emUso = Product::where('business_id', $business_id)->where('sku', $sku)->exists()
                || Variation::where('sub_sku', $sku)
                    ->join('products', 'variations.product_id', '=', 'products.id')
                    ->where('products.business_id', $business_id)
                    ->exists();
            if ($emUso) {
                throw \Illuminate\Validation\ValidationException::withMessages(['sku' => 'Este SKU já está em uso.']);
            }
        }

        $permitidos = collect(BusinessLocation::forDropdown($business_id))->keys()->map(fn ($id): int => (int) $id)->values();
        if ($permitidos->count() === 1) {
            $locais = $permitidos->all();
        } else {
            $locais = collect($dados['product_locations'] ?? [])->map(fn ($id): int => (int) $id)->unique()
                ->filter(fn (int $id): bool => $permitidos->contains($id))->values()->all();
            if ($permitidos->count() > 1 && $locais === []) {
                throw \Illuminate\Validation\ValidationException::withMessages(['product_locations' => 'Escolha ao menos um local do negócio.']);
            }
        }

        $compra = (float) $this->productUtil->num_uf($dados['compra']);
        $venda = (float) $this->productUtil->num_uf($dados['venda']);
        $margem = (float) $this->productUtil->get_percent($compra, $venda);
        // Vírgula decimal e sem milhar: o `num_uf` do `createSingleProductVariation` lê sem ambiguidade.
        $texto = fn (float $n): string => number_format($n, 4, ',', '');

        $request->query->replace([]);
        $request->replace([
            'name' => $dados['name'],
            'sku' => $sku,
            'unit_id' => (int) $dados['unit_id'],
            'category_id' => $dados['category_id'] ?? null,
            'type' => 'single',
            'barcode_type' => 'C128',
            'tax_type' => 'exclusive',
            'enable_stock' => $dados['enable_stock'] ? 1 : 0,
            'single_dpp' => $texto($compra),
            'single_dpp_inc_tax' => $texto($compra),
            'profit_percent' => $texto($margem),
            'single_dsp' => $texto($venda),
            'single_dsp_inc_tax' => $texto($venda),
            'product_locations' => $locais,
        ]);

        $saida = $this->saveQuickProduct($request);
        if (empty($saida['success'])) {
            return response()->json(['success' => false, 'msg' => (string) ($saida['msg'] ?? __('messages.something_went_wrong'))], 500);
        }
        $produto = $saida['product'];

        return response()->json([
            'success' => true,
            'msg' => 'Produto criado pelo cadastro rápido.',
            'produto' => ['id' => (int) $produto->id, 'nome' => (string) $produto->name, 'sku' => (string) $produto->sku],
        ]);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Product  $product
     * @return \Illuminate\Http\Response
     */
    public function view($id)
    {
        if (! auth()->user()->can('product.view')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $business_id = request()->session()->get('user.business_id');

            $product = Product::where('business_id', $business_id)
                        ->with(['brand', 'unit', 'category', 'sub_category', 'product_tax', 'variations', 'variations.product_variation', 'variations.group_prices', 'variations.media', 'product_locations', 'warranty', 'media'])
                        ->findOrFail($id);

            $price_groups = SellingPriceGroup::where('business_id', $business_id)->active()->pluck('name', 'id');

            $allowed_group_prices = [];
            foreach ($price_groups as $key => $value) {
                if (auth()->user()->can('selling_price_group.'.$key)) {
                    $allowed_group_prices[$key] = $value;
                }
            }

            $group_price_details = [];

            foreach ($product->variations as $variation) {
                foreach ($variation->group_prices as $group_price) {
                    $group_price_details[$variation->id][$group_price->price_group_id] = ['price' => $group_price->price_inc_tax, 'price_type' => $group_price->price_type, 'calculated_price' => $group_price->calculated_price];
                }
            }

            $rack_details = $this->productUtil->getRackDetails($business_id, $id, true);

            $combo_variations = [];
            if ($product->type == 'combo') {
                $combo_variations = $this->productUtil->__getComboProductDetails($product['variations'][0]->combo_variations, $business_id);
            }

            return view('product.view-modal')->with(compact(
                'product',
                'rack_details',
                'allowed_group_prices',
                'group_price_details',
                'combo_variations'
            ));
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());
        }
    }

    /**
     * Mass deletes products.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function massDestroy(Request $request)
    {
        if (! auth()->user()->can('product.delete')) {
            abort(403, 'Unauthorized action.');
        }
        try {
            $purchase_exist = false;

            if (! empty($request->input('selected_rows'))) {
                $business_id = $request->session()->get('user.business_id');

                $selected_rows = explode(',', $request->input('selected_rows'));

                $products = Product::where('business_id', $business_id)
                                    ->whereIn('id', $selected_rows)
                                    ->with(['purchase_lines', 'variations'])
                                    ->get();
                $deletable_products = [];

                $is_mfg_installed = $this->moduleUtil->isModuleInstalled('Manufacturing');

                DB::beginTransaction();

                foreach ($products as $product) {
                    $can_be_deleted = true;
                    //Check if product is added as an ingredient of any recipe
                    if ($is_mfg_installed) {
                        $variation_ids = $product->variations->pluck('id');

                        $exists_as_ingredient = \Modules\Manufacturing\Entities\MfgRecipeIngredient::whereIn('variation_id', $variation_ids)
                            ->exists();
                        $can_be_deleted = ! $exists_as_ingredient;
                    }

                    //Delete if no purchase found
                    if (empty($product->purchase_lines->toArray()) && $can_be_deleted) {
                        //Delete variation location details
                        VariationLocationDetails::where('product_id', $product->id)
                                                    ->delete();
                        $product->delete();
                        event(new ProductsCreatedOrModified($product, 'Deleted'));
                    } else {
                        $purchase_exist = true;
                    }
                }

                DB::commit();
            }

            if (! $purchase_exist) {
                $output = ['success' => 1,
                    'msg' => __('lang_v1.deleted_success'),
                ];
            } else {
                $output = ['success' => 0,
                    'msg' => __('lang_v1.products_could_not_be_deleted'),
                ];
            }
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
     * Shows form to add selling price group prices for a product.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function addSellingPrices($id)
    {
        if (! auth()->user()->can('product.create')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');
        $product = Product::where('business_id', $business_id)
                    ->with(['variations', 'variations.group_prices', 'variations.product_variation'])
                            ->findOrFail($id);

        $price_groups = SellingPriceGroup::where('business_id', $business_id)
                                            ->active()
                                            ->get();
        $variation_prices = [];
        foreach ($product->variations as $variation) {
            foreach ($variation->group_prices as $group_price) {
                $variation_prices[$variation->id][$group_price->price_group_id] = ['price' => $group_price->price_inc_tax, 'price_type' => $group_price->price_type];
            }
        }

        // Wave 2 B4 Produto (Agent W2-C 2026-05-15) — branch dual Inertia MWART (ADR 0104)
        if (request()->header('X-Inertia')) {
            return Inertia::render('Produto/SellingPrices', [
                'product' => [
                    'id' => (int) $product->id,
                    'name' => (string) $product->name,
                    'sku' => (string) ($product->sku ?? ''),
                    'type' => (string) $product->type,
                ],
                'variations' => $product->variations->map(fn ($v) => [
                    'id' => (int) $v->id,
                    'name' => (string) $v->name,
                    'subSku' => (string) ($v->sub_sku ?? ''),
                    'defaultSellPrice' => (float) ($v->sell_price_inc_tax ?? 0),
                ])->all(),
                'priceGroups' => $price_groups->map(fn ($pg) => [
                    'id' => (int) $pg->id,
                    'name' => (string) $pg->name,
                    'description' => $pg->description,
                ])->all(),
                'variationPrices' => $variation_prices,
                'permissions' => [
                    'save' => auth()->user()->can('product.create'),
                ],
            ]);
        }

        return view('product.add-selling-prices')->with(compact('product', 'price_groups', 'variation_prices'));
    }

    /**
     * Saves selling price group prices for a product.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function saveSellingPrices(Request $request)
    {
        if (! auth()->user()->can('product.create')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $business_id = $request->session()->get('user.business_id');
            $product = Product::where('business_id', $business_id)
                            ->with(['variations'])
                            ->findOrFail($request->input('product_id'));
            // Tier 0 — ADR 0093 / CU-PROD-10.1 ("business_id scope em TODA query").
            // O $key do laço abaixo é o price_group_id e vem CRU da chave do array do request:
            // sem isto, produto MEU (que passa no findOrFail escopado) + price_group ALHEIO grava
            // linha cross-tenant em variation_group_prices — que não tem global scope próprio
            // ($guarded = ['id']) e cujo FK só exige que o grupo EXISTA, não que seja seu.
            // Provado por UC-PTAB-04 (SellingPrices.casos.md) — falhou em CI antes deste guard.
            $allowedPriceGroupIds = SellingPriceGroup::where('business_id', $business_id)
                ->pluck('id')
                ->all();

            DB::beginTransaction();
            foreach ($product->variations as $variation) {
                $variation_group_prices = [];
                foreach ($request->input('group_prices') as $key => $value) {
                    if (! in_array((int) $key, $allowedPriceGroupIds, true)) {
                        // Tabela de preço de outro business — não grava. Log pra a tentativa não
                        // ficar invisível (abort() aqui seria engolido pelo catch genérico abaixo).
                        \Log::warning('saveSellingPrices: price_group_id fora do business — ignorado', [
                            'business_id' => $business_id,
                            'price_group_id' => $key,
                            'product_id' => $product->id,
                        ]);

                        continue;
                    }

                    if (isset($value[$variation->id])) {
                        $variation_group_price =
                        VariationGroupPrice::where('variation_id', $variation->id)
                                            ->where('price_group_id', $key)
                                            ->first();
                        if (empty($variation_group_price)) {
                            $variation_group_price = new VariationGroupPrice([
                                'variation_id' => $variation->id,
                                'price_group_id' => $key,
                            ]);
                        }

                        $variation_group_price->price_inc_tax = $this->productUtil->num_uf($value[$variation->id]['price']);
                        $variation_group_price->price_type = $value[$variation->id]['price_type'];
                        $variation_group_prices[] = $variation_group_price;
                    }
                }

                if (! empty($variation_group_prices)) {
                    $variation->group_prices()->saveMany($variation_group_prices);
                }
            }
            //Update product updated_at timestamp
            $product->touch();

            DB::commit();
            $output = ['success' => 1,
                'msg' => __('lang_v1.updated_success'),
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        if ($request->input('submit_type') == 'submit_n_add_opening_stock') {
            return redirect()->action([\App\Http\Controllers\OpeningStockController::class, 'add'],
                ['product_id' => $product->id]
            );
        } elseif ($request->input('submit_type') == 'save_n_add_another') {
            return redirect()->action([\App\Http\Controllers\ProductController::class, 'create']
            )->with('status', $output);
        }

        return redirect('products')->with('status', $output);
    }

    public function viewGroupPrice($id)
    {
        if (! auth()->user()->can('product.view')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        $product = Product::where('business_id', $business_id)
                            ->where('id', $id)
                            ->with(['variations', 'variations.product_variation', 'variations.group_prices'])
                            ->first();

        $price_groups = SellingPriceGroup::where('business_id', $business_id)->active()->pluck('name', 'id');

        $allowed_group_prices = [];
        foreach ($price_groups as $key => $value) {
            if (auth()->user()->can('selling_price_group.'.$key)) {
                $allowed_group_prices[$key] = $value;
            }
        }

        $group_price_details = [];

        foreach ($product->variations as $variation) {
            foreach ($variation->group_prices as $group_price) {
                $group_price_details[$variation->id][$group_price->price_group_id] = $group_price->price_inc_tax;
            }
        }

        return view('product.view-product-group-prices')->with(compact('product', 'allowed_group_prices', 'group_price_details'));
    }

    /**
     * Mass deactivates products.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function massDeactivate(Request $request)
    {
        if (! auth()->user()->can('product.update')) {
            abort(403, 'Unauthorized action.');
        }
        try {
            if (! empty($request->input('selected_products'))) {
                $business_id = $request->session()->get('user.business_id');

                $selected_products = explode(',', $request->input('selected_products'));

                DB::beginTransaction();

                $products = Product::where('business_id', $business_id)
                                    ->whereIn('id', $selected_products)
                                    ->update(['is_inactive' => 1]);

                DB::commit();
            }

            $output = ['success' => 1,
                'msg' => __('lang_v1.products_deactivated_success'),
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return $output;
    }

    /**
     * Activates the specified resource from storage.
     *
     * @param  \App\Product  $product
     * @return \Illuminate\Http\Response
     */
    public function activate($id)
    {
        if (! auth()->user()->can('product.update')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            try {
                $business_id = request()->session()->get('user.business_id');
                $product = Product::where('id', $id)
                                ->where('business_id', $business_id)
                                ->update(['is_inactive' => 0]);

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
     * Deletes a media file from storage and database.
     *
     * @param  int  $media_id
     * @return json
     */
    public function deleteMedia($media_id)
    {
        if (! auth()->user()->can('product.update')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            try {
                $business_id = request()->session()->get('user.business_id');

                Media::deleteMedia($business_id, $media_id);

                $output = ['success' => true,
                    'msg' => __('lang_v1.file_deleted_successfully'),
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

    public function getProductsApi($id = null)
    {
        try {
            $api_token = request()->header('API-TOKEN');
            $filter_string = request()->header('FILTERS');
            $order_by = request()->header('ORDER-BY');

            parse_str($filter_string, $filters);

            $api_settings = $this->moduleUtil->getApiSettings($api_token);

            $limit = ! empty(request()->input('limit')) ? request()->input('limit') : 10;

            $location_id = $api_settings->location_id;

            $query = Product::where('business_id', $api_settings->business_id)
                            ->active()
                            ->with(['brand', 'unit', 'category', 'sub_category',
                                'product_variations', 'product_variations.variations', 'product_variations.variations.media',
                                'product_variations.variations.variation_location_details' => function ($q) use ($location_id) {
                                    $q->where('location_id', $location_id);
                                }, ]);

            if (! empty($filters['categories'])) {
                $query->whereIn('category_id', $filters['categories']);
            }

            if (! empty($filters['brands'])) {
                $query->whereIn('brand_id', $filters['brands']);
            }

            if (! empty($filters['category'])) {
                $query->where('category_id', $filters['category']);
            }

            if (! empty($filters['sub_category'])) {
                $query->where('sub_category_id', $filters['sub_category']);
            }

            if ($order_by == 'name') {
                $query->orderBy('name', 'asc');
            } elseif ($order_by == 'date') {
                $query->orderBy('created_at', 'desc');
            }

            if (empty($id)) {
                $products = $query->paginate($limit);
            } else {
                $products = $query->find($id);
            }
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            return $this->respondWentWrong($e);
        }

        return $this->respond($products);
    }

    public function getVariationsApi()
    {
        try {
            $api_token = request()->header('API-TOKEN');
            $variations_string = request()->header('VARIATIONS');

            if (is_numeric($variations_string)) {
                $variation_ids = intval($variations_string);
            } else {
                parse_str($variations_string, $variation_ids);
            }

            $api_settings = $this->moduleUtil->getApiSettings($api_token);
            $location_id = $api_settings->location_id;
            $business_id = $api_settings->business_id;

            $query = Variation::with([
                'product_variation',
                'product' => function ($q) use ($business_id) {
                    $q->where('business_id', $business_id);
                },
                'product.unit',
                'variation_location_details' => function ($q) use ($location_id) {
                    $q->where('location_id', $location_id);
                },
            ]);

            $variations = is_array($variation_ids) ? $query->whereIn('id', $variation_ids)->get() : $query->where('id', $variation_ids)->first();
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            return $this->respondWentWrong($e);
        }

        return $this->respond($variations);
    }

    /**
     * Shows form to edit multiple products at once.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function bulkEdit(Request $request)
    {
        if (! auth()->user()->can('product.update')) {
            abort(403, 'Unauthorized action.');
        }

        $selected_products_string = $request->input('selected_products');
        if (! empty($selected_products_string)) {
            $selected_products = explode(',', $selected_products_string);
            $business_id = $request->session()->get('user.business_id');

            $products = Product::where('business_id', $business_id)
                                ->whereIn('id', $selected_products)
                                ->with(['variations', 'variations.product_variation', 'variations.group_prices', 'product_locations'])
                                ->get();

            $all_categories = Category::catAndSubCategories($business_id);

            $categories = [];
            $sub_categories = [];
            foreach ($all_categories as $category) {
                $categories[$category['id']] = $category['name'];

                if (! empty($category['sub_categories'])) {
                    foreach ($category['sub_categories'] as $sub_category) {
                        $sub_categories[$category['id']][$sub_category['id']] = $sub_category['name'];
                    }
                }
            }

            $brands = Brands::forDropdown($business_id);

            $tax_dropdown = TaxRate::forBusinessDropdown($business_id, true, true);
            $taxes = $tax_dropdown['tax_rates'];
            $tax_attributes = $tax_dropdown['attributes'];

            $price_groups = SellingPriceGroup::where('business_id', $business_id)->active()->pluck('name', 'id');
            $business_locations = BusinessLocation::forDropdown($business_id);

            // Wave 2 B4 Produto (Agent W2-C 2026-05-15) — branch dual Inertia MWART (ADR 0104)
            if ($request->header('X-Inertia')) {
                return Inertia::render('Produto/BulkEdit', [
                    'products' => $products->map(fn ($p) => [
                        'id' => (int) $p->id,
                        'name' => (string) $p->name,
                        'sku' => (string) ($p->sku ?? ''),
                        'categoryId' => $p->category_id,
                        'subCategoryId' => $p->sub_category_id,
                        'brandId' => $p->brand_id,
                        'tax' => $p->tax,
                        'productLocations' => $p->product_locations->pluck('id')->all(),
                        'variations' => $p->variations->map(fn ($v) => [
                            'id' => (int) $v->id,
                            'name' => (string) $v->name,
                            'subSku' => (string) ($v->sub_sku ?? ''),
                            'defaultPurchasePrice' => (float) ($v->default_purchase_price ?? 0),
                            'defaultSellPrice' => (float) ($v->sell_price_inc_tax ?? 0),
                        ])->all(),
                    ])->all(),
                    'categories' => $categories,
                    'subCategories' => $sub_categories,
                    'brands' => $brands,
                    'taxes' => $taxes,
                    'taxAttributes' => $tax_attributes,
                    'priceGroups' => $price_groups,
                    'businessLocations' => $business_locations,
                ]);
            }

            return view('product.bulk-edit')->with(compact(
                'products',
                'categories',
                'brands',
                'taxes',
                'tax_attributes',
                'sub_categories',
                'price_groups',
                'business_locations'
            ));
        }
    }

    /**
     * Updates multiple products at once.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function bulkUpdate(Request $request)
    {
        if (! auth()->user()->can('product.update')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $products = $request->input('products');
            $business_id = $request->session()->get('user.business_id');

            // Tier 0 — ADR 0093 / CU-PROD-10.1. Mesmo guard do `saveSellingPrices` (#4300): a
            // chave do array `group_prices` é o price_group_id e vem CRUA do request. O produto e
            // a variação são escopados abaixo, mas o GRUPO não — e `VariationGroupPrice` não tem
            // global scope ($guarded = ['id']), com FK que só exige que o grupo EXISTA, não que
            // seja seu. Sem isto, produto MEU + tabela ALHEIA grava linha cross-tenant.
            // Provado por UC-PBULK-03 (BulkEdit.casos.md) — vermelho em CI antes deste guard.
            $allowedPriceGroupIds = SellingPriceGroup::where('business_id', $business_id)
                ->pluck('id')
                ->all();

            DB::beginTransaction();
            foreach ($products as $id => $product_data) {
                $update_data = [
                    'category_id' => $product_data['category_id'],
                    'sub_category_id' => $product_data['sub_category_id'],
                    'brand_id' => $product_data['brand_id'],
                    'tax' => $product_data['tax'],
                ];

                //Update product
                $product = Product::where('business_id', $business_id)
                                ->findOrFail($id);

                $product->update($update_data);

                //Add product locations
                $product_locations = ! empty($product_data['product_locations']) ?
                                    $product_data['product_locations'] : [];
                $product->product_locations()->sync($product_locations);

                $variations_data = [];

                //Format variations data
                foreach ($product_data['variations'] as $key => $value) {
                    $variation = Variation::where('product_id', $product->id)->findOrFail($key);

                    // UC-PBULK-05 — este writer serve DOIS callers com payloads diferentes:
                    //   Blade legada -> manda os 5 campos (o JS do browser ja derivou);
                    //   tela React   -> manda 2 (custo e venda); os 3 derivados vem AUSENTES.
                    // Ausencia nao pode virar zero (REGRA MESTRE: valor mudando sem ninguem ver)
                    // nem ErrorException engolida pelo catch generico. Quando a chave vem, ela
                    // manda; quando falta, deriva pelos MESMOS helpers que o resto do sistema
                    // usa (ProductUtil:911 para o imposto; Util::get_percent = AR-PROD-007).
                    $purchase = $this->productUtil->num_uf($value['default_purchase_price']);
                    $sell = $this->productUtil->num_uf($value['default_sell_price']);
                    $tax_rate = 0;
                    if (! empty($product_data['tax'])) {
                        $tax_obj = TaxRate::find($product_data['tax']);
                        $tax_rate = $tax_obj ? $tax_obj->amount : 0;
                    }

                    $variation->default_purchase_price = $purchase;
                    $variation->default_sell_price = $sell;
                    $variation->dpp_inc_tax = isset($value['dpp_inc_tax'])
                        ? $this->productUtil->num_uf($value['dpp_inc_tax'])
                        : $this->productUtil->calc_percentage($purchase, $tax_rate, $purchase);
                    $variation->sell_price_inc_tax = isset($value['sell_price_inc_tax'])
                        ? $this->productUtil->num_uf($value['sell_price_inc_tax'])
                        : $this->productUtil->calc_percentage($sell, $tax_rate, $sell);
                    $variation->profit_percent = isset($value['profit_percent'])
                        ? $this->productUtil->num_uf($value['profit_percent'])
                        : $this->productUtil->get_percent($purchase, $sell);
                    $variations_data[] = $variation;

                    //Update price groups
                    if (! empty($value['group_prices'])) {
                        foreach ($value['group_prices'] as $k => $v) {
                            if (! in_array((int) $k, $allowedPriceGroupIds, true)) {
                                // Tabela de preço de outro business — não grava. Log pra a
                                // tentativa não ficar invisível (abort() aqui seria engolido
                                // pelo catch genérico abaixo, virando "algo deu errado" mudo).
                                \Log::warning('bulkUpdate: price_group_id fora do business — ignorado', [
                                    'business_id' => $business_id,
                                    'price_group_id' => $k,
                                    'product_id' => $product->id,
                                ]);

                                continue;
                            }

                            VariationGroupPrice::updateOrCreate(
                                ['price_group_id' => $k, 'variation_id' => $variation->id],
                                ['price_inc_tax' => $this->productUtil->num_uf($v)]
                            );
                        }
                    }
                }
                $product->variations()->saveMany($variations_data);
            }
            DB::commit();

            $output = ['success' => 1,
                'msg' => __('lang_v1.updated_success'),
            ];
        } catch (\ErrorException $e) {
            // UC-PBULK-05 — erro de PROGRAMACAO (ex.: chave ausente no payload) NAO se engole.
            // Era exatamente isto que transformava um bug em bug invisivel: a ErrorException
            // caia no catch generico abaixo, revertia o lote inteiro e devolvia "algo deu
            // errado" ao operador, com os valores intactos e ninguem sabendo por que.
            // Reverte (a transacao tem que fechar) e RELANCA, para o erro aparecer.
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return redirect('products')->with('status', $output);
    }

    /**
     * Adds product row to edit in bulk edit product form
     *
     * @param  int  $product_id
     * @return \Illuminate\Http\Response
     */
    public function getProductToEdit($product_id)
    {
        if (! auth()->user()->can('product.update')) {
            abort(403, 'Unauthorized action.');
        }
        $business_id = request()->session()->get('user.business_id');

        $product = Product::where('business_id', $business_id)
                            ->with(['variations', 'variations.product_variation', 'variations.group_prices'])
                            ->findOrFail($product_id);
        $all_categories = Category::catAndSubCategories($business_id);

        $categories = [];
        $sub_categories = [];
        foreach ($all_categories as $category) {
            $categories[$category['id']] = $category['name'];

            if (! empty($category['sub_categories'])) {
                foreach ($category['sub_categories'] as $sub_category) {
                    $sub_categories[$category['id']][$sub_category['id']] = $sub_category['name'];
                }
            }
        }

        $brands = Brands::forDropdown($business_id);

        $tax_dropdown = TaxRate::forBusinessDropdown($business_id, true, true);
        $taxes = $tax_dropdown['tax_rates'];
        $tax_attributes = $tax_dropdown['attributes'];

        $price_groups = SellingPriceGroup::where('business_id', $business_id)->active()->pluck('name', 'id');
        $business_locations = BusinessLocation::forDropdown($business_id);

        return view('product.partials.bulk_edit_product_row')->with(compact(
            'product',
            'categories',
            'brands',
            'taxes',
            'tax_attributes',
            'sub_categories',
            'price_groups',
            'business_locations'
        ));
    }

    /**
     * Gets the sub units for the given unit.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $unit_id
     * @return \Illuminate\Http\Response
     */
    public function getSubUnits(Request $request)
    {
        if (! empty($request->input('unit_id'))) {
            $unit_id = $request->input('unit_id');
            $business_id = $request->session()->get('user.business_id');
            $sub_units = $this->productUtil->getSubUnits($business_id, $unit_id, true);

            //$html = '<option value="">' . __('lang_v1.all') . '</option>';
            $html = '';
            if (! empty($sub_units)) {
                foreach ($sub_units as $id => $sub_unit) {
                    $html .= '<option value="'.$id.'">'.$sub_unit['name'].'</option>';
                }
            }

            return $html;
        }
    }

    public function updateProductLocation(Request $request)
    {
        if (! auth()->user()->can('product.update')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $selected_products = $request->input('products');
            $update_type = $request->input('update_type');
            $location_ids = $request->input('product_location');

            $business_id = $request->session()->get('user.business_id');

            $product_ids = explode(',', $selected_products);

            $products = Product::where('business_id', $business_id)
                                ->whereIn('id', $product_ids)
                                ->with(['product_locations'])
                                ->get();
            DB::beginTransaction();
            foreach ($products as $product) {
                $product_locations = $product->product_locations->pluck('id')->toArray();

                if ($update_type == 'add') {
                    $product_locations = array_unique(array_merge($location_ids, $product_locations));
                    $product->product_locations()->sync($product_locations);
                } elseif ($update_type == 'remove') {
                    foreach ($product_locations as $key => $value) {
                        if (in_array($value, $location_ids)) {
                            unset($product_locations[$key]);
                        }
                    }
                    $product->product_locations()->sync($product_locations);
                }
            }
            DB::commit();
            $output = ['success' => 1,
                'msg' => __('lang_v1.updated_success'),
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return $output;
    }

    public function productStockHistory($id)
    {
        if (! auth()->user()->can('product.view')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        // XHR LEGACY (Blade stock_history_details, aberto pelo legacyHref target="_blank")
        // — EXCLUI o Inertia partial reload, que TAMBÉM seta X-Requested-With: XMLHttpRequest
        // (axios). Sem o `! X-Inertia`, o defer de `movements` cairia aqui e devolveria Blade cru
        // em vez de resolver a prop → Kardex fica fachada em prod. Fix canônico PR #1299 (2026-05-21).
        if (request()->ajax() && ! request()->header('X-Inertia')) {

            //for ajax call $id is variation id else it is product id
            $stock_details = $this->productUtil->getVariationStockDetails($business_id, $id, request()->input('location_id'));
            $stock_history = $this->productUtil->getVariationStockHistory($business_id, $id, request()->input('location_id'));

            //if mismach found update stock in variation location details
            if (isset($stock_history[0]) && (float) $stock_details['current_stock'] != (float) $stock_history[0]['stock']) {
                VariationLocationDetails::where('variation_id',
                                            $id)
                                    ->where('location_id', request()->input('location_id'))
                                    ->update(['qty_available' => $stock_history[0]['stock']]);
                $stock_details['current_stock'] = $stock_history[0]['stock'];
            }

            return view('product.stock_history_details')
                ->with(compact('stock_details', 'stock_history'));
        }

        $product = Product::where('business_id', $business_id)
                            ->with(['variations', 'variations.product_variation', 'unit'])
                            ->findOrFail($id);

        //Get all business locations
        $business_locations = BusinessLocation::forDropdown($business_id);

        // Wave 2 B4 Produto (Agent W2-C 2026-05-15) — branch dual Inertia MWART (ADR 0104)
        if (request()->header('X-Inertia')) {
            // CU-PROD-11.1 — resolve variação/local selecionados. O front usa a 1ª variação e o 1º
            // local quando nada vem na query; o controller espelha esse default pra o defer bater.
            // Mapeia as variações UMA vez e reusa (payload + default) — evita 2º acesso a ->variations.
            $variationsPayload = $product->variations->map(fn ($v) => [
                'id' => (int) $v->id,
                'name' => (string) $v->name,
                'subSku' => (string) ($v->sub_sku ?? ''),
            ])->all();
            $selectedVariationId = (int) (request()->input('variation_id')
                ?: ($variationsPayload[0]['id'] ?? 0));
            // forDropdown() pode devolver array OU Collection (keyed por id); collect()->toArray() normaliza.
            $locationOptions = collect($business_locations)->toArray();
            $selectedLocationId = request()->input('location_id')
                ?: array_key_first($locationOptions);

            return Inertia::render('Produto/StockHistory', [
                'product' => [
                    'id' => (int) $product->id,
                    'name' => (string) $product->name,
                    'sku' => (string) ($product->sku ?? ''),
                    'type' => (string) $product->type,
                    'unit' => $product->unit?->actual_name,
                ],
                'variations' => $variationsPayload,
                'businessLocations' => $business_locations,
                'filters' => [
                    'variationId' => (string) ($selectedVariationId ?: ''),
                    'locationId' => (string) ($selectedLocationId ?? ''),
                ],
                'permissions' => [
                    'view' => true,
                ],
                // CU-PROD-11.1 `[must][reg]` — timeline real do Kardex via Inertia::defer (`[perf]` <600ms;
                // não vem no render inicial). Read-only: ao contrário do branch ajax() legado, NÃO faz
                // UPDATE em VariationLocationDetails — GET é append-only (CU-PROD-11.2 + charter anti-pattern).
                'movements' => Inertia::defer(function () use ($business_id, $selectedVariationId, $selectedLocationId) {
                    if (! $selectedVariationId || ! $selectedLocationId) {
                        return [];
                    }

                    // Isolamento Tier 0: o Product já passou pelo findOrFail escopado por business_id;
                    // getVariationStockHistory recebe o mesmo $business_id (só transações do tenant).
                    $history = $this->productUtil->getVariationStockHistory(
                        $business_id,
                        $selectedVariationId,
                        $selectedLocationId
                    );

                    return collect($history)->values()->map(function ($row, $i) {
                        $qty = (float) ($row['quantity_change'] ?? 0);
                        $type = (string) ($row['type'] ?? '');
                        $kind = $type === 'stock_adjustment'
                            ? 'ajuste'
                            : ($qty < 0 ? 'saida' : 'entrada');

                        $sign = $qty > 0 ? '+' : ($qty < 0 ? '-' : '');

                        return [
                            'id' => (string) ($row['transaction_id'] ?? $i).'-'.$i,
                            'kind' => $kind,
                            'dateLabel' => ! empty($row['date'])
                                ? \Illuminate\Support\Carbon::parse($row['date'])->format('d/m/Y H:i')
                                : '',
                            'quantity' => $qty,
                            'quantityLabel' => $sign.number_format(abs($qty), 2, ',', '.'),
                            'balanceLabel' => number_format((float) ($row['stock'] ?? 0), 2, ',', '.'),
                            'origin' => (string) ($row['type_label'] ?? $type),
                            'refNo' => (string) ($row['ref_no'] ?? ''),
                        ];
                    })->all();
                }),
            ]);
        }

        return view('product.stock_history')
                ->with(compact('product', 'business_locations'));
    }

    /**
     * Toggle WooComerce sync
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function toggleWooCommerceSync(Request $request)
    {
        try {
            $selected_products = $request->input('woocommerce_products_sync');
            $woocommerce_disable_sync = $request->input('woocommerce_disable_sync');

            $business_id = $request->session()->get('user.business_id');
            $product_ids = explode(',', $selected_products);

            DB::beginTransaction();
            if ($this->moduleUtil->isModuleInstalled('Woocommerce')) {
                Product::where('business_id', $business_id)
                        ->whereIn('id', $product_ids)
                        ->update(['woocommerce_disable_sync' => $woocommerce_disable_sync]);
            }
            DB::commit();
            $output = [
                'success' => 1,
                'msg' => __('lang_v1.success'),
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = [
                'success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return $output;
    }

    /**
     * Function to download all products in xlsx format
     */
    public function downloadExcel()
    {
        $is_admin = $this->productUtil->is_admin(auth()->user());
        if (! $is_admin) {
            abort(403, 'Unauthorized action.');
        }

        $filename = 'products-export-'.\Carbon::now()->format('Y-m-d').'.xlsx';

        return Excel::download(new ProductsExport, $filename);
    }
}
