<?php

declare(strict_types=1);

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Modules\PaymentGateway\Services\HttpClientFactory;

uses(Tests\TestCase::class);

/**
 * Audit 2026-05-23 Onda 4e gap #1+#2 — GUARDs do HttpClientFactory.
 *
 * Cobre os 5 cenários críticos:
 *  1. 502 → sucesso na 3ª tentativa (retry funciona)
 *  2. 5× 502 consecutivos → esgota retries, devolve última Response (throw:false)
 *  3. 429 com Retry-After: 2 → respeita header
 *  4. 429 sem Retry-After → default 1s
 *  5. 4xx puro (400/422) → NÃO retry, devolve Response imediato
 *
 * NÃO instancia banco — testa Http::fake puro + parser direto.
 */

// ─── Sanity check de configuração ───────────────────────────────────────────

it('constantes HttpClientFactory documentadas + valores razoáveis', function () {
    expect(HttpClientFactory::RETRY_TIMES)->toBe(3);
    expect(HttpClientFactory::SLEEP_MS_BASE)->toBe(200);
    expect(HttpClientFactory::RETRY_AFTER_CAP_SECONDS)->toBe(30);
    expect(HttpClientFactory::RETRYABLE_5XX)->toContain(502)
        ->and(HttpClientFactory::RETRYABLE_5XX)->toContain(503)
        ->and(HttpClientFactory::RETRYABLE_5XX)->toContain(504)
        ->and(HttpClientFactory::RETRYABLE_5XX)->not->toContain(500);
});

// ─── Parser Retry-After ─────────────────────────────────────────────────────

it('parseRetryAfter aceita int em segundos', function () {
    expect(HttpClientFactory::parseRetryAfter('5'))->toBe(5)
        ->and(HttpClientFactory::parseRetryAfter('0'))->toBe(0)
        ->and(HttpClientFactory::parseRetryAfter('120'))->toBe(120);
});

it('parseRetryAfter aceita HTTP-date futura', function () {
    $future = gmdate('D, d M Y H:i:s', time() + 10) . ' GMT';
    $parsed = HttpClientFactory::parseRetryAfter($future);

    // Deve estar entre 9-11s (allowance pra clock skew do teste)
    expect($parsed)->toBeGreaterThanOrEqual(9)
        ->and($parsed)->toBeLessThanOrEqual(11);
});

it('parseRetryAfter default 1s se ausente/inválido', function () {
    expect(HttpClientFactory::parseRetryAfter(null))->toBe(1)
        ->and(HttpClientFactory::parseRetryAfter(''))->toBe(1)
        ->and(HttpClientFactory::parseRetryAfter('not-a-date'))->toBe(1);
});

// ─── GUARD #1: 502 transitório → sucesso na 3ª tentativa ────────────────────

it('GUARD #1 retry recupera de 502 transitório (sucesso na 3ª)', function () {
    Http::fake([
        'https://fake-bank.test/*' => Http::sequence()
            ->push(['error' => 'bad gateway'], 502)
            ->push(['error' => 'bad gateway'], 502)
            ->push(['ok' => true, 'id' => 'tx_001'], 200),
    ]);

    $client = HttpClientFactory::make('https://fake-bank.test', ['Accept' => 'application/json']);

    $response = HttpClientFactory::send(fn () => $client->get('/v1/cobrancas/123'));

    expect($response->successful())->toBeTrue();
    expect($response->json('id'))->toBe('tx_001');

    // 3 requests no total (1 original + 2 retries antes do success)
    Http::assertSentCount(3);
});

// ─── GUARD #2: 502 5× → esgota, devolve última Response sem throw ───────────

it('GUARD #2 502 consecutivo esgota retries + devolve última Response (throw:false)', function () {
    Http::fake([
        'https://fake-bank.test/*' => Http::response(['error' => 'bad gateway'], 502),
    ]);

    $client = HttpClientFactory::make('https://fake-bank.test');

    // Drivers usam send() wrapper que captura RequestException pós-esgotamento
    $response = HttpClientFactory::send(fn () => $client->get('/v1/health'));

    expect($response->failed())->toBeTrue();
    expect($response->status())->toBe(502);

    // Exatamente RETRY_TIMES requests (1 original + 2 retries = 3 total)
    Http::assertSentCount(HttpClientFactory::RETRY_TIMES);
});

// ─── GUARD #3: 429 com Retry-After: 0 → respeita (mas cap pra teste rápido) ─

it('GUARD #3 429 com Retry-After respeita header + retry', function () {
    // Retry-After: 0 (vai virar mínimo 1s pelo max(seconds, 1), mas teste roda)
    Http::fake([
        'https://fake-bank.test/*' => Http::sequence()
            ->push(['error' => 'too many'], 429, ['Retry-After' => '0'])
            ->push(['ok' => true], 200),
    ]);

    $client = HttpClientFactory::make('https://fake-bank.test');

    $start = microtime(true);
    $response = HttpClientFactory::send(fn () => $client->get('/v1/cobrancas'));
    $elapsedSec = microtime(true) - $start;

    expect($response->successful())->toBeTrue();
    // Sleep mínimo 1s (max(0, 1)) + sleep base 200ms entre retries
    expect($elapsedSec)->toBeGreaterThanOrEqual(1.0);
    // Não deve ter esperado mais que 5s (sanity — 0 capped a 1s)
    expect($elapsedSec)->toBeLessThan(5.0);

    Http::assertSentCount(2);
});

