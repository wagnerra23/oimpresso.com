<?php

declare(strict_types=1);

/**
 * Pest 4 Browser — GATE VISUAL DE PIXEL das telas núcleo-6 (Onda Q4 · US-GOV-013).
 *
 * DOUBLE-THRESHOLD (L7 — "Wagner só na zona cinza"):
 * --------------------------------------------------------------------------------
 * O gate ANTES era binário: `assertScreenshotMatches()` do pest-plugin-browser
 * (pixelmatch nativo) ou bate (PASS) ou não-bate (FAIL), sem nuance — e o [W]
 * aprovava TUDO no olho (gargalo: 24 screenshots/tela). Este teste roteia cada
 * screenshot por 3 bandas (o "Accept" do Chromatic, sem dashboard):
 *
 *   - diff ratio  <  τ_baixo (0.1%) → AUTO-APROVA  (trata como match — não falha)
 *   - diff ratio  >  τ_alto  (2%)   → AUTO-FALHA   (regressão clara — falha o teste)
 *   - τ_baixo..τ_alto               → ZONA CINZA   (NÃO falha; coleta a tela, sobe o
 *                                      diff-view dela pro artifact `pixel-diff-views`
 *                                      e emite "N telas pro Wagner revisar" no
 *                                      $GITHUB_STEP_SUMMARY)
 *
 * τ_baixo/τ_alto são CONFIGURÁVEIS (env `VISREG_TAU_LOW`/`VISREG_TAU_HIGH`, default
 * 0.001/0.02) pro Wagner calibrar sem mexer no código. Por-arquétipo (form mais
 * apertado que lista) fica pra v2 — aqui é threshold global por design (KISS).
 *
 * POR QUE PIXELMATCH-EM-PHP (e não `assertScreenshotMatches`):
 *   O plugin computa o diff DENTRO do cliente Playwright (RPC `expectScreenshot`,
 *   vendor pest-plugin-browser/src/Playwright/Page.php:505-530) e só devolve
 *   pass/fail + a imagem-diff — NUNCA o ratio numérico. Sem o ratio não há 3 bandas.
 *   Então: (a) capturamos o PNG atual via `$page->screenshot()` (mesmo motor de
 *   screenshot do plugin), (b) lemos a baseline COMMITADA do `.snap` (raw PNG em
 *   base64 — convenção SnapshotRepository), (c) rodamos pixelmatch em GD com a MESMA
 *   semântica do plugin (YIQ NTSC perceptual + skip de anti-aliasing + threshold 0.3 —
 *   vendor Page.php:523-527). O ratio = pixels-diferentes / total.
 *
 * REUSO (arquivo:linha):
 *   - motor de screenshot: $page->screenshot() → vendor pest-plugin-browser/src/Api/
 *     Concerns/InteractsWithScreen.php:12 (salva PNG em tests/Browser/Screenshots/).
 *   - baseline store: tests/.pest/snapshots/Browser/CoreScreens/PixelBaselineTest/
 *     *.snap (base64 PNG — vendor pest/src/Repositories/SnapshotRepository.php:58).
 *   - parâmetros pixelmatch: threshold 0.3 (vendor Page.php:524).
 *   - artifact `pixel-diff-views`: .github/workflows/visual-regression.yml:256-263
 *     (sobe tests/Browser/Screenshots/ImageDiffView/ — onde gravamos o diff-view da
 *     zona cinza + regressão).
 *   - convenção de update de baseline: `npm run visreg:update` (package.json:73 —
 *     `pest PixelBaselineTest --update-snapshots`), aprovação [W] (gate F1.5).
 *
 * ACOPLAMENTO COM O L1 (flip enforcing): este L7 é o que torna SEGURO promover o
 * pixel-diff a enforcing — sem a zona cinza, enforcing false-falharia em todo diff
 * pequeno. O step segue ADVISORY (`continue-on-error` em visual-regression.yml:244 —
 * decisão de promoção do [W], NÃO mexida aqui). L1 (remover o continue-on-error) vem
 * DEPOIS de 2 runs verdes + aprovação [W].
 *
 * O contrato executável (source Inertia → rota → âncora → baseline) vive em
 * tests/Browser/visreg-screens.json. Em diff targeted, VISREG_SCREENS seleciona
 * exatamente as telas afetadas; em diff global, a suíte inteira é executada.
 *
 * ⚠️ HONESTIDADE (ADR 0108 + hook block-test-fora-ct100): NÃO rodado local — Pest
 * Browser só roda no CI (visual-regression.yml, chromium garantido) ou no CT 100.
 * Validado por: (a) `php -l` sintaxe, (b) espelhamento do AuthBridgeSmokeTest (harness
 * auth-bridge que já roda verde), (c) GD `imagecreatefromstring` confirmado no fonte
 * do plugin (`comparisonMethod pixelmatch`). VALIDA NO CT 100/CI.
 *
 * @see .github/workflows/visual-regression.yml (job pixel-diff + artifact pixel-diff-views)
 * @see tests/Browser/CoreScreens/AuthBridgeSmokeTest.php (harness auth-bridge)
 * @see vendor/pestphp/pest-plugin-browser/src/Playwright/Page.php:505 (engine nativo)
 */

