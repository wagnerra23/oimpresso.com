<?php

declare(strict_types=1);

// @covers-us US-COPI-137

use Illuminate\Support\Facades\Http;
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
 * Prova os contratos que importam:
 *   1. shouldSample: matemática determinística ~5% (sem DB/fila).
 *   2. WIRING: a config online_eval mora em `copiloto.*` (o namespace que o código
 *      REALMENTE lê) — o antigo `jana.*` era vazio (enabled=true não ligava nada).
 *   3. judge=local (default): juiz Ollama self-host CT 100 (zero egress) pontua, e o
 *      PiiRedactor roda ANTES do juiz — o texto que vai pro juiz NÃO tem PII crua.
 *   4. judge=local com Ollama indisponível: NÃO grava score (sem 0.0 fabricado).
 *   5. judge=openai: manda pro juiz externo, também PII-redigido.
 *   6. juiz em mock: NÃO pontua (não fabrica score de teatro).
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

// ── 2. WIRING — o bloco online_eval mora onde o código lê (copiloto.*) ────────

it('config default: online_eval resolve em copiloto.* (o namespace que o código lê)', function () {
    // O Job/Listener leem `copiloto.online_eval.*`. Antes do fix liam `jana.online_eval.*`,
    // que NÃO existe → enabled=true no config.php não ligava nada. Este teste morde isso.
    expect(config('copiloto.online_eval.enabled'))->toBeFalse();
    expect(config('copiloto.online_eval.judge'))->toBe('local');
    expect(config('copiloto.online_eval.sample_rate'))->toBe(0.05);
    // A prova negativa: o namespace antigo é vazio (era o bug).
    expect(config('jana.online_eval.enabled'))->toBeNull();
});

// ── 3. judge=local (default) → juiz Ollama, PII redigida ANTES do juiz ────────

it('judge=local: juiz Ollama pontua, PII redigida ANTES do juiz (zero PII crua no request)', function () {
    config([
        'jana.online_eval.judge' => 'openai', // ruído do namespace antigo NÃO deve influenciar
        'copiloto.online_eval.judge' => 'local',
        'copiloto.online_eval.local.url' => 'http://ollama.test',
        'copiloto.online_eval.local.model' => 'qwen2.5:3b',
    ]);

    $cpfCru = '123.456.789-09'; // CPF fake da whitelist do pii-redactor (prova de redação, não é PII real)
    $emailCru = 'larissa@rotalivre.com.br';

    // Ollama local (self-host) devolve o score. Http::fake = zero egress real.
    Http::fake([
        'http://ollama.test/api/chat' => Http::response([
            'message' => ['content' => json_encode(['score' => 0.88, 'supported' => 7, 'total_claims' => 8])],
        ], 200),
    ]);

    /** @var LangfuseClient&MockInterface $client */
    $client = Mockery::mock(LangfuseClient::class);
    $client->shouldReceive('recordScore')
        ->once()
        ->with('trace-local', 'ragas_faithfulness_online', 0.88, Mockery::type('string'));

    $job = new JudgeTraceOnlineJob(
        'trace-local',
        4,
        "Cliente {$cpfCru} pergunta sobre vendas",
        "Resposta pro email {$emailCru}",
    );
    // O 2º arg (RagasJudgeService openai) é ignorado no caminho local — o Job resolve
    // OllamaRagasJudge do container.
    $job->handle(app(PiiRedactor::class), app(RagasJudgeService::class), $client);

    // A prova LGPD: o que foi pro juiz LOCAL NÃO contém a PII crua (redação, não deleção).
    Http::assertSent(function ($req) use ($cpfCru, $emailCru) {
        $body = $req->body();

        return $req->url() === 'http://ollama.test/api/chat'
            && ! str_contains($body, $cpfCru)
            && ! str_contains($body, $emailCru)
            && str_contains($body, 'pergunta sobre vendas');
    });
    // Zero egress: nada saiu pro OpenAI.
    Http::assertNotSent(fn ($req) => str_contains($req->url(), 'openai.com'));
});

it('judge=local: Ollama indisponível → NÃO grava score (sem 0.0 fabricado)', function () {
    config([
        'copiloto.online_eval.judge' => 'local',
        'copiloto.online_eval.local.url' => 'http://ollama.test',
    ]);

    // Ollama caído (500) → OllamaRagasJudge lança JudgeUnavailableException → Job pula.
    Http::fake(['http://ollama.test/api/chat' => Http::response('down', 500)]);

    $client = Mockery::mock(LangfuseClient::class);
    $client->shouldNotReceive('recordScore'); // honesto: nenhum score fabricado

    $job = new JudgeTraceOnlineJob('trace-down', 4, 'input', 'output');
    $job->handle(app(PiiRedactor::class), app(RagasJudgeService::class), $client);

    expect(true)->toBeTrue(); // Mockery verifica o shouldNotReceive no teardown
});

it('judge desconhecido: SKIP total (nada roda, nada sai)', function () {
    config(['copiloto.online_eval.judge' => 'nada-disso']);
    Http::fake();

    $judge = Mockery::mock(RagasJudgeService::class);
    $judge->shouldNotReceive('scoreFaithfulness');
    $client = Mockery::mock(LangfuseClient::class);
    $client->shouldNotReceive('recordScore');

    $job = new JudgeTraceOnlineJob('trace-x', 4, 'input', 'output');
    $job->handle(app(PiiRedactor::class), $judge, $client);

    Http::assertNothingSent();
    expect(true)->toBeTrue();
});

// ── 5. judge=openai → PII redigida ANTES do juiz externo ─────────────────────

it('judge=openai: PiiRedactor roda ANTES do juiz — texto pro juiz externo NÃO tem PII crua', function () {
    config(['copiloto.online_eval.judge' => 'openai']);

    $cpfCru = '123.456.789-09'; // CPF fake da whitelist do pii-redactor (prova de redação, não é PII real)
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
    config(['copiloto.online_eval.judge' => 'openai']);

    $judge = Mockery::mock(RagasJudgeService::class);
    $judge->shouldReceive('isMockMode')->andReturn(true);
    $judge->shouldNotReceive('scoreFaithfulness');
    $client = Mockery::mock(LangfuseClient::class);
    $client->shouldNotReceive('recordScore');

    $job = new JudgeTraceOnlineJob('trace-2', 4, 'input', 'output');
    $job->handle(app(PiiRedactor::class), $judge, $client);

    expect(true)->toBeTrue();
});
