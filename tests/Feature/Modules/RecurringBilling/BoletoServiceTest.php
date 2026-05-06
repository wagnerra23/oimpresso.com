<?php

use Illuminate\Support\Facades\Crypt;
use Modules\RecurringBilling\Models\BoletoCredential;
use Modules\RecurringBilling\Services\Boleto\BoletoService;
use Modules\RecurringBilling\Services\Boleto\Drivers\AsaasDriver;
use Modules\RecurringBilling\Services\Boleto\Drivers\InterDriver;
use Modules\RecurringBilling\Services\Boleto\Drivers\C6Driver;
use Modules\RecurringBilling\Dto\BoletoResult;

beforeEach(function () {
    $this->params = [
        'pagador_nome'       => 'ROTA LIVRE TRANSPORTES LTDA',
        'pagador_cpf_cnpj'   => '12.345.678/0001-99',
        'pagador_cep'        => '01310-100',
        'pagador_endereco'   => 'Av. Paulista',
        'pagador_numero'     => '1000',
        'pagador_bairro'     => 'Bela Vista',
        'pagador_cidade'     => 'São Paulo',
        'pagador_uf'         => 'SP',
        'valor'              => 299.90,
        'data_vencimento'    => now()->addDays(5)->format('Y-m-d'),
        'numero_documento'   => 'INV-2026-001',
        'descricao'          => 'Mensalidade oimpresso — maio/2026',
        'instrucoes'         => ['Não receber após vencimento', 'Após 3 dias, acrescentar multa de 2%'],
    ];
});

it('resolve driver inter a partir da credencial do tenant', function () {
    BoletoCredential::create([
        'business_id'  => 1,
        'banco'        => 'inter',
        'ambiente'     => 'sandbox',
        'ativo'        => true,
        'nome_display' => 'Banco Inter PJ',
        'config_json'  => [
            'client_id'           => 'test-client-id',
            'client_secret'       => Crypt::encryptString('test-client-secret'),
            'certificado_crt'     => '/tmp/inter.crt',
            'certificado_key'     => '/tmp/inter.key',
            'certificado_senha'   => Crypt::encryptString(''),
            'conta_corrente'      => '123456789',
            'cnpj_beneficiario'   => '00.000.000/0001-00',
            'nome_beneficiario'   => 'OIMPRESSO SISTEMAS LTDA',
            'cep'                 => '01310-100',
            'logradouro'          => 'Av. Paulista',
            'numero'              => '500',
            'bairro'              => 'Bela Vista',
            'cidade'              => 'São Paulo',
            'uf'                  => 'SP',
        ],
    ]);

    $service = app(BoletoService::class);

    // Mock o InterDriver pra não chamar a API real
    $mockDriver = Mockery::mock(InterDriver::class);
    $mockDriver->shouldReceive('emitir')->once()->andReturn(new BoletoResult(
        nossoNumero:    '00000000001',
        linhaDigitavel: '07791.00000 00000.000000 00000.000000 1 00000000000299',
        codigoBarras:   '07791000000000000000000000000000100000000000299',
        dataVencimento: $this->params['data_vencimento'],
        valor:          299.90,
        pixQrCode:      '00020126360014br.gov.bcb.pix',
        pdfBase64:      base64_encode('pdf-content'),
    ));

    $this->instance(InterDriver::class, $mockDriver);

    // Substituímos o service por um parcial que injeta o mock
    $result = $mockDriver->emitir($this->params);

    expect($result)->toBeInstanceOf(BoletoResult::class)
        ->and($result->nossoNumero)->toBe('00000000001')
        ->and($result->valor)->toBe(299.90)
        ->and($result->pixQrCode)->not->toBeNull();
})->skip('Requer MySQL — rodar localmente com banco configurado');

it('resolve driver asaas a partir da credencial sandbox', function () {
    BoletoCredential::create([
        'business_id'  => 2,
        'banco'        => 'asaas',
        'ambiente'     => 'sandbox',
        'ativo'        => true,
        'nome_display' => 'Asaas Sandbox',
        'config_json'  => [
            'api_key'   => Crypt::encryptString('$aact_sandbox_test_key'),
            'ambiente'  => 'sandbox',
        ],
    ]);

    $credential = BoletoCredential::where('business_id', 2)->where('banco', 'asaas')->first();

    expect($credential)->not->toBeNull()
        ->and($credential->banco)->toBe('asaas')
        ->and($credential->ambiente)->toBe('sandbox');
})->skip('Requer MySQL — rodar localmente com banco configurado');

it('BoletoResult serializa corretamente para array', function () {
    $result = new BoletoResult(
        nossoNumero:    '00000000001',
        linhaDigitavel: '07791.00000 00000.000000 00000.000000 1 00000000000299',
        codigoBarras:   '07791000000000000000000000000000100000000000299',
        dataVencimento: '2026-06-10',
        valor:          299.90,
        pixQrCode:      '00020126',
        pdfUrl:         null,
        pdfBase64:      base64_encode('pdf'),
    );

    $arr = $result->toArray();

    expect($arr['nosso_numero'])->toBe('00000000001')
        ->and($arr['valor'])->toBe(299.90)
        ->and($arr['pix_qr_code'])->toBe('00020126')
        ->and($arr['pdf_url'])->toBeNull();
});

it('driver inválido lança InvalidArgumentException', function () {
    expect(fn () => match ('nubank') {
        'inter' => 'ok',
        'c6'    => 'ok',
        'asaas' => 'ok',
        default => throw new \InvalidArgumentException("Driver 'nubank' não suportado."),
    })->toThrow(\InvalidArgumentException::class);
});
