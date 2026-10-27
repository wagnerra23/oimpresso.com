<?php

// use App\Http\Controllers\BusinessController;
// use App\Http\Controllers\Modules;
// use Illuminate\Support\Facades\Route;

Route::get('/pricing', [Modules\Superadmin\Http\Controllers\PricingController::class, 'index'])->name('pricing');

// D8.b Wave 13 — throttle:superadmin (60 req/min por user/IP) protege actions sensíveis admin
// (criar business, resetar senha, destruir tenant). RateLimiter registrado em RouteServiceProvider.
Route::middleware(['web', 'auth', 'language', 'AdminSidebarMenu', 'superadmin', 'throttle:superadmin'])->prefix('superadmin')->group(function () {
    // Usuário 360° — vista única (roles, permissions, tokens, scopes, sessions, lockouts)
    Route::get('/usuarios',                 [Modules\Superadmin\Http\Controllers\Usuario360Controller::class, 'index'])->name('superadmin.usuarios.index');
    Route::get('/usuarios/{id}/360',        [Modules\Superadmin\Http\Controllers\Usuario360Controller::class, 'show'])->name('superadmin.usuarios.show');
    Route::post('/usuarios/{id}/lock',      [Modules\Superadmin\Http\Controllers\Usuario360Controller::class, 'lock'])->name('superadmin.usuarios.lock');
    Route::post('/usuarios/{id}/unlock',    [Modules\Superadmin\Http\Controllers\Usuario360Controller::class, 'unlock'])->name('superadmin.usuarios.unlock');
    Route::get('/usuarios/{id}/history',    [Modules\Superadmin\Http\Controllers\Usuario360Controller::class, 'history'])->name('superadmin.usuarios.history');

    Route::post('/install', [Modules\Superadmin\Http\Controllers\InstallController::class, 'index']);
    Route::get('/install', [Modules\Superadmin\Http\Controllers\InstallController::class, 'index']);
    Route::match(['get', 'post'], '/install/update', [Modules\Superadmin\Http\Controllers\InstallController::class, 'update']);
    Route::match(['get', 'post'], '/install/uninstall', [Modules\Superadmin\Http\Controllers\InstallController::class, 'uninstall']);

    Route::get('/', [Modules\Superadmin\Http\Controllers\SuperadminController::class, 'index']);
    Route::get('/stats', [Modules\Superadmin\Http\Controllers\SuperadminController::class, 'stats']);

    Route::get('/{business_id}/toggle-active/{is_active}', [Modules\Superadmin\Http\Controllers\BusinessController::class, 'toggleActive']);

    Route::get('/users/{business_id}', [Modules\Superadmin\Http\Controllers\BusinessController::class, 'usersList']);
    Route::post('/update-password', [Modules\Superadmin\Http\Controllers\BusinessController::class, 'updatePassword']);

    // `edit`/`update` FORA de proposito. Nao e escolha de produto — e conserto: as duas
    // rotas existiam e estavam QUEBRADAS (medido 2026-08-20, pre-flight da SA-O3 no #6011):
    //   GET  /superadmin/business/{id}/edit -> 500, `view('superadmin::edit')` nunca existiu
    //   PUT  /superadmin/business/{id}      -> 200 vazio, `update()` tinha corpo vazio
    // Andaime do gerador do UltimatePOS, exposto atras do middleware `superadmin`. Zero
    // chamadores no repo (contado, com controle positivo). Editar negocio, se um dia entrar,
    // nasce com charter e contrato — nao herdando estas.
    Route::resource('/business', Modules\Superadmin\Http\Controllers\BusinessController::class)
        ->except(['edit', 'update']);
    Route::get('/business/{id}/destroy', [Modules\Superadmin\Http\Controllers\BusinessController::class, 'destroy']);

    // `show` saiu (2026-10-27): era `view('superadmin::show')`, view que nunca existiu (500).
    Route::resource('/packages', Modules\Superadmin\Http\Controllers\PackagesController::class)->except(['show']);
    Route::get('/packages/{id}/destroy', [Modules\Superadmin\Http\Controllers\PackagesController::class, 'destroy']);

    Route::get('/settings', [Modules\Superadmin\Http\Controllers\SuperadminSettingsController::class, 'edit']);
    Route::put('/settings', [Modules\Superadmin\Http\Controllers\SuperadminSettingsController::class, 'update']);
    Route::get('/edit-subscription/{id}', [Modules\Superadmin\Http\Controllers\SuperadminSubscriptionsController::class, 'editSubscription']);
    Route::post('/update-subscription', [Modules\Superadmin\Http\Controllers\SuperadminSubscriptionsController::class, 'updateSubscription']);
    Route::resource('/superadmin-subscription', Modules\Superadmin\Http\Controllers\SuperadminSubscriptionsController::class)->except(['show']);

    Route::get('/communicator', [Modules\Superadmin\Http\Controllers\CommunicatorController::class, 'index']);
    Route::post('/communicator/send', [Modules\Superadmin\Http\Controllers\CommunicatorController::class, 'send']);
    Route::get('/communicator/get-history', [Modules\Superadmin\Http\Controllers\CommunicatorController::class, 'getHistory']);

    Route::resource('/frontend-pages', 'Modules\Superadmin\Http\Controllers\PageController');
});

Route::middleware('web', 'SetSessionData', 'auth', 'language', 'timezone', 'AdminSidebarMenu')->group(function () {
    //Routes related to paypal checkout
    Route::get('/subscription/{package_id}/paypal-express-checkout', [Modules\Superadmin\Http\Controllers\SubscriptionController::class, 'paypalExpressCheckout']);

    Route::get('/subscription/post-flutterwave-payment', [Modules\Superadmin\Http\Controllers\SubscriptionController::class, 'postFlutterwavePaymentCallback']);

    Route::post('/subscription/pay-stack', [Modules\Superadmin\Http\Controllers\SubscriptionController::class, 'getRedirectToPaystack']);
    Route::get('/subscription/post-payment-pay-stack-callback', [Modules\Superadmin\Http\Controllers\SubscriptionController::class, 'postPaymentPaystackCallback']);

    //Routes related to pesapal checkout
    Route::get('/subscription/{package_id}/pesapal-callback', [Modules\Superadmin\Http\Controllers\SubscriptionController::class, 'pesapalCallback'])->name('pesapalCallback');

    Route::get('/subscription/{package_id}/pay', [Modules\Superadmin\Http\Controllers\SubscriptionController::class, 'pay']);
    Route::any('/subscription/{package_id}/confirm', [Modules\Superadmin\Http\Controllers\SubscriptionController::class, 'confirm'])->name('subscription-confirm');

    Route::get('/subscription/{package_id}/register-pay', [Modules\Superadmin\Http\Controllers\SubscriptionController::class, 'registerPay'])->name('register-pay');

    // Só `index` (Minha assinatura, React). `show` era o modal Blade que o drawer substituiu
    // (thread 07); create/store/edit/update/destroy nunca tiveram método (500). 2026-10-27.
    Route::resource('/subscription', Modules\Superadmin\Http\Controllers\SubscriptionController::class)->only(['index']);
});

Route::get('/page/{slug}', [Modules\Superadmin\Http\Controllers\PageController::class, 'showPage'])->name('frontend-pages');
