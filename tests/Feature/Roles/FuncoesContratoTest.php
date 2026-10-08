<?php

declare(strict_types=1);
// Cobre UC-FUNC-01, UC-FUNC-02, UC-FUNC-03, UC-FUNC-04 (Funcoes/Index.casos.md).

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
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
