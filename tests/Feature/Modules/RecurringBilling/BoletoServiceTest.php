<?php

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Modules\RecurringBilling\Dto\BoletoResult;
use Modules\RecurringBilling\Models\BoletoCredential;
use Modules\RecurringBilling\Services\Boleto\BoletoService;

/*
 * BoletoService (raiz) — emissão pelo serviço real, ponta a ponta até o HTTP do gateway.
 *
 * A resolução de driver por credencial (inter/c6/asaas, inativa, ausente) é coberta pelo
 * teste homônimo do módulo (Modules/RecurringBilling/Tests/Feature/BoletoServiceTest.php,
 * sqlite). Este arquivo cobre o que aquele não cobre: o valor e a chave que chegam ao
 * gateway e o isolamento entre empresas, com o schema MySQL real (enum de banco e índice
 * único por empresa+banco).
 *
 * Até 2026-11-19 este arquivo não rodava em lane nenhuma; dois dos testes tinham `->skip()`
 * incondicional e os outros dois testavam o próprio mock e um `match` escrito no teste,
 * sem tocar o BoletoService. Roda na lane RecurringBilling · Pest (MySQL).
 *
 * Tenants 98 x 99 (ADR 0358). Nunca biz=4. Pagador e CNPJ fictícios.
 */

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('Schema real do RecurringBilling (MySQL): enum de banco e índice único por empresa.');
    }

    $this->b98 = (int) $this->seededTenant()->id;
    $this->b99 = (int) $this->seededSupportClientTenant()->id;

    // Sem credencial de boleto pré-existente nos tenants de teste (rollback no fim).
    DB::table('rb_boleto_credentials')->whereIn('business_id', [$this->b98, $this->b99])->delete();

    $this->credencialAsaas = fn (int $biz, string $chave, bool $ativo = true) => BoletoCredential::create([
            'business_id'  => $biz,
            'banco'        => 'asaas',
            'ambiente'     => 'sandbox',
            'ativo'        => $ativo,
            'nome_display' => 'Asaas Sandbox',
            'config_json'  => [
                'api_key'  => Crypt::encryptString($chave),
                'ambiente' => 'sandbox',
            ],
        ]);

    $this->params = [
        'pagador_nome'     => 'EMPRESA FICTICIA DE TESTE LTDA',
        'pagador_cpf_cnpj' => '00.000.000/0001-91', // pii-allowlist (CNPJ fictício de teste)
        'valor'            => 1234.56,
        'data_vencimento'  => '2031-06-10',
        'numero_documento' => 'INV-TESTE-001',
        'descricao'        => 'Mensalidade de teste',
    ];

    Http::fake([
        'sandbox.asaas.com/api/v3/customers*'                       => Http::sequence()
            ->push(['data' => []])
            ->push(['id' => 'cus_teste']),
        'sandbox.asaas.com/api/v3/payments/pay_teste/identificationField' => Http::response([
            'identificationField' => '00190.00009 01234.567890 12345.678901 1 00000000123456',
            'barCode'             => '00191000000000123456000000000000001234567890',
        ]),
        'sandbox.asaas.com/api/v3/payments/pay_teste/pixQrCode'     => Http::response(['payload' => '00020126PIXTESTE']),
        'sandbox.asaas.com/api/v3/payments'                         => Http::response([
            'id'          => 'pay_teste',
            'bankSlipUrl' => 'https://sandbox.asaas.com/b/pdf/pay_teste',
        ]),
    ]);
});

it('emite pela credencial ativa da empresa: o valor chega ao gateway sem arredondar e com a chave decifrada', function () {
    ($this->credencialAsaas)($this->b98, 'chave-sandbox-98');

    $result = app(BoletoService::class)->emitir($this->b98, $this->params);

    expect($result)->toBeInstanceOf(BoletoResult::class)
        ->and($result->nossoNumero)->toBe('pay_teste')
        ->and($result->valor)->toBe(1234.56)
        ->and($result->linhaDigitavel)->toBe('00190.00009 01234.567890 12345.678901 1 00000000123456')
        ->and($result->pixQrCode)->toBe('00020126PIXTESTE')
        ->and($result->pdfUrl)->toBe('https://sandbox.asaas.com/b/pdf/pay_teste');

    Http::assertSent(fn (Request $r) => $r->method() === 'POST'
        && $r->url() === 'https://sandbox.asaas.com/api/v3/payments'
        && $r['value'] === 1234.56
        && $r['customer'] === 'cus_teste'
        && $r['dueDate'] === '2031-06-10'
        && $r['externalReference'] === 'INV-TESTE-001'
        && $r->header('access_token')[0] === 'chave-sandbox-98');
});

it('Tier 0: empresa sem credencial própria não emite com a credencial de outra empresa', function () {
    ($this->credencialAsaas)($this->b98, 'chave-sandbox-98');

    expect(fn () => app(BoletoService::class)->emitir($this->b99, $this->params))
        ->toThrow(ModelNotFoundException::class);

    Http::assertNothingSent();
});

it('credencial inativa da própria empresa não é usada, mesmo existindo a ativa de outra', function () {
    ($this->credencialAsaas)($this->b98, 'chave-sandbox-98');
    ($this->credencialAsaas)($this->b99, 'chave-sandbox-99', ativo: false);

    expect(fn () => app(BoletoService::class)->emitir($this->b99, $this->params))
        ->toThrow(ModelNotFoundException::class);

    Http::assertNothingSent();
});

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
