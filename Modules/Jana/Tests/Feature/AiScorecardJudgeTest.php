<?php

declare(strict_types=1);

/**
 * AiScorecardJudgeTest — Wave 24 Agent B (Governance v4 AI baseline READ-ONLY).
 *
 * Cobre AiScorecardJudge sem chamar LLM real (mock mode default).
 * Skip DB persistence test se sem MySQL (ADR 0101 — schema UltimatePOS requer MySQL).
 *
 * @group governance-v4
 * @see Modules/Jana/Services/Scorecard/AiScorecardJudge.php
 */

use Modules\Jana\Services\Privacy\PiiRedactor;
use Modules\Jana\Services\Scorecard\AiScorecardJudge;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    $this->redactor = new PiiRedactor();
    $this->judge = new AiScorecardJudge($this->redactor);
    $this->judge->enableMock([
        'delta' => -2,
        'justificativa' => 'F1.b cobra ratio mas tests vazios — mock baseline',
        'confidence' => 0.85,
    ]);
});

// -------------------------------------------------------------------------
// buildPrompt — formato canônico PT-BR
// -------------------------------------------------------------------------

it('buildPrompt inclui módulo, bucket, score e meta', function () {
    $prompt = $this->judge->buildPrompt(
        'jana',
        ['bucket' => 'ai_central', 'target_score' => 85],
        [
            'score_total' => 72,
            'meta_bucket' => 85,
            'core' => ['D1' => ['current' => 28, 'target' => 30]],
            'bucket_dimensions' => ['A1' => ['current' => 15, 'target' => 20]],
            'paired_violations' => [],
        ],
    );

    expect($prompt)->toContain('Modulo: jana');
    expect($prompt)->toContain('Bucket: ai_central');
    expect($prompt)->toContain('Score deterministico: 72/100');
    expect($prompt)->toContain('Meta bucket: 85');
    expect($prompt)->toContain('Output JSON ESTRITO');
});

it('buildPrompt summariza dimensões core e bucket', function () {
    $prompt = $this->judge->buildPrompt(
        'admin',
        ['bucket' => 'cross_cutting_infra'],
        [
            'score_total' => 86,
            'meta_bucket' => 90,
            'core' => [
                'D1_models' => ['current' => 30, 'target' => 30],
                'D8_security' => ['current' => 3, 'target' => 3],
            ],
            'bucket_dimensions' => [],
            'paired_violations' => [],
        ],
    );

    expect($prompt)->toContain('D1_models=30/30');
    expect($prompt)->toContain('D8_security=3/3');
});

it('buildPrompt destaca paired violations quando presentes', function () {
    $prompt = $this->judge->buildPrompt(
        'sells',
        ['bucket' => 'functional_horizontal'],
        [
            'score_total' => 70,
            'meta_bucket' => 80,
            'core' => [],
            'bucket_dimensions' => [],
            'paired_violations' => [
                ['rule' => 'pest_ratio_no_assertions', 'reason' => 'tests vazios'],
            ],
        ],
    );

    expect($prompt)->toContain('pest_ratio_no_assertions');
    expect($prompt)->toContain('tests vazios');
});

// -------------------------------------------------------------------------
// summarizeDim / summarizeViolations — helpers
// -------------------------------------------------------------------------

it('summarizeDim retorna (vazio) quando array vazio', function () {
    expect($this->judge->summarizeDim([]))->toBe('(vazio)');
});

it('summarizeDim formata current/target', function () {
    $out = $this->judge->summarizeDim([
        'A1' => ['current' => 15, 'target' => 20],
        'A2' => ['current' => 18, 'target' => 20],
    ]);
    expect($out)->toBe('A1=15/20, A2=18/20');
});

it('summarizeViolations retorna nenhuma quando vazio', function () {
    expect($this->judge->summarizeViolations([]))->toBe('nenhuma');
});

it('summarizeViolations concatena rule+reason', function () {
    $out = $this->judge->summarizeViolations([
        ['rule' => 'r1', 'reason' => 'motivo'],
        ['rule' => 'r2', 'reason' => ''],
    ]);
    expect($out)->toContain('r1 (motivo)');
    expect($out)->toContain('r2');
});

// -------------------------------------------------------------------------
// parseResponse — JSON robusto
// -------------------------------------------------------------------------

it('parseResponse aceita JSON cru', function () {
    $raw = '{"delta": -3, "justificativa": "F2 tests vazios", "confidence": 0.9}';
    $out = $this->judge->parseResponse($raw);

    expect($out['delta'])->toBe(-3);
    expect($out['justificativa'])->toBe('F2 tests vazios');
    expect($out['confidence'])->toBe(0.9);
});

