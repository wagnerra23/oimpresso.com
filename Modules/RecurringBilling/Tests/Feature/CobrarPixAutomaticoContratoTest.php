<?php
// @covers-us US-RB-021

declare(strict_types=1);
// Cobre UC-RBPA-01..06 e UC-RBPA-15 (resources/js/Pages/RecurringBilling/Index.casos.md).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Modules\PaymentGateway\Models\PaymentGatewayCredential;
use Modules\PaymentGateway\Services\Drivers\BcbPixDriver;
use Modules\RecurringBilling\Services\CobradorPixAutomatico;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Motor de cobrança da assinatura pelo Pix Automático do BCB (US-RB-021 · decisão [W] 2026-10-26).
 *
 * VALOR — dois caminhos sobre a mesma fixture (plano mensal de 106,07): o valor da FATURA gerada
 * (`rb_invoices.valor` = "106.07"), a linha em `cobrancas` (`valor_centavos` = 10607) e o corpo
 * enviado ao banco (`valor.original` = "106.07") têm de bater.
 *
 * Tenant 98 contra o 99 (ADR 0358), nunca o 4. "Hoje" fixo em 2031-03-20; janela do BCB = de
 * 22/03 a 30/03. HTTP do banco é falso. ⚠️ SKIP fora do MySQL: leia assertions (LC-13).
 */
const PA_HOJE = '2031-03-20';

beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'mysql') {
        $this->markTestSkipped('Schema real do RecurringBilling/PaymentGateway (MySQL).');
    }
    $this->b98 = (int) $this->seededTenant()->id;
    $this->b99 = (int) $this->seededSupportClientTenant()->id;
    config(['recurringbilling.cobrar_pix_automatico' => true]);
});

function paCredencial(int $biz): PaymentGatewayCredential
{
    return PaymentGatewayCredential::query()->create([
        'business_id' => $biz, 'gateway_key' => 'bcb_pix', 'ambiente' => 'sandbox', 'ativo' => true,
        'nome_display' => 'BCB teste '.$biz,
        'config_json' => ['client_id' => 'c', 'client_secret' => 's', 'base_url' => 'https://psp.fake.local',
            'recebedor_agencia' => '9708', 'recebedor_conta' => '012682'],
    ]);
}

/** Assinatura mensal de 106,07 vencendo daqui a `$dias`, com recorrência no status dado. */
function paAssinatura(int $biz, int $dias, ?string $statusRec = 'APROVADA'): int
{
    $owner = (int) DB::table('business')->where('id', $biz)->value('owner_id');
    $contato = DB::table('contacts')->insertGetId(['business_id' => $biz, 'type' => 'customer',
        'name' => 'Cliente PA '.uniqid(), 'mobile' => '', 'contact_id' => 'PA-'.uniqid(),
        'created_by' => $owner, 'created_at' => now(), 'updated_at' => now()]);
    $plano = DB::table('rb_plans')->insertGetId(['business_id' => $biz, 'name' => 'Plano PA '.uniqid(),
        'slug' => 'pa-'.uniqid(), 'valor' => '106.07', 'ciclo' => 'monthly', 'created_at' => now(), 'updated_at' => now()]);
    $venc = \Illuminate\Support\Carbon::parse(PA_HOJE)->addDays($dias)->toDateString();
    $meta = $statusRec ? ['pix_automatico' => ['status' => $statusRec, 'id_rec' => 'RR'.uniqid()]] : [];

    return (int) DB::table('rb_subscriptions')->insertGetId(['business_id' => $biz, 'plan_id' => $plano,
        'contact_id' => $contato, 'status' => 'active', 'start_date' => PA_HOJE, 'next_due_date' => $venc,
        'billing_anchor_date' => $venc, 'metadata' => json_encode($meta), 'created_at' => now(), 'updated_at' => now()]);
}

function paBancoOk(): void
{
    Http::fake([
        '*/oauth/token' => Http::response(['access_token' => 'tk'], 200),
        '*/v2/cobr/*' => Http::response(['status' => 'CRIADA'], 201),
    ]);
}

