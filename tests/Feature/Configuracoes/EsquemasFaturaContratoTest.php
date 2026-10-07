<?php

declare(strict_types=1);
// Cobre UC-ESQF-01, UC-ESQF-02, UC-ESQF-03 (Configuracoes/EsquemasFatura/Index.casos.md).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Thread sistema/playbook/05, F3 — /invoice-schemes em Inertia atrás da flag useV2ConfiguracoesEsquemasFatura.
 *
 * A flag é ligada pelo override de ambiente (`feature-flags.forced_on`, inerte em produção). store/update/destroy/
 * setDefault não mudaram: EsquemasFaturaBaselineTest e EsquemaFaturaTenantTest. Tenant 98 x 99 (ADR 0358).
 */

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasTable('invoice_schemes') || ! Schema::hasTable('invoice_layouts')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }

    $this->business = $this->seededTenant();
    $this->user = $this->usuarioComPermissoes(['invoice_settings.access'], $this->business);
    $this->versaoInertia = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
    $this->actingAs($this->user);
    session(['user.business_id' => $this->business->id, 'user.id' => $this->user->id, 'business.id' => $this->business->id]);
});

test('UC-ESQF-01 com a flag ligada, GET /invoice-schemes renderiza Inertia (não a DataTable)', function () {
    config(['feature-flags.forced_on' => 'useV2ConfiguracoesEsquemasFatura']);

    $r = $this->withHeaders([
        'X-Inertia' => 'true', 'X-Inertia-Version' => $this->versaoInertia, 'X-Requested-With' => 'XMLHttpRequest',
    ])->get('/invoice-schemes');

    $r->assertOk();
    expect($r->json('component'))->toBe('Configuracoes/EsquemasFatura/Index');
    expect($r->json('props.tipos_numero'))->toHaveKey('sequential');
});

test('UC-ESQF-02 com a flag desligada, GET /invoice-schemes segue na Blade', function () {
    config(['feature-flags.forced_on' => '']);

    $this->get('/invoice-schemes')->assertOk()->assertViewIs('invoice_scheme.index');
});

test('UC-ESQF-03 Tier 0 — esquemas e layouts do negócio, com o contador do banco', function () {
    config(['feature-flags.forced_on' => 'useV2ConfiguracoesEsquemasFatura']);
    $outro = $this->seededSupportClientTenant();
    $base = ['scheme_type' => 'blank', 'number_type' => 'sequential', 'start_number' => 1, 'total_digits' => 4, 'is_default' => 0];
    $outroMeu = DB::table('invoice_schemes')->insertGetId($base + ['business_id' => $this->business->id, 'name' => 'A '.uniqid()]);
    $meu = DB::table('invoice_schemes')->insertGetId(array_merge($base, [
        'business_id' => $this->business->id, 'name' => 'Z '.uniqid(), 'scheme_type' => 'year', 'prefix' => 'OS',
        'start_number' => 100, 'invoice_count' => 7, 'is_default' => 1,
    ]));
    $alheio = DB::table('invoice_schemes')->insertGetId($base + ['business_id' => $outro->id, 'name' => 'Alheio '.uniqid()]);
    $layoutMeu = DB::table('invoice_layouts')->insertGetId(['business_id' => $this->business->id, 'name' => 'Cupom '.uniqid()]);
    $layoutAlheio = DB::table('invoice_layouts')->insertGetId(['business_id' => $outro->id, 'name' => 'Alheio '.uniqid()]);
    $local = DB::table('business_locations')->where('business_id', $this->business->id)->first(['id', 'name', 'invoice_layout_id']);
    expect($local)->not->toBeNull();
    DB::table('business_locations')->where('id', $local->id)->update(['invoice_layout_id' => $layoutMeu]);

    $r = $this->withHeaders([
        'X-Inertia' => 'true', 'X-Inertia-Version' => $this->versaoInertia,
        'X-Inertia-Partial-Component' => 'Configuracoes/EsquemasFatura/Index', 'X-Inertia-Partial-Data' => 'fatura',
    ])->get('/invoice-schemes');
    $r->assertOk();
    $esquemas = collect($r->json('props.fatura.esquemas') ?? []);
    $layouts = collect($r->json('props.fatura.layouts') ?? [])->keyBy('id');
    $ids = $esquemas->pluck('id')->all();

    expect($ids)->toContain($meu);
    expect($ids)->toContain($outroMeu);
    expect($ids)->not->toContain($alheio);
    expect($esquemas->first()['id'])->toBe($meu);
    expect($esquemas->first()['padrao'])->toBeTrue();
    expect($esquemas->first()['emitidas'])->toBe(7);
    expect($esquemas->first()['prefixo_exibido'])->toBe('OS'.date('Y').config('constants.invoice_scheme_separator'));
    expect($layouts->has($layoutMeu))->toBeTrue();
    expect($layouts->has($layoutAlheio))->toBeFalse();
    expect($layouts[$layoutMeu]['locais'])->toContain($local->name);
});
