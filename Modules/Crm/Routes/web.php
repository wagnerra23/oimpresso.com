<?php

// use App\Http\Controllers\Modules;
// use Illuminate\Support\Facades\Route;

Route::middleware('web', 'authh', 'SetSessionData', 'auth', 'language', 'timezone', 'ContactSidebarMenu', 'CheckContactLogin')->prefix('contact')->group(function () {
    Route::resource('contact-dashboard', 'Modules\Crm\Http\Controllers\DashboardController');
    Route::get('contact-profile', [Modules\Crm\Http\Controllers\ManageProfileController::class, 'getProfile']);
    Route::post('contact-password-update', [Modules\Crm\Http\Controllers\ManageProfileController::class, 'updatePassword']);
    Route::post('contact-profile-update', [Modules\Crm\Http\Controllers\ManageProfileController::class, 'updateProfile']);
    Route::get('contact-purchases', [Modules\Crm\Http\Controllers\PurchaseController::class, 'getPurchaseList']);
    Route::get('contact-sells', [Modules\Crm\Http\Controllers\SellController::class, 'getSellList']);
    Route::get('contact-ledger', [Modules\Crm\Http\Controllers\LedgerController::class, 'index']);
    Route::get('contact-get-ledger', [Modules\Crm\Http\Controllers\LedgerController::class, 'getLedger']);
    // 'as'=>'contact' prefixa route names → contact.bookings.{index,create,...}
    // Evita colisão com Route::resource('bookings', Restaurant\BookingController) em routes/web.php:510
    // (bug pré-existente — route:cache falhava no deploy 2026-05-14)
    Route::resource('bookings', 'Modules\Crm\Http\Controllers\ContactBookingController', ['as' => 'contact']);
    Route::resource('order-request', 'Modules\Crm\Http\Controllers\OrderRequestController');
    Route::get('products/list', [\App\Http\Controllers\ProductController::class, 'getProducts']);
    Route::get('order-request/get_product_row/{variation_id}/{location_id}', [Modules\Crm\Http\Controllers\OrderRequestController::class, 'getProductRow']);
});

