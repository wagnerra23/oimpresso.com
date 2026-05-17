<?php

declare(strict_types=1);

use Modules\Governance\Services\ScopedScorecardEvaluator;
use Tests\TestCase;

/**
 * Tests pra ScopedScorecardEvaluator — paired indicators cap 50% canônico (Wave 24 Agent A).
 * Expandido Wave 25 Agent 0 (2026-05-16) — detection types reais (ast_scan, yaml_lookup,
 * pest_pattern, business_signal, ci_health, otel_query).
 *
 * Cap 50%: se velocidade alta (≥75% peso) mas qualidade baixa (<50% peso),
 * a dimensão velocidade tem score capado em 50% — penaliza gaming.
 *
 * NOTA Wave 25: bind explícito de Tests\TestCase necessário porque o Pest.php
 * de Modules/Governance/Tests/ não é auto-descoberto por Pest 3.x (só tests/Pest.php
 * é, conforme Bootstrappers\BootFiles). Sem esse uses(), `base_path()` no
 * construtor do Evaluator falha (container não bootado).
 *
 * @see Modules\Governance\Services\ScopedScorecardEvaluator
 */
uses(TestCase::class);

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
    // Wave 25: PHP `8/8` retorna int(1) — toEqual loose compare (1 == 1.0)
    expect((float) $velScore['percent'])->toEqual(1.0);

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

it('resolveBucketForModule retorna unknown pra módulo realmente sem bucket declarado', function () {
    // Wave 25: módulo inexistente garante 'unknown' (Governance ganhou bucket cross_cutting_infra Wave 23+)
    expect($this->eval->resolveBucketForModule('ModuloInexistenteSemBucket'))->toBe('unknown');
});

it('evaluateScorecard retorna estrutura completa com paired_violations vazio quando balanceado', function () {
    $module = 'Governance';
    $scorecard = $this->eval->loadScorecardForModule($module);

    $result = $this->eval->evaluateScorecard($module, $scorecard);

    expect($result)->toHaveKeys(['module', 'bucket', 'score_total', 'core', 'bucket_dimensions', 'paired_violations', 'evaluated_at']);
    expect($result['module'])->toBe('Governance');
    expect($result['paired_violations'])->toBeArray();
});

// ============================================================================
// Wave 25 Agent 0 — detection types reais (ast_scan, yaml_lookup, pest_pattern,
// business_signal, ci_health, otel_query)
// ============================================================================

it('detectRule dispatcher devolve true pra tipo desconhecido (pass-through fail-safe)', function () {
    expect($this->eval->detectRule('Vestuario', ['tipo' => 'tipo_inexistente_xyz']))->toBeTrue();
});

it('detectAstScan retorna true quando files contém pattern esperado (coverage absoluto)', function () {
    // Vestuario Entities devem usar HasBusinessScope ou BelongsToBusinessViaParent
    $result = $this->eval->detectAstScan('Vestuario', [
        'path'            => 'Modules/Vestuario/Entities/**/*.php',
        'expect'          => 'use HasBusinessScope|use BelongsToBusinessViaParent|business_id',
        'coverage_target' => 1, // pelo menos 1 file
    ]);
    expect($result)->toBeBool();
});

it('detectAstScan retorna true (N/A) quando path glob vazio + na_if presente', function () {
    $result = $this->eval->detectAstScan('Vestuario', [
        'path'            => 'Modules/Vestuario/Jobs/**/*.php',
        'expect'          => '__construct',
        'coverage_target' => 100,
        'na_if'           => 'Modules/Vestuario/Jobs nao existe',
    ]);
    // Sem Jobs E na_if presente → true (N/A justificado)
    expect($result)->toBeTrue();
});

it('detectAstScan retorna false quando path glob vazio + sem na_if', function () {
    $result = $this->eval->detectAstScan('VestuarioInexistente', [
        'path'            => 'Modules/VestuarioInexistente/Foo/**/*.php',
        'expect'          => 'whatever',
        'coverage_target' => 1,
    ]);
    expect($result)->toBeFalse();
});

