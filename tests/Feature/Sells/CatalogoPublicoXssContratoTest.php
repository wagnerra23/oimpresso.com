<?php

declare(strict_types=1);
// @covers-us US-PCAT-002

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Vitrine pública sem login (/catalogue e /show-catalogue): o que o lojista grava não pode virar
 * script na origem do ERP. A descrição do produto é HTML por desenho (editor rico) e passa por
 * allowlist; o endereço do local é texto puro e sai escapado.
 *
 * Mora em tests/Feature/Sells porque os testes de Modules/ProductCatalogue/Tests não rodam em lane
 * nenhuma (mesma razão do CatalogueQrContratoTest). Lane: sells-pest.yml (MySQL). Tenant 98 (ADR 0358).
 * Lê o corpo da resposta: status 200 sozinho não diz nada sobre o que chegou ao navegador.
 */

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasTable('business_locations')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }

    $this->business = $this->seededTenant();
});

function cpxssLocal(int $bizId, array $campos = []): int
{
    $scheme = DB::table('invoice_schemes')->where('business_id', $bizId)->value('id') ?? DB::table('invoice_schemes')->value('id');
    $layout = DB::table('invoice_layouts')->where('business_id', $bizId)->value('id') ?? DB::table('invoice_layouts')->value('id');
    if (! $scheme || ! $layout) {
        test()->markTestSkipped('Sem invoice_scheme/invoice_layout no banco — as FKs de business_locations não teriam alvo.');
    }

    return (int) DB::table('business_locations')->insertGetId(array_merge([
        'business_id' => $bizId, 'invoice_scheme_id' => $scheme, 'invoice_layout_id' => $layout,
        'name' => '__CPXSS_LOCAL__', 'country' => 'Brasil', 'state' => 'SC', 'city' => 'Cidade Teste', 'zip_code' => '00000',
        'is_active' => 1, 'created_at' => now(), 'updated_at' => now(),
    ], $campos));
}

function cpxssProduto(int $bizId, int $userId, int $localId, ?string $descricao): int
{
    $carimbo = uniqid();
    $unit = DB::table('units')->where('business_id', $bizId)->value('id') ?? DB::table('units')->insertGetId([
        'business_id' => $bizId, 'actual_name' => 'Unidade vitrine', 'short_name' => 'Un', 'allow_decimal' => 1,
        'created_by' => $userId, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $produto = (int) DB::table('products')->insertGetId([
        'business_id' => $bizId, 'name' => '__CPXSS_PRODUTO__', 'type' => 'single', 'unit_id' => $unit,
        'tax_type' => 'exclusive', 'enable_stock' => 0, 'sku' => 'CPXSS-' . $carimbo, 'barcode_type' => 'C128',
        'product_description' => $descricao, 'created_by' => $userId, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $grupo = DB::table('product_variations')->insertGetId([
        'product_id' => $produto, 'name' => 'DUMMY', 'is_dummy' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('variations')->insert([
        'product_id' => $produto, 'product_variation_id' => $grupo, 'name' => 'DUMMY', 'sub_sku' => 'CPXSS-' . $carimbo,
        'default_purchase_price' => 0, 'dpp_inc_tax' => 0, 'profit_percent' => 0, 'default_sell_price' => 10,
        'sell_price_inc_tax' => 10, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('product_locations')->insert(['product_id' => $produto, 'location_id' => $localId]);

    return $produto;
}

test('detalhe público: script e handler da descrição somem, e a formatação permitida fica', function () {
    $local = cpxssLocal($this->business->id);
    $produto = cpxssProduto($this->business->id, (int) $this->business->owner_id, $local,
        '<b>formatacao-permitida</b>'
        . '<script>alert("xss-marcador-script")</script>'
        . '<img src="/img/x.png" onerror="alert(1)">'
        . '<a href="javascript:alert(2)">link</a>'
    );

    $r = $this->get("/show-catalogue/{$this->business->id}/{$produto}");

    // Controle positivo: abriu o produto certo, e a descrição chegou ao HTML.
    $r->assertOk();
    $corpo = (string) $r->getContent();
    expect($corpo)->toContain('__CPXSS_PRODUTO__');
    expect($corpo)->toContain('<b>formatacao-permitida</b>');

    expect($corpo)->not->toContain('xss-marcador-script');
    expect($corpo)->not->toContain('onerror');
    expect($corpo)->not->toContain('javascript:alert');
});

test('detalhe público: produto sem descrição abre normalmente', function () {
    $local = cpxssLocal($this->business->id);
    $produto = cpxssProduto($this->business->id, (int) $this->business->owner_id, $local, null);

    $r = $this->get("/show-catalogue/{$this->business->id}/{$produto}");

    $r->assertOk();
    expect((string) $r->getContent())->toContain('__CPXSS_PRODUTO__');
});

test('vitrine pública: endereço do local sai escapado e mantém a quebra de linha', function () {
    $local = cpxssLocal($this->business->id, ['landmark' => '<img src=x onerror=alert(3)>']);
    cpxssProduto($this->business->id, (int) $this->business->owner_id, $local, null);

    $r = $this->get("/catalogue/{$this->business->id}/{$local}");

    // Controle positivo: é a vitrine da empresa, com o produto do local.
    $r->assertOk();
    $corpo = (string) $r->getContent();
    expect($corpo)->toContain('__CPXSS_LOCAL__');
    expect($corpo)->toContain('__CPXSS_PRODUTO__');

    expect($corpo)->not->toContain('<img src=x onerror');
    expect($corpo)->toContain('&lt;img src=x onerror=alert(3)&gt;, Cidade Teste, SC, 00000<br>Brasil');
});
