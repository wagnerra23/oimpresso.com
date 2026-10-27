<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Compras\Services\NecessidadeDeCompra;
use Tests\Support\EstoqueFixture;

/**
 * US-COM-030 — MRP: necessidade de compra somando as OS e o estoque mínimo, com o prazo do fornecedor.
 *
 * Contrato (SPEC Compras US-COM-030): necessidade = comprometido + mínimo − saldo − a caminho, por
 * insumo e local; pedir até = primeiro prazo prometido das OS que reservaram − prazo do fornecedor.
 *
 * Regra mestre (estoque, leitura): os esperados estão escritos À MÃO abaixo (caminho A) e o serviço
 * calcula de outra fonte, as tabelas (caminho B). Cenário da lona, em unidades:
 *   saldo 3 · mínimo 5 · reservado para a OS 8 · compra pendente 2 · pedido aberto 10 com 9 recebidos (falta 1)
 *   a caminho = 2 + 1 = 3 · necessidade = 8 + 5 − 3 − 3 = 7
 *   OS prometida para hoje+10 · prazo do fornecedor 4 dias → pedir até hoje+6 (não atrasado)
 * O tecido (saldo 10, mínimo 5, nada reservado) não precisa de compra e não aparece.
 *
 * Tenant 98 (ADR 0358); adversário 99 ou o biz=2 do seed, nunca 1 nem 4.
 *
 * @covers-us US-COM-030
 */
uses(Tests\TestCase::class, DatabaseTransactions::class);

