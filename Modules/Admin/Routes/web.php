<?php

use Illuminate\Support\Facades\Route;
use Modules\Admin\Http\Controllers\IndexController;
use Modules\Admin\Http\Controllers\InstallController;

/*
|--------------------------------------------------------------------------
| Admin Center — rotas web
|--------------------------------------------------------------------------
|
| Sprint 1 — ADR 0122 (Centro de Operações @ CT 100, Tailscale-only).
|
| 3 rotas Install obrigatórias (ADR 0024 — botão Install em /manage-modules)
| + rota principal /admin com middleware stack defense-in-depth.
|
| @see memory/decisions/0122-admin-center-ct100.md
| @see memory/decisions/0024-receita-criar-modulo.md
*/

// Rotas de instalação 1-click (via /manage-modules → botão Install)
// NOTA: prefixo `admin-center` (não `admin`) pra evitar colisão com route
// admin do UltimatePOS core.
Route::middleware(['web', 'authh', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu'])
    ->prefix('admin-center')
    ->group(function () {
        Route::get('install',           [InstallController::class, 'index']);
        Route::get('install/uninstall', [InstallController::class, 'uninstall']);
        Route::get('install/update',    [InstallController::class, 'update']);
    });

// Painel principal Wagner-only — ordem de middleware crítica:
// tailscale-only (zero cost IP) -> auth -> is-wagner (DB check)
Route::middleware(['web', 'tailscale-only', 'auth', 'is-wagner'])
    ->prefix('admin')
    ->group(function () {
        Route::get('/', IndexController::class)->name('admin.index');
    });