function paCobrancas(int $sub): \Illuminate\Support\Collection
{
    $faturas = DB::table('rb_invoices')->where('subscription_id', $sub)->pluck('id');

    return DB::table('cobrancas')->where('origem_type', 'invoice')->whereIn('origem_id', $faturas)->orderBy('id')->get();
}

it('UC-RBPA-01 · assinatura com recorrência aprovada vencendo em 5 dias: gera a fatura e cria a cobrança do ciclo com o valor dela', function () {
    paCredencial($this->b98);
    $sub = paAssinatura($this->b98, 5);
    paBancoOk();

    app(CobradorPixAutomatico::class)->cobrar($this->b98, PA_HOJE);

    $fatura = DB::table('rb_invoices')->where('subscription_id', $sub)->first();
    expect($fatura)->not->toBeNull()
        ->and((string) $fatura->valor)->toBe('106.07')                // caminho 1: a fatura
        ->and(substr((string) $fatura->vencimento, 0, 10))->toBe('2031-03-25');
    $cob = paCobrancas($sub);
    expect($cob)->toHaveCount(1)
        ->and((int) $cob[0]->valor_centavos)->toBe(10607)              // caminho 2: a cobrança
        ->and($cob[0]->tipo)->toBe('pix_recv')
        ->and($cob[0]->status)->toBe('emitida')
        ->and((int) $cob[0]->business_id)->toBe($this->b98);
    $idRec = json_decode((string) DB::table('rb_subscriptions')->where('id', $sub)->value('metadata'), true)['pix_automatico']['id_rec'];
    Http::assertSent(fn ($r) => $r->method() === 'PUT'                 // caminho 3: o que vai ao banco
        && str_ends_with($r->url(), '/v2/cobr/'.BcbPixDriver::txidDe($cob[0]->idempotency_key))
        && $r['valor']['original'] === '106.07'
        && $r['idRec'] === $idRec
        && $r['calendario']['dataDeVencimento'] === '2031-03-25');
});

it('UC-RBPA-02 · fora da janela de 10 a 2 dias, ou sem recorrência aprovada, não cobra', function () {
    paCredencial($this->b98);
    $amanha = paAssinatura($this->b98, 1);
    $longe = paAssinatura($this->b98, 11);
    $criada = paAssinatura($this->b98, 5, 'CRIADA');
    $semMandato = paAssinatura($this->b98, 5, null);
    // Discriminante: as duas sem recorrência aprovada JÁ têm fatura aberta dentro da janela (gerada
    // por outro caminho). Sem a checagem do mandato no cobrador, elas seriam cobradas.
    foreach ([$criada, $semMandato] as $s) {
        $sub = DB::table('rb_subscriptions')->where('id', $s)->first();
        DB::table('rb_invoices')->insert(['business_id' => $this->b98, 'subscription_id' => $s, 'contact_id' => $sub->contact_id,
            'numero_documento' => 'PA-'.uniqid(), 'valor' => '106.07', 'status' => 'open', 'vencimento' => '2031-03-25',
            'created_at' => now(), 'updated_at' => now()]);
    }
    paBancoOk();

    app(CobradorPixAutomatico::class)->cobrar($this->b98, PA_HOJE);

    foreach ([$amanha, $longe, $criada, $semMandato] as $sub) {
        expect(paCobrancas($sub))->toHaveCount(0);
    }
    Http::assertNotSent(fn ($r) => str_contains($r->url(), '/v2/cobr/'));
});

it('UC-RBPA-03 · rodar duas vezes no mesmo dia não cobra duas vezes', function () {
    paCredencial($this->b98);
    $sub = paAssinatura($this->b98, 5);
    paBancoOk();

    $l1 = app(CobradorPixAutomatico::class)->cobrar($this->b98, PA_HOJE);
    $l2 = app(CobradorPixAutomatico::class)->cobrar($this->b98, PA_HOJE);

    expect(paCobrancas($sub))->toHaveCount(1)
        ->and(collect($l2)->firstWhere('fatura_id', $l1[0]['fatura_id'])['acao'])->toBe('ja_cobrada');
    Http::assertSentCount(2); // 1 token + 1 cobrança
});

