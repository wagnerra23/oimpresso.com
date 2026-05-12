<?php

use Illuminate\Support\Facades\Route;
use Modules\NfeBrasil\Http\Controllers\CertificadoController;
use Modules\NfeBrasil\Http\Controllers\ConfigDefaultController;
use Modules\NfeBrasil\Http\Controllers\ImportRegrasController;
use Modules\NfeBrasil\Http\Controllers\InstallController;
use Modules\NfeBrasil\Http\Controllers\NfeBrasilController;
use Modules\NfeBrasil\Http\Controllers\NfeInutilizacaoController;
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
//
// US-NFE-MANUAL — endpoints emissão manual (POST emitir + reenviar email + GET DANFE PDF).
// Reusa NfeService.emitirParaTransaction com modelo configurável (55 NFe / 65 NFC-e).
Route::middleware(['web', 'auth', 'SetSessionData'])
    ->prefix('nfe-brasil/api')
    ->name('nfe-brasil.api.')
    ->group(function () {
        Route::get('transactions/{tx}/nfe-status', [NfeStatusController::class, 'show'])
            ->whereNumber('tx')
            ->name('transactions.nfe-status');
        // Lista todas emissões da TX (NFC-e 65 + NFe 55) — alimenta SaleSheet section Fiscal.
        Route::get('transactions/{tx}/emissoes', [\Modules\NfeBrasil\Http\Controllers\NfeEmissaoController::class, 'listar'])
            ->whereNumber('tx')
            ->name('transactions.emissoes');
    });

// US-NFE-MANUAL — POST endpoints de ação fiscal + GET DANFE PDF.
// Web stack normal pra session+CSRF; mesmo middleware do API acima.
Route::middleware(['web', 'auth', 'SetSessionData'])
    ->prefix('nfe-brasil')
    ->name('nfe-brasil.')
    ->group(function () {
        Route::post('transactions/{tx}/emitir', [\Modules\NfeBrasil\Http\Controllers\NfeEmissaoController::class, 'emitir'])
            ->whereNumber('tx')
            ->name('transactions.emitir');
        Route::post('emissoes/{id}/reenviar-email', [\Modules\NfeBrasil\Http\Controllers\NfeEmissaoController::class, 'reenviarEmail'])
            ->whereNumber('id')
            ->name('emissoes.reenviar-email');
        Route::get('emissoes/{id}/danfe-pdf', [\Modules\NfeBrasil\Http\Controllers\NfeEmissaoController::class, 'danfePdf'])
            ->whereNumber('id')
            ->name('emissoes.danfe-pdf');
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

// US-SELL-030 — Inutilização SEFAZ de faixa de números fiscais NFe.
// Permissão: `fiscal.inutilizar` (role per-business, seeder NfeFiscalActionsSeeder).
// Refs: SPEC.md US-SELL-030 + CONFAZ Ajuste SINIEF 07/2005 Art. 14
Route::middleware(['web', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu'])
    ->prefix('nfe-brasil/inutilizacoes')
    ->name('nfe-brasil.inutilizacoes.')
    ->group(function () {
        Route::get('/', [NfeInutilizacaoController::class, 'index'])->name('index');
        Route::post('/', [NfeInutilizacaoController::class, 'store'])->name('store');
    });

// US-NFE-052 (ADR 0116 caso Gold) — UI Manifestação do Destinatário.
// Permissão `nfe.manifestacao.view` (index + JSON) + `nfe.manifestacao.manage` (mutations).
Route::middleware(['web', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu'])
    ->prefix('nfe-brasil/manifestacao')
    ->name('nfe-brasil.manifestacao.')
    ->group(function () {
        Route::get('/', [\Modules\NfeBrasil\Http\Controllers\ManifestacaoController::class, 'index'])
            ->name('index');

        // Eventos individuais
        Route::post('{id}/cienciar', [\Modules\NfeBrasil\Http\Controllers\ManifestacaoController::class, 'cienciar'])
            ->whereNumber('id')->name('cienciar');
        Route::post('{id}/confirmar', [\Modules\NfeBrasil\Http\Controllers\ManifestacaoController::class, 'confirmar'])
            ->whereNumber('id')->name('confirmar');
        Route::post('{id}/desconhecer', [\Modules\NfeBrasil\Http\Controllers\ManifestacaoController::class, 'desconhecer'])
            ->whereNumber('id')->name('desconhecer');
        Route::post('{id}/nao-realizada', [\Modules\NfeBrasil\Http\Controllers\ManifestacaoController::class, 'naoRealizada'])
            ->whereNumber('id')->name('nao-realizada');

        // Bulk + sync
        Route::post('bulk/confirmar', [\Modules\NfeBrasil\Http\Controllers\ManifestacaoController::class, 'bulkConfirmar'])
            ->name('bulk.confirmar');
        Route::post('sync-now', [\Modules\NfeBrasil\Http\Controllers\ManifestacaoController::class, 'syncNow'])
            ->name('sync-now');

        // JSON pra LinkedApps fetch lazy
        Route::get('{id}/itens', [\Modules\NfeBrasil\Http\Controllers\ManifestacaoController::class, 'listarItens'])
            ->whereNumber('id')->name('itens');
        Route::get('{id}/eventos', [\Modules\NfeBrasil\Http\Controllers\ManifestacaoController::class, 'listarEventos'])
            ->whereNumber('id')->name('eventos');
    });
