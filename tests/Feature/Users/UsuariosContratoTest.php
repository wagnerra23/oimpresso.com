<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Thread sistema/playbook/01 — F2 BACKEND BASELINE de /users (ManageUserController).
 *
 * Caracteriza o comportamento de HOJE, antes de qualquer mudança no controller (ADR 0104 F2):
 * o que o store/update gravam, o que o destroy apaga e o que a DataTable do index lista.
 * Tenant de teste 98 x cliente fictício 99 (ADR 0358). Nunca biz=4.
 *
 * Funções com prefixo `usr` de propósito: outros testes da pasta já declaram helpers globais.
 */

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasColumn('users', 'is_cmmsn_agnt')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }

    $this->business = $this->seededTenant();
    $this->user = $this->usuarioComPermissoes(['user.view', 'user.create', 'user.update', 'user.delete'], $this->business);

    // O store() revoga location.* do papel escolhido: com a permissão inexistente o Spatie lança.
    Permission::findOrCreate('access_all_locations', 'web');
    foreach (DB::table('business_locations')->where('business_id', $this->business->id)->pluck('id') as $loc) {
        Permission::findOrCreate('location.'.$loc, 'web');
    }

    // Com o Superadmin instalado, store() exige assinatura ativa e cota de usuários (0 = sem limite).
    if (app(\App\Utils\ModuleUtil::class)->isSuperadminInstalled() && Schema::hasTable('subscriptions')) {
        DB::table('subscriptions')->insert([
            'business_id' => $this->business->id, 'package_id' => 0, 'package_price' => 0,
            'start_date' => now()->subDay()->toDateString(), 'end_date' => now()->addDay()->toDateString(),
            'package_details' => json_encode(['user_count' => 0]), 'created_id' => $this->user->id,
            'status' => 'approved', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    $this->actingAs($this->user);
    session(['user.business_id' => $this->business->id, 'user.id' => $this->user->id, 'business.id' => $this->business->id]);
});

function usrPapel(int $businessId, string $nome = 'Caixa'): Role
{
    return Role::create(['name' => $nome.uniqid().'#'.$businessId, 'business_id' => $businessId, 'guard_name' => 'web']);
}

function usrPayload(Role $papel, array $extra = []): array
{
    return array_merge([
        'surname' => 'Sr', 'first_name' => 'Base'.uniqid(), 'last_name' => 'Teste',
        'email' => 'usr'.uniqid().'@exemplo.test', 'role' => $papel->id, 'is_active' => 'active',
    ], $extra);
}

function usrGravado(string $firstName): ?\App\User
{
    return \App\User::where('first_name', $firstName)->first();
}

test('baseline store 1 — sem login: grava no negócio da sessão, com a função, sem usuário nem senha', function () {
    $papel = usrPapel($this->business->id);
    $dados = usrPayload($papel);

    $this->post('/users', $dados)->assertRedirect('/users')->assertSessionHas('status.success', 1);

    $u = usrGravado($dados['first_name']);
    expect($u)->not->toBeNull();
    expect((int) $u->business_id)->toBe((int) $this->business->id);
    expect((int) $u->allow_login)->toBe(0);
    expect($u->username)->toBeNull();
    expect($u->password)->toBeNull();
    expect($u->status)->toBe('active');
    expect($u->hasRole($papel->name))->toBeTrue();
});

test('baseline store 2 — com login: usuário com a extensão da empresa e senha com hash', function () {
    $papel = usrPapel($this->business->id);
    $login = 'usr'.substr(uniqid(), -8);
    $dados = usrPayload($papel, ['allow_login' => 1, 'username' => $login, 'password' => 'SenhaBase#123']);

    $this->post('/users', $dados)->assertRedirect('/users')->assertSessionHas('status.success', 1);

    $u = usrGravado($dados['first_name']);
    expect($u)->not->toBeNull();
    expect((int) $u->allow_login)->toBe(1);
    expect($u->username)->toBe($login.app(\App\Utils\ModuleUtil::class)->getUsernameExtension());
    expect(Hash::check('SenhaBase#123', $u->password))->toBeTrue();
});

test('baseline store 3 — valor: comissão e desconto máximo chegam iguais por dois caminhos', function () {
    $util = new \App\Utils\Util;
    $papel = usrPapel($this->business->id);
    $dados = usrPayload($papel, ['cmmsn_percent' => '2,50', 'max_sales_discount_percent' => '10,00']);

    $this->post('/users', $dados)->assertSessionHas('status.success', 1);

    $u = usrGravado($dados['first_name']);
    // Caminho 1: o endpoint gravou. Caminho 2: num_uf direto sobre o mesmo texto.
    expect((float) $u->cmmsn_percent)->toBe(2.5);
    expect((float) $util->num_uf('2,50'))->toBe(2.5);
    expect((float) $u->max_sales_discount_percent)->toBe(10.0);
    expect((float) $util->num_uf('10,00'))->toBe(10.0);
});

test('baseline store 4 — contatos permitidos: grava o acesso aos contatos escolhidos', function () {
    $contato = DB::table('contacts')->where('business_id', $this->business->id)->value('id');
    expect($contato)->not->toBeNull('o seed precisa de um contato no tenant 98 (anti-vácuo)');
    $papel = usrPapel($this->business->id);
    $dados = usrPayload($papel, ['selected_contacts' => 1, 'selected_contact_ids' => [$contato]]);

    $this->post('/users', $dados)->assertSessionHas('status.success', 1);

    $u = usrGravado($dados['first_name']);
    expect((int) $u->selected_contacts)->toBe(1);
    expect($u->contactAccess()->pluck('contacts.id')->all())->toBe([(int) $contato]);
});

test('baseline store 5 — locais: todos os locais ou só o local marcado', function () {
    $loc = DB::table('business_locations')->where('business_id', $this->business->id)->value('id');
    expect($loc)->not->toBeNull('o seed precisa de um local no tenant 98 (anti-vácuo)');
    $papel = usrPapel($this->business->id);

    $todos = usrPayload($papel, ['access_all_locations' => 'access_all_locations']);
    $this->post('/users', $todos)->assertSessionHas('status.success', 1);
    expect(usrGravado($todos['first_name'])->permitted_locations($this->business->id))->toBe('all');

    $um = usrPayload($papel, ['location_permissions' => ['location.'.$loc]]);
    $this->post('/users', $um)->assertSessionHas('status.success', 1);
    expect(usrGravado($um['first_name'])->permitted_locations($this->business->id))->toBe([(int) $loc]);
});

test('baseline store — sem user.create responde 403 e não grava', function () {
    $leitor = $this->usuarioComPermissoes(['user.view'], $this->business);
    $this->actingAs($leitor);
    $dados = usrPayload(usrPapel($this->business->id));

    $this->post('/users', $dados)->assertForbidden();
    expect(usrGravado($dados['first_name']))->toBeNull();
});

test('baseline update — troca nome e função; sem allow_login o usuário perde login e contatos', function () {
    $antes = usrPapel($this->business->id, 'Antes');
    $depois = usrPapel($this->business->id, 'Depois');
    $dados = usrPayload($antes, ['allow_login' => 1, 'username' => 'usr'.substr(uniqid(), -8), 'password' => 'SenhaBase#123']);
    $this->post('/users', $dados)->assertSessionHas('status.success', 1);
    $u = usrGravado($dados['first_name']);

    $this->post("/users/{$u->id}", [
        '_method' => 'PUT', 'first_name' => 'Editado'.uniqid(), 'email' => $u->email, 'role' => $depois->id,
        'is_active' => 'active', 'max_sales_discount_percent' => '5,00',
    ])->assertRedirect('/users')->assertSessionHas('status.success', 1);

    $u = \App\User::findOrFail($u->id);
    expect($u->first_name)->toStartWith('Editado');
    expect($u->hasRole($depois->name))->toBeTrue();
    expect($u->hasRole($antes->name))->toBeFalse();
    expect((int) $u->allow_login)->toBe(0);
    expect($u->username)->toBeNull();
    expect((float) $u->max_sales_discount_percent)->toBe(5.0);
});

test('baseline destroy — AJAX apaga (soft delete) o do negócio; o de outra empresa fica', function () {
    $outro = $this->seededSupportClientTenant();
    $meu = \App\User::factory()->create(['business_id' => $this->business->id]);
    $alheio = \App\User::factory()->create(['business_id' => $outro->id]);
    $h = ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'];

    $r = $this->withHeaders($h)->delete("/users/{$meu->id}");
    $r->assertOk();
    expect($r->json('success'))->toBeTrue();
    expect(\App\User::withTrashed()->find($meu->id)->trashed())->toBeTrue();

    $r = $this->withHeaders($h)->delete("/users/{$alheio->id}");
    expect($r->json('success'))->toBeFalse();
    expect(\App\User::withTrashed()->find($alheio->id)->trashed())->toBeFalse();
});

test('baseline edit — o formulário não abre usuário de outra empresa', function () {
    $outro = $this->seededSupportClientTenant();
    $alheio = \App\User::factory()->create(['business_id' => $outro->id]);

    $this->get("/users/{$alheio->id}/edit")->assertNotFound();
});

test('baseline index — a DataTable (AJAX sem X-Inertia) lista só usuários do negócio, sem comissionados', function () {
    $outro = $this->seededSupportClientTenant();
    $meu = \App\User::factory()->create(['business_id' => $this->business->id, 'user_type' => 'user', 'is_cmmsn_agnt' => 0]);
    $agente = \App\User::factory()->create(['business_id' => $this->business->id, 'user_type' => 'user', 'is_cmmsn_agnt' => 1]);
    $alheio = \App\User::factory()->create(['business_id' => $outro->id, 'user_type' => 'user', 'is_cmmsn_agnt' => 0]);

    $r = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->get('/users?length=-1');

    $r->assertOk();
    $emails = collect($r->json('data'))->pluck('email');
    expect($emails)->toContain($meu->email);
    expect($emails->contains($agente->email))->toBeFalse();
    expect($emails->contains($alheio->email))->toBeFalse();
});
