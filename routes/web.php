<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AccountReportsController;
use App\Http\Controllers\AccountTypeController;
// use App\Http\Controllers\Auth;
use App\Http\Controllers\BackUpController;
use App\Http\Controllers\BarcodeController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\BusinessController;
use App\Http\Controllers\BusinessLocationController;
use App\Http\Controllers\CashRegisterController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CombinedPurchaseReturnController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\CustomerGroupController;
use App\Http\Controllers\DashboardConfiguratorController;
use App\Http\Controllers\DiscountController;
use App\Http\Controllers\DocumentAndNoteController;
use App\Http\Controllers\ExpenseCategoryController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\GroupTaxController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ImportOpeningStockController;
use App\Http\Controllers\ImportProductsController;
use App\Http\Controllers\ImportSalesController;
use App\Http\Controllers\Install;
use App\Http\Controllers\InvoiceLayoutController;
use App\Http\Controllers\InvoiceSchemeController;
use App\Http\Controllers\LabelsController;
use App\Http\Controllers\LedgerDiscountController;
use App\Http\Controllers\LocationSettingsController;
use App\Http\Controllers\ManageUserController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\NotificationTemplateController;
use App\Http\Controllers\OpeningStockController;
use App\Http\Controllers\PrinterController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\PurchaseRequisitionController;
use App\Http\Controllers\PurchaseReturnController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\Restaurant;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SalesCommissionAgentController;
use App\Http\Controllers\SalesOrderController;
use App\Http\Controllers\SellController;
use App\Http\Controllers\SellingPriceGroupController;
use App\Http\Controllers\SellPosController;
use App\Http\Controllers\SellReturnController;
use App\Http\Controllers\StockAdjustmentController;
use App\Http\Controllers\StockTransferController;
use App\Http\Controllers\TaxonomyController;
use App\Http\Controllers\TaxRateController;
use App\Http\Controllers\TransactionPaymentController;
use App\Http\Controllers\TypesOfServiceController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VariationTemplateController;
use App\Http\Controllers\WarrantyController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

include_once 'install_r.php';

// US-GOV-013 Fase A — probe público do gate visual (ADR 0108). Só fora de produção
// (testing/local): rota trivial 200 sem deps, usada pelo Pest Browser pra provar o
// pipeline end-to-end. NÃO existe em produção (app()->isProduction()).
if (! app()->isProduction()) {
    Route::get('/_smoke-probe', fn () => view('_smoke-probe'))->name('smoke.probe');

    // US-GOV-013 Fase B — auth bridge cross-process pro Pest 4 Browser (visual-regression).
    // O browser Playwright roda em SUBPROCESSO: a sessão do test process NÃO cruza pra ele.
    // Esta rota loga um user por id DENTRO do subprocesso do server → seta o cookie de
    // sessão no browser → as visits seguintes ficam autenticadas. Persistência exige
    // SESSION_DRIVER não-array (file/database) no .env do gate. NUNCA em produção
    // (isProduction guard) — destrava o smoke das telas autenticadas que vinha bloqueado.
    Route::get('/_visreg-login/{id}', function (int $id, \Illuminate\Http\Request $request) {
        \Illuminate\Support\Facades\Auth::loginUsingId($id);

        // `to` = tela alvo (1 visit só: loga + redireciona). Só path relativo (anti
        // open-redirect, ainda que env-guarded). Default '/'.
        $to = (string) $request->query('to', '/');
        $to = str_starts_with($to, '/') ? $to : '/';

        return redirect($to);
    })->middleware('web')->name('visreg.login');

    // GATE L2 — ESTADOS ISOLADOS do VRT (snapshot de empty/loading/dark/error por tela).
    // Espelha o /_visreg-login (loga + redireciona), mas FORCA um estado isolado da tela
    // antes do snapshot, fechando o maior gap de cobertura visual: hoje so o estado seedado
    // (default) e snapshotado, entao regressao em empty/loading/dark/error passa batido.
    //
    // FONTE UNICA do mapa tela→{rota,estados}: tests/Browser/visreg-states.json (mesmo JSON
    // que o IsolatedStatesBaselineTest e o scripts/visreg-states-lint.mjs leem — sem drift).
    //
    // Cada estado vem de um LEVER deterministico, SEM tocar controller/Page (mesmo principio
    // nao-invasivo do L3 Tier0RenderIsolation — o estado vem do tenant logado + flag de
    // sessao lida pelo VisregStateMiddleware):
    //   - default    → admin do biz=1 (VisregTenantSeeder)
    //   - empty      → admin do biz=98 (VisregEmptyTenantSeeder — tenant vazio por construcao)
    //   - dark       → admin do biz=1 + flag 'dark' → middleware seta ui_theme=dark em memoria
    //   - loading    → admin do biz=1 + flag 'loading' → middleware congela o Inertia::defer
    //   - error      → admin do biz=1 + redirect()->with('error') → toast.error (app.tsx 8s)
    //   - long-data  → admin do biz=1 (reservado; nenhuma tela declara no v1)
    // NUNCA em producao (isProduction guard acima). `to` so path relativo (anti open-redirect).
    Route::get('/_visreg-state/{tela}/{estado}', function (string $tela, string $estado, \Illuminate\Http\Request $request) {
        $manifestPath = base_path('tests/Browser/visreg-states.json');
        if (! is_file($manifestPath)) {
            abort(404); // manifesto ausente (ex: tests/ nao deployado) — rota inerte.
        }

        $manifest = json_decode((string) file_get_contents($manifestPath), true);
        $screen = $manifest['screens'][$tela] ?? null;
        if (! is_array($screen) || ! in_array($estado, $screen['states'] ?? [], true)) {
            abort(404); // tela desconhecida ou estado nao declarado pra ela.
        }

        // Resolve o admin do tenant certo (mesmo idioma skip-graceful dos browser tests):
        // empty usa o biz=98 (tenant vazio); os demais usam o biz=1 (self seedado).
        $businessId = $estado === 'empty'
            ? \Database\Seeders\VisregEmptyTenantSeeder::BIZ_EMPTY
            : 1;
        $admin = \App\User::where('business_id', $businessId)->orderBy('id')->first();
        if ($admin === null) {
            abort(409, "Tenant biz={$businessId} nao seedado pro estado '{$estado}'.");
        }

        \Illuminate\Support\Facades\Auth::loginUsingId($admin->id);

        // Flag de estado pro VisregStateMiddleware (dark/loading). Sempre (re)grava — pra
        // estados sem lever de middleware (default/empty) ela so sobrescreve flag stale de
        // um teste anterior na mesma sessao do browser (auto-cura entre testes).
        $request->session()->put(\App\Http\Middleware\VisregStateMiddleware::SESSION_KEY, $estado);

        $redirect = redirect((string) $screen['route']);

        // error: flasheia a mensagem → HandleInertiaRequests expoe flash.error → toast.
        if ($estado === 'error') {
            $redirect->with('error', 'Falha ao carregar os dados (estado de teste do VRT — L2).');
        }

        return $redirect;
    })->middleware('web')->name('visreg.state');
}

