<?php

declare(strict_types=1);

/**
 * US-INFRA-016 PR-1 (ADR 0132) — Tests do OtlpHttpHandler.
 *
 * Cobre:
 *   1. success → POST 200 → exporter envia payload OTLP corretamente
 *   2. HTTP 500 → fallback silencioso (error_log, sem throw que quebraria chat)
 *   3. timeout → mesma fallback silenciosa
 *   4. records que não são gen_ai.span são ignorados
 *   5. skip silencioso quando keys vazias (.env não configurado)
 *
 * Princípio 8 ADR 0094 (confiabilidade com fallback): handler nunca propaga
 * exceção pra cima — telemetria não pode quebrar Jana.
 */

use App\Logging\OtlpHttpHandler;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Monolog\Level;
use Monolog\LogRecord;

function makeSpanRecord(array $extraAttrs = []): LogRecord
{
    return new LogRecord(
        datetime: new \DateTimeImmutable('2026-05-10 22:30:00'),
        channel: 'otel-gen-ai-langfuse',
        level: Level::Info,
        message: 'gen_ai.span',
        context: array_merge([
            'gen_ai.system' => 'anthropic',
            'gen_ai.request.model' => 'claude-sonnet-4-6',
            'gen_ai.operation.name' => 'chat',
            'gen_ai.response.duration_ms' => 1234,
            'gen_ai.business_id' => 1,
            'gen_ai.user.id' => 42,
            'gen_ai.conversation.id' => 99,
            'gen_ai.copiloto.prompt_chars' => 256,
            'gen_ai.copiloto.driver' => 'laravel_ai_sdk',
            'gen_ai.usage.input_tokens' => 100,
            'gen_ai.usage.output_tokens' => 50,
            'gen_ai.response.finish_reason' => 'stop',
        ], $extraAttrs),
    );
}

it('exporta span com payload OTLP/JSON correto quando POST returns 200', function () {
    $captured = new \ArrayObject();
    $mock = new MockHandler([new Response(200, [], '{}')]);
    $stack = HandlerStack::create($mock);
    $stack->push(function (callable $next) use ($captured) {
        return function ($request, $options) use ($next, $captured) {
            $captured[] = $request;
            return $next($request, $options);
        };
    });

    $handler = new OtlpHttpHandler(
        endpoint: 'https://langfuse.test/api/public/otel/v1/traces',
        publicKey: 'pk-lf-test',
        secretKey: 'sk-lf-test',
        serviceName: 'jana-test',
        timeoutSeconds: 1.0,
        client: new Client(['handler' => $stack]),
    );

    $handler->handle(makeSpanRecord());

    expect($captured->count())->toBe(1);

    /** @var Request $request */
    $request = $captured[0];

    expect($request->getMethod())->toBe('POST');
    expect((string) $request->getUri())->toBe('https://langfuse.test/api/public/otel/v1/traces');

    $authHeader = $request->getHeaderLine('Authorization');
    expect($authHeader)->toStartWith('Basic ');
    expect(base64_decode(substr($authHeader, 6)))->toBe('pk-lf-test:sk-lf-test');

    $body = json_decode((string) $request->getBody(), true);
    expect($body)->toHaveKey('resourceSpans');
    expect($body['resourceSpans'])->toHaveCount(1);

    $span = $body['resourceSpans'][0]['scopeSpans'][0]['spans'][0];
    expect($span['name'])->toBe('chat');
    expect($span['kind'])->toBe(3);
    expect($span['traceId'])->toMatch('/^[0-9a-f]{32}$/');
    expect($span['spanId'])->toMatch('/^[0-9a-f]{16}$/');

    $attrs = collect($span['attributes'])->keyBy('key');
    expect($attrs['gen_ai.system']['value']['stringValue'])->toBe('anthropic');
    expect($attrs['gen_ai.business_id']['value']['intValue'])->toBe('1');
    expect($attrs['gen_ai.response.duration_ms']['value']['intValue'])->toBe('1234');
    expect($attrs['gen_ai.usage.input_tokens']['value']['intValue'])->toBe('100');

    // Dual-emit: cada int também tem irmã .str (stringValue) — workaround Langfuse v3
    // que só renderiza stringValue no UI. Quando upstream fixar, remover .str.
    expect($attrs['gen_ai.business_id.str']['value']['stringValue'])->toBe('1');
    expect($attrs['gen_ai.response.duration_ms.str']['value']['stringValue'])->toBe('1234');
    expect($attrs['gen_ai.usage.input_tokens.str']['value']['stringValue'])->toBe('100');
});

