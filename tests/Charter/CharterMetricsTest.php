<?php

declare(strict_types=1);

/**
 * Pest agregador pra M2 (GUARD pass rate) + M3 (charter coverage Tier A).
 *
 * Roda em CI via charter-gate.yml workflow. Em modo soft (F1), CI continua
 * verde mesmo se este test falha — comenta no PR mas não bloqueia merge.
 *
 * Spec em memory/sprints/s6-charter-capterra/16-six-metrics-spec.md.
 *
 * Cobertura:
 *   - M2 Tier 1 GUARD — todo charter tem 8 chaves frontmatter + 8 seções + ❌ em Non-Goals
 *   - M3 Coverage Tier A — todas as 5 telas Tier A têm *.charter.md ao lado
 *
 * Stubs honestos:
 *   - M1 (token economy) — depende telemetria mcp_audit_log
 *   - M4 (goal drift) — depende telemetria
 *   - M5 (detector latency) — vem de GH Action timing
 *   - M6 (anti-hallucination ratchet) — depende baseline + ramp-up
 *
 * @see memory/sprints/s6-charter-capterra/17-pest-aggregators.md
 */

use Symfony\Component\Yaml\Yaml;

const TIER_A_TELAS = [
    'resources/js/Pages/Repair/Dashboard',
    'resources/js/Pages/Repair/JobSheet',
    'resources/js/Pages/Financeiro/Extrato',
    'resources/js/Pages/Repair/Status',
    'resources/js/Pages/Financeiro/ContasBancarias',
];

const FRONTMATTER_OBRIGATORIO = [
    'page', 'component', 'owner', 'status',
    'last_validated', 'parent_module', 'tier', 'charter_version',
];

const SECOES_OBRIGATORIAS = [
    'Mission', 'Goals', 'Non-Goals', 'UX Targets',
    'UX Anti-patterns', 'Automation Hooks',
    'Automation Anti-hooks', 'Métricas vivas',
];

/**
 * @return array<int, array{path: string, content: string}>
 */
function chartersDescobertos(): array
{
    $base = realpath(__DIR__.'/../../resources/js/Pages');
    if ($base === false || ! is_dir($base)) {
        return [];
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS)
    );

    $out = [];
    foreach ($iterator as $file) {
        if ($file->isFile() && str_ends_with($file->getFilename(), '.charter.md')) {
            $out[] = [
                'path' => $file->getPathname(),
                'content' => (string) file_get_contents($file->getPathname()),
            ];
        }
    }
    return $out;
}

function parseFrontmatter(string $raw): array
{
    if (! preg_match('/^---\n(.+?)\n---/s', $raw, $m)) {
        return [];
    }
    try {
        return (array) Yaml::parse($m[1]);
    } catch (Throwable) {
        return [];
    }
}

function parseSecoes(string $raw): array
{
    preg_match_all('/^## (.+)$/m', $raw, $m);
    return array_map('trim', $m[1] ?? []);
}

// =============================================================
// M3 — Charter coverage Tier A
// =============================================================

it('M3 — todas as 5 telas Tier A têm charter ao lado de Index.tsx', function () {
    foreach (TIER_A_TELAS as $dir) {
        $chartersDir = base_path($dir);
        $matches = glob($chartersDir.'/*.charter.md') ?: [];

        expect(count($matches))->toBeGreaterThanOrEqual(
            1,
            'Tela Tier A sem charter: '.$dir
        );
    }
});

// =============================================================
// M2 — Tier 1 GUARD por charter
// =============================================================

it('M2 — todo charter tem 8 chaves obrigatórias no frontmatter', function () {
    $charters = chartersDescobertos();
    expect(count($charters))->toBeGreaterThan(0, 'Nenhum charter descoberto');

    foreach ($charters as $c) {
        $front = parseFrontmatter($c['content']);
        $faltando = array_diff(FRONTMATTER_OBRIGATORIO, array_keys($front));

        expect($faltando)->toBe(
            [],
            "Charter {$c['path']} faltando chaves: ".implode(', ', $faltando)
        );
    }
});

it('M2 — todo charter tem 8 seções obrigatórias', function () {
    $charters = chartersDescobertos();

    foreach ($charters as $c) {
        $secoes = parseSecoes($c['content']);
        $faltando = array_diff(SECOES_OBRIGATORIAS, $secoes);

        expect($faltando)->toBe(
            [],
            "Charter {$c['path']} faltando seções: ".implode(', ', $faltando)
        );
    }
});

it('M2 — todo Non-Goal tem prefixo ❌ na seção correspondente', function () {
    $charters = chartersDescobertos();

    foreach ($charters as $c) {
        if (! preg_match('/^## Non-Goals.*?\n(.*?)(?=^## )/sm', $c['content'], $m)) {
            continue;
        }

        $bloco = $m[1];
        $bullets = preg_match_all('/^- /m', $bloco, $unused) ?: 0;
        $prefixados = preg_match_all('/^- ❌/m', $bloco, $unused) ?: 0;

        expect($prefixados)->toBe(
            $bullets,
            "Charter {$c['path']} Non-Goals: {$bullets} bullets, {$prefixados} com ❌"
        );
    }
});
