<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rotas do módulo TeamMcp
|--------------------------------------------------------------------------
|
| Padrão UltimatePOS (ref.: Modules/Copiloto/Http/routes.php).
| Concentra governança: tokens MCP + Kanban backlog + auditoria CC sessions.
| Permissions herdadas do Copiloto (NÃO renomeadas — risco de quebrar usuários
| existentes; rename vira task de revisão futura).
|
*/

// ===========================================================================
// 1) Rotas web — prefixo /team-mcp
// ===========================================================================
Route::group(
    [
        'middleware' => ['web', 'SetSessionData', 'auth', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin'],
        'prefix'     => 'team-mcp',
        'namespace'  => 'Modules\TeamMcp\Http\Controllers',
    ],
    function () {
        // ---- Team admin — equivalente self-host Anthropic Team plan (ADR 0055) ----
        Route::get('/team',                          'TeamController@index')
            ->name('team-mcp.team.index');
        Route::post('/team/{user}/token',            'TeamController@gerarToken')
            ->name('team-mcp.team.token.gerar');
        Route::post('/team/{user}/dxt',              'TeamController@gerarDxt')
            ->name('team-mcp.team.dxt.gerar');
        // G-DESIGN-01: drill-down lista tokens individuais por dev (FICHA CAPTERRA 2026-05-25)
        Route::get('/team/{user}/tokens',            'TeamController@listTokens')
            ->name('team-mcp.team.tokens.index');
        // G-DESIGN-02: revoke individual scopa user (FICHA CAPTERRA 2026-05-25)
        Route::delete('/team/{user}/token/{tokenId}', 'TeamController@revokeToken')
            ->name('team-mcp.team.token.revoke');
        // Legacy revoke (compat — kept while existing callers migrate)
        Route::delete('/team/token/{token}',         'TeamController@revogarToken')
            ->name('team-mcp.team.token.revogar');
        Route::post('/team/{user}/quota',            'TeamController@atualizarQuota')
            ->name('team-mcp.team.quota.update');
        Route::get('/team/export.csv',               'TeamController@exportCsv')
            ->name('team-mcp.team.export.csv');

        // ---- TaskRegistry F2 (US-TR-007) — Kanban /team-mcp/tasks ----
        Route::get('/tasks',                         'TasksAdminController@index')
            ->name('team-mcp.tasks.index');
        Route::patch('/tasks/{taskId}/status',       'TasksAdminController@updateStatus')
            ->where('taskId', '[A-Z0-9\-]+')
            ->name('team-mcp.tasks.update-status');
        // Forja PR-1 — drawer de issue (read-only): situação + atividade + vínculos + subtasks
        Route::get('/tasks/{taskId}/detail',         'TasksAdminController@show')
            ->where('taskId', '[A-Za-z0-9_\-]+')
            ->name('team-mcp.tasks.detail');

        // ---- MEM-CC-UI-1 (SPEC-cc-sessions) — KB sessões Claude Code do time ----
        Route::get('/cc-sessions',                   'CcSessionsController@index')
            ->name('team-mcp.cc.index');
        Route::get('/cc-sessions/search',            'CcSessionsController@search')
            ->name('team-mcp.cc.search');
        Route::get('/cc-sessions/{sessionUuid}',     'CcSessionsController@show')
            ->where('sessionUuid', '[A-Za-z0-9\-]+')
            ->name('team-mcp.cc.show');

        // ---- G1 FICHA W22 (Wave 25 polish) — Scorecard Facts+Checks ----
        Route::get('/scorecard',                     'ScorecardController@index')
            ->name('team-mcp.scorecard.index');
    }
);

// ===========================================================================
// 1b) Forja — cockpit do cowork loop (Onda Forja). Prefixo /forja (segmento
//     PRÓPRIO: useAutoModuleNav casa o topnav por 1º segmento, e /team-mcp já é
//     do hub Equipe — colidiria). Controller mora aqui no TeamMcp (absorção, não
//     módulo novo). Permissão copiloto.mcp.usage.all (superadmin), igual Scorecard.
// ===========================================================================
Route::group(
    [
        'middleware' => ['web', 'SetSessionData', 'auth', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin'],
        'prefix'     => 'forja',
        'namespace'  => 'Modules\TeamMcp\Http\Controllers',
    ],
    function () {
        Route::get('/',          'ForjaController@triagem')->name('forja.triagem');
        Route::get('/backlog',   'ForjaController@backlog')->name('forja.backlog');
        Route::get('/quadro',    'ForjaController@quadro')->name('forja.quadro');
        Route::get('/changelog', 'ForjaController@changelog')->name('forja.changelog');
        Route::get('/mcp',       'ForjaController@mcp')->name('forja.mcp');
        // Saúde foi fundida no Scorecard real (/team-mcp/scorecard) — sem rota própria.

        // PR-7b (ADR 0283 · Fase 2) — levers do loop de handoff (re-disparar/devolver/
        // supersede) dos botões da aba MCP. Mesma mutação governada do tool MCP
        // handoff-lever (HandoffLeverService é a fonte única). 3 segmentos → não
        // colide com /{taskId}/* (2 segmentos).
        Route::post('/handoff/{slug}/lever', 'ForjaController@handoffLever')
            ->where('slug', '[A-Za-z0-9_\-]+')->name('forja.handoff.lever');

        // Triagem (aba 1) — dossiê do Analista (read-only) + ações [W] aprova.
        // Espelha /project-mgmt/triage/{id}/{dossier,aprovar,rejeitar,fundir} (PR-5a).
        // taskId aceita FORJA-150/identifier ou US-XXX legacy.
        Route::get('/{taskId}/dossier',   'ForjaController@dossier')
            ->where('taskId', '[A-Za-z0-9_\-]+')->name('forja.dossier');
        Route::post('/{taskId}/aprovar',  'ForjaController@aprovar')
            ->where('taskId', '[A-Za-z0-9_\-]+')->name('forja.aprovar');
        Route::post('/{taskId}/rejeitar', 'ForjaController@rejeitar')
            ->where('taskId', '[A-Za-z0-9_\-]+')->name('forja.rejeitar');
        Route::post('/{taskId}/fundir',   'ForjaController@fundir')
            ->where('taskId', '[A-Za-z0-9_\-]+')->name('forja.fundir');
    }
);

// ===========================================================================
// 2) Rotas de instalação 1-clique — prefixo /team-mcp/install
// ===========================================================================
Route::group(
    [
        'middleware' => ['web', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin'],
        'namespace'  => 'Modules\TeamMcp\Http\Controllers',
        'prefix'     => 'team-mcp/install',
    ],
    function () {
        Route::get('/',          'InstallController@index')->name('team-mcp.install.index');
        Route::post('/',         'InstallController@install')->name('team-mcp.install.run');
        Route::get('/uninstall', 'InstallController@uninstall')->name('team-mcp.install.uninstall');
        Route::get('/update',    'InstallController@update')->name('team-mcp.install.update');
    }
);
