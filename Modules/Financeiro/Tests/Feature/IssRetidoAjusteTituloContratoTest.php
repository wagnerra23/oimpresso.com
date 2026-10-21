<?php

declare(strict_types=1);

// @covers-us US-FIN-072 — NFS-e autorizada com retenção → a retenção entra como BAIXA (UC-FLX-30).

use App\Transaction;
use App\TransactionPayment;
use App\Utils\TransactionUtil;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Financeiro\Services\RetencaoNfseTituloService;
use Modules\Financeiro\Services\TituloAutoService;
use Modules\NFSe\Models\NfseEmissao;

uses(Tests\TestCase::class);

/**
 * US-FIN-072 · UC-FLX-30 — decisão [W] 2026-10-20: retenção é BAIXA, o título mantém o bruto.
 * Contrato: resources/js/Pages/Financeiro/ContasReceber/Index.casos.md (UC-CRISS-01..07).
 * REGRA MESTRE — valor: cada número é provado por DOIS caminhos.
 *
 * Caso-base, conta à mão:
 *   venda/serviço 1.000,00 · ISS 5% retido = 1.000,00 × 0,05 = 50,00
 *   PIS 0,65% = 6,50 · COFINS 3% = 30,00 · CSLL 1% = 10,00 → federais 46,50
 *   baixa de retenção = 50,00 + 46,50 = 96,50 · título bruto 1.000,00 · aberto 1.000,00 − 96,50 = 903,50
 *   cliente paga 903,50 → 96,50 + 903,50 = 1.000,00 → venda paid, título quitado
 * O 2º caminho é `issRecalculo()`: refaz a conta em PHP a partir dos campos da nota, sem SQL.
 *
 * Tenant 98 (ADR 0358) e 99 como business B (nunca o 4). Venda REAL (Transaction::create) para
 * o fluxo passar pelos observers Sells→Financeiro de verdade. Tudo numa transação desfeita.
 */

const ISS_RETIDO_MAO = 96.50;
const ISS_ABERTO_MAO = 903.50;

beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'mysql') {
        $this->markTestSkipped('Usa o schema MySQL do Financeiro/NFSe.');
    }
    if (! Schema::hasTable('nfse_emissoes') || ! Schema::hasColumn('nfse_emissoes', 'valor_iss_retido')) {
        $this->markTestSkipped('nfse_emissoes.valor_iss_retido ausente — rode migrate (NfeBrasil 2026_10_20_000001).');
    }

    DB::beginTransaction();
    $this->bizA = (int) $this->seededTenant()->id;
    $this->bizB = (int) $this->seededSupportClientTenant()->id;
    $this->ownerA = (int) DB::table('business')->where('id', $this->bizA)->value('owner_id');
    $this->ownerB = (int) DB::table('business')->where('id', $this->bizB)->value('owner_id');
});

