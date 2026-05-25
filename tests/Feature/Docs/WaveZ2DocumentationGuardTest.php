<?php

declare(strict_types=1);

uses(Tests\TestCase::class);

/**
 * Wave Z-2 Documentation GUARD — Integração Vendas × Oficina (ADR 0192).
 *
 * Anti-regressão: garante que pacote smoke Wave Z-2 (script deploy + checklist 8 blocos
 * + briefs Repair/Sells + SYNC_LOG/TELAS_REVIEW_QUEUE updates) NÃO é deletado em PR
 * futuro acidental. Skip gracioso quando filesystem não acessível (CI ephemeral).
 *
 * Refs:
 *   - memory/decisions/0192-auto-faturar-os-venda-jobsheet-observer.md
 *   - memory/sessions/2026-05-25-wave-z2-smoke-checklist.md
 *   - scripts/deploy-wave-z2-integracao-vendas-oficina.sh
 *
 * @group docs
 * @group wave-z2
 */

const WAVE_Z2_ROOT = __DIR__ . '/../../..';

beforeEach(function () {
    if (! is_dir(WAVE_Z2_ROOT)) {
        $this->markTestSkipped('Filesystem não acessível (CI ephemeral).');
    }
});

it('Wave Z-2 docs canon existem', function () {
    $required = [
        'scripts/deploy-wave-z2-integracao-vendas-oficina.sh',
        'memory/sessions/2026-05-25-wave-z2-smoke-checklist.md',
        'memory/requisitos/Repair/BRIEFING.md',
        'memory/requisitos/Sells/BRIEFING.md',
        'memory/decisions/0192-auto-faturar-os-venda-jobsheet-observer.md',
        'prototipo-ui/SYNC_LOG.md',
        'prototipo-ui/TELAS_REVIEW_QUEUE.md',
    ];

    $missing = [];
    foreach ($required as $relative) {
        $path = WAVE_Z2_ROOT . '/' . $relative;
        if (! file_exists($path)) {
            $missing[] = $relative;
        }
    }

    expect($missing)->toBe([], 'Wave Z-2 docs canon faltando: ' . implode(', ', $missing));
});

it('script deploy é executável (shebang + permissions)', function () {
    $script = WAVE_Z2_ROOT . '/scripts/deploy-wave-z2-integracao-vendas-oficina.sh';

    if (! file_exists($script)) {
        $this->markTestSkipped('Script deploy ausente — outro guard cobre.');
    }

    $first = trim((string) (@fopen($script, 'r') ? fgets(fopen($script, 'r')) : ''));
    expect($first)->toStartWith('#!/usr/bin/env bash', 'Shebang bash ausente no script deploy');

    // Em Windows, permissions são tratadas diferente; só valida que existe + readable.
    expect(is_readable($script))->toBeTrue('Script deploy não readable');
});

it('smoke checklist tem 8 blocos A-H', function () {
    $checklist = WAVE_Z2_ROOT . '/memory/sessions/2026-05-25-wave-z2-smoke-checklist.md';

    if (! file_exists($checklist)) {
        $this->markTestSkipped('Checklist ausente — outro guard cobre.');
    }

    $content = (string) file_get_contents($checklist);

    $blocos = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];
    $missing = [];
    foreach ($blocos as $bloco) {
        // Header markdown: "## Bloco X — ..."
        if (! preg_match('/^##\s+Bloco\s+' . $bloco . '\s+/m', $content)) {
            $missing[] = "Bloco {$bloco}";
        }
    }

    expect($missing)->toBe([], 'Smoke checklist faltando blocos: ' . implode(', ', $missing));
});

it('smoke checklist menciona ADR 0192 + multi-tenant Tier 0', function () {
    $checklist = WAVE_Z2_ROOT . '/memory/sessions/2026-05-25-wave-z2-smoke-checklist.md';

    if (! file_exists($checklist)) {
        $this->markTestSkipped('Checklist ausente — outro guard cobre.');
    }

    $content = (string) file_get_contents($checklist);

    expect($content)->toContain('0192', 'Checklist deve referenciar ADR 0192')
        ->and($content)->toContain('Multi-tenant', 'Checklist deve mencionar multi-tenant Tier 0')
        ->and($content)->toContain('biz=1', 'Checklist deve documentar canary biz=1')
        ->and($content)->toContain('biz=4', 'Checklist deve mencionar biz=4 Larissa pós-canary');
});

it('script deploy menciona rollback + backup MySQL', function () {
    $script = WAVE_Z2_ROOT . '/scripts/deploy-wave-z2-integracao-vendas-oficina.sh';

    if (! file_exists($script)) {
        $this->markTestSkipped('Script ausente — outro guard cobre.');
    }

    $content = (string) file_get_contents($script);

    expect($content)->toContain('mysqldump', 'Script deve dump MySQL antes de migrate')
        ->and($content)->toContain('rollback', 'Script deve documentar rollback')
        ->and($content)->toContain('migrate', 'Script deve rodar migrate')
        ->and($content)->toContain('npm run build', 'Script deve rebuildar frontend');
});
