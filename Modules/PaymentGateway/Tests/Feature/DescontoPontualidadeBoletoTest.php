<?php

declare(strict_types=1);

use Carbon\Carbon;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Modules\Financeiro\Listeners\OnCobrancaPagaCreateFinanceiroTitulo;
use Modules\PaymentGateway\Dto\EmitirCobrancaInput;
use Modules\PaymentGateway\Events\CobrancaPaga;
use Modules\PaymentGateway\Models\PaymentGatewayCredential;
use Modules\PaymentGateway\Services\Cnab\Drivers\BBCnabDriver;
use Modules\PaymentGateway\Services\DescontoPontualidade;
use Modules\PaymentGateway\Services\PaymentGatewayService;

uses(Tests\TestCase::class);

/**
 * P-086 (Sabor Brasil) — o % de desconto de pontualidade do cliente chega ao boleto e à baixa.
 *
 * Fonte do contrato: `resources/js/Pages/Cliente/Index.casos.md` P-086 + Delphi
 * `PESSOAS.BOLETO_PERC_DESCONTO_PADRAO` com `BOLETOS.DIADESCONTO = 0` (desconto até o próprio
 * vencimento). Layout do desconto: CNAB 240 BB segmento P (lib `Cnab/Remessa/Cnab240/Banco/Bb.php`
 * pos. 142-165) e Inter API v3 (objeto único `desconto`, changelog Inter 18/05/2026).
 *
 * Regra mestre de valor: cada valor é conferido por DOIS caminhos — o literal esperado e a conta
 * independente em float arredondado (`round(v * pct / 100, 2)`), que tem de bater com a conta em
 * inteiros do serviço. Valores fictícios. Tenant 98 (ADR 0358); tudo numa transação desfeita.
 *
 * Casos: UC-DESC-01 (cálculo) · UC-DESC-02 (BB/CNAB) · UC-DESC-03 (Inter) · UC-DESC-04 (sem
 * desconto: cliente sem %, título vencido) · UC-DESC-05 (baixa até o vencimento) · UC-DESC-06
 * (baixa depois do vencimento) · UC-DESC-07 (contato de outra empresa).
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('Usa o schema MySQL semeado (contacts/fin_titulos/cobrancas).');
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
});

afterEach(function () {
    Carbon::setTestNow();
    if (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
});

function descContato(int $biz, int $userId, ?float $pct): int
{
    return (int) DB::table('contacts')->insertGetId([
        'business_id' => $biz,
        'type' => 'customer',
        'name' => 'ZZ P-086 '.uniqid(),
        'mobile' => '0',
        'created_by' => $userId,
        'boleto_desconto_pontualidade_pct' => $pct,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function descTitulo(int $biz, int $userId, int $contactId, float $valor, string $vencimento): int
{
    return (int) DB::table('fin_titulos')->insertGetId([
        'business_id' => $biz,
        'numero' => 'ZZ-'.substr(uniqid(), -8),
        'tipo' => 'receber',
        'status' => 'aberto',
        'cliente_id' => $contactId,
        'valor_total' => $valor,
        'valor_aberto' => $valor,
        'emissao' => '2031-02-01',
        'vencimento' => $vencimento,
        'competencia_mes' => '2031-02',
        'origem' => 'manual',
        'created_by' => $userId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function descCredencial(int $biz, string $key, array $config): PaymentGatewayCredential
{
    return PaymentGatewayCredential::query()->create([
        'business_id' => $biz,
        'gateway_key' => $key,
        'ambiente' => 'sandbox',
        'ativo' => true,
        'nome_display' => 'ZZ P-086 '.$key,
        'config_json' => $config,
    ]);
}

function descBbConfig(): array
{
    return [
        'agencia' => '1234', 'conta' => '12345678', 'convenio' => '1234567', 'carteira' => '17',
        'variacao_carteira' => '019', // obrigatória desde o P-157 (#10168)
        'cedente_nome' => 'Empresa Ficticia', 'cedente_documento' => '00000000000000', // pii-allowlist — fixture
    ];
}

function descInput(int $biz, int $contactId, int $centavos, string $venc, ?int $tituloId = null): EmitirCobrancaInput
{
    return new EmitirCobrancaInput(
        businessId: $biz,
        contactId: $contactId,
        valorCentavos: $centavos,
        vencimento: new DateTimeImmutable($venc),
        descricao: 'Teste P-086',
        idempotencyKey: 'zz-p086-'.uniqid(),
        origemType: $tituloId ? 'fin_titulo' : null,
        origemId: $tituloId,
        meta: ['payer_name' => 'Pagador Ficticio', 'payer_cpf_cnpj' => '00000000000'], // pii-allowlist — fixture
    );
}

it('UC-DESC-01 — desconto em centavos inteiros, meio centavo para cima, igual à conta em float', function () {
    // [valor em centavos, % em centésimos, esperado]
    $casos = [[100000, 500, 5000], [33333, 300, 1000], [50, 700, 4], [12345, 750, 926], [100, 10000, 100]];
    foreach ($casos as [$valor, $pct, $esperado]) {
        $inteiro = DescontoPontualidade::valorCentavos($valor, $pct);
        $float = (int) round(round(($valor / 100) * ($pct / 100) / 100, 2) * 100);
        expect($inteiro)->toBe($esperado);
        expect($inteiro)->toBe($float);
    }
});

it('UC-DESC-02 — BB por CNAB: o boleto leva o desconto até o vencimento e a cobrança o guarda', function () {
    $contato = descContato($this->bizId, $this->userId, 5.0);
    $cred = descCredencial($this->bizId, 'bb_cnab', descBbConfig());

    $res = (new PaymentGatewayService())->comCredencial($cred)
        ->emitirBoleto(descInput($this->bizId, $contato, 100000, '2031-03-10'));

    $gravado = DB::table('cobrancas')->where('business_id', $this->bizId)->where('id', $res->cobrancaId)->value('payload_gateway');
    $desconto = json_decode((string) $gravado, true)['desconto_pontualidade'] ?? null;
    expect($desconto)->toMatchArray(['valor_centavos' => 5000, 'data_limite' => '2031-03-10', 'percentual' => 5.0]);

    // O boleto da lib (o que vai ao segmento P do CNAB 240) recebe valor e data.
    $driver = new BBCnabDriver();
    $build = new ReflectionMethod($driver, 'buildBoletoData');
    $input = new EmitirCobrancaInput(
        businessId: $this->bizId, contactId: $contato, valorCentavos: 100000,
        vencimento: new DateTimeImmutable('2031-03-10'), descricao: 'x', idempotencyKey: 'zz-p086-bb',
        desconto: $desconto,
    );
    $boleto = new \Eduardokum\LaravelBoleto\Boleto\Banco\Bb($build->invoke($driver, $input, descBbConfig()));
    expect((float) $boleto->getDesconto())->toBe(50.0);
    expect($boleto->getDataDesconto()->format('Y-m-d'))->toBe('2031-03-10');
});

it('UC-DESC-03 — Inter: a API v3 recebe o desconto de valor fixo até o vencimento', function () {
    $contato = descContato($this->bizId, $this->userId, 7.0);
    $cred = descCredencial($this->bizId, 'inter', ['client_id' => 'fake', 'client_secret' => 'fake']);
    $enviado = null;
    Http::fake(function (HttpRequest $req) use (&$enviado) {
        if (str_contains($req->url(), '/oauth/')) {
            return Http::response(['access_token' => 'fake', 'expires_in' => 3600], 200);
        }
        $enviado = $req->data();

        return Http::response(['nossoNumero' => '00000000001'], 201);
    });

    (new PaymentGatewayService())->comCredencial($cred)->emitirBoleto(descInput($this->bizId, $contato, 33333, '2031-03-10'));

    // 7% de 333,33 = 23,3331 → 23,33
    expect($enviado['desconto'] ?? null)->toBe(['codigo' => 'VALORFIXODATAINFORMADA', 'quantidadeDias' => 0, 'valor' => 23.33]);
    expect($enviado['valorNominal'])->toBe(333.33);
});

it('UC-DESC-04 — sem desconto quando o cliente não tem % ou o título já venceu', function () {
    $semPct = descContato($this->bizId, $this->userId, null);
    expect(DescontoPontualidade::calcular($this->bizId, $semPct, 100000, new DateTimeImmutable('2031-03-10')))->toBe([]);

    // Título vencido em 20/02: a emissão recai o vencimento pra hoje (01/03) e não dá desconto.
    $comPct = descContato($this->bizId, $this->userId, 5.0);
    $vencido = descTitulo($this->bizId, $this->userId, $comPct, 1000.00, '2031-02-20');
    expect(DescontoPontualidade::calcular($this->bizId, $comPct, 100000, new DateTimeImmutable('2031-03-01'), 'fin_titulo', $vencido))->toBe([]);

    // Controle: o mesmo cliente, título a vencer, ganha desconto.
    $aVencer = descTitulo($this->bizId, $this->userId, $comPct, 1000.00, '2031-03-10');
    expect(DescontoPontualidade::calcular($this->bizId, $comPct, 100000, new DateTimeImmutable('2031-03-10'), 'fin_titulo', $aVencer)['valor_centavos'] ?? null)->toBe(5000);
});

function descBaixa(object $t, string $pagaEm, int $pagoCentavos): object
{
    config(['financeiro.link_pagamento.enabled' => true]);
    $contato = descContato($t->bizId, $t->userId, 5.0);
    $titulo = descTitulo($t->bizId, $t->userId, $contato, 1000.00, '2031-03-10');
    DB::table('fin_titulo_links_pagamento')->insert([
        'business_id' => $t->bizId, 'titulo_id' => $titulo, 'token_hash' => hash('sha256', uniqid('', true)),
        'expira_em' => '2031-04-01 00:00:00', 'created_by' => $t->userId, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $accountId = DB::table('accounts')->insertGetId([
        'business_id' => $t->bizId, 'name' => 'ZZ P-086', 'account_number' => '0', 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('fin_contas_bancarias')->insert([
        'business_id' => $t->bizId, 'account_id' => $accountId, 'banco_codigo' => '001', 'agencia' => '1234',
        'carteira' => '17', 'beneficiario_documento' => '00.000.000/0000-00', // pii-allowlist — fixture
        'beneficiario_razao_social' => 'Empresa Ficticia', 'ativo_para_boleto' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $cobranca = (int) DB::table('cobrancas')->insertGetId([
        'business_id' => $t->bizId, 'tipo' => 'boleto', 'status' => 'paga', 'valor_centavos' => 100000,
        'valor_pago_centavos' => $pagoCentavos, 'vencimento' => '2031-03-10', 'paga_em' => $pagaEm,
        'contact_id' => $contato, 'descricao' => 'Teste P-086', 'idempotency_key' => 'zz-p086-'.uniqid(),
        'origem_type' => 'fin_titulo', 'origem_id' => $titulo, 'forma_pagamento' => 'boleto',
        'payload_gateway' => json_encode(['desconto_pontualidade' => [
            'tipo' => 'valor_fixo_ate_data', 'percentual' => 5.0, 'valor_centavos' => 5000, 'data_limite' => '2031-03-10',
        ]]),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    (new OnCobrancaPagaCreateFinanceiroTitulo())->handle(new CobrancaPaga(
        cobrancaId: $cobranca, businessId: $t->bizId, valorPagoCentavos: $pagoCentavos,
        pagaEm: new DateTimeImmutable($pagaEm), formaPagamento: 'boleto', occurredAt: new DateTimeImmutable($pagaEm),
        origemType: 'fin_titulo', origemId: $titulo,
    ));

    return (object) [
        'titulo' => DB::table('fin_titulos')->where('business_id', $t->bizId)->where('id', $titulo)->first(),
        'baixa' => DB::table('fin_titulo_baixas')->where('business_id', $t->bizId)->where('titulo_id', $titulo)->first(),
    ];
}

it('UC-DESC-05 — pago até o vencimento com o desconto: baixa registra o desconto e quita o título', function () {
    $r = descBaixa($this, '2031-03-10 15:00:00', 95000);

    // valor_baixa é o principal abatido e o desconto vai à parte: é o padrão que Dashboard,
    // Relatórios, Impostos e a exportação contábil leem (recebido = valor_baixa + juros + multa − desconto).
    // caminho 1: literais · caminho 2: o principal fecha o título e o recebido fecha o que o cliente pagou
    expect((float) $r->baixa->valor_baixa)->toBe(1000.0);
    expect((float) $r->baixa->desconto)->toBe(50.0);
    expect((float) $r->baixa->valor_baixa)->toBe((float) $r->titulo->valor_total);
    expect(round((float) $r->baixa->valor_baixa + (float) $r->baixa->juros + (float) $r->baixa->multa - (float) $r->baixa->desconto, 2))->toBe(950.0);
    expect((float) $r->titulo->valor_aberto)->toBe(0.0);
    expect($r->titulo->status)->toBe('quitado');
});

it('UC-DESC-06 — pago com desconto DEPOIS do vencimento: sem desconto, o restante fica em aberto', function () {
    $r = descBaixa($this, '2031-03-11 09:00:00', 95000);

    expect((float) $r->baixa->valor_baixa)->toBe(950.0);
    expect((float) $r->baixa->desconto)->toBe(0.0);
    expect((float) $r->titulo->valor_aberto)->toBe(50.0);
    expect($r->titulo->status)->not->toBe('quitado');
});

it('UC-DESC-07 — % de contato de OUTRA empresa não vale para a cobrança deste tenant', function () {
    $outra = (int) DB::table('business')->where('id', '!=', $this->bizId)->orderBy('id')->value('id');
    if ($outra === 0) {
        $this->markTestSkipped('Só existe um business no banco de teste.');
    }
    $deOutra = descContato($outra, $this->userId, 7.0);

    expect(DescontoPontualidade::calcular($this->bizId, $deOutra, 100000, new DateTimeImmutable('2031-03-10')))->toBe([]);
    // Controle: na empresa dona do contato, o % vale.
    expect(DescontoPontualidade::calcular($outra, $deOutra, 100000, new DateTimeImmutable('2031-03-10'))['valor_centavos'] ?? null)->toBe(7000);
});
