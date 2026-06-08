<?php

declare(strict_types=1);

use Modules\Governance\Contracts\DriftChecker;
use Modules\Governance\Services\Checkers\MeilisearchSettingsDriftChecker;
use Modules\Governance\Services\DriftFinding;

uses(Tests\TestCase::class);

/**
 * MeilisearchSettingsDriftChecker (ADR 0216) — detecta drift de embedder/filterable
 * do índice. Nasce porque o embedder do Meilisearch se perdeu 2× (recall degrada em
 * silêncio). Plugado no framework DriftChecker em vez de comando bespoke (não reinventar).
 *
 * `driftsDoIndice` é pura → testável sem bater no Meilisearch live.
 */

$cfg = [
    'embedders' => ['qwen3_local' => ['source' => 'ollama', 'model' => 'qwen3-embedding:0.6b', 'dimensions' => 1024]],
    'filterableAttributes' => ['status', 'type'],
];

it('implementa o contrato DriftChecker (plugável no framework, não bespoke)', function () {
    expect(new MeilisearchSettingsDriftChecker())->toBeInstanceOf(DriftChecker::class)
        ->and((new MeilisearchSettingsDriftChecker())->name())->toBe('meilisearch_settings_drift')
        ->and((new MeilisearchSettingsDriftChecker())->severity())->toBe('high');
});

it('está registrado em governance.drift_checkers (roda no governance:audit)', function () {
    expect((array) config('governance.drift_checkers'))
        ->toContain(MeilisearchSettingsDriftChecker::class);
});

it('settings vivos == config → sem finding', function () use ($cfg) {
    $vivo = ['embedders' => $cfg['embedders'], 'filterableAttributes' => ['type', 'status']];
    expect((new MeilisearchSettingsDriftChecker())->driftsDoIndice('x', $cfg, $vivo))->toBeEmpty();
});

it('embedder VAZIO (o bug recorrente) → finding high', function () use ($cfg) {
    $f = (new MeilisearchSettingsDriftChecker())->driftsDoIndice('jana_memoria_facts', $cfg, ['embedders' => [], 'filterableAttributes' => ['status', 'type']]);
    expect($f)->toHaveCount(1)
        ->and($f[0])->toBeInstanceOf(DriftFinding::class)
        ->and($f[0]->severity)->toBe('high')
        ->and($f[0]->message)->toContain("embedder 'qwen3_local' AUSENTE");
});

it('model divergente (ex openai/nomic) → finding', function () use ($cfg) {
    $vivo = ['embedders' => ['qwen3_local' => ['source' => 'ollama', 'model' => 'nomic-embed-text', 'dimensions' => 1024]], 'filterableAttributes' => ['status', 'type']];
    $f = (new MeilisearchSettingsDriftChecker())->driftsDoIndice('x', $cfg, $vivo);
    expect($f)->toHaveCount(1)->and($f[0]->message)->toContain('.model divergente');
});

it('filterableAttributes diferente → finding', function () use ($cfg) {
    $f = (new MeilisearchSettingsDriftChecker())->driftsDoIndice('x', $cfg, ['embedders' => $cfg['embedders'], 'filterableAttributes' => ['status']]);
    expect($f)->toHaveCount(1)->and($f[0]->message)->toContain('filterableAttributes divergente');
});
