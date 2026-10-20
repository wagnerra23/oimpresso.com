<?php

use Modules\Producao\Http\Controllers\ApontamentoController;
use Modules\Producao\Http\Controllers\AprovacaoArteController;
use Modules\Producao\Http\Controllers\ColaboradorCustoController;
use Modules\Producao\Http\Controllers\EtiquetaOsController;
use Modules\Producao\Http\Controllers\FerramentasController;
use Modules\Producao\Http\Controllers\InstallController;
use Modules\Producao\Http\Controllers\OrdemCustoController;
use Modules\Producao\Http\Controllers\PostosController;
use Modules\Producao\Http\Controllers\RomaneioController;
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
        // US-PCP-021 · UC-FLX-37 — material consumido na etapa (baixa pelo lote que saiu).
        Route::post('material', [ApontamentoController::class, 'material'])->name('material');
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
        Route::put('roteiros/{id}/produtos', [RoteirosController::class, 'produtos'])->whereNumber('id')->name('roteiros.produtos');

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
        // US-PCP-022 · UC-FLX-36 — custo da abertura × hoje da OS por item em andamento (JSON, gestor).
        Route::get('ordens/{id}/custo', [OrdemCustoController::class, 'show'])->whereNumber('id')->name('ordens.custo');
        // US-COMVIS-NEW-004 · UC-FLX-02 — gera o link da prova para o cliente aprovar (JSON).
        Route::post('ordens/{id}/aprovacao-arte', [AprovacaoArteController::class, 'solicitar'])->whereNumber('id')->name('ordens.aprovacao-arte');
    });

// ── UC-FLX-02 · US-COMVIS-NEW-004 · página pública da prova (sem login) ──
// A empresa vem do token (só o sha256 fica no banco), nunca da URL. Throttle 30/min/IP contra
// varredura de token; o `web` do RouteServiceProvider dá sessão + CSRF ao formulário.
Route::middleware(['throttle:30,1'])
    ->prefix('arte')
    ->name('producao.arte.')
    ->group(function () {
        Route::get('{token}', [AprovacaoArteController::class, 'mostrar'])->where('token', '[A-Za-z0-9]{48}')->name('mostrar');
        Route::post('{token}/aprovar', [AprovacaoArteController::class, 'aprovar'])->where('token', '[A-Za-z0-9]{48}')->name('aprovar');
        Route::post('{token}/recusar', [AprovacaoArteController::class, 'recusar'])->where('token', '[A-Za-z0-9]{48}')->name('recusar');
    });

// ── US-PCP-023 · UC-FLX-12 · romaneio de impressão peça a peça (JSON; a tela é follow-up) ──
Route::middleware(['web', 'authh', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin'])
    ->prefix('producao')
    ->name('producao.')
    ->group(function () {
        Route::post('os/{id}/romaneio', [RomaneioController::class, 'store'])->whereNumber('id')->name('os.romaneio.store');
        Route::get('romaneios/{id}', [RomaneioController::class, 'show'])->whereNumber('id')->name('romaneios.show');
    });
