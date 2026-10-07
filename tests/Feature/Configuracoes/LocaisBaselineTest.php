<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

/**
 * Baseline F2 (MWART, ADR 0104) de /business-location — o que a Blade grava ANTES da tela React.
 *
 * Thread sistema/playbook/04, tela 3 de 3. Mapa: memory/requisitos/Configuracoes/locais-parity.md.
 * Tenant de teste 98 x cliente fictício 99 (ADR 0358). Todo "não alcançou" tem contraprova positiva.
 */

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasTable('business_locations') || ! Schema::hasColumn('business_locations', 'cnpj')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }

    $this->business = $this->seededTenant();
    $this->user = $this->usuarioComPermissoes(['business_settings.access', 'access_all_locations'], $this->business);
    $this->actingAs($this->user);
    session(['user.business_id' => $this->business->id, 'user.id' => $this->user->id, 'business.id' => $this->business->id]);
    $this->ajax = ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'];

    // Cadastrar passa pela quota do pacote quando o Superadmin está instalado: assinatura ativa, sem limite.
    if (app(\App\Utils\ModuleUtil::class)->isSuperadminInstalled() && Schema::hasTable('subscriptions')) {
        DB::table('subscriptions')->insert([
            'business_id' => $this->business->id, 'package_id' => 0, 'package_price' => 0,
            'start_date' => now()->subDay()->toDateString(), 'end_date' => now()->addDay()->toDateString(),
            'package_details' => json_encode([]), 'created_id' => $this->user->id,
            'status' => 'approved', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
});

/** @return array{0:int,1:int} [esquema, layout] de fatura do negócio */
function locBaseFatura(int $businessId): array
{
    return [
        DB::table('invoice_schemes')->insertGetId(['business_id' => $businessId, 'name' => 'Esq '.uniqid(), 'scheme_type' => 'blank']),
        DB::table('invoice_layouts')->insertGetId(['business_id' => $businessId, 'name' => 'Lay '.uniqid()]),
    ];
}

function locBaseLocal(int $businessId, array $campos = []): int
{
    [$esq, $lay] = locBaseFatura($businessId);

    return DB::table('business_locations')->insertGetId(array_merge([
        'business_id' => $businessId, 'name' => 'Local '.uniqid(), 'country' => 'Brasil', 'state' => 'MT',
        'city' => 'Cuiabá', 'zip_code' => '7806500', 'invoice_scheme_id' => $esq, 'invoice_layout_id' => $lay, 'is_active' => 1,
    ], $campos));
}

function locBaseNomes($teste): string
{
    $r = $teste->withHeaders($teste->ajax)->get('/business-location');
    $r->assertOk();

    return collect($r->json('data'))->pluck(0)->implode('|');
}

test('baseline: a lista traz só os locais do negócio', function () {
    $outro = $this->seededSupportClientTenant();
    $meu = 'Matriz '.uniqid();
    $alheio = 'Alheio '.uniqid();
    locBaseLocal($this->business->id, ['name' => $meu]);
    locBaseLocal($outro->id, ['name' => $alheio]);

    $nomes = locBaseNomes($this);
    expect($nomes)->toContain($meu);
    expect($nomes)->not->toContain($alheio);
});

test('baseline: sem access_all_locations a lista traz só o local com permissão direta location.<id>', function () {
    $liberado = 'Liberado '.uniqid();
    $vedado = 'Vedado '.uniqid();
    $idLiberado = locBaseLocal($this->business->id, ['name' => $liberado]);
    locBaseLocal($this->business->id, ['name' => $vedado]);
    $restrito = $this->usuarioComPermissoes(['business_settings.access'], $this->business);
    $restrito->givePermissionTo(Permission::findOrCreate("location.{$idLiberado}", 'web'));
    $this->actingAs($restrito->fresh());

    $nomes = locBaseNomes($this);
    expect($nomes)->toContain($liberado);
    expect($nomes)->not->toContain($vedado);
});

test('baseline: cadastrar grava no negócio, gera a referência e cria a permissão do local', function () {
    [$esq, $lay] = locBaseFatura($this->business->id);
    $nome = 'Filial '.uniqid();

    $r = $this->withHeaders($this->ajax)->post('/business-location', [
        'name' => $nome, 'country' => 'Brasil', 'state' => 'MT', 'city' => 'Cuiabá', 'zip_code' => '7806500',
        'invoice_scheme_id' => $esq, 'invoice_layout_id' => $lay, 'cnpj' => '00000000000191',
    ]);
    $r->assertOk();
    expect($r->json('success'))->toBeTrue();

    $local = DB::table('business_locations')->where('name', $nome)->first();
    expect($local)->not->toBeNull();
    expect((int) $local->business_id)->toBe((int) $this->business->id);
    expect((string) $local->location_id)->not->toBe('');
    expect($local->cnpj)->toBe('00000000000191');
    expect(Permission::where('name', "location.{$local->id}")->exists())->toBeTrue();
});

test('baseline: cadastrar com esquema de fatura de outro negócio é recusado sem gravar', function () {
    $outro = $this->seededSupportClientTenant();
    [$esqAlheio] = locBaseFatura($outro->id);
    [, $lay] = locBaseFatura($this->business->id);
    $nome = 'Recusada '.uniqid();

    $r = $this->withHeaders($this->ajax)->post('/business-location', [
        'name' => $nome, 'country' => 'Brasil', 'state' => 'MT', 'city' => 'Cuiabá', 'zip_code' => '7806500',
        'invoice_scheme_id' => $esqAlheio, 'invoice_layout_id' => $lay,
    ]);
    $r->assertOk();
    expect($r->json('success'))->toBeFalse();
    expect(DB::table('business_locations')->where('name', $nome)->exists())->toBeFalse();
});

test('baseline: editar altera o local do negócio e não alcança o de outro', function () {
    $outro = $this->seededSupportClientTenant();
    $meu = locBaseLocal($this->business->id);
    $alheio = locBaseLocal($outro->id, ['name' => 'Alheio intocado']);
    [$esq, $lay] = locBaseFatura($this->business->id);
    $corpo = ['name' => 'Renomeado', 'country' => 'Brasil', 'state' => 'MT', 'city' => 'Cuiabá',
        'zip_code' => '7806500', 'invoice_scheme_id' => $esq, 'invoice_layout_id' => $lay];

    $this->withHeaders($this->ajax)->put("/business-location/{$meu}", $corpo)->assertOk();
    expect(DB::table('business_locations')->where('id', $meu)->value('name'))->toBe('Renomeado');

    $this->withHeaders($this->ajax)->put("/business-location/{$alheio}", $corpo)->assertOk();
    expect(DB::table('business_locations')->where('id', $alheio)->value('name'))->toBe('Alheio intocado');
});

test('baseline: ativar/desativar alterna o local do negócio e não alcança o de outro', function () {
    $outro = $this->seededSupportClientTenant();
    $meu = locBaseLocal($this->business->id);
    $alheio = locBaseLocal($outro->id);

    $r = $this->withHeaders($this->ajax)->get("/business-location/activate-deactivate/{$alheio}");
    expect($r->json('success'))->toBeFalse();
    expect((int) DB::table('business_locations')->where('id', $alheio)->value('is_active'))->toBe(1);

    $r = $this->withHeaders($this->ajax)->get("/business-location/activate-deactivate/{$meu}");
    expect($r->json('success'))->toBeTrue();
    expect((int) DB::table('business_locations')->where('id', $meu)->value('is_active'))->toBe(0);
});

test('baseline: sem business_settings.access a lista e o cadastro devolvem 403', function () {
    $this->actingAs($this->usuarioComPermissoes([], $this->business));
    $antes = DB::table('business_locations')->where('business_id', $this->business->id)->count();

    $this->get('/business-location')->assertForbidden();
    $this->post('/business-location', ['name' => 'Não grava'])->assertForbidden();
    expect(DB::table('business_locations')->where('business_id', $this->business->id)->count())->toBe($antes);
});
