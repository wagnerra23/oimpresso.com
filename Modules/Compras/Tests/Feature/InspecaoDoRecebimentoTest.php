<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Modules\Compras\Services\InspecaoDoRecebimento;
use Tests\Support\EstoqueFixture;

/**
 * US-COM-034 — inspeção de qualidade no recebimento (lacuna P2 de Compras, 2026-10-27).
 *
 * Contrato (SPEC Compras US-COM-034, não o código): cada linha da compra recebida é aprovada,
 * reprovada ou parcial; o reprovado não fica no estoque disponível e vira devolução ao fornecedor.
 * Cenário escrito à mão (regra mestre, valor e estoque):
 *   lona: 10 recebidas a 12,50 → 7 aprovadas, 3 reprovadas ("rasgada")
 *     estoque   10 → 7        devolução 3 × 12,50 = 37,50
 *   tinta: 4 recebidas a 20,00 → 4 aprovadas → estoque 4 → 4, nada devolvido
 * Estoque por DOIS caminhos: (1) `variation_location_details.qty_available`;
 * (2) Σ (quantity − quantity_returned) das linhas de compras recebidas da variação no local.
 * Devolução por dois caminhos: (1) `final_total` do purchase_return; (2) 3 × 12,50 à mão.
 *
 * Tenant 98 (ADR 0358); adversário 99 ou o biz=2 do seed, nunca 1 nem 4.
 *
 * @covers-uc UC-PURSHW-10 — resources/js/Pages/Purchase/Show.casos.md
 * @covers-uc UC-PURSHW-11 — resources/js/Pages/Purchase/Show.casos.md
 * @covers-us US-COM-034
 */
uses(Tests\TestCase::class, DatabaseTransactions::class);

