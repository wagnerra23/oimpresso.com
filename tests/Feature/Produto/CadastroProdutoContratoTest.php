<?php

declare(strict_types=1);
// Cobre UC-PCAD-01, UC-PCAD-02, UC-PCAD-03, UC-PCAD-04, UC-PCAD-05, UC-PCAD-06 (Create.casos.md) - G-2 rastreabilidade caso-teste.

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\Support\EstoqueFixture;

/**
 * Contrato de comportamento do Cadastro de Produto (/products/create → POST /products).
 *
 * ÂNCORA (contrato, NÃO implementação): CU-PROD-01 — "Cadastrar produto simples" `[must]`
 * em memory/requisitos/Produto/SDD-tela-cadastro-produto-v1.0.md §6.1:
 *   1. `[must]` Campos obrigatórios (name, unit, tax) validados client + server     → UC-PCAD-02
 *   2. `[must]` SKU vazio → gerado server-side; SKU digitado → validado duplicado   → UC-PCAD-01
 *   3. Defaults: type='single', enable_stock=true, tax_type='exclusive'             → UC-PCAD-03
 *   4. `[V0]` Preço de custo e venda passam pelo parser pt-BR sem ×100 (num_uf)     → UC-PCAD-04
 *   5. `[T0]` Dropdowns (categoria/marca/unidade/imposto) só do business atual      → UC-PCAD-05
 *   6. Submit retorna /products (paridade legacy)                                   → SEM UC (backlog)
 * + CU-PROD-07 — "Duplicar produto" item 2 `[T0]`: externo → 404                    → UC-PCAD-06
 *
 * POR QUE ESTE TESTE EXISTE
 * ─────────────────────────────────────────────────────────────────────────────
 * Irmão do TabelaPrecoContratoTest (#4300), mesma doença e mesmo remédio. A tela tem charter
 * desde 2026-05-15 e ZERO teste de comportamento: os dois que existem (Wave2CreateInertiaTest /
 * Wave2CreateBaselineTest) fazem `file_get_contents` + `toContain` no fonte do .tsx. Eles são o
 * INVERSO de um teste — renomear a variável `dup` deixa vermelho sem mudar comportamento; trocar a
 * lógica mantendo a string deixa verde com o comportamento quebrado. Nenhum faz POST.
 *
 * Prova de que a cobertura é falsa: os 14 testes do Wave2CreateInertiaTest passam VERDES numa tela
 * cujo card "Preço & Imposto" não tem campo de preço nenhum (recibo no §Pendência de CONTRATO do
 * Create.casos.md). Nenhum deles pergunta "o produto nasceu com preço?".
 *
 * ⚠️ ESCRITO ANTES DE LER A IMPLEMENTAÇÃO (failing-first). Os asserts saem do CU-PROD-01, não do
 * ProductController. Se algum nascer vermelho, o vermelho é o achado — não se ajusta o teste ao
 * código (proibicoes §5, entrada 2026-06-05: teste derivado do código trava o desvio em vez de
 * pegá-lo). Dois vermelhos assim no #4300 viraram: (a) a prova de que o charter prometia um 404
 * que não existia, (b) uma correção Tier 0 de vazamento cross-tenant.
 *
 * ⛔ TEST-ONLY: não altera nenhum método. Caracteriza o contrato atual do endpoint.
 *    Mexer em preço/custo é US separada sob REGRA MESTRE (dupla-confirmação + antes→depois + [W]).
 */
uses(DatabaseTransactions::class);

/** POST mínimo de produto simples. Só o que o CU-PROD-01 chama de obrigatório. */
function postProdutoMinimo(int $bizId, array $override = []): array
{
    return array_merge([
        'name' => 'Produto UC-PCAD ' . uniqid(),
        'sku' => '',
        'type' => 'single',
        'unit_id' => EstoqueFixture::unitId($bizId),
        'tax_type' => 'exclusive',
        'enable_stock' => 1,
    ], $override);
}

/** A variação (única) do produto simples recém-criado, ou null. */
function variacaoDoProduto(int $productId): ?object
{
    return DB::table('variations')->where('product_id', $productId)->first();
}

/** O produto criado pelo nome, ou null se não persistiu. */
function produtoPorNome(string $name, int $bizId): ?object
{
    return DB::table('products')->where('name', $name)->where('business_id', $bizId)->first();
}

