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
//
// 2026-07-31 (incorporação do ADS pelo Governance, parte 5/7): as 9 rotas dos 3
// controllers da Forja (tools, team-scopes, projects) saíram DESTE arquivo e
// passaram a ser registradas em `Modules/Forja/Http/routes.php` — o módulo dono
// do controller virou também o host da rota. URL e name ficaram INALTERADOS
// (`/ads/admin/*`, `ads.admin.*`): ADR 0087 resolve drift SEM mover URL, e o
// frontend chama esses endpoints por string literal (não por `route()`), então
// renomear quebraria em silêncio.
//
// A rota do GraphController (Modules/KB) saiu daqui na parte 6 — ver bloco
// abaixo. O comentário anterior dizia que ela podia morrer "porque o KB já tem
// grafo próprio em /kb/graph": medido em 2026-07-31, `/kb/graph` NÃO EXISTE em
// nenhum `Routes/*.php` do repo, então `/ads/admin/graph` era a única porta
// dessa tela.

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
    // /admin/tools/* → Modules/Forja/Http/routes.php (parte 5/7, 2026-07-31)
    Route::get('/admin/learning',   [LearningController::class,   'index'])->name('ads.admin.learning.index');
    Route::get('/admin/meta-skills', [MetaSkillsController::class, 'index'])->name('ads.admin.metaskills.index');
    Route::post('/admin/meta-skills/{id}/toggle', [MetaSkillsController::class, 'toggle'])
        ->whereNumber('id')
        ->name('ads.admin.metaskills.toggle');
    Route::post('/admin/meta-skills', [MetaSkillsController::class, 'store'])
        ->name('ads.admin.metaskills.store');
    Route::post('/admin/meta-skills/validate', [MetaSkillsController::class, 'validateRule'])
        ->name('ads.admin.metaskills.validate');

    // /admin/kb (2 redirects 301) e /admin/graph → Modules/Forja/Http/routes.php
    // (parte 6, 2026-07-31). Saíram daqui porque NÃO são do ADS: o graph serve o
    // GraphController do Modules/KB e a page Pages/ads/Admin/Graph.tsx, ambos
    // vivos — ficariam 404 quando este arquivo for deletado com o módulo.
    // Saíram daqui em vez de serem copiadas lá: `route:cache` está ativo em prod
    // e o name `ads.admin.graph.index` não pode existir nos dois arquivos.

    // /admin/team-scopes/* → Modules/Forja/Http/routes.php (parte 5/7, 2026-07-31)
    Route::get('/admin/conflicts', [ConflictsController::class, 'index'])->name('ads.admin.conflicts.index');

    // /admin/projects/* → Modules/Forja/Http/routes.php (parte 5/7, 2026-07-31)
});
