<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;

/**
 * Thread sistema/playbook/01 — F3 de /users: o ramo React atrás da chave
 * `mwart.sistema_usuarios_index` (nasce desligada; ligar é a F5, decisão [W]).
 *
 * Prova QUAL ramo o index() escolhe e o que a lista deferida traz. A Blade é trocada por stub
 * de uma linha: o layout AdminLTE não está sob teste aqui. Headers Inertia como o cliente manda
 * de fato (X-Inertia E X-Requested-With — §5 2026-09-08).
 * Tenant de teste 98 x cliente fictício 99 (ADR 0358). Nunca biz=4.
 */

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasColumn('users', 'is_cmmsn_agnt')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }

    $this->business = $this->seededTenant();
    $this->user = $this->usuarioComPermissoes(['user.view', 'user.create', 'user.update', 'user.delete'], $this->business);
    $this->versaoInertia = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());

    $dir = sys_get_temp_dir().'/usua-blade-stubs/manage_user';
    if (! is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    file_put_contents($dir.'/index.blade.php', "BLADE manage_user.index\n");
    app('view')->getFinder()->prependLocation(dirname($dir));

    config(['mwart.sistema_usuarios_index' => ['enabled' => false, 'business_ids' => []]]);

    $this->actingAs($this->user);
    session(['user.business_id' => $this->business->id, 'user.id' => $this->user->id, 'business.id' => $this->business->id]);
});

function usuaInertia($teste, array $extra = []): array
{
    return array_merge([
        'X-Inertia' => 'true', 'X-Inertia-Version' => $teste->versaoInertia, 'X-Requested-With' => 'XMLHttpRequest',
    ], $extra);
}

test('UC-USUA-01 cutover — chave desligada: GET comum fica na Blade; com X-Inertia já é a tela React', function () {
    $this->get('/users')->assertOk()->assertSee('BLADE manage_user.index');

    $r = $this->withHeaders(usuaInertia($this))->get('/users');
    $r->assertOk();
    expect($r->json('component'))->toBe('Usuarios/Index');
});

test('UC-USUA-01 cutover — chave ligada só para esta empresa: GET comum vira React; para outra empresa, Blade', function () {
    config(['mwart.sistema_usuarios_index' => ['enabled' => true, 'business_ids' => [(int) $this->business->id]]]);
    // `true`: o componente tem de existir no disco (resources/js/Pages/Usuarios/Index.tsx).
    $this->get('/users')->assertOk()->assertInertia(fn (AssertableInertia $p) => $p->component('Usuarios/Index', true));

    config(['mwart.sistema_usuarios_index' => ['enabled' => true, 'business_ids' => [(int) $this->business->id + 1000]]]);
    $this->get('/users')->assertOk()->assertSee('BLADE manage_user.index');
});

test('UC-USUA-01 cutover — AJAX sem X-Inertia segue na DataTable mesmo com a chave ligada', function () {
    config(['mwart.sistema_usuarios_index' => ['enabled' => true, 'business_ids' => []]]);

    $r = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])->get('/users');

    $r->assertOk();
    expect($r->json('data'))->toBeArray();
    expect($r->json('component'))->toBeNull();
});

test('UC-USUA-02 Tier 0 — a lista deferida só traz usuários do negócio, sem comissionados, com a função sem o #negócio', function () {
    $outro = $this->seededSupportClientTenant();
    $papel = Role::create(['name' => 'Balcao'.uniqid().'#'.$this->business->id, 'business_id' => $this->business->id, 'guard_name' => 'web']);
    $meu = \App\User::factory()->create(['business_id' => $this->business->id, 'user_type' => 'user', 'is_cmmsn_agnt' => 0, 'status' => 'inactive']);
    $meu->assignRole($papel);
    $agente = \App\User::factory()->create(['business_id' => $this->business->id, 'user_type' => 'user', 'is_cmmsn_agnt' => 1]);
    $alheio = \App\User::factory()->create(['business_id' => $outro->id, 'user_type' => 'user', 'is_cmmsn_agnt' => 0]);

    $r = $this->withHeaders(usuaInertia($this, [
        'X-Inertia-Partial-Component' => 'Usuarios/Index', 'X-Inertia-Partial-Data' => 'usuarios',
    ]))->get('/users');
    $r->assertOk();
    $linhas = collect($r->json('props.usuarios'))->keyBy('id');

    expect($linhas->has($meu->id))->toBeTrue('o usuário do próprio negócio tem de aparecer (anti-vácuo)');
    expect($linhas->has($agente->id))->toBeFalse();
    expect($linhas->has($alheio->id))->toBeFalse();
    expect($linhas[$meu->id]['funcao'])->toBe(explode('#', $papel->name)[0]);
    expect($linhas[$meu->id]['ativo'])->toBeFalse();
    expect($linhas[$this->user->id]['voce'])->toBeTrue();
    expect($linhas[$meu->id]['voce'])->toBeFalse();
});

test('UC-USUA-03 quem só tem user.view abre a tela sem criar, editar nem excluir', function () {
    $leitor = $this->usuarioComPermissoes(['user.view'], $this->business);
    $this->actingAs($leitor);

    $r = $this->withHeaders(usuaInertia($this))->get('/users');

    $r->assertOk();
    expect($r->json('component'))->toBe('Usuarios/Index');
    expect($r->json('props.pode'))->toBe(['criar' => false, 'ver' => true, 'editar' => false, 'excluir' => false]);
});

test('UC-USUA-03 sem user.view nem user.create a tela responde 403', function () {
    $this->actingAs($this->usuarioComPermissoes(['sell.view'], $this->business));

    $this->withHeaders(usuaInertia($this))->get('/users')->assertForbidden();
});
