<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia;

/**
 * Contrato da tela VIVA `/kb/v2` (+ alias `/sops`) — SOPs / KB Unificado tri-pane.
 *
 * `uses(Tests\TestCase::class)` já aplicado globalmente em tests/Pest.php
 * (uses(TestCase::class)->in(Modules/KB/Tests/Feature)). NÃO redeclarar aqui.
 *
 * Estado (2026-07-17): `kb.v2` e o alias `sops.index` agora roteiam pra
 * `KbController@indexV2`, que serve DADO REAL de kb_nodes (charter §8-bis passo 2).
 * A tela saiu do mock (`usingMock = !props.nodes` → false). Este teste blinda o
 * contrato da rota: auth + permissão (`copiloto.mcp.memory.manage`, do constructor),
 * render, read-only, sem side-effects, e Tier 0 FORTE (V5: o payload serve nodes e
 * o global scope isola biz=99). UC-06 (fallback mock) foi REVOGADO com o Controller.
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093): NUNCA biz=4 (ROTA LIVRE prod).
 * biz=1 canônico (ADR 0101); biz=99 = cliente fictício cross-tenant.
 *
 * Casos: resources/js/Pages/kb/Index.v2.casos.md (UC-KBV2-01..06 + UC-KBV2-13)
 *
 * @see resources/js/Pages/kb/Index.v2.charter.md
 * @see Modules/KB/Http/routes.php — Route::get('/v2') + prefix('sops')
 * @see Modules/KB/Tests/Feature/Wave26KbSmokeTest.php — pattern smoke rota Inertia
 */

// Guard SQLite: kbActAsUser/kbBootstrapSchema montam schema kb_* + business/users.
// KB dogfooding roda em MySQL real (CT 100, oimpresso-staging biz=1) — ADR 0101.
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped(
            'SQLite: rodar no CT 100 (oimpresso-staging MySQL, biz=1). ADR 0101 / ADR 0061.'
        );
    }

    // BLOQUEADOR 1 (lane, não prod): a lane kb-pest NÃO builda o front — stub de manifest
    // só, sem os .tsx resolvíveis pelo view-finder. `config/inertia.php` tem
    // `testing.ensure_pages_exist => true` (SEPARADO de `pages.ensure_pages_exist => false`),
    // então `assertInertia(->component('kb/Index.v2'))` tenta localizar o arquivo em disco e
    // falha "Inertia page component file [kb/Index.v2] does not exist" (AssertableInertia:110).
    // O contrato aqui é o NOME do componente Inertia servido pela rota, não que o bundle exista —
    // desligamos a checagem de disco (padrão canônico pra testar componente sem build). O
    // arquivo resources/js/Pages/kb/Index.v2.tsx EXISTE e prod renderiza; é artefato de lane.
    config(['inertia.testing.ensure_pages_exist' => false]);

    kbBootstrapSchema();
});

afterEach(function () {
    kbTeardownSchema();
});

// ── UC-KBV2-01 — rota viva exige autenticação ──────────────────────────────
it('V1: GET /kb/v2 anonimo redireciona login (nunca 200 nem 500)', function () {
    $response = $this->get('/kb/v2');

    expect($response->status())->not->toBe(200);
    expect($response->status())->not->toBe(500);
    // stack middleware `auth` → redirect login (302) OR 401/403.
    expect(in_array($response->status(), [302, 401, 403], true))->toBeTrue();
});

it('V1b: GET /sops anonimo tambem exige auth', function () {
    $response = $this->get('/sops');

    expect($response->status())->not->toBe(200);
    expect($response->status())->not->toBe(500);
    expect(in_array($response->status(), [302, 401, 403], true))->toBeTrue();
});

// ── UC-KBV2-02 — renderiza o componente Inertia kb/Index.v2 ─────────────────
it('V2: rotas kb.v2 e sops.index estao registradas nomeadas', function () {
    expect(\Route::has('kb.v2'))->toBeTrue();
    expect(\Route::has('sops.index'))->toBeTrue();
});

