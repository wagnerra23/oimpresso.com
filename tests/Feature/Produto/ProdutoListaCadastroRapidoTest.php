<?php

declare(strict_types=1);
// Cobre UC-PIDX-14 (resources/js/Pages/Produto/Index.casos.md) — G-2 rastreabilidade caso↔teste.

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EstoqueFixture;

/**
 * Cadastro rápido da lista de produtos (`POST /products/cadastro-rapido`) — protótipo
 * `prototipo-ui/cowork/Felipe/produto-blade.jsx`, `ModalCadastroRapido`.
 *
 * O contrato é o formulário Blade de cadastro rápido (`quick_add_product.blade.php` +
 * `single_product_form_part`), que grava por `saveQuickProduct`. O endpoint novo NÃO grava por conta
 * própria: monta o mesmo pedido e entrega àquele método. Por isso a prova de valor tem dois caminhos:
 *   1. a conta à mão do que a variação deve guardar;
 *   2. o MESMO produto criado pelo caminho Blade, com os campos que a tela Blade mandaria —
 *      as duas variações têm de sair iguais.
 *
 * Tenant semeado do CI (ADR 0358). Nunca biz=4. Pest só no CI / CT 100.
 */
uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasColumn('transactions', 'essentials_duration')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }
    $this->business = $this->seededTenant();
    $this->unidade = EstoqueFixture::unitId($this->business->id);
    // Um local só, permitido ao usuário: o cadastro rápido vai para ele sem perguntar.
    $this->local = EstoqueFixture::locationId($this->business->id, '-PCR-' . uniqid());
});

/**
 * Entra com as permissões pedidas e com acesso aos locais dados. O acesso a local é permissão
 * DIRETA do usuário (`location.{id}`): `User::permitted_locations()` lê `$user->permissions`, que
 * não inclui as do papel — dá-la pelo papel deixaria o usuário sem local nenhum.
 */
