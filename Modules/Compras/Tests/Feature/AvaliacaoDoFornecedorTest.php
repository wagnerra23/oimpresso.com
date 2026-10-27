<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Modules\Compras\Services\AvaliacaoDoFornecedor;
use Modules\Compras\Services\CotacaoDeCompra;
use Tests\Support\EstoqueFixture;

/**
 * US-COM-033 — avaliação automática do fornecedor por entrega (decisão coordenação 2026-10-26).
 *
 * Contrato (SPEC Compras US-COM-033): por fornecedor, quantas entregas chegaram no prazo e completas,
 * calculado das entregas registradas, mais a reprovação na inspeção (US-COM-034). Cenário à mão:
 *   Fornecedor A
 *     pedido P1: 10 un, prevista hoje−10, concluído
 *       compra C1 em hoje−12 recebe 6 (no prazo) · compra C2 em hoje−8 recebe 4 (atrasada)
 *     pedido P2: 10 un, prevista hoje−1 (vencida), recebeu 5
 *     inspeção da C2: 4 inspecionadas, 1 reprovada
 *     → entregas 2 · no prazo 1/2 = 50,0 · completas (10+5)/(10+10) = 75,0 · reprovação 1/4 = 25,0
 *   Fornecedor B: nenhum dado → os três eixos nulos (nunca 0 nem 100)
 *
 * Tenant 98 (ADR 0358); adversário 99 ou o biz=2 do seed, nunca 1 nem 4.
 *
 * @covers-uc UC-COT-01 — memory/requisitos/Compras/_telas/cotacao.casos.md
 * @covers-uc UC-COT-02 — memory/requisitos/Compras/_telas/cotacao.casos.md
 * @covers-us US-COM-033
 */
uses(Tests\TestCase::class, DatabaseTransactions::class);

beforeEach(function () {
    if (! EstoqueFixture::schemaReady() || ! \Schema::hasTable('compras_inspecoes') || ! \Schema::hasTable('compras_cotacoes')) {
        $this->markTestSkipped('Schema MySQL com as migrations de Compras ausente — rode no CT 100 / lane compras-pest.');
    }
    $this->biz = EstoqueFixture::businessId();
    $this->uid = EstoqueFixture::userId($this->biz);
    $this->loc = EstoqueFixture::locationId($this->biz, '-AVAL');
    $this->prod = EstoqueFixture::singleProduct($this->biz);
    $forn = fn (int $biz, string $nome) => DB::table('contacts')->insertGetId([
        'business_id' => $biz, 'type' => 'supplier', 'name' => $nome, 'contact_id' => 'CT-AVAL-'.uniqid(),
        'created_by' => EstoqueFixture::userId($biz), 'created_at' => now(), 'updated_at' => now(),
    ]);
    $this->a = $forn($this->biz, 'Fornecedor A AVAL');
    $this->b = $forn($this->biz, 'Fornecedor B AVAL');

    $tx = fn (string $tipo, string $status, $data, $prevista = null) => DB::table('transactions')->insertGetId([
        'business_id' => $this->biz, 'location_id' => $this->loc, 'contact_id' => $this->a, 'type' => $tipo,
        'status' => $status, 'ref_no' => 'AVAL-'.uniqid(), 'transaction_date' => $data, 'delivery_date' => $prevista,
        'final_total' => 0, 'created_by' => $this->uid, 'essentials_duration' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $linha = fn (int $txId, string $qtd, string $recebido = '0', ?int $daLinha = null) => DB::table('purchase_lines')->insertGetId([
        'transaction_id' => $txId, 'product_id' => $this->prod->productId, 'variation_id' => $this->prod->variationId(),
        'quantity' => $qtd, 'po_quantity_purchased' => $recebido, 'purchase_order_line_id' => $daLinha,
        'purchase_price' => 1, 'purchase_price_inc_tax' => 1, 'item_tax' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $p1 = $tx('purchase_order', 'completed', now()->subDays(20), now()->subDays(10));
    $l1 = $linha($p1, '10', '10');
    $p2 = $tx('purchase_order', 'partial', now()->subDays(15), now()->subDays(1));
    $l2 = $linha($p2, '10', '5');
    $c1 = $tx('purchase', 'received', now()->subDays(12));
    $linha($c1, '6', '0', $l1);
    $c2 = $tx('purchase', 'received', now()->subDays(8));
    $linhaC2 = $linha($c2, '4', '0', $l1);
    DB::table('compras_inspecoes')->insert([
        'business_id' => $this->biz, 'transaction_id' => $c2, 'purchase_line_id' => $linhaC2, 'contact_id' => $this->a,
        'quantidade_inspecionada' => '4', 'quantidade_aprovada' => '3', 'quantidade_reprovada' => '1',
        'resultado' => 'parcial', 'motivo' => 'teste', 'inspecionado_por' => $this->uid, 'created_at' => now(), 'updated_at' => now(),
    ]);
    // A compra de P2 (5 recebidas) não liga linha: só pesa em "completas", não em entregas.
    unset($l2);

    $this->svc = app(AvaliacaoDoFornecedor::class);
});

it('UC-COT-01 calcula prazo, completude e reprovação do fornecedor pelas entregas', function () {
    $r = $this->svc->porFornecedor($this->biz, [$this->a, $this->b]);

    expect($r[$this->a]['entregas'])->toBe(2);
    expect($r[$this->a]['no_prazo'])->toBe(50.0);
    expect($r[$this->a]['completas'])->toBe(75.0);
    expect($r[$this->a]['reprovacao'])->toBe(25.0);

    expect($r[$this->b])->toBe(['entregas' => 0, 'no_prazo' => null, 'completas' => null, 'inspecionado' => '0', 'reprovacao' => null]);
});

it('UC-COT-01 a cotação traz a avaliação de cada fornecedor convidado', function () {
    $cot = app(CotacaoDeCompra::class);
    $id = $cot->criar($this->biz, $this->loc, [['variation_id' => $this->prod->variationId(), 'quantidade' => '5']], [$this->a, $this->b], $this->uid);

    $av = $cot->avaliacaoDosFornecedores($this->biz, $id);

    expect(array_keys($av))->toEqualCanonicalizing([$this->a, $this->b]);
    expect($av[$this->a]['no_prazo'])->toBe(50.0);
    expect($av[$this->b]['entregas'])->toBe(0);
});

it('UC-COT-02 Tier 0: a avaliação pela outra empresa não enxerga as entregas desta, nem a cotação dela', function () {
    $outra = (int) DB::table('business')->whereIn('id', [99, 2])->where('id', '!=', $this->biz)->orderByDesc('id')->value('id');
    if ($outra <= 0) {
        $this->markTestSkipped('Sem empresa adversária fictícia (99 ou 2) neste ambiente.');
    }
    // Controle positivo: pela empresa certa o fornecedor A TEM dado.
    expect($this->svc->porFornecedor($this->biz, [$this->a])[$this->a]['entregas'])->toBe(2);

    $alheia = $this->svc->porFornecedor($outra, [$this->a]);
    expect($alheia[$this->a])->toBe(['entregas' => 0, 'no_prazo' => null, 'completas' => null, 'inspecionado' => '0', 'reprovacao' => null]);

    $cot = app(CotacaoDeCompra::class);
    $id = $cot->criar($this->biz, $this->loc, [['variation_id' => $this->prod->variationId(), 'quantidade' => '5']], [$this->a], $this->uid);
    expect(fn () => $cot->avaliacaoDosFornecedores($outra, $id))->toThrow(InvalidArgumentException::class, 'Cotação inválida');
});
