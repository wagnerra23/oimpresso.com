<?php

declare(strict_types=1);
// Cobre UC-ETQ-01, UC-ETQ-02, UC-ETQ-03 (Configuracoes/CodigoBarras/Index.casos.md).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Thread sistema/playbook/04, F3 — /barcodes em Inertia atrás da flag useV2ConfiguracoesCodigoBarras.
 *
 * A flag é ligada pelo override de ambiente (`feature-flags.forced_on`, inerte em produção).
 * store/update/destroy/setDefault não mudaram aqui: CodigoBarrasBaselineTest e CodigoBarrasTenantTest.
 * Tenant de teste 98 x cliente fictício 99 (ADR 0358).
 */

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasTable('barcodes') || ! Schema::hasColumn('users', 'business_id')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }

    $this->business = $this->seededTenant();
    $this->user = $this->usuarioComPermissoes(['barcode_settings.access'], $this->business);
    $this->versaoInertia = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
    $this->actingAs($this->user);
    session(['user.business_id' => $this->business->id, 'user.id' => $this->user->id, 'business.id' => $this->business->id]);
});

test('UC-ETQ-01 com a flag ligada, GET /barcodes renderiza Inertia (não a DataTable)', function () {
    config(['feature-flags.forced_on' => 'useV2ConfiguracoesCodigoBarras']);

    $r = $this->withHeaders([
        'X-Inertia' => 'true', 'X-Inertia-Version' => $this->versaoInertia, 'X-Requested-With' => 'XMLHttpRequest',
    ])->get('/barcodes');

    $r->assertOk();
    expect($r->json('component'))->toBe('Configuracoes/CodigoBarras/Index');
});

test('UC-ETQ-02 com a flag desligada, GET /barcodes segue na Blade', function () {
    config(['feature-flags.forced_on' => '']);

    $this->get('/barcodes')->assertOk()->assertViewIs('barcode.index');
});

test('UC-ETQ-03 Tier 0 — a lista adiada traz só as configurações do negócio, padrão primeiro, em polegada', function () {
    config(['feature-flags.forced_on' => 'useV2ConfiguracoesCodigoBarras']);
    $outro = $this->seededSupportClientTenant();
    $base = ['is_continuous' => 0, 'stickers_in_one_sheet' => 24, 'width' => 1.5, 'height' => 1];
    $outraMinha = DB::table('barcodes')->insertGetId($base + ['business_id' => $this->business->id, 'name' => 'A '.uniqid(), 'is_default' => 0]);
    $padrao = DB::table('barcodes')->insertGetId($base + ['business_id' => $this->business->id, 'name' => 'Z '.uniqid(), 'is_default' => 1]);
    $alheia = DB::table('barcodes')->insertGetId($base + ['business_id' => $outro->id, 'name' => 'Alheia '.uniqid(), 'is_default' => 0]);
    $global = DB::table('barcodes')->insertGetId($base + ['business_id' => null, 'name' => 'Global '.uniqid(), 'is_default' => 0]);

    $r = $this->withHeaders([
        'X-Inertia' => 'true', 'X-Inertia-Version' => $this->versaoInertia,
        'X-Inertia-Partial-Component' => 'Configuracoes/CodigoBarras/Index', 'X-Inertia-Partial-Data' => 'etiquetas',
    ])->get('/barcodes');
    $r->assertOk();
    $lista = collect($r->json('props.etiquetas') ?? []);
    $ids = $lista->pluck('id')->all();

    expect($ids)->toContain($padrao);
    expect($ids)->toContain($outraMinha);
    expect($ids)->not->toContain($alheia);
    expect($ids)->not->toContain($global);
    expect($lista->first()['id'])->toBe($padrao);
    expect($lista->first()['padrao'])->toBeTrue();
    expect($lista->first()['medidas']['width'])->toBe(1.5);
});
