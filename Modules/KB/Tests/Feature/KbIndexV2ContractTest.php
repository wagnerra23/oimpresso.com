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
 * Contexto de maturidade (honesto): a rota `kb.v2` e o alias `sops.index` são
 * CLOSURES INLINE que fazem `Inertia::render('kb/Index.v2')` SEM props — o
 * Controller `KbController@indexV2` do charter NUNCA foi implementado. Logo a
 * tela roda 100% em modo mock (`usingMock = !props.nodes` → true). Este teste
 * blinda o CONTRATO DA ROTA VIVA (auth · render · read-only · sem side-effects
 * · Tier 0), derivado do charter `Index.v2.charter.md` §"Automation Anti-hooks"
 * + §"Métricas vivas (Pest GUARD)" — NÃO o contrato de dados backend (pendente
 * ONDA 1). Quando o `indexV2` real chegar, V5 vira o `has('nodes')` scopado.
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093): NUNCA biz=4 (ROTA LIVRE prod).
 * biz=1 canônico (ADR 0101); biz=99 = cliente fictício cross-tenant.
 *
 * Casos: resources/js/Pages/kb/Index.v2.casos.md (UC-KBV2-01..06)
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

it('V2b: GET /kb/v2 autenticado renderiza Inertia kb/Index.v2', function () {
    kbActAsUser(bizId: 1);

    $response = $this->get('/kb/v2');

    $response->assertOk();
    $response->assertInertia(fn (AssertableInertia $p) => $p->component('kb/Index.v2'));
});

it('V2c: alias /sops renderiza o MESMO componente kb/Index.v2', function () {
    kbActAsUser(bizId: 1);

    $response = $this->get('/sops');

    $response->assertOk();
    $response->assertInertia(fn (AssertableInertia $p) => $p->component('kb/Index.v2'));
});

// ── UC-KBV2-03 — GET é read-only (não muta estado) ─────────────────────────
it('V3: GET /kb/v2 nao escreve em kb_nodes nem kb_node_versions (read-only)', function () {
    kbActAsUser(bizId: 1);

    $nodesAntes    = DB::table('kb_nodes')->count();
    $versionsAntes = DB::table('kb_node_versions')->count();

    $this->get('/kb/v2')->assertOk();

    expect(DB::table('kb_nodes')->count())->toBe($nodesAntes);
    expect(DB::table('kb_node_versions')->count())->toBe($versionsAntes);
});

// ── UC-KBV2-04 — abrir a tela não dispara Jobs nem IA ──────────────────────
it('V4: GET /kb/v2 nao enfileira nenhum Job (sem IA/email/whatsapp no render)', function () {
    kbActAsUser(bizId: 1);
    Queue::fake();

    $this->get('/kb/v2')->assertOk();

    Queue::assertNothingPushed();
});

// ── UC-KBV2-05 — Tier 0: rota não vaza nós de outro business_id ─────────────
it('V5: payload servido a biz=1 nao contem no de biz=99 (ADR 0093)', function () {
    // Nó seedado no tenant CLIENTE fictício (biz=99), NUNCA biz=4 (ROTA LIVRE prod).
    kbActAsUser(bizId: 99);
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

    // Agora o operador biz=1 abre a tela.
    kbActAsUser(bizId: 1);
    $response = $this->get('/kb/v2');

    $response->assertOk();
    // Hoje: render mock-only → prop `nodes` ausente, então o slug/título de biz=99
    // nunca aparece no payload por construção. Quando indexV2 real chegar, esta
    // asserção continua válida (scope business_id) e vira a prova forte.
    $response->assertInertia(fn (AssertableInertia $p) =>
        $p->component('kb/Index.v2')->missing('nodes.data')
    );
    expect($response->getContent())->not->toContain('SEGREDO BIZ99 NAO PODE VAZAR');
    expect($response->getContent())->not->toContain('segredo-biz99-nao-vaza');
});

// ── UC-KBV2-06 — fallback mock declarado enquanto backend ausente ───────────
it('V6: GET /kb/v2 responde 200 sem nenhuma prop (fallback MOCK_NODES)', function () {
    kbActAsUser(bizId: 1);

    // A closure inline (kb.v2) NÃO passa props → a page precisa sobreviver via
    // MOCK_NODES sem lançar "prop undefined". Prova: 200 + componente certo,
    // sem exigir prop `nodes` (que hoje é ausente por design).
    $response = $this->get('/kb/v2');

    $response->assertOk();
    $response->assertInertia(fn (AssertableInertia $p) =>
        $p->component('kb/Index.v2')->missing('nodes')
    );
});
