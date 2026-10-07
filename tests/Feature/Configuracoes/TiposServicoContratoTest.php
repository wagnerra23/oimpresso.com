<?php

declare(strict_types=1);
// Cobre UC-TSERV-01, UC-TSERV-02, UC-TSERV-03, UC-TSERV-04 (Configuracoes/TiposServico/Index.casos.md).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Thread sistema/playbook/05, F3 — /types-of-service em Inertia atrás da flag useV2ConfiguracoesTiposServico.
 *
 * A flag é ligada pelo override de ambiente (`feature-flags.forced_on`, inerte em produção).
 * store/update/destroy não mudaram: TiposServicoBaselineTest. Tenant de teste 98 x cliente fictício 99 (ADR 0358).
 */

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasTable('types_of_services') || ! Schema::hasTable('selling_price_groups')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }

    $this->business = $this->seededTenant();
    $this->user = $this->usuarioComPermissoes(['access_types_of_service'], $this->business);
    $this->versaoInertia = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
    $this->actingAs($this->user);
    session(['user.business_id' => $this->business->id, 'user.id' => $this->user->id, 'business.id' => $this->business->id]);
});

test('UC-TSERV-01 com a flag ligada, GET /types-of-service renderiza Inertia (não a DataTable)', function () {
    config(['feature-flags.forced_on' => 'useV2ConfiguracoesTiposServico']);

    $r = $this->withHeaders([
        'X-Inertia' => 'true', 'X-Inertia-Version' => $this->versaoInertia, 'X-Requested-With' => 'XMLHttpRequest',
    ])->get('/types-of-service');

    $r->assertOk();
    expect($r->json('component'))->toBe('Configuracoes/TiposServico/Index');
});

test('UC-TSERV-02 com a flag desligada, GET /types-of-service segue na Blade', function () {
    config(['feature-flags.forced_on' => '']);

    $this->get('/types-of-service')->assertOk()->assertViewIs('types_of_service.index');
});

test('UC-TSERV-03 Tier 0 e valor — tipos do negócio com a taxa e a tabela por local do banco', function () {
    config(['feature-flags.forced_on' => 'useV2ConfiguracoesTiposServico']);
    $outro = $this->seededSupportClientTenant();
    $local = DB::table('business_locations')->where('business_id', $this->business->id)->value('id');
    expect($local)->not->toBeNull();
    $atacado = DB::table('selling_price_groups')->insertGetId(['business_id' => $this->business->id, 'name' => 'Atacado '.uniqid(), 'is_active' => 1]);
    $tabelaAlheia = DB::table('selling_price_groups')->insertGetId(['business_id' => $outro->id, 'name' => 'Alheia '.uniqid(), 'is_active' => 1]);
    $base = ['enable_custom_fields' => 0, 'created_at' => now(), 'updated_at' => now()];
    $meu = DB::table('types_of_services')->insertGetId($base + [
        'business_id' => $this->business->id, 'name' => 'Montagem '.uniqid(), 'packing_charge' => 8.5, 'packing_charge_type' => 'percent',
        // 999999: um local que não é do negócio, apontando para uma tabela alheia — não pode aparecer.
        'location_price_group' => json_encode([(string) $local => (string) $atacado, '999999' => (string) $tabelaAlheia]),
    ]);
    $alheio = DB::table('types_of_services')->insertGetId($base + [
        'business_id' => $outro->id, 'name' => 'Alheio '.uniqid(), 'packing_charge' => 0, 'packing_charge_type' => 'fixed',
    ]);

    $r = $this->withHeaders([
        'X-Inertia' => 'true', 'X-Inertia-Version' => $this->versaoInertia,
        'X-Inertia-Partial-Component' => 'Configuracoes/TiposServico/Index', 'X-Inertia-Partial-Data' => 'tipos',
    ])->get('/types-of-service');
    $r->assertOk();
    $porId = collect($r->json('props.tipos') ?? [])->keyBy('id');

    expect($porId->has($meu))->toBeTrue();
    expect($porId->has($alheio))->toBeFalse();
    expect($porId[$meu]['taxa'])->toBe(8.5);
    expect($porId[$meu]['tipo_taxa'])->toBe('percent');
    expect(count($porId[$meu]['precos_por_local']))->toBe(1);
    expect($porId[$meu]['precos_por_local'][0]['tabela'])->toStartWith('Atacado');
});

test('UC-TSERV-04 valor — o drawer cadastra com a taxa do texto e edita sem mover taxa nem tabela por local', function () {
    config(['feature-flags.forced_on' => 'useV2ConfiguracoesTiposServico']);
    $ajax = ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'];
    $local = DB::table('business_locations')->where('business_id', $this->business->id)->value('id');
    expect($local)->not->toBeNull();
    $atacado = DB::table('selling_price_groups')->insertGetId(['business_id' => $this->business->id, 'name' => 'Atacado '.uniqid(), 'is_active' => 1]);
    $nome = 'Entrega '.uniqid();

    // Cadastro: o salvar() do Index.tsx manda a taxa em texto pt-BR e o mapa local → tabela.
    $r = $this->withHeaders($ajax)->post('/types-of-service', [
        'name' => $nome, 'description' => '', 'packing_charge_type' => 'fixed', 'packing_charge' => '35,00',
        'location_price_group' => [(string) $local => (string) $atacado],
    ]);
    expect($r->json('success'))->toBeTrue();
    $id = (int) DB::table('types_of_services')->where('name', $nome)->value('id');
    expect((float) DB::table('types_of_services')->where('id', $id)->value('packing_charge'))->toBe(35.0);
    expect((float) (new \App\Utils\Util)->num_uf('35,00'))->toBe(35.0);

    // Edição: o drawer parte da prop (paraTexto(taxa) + tabela_por_local) e só o nome muda.
    $h = ['X-Inertia' => 'true', 'X-Inertia-Version' => $this->versaoInertia, 'X-Inertia-Partial-Component' => 'Configuracoes/TiposServico/Index', 'X-Inertia-Partial-Data' => 'tipos'];
    $tipo = collect($this->withHeaders($h)->get('/types-of-service')->json('props.tipos'))->firstWhere('id', $id);
    expect($tipo['tabela_por_local'])->toBe([(string) $local => (string) $atacado]);
    $texto = number_format($tipo['taxa'], 2, ',', '');
    $this->withHeaders($ajax)->put("/types-of-service/{$id}", [
        'name' => $nome.' editado', 'description' => $tipo['descricao'], 'packing_charge_type' => $tipo['tipo_taxa'],
        'packing_charge' => $texto, 'location_price_group' => $tipo['tabela_por_local'],
    ])->assertOk();

    $depois = DB::table('types_of_services')->where('id', $id)->first();
    expect($depois->name)->toBe($nome.' editado');
    expect((float) $depois->packing_charge)->toBe(35.0);
    expect(json_decode($depois->location_price_group, true))->toBe([(string) $local => (string) $atacado]);
});
