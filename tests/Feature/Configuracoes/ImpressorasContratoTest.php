<?php

declare(strict_types=1);
// Cobre UC-IMPR-01, UC-IMPR-02, UC-IMPR-03 (Configuracoes/Impressoras/Index.casos.md).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Thread sistema/playbook/04, F3 — /printers em Inertia atrás da flag useV2ConfiguracoesImpressoras.
 *
 * A flag é ligada pelo override de ambiente (`feature-flags.forced_on`, inerte em produção), não
 * pelo GrowthBook. store/update/destroy não mudaram: o comportamento deles é do ImpressorasBaselineTest.
 * Tenant de teste 98 x cliente fictício 99 (ADR 0358).
 */

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasTable('printers') || ! Schema::hasColumn('users', 'business_id')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }

    $this->business = $this->seededTenant();
    $this->user = $this->usuarioComPermissoes(['access_printers'], $this->business);
    $this->versaoInertia = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
    $this->actingAs($this->user);
    session(['user.business_id' => $this->business->id, 'user.id' => $this->user->id, 'business.id' => $this->business->id]);
});

function imprLigarFlag(): void
{
    config(['feature-flags.forced_on' => 'useV2ConfiguracoesImpressoras']);
}

test('UC-IMPR-01 com a flag ligada, GET /printers renderiza Inertia (não a DataTable)', function () {
    imprLigarFlag();

    // X-Requested-With junto do X-Inertia, como o browser manda: o ramo ajax() antigo engolia isso.
    $r = $this->withHeaders([
        'X-Inertia' => 'true', 'X-Inertia-Version' => $this->versaoInertia, 'X-Requested-With' => 'XMLHttpRequest',
    ])->get('/printers');

    $r->assertOk();
    expect($r->json('component'))->toBe('Configuracoes/Impressoras/Index');
    expect($r->json('props.opcoes.conexao'))->toHaveKey('network');
});

test('UC-IMPR-02 com a flag desligada, GET /printers segue na Blade', function () {
    config(['feature-flags.forced_on' => '']);

    $this->get('/printers')->assertOk()->assertViewIs('printer.index');
});

test('UC-IMPR-03 Tier 0 — a lista adiada traz só as impressoras do negócio da sessão', function () {
    imprLigarFlag();
    $outro = $this->seededSupportClientTenant();
    $base = ['connection_type' => 'network', 'capability_profile' => 'default', 'char_per_line' => '48',
        'ip_address' => '192.168.0.31', 'port' => '9100', 'path' => '', 'created_by' => $this->user->id];
    $minha = DB::table('printers')->insertGetId($base + ['business_id' => $this->business->id, 'name' => 'Caixa '.uniqid()]);
    $alheia = DB::table('printers')->insertGetId($base + ['business_id' => $outro->id, 'name' => 'Alheia '.uniqid()]);

    $r = $this->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => $this->versaoInertia,
        'X-Inertia-Partial-Component' => 'Configuracoes/Impressoras/Index',
        'X-Inertia-Partial-Data' => 'impressoras',
    ])->get('/printers');
    $r->assertOk();
    $porId = collect($r->json('props.impressoras') ?? [])->keyBy('id');

    expect($porId->has($minha))->toBeTrue();
    expect($porId->has($alheia))->toBeFalse();
    expect($porId[$minha]['conexao'])->toBe('network');
    expect($porId[$minha]['ip'])->toBe('192.168.0.31');
});
