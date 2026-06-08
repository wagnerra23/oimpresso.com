<?php

namespace Modules\Woocommerce\Http\Controllers;

use App\Business;
use App\BusinessLocation;
use App\Category;
use App\Product;
use App\SellingPriceGroup;
use App\System;
use App\TaxRate;
use App\Utils\ModuleUtil;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Modules\Woocommerce\Repositories\WoocommerceSyncLogRepository;
use Modules\Woocommerce\Services\WoocommerceAuthorizationService;
use Modules\Woocommerce\Services\WoocommerceResetService;
use Modules\Woocommerce\Services\WoocommerceSyncService;
use Modules\Woocommerce\Utils\WoocommerceUtil;
use Yajra\DataTables\Facades\DataTables;

class WoocommerceController extends Controller
{
    /**
     * All Utils instance.
     */
    protected $woocommerceUtil;

    protected $moduleUtil;

    /**
     * Services + Repository — D4 Wave 16 governance v3:
     * Controller thin, lógica de negócio em Services injetados.
     */
    protected WoocommerceAuthorizationService $authService;

    protected WoocommerceSyncService $syncService;

    protected WoocommerceResetService $resetService;

    protected WoocommerceSyncLogRepository $logRepo;

    /**
     * Constructor — DI completa (Service Container resolve dependências automaticamente).
     */
    public function __construct(
        WoocommerceUtil $woocommerceUtil,
        ModuleUtil $moduleUtil,
        WoocommerceAuthorizationService $authService,
        WoocommerceSyncService $syncService,
        WoocommerceResetService $resetService,
        WoocommerceSyncLogRepository $logRepo
    ) {
        $this->woocommerceUtil = $woocommerceUtil;
        $this->moduleUtil = $moduleUtil;
        $this->authService = $authService;
        $this->syncService = $syncService;
        $this->resetService = $resetService;
        $this->logRepo = $logRepo;
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        try {
            $business_id = (int) request()->session()->get('business.id');
            $this->authService->ensureModulo($business_id);

            $tax_rates = TaxRate::where('business_id', $business_id)
                            ->get();

            $woocommerce_tax_rates = ['' => __('messages.please_select')];

            $woocommerce_api_settings = $this->woocommerceUtil->get_api_settings($business_id);

            $alerts = [];

            $not_synced_cat_count = Category::where('business_id', $business_id)
                                        ->whereNull('woocommerce_cat_id')
                                        ->where('category_type', 'product')
                                        ->count();

            if (! empty($not_synced_cat_count)) {
                $alerts['not_synced_cat'] = $not_synced_cat_count == 1 ? __('woocommerce::lang.one_cat_not_synced_alert') : __('woocommerce::lang.cat_not_sync_alert', ['count' => $not_synced_cat_count]);
            }

            $cat_last_sync = $this->woocommerceUtil->getLastSync($business_id, 'categories', false);
            if (! empty($cat_last_sync)) {
                $updated_cat_count = Category::where('business_id', $business_id)
                                        ->whereNotNull('woocommerce_cat_id')
                                        ->where('updated_at', '>', $cat_last_sync)
                                        ->count();
            }

            if (! empty($updated_cat_count)) {
                $alerts['updated_cat'] = $updated_cat_count == 1 ? __('woocommerce::lang.one_cat_updated_alert') : __('woocommerce::lang.cat_updated_alert', ['count' => $updated_cat_count]);
            }

            $products_last_synced = $this->woocommerceUtil->getLastSync($business_id, 'all_products', false);
            $query = Product::where('business_id', $business_id)
                                        ->whereIn('type', ['single', 'variable'])
                                        ->whereNull('woocommerce_product_id')
                                        ->where('woocommerce_disable_sync', 0);

            if (! empty($woocommerce_api_settings->location_id)) {
                $query->ForLocation($woocommerce_api_settings->location_id);
            }
            $not_synced_product_count = $query->count();

            if (! empty($not_synced_product_count)) {
                $alerts['not_synced_product'] = $not_synced_product_count == 1 ? __('woocommerce::lang.one_product_not_sync_alert') : __('woocommerce::lang.product_not_sync_alert', ['count' => $not_synced_product_count]);
            }
            if (! empty($products_last_synced)) {
                $updated_product_count = Product::where('business_id', $business_id)
                                        ->whereNotNull('woocommerce_product_id')
                                        ->where('woocommerce_disable_sync', 0)
                                        ->whereIn('type', ['single', 'variable'])
                                        ->where('updated_at', '>', $products_last_synced)
                                        ->count();
            }

            if (! empty($updated_product_count)) {
                $alerts['not_updated_product'] = $updated_product_count == 1 ? __('woocommerce::lang.one_product_updated_alert') : __('woocommerce::lang.product_updated_alert', ['count' => $updated_product_count]);
            }

            $notAllowed = $this->woocommerceUtil->notAllowedInDemo();
            if (empty($notAllowed)) {
                $response = $this->woocommerceUtil->getTaxRates($business_id);
                if (! empty($response)) {
                    foreach ($response as $r) {
                        $woocommerce_tax_rates[$r->id] = $r->name;
                    }
                }
            }
        } catch (\Exception $e) {
            $alerts['connection_failed'] = 'Unable to connect with WooCommerce, Check API settings';
        }

        return view('woocommerce::woocommerce.index')
                ->with(compact('tax_rates', 'woocommerce_tax_rates', 'alerts'));
    }