function pcrEntrar($teste, array $permissoes, array $locais = []): void
{
    $user = $teste->usuarioComPermissoes($permissoes, $teste->business);
    foreach ($locais as $id) {
        \Spatie\Permission\Models\Permission::findOrCreate('location.' . $id, 'web');
        $user->givePermissionTo('location.' . $id);
    }
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    $teste->user = \App\User::findOrFail($user->id);
    $teste->actingAs($teste->user);
    session(['user.business_id' => $teste->business->id, 'user.id' => $teste->user->id, 'business.id' => $teste->business->id]);
    session(['currency' => ['symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ','], 'business.date_format' => 'd/m/Y']);
}

/** Quem cadastra e enxerga UM local só (o do cenário). */
function pcrEntrarComUmLocal($teste): void
{
    pcrEntrar($teste, ['product.create'], [$teste->local]);
    // Anti-vácuo do cenário: o usuário enxerga exatamente UM local, o do teste.
    expect($teste->user->permitted_locations())->toBe([$teste->local]);
}

function pcrNome(): string
{
    return 'PCR ' . strtoupper(bin2hex(random_bytes(5)));
}

/** O que a janela manda: preços como texto com vírgula decimal e sem milhar. */
function pcrPedido($teste, array $over = []): array
{
    return array_merge([
        'name' => pcrNome(),
        'sku' => '',
        'unit_id' => $teste->unidade,
        'category_id' => null,
        'compra' => '10',
        'venda' => '15',
        'enable_stock' => true,
    ], $over);
}

function pcrProduto(string $nome): ?object
{
    return DB::table('products')->where('name', $nome)->first();
}

/** Os cinco campos de preço da variação do produto, como números. */
function pcrPrecos(int $productId): array
{
    $v = DB::table('variations')->where('product_id', $productId)->first();
    expect($v)->not->toBeNull();

    return [
        'compra' => (float) $v->default_purchase_price,
        'compra_com_imposto' => (float) $v->dpp_inc_tax,
        'margem' => (float) $v->profit_percent,
        'venda' => (float) $v->default_sell_price,
        'venda_com_imposto' => (float) $v->sell_price_inc_tax,
    ];
}

/** O MESMO produto pelo caminho Blade, com os campos que o formulário Blade mandaria. */
function pcrCriarPeloBlade($teste, array $precos): array
{
    $nome = pcrNome();
    $teste->post('/products/save_quick_product', array_merge([
        'name' => $nome, 'unit_id' => $teste->unidade, 'barcode_type' => 'C128', 'tax_type' => 'exclusive',
        'type' => 'single', 'sku' => '', 'enable_stock' => 1, 'product_locations' => [$teste->local],
    ], $precos));
    $p = pcrProduto($nome);
    expect($p)->not->toBeNull();

    return pcrPrecos((int) $p->id);
}

it('UC-PIDX-14 · grava os preços que o formulário Blade gravaria, e nasce com os padrões dele', function () {
    pcrEntrarComUmLocal($this);
    $pedido = pcrPedido($this);

    $r = $this->postJson('/products/cadastro-rapido', $pedido);
    $r->assertOk();
    expect($r->json('success'))->toBeTrue();

    $p = pcrProduto($pedido['name']);
    expect($p)->not->toBeNull();
    expect($r->json('produto.id'))->toBe((int) $p->id);
    $precos = pcrPrecos((int) $p->id);

    // Caminho 1 — conta à mão: compra 10, venda 15, sem imposto ⇒ com imposto = igual; margem (15−10)/10 = 50%.
    expect($precos)->toEqual(['compra' => 10.0, 'compra_com_imposto' => 10.0, 'margem' => 50.0, 'venda' => 15.0, 'venda_com_imposto' => 15.0]);
    // Caminho 2 — o formulário Blade, com o que o JS dele preenche para os mesmos dois preços.
    $blade = pcrCriarPeloBlade($this, [
        'single_dpp' => '10,00', 'single_dpp_inc_tax' => '10,00', 'profit_percent' => '50,00',
        'single_dsp' => '15,00', 'single_dsp_inc_tax' => '15,00',
    ]);
    expect($precos)->toEqual($blade);

    // Os padrões do formulário Blade e o carimbo da empresa.
    expect((int) $p->business_id)->toBe((int) $this->business->id);
    expect([(string) $p->type, (string) $p->barcode_type, (string) $p->tax_type, $p->tax, (int) $p->enable_stock, (int) $p->not_for_selling])
        ->toEqual(['single', 'C128', 'exclusive', null, 1, 0]);
    // SKU em branco: o servidor gera, e a variação recebe o mesmo.
    expect(trim((string) $p->sku))->not->toBe('');
    expect((string) DB::table('variations')->where('product_id', $p->id)->value('sub_sku'))->toBe((string) $p->sku);
    // Empresa com um local permitido: o produto vai para ele sem a janela perguntar.
    expect(DB::table('product_locations')->where('product_id', $p->id)->pluck('location_id')->map(fn ($id) => (int) $id)->all())->toBe([(int) $this->local]);
});

it('UC-PIDX-14 · preço com centavos e milhar: grava o número digitado; a margem vai com 4 casas', function () {
    pcrEntrarComUmLocal($this);
    $pedido = pcrPedido($this, ['compra' => '1234,56', 'venda' => '2000']);

    $this->postJson('/products/cadastro-rapido', $pedido)->assertOk();
    $precos = pcrPrecos((int) pcrProduto($pedido['name'])->id);

    // Conta à mão: (2000 − 1234,56) / 1234,56 × 100 = 62,001037…
    expect($precos['compra'])->toBe(1234.56);
    expect($precos['compra_com_imposto'])->toBe(1234.56);
    expect($precos['venda'])->toBe(2000.0);
    expect($precos['venda_com_imposto'])->toBe(2000.0);
    expect(abs($precos['margem'] - 62.001037))->toBeLessThan(0.0001);

    // O formulário Blade arredondava a margem na tela (2 casas) antes de enviar: os PREÇOS saem
    // iguais; a margem difere só nesse arredondamento.
    $blade = pcrCriarPeloBlade($this, [
        'single_dpp' => '1.234,56', 'single_dpp_inc_tax' => '1.234,56', 'profit_percent' => '62,00',
        'single_dsp' => '2.000,00', 'single_dsp_inc_tax' => '2.000,00',
    ]);
    foreach (['compra', 'compra_com_imposto', 'venda', 'venda_com_imposto'] as $campo) {
        expect($precos[$campo])->toBe($blade[$campo]);
    }
    expect(abs($precos['margem'] - $blade['margem']))->toBeLessThan(0.005);
});

it('UC-PIDX-14 · custo zero dá margem zero; venda abaixo do custo dá margem negativa', function () {
    pcrEntrarComUmLocal($this);

    $semCusto = pcrPedido($this, ['compra' => '0', 'venda' => '20']);
    $this->postJson('/products/cadastro-rapido', $semCusto)->assertOk();
    expect(pcrPrecos((int) pcrProduto($semCusto['name'])->id))
        ->toEqual(['compra' => 0.0, 'compra_com_imposto' => 0.0, 'margem' => 0.0, 'venda' => 20.0, 'venda_com_imposto' => 20.0]);

    $prejuizo = pcrPedido($this, ['compra' => '20', 'venda' => '15']);
    $this->postJson('/products/cadastro-rapido', $prejuizo)->assertOk();
    // (15 − 20) / 20 = −25%.
    expect(pcrPrecos((int) pcrProduto($prejuizo['name'])->id))
        ->toEqual(['compra' => 20.0, 'compra_com_imposto' => 20.0, 'margem' => -25.0, 'venda' => 15.0, 'venda_com_imposto' => 15.0]);
});

it('UC-PIDX-14 · preço fora do formato é recusado e nada é criado', function (string $campo, string $valor) {
    pcrEntrarComUmLocal($this);
    $pedido = pcrPedido($this, [$campo => $valor]);

    $r = $this->postJson('/products/cadastro-rapido', $pedido);

    $r->assertStatus(422);
    expect($r->json('errors'))->toHaveKey($campo);
    expect(pcrProduto($pedido['name']))->toBeNull();
})->with([
    'ponto que seria lido como milhar' => ['compra', '12.125'],
    'milhar com ponto' => ['venda', '1.234,56'],
    'ponto decimal' => ['compra', '80.00'],
    'negativo' => ['venda', '-5'],
    'texto' => ['compra', 'dez'],
    'vazio' => ['venda', ''],
]);

it('UC-PIDX-14 · o mesmo valor com vírgula passa (o recusado acima não é o endpoint quebrado)', function () {
    pcrEntrarComUmLocal($this);
    $pedido = pcrPedido($this, ['compra' => '12,125', 'venda' => '80']);

    $this->postJson('/products/cadastro-rapido', $pedido)->assertOk();

    $precos = pcrPrecos((int) pcrProduto($pedido['name'])->id);
    expect($precos['compra'])->toBe(12.125);
    expect($precos['venda'])->toBe(80.0);
});

it('UC-PIDX-14 · campo que a janela não tem não chega à gravação', function () {
    pcrEntrarComUmLocal($this);
    $pedido = pcrPedido($this, [
        'opening_stock' => [$this->local => ['quantity' => '50', 'purchase_price' => '1']],
        'not_for_selling' => 1,
        'enable_sr_no' => 1,
        'alert_quantity' => '7',
        'single_dsp' => '999',
        'profit_percent' => '900',
        'business_id' => 999999,
    ]);

    $this->postJson('/products/cadastro-rapido?single_dsp=999&not_for_selling=1', $pedido)->assertOk();

    $p = pcrProduto($pedido['name']);
    // Anti-vácuo: o produto foi criado — o que segue é o que ele NÃO recebeu.
    expect($p)->not->toBeNull();
    expect((int) $p->business_id)->toBe((int) $this->business->id);
    // Controle: um produto do mesmo endpoint SEM os campos enfiados — o alerta tem de sair igual.
    $limpo = pcrPedido($this);
    $this->postJson('/products/cadastro-rapido', $limpo)->assertOk();
    $controle = pcrProduto($limpo['name']);
    expect($controle)->not->toBeNull();
    expect([(int) $p->not_for_selling, (int) $p->enable_sr_no, $p->alert_quantity])
        ->toEqual([0, 0, $controle->alert_quantity]);
    expect((float) $p->alert_quantity)->not->toBe(7.0);
    expect(pcrPrecos((int) $p->id)['venda'])->toBe(15.0);
    expect(pcrPrecos((int) $p->id)['margem'])->toBe(50.0);
    expect(DB::table('variation_location_details')->where('product_id', $p->id)->count())->toBe(0);
    expect(DB::table('purchase_lines')->where('product_id', $p->id)->count())->toBe(0);
});

it('UC-PIDX-14 · com mais de um local é preciso escolher, e só entra local permitido da empresa', function () {
    $outroLocal = EstoqueFixture::locationId($this->business->id, '-PCR2-' . uniqid());
    pcrEntrar($this, ['product.create'], [$this->local, $outroLocal]);
    expect($this->user->permitted_locations())->toHaveCount(2);

    // Sem escolher: recusa e não cria.
    $semLocal = pcrPedido($this);
    $r = $this->postJson('/products/cadastro-rapido', $semLocal);
    $r->assertStatus(422);
    expect($r->json('errors'))->toHaveKey('product_locations');
    expect(pcrProduto($semLocal['name']))->toBeNull();

    // Escolhendo um dos dois, mais um id que não é local desta empresa: entra só o permitido.
    $comLocal = pcrPedido($this, ['product_locations' => [$outroLocal, 999999999]]);
    $this->postJson('/products/cadastro-rapido', $comLocal)->assertOk();
    $p = pcrProduto($comLocal['name']);
    expect($p)->not->toBeNull();
    expect(DB::table('product_locations')->where('product_id', $p->id)->pluck('location_id')->map(fn ($id) => (int) $id)->all())->toBe([(int) $outroLocal]);

    // Só o id alheio: é como não ter escolhido.
    $soAlheio = pcrPedido($this, ['product_locations' => [999999999]]);
    $this->postJson('/products/cadastro-rapido', $soAlheio)->assertStatus(422);
    expect(pcrProduto($soAlheio['name']))->toBeNull();
});

it('UC-PIDX-14 · "Gerenciar estoque" desmarcado cria produto sem controle de estoque', function () {
    pcrEntrarComUmLocal($this);
    $pedido = pcrPedido($this, ['enable_stock' => false]);

    $this->postJson('/products/cadastro-rapido', $pedido)->assertOk();

    $p = pcrProduto($pedido['name']);
    expect($p)->not->toBeNull();
    expect((int) $p->enable_stock)->toBe(0);
});

it('UC-PIDX-14 · SKU já usado na empresa é recusado; nome e unidade são obrigatórios', function () {
    pcrEntrarComUmLocal($this);
    $primeiro = pcrPedido($this, ['sku' => 'PCR-' . strtoupper(uniqid())]);
    $this->postJson('/products/cadastro-rapido', $primeiro)->assertOk();
    expect((string) pcrProduto($primeiro['name'])->sku)->toBe($primeiro['sku']);

    $repetido = pcrPedido($this, ['sku' => $primeiro['sku']]);
    $r = $this->postJson('/products/cadastro-rapido', $repetido);
    $r->assertStatus(422);
    expect($r->json('errors'))->toHaveKey('sku');
    expect(pcrProduto($repetido['name']))->toBeNull();

    $semNome = $this->postJson('/products/cadastro-rapido', pcrPedido($this, ['name' => '']));
    $semNome->assertStatus(422);
    expect($semNome->json('errors'))->toHaveKey('name');
    $semUnidade = pcrPedido($this, ['unit_id' => null]);
    $r2 = $this->postJson('/products/cadastro-rapido', $semUnidade);
    $r2->assertStatus(422);
    expect($r2->json('errors'))->toHaveKey('unit_id');
    expect(pcrProduto($semUnidade['name']))->toBeNull();
});

it('UC-PIDX-14 · unidade ou categoria de outra empresa não é aceita', function () {
    $outro = EstoqueFixture::secondBusinessId();
    if ($outro === null) {
        $this->markTestSkipped('Sem 2º business semeado.');
    }
    pcrEntrarComUmLocal($this);
    $unidadeAlheia = EstoqueFixture::unitId($outro);

    $pedido = pcrPedido($this, ['unit_id' => $unidadeAlheia]);
    $r = $this->postJson('/products/cadastro-rapido', $pedido);

    $r->assertStatus(422);
    expect($r->json('errors'))->toHaveKey('unit_id');
    expect(pcrProduto($pedido['name']))->toBeNull();
    // Anti-vácuo: com a unidade da própria empresa o mesmo pedido cria.
    $ok = pcrPedido($this);
    $this->postJson('/products/cadastro-rapido', $ok)->assertOk();
    expect(pcrProduto($ok['name']))->not->toBeNull();
});

it('UC-PIDX-14 · sem product.create o cadastro rápido é recusado', function () {
    pcrEntrar($this, ['product.view'], [$this->local]);
    $pedido = pcrPedido($this);

    $this->postJson('/products/cadastro-rapido', $pedido)->assertStatus(403);

    expect(pcrProduto($pedido['name']))->toBeNull();
});
