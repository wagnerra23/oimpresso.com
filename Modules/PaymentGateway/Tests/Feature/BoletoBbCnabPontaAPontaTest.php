<?php

declare(strict_types=1);

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Modules\Financeiro\Listeners\OnCobrancaPagaCreateFinanceiroTitulo;
use Modules\PaymentGateway\Dto\EmitirCobrancaInput;
use Modules\PaymentGateway\Events\CobrancaPaga;
use Modules\PaymentGateway\Http\Controllers\Settings\CobrancaCnabArquivosController;
use Modules\PaymentGateway\Jobs\CnabRetornoProcessor;
use Modules\PaymentGateway\Models\PaymentGatewayCredential;
use Modules\PaymentGateway\Services\PaymentGatewayService;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

uses(Tests\TestCase::class);

/**
 * P-157 (Sabor Brasil) — boleto do Banco do Brasil de ponta a ponta, por arquivo CNAB 240.
 *
 * Fonte do contrato: `resources/js/Pages/Financeiro/ContasBancarias/Index.casos.md` P-157
 * ("o boleto é registrado no banco, sai em PDF para o cliente e a baixa volta sozinha pelo
 * retorno"). Layout: lib `lib-custom/laravel-boleto` — remessa `Cnab/Remessa/Cnab240/Banco/Bb.php`
 * (segmento P: nosso número 38-57, valor 86-100) e retorno `Cnab/Retorno/Cnab240/Banco/Bb.php`
 * (T: ocorrência 16-17, nosso número 38-57; U: valor recebido 78-92, crédito 146-153).
 *
 * O retorno do teste é montado a partir da PRÓPRIA remessa gerada (o nosso número sai do
 * segmento P dela), então o teste prova que o que vai ao banco é o que volta e casa.
 * Sem chamada a banco nenhum. Valores fictícios. Tenant 98 (ADR 0358), transação desfeita.
 *
 * Casos: UC-BBC-01 (remessa CNAB 240 válida) · UC-BBC-02 (PDF) · UC-BBC-03 (retorno liquida
 * a cobrança e baixa o título) · UC-BBC-04 (download só da própria empresa).
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('Usa o schema MySQL semeado (cobrancas/fin_titulos).');
    }

    $this->bizId = (int) $this->seededTenant()->id;
    $this->userId = (int) (DB::table('users')->where('business_id', $this->bizId)->value('id') ?? 0);
    if ($this->userId === 0) {
        $this->markTestSkipped('Sem usuário no tenant de teste.');
    }

    DB::beginTransaction();
    session(['business.id' => $this->bizId, 'user.business_id' => $this->bizId]);
    Storage::fake('local');
    Carbon::setTestNow('2031-03-01 10:00:00');

    $this->cred = PaymentGatewayCredential::query()->create([
        'business_id' => $this->bizId,
        'gateway_key' => 'bb_cnab',
        'ambiente' => 'production',
        'ativo' => true,
        'nome_display' => 'ZZ P-157 BB',
        'config_json' => [
            'agencia' => '1234', 'conta' => '12345678', 'convenio' => '1234567', 'carteira' => '17',
            'variacao_carteira' => '019', 'cedente_nome' => 'Empresa Ficticia',
            'cedente_documento' => '00000000000191', // pii-allowlist — fixture
            'cedente_endereco' => 'Rua Ficticia, 1', 'cedente_cep' => '88000000', 'cedente_uf' => 'SC', 'cedente_cidade' => 'Cidade',
        ],
    ]);

    $this->contato = (int) DB::table('contacts')->insertGetId([
        'business_id' => $this->bizId, 'type' => 'customer', 'name' => 'ZZ P-157 '.uniqid(),
        'mobile' => '0', 'created_by' => $this->userId, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $this->titulo = (int) DB::table('fin_titulos')->insertGetId([
        'business_id' => $this->bizId, 'numero' => 'ZZ-'.substr(uniqid(), -8), 'tipo' => 'receber',
        'status' => 'aberto', 'cliente_id' => $this->contato, 'valor_total' => 1234.56, 'valor_aberto' => 1234.56,
        'emissao' => '2031-02-01', 'vencimento' => '2031-03-10', 'competencia_mes' => '2031-02',
        'origem' => 'manual', 'created_by' => $this->userId, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->res = (new PaymentGatewayService())->comCredencial($this->cred)->emitirBoleto(new EmitirCobrancaInput(
        businessId: $this->bizId,
        contactId: $this->contato,
        valorCentavos: 123456,
        vencimento: new DateTimeImmutable('2031-03-10'),
        descricao: 'Teste P-157',
        idempotencyKey: 'zz-p157-'.uniqid(),
        origemType: 'fin_titulo',
        origemId: $this->titulo,
        meta: [
            'payer_name' => 'Pagador Ficticio', 'payer_cpf_cnpj' => '00000000191', // pii-allowlist — fixture
            'payer_address' => 'Rua Ficticia, 2', 'payer_cep' => '88000000', 'payer_uf' => 'SC', 'payer_city' => 'Cidade',
        ],
    ));
    $this->payload = $this->res->payloadGateway;
});

afterEach(function () {
    Carbon::setTestNow();
    if (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
});

/**
 * Uma linha CNAB 240: campos [início (1-indexado) => valor]. Os segmentos T/U saem com fundo
 * '0', como no arquivo do banco: a lib divide os campos numéricos por 100, e branco não é número.
 */
