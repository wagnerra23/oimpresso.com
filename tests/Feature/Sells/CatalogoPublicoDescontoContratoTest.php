<?php

declare(strict_types=1);
// @covers-us US-PCAT-005

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Vitrine pública sem login: o selo de desconto diz a mesma oferta que o PDV aplica.
 *
 * Contrato (lote 1 da auditoria ProductCatalogue, V1): a tabela `discounts` tem `discount_type`
 * e o PDV respeita o tipo (`resources/views/sale_pos/product_row.blade.php`, linhas do
 * `$discount_type`). Desconto `percentage` aparece como porcentagem; qualquer outro tipo
 * (`fixed`) é abatimento em dinheiro e NÃO pode aparecer com "%".
 *
 * Mora em tests/Feature/Sells porque os testes de Modules/ProductCatalogue/Tests não rodam em
 * lane nenhuma (mesma razão do CatalogoPublicoSituacaoContratoTest). Lane: sells-pest.yml.
 * Tenant 98 contra o adversário 99 (ADR 0358). Valores fictícios. Lê o corpo da resposta.
 */

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasTable('discounts') || ! Schema::hasTable('business_locations')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }

    $this->business = $this->seededTenant();
    $this->userId = (int) $this->business->owner_id;
    $this->local = cpdescLocal($this->business->id);
    $this->categoria = cpdescCategoria($this->business->id, $this->userId);
});

