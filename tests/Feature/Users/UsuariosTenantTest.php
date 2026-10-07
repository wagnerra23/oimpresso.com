<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Thread sistema/playbook/01 — F2b Tier 0 de /users (ManageUserController).
 *
 * O store/update aceitavam o id de função e de contato vindos do formulário sem conferir a
 * empresa: dava para gravar usuário com função de outra empresa (inclusive o Admin dela) e com
 * acesso a contato de outra empresa. E o show() quebrava (500) com usuário de outra empresa em
 * vez de responder 404. Tenant de teste 98 x cliente fictício 99 (ADR 0358). Nunca biz=4.
 *
 * Funções com prefixo `usrt`: outros testes da pasta declaram helpers globais.
 */

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasColumn('users', 'is_cmmsn_agnt')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }

    $this->business = $this->seededTenant();
    $this->outro = $this->seededSupportClientTenant();
    $this->user = $this->usuarioComPermissoes(['user.view', 'user.create', 'user.update'], $this->business);

    Permission::findOrCreate('access_all_locations', 'web');
    foreach (DB::table('business_locations')->where('business_id', $this->business->id)->pluck('id') as $loc) {
        Permission::findOrCreate('location.'.$loc, 'web');
    }

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

function usrtPapel(int $businessId, string $nome = 'Caixa'): Role
{
    return Role::create(['name' => $nome.uniqid().'#'.$businessId, 'business_id' => $businessId, 'guard_name' => 'web']);
}

function usrtPayload(int $roleId, array $extra = []): array
{
    return array_merge([
        'surname' => 'Sr', 'first_name' => 'Tier0'.uniqid(), 'last_name' => 'Teste',
        'email' => 'usrt'.uniqid().'@exemplo.test', 'role' => $roleId, 'is_active' => 'active',
    ], $extra);
}

test('Tier 0 store — função de outra empresa é recusada e nenhum usuário é gravado', function () {
    $alheio = usrtPapel($this->outro->id);
    $dados = usrtPayload($alheio->id);

    $this->post('/users', $dados)->assertForbidden();

    expect(\App\User::where('first_name', $dados['first_name'])->exists())->toBeFalse();
    // Contraprova: com a função da própria empresa o mesmo corpo grava (o 403 não é a rota quebrada).
    $meu = usrtPapel($this->business->id);
    $this->post('/users', usrtPayload($meu->id))->assertRedirect('/users')->assertSessionHas('status.success', 1);
});

test('Tier 0 store — quem não é admin não cria usuário Admin (a função que a tela já esconde dele)', function () {
    $admin = Role::firstOrCreate(
        ['name' => 'Admin#'.$this->business->id, 'guard_name' => 'web'],
        ['business_id' => $this->business->id]
    );
    expect($this->user->hasRole($admin->name))->toBeFalse();
    $dados = usrtPayload($admin->id);

    $this->post('/users', $dados)->assertForbidden();

    expect(\App\User::where('first_name', $dados['first_name'])->exists())->toBeFalse();
});

test('Tier 0 update — trocar para função de outra empresa é recusado e a função fica', function () {
    $meu = usrtPapel($this->business->id);
    $alheio = usrtPapel($this->outro->id);
    $dados = usrtPayload($meu->id);
    $this->post('/users', $dados)->assertSessionHas('status.success', 1);
    $u = \App\User::where('first_name', $dados['first_name'])->firstOrFail();

    $this->post("/users/{$u->id}", [
        '_method' => 'PUT', 'first_name' => $u->first_name, 'email' => $u->email, 'role' => $alheio->id,
        'is_active' => 'active', 'max_sales_discount_percent' => '',
    ])->assertForbidden();

    $u = \App\User::findOrFail($u->id);
    expect($u->hasRole($meu->name))->toBeTrue();
    expect($u->hasRole($alheio->name))->toBeFalse();
});

test('Tier 0 store/update — contato de outra empresa não entra nos contatos permitidos', function () {
    $meuContato = DB::table('contacts')->where('business_id', $this->business->id)->value('id');
    expect($meuContato)->not->toBeNull('o seed precisa de um contato no tenant 98 (anti-vácuo)');
    $contatoAlheio = DB::table('contacts')->insertGetId([
        'business_id' => $this->outro->id, 'type' => 'customer', 'name' => 'Contato 99 '.uniqid(),
        'created_by' => $this->user->id, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $papel = usrtPapel($this->business->id);
    $dados = usrtPayload($papel->id, ['selected_contacts' => 1, 'selected_contact_ids' => [$meuContato, $contatoAlheio]]);

    $this->post('/users', $dados)->assertSessionHas('status.success', 1);
    $u = \App\User::where('first_name', $dados['first_name'])->firstOrFail();
    expect($u->contactAccess()->pluck('contacts.id')->all())->toBe([(int) $meuContato]);

    $this->post("/users/{$u->id}", [
        '_method' => 'PUT', 'first_name' => $u->first_name, 'email' => $u->email, 'role' => $papel->id,
        'is_active' => 'active', 'max_sales_discount_percent' => '',
        'selected_contacts' => 1, 'selected_contact_ids' => [$contatoAlheio],
    ])->assertSessionHas('status.success', 1);
    expect($u->contactAccess()->pluck('contacts.id')->all())->toBe([]);
});

test('Tier 0 show — usuário de outra empresa responde 404', function () {
    $alheio = \App\User::factory()->create(['business_id' => $this->outro->id]);

    $this->get("/users/{$alheio->id}")->assertNotFound();
});
