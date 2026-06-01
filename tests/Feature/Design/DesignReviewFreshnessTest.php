<?php

declare(strict_types=1);

// Tests\TestCase já é aplicado globalmente em tests/Pest.php (uses(TestCase::class)->in('Feature')).
// NÃO redeclarar aqui — Pest 4 lança TestCaseAlreadyInUse.

/**
 * DesignReviewFreshnessTest — gate de FRESCOR do review por tela.
 *
 * Resposta direta ao [W] (COWORK_NOTES → "Gerador design:review", "qual teste/automatização?"):
 * toda `resources/js/Pages/<Mod>/<Tela>.tsx` com charter `status: live` PRECISA ter um
 * `<Tela>.review.md` ao lado (o "relatório de tarefas por tela" = charter page viva). Tela
 * nova nascendo SEM review = a falha que pegou o Jana/Pro (criado no #2069 sem review).
 *
 * Espelha em PHP a lógica de `prototipo-ui/audit/review-freshness.mjs` (que roda local como
 * evidência; o Pest roda no CT 100 — feedback #2076). Teste PURO de arquivo: lê disco + assert,
 * SEM banco/business_id/rede (mesmo padrão de DesignLedgerIntegrityTest / DesignIndexSingleSourceTest).
 *
 * Ratchet (espelha config/eslint-baseline.json · ADR 0209): a dívida HERDADA (telas live sem
 * review em 2026-06-01) vive em `review-freshness-baseline.json`. O gate só FALHA por uma tela
 * NOVA fora do baseline (anti-regressão), nunca pela dívida herdada — e o baseline só ENCOLHE.
 *
 * `stale` (review existe mas `measured_against_sha` != sha do último commit do .tsx) é ADVISORY
 * na v1 (reviews legados de 2026-05-17 não têm o campo) → checado pelo .mjs, vira HARD aqui quando
 * regenerados (proposta de ADR de evolução do loop).
 *
 * Refs:
 *   - prototipo-ui/audit/review-gen.mjs · review-freshness.mjs · review-freshness-baseline.json
 *   - prototipo-ui/audit/GOLDEN-REFERENCE.md · score-mechanized.mjs (Fase 1)
 *   - memory/decisions/proposals/design-review-por-tela-charter-page.md (evolução do loop · mãe 0114/0236/0239)
 *
 * @group docs
 * @group design
 */

const REVIEW_PAGES_REL = 'resources/js/Pages';
const REVIEW_BASELINE_REL = 'prototipo-ui/audit/review-freshness-baseline.json';

function reviewPagesDir(): string
{
    return base_path(REVIEW_PAGES_REL);
}

/**
 * Enumera as telas com charter `status: live` + paths irmãos (.tsx / .review.md).
 * Só telas cujo .tsx EXISTE entram (charter órfã sem tela = fora de escopo deste gate).
 *
 * @return array<array{screen:string, tsx:string, review:string}>
 */
function reviewLiveCharters(): array
{
    $dir = reviewPagesDir();
    if (! is_dir($dir)) {
        return [];
    }
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
    );
    $out = [];
    foreach ($it as $f) {
        if (! $f->isFile() || ! str_ends_with($f->getFilename(), '.charter.md')) {
            continue;
        }
        $src = (string) file_get_contents($f->getPathname());
        // só telas live (frontmatter `status: live`).
        if (! preg_match('/^status:\s*live\s*$/m', $src)) {
            continue;
        }
        $base = substr($f->getFilename(), 0, -strlen('.charter.md'));
        $screenDir = $f->getPath();
        $tsx = $screenDir . DIRECTORY_SEPARATOR . $base . '.tsx';
        if (! is_file($tsx)) {
            continue; // charter sem tela — não é caso deste gate
        }
        $screen = str_replace('\\', '/', substr($screenDir . DIRECTORY_SEPARATOR . $base, strlen($dir) + 1));
        $out[] = [
            'screen' => $screen,
            'tsx' => $tsx,
            'review' => $screenDir . DIRECTORY_SEPARATOR . $base . '.review.md',
        ];
    }
    usort($out, fn ($a, $b) => strcmp($a['screen'], $b['screen']));

    return $out;
}

/**
 * Carrega o baseline-ratchet (dívida herdada de reviews ausentes).
 *
 * @return array{missing:list<string>}
 */
