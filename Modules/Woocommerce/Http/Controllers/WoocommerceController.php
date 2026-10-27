<?php

namespace Modules\Woocommerce\Http\Controllers;

use App\Business;
use App\BusinessLocation;
use App\Category;
use App\Product;
use App\SellingPriceGroup;
use App\Services\FeatureFlagService;
use App\System;
use App\TaxRate;
use App\Utils\ModuleUtil;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Modules\Woocommerce\Repositories\WoocommerceSyncLogRepository;
use Modules\Woocommerce\Services\WoocommerceAuthorizationService;
use Modules\Woocommerce\Services\WoocommerceResetService;
use Modules\Woocommerce\Services\WoocommerceSyncService;
use Modules\Woocommerce\Utils\WoocommerceUtil;
use Yajra\DataTables\Facades\DataTables;

class WoocommerceController extends Controller
{
    /**
     * Flag do caminho React do painel (thread woocommerce/playbook/01, F3). Convenção `useV2<Modulo><Tela>`.
     * Sem a chave no GrowthBook o FeatureFlagService cai no fallbackDefaults, que não a lista:
     * default OFF, a Blade segue servindo. Ligar é toggle no GrowthBook (flag:set --biz=1), não deploy.
     *
     * @see memory/requisitos/Woocommerce/RUNBOOK-painel.md
     */
    private const FLAG_V2 = 'useV2WoocommercePainel';

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
     * @return \Illuminate\Contracts\View\View|\Inertia\Response
     */
    public function index()
    {
        // Thread woocommerce/playbook/01: com a flag ligada o painel é a tela React. `?classico=1` mantém a Blade. A tela
        // React sincroniza, reinicia e mapeia impostos pelos mesmos endpoints da Blade (F3-2); as taxas da loja só são lidas
        // quando o mapa abre (mapaDeImpostos). Ao abrir, a tela React só lê o banco e nunca recebe as chaves da API.
        $business_id = (int) request()->session()->get('business.id');
        if ($this->usaTelaNova($business_id)) {
            $this->authService->ensureModulo($business_id);
            $settings = $this->woocommerceUtil->get_api_settings($business_id);

            return Inertia::render('Woocommerce/Painel/Index', [
                'configurado' => ! empty($settings->woocommerce_app_url),
                'resumo' => Inertia::defer(fn () => $this->contagensDoPainel($business_id, $settings)),
                'produtos' => Inertia::defer(fn () => $this->produtosDoPainel($business_id, $settings)),
                // Datas da última sincronização por tipo, as mesmas que a Blade busca em getSyncLog().
                'ultimas' => Inertia::defer(fn () => [
                    'categories' => $this->woocommerceUtil->getLastSync($business_id, 'categories'),
                    'new_products' => $this->woocommerceUtil->getLastSync($business_id, 'new_products'),
                    'all_products' => $this->woocommerceUtil->getLastSync($business_id, 'all_products'),
                    'orders' => $this->woocommerceUtil->getLastSync($business_id, 'orders'),
                ]),
                // Uma permissão por bloco, como os @can da Blade: reiniciar mora no bloco de quem sincroniza.
                'pode' => [
                    'configurar' => $this->authService->podeExecutarAcao($business_id, 'woocommerce.access_woocommerce_api_settings'),
                    'sincronizar' => $this->authService->podeExecutarAcao($business_id, 'woocommerce.sync_products'),
                    'categorias' => $this->authService->podeExecutarAcao($business_id, 'woocommerce.syc_categories'),
                    'pedidos' => $this->authService->podeExecutarAcao($business_id, 'woocommerce.sync_orders'),
                    'impostos' => $this->authService->podeExecutarAcao($business_id, 'woocommerce.map_tax_rates'),
                ],
            ]);
        }

        try {
            $this->authService->ensureModulo($business_id);

            $tax_rates = TaxRate::where('business_id', $business_id)
                            ->get();

            $woocommerce_tax_rates = ['' => __('messages.please_select')];

            $woocommerce_api_settings = $this->woocommerceUtil->get_api_settings($business_id);

            $alerts = [];
            $c = $this->contagensDoPainel($business_id, $woocommerce_api_settings);

            $not_synced_cat_count = $c['categorias_pendentes'];
            if (! empty($not_synced_cat_count)) {
                $alerts['not_synced_cat'] = $not_synced_cat_count == 1 ? __('woocommerce::lang.one_cat_not_synced_alert') : __('woocommerce::lang.cat_not_sync_alert', ['count' => $not_synced_cat_count]);
            }

            $updated_cat_count = $c['categorias_alteradas'];
            if (! empty($updated_cat_count)) {
                $alerts['updated_cat'] = $updated_cat_count == 1 ? __('woocommerce::lang.one_cat_updated_alert') : __('woocommerce::lang.cat_updated_alert', ['count' => $updated_cat_count]);
            }

            $not_synced_product_count = $c['produtos_pendentes'];
            if (! empty($not_synced_product_count)) {
                $alerts['not_synced_product'] = $not_synced_product_count == 1 ? __('woocommerce::lang.one_product_not_sync_alert') : __('woocommerce::lang.product_not_sync_alert', ['count' => $not_synced_product_count]);
            }

            $updated_product_count = $c['produtos_alterados'];
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
     * A tela React responde quando a flag está ligada para o negócio e o pedido não pediu a Blade (`?classico=1`).
     * Uma flag para as três telas do módulo (painel, configuração, log — threads 01 e 02): viram juntas.
     */
    private function usaTelaNova(int $business_id): bool
    {
        return ! request()->boolean('classico')
            && app(FeatureFlagService::class)->isOn(self::FLAG_V2, ['business_id' => $business_id]);
    }

    /**
     * As contagens dos alertas do painel — uma fonte só para a Blade (alertas) e para a tela React (KPIs).
     *
     * @return array{categorias_pendentes:int, categorias_alteradas:int, produtos_pendentes:int, produtos_alterados:int, ultima_sincronizacao:?string}
     */
    private function contagensDoPainel(int $business_id, $settings): array
    {
        $categorias_pendentes = Category::where('business_id', $business_id)
            ->whereNull('woocommerce_cat_id')
            ->where('category_type', 'product')
            ->count();

        $cat_last_sync = $this->woocommerceUtil->getLastSync($business_id, 'categories', false);
        $categorias_alteradas = empty($cat_last_sync) ? 0 : Category::where('business_id', $business_id)
            ->whereNotNull('woocommerce_cat_id')
            ->where('updated_at', '>', $cat_last_sync)
            ->count();

        $products_last_synced = $this->woocommerceUtil->getLastSync($business_id, 'all_products', false);
        $query = Product::where('business_id', $business_id)
            ->whereIn('type', ['single', 'variable'])
            ->whereNull('woocommerce_product_id')
            ->where('woocommerce_disable_sync', 0);
        if (! empty($settings->location_id)) {
            $query->ForLocation($settings->location_id);
        }
        $produtos_pendentes = $query->count();

        $produtos_alterados = empty($products_last_synced) ? 0 : Product::where('business_id', $business_id)
            ->whereNotNull('woocommerce_product_id')
            ->where('woocommerce_disable_sync', 0)
            ->whereIn('type', ['single', 'variable'])
            ->where('updated_at', '>', $products_last_synced)
            ->count();

        return [
            'categorias_pendentes' => $categorias_pendentes,
            'categorias_alteradas' => $categorias_alteradas,
            'produtos_pendentes' => $produtos_pendentes,
            'produtos_alterados' => $produtos_alterados,
            'ultima_sincronizacao' => empty($products_last_synced) ? null : \Carbon::createFromFormat('Y-m-d H:i:s', $products_last_synced)->format('d/m/Y H:i'),
        ];
    }

    /**
     * Produtos que sobem para a loja, com a situação de cada um pelos MESMOS predicados das contagens:
     * fora da fila (sincronização desligada na ficha) · pendente (sem id na loja) · alterado depois da última
     * sincronização · sincronizado. Sem preço nem estoque: exibir valor é decisão [W]. 50 por página.
     *
     * @return array{itens: list<array<string, mixed>>, pagina:int, ultima_pagina:int, total:int}
     */
    private function produtosDoPainel(int $business_id, $settings): array
    {
        $ultima = $this->woocommerceUtil->getLastSync($business_id, 'all_products', false);
        $query = Product::where('business_id', $business_id)->whereIn('type', ['single', 'variable']);
        if (! empty($settings->location_id)) {
            $query->ForLocation($settings->location_id);
        }
        $pagina = $query->orderBy('name')->paginate(50, ['id', 'name', 'sku', 'woocommerce_product_id', 'woocommerce_disable_sync', 'updated_at']);

        // As colunas woocommerce_* vêm da migration do módulo: lidas por getAttribute (o Larastan não as conhece).
        $itens = collect($pagina->items())->map(function (Product $p) use ($ultima): array {
            $idNaLoja = $p->getAttribute('woocommerce_product_id');
            $situacao = match (true) {
                (bool) $p->getAttribute('woocommerce_disable_sync') => 'fora_da_fila',
                empty($idNaLoja) => 'pendente',
                ! empty($ultima) && $p->updated_at?->gt($ultima) => 'alterado',
                default => 'sincronizado',
            };

            return ['id' => $p->id, 'nome' => $p->name, 'sku' => $p->sku, 'id_na_loja' => $idNaLoja, 'situacao' => $situacao];
        })->values()->all();

        return ['itens' => $itens, 'pagina' => $pagina->currentPage(), 'ultima_pagina' => $pagina->lastPage(), 'total' => $pagina->total()];
    }

    /**
     * Displays form to update woocommerce api settings.
     *
     * @return \Illuminate\Contracts\View\View|\Inertia\Response
     */
    public function apiSettings()
    {
        $business_id = (int) request()->session()->get('business.id');
        $this->authService->ensureAcao($business_id, 'woocommerce.access_woocommerce_api_settings');

        // Thread woocommerce/playbook/02: com a flag ligada a configuração é a tela React. Tier 0: os 6 segredos
        // (consumer key/secret e os 4 de webhook) não vão em prop — a Blade os entrega em claro, a tela nova só sabe
        // se cada um está preenchido.
        if ($this->usaTelaNova($business_id)) {
            return Inertia::render('Woocommerce/Configuracao/Index', $this->propsDaConfiguracao($business_id));
        }

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

    /** Segredo guardado no JSON `woocommerce_api_settings`. */
    private const SEGREDOS_DO_JSON = ['woocommerce_consumer_key', 'woocommerce_consumer_secret'];

    /** Segredo guardado em coluna própria de `business` (assinatura HMAC dos webhooks). */
    private const SEGREDOS_DE_WEBHOOK = ['woocommerce_wh_oc_secret', 'woocommerce_wh_ou_secret', 'woocommerce_wh_od_secret', 'woocommerce_wh_or_secret'];

    /** Campos da configuração que a tela React pode ver: tudo que não é segredo. */
    private const CAMPOS_VISIVEIS = [
        'woocommerce_app_url', 'location_id', 'enable_auto_sync', 'default_tax_class', 'product_tax_type',
        'default_selling_price_group', 'sync_description_as', 'product_fields_for_create', 'product_fields_for_update',
        'manage_stock_for_create', 'in_stock_for_create', 'manage_stock_for_update', 'in_stock_for_update',
        'order_statuses', 'shipping_statuses',
    ];

    /**
     * Props da tela React de configuração. Mesmas fontes da Blade (`apiSettings`), menos os segredos: no lugar deles
     * vai só se cada um está preenchido.
     */
    private function propsDaConfiguracao(int $business_id): array
    {
        $business = Business::where('id', $business_id)
            ->first(array_merge(['woocommerce_api_settings'], self::SEGREDOS_DE_WEBHOOK));
        $demonstracao = ! empty($this->woocommerceUtil->notAllowedInDemo());
        $salvo = (! $demonstracao && ! empty($business->woocommerce_api_settings))
            ? (json_decode($business->woocommerce_api_settings, true) ?: [])
            : [];

        $config = array_merge([
            'woocommerce_app_url' => '', 'location_id' => null, 'enable_auto_sync' => false, 'default_tax_class' => '',
            'product_tax_type' => 'inc', 'default_selling_price_group' => '', 'sync_description_as' => 'long',
            'product_fields_for_create' => ['category', 'quantity'], 'product_fields_for_update' => ['name', 'price', 'category', 'quantity'],
            'manage_stock_for_create' => 'none', 'in_stock_for_create' => 'none', 'manage_stock_for_update' => 'none',
            'in_stock_for_update' => 'none', 'order_statuses' => [], 'shipping_statuses' => [],
        ], array_intersect_key($salvo, array_flip(self::CAMPOS_VISIVEIS)));
        $config['enable_auto_sync'] = ! empty($config['enable_auto_sync']);
        foreach (['product_fields_for_create', 'product_fields_for_update'] as $k) {
            $config[$k] = array_values((array) ($config[$k] ?: []));
        }
        foreach (['order_statuses', 'shipping_statuses'] as $k) {
            $config[$k] = (object) array_filter((array) $config[$k]);
        }

        $preenchido = [];
        foreach (self::SEGREDOS_DO_JSON as $k) {
            $preenchido[$k] = ! empty($salvo[$k]);
        }
        foreach (self::SEGREDOS_DE_WEBHOOK as $k) {
            $preenchido[$k] = ! $demonstracao && ! empty($business->{$k});
        }

        $webhooks = [];
        foreach (['oc' => 'orderCreated', 'ou' => 'orderUpdated', 'od' => 'orderDeleted', 'or' => 'orderRestored'] as $sigla => $acao) {
            $webhooks[$sigla] = action([WoocommerceWebhookController::class, $acao], ['business_id' => $business_id]);
        }

        return [
            'config' => $config,
            'segredos' => $preenchido,
            'demonstracao' => $demonstracao,
            'opcoes' => [
                'locais' => collect(BusinessLocation::forDropdown($business_id))
                    ->map(fn ($nome, $id) => ['id' => (string) $id, 'nome' => $nome])->values(),
                'grupos_preco' => SellingPriceGroup::where('business_id', $business_id)->orderBy('name')->get(['id', 'name'])
                    ->map(fn ($g) => ['id' => (string) $g->id, 'nome' => $g->name])->values(),
                'status_loja' => [
                    'pending' => __('woocommerce::lang.pending'), 'processing' => __('woocommerce::lang.processing'),
                    'on-hold' => __('woocommerce::lang.on-hold'), 'completed' => __('woocommerce::lang.completed'),
                    'cancelled' => __('woocommerce::lang.cancelled'), 'refunded' => __('woocommerce::lang.refunded'),
                    'failed' => __('woocommerce::lang.failed'), 'shipped' => __('woocommerce::lang.shipped'),
                ],
                'status_venda' => ['final' => __('lang_v1.final'), 'draft' => __('sale.draft'), 'quotation' => __('lang_v1.quotation')],
                'status_envio' => $this->moduleUtil->shipping_statuses(),
                'webhooks' => $webhooks,
                'cron' => config('app.env') != 'demo' ? $this->moduleUtil->getCronJobCommand() : null,
                'versao_modulo' => System::getProperty('woocommerce_version'),
            ],
        ];
    }

    /**
     * Com o marcador da tela React, segredo vazio mantém o que está salvo (a página nunca recebeu o valor).
     * Lê só do negócio da sessão.
     */
    private function manterSegredosVazios(int $business_id, array $input): array
    {
        $business = Business::where('id', $business_id)
            ->first(array_merge(['woocommerce_api_settings'], self::SEGREDOS_DE_WEBHOOK));
        $salvo = json_decode($business->woocommerce_api_settings ?? '', true) ?: [];

        foreach (self::SEGREDOS_DO_JSON as $k) {
            $input[$k] = ! empty($input[$k]) ? $input[$k] : ($salvo[$k] ?? '');
        }
        foreach (self::SEGREDOS_DE_WEBHOOK as $k) {
            $input[$k] = ! empty($input[$k]) ? $input[$k] : ($business->{$k} ?? null);
        }

        return $input;
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

            // Só a tela React manda o marcador: lá o campo de segredo vem vazio quando não se quer trocar, porque a
            // página nunca recebeu o valor. Sem ele (Blade), vazio continua apagando, como sempre foi.
            if ($request->boolean('manter_segredos')) {
                unset($input['manter_segredos']);
                $input = $this->manterSegredosVazios($business_id, $input);
            }

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
     * GET /woocommerce/tax-rates-map — dados do mapa de impostos para a tela React (painel, F3-2).
     *
     * O mesmo que o index() da Blade monta: as taxas do ERP do negócio da sessão com o vínculo atual, e as taxas da
     * loja lidas da API (getTaxRates). Só é chamado quando a pessoa abre o mapa, para o painel continuar sem chamar
     * a loja ao abrir. `loja_respondeu` só é verdadeiro com pelo menos uma taxa da loja: o getAllResponse() engole erro
     * da API e devolve lista vazia, então "não respondeu" e "loja sem taxa cadastrada" não se distinguem daqui.
     */
    public function mapaDeImpostos()
    {
        $business_id = (int) request()->session()->get('business.id');
        $this->authService->ensureAcao($business_id, 'woocommerce.map_tax_rates');

        $erp = TaxRate::where('business_id', $business_id)->orderBy('name')->get(['id', 'name', 'amount', 'woocommerce_tax_rate_id'])
            ->map(fn ($t) => [
                'id' => (int) $t->id,
                'nome' => (string) $t->name,
                'aliquota' => (float) $t->amount,
                // getAttribute(): a coluna existe (migração do módulo) mas o docblock de App\TaxRate não a declara.
                'loja_id' => $t->getAttribute('woocommerce_tax_rate_id') !== null ? (int) $t->getAttribute('woocommerce_tax_rate_id') : null,
            ])->values();

        $loja = [];
        $respondeu = false;
        if (empty($this->woocommerceUtil->notAllowedInDemo())) {
            try {
                foreach ((array) $this->woocommerceUtil->getTaxRates($business_id) as $r) {
                    $loja[] = ['id' => (int) $r->id, 'nome' => (string) $r->name];
                }
                $respondeu = $loja !== [];
            } catch (\Throwable $e) {
                $respondeu = false;
            }
        }

        return response()->json(['erp' => $erp, 'loja' => $loja, 'loja_respondeu' => $respondeu]);
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
     * @return mixed JSON do DataTables (AJAX) · Blade · Inertia\Response (flag ligada)
     */
    public function viewSyncLog()
    {
        $business_id = (int) request()->session()->get('business.id');
        $this->authService->ensureModulo($business_id);

        // Thread woocommerce/playbook/02: com a flag ligada o log é a tela React. Decide por `X-Inertia`, e antes do
        // `ajax()`: o Inertia manda `X-Requested-With` em toda visita, e o DataTables da Blade não manda `X-Inertia`.
        if ($this->usaTelaNova($business_id) && (request()->header('X-Inertia') || ! request()->ajax())) {
            return Inertia::render('Woocommerce/Log/Index', [
                'logs' => Inertia::defer(fn () => $this->logsDaTela($business_id)),
                'pode' => ['configurar' => $this->authService->podeExecutarAcao($business_id, 'woocommerce.access_woocommerce_api_settings')],
            ]);
        }

        if (request()->ajax()) {
            // D4 — Repository monta query base com filtro business_id + RBAC sync_types.
            $logs = $this->logRepo->paraDatatable($business_id, $this->tiposDeLogPermitidos(), (bool) auth()->user()->can('superadmin'));

            return Datatables::of($logs)
                ->editColumn('created_at', function ($row) {
                    $created_at = $this->woocommerceUtil->format_date($row->created_at, true);
                    $for_humans = \Carbon::createFromFormat('Y-m-d H:i:s', $row->created_at)->diffForHumans();

                    return $created_at.'<br><small>'.$for_humans.'</small>';
                })
                ->editColumn('sync_type', function ($row) {
                    return $this->rotulosDoTipoDeLog()[$row->sync_type];
                })
                ->editColumn('operation_type', function ($row) {
                    $array = $this->rotulosDaOperacaoDeLog();

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
     * Tipos de log que o usuário vê — uma fonte só para o DataTables da Blade e a tela React.
     * Lista vazia (nenhuma das 3 permissões) não filtra: comportamento de antes, preservado (RUNBOOK-log §3).
     *
     * @return list<string>
     */
    private function tiposDeLogPermitidos(): array
    {
        $tipos = [];
        if (auth()->user()->can('woocommerce.syc_categories')) {
            $tipos[] = 'categories';
        }
        if (auth()->user()->can('woocommerce.sync_products')) {
            $tipos[] = 'all_products';
            $tipos[] = 'new_products';
        }
        if (auth()->user()->can('woocommerce.sync_orders')) {
            $tipos[] = 'orders';
        }

        return $tipos;
    }

    /** @return array<string, string> */
    private function rotulosDoTipoDeLog(): array
    {
        return [
            'categories' => __('category.categories'),
            'all_products' => __('sale.products'),
            'new_products' => __('sale.products'),
            'orders' => __('woocommerce::lang.orders'),
        ];
    }

    /** @return array<string, string> */
    private function rotulosDaOperacaoDeLog(): array
    {
        return [
            'created' => __('woocommerce::lang.created'),
            'updated' => __('woocommerce::lang.updated'),
            'reset' => __('woocommerce::lang.reset'),
            'deleted' => __('lang_v1.deleted'),
            'restored' => __('woocommerce::lang.order_restored'),
        ];
    }

    /**
     * Página de execuções para a tela React (25, mais recentes primeiro). Mesma query do DataTables
     * (`paraDatatable`: só o negócio da sessão, tipos pela permissão); os erros são os do partial `log_details`.
     */
    private function logsDaTela(int $business_id): array
    {
        $pagina = $this->logRepo
            ->paraDatatable($business_id, $this->tiposDeLogPermitidos(), (bool) auth()->user()->can('superadmin'))
            ->orderByDesc('woocommerce_sync_logs.created_at')->orderByDesc('woocommerce_sync_logs.id')
            ->paginate(25);
        $tipos = $this->rotulosDoTipoDeLog();
        $operacoes = $this->rotulosDaOperacaoDeLog();

        // As colunas vêm do select do paraDatatable (aliases `DT_RowId`, `log_details`, `full_name`): lidas como
        // atributos crus, não como propriedades do Model.
        $itens = collect($pagina->items())->map(function (\Illuminate\Database\Eloquent\Model $m) use ($tipos, $operacoes): array {
            $row = (object) $m->getAttributes();
            $registros = ! empty($row->data) ? array_values((array) json_decode($row->data, true)) : [];
            $erros = [];
            foreach ((array) json_decode($row->log_details ?? '') as $d) {
                if (! is_object($d) || empty($d->error_type)) {
                    continue;
                }
                $msg = match ($d->error_type) {
                    'order_product_not_found' => __('woocommerce::lang.product_not_found_error', ['product' => $d->product ?? '', 'order_number' => $d->order_number ?? '']),
                    'order_insuficient_product_qty' => __('woocommerce::lang.qty_mismatch_error', ['msg' => $d->msg ?? '', 'order_number' => $d->order_number ?? '']),
                    'order_customer_empty' => __('woocommerce::lang.order_customer_empty', ['order_number' => $d->order_number ?? '']),
                    default => null,
                };
                if ($msg !== null) {
                    $erros[] = trim(strip_tags($msg));
                }
            }

            return [
                'id' => (int) $row->DT_RowId,
                'data' => $this->woocommerceUtil->format_date($row->created_at, true),
                'quando' => \Carbon::createFromFormat('Y-m-d H:i:s', $row->created_at)->diffForHumans(),
                'tipo' => $tipos[$row->sync_type] ?? $row->sync_type,
                'operacao' => $operacoes[$row->operation_type] ?? '',
                'por' => trim(preg_replace('/\s+/', ' ', (string) $row->full_name)),
                'registros' => array_map('strval', $registros),
                'erros' => $erros,
            ];
        });

        return ['itens' => $itens, 'pagina' => $pagina->currentPage(), 'ultima_pagina' => $pagina->lastPage(), 'total' => $pagina->total()];
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
