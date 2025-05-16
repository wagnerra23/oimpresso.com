<?php

// Grupo de rotas com middlewares comuns
Route::middleware('web', 'authh', 'SetSessionData', 'auth', 'language', 'timezone', 'AdminSidebarMenu')->prefix('officeimpresso')->group(function () {

    // Rota para a API
    Route::get('/api', [Modules\Officeimpresso\Http\Controllers\OfficeimpressoController::class, 'index']);

    // Rotas para ClientController
    Route::resource('client', [Modules\Officeimpresso\Http\Controllers\ClientController::class]);
    Route::get('/regenerate', [ClientController::class, 'regenerate'])->name('client.regenerate');

    // Rotas para LicencaComputadorController
    Route::resource('licenca_computador', Modules\Officeimpresso\Http\Controllers\LicencaComputadorController::class);
    Route::get('businessall', [Modules\Officeimpresso\Http\Controllers\LicencaComputadorController::class, 'businessall'])->name('licenca_computador.businessall');
    Route::get('computadores', [Modules\Officeimpresso\Http\Controllers\LicencaComputadorController::class, 'computadores'])->name('licenca_computador.computadores');
    Route::get('/licenca_computador/{id}/toggle-block', [Modules\Officeimpresso\Http\Controllers\LicencaComputadorController::class, 'toggleBlock'])->name('licenca_computador.toggleBlock');
    Route::post('/licenca_computador/businessupdate/{id}', [Modules\Officeimpresso\Http\Controllers\LicencaComputadorController::class, 'businessupdate'])->name('licenca_computador.businessupdate');
    Route::get('/licenca_computador/businessbloqueado/{id}', [Modules\Officeimpresso\Http\Controllers\LicencaComputadorController::class, 'businessbloqueado'])->name('licenca_computador.businessbloqueado');
    Route::get('/licenca_computador/licencas/{id}', [Modules\Officeimpresso\Http\Controllers\LicencaComputadorController::class, 'viewLicencas'])->name('licenca_computador.viewLicencas');

    // Rotas para LicencaLogController
    Route::resource('licenca_log', [Modules\Officeimpresso\Http\Controllers\LicencaLogController::class]);

    // Rota para a documentação (iframe)
    Route::get('/docs', function () {
        return view('superadmin.iframe', ['url' => 'https://docs.officeimpresso.com.br']);
    })->name('superadmin.docs');

    Route::get('/install', [Modules\Officeimpresso\Http\Controllers\InstallController::class, 'index']);
    Route::post('/install', [Modules\Officeimpresso\Http\Controllers\InstallController::class, 'install']);
    Route::get('/install/uninstall', [Modules\Officeimpresso\Http\Controllers\InstallController::class, 'uninstall']);
    Route::get('/install/update', [Modules\Officeimpresso\Http\Controllers\InstallController::class, 'update']);

});