beforeEach(function () {
    if (! EstoqueFixture::schemaReady()) {
        $this->markTestSkipped('Schema UltimatePOS/seed ausente (sqlite :memory: ou DB vazio) — roda na lane MySQL / CT 100.');
    }

    try {
        // biz=1 canônico (ADR 0101 — NUNCA biz=4, que é cliente real ROTA LIVRE).
        $this->business = $this->seededTenant();
    } catch (\Throwable $e) {
        $this->markTestSkipped('Schema UltimatePOS ausente — rode com DB_CONNECTION=mysql no CT 100.');
    }

    $this->user = User::where('business_id', $this->business->id)->first();
    if (! $this->user) {
        $this->markTestSkipped('Sem user no business seeded.');
    }

    $this->actingAs($this->user);
    session([
        'user.business_id' => $this->business->id,
        'user.id' => $this->user->id,
    ]);

    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::findOrCreate('product.create', 'web');
    $this->user->givePermissionTo(['product.create']);
});

// =============================================================================
// UC-PCAD-01 — CU-PROD-01.2: SKU vazio nasce gerado NO SERVIDOR
// =============================================================================

it('UC-PCAD-01 · SKU vazio nasce gerado no servidor', function () {
    $bizId = (int) $this->business->id;
    $payload = postProdutoMinimo($bizId, ['sku' => '']);

    $this->post('/products', $payload);

    $produto = produtoPorNome($payload['name'], $bizId);
    expect($produto)->not->toBeNull('O produto não persistiu em products.');
    expect(trim((string) $produto->sku))->not->toBe('', 'SKU vazio: o servidor não gerou (CU-PROD-01.2).');
});

// =============================================================================
// UC-PCAD-02 — CU-PROD-01.1 `[must]`: obrigatório validado CLIENT + SERVER
//   O CU diz "client + server". O client está coberto (mal) pelos Wave2*.
//   Este cobre o SERVER — que é onde ninguém olhou.
// =============================================================================

it('UC-PCAD-02 · POST sem campo obrigatório não persiste produto órfão', function () {
    $bizId = (int) $this->business->id;
    $nome = 'Produto UC-PCAD-02 ' . uniqid();

    // unit_id é obrigatório pelo CU-PROD-01.1 — mandamos tudo MENOS ele.
    $payload = postProdutoMinimo($bizId, ['name' => $nome]);
    unset($payload['unit_id']);

    $this->post('/products', $payload);

    expect(produtoPorNome($nome, $bizId))
        ->toBeNull('Produto nasceu SEM unidade — o CU-PROD-01.1 exige validação server-side.');
});

// =============================================================================
// UC-PCAD-03 — CU-PROD-01.3: defaults conservadores
//   Hoje os defaults moram no useForm do React. Se a tela virar aba nova
//   (o rumo do [F]) e parar de mandá-los, o servidor decide sozinho.
//   Este UC pergunta O QUE o servidor decide.
// =============================================================================

it('UC-PCAD-03 · defaults conservadores no produto criado', function () {
    $bizId = (int) $this->business->id;
    $payload = postProdutoMinimo($bizId);
    // Omite exatamente os 3 do CU: type, enable_stock, tax_type.
    unset($payload['type'], $payload['enable_stock'], $payload['tax_type']);

    $this->post('/products', $payload);

    $produto = produtoPorNome($payload['name'], $bizId);
    expect($produto)->not->toBeNull('O produto não persistiu.');
    expect($produto->type)->toBe('single', "CU-PROD-01.3: default type='single'.");
    expect((int) $produto->enable_stock)->toBe(1, 'CU-PROD-01.3: default enable_stock=true.');
    expect($produto->tax_type)->toBe('exclusive', "CU-PROD-01.3: default tax_type='exclusive'.");
});

// =============================================================================
// UC-PCAD-04 — CU-PROD-01.4 `[V0]`: parser pt-BR não infla o preço
//   Mesmo parser (num_uf) que inflou 16 vendas ×100k na ROTA LIVRE (2026-06-05).
//   Produto ALIMENTA Sells: custo inflado aqui contamina margem/tabela/estoque.
// =============================================================================

