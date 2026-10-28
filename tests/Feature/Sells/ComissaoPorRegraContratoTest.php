<?php
// @covers-us US-COMM-015

declare(strict_types=1);

use App\Business;
use App\Services\Sells\ComissaoPorRegra;
use App\Transaction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EstoqueFixture;

/**
 * UC-COMR-01..05 · US-COMM-015 — comissão por regra (vendedor, produto ou categoria; % ou valor
 * por unidade; papel vendedor ou técnico da OS).
 *
 * Âncora: resources/js/Pages/Sells/Show.casos.md (UC-COMR-*), derivado da US-COMM-015.
 *
 * Números feitos à mão (os mesmos do corpo do PR, tabela antes→depois):
 *   linha A (produto A, categoria C) 3 × 100,00 = 300,00 → regra do produto A só do vendedor, 10% = 30,00
 *   linha B (produto B, categoria C) 2 × 50,00  = 100,00 → regra da categoria C, 5%             =  5,00
 *   linha D (produto D, sem categoria) 1 × 80,00 =  80,00 → regra geral do vendedor, 2%         =  1,60
 *   vendedor = 36,60 · técnico da OS: produto A, R$ 4,50 por unidade × 3 = 13,50 · total 50,10
 */
uses(DatabaseTransactions::class, \Tests\Support\WithSeededTenant::class);

beforeEach(function () {
    if (! EstoqueFixture::schemaReady() || ! Schema::hasTable('commission_rules') || ! Schema::hasTable('repair_job_sheets')) {
        $this->markTestSkipped('Schema ausente — roda na lane MySQL (sells-pest).');
    }
    config(['sells.comissao.por_regra' => true]);
    $this->biz = EstoqueFixture::businessId();
    $this->negocio = Business::findOrFail($this->biz);
    $dono = EstoqueFixture::userId($this->biz);
    $loc = EstoqueFixture::locationId($this->biz, 'COMR');

    $this->vendedor = $this->usuarioComPermissoes(['sell.view'], $this->negocio);
    $this->tecnico = $this->usuarioComPermissoes(['sell.view'], $this->negocio);
    $catC = (int) DB::table('categories')->insertGetId(['name' => 'Cat COMR', 'business_id' => $this->biz,
        'parent_id' => 0, 'created_by' => $dono, 'category_type' => 'product', 'created_at' => now(), 'updated_at' => now()]);

    $a = EstoqueFixture::singleProduct($this->biz);
    $b = EstoqueFixture::singleProduct($this->biz);
    $d = EstoqueFixture::singleProduct($this->biz);
    DB::table('products')->whereIn('id', [$a->productId, $b->productId])->update(['category_id' => $catC]);
    DB::table('products')->where('id', $d->productId)->update(['category_id' => null]);
    $this->produtoA = $a->productId;

    $v = EstoqueFixture::saleWithLine($a, 0, $loc, 3.0, 100.0);
    $this->linhaA = $v['sell_line_id'];
    foreach ([[$b, 2.0, 50.0], [$d, 1.0, 80.0]] as [$p, $q, $u]) {
        DB::table('transaction_sell_lines')->insert(['transaction_id' => $v['transaction_id'], 'product_id' => $p->productId,
            'variation_id' => $p->variations[0]['variation_id'], 'quantity' => $q, 'quantity_returned' => 0,
            'unit_price' => $u, 'unit_price_inc_tax' => $u, 'item_tax' => 0, 'created_at' => now(), 'updated_at' => now()]);
    }
    $cliente = (int) DB::table('contacts')->insertGetId(['business_id' => $this->biz, 'type' => 'customer', 'name' => 'Cliente COMR',
        'contact_id' => 'COMR-' . uniqid(), 'created_by' => $dono, 'created_at' => now(), 'updated_at' => now()]);
    $os = (int) DB::table('repair_job_sheets')->insertGetId(['business_id' => $this->biz, 'contact_id' => $cliente,
        'job_sheet_no' => 'COMR-' . uniqid(), 'service_type' => 'carry_in', 'serial_no' => 'X', 'status_id' => 0,
        'service_staff' => $this->tecnico->id, 'created_by' => $dono, 'created_at' => now(), 'updated_at' => now()]);
    DB::table('transactions')->where('id', $v['transaction_id'])
        ->update(['commission_agent' => $this->vendedor->id, 'repair_job_sheet_id' => $os]);
    $this->venda = Transaction::findOrFail($v['transaction_id']);

    $regra = fn (array $r) => DB::table('commission_rules')->insert($r + ['business_id' => $this->biz, 'ativo' => 1,
        'tipo' => 'percentual', 'created_at' => now(), 'updated_at' => now()]);
    $regra(['papel' => 'vendedor', 'escopo' => 'vendedor', 'valor' => 2]);
    $regra(['papel' => 'vendedor', 'escopo' => 'categoria', 'category_id' => $catC, 'valor' => 5]);
    $regra(['papel' => 'vendedor', 'escopo' => 'produto', 'product_id' => $this->produtoA, 'user_id' => $this->vendedor->id, 'valor' => 10]);
    $regra(['papel' => 'tecnico', 'escopo' => 'produto', 'product_id' => $this->produtoA, 'tipo' => 'valor', 'valor' => 4.5]);
    $this->regra = $regra;

    $this->papel = fn (array $r, string $p) => collect($r['beneficiarios'])->firstWhere('papel', $p);
});

