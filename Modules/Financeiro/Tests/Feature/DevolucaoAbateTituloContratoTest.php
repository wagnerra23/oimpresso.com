<?php

declare(strict_types=1);

use App\Transaction;
use App\TransactionPayment;
use App\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Financeiro\Models\Concerns\BusinessScopeImpl;
use Modules\Financeiro\Models\Titulo;

uses(Tests\TestCase::class);

/**
 * Devolução de venda abate o título a receber da venda — decisão [W] 2026-11-03
 * ("Abater o título aberto").
 *
 * Achado no teste real da Venda V3 (OS00135 → DEV2026/0003): o Financeiro ignorava
 * `sell_return` e o título da venda devolvida seguia aberto pelo valor inteiro. Medido em
 * produção no mesmo dia: 31 devoluções nesse estado, 30 na ROTA LIVRE.
 *
 * Regra mestre de valor: cada caso confere por dois caminhos — o `valor_aberto` do título da
 * venda e a soma a receber em aberto do tenant, antes e depois. Tenant 98 (ADR 0358); tudo
 * dentro de transação desfeita no fim (`fin_titulos` não aceita delete).
 *
 * Casos: UC-FDEV-01 · UC-FDEV-02 · UC-FDEV-03 · UC-FDEV-04 · UC-FDEV-05 · UC-FDEV-06 · UC-FDEV-07
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('Financeiro usa schema MySQL.');
    }

    $biz = $this->seededTenant();
    $this->bizId = (int) $biz->id;
    $this->userId = (int) (User::where('business_id', $this->bizId)->value('id') ?? 0);
    if ($this->userId === 0) {
        $this->markTestSkipped('Sem usuário no tenant de teste.');
    }
    $this->locationId = DB::table('business_locations')->where('business_id', $this->bizId)->value('id');

    DB::beginTransaction();
});

afterEach(function () {
    if (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
});

function fdevVenda(int $biz, int $userId, ?int $locationId, float $total): Transaction
{
    return Transaction::create([
        'business_id' => $biz,
        'location_id' => $locationId,
        'type' => 'sell',
        'status' => 'final',
        'payment_status' => 'due',
        'contact_id' => null,
        'invoice_no' => 'ZZ-FDEV-'.Str::random(8),
        'transaction_date' => '2031-05-10 10:00:00',
        'total_before_tax' => $total,
        'final_total' => $total,
        'created_by' => $userId,
    ]);
}

/** Mesma forma do TransactionUtil::addSellReturn: cria com o total e o vínculo à venda. */
function fdevDevolucao(Transaction $venda, float $total, ?int $biz = null): Transaction
{
    return Transaction::create([
        'business_id' => $biz ?? $venda->business_id,
        'location_id' => $venda->location_id,
        'type' => 'sell_return',
        'status' => 'final',
        'payment_status' => 'due',
        'contact_id' => $venda->contact_id,
        'invoice_no' => 'ZZ-FDEVR-'.Str::random(8),
        'transaction_date' => '2031-05-12 10:00:00',
        'total_before_tax' => $total,
        'final_total' => $total,
        'return_parent_id' => $venda->id,
        'created_by' => $venda->created_by,
    ]);
}

/** Caminho (a): título da venda. */
function fdevTitulo(Transaction $venda): Titulo
{
    return Titulo::query()
        ->withoutGlobalScope(BusinessScopeImpl::class)
        ->where('business_id', $venda->business_id)
        ->where('origem', 'venda')
        ->where('origem_id', $venda->id)
        ->firstOrFail();
}

/** Caminho (b): total a receber em aberto do business. */
function fdevAReceber(int $biz): float
{
    return round((float) DB::table('fin_titulos')
        ->where('business_id', $biz)
        ->where('tipo', 'receber')
        ->where('status', '!=', 'cancelado')
        ->whereNull('deleted_at')
        ->sum('valor_aberto'), 2);
}

it('UC-FDEV-01 devolução total de venda em aberto quita o título, sem entrada no caixa', function () {
    $venda = fdevVenda($this->bizId, $this->userId, $this->locationId, 250.00);
    $antes = fdevAReceber($this->bizId);
    $caixaAntes = (int) DB::table('fin_caixa_movimentos')->where('business_id', $this->bizId)->count();

    fdevDevolucao($venda, 250.00);

    $titulo = fdevTitulo($venda);
    expect((float) $titulo->valor_aberto)->toBe(0.0);
    expect($titulo->status)->toBe('quitado');
    expect(round($antes - fdevAReceber($this->bizId), 2))->toBe(250.0);
    expect((int) DB::table('fin_caixa_movimentos')->where('business_id', $this->bizId)->count())->toBe($caixaAntes);

    $baixa = DB::table('fin_titulo_baixas')->where('titulo_id', $titulo->id)->first();
    expect($baixa->meio_pagamento)->toBe('compensacao');
    expect((float) $baixa->valor_baixa)->toBe(250.0);
});

it('UC-FDEV-02 devolução parcial abate só o devolvido', function () {
    $venda = fdevVenda($this->bizId, $this->userId, $this->locationId, 400.00);
    $antes = fdevAReceber($this->bizId);

    fdevDevolucao($venda, 150.00);

    $titulo = fdevTitulo($venda);
    expect((float) $titulo->valor_aberto)->toBe(250.0);
    expect($titulo->status)->toBe('parcial');
    expect(round($antes - fdevAReceber($this->bizId), 2))->toBe(150.0);
});

