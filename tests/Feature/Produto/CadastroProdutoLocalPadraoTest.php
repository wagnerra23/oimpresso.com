<?php

declare(strict_types=1);

// Cobre UC-PCAD-22 (resources/js/Pages/Produto/Create.casos.md) e UC-PEDIT-19
// (resources/js/Pages/Produto/Edit.casos.md) — G-2 rastreabilidade caso↔teste.

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\Support\EstoqueFixture;

/**
 * Local padrão de armazenagem e endereço no estoque, no cadastro de produto (servidor).
 *
 * ÂNCORA (contrato, não implementação): CU-PROD-20 do SDD do cadastro de produto, com as decisões da
 * [M] (2026-10-22) e a proposta mergeada pelo [W] (#10544, 2026-11-06):
 *   · o local padrão é um dos locais de armazenagem do produto, do mesmo negócio;
 *   · o endereço (prateleira, fileira, posição) usa o `product_racks` que já existe;
 *   · o cadastro não grava quantidade: estoque não muda aqui (REGRA MESTRE de estoque);
 *   · o Blade, que não manda `local_padrao_id`, não muda nada.
 * E o Tier 0 (ADR 0093): local de outra empresa é recusado, no padrão e no endereço.
 *
 * ⛔ Tenant canônico de teste = 98 (ADR 0358). NUNCA biz=4.
 */
uses(DatabaseTransactions::class);

function clpCriar(object $test, array $campos): ?object
{
    $bizId = (int) $test->business->id;
    $nome = 'Produto UC-PCAD-22 ' . uniqid();
    $test->post('/products', array_merge([
        'name' => $nome, 'sku' => '', 'type' => 'single',
        'unit_id' => EstoqueFixture::unitId($bizId), 'tax_type' => 'exclusive', 'enable_stock' => 1,
    ], $campos));

    return DB::table('products')->where('business_id', $bizId)->where('name', $nome)->first();
}

function clpEditar(object $test, object $produto, array $campos)
{
    // As chaves que a tela React manda: o update() lê brand_id, category_id, tax, alert_quantity,
    // weight e product_description direto do envio (mesmo payload do CadastroProdutoPesoDimensoesTest).
    return $test->put("/products/{$produto->id}", array_merge([
        'name' => $produto->name, 'sku' => $produto->sku, 'brand_id' => $produto->brand_id ?? '',
        'unit_id' => $produto->unit_id, 'category_id' => $produto->category_id ?? '', 'tax' => $produto->tax ?? '',
        'tax_type' => $produto->tax_type, 'barcode_type' => $produto->barcode_type,
        'alert_quantity' => $produto->alert_quantity ?? '', 'weight' => $produto->weight ?? '',
        'product_description' => $produto->product_description ?? '',
        'product_locations' => [], 'preparation_time_in_minutes' => '',
    ], $campos));
}

/** location_id => [rack, row, position] gravados para o produto. */
function clpEnderecos(int $productId): array
{
    return DB::table('product_racks')->where('product_id', $productId)->orderBy('location_id')->get()
        ->mapWithKeys(fn ($r) => [(int) $r->location_id => [$r->rack, $r->row, $r->position]])->all();
}

function clpSaldoTotal(int $productId): float
{
    return (float) DB::table('variation_location_details')->where('product_id', $productId)->sum('qty_available');
}