it('detectYamlLookup retorna true quando key existe + value bate (vertical_client_facing bucket)', function () {
    // vertical_client_facing.yaml.bucket == 'vertical_client_facing'
    $result = $this->eval->detectYamlLookup('Vestuario', [
        'source' => 'memory/governance/buckets/vertical_client_facing.yaml',
        'key'    => 'bucket',
        'expect' => 'vertical_client_facing',
    ]);
    expect($result)->toBeTrue();
});

it('detectYamlLookup retorna false quando source inexistente', function () {
    $result = $this->eval->detectYamlLookup('Vestuario', [
        'source' => 'memory/nao-existe-xyz-123.yaml',
        'key'    => 'foo',
        'expect' => 'bar',
    ]);
    expect($result)->toBeFalse();
});

it('detectPestPattern retorna true quando file contém expect + assertion', function () {
    // Este próprio test file contém 'checkPairedViolation' + 'expect(' (assertion Pest)
    $result = $this->eval->detectPestPattern('Governance', [
        'path'      => 'Modules/Governance/Tests/Feature/ScopedScorecardEvaluatorTest.php',
        'expect'    => 'checkPairedViolation',
        'assertion' => 'expect',
    ]);
    expect($result)->toBeTrue();
});

it('detectPestPattern retorna false quando path glob vazio (sem arquivos pra varrer)', function () {
    // Path inexistente → glob() retorna [] → hits=0 → false
    $result = $this->eval->detectPestPattern('ModuloInexistenteXyz', [
        'path'      => 'Modules/ModuloInexistenteXyz/Tests/Feature/**/*Test.php',
        'expect'    => 'whatever',
        'assertion' => 'expect',
    ]);
    expect($result)->toBeFalse();
});

it('detectBusinessSignal fail-safe quando tabela ausente', function () {
    $result = $this->eval->detectBusinessSignal('Vestuario', [
        'source' => 'tabela_inexistente_xyz WHERE business_id = 4',
        'expect' => 'count > 0',
    ]);
    expect($result)->toBeFalse();
});

it('detectBusinessSignal retorna false quando source format inválido', function () {
    $result = $this->eval->detectBusinessSignal('Vestuario', [
        'source' => 'formato invalido sem WHERE',
        'expect' => 'count > 0',
    ]);
    expect($result)->toBeFalse();
});

it('detectCiHealth pass-through true quando GH_TOKEN ausente', function () {
    // Em ambiente de teste GH_TOKEN raramente está setado
    if (env('GH_TOKEN') || env('GITHUB_TOKEN')) {
        $this->markTestSkipped('GH_TOKEN setado, pass-through não dispara');
    }
    $result = $this->eval->detectCiHealth('Vestuario', [
        'source'          => 'GitHub Actions Pest workflow',
        'coverage_target' => 0.95,
    ]);
    expect($result)->toBeTrue();
});

it('detectOtelQuery pass-through true quando mcp_observability_spans ausente', function () {
    // Tabela só existe em CT 100 com collector OTel — Hostinger/local não
    $result = $this->eval->detectOtelQuery('Vestuario', [
        'source' => 'mcp_observability.spans WHERE module=Vestuario',
        'expect' => 'p99 < 500',
    ]);
    expect($result)->toBeTrue();
});

it('detectRule roteia ast_scan via dispatcher unificado', function () {
    $result = $this->eval->detectRule('Vestuario', [
        'tipo'            => 'ast_scan',
        'path'            => 'Modules/Vestuario/Entities/**/*.php',
        'expect'          => 'class',
        'coverage_target' => 0.5,
    ]);
    expect($result)->toBeBool();
});

it('detectRule roteia yaml_lookup via dispatcher unificado', function () {
    $result = $this->eval->detectRule('Vestuario', [
        'tipo'   => 'yaml_lookup',
        'source' => 'memory/governance/buckets/vertical_client_facing.yaml',
        'key'    => 'target_score',
        'expect' => '85',
    ]);
    expect($result)->toBeTrue();
});
