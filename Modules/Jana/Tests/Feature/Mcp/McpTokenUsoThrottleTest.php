<?php

declare(strict_types=1);

namespace Modules\Jana\Tests\Feature\Mcp;

use Modules\Jana\Entities\Mcp\McpToken;

uses(\Tests\TestCase::class);

/**
 * Incidente 2026-08-12 — `registrarUso()` gravava um UPDATE por requisição.
 *
 * O MCP server roda no CT 100 contra o MySQL do Hostinger, onde cada roundtrip
 * mede ~360ms, e o handshake do cliente faz 4 chamadas seguidas — quatro
 * escritas para carimbar o mesmo minuto. `last_used_at` é telemetria de "quando
 * foi usado por último"; granularidade de minuto responde isso igual.
 *
 * Estes testes travam as três propriedades: SEGURA dentro da janela, GRAVA
 * quando ela passa, e `0` DESLIGA. A segunda é a que impede o throttle de virar
 * "nunca mais grava" — um bug que passaria despercebido, porque o sintoma dele
 * é um campo que simplesmente para de atualizar.
 *
 * @see Modules/Jana/Entities/Mcp/McpToken.php@registrarUso
 */

/** Tenant fictício canônico de teste (ADR 0358). Nunca biz=4 (cliente real). */
const BIZ_THROTTLE = 98;

function criarTokenDeTeste(?\DateTimeInterface $ultimoUso): McpToken
{
    return McpToken::create([
        'name'         => 'probe-throttle-' . uniqid(),
        'sha256_token' => hash('sha256', 'probe-' . uniqid()),
        'user_id'      => 1,
        'business_id'  => BIZ_THROTTLE,
        'last_used_at' => $ultimoUso,
        'last_used_ip' => '10.0.0.1',
    ]);
}

afterEach(function () {
    McpToken::withTrashed()->where('name', 'like', 'probe-throttle-%')->forceDelete();
});

it('não regrava o carimbo dentro da janela', function () {
    $token = criarTokenDeTeste(now()->subSeconds(5));

    $token->registrarUso(ip: '10.0.0.99', userAgent: 'probe', throttleSegundos: 60);

    // Era daqui que saíam 4 UPDATEs por handshake, todos no mesmo minuto.
    expect($token->fresh()->last_used_ip)->toBe('10.0.0.1');
});

it('grava quando a janela já passou', function () {
    $token = criarTokenDeTeste(now()->subMinutes(5));

    $token->registrarUso(ip: '10.0.0.99', userAgent: 'probe', throttleSegundos: 60);

    // Sem este caso, um throttle quebrado viraria "nunca mais grava" em silêncio.
    expect($token->fresh()->last_used_ip)->toBe('10.0.0.99');
});

it('grava sempre quando o throttle está zerado', function () {
    $token = criarTokenDeTeste(now());

    $token->registrarUso(ip: '10.0.0.99', userAgent: 'probe', throttleSegundos: 0);

    expect($token->fresh()->last_used_ip)->toBe('10.0.0.99');
});

it('grava na primeira vez, quando nunca houve uso', function () {
    $token = criarTokenDeTeste(null);

    $token->registrarUso(ip: '10.0.0.99', userAgent: 'probe', throttleSegundos: 60);

    // last_used_at null não pode ser lido como "dentro da janela".
    expect($token->fresh()->last_used_ip)->toBe('10.0.0.99')
        ->and($token->fresh()->last_used_at)->not->toBeNull();
});
