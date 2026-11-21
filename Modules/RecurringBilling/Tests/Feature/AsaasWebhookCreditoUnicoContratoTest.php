<?php

declare(strict_types=1);

/*
 * Contrato — o webhook Asaas credita UMA vez e só quando a fatura é quitada.
 *
 * @covers-us US-RB-041
 *
 * UC (resources/js/Pages/RecurringBilling/Configuracoes/Index.casos.md · CU-RB-07 do SDD §6.1):
 *   UC-RBCFG-10 — pagamento credita uma vez só, só na transição para paga e só se cobre a fatura [V0]
 *
 * Auditoria RecurringBilling 2026-11-19, achado a3: PAYMENT_CONFIRMED e PAYMENT_RECEIVED (ids
 * diferentes, cartão manda os dois) caíam no mesmo ramo e CADA UM lançava crédito em
 * account_transactions, somava no saldo_cached e disparava InvoicePaid. O retry fazia o mesmo.
 * Valor pago menor que a fatura quitava igual, e o crédito saía com created_by=1 fixo.
 *
 * VALOR — dupla prova em cada caso: (1) a soma dos créditos em account_transactions e (2) a
 * variação do saldo_cached da conta Asaas. Os dois caminhos têm de dar o mesmo número.
 *
 * Schema sintético sqlite (idioma do AsaasWebhookIdempotencyTest). Tenant 98, adversário 99
 * (ADR 0358). Nunca biz=4.
 */

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Modules\RecurringBilling\Events\InvoicePaid;
use Modules\RecurringBilling\Jobs\ProcessAsaasWebhookJob;

uses(Tests\TestCase::class);

const RB_CU_BIZ = 98;
const RB_CU_OUTRA = 99;
const RB_CU_DONO_98 = 9801;

beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        $this->markTestSkipped('Schema sintético — lane sqlite.');
    }

    foreach (['rb_invoices', 'fin_contas_bancarias', 'account_transactions', 'pg_webhook_events', 'business'] as $t) {
        Schema::dropIfExists($t);
    }
    Schema::create('business', function ($t) {
        $t->increments('id');
        $t->unsignedInteger('owner_id')->nullable();
    });
    Schema::create('rb_invoices', function ($t) {
        $t->id();
        $t->unsignedInteger('business_id');
        $t->unsignedInteger('contact_id')->nullable();
        $t->string('numero_documento', 50);
        $t->decimal('valor', 15, 2);
        $t->string('status')->default('open');
        $t->date('vencimento')->nullable();
        $t->dateTime('pago_em')->nullable();
        $t->timestamps();
        $t->softDeletes();
        $t->unique(['business_id', 'numero_documento']);
    });
    Schema::create('fin_contas_bancarias', function ($t) {
        $t->id();
        $t->unsignedInteger('business_id');
        $t->unsignedInteger('account_id')->nullable();
        $t->string('banco_codigo', 10);
        $t->decimal('saldo_cached', 15, 2)->nullable();
        $t->dateTime('saldo_atualizado_em')->nullable();
        $t->timestamps();
        $t->softDeletes();
    });
    Schema::create('account_transactions', function ($t) {
        $t->id();
        $t->unsignedInteger('account_id');
        $t->string('type');
        $t->string('sub_type')->nullable();
        $t->decimal('amount', 22, 4);
        $t->text('note')->nullable();
        $t->dateTime('transaction_date');
        $t->integer('created_by');
        $t->timestamps();
    });
    Schema::create('pg_webhook_events', function ($t) {
        $t->id();
        $t->unsignedInteger('business_id');
        $t->string('provider', 30);
        $t->string('event_id', 100);
        $t->boolean('processed')->default(false);
        $t->timestamps();
    });

    DB::table('business')->insert([
        ['id' => RB_CU_BIZ, 'owner_id' => RB_CU_DONO_98],
        ['id' => RB_CU_OUTRA, 'owner_id' => 9901],
    ]);
    // Conta Asaas de cada empresa, saldo inicial 1000.00.
    foreach ([[RB_CU_BIZ, 5098], [RB_CU_OUTRA, 5099]] as [$biz, $account]) {
        DB::table('fin_contas_bancarias')->insert([
            'business_id' => $biz, 'account_id' => $account, 'banco_codigo' => '274',
            'saldo_cached' => '1000.00', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    Event::fake([InvoicePaid::class]);
});

afterEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        foreach (['rb_invoices', 'fin_contas_bancarias', 'account_transactions', 'pg_webhook_events', 'business'] as $t) {
            Schema::dropIfExists($t);
        }
    }
});