function reviewBaseline(): array
{
    $f = base_path(REVIEW_BASELINE_REL);
    if (! is_file($f)) {
        return ['missing' => []];
    }
    $j = json_decode((string) file_get_contents($f), true);

    return is_array($j) && isset($j['missing']) && is_array($j['missing'])
        ? ['missing' => $j['missing']]
        : ['missing' => []];
}

beforeEach(function () {
    // Skip gracioso quando o filesystem do repo não está acessível (CI ephemeral) —
    // mesmo padrão de DesignIndexSingleSourceTest / WaveZ2DocumentationGuardTest.
    if (! is_dir(reviewPagesDir())) {
        $this->markTestSkipped('resources/js/Pages não acessível (CI ephemeral).');
    }
});

// ─── SANIDADE — prova que a varredura roda (anti-verde-vacuo) ─────────────────

it('SANIDADE: há telas live + baseline + scripts do gerador existem', function () {
    expect(count(reviewLiveCharters()))->toBeGreaterThan(
        0,
        'Nenhuma charter `status: live` encontrada — a varredura quebrou (os HARD passariam vacuamente).',
    );
    expect(is_file(base_path(REVIEW_BASELINE_REL)))->toBeTrue(
        'review-freshness-baseline.json ausente — sem baseline o ratchet não funciona.',
    );
    expect(is_file(base_path('prototipo-ui/audit/review-gen.mjs')))->toBeTrue('review-gen.mjs (gerador) ausente.');
    expect(is_file(base_path('prototipo-ui/audit/review-freshness.mjs')))->toBeTrue('review-freshness.mjs (gate node) ausente.');
});

// ─── (HARD) toda tela live tem review — ou está no baseline herdado ───────────

it('(HARD) toda tela live tem .review.md (ou está no baseline herdado)', function () {
    $baselineMissing = reviewBaseline()['missing'];
    $newMissing = [];
    foreach (reviewLiveCharters() as $c) {
        if (is_file($c['review'])) {
            continue;
        }
        if (in_array($c['screen'], $baselineMissing, true)) {
            continue; // dívida herdada, grandfathered no ratchet
        }
        $newMissing[] = $c['screen'];
    }
    expect($newMissing)->toBe(
        [],
        'Tela(s) live SEM `.review.md` e FORA do baseline (regressão — tela nova nasceu sem relatório):'
        . PHP_EOL . '  - ' . implode(PHP_EOL . '  - ', $newMissing)
        . PHP_EOL . 'Gere com: node prototipo-ui/audit/review-gen.mjs <Mod/Tela> '
        . '(depois `--write-baseline` pra podar o ratchet).',
    );
});

// ─── (REGRESSÃO) o 1º caso do gerador (Jana/Pro) fica fechado de verdade ──────

it('(REGRESSÃO) Jana/Pro tem review real e NÃO está grandfathered no baseline', function () {
    $tsx = reviewPagesDir() . '/Jana/Pro.tsx';
    if (! is_file($tsx)) {
        $this->markTestSkipped('Jana/Pro.tsx não existe nesta branch — nada a checar.');
    }
    expect(is_file(reviewPagesDir() . '/Jana/Pro.review.md'))->toBeTrue(
        'Jana/Pro.review.md ausente — o 1º caso de teste do gerador `design:review` regrediu.',
    );
    expect(reviewBaseline()['missing'])->not->toContain(
        'Jana/Pro',
        'Jana/Pro foi parar no baseline (grandfathered) em vez de ter review gerado de verdade.',
    );
});

// ─── (RATCHET) baseline só encolhe — nenhuma entrada já-resolvida sobra ───────

it('(RATCHET) nenhuma entrada do baseline já tem review (o ratchet só encolhe)', function () {
    $stillBaselined = [];
    foreach (reviewBaseline()['missing'] as $screen) {
        if (is_file(reviewPagesDir() . '/' . $screen . '.review.md')) {
            $stillBaselined[] = $screen;
        }
    }
    expect($stillBaselined)->toBe(
        [],
        'Baseline lista tela(s) que JÁ têm `.review.md` — o ratchet não foi podado:'
        . PHP_EOL . '  - ' . implode(PHP_EOL . '  - ', $stillBaselined)
        . PHP_EOL . 'Rode: node prototipo-ui/audit/review-freshness.mjs --write-baseline',
    );
});
