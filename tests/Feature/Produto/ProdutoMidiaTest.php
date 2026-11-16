<?php

declare(strict_types=1);

// Cobre UC-PCAD-29 (resources/js/Pages/Produto/Create.casos.md) e UC-PEDIT-29
// (resources/js/Pages/Produto/Edit.casos.md) — G-2 rastreabilidade caso↔teste.

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\Support\EstoqueFixture;

/**
 * Imagem e folheto do produto pelo Editar React (`POST /products/{id}/midia`).
 *
 * ÂNCORA (contrato, não implementação): protótipo do [W] (`produto-blade-forms.jsx`: "Imagem e
 * folheto", imagem até 5 MB e folheto) e paridade com o `update()` do Blade, que grava a imagem por
 * `uploadFile` (só imagem, até 5 MB) e o folheto por `Media::uploadMedia`, substituindo o anterior.
 * Pedido do [F] em 2026-11-16.
 *
 * O que cada caso trava:
 *   · a imagem e o folheto escolhidos ficam gravados no produto, e a resposta traz o que a tela mostra;
 *   · arquivo que não é imagem no campo da imagem é recusado e o produto não muda;
 *   · pedido sem arquivo é recusado;
 *   · sem `product.update` é 403; produto de outra empresa é 404 e não muda [T0].
 *
 * ⛔ Tenant canônico de teste = 98 (ADR 0358). NUNCA biz=4.
 */
uses(DatabaseTransactions::class);

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
    Storage::fake(config('filesystems.default'));
    $this->actingAs($this->user);
    session(['user.business_id' => (int) $this->business->id, 'user.id' => $this->user->id, 'business.id' => (int) $this->business->id]);
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::findOrCreate('product.update', 'web');
    Permission::findOrCreate('product.create', 'web');
    $this->user->givePermissionTo(['product.update', 'product.create']);
    $this->produto = EstoqueFixture::singleProduct((int) $this->business->id);
    $this->json = ['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'];
});

it('UC-PEDIT-29 · grava a imagem e o folheto e devolve o que a tela mostra', function () {
    $r = $this->withHeaders($this->json)->post("/products/{$this->produto->productId}/midia", [
        'image' => UploadedFile::fake()->image('banner.png', 400, 400),
        'product_brochure' => UploadedFile::fake()->create('folheto.pdf', 120, 'application/pdf'),
    ]);

    $r->assertOk()->assertJsonPath('success', true);
    $imagem = (string) DB::table('products')->where('id', $this->produto->productId)->value('image');
    expect($imagem)->toEndWith('banner.png');
    expect($r->json('imagem'))->toContain(rawurlencode($imagem));
    expect($r->json('folheto.nome'))->toBe('folheto.pdf');
    expect(DB::table('media')->where('model_type', \App\Product::class)->where('model_id', $this->produto->productId)->count())->toBe(1);
});

it('UC-PEDIT-29 · arquivo que não é imagem no campo da imagem é recusado e o produto não muda', function () {
    $antes = DB::table('products')->where('id', $this->produto->productId)->value('image');

    $this->withHeaders($this->json)->post("/products/{$this->produto->productId}/midia", [
        'image' => UploadedFile::fake()->create('planilha.pdf', 10, 'application/pdf'),
    ])->assertStatus(422);

    expect(DB::table('products')->where('id', $this->produto->productId)->value('image'))->toBe($antes);
});

it('UC-PEDIT-29 · sem arquivo nenhum é recusado', function () {
    $this->withHeaders($this->json)->post("/products/{$this->produto->productId}/midia", [])
        ->assertStatus(422)->assertJsonPath('success', false);
});

it('UC-PEDIT-29 · sem product.update é 403', function () {
    $this->user->revokePermissionTo('product.update');
    $this->user->syncRoles([]);
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    $this->withHeaders($this->json)->post("/products/{$this->produto->productId}/midia", [
        'image' => UploadedFile::fake()->image('banner.png'),
    ])->assertForbidden();
});

it('UC-PEDIT-29 · produto de outra empresa é 404 e não muda [T0]', function () {
    $outra = EstoqueFixture::secondBusinessId();
    if ($outra === null || $outra === (int) $this->business->id) {
        $this->markTestSkipped('Segundo tenant não semeado.');
    }
    $alheio = EstoqueFixture::singleProduct($outra);
    $antes = DB::table('products')->where('id', $alheio->productId)->value('image');

    $this->withHeaders($this->json)->post("/products/{$alheio->productId}/midia", [
        'image' => UploadedFile::fake()->image('banner.png'),
    ])->assertNotFound();

    expect(DB::table('products')->where('id', $alheio->productId)->value('image'))->toBe($antes);
});

it('UC-PCAD-29 · o Novo com imagem e folheto (envio multipart) cria o produto com os dois', function () {
    $biz = (int) $this->business->id;
    $nome = 'Produto UC-PCAD-29 ' . uniqid();

    // Como o Novo React manda quando há arquivo: tudo como texto num multipart.
    $this->post('/products', [
        'name' => $nome, 'sku' => '', 'type' => 'single', 'unit_id' => (string) EstoqueFixture::unitId($biz),
        'tax_type' => 'exclusive', 'enable_stock' => '1', 'sub_category_id' => '',
        'image' => UploadedFile::fake()->image('capa.png', 300, 300),
        'product_brochure' => UploadedFile::fake()->create('ficha.pdf', 50, 'application/pdf'),
    ]);

    $p = DB::table('products')->where('business_id', $biz)->where('name', $nome)->first();
    expect($p)->not->toBeNull();
    expect((string) $p->image)->toEndWith('capa.png');
    expect(DB::table('media')->where('model_type', \App\Product::class)->where('model_id', $p->id)->count())->toBe(1);
});
