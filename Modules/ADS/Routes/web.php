<?php

use Illuminate\Support\Facades\Route;
use Modules\ADS\Http\Controllers\Admin\DecisoesController;
use Modules\ADS\Http\Controllers\Admin\PolicyController;
use Modules\ADS\Http\Controllers\Admin\ConfidenceController;
use Modules\ADS\Http\Controllers\Admin\MetricasController;
use Modules\ADS\Http\Controllers\Admin\PatternsController;
use Modules\ADS\Http\Controllers\Admin\LearningController;
use Modules\ADS\Http\Controllers\Admin\MetaSkillsController;
use Modules\ADS\Http\Controllers\Admin\SkillsController;
use Modules\ADS\Http\Controllers\Admin\ConflictsController;
use Modules\ADS\Http\Controllers\InstallController;
// Drift resolvido em Fase 3.7 (PR-1): 4 controllers movidos pros módulos donos.
// URLs mantêm /ads/admin/* (PR-2 fará rename de URL se aplicável).
use Modules\KB\Http\Controllers\Admin\GraphController;
use Modules\ProjectMgmt\Http\Controllers\Admin\ProjectsController;
use Modules\TeamMcp\Http\Controllers\Admin\ToolsController;
use Modules\TeamMcp\Http\Controllers\Admin\TeamScopesController;

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

    // Skills (ADR 0076) — DB primary com fallback filesystem.
    // Fase 1: lista + detalhe. Fase 2: edição inline (cria version draft em DB).
    Route::get('/admin/skills',              [SkillsController::class, 'index'])->name('ads.admin.skills.index');
    Route::get('/admin/skills/{slug}',       [SkillsController::class, 'show'])
        ->where('slug', '[a-z0-9][a-z0-9-]*')
        ->name('ads.admin.skills.show');
    Route::get('/admin/skills/{slug}/edit',  [SkillsController::class, 'edit'])
        ->where('slug', '[a-z0-9][a-z0-9-]*')
        ->name('ads.admin.skills.edit');
    Route::post('/admin/skills/{slug}',      [SkillsController::class, 'store'])
        ->where('slug', '[a-z0-9][a-z0-9-]*')
        ->name('ads.admin.skills.store');
    Route::get('/admin/skills/{slug}/test',  [SkillsController::class, 'test'])
        ->where('slug', '[a-z0-9][a-z0-9-]*')
        ->name('ads.admin.skills.test');
    Route::post('/admin/skills/{slug}/test', [SkillsController::class, 'runTest'])
        ->where('slug', '[a-z0-9][a-z0-9-]*')
        ->name('ads.admin.skills.run-test');

    // Approval queue + actions (Fase 4)
    Route::get('/admin/skills-review',                    [SkillsController::class, 'review'])
        ->name('ads.admin.skills.review');
    Route::post('/admin/skills/versions/{versionId}/approve', [SkillsController::class, 'approve'])
        ->whereNumber('versionId')
        ->name('ads.admin.skills.approve');
    Route::post('/admin/skills/versions/{versionId}/reject',  [SkillsController::class, 'reject'])
        ->whereNumber('versionId')
        ->name('ads.admin.skills.reject');
    Route::post('/admin/skills/versions/{versionId}/publish', [SkillsController::class, 'publish'])
        ->whereNumber('versionId')
        ->name('ads.admin.skills.publish');
    Route::post('/admin/skills/{slug}/move-label',        [SkillsController::class, 'moveLabel'])
        ->where('slug', '[a-z0-9][a-z0-9-]*')
        ->name('ads.admin.skills.move-label');
    Route::get('/admin/tools',      [ToolsController::class,      'index'])->name('ads.admin.tools.index');
    Route::post('/admin/tools/{name}/execute', [ToolsController::class, 'execute'])
        ->where('name', '[a-z0-9_\-\.]+')
        ->name('ads.admin.tools.execute');
    Route::get('/admin/learning',   [LearningController::class,   'index'])->name('ads.admin.learning.index');
    Route::get('/admin/meta-skills', [MetaSkillsController::class, 'index'])->name('ads.admin.metaskills.index');
    Route::post('/admin/meta-skills/{id}/toggle', [MetaSkillsController::class, 'toggle'])
        ->whereNumber('id')
        ->name('ads.admin.metaskills.toggle');
    Route::post('/admin/meta-skills', [MetaSkillsController::class, 'store'])
        ->name('ads.admin.metaskills.store');
    Route::post('/admin/meta-skills/validate', [MetaSkillsController::class, 'validateRule'])
        ->name('ads.admin.metaskills.validate');

    // KB duplicado removido (PR feat/delete-ads-kb-duplicate) — ver módulo Modules/KB
    // URL canônica é /kb (não mais /ads/admin/kb)
    Route::redirect('/admin/kb', '/kb', 301);
    Route::redirect('/admin/kb/{slug}', '/kb/{slug}/show', 301)
        ->where('slug', '[A-Za-z0-9\-_\.]+');

    // Team Scopes (caso Maiara: governance per-user × module)
    Route::get('/admin/team-scopes',          [TeamScopesController::class, 'index'])->name('ads.admin.teamscopes.index');
    Route::post('/admin/team-scopes/grant',   [TeamScopesController::class, 'grant'])->name('ads.admin.teamscopes.grant');
    Route::post('/admin/team-scopes/revoke',  [TeamScopesController::class, 'revoke'])->name('ads.admin.teamscopes.revoke');
    Route::get('/admin/graph',     [GraphController::class,     'index'])->name('ads.admin.graph.index');
    Route::get('/admin/conflicts', [ConflictsController::class, 'index'])->name('ads.admin.conflicts.index');

    // Projects (Wagner modelo: Project → Parts → ADRs)
    Route::get('/admin/projects',                [ProjectsController::class, 'index'])->name('ads.admin.projects.index');
    Route::post('/admin/projects',               [ProjectsController::class, 'store'])->name('ads.admin.projects.store');
    Route::get('/admin/projects/{id}',           [ProjectsController::class, 'show'])->whereNumber('id')->name('ads.admin.projects.show');
    Route::post('/admin/projects/{id}/decompose', [ProjectsController::class, 'decompose'])->whereNumber('id')->name('ads.admin.projects.decompose');
});