Route::middleware('web', 'authh', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin')->prefix('crm')->group(function () {
    Route::get('commissions', [Modules\Crm\Http\Controllers\ContactLoginController::class, 'commissions']);
    Route::get('all-contacts-login', [Modules\Crm\Http\Controllers\ContactLoginController::class, 'allContactsLoginList']);
    Route::resource('contact-login', 'Modules\Crm\Http\Controllers\ContactLoginController')->except(['show']);
    Route::resource('follow-ups', 'Modules\Crm\Http\Controllers\ScheduleController')->except(['show']);
    Route::get('todays-follow-ups', [Modules\Crm\Http\Controllers\ScheduleController::class, 'getTodaysSchedule']);
    Route::get('lead-follow-ups', [Modules\Crm\Http\Controllers\ScheduleController::class, 'getLeadSchedule']);
    Route::get('get-invoices', [Modules\Crm\Http\Controllers\ScheduleController::class, 'getInvoicesForFollowUp']);
    Route::get('get-followup-groups', [Modules\Crm\Http\Controllers\ScheduleController::class, 'getFollowUpGroups']);
    Route::get('all-users-call-logs', [Modules\Crm\Http\Controllers\CallLogController::class, 'allUsersCallLog']);

    Route::resource('follow-up-log', 'Modules\Crm\Http\Controllers\ScheduleLogController');

    // Wave 15 D8 Security — throttle 10/min em rotas Install (setup, idempotente mas pesado).
    Route::middleware('throttle:10,1')->group(function () {
        Route::get('install', [Modules\Crm\Http\Controllers\InstallController::class, 'index']);
        Route::post('install', [Modules\Crm\Http\Controllers\InstallController::class, 'install']);
        Route::get('install/uninstall', [Modules\Crm\Http\Controllers\InstallController::class, 'uninstall']);
        Route::get('install/update', [Modules\Crm\Http\Controllers\InstallController::class, 'update']);
    });

    Route::resource('leads', 'Modules\Crm\Http\Controllers\LeadController');
    Route::get('lead/{id}/convert', [Modules\Crm\Http\Controllers\LeadController::class, 'convertToCustomer']);
    Route::get('lead/{id}/post-life-stage', [Modules\Crm\Http\Controllers\LeadController::class, 'postLifeStage']);

    // Wave 15 D8 Security — throttle 20/min em send-notification (envia massa email/SMS — anti-abuso/spam).
    Route::middleware('throttle:20,1')
        ->get('{id}/send-campaign-notification', [Modules\Crm\Http\Controllers\CampaignController::class, 'sendNotification']);
    Route::resource('campaigns', 'Modules\Crm\Http\Controllers\CampaignController');
    Route::get('dashboard', [Modules\Crm\Http\Controllers\CrmDashboardController::class, 'index']);

    Route::get('reports', [Modules\Crm\Http\Controllers\ReportController::class, 'index']);
    Route::get('follow-ups-by-user', [Modules\Crm\Http\Controllers\ReportController::class, 'followUpsByUser']);
    Route::get('follow-ups-by-contact', [Modules\Crm\Http\Controllers\ReportController::class, 'followUpsContact']);
    Route::get('lead-to-customer-report', [Modules\Crm\Http\Controllers\ReportController::class, 'leadToCustomerConversion']);
    Route::get('lead-to-customer-details/{user_id}', [Modules\Crm\Http\Controllers\ReportController::class, 'showLeadToCustomerConversionDetails']);
    Route::get('call-log', [Modules\Crm\Http\Controllers\CallLogController::class, 'index'], ['only' => ['index']]);
    // Wave 15 D8 Security — throttle 30/min em mass-delete (bulk DELETE — anti-abuso destrutivo).
    Route::middleware('throttle:30,1')
        ->post('mass-delete-call-log', [Modules\Crm\Http\Controllers\CallLogController::class, 'massDestroy']);

    Route::get('edit-proposal-template', [Modules\Crm\Http\Controllers\ProposalTemplateController::class, 'getEdit']);
    Route::post('update-proposal-template', [Modules\Crm\Http\Controllers\ProposalTemplateController::class, 'postEdit']);
    Route::get('view-proposal-template', [Modules\Crm\Http\Controllers\ProposalTemplateController::class, 'getView']);
    // Wave 15 D8 Security — throttle 30/min em send-proposal (envia email; anti-abuso).
    Route::middleware('throttle:30,1')
        ->get('send-proposal', [Modules\Crm\Http\Controllers\ProposalTemplateController::class, 'send']);
    Route::delete('delete-proposal-media/{id}', [Modules\Crm\Http\Controllers\ProposalTemplateController::class, 'deleteProposalMedia']);
    Route::resource('proposal-template', 'Modules\Crm\Http\Controllers\ProposalTemplateController')->except(['show', 'edit', 'update', 'destroy']);
    Route::resource('proposals', 'Modules\Crm\Http\Controllers\ProposalController')->except(['create', 'edit', 'update', 'destroy']);
    Route::get('settings', [Modules\Crm\Http\Controllers\CrmSettingsController::class, 'index']);
    Route::post('update-settings', [Modules\Crm\Http\Controllers\CrmSettingsController::class, 'updateSettings']);
    Route::get('order-request', [Modules\Crm\Http\Controllers\OrderRequestController::class, 'listOrderRequests']);
    Route::get('b2b-marketplace', [Modules\Crm\Http\Controllers\CrmMarketplaceController::class, 'index']);
    Route::post('save-marketplace', [Modules\Crm\Http\Controllers\CrmMarketplaceController::class, 'save']);
    Route::get('import-leads', [Modules\Crm\Http\Controllers\CrmMarketplaceController::class, 'importLeads']);
});

