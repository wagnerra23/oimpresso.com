<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Vitrine pública sem login: só aparece o que o lojista deixou à venda. Produto desativado não
 * entra na listagem nem abre por URL; "não vender" idem; produto de outra empresa nunca aparece.
 *
 * Mora em tests/Feature/Sells porque os testes de Modules/ProductCatalogue/Tests não rodam em lane
 * nenhuma (mesma razão do CatalogueQrContratoTest). Lane: sells-pest.yml (MySQL).
 * Tenant 98 contra o adversário 99 (ADR 0358). Lê o corpo da resposta, não só o status.
 */

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasTable('business_locations')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }

    $this->business = $this->seededTenant();
    $this->userId = (int) $this->business->owner_id;
    $this->local = cpsitLocal($this->business->id, '__CPSIT_LOCAL_A__');
});

function cpsitLocal(int $bizId, string $nome): int
{
    $scheme = DB::table('invoice_schemes')->where('business_id', $bizId)->value('id') ?? DB::table('invoice_schemes')->value('id');
    $layout = DB::table('invoice_layouts')->where('business_id', $bizId)->value('id') ?? DB::table('invoice_layouts')->value('id');
    if (! $scheme || ! $layout) {
        test()->markTestSkipped('Sem invoice_scheme/invoice_layout no banco — as FKs de business_locations não teriam alvo.');
    }

    return (int) DB::table('business_locations')->insertGetId([
        'business_id' => $bizId, 'invoice_scheme_id' => $scheme, 'invoice_layout_id' => $layout,
        'name' => $nome, 'country' => 'Brasil', 'state' => 'SC', 'city' => 'Cidade Teste', 'zip_code' => '00000',
        'is_active' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function cpsitProduto(int $bizId, int $userId, int $localId, string $nome, array $campos = []): int
{
    $carimbo = uniqid();
    $unit = DB::table('units')->where('business_id', $bizId)->value('id') ?? DB::table('units')->insertGetId([
        'business_id' => $bizId, 'actual_name' => 'Unidade vitrine', 'short_name' => 'Un', 'allow_decimal' => 1,
        'created_by' => $userId, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $produto = (int) DB::table('products')->insertGetId(array_merge([
        'business_id' => $bizId, 'name' => $nome, 'type' => 'single', 'unit_id' => $unit,
        'tax_type' => 'exclusive', 'enable_stock' => 0, 'sku' => 'CPSIT-' . $carimbo, 'barcode_type' => 'C128',
        'created_by' => $userId, 'created_at' => now(), 'updated_at' => now(),
    ], $campos));
    $grupo = DB::table('product_variations')->insertGetId([
        'product_id' => $produto, 'name' => 'DUMMY', 'is_dummy' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('variations')->insert([
        'product_id' => $produto, 'product_variation_id' => $grupo, 'name' => 'DUMMY', 'sub_sku' => 'CPSIT-' . $carimbo,
        'default_purchase_price' => 0, 'dpp_inc_tax' => 0, 'profit_percent' => 0, 'default_sell_price' => 10,
        'sell_price_inc_tax' => 10, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('product_locations')->insert(['product_id' => $produto, 'location_id' => $localId]);

    return $produto;
}

test('listagem: mostra o produto à venda e esconde desativado e "não vender"', function () {
    cpsitProduto($this->business->id, $this->userId, $this->local, '__CPSIT_VISIVEL__');
    cpsitProduto($this->business->id, $this->userId, $this->local, '__CPSIT_DESATIVADO__', ['is_inactive' => 1]);
    cpsitProduto($this->business->id, $this->userId, $this->local, '__CPSIT_NAOVENDA__', ['not_for_selling' => 1]);

    $r = $this->get("/catalogue/{$this->business->id}/{$this->local}");

    $r->assertOk();
    $corpo = (string) $r->getContent();
    expect($corpo)->toContain('__CPSIT_VISIVEL__');
    expect($corpo)->not->toContain('__CPSIT_DESATIVADO__');
    expect($corpo)->not->toContain('__CPSIT_NAOVENDA__');
});

test('listagem: produto só de outro local não entra', function () {
    $outroLocal = cpsitLocal($this->business->id, '__CPSIT_LOCAL_B__');
    cpsitProduto($this->business->id, $this->userId, $this->local, '__CPSIT_VISIVEL__');
    cpsitProduto($this->business->id, $this->userId, $outroLocal, '__CPSIT_OUTROLOCAL__');

    $corpo = (string) $this->get("/catalogue/{$this->business->id}/{$this->local}")->assertOk()->getContent();

    expect($corpo)->toContain('__CPSIT_VISIVEL__');
    expect($corpo)->not->toContain('__CPSIT_OUTROLOCAL__');
});

test('listagem: produto da empresa 99 não aparece na vitrine da 98, mesmo ligado ao local dela', function () {
    $adversaria = $this->seededSupportClientTenant();
    cpsitProduto($this->business->id, $this->userId, $this->local, '__CPSIT_VISIVEL__');
    // O vínculo forjado é o que faz o caso depender do filtro de empresa, e não só do filtro de local.
    cpsitProduto($adversaria->id, (int) $adversaria->owner_id, $this->local, '__CPSIT_ALHEIO__');

    $corpo = (string) $this->get("/catalogue/{$this->business->id}/{$this->local}")->assertOk()->getContent();

    expect($corpo)->toContain('__CPSIT_VISIVEL__');
    expect($corpo)->not->toContain('__CPSIT_ALHEIO__');
});

test('detalhe: abre o produto à venda; desativado e "não vender" dão 404', function () {
    $visivel = cpsitProduto($this->business->id, $this->userId, $this->local, '__CPSIT_VISIVEL__');
    $desativado = cpsitProduto($this->business->id, $this->userId, $this->local, '__CPSIT_DESATIVADO__', ['is_inactive' => 1]);
    $naoVenda = cpsitProduto($this->business->id, $this->userId, $this->local, '__CPSIT_NAOVENDA__', ['not_for_selling' => 1]);

    $ok = $this->get("/show-catalogue/{$this->business->id}/{$visivel}");
    $ok->assertOk();
    expect((string) $ok->getContent())->toContain('__CPSIT_VISIVEL__');

    $r1 = $this->get("/show-catalogue/{$this->business->id}/{$desativado}");
    $r1->assertNotFound();
    expect((string) $r1->getContent())->not->toContain('__CPSIT_DESATIVADO__');

    $r2 = $this->get("/show-catalogue/{$this->business->id}/{$naoVenda}");
    $r2->assertNotFound();
    expect((string) $r2->getContent())->not->toContain('__CPSIT_NAOVENDA__');
});

test('detalhe: com location_id só abre produto vendido naquele local', function () {
    $outroLocal = cpsitLocal($this->business->id, '__CPSIT_LOCAL_B__');
    $produto = cpsitProduto($this->business->id, $this->userId, $this->local, '__CPSIT_VISIVEL__');

    $ok = $this->get("/show-catalogue/{$this->business->id}/{$produto}?location_id={$this->local}");
    $ok->assertOk();
    expect((string) $ok->getContent())->toContain('__CPSIT_VISIVEL__');

    $r = $this->get("/show-catalogue/{$this->business->id}/{$produto}?location_id={$outroLocal}");
    $r->assertNotFound();
    expect((string) $r->getContent())->not->toContain('__CPSIT_VISIVEL__');
});

test('detalhe: URL sem local segue abrindo produto à venda de qualquer local da empresa', function () {
    $produto = cpsitProduto($this->business->id, $this->userId, $this->local, '__CPSIT_VISIVEL__');

    $r = $this->get("/show-catalogue/{$this->business->id}/{$produto}");

    $r->assertOk();
    expect((string) $r->getContent())->toContain('__CPSIT_VISIVEL__');
});

test('detalhe: produto da empresa 99 dá 404 pela URL da 98', function () {
    $adversaria = $this->seededSupportClientTenant();
    $alheio = cpsitProduto($adversaria->id, (int) $adversaria->owner_id, $this->local, '__CPSIT_ALHEIO__');

    $r = $this->get("/show-catalogue/{$this->business->id}/{$alheio}");

    $r->assertNotFound();
    expect((string) $r->getContent())->not->toContain('__CPSIT_ALHEIO__');
});
