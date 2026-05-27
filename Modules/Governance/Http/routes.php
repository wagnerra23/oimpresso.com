<?php

use Illuminate\Support\Facades\Route;
use Modules\Governance\Http\Controllers\DashboardController;
use Modules\Governance\Http\Controllers\InstallController;
use Modules\Governance\Http\Controllers\DataController;
use Modules\Governance\Http\Controllers\PoliciesController;
use Modules\Governance\Http\Controllers\AuditController;
use Modules\Governance\Http\Controllers\DriftAlertsController;
use Modules\Governance\Http\Controllers\ModuleGradeController;

/*
|--------------------------------------------------------------------------
| Module Governance — Web Routes
|--------------------------------------------------------------------------
|
| ADR 0086 MVP — UI básica /governance lista pendências consolidadas.
| Rotas detalhadas (policies CRUD, audit filtrável, drift alerts) ficam
| pra próxima fase quando frontend for implementado.
|
*/

Route::middleware(['web', 'authh', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin'])
    ->prefix('governance')
    ->name('governance.')
    ->group(function () {
        // Wagner 2026-05-22: /governance redireciona pra /ia (entry-point
        // canon do oimpresso é o hub IA/Jana — sidebar v3 ADR 0180).
        // Dashboard governance original preservado em /governance/dashboard.
        Route::get('/', fn () => redirect('/ia', 302))->name('admin.dashboard');
        Route::get('/dashboard', [DashboardController::class, 'index'])
            ->middleware('throttle:60,1')
            ->name('admin.dashboard.legacy');

        // Policies CRUD (mcp_governance_rules) — throttle defensivo:
        // read 60/min, toggle 10/min (operação sensível afeta enforcement runtime)
        Route::get('/policies', [PoliciesController::class, 'index'])
            ->middleware('throttle:60,1')
            ->name('policies.index');
        Route::post('/policies/{id}/toggle', [PoliciesController::class, 'toggle'])
            ->middleware('throttle:10,1')
            ->name('policies.toggle');

        // Audit log drill-down — throttle 30/min (query pesada DB com filtros)
        Route::get('/audit', [AuditController::class, 'index'])
            ->middleware('throttle:30,1')
            ->name('audit.index');

        // Drift alerts (Module Charter Art. 7) — throttle 20/min (scan filesystem caro)
        Route::get('/drift', [DriftAlertsController::class, 'index'])
            ->middleware('throttle:20,1')
            ->name('drift.index');

        // Module Grades — rubrica module-grade-v3 (ADR 0155)
        // index/show throttle 30/min — cache 5min mitiga repeat hits
        Route::get('/module-grades', [ModuleGradeController::class, 'index'])
            ->middleware('throttle:30,1')
            ->name('module-grades.index');
        Route::get('/module-grades/{name}', [ModuleGradeController::class, 'show'])
            ->middleware('throttle:30,1')
            ->name('module-grades.show')
            ->where('name', '[A-Za-z0-9_-]+');

        // Install hooks (ADR 0024 — pattern padronizado BaseModuleInstallController)
        Route::get('install',           [InstallController::class, 'index'])
            ->name('install.index');
        Route::get('install/uninstall', [InstallController::class, 'uninstall'])
            ->name('install.uninstall');
        Route::get('install/update',    [InstallController::class, 'update'])
            ->name('install.update');
    });
