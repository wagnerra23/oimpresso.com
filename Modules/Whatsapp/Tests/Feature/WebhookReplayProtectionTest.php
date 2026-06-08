<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Modules\Whatsapp\Http\Middleware\VerifyBaileysWebhookHmac;

uses(Tests\TestCase::class);

/**
 * Regression test pro middleware replay protection HMAC + nonce
 * (US-WA-082 — Dogfood `whatsapp-arch-arte` 2026-05-14).
 *
 * Garante:
 *   1. Sem headers → backward compat passa direto (daemon antigo)
 *   2. HMAC válida + nonce novo + ts dentro window → 200
 *   3. HMAC inválida → 401
 *   4. ts fora replay window (>5min) → 401
 *   5. Nonce repetido → 401 (replay detectado)
 *   6. Constant-time compare via hash_equals (manual test)
 *
 * @see Modules/Whatsapp/Http/Middleware/VerifyBaileysWebhookHmac.php
 */
beforeEach(function () {
    Schema::dropIfExists('webhook_nonces');
    Schema::create('webhook_nonces', function ($table) {
        $table->bigIncrements('id');
        $table->string('nonce', 64)->unique();
        $table->string('source', 32);
        $table->timestamp('created_at');
        $table->index('created_at', 'webhook_nonces_created_at_idx');
    });

    config(['whatsapp.baileys.api_key' => 'test-api-key-32-bytes-secret-xyz']);
});

function buildHmacRequest(string $apiKey, string $body, ?string $nonceOverride = null, ?int $tsOverride = null): array
{
    $nonce = $nonceOverride ?? \Illuminate\Support\Str::uuid()->toString();
    $ts = (string) ($tsOverride ?? time());
    $signed = "{$ts}.{$nonce}.{$body}";
    $signature = hash_hmac('sha256', $signed, $apiKey);

    return [
        'x-baileys-nonce' => $nonce,
        'x-baileys-ts' => $ts,
        'x-baileys-signature' => $signature,
    ];
}

it('R-WA-HMAC-001 — sem headers HMAC passa direto (backward compat daemon antigo)', function () {
    $request = \Illuminate\Http\Request::create('/api/atendimento/channels/baileys/aaa-bbb-ccc', 'POST');
    $middleware = new VerifyBaileysWebhookHmac();

    $response = $middleware->handle($request, fn ($r) => response()->json(['next' => true], 200));

    expect($response->getStatusCode())->toBe(200);
});

it('R-WA-HMAC-002 — HMAC válida + nonce novo + ts dentro window → next', function () {
    $apiKey = config('whatsapp.baileys.api_key');
    $body = json_encode(['event' => 'connected']);
    $headers = buildHmacRequest($apiKey, $body);

    $request = \Illuminate\Http\Request::create('/api/atendimento/channels/baileys/aaa', 'POST', [], [], [], [], $body);
    foreach ($headers as $k => $v) {
        $request->headers->set($k, $v);
    }

    $middleware = new VerifyBaileysWebhookHmac();
    $response = $middleware->handle($request, fn ($r) => response()->json(['next' => true], 200));

    expect($response->getStatusCode())->toBe(200);
    // Nonce gravado na tabela
    expect(DB::table('webhook_nonces')->where('nonce', $headers['x-baileys-nonce'])->exists())->toBeTrue();
});

it('R-WA-HMAC-003 — HMAC inválida → 401 invalid_signature', function () {
    $body = json_encode(['event' => 'connected']);
    $headers = [
        'x-baileys-nonce' => \Illuminate\Support\Str::uuid()->toString(),
        'x-baileys-ts' => (string) time(),
        'x-baileys-signature' => 'invalid-signature-deadbeef',
    ];

    $request = \Illuminate\Http\Request::create('/test', 'POST', [], [], [], [], $body);
    foreach ($headers as $k => $v) {
        $request->headers->set($k, $v);
    }

    $middleware = new VerifyBaileysWebhookHmac();
    $response = $middleware->handle($request, fn ($r) => response()->json(['next' => true], 200));

    expect($response->getStatusCode())->toBe(401);
    expect(json_decode($response->getContent(), true)['error'])->toBe('invalid_signature');
});

it('R-WA-HMAC-004 — ts fora replay window (>5min) → 401', function () {
    $apiKey = config('whatsapp.baileys.api_key');
    $body = json_encode(['event' => 'connected']);
    // ts 10min atrás (fora janela 5min)
    $headers = buildHmacRequest($apiKey, $body, null, time() - 600);

    $request = \Illuminate\Http\Request::create('/test', 'POST', [], [], [], [], $body);
    foreach ($headers as $k => $v) {
        $request->headers->set($k, $v);
    }

    $middleware = new VerifyBaileysWebhookHmac();
    $response = $middleware->handle($request, fn ($r) => response()->json(['next' => true], 200));

    expect($response->getStatusCode())->toBe(401);
    expect(json_decode($response->getContent(), true)['error'])->toBe('replay_window_expired');
});

it('R-WA-HMAC-005 — nonce repetido → 401 nonce_replayed', function () {
    $apiKey = config('whatsapp.baileys.api_key');
    $body = json_encode(['event' => 'connected']);
    $sharedNonce = \Illuminate\Support\Str::uuid()->toString();
    $headers = buildHmacRequest($apiKey, $body, $sharedNonce);

    $req1 = \Illuminate\Http\Request::create('/test', 'POST', [], [], [], [], $body);
    foreach ($headers as $k => $v) $req1->headers->set($k, $v);

    $middleware = new VerifyBaileysWebhookHmac();
    $r1 = $middleware->handle($req1, fn ($r) => response()->json(['next' => true], 200));
    expect($r1->getStatusCode())->toBe(200); // 1ª vez OK

    // 2ª request: mesmo nonce
    $req2 = \Illuminate\Http\Request::create('/test', 'POST', [], [], [], [], $body);
    foreach ($headers as $k => $v) $req2->headers->set($k, $v);
    $r2 = $middleware->handle($req2, fn ($r) => response()->json(['next' => true], 200));

    expect($r2->getStatusCode())->toBe(401);
    expect(json_decode($r2->getContent(), true)['error'])->toBe('nonce_replayed');
});

it('R-WA-HMAC-006 — API_KEY vazio no .env → middleware é no-op (defensive)', function () {
    config(['whatsapp.baileys.api_key' => '']);

    $request = \Illuminate\Http\Request::create('/test', 'POST', [], [], [], [], 'qualquer body');
    $middleware = new VerifyBaileysWebhookHmac();
    $response = $middleware->handle($request, fn ($r) => response()->json(['next' => true], 200));

    expect($response->getStatusCode())->toBe(200);
});
