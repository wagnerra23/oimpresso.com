<?php

use Illuminate\Support\Facades\Route;
use Modules\Vestuario\Http\Controllers\CurvaCompraController;
use Modules\Vestuario\Http\Controllers\EtiquetaTagController;
use Modules\Vestuario\Http\Controllers\InstallController;
use Modules\Vestuario\Http\Controllers\RemarcacaoController;

// ─────────────────────────────────────────────────────────────────────────────
// Rotas Install 1-click (ADR 0024 / BaseModuleInstallController).
//
// Sem essas 3 rotas, action() helper em Install/ModulesController vira '#'
// e o botão "Install" da tela /manage-modules fica sem ação.
// Skill criar-modulo §Críticas.
// ─────────────────────────────────────────────────────────────────────────────
Route::middleware(['web', 'authh', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu', 'throttle:120,1'])
    ->prefix('vestuario')
    ->group(function () {
        Route::post('install',           [InstallController::class, 'install']);
        Route::get('install',           [InstallController::class, 'index']);
        Route::match(['get', 'post'], 'install/uninstall', [InstallController::class, 'uninstall']);
        Route::match(['get', 'post'], 'install/update',    [InstallController::class, 'update']);
    });

// ─────────────────────────────────────────────────────────────────────────────
// US-VEST-020 — Etiqueta TAG vestuário (ZPL Argox/Zebra + PDF DomPDF)
// RUNBOOK: memory/requisitos/Vestuario/RUNBOOK-etiqueta-tag.md
// ─────────────────────────────────────────────────────────────────────────────
Route::middleware(['web', 'SetSessionData', 'auth', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin'])
    ->prefix('vestuario/etiquetas')
    ->name('vestuario.etiquetas.')
    ->group(function () {
        Route::get('/',         [EtiquetaTagController::class, 'index'])->name('index');
        Route::post('lote/zpl', [EtiquetaTagController::class, 'storeZpl'])->name('lote.zpl');
        Route::post('lote/pdf', [EtiquetaTagController::class, 'storePdf'])->name('lote.pdf');
    });

// ── US-VEST-031 · sugestão de compra por tamanho pela curva de venda (decisão [W] 2026-10-26) ──
// Só lê. A tela da compra mostra a sugestão; quem compra confirma.
Route::middleware(['web', 'SetSessionData', 'auth', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin'])
    ->prefix('vestuario')
    ->name('vestuario.')
    ->group(function () {
        Route::get('curva-compra', [CurvaCompraController::class, 'show'])->name('curva-compra');
    });

// ── US-VEST-032 · remarcação por coleção (decisão [W] 2026-10-27) ──
// Desligada por padrão: setting `remarcacao.enabled` do Vestuário (sem ele, 404). Prévia antes de gravar.
Route::middleware(['web', 'SetSessionData', 'auth', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin'])
    ->prefix('vestuario/remarcacao')
    ->name('vestuario.remarcacao.')
    ->group(function () {
        Route::get('/', [RemarcacaoController::class, 'index'])->name('index');
        Route::post('previa', [RemarcacaoController::class, 'previa'])->name('previa');
        Route::post('/', [RemarcacaoController::class, 'store'])->name('store');
        Route::post('{lote}/reverter', [RemarcacaoController::class, 'reverter'])->whereNumber('lote')->name('reverter');
        Route::get('{lote}/etiquetas', [RemarcacaoController::class, 'etiquetas'])->whereNumber('lote')->name('etiquetas');
    });
