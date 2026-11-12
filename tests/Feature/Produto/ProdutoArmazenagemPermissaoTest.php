<?php

declare(strict_types=1);

// Cobre UC-PEDIT-22 (resources/js/Pages/Produto/Edit.casos.md) — G-2 rastreabilidade caso↔teste.

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\Support\EstoqueFixture;

/**
 * Quem edita os locais de armazenagem do produto (servidor).
 *
 * ÂNCORA (contrato, não implementação): CU-PROD-20 do SDD do cadastro de produto, proposta #10765
 * (autorizada pelo [F] em 2026-11-12):
 *   · permissão `product.armazenagem` só para os locais, sem acesso a preço e imposto;
 *   · `product.update` continua bastando;
 *   · a rota só de locais grava locais, endereço e local padrão e ignora qualquer outro campo;
 *   · valem as regras de UC-PEDIT-19/21 (local de outro negócio, padrão fora, local com saldo);
 *   · histórico de quem mudou, com o antes e o depois.
 *
 * ⛔ Tenant canônico de teste = 98 (ADR 0358). NUNCA biz=4.
 */
uses(DatabaseTransactions::class);

function paUsuario(int $bizId, array $permissoes): User
{
    $user = User::findOrFail(DB::table('users')->insertGetId([
        'first_name' => 'Estoquista', 'username' => 'parm_' . uniqid(), 'password' => bcrypt('ci'),
        'business_id' => $bizId, 'user_type' => 'user', 'allow_login' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]));
    foreach ($permissoes as $p) {
        $user->givePermissionTo(Permission::findOrCreate($p, 'web'));
    }
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    return $user;
}

function paEntrar(object $test, User $user): void
{
    $test->actingAs($user);
    // Sem `business` na sessão: o activityLog busca o negócio pelo id (o objeto da sessão real é um model).
    session(['user.business_id' => (int) $user->business_id, 'user.id' => $user->id]);
}

/** location_id => [rack, row, position]. */
function paEnderecos(int $productId): array
{
    return DB::table('product_racks')->where('product_id', $productId)->orderBy('location_id')->get()
        ->mapWithKeys(fn ($r) => [(int) $r->location_id => [$r->rack, $r->row, $r->position]])->all();
}

