<?php

declare(strict_types=1);

/*
 * Contrato — o webhook Pix do Inter acha a fatura pelo txid, credita UMA vez e só quando quita.
 *
 * @covers-us US-RB-047
 *
 * UC (resources/js/Pages/RecurringBilling/Configuracoes/Index.casos.md · CU-RB-07 do SDD §6.1):
 *   UC-RBCFG-11 — Pix do Inter quita a fatura do txid, credita uma vez e só se cobre a fatura [V0]
 *
 * Auditoria RecurringBilling 2026-11-19, achado a4: `rb_invoices.gateway_ref` não tinha escritor
 * em produção (o webhook nunca achava fatura) e o `InvoicePaid` saía com o txid, enquanto a NF-e
 * procura por `numero_documento`. Além disso cada Pix (e cada retry) lançava crédito e somava no
 * saldo, sem comparar com o valor da fatura — o mesmo defeito que o #11223 tirou do Asaas.
 *
 * VALOR — dupla prova em cada caso: (1) a soma dos créditos em account_transactions e (2) a
 * variação do saldo_cached da conta Inter. Os dois caminhos têm de dar o mesmo número.
 *
 * Schema sintético sqlite (idioma do AsaasWebhookCreditoUnicoContratoTest). Tenant 98,
 * adversário 99 (ADR 0358). Nunca biz=4.
 */

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Modules\RecurringBilling\Events\InvoicePaid;
use Modules\RecurringBilling\Jobs\ProcessInterWebhookJob;

uses(Tests\TestCase::class);

const RB_IN_BIZ = 98;
const RB_IN_OUTRA = 99;
const RB_IN_DONO_98 = 9801;
const RB_IN_TABELAS = ['rb_invoices', 'fin_contas_bancarias', 'account_transactions', 'pg_webhook_events', 'business'];

beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        $this->markTestSkipped('Schema sintético — lane sqlite.');
    }

    foreach (RB_IN_TABELAS as $t) {
        Schema::dropIfExists($t);
    }
    Schema::create('business', function ($t) {
        $t->increments('id');
        $t->unsignedInteger('owner_id')->nullable();
    });
    Schema::create('rb_invoices', function ($t) {
        $t->id();
        $t->unsignedInteger('business_id');
        $t->string('numero_documento', 50);
        $t->decimal('valor', 15, 2);
        $t->string('status')->default('open');
        $t->string('gateway', 30)->nullable();
        $t->string('gateway_ref', 100)->nullable();
        $t->dateTime('pago_em')->nullable();
        $t->timestamps();
        $t->softDeletes();
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
        ['id' => RB_IN_BIZ, 'owner_id' => RB_IN_DONO_98],
        ['id' => RB_IN_OUTRA, 'owner_id' => 9901],
    ]);
    // Conta Inter (077) de cada empresa, saldo inicial 1000.00.
    foreach ([[RB_IN_BIZ, 6098], [RB_IN_OUTRA, 6099]] as [$biz, $account]) {
        DB::table('fin_contas_bancarias')->insert([
            'business_id' => $biz, 'account_id' => $account, 'banco_codigo' => '077',
            'saldo_cached' => '1000.00', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    Event::fake([InvoicePaid::class]);
});

afterEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        foreach (RB_IN_TABELAS as $t) {
            Schema::dropIfExists($t);
        }
    }
});

