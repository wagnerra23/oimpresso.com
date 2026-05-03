<?php

declare(strict_types=1);

use Modules\ADS\Services\ConfidenceEngine;

// ARQ-0005 — ConfidenceEngine: lógica pura sem DB (computeDelta + HiTL)

it('computeDelta retorna positivo para success', function () {
    $engine = new ConfidenceEngine();
    expect($engine->computeDelta('success'))->toBe(1.0);
});

it('computeDelta retorna -1.5 para fail', function () {
    $engine = new ConfidenceEngine();
    expect($engine->computeDelta('fail'))->toBe(-1.5);
});

it('computeDelta retorna -0.5 para wagner_modified com diff pequeno (<=30%)', function () {
    $engine = new ConfidenceEngine();
    expect($engine->computeDelta('wagner_modified', 20))->toBe(-0.5);
    expect($engine->computeDelta('wagner_modified', 30))->toBe(-0.5);
});

it('computeDelta aplica peso 3x para wagner_modified com diff > 30%', function () {
    $engine = new ConfidenceEngine();
    // base -0.5 * 3.0 = -1.5
    expect($engine->computeDelta('wagner_modified', 50))->toBe(-1.5);
    expect($engine->computeDelta('wagner_modified', 31))->toBe(-1.5);
});

it('computeDelta retorna zero para cancelled e expired', function () {
    $engine = new ConfidenceEngine();
    expect($engine->computeDelta('cancelled'))->toBe(0.0);
    expect($engine->computeDelta('expired'))->toBe(0.0);
});

it('wagner_rejected tem penalidade maior que fail', function () {
    $engine = new ConfidenceEngine();
    expect($engine->computeDelta('wagner_rejected'))->toBeLessThan($engine->computeDelta('fail'));
});

it('score calculado a partir de success sobe o score inicial', function () {
    $engine = new ConfidenceEngine();
    $delta   = $engine->computeDelta('success');
    $newScore = max(0.0, min(1.0, 0.50 + ($delta * 0.05)));

    expect($newScore)->toBeGreaterThan(0.5);
    expect($newScore)->toBeLessThanOrEqual(1.0);
});

it('score calculado a partir de wagner_rejected cai abaixo do inicial', function () {
    $engine = new ConfidenceEngine();
    $delta   = $engine->computeDelta('wagner_rejected');
    $newScore = max(0.0, min(1.0, 0.50 + ($delta * 0.05)));

    expect($newScore)->toBeLessThan(0.5);
    expect($newScore)->toBeGreaterThanOrEqual(0.0);
});
