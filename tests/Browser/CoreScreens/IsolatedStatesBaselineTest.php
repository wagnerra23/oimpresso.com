<?php

declare(strict_types=1);

/**
 * Pest 4 Browser — GATE L2: SNAPSHOTS DE ESTADOS ISOLADOS (empty/loading/dark/error por tela).
 *
 * O BURACO QUE FECHA (maior gap de cobertura visual):
 *   O PixelBaselineTest (L7) so snapshota o estado SEEDADO de cada tela (default). Os estados
 *   ORTOGONAIS ao dado — `empty`, `loading`, `dark`, `error` — NUNCA eram snapshotados, entao
 *   regressao neles (skeleton quebrado, dark-mode com contraste perdido, empty-state sumido,
 *   toast de erro estourado) passava batido. Este gate gera 1 baseline de pixel POR
 *   (tela × estado declarado), travando esses estados.
 *
 * COMO O ESTADO E FORCADO (sem tocar controller nem Page — mesmo principio do L3):
 *   Cada teste visita /_visreg-state/{tela}/{estado} (routes/web.php, env-guarded), que loga
 *   o admin do tenant certo + grava a flag de estado, e o VisregStateMiddleware aplica o lever
 *   deterministico (dark = ui_theme em memoria; loading = congela o Inertia::defer; empty =
 *   tenant vazio biz=98; error = flash → toast). Detalhe de cada lever:
 *   @see app/Http/Middleware/VisregStateMiddleware.php
 *   @see routes/web.php (rota /_visreg-state)
 *
 * FONTE UNICA tela→{rota,ancora,estados}: tests/Browser/visreg-states.json (o MESMO JSON que a
 *   rota e o scripts/visreg-states-lint.mjs leem — o lint falha se charter `states:` divergir
 *   deste manifesto). NÃO ha mapa hardcoded aqui — evita o drift que o lint existe pra pegar.
 *
 * BASELINE v2 = VisregThreshold (o mesmo double-threshold L7 das telas núcleo): ruído abaixo de
 * τ_baixo aprova, regressão acima de τ_alto falha e a zona cinza produz um diff-view navegável.
 * Isso remove o flake conhecido do assert binário sem esconder regressão clara. A primeira execução
 * ainda usa assertScreenshotMatches para materializar a .snap que o artifact permite versionar.
 *
 * Mesmo harness auth-bridge cross-process do AuthBridgeSmokeTest/PixelBaselineTest (visit
 * /_visreg-state... e tudo numa visit so). Carbon::setTestNow pra matar flakiness de datas.
 * Skip-graceful: sem seed, ou tela que da 403/redirect (modulo opcional), o (tela,estado) sai
 * com markTestSkipped — nao falha o gate das demais combinacoes (idioma do AuthBridgeSmokeTest).
 *
 * ⚠️ HONESTIDADE (ADR 0108 + hook block-test-fora-ct100): NÃO rodado local — Pest Browser so
 *   roda no CI (visual-regression.yml, chromium garantido) ou no CT 100. Validado por: (a)
 *   php -l, (b) espelhamento do PixelBaselineTest/AuthBridgeSmokeTest (harness que ja roda
 *   verde), (c) mecanica do Inertia::defer/partial conferida no fonte (PropsResolver.php).
 *   VALIDA NO CT 100/CI.
 *
 * @see .github/workflows/visual-regression.yml (step advisory que invoca este teste)
 * @see tests/Browser/CoreScreens/PixelBaselineTest.php (gate L7 — estado default/nucleo-6)
 * @see tests/Browser/CoreScreens/AuthBridgeSmokeTest.php (harness auth-bridge espelhado)
 */

use App\Business;
use App\User;
use Database\Seeders\VisregEmptyTenantSeeder;

$grayZone = new \ArrayObject();

afterAll(function () use ($grayZone) {
    \Tests\Browser\Support\VisregThreshold::writeGrayZoneSummary($grayZone->getArrayCopy());
});

beforeEach(function () {
    // CROSS-PROCESS DB (igual PixelBaseline/AuthBridge): o browser (subprocesso) usa MySQL
    // (.env, schema-squash), mas o test process usa sqlite :memory: (phpunit.xml). Realinha
    // o test process pro MESMO MySQL pra enxergar os tenants seedados.
    config([
        'database.default' => 'mysql',
        'database.connections.mysql.database' => 'oimpresso_test',
    ]);
    \Illuminate\Support\Facades\DB::purge('mysql');

    \Carbon\Carbon::setTestNow('2026-06-11 12:00:00');
});

afterEach(fn () => \Carbon\Carbon::setTestNow());