function rbInFatura(int $biz, string $numero, string $valor, ?string $txid, string $status = 'open'): int
{
    return DB::table('rb_invoices')->insertGetId([
        'business_id' => $biz, 'numero_documento' => $numero, 'valor' => $valor, 'status' => $status,
        'gateway_ref' => $txid, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function rbInPix(int $biz, string $e2e, ?string $txid, string $valor): string
{
    $pix = ['endToEndId' => $e2e, 'valor' => $valor, 'horario' => '2026-11-19T10:00:00-03:00'];
    if ($txid !== null) {
        $pix['txid'] = $txid;
    }
    $job = new ProcessInterWebhookJob($biz, $e2e, $pix);
    $job->handle();

    return 'ok';
}

/** Caminho 1: soma dos créditos lançados na conta Inter, em centavos. */
function rbInCreditosCentavos(int $accountId): int
{
    return (int) round(((float) DB::table('account_transactions')->where('account_id', $accountId)->sum('amount')) * 100);
}

/** Caminho 2: variação do saldo_cached da conta Inter desde 1000.00, em centavos. */
function rbInDeltaSaldoCentavos(int $biz): int
{
    $saldo = (float) DB::table('fin_contas_bancarias')->where('business_id', $biz)->value('saldo_cached');

    return (int) round($saldo * 100) - 100000;
}

it('UC-RBCFG-11 · Pix do txid quita a fatura e avisa a NF-e pelo numero_documento; retry não credita de novo', function () {
    $id = rbInFatura(RB_IN_BIZ, 'RB-98-2026-11', '150.00', 'TXID98AAAAAAAAAAAAAAAAAAAAA');

    rbInPix(RB_IN_BIZ, 'E2E-1', 'TXID98AAAAAAAAAAAAAAAAAAAAA', '150.00');
    rbInPix(RB_IN_BIZ, 'E2E-1', 'TXID98AAAAAAAAAAAAAAAAAAAAA', '150.00'); // retry
    rbInPix(RB_IN_BIZ, 'E2E-2', 'TXID98AAAAAAAAAAAAAAAAAAAAA', '150.00'); // reentrega com outro e2e

    expect(DB::table('rb_invoices')->where('id', $id)->value('status'))->toBe('paid')
        ->and(DB::table('rb_invoices')->where('id', $id)->value('pago_em'))->not->toBeNull()
        ->and(DB::table('account_transactions')->where('account_id', 6098)->count())->toBe(1)
        ->and(rbInCreditosCentavos(6098))->toBe(15000)      // caminho 1
        ->and(rbInDeltaSaldoCentavos(RB_IN_BIZ))->toBe(15000); // caminho 2

    Event::assertDispatchedTimes(InvoicePaid::class, 1);
    Event::assertDispatched(InvoicePaid::class, fn (InvoicePaid $e) => $e->businessId === RB_IN_BIZ
        && $e->invoiceRef === 'RB-98-2026-11' && abs($e->valor - 150.00) < 0.001);
});

it('UC-RBCFG-11 · Pix de 149,99 numa fatura de 150,00 não quita nem credita', function () {
    $id = rbInFatura(RB_IN_BIZ, 'RB-98-2026-12', '150.00', 'TXID98BBBBBBBBBBBBBBBBBBBBB');

    rbInPix(RB_IN_BIZ, 'E2E-3', 'TXID98BBBBBBBBBBBBBBBBBBBBB', '149.99');

    expect(DB::table('rb_invoices')->where('id', $id)->value('status'))->toBe('open')
        ->and(rbInCreditosCentavos(6098))->toBe(0)
        ->and(rbInDeltaSaldoCentavos(RB_IN_BIZ))->toBe(0);
    Event::assertNotDispatched(InvoicePaid::class);
});

it('UC-RBCFG-11 · controle positivo do limite: 150,00 exatos quitam, e o crédito sai com o dono da empresa', function () {
    rbInFatura(RB_IN_BIZ, 'RB-98-2027-01', '150.00', 'TXID98CCCCCCCCCCCCCCCCCCCCC');

    rbInPix(RB_IN_BIZ, 'E2E-4', 'TXID98CCCCCCCCCCCCCCCCCCCCC', '150.00');

    expect(rbInCreditosCentavos(6098))->toBe(15000)
        ->and(rbInDeltaSaldoCentavos(RB_IN_BIZ))->toBe(15000)
        ->and((int) DB::table('account_transactions')->where('account_id', 6098)->value('created_by'))->toBe(RB_IN_DONO_98);
});

it('UC-RBCFG-11 · fatura cancelada não volta a paga e não credita', function () {
    $id = rbInFatura(RB_IN_BIZ, 'RB-98-2027-02', '150.00', 'TXID98DDDDDDDDDDDDDDDDDDDDD', 'canceled');

    rbInPix(RB_IN_BIZ, 'E2E-5', 'TXID98DDDDDDDDDDDDDDDDDDDDD', '150.00');

    expect(DB::table('rb_invoices')->where('id', $id)->value('status'))->toBe('canceled')
        ->and(rbInCreditosCentavos(6098))->toBe(0);
    Event::assertNotDispatched(InvoicePaid::class);
});

it('UC-RBCFG-11 · Tier 0: Pix na 98 com txid de fatura da 99 não mexe na 99', function () {
    $da99 = rbInFatura(RB_IN_OUTRA, 'RB-99-2026-11', '150.00', 'TXID99EEEEEEEEEEEEEEEEEEEEE');

    rbInPix(RB_IN_BIZ, 'E2E-6', 'TXID99EEEEEEEEEEEEEEEEEEEEE', '150.00');

    expect(DB::table('rb_invoices')->where('id', $da99)->value('status'))->toBe('open')
        ->and(rbInCreditosCentavos(6098))->toBe(0)
        ->and(rbInCreditosCentavos(6099))->toBe(0)
        ->and(rbInDeltaSaldoCentavos(RB_IN_OUTRA))->toBe(0);
    Event::assertNotDispatched(InvoicePaid::class);
});

it('UC-RBCFG-11 · Pix sem txid, ou com txid que não é de fatura, não credita nada', function () {
    rbInPix(RB_IN_BIZ, 'E2E-7', null, '99.90');
    rbInPix(RB_IN_BIZ, 'E2E-8', 'TXIDDESCONHECIDOXXXXXXXXXXX', '99.90');

    expect(rbInCreditosCentavos(6098))->toBe(0)
        ->and(rbInDeltaSaldoCentavos(RB_IN_BIZ))->toBe(0)
        ->and(DB::table('account_transactions')->count())->toBe(0);
    Event::assertNotDispatched(InvoicePaid::class);
});