it('UC-RBPA-04 · erro do banco marca a cobrança como erro e a rodada seguinte tenta de novo com o MESMO txid', function () {
    paCredencial($this->b98);
    $sub = paAssinatura($this->b98, 5);
    Http::fake([
        '*/oauth/token' => Http::response(['access_token' => 'tk'], 200),
        '*/v2/cobr/*' => Http::sequence()->push(['erro' => 'falha no PSP'], 500)->push(['status' => 'CRIADA'], 201),
    ]);
    // 500 não tem retry no HttpClientFactory (só 429/502/503/504): uma resposta por rodada.

    app(CobradorPixAutomatico::class)->cobrar($this->b98, PA_HOJE);
    app(CobradorPixAutomatico::class)->cobrar($this->b98, PA_HOJE);

    $cob = paCobrancas($sub);
    $fatura = (int) $cob[0]->origem_id;
    expect($cob->pluck('status')->all())->toBe(['erro', 'emitida'])
        ->and($cob[1]->idempotency_key)->toBe("rb-cobr-{$fatura}-1")             // a chave do ciclo
        ->and($cob[0]->idempotency_key)->toBe("rb-cobr-{$fatura}-1#erro{$cob[0]->id}"); // a linha com erro fica, com a chave renomeada
    $puts = collect(Http::recorded())->map(fn ($par) => $par[0])->filter(fn ($r) => $r->method() === 'PUT')->map(fn ($r) => $r->url())->values();
    expect($puts)->toHaveCount(2)
        ->and($puts->unique())->toHaveCount(1)
        ->and($puts[0])->toEndWith('/v2/cobr/'.BcbPixDriver::txidDe("rb-cobr-{$fatura}-1"));
});

it('UC-RBPA-04 · d8: timeout depois de o banco aceitar não abre segunda cobrança — a retentativa repete o txid', function () {
    // O banco aceitou a 1ª (a resposta se perdeu: aqui, 500) e recusa a 2ª por txid repetido.
    // Com txid novo na retentativa (comportamento até 2026-11-19), a 2ª chamada criaria outra
    // cobrança do mesmo ciclo no banco: o pagador pagaria duas vezes.
    paCredencial($this->b98);
    $sub = paAssinatura($this->b98, 5);
    Http::fake([
        '*/oauth/token' => Http::response(['access_token' => 'tk'], 200),
        '*/v2/cobr/*' => Http::sequence()->push(['erro' => 'timeout'], 500)->push(['title' => 'txid em uso'], 409),
    ]);

    app(CobradorPixAutomatico::class)->cobrar($this->b98, PA_HOJE);
    app(CobradorPixAutomatico::class)->cobrar($this->b98, PA_HOJE);

    $txids = collect(Http::recorded())->map(fn ($par) => $par[0])
        ->filter(fn ($r) => $r->method() === 'PUT')
        ->map(fn ($r) => substr($r->url(), strrpos($r->url(), '/') + 1))->values();
    expect($txids)->toHaveCount(2)                       // as duas rodadas chegaram ao banco
        ->and($txids->unique())->toHaveCount(1)           // com UM txid só
        ->and(paCobrancas($sub)->pluck('status')->all())->toBe(['erro', 'erro']);
});

it('UC-RBPA-04 · cobrança cancelada encerra a tentativa: a seguinte usa chave e txid novos', function () {
    paCredencial($this->b98);
    $sub = paAssinatura($this->b98, 5);
    paBancoOk();

    app(CobradorPixAutomatico::class)->cobrar($this->b98, PA_HOJE);
    $primeira = paCobrancas($sub)->first();
    DB::table('cobrancas')->where('id', $primeira->id)->update(['status' => 'cancelada']);
    app(CobradorPixAutomatico::class)->cobrar($this->b98, PA_HOJE);

    $cob = paCobrancas($sub);
    $fatura = (int) $primeira->origem_id;
    expect($cob->pluck('status')->all())->toBe(['cancelada', 'emitida'])
        ->and($cob[0]->idempotency_key)->toBe("rb-cobr-{$fatura}-1")
        ->and($cob[1]->idempotency_key)->toBe("rb-cobr-{$fatura}-2");
});

