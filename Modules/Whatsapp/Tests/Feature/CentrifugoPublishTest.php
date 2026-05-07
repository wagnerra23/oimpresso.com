<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Modules\Whatsapp\Services\Centrifugo\CentrifugoPublisher;

uses(Tests\TestCase::class);

/**
 * ADR 0058 (Centrifugo real-time) · CentrifugoPublisher.
 *
 * Cobre:
 * - publish() faz POST {url}/api com X-API-Key + body JSON
 * - 2xx retorna true; 5xx ou exception retorna false (silencioso, log)
 * - config disabled retorna false sem chamar HTTP
 * - config sem url ou api_key retorna false sem chamar HTTP
 */

it('publica payload via POST /api com header X-API-Key', function () {
    config()->set('whatsapp.centrifugo.url', 'https://centrifugo.test');
    config()->set('whatsapp.centrifugo.api_key', 'test-key-xyz');
    config()->set('whatsapp.centrifugo.enabled', true);

    Http::fake([
        'centrifugo.test/api' => Http::response(['result' => []], 200),
    ]);

    $publisher = app(CentrifugoPublisher::class);
    $ok = $publisher->publish('whatsapp:business:4', [
        'event' => 'message_received',
        'message_id' => 42,
    ]);

    expect($ok)->toBeTrue();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://centrifugo.test/api'
            && $request->header('X-API-Key')[0] === 'test-key-xyz'
            && $request['method'] === 'publish'
            && $request['params']['channel'] === 'whatsapp:business:4'
            && $request['params']['data']['event'] === 'message_received';
    });
});

it('retorna false em HTTP 5xx (silencioso)', function () {
    config()->set('whatsapp.centrifugo.url', 'https://centrifugo.test');
    config()->set('whatsapp.centrifugo.api_key', 'k');
    config()->set('whatsapp.centrifugo.enabled', true);

    Http::fake([
        'centrifugo.test/api' => Http::response(['error' => 'oops'], 500),
    ]);

    expect(app(CentrifugoPublisher::class)->publish('ch', []))->toBeFalse();
});

it('retorna false quando enabled=false (sem chamar HTTP)', function () {
    config()->set('whatsapp.centrifugo.url', 'https://centrifugo.test');
    config()->set('whatsapp.centrifugo.api_key', 'k');
    config()->set('whatsapp.centrifugo.enabled', false);

    Http::fake();

    expect(app(CentrifugoPublisher::class)->publish('ch', []))->toBeFalse();

    Http::assertNothingSent();
});

it('retorna false quando url ou api_key vazios', function () {
    config()->set('whatsapp.centrifugo.url', '');
    config()->set('whatsapp.centrifugo.api_key', 'k');
    config()->set('whatsapp.centrifugo.enabled', true);

    Http::fake();

    expect(app(CentrifugoPublisher::class)->publish('ch', []))->toBeFalse();
    Http::assertNothingSent();

    config()->set('whatsapp.centrifugo.url', 'https://centrifugo.test');
    config()->set('whatsapp.centrifugo.api_key', '');

    expect(app(CentrifugoPublisher::class)->publish('ch', []))->toBeFalse();
    Http::assertNothingSent();
});

it('retorna false em exception de rede (silencioso)', function () {
    config()->set('whatsapp.centrifugo.url', 'https://centrifugo.test');
    config()->set('whatsapp.centrifugo.api_key', 'k');
    config()->set('whatsapp.centrifugo.enabled', true);

    Http::fake(function () {
        throw new \RuntimeException('connection refused');
    });

    expect(app(CentrifugoPublisher::class)->publish('ch', []))->toBeFalse();
});
