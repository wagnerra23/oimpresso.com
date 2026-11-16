<?php

declare(strict_types=1);

use App\Transaction;
use App\Utils\TransactionUtil;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Edição de linha de pagamento de venda (`TransactionUtil::editPaymentLine`, chamado por
 * `createOrUpdatePaymentLines` no formulário web e na API do Connector).
 *
 * Contrato: o `payment_id` vem do request, então só é editável se a linha for DA VENDA em edição.
 * Tier 0 (ADR 0093): linha de pagamento de venda de outra empresa não é alterada (controle negativo);
 * linha da própria venda é (controle positivo, em par).
 */
uses(DatabaseTransactions::class);

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasTable('transaction_payments')) {
        $this->markTestSkipped('Requer schema MySQL UltimatePOS (ADR 0358).');
    }
    $this->biz = (int) $this->seededTenant()->id;
    $this->outro = (int) \App\Business::where('id', '!=', $this->biz)->orderBy('id')->value('id');
    if (! $this->outro) {
        $this->markTestSkipped('Lane sem 2º business — contrato cross-tenant não exercitável.');
    }
});

function pagLinhaVenda(int $biz): array
{
    $criador = (int) DB::table('users')->where('business_id', $biz)->value('id');
    $loc = (int) DB::table('business_locations')->where('business_id', $biz)->value('id');
    if (! $criador || ! $loc) {
        test()->markTestSkipped("Business {$biz} sem usuário/local.");
    }
    $venda = (int) DB::table('transactions')->insertGetId([
        'business_id' => $biz, 'location_id' => $loc, 'type' => 'sell', 'status' => 'final',
        'payment_status' => 'partial', 'transaction_date' => now(), 'final_total' => 100,
        'total_before_tax' => 100, 'created_by' => $criador, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $pag = (int) DB::table('transaction_payments')->insertGetId([
        'transaction_id' => $venda, 'business_id' => $biz, 'amount' => 40, 'method' => 'cash',
        'paid_on' => now(), 'created_by' => $criador, 'created_at' => now(), 'updated_at' => now(),
    ]);

    return [$venda, $pag];
}

it('não altera linha de pagamento de venda de outra empresa', function () {
    [$minhaVenda] = pagLinhaVenda($this->biz);
    [, $pagAlheio] = pagLinhaVenda($this->outro);

    $venda = Transaction::findOrFail($minhaVenda);
    expect(fn () => app(TransactionUtil::class)->editPaymentLine(
        ['payment_id' => $pagAlheio, 'amount' => 1, 'method' => 'cash'], $venda, false
    ))->toThrow(ModelNotFoundException::class);

    expect((float) DB::table('transaction_payments')->where('id', $pagAlheio)->value('amount'))->toBe(40.0);
});

it('altera linha de pagamento da própria venda (controle positivo)', function () {
    [$minhaVenda, $meuPag] = pagLinhaVenda($this->biz);

    app(TransactionUtil::class)->editPaymentLine(
        ['payment_id' => $meuPag, 'amount' => 55, 'method' => 'cash'], Transaction::findOrFail($minhaVenda), false
    );

    expect((float) DB::table('transaction_payments')->where('id', $meuPag)->value('amount'))->toBe(55.0);
});