it('UC-RBPA-05 · dry-run lista o que seria cobrado e não grava nem chama o banco', function () {
    paCredencial($this->b98);
    $sub = paAssinatura($this->b98, 5);
    Http::fake();

    expect(Artisan::call('rb:cobrar-pix-automatico', ['--business' => $this->b98, '--data' => PA_HOJE, '--dry-run' => true]))->toBe(0);

    expect(DB::table('rb_invoices')->where('subscription_id', $sub)->count())->toBe(0)
        ->and(paCobrancas($sub))->toHaveCount(0);
    expect(Artisan::output())->toContain('1 fatura(s) ainda a gerar');
    Http::assertNothingSent();
});

it('UC-RBPA-06 · Tier 0: cobrar a 98 não toca assinatura nem credencial da 99; flag desligada não cobra ninguém', function () {
    paCredencial($this->b98);
    paCredencial($this->b99);
    $sub98 = paAssinatura($this->b98, 5);
    $sub99 = paAssinatura($this->b99, 5);
    paBancoOk();

    app(CobradorPixAutomatico::class)->cobrar($this->b98, PA_HOJE);

    expect(paCobrancas($sub98))->toHaveCount(1)
        ->and(paCobrancas($sub99))->toHaveCount(0)
        ->and(DB::table('rb_invoices')->where('subscription_id', $sub99)->count())->toBe(0);

    config(['recurringbilling.cobrar_pix_automatico' => false]);
    expect(Artisan::call('rb:cobrar-pix-automatico', ['--business' => $this->b99, '--data' => PA_HOJE]))->toBe(0);
    expect(paCobrancas($sub99))->toHaveCount(0);
});

it('UC-RBPA-15 · a4: o txid da cobrança vai para a fatura, e o Pix do Inter com esse txid quita a fatura certa', function () {
    \Illuminate\Support\Facades\Event::fake([\Modules\RecurringBilling\Events\InvoicePaid::class]);
    paCredencial($this->b98);
    $sub = paAssinatura($this->b98, 5);
    $outra = paAssinatura($this->b98, 6); // vizinha da mesma empresa: não pode ser tocada
    paBancoOk();

    app(CobradorPixAutomatico::class)->cobrar($this->b98, PA_HOJE);

    $cob = paCobrancas($sub);
    $txid = BcbPixDriver::txidDe($cob[0]->idempotency_key);
    $fatura = DB::table('rb_invoices')->where('subscription_id', $sub)->first();
    expect((string) $fatura->gateway_ref)->toBe($txid)                      // caminho 1: a fatura
        ->and((string) $cob[0]->gateway_external_id)->toBe($txid)          // caminho 2: a cobrança
        ->and($fatura->gateway)->toBeNull(); // cancelar segue local: o driver de boleto não conhece txid

    (new \Modules\RecurringBilling\Jobs\ProcessInterWebhookJob($this->b98, 'E2E'.uniqid(), [
        'txid' => $txid, 'valor' => '106.07', 'horario' => '2031-03-24T10:00:00-03:00',
    ]))->handle();

    expect(DB::table('rb_invoices')->where('id', $fatura->id)->value('status'))->toBe('paid')
        ->and(DB::table('rb_invoices')->where('subscription_id', $outra)->value('status'))->toBe('open');
    \Illuminate\Support\Facades\Event::assertDispatchedTimes(\Modules\RecurringBilling\Events\InvoicePaid::class, 1);
    \Illuminate\Support\Facades\Event::assertDispatched(\Modules\RecurringBilling\Events\InvoicePaid::class,
        fn ($e) => $e->businessId === $this->b98 && $e->invoiceRef === (string) $fatura->numero_documento);
});