afterEach(function () {
    if (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
});

function issChave(int $biz, bool $ativo): void
{
    $bruto = DB::table('business')->where('id', $biz)->value('common_settings');
    $s = is_string($bruto) ? (json_decode($bruto, true) ?: []) : [];
    $s[RetencaoNfseTituloService::CHAVE] = ['ativo' => $ativo];
    DB::table('business')->where('id', $biz)->update(['common_settings' => json_encode($s)]);
}

/** Venda a prazo de 1.000,00; o observer do Financeiro cria o título a receber. */
function issVenda(int $biz, int $owner): int
{
    $tx = Transaction::create([
        'business_id' => $biz, 'location_id' => null, 'type' => 'sell', 'status' => 'final',
        'payment_status' => 'due', 'contact_id' => null, 'invoice_no' => 'ISS-'.uniqid(),
        'transaction_date' => '2031-03-01 10:00:00', 'total_before_tax' => 1000, 'final_total' => 1000,
        'created_by' => $owner,
    ]);
    app(TituloAutoService::class)->sincronizarDeTransacao($tx); // idempotente com o observer

    return (int) $tx->id;
}

function issPagar(int $txId, float $valor): void
{
    $tx = Transaction::find($txId);
    TransactionPayment::create([
        'business_id' => $tx->business_id, 'transaction_id' => $txId, 'amount' => $valor,
        'method' => 'cash', 'paid_on' => now(), 'created_by' => $tx->created_by,
    ]);
    app(TransactionUtil::class)->updatePaymentStatus($txId, $tx->final_total);
}

function issNota(int $biz, int $txId, array $over = []): NfseEmissao
{
    return NfseEmissao::withoutGlobalScopes()->create(array_merge([
        'business_id' => $biz, 'competencia' => '2031-03-01', 'tomador_nome' => 'Tomador ISS',
        'descricao' => 'Serviço — teste US-FIN-072', 'lc116_codigo' => '7.10',
        'valor_servicos' => 1000, 'aliquota_iss' => 0.05, 'valor_iss' => 0,
        'iss_retido' => true, 'valor_iss_retido' => 50,
        'retencao_federal_sujeito' => true, 'valor_ret_pis' => 6.50, 'valor_ret_cofins' => 30.00, 'valor_ret_csll' => 10.00,
        'status' => 'processando', 'idempotency_key' => hash('sha256', uniqid('iss', true)), 'transaction_id' => $txId,
    ], $over));
}

/** 2º caminho: retido refeito em PHP a partir dos campos da nota, sem SQL. */
function issRecalculo(NfseEmissao $n): float
{
    $iss = $n->iss_retido ? round((float) $n->valor_servicos * (float) $n->aliquota_iss, 2) : 0.0;

    return round($iss + (float) $n->valor_ret_pis + (float) $n->valor_ret_cofins + (float) $n->valor_ret_csll, 2);
}

function issTitulo(int $biz, int $txId): object
{
    return DB::table('fin_titulos')->where('business_id', $biz)->where('origem', 'venda')
        ->where('origem_id', $txId)->whereNull('parcela_numero')->first();
}

function issBaixasRetencao(int $tituloId): \Illuminate\Support\Collection
{
    return DB::table('fin_titulo_baixas')->where('titulo_id', $tituloId)->where('meio_pagamento', 'retencao')->get();
}

function issPagamentosRetencao(int $txId): int
{
    return DB::table('transaction_payments')->where('transaction_id', $txId)->where('method', RetencaoNfseTituloService::METODO)->count();
}

it('UC-CRISS-01 · chave desligada (o default) → nenhuma baixa de retenção', function () {
    $tx = issVenda($this->bizA, $this->ownerA);

    issNota($this->bizA, $tx)->update(['status' => 'emitida']);

    $t = issTitulo($this->bizA, $tx);
    expect((float) $t->valor_total)->toBe(1000.0);
    expect((float) $t->valor_aberto)->toBe(1000.0);
    expect(issBaixasRetencao((int) $t->id))->toHaveCount(0);
    expect(issPagamentosRetencao($tx))->toBe(0);
});

it('UC-CRISS-02 · chave ligada → título no bruto, baixa de retenção 96,50, aberto 903,50, uma vez só', function () {
    issChave($this->bizA, true);
    $tx = issVenda($this->bizA, $this->ownerA);
    $nota = issNota($this->bizA, $tx);

    $nota->update(['status' => 'emitida']);

    expect(issRecalculo($nota))->toBe(ISS_RETIDO_MAO); // os dois caminhos concordam
    expect(round(1000.0 - issRecalculo($nota), 2))->toBe(ISS_ABERTO_MAO);
    $t = issTitulo($this->bizA, $tx);
    expect((float) $t->valor_total)->toBe(1000.0);
    expect((float) $t->valor_aberto)->toBe(ISS_ABERTO_MAO);
    expect($t->status)->toBe('parcial');
    $baixas = issBaixasRetencao((int) $t->id);
    expect($baixas)->toHaveCount(1);
    expect((float) $baixas[0]->valor_baixa)->toBe(ISS_RETIDO_MAO);
    // retenção não é dinheiro em caixa
    expect(DB::table('fin_caixa_movimentos')->where('origem_tipo', 'titulo_baixa')->where('origem_id', $baixas[0]->id)->count())->toBe(0);
    $m = json_decode((string) $t->metadata, true)['retencao_nfse'];
    expect((float) $m['retencoes']['iss'])->toBe(50.0);
    expect((float) $m['pendente'])->toBe(0.0);

    // reprocessar a autorização: idempotente
    $nota->update(['status' => 'processando']);
    $nota->update(['status' => 'emitida']);
    expect(issPagamentosRetencao($tx))->toBe(1);
    expect((float) issTitulo($this->bizA, $tx)->valor_aberto)->toBe(ISS_ABERTO_MAO);
});

it('UC-CRISS-03 · cliente paga o líquido 903,50 → venda paid e título quitado', function () {
    issChave($this->bizA, true);
    $tx = issVenda($this->bizA, $this->ownerA);
    issNota($this->bizA, $tx)->update(['status' => 'emitida']);

    issPagar($tx, ISS_ABERTO_MAO);

    expect(Transaction::find($tx)->payment_status)->toBe('paid');
    $t = issTitulo($this->bizA, $tx);
    expect($t->status)->toBe('quitado');
    expect((float) $t->valor_aberto)->toBe(0.0);
    expect((float) $t->valor_total)->toBe(1000.0);
});

it('UC-CRISS-04 · venda com aberto menor que o retido → baixa só até o aberto, diferença pendente', function () {
    issChave($this->bizA, true);
    $tx = issVenda($this->bizA, $this->ownerA);
    issPagar($tx, 950.00); // aberto 50,00 < retido 96,50

    issNota($this->bizA, $tx)->update(['status' => 'emitida']);

    $t = issTitulo($this->bizA, $tx);
    $baixas = issBaixasRetencao((int) $t->id);
    expect($baixas)->toHaveCount(1);
    expect((float) $baixas[0]->valor_baixa)->toBe(50.0);
    // 96,50 − 50,00 = 46,50
    expect((float) json_decode((string) $t->metadata, true)['retencao_nfse']['pendente'])->toBe(46.5);
    expect(Transaction::find($tx)->payment_status)->toBe('paid');
    expect((float) $t->valor_aberto)->toBe(0.0);
});

it('UC-CRISS-05 · cancelar a NFS-e estorna a baixa de retenção e o título volta a 1.000,00 em aberto', function () {
    issChave($this->bizA, true);
    $tx = issVenda($this->bizA, $this->ownerA);
    $nota = issNota($this->bizA, $tx);
    $nota->update(['status' => 'emitida']);

    $nota->update(['status' => 'cancelada']);

    $t = issTitulo($this->bizA, $tx);
    $baixas = issBaixasRetencao((int) $t->id);
    expect($baixas)->toHaveCount(2); // original + estorno (append-only)
    expect(round((float) $baixas->sum('valor_baixa'), 2))->toBe(0.0);
    expect((float) $t->valor_aberto)->toBe(1000.0);
    expect(issPagamentosRetencao($tx))->toBe(0);
    expect(Transaction::find($tx)->payment_status)->toBe('due');
    expect(json_decode((string) $t->metadata, true)['retencao_nfse']['estado'])->toBe('estornado');
});

it('UC-CRISS-06 · nota de um negócio não lança retenção em venda de outro', function () {
    issChave($this->bizA, true);
    issChave($this->bizB, true);
    $txB = issVenda($this->bizB, $this->ownerB);

    issNota($this->bizA, $txB)->update(['status' => 'emitida']);

    $t = issTitulo($this->bizB, $txB);
    expect((float) $t->valor_aberto)->toBe(1000.0);
    expect(issBaixasRetencao((int) $t->id))->toHaveCount(0);
    expect(issPagamentosRetencao($txB))->toBe(0);
});

it('UC-CRISS-07 · dry-run mostra a baixa de retenção que seria lançada e não grava nada', function () {
    $tx = issVenda($this->bizA, $this->ownerA);
    // nota autorizada ANTES (chave desligada): nada lançado
    issNota($this->bizA, $tx, ['valor_iss_retido' => null])->update(['status' => 'emitida']);
    $antes = (array) issTitulo($this->bizA, $tx);

    $rc = Artisan::call('financeiro:iss-retido-ajuste', ['--business' => $this->bizA, '--dry-run' => true]);
    $saida = Artisan::output();

    expect($rc)->toBe(0);
    expect($saida)->toContain('96,50');
    expect($saida)->toContain('903,50');
    expect($saida)->toContain('baixa');
    expect((array) issTitulo($this->bizA, $tx))->toEqual($antes);
    expect(issPagamentosRetencao($tx))->toBe(0);
    expect(Artisan::call('financeiro:iss-retido-ajuste', ['--business' => $this->bizA]))->toBe(1);
});

it('UC-CRISS-08 · a lista mostra bruto 1.000,00, retido 96,50 e líquido 903,50; estornada volta a retido 0', function () {
    $svc = app(RetencaoNfseTituloService::class);
    $modelo = fn (int $txId) => \Modules\Financeiro\Models\Titulo::withoutGlobalScopes()->find((int) issTitulo($this->bizA, $txId)->id);

    // chave desligada: nada retido, líquido = bruto
    $tx0 = issVenda($this->bizA, $this->ownerA);
    issNota($this->bizA, $tx0)->update(['status' => 'emitida']);
    $t0 = $modelo($tx0);
    expect($svc->valoresDoTitulo($t0->valor_total, $t0->metadata))->toBe(['bruto' => 100000, 'retido' => 0, 'liquido' => 100000]);

    issChave($this->bizA, true);
    $tx = issVenda($this->bizA, $this->ownerA);
    $nota = issNota($this->bizA, $tx);
    $nota->update(['status' => 'emitida']);
    $t = $modelo($tx);
    $v = $svc->valoresDoTitulo($t->valor_total, $t->metadata);

    // caminho 1: conta à mão (1.000,00 − 96,50 = 903,50)
    expect($v)->toBe(['bruto' => 100000, 'retido' => 9650, 'liquido' => 90350]);
    // caminho 2: retido refeito dos campos da nota
    expect($v['retido'])->toBe((int) round(issRecalculo($nota) * 100));
    // caminho 3: sem outro pagamento, o líquido é o aberto que a baixa de retenção deixou
    expect($v['liquido'])->toBe((int) round((float) $t->valor_aberto * 100));

    $nota->update(['status' => 'cancelada']);
    $te = $modelo($tx);
    expect($svc->valoresDoTitulo($te->valor_total, $te->metadata))->toBe(['bruto' => 100000, 'retido' => 0, 'liquido' => 100000]);
});
