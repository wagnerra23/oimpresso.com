<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Models\NfseEmissao;
use Modules\NfeBrasil\Services\NfseDrivers\AbrasfV204CancelDriver;

uses(Tests\TestCase::class);

/**
 * US-NFSE-CANCEL-002 — AbrasfV204CancelDriver (stub).
 *
 * Driver real (SOAP CancelarNfseEnvio + assinatura A1) pendente — esta US
 * apenas garante que o stub:
 *   1. Identifica-se como 'ABRASF_V2.04'
 *   2. Lança RuntimeException informativa em vez de silenciar
 */

beforeEach(function () {
    if (! Schema::hasTable('nfse_emissoes')) {
        test()->markTestSkipped('Tabela nfse_emissoes não existe.');
    }
});

it('1. getDriverKey retorna ABRASF_V2.04', function () {
    $driver = new AbrasfV204CancelDriver();

    expect($driver->getDriverKey())->toBe('ABRASF_V2.04');
    expect($driver->supportedMunicipios())->toBe([]); // stub — vazio
})->group('nfse', 'nfse-cancel', 'nfse-cancel-abrasf');

it('2. cancelar lança RuntimeException com instrução pro dev (stub)', function () {
    try {
        $business = \App\Business::first();
    } catch (\Throwable $e) {
        test()->markTestSkipped('Tabela business indisponível: ' . $e->getMessage());
    }

    if (! $business) {
        test()->markTestSkipped('Sem business no banco.');
    }

    $nfse = NfseEmissao::withoutGlobalScopes()->create([
        'business_id'           => (int) $business->id,
        'transaction_id'        => null,
        'numero_rps'            => random_int(900000, 999999),
        'item_lc116'            => '17.06',
        'value_servico'         => 100.00,
        'value_iss'             => 5.00,
        'aliquota_iss'          => 0.0500,
        'tomador_doc'           => '12345678000199',
        'tomador_nome'          => 'TESTE NFSE ABRASF STUB',
        'status'                => NfseEmissao::STATUS_AUTHORIZED,
        'municipio_codigo_ibge' => '4218400',
    ]);

    try {
        $driver = new AbrasfV204CancelDriver();

        expect(fn () => $driver->cancelar($nfse, 'Cancelamento por engano operacional — teste'))
            ->toThrow(\RuntimeException::class, 'ABRASF v2.04 ainda não implementa');
    } finally {
        $nfse->delete();
    }
})->group('nfse', 'nfse-cancel', 'nfse-cancel-abrasf');
