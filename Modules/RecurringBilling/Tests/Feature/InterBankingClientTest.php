<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Modules\RecurringBilling\Services\Banking\InterBankingClient;

uses(Tests\TestCase::class);

/**
 * US-RB-045 · InterBankingClient — Banking API v2 do Inter (saldo).
 *
 * Diferente do `InterDriver` (boleto, que usa cURL próprio do
 * `eduardokum/laravel-boleto` e não captura Http::fake), o `InterBankingClient`
 * usa Laravel Http nativo — Http::fake() funciona normal.
 */
function interBankingDummyConfig(): array
{
    return [
        'client_id'           => 'inter-id',
        'client_secret'       => 'inter-secret',
        'certificado_crt_b64' => base64_encode(
            "-----BEGIN CERTIFICATE-----\n".base64_encode(random_bytes(64))."\n-----END CERTIFICATE-----\n"
        ),
        'certificado_key_b64' => base64_encode(
            "-----BEGIN PRIVATE KEY-----\n".base64_encode(random_bytes(64))."\n-----END PRIVATE KEY-----\n"
        ),
        'conta_corrente'      => '12345678',
    ];
}

beforeEach(function () {
    Cache::flush();
});

afterEach(function () {
    foreach (glob(sys_get_temp_dir().'/inter_crt_*.pem') as $f) {
        @unlink($f);
    }
    foreach (glob(sys_get_temp_dir().'/inter_key_*.pem') as $f) {
        @unlink($f);
    }
});

it('busca saldo via OAuth + Banking API v2', function () {
    Http::fake([
        '*/oauth/v2/token'    => Http::response(['access_token' => 'tk_abc', 'expires_in' => 3600]),
        '*/banking/v2/saldo'  => Http::response([
            'disponivel'                => 1234.56,
            'bloqueadoCheque'           => 0,
            'bloqueadoJudicialmente'    => 100.00,
            'bloqueadoAdministrativo'   => 0,
            'limite'                    => 5000,
        ]),
    ]);

    $client = new InterBankingClient(interBankingDummyConfig(), businessId: 1);
    $saldo  = $client->getSaldo();

    expect($saldo['disponivel'])->toBe(1234.56)
        ->and($saldo['bloqueado'])->toBe(100.0)
        ->and($saldo['limite'])->toBe(5000.0);

    Http::assertSent(function (Request $req) {
        return str_contains($req->url(), '/banking/v2/saldo')
            && $req->hasHeader('x-conta-corrente', '12345678')
            && $req->hasHeader('Authorization', 'Bearer tk_abc');
    });
});

it('cacheia OAuth token por 50min — segunda chamada não bate em /token', function () {
    Http::fake([
        '*/oauth/v2/token'   => Http::response(['access_token' => 'tk_abc']),
        '*/banking/v2/saldo' => Http::response(['disponivel' => 100]),
    ]);

    $client = new InterBankingClient(interBankingDummyConfig(), businessId: 1);
    $client->getSaldo();
    $client->getSaldo();

    // Token endpoint chamado 1x (cache hit no segundo); saldo 2x.
    Http::assertSentCount(3);
});

it('cache de token isolado por business_id (multi-tenant Tier 0)', function () {
    Http::fake([
        '*/oauth/v2/token'   => Http::response(['access_token' => 'tk_abc']),
        '*/banking/v2/saldo' => Http::response(['disponivel' => 100]),
    ]);

    $clientA = new InterBankingClient(interBankingDummyConfig(), businessId: 1);
    $clientB = new InterBankingClient(interBankingDummyConfig(), businessId: 2);

    $clientA->getSaldo();
    $clientB->getSaldo();

    // 2 token calls (1 por business, cache key isolada) + 2 saldo calls = 4.
    Http::assertSentCount(4);
});

it('cache de token isolado por scope', function () {
    Http::fake([
        '*/oauth/v2/token'   => Http::response(['access_token' => 'tk_abc']),
        '*/banking/v2/saldo' => Http::response(['disponivel' => 100]),
    ]);

    $client = new InterBankingClient(interBankingDummyConfig(), businessId: 1);

    // Mesmo business, dois scopes diferentes via reflection — cada um pega token novo.
    $reflect = (new ReflectionClass($client))->getMethod('oauthToken');
    $reflect->setAccessible(true);

    $reflect->invoke($client, 'extrato.read');
    $reflect->invoke($client, 'cob.write');
    $reflect->invoke($client, 'extrato.read'); // cache hit

    Http::assertSentCount(2);
});

it('erro 401 em /saldo propaga RequestException sem expor body em log', function () {
    Http::fake([
        '*/oauth/v2/token'   => Http::response(['access_token' => 'tk_abc']),
        '*/banking/v2/saldo' => Http::response(['error' => 'cert inválido', 'detalhe' => 'PII'], 401),
    ]);

    $client = new InterBankingClient(interBankingDummyConfig(), businessId: 1);

    expect(fn () => $client->getSaldo())
        ->toThrow(\Illuminate\Http\Client\RequestException::class);
});

it('erro 401 em /token propaga RequestException', function () {
    Http::fake([
        '*/oauth/v2/token' => Http::response(['error' => 'invalid_client'], 401),
    ]);

    $client = new InterBankingClient(interBankingDummyConfig(), businessId: 1);

    expect(fn () => $client->getSaldo())
        ->toThrow(\Illuminate\Http\Client\RequestException::class);
});

it('grava cert em /tmp com permissão 0600 (POSIX) e idempotente por md5', function () {
    Http::fake([
        '*/oauth/v2/token'   => Http::response(['access_token' => 'tk_abc']),
        '*/banking/v2/saldo' => Http::response(['disponivel' => 1]),
    ]);

    $client = new InterBankingClient(interBankingDummyConfig(), businessId: 1);
    $client->getSaldo();

    $crts = glob(sys_get_temp_dir().'/inter_crt_*.pem');
    $keys = glob(sys_get_temp_dir().'/inter_key_*.pem');

    expect($crts)->toHaveCount(1)
        ->and($keys)->toHaveCount(1);

    if (PHP_OS_FAMILY !== 'Windows') {
        expect(fileperms($crts[0]) & 0777)->toBe(0600)
            ->and(fileperms($keys[0]) & 0777)->toBe(0600);
    }
});
