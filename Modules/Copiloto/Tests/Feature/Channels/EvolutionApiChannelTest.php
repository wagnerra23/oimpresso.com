<?php

declare(strict_types=1);

use Modules\Copiloto\Drivers\Channels\EvolutionApiChannel;

uses(Tests\TestCase::class)->in(__DIR__);

/**
 * ADRs 0074 + 0075 — Driver Evolution Fase 0.
 *
 * Cobre parser de webhook + verificação de assinatura. send() depende de
 * Evolution real rodando (CT 100) — testado em integração quando container
 * estiver up; aqui smoke do contrato.
 */

it('verifySignature aceita header X-Evolution-Secret igual ao configurado', function () {
    $driver = new EvolutionApiChannel(
        baseUrl: 'http://e.local',
        apiKey: 'k',
        instance: 'i',
        webhookSecret: 'sekret',
    );

    $headers = ['x-evolution-secret' => ['sekret']];
    expect($driver->verifySignature($headers, '{}'))->toBeTrue();
});

it('verifySignature rejeita header ausente ou diferente', function () {
    $driver = new EvolutionApiChannel(
        baseUrl: 'http://e.local',
        apiKey: 'k',
        instance: 'i',
        webhookSecret: 'sekret',
    );

    expect($driver->verifySignature([], '{}'))->toBeFalse();
    expect($driver->verifySignature(['x-evolution-secret' => ['outro']], '{}'))->toBeFalse();
});

it('verifySignature rejeita quando webhookSecret está vazio (config faltando)', function () {
    $driver = new EvolutionApiChannel(
        baseUrl: 'http://e.local',
        apiKey: 'k',
        instance: 'i',
        webhookSecret: '',
    );

    expect($driver->verifySignature(['x-evolution-secret' => ['']], '{}'))->toBeFalse();
});

it('parseWebhook ignora eventos que não são messages.upsert', function () {
    $driver = new EvolutionApiChannel('http://e', 'k', 'i', 's');

    expect($driver->parseWebhook(['event' => 'connection.update']))->toBeNull();
    expect($driver->parseWebhook(['event' => 'presence.update']))->toBeNull();
});

it('parseWebhook ignora mensagens de grupo (Fase 0 = só 1:1)', function () {
    $driver = new EvolutionApiChannel('http://e', 'k', 'i', 's');

    $payload = [
        'event' => 'messages.upsert',
        'data'  => [
            'key'     => ['remoteJid' => '5511XXX@g.us', 'id' => 'M1'],
            'message' => ['conversation' => 'oi grupo'],
        ],
    ];

    expect($driver->parseWebhook($payload))->toBeNull();
});

it('parseWebhook normaliza mensagem texto 1:1 em IncomingMessage', function () {
    $driver = new EvolutionApiChannel('http://e', 'k', 'i', 's');

    $payload = [
        'event' => 'messages.upsert',
        'data'  => [
            'key'              => ['remoteJid' => '5511999999999@s.whatsapp.net', 'id' => 'MID'],
            'message'          => ['conversation' => 'quanto vendi?'],
            'messageTimestamp' => 1735689600,
        ],
    ];

    $msg = $driver->parseWebhook($payload);

    expect($msg)->not->toBeNull()
        ->and($msg->channel)->toBe('evolution')
        ->and($msg->wireId)->toBe('+5511999999999')
        ->and($msg->text)->toBe('quanto vendi?')
        ->and($msg->providerMessageId)->toBe('MID')
        ->and($msg->sentAt)->toBeInstanceOf(DateTimeImmutable::class);
});

it('parseWebhook ignora mensagem sem texto (mídia, áudio — Fase 2)', function () {
    $driver = new EvolutionApiChannel('http://e', 'k', 'i', 's');

    $payload = [
        'event' => 'messages.upsert',
        'data'  => [
            'key'     => ['remoteJid' => '5511999999999@s.whatsapp.net', 'id' => 'MID'],
            'message' => ['imageMessage' => ['url' => 'https://...']],
        ],
    ];

    expect($driver->parseWebhook($payload))->toBeNull();
});

it('name retorna identificador estável usado em métricas OTel', function () {
    $driver = new EvolutionApiChannel('http://e', 'k', 'i', 's');
    expect($driver->name())->toBe('evolution');
});
