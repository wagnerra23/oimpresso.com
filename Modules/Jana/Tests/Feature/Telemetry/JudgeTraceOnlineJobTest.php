<?php

declare(strict_types=1);

// @covers-us US-COPI-137

use Mockery\MockInterface;
use Modules\Jana\Jobs\Telemetry\JudgeTraceOnlineJob;
use Modules\Jana\Services\Privacy\PiiRedactor;
use Modules\Jana\Services\Ragas\RagasJudgeService;
use Modules\Jana\Services\Telemetry\LangfuseClient;
use Tests\TestCase;

uses(TestCase::class);

/**
 * JudgeTraceOnlineJobTest — US-COPI-137 — eval online no tráfego real.
 *
 * Prova os 3 contratos que importam:
 *   1. shouldSample: matemática determinística ~5% (sem DB/fila).
 *   2. judge=local (rota B): roteia pro backend Ollama (CT 100, zero egress pra
 *      terceiro), com PII redigida antes; judge desconhecido → SKIP (fail-safe).
 *   3. LGPD Tier 0: com judge=openai, o PiiRedactor roda ANTES do juiz — o texto
 *      que sai pro juiz externo NÃO carrega PII crua.
 */

// ── 1. shouldSample — matemática pura (a mordida da amostragem) ──────────────

it('shouldSample: rate 0 nunca amostra, rate 1 sempre', function () {
    expect(JudgeTraceOnlineJob::shouldSample('trace-x', 0.0))->toBeFalse();
    expect(JudgeTraceOnlineJob::shouldSample('trace-x', 1.0))->toBeTrue();
    expect(JudgeTraceOnlineJob::shouldSample('', 0.5))->toBeFalse(); // trace vazio nunca
});

it('shouldSample: determinístico por traceId (idempotente se re-dispatchado)', function () {
    $a = JudgeTraceOnlineJob::shouldSample('trace-abc-123', 0.05);
    $b = JudgeTraceOnlineJob::shouldSample('trace-abc-123', 0.05);
    expect($a)->toBe($b);
});

it('shouldSample: ~5% sobre 10k traces (não 0%, não 100%)', function () {
    $hits = 0;
    for ($i = 0; $i < 10000; $i++) {
        if (JudgeTraceOnlineJob::shouldSample("trace-{$i}", 0.05)) {
            $hits++;
        }
    }
    // 5% de 10k = 500 esperado; banda folgada pra não flakar (crc32 não é uniforme perfeito).
    expect($hits)->toBeGreaterThan(350)->toBeLessThan(650);
});

// ── 2. judge=local (rota B) → backend Ollama + PII redigida · desconhecido → SKIP ──

it('judge=local: roteia pro backend Ollama (zero egress pra terceiro), PII redigida antes', function () {
    config(['jana.online_eval.judge' => 'local']);

    $cpfCru = '111.444.777-35'; // pii-allowlist (CPF sintético de teste)

    $cap = new stdClass();
    $judge = new class($cap) extends RagasJudgeService
    {
        public function __construct(public stdClass $cap) {}

        public function isMockMode(): bool
        {
            return false;
        }

        public function useBackend(string $backend): void
        {
            $this->cap->backend = $backend; // prova que roteou pro local, não OpenAI
        }

        public function scoreFaithfulness(string $question, string $answer, string $context): float
        {
            $this->cap->context = $context;

            return 0.8;
        }
    };

    /** @var LangfuseClient&MockInterface $client */
    $client = Mockery::mock(LangfuseClient::class);
    $client->shouldReceive('recordScore')
        ->once()
        ->with('trace-L', 'ragas_faithfulness_online', 0.8, Mockery::type('string'));

    $job = new JudgeTraceOnlineJob('trace-L', 4, "Cliente {$cpfCru} pergunta", 'resposta');
    $job->handle(app(PiiRedactor::class), $judge, $client);

    expect($cap->backend)->toBe('ollama');           // roteou pro juiz self-hosted
    expect($cap->context)->not->toContain($cpfCru);  // PII redigida mesmo indo pro CT 100
});

it('judge desconhecido → SKIP (fail-safe: nunca cai pro OpenAI por reflexo)', function () {
    config(['jana.online_eval.judge' => 'xyz']);

    $judge = Mockery::mock(RagasJudgeService::class);
    $judge->shouldNotReceive('scoreFaithfulness');
    $client = Mockery::mock(LangfuseClient::class);
    $client->shouldNotReceive('recordScore');

    $job = new JudgeTraceOnlineJob('trace-1', 4, 'input do cliente', 'resposta');
    $job->handle(app(PiiRedactor::class), $judge, $client);

    expect(true)->toBeTrue();
});

// ── 3. LGPD Tier 0 (judge=openai) → PII redigida ANTES do juiz ───────────────

it('judge=openai: PiiRedactor roda ANTES do juiz — texto pro juiz externo NÃO tem PII crua', function () {
    config(['jana.online_eval.judge' => 'openai']);

    $cpfCru = '111.444.777-35'; // pii-allowlist (CPF sintético de teste — prova de redação)
    $emailCru = 'larissa@rotalivre.com.br';

    // Fake judge: NÃO é mock-mode (pra passar do guard de mock), captura o que recebe.
    $capturado = new stdClass();
    $judge = new class($capturado) extends RagasJudgeService
    {
        public function __construct(public stdClass $cap) {}

        public function isMockMode(): bool
        {
            return false;
        }

        public function scoreFaithfulness(string $question, string $answer, string $context): float
        {
            $this->cap->answer = $answer;
            $this->cap->context = $context;

            return 0.9;
        }
    };

    /** @var LangfuseClient&MockInterface $client */
    $client = Mockery::mock(LangfuseClient::class);
    $client->shouldReceive('recordScore')
        ->once()
        ->with('trace-9', 'ragas_faithfulness_online', 0.9, Mockery::type('string'));

    $job = new JudgeTraceOnlineJob(
        'trace-9',
        4,
        "Cliente {$cpfCru} pergunta sobre vendas",
        "Resposta pro email {$emailCru}",
    );
    $job->handle(app(PiiRedactor::class), $judge, $client);

    // A prova LGPD: o que foi pro juiz NÃO contém a PII crua.
    expect($capturado->context)->not->toContain($cpfCru);
    expect($capturado->answer)->not->toContain($emailCru);
    // ...mas contém o resto do texto (redação, não deleção).
    expect($capturado->context)->toContain('pergunta sobre vendas');
});

it('judge=openai mas juiz em mock: NÃO pontua (não fabrica score de teatro)', function () {
    config(['jana.online_eval.judge' => 'openai']);

    $judge = Mockery::mock(RagasJudgeService::class);
    $judge->shouldReceive('isMockMode')->andReturn(true);
    $judge->shouldNotReceive('scoreFaithfulness');
    $client = Mockery::mock(LangfuseClient::class);
    $client->shouldNotReceive('recordScore');

    $job = new JudgeTraceOnlineJob('trace-2', 4, 'input', 'output');
    $job->handle(app(PiiRedactor::class), $judge, $client);

    expect(true)->toBeTrue();
});
