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



use Modules\Officeimpresso\Http\Controllers\OfficeimpressoController;
use Modules\Officeimpresso\Http\Controllers\InstallController;
use Modules\Officeimpresso\Http\Controllers\ClientController;
use Modules\Officeimpresso\Http\Controllers\LicencaComputadorController;
use Modules\Officeimpresso\Http\Controllers\LicencaLogController;

Route::middleware(['web', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu'])->prefix('officeimpresso')->group(function () {

    // Catalogue QR (legacy ProductCatalogue feature)
    Route::get('catalogue-qr', [OfficeimpressoController::class, 'generateQr'])->name('officeimpresso.catalogue-qr');
    Route::get('/catalogue/{business_id}/{location_id}', [OfficeimpressoController::class, 'index'])->name('officeimpresso.catalogue');
    Route::get('/show-catalogue/{business_id}/{product_id}', [OfficeimpressoController::class, 'show'])->name('officeimpresso.show-catalogue');

    // OAuth clients management
    Route::resource('client', ClientController::class);
    Route::get('/regenerate', [ClientController::class, 'regenerate'])->name('client.regenerate');

    // Licenca de computador (desktop license) — CRUD + extras (nomes sem prefix
    // porque as views do 3.7 chamam route('business.bloqueado') etc. diretamente)
    Route::resource('licenca_computador', LicencaComputadorController::class);
    Route::get('businessall', [LicencaComputadorController::class, 'businessall'])->name('licenca_computador.businessall');
    Route::get('computadores', [LicencaComputadorController::class, 'computadores'])->name('computadores');
    Route::get('/licenca_computador/{id}/toggle-block', [LicencaComputadorController::class, 'toggleBlock'])->name('licenca_computador.toggleBlock');
    Route::post('/licenca_computador/businessupdate/{id}', [LicencaComputadorController::class, 'businessupdate'])->name('business.update');
    Route::get('/licenca_computador/businessbloqueado/{id}', [LicencaComputadorController::class, 'businessbloqueado'])->name('business.bloqueado');
    Route::get('/licenca_computado/licencas/{id}', [LicencaComputadorController::class, 'viewLicencas'])->name('empresa.licencas');

    // Logs de licenca
    Route::resource('licenca_log', LicencaLogController::class);

    // Documentacao (iframe superadmin)
    Route::get('/docs', function () {
        return view('superadmin.iframe', ['url' => 'https://docs.officeimpresso.com.br']);
    })->name('superadmin.docs');

    // Install hooks
    Route::get('install', [InstallController::class, 'index'])->name('officeimpresso.install');
    Route::post('install', [InstallController::class, 'install'])->name('officeimpresso.install.post');
    Route::get('install/uninstall', [InstallController::class, 'uninstall'])->name('officeimpresso.install.uninstall');
    Route::get('install/update', [InstallController::class, 'update'])->name('officeimpresso.install.update');
});
