<?php

declare(strict_types=1);
// Cobre UC-FUNC-01, UC-FUNC-02, UC-FUNC-03, UC-FUNC-04, UC-FUNC-05, UC-FUNC-06, UC-FUNC-07, UC-FUNC-08 (Funcoes/Index.casos.md).

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Thread sistema/playbook/02, F3 — /roles em Inertia atrás da flag useV2SistemaFuncoes.
 *
 * A flag é ligada pelo override de ambiente (`feature-flags.forced_on`, inerte em produção), não
 * pelo GrowthBook. store/update/destroy não mudaram: o comportamento deles é do FuncoesBaselineTest.
 * Tenant de teste 98 x cliente fictício 99 (ADR 0358).
 */

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasTable('roles') || ! Schema::hasColumn('roles', 'business_id')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }

    $this->business = $this->seededTenant();
    $this->user = $this->usuarioComPermissoes(['roles.view', 'roles.update'], $this->business);
    $this->versaoInertia = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
    $this->actingAs($this->user);
    session(['user.business_id' => $this->business->id, 'user.id' => $this->user->id, 'business.id' => $this->business->id]);
});

function funcLigarFlag(): void
{
    config(['feature-flags.forced_on' => 'useV2SistemaFuncoes']);
}

function funcPapel(string $nome, int $businessId, array $campos = []): Role
{
    return Role::create(array_merge(['name' => $nome.'#'.$businessId, 'business_id' => $businessId, 'guard_name' => 'web'], $campos));
}

function funcListaAdiada($teste): \Illuminate\Support\Collection
{
    $r = $teste->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => $teste->versaoInertia,
        'X-Inertia-Partial-Component' => 'Funcoes/Index',
        'X-Inertia-Partial-Data' => 'funcoes',
    ])->get('/roles');
    $r->assertOk();

    return collect($r->json('props.funcoes') ?? [])->keyBy('id');
}

test('UC-FUNC-01 com a flag ligada, GET /roles renderiza Inertia (não a DataTable) com o que o usuário pode', function () {
    funcLigarFlag();

    // X-Requested-With junto do X-Inertia, como o browser manda: o ramo ajax() antigo engolia isso.
    $r = $this->withHeaders([
        'X-Inertia' => 'true', 'X-Inertia-Version' => $this->versaoInertia, 'X-Requested-With' => 'XMLHttpRequest',
    ])->get('/roles');

    $r->assertOk();
    expect($r->json('component'))->toBe('Funcoes/Index');
    expect($r->json('props.pode'))->toBe(['criar' => false, 'editar' => true, 'excluir' => false]);
});

test('UC-FUNC-02 com a flag desligada, GET /roles segue na Blade', function () {
    config(['feature-flags.forced_on' => '']);

    $this->get('/roles')->assertOk()->assertViewIs('role.index');
});

test('UC-FUNC-03 Tier 0 — a lista adiada traz só as funções do negócio da sessão, com os usuários de cada uma', function () {
    funcLigarFlag();
    $outro = $this->seededSupportClientTenant();
    $minha = funcPapel('Balcao'.uniqid(), $this->business->id);
    $alheia = funcPapel('Alheia'.uniqid(), $outro->id);
    User::factory()->create(['business_id' => $this->business->id])->assignRole($minha);
    User::factory()->create(['business_id' => $outro->id])->assignRole($alheia);

    $porId = funcListaAdiada($this);

    expect($porId->has($minha->id))->toBeTrue();
    expect($porId->has($alheia->id))->toBeFalse();
    expect($porId[$minha->id]['nome'])->toBe(str_replace('#'.$this->business->id, '', $minha->name));
    expect($porId[$minha->id]['usuarios'])->toBe(1);
    expect($porId->pluck('nome')->filter(fn ($n) => str_contains((string) $n, '#'))->all())->toBe([]);
});

test('UC-FUNC-04 função padrão vem sem editar/excluir; a comum vem editável', function () {
    funcLigarFlag();
    $padrao = funcPapel('Padrao'.uniqid(), $this->business->id, ['is_default' => 1]);
    $comum = funcPapel('Comum'.uniqid(), $this->business->id);

    $porId = funcListaAdiada($this);

    expect($porId[$padrao->id]['padrao'])->toBeTrue();
    expect($porId[$padrao->id]['editavel'])->toBeFalse();
    expect($porId[$comum->id]['editavel'])->toBeTrue();
});

// ── F3-2: drawer de cadastrar/editar ────────────────────────────────────────────────────────

function funcEditor($teste, string $funcao): \Illuminate\Testing\TestResponse
{
    return $teste->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => $teste->versaoInertia,
        'X-Inertia-Partial-Component' => 'Funcoes/Index',
        'X-Inertia-Partial-Data' => 'editor',
    ])->get('/roles?funcao='.$funcao);
}

/** @return \Illuminate\Support\Collection<int, array<string, mixed>> */
function funcItens(array $editor): \Illuminate\Support\Collection
{
    return collect($editor['grupos'])->flatMap(fn ($g) => $g['itens']);
}

