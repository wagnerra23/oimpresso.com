<?php

declare(strict_types=1);

use Carbon\Carbon;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Modules\PaymentGateway\Dto\EmitirCobrancaInput;
use Modules\PaymentGateway\Models\PaymentGatewayCredential;
use Modules\PaymentGateway\Services\JurosMultaCarteira;
use Modules\PaymentGateway\Services\PaymentGatewayService;

uses(Tests\TestCase::class);

/**
 * Sabor Brasil, item 4 do plano de migração — juros e multa da carteira vão ao boleto.
 *
 * Fonte do contrato: `resources/js/Pages/Financeiro/ContasBancarias/Index.casos.md` UC-CTB-12..15
 * + Delphi `CONTAS.JUROS`/`CONTAS.MULTA` (medidos no Firebird do cliente em 2026-10-29).
 * Layout BB: manual `lib-custom/laravel-boleto/manuais/BB/novos/Cnab240Agosto2017.pdf` —
 * segmento P 27.3P código do juros (118), 28.3P data (119-126), 29.3P taxa (127-141, 13+2);
 * segmento R 14.3R código da multa (66), 15.3R data (67-74, "aceita a mesma data do
 * vencimento"), 16.3R percentual (75-89, 13+2).
 *
 * Regra mestre de valor: cada número é conferido por dois caminhos — o literal do arquivo e a
 * conta independente (`sprintf('%015d', pct * 100)`; mora/dia = valor × taxa / 100 / 30).
 * Valores fictícios. Tenant 98 (ADR 0358); tudo numa transação desfeita.
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('Usa o schema MySQL semeado (contacts/cobrancas).');
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

function jmContato(int $biz, int $userId, ?float $descontoPct): int
{
    return (int) DB::table('contacts')->insertGetId([
        'business_id' => $biz, 'type' => 'customer', 'name' => 'ZZ juros-multa '.uniqid(),
        'mobile' => '0', 'created_by' => $userId, 'boleto_desconto_pontualidade_pct' => $descontoPct,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

function jmCredencial(int $biz, string $key, array $config): PaymentGatewayCredential
{
    return PaymentGatewayCredential::query()->create([
        'business_id' => $biz, 'gateway_key' => $key, 'ambiente' => 'sandbox', 'ativo' => true,
        'nome_display' => 'ZZ juros-multa '.$key, 'config_json' => $config,
    ]);
}

function jmBbConfig(array $extra = []): array
{
    return array_merge([
        'agencia' => '1234', 'conta' => '12345678', 'convenio' => '1234567', 'carteira' => '17',
        'variacao_carteira' => '019', 'cedente_nome' => 'Empresa Ficticia',
        'cedente_documento' => '00000000000191', // pii-allowlist — fixture
        'cedente_endereco' => 'Rua Ficticia, 1', 'cedente_cep' => '88000000', 'cedente_uf' => 'SC', 'cedente_cidade' => 'Cidade',
    ], $extra);
}

function jmInput(int $biz, int $contactId): EmitirCobrancaInput
{
    return new EmitirCobrancaInput(
        businessId: $biz, contactId: $contactId, valorCentavos: 123456,
        vencimento: new DateTimeImmutable('2031-03-10'), descricao: 'Teste juros e multa',
        idempotencyKey: 'zz-jm-'.uniqid(),
        meta: [
            'payer_name' => 'Pagador Ficticio', 'payer_cpf_cnpj' => '00000000191', // pii-allowlist — fixture
            'payer_address' => 'Rua Ficticia, 2', 'payer_cep' => '88000000', 'payer_uf' => 'SC', 'payer_city' => 'Cidade',
        ],
    );
}

/** @return array{0: string, 1: string} segmentos P e R da remessa gravada */
function jmSegmentosPR(array $payload): array
{
    $linhas = array_values(array_filter(preg_split('/\r\n|\n/', Storage::disk('local')->get($payload['cnab_remessa_path'])), fn ($l) => $l !== ''));

    return [$linhas[2], $linhas[4]];
}

it('UC-CTB-12 — a carteira lê juros ao mês e multa em %, com 2 casas, e ignora o que não é % válido', function () {
    expect(JurosMultaCarteira::daCarteira(['juros_mes_pct' => 1, 'multa_pct' => '2,00']))->toBe([
        'juros' => ['tipo' => 'taxa_mensal', 'percentual' => 1.0],
        'multa' => ['tipo' => 'percentual', 'percentual' => 2.0],
    ]);
    expect(JurosMultaCarteira::daCarteira(['juros_mes_pct' => 0.333]))->toBe([
        'juros' => ['tipo' => 'taxa_mensal', 'percentual' => 0.33], 'multa' => [],
    ]);
    foreach ([[], ['juros_mes_pct' => 0], ['juros_mes_pct' => -1], ['juros_mes_pct' => 101], ['multa_pct' => 'x']] as $config) {
        expect(JurosMultaCarteira::daCarteira($config))->toBe(['juros' => [], 'multa' => []]);
    }
});