beforeEach(function () {
    if (! EstoqueFixture::schemaReady() || ! \Schema::hasTable('compras_inspecoes')) {
        $this->markTestSkipped('Schema MySQL com as migrations de Compras ausente — rode no CT 100 / lane compras-pest.');
    }
    $this->biz = EstoqueFixture::businessId();
    $this->uid = EstoqueFixture::userId($this->biz);
    $this->loc = EstoqueFixture::locationId($this->biz, '-INSP');
    $this->lona = EstoqueFixture::singleProduct($this->biz);
    $this->tinta = EstoqueFixture::singleProduct($this->biz);
    $this->forn = DB::table('contacts')->insertGetId([
        'business_id' => $this->biz, 'type' => 'supplier', 'name' => 'Fornecedor INSP', 'contact_id' => 'CT-INSP-'.uniqid(),
        'created_by' => $this->uid, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $this->compra = DB::table('transactions')->insertGetId([
        'business_id' => $this->biz, 'location_id' => $this->loc, 'contact_id' => $this->forn,
        'type' => 'purchase', 'status' => 'received', 'payment_status' => 'due', 'ref_no' => 'INSP-'.uniqid(),
        'transaction_date' => now(), 'total_before_tax' => 205, 'final_total' => 205,
        'created_by' => $this->uid, 'essentials_duration' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $linha = fn ($p, string $qtd, string $preco) => DB::table('purchase_lines')->insertGetId([
        'transaction_id' => $this->compra, 'product_id' => $p->productId, 'variation_id' => $p->variationId(),
        'quantity' => $qtd, 'quantity_returned' => 0, 'pp_without_discount' => $preco, 'purchase_price' => $preco,
        'purchase_price_inc_tax' => $preco, 'item_tax' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $this->linhaLona = $linha($this->lona, '10', '12.50');
    $this->linhaTinta = $linha($this->tinta, '4', '20.00');
    EstoqueFixture::setStock($this->lona, 0, $this->loc, 10);
    EstoqueFixture::setStock($this->tinta, 0, $this->loc, 4);

    $this->svc = app(InspecaoDoRecebimento::class);
    $this->svc->definir($this->biz, true);
});

/** Caminho 2 do estoque: o que as compras recebidas deixaram na variação, menos o devolvido. */
function insp034Recebido($t, $p): float
{
    return (float) DB::table('purchase_lines as pl')->join('transactions as tx', 'tx.id', '=', 'pl.transaction_id')
        ->where('tx.business_id', $t->biz)->where('tx.type', 'purchase')->where('tx.status', 'received')
        ->where('tx.location_id', $t->loc)->where('pl.variation_id', $p->variationId())
        ->sum(DB::raw('pl.quantity - pl.quantity_returned'));
}

it('UC-PURSHW-10 parcial: 7 aprovadas e 3 reprovadas tiram 3 do estoque e geram devolução de 37,50', function () {
    expect(EstoqueFixture::currentStock($this->lona, 0, $this->loc))->toBe(10.0);
    expect(insp034Recebido($this, $this->lona))->toBe(10.0);

    $r = $this->svc->registrar($this->biz, $this->compra, $this->uid, [
        $this->linhaLona => ['aprovada' => '7', 'reprovada' => '3', 'motivo' => 'rasgada'],
        $this->linhaTinta => ['aprovada' => '4', 'reprovada' => '0'],
    ]);

    expect(EstoqueFixture::currentStock($this->lona, 0, $this->loc))->toBe(7.0);
    expect(insp034Recebido($this, $this->lona))->toBe(7.0);
    expect(EstoqueFixture::currentStock($this->tinta, 0, $this->loc))->toBe(4.0);
    expect(insp034Recebido($this, $this->tinta))->toBe(4.0);

    $dev = DB::table('transactions')->where('id', $r['devolucao_id'])->first();
    expect($dev->type)->toBe('purchase_return');
    expect((int) $dev->business_id)->toBe($this->biz);
    expect((int) $dev->return_parent_id)->toBe($this->compra);
    expect((string) $dev->final_total)->toBe(number_format(3 * 12.50, 4, '.', ''));

    $porLinha = DB::table('compras_inspecoes')->whereIn('id', $r['inspecoes'])->pluck('resultado', 'purchase_line_id');
    expect($porLinha[$this->linhaLona])->toBe('parcial');
    expect($porLinha[$this->linhaTinta])->toBe('aprovado');
});

it('UC-PURSHW-10 tudo aprovado não mexe no estoque nem cria devolução', function () {
    $r = $this->svc->registrar($this->biz, $this->compra, $this->uid, [
        $this->linhaLona => ['aprovada' => '10', 'reprovada' => '0'],
    ]);

    expect($r['devolucao_id'])->toBeNull();
    expect(EstoqueFixture::currentStock($this->lona, 0, $this->loc))->toBe(10.0);
    expect(DB::table('transactions')->where('return_parent_id', $this->compra)->exists())->toBeFalse();
});

it('UC-PURSHW-10 reprovado inteiro: resultado reprovado e estoque zerado', function () {
    $this->svc->registrar($this->biz, $this->compra, $this->uid, [
        $this->linhaTinta => ['aprovada' => '0', 'reprovada' => '4', 'motivo' => 'cor errada'],
    ]);

    expect(EstoqueFixture::currentStock($this->tinta, 0, $this->loc))->toBe(0.0);
    expect(insp034Recebido($this, $this->tinta))->toBe(0.0);
    expect(DB::table('compras_inspecoes')->where('purchase_line_id', $this->linhaTinta)->value('resultado'))->toBe('reprovado');
});

it('UC-PURSHW-11 recusa soma diferente do recebido, reprovado sem motivo e linha inspecionada duas vezes', function () {
    expect(fn () => $this->svc->registrar($this->biz, $this->compra, $this->uid, [
        $this->linhaLona => ['aprovada' => '7', 'reprovada' => '2', 'motivo' => 'x'],
    ]))->toThrow(InvalidArgumentException::class);
    expect(fn () => $this->svc->registrar($this->biz, $this->compra, $this->uid, [
        $this->linhaLona => ['aprovada' => '7', 'reprovada' => '3'],
    ]))->toThrow(InvalidArgumentException::class);

    $this->svc->registrar($this->biz, $this->compra, $this->uid, [$this->linhaLona => ['aprovada' => '10', 'reprovada' => '0']]);
    expect(fn () => $this->svc->registrar($this->biz, $this->compra, $this->uid, [
        $this->linhaLona => ['aprovada' => '9', 'reprovada' => '1', 'motivo' => 'x'],
    ]))->toThrow(InvalidArgumentException::class);
    expect(EstoqueFixture::currentStock($this->lona, 0, $this->loc))->toBe(10.0);
});

it('UC-PURSHW-11 desligada por padrão: sem a chave a inspeção é recusada e o estoque não muda', function () {
    $this->svc->definir($this->biz, false);

    expect(fn () => $this->svc->registrar($this->biz, $this->compra, $this->uid, [
        $this->linhaLona => ['aprovada' => '0', 'reprovada' => '10', 'motivo' => 'x'],
    ]))->toThrow(InvalidArgumentException::class);
    expect(EstoqueFixture::currentStock($this->lona, 0, $this->loc))->toBe(10.0);
});

it('UC-PURSHW-11 Tier 0: compra de outra empresa é recusada e o estoque dela não muda', function () {
    $outra = (int) DB::table('business')->whereIn('id', [99, 2])->where('id', '!=', $this->biz)->orderByDesc('id')->value('id');
    if ($outra <= 0) {
        $this->markTestSkipped('Sem empresa adversária fictícia (99 ou 2) neste ambiente.');
    }
    $this->svc->definir($outra, true);

    // A empresa adversária tem a chave ligada: a recusa vem do filtro por empresa, não da chave.
    expect(fn () => $this->svc->registrar($outra, $this->compra, EstoqueFixture::userId($outra), [
        $this->linhaLona => ['aprovada' => '0', 'reprovada' => '10', 'motivo' => 'x'],
    ]))->toThrow(InvalidArgumentException::class, 'Compra inválida');
    expect(EstoqueFixture::currentStock($this->lona, 0, $this->loc))->toBe(10.0);
});