function bbLinha(array $campos, string $fundo = ' '): string
{
    $l = str_repeat($fundo, 240);
    foreach ($campos as $ini => $v) {
        $l = substr_replace($l, $v, $ini - 1, strlen($v));
    }

    return $l;
}

/** @return array<int, string> linhas da remessa gerada */
function bbRemessa(array $payload): array
{
    return array_values(array_filter(preg_split('/\r\n|\n/', Storage::disk('local')->get($payload['cnab_remessa_path'])), fn ($l) => $l !== ''));
}

it('UC-BBC-01 — a remessa é um CNAB 240 do BB de verdade, com o boleto no segmento P', function () {
    $linhas = bbRemessa($this->payload);

    expect($this->payload['cnab_remessa_path'])->toStartWith("cnab-remessas/biz-{$this->bizId}/cred-{$this->cred->id}/");
    foreach ($linhas as $l) {
        expect(mb_strlen($l))->toBe(240);
        expect(substr($l, 0, 3))->toBe('001');
    }
    // header (0), header de lote (1), P/Q/R (3), trailer de lote (5), trailer (9)
    expect(array_map(fn ($l) => $l[7], $linhas))->toBe(['0', '1', '3', '3', '3', '5', '9']);
    expect(substr($linhas[0], 142, 1))->toBe('1'); // 1 = remessa
    expect(substr($linhas[0], 47, 3))->toBe('019'); // variação da carteira

    $p = $linhas[2];
    expect($p[13])->toBe('P');
    expect(trim(substr($p, 37, 20)))->toBe($this->res->nossoNumero);
    expect(substr($p, 77, 8))->toBe('10032031');                 // vencimento
    expect(substr($p, 85, 15))->toBe('000000000123456');         // valor 1.234,56
});

it('UC-BBC-02 — o PDF do boleto é gerado e guardado na pasta da empresa', function () {
    expect($this->payload)->not->toHaveKey('boleto_pdf_erro');
    $pdf = $this->payload['boleto_pdf_path'] ?? '';
    expect($pdf)->toStartWith("cnab-boletos/biz-{$this->bizId}/");
    expect(substr(Storage::disk('local')->get($pdf), 0, 4))->toBe('%PDF');
});

