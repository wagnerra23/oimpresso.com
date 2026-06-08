<?php

declare(strict_types=1);

use Modules\ADS\Services\PatternLearningService;

// T15 — Wilson Score Interval Lower Bound (95% confidence)
// (uses() já declarado em PolicyEngineTest.php — Pest valida 1× por diretório)

it('Wilson LB retorna 0 quando total=0', function () {
    $svc = new PatternLearningService();
    expect($svc->wilsonLowerBound(0, 0))->toBe(0.0);
});

it('Wilson LB de 3/3 NÃO chega a 0.80 (evita promoção por ruído)', function () {
    $svc = new PatternLearningService();
    $lb = $svc->wilsonLowerBound(3, 3);

    expect($lb)->toBeGreaterThan(0.0);
    expect($lb)->toBeLessThan(0.80); // crítico: 100% naïve não promove
    expect($lb)->toBeGreaterThan(0.40); // mas não é zero — tem confiança
});

it('Wilson LB cresce com mais amostras com mesma taxa', function () {
    $svc = new PatternLearningService();

    $small = $svc->wilsonLowerBound(8, 10);   // 80%
    $med   = $svc->wilsonLowerBound(80, 100); // 80%
    $large = $svc->wilsonLowerBound(800, 1000); // 80%

    expect($small)->toBeLessThan($med);
    expect($med)->toBeLessThan($large);
});

it('Wilson LB de 80/100 é confiável mas abaixo do gate 0.80', function () {
    $svc = new PatternLearningService();
    $lb = $svc->wilsonLowerBound(80, 100);

    expect($lb)->toBeGreaterThan(0.65); // sólido
    expect($lb)->toBeLessThan(0.80); // mas não passa do gate de promoção
});

it('Wilson LB de 1/10 é muito baixo — não promove', function () {
    $svc = new PatternLearningService();
    expect($svc->wilsonLowerBound(1, 10))->toBeLessThan(0.10);
});

it('isPromotionCandidate retorna false para n < 10 mesmo com 100% sucesso', function () {
    $svc = new PatternLearningService();
    $pattern = (object) [
        'success_count' => 9,
        'total_count'   => 9,
        'is_hardcoded'  => false,
    ];
    expect($svc->isPromotionCandidate($pattern))->toBeFalse();
});

it('isPromotionCandidate retorna true para wilson >= 0.80 com n >= 10', function () {
    $svc = new PatternLearningService();
    $pattern = (object) [
        'success_count' => 50,
        'total_count'   => 50,
        'is_hardcoded'  => false,
    ];
    // 50/50 → wilson_lb = ?
    $lb = $svc->wilsonLowerBound(50, 50);
    expect($lb)->toBeGreaterThan(0.92);
    expect($svc->isPromotionCandidate($pattern))->toBeTrue();
});

it('isPromotionCandidate retorna false se já hardcoded', function () {
    $svc = new PatternLearningService();
    $pattern = (object) [
        'success_count' => 100,
        'total_count'   => 100,
        'is_hardcoded'  => true, // já é regra Policy
    ];
    expect($svc->isPromotionCandidate($pattern))->toBeFalse();
});
