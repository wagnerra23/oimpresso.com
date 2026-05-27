<?php

declare(strict_types=1);

/**
 * ADR 0191 — POST /api/consent (banner LGPD). Endpoint público (sem auth).
 * Cookie é unencrypted (excluído em EncryptCookies — zero PII).
 */

use Illuminate\Support\Facades\Cookie;

beforeEach(function () {
    Cookie::flushQueuedCookies();
});

test('POST /api/consent retorna 204 + cookie HttpOnly+SameSite=Lax com payload correto', function () {
    $cookieName = (string) config('services.consent.cookie_name');

    $response = $this->postJson('/api/consent', ['analytics' => true, 'marketing' => false]);

    $response->assertNoContent();

    $cookie = collect($response->headers->getCookies())
        ->firstWhere(fn ($c) => $c->getName() === $cookieName);

    expect($cookie)->not->toBeNull();
    expect($cookie->isHttpOnly())->toBeTrue();
    expect($cookie->getSameSite())->toBe('lax');

    $decoded = json_decode(urldecode($cookie->getValue()), true);
    expect($decoded)->toBeArray()
        ->and($decoded['analytics'])->toBeTrue()
        ->and($decoded['marketing'])->toBeFalse()
        ->and($decoded['ts'] ?? null)->toBeString();
});

test('POST /api/consent valida campos obrigatórios', function () {
    $response = $this->postJson('/api/consent', ['marketing' => false]);
    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['analytics']);
});

test('Inertia share reflete consent quando cookie presente', function () {
    $cookieName = (string) config('services.consent.cookie_name');
    $payload = json_encode(['analytics' => true, 'marketing' => false, 'ts' => now()->toIso8601String()]);

    $request = \Illuminate\Http\Request::create('/login', 'GET', [], [$cookieName => $payload]);
    $middleware = new \App\Http\Middleware\HandleInertiaRequests();
    $method = (new \ReflectionClass($middleware))->getMethod('consentShare');
    $method->setAccessible(true);

    $consent = $method->invoke($middleware, $request);

    expect($consent['needs_banner'])->toBeFalse()
        ->and($consent['analytics_accepted'])->toBeTrue()
        ->and($consent['marketing_accepted'])->toBeFalse();
});

test('Inertia share sem cookie devolve needs_banner=true', function () {
    $request = \Illuminate\Http\Request::create('/login', 'GET');
    $middleware = new \App\Http\Middleware\HandleInertiaRequests();
    $method = (new \ReflectionClass($middleware))->getMethod('consentShare');
    $method->setAccessible(true);

    $consent = $method->invoke($middleware, $request);

    expect($consent['needs_banner'])->toBeTrue()
        ->and($consent['analytics_accepted'])->toBeFalse()
        ->and($consent['marketing_accepted'])->toBeFalse();
});
