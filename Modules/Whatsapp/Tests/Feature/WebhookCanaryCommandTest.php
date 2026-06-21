<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Modules\Whatsapp\Console\Commands\WebhookCanaryCommand;

uses(Tests\TestCase::class);

/**
 * Canário do webhook WhatsApp — Fase 1 perda-zero (incidente 2026-06-16 #2726).
 *
 * A lógica de veredito vive em evaluateCanary (estática, sem rede — mesmo padrão
 * de HealthCheckCommand::evaluateInboundFlow). Os testes de fiação usam Http::fake
 * (determinístico, sem rede real — o host de dev não tem PHP/pest; o vermelho roda
 * no CI). O shape sintético espelha o "contrato REAL do daemon" provado em
 * WhatsmeowWebhookAuthTest (Presence → 200 sem criar mensagem).
 *
 * @see Modules/Whatsapp/Console/Commands/WebhookCanaryCommand.php
 */

// ─── Lógica pura do veredito ────────────────────────────────────────────────

test('evaluateCanary: HTTP 200 = ok', function () {
    $r = WebhookCanaryCommand::evaluateCanary(200, null);
    expect($r['ok'])->toBeTrue();
    expect($r['status'])->toBe(200);
    expect($r['reason'])->toBe('HTTP 200');
});

test('evaluateCanary: não-200 (401/500) = falha', function () {
    expect(WebhookCanaryCommand::evaluateCanary(401, null)['ok'])->toBeFalse();
    $r = WebhookCanaryCommand::evaluateCanary(500, null);
    expect($r['ok'])->toBeFalse();
    expect($r['reason'])->toContain('500');
    expect($r['reason'])->toContain('esperado 200');
});

test('evaluateCanary: erro de conexão (timeout) = falha sem status', function () {
    $r = WebhookCanaryCommand::evaluateCanary(null, 'cURL error 28: timeout');
    expect($r['ok'])->toBeFalse();
    expect($r['status'])->toBeNull();
    expect($r['reason'])->toContain('erro de conexão');
});

test('syntheticPresenceBody espelha o envelope real do daemon (Presence, sem PII)', function () {
    $outer = json_decode(WebhookCanaryCommand::syntheticPresenceBody(), true);
    expect($outer)->toHaveKeys(['instanceName', 'jsonData']);

    $inner = json_decode($outer['jsonData'], true);
    expect($inner['type'])->toBe('Presence');           // benigno — ACKa 200 sem criar mensagem
    expect($inner['event']['Info']['Chat'])->toBe('');   // sem JID real → no_channel → 200
});

// ─── Fiação do comando (Http::fake, sem rede) ───────────────────────────────

test('200 → exit 0, grava tick verde no cache, manda o segredo na query e Presence no body', function () {
    Cache::flush();
    Http::fake(['*' => Http::response('{"ok":true,"note":"no_channel"}', 200)]);
    config([
        'whatsapp.canary.enabled' => true,
        'whatsapp.whatsmeow.webhook_url_secret' => 'wh-test',
    ]);

    $this->artisan('whatsapp:webhook-canary', [
        '--url' => 'http://localhost/api/whatsapp/webhook/whatsmeow/uuid-alvo',
    ])->assertExitCode(0);

    $last = Cache::get(WebhookCanaryCommand::CACHE_KEY);
    expect($last['ok'])->toBeTrue();
    expect($last['status'])->toBe(200);
    expect($last['at'])->not->toBeNull();

    Http::assertSent(fn ($req) => str_contains($req->url(), 'wh=wh-test')
        && str_contains($req->body(), 'Presence')
        && str_contains($req->body(), 'oimpresso-webhook-canary'));
});

test('não-200 → exit 1 e grava tick vermelho no cache', function () {
    Cache::flush();
    Http::fake(['*' => Http::response('unauthorized', 401)]);
    config([
        'whatsapp.canary.enabled' => true,
        'whatsapp.whatsmeow.webhook_url_secret' => 'wh-test',
    ]);

    $this->artisan('whatsapp:webhook-canary', [
        '--url' => 'http://localhost/api/whatsapp/webhook/whatsmeow/uuid-alvo',
    ])->assertExitCode(1);

    $last = Cache::get(WebhookCanaryCommand::CACHE_KEY);
    expect($last['ok'])->toBeFalse();
    expect($last['status'])->toBe(401);
});

test('sem segredo configurado → pula (exit 0) e NÃO grava cache (cold-start no health-check)', function () {
    Cache::flush();
    Http::fake();
    config([
        'whatsapp.canary.enabled' => true,
        'whatsapp.whatsmeow.webhook_url_secret' => '',
    ]);

    $this->artisan('whatsapp:webhook-canary')->assertExitCode(0);

    expect(Cache::get(WebhookCanaryCommand::CACHE_KEY))->toBeNull();
    Http::assertNothingSent();
});

test('desabilitado via config → pula (exit 0) sem tocar a rede', function () {
    Cache::flush();
    Http::fake();
    config([
        'whatsapp.canary.enabled' => false,
        'whatsapp.whatsmeow.webhook_url_secret' => 'wh-test',
    ]);

    $this->artisan('whatsapp:webhook-canary')->assertExitCode(0);
    Http::assertNothingSent();
});
