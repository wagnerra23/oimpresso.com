<?php

declare(strict_types=1);
// Cobre UC-LOCAL-01, UC-LOCAL-02, UC-LOCAL-03 (Configuracoes/Locais/Index.casos.md).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

/**
 * Thread sistema/playbook/04, F3 — /business-location em Inertia atrás da flag useV2ConfiguracoesLocais.
 *
 * A flag é ligada pelo override de ambiente (`feature-flags.forced_on`, inerte em produção).
 * store/update/activateDeactivateLocation não mudaram: LocaisBaselineTest.
 * Tenant de teste 98 x cliente fictício 99 (ADR 0358).
 */

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasTable('business_locations') || ! Schema::hasColumn('business_locations', 'cnpj')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }

    $this->business = $this->seededTenant();
    $this->user = $this->usuarioComPermissoes(['business_settings.access', 'access_all_locations'], $this->business);
    $this->versaoInertia = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
    $this->actingAs($this->user);
    session(['user.business_id' => $this->business->id, 'user.id' => $this->user->id, 'business.id' => $this->business->id]);
});

function locContratoLocal(int $businessId, string $nome): int
{
    $esq = DB::table('invoice_schemes')->insertGetId(['business_id' => $businessId, 'name' => 'Esq '.uniqid(), 'scheme_type' => 'blank']);
    $lay = DB::table('invoice_layouts')->insertGetId(['business_id' => $businessId, 'name' => 'Lay '.uniqid()]);

    return DB::table('business_locations')->insertGetId([
        'business_id' => $businessId, 'name' => $nome, 'country' => 'Brasil', 'state' => 'MT', 'city' => 'Cuiabá',
        'zip_code' => '7806500', 'invoice_scheme_id' => $esq, 'invoice_layout_id' => $lay, 'is_active' => 1,
    ]);
}

function locContratoIds($teste): array
{
    $r = $teste->withHeaders([
        'X-Inertia' => 'true', 'X-Inertia-Version' => $teste->versaoInertia,
        'X-Inertia-Partial-Component' => 'Configuracoes/Locais/Index', 'X-Inertia-Partial-Data' => 'locais',
    ])->get('/business-location');
    $r->assertOk();

    return collect($r->json('props.locais') ?? [])->pluck('id')->all();
}

test('UC-LOCAL-01 com a flag ligada, GET /business-location renderiza Inertia (não a DataTable)', function () {
    config(['feature-flags.forced_on' => 'useV2ConfiguracoesLocais']);

    $r = $this->withHeaders([
        'X-Inertia' => 'true', 'X-Inertia-Version' => $this->versaoInertia, 'X-Requested-With' => 'XMLHttpRequest',
    ])->get('/business-location');

    $r->assertOk();
    expect($r->json('component'))->toBe('Configuracoes/Locais/Index');
});

test('UC-LOCAL-02 com a flag desligada, GET /business-location segue na Blade', function () {
    config(['feature-flags.forced_on' => '']);

    $this->get('/business-location')->assertOk()->assertViewIs('business_location.index');
});

test('UC-LOCAL-03 Tier 0 — a lista adiada é do negócio e respeita os locais permitidos', function () {
    config(['feature-flags.forced_on' => 'useV2ConfiguracoesLocais']);
    $outro = $this->seededSupportClientTenant();
    $liberado = locContratoLocal($this->business->id, 'Liberado '.uniqid());
    $vedado = locContratoLocal($this->business->id, 'Vedado '.uniqid());
    $alheio = locContratoLocal($outro->id, 'Alheio '.uniqid());

    $todos = locContratoIds($this);
    expect($todos)->toContain($liberado);
    expect($todos)->toContain($vedado);
    expect($todos)->not->toContain($alheio);

    $restrito = $this->usuarioComPermissoes(['business_settings.access'], $this->business);
    $restrito->givePermissionTo(Permission::findOrCreate("location.{$liberado}", 'web'));
    $this->actingAs($restrito->fresh());

    $permitidos = locContratoIds($this);
    expect($permitidos)->toContain($liberado);
    expect($permitidos)->not->toContain($vedado);
    expect($permitidos)->not->toContain($alheio);
});
