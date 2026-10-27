<?php
// @covers-us US-RB-013

declare(strict_types=1);
// Cobre UC-RBCC-05..10 (resources/js/Pages/RecurringBilling/Index.casos.md).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Modules\PaymentGateway\Models\PaymentGatewayCredential;
use Modules\RecurringBilling\Models\CartaoSalvo;
use Modules\RecurringBilling\Services\CobradorCartaoRecorrente;
use Modules\Whatsapp\Services\Notify\NotificadorClienteWhatsapp;
use Modules\Whatsapp\Services\Notify\ResultadoNotificacao;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * US-RB-013 · fatura cobrada no cartão salvo, com nova tentativa só na recusa leve.
 *
 * VALOR — dois caminhos sobre a mesma fixture (fatura de 89,90): a tentativa grava
 * `request_json.valor_centavos = 8990`, a linha em `cobrancas` nasce com `valor_centavos = 8990` e
 * o corpo enviado ao Asaas leva `value = 89.9` — os três batem.
 *
 * Intervalos 1, 3, 7 dias (padrão) → até 4 tentativas. Tenant 98 contra 99 (ADR 0358), nunca o 4.
 * Token de cartão sintético (`tok-teste-*`); nenhum número de cartão aqui. HTTP do Asaas é falso.
 * ⚠️ SKIP fora do MySQL: leia assertions (LC-13).
 */
const CC_HOJE = '2031-04-01';

beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'mysql') {
        $this->markTestSkipped('Schema real do RecurringBilling/PaymentGateway (MySQL).');
    }
    $this->b98 = (int) $this->seededTenant()->id;
    $this->b99 = (int) $this->seededSupportClientTenant()->id;
    config(['recurringbilling.cobrar_cartao' => true, 'recurringbilling.cartao_retry_dias' => '1,3,7']);
});

function ccCredencial(int $biz): void
{
    PaymentGatewayCredential::query()->create(['business_id' => $biz, 'gateway_key' => 'asaas', 'ambiente' => 'sandbox',
        'ativo' => true, 'nome_display' => 'Asaas teste '.$biz, 'config_json' => ['api_key' => '$aact_fake']]);
}

/** Assinatura com cartão salvo e fatura de 89,90 vencida em CC_HOJE. Devolve [fatura, contato]. */
function ccFaturaComCartao(int $biz): array
{
    $owner = (int) DB::table('business')->where('id', $biz)->value('owner_id');
    $contato = DB::table('contacts')->insertGetId(['business_id' => $biz, 'type' => 'customer',
        'name' => 'Cliente Cartão '.uniqid(), 'mobile' => '', 'contact_id' => 'CR-'.uniqid(),
        'created_by' => $owner, 'created_at' => now(), 'updated_at' => now()]);
    // SUPERADMIN: fixture grava o cartão do tenant de teste sem sessão.
    $cartao = CartaoSalvo::withoutGlobalScopes()->create(['business_id' => $biz, 'contact_id' => $contato,
        'gateway_key' => 'asaas', 'token' => 'tok-teste-'.uniqid(), 'token_hash' => hash('sha256', uniqid('', true)),
        'ultimos_4' => '8829', 'bandeira' => 'MASTERCARD', 'status' => 'ativo']);
    $sub = DB::table('rb_subscriptions')->insertGetId(['business_id' => $biz, 'plan_id' => null, 'contact_id' => $contato,
        'status' => 'active', 'start_date' => '2031-03-01', 'next_due_date' => '2031-05-01', 'billing_anchor_date' => '2031-05-01',
        'payment_method' => 'card', 'cartao_salvo_id' => $cartao->id, 'metadata' => json_encode(['valor' => '89.90']),
        'created_at' => now(), 'updated_at' => now()]);
    $fatura = DB::table('rb_invoices')->insertGetId(['business_id' => $biz, 'subscription_id' => $sub, 'contact_id' => $contato,
        'numero_documento' => 'CR-'.uniqid(), 'valor' => '89.90', 'status' => 'open', 'vencimento' => CC_HOJE,
        'created_at' => now(), 'updated_at' => now()]);

    return [$fatura, $contato];
}

