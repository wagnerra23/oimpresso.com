<?php

declare(strict_types=1);

use App\Product;
use App\User;
use App\Utils\ModuleUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Compras\Services\CustoDoInsumoService;
use Modules\Compras\Services\MetodoCustoDoProduto;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\EstoqueFixture;
use Tests\Support\WithSeededTenant;

/**
 * US-COM-024 · UC-FLX-38 — "Método de custo" no cadastro do produto (formulário Blade de editar),
 * gravado pelo gancho `after_product_saved` do módulo Compras no `PUT /products/{id}` real.
 * Cobre UC-INSMET-10..16 (resources/js/Pages/Manufacturing/Insumos.casos.md).
 *
 * Âncora de contrato: memory/requisitos/Compras/SPEC.md US-COM-024. Vale para qualquer item
 * comprado (matéria-prima ou revenda): o app não marca tipo de produto.
 *
 * VALOR (regra mestre, proibicoes.md): na variação 1, compras recebidas B 10 un × 12,00 e
 * C 30 un × 16,00 com 10 devolvidas. Em 31/03: médio = (10×12 + 20×16) / 30 = 14,6667 ·
 * último = 16,0000. O serviço confere depois de salvar o formulário.
 *
 * O core só chama os ganchos de módulo INSTALADO (`system.compras_version`, ModuleUtil). O teste
 * cria a chave dentro da transação; sem ela o campo não aparece nem grava.
 */
uses(Tests\TestCase::class, DatabaseTransactions::class, WithSeededTenant::class);

