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
// 1) Rotas web — prefixo /ia (canon ADR 0180 sidebar v3 — label "IA" no topo)
// ===========================================================================
// D8.a (Wave 14 governance v3) — throttle:120,1 por user (auth) reforça defesa
// abuse contra rotas Jana mesmo já sendo Tailscale-only no CT 100. 120 req/min
// é folgado pra UX chat real (mensagens + sugestões + sidebar polling) e ainda
// bloqueia bot scraping. Rotas SSE/stream pesadas têm throttle agressivo extra
// inline abaixo (60,1). Referência: ADR 0093 + segurança fail-secure.
//
// Rename /jana → /ia (Wagner 2026-05-22, ADR 0180 vertical-slice IA piloto):
// label do sidebar v3 é "IA" → URL casa. Route names `jana.*` preservados
// (route('jana.x') resolve automático pro novo prefix). Redirect 301
// /jana/* → /ia/* no fim deste arquivo cobre bookmarks externos.
Route::group(
    [
        'middleware' => ['web', 'SetSessionData', 'auth', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin', 'throttle:120,1'],
        'prefix'     => 'ia',
        'namespace'  => 'Modules\Jana\Http\Controllers',
    ],
    function () {
        // ---- Chat (entry-point, ver adr/arq/0002) --------------------------
        Route::get('/',                                    'ChatController@index')->name('jana.chat.index');

        // ---- Cockpit MVP (padrao "Chat Cockpit", ADR 0039 - rota paralela
        //      pra validacao visual sem substituir a /copiloto atual). ----------
        Route::get('/cockpit',                             'ChatController@cockpit')->name('jana.cockpit');

        // ---- Painel Analista IA (Jana V2 — cycle CYCLE-06 goal #4) ---------
        // Canon Cowork: prototipo-ui/cowork-snapshot/chat-jana.jsx (491 ln IIFE).
        // Render Inertia/React via resources/js/Pages/Jana/Painel.tsx.
        // Onda A1: esqueleto + mock data · Onda B: queries SQL reais · Onda C: BriefDiarioAgent.
        Route::get('/painel',                              'PainelController@index')->name('jana.painel');

        Route::post('/conversas',                          'ChatController@criarConversa')->name('jana.conversas.store');
        // Atalho GET — link "Nova conversa" da sidebar (UX Wagner 2026-05-08).
        // Cria conversa e redireciona pro /conversas/{id}. Antes era 404.
        Route::get('/conversas/nova',                      'ChatController@novaConversa')->name('jana.conversas.nova');
        Route::get('/conversas/{id}',                      'ChatController@show')->name('jana.conversas.show');
        Route::post('/conversas/{id}/mensagens',           'ChatController@send')
            ->middleware('throttle:60,1')
            ->name('jana.conversas.mensagens.store');
        // Streaming SSE — UX token-por-token (versão preferencial pelo frontend)
        // D8.a — throttle 60,1 extra (mais agressivo que group 120,1) porque
        // cada mensagem chama LLM (custo R$ + latência). 1 msg/seg sustained
        // é mais que suficiente pra UX humana e bloqueia abuso de tokens.
        Route::post('/conversas/{id}/mensagens/stream',    'ChatController@sendStream')
            ->middleware('throttle:60,1')
            ->name('jana.conversas.mensagens.stream');
        Route::patch('/conversas/{id}',                    'ChatController@updateConversa')->name('jana.conversas.update');
        Route::post('/sugestoes/{id}/escolher',            'ChatController@escolher')->name('jana.sugestoes.escolher');
        Route::post('/sugestoes/{id}/rejeitar',            'ChatController@rejeitar')->name('jana.sugestoes.rejeitar');

        // ---- Dashboard -----------------------------------------------------
        Route::get('/dashboard',                           'DashboardController@index')->name('jana.dashboard.index');

        // ---- Metas CRUD ----------------------------------------------------
        Route::resource('/metas',                          'MetasController', ['names' => [
            'index'   => 'jana.metas.index',
            'create'  => 'jana.metas.create',
            'store'   => 'jana.metas.store',
            'show'    => 'jana.metas.show',
            'edit'    => 'jana.metas.edit',
            'update'  => 'jana.metas.update',
            'destroy' => 'jana.metas.destroy',
        ]]);
        Route::post('/metas/{id}/reapurar',                'MetasController@reapurar')->name('jana.metas.reapurar');

        // ---- Períodos (aninhado em meta) -----------------------------------
        Route::resource('/metas.periodos',                 'PeriodosController', ['only' => ['store', 'update', 'destroy']]);

        // ---- Fontes (aninhado em meta, permissão restrita) -----------------
        // FontesController migrado pra Modules/KB em Fase 3.7 (drift resolution).
        // URL mantém /copiloto/metas/{id}/fonte.
        Route::get('/metas/{id}/fonte',                    [\Modules\KB\Http\Controllers\FontesController::class, 'show'])->name('jana.fontes.show');
        Route::patch('/metas/{id}/fonte',                  [\Modules\KB\Http\Controllers\FontesController::class, 'update'])->name('jana.fontes.update');

        // ---- Alertas -------------------------------------------------------
        Route::get('/alertas',                             'AlertasController@index')->name('jana.alertas.index');
        Route::get('/alertas/config',                      'AlertasController@config')->name('jana.alertas.config');
        Route::patch('/alertas/config',                    'AlertasController@updateConfig')->name('jana.alertas.config.update');

        // ---- Memória (tela "O Copiloto lembra de você", LGPD US-COPI-MEM-012) -
        // MemoriaController migrado pra Modules/KB em Fase 3.7 (drift resolution).
        // URL mantém /copiloto/memoria.
        Route::get('/memoria',                             [\Modules\KB\Http\Controllers\MemoriaController::class, 'index'])->name('jana.memoria.index');
        Route::patch('/memoria/{id}',                      [\Modules\KB\Http\Controllers\MemoriaController::class, 'update'])->name('jana.memoria.update');
        Route::delete('/memoria/{id}',                     [\Modules\KB\Http\Controllers\MemoriaController::class, 'destroy'])->name('jana.memoria.destroy');

        // ---- Ghosts canon ADR 0182 + GUIA-SIDEBAR-V3 (Wagner 2026-05-21) -----
        // 5 destinos canon do hub IA: Copiloto / Brief / Memórias / KB / Regras.
        // Brief e Regras são stubs "Em construção" (telas dedicadas vêm em onda
        // futura); Memórias e KB redirecionam pras rotas existentes preservando
        // o ghost clicável no header canon.
        Route::get('/brief',                               'BriefController@index')->name('jana.brief.index');
        Route::get('/regras',                              'RegrasController@index')->name('jana.regras.index');

        // ---- Superadmin (metas da plataforma, ver adr/arq/0001) ------------
        Route::get('/superadmin/metas',                    'SuperadminController@metas')->name('jana.superadmin.metas');

        // ---- Administração — Onda 1 (ROI direto, ver adr/arq/0003) ----------
        // US-COPI-070: dashboard de custo de IA por business
        Route::get('/admin/custos',                        'Admin\CustosController@index')
            ->name('jana.admin.custos.index');

        // ---- Administração — Governança MCP (MEM-MCP-1.e, ADR 0053) --------
        // Visão cross-team do consumo do MCP server.
        // Permission: copiloto.mcp.usage.all (Wagner/superadmin).
        Route::get('/admin/governanca',                    'Admin\GovernancaController@index')
            ->name('jana.admin.governanca.index');

        // ---- Team admin / Tasks / CC sessions MOVIDOS pra Modules/TeamMcp/ ----
        // URLs antigas redirecionam via Route::redirect 301 (ver fim deste arquivo).
        // Permissions copiloto.mcp.usage.all / copiloto.cc.read.team mantidas
        // (rename pra team-mcp.* vira ADR + migration de permissões em etapa
        // futura — não nesta separação). Sub-rotas POST/PATCH/DELETE não
        // têm redirect (UI Inertia foi atualizada pra apontar pras novas URLs).

        // ---- MEM-KB-1 (ADR 0053) — KB MOVIDO PRO MÓDULO Modules/KB (Etapa 2 modularização, 2026-05-03)
        // Rotas /copiloto/admin/memoria* foram migradas pra /kb*. Redirects 301
        // GET ficam no fim deste arquivo (DELETE/POST não redirecionam — clients
        // novos chamam /kb diretamente).

        // ---- MEM-MET-4 (ADR 0050) — Page /copiloto/admin/qualidade
        Route::get('/admin/qualidade',                     'Admin\QualidadeController@index')
            ->name('jana.admin.qualidade.index');

        // ---- Onda 5 V1 — Roadmap timeline (SVAR React Gantt MIT) -----------
        // Visualiza mcp_cycles + mcp_tasks como Gantt. Filtros cycle/owner/
        // priority/module via query params. Permission: jana.mcp.tasks.read.
        // Ver memory/requisitos/Jana/ONDA-5-DOSSIER-2026-05-13.md §V1.
        Route::get('/admin/roadmap',                       'Admin\RoadmapController@index')
            ->name('jana.admin.roadmap.index');

        // ---- JANA Pro Sprint A (US-COPI-201, ADR 0140) — preview brief diário
        // Endpoint admin pra rodar BriefDiarioService manualmente e ver JSON
        // antes de configurar Job 8h. Permission: copiloto.superadmin
        // (Wagner inicial — depois jana_pro.preview quando US-COPI-212 entrar).
        Route::get('/admin/jana-pro/preview',              'Admin\JanaProController@preview')
            ->middleware('can:copiloto.superadmin')
            ->name('jana.admin.jana_pro.preview');

        // (TaskRegistry F2 e MEM-CC-UI-1 movidos pra Modules/TeamMcp/ — ver
        //  Modules/TeamMcp/Http/routes.php; redirects 301 no rodapé deste arquivo)
    }
);

// ===========================================================================
// 1.b) Redirects 301 — URLs antigas após modularização (TeamMcp + KB)
// ===========================================================================
// Mantém bookmarks/links externos vivos. POST/PATCH/DELETE NÃO redirecionam
// (UI Inertia foi atualizada pras novas rotas; chamadas server-to-server não
//  existiam fora da própria UI).
//
// Split TeamMcp (2026-05-03):
Route::redirect('/copiloto/admin/team',         '/team-mcp/team',         301);
Route::redirect('/copiloto/admin/tasks',        '/team-mcp/tasks',        301);
Route::redirect('/copiloto/admin/cc-sessions',  '/team-mcp/cc-sessions',  301);

// Split KB (2026-05-03) — clients novos devem chamar /kb diretamente:
Route::middleware(['web'])->group(function () {
    Route::redirect('/copiloto/admin/memoria', '/kb', 301);
    Route::redirect('/copiloto/admin/memoria/{slug}/show', '/kb/{slug}/show', 301)
        ->where('slug', '[A-Za-z0-9\-_]+');
    Route::redirect('/copiloto/admin/memoria/{slug}/history', '/kb/{slug}/history', 301)
        ->where('slug', '[A-Za-z0-9\-_]+');
});

// Ghosts canon ADR 0182 — aliases pros destinos reais (Wagner 2026-05-22):
//   /ia/memorias → /ia/memoria  (KB MemoriaController; já existe)
//   /ia/kb       → /kb          (Modules/KB canon ADR 0180 Wave C; 660+ docs sync via webhook GitHub)
// Mantém ghost clicável + nome curto no header sem duplicar tela.
//
// 2026-05-22: /ia/kb mudou de `/essentials/knowledge-base` (Essentials legacy,
// renderizava branco fora do pacote essentials_module) para `/kb` (canon Wave C).
// Mesma decisão arquitetural do PR #1387 que escondeu a entrada Essentials no
// sidebar quando KB canon está instalado.
Route::redirect('/ia/memorias', '/ia/memoria', 302);
Route::redirect('/ia/kb',       '/kb',         302);

// ===========================================================================
// 2) Rotas de instalação 1-clique — prefixo /ia/install (canônico após rename)
// ===========================================================================
// Padrão BaseModuleInstallController + ADR memory/decisions/0023.
// Disparado pelo /manage-modules (superadmin); roda migrations + seta version.
//
// Fix da regressão do PR #97 (Fase 3.7 PR-2): botão Install em /manage-modules
// usa action(\Modules\Jana\...\InstallController), que precisa pegar o nome
// novo /ia/install pra ficar consistente com o sidebar v3 label "IA". URLs
// antigas /copiloto/install/* e /jana/install/* mantidas via 301 redirect.
Route::group(
    [
        'middleware' => ['web', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin'],
        'namespace'  => 'Modules\Jana\Http\Controllers',
        'prefix'     => 'ia/install',
    ],
    function () {
        Route::get('/',          'InstallController@index')->name('jana.install.index');
        Route::post('/',         'InstallController@install')->name('jana.install.run');
        Route::get('/uninstall', 'InstallController@uninstall')->name('jana.install.uninstall');
        Route::get('/update',    'InstallController@update')->name('jana.install.update');
    }
);

// 301 das URLs antigas /copiloto/install/* → /ia/install/* (compat bookmarks)
Route::redirect('/copiloto/install',           '/ia/install',           301);
Route::redirect('/copiloto/install/uninstall', '/ia/install/uninstall', 301);
Route::redirect('/copiloto/install/update',    '/ia/install/update',    301);
Route::redirect('/jana/install',               '/ia/install',           301);
Route::redirect('/jana/install/uninstall',     '/ia/install/uninstall', 301);
Route::redirect('/jana/install/update',        '/ia/install/update',    301);

// ===========================================================================
// 3) MCP server endpoints (ADR 0053) — prefixo /api/mcp
// ===========================================================================
// Públicos (sem auth):
//   POST /api/mcp/sync-memory  — webhook GitHub (auth via X-MCP-Sync-Token)
//   GET  /api/mcp/health       — status básico do server
// Autenticados (Bearer mcp_*):
//   GET  /api/mcp/health/auth  — info do user/token autenticado
// Controllers migrados pra Modules/TeamMcp em Fase 3.7 (drift resolution).
// URLs mantêm /api/mcp/* — só o namespace prefix mudou.
Route::group(
    [
        'middleware' => ['api'],
        'prefix'     => 'api/mcp',
        'namespace'  => 'Modules\TeamMcp\Http\Controllers\Mcp',
    ],
    function () {
        // Públicos
        Route::post('/sync-memory', 'SyncMemoryWebhookController@handle')
            ->name('jana.mcp.sync-memory');
        Route::get('/health', 'HealthController@publico')
            ->name('jana.mcp.health');

        // Autenticados via McpAuth
        Route::group(['middleware' => 'mcp.auth'], function () {
            Route::get('/health/auth', 'HealthController@autenticado')
                ->name('jana.mcp.health.auth');
        });
    }
);

// MEM-CC-1 (ADR 0053 + SPEC-cc-sessions) — Endpoint ingest pra watcher Node
//   POST /api/cc/ingest  — Bearer mcp_*  — payload {session, messages}
// CcIngestController migrado pra Modules/TeamMcp em Fase 3.7 (URL mantida).
Route::group(
    [
        'middleware' => ['api', 'mcp.auth'],
        'prefix'     => 'api/cc',
        'namespace'  => 'Modules\TeamMcp\Http\Controllers\Mcp',
    ],
    function () {
        Route::post('/ingest', 'CcIngestController@ingest')
            ->name('jana.cc.ingest');
    }
);

// MEM-MCP-1.c (ADR 0053) — Servidor MCP protocol (JSON-RPC) via laravel/mcp
// Auth via mcp.auth middleware (mesmo Bearer mcp_* do health/auth).
// Endpoint POST /api/mcp protocolo JSON-RPC 2.0 — clientes Claude Code/Desktop
// usam configurando .claude/settings.local.json com URL + Bearer.
//
// US-COPI-094 (2026-05-07): rota condicionada a MCP_TOOLS_EXPOSED env. CT 100
// Proxmox tem true no .env; Hostinger fica sem (default false), retorna 404.
// Wagner regra canônica: "MCP é só CT 100 — Hostinger não funciona e fica lento".
if (config('mcp.tools_exposed')) {
    \Laravel\Mcp\Facades\Mcp::web('/api/mcp', \Modules\Jana\Mcp\OimpressoMcpServer::class)
        ->middleware(['api', 'mcp.auth'])
        ->name('jana.mcp.server');
}

// ===========================================================================
// 1.c) Redirects 301 — /copiloto/* e /jana/* → /ia/* (sidebar v3 ADR 0180)
// ===========================================================================
// Wagner 2026-05-09: rename /copiloto → /jana (Fase 2b Jana naming alignment).
// Wagner 2026-05-22: rename /jana → /ia (vertical-slice IA piloto sidebar v3 —
// label do topo é "IA" → URL canon casa).
//
// Cadeia: /copiloto/X → 301 /jana/X → 301 /ia/X. Browser segue 2 hops apenas
// pra bookmarks antigos do /copiloto; bookmarks /jana fazem 1 hop direto pro
// canon /ia. Sem latency em URLs canon.
//
// IMPORTANTE: estes redirects genéricos vêm APÓS os específicos do bloco 1.b
// (TeamMcp, KB) e dos ghost aliases — Laravel matching é first-wins; manter
// ordem.
//
// `where('any', '.*')` permite path com qualquer profundidade incluindo barras.
Route::redirect('/copiloto/{any?}', '/jana/{any?}', 301)
    ->where('any', '.*');

Route::redirect('/jana/{any?}', '/ia/{any?}', 301)
    ->where('any', '.*');
