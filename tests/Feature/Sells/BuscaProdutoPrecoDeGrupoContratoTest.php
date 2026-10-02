<?php

declare(strict_types=1);

use App\Utils\ProductUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Support\EstoqueFixture;

/**
 * UC-S04 (resources/js/Pages/Sells/Create.casos.md) — PDV React: o produto entra pelo preço do GRUPO quando a venda tem grupo de preço.
 *
 * O conserto (2026-10-01) faz o `ProductSearchAutocomplete` mandar `price_group` para
 * `/products/list` (ProductController::getProducts → ProductUtil::filterProduct) e o
 * Create.tsx usar `variation_group_price` ao adicionar. Este arquivo é a prova 2 de 2 da
 * REGRA MESTRE (valor): o número que o React passa a usar — calculado em SQL dentro do
 * filterProduct — tem que ser IGUAL ao que o POS Blade aplica, calculado em PHP pelo
 * getVariationGroupPrice (SellPosController::getProductRow). Dois caminhos independentes,
 * o mesmo preço. A prova 1 (o front manda o grupo e usa o preço) é
 * tests/js/sells-busca-preco-grupo.test.tsx.
 *
 * Caracteriza o comportamento atual do backend — não muda cálculo nenhum.
 * Tenant 98 (ADR 0358). Nunca biz=4.
 */
uses(DatabaseTransactions::class);

/**
 * @return array{biz:int, nome:string, variacao:int, grupo:int}
 */
function buscaGrupoCenario(float $base, ?array $precoGrupo): array
{
    $biz = EstoqueFixture::businessId();
    $produto = EstoqueFixture::singleProduct($biz);
    $variacao = $produto->variationId();
    DB::table('variations')->where('id', $variacao)->update(['sell_price_inc_tax' => $base]);

    $grupo = (int) DB::table('selling_price_groups')->insertGetId([
        'name' => 'BPG-'.uniqid(),
        'business_id' => $biz,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    if ($precoGrupo !== null) {
        DB::table('variation_group_prices')->insert([
            'variation_id' => $variacao,
            'price_group_id' => $grupo,
            'price_inc_tax' => $precoGrupo['valor'],
            'price_type' => $precoGrupo['tipo'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    $nome = (string) DB::table('products')->where('id', $produto->productId)->value('name');

    return ['biz' => $biz, 'nome' => $nome, 'variacao' => $variacao, 'grupo' => $grupo];
}

/** A linha que o React recebe de /products/list (location null: o preço não depende do local). */
function buscaGrupoLinha(array $c, ?int $grupo): object
{
    $linhas = app(ProductUtil::class)->filterProduct($c['biz'], $c['nome'], null, null, $grupo, [], ['name']);
    $linha = $linhas->firstWhere('variation_id', $c['variacao']);
    expect($linha)->not->toBeNull();

    return $linha;
}

beforeEach(function () {
    if (! EstoqueFixture::schemaReady()) {
        $this->markTestSkipped('Schema UltimatePOS ausente — roda na lane MySQL (sells-pest) / CT 100.');
    }
});

dataset('precos de grupo', [
    'fixo 40 sobre base 50' => [50.0, ['valor' => 40, 'tipo' => 'fixed'], 40.0],
    'percentual 90 sobre base 50' => [50.0, ['valor' => 90, 'tipo' => 'percentage'], 45.0],
    'percentual fracionário 33,33 sobre 59,90' => [59.90, ['valor' => 33.33, 'tipo' => 'percentage'], 19.9647],
]);

it('UC-S04 · preço do grupo que o React recebe (SQL) é igual ao que o Blade aplica (PHP) e à conta feita à mão', function (float $base, array $precoGrupo, float $esperado) {
    $c = buscaGrupoCenario($base, $precoGrupo);

    $react = (float) buscaGrupoLinha($c, $c['grupo'])->variation_group_price;
    $blade = (float) app(ProductUtil::class)->getVariationGroupPrice($c['variacao'], $c['grupo'], null)['price_inc_tax'];

    expect($react)->toEqualWithDelta($esperado, 0.0005);
    expect($blade)->toEqualWithDelta($esperado, 0.0005);
    expect($react)->toEqualWithDelta($blade, 0.0005);
    // O preço base segue disponível na mesma linha (é o fallback do front).
    expect((float) buscaGrupoLinha($c, $c['grupo'])->selling_price)->toEqualWithDelta($base, 0.0005);
})->with('precos de grupo');

it('UC-S04 · variação sem preço no grupo continua na busca, com variation_group_price nulo (front cai no preço base)', function () {
    $c = buscaGrupoCenario(50.0, null);

    $linha = buscaGrupoLinha($c, $c['grupo']);

    expect($linha->variation_group_price)->toBeNull();
    expect((float) $linha->selling_price)->toEqualWithDelta(50.0, 0.0005);
    // O Blade também não aplica nada: devolve vazio e mantém o preço da variação.
    expect(app(ProductUtil::class)->getVariationGroupPrice($c['variacao'], $c['grupo'], null)['price_inc_tax'])->toBe('');
});

it('UC-S04 · sem price_group a busca não traz variation_group_price — é o comportamento de antes do conserto', function () {
    $c = buscaGrupoCenario(50.0, ['valor' => 40, 'tipo' => 'fixed']);

    $linha = buscaGrupoLinha($c, null);

    // filterProduct devolve models Eloquent: a coluna só existe se o SELECT a trouxe.
    expect(array_key_exists('variation_group_price', $linha->getAttributes()))->toBeFalse();
    expect((float) $linha->selling_price)->toEqualWithDelta(50.0, 0.0005);
});

/*
 * Preço de grupo ZERO (2026-10-02). Medido no MySQL: o Blade (getProductRow) usa o grupo quando
 * `!empty($price_inc_tax)`. Fixo chega como string "0.0000" (não-vazia) → a linha fica em 0.
 * Percentual sai de calc_percentage como float 0 (vazio) → a linha fica no preço base. O React
 * aplica `variation_group_price` sempre que não é nulo (precoDaBusca.ts), então o filterProduct
 * devolve NULL no percentual que dá zero. Os dois caminhos têm de chegar ao mesmo preço final.
 */
dataset('precos de grupo zero', [
    'fixo 0 → o grupo vale (preço 0)' => ['fixed', 0.0],
    'percentual 0 → sem preço de grupo (preço base 50)' => ['percentage', 50.0],
]);

it('UC-S04 · preço de grupo zero: React e Blade chegam ao mesmo preço final', function (string $tipo, float $esperado) {
    $c = buscaGrupoCenario(50.0, ['valor' => 0, 'tipo' => $tipo]);

    // Caminho React: a linha de /products/list + a regra do precoDaBusca (grupo não-nulo vence).
    $linha = buscaGrupoLinha($c, $c['grupo']);
    $react = $linha->variation_group_price !== null
        ? (float) $linha->variation_group_price
        : (float) $linha->selling_price;

    // Caminho Blade: a mesma condição do SellPosController::getProductRow.
    $grupo = app(ProductUtil::class)->getVariationGroupPrice($c['variacao'], $c['grupo'], null);
    $blade = ! empty($grupo['price_inc_tax']) ? (float) $grupo['price_inc_tax'] : 50.0;

    expect($react)->toEqualWithDelta($esperado, 0.0005);
    expect($blade)->toEqualWithDelta($esperado, 0.0005);
})->with('precos de grupo zero');
