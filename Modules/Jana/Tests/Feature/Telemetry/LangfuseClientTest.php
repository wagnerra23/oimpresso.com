<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Modules\Jana\Jobs\Telemetry\LangfuseTraceJob;
use Modules\Jana\Services\Telemetry\LangfuseClient;

uses(Tests\TestCase::class);

/**
 * Cobertura LangfuseClient (ADR 0132 + ADR 0037 §GAP-1).
 *
 *  - Flag LANGFUSE_ENABLED=false → 0 dispatch (zero side-effect)
 *  - Modo 'log' → não chama Http nem Bus, só loga payload
 *  - Modo 'sync' → flushBatch HTTP imediato; payload formato Langfuse v3
 *  - Modo 'queue' (default) → dispatch LangfuseTraceJob
 *  - business_id presente em metadata do trace (multi-tenant Tier 0)
 *  - Fail-open: HTTP 500/timeout viram warning, NUNCA exception
 *  - Score event format
 */

beforeEach(function () {
    config()->set('langfuse.enabled', true);
    config()->set('langfuse.host', 'https://langfuse.test');
    config()->set('langfuse.public_key', 'pk-test-123');
    config()->set('langfuse.secret_key', 'sk-test-456');
    config()->set('langfuse.sample_rate', 1.0);
    config()->set('langfuse.redact_pii', false); // testes não testam redactor aqui
});

it('skipa todas chamadas quando LANGFUSE_ENABLED=false', function () {
    config()->set('langfuse.enabled', false);

    Bus::fake();
    Http::fake();

    $client = new LangfuseClient;
    $traceId = $client->startTrace([
        'name' => 'jana-chat',
        'business_id' => 1,
        'user_id' => 1,
        'input' => 'oi',
    ]);

    expect($traceId)->toBeString()->not->toBeEmpty();
    Bus::assertNothingDispatched();
    Http::assertNothingSent();
});

it('em modo log NÃO chama Http nem dispatch fila', function () {
    config()->set('langfuse.dispatch', 'log');

    Bus::fake();
    Http::fake();

    $client = new LangfuseClient;
    $client->startTrace([
        'name' => 'jana-chat',
        'business_id' => 1,
        'user_id' => 1,
        'input' => 'pergunta',
    ]);

    Bus::assertNothingDispatched();
    Http::assertNothingSent();
});

it('em modo queue dispara LangfuseTraceJob com batch events', function () {
    config()->set('langfuse.dispatch', 'queue');

    Bus::fake();

    $client = new LangfuseClient;
    $traceId = $client->startTrace([
        'name' => 'kb-answer',
        'business_id' => 1,
        'user_id' => 1,
        'input' => 'qual é o faturamento?',
        'tool' => 'kb-answer',
    ]);

    expect($traceId)->toBeString();

    Bus::assertDispatched(LangfuseTraceJob::class, function ($job) {
        return count($job->events) === 1
            && $job->events[0]['type'] === 'trace-create'
            && $job->events[0]['body']['name'] === 'kb-answer'
            && $job->events[0]['body']['metadata']['business_id'] === 1
            && $job->events[0]['body']['metadata']['tool'] === 'kb-answer';
    });
});

it('em modo sync chama Http::post com batch + Basic Auth', function () {
    config()->set('langfuse.dispatch', 'sync');

    Http::fake([
        'https://langfuse.test/api/public/ingestion' => Http::response(['status' => 'ok'], 207),
    ]);

    $client = new LangfuseClient;
    $client->startTrace([
        'name' => 'jana-chat',
        'business_id' => 7,
        'user_id' => 2,
        'input' => 'oi',
    ]);

    Http::assertSent(function ($request) {
        $body = $request->data();

        return $request->url() === 'https://langfuse.test/api/public/ingestion'
            && $request->hasHeader('Authorization')
            && str_starts_with($request->header('Authorization')[0] ?? '', 'Basic ')
            && isset($body['batch'])
            && is_array($body['batch'])
            && count($body['batch']) === 1
            && $body['batch'][0]['type'] === 'trace-create';
    });
});

it('inclui business_id em metadata do trace (multi-tenant Tier 0)', function () {
    config()->set('langfuse.dispatch', 'sync');

    Http::fake([
        'https://langfuse.test/api/public/ingestion' => Http::response([], 207),
    ]);

    $client = new LangfuseClient;
    $client->startTrace([
        'name' => 'handoff-diff',
        'business_id' => 99,
        'user_id' => 5,
        'input' => 'diff',
    ]);

    Http::assertSent(function ($request) {
        $event = $request->data()['batch'][0];

        return $event['body']['metadata']['business_id'] === 99;
    });
});