use App\Business;
use App\User;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    config([
        'database.default' => 'mysql',
        'database.connections.mysql.database' => 'oimpresso_test',
    ]);
    \Illuminate\Support\Facades\DB::purge('mysql');

    \Carbon\Carbon::setTestNow('2026-06-11 12:00:00');
});

afterEach(fn () => \Carbon\Carbon::setTestNow());

/**
 * Acumulador da ZONA CINZA — telas cujo diff caiu entre τ_baixo e τ_alto. Não falham
 * o teste, mas o afterAll emite o resumo "N telas pro Wagner revisar" no step summary.
 *
 * Static via closure-bind no escopo do arquivo: Pest roda os `it()` no mesmo processo,
 * então o array sobrevive entre os testes desta suíte.
 */
$grayZone = new ArrayObject();

afterAll(function () use ($grayZone) {
    \Tests\Browser\Support\VisregThreshold::writeGrayZoneSummary($grayZone->getArrayCopy());
});

$manifestPath = dirname(__DIR__) . '/visreg-screens.json';
$screens = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
$scope = getenv('VISREG_SCOPE') ?: 'global';
$requested = json_decode(getenv('VISREG_SCREENS') ?: '[]', true, 512, JSON_THROW_ON_ERROR);

if ($scope === 'targeted') {
    $screens = array_values(array_filter(
        $screens,
        static fn (array $screen): bool => in_array($screen['source'] ?? '', $requested, true),
    ));
    $resolved = array_column($screens, 'source');
    $missing = array_values(array_diff($requested, $resolved));
    if ($missing !== []) {
        throw new RuntimeException('Telas sem contrato visreg: ' . implode(', ', $missing));
    }
}

foreach ($screens as $screen) {
    foreach (['screen', 'source', 'route', 'anchor', 'baseline'] as $required) {
        if (! isset($screen[$required]) || ! is_string($screen[$required]) || $screen[$required] === '') {
            throw new RuntimeException("Contrato visreg inválido: campo {$required} ausente.");
        }
    }

    $nome = $screen['screen'];
    $source = $screen['source'];
    $rota = $screen['route'];
    $ancora = $screen['anchor'];
    $baseline = $screen['baseline'];

    it("{$nome} bate com a baseline de pixel (núcleo-6)", function () use ($nome, $source, $rota, $ancora, $baseline, $grayZone) {
        $business = Business::first();
        if (! $business) {
            test()->markTestSkipped('Sem business seedado (VisregTenantSeeder não rodou).');
        }
        $admin = User::where('business_id', $business->id)->orderBy('id')->first();
        if (! $admin) {
            test()->markTestSkipped('Sem user no business seedado.');
        }

        $this->actingAs($admin)
            ->get($rota)
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component($source));

        $page = visit('/_visreg-login/' . $admin->id . '?to=' . urlencode($rota));
        $page->assertSee($ancora);

        // ESTABILIZAÇÃO (diagnóstico runs 27370651063/27370956421 — diff views):
        // (a) controles NATIVOS (select / input date|datetime|time) pintam com variação
        //     subpixel run-a-run E carregam valores vivos (Data da venda = agora) →
        //     visibility:hidden preserva o layout e zera a variância;
        // (b) settle explícito mata o early-paint (baseline de 2KB com "?" de fonte
        //     não carregada que o networkidle+readyState do plugin não pegou);
        // (c) MESMA normalização visual do plugin (transitions/animations off + fonte
        //     Arial + antialiasing) pra o nosso ratio bater com o do engine nativo —
        //     vendor pest-plugin-browser/src/Api/Concerns/MakesScreenshotAssertions.php:20.
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

        // CLASSIFICAÇÃO 3-BANDAS — substitui o assertScreenshotMatches() binário.
        // O helper:
        //   1. captura o PNG atual via $page->screenshot() (fullPage:false — viewport é o
        //      contrato visual estável; full em lista longa varia com o seed),
        //   2. lê a baseline commitada (.snap base64) desta tela,
        //   3. roda pixelmatch-GD (mesma semântica do plugin) → ratio,
        //   4. roteia: <τ_baixo APROVA · >τ_alto FALHA · meio = ZONA CINZA (coleta).
        \Tests\Browser\Support\VisregThreshold::assertBandedScreenshot(
            page: $page,
            screenName: $nome,
            grayZone: $grayZone,
            baselineFile: $baseline,
        );
    });
}
