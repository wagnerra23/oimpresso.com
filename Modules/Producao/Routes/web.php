<?php

use Modules\Producao\Http\Controllers\ColaboradorCustoController;
use Modules\Producao\Http\Controllers\EtiquetaOsController;
use Modules\Producao\Http\Controllers\FerramentasController;
use Modules\Producao\Http\Controllers\InstallController;
use Modules\Producao\Http\Controllers\PostosController;
use Modules\Producao\Http\Controllers\RoteirosController;

/*
 * Esqueleto (ADR 0431): só as rotas Install 1-clique (ADR 0024 /
 * BaseModuleInstallController). Sem elas o helper action() em
 * Install/ModulesController vira '#' e o botão "Install" de /manage-modules não
 * faz nada. Rotas de negócio entram nas threads seguintes do playbook.
 */
Route::middleware(['web', 'authh', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu'])
    ->prefix('producao')
    ->group(function () {
        Route::post('install',           [InstallController::class, 'install']);
        Route::get('install',           [InstallController::class, 'index']);
        Route::match(['get', 'post'], 'install/uninstall', [InstallController::class, 'uninstall']);
        Route::match(['get', 'post'], 'install/update',    [InstallController::class, 'update']);
    });


// ── Thread 03 + E03 · cadastros (postos, motivos, roteiros, ferramentas, custo-hora) ──
// Bloco próprio: as threads seguintes acrescentam o delas embaixo, sem mexer neste.
Route::middleware(['web', 'authh', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin'])
    ->prefix('producao')
    ->name('producao.')
    ->group(function () {
        Route::get('roteiros', [RoteirosController::class, 'index'])->name('roteiros.index');
        Route::post('roteiros', [RoteirosController::class, 'store'])->name('roteiros.store');
        Route::put('roteiros/{id}', [RoteirosController::class, 'update'])->whereNumber('id')->name('roteiros.update');

        Route::get('postos', [PostosController::class, 'index'])->name('postos.index');
        Route::post('postos', [PostosController::class, 'store'])->name('postos.store');
        Route::put('postos/{id}', [PostosController::class, 'update'])->whereNumber('id')->name('postos.update');
        Route::put('postos/{id}/motivos', [PostosController::class, 'motivos'])->whereNumber('id')->name('postos.motivos');

        Route::post('ferramentas', [FerramentasController::class, 'store'])->name('ferramentas.store');
        Route::put('ferramentas/{id}', [FerramentasController::class, 'update'])->whereNumber('id')->name('ferramentas.update');

        Route::put('colaboradores/{userId}/custo-hora', [ColaboradorCustoController::class, 'update'])->whereNumber('userId')->name('colaboradores.custo');
    });

// ── PR 07 propostas-app · E05 modelo a4_os · PDF da OS com o QR da etiqueta ──
Route::middleware(['web', 'authh', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin'])
    ->prefix('producao')
    ->name('producao.')
    ->group(function () {
        Route::get('os/{id}/etiqueta', [EtiquetaOsController::class, 'show'])->whereNumber('id')->name('os.etiqueta');
    });
