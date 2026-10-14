<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia;

/**
 * Contrato da tela `/ads/admin/graph` — Knowledge Graph (Cognitive Control Panel #3).
 *
 * Casos: Modules/KB/Resources/js/Pages/ads/Admin/Graph.casos.md (UC-KBGR-01..04)
 * Lei:   Modules/KB/Resources/js/Pages/ads/Admin/Graph.charter.md
 * Rota:  Modules/Forja/Http/routes.php → `ads.admin.graph.index` (recebida na parte 6 da
 *        ADR 0363 quando o ADS foi removido; o controller é do KB — SCOPE.md `contains`).
 *
 * NÃO confundir com `/kb/graph` (KbGraphContratoTest, UC-KBG-*): aquela é fachada sem props;
 * esta monta 5 fontes reais. O eixo "skills" (mcp_decision_patterns) foi DROPADO em
 * 2026-07-31 — este arquivo NÃO contrata esse eixo (capacidade aposentada; ver casos.md).
 *
 * Tenant fictício 98 (+ 99 como vizinho) — ADR 0358. NUNCA biz=4.
 *
 * Lane: allowlist do step Pest de .github/workflows/kb-pest.yml.
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite: rodar na lane MySQL (kb-pest) ou CT 100. ADR 0062.');
    }

    // A lane não builda o front; o contrato é o NOME do componente servido, não o bundle.
    config(['inertia.testing.ensure_pages_exist' => false]);

    kbBootstrapSchema();
});

afterEach(function () {
    kbTeardownSchema();
});

// ── UC-KBGR-01 — a rota exige autenticação ─────────────────────────────────

it('GR1: GET /ads/admin/graph anonimo nao devolve 200 nem 500 (UC-KBGR-01)', function () {
    $response = $this->get('/ads/admin/graph');

    expect($response->status())->not->toBe(200);
    expect($response->status())->not->toBe(500);
    expect(in_array($response->status(), [302, 401, 403], true))->toBeTrue();
});

// ── UC-KBGR-02 — a tela abre com a faixa de 4 KPIs e o grafo ────────────────

it('GR2: GET /ads/admin/graph autenticado serve ads/Admin/Graph com 4 KPIs e o no Memory central (UC-KBGR-02)', function () {
    kbActAsUser(bizId: 98);

    $response = $this->get('/ads/admin/graph');

    $response->assertOk();
    $response->assertInertia(fn (AssertableInertia $p) => $p->component('ads/Admin/Graph'));

    $props = $response->viewData('page')['props'];

    // Charter §Goals: "4 KPIs de topo". O contrato é a QUANTIDADE e serem contagens,
    // não o nome de cada chave.
    expect($props['kpis'])->toBeArray();
    expect(count($props['kpis']))->toBe(4);
    foreach ($props['kpis'] as $valor) {
        expect(is_int($valor) && $valor >= 0)->toBeTrue();
    }

    // Charter §Mission: "Memory (centro)" — exatamente um nó central de memória.
    $memoria = array_values(array_filter($props['nodes'], fn ($n) => ($n['type'] ?? null) === 'memory'));
    expect(count($memoria))->toBe(1);

    expect(\Route::has('ads.admin.graph.index'))->toBeTrue();
});

// ── UC-KBGR-03 [T0] — não cruza dados entre businesses ─────────────────────

it('GR3: a contagem de Memory do grafo conta so os docs do business da sessao (UC-KBGR-03)', function () {
    kbActAsUser(bizId: 98);
    kbCreateBusinessRow(99);

    foreach (range(1, 3) as $_) {
        kbCreateMcpDoc(98);
    }
    foreach (range(1, 7) as $_) {
        kbCreateMcpDoc(99);
    }

    $doBusiness = DB::table('mcp_memory_documents')->where('business_id', 98)->count();
    $global = DB::table('mcp_memory_documents')->whereIn('business_id', [98, 99])->count();
    // Anti-vácuo: os dois números TÊM de diferir, senão o assert abaixo não discrimina.
    expect($doBusiness)->toBeGreaterThan(0);
    expect($global)->toBeGreaterThan($doBusiness);

    $props = $this->get('/ads/admin/graph')->assertOk()->viewData('page')['props'];

    $memoria = array_values(array_filter($props['nodes'], fn ($n) => ($n['type'] ?? null) === 'memory'))[0];
    $numeros = array_values(array_filter($memoria['data'], 'is_int'));

    expect(in_array($doBusiness, $numeros, true))->toBeTrue();
    expect(in_array($global, $numeros, true))->toBeFalse();
});

// ── UC-KBGR-04 — abrir o grafo é leitura pura ──────────────────────────────

it('GR4: GET /ads/admin/graph nao escreve em memoria nem em regras de governanca (UC-KBGR-04)', function () {
    kbActAsUser(bizId: 98);
    // Semear é o anti-vácuo: "nada mudou" sobre tabela vazia não prova leitura pura.
    kbCreateMcpDoc(98);

    $docsAntes = DB::table('mcp_memory_documents')->count();
    $regrasAntes = DB::table('mcp_governance_rules')->count();
    expect($docsAntes)->toBeGreaterThan(0);

    $this->get('/ads/admin/graph')->assertOk();

    expect(DB::table('mcp_memory_documents')->count())->toBe($docsAntes);
    expect(DB::table('mcp_governance_rules')->count())->toBe($regrasAntes);
});

it('GR4b: GET /ads/admin/graph nao enfileira Job (UC-KBGR-04)', function () {
    kbActAsUser(bizId: 98);
    Queue::fake();

    $this->get('/ads/admin/graph')->assertOk();

    Queue::assertNothingPushed();
});
