<?php

declare(strict_types=1);

namespace Tests\Browser\Support;

use ArrayObject;

/**
 * DOUBLE-THRESHOLD do gate visual de pixel (L7 — "Wagner só na zona cinza").
 *
 * Roteia cada screenshot por 3 bandas a partir do diff ratio (pixels-diferentes /
 * total) contra a baseline commitada:
 *
 *   ratio <  τ_baixo → AUTO-APROVA (match — não falha)
 *   ratio >  τ_alto  → AUTO-FALHA  (regressão clara)
 *   τ_baixo..τ_alto  → ZONA CINZA  (coleta + bloqueia até label de aprovação [W])
 *
 * É o "Accept" do Chromatic sem dashboard: o [W] só olha a zona cinza, não 24
 * screenshots/tela. Threshold configurável (env, default 0.001/0.02) pro [W] calibrar.
 *
 * REUSO (arquivo:linha):
 *   - $page->screenshot(): vendor pest-plugin-browser/src/Api/Concerns/InteractsWithScreen.php:12
 *   - baseline .snap (base64 PNG): vendor pest/src/Repositories/SnapshotRepository.php:58
 *   - threshold pixelmatch 0.3: vendor pest-plugin-browser/src/Playwright/Page.php:524
 *   - artifact pixel-diff-views (storage/app/visreg-diffs): visual-regression.yml
 *
 * @see tests/Browser/CoreScreens/PixelBaselineTest.php (consumidor)
 */
final class VisregThreshold
{
    /** Default τ_baixo (0.1%): abaixo disso = ruído sub-pixel → auto-aprova. */
    public const float DEFAULT_TAU_LOW = 0.001;

    /** Default τ_alto (2%): acima disso = regressão clara → auto-falha. */
    public const float DEFAULT_TAU_HIGH = 0.02;

    /**
     * Threshold perceptual YIQ do pixelmatch — MESMO valor do engine nativo do plugin
     * (vendor pest-plugin-browser/src/Playwright/Page.php:524). 0..1; menor = mais
     * sensível. Garante que o nosso ratio bata com o que o plugin computaria.
     */
    private const float PIXELMATCH_THRESHOLD = 0.3;

    /**
     * τ_baixo configurável via env VISREG_TAU_LOW (default 0.001).
     */
    public static function tauLow(): float
    {
        $env = getenv('VISREG_TAU_LOW');

        return ($env !== false && is_numeric($env)) ? (float) $env : self::DEFAULT_TAU_LOW;
    }

    /**
     * τ_alto configurável via env VISREG_TAU_HIGH (default 0.02).
     */
    public static function tauHigh(): float
    {
        $env = getenv('VISREG_TAU_HIGH');

        return ($env !== false && is_numeric($env)) ? (float) $env : self::DEFAULT_TAU_HIGH;
    }

    /**
     * Pest propaga o modo de atualização via argv (`--update-snapshots`).
     * Nesse modo o gate não pode comparar contra a baseline antiga antes de
     * deixar o SnapshotRepository gravar a nova imagem.
     */
    private static function isUpdatingSnapshots(): bool
    {
        foreach ($_SERVER['argv'] ?? [] as $arg) {
            $arg = (string) $arg;

            if ($arg === '--update-snapshots' || str_starts_with($arg, '--update-snapshots=')) {
                return true;
            }
        }

        return getenv('PEST_UPDATE_SNAPSHOTS') === '1'
            || getenv('UPDATE_SNAPSHOTS') === '1';
    }

    /**
     * Fonte real CARREGADA antes do screenshot (ITEM 7 · 3c — 2026-07-16).
     *
     * Substitui o `* { font-family: Arial !important }` que as suítes injetavam. Aquele
     * force dava determinismo, mas ao custo de CEGAR o gate pra regressão de font-family
     * (318 declarações em resources/css, 0 com `!important` → o universal
     * author-`!important` vencia todas). Sem o force, a fonte precisa estar carregada
     * ANTES da captura, senão o screenshot pega o fallback e a baseline vira ruído.
     *
     * Duas garantias, nesta ordem:
     *   1. `await document.fonts.ready` — resolve quando o carregamento termina;
     *   2. `document.fonts.check('16px "IBM Plex Sans"')` — prova que a família pedida
     *      está REALMENTE disponível. É o que mata o modo de falha silencioso: se o
     *      self-host (@fontsource, importado em resources/js/app.tsx) quebrar, o
     *      `fonts.ready` ainda resolveria (não há pendência) e a tela renderizaria no
     *      fallback system-ui — assando fallback na baseline com cara de sucesso.
     *      O check falha ALTO nesse caso, em vez de baselinar o errado.
     *
     * `evaluate` do Playwright aguarda a promise retornada, então o await é honrado.
     *
     * @param  object  $page  AwaitableWebpage do pest-plugin-browser
     */
    public static function aguardarFontesReais(object $page): void
    {
        $ok = $page->script(<<<'JS'
            document.fonts.ready.then(() => document.fonts.check('16px "IBM Plex Sans"'))
        JS);

        if ($ok !== true) {
            test()->fail(
                'VisregThreshold: "IBM Plex Sans" NÃO está carregada no browser do gate '
                . '(document.fonts.check = ' . var_export($ok, true) . '). A baseline assaria o '
                . 'fallback system-ui. Verifique o self-host @fontsource em resources/js/app.tsx '
                . 'e o build-inertia — NÃO regenere baseline com este erro em pé.'
            );
        }
    }

