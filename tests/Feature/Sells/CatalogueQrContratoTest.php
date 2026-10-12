<?php

declare(strict_types=1);
// Cobre UC-CQR-01, UC-CQR-03, UC-CQR-06, UC-CQR-08, UC-CQR-10 (ProductCatalogue/CatalogueQr.casos.md).

use App\Utils\ModuleUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Thread modulos-faltantes/playbook/04 — /product-catalogue/catalogue-qr em Inertia.
 *
 * Mora em tests/Feature/Sells porque o catálogo é ghost do hub Vendas (ADR 0180) e porque os
 * testes de Modules/ProductCatalogue/Tests não rodam em lane nenhuma (test-lane-coverage,
 * 2026-10-09: 7 de 7 órfãos). Lane: sells-pest.yml (MySQL).
 *
 * A assinatura do módulo vem do ModuleUtil parcial: o pacote semeado na lane não é o que
 * o teste exercita. A permissão do papel é a real (usuarioComPermissoes), porque é ela que a
 * decisão PERM-CQR acrescentou. Tenant de teste 98 x cliente fictício 99 (ADR 0358).
 */

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasTable('business_locations')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }

    $this->business = $this->seededTenant();
    $this->versaoInertia = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
    cqrAssinatura(true);
});

function cqrAssinatura(bool $temModulo): void
{
    // Flag mutável, e não um mock novo por chamada: o Laravel guarda a instância do controller
    // na rota entre requisições do mesmo teste, então o serviço injetado na 1ª requisição é o
    // que atende as seguintes. Trocar o binding no meio do teste não chegaria lá.
    $GLOBALS['cqr_tem_modulo'] = $temModulo;
    if (! (app()->bound(ModuleUtil::class) && app(ModuleUtil::class) instanceof \Mockery\MockInterface)) {
        $mu = Mockery::mock(ModuleUtil::class)->makePartial();
        $mu->shouldReceive('hasThePermissionInSubscription')
            ->andReturnUsing(fn ($biz, $perm) => $perm === 'productcatalogue_module' ? $GLOBALS['cqr_tem_modulo'] : true);
        app()->instance(ModuleUtil::class, $mu);
    }
}

function cqrEntrar($teste, array $permissoes): \App\User
{
    $user = $teste->usuarioComPermissoes($permissoes, $teste->business);
    $teste->actingAs($user);
    session(['user.business_id' => $teste->business->id, 'user.id' => $user->id, 'business.id' => $teste->business->id]);

    return $user;
}

function cqrAbrir($teste)
{
    // X-Requested-With junto do X-Inertia, como o browser manda.
    return $teste->withHeaders([
        'X-Inertia' => 'true', 'X-Inertia-Version' => $teste->versaoInertia, 'X-Requested-With' => 'XMLHttpRequest',
    ])->get('/product-catalogue/catalogue-qr');
}

test('UC-CQR-01 GET /product-catalogue/catalogue-qr renderiza Inertia ProductCatalogue/CatalogueQr', function () {
    cqrEntrar($this, ['product.view', 'access_all_locations']);

    $r = cqrAbrir($this);

    $r->assertOk();
    expect($r->json('component'))->toBe('ProductCatalogue/CatalogueQr');
    expect($r->json('props.negocio.nome'))->toBe((string) $this->business->name);
    expect((string) $r->json('props.qr_script'))->toEndWith('modules/productcatalogue/plugins/easy.qrcode.min.js');
});

test('UC-CQR-03 Tier 0 — a base do link é o negócio da sessão', function () {
    cqrEntrar($this, ['product.view', 'access_all_locations']);

    $base = (string) cqrAbrir($this)->json('props.link_base');

    expect($base)->toEndWith('/catalogue/' . $this->business->id);
});

test('UC-CQR-06 sem logo cadastrado, logo_url é null; com logo, aponta para uploads/business_logos', function () {
    cqrEntrar($this, ['product.view', 'access_all_locations']);

    DB::table('business')->where('id', $this->business->id)->update(['logo' => null]);
    expect(cqrAbrir($this)->json('props.negocio.logo_url'))->toBeNull();

    DB::table('business')->where('id', $this->business->id)->update(['logo' => 'logo-cqr.png']);
    expect((string) cqrAbrir($this)->json('props.negocio.logo_url'))->toEndWith('uploads/business_logos/logo-cqr.png');
});

test('UC-CQR-08 Tier 0 — os locais são só os do negócio da sessão', function () {
    cqrEntrar($this, ['product.view', 'access_all_locations']);
    $outro = $this->seededSupportClientTenant();

    $meu = DB::table('business_locations')->where('business_id', $this->business->id)->value('id');
    expect($meu)->not->toBeNull();

    $scheme = DB::table('invoice_schemes')->where('business_id', $outro->id)->value('id') ?? DB::table('invoice_schemes')->value('id');
    $layout = DB::table('invoice_layouts')->where('business_id', $outro->id)->value('id') ?? DB::table('invoice_layouts')->value('id');
    if (! $scheme || ! $layout) {
        $this->markTestSkipped('Sem invoice_scheme/invoice_layout no banco — as FKs de business_locations não teriam alvo.');
    }
    $alheio = DB::table('business_locations')->insertGetId([
        'business_id' => $outro->id, 'invoice_scheme_id' => $scheme, 'invoice_layout_id' => $layout,
        'name' => '__CQR_LOCAL_ALHEIO__', 'country' => 'BR', 'state' => 'XX', 'city' => 'Teste', 'zip_code' => '00000',
        'is_active' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $ids = collect(cqrAbrir($this)->json('props.locais'))->pluck('id')->all();

    expect($ids)->toContain((int) $meu);
    expect($ids)->not->toContain((int) $alheio);
});

test('UC-CQR-10 sem product.view é 403; sem o módulo na assinatura também', function () {
    cqrEntrar($this, ['access_all_locations']);
    cqrAbrir($this)->assertForbidden();

    cqrAssinatura(false);
    cqrEntrar($this, ['product.view', 'access_all_locations']);
    cqrAbrir($this)->assertForbidden();

    // Controle positivo: com as duas, abre.
    cqrAssinatura(true);
    cqrAbrir($this)->assertOk();
});
