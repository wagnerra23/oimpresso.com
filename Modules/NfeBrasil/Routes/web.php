<?php

use Illuminate\Support\Facades\Route;
use Modules\NfeBrasil\Http\Controllers\CertificadoController;
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

// US-NFE-041 — gerenciamento do certificado A1 (upload + status)
// Permissão `nfe.configuracao.manage` validada no FormRequest.
Route::middleware(['web', 'auth', 'SetSessionData'])
    ->prefix('nfe-brasil/configuracao')
    ->group(function () {
        Route::get('certificado', [CertificadoController::class, 'status'])
            ->name('nfe-brasil.certificado.status');
        Route::post('certificado', [CertificadoController::class, 'upload'])
            ->name('nfe-brasil.certificado.upload');
    });