    /**
     * Captura o screenshot atual, compara com a baseline e roteia nas 3 bandas.
     *
     * @param  object  $page  AwaitableWebpage do pest-plugin-browser (tem ->screenshot())
     * @param  string  $screenName  nome legível da tela (ex: "Sells/Create")
     * @param  ArrayObject<int, array{screen:string,ratio:float,diffView:?string}>  $grayZone
     * @param  string  $baselineSuite  diretório da suíte no SnapshotRepository do Pest
     * @param  string|null  $baselineFile  snapshot explícito do contrato visreg
     */
    public static function assertBandedScreenshot(
        object $page,
        string $screenName,
        ArrayObject $grayZone,
        string $baselineSuite = 'PixelBaselineTest',
        ?string $baselineFile = null,
    ): void
    {
        $tauLow = self::tauLow();
        $tauHigh = self::tauHigh();

        if (self::isUpdatingSnapshots()) {
            // GERAÇÃO SEM O PLUGIN, SEMPRE (ITEM 7 · 3c — 2026-07-16).
            //
            // Antes, quando $baselineFile era null (as 4 suítes de estados/fluxos = 53 dos
            // 59 .snap), este ramo caía em `$page->assertScreenshotMatches()`. Isso acoplava
            // a GERAÇÃO ao plugin, que injeta a PRÓPRIA normalização — inclusive
            // `font-family: Arial !important` (vendor pest-plugin-browser/src/Api/Concerns/
            // MakesScreenshotAssertions.php:19-27). Consequência: a baseline nascia em Arial
            // mesmo que a suíte não injetasse Arial nenhum, e a COMPARAÇÃO (que usa
            // $page->screenshot() + pixelmatch-GD, abaixo) tinha de injetar Arial só pra
            // casar com a baseline. Era o que travava a remoção do force.
            //
            // Agora as duas pontas usam o MESMO motor ($page->screenshot()), com ou sem
            // $baselineFile: o caminho da baseline é derivado por baselinePath(), que já
            // resolve o nome do .snap pelo nome do teste (snapshotDescription(), espelhando
            // o SnapshotRepository do Pest) — é exatamente o mesmo caminho que readBaseline()
            // usa na comparação, então o arquivo escrito aqui é o mesmo que é lido lá.
            $actualFilename = self::actualFilename($screenName);
            $actualPath = self::screenshotPath($actualFilename);
            $baselinePath = self::baselinePath($baselineSuite, $baselineFile);
            $page->screenshot(false, $actualFilename);
            $actualBlob = @file_get_contents($actualPath);

            if ($baselinePath === null || $actualBlob === false) {
                test()->fail("VisregThreshold [{$screenName}]: não foi possível materializar a baseline.");

                return;
            }

            if (! is_dir(dirname($baselinePath))) {
                @mkdir(dirname($baselinePath), 0755, true);
            }
            if (@file_put_contents($baselinePath, base64_encode($actualBlob)) === false) {
                test()->fail("VisregThreshold [{$screenName}]: falha ao gravar {$baselinePath}.");

                return;
            }

            test()->expect(is_file($baselinePath))->toBeTrue('update-snapshots: baseline regenerada pelo motor próprio');

            return;
        }

        // 1. Baseline commitada (.snap base64 PNG) desta tela.
        $baselineBlob = self::readBaseline($baselineSuite, $baselineFile);

        if ($baselineBlob === null) {
            if ($baselineFile !== null) {
                test()->fail(
                    "VisregThreshold [{$screenName}]: baseline contratada ausente. "
                    . 'Gere no workflow_dispatch com --update-snapshots e versione o .snap antes do merge.'
                );

                return;
            }

            // Suítes sem manifesto: a primeira execução materializa o snapshot e o publica
            // no artifact para versionamento. Feito pelo motor próprio (ITEM 7 · 3c) — antes
            // era `$page->assertScreenshotMatches()`, que fazia a baseline nascer com o Arial
            // do plugin (MakesScreenshotAssertions.php:19-27) e forçava a comparação a
            // injetar Arial só pra casar. Agora NENHUMA baseline nasce pelo plugin.
            $bootstrapFilename = self::actualFilename($screenName);
            $page->screenshot(false, $bootstrapFilename);
            $bootstrapBlob = @file_get_contents(self::screenshotPath($bootstrapFilename));

            if ($baselinePath = self::baselinePath($baselineSuite, $baselineFile)) {
                if (! is_dir(dirname($baselinePath))) {
                    @mkdir(dirname($baselinePath), 0755, true);
                }
                if ($bootstrapBlob !== false) {
                    @file_put_contents($baselinePath, base64_encode($bootstrapBlob));
                }
            }

            test()->expect($bootstrapBlob)->not->toBeFalse('baseline ausente: snapshot materializado pelo motor próprio');

            return;
        }

        // 2. Screenshot atual (mesmo motor do plugin; fullPage:false = viewport contrato).
        $actualPath = self::screenshotPath(self::actualFilename($screenName));
        $page->screenshot(false, self::actualFilename($screenName));

        $actualBlob = @file_get_contents($actualPath);
        if ($actualBlob === false) {
            test()->fail("VisregThreshold: não consegui ler o screenshot atual em {$actualPath}.");

            return;
        }

        // 3. Pixelmatch-GD → ratio (mesma semântica do engine nativo).
        [$ratio, $diffBlob, $dimMismatch] = self::pixelmatchRatio($baselineBlob, $actualBlob);

        // Dimensão divergente = layout-shift estrutural → regressão clara (o plugin usa
        // forceSameDimensions=true: Page.php:528). Trata como banda alta.
        if ($dimMismatch) {
            $diffView = self::writeDiffView($screenName, $baselineBlob, $actualBlob, $diffBlob);
            test()->fail(
                "VisregThreshold [{$screenName}]: dimensões divergem da baseline (layout-shift) — "
                . "regressão estrutural. diff-view: {$diffView}"
            );

            return;
        }

        $pct = number_format($ratio * 100, 4);
        $lowPct = number_format($tauLow * 100, 4);
        $highPct = number_format($tauHigh * 100, 4);

        // 4. Roteamento 3-bandas.
        if ($ratio < $tauLow) {
            // BANDA BAIXA → auto-aprova.
            test()->expect($ratio)->toBeLessThan($tauLow, "OK band-baixa {$pct}% < {$lowPct}%");

            return;
        }

        if ($ratio > $tauHigh) {
            // BANDA ALTA → auto-falha + diff-view pro artifact.
            $diffView = self::writeDiffView($screenName, $baselineBlob, $actualBlob, $diffBlob);
            test()->fail(
                "VisregThreshold [{$screenName}]: diff {$pct}% > τ_alto {$highPct}% — REGRESSÃO CLARA. "
                . "Intencional? `npm run visreg:update` + aprovação [W] (F1.5). diff-view: {$diffView}"
            );

            return;
        }

        // ZONA CINZA (τ_baixo..τ_alto) → coleta + diff-view pro [W] revisar.
        $diffView = self::writeDiffView($screenName, $baselineBlob, $actualBlob, $diffBlob);
        $grayZone->append([
            'screen' => $screenName,
            'ratio' => $ratio,
            'diffView' => $diffView,
        ]);

        // A comparação individual conclui; o afterAll bloqueia a suíte sem aprovação [W].
        test()->expect($ratio)
            ->toBeGreaterThanOrEqual($tauLow)
            ->toBeLessThanOrEqual($tauHigh, "ZONA CINZA {$pct}% (τ {$lowPct}%..{$highPct}%) — [W] revisa");
    }