Route::middleware(['setData'])->group(function () {
    Route::get('/', function () {
        return view('welcome');
    });

    Auth::routes();

    // PR3: login social via Socialite (Google + Microsoft).
    // throttle:10,1 — anti-bruteforce no callback OAuth (state token guessing).
    Route::get('/auth/{provider}/redirect', [\App\Http\Controllers\Auth\SocialAuthController::class, 'redirect'])
        ->where('provider', 'google|microsoft')
        ->middleware('throttle:10,1')
        ->name('auth.social.redirect');
    Route::get('/auth/{provider}/callback', [\App\Http\Controllers\Auth\SocialAuthController::class, 'callback'])
        ->where('provider', 'google|microsoft')
        ->middleware('throttle:10,1')
        ->name('auth.social.callback');

    // Blade legado (UltimatePOS) — mantém /login/old e /register/old durante a transição.
    // throttle:5,1 — anti-bruteforce de exibição (consistente com /login canônico).
    Route::get('/login/old', [\App\Http\Controllers\Auth\LoginController::class, 'showLoginFormLegacy'])
        ->middleware('throttle:5,1')
        ->name('login.legacy');

    // /business/register — registro público novo business; alvo de enum/bruteforce + spam.
    // throttle:5,1 no POST (cria business); throttle:30,1 nas checagens AJAX (UX permite mais).
    Route::get('/business/register', [BusinessController::class, 'getRegister'])->name('business.getRegister');
    Route::post('/business/register', [BusinessController::class, 'postRegister'])
        ->middleware('throttle:5,1')
        ->name('business.postRegister');
    Route::post('/business/register/check-username', [BusinessController::class, 'postCheckUsername'])
        ->middleware('throttle:30,1')
        ->name('business.postCheckUsername');
    Route::post('/business/register/check-email', [BusinessController::class, 'postCheckEmail'])
        ->middleware('throttle:30,1')
        ->name('business.postCheckEmail');

    // Rotas públicas com token (vulneráveis a enum de tokens). throttle:30,1 — limite UX gentil + freio enum.
    Route::get('/invoice/{token}', [SellPosController::class, 'showInvoice'])
        ->middleware('throttle:30,1')
        ->name('show_invoice');
    Route::get('/quote/{token}', [SellPosController::class, 'showInvoice'])
        ->middleware('throttle:30,1')
        ->name('show_quote');

    Route::get('/pay/{token}', [SellPosController::class, 'invoicePayment'])
        ->middleware('throttle:30,1')
        ->name('invoice_payment');
    // /confirm-payment — manipula pagamento; throttle:10,1 mais conservador.
    Route::post('/confirm-payment/{id}', [SellPosController::class, 'confirmPayment'])
        ->middleware('throttle:10,1')
        ->name('confirm_payment');

    // ADR 0191 — banner LGPD consent (pré-requisito Microsoft Clarity).
    // Público (sem auth) — banner aparece pra anônimo na landing tambem.
    // throttle:30,1 — UX gentil + freio contra abuso.
    Route::post('/api/consent', [\App\Http\Controllers\ConsentController::class, 'store'])
        ->middleware('throttle:30,1')
        ->name('consent.store');
});