it('UC-BBC-03 — o retorno do BB liquida a cobrança e a baixa quita o título', function () {
    $nosso = substr(bbRemessa($this->payload)[2], 37, 20);
    $retorno = implode("\r\n", [
        bbLinha([1 => '00100000', 18 => '2', 19 => '00000000000191', 143 => '2', 144 => '11032031']),
        bbLinha([1 => '00100011', 9 => 'T', 192 => '11032031', 200 => '11032031']),
        bbLinha([1 => '00100013', 9 => '00001', 14 => 'T', 16 => '06', 38 => $nosso, 74 => '10032031', 82 => '000000000123456'], '0'),
        bbLinha([1 => '00100013', 9 => '00002', 14 => 'U', 16 => '06', 78 => '000000000123456', 93 => '000000000123456', 138 => '11032031', 146 => '11032031'], '0'),
        bbLinha([1 => '00100015', 18 => '000004']),
        bbLinha([1 => '00199999', 18 => '000001', 24 => '000006']),
    ]);
    $arquivo = "cnab-retornos/biz-{$this->bizId}/cred-{$this->cred->id}/teste.ret";
    Storage::disk('local')->put($arquivo, $retorno);

    Event::fake([CobrancaPaga::class]);
    (new CnabRetornoProcessor(credentialId: $this->cred->id, arquivoRetornoPath: $arquivo, disk: 'local'))->handle();

    $cob = DB::table('cobrancas')->where('business_id', $this->bizId)->where('id', $this->res->cobrancaId)->first();
    expect($cob->status)->toBe('paga');
    expect((int) $cob->valor_pago_centavos)->toBe(123456);

    $evento = null;
    Event::assertDispatched(CobrancaPaga::class, function (CobrancaPaga $e) use (&$evento) {
        $evento = $e;

        return $e->cobrancaId === $this->res->cobrancaId && $e->businessId === $this->bizId
            && $e->origemType === 'fin_titulo' && $e->origemId === $this->titulo;
    });

    // A baixa automática fora da empresa dona do SaaS exige o título ter link de pagamento (US-FIN-081).
    config(['financeiro.link_pagamento.enabled' => true]);
    DB::table('fin_titulo_links_pagamento')->insert([
        'business_id' => $this->bizId, 'titulo_id' => $this->titulo, 'token_hash' => hash('sha256', uniqid('', true)),
        'expira_em' => '2031-04-01 00:00:00', 'created_by' => $this->userId, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $accountId = DB::table('accounts')->insertGetId([
        'business_id' => $this->bizId, 'name' => 'ZZ P-157', 'account_number' => '0', 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('fin_contas_bancarias')->insert([
        'business_id' => $this->bizId, 'account_id' => $accountId, 'banco_codigo' => '001', 'agencia' => '1234',
        'carteira' => '17', 'beneficiario_documento' => '00.000.000/0001-91', // pii-allowlist — fixture
        'beneficiario_razao_social' => 'Empresa Ficticia', 'ativo_para_boleto' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    (new OnCobrancaPagaCreateFinanceiroTitulo())->handle($evento);

    $titulo = DB::table('fin_titulos')->where('business_id', $this->bizId)->where('id', $this->titulo)->first();
    $baixa = DB::table('fin_titulo_baixas')->where('business_id', $this->bizId)->where('titulo_id', $this->titulo)->first();
    expect((float) $baixa->valor_baixa)->toBe(1234.56);
    expect($baixa->data_baixa)->toStartWith('2031-03-11');
    expect((float) $titulo->valor_aberto)->toBe(0.0);
    expect($titulo->status)->toBe('quitado');
});

it('UC-BBC-04 — remessa e PDF só baixam na própria empresa', function () {
    $ctl = new CobrancaCnabArquivosController();
    $req = Request::create('/settings/payment-gateways/cobrancas/x/remessa');
    $req->setLaravelSession(app('session.store'));

    expect($ctl->remessa($req, $this->res->cobrancaId)->getStatusCode())->toBe(200);
    expect($ctl->boleto($req, $this->res->cobrancaId)->getStatusCode())->toBe(200);

    $outra = (int) DB::table('business')->where('id', '!=', $this->bizId)->orderBy('id')->value('id');
    if ($outra === 0) {
        $this->markTestSkipped('Só existe um business no banco de teste.');
    }
    session(['business.id' => $outra, 'user.business_id' => $outra]);
    expect(fn () => $ctl->remessa($req, $this->res->cobrancaId))->toThrow(NotFoundHttpException::class);
    expect(fn () => $ctl->boleto($req, $this->res->cobrancaId))->toThrow(NotFoundHttpException::class);
});

/** Emite mais um boleto BB na mesma credencial e devolve o resultado. */
function bbEmitirOutro(object $t)
{
    return (new PaymentGatewayService())->comCredencial($t->cred)->emitirBoleto(new EmitirCobrancaInput(
        businessId: $t->bizId,
        contactId: $t->contato,
        valorCentavos: 5000,
        vencimento: new DateTimeImmutable('2031-03-20'),
        descricao: 'Teste caso 165',
        idempotencyKey: 'zz-c165-'.uniqid(),
        meta: [
            'payer_name' => 'Pagador Ficticio', 'payer_cpf_cnpj' => '00000000191', // pii-allowlist — fixture
            'payer_address' => 'Rua Ficticia, 2', 'payer_cep' => '88000000', 'payer_uf' => 'SC', 'payer_city' => 'Cidade',
        ],
    ));
}

it('caso 165 — o nosso número é o próximo da credencial, sem repetir', function () {
    expect((int) DB::table('payment_gateway_credentials')->where('id', $this->cred->id)->value('cnab_ultimo_numero'))->toBe(1);

    $segundo = bbEmitirOutro($this);

    expect($segundo->nossoNumero)->not->toBe($this->res->nossoNumero);
    expect((int) DB::table('payment_gateway_credentials')->where('id', $this->cred->id)->value('cnab_ultimo_numero'))->toBe(2);
    // BB, convênio de 7 dígitos: o nosso número é o convênio seguido do número com 10 dígitos.
    expect($this->res->nossoNumero)->toBe('1234567'.'0000000001');
    expect($segundo->nossoNumero)->toBe('1234567'.'0000000002');
});

it('caso 165 — número já usado por um boleto antigo é pulado', function () {
    // Volta o contador: a próxima emissão calcularia o mesmo número do boleto do beforeEach.
    DB::table('payment_gateway_credentials')->where('id', $this->cred->id)->update(['cnab_ultimo_numero' => 0]);

    $outro = bbEmitirOutro($this);

    expect($outro->nossoNumero)->not->toBe($this->res->nossoNumero);
    expect((int) DB::table('payment_gateway_credentials')->where('id', $this->cred->id)->value('cnab_ultimo_numero'))->toBe(2);
});

it('caso 165 — retorno com o mesmo nosso número em duas cobranças não quita nenhuma', function () {
    // Uma segunda cobrança da mesma credencial com o nosso número da primeira (como o hash antigo podia gerar).
    $copia = (array) DB::table('cobrancas')->where('id', $this->res->cobrancaId)->first();
    unset($copia['id']);
    $copia['idempotency_key'] = 'zz-c165-dup-'.uniqid();
    $copia['origem_id'] = null;
    $duplicada = (int) DB::table('cobrancas')->insertGetId($copia);

    $nosso = substr(bbRemessa($this->payload)[2], 37, 20);
    $retorno = implode("\r\n", [
        bbLinha([1 => '00100000', 18 => '2', 19 => '00000000000191', 143 => '2', 144 => '11032031']),
        bbLinha([1 => '00100011', 9 => 'T', 192 => '11032031', 200 => '11032031']),
        bbLinha([1 => '00100013', 9 => '00001', 14 => 'T', 16 => '06', 38 => $nosso, 74 => '10032031', 82 => '000000000123456'], '0'),
        bbLinha([1 => '00100013', 9 => '00002', 14 => 'U', 16 => '06', 78 => '000000000123456', 93 => '000000000123456', 138 => '11032031', 146 => '11032031'], '0'),
        bbLinha([1 => '00100015', 18 => '000004']),
        bbLinha([1 => '00199999', 18 => '000001', 24 => '000006']),
    ]);
    $arquivo = "cnab-retornos/biz-{$this->bizId}/cred-{$this->cred->id}/dup.ret";
    Storage::disk('local')->put($arquivo, $retorno);

    Event::fake([CobrancaPaga::class]);
    (new CnabRetornoProcessor(credentialId: $this->cred->id, arquivoRetornoPath: $arquivo, disk: 'local'))->handle();

    // O mesmo arquivo com uma cobrança só quita (UC-BBC-03); com duas, nenhuma é tocada.
    expect(DB::table('cobrancas')->whereIn('id', [$this->res->cobrancaId, $duplicada])->pluck('status')->unique()->values()->all())->toBe(['emitida']);
    Event::assertNotDispatched(CobrancaPaga::class);
});
