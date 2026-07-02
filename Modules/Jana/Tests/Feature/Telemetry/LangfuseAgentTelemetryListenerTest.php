<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Providers\TextProvider;
use Laravel\Ai\Events\AgentPrompted;
use Laravel\Ai\Events\AgentStreamed;
use Laravel\Ai\Events\PromptingAgent;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use Modules\Jana\Jobs\Telemetry\LangfuseTraceJob;

uses(Tests\TestCase::class);

/**
 * Cobertura LangfuseAgentTelemetryListener (ADR 0132 — ponto único de
 * observabilidade LLM via events nativos do laravel/ai).
 *
 *  - AgentPrompted → trace-create + generation-create num ÚNICO batch
 *  - AgentStreamed (subclass) também emite (registro explícito, dispatcher
 *    Laravel não propaga listener por herança)
 *  - business_id extraído da propriedade pública `businessId` do Agent
 *    (convenção ADR 0141 Tier 0 mecânico) presente em trace metadata
 *  - Duração medida entre PromptingAgent e AgentPrompted (invocationId)
 *  - LANGFUSE_ENABLED=false → zero side-effect
 *  - Fail-open: erro interno NUNCA propaga pra chamada LLM
 */

/**
 * Agent fake mínimo — mesma anatomia dos Agents canônicos (ADR 0141):
 * implements Agent + use Promptable + businessId público no constructor.
 */
class FakeTelemetryAgent implements Agent
{
    use \Laravel\Ai\Promptable;

    public function __construct(public readonly int $businessId)
    {
    }

    public function instructions(): Stringable|string
    {
        return 'agente de teste';
    }
}

function makeAgentPromptedEvent(int $businessId = 1, string $invocationId = 'inv-test-1'): AgentPrompted
{
    $agent = new FakeTelemetryAgent($businessId);
    $prompt = new AgentPrompt(
        $agent,
        'qual o faturamento do mês?',
        [],
        Mockery::mock(TextProvider::class),
        'gpt-4o-mini',
    );
    $response = new AgentResponse(
        $invocationId,
        'faturamento foi X',
        new Usage(promptTokens: 120, completionTokens: 45, cacheReadInputTokens: 30),
        new Meta(provider: 'openai', model: 'gpt-4o-mini'),
    );

    return new AgentPrompted($invocationId, $prompt, $response);
}

beforeEach(function () {
    config()->set('langfuse.enabled', true);
    config()->set('langfuse.host', 'https://langfuse.test');
    config()->set('langfuse.public_key', 'pk-test-123');
    config()->set('langfuse.secret_key', 'sk-test-456');
    config()->set('langfuse.sample_rate', 1.0);
    config()->set('langfuse.redact_pii', false);
    config()->set('langfuse.dispatch', 'queue');
});

it('AgentPrompted emite trace + generation num único batch com business_id', function () {
    Bus::fake();

    event(makeAgentPromptedEvent(businessId: 42));

    Bus::assertDispatched(LangfuseTraceJob::class, function (LangfuseTraceJob $job) {
        expect($job->events)->toHaveCount(2);

        [$trace, $generation] = $job->events;

        expect($trace['type'])->toBe('trace-create')
            ->and($trace['body']['name'])->toBe('fake-telemetry-agent')
            ->and($trace['body']['metadata']['business_id'])->toBe(42)
            ->and($trace['body']['input'])->toBe('qual o faturamento do mês?');

        expect($generation['type'])->toBe('generation-create')
            ->and($generation['body']['traceId'])->toBe($trace['body']['id'])
            ->and($generation['body']['model'])->toBe('gpt-4o-mini')
            ->and($generation['body']['usage']['input'])->toBe(120)
            ->and($generation['body']['usage']['output'])->toBe(45)
            ->and($generation['body']['output'])->toBe('faturamento foi X')
            ->and($generation['body']['metadata']['cache_read_input_tokens'])->toBe(30);

        return true;
    });
});

it('AgentStreamed (subclass) também emite — registro explícito no subscriber', function () {
    Bus::fake();

    $base = makeAgentPromptedEvent(businessId: 7, invocationId: 'inv-stream-1');
    event(new AgentStreamed($base->invocationId, $base->prompt, $base->response));

    Bus::assertDispatched(LangfuseTraceJob::class, function (LangfuseTraceJob $job) {
        expect($job->events[0]['body']['metadata']['business_id'])->toBe(7)
            ->and($job->events[0]['body']['metadata']['stream'])->toBeTrue();

        return true;
    });
});

it('mede duration_ms entre PromptingAgent e AgentPrompted (mesmo invocationId)', function () {
    Bus::fake();

    $done = makeAgentPromptedEvent(invocationId: 'inv-dur-1');
    event(new PromptingAgent('inv-dur-1', $done->prompt));
    usleep(10_000); // 10ms
    event($done);

    Bus::assertDispatched(LangfuseTraceJob::class, function (LangfuseTraceJob $job) {
        $generation = $job->events[1];
        // endTime presente = duration_ms foi calculado (>= 10ms de sleep)
        expect($generation['body'])->toHaveKey('endTime');

        return true;
    });
});

it('LANGFUSE_ENABLED=false → zero side-effect', function () {
    config()->set('langfuse.enabled', false);

    Bus::fake();
    Http::fake();

    event(makeAgentPromptedEvent());

    Bus::assertNothingDispatched();
    Http::assertNothingSent();
});

it('fail-open: erro no client NUNCA propaga pra chamada LLM', function () {
    // Client quebrado no container — listener deve engolir e só logar debug.
    app()->bind(\Modules\Jana\Services\Telemetry\LangfuseClient::class, function () {
        return new class extends \Modules\Jana\Services\Telemetry\LangfuseClient
        {
            public function traceComGeneration(array $trace, array $generation): string
            {
                throw new RuntimeException('langfuse indisponível');
            }
        };
    });

    expect(fn () => event(makeAgentPromptedEvent()))->not->toThrow(Exception::class);
});
