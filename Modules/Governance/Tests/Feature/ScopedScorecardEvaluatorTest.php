<?php

declare(strict_types=1);

use Modules\Governance\Services\ScopedScorecardEvaluator;

/**
 * Tests pra ScopedScorecardEvaluator — paired indicators cap 50% canônico (Wave 24 Agent A).
 *
 * Cap 50%: se velocidade alta (≥75% peso) mas qualidade baixa (<50% peso),
 * a dimensão velocidade tem score capado em 50% — penaliza gaming.
 *
 * @see Modules\Governance\Services\ScopedScorecardEvaluator
 */

beforeEach(function () {
    $this->eval = new ScopedScorecardEvaluator();
});

it('resolveRuleScore retorna null pra key sem ponto', function () {
    $result = ['bucket_dimensions' => [], 'core' => []];
    expect($this->eval->resolveRuleScore($result, 'invalid_key'))->toBeNull();
});

it('resolveRuleScore lê regra interna a uma dimensão (com regras)', function () {
    $result = [
        'bucket_dimensions' => [
            'F1_pest_e2e' => [
                'peso'   => 20,
                'score'  => 16,
                'regras' => [
                    'F1_b' => ['peso' => 8, 'current' => 8], // 100%
                    'F1_c' => ['peso' => 6, 'current' => 2], // 33%
                ],
            ],
        ],
        'core' => [],
    ];

    $velScore = $this->eval->resolveRuleScore($result, 'F1_pest_e2e.F1_b');
    expect($velScore)->not->toBeNull();
    expect($velScore['percent'])->toBe(1.0);

    $qualScore = $this->eval->resolveRuleScore($result, 'F1_pest_e2e.F1_c');
    expect($qualScore['percent'])->toBeLessThan(0.5);
});

it('checkPairedViolation aplica cap 50% quando velocidade alta + qualidade baixa', function () {
    $result = [
        'bucket_dimensions' => [
            'F1_pest_e2e' => [
                'peso'   => 20,
                'score'  => 18, // antes do cap
                'regras' => [
                    'F1_b' => ['peso' => 8, 'current' => 8], // 100% velocidade
                    'F1_c' => ['peso' => 6, 'current' => 2], // 33% qualidade
                ],
            ],
        ],
        'core'              => [],
        'paired_violations' => [],
    ];

    $pair = [
        'velocidade' => 'F1_pest_e2e.F1_b',
        'qualidade'  => 'F1_pest_e2e.F1_c',
        'rule'       => 'Velocidade >=75% + Qualidade <50% -> cap 50%',
        'racional'   => 'Testes rápidos sem asserts robustas',
    ];

    $violation = $this->eval->checkPairedViolation($result, $pair);

    expect($violation)->toBeArray();
    expect($violation['cap_applied'])->toBe('50%');
    expect($violation['pair'])->toBe('F1_pest_e2e.F1_b x F1_pest_e2e.F1_c');
    // Score capado em 50% do peso (20 * 0.5 = 10)
    expect($result['bucket_dimensions']['F1_pest_e2e']['score'])->toBe(10);
    expect($result['bucket_dimensions']['F1_pest_e2e']['capped_by_paired'])->toBeTrue();
});

it('checkPairedViolation retorna null quando velocidade e qualidade balanceadas', function () {
    $result = [
        'bucket_dimensions' => [
            'F1_pest_e2e' => [
                'peso'   => 20,
                'score'  => 18,
                'regras' => [
                    'F1_b' => ['peso' => 8, 'current' => 8], // 100% velocidade
                    'F1_c' => ['peso' => 6, 'current' => 5], // 83% qualidade — OK
                ],
            ],
        ],
        'core'              => [],
        'paired_violations' => [],
    ];

    $pair = [
        'velocidade' => 'F1_pest_e2e.F1_b',
        'qualidade'  => 'F1_pest_e2e.F1_c',
        'rule'       => 'X',
        'racional'   => 'Y',
    ];

    $violation = $this->eval->checkPairedViolation($result, $pair);
    expect($violation)->toBeNull();
    // Score não foi alterado
    expect($result['bucket_dimensions']['F1_pest_e2e']['score'])->toBe(18);
    expect($result['bucket_dimensions']['F1_pest_e2e']['capped_by_paired'] ?? false)->toBeFalse();
});

it('checkPairedViolation retorna null quando velocidade baixa (não dispara cap)', function () {
    $result = [
        'bucket_dimensions' => [
            'F1_pest_e2e' => [
                'peso'   => 20,
                'score'  => 6,
                'regras' => [
                    'F1_b' => ['peso' => 8, 'current' => 3], // 37% velocidade — abaixo do threshold
                    'F1_c' => ['peso' => 6, 'current' => 2], // 33% qualidade
                ],
            ],
        ],
        'core'              => [],
        'paired_violations' => [],
    ];

    $pair = [
        'velocidade' => 'F1_pest_e2e.F1_b',
        'qualidade'  => 'F1_pest_e2e.F1_c',
        'rule'       => 'X',
        'racional'   => 'Y',
    ];

    expect($this->eval->checkPairedViolation($result, $pair))->toBeNull();
});

it('loadBucketConfig carrega vertical_client_facing.yaml com paired declarado', function () {
    $config = $this->eval->loadBucketConfig('vertical_client_facing');

    expect($config)->toBeArray()->not->toBeEmpty();
    expect($config['bucket'])->toBe('vertical_client_facing');
    expect($config['target_score'])->toBe(85);
    expect($config['paired'])->toBeArray();
    expect(count($config['paired']))->toBeGreaterThanOrEqual(1);
    expect($config['paired'][0])->toHaveKey('velocidade');
    expect($config['paired'][0])->toHaveKey('qualidade');
    expect($config['paired'][0])->toHaveKey('rule');
});

it('loadBucketConfig retorna array vazio pra bucket inexistente', function () {
    expect($this->eval->loadBucketConfig('bucket_inexistente_xyz'))->toBe([]);
});

it('resolveBucketForModule lê governance.bucket de module.json', function () {
    // Vestuario declara bucket=vertical_client_facing em module.json
    expect($this->eval->resolveBucketForModule('Vestuario'))->toBe('vertical_client_facing');
});

it('resolveBucketForModule retorna unknown pra módulo sem bucket declarado', function () {
    // Governance module.json não declara bucket (só fsm_n_a)
    expect($this->eval->resolveBucketForModule('Governance'))->toBe('unknown');
});

it('evaluateScorecard retorna estrutura completa com paired_violations vazio quando balanceado', function () {
    $module = 'Governance';
    $scorecard = $this->eval->loadScorecardForModule($module);

    $result = $this->eval->evaluateScorecard($module, $scorecard);

    expect($result)->toHaveKeys(['module', 'bucket', 'score_total', 'core', 'bucket_dimensions', 'paired_violations', 'evaluated_at']);
    expect($result['module'])->toBe('Governance');
    expect($result['paired_violations'])->toBeArray();
});
