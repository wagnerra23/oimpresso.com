<?php
// @covers-us US-SELL-069

declare(strict_types=1);

use App\Exceptions\MargemAbaixoDoMinimo;
use App\Transaction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\Support\EstoqueFixture;

/**
 * UC-DRAFT-05 · US-SELL-069 · UC-FLX-35 — orçamento com margem abaixo do mínimo da empresa só
 * vira venda com `sell.aprovar_margem_baixa`. Decisão [W] 2026-10-19: bloqueia.
 *
 * Âncora: resources/js/Pages/Sells/Drafts.casos.md UC-DRAFT-05. Exercita o ponto onde todos os
 * caminhos de conversão se encontram: a transação `sell` passando de `draft` para `final`.
 *
 * Números feitos à mão (margem = (receita líquida − custo guardado) / receita líquida):
 *   2 un × 50,00, custo guardado 40,00 → (100 − 80) / 100 = 20%  (abaixo de 30%)
 *   2 un × 50,00, custo guardado 30,00 → (100 − 60) / 100 = 40%  (acima de 30%)
 *   o mesmo com 20,00 de desconto no pedido → (80 − 60) / 80 = 25%  (abaixo de 30%)
 */
uses(DatabaseTransactions::class, \Tests\Support\WithSeededTenant::class);

beforeEach(function () {
    if (! EstoqueFixture::schemaReady()
        || ! Schema::hasColumn('transaction_sell_lines', 'custo_previsto')
        || ! Schema::hasColumn('fsm_modelos_empresa', 'margem_minima_pct')) {
        $this->markTestSkipped('Schema UltimatePOS/seed ausente — roda na lane MySQL / CT 100.');
    }

    $this->bizId = EstoqueFixture::businessId();
    $biz = \App\Business::findOrFail($this->bizId);
    Permission::findOrCreate('sell.aprovar_margem_baixa', 'web');
    $this->vendedor = $this->usuarioComPermissoes(['sell.create'], $biz);
    $this->aprovador = $this->usuarioComPermissoes(['sell.create', 'sell.aprovar_margem_baixa'], $biz);
    $this->actingAs($this->vendedor);

    DB::table('fsm_modelos_empresa')->updateOrInsert(
        ['business_id' => $this->bizId],
        ['modelo' => 'cv-pequena', 'margem_minima_pct' => 30, 'updated_at' => now()],
    );

    $this->produto = EstoqueFixture::singleProduct($this->bizId);
    $this->orcamento = function (float $custo, float $desconto = 0): Transaction {
        $venda = EstoqueFixture::saleWithLine($this->produto, 0, EstoqueFixture::locationId($this->bizId), 2.0, 50.0);
        DB::table('transactions')->where('id', $venda['transaction_id'])
            ->update(['status' => 'draft', 'discount_type' => 'fixed', 'discount_amount' => $desconto]);
        DB::table('transaction_sell_lines')->where('id', $venda['sell_line_id'])->update(['custo_previsto' => $custo]);

        return Transaction::findOrFail($venda['transaction_id']);
    };
    $this->converter = function (Transaction $tx): void {
        $tx->status = 'final';
        $tx->save();
    };
});

it('UC-DRAFT-05 · vendedor sem a permissão não converte orçamento com margem de 20% (mínimo 30%)', function () {
    $tx = ($this->orcamento)(40.0);

    expect(fn () => ($this->converter)($tx))->toThrow(MargemAbaixoDoMinimo::class, 'Margem de 20,0% abaixo do mínimo de 30,0%');
    expect(DB::table('transactions')->where('id', $tx->id)->value('status'))->toBe('draft');
});

it('UC-DRAFT-05 · quem tem a permissão converte o mesmo orçamento', function () {
    $this->actingAs($this->aprovador);
    $tx = ($this->orcamento)(40.0);

    ($this->converter)($tx);

    expect(DB::table('transactions')->where('id', $tx->id)->value('status'))->toBe('final');
});

it('UC-DRAFT-05 · margem acima do mínimo converte sem permissão', function () {
    $tx = ($this->orcamento)(30.0);

    ($this->converter)($tx);

    expect(DB::table('transactions')->where('id', $tx->id)->value('status'))->toBe('final');
});

it('UC-DRAFT-05 · o desconto do pedido entra na margem (40% vira 25% com 20,00 de desconto)', function () {
    $tx = ($this->orcamento)(30.0, 20.0);

    expect(\App\Observers\TransactionMargemMinimaObserver::margem($tx))->toBe(25.0);
    expect(fn () => ($this->converter)($tx))->toThrow(MargemAbaixoDoMinimo::class);
});

it('UC-DRAFT-05 · sem margem mínima configurada nada é bloqueado', function () {
    DB::table('fsm_modelos_empresa')->where('business_id', $this->bizId)->update(['margem_minima_pct' => null]);
    $tx = ($this->orcamento)(40.0);

    ($this->converter)($tx);

    expect(DB::table('transactions')->where('id', $tx->id)->value('status'))->toBe('final');
});

it('UC-DRAFT-05 · orçamento sem custo guardado converte (anterior à US-SELL-067)', function () {
    $tx = ($this->orcamento)(40.0);
    DB::table('transaction_sell_lines')->where('transaction_id', $tx->id)->update(['custo_previsto' => null]);

    ($this->converter)($tx);

    expect(DB::table('transactions')->where('id', $tx->id)->value('status'))->toBe('final');
});
