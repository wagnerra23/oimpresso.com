<?php

declare(strict_types=1);
// Cobre UC-PCAD-07 (resources/js/Pages/Produto/Create.casos.md) e UC-PEDIT-09
// (resources/js/Pages/Produto/Edit.casos.md) — G-2 rastreabilidade caso↔teste.

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Modules\NfeBrasil\Services\MotorTributarioService;
use Modules\NfeBrasil\Services\NfeService;
use Modules\NfeBrasil\Services\Tributacao\ProdutoFiscalContext;
use Modules\NfeBrasil\Services\Tributacao\TributoCalculado;
use Spatie\Permission\Models\Permission;
use Tests\Support\EstoqueFixture;

/**
 * Origem da mercadoria por produto — tabela A da NF-e (`orig`, 0 a 8).
 *
 * ÂNCORA (contrato, não implementação): AR-PROD-128 (o Office Comercial tinha a "Origem da
 * Mercadoria" no cadastro, `PRODUTO.ORIGEM_MERCADORIA`) + ADR 0093 (Tier 0) + a tabela A do
 * Manual de Orientação do Contribuinte (o `orig` só aceita 0 a 8).
 *
 * O que cada caso trava:
 *   · grava 0–8 vindo da tela React; recusa fora da tabela sem gravar;
 *   · o Blade (que não manda o campo) não zera a origem escolhida na React;
 *   · produto de outra empresa não muda (404 antes de qualquer gravação);
 *   · a emissão por item (NfeBrasil) leva a origem da linha ao `icms.orig`, e linha sem a
 *     chave continua saindo com 0 — o comportamento de antes desta coluna.
 *
 * ⛔ Tenant canônico de teste = 98 (ADR 0358). NUNCA biz=4.
 */
uses(DatabaseTransactions::class);

function origemPayloadEdit(object $produto, array $override = []): array
{
    return array_merge([
        'name' => $produto->name,
        'sku' => $produto->sku,
        'brand_id' => $produto->brand_id ?? '',
        'unit_id' => $produto->unit_id,
        'category_id' => $produto->category_id ?? '',
        'tax' => $produto->tax ?? '',
        'tax_type' => $produto->tax_type,
        'barcode_type' => $produto->barcode_type,
        'alert_quantity' => $produto->alert_quantity ?? '',
        'weight' => $produto->weight ?? '',
        'product_description' => $produto->product_description ?? '',
        'product_locations' => [],
        'preparation_time_in_minutes' => '',
    ], $override);
}

function origemDoProduto(int $productId): int
{
    return (int) DB::table('products')->where('id', $productId)->value('origem_mercadoria');
}

