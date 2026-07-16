<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rotas do módulo KB (Knowledge Base) — Grafo de Conhecimento (ADR 0149)
|--------------------------------------------------------------------------
|
| Padrão UltimatePOS (ref.: Modules/Copiloto/Http/routes.php).
|
| 3 blocos:
|   1. /kb (Inertia + KB browser legacy + endpoints REST do grafo Onda 1)
|   2. /kb/install (boilerplate nWidart)
|
| Stack middlewares canônica: web + SetSessionData + auth + language +
| timezone + AdminSidebarMenu + CheckUserLogin (UltimatePOS herdado).
|
*/

// ===========================================================================
// 1) Rotas web — prefixo /kb
// ===========================================================================
Route::group(
    [
        'middleware' => ['web', 'SetSessionData', 'auth', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin'],
        'prefix'     => 'kb',
        'namespace'  => 'Modules\KB\Http\Controllers',
    ],
    function () {
        // ---- ROTAS ESTÁTICAS PRIMEIRO ----
        // IMPORTANTE: registrar ANTES das rotas com {slug} dinâmico abaixo,
        // senão Laravel matcha /kb/v2 vs /kb/{slug} e devolve MethodNotAllowed
        // por causa do DELETE softDelete.

        Route::get('/',                  'KbController@index')->name('kb.index');

        // ---- FREEZER do par mock (V2 tri-pane + Grafo) — [W] 2026-07-16 -------------
        // As rotas /kb/v2, /sops, /kb/graph e /kb/graph/data foram REMOVIDAS. O código
        // das telas está PRESERVADO (resources/js/Pages/kb/Index.v2.tsx · Graph.tsx ·
        // _components/ · _lib/ · schema kb_* · 6 controllers · KbBridgeFromMcpJob).
        //
        // POR QUÊ: as duas rodavam em prod com auth real servindo dado FICTÍCIO —
        // MOCK_NODES é uma KB de gráfica (Roland VS-540 / HP Latex) num tenant que é
        // loja de vestuário. Quatro ações respondiam `toast.success('Artigo re-verificado
        // e marcado como fresco')` sem persistir nada, e o /kb/graph/data devolvia
        // `{nodes:[],edges:[],kpis:null}` HARDCODED — backend falso que garantia que o
        // fallback mock nunca desligasse. Zero link na UI apontava pra cá: o acesso era
        // digitar a URL. [W] 2026-07-16: "difícil de decidir, pois estão em obras" —
        // obra fica atrás do tapume, não aberta ao público.
        //
        // NÃO é arquivamento, e NÃO decide o destino da V2 (Tier 0 [W], em aberto:
        // promover / manter / arquivar). É freezer: tira do ar o que engana enquanto a
        // decisão amadurece, sem jogar fora o trabalho.
        //
        // COMO VOLTAR (1 commit): restaure este bloco. A tela renderiza igual — o
        // fallback mock é interno a ela. Se a volta for pra valer, o caminho é o
        // Controller real (KbController@indexV2) servindo kb_nodes, não a closure.
        //
        // GATILHO objetivo pra reabrir (do veredito adversarial 2026-07-16):
        //   SELECT COUNT(*) FROM kb_nodes WHERE is_editable = 1  → > 0 fora do seed
        //   significa que existe humano criando SOP = há demanda (ADR 0105).
        //
        // Contrato do freezer: UC-KBV2-11 (Index.v2.casos.md) + KbIndexV2ContractTest.
        // Refs: veredito adversarial 2026-07-16 · ADR 0105 (sinal de cliente) · ADR 0114.

        // ---- LEGACY (V0) — KB browser dos docs MCP. Continua respondendo /kb/{slug}/show etc.
        Route::get('/{slug}/show',       'KbController@show')
            ->where('slug', '[A-Za-z0-9\-_]+')
            ->name('kb.show');
        Route::get('/{slug}/history',    'KbController@history')
            ->where('slug', '[A-Za-z0-9\-_]+')
            ->name('kb.history');
        Route::delete('/{slug}',         'KbController@softDelete')
            ->where('slug', '[A-Za-z0-9\-_]+')
            ->name('kb.softdelete');
        Route::post('/{slug}/restore',   'KbController@restore')
            ->where('slug', '[A-Za-z0-9\-_]+')
            ->name('kb.restore');

        // ---- ONDA 1 (2026-05-15) — KB unificado como grafo (ADR 0149) ----
        // SCHEMA-DB-V1.md §11

        // Nós (kb_nodes) — endpoints REST. Tem prefixo /nodes pra não colidir com legacy /kb/{slug}/show.
        Route::get('/nodes',                              'KbNodeController@index')->name('kb.nodes.index');
        Route::post('/nodes',                             'KbNodeController@store')->name('kb.nodes.store');
        Route::get('/nodes/{slug}',                       'KbNodeController@show')
            ->where('slug', '[A-Za-z0-9\-_]+')->name('kb.nodes.show');
        Route::put('/nodes/{slug}',                       'KbNodeController@update')
            ->where('slug', '[A-Za-z0-9\-_]+')->name('kb.nodes.update');
        Route::delete('/nodes/{slug}',                    'KbNodeController@destroy')
            ->where('slug', '[A-Za-z0-9\-_]+')->name('kb.nodes.destroy');
        Route::post('/nodes/{slug}/restore',              'KbNodeController@restore')
            ->where('slug', '[A-Za-z0-9\-_]+')->name('kb.nodes.restore');
        Route::post('/nodes/{slug}/reverify',             'KbNodeController@reverify')
            ->where('slug', '[A-Za-z0-9\-_]+')->name('kb.nodes.reverify');

        // Versões.
        Route::get('/nodes/{slug}/versions',              'KbVersionController@index')
            ->where('slug', '[A-Za-z0-9\-_]+')->name('kb.versions.index');
        Route::post('/nodes/{slug}/restore-version',      'KbVersionController@restoreVersion')
            ->where('slug', '[A-Za-z0-9\-_]+')->name('kb.versions.restore');

        // Favoritos.
        Route::post('/nodes/{slug}/favorite',             'KbFavoriteController@toggle')
            ->where('slug', '[A-Za-z0-9\-_]+')->name('kb.favorites.toggle');

        // Comments inline.
        Route::post('/nodes/{slug}/comments',             'KbCommentController@store')
            ->where('slug', '[A-Za-z0-9\-_]+')->name('kb.comments.store');
        Route::delete('/comments/{id}',                   'KbCommentController@destroy')
            ->where('id', '[0-9]+')->name('kb.comments.destroy');

        // Trilhas.
        Route::get('/paths',                              'KbPathController@index')->name('kb.paths.index');
        Route::post('/paths',                             'KbPathController@store')->name('kb.paths.store');
        Route::get('/paths/{slug}',                       'KbPathController@show')
            ->where('slug', '[A-Za-z0-9\-_]+')->name('kb.paths.show');
        Route::put('/paths/{slug}',                       'KbPathController@update')
            ->where('slug', '[A-Za-z0-9\-_]+')->name('kb.paths.update');

        // Decision trees (troubleshooters).
        Route::get('/decision-trees',                     'KbDecisionTreeController@index')->name('kb.dt.index');
        Route::post('/decision-trees',                    'KbDecisionTreeController@store')->name('kb.dt.store');
        Route::get('/decision-trees/{slug}',              'KbDecisionTreeController@show')
            ->where('slug', '[A-Za-z0-9\-_]+')->name('kb.dt.show');
        Route::put('/decision-trees/{slug}',              'KbDecisionTreeController@update')
            ->where('slug', '[A-Za-z0-9\-_]+')->name('kb.dt.update');

        // Edges (arestas manuais).
        Route::get('/edges',                              'KbEdgeController@index')->name('kb.edges.index');
        Route::post('/edges',                             'KbEdgeController@store')->name('kb.edges.store');
        Route::delete('/edges/{id}',                      'KbEdgeController@destroy')
            ->where('id', '[0-9]+')->name('kb.edges.destroy');

        // ---- AI endpoints — implementados pelo Agent F em group separado no FINAL do arquivo
        //      (group /kb/ai). Wagner pode mover pra cá no merge final.

        // ---- Imprimir SOP (ONDA 5 — placeholder, sem precedente Inertia ainda) ----
        // Route::get('/print-sop/{slug}',                  'PrintSopController@show');
    }
);

// ===========================================================================
// 2) Rotas de instalação 1-clique — prefixo /kb/install
// ===========================================================================
Route::group(
    [
        'middleware' => ['web', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin'],
        'namespace'  => 'Modules\KB\Http\Controllers',
        'prefix'     => 'kb/install',
    ],
    function () {
        Route::get('/',          'InstallController@index')->name('kb.install.index');
        Route::post('/',         'InstallController@install')->name('kb.install.run');
        Route::get('/uninstall', 'InstallController@uninstall')->name('kb.install.uninstall');
        Route::get('/update',    'InstallController@update')->name('kb.install.update');
    }
);

// ===========================================================================
// 3) Alias /sops — REMOVIDO no freezer do par mock ([W] 2026-07-16)
// ===========================================================================
// O alias existia desde 2026-05-17 como atalho pra abrir a V2 sem digitar /kb/v2
// (o comentário original dizia "Wagner decide depois se promove V2 oficialmente" —
// era conveniência de gate visual, nunca pedido de produto confirmado; a revisão
// adversarial de 2026-07-16 flagrou que o único artefato disso era este comentário,
// escrito por agente, e que ele não deve contar como sinal em nenhuma direção).
//
// Foi removido junto com /kb/v2 · /kb/graph · /kb/graph/data — ver o bloco FREEZER
// no group /kb acima pro porquê completo, o caminho de volta (1 commit) e o gatilho
// objetivo de reabertura. A V3 (/kb, docs canônicos com dado REAL) segue intacta e
// continua sendo pra onde o sidebar aponta (DataController → route('kb.index')).

// ============= IA RAG (Agent F · ONDA 4) =============
// Adicionado em paralelo ao Agent A (CRUD nodes/paths/...). Wagner: no merge
// final, considerar mover este bloco PRA DENTRO do group /kb principal acima
// (placeholder comentado linhas 101-104) — split mantido pra evitar conflito
// de edição simultânea entre agents.
//
// Contrato:    memory/requisitos/KB/SCHEMA-DB-V1.md §11 (endpoints /kb/ai/*)
// Service:     Modules/KB/Services/KbRagService.php (RAG flow + cache)
// DTOs:        Modules/KB/Services/Dtos/{RagResult,SummaryResult,MetaSuggestion}.php
// Audit:       mcp_audit_log (append-only via McpAuditLog::registrar())
// Permission:  can:kb.ai.ask (SCHEMA §12) — fallback temporário copiloto.mcp.memory.manage
// Rate-limit:  throttle 10/min/user (constructor KbAiController)
// IA stack:    laravel/ai SDK (ADR 0035) via Modules/Jana/Ai/Agents/KbAnswerAgent
//
// Endpoints:
//   POST   /kb/ai/ask                    → RAG sobre corpus → answer + sources[]
//   POST   /kb/ai/summarize/{slug}       → TL;DR de 1 node
//   POST   /kb/ai/suggest-meta           → auto-tag a partir de body_blocks rascunho
// =====================================================
Route::group(
    [
        'middleware' => ['web', 'SetSessionData', 'auth', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin'],
        'prefix'     => 'kb/ai',
        'namespace'  => 'Modules\KB\Http\Controllers',
        'as'         => 'kb.ai.',
    ],
    function () {
        Route::post('/ask',                  'KbAiController@ask')->name('ask');
        Route::post('/summarize/{slug}',     'KbAiController@summarize')
            ->where('slug', '[A-Za-z0-9\-_\.]+')
            ->name('summarize');
        Route::post('/suggest-meta',         'KbAiController@suggestMeta')->name('suggest-meta');
    }
);
