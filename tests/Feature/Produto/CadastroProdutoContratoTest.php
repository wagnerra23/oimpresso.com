<?php

declare(strict_types=1);
// Cobre UC-PCAD-01, UC-PCAD-04, UC-PCAD-06 (Create.casos.md) - G-2 rastreabilidade caso-teste.
// UC-PCAD-02/03 removidos (gaps de paridade, não bugs) · UC-PCAD-05 no backlog (achado Tier 0, US própria) — correção [F] 2026-07-17.

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
// UC-PCAD-02 e UC-PCAD-03 — REMOVIDOS (2026-07-17, correção [F]).
//   NÃO eram bugs — eram meu erro de âncora (proibicoes §5, entrada 2026-07-15
//   "achado derivado de leitura"). Reincidi: analisei a casca React `Create.tsx`
//   (draft) como se fosse o cadastro. O cadastro REAL em produção é o Blade
//   `resources/views/product/create.blade.php` + `store()`.
//
//   - "validação server" (ex-UC-PCAD-02): o UltimatePOS valida CLIENT-SIDE no Blade
//     (`required` em name/unit_id/tax_type/preço + jQuery validate). O `store()`
//     NUNCA validou server-side, POR DESIGN. Meu teste batia no endpoint cru,
//     pulando a view — testava um contrato que não existe. O `CU-PROD-01.1` do SDD
//     dizia "client + server"; é impreciso (só client).
//   - "defaults server" (ex-UC-PCAD-03): os defaults moram no FORM (Blade + o
//     useForm do React), não no `store()`. O caminho real não grava lixo.
//
//   Os dois viraram GAPS DE PARIDADE Blade→React (o que a casca React não migrou),
//   não achados de bug. Ver a grade em Create.charter.md §Paridade Blade→React.
// =============================================================================

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
// UC-PCAD-05 — CU-PROD-01.5 `[T0]`: cadastro NÃO aceita insumo de outro business.
//   ACHADO Tier 0 REAL da [M]/[F] (#4417): `store()` gravava category_id alheio
//   (`$request->only()` sem `exists:` escopado; família do UC-PTAB-04/#4300). FIX no
//   `store()` (guard business-scoped nos FKs, ANTES do try). ⚠️ Toca o `store()` legado
//   (~6.4k chamadas) — precisa da lane `Estoque · MySQL` (CT 100, biz1+biz2) pra provar
//   que não regride os fluxos antigos. Failing-first, padrão #4300/#4417.
// =============================================================================

it('UC-PCAD-05 · category_id de outro business não vincula', function () {
    $outroBizId = EstoqueFixture::secondBusinessId();
    if ($outroBizId === null) {
        $this->markTestSkipped('DB só tem 1 business — sem par cross-tenant pra provar isolamento.');
    }

    // Categoria que pertence ao OUTRO business (insumo alheio).
    $catAlheiaId = DB::table('categories')->insertGetId([
        'name' => 'Cat alheia UC-PCAD-05 ' . uniqid(),
        'business_id' => $outroBizId,
        'category_type' => 'product',
        'created_by' => (int) $this->user->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $bizId = (int) $this->business->id;
    $payload = postProdutoMinimo($bizId, ['category_id' => $catAlheiaId]);

    $this->post('/products', $payload);

    // O produto NÃO pode nascer carimbado com a categoria alheia — o guard recusa o FK.
    $vazou = DB::table('products')->where('category_id', $catAlheiaId)->count();
    expect($vazou)->toBe(0, 'store() gravou category_id de outro business (vazamento Tier 0).');
});

// =============================================================================
// UC-PCAD-06 — CU-PROD-07.2 `[T0]`: duplicar produto alheio → 404
//   Aqui o 404 é o CONTRATO falando (o CU crava), não proxy inventado por charter.
//   FIX no mesmo PR (failing-first, padrão #4300): create() L539 find()→findOrFail().
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