// ADR 0179 Wave C-BE -- Cliente drawer cadastro autosave + lookups BR.
//
// Stack middleware canon UPOS Admin (copiada literal do grupo /cliente raiz
// em routes/web.php:132): 'setData','auth','SetSessionData','language',
// 'timezone','AdminSidebarMenu','CheckUserLogin'. Pre-flight LICOES F3
// T-AP-3 (middleware fantasma) -- NAO inventamos middleware 'tenant'.
//
// `setData` = popula `session('user.business_id')` que ClienteAutosaveController
// usa pra multi-tenant scope (ADR 0093 IRREVOGAVEL). `CheckUserLogin` recusa
// usuarios sem business_id valido.
//
// 5 endpoints PATCH cadastrais + 2 endpoints GET lookup (BrLookupService
// proxy ViaCEP/BrasilAPI com cache Redis -- evita rate limit federal pra
// Larissa biz=4 ~30 cadastros/dia em pico).
//
// Refs:
//   - memory/decisions/0179-cliente-drawer-760px-substitui-show-fullpage.md
//   - memory/requisitos/Crm/RUNBOOK-Cliente-drawer-760px.md §4 Wave C
//   - resources/js/Pages/Cliente/Index.charter.md v3
Route::middleware(['setData', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin'])
    ->prefix('cliente')
    ->name('cliente.')
    ->group(function () {
        // 5 endpoints autosave (Q2 inline -- debounce 800ms client-side).
        Route::patch('{id}/identificacao', [\Modules\Crm\Http\Controllers\ClienteAutosaveController::class, 'identificacao'])
            ->whereNumber('id')
            ->name('autosave.identificacao');
        Route::patch('{id}/contato', [\Modules\Crm\Http\Controllers\ClienteAutosaveController::class, 'contato'])
            ->whereNumber('id')
            ->name('autosave.contato');
        Route::patch('{id}/endereco', [\Modules\Crm\Http\Controllers\ClienteAutosaveController::class, 'endereco'])
            ->whereNumber('id')
            ->name('autosave.endereco');
        Route::patch('{id}/comercial', [\Modules\Crm\Http\Controllers\ClienteAutosaveController::class, 'comercial'])
            ->whereNumber('id')
            ->name('autosave.comercial');
        Route::patch('{id}/classificacao', [\Modules\Crm\Http\Controllers\ClienteAutosaveController::class, 'classificacao'])
            ->whereNumber('id')
            ->name('autosave.classificacao');

        // 2 endpoints lookup (cache Redis: CEP 90d, CNPJ 30d).
        // Wave 15 D8 Security -- throttle 60/min anti-abuso pro caso de
        // Auth bypassado em prod (defensivo): Larissa biz=4 ~30 cadastros/dia
        // = ~5 lookup/min em pico = cabe folgado em 60/min.
        Route::middleware('throttle:60,1')->group(function () {
            Route::get('lookup/cep/{cep}', [\Modules\Crm\Http\Controllers\ClienteLookupController::class, 'cep'])
                ->where('cep', '\d{5}-?\d{3}|\d{8}')
                ->name('lookup.cep');
            Route::get('lookup/cnpj/{cnpj}', [\Modules\Crm\Http\Controllers\ClienteLookupController::class, 'cnpj'])
                ->where('cnpj', '[\d./\-]{14,18}')
                ->name('lookup.cnpj');
        });
    });

// Wave E -- Tab IA endpoints (ADR 0179 Q4 Default ON pra todos).
//
// 3 endpoints POST LLM (resumo/segmento/proxima-acao via Modules/Crm/Ai/Agents)
// + 1 endpoint GET deterministico (score-risco, zero LLM -- 8 sinais canon
// que espelham resources/js/Pages/Cliente/_show/RiscoClienteCard.tsx).
//
// Middleware stack canon UPOS Admin (copiada LITERAL do grupo /cliente Wave C
// linha 101 acima -- NAO inventamos middleware 'tenant'). Pre-flight LICOES
// F3 T-AP-3 (middleware fantasma) -- usa setData+SetSessionData canon pra
// popular session('user.business_id') que ClienteIaController usa em todo
// query (Tier 0 ADR 0093 IRREVOGAVEL).
//
// Throttle defensivo 30/min anti-abuso custo Brain B (Wagner regride pra
// gate copiloto.admin.custos se hit rate alto). Q4 explicito: sem gate
// quota inicial -- Default ON pra todos.
//
// Refs:
//   - memory/decisions/0179-cliente-drawer-760px-substitui-show-fullpage.md §Q4
//   - Modules/Crm/Http/Controllers/ClienteIaController.php
//   - Modules/Crm/Ai/Agents/Cliente*Agent.php
Route::middleware(['setData', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin'])
    ->prefix('cliente')
    ->name('cliente.')
    ->group(function () {
        Route::middleware('throttle:30,1')->group(function () {
            Route::post('{id}/ia/resumo', [\Modules\Crm\Http\Controllers\ClienteIaController::class, 'resumo'])
                ->whereNumber('id')
                ->name('ia.resumo');
            Route::post('{id}/ia/segmento', [\Modules\Crm\Http\Controllers\ClienteIaController::class, 'segmento'])
                ->whereNumber('id')
                ->name('ia.segmento');
            Route::post('{id}/ia/proxima-acao', [\Modules\Crm\Http\Controllers\ClienteIaController::class, 'proximaAcao'])
                ->whereNumber('id')
                ->name('ia.proxima-acao');
            Route::get('{id}/ia/score-risco', [\Modules\Crm\Http\Controllers\ClienteIaController::class, 'scoreRisco'])
                ->whereNumber('id')
                ->name('ia.score-risco');
        });
    });

// Wave F -- Tab Auditoria endpoints (ADR 0179 -- LGPD Art. 18)
//
// Timeline Spatie ActivityLog v4.8 (subject_type=App\Contact + subject_id +
// scope explicito where('activity_log.business_id', $bizId) defesa Tier 0
// ADR 0093 IRREVOGAVEL) + CSV export UTF-8 BOM (Excel BR abre acentos certo).
//
// 2 endpoints:
//   GET /cliente/{id}/auditoria          -> timeline paginada JSON (20/pg, max 100)
//   GET /cliente/{id}/auditoria/export   -> download CSV streaming chunk(500)
//
// Reusa MESMO middleware stack canon UPOS Admin (copia literal do grupo Wave C
// linha 101 acima): 'setData','auth','SetSessionData','language','timezone',
// 'AdminSidebarMenu','CheckUserLogin'. `setData` popula session('user.business_id')
// que ClienteAuditoriaController usa em todo query Tier 0.
//
// Permission gate amplo (LGPD Art. 18 direito de acesso): customer.view OU
// supplier.view OU equivalente .view_own. NAO exige .update (leitura).
//
// Refs:
//   - memory/decisions/0179-cliente-drawer-760px-substitui-show-fullpage.md §Wave F
//   - resources/js/Pages/Cliente/_drawer/AuditoriaTab.tsx
//   - Modules/Crm/Http/Controllers/ClienteAuditoriaController.php
//   - prototipo-ui/prototipos/clientes/HANDOFF_CLIENTES.md §6
Route::middleware(['setData', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin'])
    ->prefix('cliente')
    ->name('cliente.')
    ->group(function () {
        Route::get('{id}/auditoria', [\Modules\Crm\Http\Controllers\ClienteAuditoriaController::class, 'index'])
            ->whereNumber('id')
            ->name('auditoria.index');
        Route::get('{id}/auditoria/export', [\Modules\Crm\Http\Controllers\ClienteAuditoriaController::class, 'export'])
            ->whereNumber('id')
            ->name('auditoria.export');
    });