//Routes for authenticated users only
Route::middleware(['setData', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin'])->group(function () {
    Route::get('pos/payment/{id}', [SellPosController::class, 'edit'])->name('edit-pos-payment');
    Route::get('service-staff-availability', [SellPosController::class, 'showServiceStaffAvailibility']);
    Route::get('pause-resume-service-staff-timer/{user_id}', [SellPosController::class, 'pauseResumeServiceStaffTimer']);
    Route::get('mark-as-available/{user_id}', [SellPosController::class, 'markAsAvailable']);

    Route::resource('purchase-requisition', PurchaseRequisitionController::class)->except(['edit', 'update']);
    Route::post('/get-requisition-products', [PurchaseRequisitionController::class, 'getRequisitionProducts'])->name('get-requisition-products');
    Route::get('get-purchase-requisitions/{location_id}', [PurchaseRequisitionController::class, 'getPurchaseRequisitions']);
    Route::get('get-purchase-requisition-lines/{purchase_requisition_id}', [PurchaseRequisitionController::class, 'getPurchaseRequisitionLines']);

    Route::get('/sign-in-as-user/{id}', [ManageUserController::class, 'signInAsUser'])->name('sign-in-as-user');

    // Design System — showcase dos componentes shared (dev/design review)
    Route::get('/showcase/components', fn () => inertia('_Showcase/Components'))
        ->middleware('superadmin')
        ->name('showcase.components');

    // Tarefas — inbox unificada cross-módulo (UI-0011, 2026-05-05).
    // Stub que renderiza Page placeholder até Fase 4 do plano de migração ADR 0039
    // (TaskProvider interface + TaskRegistry agregando providers de cada módulo).
    Route::get('/tarefas', fn () => inertia('Tarefas/Index'))->name('tarefas.index');

    // Wagner 2026-05-22: /home redireciona pra hub IA/Jana — sidebar v3 ADR 0180.
    // Wagner 2026-05-25: alvo passou de /ia (chat) pra /ia/dashboard (Dashboard
    // Jana = primeira aba canon, com farol das metas + KPIs do business). Chat
    // continua entry-point IA conversacional via aba 2 e FAB. Dashboard legacy
    // UltimatePOS (HomeController@index com cards Total Vendas/Líquido/A
    // Receber) ainda acessível via /dashboard-legacy se precisar. Name `home`
    // preservado pra compat com `route('home')` chamado em ~30 lugares
    // (post-login redirect, breadcrumbs, links externos UltimatePOS core).
    Route::get('/home', fn () => redirect('/ia/dashboard', 302))->name('home');
    Route::get('/dashboard-legacy', [HomeController::class, 'index'])->name('home.legacy');
    Route::get('/home/get-totals', [HomeController::class, 'getTotals']);
    Route::get('/home/product-stock-alert', [HomeController::class, 'getProductStockAlert']);
    Route::get('/home/purchase-payment-dues', [HomeController::class, 'getPurchasePaymentDues']);
    Route::get('/home/sales-payment-dues', [HomeController::class, 'getSalesPaymentDues']);
    Route::post('/attach-medias-to-model', [HomeController::class, 'attachMediasToGivenModel'])->name('attach.medias.to.model');
    Route::get('/calendar', [HomeController::class, 'getCalendar'])->name('calendar');

    Route::post('/test-email', [BusinessController::class, 'testEmailConfiguration']);
    Route::post('/test-sms', [BusinessController::class, 'testSmsConfiguration']);
    Route::get('/business/settings', [BusinessController::class, 'getBusinessSettings'])->name('business.getBusinessSettings');
    Route::post('/business/update', [BusinessController::class, 'postBusinessSettings'])->name('business.postBusinessSettings');
    Route::get('/user/profile', [UserController::class, 'getProfile'])->name('user.getProfile');
    Route::post('/user/update', [UserController::class, 'updateProfile'])->name('user.updateProfile');
    Route::post('/user/update-password', [UserController::class, 'updatePassword'])->name('user.updatePassword');

    // Meu perfil — tela Inertia nova (redesign ComVis). Legado /user/profile (Blade) intacto.
    Route::get('/perfil', [UserController::class, 'perfil'])->name('perfil');
    Route::post('/perfil/update', [UserController::class, 'perfilUpdate'])->name('perfil.update');
    Route::post('/perfil/password', [UserController::class, 'perfilPassword'])->name('perfil.password');

    Route::resource('brands', BrandController::class);

    Route::resource('tax-rates', TaxRateController::class);

    Route::resource('units', UnitController::class);

    Route::resource('ledger-discount', LedgerDiscountController::class)->only('edit', 'destroy', 'store', 'update');

    Route::post('check-mobile', [ContactController::class, 'checkMobile']);
    Route::get('/get-contact-due/{contact_id}', [ContactController::class, 'getContactDue']);
    Route::get('/contacts/payments/{contact_id}', [ContactController::class, 'getContactPayments']);
    Route::get('/contacts/map', [ContactController::class, 'contactMap']);
    Route::get('/contacts/update-status/{id}', [ContactController::class, 'updateStatus']);
    Route::get('/contacts/stock-report/{supplier_id}', [ContactController::class, 'getSupplierStockReport']);
    Route::get('/contacts/ledger', [ContactController::class, 'getLedger']);
    Route::post('/contacts/send-ledger', [ContactController::class, 'sendLedger']);
    Route::get('/contacts/import', [ContactController::class, 'getImportContacts'])->name('contacts.import');
    Route::post('/contacts/import', [ContactController::class, 'postImportContacts']);
    Route::post('/contacts/check-contacts-id', [ContactController::class, 'checkContactId']);
    Route::get('/contacts/customers', [ContactController::class, 'getCustomers']);
    Route::get('/contacts/create-page', [ContactController::class, 'createPage']);

    // Slice 5a — BrasilAPI lookup CNPJ. Proxy AJAX informativo (gov.br público).
    // Permission check + Rule\BR\CpfCnpj (mod-11) no controller.
    // Mantido ANTES de `Route::resource('contacts', …)` pra evitar match em /contacts/{id}.
    Route::get(
        '/contacts/lookup/cnpj/{cnpj}',
        [\App\Http\Controllers\Cliente\ContactLookupController::class, 'cnpj']
    )->where('cnpj', '[0-9./\\-]+');

    Route::resource('contacts', ContactController::class);

    // Wagner 2026-05-21 Fase 2 deprecação legacy Cliente — URLs canon /cliente.
    // INDEX+SHOW canary (read-only). Wrappers thin que reusam ContactController.
    // Multi-tenant Tier 0: type=customer injetado pra Index; guard customer/both
    // pra Show (supplier NUNCA cai aqui). Ver app/Http/Middleware/RedirectLegacyContacts.php.
    //
    // ADR 0188 (2026-05-24) — Slot 2 PT-01 multi-type: `/cliente?type=X` aceita
    // whitelist 6 valores (customer/supplier/employee/representative/other/all). Default
    // 'customer' se ausente ou inválido pra retrocompat com bookmarks/links externos.
    // ADR 0246 (2026-06-03) — `other` (aba "Outros") incluído: o frontend (Index.tsx
    // SLOT2_TABS) + controller ($types/$inertiaTypes) já aceitavam, mas esta whitelist
    // ficou pra trás → `?type=other` caía no fallback `customer` (aba abria em Clientes).
    Route::get('/cliente', function (ContactController $c) {
        $allowed = ['customer', 'supplier', 'employee', 'representative', 'other', 'all'];
        $type = (string) request()->query('type', 'customer');
        if (! in_array($type, $allowed, true)) {
            $type = 'customer';
        }
        request()->merge(['type' => $type]);
        return $c->index();
    })->name('cliente.index');

    // Wave G (ADR 0179) — Export CSV da listagem.
    // Multi-tenant Tier 0 scope automático via session('user.business_id') no controller.
    // Mantido ANTES de `/cliente/{id}` pra evitar match (`export` cair em {id} regex).
    Route::get('/cliente/export', [ContactController::class, 'clienteExport'])
        ->name('cliente.export');

    Route::get('/cliente/{id}', function (int $id, ContactController $c) {
        $bizId = (int) session('user.business_id');
        $ok = \App\Contact::where('business_id', $bizId)
            ->where('id', $id)
            ->whereIn('type', ['customer', 'both'])
            ->exists();
        if (! $ok) {
            abort(404);
        }

        // Wave B ADR 0179: paradigma drawer 760px substitui Show.tsx full-page.
        // Quando cliente_index liga, /cliente/{id} redirect 302 -> Index com
        // deeplink ?contact_id={id}&tab=identificacao (drawer abre on-load).
        // Tab opcional via ?tab= preserva navegacao especifica do Copiloto/Slack.
        // Fallback legacy (canary biz=1 rollback): cliente_show liga -> Show.tsx.
        if (config('mwart.cliente_index.enabled')) {
            $tab = (string) (request()->query('tab') ?: 'identificacao');
            return redirect()->to("/cliente?contact_id={$id}&tab={$tab}");
        }

        return $c->show($id);
    })->name('cliente.show')->whereNumber('id');

    // Wagner 2026-06-01 — anexos do cliente (drawer Operações → Documentos). JSON.
    // Tier 0 multi-tenant: scope business_id no controller (ContactController::anexos).
    Route::get('/cliente/{id}/anexos', [ContactController::class, 'anexos'])
        ->name('cliente.anexos.index')->whereNumber('id');
    Route::post('/cliente/{id}/anexos', [ContactController::class, 'storeAnexo'])
        ->name('cliente.anexos.store')->whereNumber('id');
    Route::delete('/cliente/{id}/anexos/{mediaId}', [ContactController::class, 'destroyAnexo'])
        ->name('cliente.anexos.destroy')->whereNumber('id')->whereNumber('mediaId');

    // Fix 2026-06-08 — vendas do cliente (drawer Operações → Vendas). JSON.
    // Bug: SalesTab no drawer recebia sales=undefined e nunca buscava → skeleton
    // infinito ("as vendas não aparecem no cadastro de cliente"). Endpoint dá o
    // self-fetch que faltava. Tier 0 multi-tenant: scope no ContactController::salesJson.
    Route::get('/cliente/{id}/sales-json', [ContactController::class, 'salesJson'])
        ->name('cliente.sales.json')->whereNumber('id');

    // Fix 2026-06-08 — pagamentos/pontos/assinaturas do cliente (drawer Operações). JSON.
    // Mesmas abas que ficavam vazias/"aguardando wiring" no drawer por falta de fonte
    // self-fetch (recebiam prop undefined). Tier 0 multi-tenant: scope no controller.
    Route::get('/cliente/{id}/payments-json', [ContactController::class, 'paymentsJson'])
        ->name('cliente.payments.json')->whereNumber('id');
    Route::get('/cliente/{id}/rewards-json', [ContactController::class, 'rewardsJson'])
        ->name('cliente.rewards.json')->whereNumber('id');
    Route::get('/cliente/{id}/subscriptions-json', [ContactController::class, 'subscriptionsJson'])
        ->name('cliente.subscriptions.json')->whereNumber('id');

    Route::get('taxonomies-ajax-index-page', [TaxonomyController::class, 'getTaxonomyIndexPage']);
    Route::resource('taxonomies', TaxonomyController::class);

    Route::resource('variation-templates', VariationTemplateController::class);

    Route::get('/products/download-excel', [ProductController::class, 'downloadExcel']);

    // Catálogo Unificado (Cockpit V2) — 5 sub-telas em uma rota.
    // Persona Larissa [L] · 1280×1024 · ROTA LIVRE.
    // TODO [CL]: adicionar middleware('can:product.view') após confirmar permission name.
    Route::get('/products/unificado', [\App\Http\Controllers\ProdutoUnificadoController::class, 'index'])
        ->name('products.unificado.index');

    Route::get('/products/stock-history/{id}', [ProductController::class, 'productStockHistory']);
    Route::get('/delete-media/{media_id}', [ProductController::class, 'deleteMedia']);
    Route::post('/products/mass-deactivate', [ProductController::class, 'massDeactivate']);
    Route::get('/products/activate/{id}', [ProductController::class, 'activate']);
    Route::get('/products/view-product-group-price/{id}', [ProductController::class, 'viewGroupPrice']);
    Route::get('/products/add-selling-prices/{id}', [ProductController::class, 'addSellingPrices']);
    Route::post('/products/save-selling-prices', [ProductController::class, 'saveSellingPrices']);
    Route::post('/products/mass-delete', [ProductController::class, 'massDestroy']);
    Route::get('/products/view/{id}', [ProductController::class, 'view']);
    Route::get('/products/list', [ProductController::class, 'getProducts']);
    Route::get('/products/list-no-variation', [ProductController::class, 'getProductsWithoutVariations']);
    Route::post('/products/bulk-edit', [ProductController::class, 'bulkEdit']);
    Route::post('/products/bulk-update', [ProductController::class, 'bulkUpdate']);
    Route::post('/products/bulk-update-location', [ProductController::class, 'updateProductLocation']);
    Route::get('/products/get-product-to-edit/{product_id}', [ProductController::class, 'getProductToEdit']);

    Route::post('/products/get_sub_categories', [ProductController::class, 'getSubCategories']);
    Route::get('/products/get_sub_units', [ProductController::class, 'getSubUnits']);
    Route::post('/products/product_form_part', [ProductController::class, 'getProductVariationFormPart']);
    Route::post('/products/get_product_variation_row', [ProductController::class, 'getProductVariationRow']);
    Route::post('/products/get_variation_template', [ProductController::class, 'getVariationTemplate']);
    Route::get('/products/get_variation_value_row', [ProductController::class, 'getVariationValueRow']);
    Route::post('/products/check_product_sku', [ProductController::class, 'checkProductSku']);
    Route::post('/products/validate_variation_skus', [ProductController::class, 'validateVaritionSkus']); //validates multiple skus at once
    Route::get('/products/quick_add', [ProductController::class, 'quickAdd']);
    Route::post('/products/save_quick_product', [ProductController::class, 'saveQuickProduct']);
    Route::get('/products/get-combo-product-entry-row', [ProductController::class, 'getComboProductEntryRow']);
    Route::post('/products/toggle-woocommerce-sync', [ProductController::class, 'toggleWooCommerceSync']);

    Route::resource('products', ProductController::class);

    // US-INV-001 — endpoints CRUD BOM (Bill of Materials).
    // Multi-tenant Tier 0 + permission product.update enforced no Controller.
    Route::get('/api/products/{id}/bom', [\App\Http\Controllers\Inventory\ProductBomController::class, 'index'])
        ->whereNumber('id')
        ->name('products.bom.index');
    Route::post('/api/products/{id}/bom', [\App\Http\Controllers\Inventory\ProductBomController::class, 'store'])
        ->whereNumber('id')
        ->name('products.bom.store');
    Route::delete('/api/products/{id}/bom/{bom_id}', [\App\Http\Controllers\Inventory\ProductBomController::class, 'destroy'])
        ->whereNumber('id')->whereNumber('bom_id')
        ->name('products.bom.destroy');

    Route::get('/toggle-subscription/{id}', [SellPosController::class, 'toggleRecurringInvoices']);
    Route::post('/sells/pos/get-types-of-service-details', [SellPosController::class, 'getTypesOfServiceDetails']);
    Route::get('/sells/subscriptions', [SellPosController::class, 'listSubscriptions']);
    Route::get('/sells/duplicate/{id}', [SellController::class, 'duplicateSell']);
    Route::get('/sells/drafts', [SellController::class, 'getDrafts']);
    Route::get('/sells/convert-to-draft/{id}', [SellPosController::class, 'convertToInvoice']);
    Route::get('/sells/convert-to-proforma/{id}', [SellPosController::class, 'convertToProforma']);
    Route::get('/sells/quotations', [SellController::class, 'getQuotations']);
    Route::get('/sells/draft-dt', [SellController::class, 'getDraftDatables']);
    // US-SELL-008 — Sells/Index.tsx Inertia endpoints (lista JSON + drawer detail).
    Route::get('/sells-list-json', [SellController::class, 'inertiaList']);
    Route::get('/sells/{id}/sheet-data', [SellController::class, 'sheetData']);
    // Onda 6 (ADR 0192 A1 KB-9.75) — Caixa do dia Inertia em rota nova.
    // COEXISTE com /cash-register/* Blade legacy (decisão Wagner 2026-05-25 ~15h).
    // Permission gate dentro do controller (direct_sell.view + variants).
    Route::get('/vendas/caixa', [SellController::class, 'inertiaCaixa'])->name('vendas.caixa');
    Route::post('/sells/{id}/quick-payment', [SellController::class, 'quickPayment']);
    // Onda 4d.5 — Wire-up emissão real via PaymentGatewayContract::emitirX().
    // Chip "Emitir cobrança" no footer drawer Sells (ADR 0144 + 0170).
    Route::post('/sells/{id}/emitir-cobranca', [SellController::class, 'emitirCobranca'])
        ->whereNumber('id')
        ->name('sells.emitir-cobranca');
    // US-SELL-COWORK-R2-IA — Cowork KB-9.75 Onda 2: painel ✦ IA no drawer SaleSheet.
    // 3 modos: summary|history|suggest. Stub determinístico nesta Onda; Onda 2.5
    // integra Modules/Jana/Ai/Agents/ real.
    Route::post('/sells/{id}/ai-ask', [SellController::class, 'aiAsk']);
    // US-OFICINA-OS-LINK — Criar OS a partir da venda (modos: auto/single/per_line).
    Route::post('/sells/{id}/create-os', [SellController::class, 'createOs'])->name('sells.create-os');
    // US-SELL-COWORK-R4-C1 — Transcript PDF server-side via Browsershot Chrome headless.
    // Substitui window.print() do modal SaleTranscriptPDF.tsx por download forçado.
    // Fallback 503 estruturado em runtimes sem Chrome (Hostinger shared) — frontend
    // degrada gracefully ocultando botão (graceful degradation).
    Route::get('/sells/{sale}/transcript.pdf', [\App\Http\Controllers\SellTranscriptPdfController::class, 'show'])
        ->whereNumber('sale')
        ->name('sells.transcript-pdf');
    // US-SELL-016 — Bulk actions (Grade Avançada — multiseleção).
    Route::post('/sells/bulk-print', [SellController::class, 'bulkPrint'])->name('sells.bulk-print');
    Route::post('/sells/bulk-export', [SellController::class, 'bulkExport'])->name('sells.bulk-export');
    // US-SELL-035 — Timeline FSM (sale_stage_history) pra drawer e auditoria.
    Route::get('/api/sells/{id}/history', [\App\Http\Controllers\SaleHistoryController::class, 'index'])
        ->name('sells.history');
    // P4 parking lot pós-PR #1663 (gap #11 r4 visual-comparison) — timeline
    // cross-source: FSM + payments + activities + comments + audit_log num
    // único stream cronológico reverso pra Sells/Show + drawer.
    Route::get('/api/sells/{id}/timeline-unified', [\App\Http\Controllers\SaleHistoryController::class, 'timelineUnified'])
        ->name('sells.timeline-unified');
    // US-SELL-COWORK-R3-CURADORIA Onda 3.5 — Audit Trail FSM real (formato flat
    // amigável pro componente SaleAuditTrail.tsx). Multi-tenant Tier 0.
    Route::get('/sells/{sale}/audit', [\App\Http\Controllers\SellAuditController::class, 'show'])
        ->name('sells.audit');
    // Wire-up UI FSM — actions disponíveis no stage atual + execute transição.
    Route::get('/api/sells/{id}/fsm-actions', [\App\Http\Controllers\SaleFsmActionController::class, 'actions'])
        ->name('sells.fsm-actions');
    Route::post('/sells/{id}/fsm-action', [\App\Http\Controllers\SaleFsmActionController::class, 'execute'])
        ->name('sells.fsm-execute');
    Route::post('/sells/{id}/fsm-start-pipeline', [\App\Http\Controllers\SaleFsmActionController::class, 'startPipeline'])
        ->name('sells.fsm-start-pipeline');
    // ADR 0192 Onda 2 follow-up — Editor UI do split de comissão (mecânico/balcão).
    // Endpoint dedicado pra preservar SoC (não cruza com SellPosController::update).
    Route::patch('/sells/{id}/commission-split', [\App\Http\Controllers\SellCommissionSplitController::class, 'update'])
        ->whereNumber('id')
        ->name('sells.commission-split.update');
    Route::resource('sells', SellController::class)->except(['show']);
    Route::get('/sells/copy-quotation/{id}', [SellPosController::class, 'copyQuotation']);

    Route::post('/import-purchase-products', [PurchaseController::class, 'importPurchaseProducts']);
    Route::post('/purchases/update-status', [PurchaseController::class, 'updateStatus']);
    Route::get('/purchases/get_products', [PurchaseController::class, 'getProducts']);
    Route::get('/purchases/get_suppliers', [PurchaseController::class, 'getSuppliers']);
    Route::post('/purchases/get_purchase_entry_row', [PurchaseController::class, 'getPurchaseEntryRow']);
    Route::post('/purchases/check_ref_number', [PurchaseController::class, 'checkRefNumber']);
    // US-COM-005 — layout da grade tam×cor pra produto variável (Tier 0 via business_id da sessão).
    Route::get('/purchases/grade-matrix', [PurchaseController::class, 'gradeMatrix'])->name('purchases.grade-matrix');
    Route::resource('purchases', PurchaseController::class)->except(['show']);

    // As rotas /sells (subscriptions, drafts, quotations, duplicate, convert-to-draft,
    // convert-to-proforma, draft-dt, get-types-of-service-details, toggle-subscription)
    // e Route::resource('sells') já estão declaradas ACIMA, antes do resource. O bloco
    // que existia aqui era duplicata byte-a-byte e, por ficar APÓS o resource, suas rotas
    // GET eram sombreadas por /sells/{id}; redeclarar o resource aqui quebrava route:cache
    // ("Another route has already been assigned name [sells.index]"). Bloco removido.

    Route::get('/import-sales', [ImportSalesController::class, 'index']);
    Route::post('/import-sales/preview', [ImportSalesController::class, 'preview']);
    Route::post('/import-sales', [ImportSalesController::class, 'import']);
    Route::get('/revert-sale-import/{batch}', [ImportSalesController::class, 'revertSaleImport']);

    Route::get('/sells/pos/get_product_row/{variation_id}/{location_id}', [SellPosController::class, 'getProductRow']);
    Route::post('/sells/pos/get_payment_row', [SellPosController::class, 'getPaymentRow']);
    Route::post('/sells/pos/get-reward-details', [SellPosController::class, 'getRewardDetails']);
    Route::get('/sells/pos/get-recent-transactions', [SellPosController::class, 'getRecentTransactions']);
    Route::get('/sells/pos/get-product-suggestion', [SellPosController::class, 'getProductSuggestion']);
    Route::get('/sells/pos/get-featured-products/{location_id}', [SellPosController::class, 'getFeaturedProducts']);
    Route::get('/reset-mapping', [SellController::class, 'resetMapping']);

    Route::resource('pos', SellPosController::class);

    Route::resource('roles', RoleController::class);

    Route::resource('users', ManageUserController::class);

    Route::resource('group-taxes', GroupTaxController::class);

    Route::get('/barcodes/set_default/{id}', [BarcodeController::class, 'setDefault']);
    Route::resource('barcodes', BarcodeController::class);

    //Invoice schemes..
    Route::get('/invoice-schemes/set_default/{id}', [InvoiceSchemeController::class, 'setDefault']);
    Route::resource('invoice-schemes', InvoiceSchemeController::class);

    //Print Labels
    Route::get('/labels/show', [LabelsController::class, 'show']);
    Route::get('/labels/add-product-row', [LabelsController::class, 'addProductRow']);
    Route::get('/labels/preview', [LabelsController::class, 'preview']);

    //Reports...
    Route::get('/reports/gst-purchase-report', [ReportController::class, 'gstPurchaseReport']);
    Route::get('/reports/gst-sales-report', [ReportController::class, 'gstSalesReport']);
    Route::get('/reports/get-stock-by-sell-price', [ReportController::class, 'getStockBySellingPrice']);
    Route::get('/reports/purchase-report', [ReportController::class, 'purchaseReport']);
    Route::get('/reports/sale-report', [ReportController::class, 'saleReport']);
    Route::get('/reports/service-staff-report', [ReportController::class, 'getServiceStaffReport']);
    Route::get('/reports/service-staff-line-orders', [ReportController::class, 'serviceStaffLineOrders']);
    Route::get('/reports/table-report', [ReportController::class, 'getTableReport']);
    Route::get('/reports/profit-loss', [ReportController::class, 'getProfitLoss']);
    Route::get('/reports/get-opening-stock', [ReportController::class, 'getOpeningStock']);
    Route::get('/reports/purchase-sell', [ReportController::class, 'getPurchaseSell']);
    Route::get('/reports/customer-supplier', [ReportController::class, 'getCustomerSuppliers']);
    Route::get('/reports/stock-report', [ReportController::class, 'getStockReport']);
    Route::get('/reports/stock-details', [ReportController::class, 'getStockDetails']);
    Route::get('/reports/tax-report', [ReportController::class, 'getTaxReport']);
    Route::get('/reports/tax-details', [ReportController::class, 'getTaxDetails']);
    Route::get('/reports/trending-products', [ReportController::class, 'getTrendingProducts']);
    Route::get('/reports/expense-report', [ReportController::class, 'getExpenseReport']);
    Route::get('/reports/stock-adjustment-report', [ReportController::class, 'getStockAdjustmentReport']);
    Route::get('/reports/register-report', [ReportController::class, 'getRegisterReport']);
    Route::get('/reports/sales-representative-report', [ReportController::class, 'getSalesRepresentativeReport']);
    Route::get('/reports/sales-representative-total-expense', [ReportController::class, 'getSalesRepresentativeTotalExpense']);
    Route::get('/reports/sales-representative-total-sell', [ReportController::class, 'getSalesRepresentativeTotalSell']);
    Route::get('/reports/sales-representative-total-commission', [ReportController::class, 'getSalesRepresentativeTotalCommission']);
    Route::get('/reports/stock-expiry', [ReportController::class, 'getStockExpiryReport']);
    Route::get('/reports/stock-expiry-edit-modal/{purchase_line_id}', [ReportController::class, 'getStockExpiryReportEditModal']);
    Route::post('/reports/stock-expiry-update', [ReportController::class, 'updateStockExpiryReport'])->name('updateStockExpiryReport');
    Route::get('/reports/customer-group', [ReportController::class, 'getCustomerGroup']);
    Route::get('/reports/product-purchase-report', [ReportController::class, 'getproductPurchaseReport']);
    Route::get('/reports/product-sell-grouped-by', [ReportController::class, 'productSellReportBy']);
    Route::get('/reports/product-sell-report', [ReportController::class, 'getproductSellReport']);
    Route::get('/reports/product-sell-report-with-purchase', [ReportController::class, 'getproductSellReportWithPurchase']);
    Route::get('/reports/product-sell-grouped-report', [ReportController::class, 'getproductSellGroupedReport']);
    Route::get('/reports/lot-report', [ReportController::class, 'getLotReport']);
    Route::get('/reports/purchase-payment-report', [ReportController::class, 'purchasePaymentReport']);
    Route::get('/reports/sell-payment-report', [ReportController::class, 'sellPaymentReport']);
    Route::get('/reports/product-stock-details', [ReportController::class, 'productStockDetails']);
    Route::get('/reports/adjust-product-stock', [ReportController::class, 'adjustProductStock']);
    Route::get('/reports/get-profit/{by?}', [ReportController::class, 'getProfit']);
    Route::get('/reports/items-report', [ReportController::class, 'itemsReport']);
    Route::get('/reports/get-stock-value', [ReportController::class, 'getStockValue']);

    Route::get('business-location/activate-deactivate/{location_id}', [BusinessLocationController::class, 'activateDeactivateLocation']);

    //Business Location Settings...
    Route::prefix('business-location/{location_id}')->name('location.')->group(function () {
        Route::get('settings', [LocationSettingsController::class, 'index'])->name('settings');
        Route::post('settings', [LocationSettingsController::class, 'updateSettings'])->name('settings_update');
    });

    //Business Locations...
    Route::post('business-location/check-location-id', [BusinessLocationController::class, 'checkLocationId']);
    Route::resource('business-location', BusinessLocationController::class);

    //Invoice layouts..
    Route::resource('invoice-layouts', InvoiceLayoutController::class);

    Route::post('get-expense-sub-categories', [ExpenseCategoryController::class, 'getSubCategories']);

    //Expense Categories...
    Route::resource('expense-categories', ExpenseCategoryController::class);

    //Expenses...
    Route::resource('expenses', ExpenseController::class);

    //Transaction payments...
    // Route::get('/payments/opening-balance/{contact_id}', 'TransactionPaymentController@getOpeningBalancePayments');
    Route::get('/payments/show-child-payments/{payment_id}', [TransactionPaymentController::class, 'showChildPayments']);
    Route::get('/payments/view-payment/{payment_id}', [TransactionPaymentController::class, 'viewPayment']);
    Route::get('/payments/add_payment/{transaction_id}', [TransactionPaymentController::class, 'addPayment']);
    Route::get('/payments/pay-contact-due/{contact_id}', [TransactionPaymentController::class, 'getPayContactDue']);
    Route::post('/payments/pay-contact-due', [TransactionPaymentController::class, 'postPayContactDue']);

    // MWART · /payments/v2 — Inertia coexiste com Blade /payments (Wave Blade T1 Migration B)
    Route::get('/payments/v2', [TransactionPaymentController::class, 'indexInertia'])->name('payments.v2.index');
    Route::get('/payments/v2/{id}/edit', [TransactionPaymentController::class, 'editInertia'])->name('payments.v2.edit');
    Route::get('/payments/v2/{id}', [TransactionPaymentController::class, 'showInertia'])->name('payments.v2.show');

    Route::resource('payments', TransactionPaymentController::class);

    //Printers...
    Route::resource('printers', PrinterController::class);

    Route::get('/stock-adjustments/remove-expired-stock/{purchase_line_id}', [StockAdjustmentController::class, 'removeExpiredStock']);
    Route::post('/stock-adjustments/get_product_row', [StockAdjustmentController::class, 'getProductRow']);
    Route::resource('stock-adjustments', StockAdjustmentController::class);

    Route::get('/cash-register/register-details', [CashRegisterController::class, 'getRegisterDetails']);
    Route::get('/cash-register/close-register/{id?}', [CashRegisterController::class, 'getCloseRegister']);
    Route::post('/cash-register/close-register', [CashRegisterController::class, 'postCloseRegister']);
    Route::resource('cash-register', CashRegisterController::class);

    //Import products
    Route::get('/import-products', [ImportProductsController::class, 'index']);
    Route::post('/import-products/store', [ImportProductsController::class, 'store']);

    //Sales Commission Agent
    Route::resource('sales-commission-agents', SalesCommissionAgentController::class);

    //Stock Transfer
    Route::get('stock-transfers/print/{id}', [StockTransferController::class, 'printInvoice']);
    Route::post('stock-transfers/update-status/{id}', [StockTransferController::class, 'updateStatus']);
    Route::resource('stock-transfers', StockTransferController::class);

    Route::get('/opening-stock/add/{product_id}', [OpeningStockController::class, 'add']);
    Route::post('/opening-stock/save', [OpeningStockController::class, 'save']);

    //Customer Groups
    Route::resource('customer-group', CustomerGroupController::class);

    //Import opening stock
    Route::get('/import-opening-stock', [ImportOpeningStockController::class, 'index']);
    Route::post('/import-opening-stock/store', [ImportOpeningStockController::class, 'store']);

    //Sell return
    Route::get('validate-invoice-to-return/{invoice_no}', [SellReturnController::class, 'validateInvoiceToReturn']);
    // service staff replacement
    Route::get('validate-invoice-to-service-staff-replacement/{invoice_no}', [SellPosController::class, 'validateInvoiceToServiceStaffReplacement']);
    Route::put('change-service-staff/{id}', [SellPosController::class, 'change_service_staff'])->name('change_service_staff');

    Route::resource('sell-return', SellReturnController::class);
    Route::get('sell-return/get-product-row', [SellReturnController::class, 'getProductRow']);
    Route::get('/sell-return/print/{id}', [SellReturnController::class, 'printInvoice']);
    Route::get('/sell-return/add/{id}', [SellReturnController::class, 'add']);

    //Backup
    Route::get('backup/download/{file_name}', [BackUpController::class, 'download']);
    Route::get('backup/{id}/delete', [BackUpController::class, 'delete'])->name('delete_backup');
    Route::resource('backup', BackUpController::class)->only('index', 'create', 'store');

    Route::get('selling-price-group/activate-deactivate/{id}', [SellingPriceGroupController::class, 'activateDeactivate']);
    Route::get('update-product-price', [SellingPriceGroupController::class, 'updateProductPrice'])->name('update-product-price');
    Route::get('export-product-price', [SellingPriceGroupController::class, 'export']);
    Route::post('import-product-price', [SellingPriceGroupController::class, 'import']);

    Route::resource('selling-price-group', SellingPriceGroupController::class);

    Route::resource('notification-templates', NotificationTemplateController::class)->only(['index', 'store']);
    Route::get('notification/get-template/{transaction_id}/{template_for}', [NotificationController::class, 'getTemplate']);
    Route::post('notification/send', [NotificationController::class, 'send']);

    Route::post('/purchase-return/update', [CombinedPurchaseReturnController::class, 'update']);
    Route::get('/purchase-return/edit/{id}', [CombinedPurchaseReturnController::class, 'edit']);
    Route::post('/purchase-return/save', [CombinedPurchaseReturnController::class, 'save']);
    Route::post('/purchase-return/get_product_row', [CombinedPurchaseReturnController::class, 'getProductRow']);
    Route::get('/purchase-return/create', [CombinedPurchaseReturnController::class, 'create']);
    Route::get('/purchase-return/add/{id}', [PurchaseReturnController::class, 'add']);
    Route::resource('/purchase-return', PurchaseReturnController::class)->except('create');

    Route::get('/discount/activate/{id}', [DiscountController::class, 'activate']);
    Route::post('/discount/mass-deactivate', [DiscountController::class, 'massDeactivate']);
    Route::resource('discount', DiscountController::class);

    Route::prefix('account')->group(function () {
        Route::resource('/account', AccountController::class);
        Route::get('/fund-transfer/{id}', [AccountController::class, 'getFundTransfer']);
        Route::post('/fund-transfer', [AccountController::class, 'postFundTransfer']);
        Route::get('/deposit/{id}', [AccountController::class, 'getDeposit']);
        Route::post('/deposit', [AccountController::class, 'postDeposit']);
        Route::get('/close/{id}', [AccountController::class, 'close']);
        Route::get('/activate/{id}', [AccountController::class, 'activate']);
        Route::get('/delete-account-transaction/{id}', [AccountController::class, 'destroyAccountTransaction']);
        Route::get('/edit-account-transaction/{id}', [AccountController::class, 'editAccountTransaction']);
        Route::post('/update-account-transaction/{id}', [AccountController::class, 'updateAccountTransaction']);
        Route::get('/get-account-balance/{id}', [AccountController::class, 'getAccountBalance']);
        Route::get('/balance-sheet', [AccountReportsController::class, 'balanceSheet']);
        Route::get('/trial-balance', [AccountReportsController::class, 'trialBalance']);
        Route::get('/payment-account-report', [AccountReportsController::class, 'paymentAccountReport']);
        Route::get('/link-account/{id}', [AccountReportsController::class, 'getLinkAccount']);
        Route::post('/link-account', [AccountReportsController::class, 'postLinkAccount']);
        Route::get('/cash-flow', [AccountController::class, 'cashFlow']);
    });

    Route::resource('account-types', AccountTypeController::class);

    //Restaurant module
    Route::prefix('modules')->group(function () {
        Route::resource('tables', Restaurant\TableController::class);
        Route::resource('modifiers', Restaurant\ModifierSetsController::class);

        //Map modifier to products
        Route::get('/product-modifiers/{id}/edit', [Restaurant\ProductModifierSetController::class, 'edit']);
        Route::post('/product-modifiers/{id}/update', [Restaurant\ProductModifierSetController::class, 'update']);
        Route::get('/product-modifiers/product-row/{product_id}', [Restaurant\ProductModifierSetController::class, 'product_row']);

        Route::get('/add-selected-modifiers', [Restaurant\ProductModifierSetController::class, 'add_selected_modifiers']);

        Route::get('/kitchen', [Restaurant\KitchenController::class, 'index']);
        Route::get('/kitchen/mark-as-cooked/{id}', [Restaurant\KitchenController::class, 'markAsCooked']);
        Route::post('/refresh-orders-list', [Restaurant\KitchenController::class, 'refreshOrdersList']);
        Route::post('/refresh-line-orders-list', [Restaurant\KitchenController::class, 'refreshLineOrdersList']);

        Route::get('/orders', [Restaurant\OrderController::class, 'index']);
        Route::get('/orders/mark-as-served/{id}', [Restaurant\OrderController::class, 'markAsServed']);
        Route::get('/data/get-pos-details', [Restaurant\DataController::class, 'getPosDetails']);
        Route::get('/data/check-staff-pin', [Restaurant\DataController::class, 'checkStaffPin']);
        Route::get('/orders/mark-line-order-as-served/{id}', [Restaurant\OrderController::class, 'markLineOrderAsServed']);
        Route::get('/print-line-order', [Restaurant\OrderController::class, 'printLineOrder']);
    });

    Route::get('bookings/get-todays-bookings', [Restaurant\BookingController::class, 'getTodaysBookings']);
    Route::resource('bookings', Restaurant\BookingController::class);

    Route::resource('types-of-service', TypesOfServiceController::class);
    Route::get('sells/edit-shipping/{id}', [SellController::class, 'editShipping']);
    Route::put('sells/update-shipping/{id}', [SellController::class, 'updateShipping']);
    Route::get('shipments', [SellController::class, 'shipments']);

    Route::post('upload-module', [Install\ModulesController::class, 'uploadModule']);
    Route::delete('manage-modules/destroy/{module_name}', [Install\ModulesController::class, 'destroy']);
    Route::resource('manage-modules', Install\ModulesController::class)
        ->only(['index', 'update']);
    Route::get('regenerate', [Install\ModulesController::class, 'regenerate']);

    Route::resource('warranties', WarrantyController::class);

    Route::resource('dashboard-configurator', DashboardConfiguratorController::class)
    ->only(['edit', 'update']);

    Route::get('view-media/{model_id}', [SellController::class, 'viewMedia']);

    //common controller for document & note
    Route::get('get-document-note-page', [DocumentAndNoteController::class, 'getDocAndNoteIndexPage']);
    Route::post('post-document-upload', [DocumentAndNoteController::class, 'postMedia']);
    Route::resource('note-documents', DocumentAndNoteController::class);
    Route::resource('purchase-order', PurchaseOrderController::class);
    Route::get('get-purchase-orders/{contact_id}', [PurchaseOrderController::class, 'getPurchaseOrders']);
    Route::get('get-purchase-order-lines/{purchase_order_id}', [PurchaseController::class, 'getPurchaseOrderLines']);
    Route::get('edit-purchase-orders/{id}/status', [PurchaseOrderController::class, 'getEditPurchaseOrderStatus']);
    Route::put('update-purchase-orders/{id}/status', [PurchaseOrderController::class, 'postEditPurchaseOrderStatus']);
    Route::resource('sales-order', SalesOrderController::class)->only(['index']);
    Route::get('get-sales-orders/{customer_id}', [SalesOrderController::class, 'getSalesOrders']);
    Route::get('get-sales-order-lines', [SellPosController::class, 'getSalesOrderLines']);
    Route::get('edit-sales-orders/{id}/status', [SalesOrderController::class, 'getEditSalesOrderStatus']);
    Route::put('update-sales-orders/{id}/status', [SalesOrderController::class, 'postEditSalesOrderStatus']);
    Route::get('reports/activity-log', [ReportController::class, 'activityLog']);
    Route::get('user-location/{latlng}', [HomeController::class, 'getUserLocation']);
});

