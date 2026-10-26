<?php
// @covers-us US-RB-021

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Modules\PaymentGateway\Contracts\CobrancaRecorrenteDriver;
use Modules\PaymentGateway\Dto\CardToken;
use Modules\PaymentGateway\Dto\EmitirCobrancaInput;
use Modules\PaymentGateway\Exceptions\CredentialMisconfiguredException;
use Modules\PaymentGateway\Exceptions\DriverNotSupportedException;
use Modules\PaymentGateway\Exceptions\GatewayUnavailableException;
use Modules\PaymentGateway\Exceptions\InvalidPayerException;
use Modules\PaymentGateway\Models\PaymentGatewayCredential;
use Modules\PaymentGateway\Services\Drivers\BcbPixDriver;

uses(Tests\TestCase::class);

/**
 * BcbPixDriver — Pix Automático do BCB (Resolução 380/2024), conforme a API Pix v2.10.0
 * (`bacen/pix-api` `openapi.yaml`, conferida em 2026-10-26):
 *   - recorrência:            POST /rec                (antes: PUT /rec/{id}, que a spec não tem)
 *   - cancelar recorrência:   PATCH /rec/{idRec} {status: CANCELADA}   (antes: DELETE, idem)
 *   - cobrança de cada ciclo: PUT /cobr/{txid}         (não existia; o recebedor cria cada uma)
 *   - escopos OAuth:          rec.* cobr.* solicrec.*  (antes: pix.recorrencia.*)
 * Decisão [W] 2026-10-26: Pix Automático da assinatura pelo BCB direto (US-RECURRINGBILLING-002).
 *
 * Sem banco: a credencial é montada em memória e o HTTP é falso — por isso este arquivo pode rodar
 * na lane SQLite. Antes ele criava a credencial no banco do business 1 e não rodava em lane nenhuma.
 */
function bcbCred(array $config = [], string $gateway = 'bcb_pix'): PaymentGatewayCredential
{
    $cred = new PaymentGatewayCredential([
        'business_id' => 98, 'gateway_key' => $gateway, 'ambiente' => 'sandbox', 'ativo' => true,
        'nome_display' => 'BCB Pix Auto Test',
        'config_json' => array_merge([
            'client_id' => 'bcb-cli', 'client_secret' => 'bcb-sec', 'base_url' => 'https://psp.fake.local',
            'recebedor_agencia' => '9708', 'recebedor_conta' => '012682',
        ], $config),
    ]);
    $cred->id = 4242;

    return $cred;
}

function bcbInput(array $over = []): EmitirCobrancaInput
{
    return new EmitirCobrancaInput(...array_merge([
        'businessId' => 98, 'contactId' => 700, 'valorCentavos' => 10607,
        'vencimento' => new DateTimeImmutable('2031-04-15'), 'descricao' => 'Mensalidade Oimpresso',
        'idempotencyKey' => 'rb-inv-55-2031-04', 'origemType' => 'invoice', 'origemId' => 55,
        'meta' => ['payer_cpf_cnpj' => '123.456.789-01', 'payer_name' => 'Cliente Teste', 'periodicidade' => 'MENSAL'],
    ], $over));
}

beforeEach(function () {
    $this->cred = bcbCred();
});

it('key=bcb_pix, supports só pix_recv e implementa a cobrança recorrente', function () {
    $d = new BcbPixDriver();
    expect($d->key())->toBe('bcb_pix')
        ->and($d->supports('pix_recv'))->toBeTrue()
        ->and($d->supports('boleto'))->toBeFalse()
        ->and($d->supports('pix_cob'))->toBeFalse()
        ->and($d)->toBeInstanceOf(CobrancaRecorrenteDriver::class);
});

it('boleto, pix cob/cobv, cartão e refund não são deste driver', function () {
    $d = new BcbPixDriver();
    expect(fn () => $d->emitirBoleto(bcbInput(), $this->cred))->toThrow(DriverNotSupportedException::class);
    expect(fn () => $d->emitirPix(bcbInput(), $this->cred, 'cob'))->toThrow(DriverNotSupportedException::class);
    expect(fn () => $d->emitirPix(bcbInput(), $this->cred, 'cobv'))->toThrow(DriverNotSupportedException::class);
    expect(fn () => $d->cobrarCartao(bcbInput(), $this->cred,
        new CardToken(token: 't', brand: 'visa', lastFour: '4242', holderName: 'X', expMonth: '12', expYear: '2030')))
        ->toThrow(DriverNotSupportedException::class);
    expect(fn () => $d->refund((object) ['gateway_external_id' => 'x'], $this->cred, 100, 'm'))
        ->toThrow(DriverNotSupportedException::class);
});

