<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\Whatsapp\Http\Controllers\Api\WhatsmeowWebhookController;
use Modules\Whatsapp\Http\Middleware\VerifyWhatsmeowSignature;

uses(Tests\TestCase::class);

/**
 * Auth do webhook Whatsmeow (Tier 0 segurança) — guard de regressão da remoção
 * do fallback de IP whitelist spoofável (fix 2026-06-14, VerifyWhatsmeowSignature).
 *
 * red-first: este host de dev NÃO tem PHP/pest (`php: command not found`) — o run
 * vermelho roda no CI. Contra o `main` atual (que ainda tem o fallback de IP), os
 * casos "X-Forwarded-For forjado → 401" FALHAM vermelho: o middleware antigo cai na
 * trilha de IP (`$request->ip()` reflete XFF sob TrustProxies '*') e retorna 200.
 * Este teste DISCRIMINA o bug — prova que IP não autentica mais. Pós-fix = verde.
 *
 * Cobre:
 *  - HMAC válido = 200
 *  - HMAC inválido = 401
 *  - sem HMAC + sem Token, X-Forwarded-For forjado (10.0.0.1) = 401   ← regressão
 *  - sem HMAC + sem Token, X-Forwarded-For = IP do daemon (177.74.67.30) = 401
 *  - sem HMAC + sem Token + sem XFF = 401 (fail-closed)
 *  - HMAC presente mas secret ausente = 401 (fail-closed, sem downgrade)
 *  - business_uuid inexistente = 404
 *
 * Padrão SQLite-friendly (cria `business` em beforeEach), espelha WebhookSignatureTest.
 */

beforeEach(function () {
    // era-sqlite: este teste cria schema manual (sqlite-friendly). No MySQL persistente
    // do nightly isso DROPA tabelas reais → corrompe os testes irmãos (lever do floor SDD).
    // Cobertura real é na lane sqlite (per-PR); pula no MySQL.
    if (config('database.default') !== 'sqlite') {
        $this->markTestSkipped('era-sqlite: corruptor de schema compartilhado no MySQL — sqlite-only no burn-down do floor SDD.');
    }
    Schema::dropIfExists('business');
    Schema::create('business', function ($table) {
        $table->bigIncrements('id');
        $table->uuid('uuid')->nullable()->index();
        $table->string('name')->nullable();
        $table->timestamps();
    });

    config(['whatsapp.whatsmeow.hmac_secret' => 'super-secret-hmac-key-0123456789ab']);

    app('router')->aliasMiddleware('whatsapp.whatsmeow.signature', VerifyWhatsmeowSignature::class);
    Route::post('/api/whatsapp/webhook/whatsmeow/{business_uuid}', [WhatsmeowWebhookController::class, 'handle'])
        ->middleware('whatsapp.whatsmeow.signature');
});

function wmAuthSeedBusiness(string $uuid): void
{
    DB::table('business')->insert([
        'uuid' => $uuid,
        'name' => 'Test Biz',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('HMAC válido retorna 200', function () {
    $uuid = Str::uuid()->toString();
    wmAuthSeedBusiness($uuid);

    // Evento desconhecido sem Username/instanceName → controller responde
    // 200 no_channel sem tocar a tabela `channels`.
    $body = json_encode(['type' => 'Ping']);
    $hmac = hash_hmac('sha256', $body, (string) config('whatsapp.whatsmeow.hmac_secret'));

    $response = $this->call(
        'POST',
        "/api/whatsapp/webhook/whatsmeow/{$uuid}",
        [], [], [],
        ['HTTP_X-HMAC-SIGNATURE' => "sha256={$hmac}", 'CONTENT_TYPE' => 'application/json'],
        $body
    );

    $response->assertStatus(200);
});

it('HMAC inválido retorna 401', function () {
    $uuid = Str::uuid()->toString();
    wmAuthSeedBusiness($uuid);

    $response = $this->postJson(
        "/api/whatsapp/webhook/whatsmeow/{$uuid}",
        ['type' => 'Ping'],
        ['x-hmac-signature' => 'sha256=000000deadbeef000000']
    );

    $response->assertStatus(401);
});

it('sem HMAC nem Token, com X-Forwarded-For forjado em 10.0.0.0/8 = 401 (IP não autentica mais)', function () {
    $uuid = Str::uuid()->toString();
    wmAuthSeedBusiness($uuid);

    // Sob o fallback antigo, este XFF cairia em `10.0.0.0/8` → 200. Pós-fix → 401.
    $response = $this->postJson(
        "/api/whatsapp/webhook/whatsmeow/{$uuid}",
        ['type' => 'Message'],
        ['X-Forwarded-For' => '10.0.0.1']
    );

    $response->assertStatus(401);
});

it('sem HMAC nem Token, com X-Forwarded-For = IP público do daemon = 401', function () {
    $uuid = Str::uuid()->toString();
    wmAuthSeedBusiness($uuid);

    // Mesmo forjando o IP exato que o fallback antigo aceitava (CT 100 egress),
    // a auth agora exige HMAC/Token — IP é input morto.
    $response = $this->postJson(
        "/api/whatsapp/webhook/whatsmeow/{$uuid}",
        ['type' => 'Message'],
        ['X-Forwarded-For' => '177.74.67.30']
    );

    $response->assertStatus(401);
});

it('sem HMAC nem Token nem XFF = 401 (fail-closed)', function () {
    $uuid = Str::uuid()->toString();
    wmAuthSeedBusiness($uuid);

    $response = $this->postJson(
        "/api/whatsapp/webhook/whatsmeow/{$uuid}",
        ['type' => 'Message'],
    );

    $response->assertStatus(401);
});

it('HMAC presente mas WHATSMEOW_HMAC_SECRET ausente = 401 (fail-closed, sem downgrade)', function () {
    config(['whatsapp.whatsmeow.hmac_secret' => '']);
    $uuid = Str::uuid()->toString();
    wmAuthSeedBusiness($uuid);

    // Secret vazio NÃO pode rebaixar pra trilha mais fraca. Sob o código antigo,
    // secret vazio pulava o HMAC e caía no IP (XFF forjado) → 200. Pós-fix → 401.
    $response = $this->postJson(
        "/api/whatsapp/webhook/whatsmeow/{$uuid}",
        ['type' => 'Message'],
        ['x-hmac-signature' => 'sha256=deadbeef', 'X-Forwarded-For' => '10.0.0.1']
    );

    $response->assertStatus(401);
});

it('business_uuid inexistente = 404', function () {
    $response = $this->postJson(
        '/api/whatsapp/webhook/whatsmeow/00000000-0000-0000-0000-000000000000',
        ['type' => 'Message'],
        ['X-Forwarded-For' => '10.0.0.1']
    );

    $response->assertStatus(404);
});
