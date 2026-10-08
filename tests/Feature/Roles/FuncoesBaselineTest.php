<?php

declare(strict_types=1);

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

/**
 * Baseline F2 (MWART, ADR 0104) de /roles — o comportamento da Blade ANTES da tela React.
 *
 * Thread sistema/playbook/02. Trava o que index/store/edit/update/destroy fazem hoje, para a F3
 * (ramo Inertia atrás da flag useV2SistemaFuncoes) não mudar nada disso sem um teste cair.
 * Mapa campo-a-campo: memory/requisitos/User/funcoes-parity.md.
 *
 * Tenant de teste 98 x cliente fictício 99 (ADR 0358). Toda prova de "não alcançou o alheio" vem
 * com a contraprova positiva no próprio negócio: sem ela, um 403 ou um erro genérico passaria por
 * isolamento. Papel e permissão são Tier 0 (ADR 0093) — o Role é o Spatie puro, sem global scope.
 */

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasTable('roles') || ! Schema::hasColumn('roles', 'business_id')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }

    $this->business = $this->seededTenant();
    $this->outro = $this->seededSupportClientTenant();
    $this->user = $this->usuarioComPermissoes(['roles.view', 'roles.create', 'roles.update', 'roles.delete'], $this->business);
    $this->actingAs($this->user);
    session(['user.business_id' => $this->business->id, 'user.id' => $this->user->id, 'business.id' => $this->business->id]);
    $this->ajax = ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'];
});

function funcBase(string $nome, int $businessId, array $campos = []): Role
{
    return Role::create(array_merge(['name' => $nome.'#'.$businessId, 'business_id' => $businessId, 'guard_name' => 'web'], $campos));
}

test('baseline: a lista traz só os papéis do negócio da sessão, sem o sufixo #negócio', function () {
    $meu = funcBase('Balcao'.uniqid(), $this->business->id);
    $alheio = funcBase('Alheio'.uniqid(), $this->outro->id);

    $r = $this->withHeaders($this->ajax)->get('/roles');
    $r->assertOk();
    $nomes = collect($r->json('data'))->pluck(0);

    expect($nomes)->toContain(str_replace('#'.$this->business->id, '', $meu->name));
    expect($nomes)->not->toContain(str_replace('#'.$this->outro->id, '', $alheio->name));
    expect($nomes->filter(fn ($n) => str_contains((string) $n, '#'))->all())->toBe([]);
});

test('baseline: papel padrão não tem editar/excluir na lista; papel comum tem os dois', function () {
    $padrao = funcBase('Padrao'.uniqid(), $this->business->id, ['is_default' => 1]);
    $comum = funcBase('Comum'.uniqid(), $this->business->id);

    $linhas = collect($this->withHeaders($this->ajax)->get('/roles')->json('data'))->keyBy(0);

    expect((string) $linhas[str_replace('#'.$this->business->id, '', $padrao->name)][1])->toBe('');
    $acoes = (string) $linhas[str_replace('#'.$this->business->id, '', $comum->name)][1];
    expect($acoes)->toContain("/roles/{$comum->id}/edit");
    expect($acoes)->toContain('delete_role_button');
});

test('baseline: cadastrar grava Nome#negócio, junta grupo de preço e escopo, e descarta permissão fora do catálogo', function () {
    $nome = 'Caixa'.uniqid();
    $this->post('/roles', [
        'name' => $nome, 'is_service_staff' => 1,
        'permissions' => ['customer.create', 'permissao.inventada'],
        'spg_permissions' => ['selling_price_group.7'],
        'radio_option' => ['customer_view' => 'customer.view_own'],
    ])->assertRedirect('/roles');

    $papel = Role::where('name', $nome.'#'.$this->business->id)->first();
    expect($papel)->not->toBeNull();
    expect((int) $papel->business_id)->toBe((int) $this->business->id);
    expect((int) $papel->is_service_staff)->toBe(1);
    expect($papel->permissions->pluck('name')->sort()->values()->all())
        ->toBe(['customer.create', 'customer.view_own', 'selling_price_group.7']);
    expect(DB::table('permissions')->where('name', 'permissao.inventada')->exists())->toBeFalse();
});