it('UC-CTB-13 — BB: a remessa CNAB 240 leva juros no segmento P e multa no R, e mantém o desconto', function () {
    $contato = jmContato($this->bizId, $this->userId, 5.0);
    $cred = jmCredencial($this->bizId, 'bb_cnab', jmBbConfig(['juros_mes_pct' => 1, 'multa_pct' => 2]));

    $res = (new PaymentGatewayService())->comCredencial($cred)->emitirBoleto(jmInput($this->bizId, $contato));
    [$p, $r] = jmSegmentosPR($res->payloadGateway);

    expect($p[13])->toBe('P');
    expect(substr($p, 117, 1))->toBe('2');                       // 27.3P código 2 = taxa mensal
    expect(substr($p, 118, 8))->toBe('10032031');                // 28.3P data = vencimento
    expect(substr($p, 126, 15))->toBe('000000000000100');        // 29.3P 1,00 % ao mês
    expect(substr($p, 126, 15))->toBe(sprintf('%015d', 1 * 100)); // segundo caminho
    // P-086 intacto: 5% de 1.234,56 = 61,728 → 61,73 até o vencimento.
    expect(substr($p, 141, 1))->toBe('1');
    expect(substr($p, 150, 15))->toBe('000000000006173');

    expect($r[13])->toBe('R');
    expect(substr($r, 65, 1))->toBe('2');                        // 14.3R código 2 = percentual
    expect(substr($r, 66, 8))->toBe('10032031');                 // 15.3R data = vencimento
    expect(substr($r, 74, 15))->toBe('000000000000200');         // 16.3R 2,00 %
    expect(substr($r, 74, 15))->toBe(sprintf('%015d', 2 * 100));
});

it('UC-CTB-14 — BB: carteira sem juros e multa sai isenta, como antes', function () {
    $contato = jmContato($this->bizId, $this->userId, null);
    $cred = jmCredencial($this->bizId, 'bb_cnab', jmBbConfig());

    $res = (new PaymentGatewayService())->comCredencial($cred)->emitirBoleto(jmInput($this->bizId, $contato));
    [$p, $r] = jmSegmentosPR($res->payloadGateway);

    expect(substr($p, 117, 1))->toBe('3');                       // isento
    expect(substr($p, 126, 15))->toBe('000000000000000');
    expect(substr($r, 65, 1))->toBe('0');
    expect(substr($r, 74, 15))->toBe('000000000000000');
});

it('UC-CTB-15 — Inter: a API v3 recebe mora ao mês e multa em %, junto do desconto', function () {
    $contato = jmContato($this->bizId, $this->userId, 5.0);
    $cred = jmCredencial($this->bizId, 'inter', ['client_id' => 'fake', 'client_secret' => 'fake', 'juros_mes_pct' => 1, 'multa_pct' => 2]);
    $enviado = null;
    Http::fake(function (HttpRequest $req) use (&$enviado) {
        if (str_contains($req->url(), '/oauth/')) {
            return Http::response(['access_token' => 'fake', 'expires_in' => 3600], 200);
        }
        $enviado = $req->data();

        return Http::response(['nossoNumero' => '00000000001'], 201);
    });

    (new PaymentGatewayService())->comCredencial($cred)->emitirBoleto(jmInput($this->bizId, $contato));

    expect($enviado['mora'] ?? null)->toBe(['codigo' => 'TAXAMENSAL', 'taxa' => 1.0]);
    expect($enviado['multa'] ?? null)->toBe(['codigo' => 'PERCENTUAL', 'taxa' => 2.0]);
    expect($enviado['desconto']['valor'] ?? null)->toBe(61.73);

    // Segundo caminho, o que o pagador paga num atraso de 10 dias sobre 1.234,56:
    // multa 2% = 24,69 · mora 1% a.m. = 0,41/dia × 10 = 4,12 (conta do banco, não nossa).
    expect(round(1234.56 * 2 / 100, 2))->toBe(24.69);
    expect(round(1234.56 * 1 / 100 / 30 * 10, 2))->toBe(4.12);
});
