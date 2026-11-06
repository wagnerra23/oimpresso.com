<?php

declare(strict_types=1);
// Cobre UC-PIDX-41 (resources/js/Pages/Produto/Index.casos.md) — G-2 rastreabilidade caso↔teste.

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EstoqueFixture;

/**
 * Giro (vendido nos últimos 90 dias) e as 3 últimas compras recebidas, na aba Estoque do painel do
 * produto (D8). Só leitura de estoque, mas a conta do vendido é provada por dois caminhos: (1) à mão;
 * (2) o "Total vendido" do Relatório de estoque (que conta desde sempre), menos a venda antiga.
 *
 * Tenant semeado do CI (ADR 0358). Nunca biz=4. Pest só no CI / CT 100.
 */
uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasColumn('transactions', 'essentials_duration')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }
    $this->business = $this->seededTenant();
    $this->inertia = [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request()),
        'X-Requested-With' => 'XMLHttpRequest',
    ];
});

function pgcEntrar($teste, array $permissoes): void
{
    $teste->user = $teste->usuarioComPermissoes($permissoes, $teste->business);
    $teste->actingAs($teste->user);
    session(['user.business_id' => $teste->business->id, 'user.id' => $teste->user->id, 'business.id' => $teste->business->id]);
    session(['currency' => ['symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ','], 'business.date_format' => 'd/m/Y']);
}

function pgcDetalhe($teste, int $id): array
{
    $r = $teste->get('/products?ver=' . $id, $teste->inertia + [
        'X-Inertia-Partial-Component' => 'Produto/Index',
        'X-Inertia-Partial-Data' => 'detalhe',
    ]);
    $r->assertOk();
    expect($r->json('component'))->toBe('Produto/Index');

    return $r->json('props.detalhe');
}

/** Venda do produto num local, há `$dias` dias. */
function pgcVenda(int $biz, object $p, int $local, string $status, float $qtd, float $devolvido, int $dias): void
{
    $venda = DB::table('transactions')->insertGetId([
        'business_id' => $biz, 'location_id' => $local, 'type' => 'sell', 'status' => $status, 'payment_status' => 'paid',
        'transaction_date' => now()->subDays($dias)->format('Y-m-d 10:00:00'), 'final_total' => $qtd * 20, 'total_before_tax' => $qtd * 20,
        'created_by' => EstoqueFixture::userId($biz), 'essentials_duration' => 0, 'invoice_no' => 'PGC-' . uniqid(), 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('transaction_sell_lines')->insert([
        'transaction_id' => $venda, 'product_id' => $p->productId, 'variation_id' => $p->variations[0]['variation_id'], 'quantity' => $qtd,
        'quantity_returned' => $devolvido, 'unit_price' => 20, 'unit_price_inc_tax' => 20, 'unit_price_before_discount' => 20,
        'item_tax' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function pgcFornecedor(int $biz, string $nome): int
{
    return (int) DB::table('contacts')->insertGetId([
        'business_id' => $biz, 'type' => 'supplier', 'name' => $nome, 'supplier_business_name' => $nome, 'mobile' => '',
        'contact_id' => 'PGC-' . uniqid(), 'created_by' => EstoqueFixture::userId($biz), 'created_at' => now(), 'updated_at' => now(),
    ]);
}

/** Compra do produto, há `$dias` dias, com uma linha por quantidade em `$linhas`. Devolve o id. */
function pgcCompra(int $biz, object $p, int $local, int $fornecedor, string $status, array $linhas, int $dias): int
{
    $compra = DB::table('transactions')->insertGetId([
        'business_id' => $biz, 'location_id' => $local, 'contact_id' => $fornecedor, 'type' => 'purchase', 'status' => $status,
        'payment_status' => 'paid', 'transaction_date' => now()->subDays($dias)->format('Y-m-d 09:00:00'),
        'final_total' => array_sum($linhas) * 10, 'total_before_tax' => array_sum($linhas) * 10,
        'created_by' => EstoqueFixture::userId($biz), 'essentials_duration' => 0, 'ref_no' => 'PGC-' . uniqid(), 'created_at' => now(), 'updated_at' => now(),
    ]);
    foreach ($linhas as $qtd) {
        DB::table('purchase_lines')->insert([
            'transaction_id' => $compra, 'product_id' => $p->productId, 'variation_id' => $p->variations[0]['variation_id'], 'quantity' => $qtd,
            'purchase_price' => 10, 'purchase_price_inc_tax' => 10, 'item_tax' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    return $compra;
}

it('UC-PIDX-41 · o vendido em 90 dias conta só as vendas finalizadas do período, menos o devolvido', function () {
    $biz = $this->business->id;
    pgcEntrar($this, ['product.view', 'access_all_locations', 'stock_report.view', 'purchase.view']);
    $p = EstoqueFixture::singleProduct($biz);
    $local = EstoqueFixture::locationId($biz);
    EstoqueFixture::setStock($p, 0, $local, 50);
    pgcVenda($biz, $p, $local, 'final', 5, 1, 30);   // entra: 4
    pgcVenda($biz, $p, $local, 'final', 3, 0, 80);   // entra: 3
    pgcVenda($biz, $p, $local, 'final', 7, 0, 120);  // fora do período
    pgcVenda($biz, $p, $local, 'draft', 9, 0, 10);   // rascunho não é venda

    $d = pgcDetalhe($this, $p->productId);

    // Caminho 1 — à mão: (5 − 1) + 3 = 7.
    expect(abs($d['vendido_90_dias'] - 7))->toBeLessThan(0.005);
    // Caminho 2 — o "Total vendido" do Relatório de estoque (desde sempre) menos a venda de 120 dias.
    $desdeSempre = collect($d['estoque'])->sum('vendido');
    expect(abs($desdeSempre - 14))->toBeLessThan(0.005);
    expect(abs($d['vendido_90_dias'] - ($desdeSempre - 7)))->toBeLessThan(0.005);
});

it('UC-PIDX-41 · as 3 últimas compras recebidas, da mais nova para a mais antiga', function () {
    $biz = $this->business->id;
    pgcEntrar($this, ['product.view', 'access_all_locations', 'stock_report.view', 'purchase.view']);
    $p = EstoqueFixture::singleProduct($biz);
    $local = EstoqueFixture::locationId($biz);
    EstoqueFixture::setStock($p, 0, $local, 50);
    $oracal = pgcFornecedor($biz, 'Oracal PGC');
    $avery = pgcFornecedor($biz, 'Avery PGC');
    pgcCompra($biz, $p, $local, $oracal, 'received', [10], 60);         // a 4ª: fica fora
    $c3 = pgcCompra($biz, $p, $local, $avery, 'received', [6], 40);
    $c2 = pgcCompra($biz, $p, $local, $oracal, 'received', [8, 4], 20); // duas linhas do produto: 12
    $c1 = pgcCompra($biz, $p, $local, $avery, 'received', [5], 5);
    pgcCompra($biz, $p, $local, $oracal, 'pending', [30], 1);           // ainda não entrou no estoque

    $compras = pgcDetalhe($this, $p->productId)['ultimas_compras'];

    expect(array_column($compras, 'id'))->toBe([$c1, $c2, $c3]);
    expect(array_column($compras, 'fornecedor'))->toBe(['Avery PGC', 'Oracal PGC', 'Avery PGC']);
    expect(array_map(fn ($c) => round($c['quantidade'], 4), $compras))->toBe([5.0, 12.0, 6.0]);
    expect($compras[0]['data'])->toBe(now()->subDays(5)->format('Y-m-d'));
    expect($compras[0]['url'])->toBe('/purchases/' . $c1);
    expect($compras[0]['local'])->toBe(DB::table('business_locations')->where('id', $local)->value('name'));
});

it('UC-PIDX-41 · sem compra recebida a lista vem vazia; sem purchase.view não vem; sem stock_report.view nenhum dos dois', function () {
    $biz = $this->business->id;
    $p = EstoqueFixture::singleProduct($biz);
    $local = EstoqueFixture::locationId($biz);
    EstoqueFixture::setStock($p, 0, $local, 5);

    pgcEntrar($this, ['product.view', 'access_all_locations', 'stock_report.view', 'purchase.view']);
    $d = pgcDetalhe($this, $p->productId);
    expect($d['ultimas_compras'])->toBe([]);
    expect(abs($d['vendido_90_dias']))->toBeLessThan(0.005);

    pgcEntrar($this, ['product.view', 'access_all_locations', 'stock_report.view']);
    $d = pgcDetalhe($this, $p->productId);
    // Controle positivo: a tabela de locais e o giro vieram.
    expect($d['estoque'])->not->toBeNull();
    expect($d['vendido_90_dias'])->not->toBeNull();
    expect($d['ultimas_compras'])->toBeNull();

    pgcEntrar($this, ['product.view', 'access_all_locations', 'purchase.view']);
    $d = pgcDetalhe($this, $p->productId);
    expect($d['vendido_90_dias'])->toBeNull();
    expect($d['ultimas_compras'])->toBeNull();
});

it('UC-PIDX-41 · quem vê um local só conta as vendas e vê as compras daquele local', function () {
    $biz = $this->business->id;
    $p = EstoqueFixture::singleProduct($biz);
    $matriz = EstoqueFixture::locationId($biz);
    $filial = EstoqueFixture::locationId($biz, 'filial');
    EstoqueFixture::setStock($p, 0, $matriz, 10);
    EstoqueFixture::setStock($p, 0, $filial, 10);
    pgcVenda($biz, $p, $matriz, 'final', 2, 0, 10);
    pgcVenda($biz, $p, $filial, 'final', 6, 0, 10);
    $forn = pgcFornecedor($biz, 'Fornecedor PGC');
    $daMatriz = pgcCompra($biz, $p, $matriz, $forn, 'received', [3], 15);
    pgcCompra($biz, $p, $filial, $forn, 'received', [9], 5);

    // `permitted_locations()` lê a permissão DIRETA do usuário, não a do papel.
    pgcEntrar($this, ['product.view', 'stock_report.view', 'purchase.view']);
    \Spatie\Permission\Models\Permission::findOrCreate('location.' . $matriz, 'web');
    $this->user->givePermissionTo('location.' . $matriz);
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    $this->user = \App\User::findOrFail($this->user->id);
    $this->actingAs($this->user);

    $d = pgcDetalhe($this, $p->productId);
    expect(abs($d['vendido_90_dias'] - 2))->toBeLessThan(0.005);
    expect(array_column($d['ultimas_compras'], 'id'))->toBe([$daMatriz]);
});
