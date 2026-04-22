<?php

use Modules\Officeimpresso\Http\Controllers\ClientController;
use Modules\Officeimpresso\Http\Controllers\InstallController;
use Modules\Officeimpresso\Http\Controllers\LicencaComputadorController;
use Modules\Officeimpresso\Http\Controllers\LicencaLogController;
use Modules\Officeimpresso\Http\Controllers\OfficeimpressoController;

// Rotas principais — requer autenticação
Route::middleware(['web', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu'])
    ->prefix('officeimpresso')
    ->group(function () {

        Route::resource('apiclient', ClientController::class);
        Route::get('/apiclient/regenerate', [ClientController::class, 'regenerate']);

        Route::resource('licenca_computador', LicencaComputadorController::class);
        Route::get('businessall', [LicencaComputadorController::class, 'businessall']);
        Route::get('computadores', [LicencaComputadorController::class, 'computadores']);
        Route::get('/licenca_computador/{id}/toggle-block', [LicencaComputadorController::class, 'toggleBlock'])->name('licenca_computador.toggleBlock');
        Route::post('/licenca_computador/businessupdate/{id}', [LicencaComputadorController::class, 'businessupdate'])->name('business.update');
        Route::get('/licenca_computador/businessbloqueado/{id}', [LicencaComputadorController::class, 'businessbloqueado'])->name('business.bloqueado');
        Route::get('/licenca_computado/licencas/{id}', [LicencaComputadorController::class, 'viewLicencas'])->name('empresa.licencas');

        Route::resource('licenca_log', LicencaLogController::class);

        Route::get('/docs', function () {
            return view('superadmin.iframe', ['url' => 'https://docs.officeimpresso.com.br']);
        })->name('superadmin.docs');

        Route::get('catalogue-qr', [OfficeimpressoController::class, 'generateQr'])->name('officeimpresso.generateQr');
    });

// Rotas de instalação — authh + CheckUserLogin
Route::middleware(['web', 'authh', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin'])
    ->prefix('officeimpresso')
    ->group(function () {
        Route::get('install', [InstallController::class, 'index']);
        Route::post('install', [InstallController::class, 'install']);
        Route::get('install/uninstall', [InstallController::class, 'uninstall']);
        Route::get('install/update', [InstallController::class, 'update']);
    });