it('recorrência: POST /v2/rec com vinculo.devedor, valorRec em texto e escopos da spec', function () {
    Http::fake([
        '*/oauth/token' => Http::response(['access_token' => 'bcb-tk'], 200),
        '*/v2/rec' => Http::response(['idRec' => 'RR1234567820240115abcdefghijk', 'status' => 'CRIADA'], 201),
    ]);

    $r = (new BcbPixDriver())->emitirPixAutomatico(bcbInput(['valorCentavos' => 3500]), $this->cred);

    expect($r->gatewayExternalId)->toBe('RR1234567820240115abcdefghijk')->and($r->tipo)->toBe('pix_recv');
    Http::assertSent(fn ($req) => str_ends_with($req->url(), '/oauth/token')
        && $req['scope'] === 'rec.read rec.write cobr.read cobr.write solicrec.read solicrec.write payloadlocationrec.read payloadlocationrec.write');
    Http::assertSent(fn ($req) => $req->method() === 'POST' && str_ends_with($req->url(), '/v2/rec')
        && $req['vinculo']['devedor'] === ['cpf' => '12345678901', 'nome' => 'Cliente Teste']
        && $req['vinculo']['contrato'] === 'rb-inv-55-2031-04'
        && $req['calendario'] === ['dataInicial' => '2031-04-15', 'periodicidade' => 'MENSAL']
        && $req['valor'] === ['valorRec' => '35.00']
        && $req['politicaRetentativa'] === 'NAO_PERMITE'
        && ! isset($req['recebedor']) && ! isset($req['pagador']));
});

it('recorrência com CNPJ manda cnpj, não cpf; documento inválido é recusado', function () {
    Http::fake([
        '*/oauth/token' => Http::response(['access_token' => 'tk'], 200),
        '*/v2/rec' => Http::response(['idRec' => 'RR-pj', 'status' => 'CRIADA'], 201),
    ]);
    (new BcbPixDriver())->emitirPixAutomatico(bcbInput(['meta' => ['payer_cpf_cnpj' => '12.345.678/0001-00']]), $this->cred);
    Http::assertSent(fn ($req) => str_ends_with($req->url(), '/v2/rec')
        && $req['vinculo']['devedor'] === ['cnpj' => '12345678000100']);

    expect(fn () => (new BcbPixDriver())->emitirPixAutomatico(bcbInput(['meta' => ['payer_cpf_cnpj' => '123']]), $this->cred))
        ->toThrow(InvalidPayerException::class);
});

it('recorrência: erro do PSP ou resposta sem idRec viram GatewayUnavailable', function () {
    Http::fake([
        '*/oauth/token' => Http::response(['access_token' => 'tk'], 200),
        '*/v2/rec' => Http::sequence()->push(['erro' => 'periodicidade inválida'], 400)->push(['status' => 'CRIADA'], 201),
    ]);
    expect(fn () => (new BcbPixDriver())->emitirPixAutomatico(bcbInput(), $this->cred))->toThrow(GatewayUnavailableException::class);
    expect(fn () => (new BcbPixDriver())->emitirPixAutomatico(bcbInput(), $this->cred))->toThrow(GatewayUnavailableException::class);
});

it('cobrança do ciclo: PUT /v2/cobr/{txid} com idRec, vencimento, valor original e conta do recebedor', function () {
    Http::fake([
        '*/oauth/token' => Http::response(['access_token' => 'tk'], 200),
        '*/v2/cobr/*' => Http::response(['txid' => 'devolvido-pelo-psp', 'status' => 'CRIADA'], 201),
    ]);
    $txid = BcbPixDriver::txidDe('rb-inv-55-2031-04');

    $r = (new BcbPixDriver())->emitirCobrancaRecorrente(bcbInput(), $this->cred, 'RR1234567820240115abcdefghijk');

    expect($r->gatewayExternalId)->toBe('devolvido-pelo-psp');
    expect(strlen($txid))->toBe(35)->and($txid)->toMatch('/^[a-zA-Z0-9]{26,35}$/');
    Http::assertSent(fn ($req) => $req->method() === 'PUT' && str_ends_with($req->url(), "/v2/cobr/{$txid}")
        && $req['idRec'] === 'RR1234567820240115abcdefghijk'
        && $req['calendario'] === ['dataDeVencimento' => '2031-04-15']
        && $req['valor'] === ['original' => '106.07']
        && $req['ajusteDiaUtil'] === true
        && $req['recebedor'] === ['agencia' => '9708', 'conta' => '012682', 'tipoConta' => 'CORRENTE']);
});

it('cobrança do ciclo: mesma chave dá o mesmo txid; chave diferente, txid diferente', function () {
    expect(BcbPixDriver::txidDe('rb-inv-55-2031-04'))->toBe(BcbPixDriver::txidDe('rb-inv-55-2031-04'))
        ->and(BcbPixDriver::txidDe('rb-inv-55-2031-04'))->not->toBe(BcbPixDriver::txidDe('rb-inv-55-2031-05'));
});