/** O corpo que o drawer manda (_components/funcao-form.ts::corpoDoFormulario): o que o <form> da Blade mandaria. */
function funcCorpoDoDrawer(array $editor): array
{
    $corpo = ['name' => $editor['nome'], 'permissions' => [], 'spg_permissions' => [], 'radio_option' => []];
    foreach (funcItens($editor)->where('marcado', true) as $i) {
        if ($i['campo'] === 'permissions[]') {
            $corpo['permissions'][] = $i['valor'];
        } elseif ($i['campo'] === 'spg_permissions[]') {
            $corpo['spg_permissions'][] = $i['valor'];
        } elseif ($i['campo'] === 'is_service_staff') {
            $corpo['is_service_staff'] = 1;
        } elseif (preg_match('/^radio_option\[(.+)\]$/', $i['campo'], $m) === 1) {
            $corpo['radio_option'][$m[1]] = $i['valor'];
        }
    }

    return $corpo;
}

function funcComPermissoes(Role $papel, array $nomes): Role
{
    foreach ($nomes as $n) {
        Permission::findOrCreate($n, 'web');
    }
    $papel->givePermissionTo($nomes);

    return $papel->fresh('permissions');
}

test('UC-FUNC-05 o editor traz os controles da Blade em blocos, com o nome sem #negócio e o que a função já tem marcado', function () {
    funcLigarFlag();
    $papel = funcComPermissoes(funcPapel('Caixa'.uniqid(), $this->business->id), ['customer.update', 'supplier.view_own']);

    $r = funcEditor($this, (string) $papel->id);
    $r->assertOk();
    $editor = $r->json('props.editor');

    expect($editor['id'])->toBe($papel->id);
    expect($editor['nome'])->toBe(str_replace('#'.$this->business->id, '', $papel->name));
    expect(count($editor['grupos']))->toBeGreaterThan(10);

    $itens = funcItens($editor)->keyBy(fn ($i) => $i['campo'].'|'.$i['valor']);
    expect($itens['permissions[]|customer.update']['marcado'])->toBeTrue();
    expect($itens['permissions[]|customer.delete']['marcado'])->toBeFalse();
    expect($itens['radio_option[supplier_view]|supplier.view_own']['tipo'])->toBe('radio');
    expect($itens['radio_option[supplier_view]|supplier.view_own']['marcado'])->toBeTrue();
    expect($itens['radio_option[supplier_view]|supplier.view']['marcado'])->toBeFalse();
    // Rótulo vem do <label> da Blade, não do nome técnico.
    expect($itens['permissions[]|customer.update']['rotulo'])->not->toBe('customer.update');
    expect($itens['permissions[]|customer.update']['rotulo'])->not->toBe('');
});

test('UC-FUNC-06 Tier 0 — função de outro negócio não abre no editor (404) e nada dela vaza', function () {
    funcLigarFlag();
    $outro = $this->seededSupportClientTenant();
    $alheia = funcPapel('Segredo'.uniqid(), $outro->id);

    $r = funcEditor($this, (string) $alheia->id);

    $r->assertNotFound();
    expect($r->getContent())->not->toContain(str_replace('#'.$outro->id, '', $alheia->name));

    // Contraprova positiva: a minha abre.
    $minha = funcPapel('Minha'.uniqid(), $this->business->id);
    funcEditor($this, (string) $minha->id)->assertOk();
});

test('UC-FUNC-07 salvar pelo drawer sem mexer em nada não muda as permissões da função', function () {
    funcLigarFlag();
    $antes = ['customer.update', 'customer.view_own', 'sell.view', 'customer_with_no_sell_one_month'];
    $papel = funcComPermissoes(funcPapel('Estavel'.uniqid(), $this->business->id), $antes);

    $editor = funcEditor($this, (string) $papel->id)->json('props.editor');
    $this->put("/roles/{$papel->id}", funcCorpoDoDrawer($editor))->assertRedirect('/roles');

    $papel->refresh();
    expect($papel->permissions->pluck('name')->sort()->values()->all())->toBe(collect($antes)->sort()->values()->all());
    expect($papel->name)->toBe($editor['nome'].'#'.$this->business->id);

    // E a mudança vai: desmarcar uma no corpo revoga só ela.
    $corpo = funcCorpoDoDrawer($editor);
    $corpo['permissions'] = array_values(array_diff($corpo['permissions'], ['sell.view']));
    $this->put("/roles/{$papel->id}", $corpo)->assertRedirect('/roles');
    expect($papel->fresh('permissions')->permissions->pluck('name')->all())->not->toContain('sell.view');
    expect($papel->fresh('permissions')->permissions->pluck('name')->all())->toContain('customer.update');
});

test('UC-FUNC-08 nova função abre vazia com roles.create; sem ela, 403; função padrão não abre', function () {
    funcLigarFlag();

    // O usuário do beforeEach tem roles.view + roles.update, sem roles.create.
    funcEditor($this, 'nova')->assertForbidden();

    $padrao = funcPapel('Padrao'.uniqid(), $this->business->id, ['is_default' => 1]);
    funcEditor($this, (string) $padrao->id)->assertForbidden();

    $criador = $this->usuarioComPermissoes(['roles.view', 'roles.create'], $this->business);
    $this->actingAs($criador);
    session(['user.business_id' => $this->business->id, 'user.id' => $criador->id, 'business.id' => $this->business->id]);

    $r = funcEditor($this, 'nova');
    $r->assertOk();
    expect($r->json('props.editor.id'))->toBeNull();
    expect($r->json('props.editor.nome'))->toBe('');
    expect(funcItens($r->json('props.editor'))->where('valor', 'customer.update')->count())->toBe(1);
});
