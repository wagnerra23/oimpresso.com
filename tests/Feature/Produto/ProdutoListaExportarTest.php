<?php

declare(strict_types=1);
// Cobre UC-PIDX-10 e UC-PIDX-34 (resources/js/Pages/Produto/Index.casos.md) — G-2 rastreabilidade caso↔teste.

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EstoqueFixture;

/**
 * "Exportar filtrados" da lista de produtos (`GET /products?exportar=csv&…`) — protótipo
 * `prototipo-ui/cowork/Felipe/produto-blade.jsx`, rodapé da vista `lista`.
 *
 * O CSV tem de trazer as MESMAS linhas que a lista mostra para os mesmos filtros (comparado com a
 * prop `lista`, não com o próprio CSV), e as colunas de preço só com a permissão de vê-lo.
 *
 * Tenant semeado do CI (ADR 0358). Nunca biz=4. Pest só no CI / CT 100.
 */
uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasColumn('transactions', 'essentials_duration')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }
    $this->business = $this->seededTenant();
});

function pexEntrar($teste, array $permissoes): void
{
    $teste->user = $teste->usuarioComPermissoes($permissoes, $teste->business);
    $teste->actingAs($teste->user);
    session(['user.business_id' => $teste->business->id, 'user.id' => $teste->user->id, 'business.id' => $teste->business->id]);
    session(['currency' => ['symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ','], 'business.date_format' => 'd/m/Y']);
}

/** O CSV exportado, já sem o BOM, em linhas de células. */
function pexCsv($teste, array $query): array
{
    $r = $teste->get('/products?' . http_build_query(array_merge($query, ['exportar' => 'csv'])));
    $r->assertOk();
    expect((string) $r->headers->get('content-type'))->toContain('text/csv');
    $corpo = (string) $r->getContent();
    expect(substr($corpo, 0, 3))->toBe("\xEF\xBB\xBF");

    $linhas = array_values(array_filter(preg_split('/\r?\n/', substr($corpo, 3)), fn ($l) => $l !== ''));

    return array_map(fn ($l) => str_getcsv($l, ';', '"', ''), $linhas);
}

/** Os nomes que a prop `lista` mostra para os mesmos filtros, todas as páginas. */
function pexNomesDaLista($teste, array $query): array
{
    $r = $teste->get('/products?' . http_build_query(array_merge($query, ['per_page' => -1])), [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request()),
        'X-Inertia-Partial-Component' => 'Produto/Index',
        'X-Inertia-Partial-Data' => 'lista',
        'X-Requested-With' => 'XMLHttpRequest',
    ]);
    $r->assertOk();

    return array_map(fn ($l) => $l['nome'], $r->json('props.lista.data'));
}

it('UC-PIDX-10 · o CSV traz as mesmas linhas que a lista mostra para a busca, e não o produto de outra empresa', function () {
    $marca = 'PEXCSV' . strtoupper(bin2hex(random_bytes(3)));
    foreach (['A', 'B'] as $sufixo) {
        $p = EstoqueFixture::singleProduct($this->business->id);
        DB::table('products')->where('id', $p->productId)->update(['name' => "{$marca} {$sufixo}"]);
    }
    $outro = EstoqueFixture::secondBusinessId();
    if ($outro !== null) {
        $alheio = EstoqueFixture::singleProduct($outro);
        DB::table('products')->where('id', $alheio->productId)->update(['name' => "{$marca} ALHEIO"]);
    }
    pexEntrar($this, ['product.view', 'access_all_locations']);

    $csv = pexCsv($this, ['q' => $marca]);
    $nomes = array_map(fn ($l) => $l[0], array_slice($csv, 1));
    $daLista = pexNomesDaLista($this, ['q' => $marca]);
    sort($nomes);
    sort($daLista);

    // Anti-vácuo: os dois do cenário estão lá — comparar duas listas vazias provaria nada.
    expect($daLista)->toBe(["{$marca} A", "{$marca} B"]);
    expect($nomes)->toBe($daLista);
});

it('UC-PIDX-10 · as colunas de preço só saem com a permissão de ver o preço', function () {
    $marca = 'PEXPRECO' . strtoupper(bin2hex(random_bytes(3)));
    $p = EstoqueFixture::singleProduct($this->business->id);
    DB::table('products')->where('id', $p->productId)->update(['name' => $marca]);

    pexEntrar($this, ['product.view', 'access_all_locations']);
    $semPermissao = pexCsv($this, ['q' => $marca])[0];
    expect($semPermissao)->toContain('Produto');
    expect(implode('|', $semPermissao))->not->toContain('Preço');

    pexEntrar($this, ['product.view', 'access_all_locations', 'view_purchase_price', 'access_default_selling_price']);
    $comPermissao = pexCsv($this, ['q' => $marca])[0];
    expect($comPermissao)->toContain('Preço de compra (mín.)', 'Preço de venda (mín.)');
});

// A coluna Imposto do CSV segue a da lista: só em empresa com alíquota cadastrada (decisão [F]
// 2026-10-26). "Sem alíquota" é produzido apagando, dentro da transação do teste, as alíquotas da
// empresa (`tax_rates` usa SoftDeletes; nada fica gravado).
it('UC-PIDX-34 · com alíquota cadastrada, o CSV tem a coluna Imposto com o imposto do produto', function () {
    $marca = 'PEXIMP' . strtoupper(bin2hex(random_bytes(3)));
    $p = EstoqueFixture::singleProduct($this->business->id);
    $taxa = (int) DB::table('tax_rates')->insertGetId([
        'business_id' => $this->business->id, 'name' => "IVA {$marca}", 'amount' => 10,
        'created_by' => EstoqueFixture::userId($this->business->id), 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('products')->where('id', $p->productId)->update(['name' => $marca, 'tax' => $taxa]);
    pexEntrar($this, ['product.view', 'access_all_locations']);

    $csv = pexCsv($this, ['q' => $marca]);
    $coluna = array_search('Imposto', $csv[0], true);
    expect($coluna)->not->toBeFalse();
    expect($csv)->toHaveCount(2);
    expect($csv[1][$coluna])->toBe("IVA {$marca}");
    expect(count($csv[1]))->toBe(count($csv[0]));
});

it('UC-PIDX-34 · sem alíquota cadastrada, o CSV não tem a coluna Imposto', function () {
    $marca = 'PEXSEMIMP' . strtoupper(bin2hex(random_bytes(3)));
    $p = EstoqueFixture::singleProduct($this->business->id);
    DB::table('products')->where('id', $p->productId)->update(['name' => $marca]);
    DB::table('tax_rates')->where('business_id', $this->business->id)->update(['deleted_at' => now()]);
    pexEntrar($this, ['product.view', 'access_all_locations']);

    $csv = pexCsv($this, ['q' => $marca]);
    // Anti-vácuo: o arquivo veio, com o produto e as colunas vizinhas.
    expect($csv)->toHaveCount(2);
    expect($csv[1][0])->toBe($marca);
    $unidade = array_search('Unidade', $csv[0], true);
    expect($unidade)->not->toBeFalse();
    expect($csv[0][$unidade + 1])->toBe('Locais');
    expect($csv[0])->not->toContain('Imposto');
    expect(count($csv[1]))->toBe(count($csv[0]));
});
