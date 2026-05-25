<?php

declare(strict_types=1);

/**
 * ADR 0191 — clarityShare() do HandleInertiaRequests retorna config Clarity
 * APENAS quando 5 guards passam (env on, project_id setado, user autenticado,
 * user_type não-interno, consent analytics aceito).
 *
 * Reflection-based: chama o protected method direto sem hidratar DB completo,
 * mesmo pattern usado em ConsentControllerTest.
 */

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Http\Request;

/**
 * Helper — chama HandleInertiaRequests::clarityShare() via reflection.
 *
 * @param  array{user?: object|null, cookie?: string|null}  $opts
 */
function callClarityShare(array $opts = []): ?array
{
    $cookieName = (string) config('services.consent.cookie_name', 'oimpresso_consent_v1');
    $cookies = isset($opts['cookie']) ? [$cookieName => $opts['cookie']] : [];

    $request = Request::create('/', 'GET', [], $cookies);
    if (array_key_exists('user', $opts) && $opts['user'] !== null) {
        $request->setUserResolver(fn () => $opts['user']);
    }

    $middleware = new HandleInertiaRequests();
    $method = (new ReflectionClass($middleware))->getMethod('clarityShare');
    $method->setAccessible(true);

    return $method->invoke($middleware, $request);
}

function fakeUser(string $userType = 'user', int $businessId = 4): object
{
    return new class($userType, $businessId) {
        public function __construct(public string $user_type, public int $business_id) {}
    };
}

function consentCookieAnalytics(bool $analytics): string
{
    return (string) json_encode(['analytics' => $analytics, 'marketing' => false, 'ts' => now()->toIso8601String()]);
}

beforeEach(function () {
    config()->set('services.clarity.enabled', true);
    config()->set('services.clarity.project_id', 'abc1234567');
    config()->set('services.clarity.mask_strategy', 'mask-all');
});

test('clarityShare retorna null quando CLARITY_ENABLED=false', function () {
    config()->set('services.clarity.enabled', false);
    $result = callClarityShare(['user' => fakeUser(), 'cookie' => consentCookieAnalytics(true)]);
    expect($result)->toBeNull();
});

test('clarityShare retorna null quando CLARITY_PROJECT_ID está vazio', function () {
    config()->set('services.clarity.project_id', null);
    $result = callClarityShare(['user' => fakeUser(), 'cookie' => consentCookieAnalytics(true)]);
    expect($result)->toBeNull();
});

test('clarityShare retorna null quando user não está autenticado', function () {
    $result = callClarityShare(['user' => null, 'cookie' => consentCookieAnalytics(true)]);
    expect($result)->toBeNull();
});

test('clarityShare retorna null quando user_type é superadmin', function () {
    $result = callClarityShare([
        'user' => fakeUser('superadmin'),
        'cookie' => consentCookieAnalytics(true),
    ]);
    expect($result)->toBeNull();
});

test('clarityShare retorna null quando user_type é user_oimpresso', function () {
    $result = callClarityShare([
        'user' => fakeUser('user_oimpresso'),
        'cookie' => consentCookieAnalytics(true),
    ]);
    expect($result)->toBeNull();
});

test('clarityShare retorna null quando consent analytics não foi aceito', function () {
    $result = callClarityShare([
        'user' => fakeUser(),
        'cookie' => consentCookieAnalytics(false),
    ]);
    expect($result)->toBeNull();
});

test('clarityShare retorna null quando cookie consent ausente', function () {
    $result = callClarityShare(['user' => fakeUser(), 'cookie' => null]);
    expect($result)->toBeNull();
});

test('clarityShare retorna config completa quando todos guards passam', function () {
    $result = callClarityShare([
        'user' => fakeUser('user', 4),
        'cookie' => consentCookieAnalytics(true),
    ]);

    expect($result)->toBeArray()
        ->and($result['project_id'])->toBe('abc1234567')
        ->and($result['business_id'])->toBe('4')
        ->and($result['user_type'])->toBe('user')
        ->and($result['mask_strategy'])->toBe('mask-all');
});
