<?php

declare(strict_types=1);

// @covers-us US-COPI-108 — chamadas LLM via Http:: direto (fora do listener global
// Langfuse, que só cobre events do laravel/ai) emitem trace+generation inline:
// RagasJudgeService (+score), ContextualizerService, AiScorecardJudge,
// SkillTestRunnerService. Reconciliação 2026-07-12 (juiz wf_33e38126): o checkbox
// "RAGAS gate CI emite traces" era FALSO — grep langfuse em Services/Ragas = vazio.

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Modules\Jana\Jobs\Telemetry\LangfuseTraceJob;
use Modules\Jana\Services\Memoria\Contextual\ContextualizerService;
use Modules\Jana\Services\Ragas\RagasJudgeService;
use Modules\Jana\Services\Scorecard\AiScorecardJudge;
use Modules\Jana\Services\Skills\SkillTestRunnerService;

uses(Tests\TestCase::class);

// ── helpers ──────────────────────────────────────────────────────────────

/** Coleta os types de todos os events despachados em LangfuseTraceJob. */
function langfuseDispatchedTypes(): array
{
    $types = [];
    Bus::assertDispatched(LangfuseTraceJob::class, function (LangfuseTraceJob $job) use (&$types) {
        foreach ($job->events as $event) {
            $types[] = $event['type'] ?? 'unknown';
        }

        return true; // não filtra — só coleta
    });

    return $types;
}

beforeEach(function () {
    config()->set('langfuse.enabled', true);
    config()->set('langfuse.host', 'https://langfuse.test');
    config()->set('langfuse.public_key', 'pk-test-123');
    config()->set('langfuse.secret_key', 'sk-test-456');
    config()->set('langfuse.sample_rate', 1.0);
    config()->set('langfuse.redact_pii', false);
    config()->set('langfuse.dispatch', 'queue');

    Bus::fake([LangfuseTraceJob::class]);
});

// ── 1. RagasJudgeService (o checkbox falso da US-COPI-108) ────────────────

it('RagasJudgeService emite trace + generation + score por métrica julgada', function () {
    config()->set('openai.api_key', 'sk-fake');
    config()->set('ragas.judge_model', 'gpt-4o-mini');

    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [['message' => ['content' => json_encode(['score' => 0.87])]]],
            'usage'   => ['prompt_tokens' => 1200, 'completion_tokens' => 42],
        ], 200),
    ]);

    $score = (new RagasJudgeService())->scoreFaithfulness('pergunta?', 'resposta', 'contexto');

    expect($score)->toBe(0.87);

    $types = langfuseDispatchedTypes();
    expect($types)->toContain('trace-create')
        ->toContain('generation-create')
        ->toContain('score-create');

    // O score RAGAS vai como score-create numérico correlacionado ao trace.
    Bus::assertDispatched(LangfuseTraceJob::class, function (LangfuseTraceJob $job) {
        foreach ($job->events as $event) {
            if (($event['type'] ?? '') === 'score-create') {
                return ($event['body']['name'] ?? '') === 'ragas_faithfulness'
                    && abs((float) ($event['body']['value'] ?? 0) - 0.87) < 0.001;
            }
        }

        return false;
    });
});

// ── 2. ContextualizerService ──────────────────────────────────────────────

it('ContextualizerService emite trace + generation com usage (incl. cache tokens em metadata)', function () {
    config()->set('copiloto.contextual_retrieval.enabled', true);
    config()->set('copiloto.contextual_retrieval.force_mock', false);
    config()->set('copiloto.contextual_retrieval.api_key', 'sk-ant-fake');

    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => 'Contexto sucinto do chunk.']],
            'usage'   => [
                'input_tokens'                => 900,
                'output_tokens'               => 60,
                'cache_creation_input_tokens' => 800,
                'cache_read_input_tokens'     => 0,
            ],
        ], 200),
    ]);

    $contexto = (new ContextualizerService())->contextualize('documento inteiro aqui', 'chunk aqui');

    expect($contexto)->toBe('Contexto sucinto do chunk.');

    $types = langfuseDispatchedTypes();
    expect($types)->toContain('trace-create')->toContain('generation-create');
});

// ── 3. AiScorecardJudge (callLlm protected — reflection, mesmo molde TimeDecayTest) ──

it('AiScorecardJudge emite trace + generation na chamada LLM real', function () {
    config()->set('services.anthropic.api_key', 'sk-ant-fake');

    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => '{"delta": -2, "justificativa": "gap X", "confidence": 0.7}']],
            'usage'   => ['input_tokens' => 1500, 'output_tokens' => 250],
        ], 200),
    ]);

    $judge  = app(AiScorecardJudge::class);
    $ref    = new ReflectionClass($judge);
    $method = $ref->getMethod('callLlm');
    $method->setAccessible(true);

    $text = $method->invoke($judge, 'YAML + breakdown deterministico aqui');

    expect($text)->toContain('delta');

    $types = langfuseDispatchedTypes();
    expect($types)->toContain('trace-create')->toContain('generation-create');
});

// ── 4. SkillTestRunnerService (callAnthropic private — reflection) ────────

it('SkillTestRunnerService emite trace + generation ao rodar skill contra Anthropic', function () {
    config()->set('services.anthropic.api_key', 'sk-ant-fake');

    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => 'saída da skill']],
            'usage'   => ['input_tokens' => 700, 'output_tokens' => 120],
        ], 200),
    ]);

    $runner = app(SkillTestRunnerService::class);
    $ref    = new ReflectionClass($runner);
    $method = $ref->getMethod('callAnthropic');
    $method->setAccessible(true);

    [$output, $tokens] = $method->invoke($runner, 'system prompt da skill', 'prompt do user');

    expect($output)->toBe('saída da skill');
    expect($tokens)->toBe(120);

    $types = langfuseDispatchedTypes();
    expect($types)->toContain('trace-create')->toContain('generation-create');
});

// ── 5. Back-compat: langfuse desligado = zero side-effect ─────────────────

it('com langfuse.enabled=false (caso do CI GH Actions) NENHUM job é despachado', function () {
    config()->set('langfuse.enabled', false);
    config()->set('openai.api_key', 'sk-fake');

    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [['message' => ['content' => json_encode(['score' => 0.5])]]],
            'usage'   => ['prompt_tokens' => 10, 'completion_tokens' => 5],
        ], 200),
    ]);

    $score = (new RagasJudgeService())->scoreFaithfulness('q?', 'a', 'ctx');

    expect($score)->toBe(0.5);
    Bus::assertNotDispatched(LangfuseTraceJob::class);
});
