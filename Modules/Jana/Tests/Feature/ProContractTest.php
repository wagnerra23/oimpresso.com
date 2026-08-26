<?php

declare(strict_types=1);

use App\User;
use Inertia\Testing\AssertableInertia;

uses(Tests\TestCase::class);

/**
 * Jana Pro — paywall/upgrade — CONTRATO (MV batch 2026-07-06, piloto Módulo Vivo).
 *
 * Tela nova (`/ia/pro`, ProController@index) tinha charter (Pro.charter.md) mas
 * ZERO teste de contrato — os `Pro*` existentes em Modules/Jana/Tests são de
 * "ProximaPergunta", não desta tela. Fecha o gap com asserção derivada do
 * charter/ADR 0140 (não do código): valores canon são afirmados de forma
 * independente pra que um drift no Controller QUEBRE o teste.
 *
 *  (P1) UC-PRO-01 — rota abre a tela (200 + component 'Jana/Pro')
 *  (P2) UC-PRO-02 — contrato de props: plan/pricing/proof/business
 *  (P3) UC-PRO-03 — preço 49 + trial 14 (ADR 0140)
 *  (P4) UC-PRO-04 — plan 'free' (Sprint A mock, billing é Sprint B)
 *  (P5) UC-PRO-05 — Tier 0: business.id da sessão, ignora ?business_id
 *  (P6) UC-PRO-06 — render idempotente (leitura pura, sem efeito colateral)
 *
 * Padrão dos Controller tests do Jana (PainelControllerTest) + tenant canônico
 * via trait WithSeededTenant (biz=1, ADR 0101) — NUNCA resolução crua de tenant
 * em teste novo (catraca foundation-ratchet n_business_first). Skip gracioso se
 * o seed mínimo não rodou (UPos não migra em SQLite; roda contra MySQL CT 100/CI).
 *
 * O UC-id vive no TÍTULO de cada `it()`, não só neste docblock: o coletor do
 * manifesto por-UC (scripts/casos-results-collect.mjs, G-7 do casos-gate) casa o id
 * pelo atributo `name` do `<testcase>` do JUnit — que é o título. Enquanto os ids
 * ficaram só aqui em cima, os 6 UCs eram `teto_so_docblock` no casos-coverage-guard:
 * satisfaziam o G-2 (citados em algum teste) e eram INALCANÇÁVEIS pelo G-7, então o
 * `🧪 aguarda run verde` do Pro.casos.md não podia virar ✅ nem com a lane verde.
 *
 * ⚠️ ESTE ARQUIVO NÃO ESTÁ EM LANE NENHUMA (medido 2026-08-26) — o id no título é
 * pré-requisito, não suficiente. Ele passa no CT 100 (6 passed, 65 assertions, MySQL
 * real) e REPROVA no `jana-pest.yml` com 6× `GET /ia/pro` devolvendo 403: a DB do CT 100
 * é persistente e tem módulo/permissões da Jana provisionados de runs antigos, enquanto
 * a lane semeia o mínimo (biz=1/biz=2) num banco FRESCO. Quem medir só no CT 100 vai
 * concluir que dá pra ligar — não dá. O bloqueio é o seed, e o `pest-mysql-setup` é
 * compartilhado por 16 lanes, então provisionar a permissão é PR próprio. O recibo
 * completo da medição está no comentário da leva em `.github/workflows/jana-pest.yml`.
 * Enquanto isso, o `test-lane-coverage.mjs` segue acusando este arquivo como órfão — é
 * lá que o buraco aparece, não no teto de título do `casos-coverage-guard`.
 */

function proContratoBootstrap(): User
{
    // Tenant canônico via trait WithSeededTenant (biz=1, skip acionável se seed ausente) —
    // NUNCA resolução crua de tenant em teste novo (catraca foundation-ratchet).
    try {
        $business = test()->seededTenant();
    } catch (\Throwable $e) {
        test()->markTestSkipped('Tabela business indisponível: '.$e->getMessage());
    }

    $user = User::where('business_id', $business->id)->first();

    if (! $user) {
        test()->markTestSkipped("Sem user em business_id={$business->id}.");
    }

    test()->actingAs($user);
    session([
        'user.business_id' => $business->id,
        'user.id'          => $user->id,
        'business'         => ['id' => $business->id, 'name' => $business->name],
    ]);

    return $user;
}

beforeEach(function () {
    $this->user     = proContratoBootstrap();
    $this->business = $this->user->business_id;
});

/** P1 · UC-PRO-01 — rota abre a tela de decisão. */
it('UC-PRO-01 · GET /ia/pro retorna 200 com Inertia component Jana/Pro', function () {
    $response = $this->get('/ia/pro');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page->component('Jana/Pro'));
});

/** P2 · UC-PRO-02 — contrato de props do paywall. */
it('UC-PRO-02 · payload contém o contrato do paywall: plan + pricing + proof + business', function () {
    $response = $this->get('/ia/pro');

    $response->assertInertia(fn ($page) => $page
        ->component('Jana/Pro')
        ->has('plan')
        ->has('pricing.monthly')
        ->has('pricing.trialDays')
        ->has('proof.bruto')
        ->has('proof.liquido')
        ->has('proof.caixa')
        ->has('business.id')
        ->has('business.name')
    );
});

/** P3 · UC-PRO-03 — preço e trial batem o plano comercial (ADR 0140). */
it('UC-PRO-03 · pricing mensal + trial de 14 dias batem o plano comercial (ADR 0140)', function () {
    $response = $this->get('/ia/pro');

    $response->assertInertia(fn ($page) => $page
        ->where('pricing.monthly', 49)
        ->where('pricing.trialDays', 14)
    );
});

/** P4 · UC-PRO-04 — plano atual é 'free' (Sprint A mock; billing é Sprint B). */
it("UC-PRO-04 · plan atual é 'free' (paywall assume Grátis até Sprint JANA-B)", function () {
    $response = $this->get('/ia/pro');

    $response->assertInertia(fn ($page) => $page->where('plan', 'free'));
});

/** P5 · UC-PRO-05 — Tier 0: business da sessão, nunca de input (ADR 0093). */
it('UC-PRO-05 · business.id vem da sessão e ignora ?business_id na URL (Tier 0)', function () {
    $response = $this->get('/ia/pro?business_id=999');

    $response->assertInertia(fn ($page) => $page
        ->where('business.id', $this->business)
    );

    expect($this->business)->not->toBe(999);
});

/** P6 · UC-PRO-06 — render idempotente: leitura pura, sem efeito colateral. */
it('UC-PRO-06 · render é idempotente (sem mutação de estado entre dois GETs)', function () {
    $snapshot = null;
    $this->get('/ia/pro')->assertInertia(function (AssertableInertia $page) use (&$snapshot) {
        $props    = $page->toArray()['props'];
        $snapshot = ['plan' => $props['plan'], 'pricing' => $props['pricing'], 'proof' => $props['proof']];
    });

    $this->get('/ia/pro')->assertInertia(function (AssertableInertia $page) use (&$snapshot) {
        $props = $page->toArray()['props'];
        expect($props['plan'])->toBe($snapshot['plan']);
        expect($props['pricing'])->toBe($snapshot['pricing']);
        expect($props['proof'])->toBe($snapshot['proof']);
    });
});