it('UC-FDEV-03 devolução maior que o aberto abate só até o aberto; a sobra fica como crédito na devolução', function () {
    $venda = fdevVenda($this->bizId, $this->userId, $this->locationId, 300.00);
    TransactionPayment::create([
        'transaction_id' => $venda->id,
        'business_id' => $venda->business_id,
        'amount' => 200.00,
        'method' => 'cash',
        'paid_on' => '2031-05-11 10:00:00',
        'created_by' => $this->userId,
    ]);
    $venda->payment_status = 'partial';
    $venda->save();
    expect((float) fdevTitulo($venda)->valor_aberto)->toBe(100.0);
    $antes = fdevAReceber($this->bizId);

    $devolucao = fdevDevolucao($venda, 180.00);

    $titulo = fdevTitulo($venda);
    expect((float) $titulo->valor_aberto)->toBe(0.0);
    expect(round($antes - fdevAReceber($this->bizId), 2))->toBe(100.0);
    // A sobra (80,00) não vira baixa: segue devida ao cliente no próprio sell_return.
    expect((float) $devolucao->fresh()->final_total)->toBe(180.0);
    expect((int) DB::table('transaction_payments')->where('transaction_id', $devolucao->id)->count())->toBe(0);
});

it('UC-FDEV-04 devolução já paga ao cliente não abate: o título volta ao valor da venda', function () {
    $venda = fdevVenda($this->bizId, $this->userId, $this->locationId, 120.00);
    $devolucao = fdevDevolucao($venda, 120.00);
    expect((float) fdevTitulo($venda)->valor_aberto)->toBe(0.0);
    $antes = fdevAReceber($this->bizId);

    TransactionPayment::create([
        'transaction_id' => $devolucao->id,
        'business_id' => $devolucao->business_id,
        'amount' => 120.00,
        'method' => 'cash',
        'paid_on' => '2031-05-13 10:00:00',
        'created_by' => $this->userId,
    ]);
    $devolucao->payment_status = 'paid';
    $devolucao->save();

    $titulo = fdevTitulo($venda);
    expect((float) $titulo->valor_aberto)->toBe(120.0);
    expect($titulo->status)->toBe('aberto');
    expect(round(fdevAReceber($this->bizId) - $antes, 2))->toBe(120.0);
    // Append-only: a baixa original continua, com um estorno apontando para ela.
    expect((int) DB::table('fin_titulo_baixas')->where('titulo_id', $titulo->id)->count())->toBe(2);
});

it('UC-FDEV-05 excluir a devolução estorna o abatimento', function () {
    $venda = fdevVenda($this->bizId, $this->userId, $this->locationId, 90.00);
    $devolucao = fdevDevolucao($venda, 90.00);
    $antes = fdevAReceber($this->bizId);

    $devolucao->delete();

    $titulo = fdevTitulo($venda);
    expect((float) $titulo->valor_aberto)->toBe(90.0);
    expect($titulo->status)->toBe('aberto');
    expect(round(fdevAReceber($this->bizId) - $antes, 2))->toBe(90.0);
});

it('UC-FDEV-06 devolução de outra empresa não toca o título da venda', function () {
    $outro = (int) (DB::table('business')->where('id', '!=', $this->bizId)->value('id') ?? 0);
    if ($outro === 0) {
        $this->markTestSkipped('Sem segunda empresa no banco para o caso cross-tenant.');
    }

    $venda = fdevVenda($this->bizId, $this->userId, $this->locationId, 75.00);
    $antes = fdevAReceber($this->bizId);

    fdevDevolucao($venda, 75.00, $outro);

    expect((float) fdevTitulo($venda)->valor_aberto)->toBe(75.0);
    expect(fdevAReceber($this->bizId))->toBe($antes);
});

it('UC-FDEV-07 o comando mostra sem gravar, grava com --apply e não repete', function () {
    $venda = fdevVenda($this->bizId, $this->userId, $this->locationId, 60.00);
    $devolucao = fdevDevolucao($venda, 60.00);

    // Simula a devolução antiga, de antes da regra: estorna o abatimento que o observer fez.
    app(\Modules\Financeiro\Services\DevolucaoVendaTituloService::class)->estornarSeExistir($devolucao);
    expect((float) fdevTitulo($venda)->valor_aberto)->toBe(60.0);

    expect(Artisan::call('financeiro:devolucoes-abater', ['--business' => $this->bizId]))->toBe(0);
    expect((float) fdevTitulo($venda)->valor_aberto)->toBe(60.0);

    expect(Artisan::call('financeiro:devolucoes-abater', ['--business' => $this->bizId, '--apply' => true]))->toBe(0);
    expect((float) fdevTitulo($venda)->valor_aberto)->toBe(0.0);
    $baixas = (int) DB::table('fin_titulo_baixas')->where('titulo_id', fdevTitulo($venda)->id)->count();

    expect(Artisan::call('financeiro:devolucoes-abater', ['--business' => $this->bizId, '--apply' => true]))->toBe(0);
    expect((int) DB::table('fin_titulo_baixas')->where('titulo_id', fdevTitulo($venda)->id)->count())->toBe($baixas);
});
