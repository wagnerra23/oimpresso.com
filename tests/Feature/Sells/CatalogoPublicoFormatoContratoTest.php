<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Vitrine pública: números saem pela moeda da EMPRESA, sem depender da sessão.
 *
 * Contrato (lote 1 da auditoria ProductCatalogue, baixos de valor): a rota é anônima (sem
 * SetSessionData), então `session('currency')` vem vazia. O preço de cada variação no <select>
 * sai formatado pela moeda da empresa (antes, número cru com 4 casas); a faixa de preço vai do
 * menor ao maior; o componente de combo mostra o preço na unidade do combo (preço base ×
 * multiplicador da sub-unidade, como o PDV); "Sem categoria" sai traduzido.
 *
 * Lane: sells-pest.yml. Tenant 98 contra o 99 (ADR 0358). Valores fictícios. Lê o corpo.
 */

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasTable('business_locations')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }

    $tenant = $this->seededTenant();
    $this->biz = (int) $tenant->id;
    $this->user = (int) $tenant->owner_id;
    $scheme = DB::table('invoice_schemes')->where('business_id', $this->biz)->value('id') ?? DB::table('invoice_schemes')->value('id');
    $layout = DB::table('invoice_layouts')->where('business_id', $this->biz)->value('id') ?? DB::table('invoice_layouts')->value('id');
    if (! $scheme || ! $layout) {
        $this->markTestSkipped('Sem invoice_scheme/invoice_layout no banco — as FKs de business_locations não teriam alvo.');
    }
    $this->local = (int) DB::table('business_locations')->insertGetId([
        'business_id' => $this->biz, 'invoice_scheme_id' => $scheme, 'invoice_layout_id' => $layout,
        'name' => '__CPFMT_LOCAL__', 'country' => 'Brasil', 'state' => 'SC', 'city' => 'Cidade Teste', 'zip_code' => '00000',
        'is_active' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $this->unidade = (int) DB::table('units')->insertGetId([
        'business_id' => $this->biz, 'actual_name' => '__CPFMT_UN__', 'short_name' => 'Un', 'allow_decimal' => 1,
        'created_by' => $this->user, 'created_at' => now(), 'updated_at' => now(),
    ]);
});