it('parseResponse tolera markdown wrap acidental', function () {
    $raw = "```json\n{\"delta\": 2, \"justificativa\": \"ok\", \"confidence\": 0.5}\n```";
    $out = $this->judge->parseResponse($raw);

    expect($out['delta'])->toBe(2);
    expect($out['confidence'])->toBe(0.5);
});

it('parseResponse clampa delta entre -10 e +10', function () {
    $highRaw = '{"delta": 99, "justificativa": "x", "confidence": 0.5}';
    expect($this->judge->parseResponse($highRaw)['delta'])->toBe(10);

    $lowRaw = '{"delta": -50, "justificativa": "x", "confidence": 0.5}';
    expect($this->judge->parseResponse($lowRaw)['delta'])->toBe(-10);
});

it('parseResponse clampa confidence entre 0 e 1', function () {
    $highRaw = '{"delta": 0, "justificativa": "x", "confidence": 5.0}';
    expect($this->judge->parseResponse($highRaw)['confidence'])->toBe(1.0);

    $lowRaw = '{"delta": 0, "justificativa": "x", "confidence": -1.0}';
    expect($this->judge->parseResponse($lowRaw)['confidence'])->toBe(0.0);
});

it('parseResponse trunca justificativa em 160 chars', function () {
    $long = str_repeat('x', 300);
    $raw = "{\"delta\": 0, \"justificativa\": \"{$long}\", \"confidence\": 0.5}";
    $out = $this->judge->parseResponse($raw);
    expect(strlen($out['justificativa']))->toBe(160);
});

it('parseResponse lança exception em JSON malformado', function () {
    $this->judge->parseResponse('isso não é JSON valido');
})->throws(RuntimeException::class);

// -------------------------------------------------------------------------
// suggestScoreAdjustment — mock mode fluxo completo (sem DB)
// -------------------------------------------------------------------------

it('suggestScoreAdjustment retorna shape canônico em mock mode', function () {
    $out = $this->judge->suggestScoreAdjustment(
        'jana',
        ['bucket' => 'ai_central', 'slug' => 'jana'],
        [
            'score_total' => 72,
            'meta_bucket' => 85,
            'core' => [],
            'bucket_dimensions' => [],
            'paired_violations' => [],
        ],
    );

    expect($out)->toHaveKeys([
        'module',
        'deterministic_score',
        'ai_suggested_delta',
        'ai_justificativa',
        'ai_model',
        'confidence',
        'created_at',
    ]);
    expect($out['module'])->toBe('jana');
    expect($out['deterministic_score'])->toBe(72);
    expect($out['ai_suggested_delta'])->toBe(-2);
    expect($out['confidence'])->toBe(0.85);
});

// -------------------------------------------------------------------------
// PII redaction defense in depth — rubricas YAML não devem ter PII, mas...
// -------------------------------------------------------------------------

it('redacta PII (CPF) caso aparece em rubrica/breakdown', function () {
    // Edge case: someone put CPF in dimension reason (shouldn't happen, defense)
    $prompt = $this->judge->buildPrompt(
        'vestuario',
        ['bucket' => 'vertical_client_facing'],
        [
            'score_total' => 80,
            'meta_bucket' => 85,
            'core' => [],
            'bucket_dimensions' => [],
            'paired_violations' => [
                ['rule' => 'lgpd_violation', 'reason' => 'CPF 123.456.789-09 em log'],
            ],
        ],
    );
    // O prompt cru ainda contém — redaction acontece dentro de suggestScoreAdjustment
    expect($prompt)->toContain('123.456.789-09');

    $redacted = $this->redactor->redact($prompt);
    expect($redacted)->not->toContain('123.456.789-09');
    expect($redacted)->toContain('[REDACTED:CPF]');
});

// -------------------------------------------------------------------------
// DB persistence — skip se sem MySQL
// -------------------------------------------------------------------------

it('persiste sugestão em mcp_scorecard_ai_suggestions quando tabela existe', function () {
    if (! \Illuminate\Support\Facades\Schema::hasTable('mcp_scorecard_ai_suggestions')) {
        $this->markTestSkipped('Tabela mcp_scorecard_ai_suggestions ausente — rodar migrations primeiro.');
    }

    $beforeCount = \Illuminate\Support\Facades\DB::table('mcp_scorecard_ai_suggestions')->count();

    $this->judge->suggestScoreAdjustment(
        'test-module',
        ['bucket' => 'ai_central'],
        ['score_total' => 50, 'meta_bucket' => 85, 'core' => [], 'bucket_dimensions' => [], 'paired_violations' => []],
    );

    $afterCount = \Illuminate\Support\Facades\DB::table('mcp_scorecard_ai_suggestions')->count();
    expect($afterCount)->toBe($beforeCount + 1);
})->group('db');
