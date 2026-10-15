<?php

use Modules\Producao\Http\Controllers\ApontamentoController;
use Modules\Producao\Http\Controllers\InstallController;

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

/*
 * Thread 04 + E04 — celular do operador (PWA). Bloco próprio: a thread 03 (cadastros) acrescenta o
 * dela em outro bloco. As escritas são JSON e aceitam a mesma `chave` reenviada pela fila offline.
 */
Route::middleware(['web', 'authh', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu'])
    ->prefix('producao/apontamento')
    ->name('producao.apontamento.')
    ->group(function () {
        Route::get('/', [ApontamentoController::class, 'index'])->name('index');
        Route::post('qr', [ApontamentoController::class, 'qr'])->name('qr');
        Route::post('iniciar', [ApontamentoController::class, 'iniciar'])->name('iniciar');
        Route::post('{inicio}/pausar', [ApontamentoController::class, 'pausar'])->whereNumber('inicio')->name('pausar');
        Route::post('{inicio}/concluir', [ApontamentoController::class, 'concluir'])->whereNumber('inicio')->name('concluir');
        Route::post('{inicio}/corrigir', [ApontamentoController::class, 'corrigir'])->whereNumber('inicio')->name('corrigir');
    });
