<?php

use Illuminate\Support\Facades\Route;
use Modules\Admin\Http\Controllers\FeatureFlagsController;
use Modules\Admin\Http\Controllers\GovernanceV4DashboardController;
use Modules\Admin\Http\Controllers\IndexController;
use Modules\Admin\Http\Controllers\InstallController;
use Modules\Admin\Http\Controllers\MutationsController;

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

        // Sprint 2 mutations — ações destrutivas com double-confirmation.
        // Todas exigem body { reason >=5 chars, confirm: true } + auditadas
        // em mcp_admin_audit_log.
        Route::post('mutations/curador/apply',          [MutationsController::class, 'applyCurador'])
            ->name('admin.mutations.curador.apply');
        Route::post('mutations/mcp-token/regenerate',   [MutationsController::class, 'regenerateMcpToken'])
            ->name('admin.mutations.mcp-token.regenerate');
        Route::post('mutations/health-check/run-now',   [MutationsController::class, 'runHealthCheckNow'])
            ->name('admin.mutations.health-check.run-now');

        // Wave 24 Agent B — Governance v4 Dashboard intra-bucket (AI baseline READ-ONLY 30d).
        // Lista ranking por bucket (vertical/cross-cutting/ai/functional) com sparkline 30d
        // + paired violations + AI suggestions (NÃO altera score oficial — anti-Goodhart).
        // @see Modules/Jana/Services/Scorecard/AiScorecardJudge.php
        Route::get('governance-v4', GovernanceV4DashboardController::class)
            ->name('admin.governance-v4.index');

        // US-INFRA-008 (2026-05-13) — Painel de feature flags GrowthBook.
        // Read via GrowthBookAdminService. Audit em feature_flag_audits (dedicado).
        // Cintura+suspensório com Tool MCP `flag-*` e Artisan `flag:*`.
        Route::prefix('feature-flags')->name('admin.feature-flags.')->group(function () {
            Route::get('/',                       [FeatureFlagsController::class, 'index'])->name('index');
            Route::get('{key}',                   [FeatureFlagsController::class, 'show'])->name('show');
            Route::post('{key}/biz-rule',         [FeatureFlagsController::class, 'setBizRule'])->name('biz-rule');
            Route::post('{key}/env-enabled',      [FeatureFlagsController::class, 'setEnvEnabled'])->name('env-enabled');
            Route::post('cache/clear',            [FeatureFlagsController::class, 'clearCache'])->name('cache.clear');
        });
    });