    /**
     * Displays form to update woocommerce api settings.
     *
     * @return Response
     */
    public function apiSettings()
    {
        $business_id = (int) request()->session()->get('business.id');
        $this->authService->ensureAcao($business_id, 'woocommerce.access_woocommerce_api_settings');

        $default_settings = [
            'woocommerce_app_url' => '',
            'woocommerce_consumer_key' => '',
            'woocommerce_consumer_secret' => '',
            'location_id' => null,
            'default_tax_class' => '',
            'product_tax_type' => 'inc',
            'default_selling_price_group' => '',
            'product_fields_for_create' => ['category', 'quantity'],
            'product_fields_for_update' => ['name', 'price', 'category', 'quantity'],
        ];

        $price_groups = SellingPriceGroup::where('business_id', $business_id)
                        ->pluck('name', 'id')->prepend(__('lang_v1.default'), '');

        $business = Business::find($business_id);

        $notAllowed = $this->woocommerceUtil->notAllowedInDemo();
        if (! empty($notAllowed)) {
            $business = null;
        }

        if (! empty($business->woocommerce_api_settings)) {
            $default_settings = json_decode($business->woocommerce_api_settings, true);
            if (empty($default_settings['product_fields_for_create'])) {
                $default_settings['product_fields_for_create'] = [];
            }

            if (empty($default_settings['product_fields_for_update'])) {
                $default_settings['product_fields_for_update'] = [];
            }
        }

        $locations = BusinessLocation::forDropdown($business_id);
        $module_version = System::getProperty('woocommerce_version');

        $cron_job_command = $this->moduleUtil->getCronJobCommand();

        $shipping_statuses = $this->moduleUtil->shipping_statuses();

        return view('woocommerce::woocommerce.api_settings')
                ->with(compact('default_settings', 'locations', 'price_groups', 'module_version', 'cron_job_command', 'business', 'shipping_statuses'));
    }

    /**
     * Updates woocommerce api settings.
     *
     * @return Response
     */
    public function updateSettings(Request $request)
    {
        $business_id = (int) request()->session()->get('business.id');
        $this->authService->ensureAcao($business_id, 'woocommerce.access_woocommerce_api_settings');

        $notAllowed = $this->woocommerceUtil->notAllowedInDemo();
        if (! empty($notAllowed)) {
            return $notAllowed;
        }

        try {
            $input = $request->except('_token');

            $input['product_fields_for_create'] = ! empty($input['product_fields_for_create']) ? $input['product_fields_for_create'] : [];
            $input['product_fields_for_update'] = ! empty($input['product_fields_for_update']) ? $input['product_fields_for_update'] : [];
            $input['order_statuses'] = ! empty($input['order_statuses']) ? $input['order_statuses'] : [];
            $input['shipping_statuses'] = ! empty($input['shipping_statuses']) ? $input['shipping_statuses'] : [];

            $business = Business::find($business_id);
            $business->woocommerce_api_settings = json_encode($input);
            $business->woocommerce_wh_oc_secret = $input['woocommerce_wh_oc_secret'];
            $business->woocommerce_wh_ou_secret = $input['woocommerce_wh_ou_secret'];
            $business->woocommerce_wh_od_secret = $input['woocommerce_wh_od_secret'];
            $business->woocommerce_wh_or_secret = $input['woocommerce_wh_or_secret'];
            $business->save();

            $output = ['success' => 1,
                'msg' => trans('lang_v1.updated_succesfully'),
            ];
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => 0,
                'msg' => trans('messages.something_went_wrong'),
            ];
        }