it('inclui business_id como TAG do trace — filtro multi-tenant no UI (US-COPI-132)', function () {
    config()->set('langfuse.dispatch', 'sync');
    config()->set('langfuse.environment', 'production');

    Http::fake([
        'https://langfuse.test/api/public/ingestion' => Http::response([], 207),
    ]);

    $client = new LangfuseClient;
    $client->startTrace([
        'name' => 'brain-b-agent',
        'business_id' => 4,
        'input' => 'faturamento',
    ]);

    Http::assertSent(function ($request) {
        $tags = $request->data()['batch'][0]['body']['tags'];

        // Tier 0: a tag business_id:{N} tem que estar presente pra filtrar por tenant
        return in_array('business_id:4', $tags, true)
            && in_array('production', $tags, true);
    });
});

it('não cria tag business_id quando ausente (não vaza tag vazia)', function () {
    config()->set('langfuse.dispatch', 'sync');

    Http::fake([
        'https://langfuse.test/api/public/ingestion' => Http::response([], 207),
    ]);

    $client = new LangfuseClient;
    $client->startTrace([
        'name' => 'anonymous-agent',
        'input' => 'x',
    ]);

    Http::assertSent(function ($request) {
        $tags = $request->data()['batch'][0]['body']['tags'];

        // Nenhuma tag começando com "business_id:" quando business_id é null
        return count(array_filter($tags, fn ($t) => str_starts_with($t, 'business_id:'))) === 0;
    });
});

it('fail-open quando Http retorna 500 — não lança exception', function () {
    config()->set('langfuse.dispatch', 'sync');

    Http::fake([
        'https://langfuse.test/api/public/ingestion' => Http::response(['error' => 'down'], 500),
    ]);

    $client = new LangfuseClient;

    // Não deve lançar exception
    $traceId = $client->startTrace([
        'name' => 'jana-chat',
        'business_id' => 1,
        'user_id' => 1,
        'input' => 'oi',
    ]);

    expect($traceId)->toBeString();

    // Confirma que Http foi tentado
    Http::assertSentCount(1);
});

it('recordGeneration emite generation-create com usage + duration', function () {
    config()->set('langfuse.dispatch', 'queue');

    Bus::fake();

    $client = new LangfuseClient;
    $client->recordGeneration('trace-abc-123', [
        'name' => 'chat-response',
        'model' => 'gpt-4o-mini',
        'input' => 'pergunta',
        'output' => 'resposta',
        'usage' => ['input' => 100, 'output' => 50],
        'duration_ms' => 1234,
    ]);

    Bus::assertDispatched(LangfuseTraceJob::class, function ($job) {
        $event = $job->events[0];

        return $event['type'] === 'generation-create'
            && $event['body']['traceId'] === 'trace-abc-123'
            && $event['body']['name'] === 'chat-response'
            && $event['body']['model'] === 'gpt-4o-mini'
            && $event['body']['usage']['input'] === 100
            && $event['body']['usage']['output'] === 50
            && $event['body']['usage']['total'] === 150;
    });
});

it('recordScore emite score-create (wire RAGAS judge → Langfuse)', function () {
    config()->set('langfuse.dispatch', 'queue');

    Bus::fake();

    $client = new LangfuseClient;
    $client->recordScore('trace-xyz', 'ragas_faithfulness', 0.87, 'judge gpt-4o-mini');

    Bus::assertDispatched(LangfuseTraceJob::class, function ($job) {
        $event = $job->events[0];

        return $event['type'] === 'score-create'
            && $event['body']['traceId'] === 'trace-xyz'
            && $event['body']['name'] === 'ragas_faithfulness'
            && $event['body']['value'] === 0.87
            && $event['body']['dataType'] === 'NUMERIC';
    });
});

it('skipa emissão quando sample_rate=0 (off por amostragem)', function () {
    config()->set('langfuse.sample_rate', 0.0);
    config()->set('langfuse.dispatch', 'sync');

    Http::fake();

    $client = new LangfuseClient;
    $client->startTrace([
        'name' => 'jana-chat',
        'business_id' => 1,
        'user_id' => 1,
        'input' => 'oi',
    ]);

    Http::assertNothingSent();
});

it('flushBatch retorna false quando keys ausentes (silenciosamente)', function () {
    config()->set('langfuse.public_key', '');
    config()->set('langfuse.secret_key', '');

    Http::fake();

    $client = new LangfuseClient;
    $result = $client->flushBatch([
        [
            'id' => 'e1',
            'type' => 'trace-create',
            'timestamp' => '2026-05-13T00:00:00.000Z',
            'body' => ['id' => 't1', 'name' => 'test'],
        ],
    ]);

    expect($result)->toBeFalse();
    Http::assertNothingSent();
});
