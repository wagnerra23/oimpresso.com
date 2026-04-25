<?php

use App\Account;
use Modules\Financeiro\Models\ContaBancaria;
use Modules\Financeiro\Models\Titulo;
use Modules\Financeiro\Strategies\CnabDirectStrategy;

/**
 * Contract test do CnabDirectStrategy (ADR TECH-0003).
 *
 * Itera 5 bancos prioritarios cobrindo a interface ConcreteStrategy:
 *  - Bancoob (Sicoob) — cliente real ROTA LIVRE
 *  - Banco do Brasil — referencia popular
 *  - Inter — Wagner tem credenciais
 *  - C6 — Wagner tem credenciais
 *  - Itau — referencia popular
 *
 * Os outros 13 bancos da lib (Ailos, Banrisul, BNB, BTG, Bradesco, Caixa,
 * Cresol, Delbank, Fibra, Hsbc, Ourinvest, Pine, Rendimento, Santander,
 * Sicredi, Unicred) ficam pra Onda 2 — cada um tem requisitos especificos
 * (codigoCliente, modalidadeCarteira, etc.) que precisam fixtures proprias.
 *
 * Test foca em geracao OFFLINE (sem DB) via metodo gerarBoleto(), que
 * retorna a instancia AbstractBoleto da lib eduardokum. O fluxo completo
 * com persistencia em fin_boleto_remessas (metodo emitir()) tem teste
 * separado em CnabDirectStrategyEmitirTest (DB-backed).
 */

dataset('bancos_mvp', [
    'Bancoob (Sicoob) ROTA LIVRE' => [
        'banco_codigo' => '756',
        'carteira' => '1',
        'agencia' => '1234',
        'agencia_dv' => null,
        'conta_dv' => '0',
        'numero_conta' => '12345',
        'convenio' => '123456',
        'codigo_cedente' => null,
        'metadata_extra' => [],
    ],
    'Banco do Brasil' => [
        'banco_codigo' => '001',
        'carteira' => '18',
        'agencia' => '1234',
        'agencia_dv' => null,
        'conta_dv' => '6',
        'numero_conta' => '12345',
        'convenio' => '1234567',
        'codigo_cedente' => null,
        'metadata_extra' => [],
    ],
    'Inter' => [
        'banco_codigo' => '077',
        'carteira' => '112',
        'agencia' => '0001',
        'agencia_dv' => null,
        'conta_dv' => null,
        'numero_conta' => '1234567',
        'convenio' => null,
        'codigo_cedente' => null,
        'metadata_extra' => ['operacao' => '1', 'nossoNumero' => '00000000001'],
    ],
    'C6' => [
        'banco_codigo' => '336',
        'carteira' => '60',
        'agencia' => '0001',
        'agencia_dv' => null,
        'conta_dv' => null,
        'numero_conta' => '1234567',
        'convenio' => null,
        'codigo_cedente' => '12345',
        'metadata_extra' => [],
    ],
    'Itau' => [
        'banco_codigo' => '341',
        'carteira' => '109',
        'agencia' => '1234',
        'agencia_dv' => null,
        'conta_dv' => '6',
        'numero_conta' => '12345',
        'convenio' => null,
        'codigo_cedente' => null,
        'metadata_extra' => [],
    ],
]);

it('gera boleto valido por banco', function (
    string $banco_codigo,
    string $carteira,
    string $agencia,
    ?string $agencia_dv,
    ?string $conta_dv,
    string $numero_conta,
    ?string $convenio,
    ?string $codigo_cedente,
    array $metadata_extra,
) {
    $account = new Account([
        'name' => 'Conta Teste',
        'account_number' => $numero_conta,
    ]);

    $conta = new ContaBancaria([
        'business_id' => 1,
        'account_id' => 1,
        'banco_codigo' => $banco_codigo,
        'agencia' => $agencia,
        'agencia_dv' => $agencia_dv,
        'conta_dv' => $conta_dv,
        'carteira' => $carteira,
        'convenio' => $convenio,
        'codigo_cedente' => $codigo_cedente,
        'beneficiario_documento' => '12.345.678/0001-99',
        'beneficiario_razao_social' => 'TESTE BENEFICIARIO LTDA',
        'beneficiario_logradouro' => 'Rua Teste, 100',
        'beneficiario_bairro' => 'Centro',
        'beneficiario_cidade' => 'Sao Paulo',
        'beneficiario_uf' => 'SP',
        'beneficiario_cep' => '01000-000',
        'ativo_para_boleto' => true,
        'metadata' => $metadata_extra,
    ]);
    $conta->id = 1;
    $conta->setRelation('account', $account);

    $titulo = new Titulo([
        'business_id' => 1,
        'numero' => '1',
        'tipo' => 'receber',
        'status' => 'aberto',
        'cliente_descricao' => 'CLIENTE TESTE LTDA',
        'valor_total' => 100.00,
        'valor_aberto' => 100.00,
        'moeda' => 'BRL',
        'emissao' => now()->toDateString(),
        'vencimento' => now()->addDays(30)->toDateString(),
        'origem' => 'manual',
        'metadata' => ['cliente_documento' => '999.999.999-99'],
    ]);
    $titulo->id = 1;

    $strategy = new CnabDirectStrategy();
    $boleto = $strategy->gerarBoleto($titulo, $conta);

    // Linha digitavel pode vir formatada com pontos e espaços ou crua
    $linhaSoDigitos = preg_replace('/\D/', '', $boleto->getLinhaDigitavel());
    expect(strlen($linhaSoDigitos))->toBeIn([47, 48], "Linha digitavel deve ter 47 ou 48 digitos para banco {$banco_codigo}");

    // Codigo de barras sempre 44 digitos
    expect($boleto->getCodigoBarras())->toMatch('/^\d{44}$/', "Codigo de barras deve ter 44 digitos para banco {$banco_codigo}");

    // Valor preservado
    expect((float) $boleto->getValor())->toBe(100.00);
})->with('bancos_mvp');

it('lanca DomainException pra banco nao mapeado', function () {
    $account = new Account(['name' => 'X', 'account_number' => '1']);
    $conta = new ContaBancaria([
        'business_id' => 1, 'account_id' => 1,
        'banco_codigo' => '999', // inexistente
        'agencia' => '1', 'carteira' => '1',
        'beneficiario_documento' => '00.000.000/0001-00',
        'beneficiario_razao_social' => 'X',
    ]);
    $conta->id = 1;
    $conta->setRelation('account', $account);

    $titulo = new Titulo([
        'business_id' => 1, 'numero' => '1', 'tipo' => 'receber', 'status' => 'aberto',
        'valor_total' => 1, 'valor_aberto' => 1, 'moeda' => 'BRL',
        'emissao' => now()->toDateString(), 'vencimento' => now()->addDays(1)->toDateString(),
        'origem' => 'manual',
    ]);
    $titulo->id = 1;

    expect(fn () => (new CnabDirectStrategy())->gerarBoleto($titulo, $conta))
        ->toThrow(\DomainException::class, 'Banco 999 nao suportado');
});
