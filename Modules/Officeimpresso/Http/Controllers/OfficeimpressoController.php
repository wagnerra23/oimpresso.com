<?php

namespace Modules\Officeimpresso\Http\Controllers;

use App\Business;
use App\BusinessLocation;
use App\Category;
use App\Discount;
use App\Product;
use App\SellingPriceGroup;
use App\Utils\ModuleUtil;
use App\Utils\ProductUtil;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

class OfficeimpressoController extends Controller
{
    /**
     * All Utils instance.
     */
    protected $productUtil;

    protected $moduleUtil;

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
    }

    /**
     * Porta de entrada do módulo — `GET /officeimpresso` (sem sufixo).
     *
     * O prefixo `officeimpresso` nunca teve rota na raiz (verificado em 16.499
     * commits: os 21 commits que tocaram o Routes/web.php só registraram rotas
     * com sufixo), então quem digitava a URL "óbvia" levava 404 — os 6 links do
     * menu sempre apontaram pras telas internas.
     *
     * Manda cada nível pra primeira tela que ele CONSEGUE abrir, espelhando o
     * `$baseUrl` de DataController::modifyAdminMenu — sem isso um redirect fixo
     * pra /computadores jogaria o atendente (que só tem `clientes.liberar`)
     * direto num 403, exatamente o que o #5044 evitou no menu.
     *
     * Não usa Closure na rota de propósito: Closure quebra `php artisan
     * route:cache` (mesma pegadinha do name colidente já anotada no web.php).
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function home()
    {
        $user = auth()->user();

        if ($user->can('superadmin') || $user->can('officeimpresso.access')) {
            return redirect()->action([LicencaComputadorController::class, 'computadores']);
        }

        if ($user->can('officeimpresso.clientes.liberar')) {
            return redirect()->action([ClientController::class, 'index']);
        }

        // Sem nenhuma permissão do módulo: 403 aqui é mais honesto que redirecionar
        // pra uma tela que vai negar do mesmo jeito. Espelha os `abort_unless()`
        // dos controllers de licença.
        abort(403, 'Unauthorized action.');
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index($business_id, $location_id)
    {
        $products = Product::where('business_id', $business_id)
                ->whereHas('product_locations', function ($q) use ($location_id) {
                    $q->where('product_locations.location_id', $location_id);
                })
                ->ProductForSales()
                ->with(['variations', 'variations.product_variation', 'category'])
                ->get()
                ->groupBy('category_id');
        $business = Business::with(['currency'])->findOrFail($business_id);
        $business_location = BusinessLocation::where('business_id', $business_id)->findOrFail($location_id);

        $now = \Carbon::now()->toDateTimeString();
        $discounts = Discount::where('business_id', $business_id)
                                ->where('location_id', $location_id)
                                ->where('is_active', 1)
                                ->where('starts_at', '<=', $now)
                                ->where('ends_at', '>=', $now)
                                ->orderBy('priority', 'desc')
                                ->get();
        foreach ($discounts as $key => $value) {
            $discounts[$key]->discount_amount = $this->productUtil->num_f($value->discount_amount, false, $business);
        }

        $categories = Category::forDropdown($business_id, 'product');

        return view('officeimpresso::catalogue.index')->with(compact('products', 'business', 'discounts', 'business_location', 'categories'));
    }

    /**
     * Show the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function show($business_id, $id)
    {
        $product = Product::with(['brand', 'unit', 'category', 'sub_category', 'product_tax', 'variations', 'variations.product_variation', 'variations.group_prices', 'variations.media', 'product_locations', 'warranty'])->where('business_id', $business_id)
                        ->findOrFail($id);

        $price_groups = SellingPriceGroup::where('business_id', $product->business_id)->active()->pluck('name', 'id');

        $allowed_group_prices = [];
        foreach ($price_groups as $key => $value) {
            $allowed_group_prices[$key] = $value;
        }

        $group_price_details = [];
        $discounts = [];
        foreach ($product->variations as $variation) {
            foreach ($variation->group_prices as $group_price) {
                $group_price_details[$variation->id][$group_price->price_group_id] = $group_price->price_inc_tax;
            }

            $discounts[$variation->id] = $this->productUtil->getProductDiscount($product, $product->business_id, request()->input('location_id'), false, null, $variation->id);
        }

        $combo_variations = [];
        if ($product->type == 'combo') {
            $combo_variations = $this->productUtil->__getComboProductDetails($product['variations'][0]->combo_variations, $product->business_id);
        }

        return view('officeimpresso::catalogue.show')->with(compact(
            'product',
            'allowed_group_prices',
            'group_price_details',
            'combo_variations',
            'discounts'
        ));
    }

    public function generateQr()
    {
        $business_id = request()->session()->get('user.business_id');
        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'officeimpresso_module'))) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');
        $business_locations = BusinessLocation::forDropdown($business_id);
        $business = Business::findOrFail($business_id);

        return view('officeimpresso::catalogue.generate_qr')
                    ->with(compact('business_locations', 'business'));
    }
}