// ─── GUARD #4: 429 sem Retry-After → default 1s ────────────────────────────

it('GUARD #4 429 sem Retry-After usa default 1s', function () {
    Http::fake([
        'https://fake-bank.test/*' => Http::sequence()
            ->push(['error' => 'rate limited'], 429) // SEM Retry-After
            ->push(['ok' => true], 200),
    ]);

    $client = HttpClientFactory::make('https://fake-bank.test');

    $start = microtime(true);
    $response = HttpClientFactory::send(fn () => $client->get('/v1/cobrancas'));
    $elapsedSec = microtime(true) - $start;

    expect($response->successful())->toBeTrue();
    // Default 1s
    expect($elapsedSec)->toBeGreaterThanOrEqual(1.0);
    expect($elapsedSec)->toBeLessThan(3.0);

    Http::assertSentCount(2);
});

// ─── GUARD #5: 4xx não retry, devolve imediato ──────────────────────────────
// NOTA: 4xx não-retryável NÃO dispara throwIf (callback retorna false),
// então drivers podem chamar direto SEM send() wrapper. Mas para consistência,
// recomendamos sempre usar send() — é noop nesse caso.

it('GUARD #5 status 400 NÃO retry, devolve Response imediato', function () {
    Http::fake([
        'https://fake-bank.test/*' => Http::response(['error' => 'invalid payload'], 400),
    ]);

    $client = HttpClientFactory::make('https://fake-bank.test');

    $start = microtime(true);
    $response = HttpClientFactory::send(fn () => $client->post('/v1/cobrancas', ['valor' => 'invalid']));
    $elapsedSec = microtime(true) - $start;

    expect($response->status())->toBe(400);
    expect($response->failed())->toBeTrue();
    expect($elapsedSec)->toBeLessThan(1.0); // sem retry, retorna rápido

    // EXATAMENTE 1 request — sem retry pra 4xx
    Http::assertSentCount(1);
});

it('GUARD #5 status 401 NÃO retry, devolve Response imediato', function () {
    Http::fake([
        'https://fake-bank.test/*' => Http::response(['error' => 'unauthorized'], 401),
    ]);

    $client = HttpClientFactory::make('https://fake-bank.test');
    $response = HttpClientFactory::send(fn () => $client->get('/v1/cobrancas'));

    expect($response->status())->toBe(401);
    Http::assertSentCount(1);
});

it('GUARD #5 status 422 NÃO retry, devolve Response imediato', function () {
    Http::fake([
        'https://fake-bank.test/*' => Http::response(['errors' => ['valor' => 'required']], 422),
    ]);

    $client = HttpClientFactory::make('https://fake-bank.test');
    $response = HttpClientFactory::send(fn () => $client->post('/v1/cobrancas', []));

    expect($response->status())->toBe(422);
    Http::assertSentCount(1);
});

it('GUARD #5 status 500 puro NÃO retry (poderia ser bug payload)', function () {
    Http::fake([
        'https://fake-bank.test/*' => Http::response(['error' => 'internal'], 500),
    ]);

    $client = HttpClientFactory::make('https://fake-bank.test');
    $response = HttpClientFactory::send(fn () => $client->get('/v1/cobrancas'));

    expect($response->status())->toBe(500);
    Http::assertSentCount(1);
});

// ─── Healthcheck mode (withRetry: false) ───────────────────────────────────

it('withRetry:false NÃO faz retry mesmo em 502 (healthcheck mode)', function () {
    Http::fake([
        'https://fake-bank.test/*' => Http::response(['error' => 'down'], 502),
    ]);

    $client = HttpClientFactory::make(
        baseUrl: 'https://fake-bank.test',
        withRetry: false,
    );

    $response = $client->get('/health');

    expect($response->status())->toBe(502);
    Http::assertSentCount(1); // exato 1 request, sem retry
});

// ─── Headers passthrough ───────────────────────────────────────────────────

it('headers são propagados pro PendingRequest', function () {
    Http::fake([
        'https://fake-bank.test/*' => Http::response(['ok' => true], 200),
    ]);

    $client = HttpClientFactory::make(
        baseUrl: 'https://fake-bank.test',
        headers: ['X-Custom-Auth' => 'token-xyz', 'X-Request-Id' => 'req-001'],
    );

    $client->get('/v1/ping');

    Http::assertSent(function ($request) {
        return $request->hasHeader('X-Custom-Auth', 'token-xyz')
            && $request->hasHeader('X-Request-Id', 'req-001');
    });
});