beforeEach(function () {
    if (! EstoqueFixture::schemaReady() || ! Schema::hasColumn('products', 'local_padrao_id')) {
        $this->markTestSkipped('Schema UltimatePOS ou a coluna local_padrao_id ausente — roda na lane MySQL / CT 100.');
    }
    $this->business = $this->seededTenant();
    $this->user = User::where('business_id', $this->business->id)->first();
    if (! $this->user) {
        $this->markTestSkipped('Sem user no business seeded.');
    }
    $this->actingAs($this->user);
    session([
        'user.business_id' => $this->business->id, 'user.id' => $this->user->id, 'business.id' => $this->business->id,
        // O update() e o edit() formatam número com a moeda da sessão.
        'currency' => ['id' => 1, 'code' => 'BRL', 'symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ','],
    ]);
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::findOrCreate('product.create', 'web');
    Permission::findOrCreate('product.update', 'web');
    $this->user->givePermissionTo(['product.create', 'product.update']);

    $biz = (int) $this->business->id;
    $this->deposito = EstoqueFixture::locationId($biz, 'CLP-DEP');
    $this->loja = EstoqueFixture::locationId($biz, 'CLP-LOJA');
});

// ─── UC-PCAD-22 · Novo ──────────────────────────────────────────────────────

it('UC-PCAD-22 · produto novo grava o local padrão e o endereço de cada local, sem estoque', function () {
    $produto = clpCriar($this, [
        'product_racks' => [
            $this->deposito => ['rack' => 'A', 'row' => '2', 'position' => '05'],
            $this->loja => ['rack' => '', 'row' => '', 'position' => ''],
        ],
        'local_padrao_id' => $this->deposito,
    ]);

    expect($produto)->not->toBeNull();
    expect((int) $produto->local_padrao_id)->toBe($this->deposito);
    $enderecos = clpEnderecos((int) $produto->id);
    expect($enderecos)->toHaveCount(2);
    expect($enderecos[$this->deposito])->toBe(['A', '2', '05']);
    expect($enderecos[$this->loja])->toBe([null, null, null]);
    // REGRA MESTRE de estoque: o cadastro não cria saldo.
    expect(clpSaldoTotal((int) $produto->id))->toBe(0.0);
});

it('UC-PCAD-22 · sem local padrão (e pelo Blade, que não o manda) o produto nasce com o padrão nulo', function () {
    $produto = clpCriar($this, ['product_racks' => [$this->deposito => ['rack' => 'B', 'row' => '', 'position' => '']]]);

    expect($produto)->not->toBeNull();
    expect($produto->local_padrao_id)->toBeNull();
    expect(clpEnderecos((int) $produto->id))->toEqual([$this->deposito => ['B', null, null]]);
});

it('UC-PCAD-22 · local padrão fora dos locais de armazenagem é recusado e nenhum produto nasce', function () {
    $antes = DB::table('products')->where('business_id', $this->business->id)->count();
    $resp = $this->post('/products', [
        'name' => 'Produto UC-PCAD-22 fora ' . uniqid(), 'sku' => '', 'type' => 'single',
        'unit_id' => EstoqueFixture::unitId((int) $this->business->id), 'tax_type' => 'exclusive', 'enable_stock' => 1,
        'product_racks' => [$this->deposito => ['rack' => '', 'row' => '', 'position' => '']],
        'local_padrao_id' => $this->loja,
    ]);

    $resp->assertSessionHasErrors(['local_padrao_id']);
    expect(DB::table('products')->where('business_id', $this->business->id)->count())->toBe($antes);
});

it('UC-PCAD-22 · local de outra empresa é recusado, no padrão e no endereço [T0]', function () {
    $outra = EstoqueFixture::secondBusinessId();
    if ($outra === null || $outra === (int) $this->business->id) {
        $this->markTestSkipped('Segundo tenant não semeado.');
    }
    $alheio = EstoqueFixture::locationId($outra, 'CLP-ALHEIO');
    $antes = DB::table('products')->where('business_id', $this->business->id)->count();

    $this->post('/products', [
        'name' => 'Produto UC-PCAD-22 alheio ' . uniqid(), 'sku' => '', 'type' => 'single',
        'unit_id' => EstoqueFixture::unitId((int) $this->business->id), 'tax_type' => 'exclusive', 'enable_stock' => 1,
        'product_racks' => [$alheio => ['rack' => 'X', 'row' => '', 'position' => '']],
        'local_padrao_id' => $alheio,
    ])->assertSessionHasErrors(['product_racks', 'local_padrao_id']);

    expect(DB::table('products')->where('business_id', $this->business->id)->count())->toBe($antes);
    expect(DB::table('product_racks')->where('location_id', $alheio)->where('business_id', $this->business->id)->exists())->toBeFalse();
});

// ─── UC-PEDIT-19 · Editar ───────────────────────────────────────────────────

it('UC-PEDIT-19 · editar troca o local padrão para outro local de armazenagem e muda o endereço', function () {
    $produto = clpCriar($this, [
        'product_racks' => [$this->deposito => ['rack' => 'A', 'row' => '', 'position' => '']],
        'local_padrao_id' => $this->deposito,
    ]);

    clpEditar($this, $produto, [
        'product_racks_update' => [$this->deposito => ['rack' => 'C', 'row' => '1', 'position' => '']],
        'product_racks' => [$this->loja => ['rack' => '', 'row' => '', 'position' => '9']],
        'local_padrao_id' => $this->loja,
    ])->assertSessionHasNoErrors();

    $depois = DB::table('products')->where('id', $produto->id)->first();
    expect((int) $depois->local_padrao_id)->toBe($this->loja);
    expect(clpEnderecos((int) $produto->id)[$this->deposito])->toBe(['C', '1', null]);
    expect(clpEnderecos((int) $produto->id)[$this->loja])->toBe([null, null, '9']);
});

it('UC-PEDIT-19 · vazio volta o padrão a nulo; sem a chave (Blade) o padrão fica como está', function () {
    $produto = clpCriar($this, [
        'product_racks' => [$this->deposito => ['rack' => '', 'row' => '', 'position' => '']],
        'local_padrao_id' => $this->deposito,
    ]);

    clpEditar($this, $produto, [])->assertSessionHasNoErrors();
    expect((int) DB::table('products')->where('id', $produto->id)->value('local_padrao_id'))->toBe($this->deposito);

    clpEditar($this, $produto, ['local_padrao_id' => ''])->assertSessionHasNoErrors();
    expect(DB::table('products')->where('id', $produto->id)->value('local_padrao_id'))->toBeNull();
});

it('UC-PEDIT-19 · padrão que não é local de armazenagem do produto é recusado sem gravar', function () {
    $produto = clpCriar($this, [
        'product_racks' => [$this->deposito => ['rack' => '', 'row' => '', 'position' => '']],
        'local_padrao_id' => $this->deposito,
    ]);

    clpEditar($this, $produto, ['local_padrao_id' => $this->loja])->assertSessionHasErrors(['local_padrao_id']);
    expect((int) DB::table('products')->where('id', $produto->id)->value('local_padrao_id'))->toBe($this->deposito);
});

it('UC-PEDIT-19 · a tela Editar recebe o local padrão e as partes do endereço que a empresa usa', function () {
    $produto = clpCriar($this, [
        'product_racks' => [$this->deposito => ['rack' => '', 'row' => '', 'position' => '']],
        'local_padrao_id' => $this->deposito,
    ]);
    session(['business.enable_racks' => 1, 'business.enable_row' => 0, 'business.enable_position' => 1,
        'currency' => ['id' => 1, 'code' => 'BRL', 'symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ',']]);

    $versao = (string) app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
    $r = $this->get("/products/{$produto->id}/edit", ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'X-Inertia-Version' => $versao]);
    $r->assertOk();
    expect($r->json('props.product.localPadraoId'))->toBe($this->deposito);
    expect($r->json('props.enderecoNoEstoque'))->toBe(['prateleira' => true, 'fileira' => false, 'posicao' => true]);
});
