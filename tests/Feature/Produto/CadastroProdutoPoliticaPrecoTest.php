<?php

declare(strict_types=1);

// Cobre UC-PCAD-23 (resources/js/Pages/Produto/Create.casos.md) e UC-PEDIT-20
// (resources/js/Pages/Produto/Edit.casos.md) — G-2 rastreabilidade caso↔teste.

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\Support\EstoqueFixture;

/**
 * Política de preço do produto, no cadastro (servidor).
 *
 * ÂNCORA (contrato, não implementação): CU-PROD-21 do SDD do cadastro de produto — três políticas com
 * os nomes de Fluxos e da Fabricação (`fixo` · `acompanha` · `margem`), produto novo com Preço fixo
 * (resposta do [W] na US-PROD-030), "Margem alvo (%)" só com a política `margem`, e a margem é sobre o
 * preço de venda (por isso fica abaixo de 100).
 *
 * REGRA MESTRE de valor: este passo só grava a escolha. Nenhum preço do produto muda ao salvar; quem
 * vai ler a política é o recebimento da compra, num passo seguinte.
 *
 * ⛔ Tenant canônico de teste = 98 (ADR 0358). NUNCA biz=4.
 */
uses(DatabaseTransactions::class);

function cppCriar(object $test, array $campos): ?object
{
    $bizId = (int) $test->business->id;
    $nome = 'Produto UC-PCAD-23 ' . uniqid();
    $test->post('/products', array_merge([
        'name' => $nome, 'sku' => '', 'type' => 'single',
        'unit_id' => EstoqueFixture::unitId($bizId), 'tax_type' => 'exclusive', 'enable_stock' => 0,
    ], $campos));

    return DB::table('products')->where('business_id', $bizId)->where('name', $nome)->first();
}

function cppEditar(object $test, object $produto, array $campos)
{
    // As chaves que a tela React manda (o update() lê várias direto do envio).
    return $test->put("/products/{$produto->id}", array_merge([
        'name' => $produto->name, 'sku' => $produto->sku, 'brand_id' => $produto->brand_id ?? '',
        'unit_id' => $produto->unit_id, 'category_id' => $produto->category_id ?? '', 'tax' => $produto->tax ?? '',
        'tax_type' => $produto->tax_type, 'barcode_type' => $produto->barcode_type,
        'alert_quantity' => $produto->alert_quantity ?? '', 'weight' => $produto->weight ?? '',
        'product_description' => $produto->product_description ?? '',
        'product_locations' => [], 'preparation_time_in_minutes' => '',
    ], $campos));
}

function cppRecarregar(object $produto): object
{
    return DB::table('products')->where('id', $produto->id)->first();
}

/** Os preços das variações do produto, para provar que salvar a política não mexe em preço. */
function cppPrecos(int $productId): array
{
    return DB::table('variations')->where('product_id', $productId)->orderBy('id')
        ->get(['default_purchase_price', 'dpp_inc_tax', 'profit_percent', 'default_sell_price', 'sell_price_inc_tax'])
        ->map(fn ($v) => (array) $v)->all();
}

