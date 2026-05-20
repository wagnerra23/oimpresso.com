<?php

use Illuminate\Support\Facades\Route;
use Modules\Fiscal\Http\Controllers\InstallController;
use Modules\Fiscal\Http\Controllers\NfeCockpitController;

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
        // Cockpit NF-e · NFC-e (sub-página 2 do design — PR #1).
        // Próximos PRs: /fiscal (cockpit), /fiscal/nfse, /fiscal/dfe, /fiscal/eventos,
        // /fiscal/config, /fiscal/sped.
        Route::get('/nfe', [NfeCockpitController::class, 'index'])->name('nfe.index');
    });