beforeEach(function () {
    if (! EstoqueFixture::schemaReady()) {
        $this->markTestSkipped('Schema UltimatePOS/seed ausente — roda na lane MySQL (compras-pest) ou no CT 100.');
    }
    try {
        $this->biz = $this->seededTenant();
    } catch (\Throwable $e) {
        $this->markTestSkipped('Schema UltimatePOS ausente: '.$e->getMessage());
    }

    if (! DB::table('system')->where('key', 'compras_version')->exists()) {
        DB::table('system')->insert(['key' => 'compras_version', 'value' => '1.0']);
    }

    $this->user = User::factory()->create([
        'business_id' => $this->biz->id, 'username' => 'metodo_produto_'.uniqid(),
        'user_type' => 'user', 'allow_login' => 1,
    ]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    foreach (['product.update', 'purchase.update'] as $p) {
        $this->user->givePermissionTo(Permission::findOrCreate($p, 'web'));
    }
    $this->actingAs($this->user);
    session(['user.business_id' => $this->biz->id, 'user.id' => $this->user->id]);

    $this->produto = EstoqueFixture::variableProduct($this->biz->id, 2);
    $this->variacoes = array_map(fn ($v) => (int) $v['variation_id'], $this->produto->variations);

    $agora = Carbon::now();
    foreach ([['2026-03-01 09:00:00', 10, 0, 12], ['2026-03-10 09:00:00', 30, 10, 16]] as [$data, $qtd, $dev, $preco]) {
        $tid = DB::table('transactions')->insertGetId([
            'business_id' => $this->biz->id, 'type' => 'purchase', 'status' => 'received', 'payment_status' => 'due',
            'transaction_date' => $data, 'ref_no' => 'METPROD-'.uniqid(), 'final_total' => $qtd * $preco,
            'total_before_tax' => $qtd * $preco, 'tax_amount' => 0, 'discount_amount' => 0,
            'created_by' => $this->user->id, 'essentials_duration' => 0, 'created_at' => $agora, 'updated_at' => $agora,
        ]);
        DB::table('purchase_lines')->insert([
            'transaction_id' => $tid, 'product_id' => $this->produto->productId, 'variation_id' => $this->variacoes[0],
            'quantity' => $qtd, 'quantity_returned' => $dev, 'pp_without_discount' => $preco,
            'purchase_price' => $preco, 'purchase_price_inc_tax' => $preco, 'item_tax' => 0,
            'created_at' => $agora, 'updated_at' => $agora,
        ]);
    }
});

/** O que o formulário de editar manda para um produto variável, sem o campo de método. */
function envioDoCadastroDoProduto(Product $produto): array
{
    $campos = [];
    for ($i = 1; $i <= 20; $i++) {
        $campos['product_custom_field'.$i] = $produto->{'product_custom_field'.$i} ?? '';
    }

    return $campos + [
        'name' => $produto->name.' (editado)', 'sku' => $produto->sku, 'brand_id' => '',
        'unit_id' => $produto->unit_id, 'category_id' => '', 'sub_category_id' => '', 'tax' => '',
        'tax_type' => $produto->tax_type, 'barcode_type' => $produto->barcode_type, 'alert_quantity' => '',
        'weight' => '', 'product_description' => '', 'product_locations' => [], 'warranty_id' => '',
        'expiry_period' => '', 'expiry_period_type' => '', 'preparation_time_in_minutes' => '',
    ];
}

/** Salva o formulário e exige que o produto tenha sido gravado (sem isto o caso não foi exercido). */
function salvarCadastroDoProduto($teste, array $extra): void
{
    $produto = Product::findOrFail($teste->produto->productId);
    $envio = envioDoCadastroDoProduto($produto) + $extra;
    $teste->put("/products/{$produto->id}", $envio);

    expect(Product::findOrFail($produto->id)->name)->toBe(
        $envio['name'],
        'PRÉ-CONDIÇÃO: o PUT /products/{id} não gravou o produto, então o gancho do método não foi exercido.'
    );
}

function metodosDasVariacoes(int $biz, array $variacoes): array
{
    $m = DB::table('compras_insumo_metodo_custo')->where('business_id', $biz)
        ->whereIn('variation_id', $variacoes)->pluck('metodo', 'variation_id')->all();
    ksort($m);

    return $m;
}

it('UC-INSMET-10 · UC-FLX-38 · escolher Último no cadastro grava em todas as variações e o custo por insumo passa a 16,0000', function () {
    $servico = app(CustoDoInsumoService::class);
    expect($servico->naData($this->biz->id, $this->variacoes[0], '2026-03-31', 'por_insumo')['custo'])->toBeNull();

    salvarCadastroDoProduto($this, ['has_module_data' => '1', 'compras_metodo_custo' => 'ultimo']);

    expect(metodosDasVariacoes($this->biz->id, $this->variacoes))
        ->toBe([$this->variacoes[0] => 'ultimo', $this->variacoes[1] => 'ultimo'])
        // À mão: compra C, 16,00.
        ->and($servico->naData($this->biz->id, $this->variacoes[0], '2026-03-31', 'por_insumo')['custo'])->toBe('16.0000');

    salvarCadastroDoProduto($this, ['has_module_data' => '1', 'compras_metodo_custo' => 'medio']);

    // À mão: (10×12 + 20×16) / 30 = 14,6667. As linhas são as mesmas duas, não quatro.
    expect(metodosDasVariacoes($this->biz->id, $this->variacoes))
        ->toBe([$this->variacoes[0] => 'medio', $this->variacoes[1] => 'medio'])
        ->and($servico->naData($this->biz->id, $this->variacoes[0], '2026-03-31', 'por_insumo')['custo'])->toBe('14.6667');
});

it('UC-INSMET-11 · UC-FLX-38 · envio sem o campo (tela que não gerencia o método) não muda o que está gravado', function () {
    DB::table('compras_insumo_metodo_custo')->insert([
        'business_id' => $this->biz->id, 'variation_id' => $this->variacoes[0], 'metodo' => 'ultimo',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    salvarCadastroDoProduto($this, []);
    salvarCadastroDoProduto($this, ['has_module_data' => '1']);

    expect(metodosDasVariacoes($this->biz->id, $this->variacoes))->toBe([$this->variacoes[0] => 'ultimo']);
});

it('UC-INSMET-12 · UC-FLX-38 · escolher "Não definido" apaga o método das variações do produto', function () {
    salvarCadastroDoProduto($this, ['has_module_data' => '1', 'compras_metodo_custo' => 'ultimo']);
    expect(metodosDasVariacoes($this->biz->id, $this->variacoes))->toHaveCount(2);

    salvarCadastroDoProduto($this, ['has_module_data' => '1', 'compras_metodo_custo' => '']);

    expect(metodosDasVariacoes($this->biz->id, $this->variacoes))->toBe([]);
});

it('UC-INSMET-13 · UC-FLX-38 · variações com métodos diferentes: o formulário mostra "misto" e, mantido, nada muda', function () {
    foreach ([[$this->variacoes[0], 'medio'], [$this->variacoes[1], 'ultimo']] as [$v, $m]) {
        DB::table('compras_insumo_metodo_custo')->insert([
            'business_id' => $this->biz->id, 'variation_id' => $v, 'metodo' => $m, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
    expect(app(MetodoCustoDoProduto::class)->atual($this->biz->id, $this->produto->productId))->toBe('misto');

    salvarCadastroDoProduto($this, ['has_module_data' => '1', 'compras_metodo_custo' => 'misto']);

    expect(metodosDasVariacoes($this->biz->id, $this->variacoes))
        ->toBe([$this->variacoes[0] => 'medio', $this->variacoes[1] => 'ultimo']);
});

it('UC-INSMET-14 · UC-FLX-38 · sem purchase.update o produto salva e o método não é gravado', function () {
    $this->user->revokePermissionTo('purchase.update');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->actingAs($this->user->fresh());

    salvarCadastroDoProduto($this, ['has_module_data' => '1', 'compras_metodo_custo' => 'ultimo']);

    expect(metodosDasVariacoes($this->biz->id, $this->variacoes))->toBe([]);
});

it('UC-INSMET-15 · UC-FLX-38 · o método mostrado é o das variações: vazio, um só, ou misto quando só parte tem', function () {
    $metodo = app(MetodoCustoDoProduto::class);
    expect($metodo->atual($this->biz->id, $this->produto->productId))->toBe('');

    DB::table('compras_insumo_metodo_custo')->insert([
        'business_id' => $this->biz->id, 'variation_id' => $this->variacoes[0], 'metodo' => 'ultimo',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    expect($metodo->atual($this->biz->id, $this->produto->productId))->toBe('misto');

    DB::table('compras_insumo_metodo_custo')->insert([
        'business_id' => $this->biz->id, 'variation_id' => $this->variacoes[1], 'metodo' => 'ultimo',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    expect($metodo->atual($this->biz->id, $this->produto->productId))->toBe('ultimo');

    // Tier 0: a mesma consulta pela outra empresa não enxerga as variações deste produto.
    $outra = $this->seededSupportClientTenant();
    expect($metodo->atual($outra->id, $this->produto->productId))->toBe('');
});

/** A parte do formulário que o core pede ao módulo, como no GET /products/{id}/edit. */
function partesDoFormularioDeEditar(int $productId): array
{
    $req = Illuminate\Http\Request::create("/products/{$productId}/edit");
    $req->setLaravelSession(app('session.store'));
    $rota = app('router')->getRoutes()->match($req);
    $req->setRouteResolver(fn () => $rota);
    app()->instance('request', $req);

    return (new Modules\Compras\Http\Controllers\DataController)->product_form_part();
}

it('UC-INSMET-16 · UC-FLX-38 · o campo só vai ao formulário de quem tem o módulo Compras no pacote', function () {
    DB::table('compras_insumo_metodo_custo')->insert([
        'business_id' => $this->biz->id, 'variation_id' => $this->variacoes[0], 'metodo' => 'ultimo',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('compras_insumo_metodo_custo')->insert([
        'business_id' => $this->biz->id, 'variation_id' => $this->variacoes[1], 'metodo' => 'ultimo',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    // O gate é o pacote da empresa (ModuleUtil::hasThePermissionInSubscription). O teste fixa a
    // resposta nos dois sentidos, em vez de depender da assinatura que o banco tiver.
    $pacote = fn (bool $tem) => $this->mock(ModuleUtil::class)
        ->shouldReceive('hasThePermissionInSubscription')
        ->with($this->biz->id, 'compras_module', 'superadmin_package')
        ->andReturn($tem);

    $pacote(false);
    expect(partesDoFormularioDeEditar($this->produto->productId))->toBe([]);

    $pacote(true);
    $parte = partesDoFormularioDeEditar($this->produto->productId);
    expect($parte['template_path'] ?? null)->toBe('compras::partials.metodo_custo_produto')
        ->and($parte['template_data']['compras_metodo_custo_atual'])->toBe('ultimo')
        ->and($parte['template_data']['compras_metodo_custo_editavel'])->toBeTrue();
});