it('UC-COMR-01 a regra mais específica vale por linha: produto > categoria > geral do vendedor', function () {
    $r = app(ComissaoPorRegra::class)->daVenda($this->venda);
    $vend = ($this->papel)($r, 'vendedor');

    expect($vend['user_id'])->toBe($this->vendedor->id);
    expect(collect($vend['linhas'])->pluck('comissao')->all())->toEqual([30.0, 5.0, 1.6]);
    expect($vend['total'])->toBe(36.6);
});

it('UC-COMR-02 o técnico da OS ganha pela regra do papel técnico, em valor por unidade', function () {
    $r = app(ComissaoPorRegra::class)->daVenda($this->venda);
    $tec = ($this->papel)($r, 'tecnico');

    expect($tec['user_id'])->toBe($this->tecnico->id);
    expect($tec['total'])->toBe(13.5);
    expect(count($tec['linhas']))->toBe(1);
    expect($r['total'])->toBe(50.1);
});

it('UC-COMR-03 quantidade devolvida sai da base e da comissão', function () {
    DB::table('transaction_sell_lines')->where('id', $this->linhaA)->update(['quantity_returned' => 1]);
    $r = app(ComissaoPorRegra::class)->daVenda($this->venda->fresh());

    expect(($this->papel)($r, 'vendedor')['linhas'][0]['comissao'])->toBe(20.0);
    expect(($this->papel)($r, 'tecnico')['total'])->toBe(9.0);
});

it('UC-COMR-04 desligada, a rota responde 404 e nada é calculado', function () {
    config(['sells.comissao.por_regra' => false]);
    $user = $this->usuarioComPermissoes(['sell.view'], $this->negocio);

    $this->actingAs($user)->withSession(['user.business_id' => $this->biz, 'business.id' => $this->biz])
        ->getJson("/sells/{$this->venda->id}/comissao-por-regra")->assertNotFound();
});

it('UC-COMR-05 ligada, devolve o cálculo; regra de outra empresa não entra; venda de outra empresa é 404', function () {
    $outra = EstoqueFixture::secondBusinessId();
    if ($outra) {
        DB::table('commission_rules')->insert(['business_id' => $outra, 'papel' => 'vendedor', 'escopo' => 'vendedor',
            'tipo' => 'percentual', 'valor' => 50, 'ativo' => 1, 'created_at' => now(), 'updated_at' => now()]);
    }
    $user = $this->usuarioComPermissoes(['sell.view'], $this->negocio);

    $this->actingAs($user)->withSession(['user.business_id' => $this->biz, 'business.id' => $this->biz])
        ->getJson("/sells/{$this->venda->id}/comissao-por-regra")
        ->assertOk()->assertJsonPath('total', 50.1);

    if ($outra) {
        $deFora = $this->usuarioComPermissoes(['sell.view'], Business::findOrFail($outra));
        $this->actingAs($deFora)->withSession(['user.business_id' => $outra, 'business.id' => $outra])
            ->getJson("/sells/{$this->venda->id}/comissao-por-regra")->assertNotFound();
    }
});
