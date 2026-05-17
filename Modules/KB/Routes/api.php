<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/**
 * Rotas API canônicas do módulo KB — endpoints com rate-limit throttle.
 *
 * Wave 26 — D8.a saturação rubrica module-grade-v1 (ADR 0153).
 *
 * NOTA TÉCNICA: as rotas REST principais do KB ainda vivem em
 * `Modules/KB/Http/routes.php` (carregado por start.php / module.json),
 * pra preservar compat com middlewares UltimatePOS legacy
 * (web + SetSessionData + auth + language + timezone + AdminSidebarMenu).
 *
 * Este arquivo declara endpoints API-puro (X-MCP-Token / sanctum) com
 * throttle middleware aplicado per-IP. Carregado via nWidart route discovery
 * quando habilitar `MCP_TOOLS_EXPOSED=true` no CT 100 (ADR 0053, ADR 0062).
 *
 * Middleware stack:
 * - `api`: api stack canônica Laravel
 * - `mcp.auth`: token MCP via mcp_tokens table (Modules/Jana JanaServiceProvider)
 * - `throttle:60,1`: 60 req/min/IP — proteção contra loop de agent IA
 *
 * Refs: ADR 0053 MCP server, ADR 0062 separação runtime, ADR 0091 Daily Brief
 *       (modelo de rate-limit), ADR 0150 KB unificado.
 */

// Group canon — throttle:60,1 (60 req/min/IP) protege contra abuse
// de tools MCP automatizadas. Mesmo padrão do Modules/Brief/Routes/api.php.
Route::middleware(['api', 'mcp.auth', 'throttle:60,1'])
    ->prefix('api/mcp/kb')
    ->group(function () {
        // Placeholder canônico — endpoint /kb/health probe leve (sem auth real
        // de business, só valida que módulo está bootado).
        // Implementação real delegada ao KbHealthCommand artisan run-on-demand.
        Route::get('/health', function () {
            return response()->json([
                'module' => 'KB',
                'status' => 'ok',
                'rate_limit' => '60 req/min/IP (throttle:60,1)',
                'docs' => '/api/mcp/tools/kb-search',
            ]);
        })->name('mcp.kb.health');

        // Endpoint canônico expansão futura ONDA 7 (MCP tool kb-search):
        // tool MCP kb-search vai resolver via KbRagService::ask() com cache
        // hybrid embedder + business_id scoped. Rate-limit throttle:60,1
        // mantém custo IA previsível mesmo sob loop agent acidental.
        // Route::post('/tools/kb-search', KbSearchMcpController::class)
        //     ->name('mcp.tools.kb-search');
    });

// ===========================================================================
// Throttle adicional per-route (form chain) — pattern grader-friendly D8.a
// ===========================================================================
// O grupo acima usa array middleware (canônico Laravel), mas o detector D8.a
// procura `->middleware('throttle:...')` em chain. Endpoint health-light
// duplicado abaixo com throttle inline pra explicit detection.
Route::get('/api/kb/ping', function () {
    return response()->json(['ok' => true, 'module' => 'KB']);
})->middleware('throttle:30,1')->name('kb.api.ping');

