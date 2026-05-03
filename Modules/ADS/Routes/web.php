<?php

use Illuminate\Support\Facades\Route;
use Modules\ADS\Http\Controllers\Admin\DecisoesController;
use Modules\ADS\Http\Controllers\Admin\PolicyController;
use Modules\ADS\Http\Controllers\Admin\ConfidenceController;
use Modules\ADS\Http\Controllers\Admin\MetricasController;
use Modules\ADS\Http\Controllers\Admin\PatternsController;
use Modules\ADS\Http\Controllers\Admin\ToolsController;
use Modules\ADS\Http\Controllers\Admin\LearningController;
use Modules\ADS\Http\Controllers\Admin\MetaSkillsController;
use Modules\ADS\Http\Controllers\Admin\GraphController;
use Modules\ADS\Http\Controllers\Admin\ConflictsController;
use Modules\ADS\Http\Controllers\Admin\ProjectsController;
use Modules\ADS\Http\Controllers\InstallController;

// Rotas de instalação 1-click (via /manage-modules → botão Install)
// Pattern: ADR 0024 / feedback_pattern_install_modulos
Route::middleware(['web', 'authh', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu'])
    ->prefix('ads')
    ->group(function () {
        Route::get('install',           [InstallController::class, 'index']);
        Route::get('install/uninstall', [InstallController::class, 'uninstall']);
        Route::get('install/update',    [InstallController::class, 'update']);
    });

Route::group([
    'middleware' => ['web', 'SetSessionData', 'auth', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin'],
    'prefix'     => 'ads',
], function () {
    // Inbox de decisions
    Route::get('/admin/decisoes',                  [DecisoesController::class, 'index'])
        ->name('ads.admin.decisoes.index');
    Route::get('/admin/decisoes/{id}',             [DecisoesController::class, 'show'])
        ->whereNumber('id')
        ->name('ads.admin.decisoes.show');
    Route::post('/admin/decisoes/{id}/approve',    [DecisoesController::class, 'approve'])
        ->whereNumber('id')
        ->name('ads.admin.decisoes.approve');
    Route::post('/admin/decisoes/{id}/reject',     [DecisoesController::class, 'reject'])
        ->whereNumber('id')
        ->name('ads.admin.decisoes.reject');
    Route::post('/admin/decisoes/{id}/dismiss',    [DecisoesController::class, 'dismiss'])
        ->whereNumber('id')
        ->name('ads.admin.decisoes.dismiss');

    // Páginas read-only de transparência
    Route::get('/admin/policy',     [PolicyController::class,     'index'])->name('ads.admin.policy.index');
    Route::get('/admin/confidence', [ConfidenceController::class, 'index'])->name('ads.admin.confidence.index');
    Route::get('/admin/metricas',   [MetricasController::class,   'index'])->name('ads.admin.metricas.index');
    Route::get('/admin/patterns',   [PatternsController::class,   'index'])->name('ads.admin.patterns.index');
    Route::get('/admin/skills',     [PatternsController::class,   'index'])->name('ads.admin.skills.index'); // alias semântico
    Route::get('/admin/tools',      [ToolsController::class,      'index'])->name('ads.admin.tools.index');
    Route::post('/admin/tools/{name}/execute', [ToolsController::class, 'execute'])
        ->where('name', '[a-z0-9_\-\.]+')
        ->name('ads.admin.tools.execute');
    Route::get('/admin/learning',   [LearningController::class,   'index'])->name('ads.admin.learning.index');
    Route::get('/admin/meta-skills', [MetaSkillsController::class, 'index'])->name('ads.admin.metaskills.index');
    Route::post('/admin/meta-skills/{id}/toggle', [MetaSkillsController::class, 'toggle'])
        ->whereNumber('id')
        ->name('ads.admin.metaskills.toggle');
    Route::get('/admin/graph',     [GraphController::class,     'index'])->name('ads.admin.graph.index');
    Route::get('/admin/conflicts', [ConflictsController::class, 'index'])->name('ads.admin.conflicts.index');

    // Projects (Wagner modelo: Project → Parts → ADRs)
    Route::get('/admin/projects',                [ProjectsController::class, 'index'])->name('ads.admin.projects.index');
    Route::post('/admin/projects',               [ProjectsController::class, 'store'])->name('ads.admin.projects.store');
    Route::get('/admin/projects/{id}',           [ProjectsController::class, 'show'])->whereNumber('id')->name('ads.admin.projects.show');
    Route::post('/admin/projects/{id}/decompose', [ProjectsController::class, 'decompose'])->whereNumber('id')->name('ads.admin.projects.decompose');
});