beforeEach(function () {
    if (! EstoqueFixture::schemaReady() || ! Schema::hasColumn('products', 'politica_preco')) {
        $this->markTestSkipped('Schema UltimatePOS ou a coluna politica_preco ausente — roda na lane MySQL / CT 100.');
    }
    $this->business = $this->seededTenant();
    $this->user = User::where('business_id', $this->business->id)->first();
    if (! $this->user) {
        $this->markTestSkipped('Sem user no business seeded.');
    }
    $this->actingAs($this->user);
    session([
        'user.business_id' => $this->business->id, 'user.id' => $this->user->id, 'business.id' => $this->business->id,
        'currency' => ['id' => 1, 'code' => 'BRL', 'symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ','],
    ]);
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::findOrCreate('product.create', 'web');
    Permission::findOrCreate('product.update', 'web');
    $this->user->givePermissionTo(['product.create', 'product.update']);
});

// ─── UC-PCAD-23 · Novo ──────────────────────────────────────────────────────

it('UC-PCAD-23 · produto novo grava a política Margem alvo com a margem', function () {
    $produto = cppCriar($this, ['politica_preco' => 'margem', 'margem_alvo_pct' => '38.5']);

    expect($produto)->not->toBeNull();
    expect($produto->politica_preco)->toBe('margem');
    expect((float) $produto->margem_alvo_pct)->toBe(38.5);
});

it('UC-PCAD-23 · sem política no envio (o Blade não manda) o produto nasce com Preço fixo', function () {
    $produto = cppCriar($this, []);

    expect($produto)->not->toBeNull();
    expect($produto->politica_preco)->toBe('fixo');
    expect($produto->margem_alvo_pct)->toBeNull();
});

it('UC-PCAD-23 · fora de Margem alvo a margem não fica gravada', function () {
    $produto = cppCriar($this, ['politica_preco' => 'acompanha', 'margem_alvo_pct' => '30']);

    expect($produto->politica_preco)->toBe('acompanha');
    expect($produto->margem_alvo_pct)->toBeNull();
});

it('UC-PCAD-23 · Margem alvo sem margem, margem de 100% ou política desconhecida são recusadas e nada nasce', function (array $campos, string $campoComErro) {
    $antes = DB::table('products')->where('business_id', $this->business->id)->count();
    $resp = $this->post('/products', array_merge([
        'name' => 'Produto UC-PCAD-23 recusa ' . uniqid(), 'sku' => '', 'type' => 'single',
        'unit_id' => EstoqueFixture::unitId((int) $this->business->id), 'tax_type' => 'exclusive', 'enable_stock' => 0,
    ], $campos));

    $resp->assertSessionHasErrors([$campoComErro]);
    expect(DB::table('products')->where('business_id', $this->business->id)->count())->toBe($antes);
})->with([
    'margem sem valor' => [['politica_preco' => 'margem', 'margem_alvo_pct' => ''], 'margem_alvo_pct'],
    'margem de 100%' => [['politica_preco' => 'margem', 'margem_alvo_pct' => '100'], 'margem_alvo_pct'],
    'margem zero' => [['politica_preco' => 'margem', 'margem_alvo_pct' => '0'], 'margem_alvo_pct'],
    'política desconhecida' => [['politica_preco' => 'sempre'], 'politica_preco'],
]);

// ─── UC-PEDIT-20 · Editar ───────────────────────────────────────────────────

it('UC-PEDIT-20 · o Editar troca a política e a margem, e nenhum preço do produto muda', function () {
    $produto = cppCriar($this, []);
    $precosAntes = cppPrecos((int) $produto->id);
    expect($precosAntes)->not->toBeEmpty();

    cppEditar($this, $produto, ['politica_preco' => 'margem', 'margem_alvo_pct' => '45'])->assertSessionHasNoErrors();
    $depois = cppRecarregar($produto);
    expect($depois->politica_preco)->toBe('margem');
    expect((float) $depois->margem_alvo_pct)->toBe(45.0);
    // REGRA MESTRE de valor: gravar a política não recalcula preço nenhum.
    expect(cppPrecos((int) $produto->id))->toBe($precosAntes);

    cppEditar($this, $depois, ['politica_preco' => 'fixo', 'margem_alvo_pct' => ''])->assertSessionHasNoErrors();
    $fixo = cppRecarregar($produto);
    expect($fixo->politica_preco)->toBe('fixo');
    expect($fixo->margem_alvo_pct)->toBeNull();
});

it('UC-PEDIT-20 · salvar sem a política no envio (tela sem mudança, ou o Blade) não muda o que está gravado', function () {
    $produto = cppCriar($this, ['politica_preco' => 'margem', 'margem_alvo_pct' => '45']);

    cppEditar($this, $produto, [])->assertSessionHasNoErrors();
    $depois = cppRecarregar($produto);
    expect($depois->politica_preco)->toBe('margem');
    expect((float) $depois->margem_alvo_pct)->toBe(45.0);
});

it('UC-PEDIT-20 · o Editar entrega a política e a margem gravadas para a tela', function () {
    $produto = cppCriar($this, ['politica_preco' => 'margem', 'margem_alvo_pct' => '45']);

    $this->withHeaders([
        'X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest',
        'X-Inertia-Version' => (string) app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request()),
    ])
        ->get("/products/{$produto->id}/edit")
        ->assertOk()
        ->assertJsonPath('component', 'Produto/Edit')
        ->assertJsonPath('props.product.politicaPreco', 'margem')
        // O json_encode do PHP escreve 45.0 como 45: a tela recebe um número, inteiro ou não.
        ->assertJsonPath('props.product.margemAlvoPct', fn ($v) => is_numeric($v) && (float) $v === 45.0);
});
