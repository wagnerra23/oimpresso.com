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
        // Dashboard consolidado
        Route::get('/', [DashboardController::class, 'index'])
            ->name('admin.dashboard');

        // Policies CRUD (mcp_governance_rules)
        Route::get('/policies', [PoliciesController::class, 'index'])
            ->name('policies.index');
        Route::post('/policies/{id}/toggle', [PoliciesController::class, 'toggle'])
            ->name('policies.toggle');

        // Audit log drill-down
        Route::get('/audit', [AuditController::class, 'index'])
            ->name('audit.index');

        // Drift alerts (Module Charter Art. 7)
        Route::get('/drift', [DriftAlertsController::class, 'index'])
            ->name('drift.index');

        // Module Grades — rubrica module-grade-v1 (ADR 0153)
        Route::get('/module-grades', [ModuleGradeController::class, 'index'])
            ->name('module-grades.index');
        Route::get('/module-grades/{name}', [ModuleGradeController::class, 'show'])
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
