<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia;

/**
 * Contrato da tela `/kb/graph` — visualização-grafo (US-KB-006).
 *
 * Casos: resources/js/Pages/kb/Graph.casos.md (UC-KBG-01..03)
 * SDD:   memory/requisitos/KB/SDD-tela-kb-unificado-v1.0.md §5.3 F7 · §6.2 CU-KB-09 · §9 D-3
 *
 * ⚠️ A TELA É FACHADA (medido 2026-07-28): a rota `/kb/graph` é uma closure
 * `Inertia::render('kb/Graph')` SEM props e `/kb/graph/data` devolve
 * `{nodes:[],edges:[],kpis:null}` hardcoded → o front cai em `_lib/mockGraphData.ts`.
 * Por isso este arquivo trava só o PISO da rota (auth · render · leitura pura) —
 * contratar conteúdo agora seria contratar o mock. O grafo real vira teste no mesmo
 * PR que trouxer o KbGraphController (decisão de produto de [W]).
 *
 * ⚠️ NÃO está na allowlist de .github/workflows/kb-pest.yml (catraca-por-prova-verde).
 * Entrada PROPOSTA — enquanto não entrar, o veredito é estruturalmente pendente.
 *
 * @see resources/js/Pages/kb/Graph.charter.md
 * @see Modules/KB/Tests/Feature/KbIndexV2ContractTest.php — mesmo padrão, era-mock da irmã
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped(
            'SQLite: rodar no CT 100 (oimpresso-staging MySQL, biz=1). ADR 0101 / ADR 0062.'
        );
    }

    // A lane não builda o front; o contrato é o NOME do componente servido, não o bundle.
    config(['inertia.testing.ensure_pages_exist' => false]);

    kbBootstrapSchema();
});

afterEach(function () {
    kbTeardownSchema();
});

// A closure de /kb/graph herda a stack do grupo /kb, mas NÃO o `can:` do KbController
// (o gate coarse mora no construtor do Controller, e aqui não há Controller). O que a
// rota exige é `auth` — é exatamente isso que UC-KBG-01 trava.
$permKb = ['jana.mcp.memory.manage'];

// ── UC-KBG-01 — a rota do grafo exige autenticação ──────────────────────────

it('G1: GET /kb/graph anonimo nao devolve 200 nem 500 (UC-KBG-01)', function () {
    $response = $this->get('/kb/graph');

    expect($response->status())->not->toBe(200);
    expect($response->status())->not->toBe(500);
    expect(in_array($response->status(), [302, 401, 403], true))->toBeTrue();
});

it('G1b: GET /kb/graph/data anonimo tambem e barrado (UC-KBG-01)', function () {
    // A ESTRUTURA do grafo já é informação de governança — barrar a página e deixar o
    // endpoint aberto seria porta dos fundos.
    $response = $this->get('/kb/graph/data');

    expect($response->status())->not->toBe(200);
    expect($response->status())->not->toBe(500);
});

// ── UC-KBG-02 — a rota serve a tela, e está registrada nomeada ──────────────

it('G2: GET /kb/graph autenticado renderiza kb/Graph e a rota nomeada real existe (UC-KBG-02)', function () use ($permKb) {
    kbActAsUser(bizId: 1, permissions: $permKb);

    $response = $this->get('/kb/graph');

    $response->assertOk();
    $response->assertInertia(fn (AssertableInertia $p) => $p->component('kb/Graph'));

    // O nome REAL é kb.graph.page. O charter dizia `kb.graph` — que NÃO existe. Travar o
    // nome certo impede a divergência de voltar calada (o front que chamasse route('kb.graph')
    // estouraria em runtime, não em teste).
    expect(\Route::has('kb.graph.page'))->toBeTrue();
});

// ── UC-KBG-03 — abrir o grafo é leitura pura ───────────────────────────────

it('G3: GET /kb/graph nao escreve em kb_nodes nem kb_edges (UC-KBG-03)', function () use ($permKb) {
    kbActAsUser(bizId: 1, permissions: $permKb);
    // Semear é o anti-vácuo: "nada mudou" sobre tabelas vazias não prova leitura pura.
    DB::table('kb_nodes')->insert([
        'business_id' => 1, 'type' => 'adr', 'slug' => 'uckbg03-no-do-grafo',
        'title' => 'NO DO GRAFO', 'is_editable' => false, 'status' => 'ok',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $nodesAntes = DB::table('kb_nodes')->count();
    $edgesAntes = DB::table('kb_edges')->count();
    expect($nodesAntes)->toBeGreaterThan(0);

    $this->get('/kb/graph')->assertOk();

    expect(DB::table('kb_nodes')->count())->toBe($nodesAntes);
    expect(DB::table('kb_edges')->count())->toBe($edgesAntes);
});

it('G3b: GET /kb/graph nao enfileira Job (nao dispara bridge nem derivacao de arestas) (UC-KBG-03)', function () use ($permKb) {
    kbActAsUser(bizId: 1, permissions: $permKb);
    Queue::fake();

    $this->get('/kb/graph')->assertOk();

    Queue::assertNothingPushed();
});
