<?php

use Illuminate\Support\Facades\Route;
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
    });