function cpdescLocal(int $bizId): int
{
    $scheme = DB::table('invoice_schemes')->where('business_id', $bizId)->value('id') ?? DB::table('invoice_schemes')->value('id');
    $layout = DB::table('invoice_layouts')->where('business_id', $bizId)->value('id') ?? DB::table('invoice_layouts')->value('id');
    if (! $scheme || ! $layout) {
        test()->markTestSkipped('Sem invoice_scheme/invoice_layout no banco — as FKs de business_locations não teriam alvo.');
    }

    return (int) DB::table('business_locations')->insertGetId([
        'business_id' => $bizId, 'invoice_scheme_id' => $scheme, 'invoice_layout_id' => $layout,
        'name' => '__CPDESC_LOCAL__', 'country' => 'Brasil', 'state' => 'SC', 'city' => 'Cidade Teste', 'zip_code' => '00000',
        'is_active' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function cpdescCategoria(int $bizId, int $userId): int
{
    return (int) DB::table('categories')->insertGetId([
        'name' => '__CPDESC_CATEGORIA__', 'business_id' => $bizId, 'parent_id' => 0,
        'created_by' => $userId, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function cpdescProduto(int $bizId, int $userId, int $localId, int $categoriaId, string $nome): int
{
    $carimbo = uniqid();
    $unit = DB::table('units')->where('business_id', $bizId)->value('id') ?? DB::table('units')->insertGetId([
        'business_id' => $bizId, 'actual_name' => 'Unidade vitrine', 'short_name' => 'Un', 'allow_decimal' => 1,
        'created_by' => $userId, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $produto = (int) DB::table('products')->insertGetId([
        'business_id' => $bizId, 'name' => $nome, 'type' => 'single', 'unit_id' => $unit, 'category_id' => $categoriaId,
        'tax_type' => 'exclusive', 'enable_stock' => 0, 'sku' => 'CPDESC-' . $carimbo, 'barcode_type' => 'C128',
        'created_by' => $userId, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $grupo = DB::table('product_variations')->insertGetId([
        'product_id' => $produto, 'name' => 'DUMMY', 'is_dummy' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('variations')->insert([
        'product_id' => $produto, 'product_variation_id' => $grupo, 'name' => 'DUMMY', 'sub_sku' => 'CPDESC-' . $carimbo,
        'default_purchase_price' => 0, 'dpp_inc_tax' => 0, 'profit_percent' => 0, 'default_sell_price' => 200,
        'sell_price_inc_tax' => 200, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('product_locations')->insert(['product_id' => $produto, 'location_id' => $localId]);

    return $produto;
}

function cpdescDesconto(int $bizId, int $localId, int $categoriaId, string $tipo, float $valor): void
{
    DB::table('discounts')->insert([
        'name' => '__CPDESC_DESCONTO__', 'business_id' => $bizId, 'brand_id' => null, 'category_id' => $categoriaId,
        'location_id' => $localId, 'priority' => 1, 'discount_type' => $tipo, 'discount_amount' => $valor,
        'starts_at' => now()->subDay(), 'ends_at' => now()->addDay(), 'is_active' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

/** Textos dos selos de desconto presentes no HTML. */
function cpdescSelos(string $html): array
{
    preg_match_all('/class="label label-warning discount-badge[^"]*">([^<]*)</', $html, $m);

    return array_map(fn ($t) => trim(html_entity_decode($t)), $m[1]);
}

/** O número com os separadores da moeda da empresa — calculado aqui, sem o formatador da vitrine. */
function cpdescNumero(int $bizId, float $valor): string
{
    $b = DB::table('business')->join('currencies', 'currencies.id', '=', 'business.currency_id')
        ->where('business.id', $bizId)
        ->first(['business.currency_precision', 'currencies.decimal_separator', 'currencies.thousand_separator']);

    return number_format($valor, (int) ($b->currency_precision ?? 2), $b->decimal_separator, $b->thousand_separator);
}

test('listagem: desconto em porcentagem aparece com "%" (controle positivo)', function () {
    cpdescProduto($this->business->id, $this->userId, $this->local, $this->categoria, '__CPDESC_PRODUTO__');
    cpdescDesconto($this->business->id, $this->local, $this->categoria, 'percentage', 10);

    $selos = cpdescSelos((string) $this->get("/catalogue/{$this->business->id}/{$this->local}")->assertOk()->getContent());

    expect($selos)->toBe(['- ' . cpdescNumero($this->business->id, 10) . '%']);
});

test('listagem: desconto fixo aparece em dinheiro, nunca com "%"', function () {
    cpdescProduto($this->business->id, $this->userId, $this->local, $this->categoria, '__CPDESC_PRODUTO__');
    cpdescDesconto($this->business->id, $this->local, $this->categoria, 'fixed', 15);

    $selos = cpdescSelos((string) $this->get("/catalogue/{$this->business->id}/{$this->local}")->assertOk()->getContent());

    expect($selos)->toHaveCount(1);
    expect($selos[0])->not->toContain('%');
    expect($selos[0])->toContain(cpdescNumero($this->business->id, 15));
});

test('detalhe: porcentagem com "%" e fixo em dinheiro', function () {
    $produto = cpdescProduto($this->business->id, $this->userId, $this->local, $this->categoria, '__CPDESC_PRODUTO__');
    cpdescDesconto($this->business->id, $this->local, $this->categoria, 'percentage', 10);

    $selos = cpdescSelos((string) $this->get("/show-catalogue/{$this->business->id}/{$produto}?location_id={$this->local}")->assertOk()->getContent());
    expect($selos)->toBe(['- ' . cpdescNumero($this->business->id, 10) . '%']);

    DB::table('discounts')->where('business_id', $this->business->id)->where('name', '__CPDESC_DESCONTO__')
        ->update(['discount_type' => 'fixed', 'discount_amount' => 15]);

    $selos = cpdescSelos((string) $this->get("/show-catalogue/{$this->business->id}/{$produto}?location_id={$this->local}")->assertOk()->getContent());
    expect($selos)->toHaveCount(1);
    expect($selos[0])->not->toContain('%');
    expect($selos[0])->toContain(cpdescNumero($this->business->id, 15));
});

test('desconto da empresa 99 no mesmo local não gera selo na vitrine da 98', function () {
    $adversaria = $this->seededSupportClientTenant();
    $produto = cpdescProduto($this->business->id, $this->userId, $this->local, $this->categoria, '__CPDESC_PRODUTO__');
    cpdescDesconto($adversaria->id, $this->local, $this->categoria, 'percentage', 10);

    $lista = (string) $this->get("/catalogue/{$this->business->id}/{$this->local}")->assertOk()->getContent();
    $detalhe = (string) $this->get("/show-catalogue/{$this->business->id}/{$produto}?location_id={$this->local}")->assertOk()->getContent();

    expect($lista)->toContain('__CPDESC_PRODUTO__');
    expect(cpdescSelos($lista))->toBe([]);
    expect(cpdescSelos($detalhe))->toBe([]);
});
