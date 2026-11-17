<?php

declare(strict_types=1);
// Cobre UC-PEDIT-18 (resources/js/Pages/Produto/Edit.casos.md) — G-2 rastreabilidade caso↔teste.

use App\Utils\ModuleUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\Support\EstoqueFixture;

/**
 * Card "Produção" do Editar produto (`GET /products/{id}/edit`, prop deferida `producao`).
 *
 * ÂNCORA (contrato, não implementação)
 * ─────────────────────────────────────────────────────────────────────────────
 * - SDD-tela-cadastro-produto CU-PROD-22, decisão [W] opção A (#10545): o card entra só leitura,
 *   só no Editar, só para empresa com o módulo Produção no pacote, nunca para composição nem
 *   serviço. Sem roteiro, a tela diz que o produto não vira OS.
 * - Producao/Roteiros UC-PCROT-02 (produto sem roteiro não vira OS) e UC-PCROT-04 (vínculo com
 *   roteiro de outra empresa conta como sem roteiro) — o mesmo vínculo que a OS lê.
 * - ADR 0093 (Tier 0): o roteiro é lido só da empresa da sessão.
 *
 * Tenant semeado do CI (ADR 0358). Nunca biz=4. Pest só no CI / CT 100.
 */
uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! EstoqueFixture::schemaReady() || ! Schema::hasTable('pcp_produto_roteiro')) {
        $this->markTestSkipped('Schema UltimatePOS ou do módulo Produção ausente — roda na lane MySQL / CT 100.');
    }
    $this->business = $this->seededTenant();
    // Usuário só com o que a tela pede: o admin semeado teria `producao.access` por papel.
    $this->user = $this->usuarioComPermissoes(['product.update', 'access_all_locations'], $this->business);
    $this->actingAs($this->user);
    session(['user.business_id' => $this->business->id, 'user.id' => $this->user->id, 'business.id' => $this->business->id]);
    // Objeto, não variável: o `fn` do mock lê o valor na hora da chamada.
    $this->pacote = (object) ['producao' => true];
    $pacote = $this->pacote;
    $this->partialMock(ModuleUtil::class, fn ($m) => $m->shouldReceive('hasThePermissionInSubscription')
        ->andReturnUsing(fn ($b, $perm) => $perm === 'producao_module' && $pacote->producao));
});

/** A prop deferida `producao`, pedida como o Inertia pede depois de montar a página. */
function pcardProducao($teste, int $productId): mixed
{
    $versao = (string) app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
    $r = $teste->get("/products/{$productId}/edit", [
        'X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'X-Inertia-Version' => $versao,
        'X-Inertia-Partial-Component' => 'Produto/Edit', 'X-Inertia-Partial-Data' => 'producao',
    ]);
    $r->assertOk();
    // Anti-vácuo: a resposta é da tela React e trouxe a chave; a ausência, quando houver, é do card.
    expect($r->json('component'))->toBe('Produto/Edit');
    expect($r->json('props'))->toHaveKey('producao');

    return $r->json('props.producao');
}

/** Roteiro com `$etapas` etapas ligado ao produto, como a tela de Roteiros grava. */
function pcardRoteiro(int $businessId, int $productId, int $etapas, string $nome, ?int $roteiroBusinessId = null): void
{
    $dono = $roteiroBusinessId ?? $businessId;
    $roteiroId = DB::table('pcp_roteiros')->insertGetId([
        'business_id' => $dono, 'familia' => 'Banner', 'nome' => $nome, 'ativo' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    for ($i = 0; $i < $etapas; $i++) {
        DB::table('pcp_roteiro_etapas')->insert([
            'business_id' => $dono, 'roteiro_id' => $roteiroId, 'bloco' => $i + 1, 'trilha' => 0, 'ordem' => $i,
            'nome' => 'Etapa ' . ($i + 1), 'posto_id' => null, 'horas' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
    DB::table('pcp_produto_roteiro')->insert([
        'business_id' => $businessId, 'product_id' => $productId, 'roteiro_id' => $roteiroId, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

it('UC-PEDIT-18 · a prop vem deferida: a primeira visita não lê o roteiro', function () {
    $p = EstoqueFixture::singleProduct($this->business->id);
    $versao = (string) app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
    $r = $this->get("/products/{$p->productId}/edit", [
        'X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'X-Inertia-Version' => $versao,
    ]);
    $r->assertOk();
    expect($r->json('props'))->toHaveKey('product');
    expect($r->json('props'))->not->toHaveKey('producao');
    expect(collect($r->json('deferredProps'))->flatten()->all())->toContain('producao');
});

it('UC-PEDIT-18 · com roteiro: nome, número de etapas e se está ativo', function () {
    $p = EstoqueFixture::singleProduct($this->business->id);
    $nome = 'Impressão digital + acabamento ' . uniqid();
    pcardRoteiro($this->business->id, $p->productId, 4, $nome);

    $card = pcardProducao($this, $p->productId);
    expect($card['roteiro'])->toEqual(['nome' => $nome, 'etapas' => 4, 'ativo' => true]);
});

it('UC-PEDIT-18 · sem roteiro: o card vem, com o roteiro vazio (o produto não vira OS)', function () {
    $p = EstoqueFixture::singleProduct($this->business->id);

    $card = pcardProducao($this, $p->productId);
    expect($card)->not->toBeNull();
    expect($card['roteiro'])->toBeNull();
});

it('UC-PEDIT-18 · empresa sem o módulo Produção no pacote: sem card', function () {
    $p = EstoqueFixture::singleProduct($this->business->id);
    pcardRoteiro($this->business->id, $p->productId, 2, 'Corte ' . uniqid());
    $this->pacote->producao = false;

    expect(pcardProducao($this, $p->productId))->toBeNull();
});

it('UC-PEDIT-18 · composição: sem card, mesmo com roteiro ligado', function () {
    $p = EstoqueFixture::singleProduct($this->business->id);
    DB::table('products')->where('id', $p->productId)->update(['type' => 'combo']);
    pcardRoteiro($this->business->id, $p->productId, 2, 'Kit ' . uniqid());

    expect(pcardProducao($this, $p->productId))->toBeNull();
});

it('UC-PEDIT-18 · vínculo com roteiro de outra empresa conta como sem roteiro [T0]', function () {
    $outra = EstoqueFixture::secondBusinessId();
    if ($outra === null || $outra === $this->business->id) {
        $this->markTestSkipped('Segundo tenant não semeado.');
    }
    $p = EstoqueFixture::singleProduct($this->business->id);
    $nomeAlheio = 'Roteiro alheio ' . uniqid();
    pcardRoteiro($this->business->id, $p->productId, 3, $nomeAlheio, $outra);

    $card = pcardProducao($this, $p->productId);
    expect($card)->not->toBeNull();
    expect($card['roteiro'])->toBeNull();
    expect(json_encode($card))->not->toContain($nomeAlheio);
});

it('UC-PEDIT-18 · o link para Roteiros só vem com `producao.access`', function () {
    $p = EstoqueFixture::singleProduct($this->business->id);
    expect(pcardProducao($this, $p->productId)['linkRoteiros'])->toBeNull();

    Permission::findOrCreate('producao.access', 'web');
    $this->user->givePermissionTo(['producao.access']);
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    expect(pcardProducao($this, $p->productId)['linkRoteiros'])->toBe('/producao/roteiros');
});