/** Asaas falso: cliente existe; o POST /payments responde com o status/corpo dado. */
function ccAsaas(int $status, array $corpo): void
{
    Http::fake([
        '*/customers*' => Http::response(['data' => [['id' => 'cus_teste']]], 200),
        '*/payments' => Http::response($corpo, $status),
    ]);
}

function ccTentativas(int $fatura): \Illuminate\Support\Collection
{
    return DB::table('rb_charge_attempts')->where('invoice_id', $fatura)->orderBy('attempt_n')->get();
}

/** Recua o created_at das tentativas: simula que a última foi há `$dias` dias. */
function ccEnvelhecer(int $fatura, int $dias): void
{
    DB::table('rb_charge_attempts')->where('invoice_id', $fatura)
        ->update(['created_at' => \Illuminate\Support\Carbon::parse(CC_HOJE)->subDays($dias)]);
}

it('UC-RBCC-05 · fatura vencida é cobrada no cartão salvo pelo valor dela', function () {
    ccCredencial($this->b98);
    [$fatura] = ccFaturaComCartao($this->b98);
    ccAsaas(200, ['id' => 'pay_ok', 'status' => 'CONFIRMED']);

    $linhas = app(CobradorCartaoRecorrente::class)->cobrar($this->b98, CC_HOJE);

    $t = ccTentativas($fatura);
    expect($t)->toHaveCount(1)
        ->and($t[0]->status)->toBe('succeeded')
        ->and((int) $t[0]->attempt_n)->toBe(1)
        ->and(json_decode($t[0]->request_json, true)['valor_centavos'])->toBe(8990); // caminho 1
    $cob = DB::table('cobrancas')->where('origem_type', 'invoice')->where('origem_id', $fatura)->first();
    expect((int) $cob->valor_centavos)->toBe(8990)->and($cob->tipo)->toBe('card'); // caminho 2
    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/payments') && $r['billingType'] === 'CREDIT_CARD'
        && (float) $r['value'] === 89.9 && str_starts_with((string) $r['creditCardToken'], 'tok-teste-')); // caminho 3
    expect(collect($linhas)->firstWhere('fatura_id', $fatura)['acao'])->toBe('succeeded');

    // Rodar de novo não cobra outra vez.
    app(CobradorCartaoRecorrente::class)->cobrar($this->b98, CC_HOJE);
    expect(ccTentativas($fatura))->toHaveCount(1);
});

it('UC-RBCC-06 · recusa leve espera o intervalo e tenta de novo, até o limite de 4 tentativas', function () {
    ccCredencial($this->b98);
    [$fatura] = ccFaturaComCartao($this->b98);
    ccAsaas(400, ['errors' => [['code' => 'invalid_creditCard', 'description' => 'Transação não autorizada. Saldo insuficiente.']]]);
    $c = app(CobradorCartaoRecorrente::class);

    $c->cobrar($this->b98, CC_HOJE);
    expect(ccTentativas($fatura)->pluck('status')->all())->toBe(['soft_decline'])
        ->and(ccTentativas($fatura)[0]->error_message)->toContain('Saldo insuficiente');
    expect(DB::table('cobrancas')->where('origem_id', $fatura)->where('origem_type', 'invoice')->value('status'))->toBe('erro');

    // No mesmo dia: aguarda (intervalo de 1 dia).
    $l = collect($c->cobrar($this->b98, CC_HOJE))->firstWhere('fatura_id', $fatura);
    expect($l['acao'])->toBe('aguardando')->and(ccTentativas($fatura))->toHaveCount(1);

    foreach ([1, 3, 7] as $dias) { // 2ª, 3ª e 4ª tentativas, cada uma depois do seu intervalo
        ccEnvelhecer($fatura, $dias);
        $c->cobrar($this->b98, CC_HOJE);
    }
    expect(ccTentativas($fatura)->pluck('attempt_n')->map('intval')->all())->toBe([1, 2, 3, 4]);

    ccEnvelhecer($fatura, 30);
    $l = collect($c->cobrar($this->b98, CC_HOJE))->firstWhere('fatura_id', $fatura);
    expect($l['acao'])->toBe('tentativas_esgotadas')->and(ccTentativas($fatura))->toHaveCount(4);
});

