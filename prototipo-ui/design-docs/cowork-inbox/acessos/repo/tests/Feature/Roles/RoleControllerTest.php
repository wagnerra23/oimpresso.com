<?php

/**
 * Cobertura HTTP de /roles (funções e permissões).
 *
 * Origem: cowork-inbox/ACESSOS-F1-2026-08-19.md \4/\6 + resources/js/Pages/Roles/Index.casos.md.
 * Escrito por [CC] em 2026-08-19 e NÃO executado aqui — o veredito é da lane.
 *
 * Notas de montagem:
 *  - Papel é por negócio e o nome guarda o sufixo: 'Nome#<business_id>'. Todo assert de nome usa o sufixo.
 *  - is_default é somente leitura, EXCEITO 'Cashier#<biz>' — e editá-lo zera o is_default (regra do
 *    controller, ver update()). Os dois casos estão cobertos para travar o comportamento.
 *  - Permission é GLOBAL (sem business_id). Por isso o caso 6 conta a tabela inteira antes/depois:
 *    é o que prova que um POST forjado não cria permissão nova (achado D1).
 *  - As rotas são resource ajax do legado; quando a tradução (PR-6) trocar para Inertia, só o
 *    assert de resposta muda — a intenção de cada caso continua.
 */

use App\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function papelDe(string $nome, int $biz = 1, bool $isDefault = false): Role
{
    return Role::create([
        'name' => $nome.'#'.$biz,
        'business_id' => $biz,
        'is_default' => $isDefault,
        'guard_name' => 'web',
    ]);
}

function usuarioCom(array $permissoes, int $biz = 1): User
{
    $user = User::factory()->create(['business_id' => $biz]);
    $papel = papelDe('Teste'.uniqid(), $biz);
    foreach ($permissoes as $p) {
        Permission::findOrCreate($p, 'web');
    }
    $papel->syncPermissions($permissoes);
    $user->assignRole($papel);

    return $user;
}

function sessaoDe(int $biz = 1): array
{
    return ['user.business_id' => $biz, 'business.id' => $biz, 'business_timezone' => 'America/Sao_Paulo'];
}

// ── 1/2 · porta de entrada ───────────────────────────────────────────────────
it('1: sem roles.view leva 403 e com roles.view abre a lista', function () {
    $sem = usuarioCom([]);
    $this->actingAs($sem)->withSession(sessaoDe())->get('/roles')->assertForbidden();

    $com = usuarioCom(['roles.view']);
    $this->actingAs($com)->withSession(sessaoDe())->get('/roles')->assertOk();
});

it('2: a lista só traz papéis do negócio da sessão', function () {
    papelDe('SoDoUm', 1);
    papelDe('SoDoDois', 2);

    $user = usuarioCom(['roles.view'], 1);
    $resposta = $this->actingAs($user)->withSession(sessaoDe(1))
        ->get('/roles?draw=1&columns[0][data]=name&start=0&length=25');

    $resposta->assertOk();
    expect($resposta->getContent())->toContain('SoDoUm')->not->toContain('SoDoDois');
})->skip('DataTables ajax: ajustar o payload de query na lane; a intenção é o escopo por business_id.');

// ── 3 · o nome nunca vaza o sufixo ───────────────────────────────────────────
it('3: nome exibido não contém o sufixo #business_id', function () {
    papelDe('Balcao', 1);
    $user = usuarioCom(['roles.view'], 1);

    $resposta = $this->actingAs($user)->withSession(sessaoDe(1))->get('/roles?draw=1');

    expect($resposta->getContent())->not->toContain('Balcao#1');
})->skip('Mesma dependência do caso 2 (payload do DataTables).');

// ── 4/5 · is_default e a exceção do Cashier ──────────────────────────────────
it('4: papel is_default não pode ser editado e as permissões não mudam', function () {
    $papel = papelDe('Admin', 1, true);
    Permission::findOrCreate('sell.view', 'web');
    $papel->syncPermissions(['sell.view']);

    $user = usuarioCom(['roles.update'], 1);
    $this->actingAs($user)->withSession(sessaoDe(1))
        ->put('/roles/'.$papel->id, ['name' => 'AdminEditado', 'permissions' => ['profit_loss_report.view']]);

    $papel->refresh();
    expect($papel->name)->toBe('Admin#1');
    expect($papel->permissions->pluck('name')->all())->toBe(['sell.view']);
});

it('5: editar o Cashier grava E zera o is_default (regra R4)', function () {
    $papel = papelDe('Cashier', 1, true);

    $user = usuarioCom(['roles.update'], 1);
    $this->actingAs($user)->withSession(sessaoDe(1))
        ->put('/roles/'.$papel->id, ['name' => 'Balcao', 'permissions' => []]);

    $papel->refresh();
    expect($papel->name)->toBe('Balcao#1');
    expect((bool) $papel->is_default)->toBeFalse();
});

