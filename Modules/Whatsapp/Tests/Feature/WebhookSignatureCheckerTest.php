<?php

declare(strict_types=1);

use Modules\Whatsapp\Services\Webhook\WebhookSignatureChecker;

uses(Tests\TestCase::class);

/**
 * Wave 18 saturation D2/D4 — WebhookSignatureChecker (extraído como Service
 * canon pra uniformizar checks dispersos em Controllers).
 *
 * Cobre Meta Cloud (formato `sha256=<hex>`), Baileys (hex puro) e Z-API.
 * Foca em edge cases que travavam validações em prod (header faltando,
 * prefixo errado, payload tampered, secret errado).
 *
 * @see Modules/Whatsapp/Services/Webhook/WebhookSignatureChecker.php
 */

beforeEach(function () {
    config()->set('otel.enabled', false);
    $this->svc = new WebhookSignatureChecker();
    $this->secret = 'super-secret-test-key';
    $this->body = '{"event":"message","wam_id":"wamid.123"}';
});

it('cenario 1: Meta valida assinatura correta com prefixo sha256=', function () {
    $sig = 'sha256='.hash_hmac('sha256', $this->body, $this->secret);
    expect($this->svc->verifyMeta($this->body, $sig, $this->secret))->toBeTrue();
});

it('cenario 2: Meta rejeita header sem prefixo sha256=', function () {
    $hexOnly = hash_hmac('sha256', $this->body, $this->secret);
    expect($this->svc->verifyMeta($this->body, $hexOnly, $this->secret))->toBeFalse();
});

it('cenario 3: Meta rejeita header null/vazio', function () {
    expect($this->svc->verifyMeta($this->body, null, $this->secret))->toBeFalse()
        ->and($this->svc->verifyMeta($this->body, '', $this->secret))->toBeFalse();
});

it('cenario 4: Meta rejeita payload tampered (body diff)', function () {
    $sig = 'sha256='.hash_hmac('sha256', $this->body, $this->secret);
    $tampered = $this->body.'EXTRA';
    expect($this->svc->verifyMeta($tampered, $sig, $this->secret))->toBeFalse();
});

it('cenario 5: Meta rejeita secret errado (key rotation cenário)', function () {
    $sig = 'sha256='.hash_hmac('sha256', $this->body, $this->secret);
    expect($this->svc->verifyMeta($this->body, $sig, 'other-secret'))->toBeFalse();
});

it('cenario 6: Meta rejeita hex inválido após prefixo (chars não-hex)', function () {
    expect($this->svc->verifyMeta($this->body, 'sha256=NOT_HEX_AT_ALL!', $this->secret))->toBeFalse();
});

it('cenario 7: Baileys valida hex puro (sem prefixo)', function () {
    $sig = hash_hmac('sha256', $this->body, $this->secret);
    expect($this->svc->verifyBaileys($this->body, $sig, $this->secret))->toBeTrue();
});

it('cenario 8: Baileys rejeita prefixo sha256= (formato Meta não aplicável)', function () {
    $sig = 'sha256='.hash_hmac('sha256', $this->body, $this->secret);
    expect($this->svc->verifyBaileys($this->body, $sig, $this->secret))->toBeFalse();
});

it('cenario 9: Z-API valida hex puro idêntico ao Baileys', function () {
    $sig = hash_hmac('sha256', $this->body, $this->secret);
    expect($this->svc->verifyZapi($this->body, $sig, $this->secret))->toBeTrue();
});

it('cenario 10: dispatch verify() por driver name canonico', function () {
    $sigMeta = 'sha256='.hash_hmac('sha256', $this->body, $this->secret);
    $sigHex  = hash_hmac('sha256', $this->body, $this->secret);

    expect($this->svc->verify('meta_cloud', $this->body, $sigMeta, $this->secret))->toBeTrue()
        ->and($this->svc->verify('baileys',   $this->body, $sigHex,  $this->secret))->toBeTrue()
        ->and($this->svc->verify('zapi',      $this->body, $sigHex,  $this->secret))->toBeTrue()
        ->and($this->svc->verify('driver-desconhecido', $this->body, $sigHex, $this->secret))->toBeFalse();
});

it('cenario 11: source-grep confirma OtelHelper canon + 3 spans whatsapp.webhook.signature.*', function () {
    $file = (new ReflectionClass(WebhookSignatureChecker::class))->getFileName();
    $src  = file_get_contents($file);

    expect($src)->toContain('use App\Util\OtelHelper;');

    $matches = preg_match_all("/'whatsapp\\.webhook\\.signature\\.[a-z_]+'/", $src);
    expect($matches)->toBeGreaterThanOrEqual(3, "Esperava 3+ spans canon (meta/baileys/zapi)");
});

it('cenario 12: hash_equals é constant-time (verify NÃO short-circuit por length diff)', function () {
    $correctHex = hash_hmac('sha256', $this->body, $this->secret);
    $shortHex   = substr($correctHex, 0, 10);

    // Não deve throw nem warn — só retorna false
    expect($this->svc->verifyBaileys($this->body, $shortHex, $this->secret))->toBeFalse();
});
