<?php

use App\Account;
use Modules\Financeiro\Contracts\BoletoStrategy;
use Modules\Financeiro\Models\BoletoRemessa;
use Modules\Financeiro\Models\ContaBancaria;
use Modules\Financeiro\Models\Titulo;
use Modules\Financeiro\Services\TituloService;

/**
 * Test offline-safe (sem DB) — usa Mockery na BoletoStrategy.
 * Cobre: delega correto pra strategy + variante explicita com conta.
 *
 * Testes que envolvem resolverConta() com query real ficam pra
 * TituloServiceIntegrationTest quando setup de DB no CI for ligado.
 */

it('delega emitirBoletoComConta direto pra strategy', function () {
    $titulo = new Titulo(['business_id' => 1, 'numero' => '1', 'tipo' => 'receber',
        'status' => 'aberto', 'valor_total' => 100, 'valor_aberto' => 100, 'moeda' => 'BRL',
        'emissao' => '2026-04-25', 'vencimento' => '2026-05-25', 'origem' => 'manual']);
    $titulo->id = 1;

    $account = new Account(['name' => 'X', 'account_number' => '12345']);
    $conta = new ContaBancaria(['business_id' => 1, 'account_id' => 1, 'banco_codigo' => '756',
        'agencia' => '1234', 'carteira' => '1', 'beneficiario_documento' => '00.000.000/0001-00',
        'beneficiario_razao_social' => 'X']);
    $conta->id = 1;
    $conta->setRelation('account', $account);

    $remessaEsperada = new BoletoRemessa(['nosso_numero' => '0001']);

    $strategy = Mockery::mock(BoletoStrategy::class);
    $strategy->shouldReceive('emitir')
        ->once()
        ->with($titulo, $conta)
        ->andReturn($remessaEsperada);

    $service = new TituloService($strategy);
    $result = $service->emitirBoletoComConta($titulo, $conta);

    expect($result)->toBe($remessaEsperada);
});

it('delega cancelarBoleto pra strategy com motivo', function () {
    $remessa = new BoletoRemessa(['id' => 1]);
    $remessa->id = 1;

    $strategy = Mockery::mock(BoletoStrategy::class);
    $strategy->shouldReceive('cancelar')
        ->once()
        ->with($remessa, 'duplicidade');

    $service = new TituloService($strategy);
    $service->cancelarBoleto($remessa, 'duplicidade');
});

it('delega statusBoleto pra strategy e retorna o status atual', function () {
    $remessa = new BoletoRemessa(['id' => 1]);
    $remessa->id = 1;

    $strategy = Mockery::mock(BoletoStrategy::class);
    $strategy->shouldReceive('statusAtual')
        ->once()
        ->with($remessa)
        ->andReturn(BoletoRemessa::STATUS_GERADO_MOCK);

    $service = new TituloService($strategy);

    expect($service->statusBoleto($remessa))->toBe(BoletoRemessa::STATUS_GERADO_MOCK);
});
