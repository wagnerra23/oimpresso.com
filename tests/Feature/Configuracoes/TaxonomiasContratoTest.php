<?php

declare(strict_types=1);

use App\Business;
use App\Category;
use App\Utils\ModuleUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia;

/**
 * Contrato da tela `/taxonomies?type=…` (listas dos módulos) — Onda 1 do RUNBOOK-taxonomias:
 * Inertia atrás da flag `useV2Taxonomias`, ligada aqui pelo override `feature-flags.forced_on`.
 *
 * Os UCs vêm do contrato, não do código:
 *   resources/js/Pages/Configuracoes/Taxonomias/Index.casos.md
 *
 * A declaração dos tipos (`ModuleUtil::getModuleData('addTaxonomies')`) é dublê: o teste fixa o que o módulo
 * declara para o usuário, que é a pré-condição do caso, e mede o que o controller faz com isso.
 * Tenant 98 (seededTenant, ADR 0358) e um negócio vizinho. NUNCA biz=4.
 *
 * @see app/Http/Controllers/TaxonomyController.php
 */
uses(DatabaseTransactions::class);

const TAX_TAG = '[tax-contrato]';

beforeEach(function () {
    if (! Schema::hasTable('categories') || ! Schema::hasColumn('users', 'essentials_department_id')) {
        $this->markTestSkipped('Schema UltimatePOS/Essentials ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }

    $this->business = $this->seededTenant();
    $this->user = $this->usuarioComPermissoes([], $this->business);
    $this->versaoInertia = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
    $this->actingAs($this->user);
    session(['user.business_id' => $this->business->id, 'user.id' => $this->user->id, 'business.id' => $this->business->id]);
    config(['feature-flags.forced_on' => 'useV2Taxonomias']);
    taxDeclara(['hrm_department' => true, 'hrm_designation' => true, 'source' => false]);
});

/** O que os módulos declaram para o usuário: tipo => declarado? (Departamento e Cargo no RH, Fonte no CRM). */
function taxDeclara(array $tipos): void
{
    $rh = [];
    foreach (['hrm_department' => 'Departamentos', 'hrm_designation' => 'Cargos'] as $tipo => $titulo) {
        $rh[$tipo] = ! empty($tipos[$tipo]) ? ['heading' => $titulo, 'taxonomy_label' => $titulo, 'navbar' => 'essentials::layouts.nav_hrm'] : [];
    }
    $crm = ['source' => ! empty($tipos['source']) ? ['heading' => 'Fontes', 'taxonomy_label' => 'Fonte', 'navbar' => 'crm::layouts.nav'] : []];

    $util = Mockery::mock(ModuleUtil::class)->makePartial();
    $util->shouldReceive('getModuleData')->with('addTaxonomies')->andReturn(['Essentials' => $rh, 'Crm' => $crm]);
    $util->shouldReceive('getModuleData')->withAnyArgs()->passthru();
    app()->instance(ModuleUtil::class, $util);
}

function taxCategoria(int $biz, string $tipo, string $nome): int
{
    return (int) DB::table('categories')->insertGetId([
        'business_id' => $biz, 'category_type' => $tipo, 'name' => $nome.' '.TAX_TAG, 'parent_id' => 0,
        'created_by' => test()->user->id, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function taxVizinho(): int
{
    $outro = Business::where('id', '!=', test()->business->id)->where('id', '!=', 4)->value('id');
    if (! $outro) {
        test()->markTestSkipped('Sem um segundo negócio no banco para o caso cross-tenant.');
    }

    return (int) $outro;
}

/** Props pedidas como o browser pede (X-Inertia + X-Requested-With), parcial em `$parcial`. */
function taxProps(string $query, string $parcial): array
{
    $r = test()->get('/taxonomies'.$query, [
        'X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest',
        'X-Inertia-Version' => (string) test()->versaoInertia,
        'X-Inertia-Partial-Data' => $parcial,
        'X-Inertia-Partial-Component' => 'Configuracoes/Taxonomias/Index',
    ]);
    $r->assertOk();

    return (array) $r->json('props');
}

it('UC-TAX-01 · tipo declarado pelo módulo abre a tela React; com ?classico=1, a Blade', function () {
    $this->get('/taxonomies?type=hrm_department')->assertOk()
        ->assertInertia(fn (AssertableInertia $p) => $p->component('Configuracoes/Taxonomias/Index', false)
            ->where('tipo', 'hrm_department')
            ->where('abas', [['tipo' => 'hrm_department', 'titulo' => 'Departamentos'], ['tipo' => 'hrm_designation', 'titulo' => 'Cargos']]));

    $this->get('/taxonomies?type=hrm_department&classico=1')->assertOk()->assertViewIs('taxonomy.index');
});

it('UC-TAX-02 · categoria de produto segue na Blade', function () {
    $user = $this->usuarioComPermissoes(['category.view'], $this->business);
    $this->actingAs($user);

    $this->get('/taxonomies?type=product')->assertOk()->assertViewIs('taxonomy.index');
});

it('UC-TAX-03 · a lista é só do meu negócio e traz quantos colaboradores usam cada item', function () {
    $meu = taxCategoria($this->business->id, 'hrm_department', 'Produção');
    taxCategoria(taxVizinho(), 'hrm_department', 'Departamento da vizinha');
    DB::table('users')->where('id', $this->user->id)->update(['essentials_department_id' => $meu]);

    $itens = collect(taxProps('?type=hrm_department', 'itens')['itens'] ?? [])
        ->filter(fn ($i) => str_contains((string) $i['nome'], TAX_TAG));

    expect($itens->pluck('nome')->all())->toBe(['Produção '.TAX_TAG]);
    expect($itens->first()['em_uso'])->toBe(1);
});

it('UC-TAX-04 · item em uso não se exclui, e continua no banco', function () {
    $id = taxCategoria($this->business->id, 'hrm_department', 'Em uso');
    DB::table('users')->where('id', $this->user->id)->update(['essentials_department_id' => $id]);

    $this->deleteJson('/taxonomies/'.$id, [], ['X-Requested-With' => 'XMLHttpRequest'])
        ->assertOk()
        ->assertJson(['success' => false, 'em_uso' => 1]);

    expect(Category::whereKey($id)->exists())->toBeTrue();
});

it('UC-TAX-05 · item sem uso se exclui', function () {
    $id = taxCategoria($this->business->id, 'hrm_department', 'Sem uso');

    $this->deleteJson('/taxonomies/'.$id, [], ['X-Requested-With' => 'XMLHttpRequest'])
        ->assertOk()
        ->assertJson(['success' => true]);

    expect(Category::whereKey($id)->exists())->toBeFalse();
});

it('UC-TAX-06 · tipo que o módulo não declara para mim segue na Blade', function () {
    taxDeclara(['hrm_department' => true, 'source' => false]);

    $this->get('/taxonomies?type=source')->assertOk()->assertViewIs('taxonomy.index');
});
