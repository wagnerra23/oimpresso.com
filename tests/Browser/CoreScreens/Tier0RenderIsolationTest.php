<?php

declare(strict_types=1);

/**
 * Pest 4 Browser — GATE DE ISOLAMENTO MULTI-TENANT NO RENDER (L3 / Tier 0, princípio #1).
 *
 * O QUE PROVA (o buraco que o Model/SQL-only deixava aberto):
 *   O isolamento `business_id` (ADR 0093 IRREVOGÁVEL) era asserido só no Model/SQL. Mas a
 *   tela RENDERIZADA podia vazar dado de outro tenant por um caminho que o scope-test não vê:
 *   há ~21 `withoutGlobalScope`/`DB::table` em app/Http/Controllers/ (5 no SellController —
 *   ex. as subqueries de transaction_payments/transactions cruas) que NÃO passam pelo global
 *   scope. Este gate visita a tela como admin do biz=1 no Chromium real e prova que o dado
 *   do biz=99 (token sentinela `ZZLEAK99`) NÃO aparece — nem no DOM visível, nem no payload cru.
 *
 * DOIS LADOS (testar o teste — PORTÃO 4 / L-31 "todo ✅ tem que ter sido visto falhar"):
 *   1. CONTROLE (sensibilidade): logado como admin do biz=99, /cliente DEVE mostrar
 *      `ZZLEAK99` (assertSee). Se não mostrar, o gate é VÁCUO — o token nunca renderiza e o
 *      assertDontSee do lado 2 passaria por nada.
 *   2. ISOLAMENTO (o gate): logado como admin do biz=1, as telas que renderizam dado de
 *      contato/transação NÃO podem mostrar `ZZLEAK99` (assertDontSee).
 *
 * A PROBE DO PAYLOAD CRU (o coração — por que assertDontSee não basta):
 *   `assertDontSee` checa só TEXTO VISÍVEL (getByText + isVisible — ver
 *   vendor/pestphp/pest-plugin-browser/src/Api/Concerns/MakesElementAssertions.php:68).
 *   Um vazamento pode estar em PROPS NÃO-RENDERIZADAS: o atributo `data-page` do Inertia
 *   (#app[data-page] = JSON com TODOS os props que o controller passou — resources/views/
 *   layouts/inertia.blade.php @inertia), ou em atributos/markup oculto. Por isso a probe lê
 *   o HTML CRU via ->content() E o atributo data-page via ->script(): se o token estiver no
 *   JSON dos props mas escondido do olho, ESTE gate pega, o assertDontSee não pegaria.
 *
 *   ->content() (vendor .../Playwright/Page.php:219) devolve o DOM VIVO serializado (estado
 *   atual pós-hidratação), não o body HTTP inicial — então captura inclusive linhas que
 *   chegam por Inertia::defer (ex. `customers` em /cliente é deferred). data-page reflete os
 *   props da navegação. LIMITAÇÃO HONESTA: props deferred resolvem por partial-reload e o
 *   plugin não expõe API pra esperar o defer especificamente — content()/assertDontSee já
 *   esperam networkidle (wait()), o que cobre o caso comum; se um defer demorar além do
 *   networkidle a probe é melhor-esforço (não há método nativo melhor no plugin v4).
 *
 * PADRÃO ESPELHADO: tests/Browser/CoreScreens/AuthBridgeSmokeTest.php (cross-process DB +
 *   /_visreg-login/{id}?to=<rota>) e ConformanceProbesTest.php (->script()/->content()).
 *   Convenção biz: biz=1 self, biz=99 adversário sentinela, NUNCA biz=4 (ADR 0101).
 *
 * ⚠️ HONESTIDADE (ADR 0108 + hook block-test-fora-ct100): NÃO rodado local — Pest Browser só
 *   roda no CI (visual-regression.yml, chromium garantido) ou no CT 100. Validado por:
 *   (a) php -l, (b) espelhamento do AuthBridge/ConformanceProbes que já rodam verdes,
 *   (c) API ->content()/->script() confirmada no fonte do plugin v4 (Webpage.php:59/85).
 *
 * @see database/seeders/VisregTenantBLeakSeeder.php (seeda o biz=99 + token ZZLEAK99)
 * @see tests/Browser/CoreScreens/AuthBridgeSmokeTest.php (harness auth-bridge espelhado)
 * @see .github/workflows/visual-regression.yml (gate que invoca — BLOCKING, sem continue-on-error)
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md (princípio #1 IRREVOGÁVEL)
 */

use App\Business;
use App\User;
use Database\Seeders\VisregTenantBLeakSeeder;

const LEAK_TOKEN = VisregTenantBLeakSeeder::LEAK_TOKEN; // 'ZZLEAK99'

beforeEach(function () {
    // CROSS-PROCESS DB (igual AuthBridge/A11yAxe/ConformanceProbes): o browser (subprocesso)
    // usa MySQL (.env, schema-squash), mas o test process usa sqlite :memory: (phpunit.xml).
    // Realinha o test process pro MESMO MySQL pra enxergar os tenants seedados.
    config([
        'database.default' => 'mysql',
        'database.connections.mysql.database' => 'oimpresso_test',
    ]);
    \Illuminate\Support\Facades\DB::purge('mysql');

    \Carbon\Carbon::setTestNow('2026-06-23 12:00:00');
});
afterEach(fn () => \Carbon\Carbon::setTestNow());