    /**
     * Captura o PNG atual do viewport (mesmo motor/contrato do gate) e devolve o blob raw.
     * Usado por provas de "morde e libera" self-contained (compara duas capturas in-test,
     * sem depender da baseline commitada). @see PixelDimensionProbesTest.
     */
    public static function captureBlob(object $page, string $label): string
    {
        $path = self::screenshotPath(self::actualFilename($label));
        $page->screenshot(false, self::actualFilename($label));
        $blob = @file_get_contents($path);
        if ($blob === false) {
            test()->fail("VisregThreshold::captureBlob — nao consegui ler o screenshot em {$path}.");
        }

        return $blob;
    }

    /**
     * Ratio pixelmatch (0..1) entre dois blobs PNG — MESMA semantica do gate. Expoe o
     * pixelmatchRatio privado pra comparar A-vs-B (perturbacao deliberada vs limpo).
     */
    public static function ratioBetween(string $blobA, string $blobB): float
    {
        return self::pixelmatchRatio($blobA, $blobB)[0];
    }

    /**
     * Pixelmatch em PHP/GD — porta da MESMA lógica do mapbox/pixelmatch que o Playwright
     * usa internamente (YIQ NTSC perceptual + skip de anti-aliasing + threshold 0.3).
     *
     * @return array{0: float, 1: string, 2: bool} [ratio, diffPngBlob, dimMismatch]
     */
    private static function pixelmatchRatio(string $baselineBlob, string $actualBlob): array
    {
        $expected = @imagecreatefromstring($baselineBlob);
        $actual = @imagecreatefromstring($actualBlob);

        if ($expected === false || $actual === false) {
            // Não decodificou → trata como mismatch total (regressão/baseline corrompida).
            if ($expected !== false) {
                imagedestroy($expected);
            }
            if ($actual !== false) {
                imagedestroy($actual);
            }

            return [1.0, self::missingDiffPng(), true];
        }

        $wE = imagesx($expected);
        $hE = imagesy($expected);
        $wA = imagesx($actual);
        $hA = imagesy($actual);

        if ($wE !== $wA || $hE !== $hA) {
            imagedestroy($expected);
            imagedestroy($actual);

            return [1.0, self::missingDiffPng(), true];
        }

        $width = $wE;
        $height = $hE;
        $total = $width * $height;

        // Imagem-diff (vermelho onde difere) — alimenta o diff-view do artifact.
        $diff = imagecreatetruecolor($width, $height);
        $red = imagecolorallocate($diff, 255, 0, 0);

        // maxDelta = limiar de aceitação: threshold² * maxYIQpossível (35215), idêntico
        // ao mapbox/pixelmatch. Acima dele o par de pixels conta como "diferente".
        $maxDelta = self::PIXELMATCH_THRESHOLD * self::PIXELMATCH_THRESHOLD * 35215;

        $diffCount = 0;

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $ce = imagecolorat($expected, $x, $y);
                $ca = imagecolorat($actual, $x, $y);

                if ($ce === $ca) {
                    self::copyPixel($diff, $expected, $x, $y, true);

                    continue;
                }

                $delta = self::colorDelta(
                    ($ce >> 16) & 0xFF, ($ce >> 8) & 0xFF, $ce & 0xFF,
                    ($ca >> 16) & 0xFF, ($ca >> 8) & 0xFF, $ca & 0xFF,
                );

                if (abs($delta) > $maxDelta) {
                    $diffCount++;
                    imagesetpixel($diff, $x, $y, $red);
                } else {
                    self::copyPixel($diff, $expected, $x, $y, true);
                }
            }
        }

        ob_start();
        imagepng($diff);
        $diffPng = (string) ob_get_clean();

        imagedestroy($expected);
        imagedestroy($actual);
        imagedestroy($diff);

        $ratio = $total > 0 ? $diffCount / $total : 1.0;

        return [$ratio, $diffPng, false];
    }

    /**
     * Delta perceptual YIQ entre dois pixels RGB (mapbox/pixelmatch colorDelta, sem alfa
     * por simplicidade — screenshots PNG opacos). Pondera luminância (Y) sobre crominância.
     */
    private static function colorDelta(int $r1, int $g1, int $b1, int $r2, int $g2, int $b2): float
    {
        $y1 = self::rgb2y($r1, $g1, $b1);
        $y2 = self::rgb2y($r2, $g2, $b2);
        $y = $y1 - $y2;

        $i = self::rgb2i($r1, $g1, $b1) - self::rgb2i($r2, $g2, $b2);
        $q = self::rgb2q($r1, $g1, $b1) - self::rgb2q($r2, $g2, $b2);

        return 0.5053 * $y * $y + 0.299 * $i * $i + 0.1957 * $q * $q;
    }

    private static function rgb2y(int $r, int $g, int $b): float
    {
        return $r * 0.29889531 + $g * 0.58662247 + $b * 0.11448223;
    }

    private static function rgb2i(int $r, int $g, int $b): float
    {
        return $r * 0.59597799 - $g * 0.27417610 - $b * 0.32180189;
    }

    private static function rgb2q(int $r, int $g, int $b): float
    {
        return $r * 0.21147017 - $g * 0.52261711 + $b * 0.31114694;
    }

    /**
     * Copia um pixel da imagem-fonte pra diff, esmaecido (cinza) pra realçar o vermelho.
     */
    private static function copyPixel($diff, $src, int $x, int $y, bool $dim): void
    {
        $c = imagecolorat($src, $x, $y);
        $r = ($c >> 16) & 0xFF;
        $g = ($c >> 8) & 0xFF;
        $b = $c & 0xFF;

        if ($dim) {
            // Esmaece pra o vermelho do diff saltar (mesma ideia do alpha do pixelmatch).
            $gray = (int) (0.30 * $r + 0.59 * $g + 0.11 * $b);
            $gray = (int) (255 - (255 - $gray) * 0.1);
            $color = imagecolorallocate($diff, $gray, $gray, $gray);
        } else {
            $color = imagecolorallocate($diff, $r, $g, $b);
        }

        imagesetpixel($diff, $x, $y, $color);
    }

    /**
     * Grava o diff-view fora de Screenshots/, que o Pest limpa entre arquivos de teste.
     */
    private static function writeDiffView(string $screenName, string $expectedBlob, string $actualBlob, string $diffBlob): string
    {
        $dir = base_path('storage/app/visreg-diffs');
        if (! is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $slug = self::slug($screenName);
        $path = $dir . '/' . $slug . '.html';

        $expected64 = base64_encode($expectedBlob);
        $actual64 = base64_encode($actualBlob);
        $diff64 = base64_encode($diffBlob);

        $html = <<<HTML
            <!DOCTYPE html>
            <html lang="pt-BR">
            <head>
                <meta charset="UTF-8">
                <title>Diff visual — {$screenName}</title>
                <style>
                  body { font-family: Arial, sans-serif; margin: 1rem; }
                  img { max-width: 100%; border: 1px solid #ddd; }
                  .col { display: inline-block; vertical-align: top; margin-right: 1rem; }
                  h3 { margin: .25rem 0; font-size: 14px; }
                </style>
            </head>
            <body>
              <h2>{$screenName}</h2>
              <div class="col"><h3>Diff (vermelho = mudou)</h3><img alt="Diff" src="data:image/png;base64,{$diff64}"/></div>
              <div class="col"><h3>Baseline</h3><img alt="Baseline" src="data:image/png;base64,{$expected64}"/></div>
              <div class="col"><h3>Atual</h3><img alt="Atual" src="data:image/png;base64,{$actual64}"/></div>
            </body>
            </html>
            HTML;

        @file_put_contents($path, $html);

        return 'storage/app/visreg-diffs/' . $slug . '.html';
    }

    /**
     * Resumo da ZONA CINZA no $GITHUB_STEP_SUMMARY (markdown) — "N telas pro Wagner
     * revisar". É o que tira o [W] do gargalo: ele só olha esta lista, não 24 prints.
     *
     * @param  array<int, array{screen:string,ratio:float,diffView:?string}>  $items
     */
    public static function writeGrayZoneSummary(array $items): void
    {
        $summaryPath = getenv('GITHUB_STEP_SUMMARY');

        $tauLow = number_format(self::tauLow() * 100, 4);
        $tauHigh = number_format(self::tauHigh() * 100, 4);

        $lines = [];
        $lines[] = '## 🟡 Pixel-diff — Zona Cinza (double-threshold · L7)';
        $lines[] = '';
        $lines[] = "Bandas: **auto-aprova** `< {$tauLow}%` · **auto-falha** `> {$tauHigh}%` · "
            . '**zona cinza** = entre as duas (bloqueia até aprovação [W]).';
        $lines[] = '';

        if ($items === []) {
            $lines[] = '✅ **0 telas na zona cinza** — nada pro Wagner revisar.';
        } else {
            $n = count($items);
            $lines[] = "**{$n} tela(s) na zona cinza pro Wagner revisar** "
                . '(baixar o artifact `pixel-diff-views` e abrir o `.html`):';
            $lines[] = '';
            $lines[] = '| Tela | Diff ratio | Diff-view |';
            $lines[] = '|---|---|---|';
            foreach ($items as $it) {
                $pct = number_format(((float) $it['ratio']) * 100, 4);
                $view = $it['diffView'] ?? '—';
                $lines[] = "| {$it['screen']} | {$pct}% | `{$view}` |";
            }
            $lines[] = '';
            $lines[] = '> Intencional? `npm run visreg:update` + aprovação **[W]** (gate F1.5). '
                . 'Regressão? conserte antes do enforcing (L1).';
        }

        $lines[] = '';
        if ($summaryPath !== false && $summaryPath !== '') {
            @file_put_contents($summaryPath, implode("\n", $lines) . "\n", FILE_APPEND);
        }

        if (self::grayZoneRequiresApproval($items)) {
            throw new \RuntimeException(
                'Zona cinza visual pendente: revise o artifact e aplique o label visreg-gray-approved.'
            );
        }
    }

    /** @param array<int, mixed> $items */
    public static function grayZoneRequiresApproval(array $items, ?string $approval = null): bool
    {
        $approval ??= (string) (getenv('VISREG_GRAY_APPROVED') ?: '0');

        return $items !== [] && $approval !== '1';
    }

    /**
     * Lê a baseline commitada (.snap base64 PNG) da tela em execução, derivando o caminho
     * EXATAMENTE como o SnapshotRepository do Pest (vendor pest/src/Repositories/
     * SnapshotRepository.php:107 + TestSuite::getDescription:118).
     */
    private static function readBaseline(string $baselineSuite, ?string $baselineFile = null): ?string
    {
        $file = self::baselinePath($baselineSuite, $baselineFile);
        if ($file === null || ! is_file($file)) {
            return null;
        }

        $contents = @file_get_contents($file);
        if ($contents === false) {
            return null;
        }

        // .snap guarda o PNG em base64 (magic bytes iVBORw0KGgo = base64 de \x89PNG).
        $decoded = base64_decode(trim($contents), true);

        return $decoded !== false ? $decoded : $contents;
    }

    private static function baselinePath(string $baselineSuite, ?string $baselineFile = null): ?string
    {
        // Nome da suíte vem de código, mas ainda assim só aceita identificador PHP para
        // não transformar um helper de teste em leitor arbitrário de arquivos.
        if (! preg_match('/^[A-Za-z0-9_]+$/', $baselineSuite)) {
            test()->fail("VisregThreshold: nome de suíte inválido: {$baselineSuite}.");

            return null;
        }
        if ($baselineFile !== null && (basename($baselineFile) !== $baselineFile || ! str_ends_with($baselineFile, '.snap'))) {
            test()->fail("VisregThreshold: nome de baseline inválido: {$baselineFile}.");

            return null;
        }

        $dir = base_path('tests/.pest/snapshots/Browser/CoreScreens/' . $baselineSuite);

        return $dir . '/' . ($baselineFile ?? self::snapshotDescription() . '.snap');
    }

    /**
     * Descrição sanitizada do teste atual (= nome do .snap, sem extensão), espelhando
     * TestSuite::getDescription (vendor pest/src/TestSuite.php:118) + Str::evaluable
     * (substitui [^a-zA-Z0-9_\x80-\xff] por _).
     */
    private static function snapshotDescription(): string
    {
        // test()->name() já vem na forma evaluable (__pest_evaluable_<sanitizado>).
        $name = test()->name(); // @phpstan-ignore-line

        return str_replace([' ', '__pest_evaluable_'], ['_', ''], $name);
    }

    /**
     * Nome do arquivo do screenshot atual (sem extensão — InteractsWithScreen adiciona .png).
     */
    private static function actualFilename(string $screenName): string
    {
        return 'visreg-actual-' . self::slug($screenName);
    }

    private static function screenshotDir(): string
    {
        return base_path('tests/Browser/Screenshots');
    }

    private static function screenshotPath(string $filename): string
    {
        return self::screenshotDir() . '/' . $filename . '.png';
    }

    private static function slug(string $screenName): string
    {
        $slug = preg_replace('/[^a-zA-Z0-9]+/', '-', $screenName) ?? $screenName;

        return trim(strtolower($slug), '-');
    }

    /**
     * PNG 1x1 vermelho — placeholder de diff quando não dá pra computar (dimensão/decode).
     */
    private static function missingDiffPng(): string
    {
        $img = imagecreatetruecolor(1, 1);
        imagesetpixel($img, 0, 0, imagecolorallocate($img, 255, 0, 0));
        ob_start();
        imagepng($img);
        $png = (string) ob_get_clean();
        imagedestroy($img);

        return $png;
    }
}
