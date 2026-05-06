<?php

use Illuminate\Support\Facades\Route;
use Modules\Brief\Http\Controllers\BriefFetchController;

/**
 * Rotas API do módulo Brief — endpoint MCP brief-fetch.
 *
 * Middleware:
 * - mcp.auth: registrado pelo JanaServiceProvider (ADR 0053).
 *   Garante token válido em mcp_tokens + carrega scopes Spatie.
 * - throttle:60,1: 60 req/min/IP — proteção básica contra loop em agent.
 *
 * Rota: POST /api/mcp/tools/brief-fetch
 */
Route::middleware(['api', 'mcp.auth', 'throttle:60,1'])
    ->prefix('api/mcp')
    ->group(function () {
        Route::post('/tools/brief-fetch', BriefFetchController::class)
            ->name('mcp.tools.brief-fetch');
    });
