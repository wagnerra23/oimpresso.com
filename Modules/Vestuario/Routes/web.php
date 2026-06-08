<?php

use Illuminate\Support\Facades\Route;
use Modules\Vestuario\Http\Controllers\EtiquetaTagController;
use Modules\Vestuario\Http\Controllers\InstallController;

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
        Route::get('install',           [InstallController::class, 'index']);
        Route::get('install/uninstall', [InstallController::class, 'uninstall']);
        Route::get('install/update',    [InstallController::class, 'update']);
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