it('cobrança do ciclo: valor em centavos vira texto sem float (1 centavo, 10 centavos, milhar)', function () {
    Http::fake(['*/oauth/token' => Http::response(['access_token' => 'tk'], 200), '*/v2/cobr/*' => Http::response(['status' => 'CRIADA'], 201)]);
    $d = new BcbPixDriver();
    foreach ([1 => '0.01', 10 => '0.10', 123456 => '1234.56'] as $centavos => $texto) {
        $d->emitirCobrancaRecorrente(bcbInput(['valorCentavos' => $centavos, 'idempotencyKey' => "k{$centavos}"]), $this->cred, 'RR-x');
        Http::assertSent(fn ($req) => str_contains($req->url(), '/v2/cobr/') && $req['valor']['original'] === $texto);
    }
});

it('cobrança do ciclo recusa sem idRec, valor zero ou sem conta do recebedor', function () {
    $d = new BcbPixDriver();
    expect(fn () => $d->emitirCobrancaRecorrente(bcbInput(), $this->cred, ''))->toThrow(InvalidPayerException::class);
    expect(fn () => $d->emitirCobrancaRecorrente(bcbInput(['valorCentavos' => 0]), $this->cred, 'RR-x'))->toThrow(InvalidPayerException::class);
    expect(fn () => $d->emitirCobrancaRecorrente(bcbInput(), bcbCred(['recebedor_conta' => '']), 'RR-x'))
        ->toThrow(CredentialMisconfiguredException::class);
});

it('cancelar a recorrência é PATCH status CANCELADA; sem id é recusado', function () {
    Http::fake([
        '*/oauth/token' => Http::response(['access_token' => 'tk'], 200),
        '*/v2/rec/RR000123' => Http::response(['status' => 'CANCELADA'], 200),
    ]);
    (new BcbPixDriver())->cancelar((object) ['gateway_external_id' => 'RR000123'], $this->cred, 'cliente cancelou');
    Http::assertSent(fn ($req) => $req->method() === 'PATCH' && str_ends_with($req->url(), '/v2/rec/RR000123')
        && $req->data() === ['status' => 'CANCELADA']);

    expect(fn () => (new BcbPixDriver())->cancelar((object) [], $this->cred, 'x'))->toThrow(InvalidPayerException::class);
});

it('consultar mapeia status da recorrência', function () {
    Http::fake([
        '*/oauth/token' => Http::response(['access_token' => 'tk'], 200),
        '*/v2/rec/RR-active' => Http::response(['idRec' => 'RR-active', 'status' => 'ATIVA'], 200),
    ]);
    expect((new BcbPixDriver())->consultar((object) ['gateway_external_id' => 'RR-active'], $this->cred)->status)->toBe('emitida');
});

it('healthCheck OK e down', function () {
    Http::fake(['*/oauth/token' => Http::sequence()->push(['access_token' => 'tk'], 200)->push(['error' => 'bad'], 401)]);
    expect((new BcbPixDriver())->healthCheck($this->cred)->ok)->toBeTrue();
    expect((new BcbPixDriver())->healthCheck($this->cred)->ok)->toBeFalse();
});

it('processWebhook lê os formatos da spec: cobsr[].txid e recs[].idRec', function () {
    $d = new BcbPixDriver();
    expect($d->processWebhook(['cobsr' => [['idRec' => 'RR-9', 'txid' => 'TX-cobr-1', 'status' => 'CONCLUIDA']]], $this->cred)->gateway_external_id)->toBe('TX-cobr-1')
        ->and($d->processWebhook(['recs' => [['idRec' => 'RR-rec-1', 'status' => 'APROVADA']]], $this->cred)->gateway_external_id)->toBe('RR-rec-1');
});

it('processWebhook extrai idRec do payload BCB', function () {
    $d = new BcbPixDriver();
    expect($d->processWebhook(['pix' => [['valor' => '99.90', 'infoPagador' => ['idRec' => 'RR-001']]]], $this->cred)->gateway_external_id)->toBe('RR-001')
        ->and($d->processWebhook(['idRec' => 'RR-direct'], $this->cred)->gateway_external_id)->toBe('RR-direct')
        ->and($d->processWebhook(['no_id' => 'x'], $this->cred))->toBeNull();
});

it('credencial sem base_url ou de outro gateway é recusada', function () {
    expect(fn () => (new BcbPixDriver())->healthCheck(bcbCred(['base_url' => ''])))->toThrow(CredentialMisconfiguredException::class);
    expect(fn () => (new BcbPixDriver())->healthCheck(bcbCred([], 'inter')))->toThrow(CredentialMisconfiguredException::class);
});