beforeEach(function () {
    if (! EstoqueFixture::schemaReady() || ! \Schema::hasTable('compras_prazo_fornecedor') || ! \Schema::hasTable('stock_reservations')) {
        $this->markTestSkipped('Schema MySQL com as migrations de Compras ausente — rode no CT 100 / lane compras-pest.');
    }
    $this->biz = EstoqueFixture::businessId();
    $this->hoje = Carbon::parse('2026-11-02');
    $this->loc = EstoqueFixture::locationId($this->biz, '-MRP');
    $uid = EstoqueFixture::userId($this->biz);

    $this->lona = EstoqueFixture::singleProduct($this->biz);
    $this->tecido = EstoqueFixture::singleProduct($this->biz);
    DB::table('products')->whereIn('id', [$this->lona->productId, $this->tecido->productId])->update(['alert_quantity' => 5]);
    EstoqueFixture::setStock($this->lona, 0, $this->loc, 3);
    EstoqueFixture::setStock($this->tecido, 0, $this->loc, 10);

    $this->fornecedor = DB::table('contacts')->insertGetId([
        'business_id' => $this->biz, 'type' => 'supplier', 'name' => 'Fornecedor MRP',
        'contact_id' => 'CT-MRP-' . uniqid(), 'created_by' => $uid, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $tx = fn (string $type, string $status) => DB::table('transactions')->insertGetId([
        'business_id' => $this->biz, 'location_id' => $this->loc, 'contact_id' => $this->fornecedor,
        'type' => $type, 'status' => $status, 'transaction_date' => $this->hoje->copy()->subDays(30),
        'created_by' => $uid, 'essentials_duration' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $linha = fn (int $t, float $q, float $recebido = 0) => DB::table('purchase_lines')->insert([
        'transaction_id' => $t, 'product_id' => $this->lona->productId, 'variation_id' => $this->lona->variationId(),
        'quantity' => $q, 'po_quantity_purchased' => $recebido, 'purchase_price' => 10, 'purchase_price_inc_tax' => 10,
        'item_tax' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $linha($tx('purchase', 'received'), 3);          // última compra recebida → define o fornecedor
    $linha($tx('purchase', 'pending'), 2);           // a caminho
    $linha($tx('purchase_order', 'partial'), 10, 9); // falta 1 do pedido

    DB::table('compras_prazo_fornecedor')->insert([
        'business_id' => $this->biz, 'product_id' => $this->lona->productId, 'contact_id' => $this->fornecedor,
        'dias' => 4, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $venda = $tx('sell', 'final');
    $ordem = \Schema::hasTable('pcp_ordens') && \Schema::hasColumn('pcp_ordens', 'prazo_prometido')
        ? DB::table('pcp_ordens')->insertGetId([
            'business_id' => $this->biz, 'transaction_id' => $venda, 'transaction_sell_line_id' => random_int(900000000, 999999999),
            'product_id' => $this->tecido->productId, 'quantidade' => 1, 'prazo_prometido' => $this->hoje->copy()->addDays(10)->toDateString(),
            'created_at' => now(), 'updated_at' => now(),
        ])
        : null;
    $this->temOs = $ordem !== null;
    DB::table('stock_reservations')->insert(array_filter([
        'business_id' => $this->biz, 'transaction_id' => $venda, 'pcp_ordem_id' => $ordem,
        'product_id' => $this->lona->productId, 'variation_id' => $this->lona->variationId(),
        'location_id' => $this->loc, 'qty_reserved' => 8, 'status' => 'active',
        'created_at' => now(), 'updated_at' => now(),
    ], fn ($v) => $v !== null));
});

function mrpLinha($t, int $variationId): ?array
{
    foreach (app(NecessidadeDeCompra::class)->listar($t->biz, $t->hoje) as $l) {
        if ($l['variation_id'] === $variationId && $l['location_id'] === $t->loc) {
            return $l;
        }
    }

    return null;
}

it('a conta da necessidade é comprometido + mínimo − saldo − a caminho, nunca negativa', function () {
    expect(NecessidadeDeCompra::necessidade(80000, 50000, 30000, 30000))->toBe(70000);
    expect(NecessidadeDeCompra::necessidade(0, 50000, 100000, 0))->toBe(0);
});

it('soma reserva da OS, mínimo, saldo e o que está a caminho: a lona precisa de 7', function () {
    $l = mrpLinha($this, $this->lona->variationId());

    expect($l)->not->toBeNull('A lona deveria aparecer no MRP.');
    expect($l['saldo'])->toBe('3.0000');
    expect($l['comprometido'])->toBe('8.0000');
    expect($l['minimo'])->toBe('5.0000');
    expect($l['a_caminho'])->toBe('3.0000');
    expect($l['necessidade'])->toBe('7.0000');
    expect($l['fornecedor_id'])->toBe($this->fornecedor);
    expect($l['prazo_dias'])->toBe(4);
});

it('pedir até = prazo prometido da OS − prazo do fornecedor', function () {
    if (! $this->temOs) {
        $this->markTestSkipped('pcp_ordens.prazo_prometido ausente neste schema.');
    }
    $l = mrpLinha($this, $this->lona->variationId());

    expect($l['precisa_em'])->toBe('2026-11-12');
    expect($l['pedir_ate'])->toBe('2026-11-08');
    expect($l['atrasado'])->toBeFalse();
});

it('o que não precisa de compra não aparece', function () {
    expect(mrpLinha($this, $this->tecido->variationId()))->toBeNull();
});

it('não traz insumo de outra empresa', function () {
    $outra = (int) DB::table('business')->whereIn('id', [99, 2])->where('id', '!=', $this->biz)->orderByDesc('id')->value('id');
    if ($outra <= 0) {
        $this->markTestSkipped('Sem empresa adversária fictícia (99 ou 2) neste ambiente.');
    }
    $alheio = EstoqueFixture::singleProduct($outra);
    DB::table('products')->where('id', $alheio->productId)->update(['alert_quantity' => 50]);
    EstoqueFixture::setStock($alheio, 0, EstoqueFixture::locationId($outra, '-MRP'), 0);

    $ids = array_column(app(NecessidadeDeCompra::class)->listar($this->biz, $this->hoje), 'variation_id');
    expect($ids)->not->toContain($alheio->variationId());
});
