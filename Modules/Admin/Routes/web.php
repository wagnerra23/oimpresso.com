<?php

use Illuminate\Support\Facades\Route;
use Modules\Admin\Http\Controllers\FeatureFlagsController;
use Modules\Admin\Http\Controllers\IndexController;
use Modules\Admin\Http\Controllers\InstallController;
use Modules\Admin\Http\Controllers\MutationsController;
use Modules\Admin\Http\Controllers\RagQualityDashboardController;
use Modules\Admin\Http\Controllers\ScreenReviewController;

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
// tailscale-only (zero cost IP) -> SetSessionData (popula session.business
// usado pelo AdminSidebarMenu) -> auth -> is-wagner (DB check) ->
// language/timezone (cosmetic UltimatePOS) -> AdminSidebarMenu (popula
// Menu::create('admin-sidebar-menu') consumido pelo HandleInertiaRequests
// como shell.menu; sem ele o Cockpit Sidebar exibe "Menu vazio")
Route::middleware(['web', 'tailscale-only', 'SetSessionData', 'auth', 'is-wagner', 'language', 'timezone', 'AdminSidebarMenu'])
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

        // Governance v4 (scoped scorecards) APOSENTADO em 2026-07-26 (ADR 0353,
        // supersede 0160/0161/0163): o score nao dependia do scorecard — controle
        // negativo (com o YAML x com []) deu identico em 4 dos 5 modulos, e o motor
        // de deteccao (detectRule) tinha zero call sites em producao. A regua viva e
        // a v3: `module:grade-snapshot` (06:05 BRT) + /admin/governance.
        // Wave 28 §G3 — RAG Quality Dashboard (KB + Jana cross-pipeline observability).
        // 3 sparklines (retrieve/rerank/generate p99), nDCG@5 / recall@5 trend 30d,
        // top 10 queries lentas, fallback rate BGE. Inertia::defer pra props caras.
        // @see Modules/Admin/Http/Controllers/RagQualityDashboardController.php
        // @see Modules/KB/Services/KbBgeRerankerService.php (span kb.rerank.bge_v2_m3)
        Route::get('rag-quality', RagQualityDashboardController::class)
            ->name('admin.rag-quality.index');

        // W30 Agent B (2026-05-17) — Screen Review tri-pane PDCA Wagner-only.
        // Lista TODAS telas .tsx do projeto com status pending/approved/rejected/iterate.
        // updateStatus é append-only em <Tela>.review.md (cada call = round novo bloco YAML).
        // Status `rejected` opcionalmente abre Initiative via InitiativeService (idempotent).
        // {screenPath} usa where(.*) pra aceitar barras URL-encoded (Admin/GovernanceV4).
        // @see Modules\Admin\Http\Controllers\ScreenReviewController
        // @see resources/js/Pages/Admin/ScreenReview.charter.md
        // Dashboard split (2026-05-17 Wagner) — landing leve com 5 KPIs PDCA.
        // Registrada ANTES de /screen-review pra ordem importar (route precedence).
        Route::get('screen-review/dashboard',                    [ScreenReviewController::class, 'dashboard'])
            ->name('admin.screen-review.dashboard');
        Route::get('screen-review',                              [ScreenReviewController::class, 'index'])
            ->name('admin.screen-review');
        Route::post('screen-review/{screenPath}/status',         [ScreenReviewController::class, 'updateStatus'])
            ->where('screenPath', '.*')
            ->name('admin.screen-review.update-status');

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
