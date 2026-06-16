<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rotas do módulo ProjectMgmt
|--------------------------------------------------------------------------
|
| Padrão UltimatePOS (ref.: Modules/TeamMcp/Http/routes.php).
| Promovido a módulo próprio em 2026-05-04 (ADR 0070) — Jira-style PM
| sobre tabelas mcp_jira_projects/epics/cycles/tasks.
|
| Permissões herdadas do Copiloto (`copiloto.mcp.usage.all`) — mesmo padrão
| do TeamMcp pra evitar quebrar usuários com setup atual.
|
*/

// ===========================================================================
// 1) Rotas web — prefixo /project-mgmt
// ===========================================================================
Route::group(
    [
        'middleware' => ['web', 'SetSessionData', 'auth', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin'],
        'prefix'     => 'project-mgmt',
        'namespace'  => 'Modules\ProjectMgmt\Http\Controllers',
    ],
    function () {
        // Default: redireciona pra /project-mgmt/board
        Route::get('/', function () {
            return redirect()->route('project-mgmt.board.index');
        })->name('project-mgmt.index');

        // ---- Board (Kanban) — US-TR-201 ------------------------------------
        Route::get('/board', 'BoardController@index')
            ->name('project-mgmt.board.index');

        Route::patch('/board/{taskId}/status', 'BoardController@updateStatus')
            ->where('taskId', '[A-Z0-9\-]+')
            ->name('project-mgmt.board.update-status');

        // ---- Detail Sheet — PMG-004 (ADR 0100) -----------------------------
        Route::get('/board/{taskId}/detail', 'BoardController@show')
            ->where('taskId', '[A-Z0-9\-]+')
            ->name('project-mgmt.board.show');

        // ---- Comments + @mentions — PMG-005 (ADR 0100) ---------------------
        Route::post('/board/{taskId}/comment', 'BoardController@addComment')
            ->where('taskId', '[A-Z0-9\-]+')
            ->name('project-mgmt.board.add-comment');

        Route::get('/board/users/suggest', 'BoardController@suggestUsers')
            ->name('project-mgmt.board.users.suggest');

        // ---- Watchers — PMG-006 (ADR 0100) ---------------------------------
        Route::post('/board/{taskId}/watch', 'BoardController@watch')
            ->where('taskId', '[A-Z0-9\-]+')
            ->name('project-mgmt.board.watch');

        Route::delete('/board/{taskId}/watch', 'BoardController@unwatch')
            ->where('taskId', '[A-Z0-9\-]+')
            ->name('project-mgmt.board.unwatch');

        // ---- Subtasks UI — PMG-007 (ADR 0100) ------------------------------
        Route::post('/board/{taskId}/subtask', 'BoardController@addSubtask')
            ->where('taskId', '[A-Z0-9\-]+')
            ->name('project-mgmt.board.add-subtask');

        // ---- Cmd+K Search Global — PMG-002 (ADR 0100) ----------------------
        Route::get('/search', 'SearchController@index')
            ->name('project-mgmt.search');

        // ---- My Work + Inbox — US-TR-204 -----------------------------------
        Route::get('/my-work', 'MyWorkController@index')
            ->name('project-mgmt.my-work.index');

        Route::post('/my-work/inbox/read-all', 'MyWorkController@markAllRead')
            ->name('project-mgmt.my-work.inbox.read-all');

        Route::post('/my-work/inbox/{id}/read', 'MyWorkController@markRead')
            ->where('id', '[0-9]+')
            ->name('project-mgmt.my-work.inbox.read');

        Route::patch('/my-work/{taskId}/status', 'MyWorkController@bumpStatus')
            ->where('taskId', '[A-Z0-9\-]+')
            ->name('project-mgmt.my-work.bump-status');

        // ---- Triage — US-TR-301..303 (SPEC-UI-FASE7 Onda 2) ----------------
        // Superfície humana da tool MCP `triage` (tasks órfãs).
        Route::get('/triage', 'TriageController@index')
            ->name('project-mgmt.triage.index');

        // Atribuição inline owner/prio/cycle/epic — reusa tasks-update.
        Route::patch('/triage/{taskId}/assign', 'TriageController@assign')
            ->where('taskId', '[A-Z0-9\-]+')
            ->name('project-mgmt.triage.assign');

        // ---- Triage Analista (Forja PR-5a) — dossiê read-only + ações [W] aprova ----
        Route::get('/triage/{taskId}/dossier', 'TriageController@dossier')
            ->where('taskId', '[A-Za-z0-9_\-]+')
            ->name('project-mgmt.triage.dossier');
        Route::post('/triage/{taskId}/aprovar', 'TriageController@aprovar')
            ->where('taskId', '[A-Za-z0-9_\-]+')
            ->name('project-mgmt.triage.aprovar');
        Route::post('/triage/{taskId}/rejeitar', 'TriageController@rejeitar')
            ->where('taskId', '[A-Za-z0-9_\-]+')
            ->name('project-mgmt.triage.rejeitar');
        Route::post('/triage/{taskId}/fundir', 'TriageController@fundir')
            ->where('taskId', '[A-Za-z0-9_\-]+')
            ->name('project-mgmt.triage.fundir');

        // ---- Inbox — US-TR-304..306 (SPEC-UI-FASE7 Onda 2) -----------------
        // Caixa de entrada dedicada (mcp_inbox_notifications do auth user).
        Route::get('/inbox', 'InboxController@index')
            ->name('project-mgmt.inbox.index');

        Route::patch('/inbox/read-all', 'InboxController@markAllRead')
            ->name('project-mgmt.inbox.read-all');

        Route::patch('/inbox/{id}/read', 'InboxController@markRead')
            ->where('id', '[0-9]+')
            ->name('project-mgmt.inbox.read');

        // ---- Backlog — US-TR-202 -------------------------------------------
        Route::get('/backlog', 'BacklogController@index')
            ->name('project-mgmt.backlog.index');

        Route::post('/backlog/bulk', 'BacklogController@bulk')
            ->name('project-mgmt.backlog.bulk');

        // ---- Roadmap — US-TR-203 -------------------------------------------
        Route::get('/roadmap', 'RoadmapController@index')
            ->name('project-mgmt.roadmap.index');

        // ---- Activity feed — US-TR-205 -------------------------------------
        Route::get('/activity', 'ActivityController@index')
            ->name('project-mgmt.activity.index');

        // ---- Burndown chart — US-TR-206 ------------------------------------
        Route::get('/burndown', 'BurndownController@index')
            ->name('project-mgmt.burndown.index');
    }
);

// ===========================================================================
// 2) Rotas de instalação 1-clique — prefixo /project-mgmt/install
// ===========================================================================
// Padrão BaseModuleInstallController + ADR 0024.
// As 3 rotas abaixo são OBRIGATÓRIAS — sem elas o botão Install em
// /manage-modules fica sem ação (action() → '#').
Route::group(
    [
        'middleware' => ['web', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin'],
        'namespace'  => 'Modules\ProjectMgmt\Http\Controllers',
        'prefix'     => 'project-mgmt/install',
    ],
    function () {
        Route::get('/',          'InstallController@index')->name('project-mgmt.install.index');
        Route::post('/',         'InstallController@install')->name('project-mgmt.install.run');
        Route::get('/uninstall', 'InstallController@uninstall')->name('project-mgmt.install.uninstall');
        Route::get('/update',    'InstallController@update')->name('project-mgmt.install.update');
    }
);
