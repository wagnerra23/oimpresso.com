<?php

declare(strict_types=1);
// @covers-us US-OFICINA-001

use Illuminate\Support\Facades\Http;
use Modules\OficinaAuto\Services\PlacaLookup\HttpPlacaProvider;
use Modules\OficinaAuto\Services\PlacaLookup\PlacaLookupException;

uses(Tests\TestCase::class);

/**
 * Consulta de placa: a falha de conexão com o fornecedor não pode carregar a placa (PII) nem a
 * chave do fornecedor (segredo) na mensagem — o VehicleController@consultaPlaca loga essa
 * mensagem, e o PiiRedactor não conhece placa nem token de URL.
 *
 * A mensagem do Guzzle termina em "for <URL completa>"; no auth_mode=query (o padrão) a URL leva
 * `placa=` e a chave. `Http::failedConnection()` reproduz exatamente esse texto. Sem DB.
 */
function placaProviderQuery(): HttpPlacaProvider
{
    return new HttpPlacaProvider([
        'base_url' => 'https://fornecedor-teste.invalid/consulta',
        'api_key' => 'CHAVE-SECRETA-XYZ',
        'auth_mode' => 'query',
        'auth_key' => 'token',
        'timeout' => 2,
    ]);
}

it('falha de conexão: a mensagem não traz a placa nem a chave do fornecedor', function () {
    Http::fake(['*' => Http::failedConnection()]);

    try {
        placaProviderQuery()->lookup('ABC1D23');
        $this->fail('esperava PlacaLookupException');
    } catch (PlacaLookupException $e) {
        $msg = $e->getMessage();
        // controle positivo: o motivo útil continua lá
        expect($msg)->toContain('cURL error 6');
        expect(str_contains($msg, 'ABC1D23'))->toBeFalse();
        expect(str_contains($msg, 'CHAVE-SECRETA-XYZ'))->toBeFalse();
        expect(str_contains($msg, 'fornecedor-teste.invalid/consulta?'))->toBeFalse();
    }
});

it('falha de conexão: placa e chave somem mesmo se aparecerem fora da URL', function () {
    Http::fake(['*' => Http::failedConnection('timeout lendo ABC1D23 com CHAVE-SECRETA-XYZ')]);

    try {
        placaProviderQuery()->lookup('ABC1D23');
        $this->fail('esperava PlacaLookupException');
    } catch (PlacaLookupException $e) {
        expect($e->getMessage())->toContain('[placa]')->toContain('[segredo]')
            ->and(str_contains($e->getMessage(), 'ABC1D23'))->toBeFalse()
            ->and(str_contains($e->getMessage(), 'CHAVE-SECRETA-XYZ'))->toBeFalse();
    }
});
