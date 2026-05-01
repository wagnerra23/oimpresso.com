<?php

use Illuminate\Support\Facades\Route;
use Modules\NFSe\Http\Controllers\InstallController;
use Modules\NFSe\Http\Controllers\NfseController;

/*
|--------------------------------------------------------------------------
| Web Routes — NFSe
|--------------------------------------------------------------------------
| Stack middlewares UltimatePOS (CLAUDE.md §5)
*/

// Rotas de instalação (acessadas via /manage-modules → Install).
// Pattern: memory/claude/feedback_pattern_install_modulos.md
Route::middleware(['web', 'authh', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu'])
    ->prefix('nfse')
    ->group(function () {
        Route::get('install', [InstallController::class, 'index']);
        Route::get('install/uninstall', [InstallController::class, 'uninstall']);
        Route::get('install/update', [InstallController::class, 'update']);
    });

// Rotas operacionais
Route::middleware(['web', 'SetSessionData', 'auth', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin'])
    ->prefix('nfse')
    ->name('nfse.')
    ->group(function () {
        // US-NFSE-008: listagem de NFSes emitidas
        Route::get('/', [NfseController::class, 'index'])->name('index');

        // US-NFSE-009: formulário de emissão
        Route::get('/emitir', [NfseController::class, 'create'])->name('create');
        Route::post('/emitir', [NfseController::class, 'store'])->name('store');

        // US-NFSE-006: detalhe + cancelar + PDF
        Route::get('/{nfse}', [NfseController::class, 'show'])->name('show');
        Route::post('/{nfse}/cancelar', [NfseController::class, 'cancelar'])->name('cancelar');
        Route::get('/{nfse}/pdf', [NfseController::class, 'pdf'])->name('pdf');
    });