it('UC-RBCC-07 · recusa definitiva não tenta de novo e avisa o cliente', function () {
    ccCredencial($this->b98);
    [$fatura, $contato] = ccFaturaComCartao($this->b98);
    ccAsaas(400, ['errors' => [['code' => 'invalid_creditCard', 'description' => 'Cartão vencido.']]]);
    $avisos = [];
    $mock = Mockery::mock(NotificadorClienteWhatsapp::class);
    $mock->shouldReceive('notificar')->andReturnUsing(function (...$a) use (&$avisos) {
        $avisos[] = $a;

        return ResultadoNotificacao::Enviado;
    });
    app()->instance(NotificadorClienteWhatsapp::class, $mock);
    $c = app(CobradorCartaoRecorrente::class);

    $c->cobrar($this->b98, CC_HOJE);
    ccEnvelhecer($fatura, 30);
    $l = collect($c->cobrar($this->b98, CC_HOJE))->firstWhere('fatura_id', $fatura);

    expect(ccTentativas($fatura)->pluck('status')->all())->toBe(['hard_decline'])
        ->and($l['acao'])->toBe('recusa_definitiva')
        ->and($avisos)->toHaveCount(1)
        ->and($avisos[0][0])->toBe($this->b98)
        ->and($avisos[0][1])->toBe($contato)
        ->and($avisos[0][3])->toContain('final 8829');
});

it('UC-RBCC-08 · intervalos configuráveis mudam espera e limite', function () {
    config(['recurringbilling.cartao_retry_dias' => '2']);
    ccCredencial($this->b98);
    [$fatura] = ccFaturaComCartao($this->b98);
    ccAsaas(400, ['errors' => [['description' => 'Transação não autorizada.']]]);
    $c = app(CobradorCartaoRecorrente::class);

    $c->cobrar($this->b98, CC_HOJE);
    ccEnvelhecer($fatura, 1);
    expect(collect($c->cobrar($this->b98, CC_HOJE))->firstWhere('fatura_id', $fatura)['acao'])->toBe('aguardando');
    ccEnvelhecer($fatura, 2);
    $c->cobrar($this->b98, CC_HOJE);
    ccEnvelhecer($fatura, 9);
    expect(collect($c->cobrar($this->b98, CC_HOJE))->firstWhere('fatura_id', $fatura)['acao'])->toBe('tentativas_esgotadas')
        ->and(ccTentativas($fatura))->toHaveCount(2);
});

it('UC-RBCC-09 · --dry-run lista sem gravar nem chamar o gateway; flag desligada não cobra', function () {
    ccCredencial($this->b98);
    [$fatura] = ccFaturaComCartao($this->b98);
    Http::fake();

    Artisan::call('rb:cobrar-cartao', ['--business' => $this->b98, '--data' => CC_HOJE, '--dry-run' => true]);
    expect(Artisan::output())->toContain('8990');

    config(['recurringbilling.cobrar_cartao' => false]);
    Artisan::call('rb:cobrar-cartao', ['--business' => $this->b98, '--data' => CC_HOJE]);

    expect(ccTentativas($fatura))->toHaveCount(0);
    Http::assertNothingSent();
});

it('UC-RBCC-10 · a rodada de um business não cobra a fatura do outro', function () {
    ccCredencial($this->b98);
    ccCredencial($this->b99);
    [$fatura99] = ccFaturaComCartao($this->b99);
    ccAsaas(200, ['id' => 'pay_ok', 'status' => 'CONFIRMED']);

    $linhas = app(CobradorCartaoRecorrente::class)->cobrar($this->b98, CC_HOJE);

    expect(collect($linhas)->pluck('fatura_id'))->not->toContain($fatura99)
        ->and(ccTentativas($fatura99))->toHaveCount(0);
});
