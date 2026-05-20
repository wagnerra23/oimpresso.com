<?php

use Illuminate\Support\Facades\Route;
use Modules\Fiscal\Http\Controllers\AcoesController;
use Modules\Fiscal\Http\Controllers\CockpitController;
use Modules\Fiscal\Http\Controllers\ConfigController;
use Modules\Fiscal\Http\Controllers\DfeController;
use Modules\Fiscal\Http\Controllers\EventosController;
use Modules\Fiscal\Http\Controllers\InstallController;
use Modules\Fiscal\Http\Controllers\NfeCockpitController;
use Modules\Fiscal\Http\Controllers\NfseCockpitController;
use Modules\Fiscal\Http\Controllers\SpedController;

/*
|--------------------------------------------------------------------------
| Web Routes — Fiscal (cockpit unificado)
|--------------------------------------------------------------------------
| Padrão UltimatePOS (alinhado com Modules/Financeiro/Routes/web.php).
| Cockpit thin agregador: lê NfeBrasil + NFSe — não duplica backend.
*/

// Install routes (acessadas via /manage-modules link "Install").
// Pattern reutilizado de Modules/Financeiro/Routes/web.php.
Route::middleware(['web', 'authh', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu'])
    ->prefix('fiscal')
    ->group(function () {
        Route::get('install', [InstallController::class, 'index']);
        Route::get('install/uninstall', [InstallController::class, 'uninstall']);
        Route::get('install/update', [InstallController::class, 'update']);
    });

// Rotas operacionais do módulo Fiscal — cockpit + sub-páginas (PR #1: só NF-e).
Route::middleware(['web', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu'])
    ->prefix('fiscal')
    ->name('fiscal.')
    ->group(function () {
        // Cockpit raiz (sub-página 1 do design — PR #2 Wave consolidada).
        Route::get('/', [CockpitController::class, 'index'])->name('cockpit');

        // Cockpit NF-e · NFC-e (sub-página 2 do design — PR #1).
        Route::get('/nfe', [NfeCockpitController::class, 'index'])->name('nfe.index');

        // Cockpit NFS-e (sub-página 3 do design — PR #2 Wave consolidada).
        Route::get('/nfse', [NfseCockpitController::class, 'index'])->name('nfse.index');

        // Eventos timeline (sub-página 5 do design — PR #2 Wave consolidada).
        // CC-e + Cancelamento + EPEC + Manifestação destinatário.
        Route::get('/eventos', [EventosController::class, 'index'])->name('eventos.index');

        // Manifesto DF-e (sub-página 4 — PR #3 Wave final).
        Route::get('/dfe', [DfeController::class, 'index'])->name('dfe.index');

        // Cert/Cfg fiscal (sub-página 6 — PR #3 Wave final).
        Route::get('/config', [ConfigController::class, 'index'])->name('config.index');

        // SPED & Livros (sub-página 7 — PR #3 Wave final, placeholder).
        Route::get('/sped', [SpedController::class, 'index'])->name('sped.index');

        // ─── PR #8 Wave: SPED Fiscal EFD-ICMS/IPI ────────────────────────
        // Download TXT layout CONFAZ v3.1.1 (perfil A) por ano+mês.
        // Throttle 3/min (gera arquivo pesado; evita abuso).
        Route::get('/sped/icms-ipi/{ano}/{mes}', [SpedController::class, 'gerar'])
            ->whereNumber('ano')
            ->whereNumber('mes')
            ->middleware('throttle:3,1')
            ->name('sped.icms-ipi');

        // ─── PR #4 Wave Ações Mutação ──────────────────────────────────
        // Cancelar NFe/NFC-e (delega NfeService::cancelar — FSM cascade ADR 0143).
        // Throttle 30/min anti-DOS (pattern Modules/NfeBrasil — protege SEFAZ).
        Route::post('/acoes/nfe/{emissao}/cancelar', [AcoesController::class, 'cancelarNfe'])
            ->whereNumber('emissao')
            ->middleware('throttle:30,1')
            ->name('acoes.nfe.cancelar');

        // Manifestar DF-e (cienciar/confirmar/desconhecer/nao_realizada).
        // Delega ManifestacaoService Modules/NfeBrasil.
        Route::post('/acoes/dfe/{recebido}/{acao}', [AcoesController::class, 'manifestarDfe'])
            ->whereNumber('recebido')
            ->where('acao', 'cienciar|confirmar|desconhecer|nao_realizada')
            ->middleware('throttle:30,1')
            ->name('acoes.dfe.manifestar');
    });