function rbCuFatura(int $biz, string $numero, string $valor, string $status = 'open'): int
{
    return DB::table('rb_invoices')->insertGetId([
        'business_id' => $biz, 'numero_documento' => $numero, 'valor' => $valor,
        'status' => $status, 'vencimento' => '2026-11-20', 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function rbCuWebhook(int $biz, string $eventId, string $event, string $ref, float $valor): void
{
    (new ProcessAsaasWebhookJob($biz, $eventId, $event, [
        'id' => 'pay_' . $ref, 'value' => $valor, 'externalReference' => $ref, 'paymentDate' => '2026-11-19',
    ]))->handle();
}

/** Caminho 1: soma dos créditos lançados na conta Asaas da empresa, em centavos. */
function rbCuCreditosCentavos(int $accountId): int
{
    return (int) round(((float) DB::table('account_transactions')->where('account_id', $accountId)->sum('amount')) * 100);
}

/** Caminho 2: variação do saldo_cached da conta Asaas desde 1000.00, em centavos. */
function rbCuDeltaSaldoCentavos(int $biz): int
{
    $saldo = (float) DB::table('fin_contas_bancarias')->where('business_id', $biz)->value('saldo_cached');

    return (int) round($saldo * 100) - 100000;
}

it('UC-RBCFG-10 · CONFIRMED + RECEIVED + retry da mesma fatura creditam 150,00 uma vez só', function () {
    $id = rbCuFatura(RB_CU_BIZ, 'INV-98-0001', '150.00');

    rbCuWebhook(RB_CU_BIZ, 'evt_conf', 'PAYMENT_CONFIRMED', 'INV-98-0001', 150.00);
    rbCuWebhook(RB_CU_BIZ, 'evt_rec', 'PAYMENT_RECEIVED', 'INV-98-0001', 150.00);
    rbCuWebhook(RB_CU_BIZ, 'evt_conf', 'PAYMENT_CONFIRMED', 'INV-98-0001', 150.00); // retry

    expect(DB::table('rb_invoices')->where('id', $id)->value('status'))->toBe('paid')
        ->and(DB::table('rb_invoices')->where('id', $id)->value('pago_em'))->not->toBeNull()
        ->and(DB::table('account_transactions')->where('account_id', 5098)->count())->toBe(1)
        ->and(rbCuCreditosCentavos(5098))->toBe(15000)   // caminho 1
        ->and(rbCuDeltaSaldoCentavos(RB_CU_BIZ))->toBe(15000); // caminho 2

    Event::assertDispatchedTimes(InvoicePaid::class, 1);
    Event::assertDispatched(InvoicePaid::class, fn (InvoicePaid $e) => $e->businessId === RB_CU_BIZ
        && $e->invoiceRef === 'INV-98-0001' && abs($e->valor - 150.00) < 0.001);
});

it('UC-RBCFG-10 · pagamento de 120,00 numa fatura de 150,00 não quita nem credita', function () {
    $id = rbCuFatura(RB_CU_BIZ, 'INV-98-0002', '150.00');

    rbCuWebhook(RB_CU_BIZ, 'evt_parcial', 'PAYMENT_RECEIVED', 'INV-98-0002', 120.00);

    expect(DB::table('rb_invoices')->where('id', $id)->value('status'))->toBe('open')
        ->and(rbCuCreditosCentavos(5098))->toBe(0)
        ->and(rbCuDeltaSaldoCentavos(RB_CU_BIZ))->toBe(0);
    Event::assertNotDispatched(InvoicePaid::class);
});

it('UC-RBCFG-10 · controle positivo do limite: 150,00 exatos numa fatura de 150,00 quita', function () {
    $id = rbCuFatura(RB_CU_BIZ, 'INV-98-0003', '150.00');

    rbCuWebhook(RB_CU_BIZ, 'evt_exato', 'PAYMENT_RECEIVED', 'INV-98-0003', 150.00);

    expect(DB::table('rb_invoices')->where('id', $id)->value('status'))->toBe('paid')
        ->and(rbCuCreditosCentavos(5098))->toBe(15000)
        ->and(rbCuDeltaSaldoCentavos(RB_CU_BIZ))->toBe(15000);
});

it('UC-RBCFG-10 · o crédito sai com o dono da empresa, não com o usuário 1', function () {
    rbCuFatura(RB_CU_BIZ, 'INV-98-0004', '80.00');

    rbCuWebhook(RB_CU_BIZ, 'evt_dono', 'PAYMENT_RECEIVED', 'INV-98-0004', 80.00);

    expect((int) DB::table('account_transactions')->where('account_id', 5098)->value('created_by'))
        ->toBe(RB_CU_DONO_98)
        ->not->toBe(1);
});

it('UC-RBCFG-10 · fatura cancelada não volta a paga e não credita', function () {
    $id = rbCuFatura(RB_CU_BIZ, 'INV-98-0005', '150.00', 'canceled');

    rbCuWebhook(RB_CU_BIZ, 'evt_canc', 'PAYMENT_RECEIVED', 'INV-98-0005', 150.00);

    expect(DB::table('rb_invoices')->where('id', $id)->value('status'))->toBe('canceled')
        ->and(rbCuCreditosCentavos(5098))->toBe(0);
    Event::assertNotDispatched(InvoicePaid::class);
});

it('UC-RBCFG-10 · webhook da 98 com número de fatura da 99 não mexe na 99', function () {
    $da99 = rbCuFatura(RB_CU_OUTRA, 'INV-99-0001', '150.00');

    rbCuWebhook(RB_CU_BIZ, 'evt_cruzado', 'PAYMENT_RECEIVED', 'INV-99-0001', 150.00);

    expect(DB::table('rb_invoices')->where('id', $da99)->value('status'))->toBe('open')
        ->and(rbCuCreditosCentavos(5098))->toBe(0)
        ->and(rbCuCreditosCentavos(5099))->toBe(0)
        ->and(rbCuDeltaSaldoCentavos(RB_CU_OUTRA))->toBe(0);
    Event::assertNotDispatched(InvoicePaid::class);
});

it('UC-RBCFG-10 · pagamento sem externalReference não credita nada', function () {
    (new ProcessAsaasWebhookJob(RB_CU_BIZ, 'evt_sem_ref', 'PAYMENT_RECEIVED', [
        'id' => 'pay_avulso', 'value' => 99.90, 'paymentDate' => '2026-11-19',
    ]))->handle();

    expect(rbCuCreditosCentavos(5098))->toBe(0)
        ->and(rbCuDeltaSaldoCentavos(RB_CU_BIZ))->toBe(0);
});