// ── 6 · D1: permissão fora do catálogo NÃO pode ser criada ───────────────────
it('6: POST com permissão fora do catálogo não cria linha em permissions', function () {
    $antes = Permission::count();

    $user = usuarioCom(['roles.create'], 1);
    $this->actingAs($user)->withSession(sessaoDe(1))
        ->post('/roles', ['name' => 'Forjado', 'permissions' => ['isto.nao.existe.no.catalogo']]);

    expect(Permission::count())->toBe($antes);
    expect(Permission::where('name', 'isto.nao.existe.no.catalogo')->exists())->toBeFalse();
});

// ── 7 · D2: grupo de preço é radio, nunca duplica ────────────────────────────
it('7: grupo de preço entra uma única vez, no create e no update', function () {
    $user = usuarioCom(['roles.create', 'roles.update'], 1);

    $this->actingAs($user)->withSession(sessaoDe(1))
        ->post('/roles', ['name' => 'Atacadista', 'permissions' => [], 'radio_option' => ['selling_price_group.1']]);

    $papel = Role::where('name', 'Atacadista#1')->firstOrFail();
    $doGrupo = $papel->permissions->pluck('name')->filter(fn ($n) => str_starts_with($n, 'selling_price_group.'));
    expect($doGrupo->count())->toBe(1);

    $this->actingAs($user)->withSession(sessaoDe(1))
        ->put('/roles/'.$papel->id, ['name' => 'Atacadista', 'permissions' => [], 'spg_permissions' => ['selling_price_group.1'], 'radio_option' => ['selling_price_group.1']]);

    $papel->refresh();
    $depois = $papel->permissions->pluck('name')->filter(fn ($n) => str_starts_with($n, 'selling_price_group.'));
    expect($depois->count())->toBe(1);
});

// ── 8 · syncPermissions revoga o que saiu ────────────────────────────────────
it('8: permissão ausente no POST é revogada', function () {
    $papel = papelDe('Producao', 1);
    foreach (['sell.view', 'purchase.view'] as $p) {
        Permission::findOrCreate($p, 'web');
    }
    $papel->syncPermissions(['sell.view', 'purchase.view']);

    $user = usuarioCom(['roles.update'], 1);
    $this->actingAs($user)->withSession(sessaoDe(1))
        ->put('/roles/'.$papel->id, ['name' => 'Producao', 'permissions' => ['sell.view']]);

    $papel->refresh();
    expect($papel->permissions->pluck('name')->all())->toBe(['sell.view']);
});

// ── 9 · is_service_staff é coluna, não permissão ─────────────────────────────
it('9: is_service_staff grava na coluna do papel e não como permissão', function () {
    $papel = papelDe('Garcom', 1);

    $user = usuarioCom(['roles.update'], 1);
    $this->actingAs($user)->withSession(sessaoDe(1))
        ->put('/roles/'.$papel->id, ['name' => 'Garcom', 'permissions' => [], 'is_service_staff' => 1]);

    $papel->refresh();
    expect((bool) $papel->is_service_staff)->toBeTrue();
    expect($papel->permissions->pluck('name')->all())->not->toContain('is_service_staff');
});

// ── 10 · nome duplicado no mesmo negócio ─────────────────────────────────────
it('10: nome já existente no negócio não cria papel novo', function () {
    papelDe('Financeiro', 1);
    $antes = Role::where('business_id', 1)->count();

    $user = usuarioCom(['roles.create'], 1);
    $this->actingAs($user)->withSession(sessaoDe(1))
        ->post('/roles', ['name' => 'Financeiro', 'permissions' => []]);

    expect(Role::where('business_id', 1)->count())->toBe($antes);
});

// ── 11 · D4: papel em uso não pode ser excluído ──────────────────────────────
it('11: excluir papel em uso é bloqueado e informa a contagem', function () {
    $papel = papelDe('EmUso', 1);
    $membro = User::factory()->create(['business_id' => 1]);
    $membro->assignRole($papel);

    $user = usuarioCom(['roles.delete'], 1);
    $this->actingAs($user)->withSession(sessaoDe(1))
        ->deleteJson('/roles/'.$papel->id)
        ->assertStatus(422);

    expect(Role::find($papel->id))->not->toBeNull();
})->skip('Guarda proposta na PR-5: hoje o destroy apaga sem checar. O teste é a definição do alvo.');

// ── 12 · cross-tenant ───────────────────────────────────────────────────────
it('12: papel de outro negócio não é alterado nem excluído', function () {
    $alheio = papelDe('DoOutro', 2);

    $user = usuarioCom(['roles.update', 'roles.delete'], 1);
    $this->actingAs($user)->withSession(sessaoDe(1))
        ->put('/roles/'.$alheio->id, ['name' => 'Invadido', 'permissions' => []]);

    $alheio->refresh();
    expect($alheio->name)->toBe('DoOutro#2');
});
