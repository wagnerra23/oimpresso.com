<?php

use Illuminate\Support\Facades\Route;
use Modules\IProduction\Http\Controllers\InstallController;

// ─────────────────────────────────────────────────────────────────────────────
// Rotas Install 1-click (ADR 0024 / BaseModuleInstallController).
//
// Sem essas 3 rotas, action() helper em Install/ModulesController vira '#'
// e o botão "Install" da tela /manage-modules fica sem ação.
// Skill criar-modulo §Críticas.
// ─────────────────────────────────────────────────────────────────────────────
Route::middleware(['web', 'authh', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu'])
    ->prefix('iproduction')
    ->group(function () {
        Route::get('install',           [InstallController::class, 'index']);
        Route::get('install/uninstall', [InstallController::class, 'uninstall']);
        Route::get('install/update',    [InstallController::class, 'update']);
    });
