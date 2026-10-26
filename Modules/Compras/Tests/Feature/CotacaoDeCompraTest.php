<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Modules\Compras\Services\CotacaoDeCompra;
use Tests\Support\EstoqueFixture;

/**
 * US-COM-031 — cotação de compra entre fornecedores (decisão coordenação 2026-10-26).
 *
 * Contrato (SPEC Compras US-COM-031): pedir preço e prazo a vários fornecedores para os itens, comparar
 * lado a lado; vence o menor preço, no empate o menor prazo. Cenário escrito à mão:
 *   item 1 (lona, 10 un):   A = 10,00 em 5 dias · B =  9,50 em 10 dias → vence B (preço)
 *   item 2 (tinta, 2 un):   A = 20,00 em 7 dias · B = 20,00 em 3 dias  → vence B (empate, prazo)
 *   item 3 (ilhós, 100 un): ninguém respondeu → sem vencedor
 *
 * Tenant 98 (ADR 0358); adversário 99 ou o biz=2 do seed, nunca 1 nem 4.
 *
 * @covers-us US-COM-031
 */
uses(Tests\TestCase::class, DatabaseTransactions::class);

beforeEach(function () {
    if (! EstoqueFixture::schemaReady() || ! \Schema::hasTable('compras_cotacoes')) {
        $this->markTestSkipped('Schema MySQL com as migrations de Compras ausente — rode no CT 100 / lane compras-pest.');
    }
    $this->biz = EstoqueFixture::businessId();
    $this->uid = EstoqueFixture::userId($this->biz);
    $this->loc = EstoqueFixture::locationId($this->biz, '-COT');
    $this->lona = EstoqueFixture::singleProduct($this->biz);
    $this->tinta = EstoqueFixture::singleProduct($this->biz);
    $this->ilhos = EstoqueFixture::singleProduct($this->biz);
    $forn = fn (int $biz, string $nome) => DB::table('contacts')->insertGetId([
        'business_id' => $biz, 'type' => 'supplier', 'name' => $nome, 'contact_id' => 'CT-COT-'.uniqid(),
        'created_by' => EstoqueFixture::userId($biz), 'created_at' => now(), 'updated_at' => now(),
    ]);
    $this->a = $forn($this->biz, 'Fornecedor A');
    $this->b = $forn($this->biz, 'Fornecedor B');
    $this->outra = (int) DB::table('business')->whereIn('id', [99, 2])->where('id', '!=', $this->biz)->orderByDesc('id')->value('id');
    $this->alheio = $this->outra > 0 ? $forn($this->outra, 'Fornecedor alheio') : null;
    $this->svc = app(CotacaoDeCompra::class);
});

function cotCriar($t): int
{
    return $t->svc->criar($t->biz, $t->loc, [
        ['variation_id' => $t->lona->variationId(), 'quantidade' => '10'],
        ['variation_id' => $t->tinta->variationId(), 'quantidade' => '2'],
        ['variation_id' => $t->ilhos->variationId(), 'quantidade' => '100'],
    ], [$t->a, $t->b], $t->uid);
}

it('compara as respostas e escolhe o menor preço, e no empate o menor prazo', function () {
    $id = cotCriar($this);
    $itens = DB::table('compras_cotacao_itens')->where('cotacao_id', $id)->orderBy('id')->pluck('id')->all();
    expect($itens)->toHaveCount(3);

    $this->svc->responder($this->biz, $id, $this->a, [
        $itens[0] => ['preco_unitario' => '10.00', 'prazo_dias' => 5],
        $itens[1] => ['preco_unitario' => '20.00', 'prazo_dias' => 7],
    ]);
    $this->svc->responder($this->biz, $id, $this->b, [
        $itens[0] => ['preco_unitario' => '9.50', 'prazo_dias' => 10],
        $itens[1] => ['preco_unitario' => '20.00', 'prazo_dias' => 3],
    ]);

    $c = collect($this->svc->comparar($this->biz, $id))->keyBy('item_id');
    expect($c[$itens[0]]['melhor_contact_id'])->toBe($this->b);
    expect($c[$itens[1]]['melhor_contact_id'])->toBe($this->b);
    expect($c[$itens[2]]['melhor_contact_id'])->toBeNull();
    expect($c[$itens[0]]['respostas'])->toHaveCount(2);
    expect(DB::table('compras_cotacoes')->where('id', $id)->value('status'))->toBe('aberta');
});

it('responder de novo substitui a resposta do fornecedor', function () {
    $id = cotCriar($this);
    $item = (int) DB::table('compras_cotacao_itens')->where('cotacao_id', $id)->orderBy('id')->value('id');
    $this->svc->responder($this->biz, $id, $this->a, [$item => ['preco_unitario' => '10.00']]);
    $this->svc->responder($this->biz, $id, $this->a, [$item => ['preco_unitario' => '8.00', 'prazo_dias' => 2]]);

    expect(DB::table('compras_cotacao_respostas')->where('item_id', $item)->count())->toBe(1);
    expect((float) DB::table('compras_cotacao_respostas')->where('item_id', $item)->value('preco_unitario'))->toBe(8.0);
});

it('a cotação de uma requisição traz os itens dela', function () {
    $req = DB::table('transactions')->insertGetId([
        'business_id' => $this->biz, 'location_id' => $this->loc, 'type' => 'purchase_requisition', 'status' => 'ordered',
        'transaction_date' => now(), 'created_by' => $this->uid, 'essentials_duration' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('purchase_lines')->insert([
        'transaction_id' => $req, 'product_id' => $this->lona->productId, 'variation_id' => $this->lona->variationId(),
        'quantity' => 12, 'purchase_price' => 0, 'purchase_price_inc_tax' => 0, 'item_tax' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $id = $this->svc->daRequisicao($this->biz, $req, [$this->a], $this->uid);

    $c = DB::table('compras_cotacoes')->where('id', $id)->first();
    expect($c->origem)->toBe('requisicao');
    expect((int) $c->origem_id)->toBe($req);
    expect((float) DB::table('compras_cotacao_itens')->where('cotacao_id', $id)->value('quantidade'))->toBe(12.0);
});

it('não aceita fornecedor, requisição nem cotação de outra empresa', function () {
    if ($this->alheio === null) {
        $this->markTestSkipped('Sem empresa adversária fictícia (99 ou 2) neste ambiente.');
    }
    expect(fn () => $this->svc->criar($this->biz, $this->loc, [['variation_id' => $this->lona->variationId(), 'quantidade' => '1']], [$this->alheio], $this->uid))
        ->toThrow(InvalidArgumentException::class, 'Fornecedor inválido para este negócio.');

    $id = cotCriar($this);
    expect(fn () => $this->svc->comparar($this->outra, $id))->toThrow(InvalidArgumentException::class, 'Cotação inválida para este negócio.');
    expect(fn () => $this->svc->responder($this->biz, $id, $this->alheio, []))->toThrow(InvalidArgumentException::class, 'Este fornecedor não foi convidado para a cotação.');
});
