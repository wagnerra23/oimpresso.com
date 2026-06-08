<?php

use Illuminate\Support\Facades\Route;
use Modules\Brief\Http\Controllers\InstallController;

/*
|--------------------------------------------------------------------------
| Brief — rotas web
|--------------------------------------------------------------------------
|
| Sprint 1 — Daily Brief (camada L7 da Constituição V2). Ver ADR 0091.
|
| Brief é primariamente consumido via tool MCP `brief-fetch` (CT 100).
| Estas rotas web servem só pro fluxo de Install via /manage-modules
| (ADR 0024 — 3 rotas obrigatórias install/install/uninstall/install/update).
|
| @see memory/decisions/0091-daily-brief.md
| @see memory/decisions/0024-receita-criar-modulo.md
*/

// Rotas de instalação 1-click (via /manage-modules → botão Install)
Route::middleware(['web', 'authh', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu'])
    ->prefix('brief')
    ->group(function () {
        Route::get('install',           [InstallController::class, 'index']);
        Route::get('install/uninstall', [InstallController::class, 'uninstall']);
        Route::get('install/update',    [InstallController::class, 'update']);
    });
