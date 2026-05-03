<?php

declare(strict_types=1);

use Modules\ADS\Services\GovernanceRulesService;

// Meta-skills DSL — evaluator de condições JSON
// (uses() já declarado em PolicyEngineTest.php)

it('avalia leaf simples == true', function () {
    $svc = new GovernanceRulesService();
    $cond = ['field' => 'enabled', 'op' => '==', 'value' => true];
    expect($svc->evaluate($cond, ['enabled' => true]))->toBeTrue();
    expect($svc->evaluate($cond, ['enabled' => false]))->toBeFalse();
});

it('avalia operadores numéricos', function () {
    $svc = new GovernanceRulesService();

    expect($svc->evaluate(['field' => 'score', 'op' => '>=', 'value' => 0.8],   ['score' => 0.8]))->toBeTrue();
    expect($svc->evaluate(['field' => 'score', 'op' => '>=', 'value' => 0.8],   ['score' => 0.79]))->toBeFalse();
    expect($svc->evaluate(['field' => 'score', 'op' => '<',  'value' => 0.5],   ['score' => 0.4]))->toBeTrue();
    expect($svc->evaluate(['field' => 'score', 'op' => '!=', 'value' => 1.0],   ['score' => 0.9]))->toBeTrue();
});

it('avalia AND aninhado — todos verdadeiros', function () {
    $svc = new GovernanceRulesService();
    $cond = [
        'op' => 'AND',
        'conds' => [
            ['field' => 'wilson_lower_bound', 'op' => '>=', 'value' => 0.80],
            ['field' => 'total_count',        'op' => '>=', 'value' => 10],
            ['field' => 'is_hardcoded',       'op' => '==', 'value' => false],
        ],
    ];
    $context = ['wilson_lower_bound' => 0.85, 'total_count' => 50, 'is_hardcoded' => false];
    expect($svc->evaluate($cond, $context))->toBeTrue();
});

it('avalia AND aninhado — uma falha → false', function () {
    $svc = new GovernanceRulesService();
    $cond = [
        'op' => 'AND',
        'conds' => [
            ['field' => 'wilson_lower_bound', 'op' => '>=', 'value' => 0.80],
            ['field' => 'total_count',        'op' => '>=', 'value' => 10],
        ],
    ];
    $context = ['wilson_lower_bound' => 0.85, 'total_count' => 5]; // fail no total_count
    expect($svc->evaluate($cond, $context))->toBeFalse();
});

it('avalia OR — uma verdadeira basta', function () {
    $svc = new GovernanceRulesService();
    $cond = [
        'op' => 'OR',
        'conds' => [
            ['field' => 'review_score', 'op' => '<', 'value' => 50],
            ['field' => 'review_confidence', 'op' => '<', 'value' => 0.6],
        ],
    ];
    expect($svc->evaluate($cond, ['review_score' => 30, 'review_confidence' => 0.8]))->toBeTrue();
    expect($svc->evaluate($cond, ['review_score' => 80, 'review_confidence' => 0.4]))->toBeTrue();
    expect($svc->evaluate($cond, ['review_score' => 80, 'review_confidence' => 0.8]))->toBeFalse();
});

it('campo ausente no contexto retorna false', function () {
    $svc = new GovernanceRulesService();
    $cond = ['field' => 'inexistente', 'op' => '>=', 'value' => 1];
    expect($svc->evaluate($cond, ['outro' => 5]))->toBeFalse();
});

it('humanize converte DSL em PT-BR legível', function () {
    $svc = new GovernanceRulesService();

    $simple = $svc->humanize(['field' => 'score', 'op' => '>=', 'value' => 0.8]);
    expect($simple)->toBe('score >= 0.8');

    $compound = $svc->humanize([
        'op' => 'AND',
        'conds' => [
            ['field' => 'wilson_lower_bound', 'op' => '>=', 'value' => 0.8],
            ['field' => 'total_count',        'op' => '>=', 'value' => 10],
        ],
    ]);
    expect($compound)->toBe('(wilson_lower_bound >= 0.8 E total_count >= 10)');
});
