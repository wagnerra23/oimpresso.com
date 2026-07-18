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

        // ---- Index V2 tri-pane — DADO REAL (charter §8-bis passo 2, [W] 2026-07-17) ----
        // Antes: closure `Inertia::render('kb/Index.v2')` sem props → tela em MOCK.
        // Agora: KbController@indexV2 serve kb_nodes reais (categorias/subcategorias/
        // business), a tela sai do mock. Tier 0 scopado pelo global scope (web request).
        Route::get('/v2', [\Modules\KB\Http\Controllers\KbController::class, 'indexV2'])->name('kb.v2');

        // ---- Grafo (ONDA 5, Agent E) — vis-grafo Reactflow ----
        // Page Inertia com mock 50 nodes / 64 edges quando /kb/graph/data não
        // está populado ainda. Coração da promessa "visualização sobre meus
        // dados e arquivos importantes".
        Route::get('/graph', function () {
            return \Inertia\Inertia::render('kb/Graph');
        })->name('kb.graph.page');

        // /kb/graph/data — endpoint JSON pro Reactflow.
        // V1 placeholder: retorna estrutura vazia; frontend cai automaticamente
        // pra mockGraphData.ts (badge "modo mock" visível). Agent A substitui
        // por KbGraphController@vis real (Inertia::defer business_id scope).
        Route::get('/graph/data', function () {
            return response()->json(['nodes' => [], 'edges' => [], 'kpis' => null]);
        })->name('kb.graph.data');

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
// 3) Alias /sops — atalho semântico pra KB v2 (Standard Operating Procedures)
// ===========================================================================
// Wagner pediu rota memorável pra acessar V2 sem digitar /kb/v2 (2026-05-17).
// Mesma stack middleware /kb canônica. Index.v2.tsx renderizado igual /kb/v2 —
// SEM rename de arquivos, SEM cutover de /kb (V3 continua browser dos 352 docs
// canônicos). Wagner decide depois se promove V2 oficialmente.
//
// Coexistência: /kb (V3 docs canon) · /kb/v2 (gate visual) · /sops (alias).
// Reversível: deletar este bloco remove a rota sem afetar V2 nem V3.
Route::group(
    [
        'middleware' => ['web', 'SetSessionData', 'auth', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin'],
        'prefix'     => 'sops',
    ],
    function () {
        // DADO REAL (charter §8-bis, [W] 2026-07-17): mesmo Controller que /kb/v2 — o alias
        // /sops serve o tri-pane com kb_nodes reais, não mais o mock.
        Route::get('/', [\Modules\KB\Http\Controllers\KbController::class, 'indexV2'])->name('sops.index');
    }
);

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
