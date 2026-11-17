<?php

declare(strict_types=1);

use App\Scopes\ScopeByBusiness;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\Whatsapp\Entities\WhatsappBusinessConfig;
use Modules\Whatsapp\Http\Middleware\VerifyMetaSignature;

uses(Tests\TestCase::class);

/**
 * R-WA-META-SECRET · webhook Meta com `meta_app_secret` vazio não aceita assinatura.
 *
 * Auditoria Whatsapp 2026-11-05 (eixo segurança, P1): `VerifyMetaSignature` calculava
 * `hash_hmac('sha256', $rawBody, (string) $config->meta_app_secret)` sem checar vazio.
 * Com segredo null/'' quem conhece o algoritmo forja `X-Hub-Signature-256` — o teste
 * abaixo monta exatamente essa assinatura forjada e exige 401.
 *
 * O controle positivo (segredo preenchido + HMAC certo → passa) prova que a recusa
 * vem do segredo vazio, e não de outro defeito do harness.
 */
beforeEach(function () {
    // era-sqlite: schema sintético; no MySQL persistente isso derrubaria a tabela real.
    if (config('database.default') !== 'sqlite') {
        $this->markTestSkipped('era-sqlite: schema sintético — roda na lane sqlite.');
    }
    Schema::dropIfExists('whatsapp_business_configs');
    Schema::create('whatsapp_business_configs', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedInteger('business_id');
        $table->uuid('business_uuid')->unique();
        $table->string('driver', 20)->default('meta_cloud');
        $table->text('meta_access_token')->nullable();
        $table->text('meta_app_secret')->nullable();
        $table->string('meta_webhook_verify_token', 64)->nullable();
        $table->text('zapi_instance_token')->nullable();
        $table->text('zapi_client_token')->nullable();
        $table->timestamps();
    });
});

function metaSecretRequest(string $uuid, string $assinaturaComChave): Request
{
    $body = json_encode(['entry' => []]);
    $request = Request::create("/api/whatsapp/webhook/meta/{$uuid}", 'POST', [], [], [], [], $body);
    $request->headers->set('X-Hub-Signature-256', 'sha256=' . hash_hmac('sha256', $body, $assinaturaComChave));
    $request->setRouteResolver(function () use ($request, $uuid) {
        $route = new \Illuminate\Routing\Route('POST', 'api/whatsapp/webhook/meta/{business_uuid}', []);
        $route->bind($request);
        $route->setParameter('business_uuid', $uuid);

        return $route;
    });

    return $request;
}

function metaSecretConfig(?string $secret): string
{
    $uuid = Str::uuid()->toString();
    WhatsappBusinessConfig::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 98,
        'business_uuid' => $uuid,
        'driver' => 'meta_cloud',
        'meta_app_secret' => $secret,
    ]);

    return $uuid;
}

it('R-WA-META-SECRET-01 — meta_app_secret NULL + assinatura forjada com chave vazia → 401', function () {
    $uuid = metaSecretConfig(null);
    $chegou = false;

    $response = (new VerifyMetaSignature())->handle(metaSecretRequest($uuid, ''), function () use (&$chegou) {
        $chegou = true;

        return response()->json(['next' => true], 200);
    });

    expect($response->getStatusCode())->toBe(401);
    expect(json_decode($response->getContent(), true)['error'])->toBe('app_secret_not_configured');
    expect($chegou)->toBeFalse();
});

it('R-WA-META-SECRET-02 — meta_app_secret string vazia + assinatura forjada → 401', function () {
    $uuid = metaSecretConfig('');

    $response = (new VerifyMetaSignature())->handle(metaSecretRequest($uuid, ''), fn () => response()->json(['next' => true], 200));

    expect($response->getStatusCode())->toBe(401);
    expect(json_decode($response->getContent(), true)['error'])->toBe('app_secret_not_configured');
});

it('R-WA-META-SECRET-03 — controle: segredo preenchido + HMAC certo → passa', function () {
    $uuid = metaSecretConfig('segredo-de-teste-98');
    $chegou = false;

    $response = (new VerifyMetaSignature())->handle(metaSecretRequest($uuid, 'segredo-de-teste-98'), function () use (&$chegou) {
        $chegou = true;

        return response()->json(['next' => true], 200);
    });

    expect($response->getStatusCode())->toBe(200);
    expect($chegou)->toBeTrue();
});
