<?php

declare(strict_types=1);

use Modules\ADS\Services\RiskEngine;

uses(Tests\TestCase::class);

// ARQ-0004 — RiskEngine: fórmula + priors + zonas

it('eventos BLOCK_ALWAYS têm risco >= 0.9', function (string $eventType) {
    $engine = new RiskEngine();
    $result = $engine->calculate($eventType);

    expect($result->score)->toBeGreaterThanOrEqual(0.9);
    expect($result->zone())->toBe('red');
})->with([
    'env_production',
    'append_only_table',
    'delphi_contract',
]);

it('eventos triviais têm risco < 0.05', function (string $eventType) {
    $engine = new RiskEngine();
    $result = $engine->calculate($eventType);

    expect($result->score)->toBeLessThan(0.05);
    expect($result->zone())->toBeIn(['green', 'yellow']);
})->with([
    'lang_file_pt_br',
    'adr_frontmatter_fix',
    'md_link_fix',
    'session_log_creation',
    'comment_typo',
]);

it('score está sempre entre 0 e 1', function (string $eventType) {
    $engine = new RiskEngine();
    $result = $engine->calculate($eventType);

    expect($result->score)->toBeGreaterThanOrEqual(0.0);
    expect($result->score)->toBeLessThanOrEqual(1.0);
})->with([
    'env_production',
    'db_schema_change',
    'service_layer_refactor',
    'blade_view_ui_only',
    'lang_file_pt_br',
    'evento_desconhecido',
]);

it('evento desconhecido usa prior conservador e não retorna zero', function () {
    $engine = new RiskEngine();
    $result = $engine->calculate('evento_nunca_visto_123');

    expect($result->score)->toBeGreaterThan(0.0);
    expect($result->usedPrior)->toBeTrue();
});

it('calibrateUncertainty reduz risco com histórico de sucesso', function () {
    $engine = new RiskEngine();

    $semHistorico = $engine->calculate('service_layer_refactor');
    $incertezaCalibrada = $engine->calibrateUncertainty('service_layer_refactor', 1.0);
    $comHistorico = $engine->calculate('service_layer_refactor', $incertezaCalibrada);

    expect($comHistorico->score)->toBeLessThanOrEqual($semHistorico->score);
    expect($incertezaCalibrada)->toBeGreaterThan(0.0); // nunca chega a zero
});

it('calibrateUncertainty nunca retorna zero mesmo com 100% de sucesso', function () {
    $engine = new RiskEngine();
    $calibrated = $engine->calibrateUncertainty('lang_file_pt_br', 1.0);

    expect($calibrated)->toBeGreaterThan(0.0);
});

it('zona verde é risco < 0.20', function () {
    $engine = new RiskEngine();
    $result = $engine->calculate('lang_file_pt_br');

    expect($result->isGreen())->toBeTrue();
    expect($result->isRed())->toBeFalse();
});

it('zona vermelha é risco >= 0.70', function () {
    $engine = new RiskEngine();
    $result = $engine->calculate('env_production');

    expect($result->isRed())->toBeTrue();
    expect($result->isGreen())->toBeFalse();
});