/**
 * Le o manifesto (FONTE UNICA) e expande em pares (tela, estado). Tela => [slug, rota, ancora,
 * estado]. Falha cedo e barulhento se o JSON sumir/corromper (gate vacuo = bug silencioso).
 *
 * @return array<string, array{0:string,1:string,2:string,3:string}>
 */
function isolatedStatesCases(): array
{
    // dirname(__DIR__) = tests/Browser (este arquivo vive em tests/Browser/CoreScreens/).
    // NÃO usar base_path() aqui: esta função roda na COLETA do Pest (top-level do arquivo),
    // ANTES do app Laravel bootar — `app()` é só um Container cru sem ->basePath(), entao
    // base_path() dá "Call to undefined method Container::basePath()" e o arquivo inteiro
    // erra na coleta (0 testes, 0 baselines → gate vácuo). __DIR__ resolve sem o app.
    $path = dirname(__DIR__) . '/visreg-states.json';
    $manifest = json_decode((string) @file_get_contents($path), true);

    if (! is_array($manifest) || ! isset($manifest['screens']) || ! is_array($manifest['screens'])) {
        throw new RuntimeException("visreg-states.json ausente/invalido em {$path} — gate L2 sem fonte.");
    }

    $cases = [];
    foreach ($manifest['screens'] as $slug => $screen) {
        $rota = (string) ($screen['route'] ?? '');
        $ancora = (string) ($screen['anchor'] ?? '');
        foreach ((array) ($screen['states'] ?? []) as $estado) {
            $label = "{$slug} · estado={$estado}";
            $cases[$label] = [(string) $slug, $rota, $ancora, (string) $estado];
        }
    }

    return $cases;
}

foreach (isolatedStatesCases() as $label => [$slug, $rota, $ancora, $estado]) {
    it("{$label} bate com a baseline isolada", function () use ($slug, $ancora, $estado, $grayZone) {
        // Tenant exigido pelo estado: empty usa o biz=98 (VisregEmptyTenantSeeder); os demais
        // usam o biz=1 (VisregTenantSeeder). Sem o seed → skip (nao falha), igual AuthBridge.
        $businessId = $estado === 'empty' ? VisregEmptyTenantSeeder::BIZ_EMPTY : 1;

        $business = Business::find($businessId);
        if (! $business) {
            test()->markTestSkipped("Tenant biz={$businessId} nao seedado pro estado '{$estado}'.");
        }
        $admin = User::where('business_id', $businessId)->orderBy('id')->first();
        if (! $admin) {
            test()->markTestSkipped("Sem admin no biz={$businessId} pro estado '{$estado}'.");
        }

        // 1 visit: a rota loga o admin certo + grava a flag de estado + redireciona pra tela.
        $page = visit('/_visreg-state/' . $slug . '/' . $estado);

        // Prova que a tela montou (nao caiu em 403/login/erro).
        $page->assertSee($ancora);

        // ESTABILIZACAO (identica ao PixelBaselineTest — runs 27370651063/27370956421):
        // transitions/animations off (mata early-paint), fonte Arial normalizada +
        // antialiasing, e controles nativos (select/date/time) com visibility:hidden
        // (pintam com variancia subpixel run-a-run).
        $page->script(<<<'JS'
            (() => {
              const s = document.createElement('style');
              s.textContent = `
                * { transition: none !important; animation: none !important; font-family: Arial, sans-serif !important; }
                body { -webkit-font-smoothing: antialiased !important; -moz-osx-font-smoothing: grayscale !important; }
                select, input[type=date], input[type=datetime-local], input[type=time] { visibility: hidden !important; }
              `;
              document.head.appendChild(s);
              return true;
            })()
        JS);
        $page->wait(1.5);

        // fullPage:false — as baselines commitadas foram capturadas assim, e o
        // VisregThreshold:175-183 trata dimensão divergente como regressão estrutural.
        // NÃO é "varia com o seed": medido 2026-07-16, os 6 seeders Visreg têm 0 ocorrência
        // de faker|Faker|rand(|random|uniqid|Str::random. A baseline é por (tela,estado).
        //
        // Esta suíte NÃO passa `baselineFile` → em --update-snapshots a baseline nasce via
        // assertScreenshotMatches do plugin (VisregThreshold:132), que injeta o próprio
        // Arial (MakesScreenshotAssertions.php:19-27); na comparação usa o pixelmatch-GD
        // (:159-171). Por isso a injeção de Arial acima (:131-142) é load-bearing aqui —
        // diferente das 6 do PixelBaselineTest, que são auto-consistentes.
        \Tests\Browser\Support\VisregThreshold::assertBandedScreenshot(
            page: $page,
            screenName: "{$slug} · estado={$estado}",
            grayZone: $grayZone,
            baselineSuite: 'IsolatedStatesBaselineTest',
        );
    });
}