// Route::middleware(['EcomApi'])->prefix('api/ecom')->group(function () {
//     Route::get('products/{id?}', [ProductController::class, 'getProductsApi']);
//     Route::get('categories', [CategoryController::class, 'getCategoriesApi']);
//     Route::get('brands', [BrandController::class, 'getBrandsApi']);
//     Route::post('customers', [ContactController::class, 'postCustomersApi']);
//     Route::get('settings', [BusinessController::class, 'getEcomSettings']);
//     Route::get('variations', [ProductController::class, 'getVariationsApi']);
//     Route::post('orders', [SellPosController::class, 'placeOrdersApi']);
// });

//common route
Route::middleware(['auth'])->group(function () {
    Route::get('/logout', [App\Http\Controllers\Auth\LoginController::class, 'logout']);

    // Preferências de UI do usuário (tema, sidebar colapsado). Qualquer request
    // autenticada pode atualizar — não depende de SetSessionData/business_id.
    Route::post('/user/preferences/theme',
        [\App\Http\Controllers\UserPreferencesController::class, 'updateTheme']
    )->name('user.preferences.theme');
    Route::post('/user/preferences/sidebar',
        [\App\Http\Controllers\UserPreferencesController::class, 'updateSidebarCollapsed']
    )->name('user.preferences.sidebar');
});