// NOTA: o Controller `KbController@indexV2` exige `can:copiloto.mcp.memory.manage`
// (constructor do KbController — MESMA permissão da V3 /kb, consistente). Por isso os
// testes autenticados concedem a permissão ao user biz=1.
$permKb = ['copiloto.mcp.memory.manage'];

it('V2b: GET /kb/v2 autenticado renderiza Inertia kb/Index.v2', function () use ($permKb) {
    kbActAsUser(bizId: 1, permissions: $permKb);

    $response = $this->get('/kb/v2');

    $response->assertOk();
    $response->assertInertia(fn (AssertableInertia $p) => $p->component('kb/Index.v2'));
});

it('V2c: alias /sops renderiza o MESMO componente kb/Index.v2', function () use ($permKb) {
    kbActAsUser(bizId: 1, permissions: $permKb);

    $response = $this->get('/sops');

    $response->assertOk();
    $response->assertInertia(fn (AssertableInertia $p) => $p->component('kb/Index.v2'));
});

// ── UC-KBV2-03 — GET é read-only (não muta estado) ─────────────────────────
it('V3: GET /kb/v2 nao escreve em kb_nodes nem kb_node_versions (read-only)', function () use ($permKb) {
    kbActAsUser(bizId: 1, permissions: $permKb);

    $nodesAntes    = DB::table('kb_nodes')->count();
    $versionsAntes = DB::table('kb_node_versions')->count();

    $this->get('/kb/v2')->assertOk();

    expect(DB::table('kb_nodes')->count())->toBe($nodesAntes);
    expect(DB::table('kb_node_versions')->count())->toBe($versionsAntes);
});

// ── UC-KBV2-04 — abrir a tela não dispara Jobs nem IA ──────────────────────
it('V4: GET /kb/v2 nao enfileira nenhum Job (sem IA/email/whatsapp no render)', function () use ($permKb) {
    kbActAsUser(bizId: 1, permissions: $permKb);
    Queue::fake();

    $this->get('/kb/v2')->assertOk();

    Queue::assertNothingPushed();
});

// ── UC-KBV2-05 — Tier 0: rota não vaza nós de outro business_id (PROVA FORTE) ─
it('V5: payload servido a biz=1 nao contem no de biz=99 (ADR 0093)', function () use ($permKb) {
    // Nó seedado no tenant CLIENTE fictício (biz=99), NUNCA biz=4 (ROTA LIVRE prod).
    kbActAsUser(bizId: 99, permissions: $permKb);
    DB::table('kb_nodes')->insert([
        'business_id' => 99,
        'type'        => 'article',
        'slug'        => 'segredo-biz99-nao-vaza',
        'title'       => 'SEGREDO BIZ99 NAO PODE VAZAR',
        'is_editable' => true,
        'body_blocks' => json_encode([['kind' => 'para', 'text' => 'confidencial']]),
        'status'      => 'ok',
        'created_at'  => now(),
        'updated_at'  => now(),
    ]);
    // Um nó do PRÓPRIO business (biz=1) — pra provar que o payload NÃO é vazio.
    kbActAsUser(bizId: 1, permissions: $permKb);
    DB::table('kb_nodes')->insert([
        'business_id' => 1,
        'type'        => 'article',
        'slug'        => 'doc-biz1-visivel',
        'title'       => 'DOC BIZ1 VISIVEL',
        'is_editable' => false,
        'status'      => 'ok',
        'created_at'  => now(),
        'updated_at'  => now(),
    ]);

    $response = $this->get('/kb/v2');

    $response->assertOk();
    // PROVA FORTE (o Controller agora SERVE nodes, então isto morde de verdade):
    // o payload TEM nodes.data (não é mais mock), e o nó de biz=99 NÃO está lá —
    // é o global scope do KbNode isolando o tenant (ADR 0093), não "por construção".
    $response->assertInertia(fn (AssertableInertia $p) =>
        $p->component('kb/Index.v2')->has('nodes.data')
    );
    expect($response->getContent())->not->toContain('SEGREDO BIZ99 NAO PODE VAZAR');
    expect($response->getContent())->not->toContain('segredo-biz99-nao-vaza');
    // e o do próprio business aparece:
    expect($response->getContent())->toContain('doc-biz1-visivel');
});

