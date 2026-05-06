<?php

use Illuminate\Support\Facades\Route;
use Modules\NfeBrasil\Http\Controllers\CertificadoController;
use Modules\NfeBrasil\Http\Controllers\ConfiguracaoController;
use Modules\NfeBrasil\Http\Controllers\InstallController;
use Modules\NfeBrasil\Http\Controllers\NfeBrasilController;

/*
|--------------------------------------------------------------------------
| Web Routes — NfeBrasil
|--------------------------------------------------------------------------
*/

// Install routes (acessadas via /manage-modules link "Install").
// Pattern reutilizado de Modules/Connector/Routes/web.php.
Route::middleware(['web', 'authh', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu'])
    ->prefix('nfebrasil')
    ->group(function () {
        Route::get('install', [InstallController::class, 'index']);
        Route::get('install/uninstall', [InstallController::class, 'uninstall']);
        Route::get('install/update', [InstallController::class, 'update']);
    });

// Rotas operacionais (placeholder — a expandir nas próximas sub-ondas)
Route::group([], function () {
    Route::resource('nfebrasil', NfeBrasilController::class)->names('nfebrasil');
});

// US-NFE-041 — JSON API do certificado A1 (para uso programático)
// Permissão validada no FormRequest (nfebrasil.settings.manage | superadmin).
Route::middleware(['web', 'auth', 'SetSessionData'])
    ->prefix('nfe-brasil/configuracao')
    ->group(function () {
        Route::get('certificado', [CertificadoController::class, 'status'])
            ->name('nfe-brasil.certificado.status');
        Route::post('certificado', [CertificadoController::class, 'upload'])
            ->name('nfe-brasil.certificado.upload');
    });

// US-NFE-041 fase 2 — página Inertia de configuração do certificado A1.
Route::middleware(['web', 'authh', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin'])
    ->group(function () {
        Route::get('nfe-brasil/configuracao', [ConfiguracaoController::class, 'index'])
            ->name('nfe-brasil.configuracao.index');
        Route::post('nfe-brasil/configuracao', [ConfiguracaoController::class, 'store'])
            ->name('nfe-brasil.configuracao.certificado.store');
    });
