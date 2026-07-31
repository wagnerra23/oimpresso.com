<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rotas do módulo Forja
|--------------------------------------------------------------------------
|
| Padrão UltimatePOS (ref.: Modules/Forja/Http/routes.php).
| Promovido a módulo próprio em 2026-05-04 (ADR 0070) — Jira-style PM
| sobre tabelas mcp_jira_projects/epics/cycles/tasks.
|
| Permissões herdadas do Copiloto (`jana.mcp.usage.all`) — mesmo padrão
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
        'namespace'  => 'Modules\Forja\Http\Controllers',
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
        'namespace'  => 'Modules\Forja\Http\Controllers',
        'prefix'     => 'project-mgmt/install',
    ],
    function () {
        Route::get('/',          'InstallController@index')->name('project-mgmt.install.index');
        Route::post('/',         'InstallController@install')->name('project-mgmt.install.run');
        Route::get('/uninstall', 'InstallController@uninstall')->name('project-mgmt.install.uninstall');
        Route::get('/update',    'InstallController@update')->name('project-mgmt.install.update');
    }
);

// ===========================================================================
// 3) MCP server endpoints (ADR 0053) — prefixo /api/mcp
// ===========================================================================
// Públicos (sem auth):
//   POST /api/mcp/sync-memory  — webhook GitHub (auth via X-MCP-Sync-Token)
//   GET  /api/mcp/health       — status básico do server
// Autenticados (Bearer mcp_*):
//   GET  /api/mcp/health/auth  — info do user/token autenticado
// Vieram de Modules/Jana em 2026-07-30 (decisão [W] "MCP vai para Forja" —
// proposal 2026-07-30-mcp-e-forja-jana-e-usuario: o MCP é plataforma/dev, a
// Jana é o produto do tenant). Medido: mcp_memory_documents tem business_id,
// mas as 2082 linhas são TODAS business_id=1 — a coluna é nominal; o canon
// é conteúdo de plataforma. URLs e names (jana.mcp.*) INALTERADOS — renomear
// namespace é a fase F1-F3 da proposal (gate aditivo), não aqui.
Route::group(
    [
        'middleware' => ['api'],
        'prefix'     => 'api/mcp',
        'namespace'  => 'Modules\Forja\Http\Controllers\Mcp',
    ],
    function () {
        // Públicos
        Route::post('/sync-memory', 'SyncMemoryWebhookController@handle')
            ->name('jana.mcp.sync-memory');
        Route::get('/health', 'HealthController@publico')
            ->name('jana.mcp.health');

        // Drift sentinel (ADR 0256): token DEDICADO MCP_DRIFT_TOKEN checado no
        // controller (sem mcp.auth/RBAC/user) — vazar revela só o SHA do commit.
        Route::get('/version', 'HealthController@versao')
            ->name('jana.mcp.version');

        // G6 (porta de saída do loop): cycle ATIVO pro cron do shipped-log descobrir
        // cycle+janela sem depender do shipped-log anterior. Mesmo token do /version.
        Route::get('/cycle-active', 'HealthController@cicloAtivo')
            ->name('jana.mcp.cycle-active');

        // Autenticados via McpAuth
        Route::group(['middleware' => 'mcp.auth'], function () {
            Route::get('/health/auth', 'HealthController@autenticado')
                ->name('jana.mcp.health.auth');
        });
    }
);

// ===========================================================================
// 4) Endpoint MCP do Daily Brief — POST /api/mcp/tools/brief-fetch
// ===========================================================================
// Ex-Modules/Brief/Routes/api.php, absorvido em 2026-07-30 (ADR 0091).
// O NOME da tool não mudou (`brief-fetch`) — só o módulo dono. Por isso
// CLAUDE.md passo 1, o hook SessionStart e os 6 agentes seguem intactos.
//
// Middleware:
// - mcp.auth: registrado pelo JanaServiceProvider (ADR 0053). Garante token
//   válido em mcp_tokens + carrega scopes Spatie.
// - throttle:60,1: 60 req/min/IP — proteção contra loop em agent.
Route::middleware(['api', 'mcp.auth', 'throttle:60,1'])
    ->prefix('api/mcp')
    ->group(function () {
        Route::post('/tools/brief-fetch', \Modules\Forja\Http\Controllers\BriefFetchController::class)
            ->name('mcp.tools.brief-fetch');
    });

// MEM-CC-1 (ADR 0053 + SPEC-cc-sessions) — Endpoint ingest pra watcher Node
//   POST /api/cc/ingest  — Bearer mcp_*  — payload {session, messages}
// Veio de Modules/Jana em 2026-07-31 com o cluster de ingest ([W] "MCP vai
// para Forja"). URL e name (jana.cc.ingest) INALTERADOS — cada watcher local
// dos devs aponta pra essa URL; mover o endereco exigiria reconfigurar todos.
Route::group(
    [
        'middleware' => ['api', 'mcp.auth'],
        'prefix'     => 'api/cc',
        'namespace'  => 'Modules\Forja\Http\Controllers\Mcp',
    ],
    function () {
        Route::post('/ingest', 'CcIngestController@ingest')
            ->name('jana.cc.ingest');
    }
);

// ===========================================================================
// Hub Equipe — prefixo /team-mcp (veio de Modules/TeamMcp em 2026-07-31,
// [W] "MCP vai para Forja"). URLs e names team-mcp.* INALTERADOS: o time usa
// esses enderecos todo dia, e as paginas React seguem em Pages/team-mcp/*.
// Renomear URL e decisao separada, com 301 — ADR 0087 (drift SEM mover URL).
// ===========================================================================
Route::group(
    [
        'middleware' => ['web', 'SetSessionData', 'auth', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin'],
        'prefix'     => 'team-mcp',
        'namespace'  => 'Modules\Forja\Http\Controllers',
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
//     módulo novo). Permissão jana.mcp.usage.all (superadmin), igual Scorecard.
// ===========================================================================
Route::group(
    [
        'middleware' => ['web', 'SetSessionData', 'auth', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin'],
        'prefix'     => 'forja',
        'namespace'  => 'Modules\Forja\Http\Controllers',
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