// ── UC-KBV2-06 — ⚰️ REVOGADO: o Controller entrega props, a tela sai do mock ──
// A V6 antiga assertava `missing('nodes')` — o contrato da era-mock. O Controller
// `indexV2` agora PASSA `nodes`/`categories`/`business`, então `missing('nodes')`
// seria FALSO. Revogado no MESMO commit do Controller (charter §8-bis passo 2).
// Substituído pela prova de que a tela recebe DADO REAL:
it('V6: GET /kb/v2 serve DADO REAL — nodes + categories + business (não mock)', function () use ($permKb) {
    kbActAsUser(bizId: 1, permissions: $permKb);
    DB::table('kb_nodes')->insert([
        'business_id' => 1, 'type' => 'adr', 'slug' => 'adr-real-0001',
        'title' => 'ADR real', 'is_editable' => false, 'status' => 'ok',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $response = $this->get('/kb/v2');

    $response->assertOk();
    $response->assertInertia(fn (AssertableInertia $p) =>
        $p->component('kb/Index.v2')
            ->has('nodes.data')          // serve nós reais (usingMock = !props.nodes → false)
            ->has('categories')          // lateral real
            ->has('subcategories')
            ->has('business.name')       // NOVO-A: rótulo da empresa ativa
    );
});

// ── UC-KBV2-13 — Fase #5: code_drift_state chega no payload, scopado (Tier 0) ─
// O sinal doc↔código (kb:drift-detector A1/A2) surface na UI (5º quadrante +
// badge). Aqui o contrato de BACKEND: o campo já viaja (buildListQuery = select *),
// e o drift de outro business NÃO vaza (global scope de KbNode, ADR 0093).
it('V7: payload de biz=1 carrega code_drift_state e nao vaza drift de biz=99 (UC-KBV2-13)', function () use ($permKb) {
    // nó driftado no tenant fictício biz=99 — NUNCA pode aparecer pra biz=1.
    kbActAsUser(bizId: 99, permissions: $permKb);
    DB::table('kb_nodes')->insert([
        'business_id'      => 99,
        'type'             => 'article',
        'slug'             => 'drift-biz99-oculto',
        'title'            => 'DRIFT BIZ99 OCULTO',
        'is_editable'      => true,
        'body_blocks'      => json_encode([['kind' => 'para', 'text' => 'x']]),
        'status'           => 'ok',
        'code_drift_state' => json_encode([
            'checked_at' => now()->toIso8601String(),
            'refs'       => [['path' => 'ModulesSecretoBiz99.php', 'drift_type' => 'reference_deleted_path']],
        ]),
        'created_at'       => now(),
        'updated_at'       => now(),
    ]);
    // nó driftado do PRÓPRIO business (biz=1) — deve chegar no payload com o campo.
    kbActAsUser(bizId: 1, permissions: $permKb);
    DB::table('kb_nodes')->insert([
        'business_id'      => 1,
        'type'             => 'adr',
        'slug'             => 'adr-biz1-com-drift',
        'title'            => 'ADR BIZ1 COM DRIFT',
        'is_editable'      => false,
        'status'           => 'ok',
        'code_drift_state' => json_encode([
            'checked_at' => now()->toIso8601String(),
            'refs'       => [['path' => 'memory-decisions-0000-removida.md', 'drift_type' => 'reference_deleted_path']],
        ]),
        'created_at'       => now(),
        'updated_at'       => now(),
    ]);

    $response = $this->get('/kb/v2');

    $response->assertOk();
    $response->assertInertia(fn (AssertableInertia $p) => $p->component('kb/Index.v2')->has('nodes.data'));

    // (substrings sem barra "/" pra não depender do escape JSON do data-page)
    // o path driftado do próprio business viaja serializado no payload:
    expect($response->getContent())->toContain('memory-decisions-0000-removida.md');
    // Tier 0: nada do drift de biz=99 (nem título nem path):
    expect($response->getContent())->not->toContain('DRIFT BIZ99 OCULTO');
    expect($response->getContent())->not->toContain('ModulesSecretoBiz99.php');
});