        return redirect()->back()->with(['status' => $output]);
    }

    /**
     * Synchronizes pos categories with Woocommerce categories
     *
     * @return Response
     */
    public function syncCategories()
    {
        $business_id = (int) request()->session()->get('business.id');
        $this->authService->ensureAcao($business_id, 'woocommerce.syc_categories');

        $notAllowed = $this->woocommerceUtil->notAllowedInDemo();
        if (! empty($notAllowed)) {
            return $notAllowed;
        }

        $user_id = (int) request()->session()->get('user.id');

        // D4 — delega ao Service (transação + try/catch + WooCommerceError handling lá).
        return $this->syncService->sincronizarCategorias($business_id, $user_id);
    }

    /**
     * Synchronizes pos products with Woocommerce products
     *
     * @return Response
     */
    public function syncProducts()
    {
        $notAllowed = $this->woocommerceUtil->notAllowedInDemo();
        if (! empty($notAllowed)) {
            return $notAllowed;
        }

        $business_id = (int) request()->session()->get('business.id');
        $this->authService->ensureAcao($business_id, 'woocommerce.sync_products');

        $user_id = (int) request()->session()->get('user.id');
        $sync_type = (string) request()->input('type');
        $offset = request()->input('offset');
        $offsetInt = $offset !== null ? (int) $offset : null;

        // D4 — Service gerencia ini_set + transação + retry shape.
        return $this->syncService->sincronizarProdutos($business_id, $user_id, $sync_type, 100, $offsetInt);
    }

    /**
     * Synchronizes Woocommers Orders with POS sales
     *
     * @return Response
     */
    public function syncOrders()
    {
        $notAllowed = $this->woocommerceUtil->notAllowedInDemo();
        if (! empty($notAllowed)) {
            return $notAllowed;
        }

        $business_id = (int) request()->session()->get('business.id');
        $this->authService->ensureAcao($business_id, 'woocommerce.sync_orders');

        $user_id = (int) request()->session()->get('user.id');

        // D4 — delega ao Service.
        return $this->syncService->sincronizarOrders($business_id, $user_id);
    }

    /**
     * Retrives sync log
     *
     * @return Response
     */
    public function getSyncLog()
    {
        $notAllowed = $this->woocommerceUtil->notAllowedInDemo();
        if (! empty($notAllowed)) {
            return $notAllowed;
        }

        $business_id = (int) request()->session()->get('business.id');
        $this->authService->ensureModulo($business_id);

        if (request()->ajax()) {
            $last_sync = [
                'categories' => $this->woocommerceUtil->getLastSync($business_id, 'categories'),
                'new_products' => $this->woocommerceUtil->getLastSync($business_id, 'new_products'),
                'all_products' => $this->woocommerceUtil->getLastSync($business_id, 'all_products'),
                'orders' => $this->woocommerceUtil->getLastSync($business_id, 'orders'),

            ];

            return $last_sync;
        }
    }

    /**
     * Maps POS tax_rates with Woocommerce tax rates.
     *
     * @return Response
     */
    public function mapTaxRates(Request $request)
    {
        $notAllowed = $this->woocommerceUtil->notAllowedInDemo();
        if (! empty($notAllowed)) {
            return $notAllowed;
        }

        $business_id = (int) request()->session()->get('business.id');
        $this->authService->ensureAcao($business_id, 'woocommerce.map_tax_rates');

        try {
            $input = $request->except('_token');
            foreach ($input['taxes'] as $key => $value) {
                $value = ! empty($value) ? $value : null;
                TaxRate::where('business_id', $business_id)
                        ->where('id', $key)
                        ->update(['woocommerce_tax_rate_id' => $value]);
            }

            $output = ['success' => 1,
                'msg' => __('lang_v1.updated_succesfully'),
            ];
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return redirect()->back()->with(['status' => $output]);
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function viewSyncLog()
    {
        $business_id = (int) request()->session()->get('business.id');
        $this->authService->ensureModulo($business_id);

        if (request()->ajax()) {
            // D4 — Repository monta query base com filtro business_id + RBAC sync_types.
            $sync_type = [];
            if (auth()->user()->can('woocommerce.syc_categories')) {
                $sync_type[] = 'categories';
            }
            if (auth()->user()->can('woocommerce.sync_products')) {
                $sync_type[] = 'all_products';
                $sync_type[] = 'new_products';
            }
            if (auth()->user()->can('woocommerce.sync_orders')) {
                $sync_type[] = 'orders';
            }

            $isSuperadmin = (bool) auth()->user()->can('superadmin');
            $logs = $this->logRepo->paraDatatable($business_id, $sync_type, $isSuperadmin);

            return Datatables::of($logs)
                ->editColumn('created_at', function ($row) {
                    $created_at = $this->woocommerceUtil->format_date($row->created_at, true);
                    $for_humans = \Carbon::createFromFormat('Y-m-d H:i:s', $row->created_at)->diffForHumans();

                    return $created_at.'<br><small>'.$for_humans.'</small>';
                })
                ->editColumn('sync_type', function ($row) {
                    $array = [
                        'categories' => __('category.categories'),
                        'all_products' => __('sale.products'),
                        'new_products' => __('sale.products'),
                        'orders' => __('woocommerce::lang.orders'),
                    ];

                    return $array[$row->sync_type];
                })
                ->editColumn('operation_type', function ($row) {
                    $array = [
                        'created' => __('woocommerce::lang.created'),
                        'updated' => __('woocommerce::lang.updated'),
                        'reset' => __('woocommerce::lang.reset'),
                        'deleted' => __('lang_v1.deleted'),
                        'restored' => __('woocommerce::lang.order_restored'),
                    ];

                    return array_key_exists($row->operation_type, $array) ? $array[$row->operation_type] : '';
                })
                ->editColumn('data', function ($row) {
                    if (! empty($row->data)) {
                        $data = json_decode($row->data, true);

                        return implode(', ', $data).'<br><small>'.count($data).' '.__('woocommerce::lang.records').'</small>';
                    } else {
                        return '';
                    }
                })
                ->editColumn('log_details', function ($row) {
                    $details = '';
                    if (! empty($row->log_details)) {
                        $details = $row->log_details;
                    }

                    return $details;
                })
                ->filterColumn('full_name', function ($query, $keyword) {
                    $query->whereRaw("CONCAT(COALESCE(surname, ''), ' ', COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) like ?", ["%{$keyword}%"]);
                })
                ->rawColumns(['created_at', 'data'])
                ->make(true);
        }

        return view('woocommerce::woocommerce.sync_log');
    }

    /**
     * Retrives details of a sync log.
     *
     * @param  int  $id
     * @return Response
     */
    public function getLogDetails($id)
    {
        $business_id = (int) request()->session()->get('business.id');
        $this->authService->ensureModulo($business_id);

        if (request()->ajax()) {
            // D4 — Repository encapsula scope business_id.
            $log = $this->logRepo->detalhe($business_id, (int) $id);
            $log_details = $log ? json_decode($log->details) : null;

            return view('woocommerce::woocommerce.partials.log_details')
                    ->with(compact('log_details'));
        }
    }

    /**
     * Resets synced categories
     *
     * @return json
     */
    public function resetCategories()
    {
        $business_id = (int) request()->session()->get('business.id');
        $this->authService->ensureModulo($business_id);

        if (request()->ajax()) {
            $user_id = (int) request()->session()->get('user.id');

            // D4 — delega ao ResetService (5 tabelas tocadas lá com filtro business_id).
            return $this->resetService->resetarCategorias($business_id, $user_id);
        }
    }

    /**
     * Resets synced products
     *
     * @return json
     */
    public function resetProducts()
    {
        $business_id = (int) request()->session()->get('business.id');
        $this->authService->ensureModulo($business_id);

        if (request()->ajax()) {
            $user_id = (int) request()->session()->get('user.id');

            // D4 — delega ao ResetService (5 tabelas: products, variations,
            // variation_templates, medias, woocommerce_sync_logs).
            return $this->resetService->resetarProdutos($business_id, $user_id);
        }
    }
}
