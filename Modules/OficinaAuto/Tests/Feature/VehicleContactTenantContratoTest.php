<?php
// @covers-us US-OFICINA-001

declare(strict_types=1);

// casos (G-2 rastreabilidade · ADR 0264): defende
//   UC-OVC-04 (OficinaAuto/Vehicles/Create) — cliente de outro business rejeitado no cadastro
//   UC-OVE-03 (OficinaAuto/Vehicles/Edit)   — cliente de outro business rejeitado na edição

use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\OficinaAuto\Entities\Vehicle;

uses(Tests\TestCase::class);

/**
 * Contrato Tier 0 (ADR 0093): o dono (contact_id) do veículo só pode ser cliente da
 * própria empresa.
 *
 * Antes deste teste a regra era `['nullable', 'integer']`, sem exists nem escopo: o
 * veículo aceitava cliente de outra empresa, e quando a OS é concluída a venda derivada
 * sai em nome desse cliente.
 *
 * O 302 não prova nada (sucesso e recusa são ambos redirect). A prova é: erro NO CAMPO
 * contact_id + nenhuma linha gravada/alterada. O caso de controle (cliente da própria
 * empresa) garante que a requisição chega ao controller.
 *
 * Tenants: 98 é a empresa do usuário (ADR 0358). A empresa "outra" é o biz=2 do seed da
 * lane — o 99 não existe no seed e quebraria as FKs de business.
 */

const CTEN_BIZ_PROPRIA = 98;
const CTEN_BIZ_OUTRA = 2;
const CTEN_PLACA = 'CTEN';

beforeEach(function () {
    $this->contatos = []; // antes dos skips: o ->after() roda mesmo em teste pulado

    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: requer schema MySQL UltimatePOS');
    }
    if (! Schema::hasTable('vehicles') || ! Schema::hasTable('contacts')) {
        $this->markTestSkipped('tabelas vehicles/contacts ausentes');
    }
    foreach ([CTEN_BIZ_PROPRIA, CTEN_BIZ_OUTRA] as $biz) {
        if (! DB::table('business')->where('id', $biz)->exists()) {
            $this->markTestSkipped("business {$biz} ausente — seed pest-mysql-setup não rodou");
        }
    }

    session(['user.business_id' => CTEN_BIZ_PROPRIA]);
});

function cten_contato(int $biz): int
{
    $dono = (int) DB::table('business')->where('id', $biz)->value('owner_id');

    return (int) DB::table('contacts')->insertGetId([
        'business_id'    => $biz,
        'type'           => 'customer',
        'name'           => 'Cliente CTEN ' . $biz,
        'mobile'         => '48999990000',
        'contact_status' => 'active',
        'created_by'     => $dono,
        'created_at'     => now(),
        'updated_at'     => now(),
    ]);
}

function cten_usuario(): User
{
    // user_type/allow_login explícitos: sem eles o CheckUserLogin devolve 403 antes do controller.
    $user = User::factory()->create([
        'business_id' => CTEN_BIZ_PROPRIA,
        'user_type'   => 'user',
        'allow_login' => 1,
    ]);
    $user->givePermissionTo('superadmin');

    return $user;
}

function cten_placa(): string
{
    return CTEN_PLACA . random_int(100, 999);
}

function cten_limpar(array $contatos): void
{
    Vehicle::withoutGlobalScopes() // SUPERADMIN: limpeza só dos dois tenants do teste
        ->whereIn('business_id', [CTEN_BIZ_PROPRIA, CTEN_BIZ_OUTRA])
        ->where('plate', 'like', CTEN_PLACA . '%')
        ->forceDelete();
    if ($contatos !== []) {
        DB::table('contacts')->whereIn('id', $contatos)->delete();
    }
}

it('UC-OVC-04 · cadastrar veículo com cliente de OUTRA empresa é recusado no campo contact_id e não grava', function () {
    $this->contatos[] = $alheio = cten_contato(CTEN_BIZ_OUTRA);
    $placa = cten_placa();

    $this->actingAs(cten_usuario())
        ->post('/oficina-auto/veiculos', [
            'plate'        => $placa,
            'vehicle_type' => 'automovel',
            'contact_id'   => $alheio,
        ])
        ->assertSessionHasErrors('contact_id');

    expect(Vehicle::withoutGlobalScopes()->where('plate', $placa)->count())->toBe(0);
})->after(fn () => cten_limpar($this->contatos));

it('UC-OVC-04 · controle: cadastrar veículo com cliente da PRÓPRIA empresa grava', function () {
    $this->contatos[] = $proprio = cten_contato(CTEN_BIZ_PROPRIA);
    $placa = cten_placa();

    $this->actingAs(cten_usuario())
        ->post('/oficina-auto/veiculos', [
            'plate'        => $placa,
            'vehicle_type' => 'automovel',
            'contact_id'   => $proprio,
        ])
        ->assertSessionHasNoErrors();

    $veiculo = Vehicle::withoutGlobalScopes()->where('plate', $placa)->first();
    expect($veiculo)->not->toBeNull();
    expect((int) $veiculo->contact_id)->toBe($proprio);
    expect((int) $veiculo->business_id)->toBe(CTEN_BIZ_PROPRIA);
})->after(fn () => cten_limpar($this->contatos));

it('UC-OVE-03 · editar veículo trocando pra cliente de OUTRA empresa é recusado e o dono não muda', function () {
    $this->contatos[] = $proprio = cten_contato(CTEN_BIZ_PROPRIA);
    $this->contatos[] = $alheio = cten_contato(CTEN_BIZ_OUTRA);
    $veiculo = Vehicle::withoutGlobalScopes()->create([ // SUPERADMIN: fixture de teste em tenant explícito
        'business_id'  => CTEN_BIZ_PROPRIA,
        'plate'        => cten_placa(),
        'vehicle_type' => 'automovel',
        'contact_id'   => $proprio,
    ]);

    $this->actingAs(cten_usuario())
        ->put('/oficina-auto/veiculos/' . $veiculo->id, [
            'plate'        => $veiculo->plate,
            'vehicle_type' => 'automovel',
            'contact_id'   => $alheio,
        ])
        ->assertSessionHasErrors('contact_id');

    expect((int) Vehicle::withoutGlobalScopes()->find($veiculo->id)->contact_id)->toBe($proprio);
})->after(fn () => cten_limpar($this->contatos));

it('UC-OVE-03 · controle: editar veículo trocando pra outro cliente da PRÓPRIA empresa grava', function () {
    $this->contatos[] = $proprio = cten_contato(CTEN_BIZ_PROPRIA);
    $this->contatos[] = $outroProprio = cten_contato(CTEN_BIZ_PROPRIA);
    $veiculo = Vehicle::withoutGlobalScopes()->create([ // SUPERADMIN: fixture de teste em tenant explícito
        'business_id'  => CTEN_BIZ_PROPRIA,
        'plate'        => cten_placa(),
        'vehicle_type' => 'automovel',
        'contact_id'   => $proprio,
    ]);

    $this->actingAs(cten_usuario())
        ->put('/oficina-auto/veiculos/' . $veiculo->id, [
            'plate'        => $veiculo->plate,
            'vehicle_type' => 'automovel',
            'contact_id'   => $outroProprio,
        ])
        ->assertSessionHasNoErrors();

    expect((int) Vehicle::withoutGlobalScopes()->find($veiculo->id)->contact_id)->toBe($outroProprio);
})->after(fn () => cten_limpar($this->contatos));
