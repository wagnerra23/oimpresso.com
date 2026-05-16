<?php

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

// Wave R — throttle anti-scraping em rotas públicas QR catalogue (30 req/min por IP)
Route::middleware('throttle:30,1')->group(function () {
    Route::get('/catalogue/{business_id}/{location_id}', [\Modules\ProductCatalogue\Http\Controllers\ProductCatalogueController::class, 'index']);
    Route::get('/show-catalogue/{business_id}/{product_id}', [\Modules\ProductCatalogue\Http\Controllers\ProductCatalogueController::class, 'show']);
});

// Wave 14 D8 Security — throttle:60,1 (60 req/min/IP) em rotas admin do catalogo.
// Rotas autenticadas (auth web) — throttle limita abuso por usuario logado
// (ex: brute-force install, scraping de QR generation, varredura de configs).
// Public scraping ja coberto por throttle:30,1 acima (rotas /catalogue/* sem auth).
Route::middleware('throttle:60,1', 'web', 'authh', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu')->prefix('product-catalogue')->group(function () {
    Route::get('catalogue-qr', [\Modules\ProductCatalogue\Http\Controllers\ProductCatalogueController::class, 'generateQr']);

    Route::get('install', [\Modules\ProductCatalogue\Http\Controllers\InstallController::class, 'index']);
    Route::post('install', [\Modules\ProductCatalogue\Http\Controllers\InstallController::class, 'install']);
    Route::get('install/uninstall', [\Modules\ProductCatalogue\Http\Controllers\InstallController::class, 'uninstall']);
    Route::get('install/update', [\Modules\ProductCatalogue\Http\Controllers\InstallController::class, 'update']);
});