beforeEach(function () {
    if (! EstoqueFixture::schemaReady()) {
        $this->markTestSkipped('Schema UltimatePOS/seed ausente — roda na lane MySQL / CT 100.');
    }

    try {
        $this->business = $this->seededTenant(); // biz=98 (ADR 0358 — nunca biz=4).
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
    Permission::findOrCreate('product.update', 'web');
    $this->user->givePermissionTo(['product.create', 'product.update']);
});

// =============================================================================
// UC-PCAD-07 — cadastrar produto com a origem escolhida
// =============================================================================

it('UC-PCAD-07 · produto novo grava a origem escolhida na tela', function () {
    $bizId = (int) $this->business->id;
    $nome = 'Produto UC-PCAD-07 ' . uniqid();

    $this->post('/products', [
        'name' => $nome, 'sku' => '', 'type' => 'single',
        'unit_id' => EstoqueFixture::unitId($bizId), 'tax_type' => 'exclusive',
        'enable_stock' => 1, 'origem_mercadoria' => 1,
    ]);

    $produto = DB::table('products')->where('business_id', $bizId)->where('name', $nome)->first();
    expect($produto)->not->toBeNull();
    expect((int) $produto->origem_mercadoria)->toBe(1);
});

it('UC-PCAD-07 · produto novo sem o campo (caminho Blade) nasce com origem 0', function () {
    $bizId = (int) $this->business->id;
    $nome = 'Produto UC-PCAD-07 blade ' . uniqid();

    $this->post('/products', [
        'name' => $nome, 'sku' => '', 'type' => 'single',
        'unit_id' => EstoqueFixture::unitId($bizId), 'tax_type' => 'exclusive', 'enable_stock' => 1,
    ]);

    $produto = DB::table('products')->where('business_id', $bizId)->where('name', $nome)->first();
    expect($produto)->not->toBeNull();
    expect((int) $produto->origem_mercadoria)->toBe(0);
});

// =============================================================================
// UC-PEDIT-09 — editar a origem
// =============================================================================

it('UC-PEDIT-09 · editar grava a origem escolhida', function () {
    $p = EstoqueFixture::singleProduct((int) $this->business->id);
    $produto = DB::table('products')->find($p->productId);
    expect(origemDoProduto($p->productId))->toBe(0); // default da coluna

    $this->put('/products/' . $p->productId, origemPayloadEdit($produto, ['origem_mercadoria' => 2]));

    expect(origemDoProduto($p->productId))->toBe(2);
});

it('UC-PEDIT-09 · origem fora da tabela A é recusada e nada é gravado', function () {
    $p = EstoqueFixture::singleProduct((int) $this->business->id);
    $produto = DB::table('products')->find($p->productId);

    $this->put('/products/' . $p->productId, origemPayloadEdit($produto, [
        'name' => $produto->name . ' (não pode gravar)', 'origem_mercadoria' => 9,
    ]))->assertSessionHasErrors('origem_mercadoria');

    // A recusa vem da validação ANTES do save: nem a origem nem o nome mudaram.
    expect(origemDoProduto($p->productId))->toBe(0);
    expect(DB::table('products')->where('id', $p->productId)->value('name'))->toBe($produto->name);
});

it('UC-PEDIT-09 · editar pelo Blade (sem o campo) não zera a origem escolhida', function () {
    $p = EstoqueFixture::singleProduct((int) $this->business->id);
    DB::table('products')->where('id', $p->productId)->update(['origem_mercadoria' => 1]);
    $produto = DB::table('products')->find($p->productId);
    $novoNome = $produto->name . ' (blade)';

    $this->put('/products/' . $p->productId, origemPayloadEdit($produto, ['name' => $novoNome]));

    // Pré-condição anti-vácuo: o PUT persistiu (senão "a origem ficou" não prova nada).
    expect(DB::table('products')->where('id', $p->productId)->value('name'))->toBe($novoNome);
    expect(origemDoProduto($p->productId))->toBe(1);
});

it('UC-PEDIT-09 · editar a origem de produto de outra empresa devolve 404 e não grava', function () {
    $outroBizId = EstoqueFixture::secondBusinessId();
    if ($outroBizId === null) {
        $this->markTestSkipped('DB só tem 1 business — sem par cross-tenant pra provar isolamento.');
    }

    $alheio = EstoqueFixture::singleProduct($outroBizId);
    $produto = DB::table('products')->find($alheio->productId);

    $this->put('/products/' . $alheio->productId, origemPayloadEdit($produto, ['origem_mercadoria' => 1]))
        ->assertStatus(404);

    expect(origemDoProduto($alheio->productId))->toBe(0);
});

// =============================================================================
// Emissão por item (NfeBrasil) — a origem da linha vai ao `icms.orig`
// =============================================================================

function motorFakeOrigem(): MotorTributarioService
{
    return new class extends MotorTributarioService {
        public function __construct() {}

        public function calcular(ProdutoFiscalContext $produto, int $businessId, string $ufOrigem, string $ufDestino): TributoCalculado
        {
            return new TributoCalculado(
                cfop: '6102', csosn: null, cst: '00',
                aliquota_icms: 0.12, aliquota_pis: 0.0165, aliquota_cofins: 0.076, aliquota_ipi: 0.0,
                valor_icms: round($produto->valor * 0.12, 2),
                valor_pis: round($produto->valor * 0.0165, 2),
                valor_cofins: round($produto->valor * 0.076, 2),
                valor_ipi: 0.0,
                nivel_usado: 2,
                regra_id: null,
            );
        }
    };
}

it('UC-PEDIT-09 · a nota leva a origem de cada produto, e linha sem origem sai com 0', function () {
    $linha = fn (int $id, ?int $origem) => array_filter([
        'sell_line_id' => $id, 'product_id' => 9100 + $id, 'cprod' => "SKU{$id}",
        'xprod' => "Produto {$id}", 'ncm' => '49111090', 'origem' => $origem, 'unidade' => 'UN',
        'quantidade' => 1.0, 'valor_unitario' => 100.0,
    ], fn ($v) => $v !== null);

    $r = app(NfeService::class)->montarItensNfe(
        linhas: [$linha(1, 1), $linha(2, 0), $linha(3, null)], valorNota: 300.0, frete: 0.0,
        ncmDefault: '49111090', motor: motorFakeOrigem(), businessId: 98, ufOrigem: 'SC', ufDestino: 'SP',
    );

    expect(array_map(fn ($d) => $d['icms']['orig'], $r['dets']))->toBe([1, 0, 0]);
    // A origem não mexe em valor: o total da nota é o mesmo dos três itens.
    expect(array_sum(array_column($r['dets'], 'vprod')))->toBe(300.0);
});

it('UC-PEDIT-09 · a linha da venda lê a origem do produto da própria empresa', function () {
    $bizId = (int) $this->business->id;
    $p = EstoqueFixture::singleProduct($bizId);
    DB::table('products')->where('id', $p->productId)->update(['origem_mercadoria' => 2]);
    $venda = EstoqueFixture::saleWithLine($p, 0, EstoqueFixture::locationId($bizId), 1.0);

    $ler = fn (int $biz) => (new ReflectionMethod(NfeService::class, 'linhasDaVenda'))
        ->invoke(app(NfeService::class), $biz, $venda['transaction_id']);

    $linhas = $ler($bizId);
    expect($linhas)->toHaveCount(1);
    expect($linhas[0]['origem'])->toBe(2);

    // Tier 0: a mesma venda lida por outra empresa não traz a linha (o JOIN filtra business_id).
    $outroBizId = EstoqueFixture::secondBusinessId();
    if ($outroBizId !== null) {
        expect($ler($outroBizId))->toBe([]);
    }
});