it('cai em fallback silencioso quando POST returns HTTP 500 (não throws)', function () {
    $captured = new \ArrayObject();
    $mock = new MockHandler([
        new ServerException(
            'Server error',
            new Request('POST', 'https://langfuse.test/api/public/otel/v1/traces'),
            new Response(500, [], 'Internal Server Error'),
        ),
    ]);
    $stack = HandlerStack::create($mock);
    $stack->push(function (callable $next) use ($captured) {
        return function ($request, $options) use ($next, $captured) {
            $captured[] = $request;
            return $next($request, $options);
        };
    });

    $handler = new OtlpHttpHandler(
        endpoint: 'https://langfuse.test/api/public/otel/v1/traces',
        publicKey: 'pk-lf-test',
        secretKey: 'sk-lf-test',
        serviceName: 'jana-test',
        timeoutSeconds: 1.0,
        client: new Client(['handler' => $stack]),
    );

    expect(fn () => $handler->handle(makeSpanRecord()))->not->toThrow(\Throwable::class);
    expect($captured->count())->toBe(1);
});

it('cai em fallback silencioso quando POST dá timeout (não throws)', function () {
    $captured = new \ArrayObject();
    $mock = new MockHandler([
        new ConnectException(
            'Connection timed out',
            new Request('POST', 'https://langfuse.test/api/public/otel/v1/traces'),
        ),
    ]);
    $stack = HandlerStack::create($mock);
    $stack->push(function (callable $next) use ($captured) {
        return function ($request, $options) use ($next, $captured) {
            $captured[] = $request;
            return $next($request, $options);
        };
    });

    $handler = new OtlpHttpHandler(
        endpoint: 'https://langfuse.test/api/public/otel/v1/traces',
        publicKey: 'pk-lf-test',
        secretKey: 'sk-lf-test',
        serviceName: 'jana-test',
        timeoutSeconds: 1.0,
        client: new Client(['handler' => $stack]),
    );

    expect(fn () => $handler->handle(makeSpanRecord()))->not->toThrow(\Throwable::class);
    expect($captured->count())->toBe(1);
});

it('ignora records que não são gen_ai.span (não exporta)', function () {
    $captured = new \ArrayObject();
    $mock = new MockHandler([new Response(200, [], '{}')]);
    $stack = HandlerStack::create($mock);
    $stack->push(function (callable $next) use ($captured) {
        return function ($request, $options) use ($next, $captured) {
            $captured[] = $request;
            return $next($request, $options);
        };
    });

    $handler = new OtlpHttpHandler(
        endpoint: 'https://langfuse.test/api/public/otel/v1/traces',
        publicKey: 'pk-lf-test',
        secretKey: 'sk-lf-test',
        client: new Client(['handler' => $stack]),
    );

    $other = new LogRecord(
        datetime: new \DateTimeImmutable('2026-05-10 22:30:00'),
        channel: 'otel-gen-ai-langfuse',
        level: Level::Info,
        message: 'something.else',
        context: ['foo' => 'bar'],
    );

    $handler->handle($other);

    expect($captured->count())->toBe(0);
});

it('skip silencioso quando publicKey ou secretKey está vazio (.env não configurado ainda)', function () {
    $captured = new \ArrayObject();
    $mock = new MockHandler([new Response(200, [], '{}')]);
    $stack = HandlerStack::create($mock);
    $stack->push(function (callable $next) use ($captured) {
        return function ($request, $options) use ($next, $captured) {
            $captured[] = $request;
            return $next($request, $options);
        };
    });

    $handler = new OtlpHttpHandler(
        endpoint: 'https://langfuse.test/api/public/otel/v1/traces',
        publicKey: '',
        secretKey: '',
        client: new Client(['handler' => $stack]),
    );

    expect(fn () => $handler->handle(makeSpanRecord()))->not->toThrow(\Throwable::class);
    expect($captured->count())->toBe(0);
});
