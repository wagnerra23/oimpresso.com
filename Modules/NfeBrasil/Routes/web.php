<?php

use Illuminate\Support\Facades\Route;
use Modules\NfeBrasil\Http\Controllers\CertificadoController;
use Modules\NfeBrasil\Http\Controllers\ConfigDefaultController;
use Modules\NfeBrasil\Http\Controllers\InstallController;
use Modules\NfeBrasil\Http\Controllers\NfeBrasilController;
use Modules\NfeBrasil\Http\Controllers\TributacaoController;

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
// Stack canônica oimpresso: web + auth + language + timezone + AdminSidebarMenu
// (mesmo de Modules/Financeiro/Routes/web.php).
Route::middleware(['web', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu'])
    ->prefix('nfe-brasil/configuracao')
    ->group(function () {
        Route::get('certificado', [CertificadoController::class, 'status'])
            ->name('nfe-brasil.certificado.status');
        Route::post('certificado', [CertificadoController::class, 'upload'])
            ->name('nfe-brasil.certificado.upload');
    });

// US-NFE-010 fase 2 — UI tributação (regras NCM + config default).
// Permissão `nfe.tributacao.manage` validada no FormRequest.
Route::middleware(['web', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu'])
    ->prefix('nfe-brasil/tributacao')
    ->name('nfe-brasil.tributacao.')
    ->group(function () {
        Route::get('/', [TributacaoController::class, 'index'])->name('index');

        // Config default (Nível 4 cascade)
        Route::get('config-default', [ConfigDefaultController::class, 'show'])->name('config.show');
        Route::post('config-default', [ConfigDefaultController::class, 'upsert'])->name('config.upsert');

        // CRUD regras
        Route::get('regras/create', [TributacaoController::class, 'create'])->name('regras.create');
        Route::post('regras', [TributacaoController::class, 'store'])->name('regras.store');
        Route::get('regras/{id}/edit', [TributacaoController::class, 'edit'])
            ->whereNumber('id')->name('regras.edit');
        Route::put('regras/{id}', [TributacaoController::class, 'update'])
            ->whereNumber('id')->name('regras.update');
        Route::delete('regras/{id}', [TributacaoController::class, 'destroy'])
            ->whereNumber('id')->name('regras.destroy');
    });