// Gerenciador de Módulos — substituto React do /manage-modules (AdminLTE quebrado).
// Precisa de SetSessionData p/ ter business_id + is_admin na sessão, e
// AdminSidebarMenu p/ popular o menu do shell (33 módulos).
Route::middleware(['web', 'setData', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu'])
    ->group(function () {
        Route::get('/modulos',
            [\App\Http\Controllers\ModuleManagementController::class, 'index']
        )->name('modules.index');
        Route::post('/modulos/{name}/toggle',
            [\App\Http\Controllers\ModuleManagementController::class, 'toggle']
        )->name('modules.toggle');
        Route::post('/modulos/{name}/install',
            [\App\Http\Controllers\ModuleManagementController::class, 'install']
        )->name('modules.install');
        Route::post('/modulos/{name}/uninstall',
            [\App\Http\Controllers\ModuleManagementController::class, 'uninstall']
        )->name('modules.uninstall');
    });

Route::middleware(['setData', 'auth', 'SetSessionData', 'language', 'timezone'])->group(function () {
    Route::get('/load-more-notifications', [HomeController::class, 'loadMoreNotifications']);
    Route::get('/get-total-unread', [HomeController::class, 'getTotalUnreadNotifications']);
    Route::get('/purchases/print/{id}', [PurchaseController::class, 'printInvoice']);
    Route::get('/purchases/{id}', [PurchaseController::class, 'show']);
    Route::get('/download-purchase-order/{id}/pdf', [PurchaseOrderController::class, 'downloadPdf'])->name('purchaseOrder.downloadPdf');
    Route::get('/sells/{id}', [SellController::class, 'show']);
    Route::get('/sells/{transaction_id}/print', [SellPosController::class, 'printInvoice'])->name('sell.printInvoice');
    Route::get('/download-sells/{transaction_id}/pdf', [SellPosController::class, 'downloadPdf'])->name('sell.downloadPdf');
    Route::get('/download-quotation/{id}/pdf', [SellPosController::class, 'downloadQuotationPdf'])
        ->name('quotation.downloadPdf');
    Route::get('/download-packing-list/{id}/pdf', [SellPosController::class, 'downloadPackingListPdf'])
        ->name('packing.downloadPdf');
    Route::get('/sells/invoice-url/{id}', [SellPosController::class, 'showInvoiceUrl']);
    Route::get('/show-notification/{id}', [HomeController::class, 'showNotification']);
});

// Modo Suporte — empresas-cliente acessíveis (exceto a operadora). Read-only (ADR 0305) +
// fase A "Acessar como" (login-as guardado, ADR 0308). Autorização nível-empresa + auditoria
// de entrada no middleware support.access (que lê {business}); a impersonação re-checa no controller.
Route::middleware(['auth', 'SetSessionData', 'language', 'timezone', 'support.access'])
    ->prefix('suporte')
    ->group(function () {
        Route::get('empresas', [\App\Http\Controllers\Support\SupportController::class, 'index'])
            ->name('suporte.empresas');
        Route::get('empresas/{business}', [\App\Http\Controllers\Support\SupportController::class, 'show'])
            ->whereNumber('business')
            ->name('suporte.empresas.show');
        // POST (não GET): "Acessar como" é escrita (troca a identidade) — exige CSRF.
        Route::post('empresas/{business}/acessar-como/{user}', [\App\Http\Controllers\Support\SupportController::class, 'acessarComo'])
            ->whereNumber(['business', 'user'])
            ->name('suporte.empresas.acessar-como');
    });