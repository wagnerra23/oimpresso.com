<?php

use Illuminate\Support\Facades\Route;
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

// Modules/Vestuario — rotas web. Sprint 1: scaffold vazio.
// Routes reais (Pages Inertia, Controllers) entram Sprint 2+ conforme
// sinal qualificado [ADR 0105].
