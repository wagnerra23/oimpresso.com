<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Modules\Arquivos\Entities\Arquivo;
use Modules\Arquivos\Services\Curador\CuradorEngine;

uses(Tests\TestCase::class);

beforeEach(function () {
    // CI SQLite :memory: — pula gracioso se migrate não criou tabela arquivos.
    // CuradorEngine é pura logic mas Arquivo Model resolve casts/global scope
    // ao instanciar, e isso pode tocar Schema metadata.
    if (! Schema::hasTable('arquivos')) {
        $this->markTestSkipped('Tabela arquivos ausente — rode migrate Modules/Arquivos primeiro.');
    }
});

/**
 * ParityTest JS×PHP — US-ARQ-007.
 *
 * Lê fixtures geradas por `node scripts/curador/parity-fixtures.mjs` em
 * `tests/Fixtures/CuradorParity/fixtures.json` (output do classifyFile JS)
 * e compara com CuradorEngine PHP.
 *
 * Regras:
 * - rule_matched DEVE bater exatamente
 * - bucket pode divergir SE ambos cairam em fallback (`no_rule_matched`):
 *   JS retorna `ambiguous`, PHP retorna `active` — INTENCIONAL (uso diferente
 *   nos runtimes; JS = "review", PHP = "anexo comum")
 *
 * Pra regenerar fixtures: `node scripts/curador/parity-fixtures.mjs`
 *
 * @see scripts/curador/lib/rules.mjs (fonte de verdade JS)
 * @see Modules/Arquivos/Services/Curador/CuradorEngine.php (port PHP)
 */

const FIXTURES_PATH = __DIR__ . '/../../../../tests/Fixtures/CuradorParity/fixtures.json';

function loadParityFixtures(): array
{
    $path = realpath(FIXTURES_PATH);
    if (! $path || ! is_file($path)) {
        throw new RuntimeException(
            'fixtures.json ausente em tests/Fixtures/CuradorParity/. ' .
            'Rode: node scripts/curador/parity-fixtures.mjs'
        );
    }
    $raw = file_get_contents($path);
    return json_decode($raw, true) ?? [];
}

function buildArquivoFromFixture(array $input): Arquivo
{
    return new Arquivo([
        'original_name' => $input['basename'],
        'storage_path'  => $input['path'],
        'size_bytes'    => (int) $input['sizeBytes'],
        'mime_type'     => 'application/octet-stream',
        'md5'           => $input['md5'],
    ]);
}

it('fixtures.json existe (rode parity-fixtures.mjs se faltar)', function () {
    $fixtures = loadParityFixtures();
    expect($fixtures)->toBeArray()->not->toBeEmpty();
    expect(count($fixtures))->toBeGreaterThanOrEqual(20);
});

it('parity rate >= 95% — rule_matched JS==PHP em todos casos não-fallback', function () {
    $fixtures = loadParityFixtures();
    $engine = new CuradorEngine();
    $total = 0;
    $matched = 0;
    $diffs = [];

    foreach ($fixtures as $name => $fix) {
        $arquivo = buildArquivoFromFixture($fix['input']);
        $php = $engine->classify($arquivo);
        $jsRule = $fix['expected']['ruleMatched'] ?? '';
        $phpRule = $php['rule_matched'] ?? '';

        $total++;

        // Equivalência: ambos no_rule_matched OK (mesmo bucket diferente)
        if ($jsRule === 'no_rule_matched' && $phpRule === 'no_rule_matched') {
            $matched++;
            continue;
        }

        if ($jsRule === $phpRule) {
            $matched++;
        } else {
            $diffs[] = "  {$name}: JS={$jsRule} PHP={$phpRule}";
        }
    }

    $rate = $total > 0 ? ($matched / $total) * 100 : 0;
    $msg = sprintf(
        'parity %.1f%% (%d/%d)%s',
        $rate,
        $matched,
        $total,
        count($diffs) > 0 ? "\n" . implode("\n", $diffs) : '',
    );

    expect($rate)->toBeGreaterThanOrEqual(95.0, $msg);
});

it('paridade fixtures sensitive — todas devem retornar bucket=sensitive', function () {
    $fixtures = loadParityFixtures();
    $engine = new CuradorEngine();
    $errors = [];

    foreach ($fixtures as $name => $fix) {
        if (($fix['expected']['bucket'] ?? '') !== 'sensitive') continue;

        $php = $engine->classify(buildArquivoFromFixture($fix['input']));
        if ($php['bucket'] !== 'sensitive') {
            $errors[] = "  {$name}: PHP bucket={$php['bucket']} esperado=sensitive";
        }
    }

    expect($errors)->toBeEmpty(implode("\n", $errors));
});

it('paridade fixtures discard — todas devem retornar bucket=discard', function () {
    $fixtures = loadParityFixtures();
    $engine = new CuradorEngine();
    $errors = [];

    foreach ($fixtures as $name => $fix) {
        if (($fix['expected']['bucket'] ?? '') !== 'discard') continue;

        $php = $engine->classify(buildArquivoFromFixture($fix['input']));
        if ($php['bucket'] !== 'discard') {
            $errors[] = "  {$name}: PHP bucket={$php['bucket']} esperado=discard";
        }
    }

    expect($errors)->toBeEmpty(implode("\n", $errors));
});

it('paridade fixtures memory — todas devem retornar bucket=memory', function () {
    $fixtures = loadParityFixtures();
    $engine = new CuradorEngine();
    $errors = [];

    foreach ($fixtures as $name => $fix) {
        if (($fix['expected']['bucket'] ?? '') !== 'memory') continue;

        $php = $engine->classify(buildArquivoFromFixture($fix['input']));
        if ($php['bucket'] !== 'memory') {
            $errors[] = "  {$name}: PHP bucket={$php['bucket']} esperado=memory";
        }
    }

    expect($errors)->toBeEmpty(implode("\n", $errors));
});