test('baseline: nome repetido no mesmo negócio não cria outro papel', function () {
    $nome = 'Repetido'.uniqid();
    funcBase($nome, $this->business->id);

    $this->post('/roles', ['name' => $nome, 'permissions' => []])->assertRedirect('/roles');

    expect(Role::where('name', $nome.'#'.$this->business->id)->count())->toBe(1);
});

test('baseline: o formulário de edição abre o papel do negócio e não abre o de outro', function () {
    $meu = funcBase('Editavel'.uniqid(), $this->business->id);
    $alheio = funcBase('Segredo'.uniqid(), $this->outro->id);

    $this->get("/roles/{$meu->id}/edit")->assertOk()->assertViewIs('role.edit');

    // Hoje edit() usa find() e o null quebra no foreach: não é 200 e não mostra o papel alheio.
    // (achado da F1, parity §4: deveria ser 404 — a F3 não muda isto sem decisão.)
    $r = $this->get("/roles/{$alheio->id}/edit");
    expect($r->status())->not->toBe(200);
    expect($r->getContent())->not->toContain(str_replace('#'.$this->outro->id, '', $alheio->name));
});

test('baseline: editar altera o papel do negócio e não alcança o de outro', function () {
    $meu = funcBase('Antes'.uniqid(), $this->business->id);
    $alheio = funcBase('Intocado'.uniqid(), $this->outro->id);
    $nomeAlheio = $alheio->name;

    $novo = 'Depois'.uniqid();
    $this->put("/roles/{$meu->id}", ['name' => $novo, 'permissions' => ['customer.update']])->assertRedirect('/roles');
    $meu->refresh();
    expect($meu->name)->toBe($novo.'#'.$this->business->id);
    expect($meu->permissions->pluck('name')->all())->toContain('customer.update');

    $this->put("/roles/{$alheio->id}", ['name' => 'Invadido', 'permissions' => ['customer.update']])->assertRedirect('/roles');
    $alheio->refresh();
    expect($alheio->name)->toBe($nomeAlheio);
    expect((int) $alheio->business_id)->toBe((int) $this->outro->id);
    expect($alheio->permissions->pluck('name')->all())->toBe([]);
});

test('baseline: excluir não alcança o papel de outro, recusa papel em uso e papel padrão, e apaga o livre', function () {
    $alheio = funcBase('NaoApagar'.uniqid(), $this->outro->id);
    $emUso = funcBase('EmUso'.uniqid(), $this->business->id);
    User::factory()->create(['business_id' => $this->business->id])->assignRole($emUso);
    $padrao = funcBase('Padrao'.uniqid(), $this->business->id, ['is_default' => 1]);
    $livre = funcBase('Livre'.uniqid(), $this->business->id);

    $r = $this->withHeaders($this->ajax)->delete("/roles/{$alheio->id}");
    $r->assertOk();
    expect($r->json('success'))->toBeFalse();
    expect(Role::find($alheio->id))->not->toBeNull();

    $this->withHeaders($this->ajax)->delete("/roles/{$emUso->id}")->assertStatus(422);
    expect(Role::find($emUso->id))->not->toBeNull();

    $r = $this->withHeaders($this->ajax)->delete("/roles/{$padrao->id}");
    expect((bool) $r->json('success'))->toBeFalse();
    expect(Role::find($padrao->id))->not->toBeNull();

    $r = $this->withHeaders($this->ajax)->delete("/roles/{$livre->id}");
    expect($r->json('success'))->toBeTrue();
    expect(Role::find($livre->id))->toBeNull();
});

test('baseline: sem as permissões de papel a lista, o cadastro, a edição e a exclusão devolvem 403', function () {
    $meu = funcBase('Protegido'.uniqid(), $this->business->id);
    $semPermissao = $this->usuarioComPermissoes([], $this->business);
    $this->actingAs($semPermissao);

    $this->get('/roles')->assertForbidden();
    $this->post('/roles', ['name' => 'NaoGrava'.uniqid()])->assertForbidden();
    $this->put("/roles/{$meu->id}", ['name' => 'NaoMuda'])->assertForbidden();
    $this->withHeaders($this->ajax)->delete("/roles/{$meu->id}")->assertForbidden();
    expect(Role::find($meu->id)->name)->toBe($meu->name);
});
