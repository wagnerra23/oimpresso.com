<?php

use Illuminate\Support\Facades\Route;
use Modules\NfeBrasil\Http\Controllers\CertificadoController;
use Modules\NfeBrasil\Http\Controllers\ConfigDefaultController;
use Modules\NfeBrasil\Http\Controllers\ImportRegrasController;
use Modules\NfeBrasil\Http\Controllers\InstallController;
use Modules\NfeBrasil\Http\Controllers\NfeBrasilController;
use Modules\NfeBrasil\Http\Controllers\NfeStatusController;
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
        Route::post('certificado/testar', [CertificadoController::class, 'testar'])
            ->name('nfe-brasil.certificado.testar');
        Route::post('certificado/ambiente', [CertificadoController::class, 'updateAmbiente'])
            ->name('nfe-brasil.certificado.ambiente');
    });

// US-NFE-010 fase 2 — UI tributação (regras NCM + config default).
// Permissão `nfe.tributacao.manage` validada no FormRequest.
Route::middleware(['web', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu'])
    ->prefix('nfe-brasil/tributacao')
    ->name('nfe-brasil.tributacao.')
    ->group(function () {
        Route::get('/', [TributacaoController::class, 'index'])->name('index');

        // Per-business auto-emission gate (ADR 0093 multi-tenant Tier 0).
        Route::post('auto-emission/toggle', [TributacaoController::class, 'toggleAutoEmission'])
            ->name('auto-emission.toggle');

        // Config default (Nível 4 cascade)
        Route::get('config-default', [ConfigDefaultController::class, 'show'])->name('config.show');
        Route::post('config-default', [ConfigDefaultController::class, 'upsert'])->name('config.upsert');

        // Templates tributários L1 (US-NFE-TPL-001)
        Route::post('templates/{slug}/aplicar', [TributacaoController::class, 'aplicarTemplate'])
            ->where('slug', '[a-z0-9\-]+')
            ->name('templates.aplicar');

        // CRUD regras
        Route::get('regras/create', [TributacaoController::class, 'create'])->name('regras.create');
        Route::post('regras', [TributacaoController::class, 'store'])->name('regras.store');
        Route::get('regras/{id}/edit', [TributacaoController::class, 'edit'])
            ->whereNumber('id')->name('regras.edit');
        Route::put('regras/{id}', [TributacaoController::class, 'update'])
            ->whereNumber('id')->name('regras.update');
        Route::delete('regras/{id}', [TributacaoController::class, 'destroy'])
            ->whereNumber('id')->name('regras.destroy');

        // Import CSV em massa (US-NFE-010 fase 3)
        Route::get('import', [ImportRegrasController::class, 'show'])->name('import.show');
        Route::post('import/preview', [ImportRegrasController::class, 'preview'])->name('import.preview');
        Route::post('import/aplicar', [ImportRegrasController::class, 'aplicar'])->name('import.aplicar');
    });

// US-NFE-002 fase 2C — endpoint JSON polling-friendly pra status NFC-e pós-venda.
// UI cockpit POS chama a cada 2s após dispatch do Job, até receber status terminal
// (autorizada/rejeitada/denegada). Não exige broadcast daemon — funciona em runtime
// Hostinger sem violar ADR 0058/0062.
Route::middleware(['web', 'auth', 'SetSessionData'])
    ->prefix('nfe-brasil/api')
    ->name('nfe-brasil.api.')
    ->group(function () {
        Route::get('transactions/{tx}/nfe-status', [NfeStatusController::class, 'show'])
            ->whereNumber('tx')
            ->name('transactions.nfe-status');
    });

// Page Inertia demo da fase 2C — badge reativo via useNfceStatus.
// Acesso: /nfe-brasil/transactions/{tx}/status. Usuário consulta status de
// uma venda já finalizada; serve também de dogfooding antes integração POS.
Route::middleware(['web', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu'])
    ->prefix('nfe-brasil/transactions')
    ->name('nfe-brasil.transactions.')
    ->group(function () {
        Route::get('{tx}/status', [NfeStatusController::class, 'showPage'])
            ->whereNumber('tx')
            ->name('status');
    });
