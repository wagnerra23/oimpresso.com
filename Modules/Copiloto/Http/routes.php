<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rotas do módulo Copiloto
|--------------------------------------------------------------------------
|
| Padrão UltimatePOS (ref.: Modules/PontoWr2/Http/routes.php).
| Chat é o entry-point do módulo (ver adr/arq/0002). Rota raiz abre o chat.
|
*/

// ===========================================================================
// 1) Rotas web — prefixo /copiloto
// ===========================================================================
Route::group(
    [
        'middleware' => ['web', 'SetSessionData', 'auth', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin'],
        'prefix'     => 'copiloto',
        'namespace'  => 'Modules\Copiloto\Http\Controllers',
    ],
    function () {
        // ---- Chat (entry-point, ver adr/arq/0002) --------------------------
        Route::get('/',                                    'ChatController@index')->name('copiloto.chat.index');

        // ---- Cockpit MVP (padrao "Chat Cockpit", ADR 0039 - rota paralela
        //      pra validacao visual sem substituir a /copiloto atual). ----------
        Route::get('/cockpit',                             'ChatController@cockpit')->name('copiloto.cockpit');
        Route::post('/conversas',                          'ChatController@criarConversa')->name('copiloto.conversas.store');
        Route::get('/conversas/{id}',                      'ChatController@show')->name('copiloto.conversas.show');
        Route::post('/conversas/{id}/mensagens',           'ChatController@send')->name('copiloto.conversas.mensagens.store');
        // Streaming SSE — UX token-por-token (versão preferencial pelo frontend)
        Route::post('/conversas/{id}/mensagens/stream',    'ChatController@sendStream')->name('copiloto.conversas.mensagens.stream');
        Route::patch('/conversas/{id}',                    'ChatController@updateConversa')->name('copiloto.conversas.update');
        Route::post('/sugestoes/{id}/escolher',            'ChatController@escolher')->name('copiloto.sugestoes.escolher');
        Route::post('/sugestoes/{id}/rejeitar',            'ChatController@rejeitar')->name('copiloto.sugestoes.rejeitar');

        // ---- Dashboard -----------------------------------------------------
        Route::get('/dashboard',                           'DashboardController@index')->name('copiloto.dashboard.index');

        // ---- Metas CRUD ----------------------------------------------------
        Route::resource('/metas',                          'MetasController', ['names' => [
            'index'   => 'copiloto.metas.index',
            'create'  => 'copiloto.metas.create',
            'store'   => 'copiloto.metas.store',
            'show'    => 'copiloto.metas.show',
            'edit'    => 'copiloto.metas.edit',
            'update'  => 'copiloto.metas.update',
            'destroy' => 'copiloto.metas.destroy',
        ]]);
        Route::post('/metas/{id}/reapurar',                'MetasController@reapurar')->name('copiloto.metas.reapurar');

        // ---- Períodos (aninhado em meta) -----------------------------------
        Route::resource('/metas.periodos',                 'PeriodosController', ['only' => ['store', 'update', 'destroy']]);

        // ---- Fontes (aninhado em meta, permissão restrita) -----------------
        Route::get('/metas/{id}/fonte',                    'FontesController@show')->name('copiloto.fontes.show');
        Route::patch('/metas/{id}/fonte',                  'FontesController@update')->name('copiloto.fontes.update');

        // ---- Alertas -------------------------------------------------------
        Route::get('/alertas',                             'AlertasController@index')->name('copiloto.alertas.index');
        Route::get('/alertas/config',                      'AlertasController@config')->name('copiloto.alertas.config');
        Route::patch('/alertas/config',                    'AlertasController@updateConfig')->name('copiloto.alertas.config.update');

        // ---- Memória (tela "O Copiloto lembra de você", LGPD US-COPI-MEM-012) -
        Route::get('/memoria',                             'MemoriaController@index')->name('copiloto.memoria.index');
        Route::patch('/memoria/{id}',                      'MemoriaController@update')->name('copiloto.memoria.update');
        Route::delete('/memoria/{id}',                     'MemoriaController@destroy')->name('copiloto.memoria.destroy');

        // ---- Superadmin (metas da plataforma, ver adr/arq/0001) ------------
        Route::get('/superadmin/metas',                    'SuperadminController@metas')->name('copiloto.superadmin.metas');

        // ---- Administração — Onda 1 (ROI direto, ver adr/arq/0003) ----------
        // US-COPI-070: dashboard de custo de IA por business
        Route::get('/admin/custos',                        'Admin\CustosController@index')
            ->name('copiloto.admin.custos.index');

        // ---- Administração — Governança MCP (MEM-MCP-1.e, ADR 0053) --------
        // Visão cross-team do consumo do MCP server.
        // Permission: copiloto.mcp.usage.all (Wagner/superadmin).
        Route::get('/admin/governanca',                    'Admin\GovernancaController@index')
            ->name('copiloto.admin.governanca.index');

        // ---- Team admin / Tasks / CC sessions MOVIDOS pra Modules/TeamMcp/ ----
        // URLs antigas redirecionam via Route::redirect 301 (ver fim deste arquivo).
        // Permissions copiloto.mcp.usage.all / copiloto.cc.read.team mantidas
        // (rename pra team-mcp.* vira ADR + migration de permissões em etapa
        // futura — não nesta separação). Sub-rotas POST/PATCH/DELETE não
        // têm redirect (UI Inertia foi atualizada pra apontar pras novas URLs).

        // ---- MEM-KB-1 (ADR 0053) — KB browser dos docs servidos via MCP server
        Route::get('/admin/memoria',                       'Admin\MemoriaKbController@index')
            ->name('copiloto.admin.memoria.index');
        Route::get('/admin/memoria/{slug}/show',           'Admin\MemoriaKbController@show')
            ->where('slug', '[A-Za-z0-9\-_]+')
            ->name('copiloto.admin.memoria.show');
        Route::get('/admin/memoria/{slug}/history',        'Admin\MemoriaKbController@history')
            ->where('slug', '[A-Za-z0-9\-_]+')
            ->name('copiloto.admin.memoria.history');
        Route::delete('/admin/memoria/{slug}',             'Admin\MemoriaKbController@softDelete')
            ->where('slug', '[A-Za-z0-9\-_]+')
            ->name('copiloto.admin.memoria.softdelete');
        Route::post('/admin/memoria/{slug}/restore',       'Admin\MemoriaKbController@restore')
            ->where('slug', '[A-Za-z0-9\-_]+')
            ->name('copiloto.admin.memoria.restore');

        // ---- MEM-MET-4 (ADR 0050) — Page /copiloto/admin/qualidade
        Route::get('/admin/qualidade',                     'Admin\QualidadeController@index')
            ->name('copiloto.admin.qualidade.index');

        // (TaskRegistry F2 e MEM-CC-UI-1 movidos pra Modules/TeamMcp/ — ver
        //  Modules/TeamMcp/Http/routes.php; redirects 301 no rodapé deste arquivo)
    }
);

// ===========================================================================
// 1.b) Redirects 301 — URLs antigas → /team-mcp/* (split TeamMcp)
// ===========================================================================
// Mantém bookmarks/links externos vivos. POST/PATCH/DELETE NÃO redirecionam
// (UI Inertia foi atualizada pra novas rotas; chamadas server-to-server não
//  existiam fora da própria UI).
Route::redirect('/copiloto/admin/team',         '/team-mcp/team',         301);
Route::redirect('/copiloto/admin/tasks',        '/team-mcp/tasks',        301);
Route::redirect('/copiloto/admin/cc-sessions',  '/team-mcp/cc-sessions',  301);

// ===========================================================================
// 2) Rotas de instalação 1-clique — prefixo /copiloto/install
// ===========================================================================
// Padrão BaseModuleInstallController + ADR memory/decisions/0023.
// Disparado pelo /manage-modules (superadmin); roda migrations + seta version.
Route::group(
    [
        'middleware' => ['web', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin'],
        'namespace'  => 'Modules\Copiloto\Http\Controllers',
        'prefix'     => 'copiloto/install',
    ],
    function () {
        Route::get('/',          'InstallController@index')->name('copiloto.install.index');
        Route::post('/',         'InstallController@install')->name('copiloto.install.run');
        Route::get('/uninstall', 'InstallController@uninstall')->name('copiloto.install.uninstall');
        Route::get('/update',    'InstallController@update')->name('copiloto.install.update');
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
Route::group(
    [
        'middleware' => ['api'],
        'prefix'     => 'api/mcp',
        'namespace'  => 'Modules\Copiloto\Http\Controllers\Mcp',
    ],
    function () {
        // Públicos
        Route::post('/sync-memory', 'SyncMemoryWebhookController@handle')
            ->name('copiloto.mcp.sync-memory');
        Route::get('/health', 'HealthController@publico')
            ->name('copiloto.mcp.health');

        // Autenticados via McpAuth
        Route::group(['middleware' => 'mcp.auth'], function () {
            Route::get('/health/auth', 'HealthController@autenticado')
                ->name('copiloto.mcp.health.auth');
        });
    }
);

// MEM-CC-1 (ADR 0053 + SPEC-cc-sessions) — Endpoint ingest pra watcher Node
//   POST /api/cc/ingest  — Bearer mcp_*  — payload {session, messages}
Route::group(
    [
        'middleware' => ['api', 'mcp.auth'],
        'prefix'     => 'api/cc',
        'namespace'  => 'Modules\Copiloto\Http\Controllers\Mcp',
    ],
    function () {
        Route::post('/ingest', 'CcIngestController@ingest')
            ->name('copiloto.cc.ingest');
    }
);

// MEM-MCP-1.c (ADR 0053) — Servidor MCP protocol (JSON-RPC) via laravel/mcp
// Auth via mcp.auth middleware (mesmo Bearer mcp_* do health/auth).
// Endpoint POST /api/mcp protocolo JSON-RPC 2.0 — clientes Claude Code/Desktop
// usam configurando .claude/settings.local.json com URL + Bearer.
\Laravel\Mcp\Facades\Mcp::web('/api/mcp', \Modules\Copiloto\Mcp\OimpressoMcpServer::class)
    ->middleware(['api', 'mcp.auth'])
    ->name('copiloto.mcp.server');