beforeEach(function () {
    if (! EstoqueFixture::schemaReady() || ! Schema::hasColumn('products', 'local_padrao_id')) {
        $this->markTestSkipped('Schema UltimatePOS ou a coluna local_padrao_id ausente — roda na lane MySQL / CT 100.');
    }
    $this->business = $this->seededTenant();
    $biz = (int) $this->business->id;
    $this->deposito = EstoqueFixture::locationId($biz, 'PA-DEP');
    $this->loja = EstoqueFixture::locationId($biz, 'PA-LOJA');
    $this->produto = EstoqueFixture::singleProduct($biz);
    DB::table('product_racks')->insert([
        'business_id' => $biz, 'location_id' => $this->deposito, 'product_id' => $this->produto->productId,
        'rack' => 'A', 'row' => null, 'position' => null, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('products')->where('id', $this->produto->productId)->update(['local_padrao_id' => $this->deposito]);
});

it('UC-PEDIT-22 · só com product.armazenagem grava os locais e ignora nome, imposto e qualquer outro campo', function () {
    $antes = DB::table('products')->where('id', $this->produto->productId)->first();
    paEntrar($this, paUsuario((int) $this->business->id, ['product.armazenagem']));

    $this->patch("/products/{$this->produto->productId}/armazenagem", [
        'product_racks_update' => [$this->deposito => ['rack' => 'C', 'row' => '2', 'position' => '']],
        'product_racks' => [$this->loja => ['rack' => 'B', 'row' => '', 'position' => '']],
        'local_padrao_id' => $this->loja,
        'name' => 'Nome trocado pela rota de locais', 'tax' => 999, 'alert_quantity' => 77,
    ])->assertSessionHasNoErrors()->assertRedirect();

    $depois = DB::table('products')->where('id', $this->produto->productId)->first();
    expect((int) $depois->local_padrao_id)->toBe($this->loja);
    expect(paEnderecos($this->produto->productId))->toBe([
        $this->deposito => ['C', '2', null],
        $this->loja => ['B', null, null],
    ]);
    // Nada além dos locais muda.
    expect($depois->name)->toBe($antes->name);
    expect($depois->tax)->toBe($antes->tax);
    expect($depois->alert_quantity)->toBe($antes->alert_quantity);
});

it('UC-PEDIT-22 · product.update também usa a rota de locais', function () {
    paEntrar($this, paUsuario((int) $this->business->id, ['product.update']));

    $this->patch("/products/{$this->produto->productId}/armazenagem", [
        'product_racks_update' => [$this->deposito => ['rack' => 'Z', 'row' => '', 'position' => '']],
        'local_padrao_id' => $this->deposito,
    ])->assertSessionHasNoErrors();

    expect(paEnderecos($this->produto->productId)[$this->deposito][0])->toBe('Z');
});

it('UC-PEDIT-22 · sem nenhuma das duas permissões é recusado e nada muda', function () {
    paEntrar($this, paUsuario((int) $this->business->id, ['product.view']));

    $this->patch("/products/{$this->produto->productId}/armazenagem", [
        'product_racks_update' => [$this->deposito => ['rack' => 'X', 'row' => '', 'position' => '']],
        'local_padrao_id' => '',
    ])->assertForbidden();

    expect(paEnderecos($this->produto->productId)[$this->deposito][0])->toBe('A');
    expect((int) DB::table('products')->where('id', $this->produto->productId)->value('local_padrao_id'))->toBe($this->deposito);
});

it('UC-PEDIT-22 · só com product.armazenagem a gravação do produto inteiro segue recusada', function () {
    paEntrar($this, paUsuario((int) $this->business->id, ['product.armazenagem']));

    $this->put("/products/{$this->produto->productId}", ['name' => 'Tentativa pelo update'])->assertForbidden();
    expect(DB::table('products')->where('id', $this->produto->productId)->value('name'))->not->toBe('Tentativa pelo update');
});

it('UC-PEDIT-22 · local com saldo não sai pela rota de locais', function () {
    EstoqueFixture::setStock($this->produto, 0, $this->deposito, 5);
    paEntrar($this, paUsuario((int) $this->business->id, ['product.armazenagem']));

    $this->patch("/products/{$this->produto->productId}/armazenagem", [
        'product_racks_remover' => [$this->deposito], 'local_padrao_id' => '',
    ])->assertSessionHasErrors(['product_racks_remover']);

    expect(array_keys(paEnderecos($this->produto->productId)))->toBe([$this->deposito]);
    expect(EstoqueFixture::totalStock($this->produto))->toBe(5.0);
});

it('UC-PEDIT-22 · produto de outro negócio dá 404 [T0]', function () {
    $outra = EstoqueFixture::secondBusinessId();
    if ($outra === null || $outra === (int) $this->business->id) {
        $this->markTestSkipped('Segundo tenant não semeado.');
    }
    $alheio = EstoqueFixture::singleProduct($outra);
    paEntrar($this, paUsuario((int) $this->business->id, ['product.armazenagem']));

    $this->patch("/products/{$alheio->productId}/armazenagem", ['local_padrao_id' => ''])->assertNotFound();
    expect(DB::table('product_racks')->where('product_id', $alheio->productId)->exists())->toBeFalse();
});

it('UC-PEDIT-22 · produto sem estoque controlado não tem locais de armazenagem', function () {
    DB::table('products')->where('id', $this->produto->productId)->update(['enable_stock' => 0]);
    paEntrar($this, paUsuario((int) $this->business->id, ['product.armazenagem']));

    $this->patch("/products/{$this->produto->productId}/armazenagem", [
        'product_racks_update' => [$this->deposito => ['rack' => 'Q', 'row' => '', 'position' => '']],
    ])->assertSessionHasErrors(['product_racks']);
    expect(paEnderecos($this->produto->productId)[$this->deposito][0])->toBe('A');
});

it('UC-PEDIT-22 · o histórico registra quem mudou, com o antes e o depois; salvar sem mudar não registra', function () {
    $user = paUsuario((int) $this->business->id, ['product.armazenagem']);
    paEntrar($this, $user);
    $doProduto = fn () => DB::table('activity_log')->where('subject_type', \App\Product::class)
        ->where('subject_id', $this->produto->productId)->where('description', 'armazenagem_alterada');

    $this->patch("/products/{$this->produto->productId}/armazenagem", [
        'product_racks_update' => [$this->deposito => ['rack' => 'A', 'row' => '', 'position' => '']],
        'local_padrao_id' => $this->deposito,
    ])->assertSessionHasNoErrors();
    expect($doProduto()->count())->toBe(0);

    $this->patch("/products/{$this->produto->productId}/armazenagem", [
        'product_racks_update' => [$this->deposito => ['rack' => 'D', 'row' => '', 'position' => '']],
        'local_padrao_id' => $this->deposito,
    ])->assertSessionHasNoErrors();

    $log = $doProduto()->latest('id')->first();
    expect($log)->not->toBeNull();
    expect((int) $log->causer_id)->toBe((int) $user->id);
    expect((int) $log->business_id)->toBe((int) $this->business->id);
    $props = json_decode((string) $log->properties, true);
    expect($props['old']['enderecos'][$this->deposito]['prateleira'])->toBe('A');
    expect($props['attributes']['enderecos'][$this->deposito]['prateleira'])->toBe('D');
});
