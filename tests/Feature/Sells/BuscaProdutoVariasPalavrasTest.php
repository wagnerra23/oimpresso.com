<?php

declare(strict_types=1);

use App\Utils\ProductUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Support\EstoqueFixture;

/**
 * Busca de produto da venda (`/products/list` → ProductController::getProducts →
 * ProductUtil::filterProduct, o mesmo caminho do BuscaProdutoPrecoDeGrupoContratoTest) com mais
 * de uma palavra.
 *
 * Pedido da ROTA LIVRE (2026-11-11), irmão da busca de cliente (#10719): o texto inteiro ia num
 * LIKE só, então "camisa azul" não achava "CAMISA POLO AZUL". Agora cada palavra precisa casar
 * em algum dos campos escolhidos (nome, sku...). A busca exata (código lido no leitor) não muda.
 *
 * Só busca: não toca preço, total nem estoque. Nomes fictícios. Tenant 98 × 99 (ADR 0358).
 */
uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! EstoqueFixture::schemaReady()) {
        $this->markTestSkipped('Schema UltimatePOS ausente — roda na lane MySQL (sells-pest) / CT 100.');
    }
    $this->biz = EstoqueFixture::businessId();
    $this->marca = 'ZQP' . random_int(1000, 9999);
});

function bpvpProduto(int $biz, string $nome): int
{
    $produto = EstoqueFixture::singleProduct($biz);
    DB::table('products')->where('id', $produto->productId)->update(['name' => $nome]);

    return $produto->productId;
}

/** Ids de produto que a busca devolve. */
function bpvpBusca(int $biz, string $termo, array $campos = ['name', 'sku']): array
{
    return app(ProductUtil::class)->filterProduct($biz, $termo, null, null, null, [], $campos)
        ->pluck('product_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
}

it('acha o produto com palavras fora de ordem e com outra palavra entre elas', function () {
    $alvo = bpvpProduto($this->biz, "{$this->marca}CAMISA POLO {$this->marca}AZUL");
    $outro = bpvpProduto($this->biz, "{$this->marca}CAMISA POLO {$this->marca}VERDE");

    // Controle positivo: uma palavra só acha os dois.
    $umaPalavra = bpvpBusca($this->biz, "{$this->marca}CAMISA");
    expect($umaPalavra)->toContain($alvo)->and($umaPalavra)->toContain($outro);

    $achados = bpvpBusca($this->biz, "{$this->marca}CAMISA {$this->marca}AZUL");
    expect($achados)->toContain($alvo)
        ->and($achados)->not->toContain($outro);

    // A ordem digitada não importa.
    expect(bpvpBusca($this->biz, "{$this->marca}AZUL {$this->marca}CAMISA"))->toContain($alvo);
});

it('texto que já casava inteiro continua casando (o resultado só cresce)', function () {
    $alvo = bpvpProduto($this->biz, "{$this->marca}CAMISA POLO {$this->marca}AZUL");

    expect(bpvpBusca($this->biz, "{$this->marca}CAMISA POLO {$this->marca}AZUL"))->toContain($alvo);
});

it('texto só de espaços não vira "devolver todos os produtos"', function () {
    $alvo = bpvpProduto($this->biz, "{$this->marca}CAMISA {$this->marca}AZUL");

    expect(bpvpBusca($this->biz, '   '))->not->toContain($alvo);
});

it('Tier 0 — produto de outra empresa não volta na busca', function () {
    $outroBiz = EstoqueFixture::secondBusinessId();
    if (! $outroBiz || $outroBiz === $this->biz) {
        $this->markTestSkipped('Sem segunda empresa nesta lane.');
    }
    $meu = bpvpProduto($this->biz, "{$this->marca}CAMISA {$this->marca}AZUL");
    $alheio = bpvpProduto($outroBiz, "{$this->marca}CAMISA {$this->marca}AZUL");

    $achados = bpvpBusca($this->biz, "{$this->marca}CAMISA {$this->marca}AZUL");
    expect($achados)->toContain($meu)
        ->and($achados)->not->toContain($alheio);
});