/**
 * Resolve o admin de um business seedado ou skip-graceful (mesmo idioma do AuthBridge).
 */
function tier0AdminFor(int $businessId): ?User
{
    $business = Business::find($businessId);
    if (! $business) {
        return null;
    }

    return User::where('business_id', $business->id)->orderBy('id')->first();
}

/**
 * Probe do PAYLOAD CRU — procura o token no HTML cru E no atributo data-page do Inertia.
 * Retorna ['inContent' => bool, 'inDataPage' => bool] pra a assertion documentar ONDE vazou.
 *
 * data-page: #app[data-page] carrega o JSON de TODOS os props do controller (o que
 * assertDontSee — só texto visível — deixa passar). content(): HTML cru completo da página.
 */
function tier0RawLeakProbe($page, string $token): array
{
    $content = (string) $page->content();

    // Lê o atributo data-page diretamente do DOM (props Inertia serializados em JSON).
    // Se #app não existir (tela não-Inertia) o script devolve '' — probe degrada gracioso.
    $dataPage = (string) $page->script(
        "(() => { const el = document.getElementById('app'); return el ? (el.getAttribute('data-page') || '') : ''; })()"
    );

    return [
        'inContent' => str_contains($content, $token),
        'inDataPage' => str_contains($dataPage, $token),
    ];
}

// ── LADO 1 — CONTROLE (sensibilidade): o token RENDERIZA quando DEVE (biz=99 vê o próprio) ──
it('CONTROLE — admin do biz=99 VÊ o token sentinela em /cliente (senão o gate é vácuo)', function () {
    $admin = tier0AdminFor(VisregTenantBLeakSeeder::BIZ_B);
    if (! $admin) {
        test()->markTestSkipped('Tenant-B (biz=99) não seedado — rode VisregTenantBLeakSeeder.');
    }

    $page = visit('/_visreg-login/' . $admin->id . '?to=' . urlencode('/cliente'));

    // O token renderiza no DOM visível: prova que o seed funciona e que assertSee/DontSee
    // têm semântica real nesta tela. Gate vácuo = bug silencioso (L-31).
    $page->assertSee(LEAK_TOKEN);

    // E está no payload cru (data-page) — confirma que a probe sabe achar quando há vazamento.
    $probe = tier0RawLeakProbe($page, LEAK_TOKEN);
    expect($probe['inContent'])->toBeTrue('CONTROLE: token sentinela ausente do HTML cru do dono — seed/probe inválidos');
});

// ── LADO 2 — ISOLAMENTO (o gate): admin do biz=1 NÃO PODE ver dado do biz=99 ──────────────
/**
 * Tela => rota. Telas que renderizam dado de contato/transação por business_id. Telas de
 * módulo opcional que derem 403/redirect no CI saem daqui com nota (não bloqueiam as demais),
 * igual o AuthBridgeSmokeTest faz com as telas gateadas por install/enabled_modules.
 */
$telas = [
    'Clientes'             => '/cliente',
    'Vendas/Lista'         => '/sells',
    'Compras'              => '/compras',
    'Financeiro/Unificado' => '/financeiro/unificado',
];

foreach ($telas as $nome => $rota) {
    it("ISOLAMENTO — {$nome} NÃO vaza o token do biz=99 pro admin do biz=1 (DOM + payload cru)", function () use ($rota) {
        $admin = tier0AdminFor(1);
        if (! $admin) {
            test()->markTestSkipped('Tenant self (biz=1) não seedado — rode VisregTenantSeeder.');
        }
        // Pré-condição: o adversário tem que existir, senão não há o que vazar (gate vácuo).
        if (! Business::find(VisregTenantBLeakSeeder::BIZ_B)) {
            test()->markTestSkipped('Tenant-B (biz=99) não seedado — rode VisregTenantBLeakSeeder.');
        }

        $page = visit('/_visreg-login/' . $admin->id . '?to=' . urlencode($rota));

        // Gate A — DOM visível: o token do outro tenant não pode aparecer pro olho.
        $page->assertDontSee(LEAK_TOKEN);

        // Gate B — PAYLOAD CRU (o coração): o token não pode estar nos props Inertia
        // (data-page) nem em qualquer markup oculto do HTML. assertDontSee (só texto
        // visível) deixaria passar um vazamento em prop não-renderizada.
        $probe = tier0RawLeakProbe($page, LEAK_TOKEN);
        expect($probe['inDataPage'])->toBeFalse(
            "Tier 0 VIOLADO: token do biz=99 vazou no data-page (props Inertia) de {$rota}"
        );
        expect($probe['inContent'])->toBeFalse(
            "Tier 0 VIOLADO: token do biz=99 vazou no HTML cru de {$rota}"
        );
    });
}
