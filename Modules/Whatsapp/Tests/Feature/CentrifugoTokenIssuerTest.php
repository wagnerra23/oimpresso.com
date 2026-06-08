<?php

declare(strict_types=1);

use Modules\Whatsapp\Services\Centrifugo\CentrifugoTokenIssuer;

uses(Tests\TestCase::class);

/**
 * ADR 0058 · CentrifugoTokenIssuer — JWT HS256 puro pra subscribe.
 *
 * Cobre:
 * - Token gerado tem 3 partes (header.payload.signature) base64url
 * - issue() retorna null se token_hmac_secret não configurado (graceful)
 * - verify() retorna payload válido pra token bem-emitido
 * - verify() retorna null pra token expirado
 * - verify() retorna null pra signature alterada (tamper)
 * - verify() retorna null pra secret diferente (HMAC verify falha)
 */

beforeEach(function () {
    config()->set('whatsapp.centrifugo.token_hmac_secret', 'test-secret-32-bytes-min-required-here');
});

it('emite JWT HS256 com header.payload.signature base64url', function () {
    $token = (new CentrifugoTokenIssuer())->issue(42, ['whatsapp:business:4']);

    expect($token)->toBeString();
    $parts = explode('.', $token);
    expect($parts)->toHaveCount(3);

    // Header decodável
    $header = json_decode(base64_decode(strtr($parts[0], '-_', '+/')), true);
    expect($header)->toMatchArray(['typ' => 'JWT', 'alg' => 'HS256']);

    // Payload contém sub + channels + exp
    $payload = json_decode(base64_decode(strtr(str_pad($parts[1], strlen($parts[1]) + ((4 - strlen($parts[1]) % 4) % 4), '='), '-_', '+/')), true);
    expect($payload['sub'])->toBe('42');
    expect($payload['channels'])->toBe(['whatsapp:business:4']);
    expect($payload['exp'])->toBeGreaterThan(time());
});

it('retorna null quando token_hmac_secret não configurado (graceful)', function () {
    config()->set('whatsapp.centrifugo.token_hmac_secret', '');
    expect((new CentrifugoTokenIssuer())->issue(42, ['ch']))->toBeNull();

    config()->set('whatsapp.centrifugo.token_hmac_secret', null);
    expect((new CentrifugoTokenIssuer())->issue(42, ['ch']))->toBeNull();
});

it('verify() recupera payload pra token bem-emitido', function () {
    $issuer = new CentrifugoTokenIssuer();
    $token = $issuer->issue(99, ['whatsapp:business:7'], 60);

    $payload = $issuer->verify($token);
    expect($payload)->not->toBeNull();
    expect($payload['sub'])->toBe('99');
    expect($payload['channels'])->toBe(['whatsapp:business:7']);
});

it('verify() retorna null pra token expirado', function () {
    $issuer = new CentrifugoTokenIssuer();
    // exp negativo = token já expirou
    $token = $issuer->issue(1, ['ch'], -10);

    expect($issuer->verify($token))->toBeNull();
});

it('verify() retorna null pra signature alterada (tamper)', function () {
    $issuer = new CentrifugoTokenIssuer();
    $token = $issuer->issue(1, ['ch']);

    // Tampera última char da signature
    $tampered = substr($token, 0, -1) . (substr($token, -1) === 'A' ? 'B' : 'A');

    expect($issuer->verify($tampered))->toBeNull();
});

it('verify() retorna null se secret mudou (HMAC fail)', function () {
    $issuer = new CentrifugoTokenIssuer();
    $token = $issuer->issue(1, ['ch']);

    // Roda verify com secret diferente
    config()->set('whatsapp.centrifugo.token_hmac_secret', 'different-secret-other-than-issued');

    expect($issuer->verify($token))->toBeNull();
});
