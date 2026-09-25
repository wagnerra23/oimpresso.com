<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Modules\Whatsapp\Entities\WhatsappBusinessConfig;
use Modules\Whatsapp\Services\Drivers\WhatsmeowDriver;

uses(Tests\TestCase::class);

/**
 * WhatsmeowSessionStatusParseTest — contrato do `GET /session/status` do WuzAPI.
 *
 * Incidente 2026-09-25: o Suporte pareou pelo QR e o modal "Reconectar canal"
 * não fechou. O daemon real responde envelope `data` + chaves minúsculas
 * (payload abaixo, copiado do daemon CT 100 no build sha256:7f2aee54). O status
 * do controller e o `ping()` liam `Connected`/`LoggedIn` na raiz → sempre false →
 * canal pareado lido como "desconectado". Os mocks antigos usavam o formato
 * inventado, por isso passavam.
 */

/** Resposta real do daemon, trecho relevante (2026-09-25). */
function wuzapiStatusReal(bool $connected, bool $loggedIn): array
{
    return [
        'code' => 200,
        'data' => [
            'connected' => $connected,
            'loggedIn' => $loggedIn,
            'jid' => '554899999999:65@s.whatsapp.net',
            'events' => 'Message,ReadReceipt,Connected,Disconnected,LoggedOut',
        ],
        'success' => true,
    ];
}

beforeEach(function () {
    config([
        'whatsapp.whatsmeow.daemon_url' => 'https://whatsapp-whatsmeow.oimpresso.com',
        'whatsapp.whatsmeow.request_timeout' => 5,
    ]);
});

it('parseSessionStatus lê o payload REAL do daemon (envelope data + minúsculas)', function () {
    $s = WhatsmeowDriver::parseSessionStatus(wuzapiStatusReal(true, true));

    expect($s['connected'])->toBeTrue();
    expect($s['loggedIn'])->toBeTrue();
    expect($s['jid'])->toBe('554899999999:65@s.whatsapp.net');
});

it('parseSessionStatus aceita a grafia antiga (maiúsculas, com e sem envelope)', function () {
    $semEnvelope = WhatsmeowDriver::parseSessionStatus(['Connected' => true, 'LoggedIn' => true, 'Jid' => 'x@s.whatsapp.net']);
    $comEnvelope = WhatsmeowDriver::parseSessionStatus(['data' => ['Connected' => true, 'LoggedIn' => false]]);

    expect($semEnvelope['connected'])->toBeTrue();
    expect($semEnvelope['loggedIn'])->toBeTrue();
    expect($semEnvelope['jid'])->toBe('x@s.whatsapp.net');
    expect($comEnvelope['connected'])->toBeTrue();
    expect($comEnvelope['loggedIn'])->toBeFalse();
});

it('parseSessionStatus devolve tudo falso para corpo vazio ou inválido', function () {
    foreach ([null, [], 'texto', ['data' => null]] as $body) {
        $s = WhatsmeowDriver::parseSessionStatus($body);
        expect($s['connected'])->toBeFalse();
        expect($s['loggedIn'])->toBeFalse();
        expect($s['jid'])->toBeNull();
    }
});

it('ping() com o payload REAL pareado devolve healthy', function () {
    Http::fake([
        '*/session/status' => Http::response(wuzapiStatusReal(true, true), 200),
    ]);

    $config = new WhatsappBusinessConfig(['business_id' => 98, 'driver' => 'whatsmeow']);
    $config->setRawAttributes(array_merge($config->getAttributes(), [
        'whatsmeow_user_token' => 'user_token_fake_32hex',
    ]));

    $health = app(WhatsmeowDriver::class)->ping($config);

    expect($health->sessionState)->toBe('connected');
});

it('ping() com o payload REAL aguardando QR devolve qr_required', function () {
    Http::fake([
        '*/session/status' => Http::response(wuzapiStatusReal(true, false), 200),
    ]);

    $config = new WhatsappBusinessConfig(['business_id' => 98, 'driver' => 'whatsmeow']);
    $config->setRawAttributes(array_merge($config->getAttributes(), [
        'whatsmeow_user_token' => 'user_token_fake_32hex',
    ]));

    $health = app(WhatsmeowDriver::class)->ping($config);

    expect($health->sessionState)->toBe('qr_required');
});