/** @param array<string,float> $variacoes nome => preço  @return array<string,int> nome => id da variação */
function cpfmtProduto(object $t, string $nome, string $tipo, array $variacoes, array $extraVariacao = []): array
{
    $carimbo = uniqid();
    $produto = (int) DB::table('products')->insertGetId([
        'business_id' => $t->biz, 'name' => $nome, 'type' => $tipo, 'unit_id' => $t->unidade,
        'tax_type' => 'exclusive', 'enable_stock' => 0, 'sku' => 'CPFMT-' . $carimbo, 'barcode_type' => 'C128',
        'created_by' => $t->user, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $grupo = DB::table('product_variations')->insertGetId([
        'product_id' => $produto, 'name' => $tipo === 'variable' ? 'Tamanho' : 'DUMMY', 'is_dummy' => $tipo === 'variable' ? 0 : 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $ids = ['produto' => $produto];
    foreach ($variacoes as $nomeVar => $preco) {
        $ids[$nomeVar] = (int) DB::table('variations')->insertGetId(array_merge([
            'product_id' => $produto, 'product_variation_id' => $grupo, 'name' => $nomeVar, 'sub_sku' => 'CPFMT-' . $carimbo . '-' . $nomeVar,
            'default_purchase_price' => 0, 'dpp_inc_tax' => 0, 'profit_percent' => 0, 'default_sell_price' => $preco,
            'sell_price_inc_tax' => $preco, 'created_at' => now(), 'updated_at' => now(),
        ], $extraVariacao));
    }
    DB::table('product_locations')->insert(['product_id' => $produto, 'location_id' => $t->local]);

    return $ids;
}

/** Dinheiro com a moeda da empresa, calculado aqui sem o formatador da vitrine. */
function cpfmtDinheiro(int $bizId, float $valor): string
{
    $b = DB::table('business')->join('currencies', 'currencies.id', '=', 'business.currency_id')->where('business.id', $bizId)
        ->first(['business.currency_precision', 'business.currency_symbol_placement', 'currencies.symbol', 'currencies.decimal_separator', 'currencies.thousand_separator']);
    $n = number_format($valor, (int) ($b->currency_precision ?? 2), $b->decimal_separator, $b->thousand_separator);

    return $b->currency_symbol_placement === 'after' ? $n . ' ' . $b->symbol : $b->symbol . ' ' . $n;
}

test('listagem: preço de cada variação no seletor sai com a moeda da empresa', function () {
    cpfmtProduto($this, '__CPFMT_VARIAVEL__', 'variable', ['P' => 100, 'G' => 1234.5]);

    $html = (string) $this->get("/catalogue/{$this->biz}/{$this->local}")->assertOk()->getContent();
    preg_match_all('/<option value="\d+">([^<]*)<\/option>/', $html, $m);
    $opcoes = array_map(fn ($o) => html_entity_decode(trim($o)), $m[1]);

    expect($opcoes)->toHaveCount(2);
    expect($opcoes[0])->toEndWith(' - ' . cpfmtDinheiro($this->biz, 100));
    expect($opcoes[1])->toEndWith(' - ' . cpfmtDinheiro($this->biz, 1234.5));
    expect(implode('|', $opcoes))->not->toContain('.0000');
});

test('listagem: faixa de preço vai do menor ao maior', function () {
    cpfmtProduto($this, '__CPFMT_VARIAVEL__', 'variable', ['P' => 100, 'G' => 1234.5]);

    $html = (string) $this->get("/catalogue/{$this->biz}/{$this->local}")->assertOk()->getContent();
    preg_match('/<td class="pb-0">\s*(?:\{\{--.*?--\}\})?\s*<span class="display_currency"[^>]*>([^<]*)<\/span>\s*-\s*<span class="display_currency"[^>]*>([^<]*)<\/span>/s', $html, $m);

    expect($m)->toHaveCount(3);
    expect((float) $m[1])->toBe(100.0);
    expect((float) $m[2])->toBe(1234.5);
});

test('listagem: produto sem categoria sai sob o rótulo traduzido, não "Uncategorized" fixo', function () {
    cpfmtProduto($this, '__CPFMT_SEMCAT__', 'single', ['DUMMY' => 10]);
    // A rota pública não tem o middleware `language`: vale o locale da aplicação.
    app()->setLocale('pt');

    $html = (string) $this->get("/catalogue/{$this->biz}/{$this->local}")->assertOk()->getContent();

    expect($html)->toContain('__CPFMT_SEMCAT__');
    expect(substr_count($html, 'Sem categoria'))->toBeGreaterThanOrEqual(2);
    expect($html)->not->toContain('Uncategorized');
});

test('detalhe do combo: componente vendido em caixa mostra preço da caixa (base × multiplicador)', function () {
    $caixa = (int) DB::table('units')->insertGetId([
        'business_id' => $this->biz, 'actual_name' => '__CPFMT_CAIXA__', 'short_name' => 'Cx', 'allow_decimal' => 0,
        'base_unit_id' => $this->unidade, 'base_unit_multiplier' => 12,
        'created_by' => $this->user, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $componente = cpfmtProduto($this, '__CPFMT_COMPONENTE__', 'single', ['DUMMY' => 10]);
    $combo = cpfmtProduto($this, '__CPFMT_COMBO__', 'combo', ['DUMMY' => 120], [
        'combo_variations' => json_encode([['variation_id' => $componente['DUMMY'], 'quantity' => 1, 'unit_id' => $caixa]]),
    ]);

    $html = (string) $this->get("/show-catalogue/{$this->biz}/{$combo['produto']}?location_id={$this->local}")->assertOk()->getContent();
    $bloco = substr($html, (int) strpos($html, '__CPFMT_COMPONENTE__'));
    preg_match('/<span class="display_currency" data-currency_symbol="true">([^<]*)<\/span>/', $bloco, $m);

    expect($bloco)->toContain('__CPFMT_CAIXA__');
    expect((float) ($m[1] ?? -1))->toBe(120.0);
});

test('vitrine da 98 não mostra o produto da 99, mesmo ligado ao local dela', function () {
    $adversaria = $this->seededSupportClientTenant();
    $alheio = (int) DB::table('products')->insertGetId([
        'business_id' => $adversaria->id, 'name' => '__CPFMT_ALHEIO__', 'type' => 'single', 'unit_id' => $this->unidade,
        'tax_type' => 'exclusive', 'enable_stock' => 0, 'sku' => 'CPFMT-ALHEIO-' . uniqid(), 'barcode_type' => 'C128',
        'created_by' => (int) $adversaria->owner_id, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('product_locations')->insert(['product_id' => $alheio, 'location_id' => $this->local]);
    cpfmtProduto($this, '__CPFMT_PROPRIO__', 'single', ['DUMMY' => 10]);

    $html = (string) $this->get("/catalogue/{$this->biz}/{$this->local}")->assertOk()->getContent();

    expect($html)->toContain('__CPFMT_PROPRIO__');
    expect($html)->not->toContain('__CPFMT_ALHEIO__');
});