it('UC-PCAD-04 · custo pt-BR com milhar e decimal grava o valor certo', function () {
    $bizId = (int) $this->business->id;
    $payload = postProdutoMinimo($bizId, [
        'single_dpp' => '1.234,56',   // mil duzentos e trinta e quatro reais e cinquenta e seis
        'single_dsp' => '2.000,00',
    ]);

    $this->post('/products', $payload);

    $produto = produtoPorNome($payload['name'], $bizId);
    expect($produto)->not->toBeNull('O produto não persistiu.');

    $variacao = variacaoDoProduto((int) $produto->id);
    expect($variacao)->not->toBeNull('Produto single nasceu SEM variação.');
    expect((float) $variacao->default_purchase_price)
        ->toEqualWithDelta(1234.56, 0.01, 'Custo pt-BR inflado ou truncado (num_uf — CU-PROD-01.4 [V0]).');
    expect((float) $variacao->default_sell_price)
        ->toEqualWithDelta(2000.00, 0.01, 'Preço de venda pt-BR inflado ou truncado.');
});

it('UC-PCAD-04 · custo fracionário com ponto NÃO infla ×100k', function () {
    $bizId = (int) $this->business->id;
    // A forma que o React manda (Number.toString()): ponto = separador DECIMAL, não milhar.
    // Foi exatamente este caso que o num_uf leu como milhar no incidente 2026-06-05.
    $payload = postProdutoMinimo($bizId, ['single_dpp' => '204.99605']);

    $this->post('/products', $payload);

    $produto = produtoPorNome($payload['name'], $bizId);
    expect($produto)->not->toBeNull('O produto não persistiu.');

    $variacao = variacaoDoProduto((int) $produto->id);
    expect($variacao)->not->toBeNull('Produto single nasceu SEM variação.');
    expect((float) $variacao->default_purchase_price)
        ->toBeLessThan(1000.0, 'Custo 204.99605 virou ordem de grandeza maior — é o ×100k de 2026-06-05.');
});

// =============================================================================
// UC-PCAD-05 — CU-PROD-01.5 `[T0]`: insumo de outro business não vincula
//   O CU fala de "dropdowns", que é UI. Dropdown escopado impede ESCOLHER,
//   não impede o REQUEST de mandar. Foi essa família de furo que o UC-PTAB-04
//   achou vermelho no #4300 (price_group_id cru da chave do array → gravou).
// =============================================================================

it('UC-PCAD-05 · category_id de outro business não vincula', function () {
    $outroBizId = EstoqueFixture::secondBusinessId();
    if ($outroBizId === null) {
        $this->markTestSkipped('DB só tem 1 business — sem par cross-tenant pra provar isolamento.');
    }

    $bizId = (int) $this->business->id;

    // `categories.created_by` é NOT NULL com FK pra users — tem que ser um user DO outro business.
    $categoriaAlheia = (int) DB::table('categories')->insertGetId([
        'name' => 'Categoria alheia UC-PCAD-05',
        'business_id' => $outroBizId,
        'category_type' => 'product',
        'created_by' => EstoqueFixture::userId($outroBizId),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $payload = postProdutoMinimo($bizId, ['category_id' => $categoriaAlheia]);

    $this->post('/products', $payload);

    $produto = produtoPorNome($payload['name'], $bizId);

    // O invariante é ISOLAMENTO, não status HTTP (lição do UC-PTAB-02: o 404 era proxy errado).
    // Aceita: recusar o produto OU criar sem o vínculo alheio. Recusa: carimbar a categoria alheia.
    if ($produto !== null) {
        expect((int) $produto->category_id)->not->toBe(
            $categoriaAlheia,
            'Produto do meu business ficou vinculado a categoria de OUTRO business (Tier 0 — ADR 0093).'
        );
    }
});

// =============================================================================
// UC-PCAD-06 — CU-PROD-07.2 `[T0]`: duplicar produto alheio → 404
//   Aqui o 404 é o CONTRATO falando (o CU crava), não proxy inventado por charter.
// =============================================================================

it('UC-PCAD-06 · duplicar produto de outro business retorna 404', function () {
    $outroBizId = EstoqueFixture::secondBusinessId();
    if ($outroBizId === null) {
        $this->markTestSkipped('DB só tem 1 business — sem par cross-tenant pra provar isolamento.');
    }

    $produtoAlheio = EstoqueFixture::singleProduct($outroBizId);

    $this->get('/products/create?d=' . $produtoAlheio->productId)
        ->assertStatus(404);
});
