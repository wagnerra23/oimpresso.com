<?php

declare(strict_types=1);

// Cobre UC-PEDIT-14 (resources/js/Pages/Produto/Edit.casos.md) — G-2 rastreabilidade caso↔teste.

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\Support\EstoqueFixture;

/**
 * Natureza fiscal do produto na tela Editar (UC-PEDIT-14).
 *
 * ÂNCORA (contrato, não implementação): CU-PROD-19 do SDD do cadastro de produto + D-NATUREZA
 * ([W] 2026-10-06: "o contador, por produto, com fonte registrada"). Quem decide se o produto é
 * mercadoria ou serviço é o contador, na Tributação (`nfe_naturezas_produto`); o cadastro só
 * mostra. O núcleo pede o dado ao módulo fiscal pelo gancho `getModuleData`, sem importá-lo.
 *
 * O que cada caso trava:
 *   · produto decidido como serviço chega à tela com a natureza e o item da LC 116;
 *   · produto decidido como mercadoria chega com a natureza;
 *   · produto sem decisão registrada chega sem natureza (null);
 *   · decisão gravada com o business_id de OUTRA empresa não aparece (Tier 0, ADR 0093).
 *
 * ⛔ Tenant canônico de teste = 98 (ADR 0358). NUNCA biz=4.
 */
uses(DatabaseTransactions::class);

function pnfVisitaInertia(): array
{
    return [
        'X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest',
        'X-Inertia-Version' => (string) app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request()),
    ];
}

function pnfDecidir(int $businessId, int $productId, string $natureza, ?string $codigo = null): void
{
    DB::table('nfe_naturezas_produto')->insert([
        'business_id' => $businessId, 'product_id' => $productId, 'natureza' => $natureza,
        'codigo_servico' => $codigo, 'fonte' => 'Teste UC-PEDIT-14',
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

/** A natureza que a tela Editar recebe do servidor. */
function pnfNaturezaNaTela(object $test, int $productId): mixed
{
    $resposta = $test->withHeaders(pnfVisitaInertia())
        ->get('/products/' . $productId . '/edit')
        ->assertOk()
        ->assertJsonPath('component', 'Produto/Edit')
        ->assertJsonPath('props.product.id', $productId);

    // Pré-condição anti-vácuo: a chave existe no payload (null também é resposta).
    expect(array_key_exists('naturezaFiscal', (array) $resposta->json('props.product')))->toBeTrue();

    return $resposta->json('props.product.naturezaFiscal');
}

beforeEach(function () {
    if (! EstoqueFixture::schemaReady()) {
        $this->markTestSkipped('Schema UltimatePOS/seed ausente — roda na lane MySQL / CT 100.');
    }
    if (! Schema::hasTable('nfe_naturezas_produto') || ! Schema::hasTable('system')) {
        $this->markTestSkipped('Tabela nfe_naturezas_produto ou system ausente — migrations do módulo fiscal não rodaram.');
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

    // O gancho getModuleData só pergunta a módulo instalado (system.<modulo>_version).
    if (! DB::table('system')->where('key', 'nfebrasil_version')->exists()) {
        DB::table('system')->insert(['key' => 'nfebrasil_version', 'value' => '1.0']);
    }

    $this->actingAs($this->user);
    session([
        'user.business_id' => $this->business->id,
        'user.id' => $this->user->id,
        'business.id' => $this->business->id,
        // O edit() formata número com a moeda da sessão (mesmo setup do ProdutoEditPayloadContratoTest).
        'currency' => ['id' => 1, 'code' => 'BRL', 'symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ','],
    ]);

    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::findOrCreate('product.update', 'web');
    $this->user->givePermissionTo('product.update');
});

it('UC-PEDIT-14 · produto de serviço chega ao Editar com a natureza e o item da LC 116', function () {
    $bizId = (int) $this->business->id;
    $p = EstoqueFixture::singleProduct($bizId);
    pnfDecidir($bizId, $p->productId, 'servico', '24.01');

    $natureza = pnfNaturezaNaTela($this, $p->productId);

    expect($natureza['natureza'])->toBe('servico');
    expect($natureza['codigoServico'])->toBe('24.01');
});

it('UC-PEDIT-14 · produto de mercadoria chega ao Editar com a natureza', function () {
    $bizId = (int) $this->business->id;
    $p = EstoqueFixture::singleProduct($bizId);
    pnfDecidir($bizId, $p->productId, 'mercadoria');

    expect(pnfNaturezaNaTela($this, $p->productId)['natureza'])->toBe('mercadoria');
});

it('UC-PEDIT-14 · produto sem decisão do contador chega sem natureza', function () {
    $p = EstoqueFixture::singleProduct((int) $this->business->id);

    expect(pnfNaturezaNaTela($this, $p->productId))->toBeNull();
});

it('UC-PEDIT-14 · decisão gravada com o business_id de outra empresa não aparece', function () {
    $outroBizId = EstoqueFixture::secondBusinessId();
    if ($outroBizId === null) {
        $this->markTestSkipped('DB só tem 1 business — sem par cross-tenant pra provar isolamento.');
    }

    $p = EstoqueFixture::singleProduct((int) $this->business->id);
    // Linha inválida de propósito: produto desta empresa, business_id da outra.
    pnfDecidir($outroBizId, $p->productId, 'servico', '24.01');

    expect(pnfNaturezaNaTela($this, $p->productId))->toBeNull();
});
