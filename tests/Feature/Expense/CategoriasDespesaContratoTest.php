<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Contrato de /expense-categories na tela React (Financeiro/CategoriasDespesa/Index) — US-MWART-018, parte 2,
 * thread financeiro/13. A tela grava pelos MESMOS endpoints da Blade, então os casos valem para as duas.
 *
 * UC-CATD-01 `[T0]` — a lista nova mostra só as categorias do meu negócio.
 * UC-CATD-02        — criar grava no negócio da sessão; como subcategoria, grava o pai.
 * UC-CATD-03 `[T0]` — editar altera a minha categoria e não alcança a de outro negócio.
 * UC-CATD-04 `[T0]` — excluir leva as minhas subcategorias junto e não alcança outro negócio.
 * UC-CATD-05        — flag desligada, ou `?classico=1`, servem a Blade.
 *
 * Trio: resources/js/Pages/Financeiro/CategoriasDespesa/{Index.charter.md,Index.casos.md}
 * Tier 0 (ADR 0093 + ADR 0358): tenant 98 x business 2. NUNCA biz=4.
 * Lições da pilha de Despesas aplicadas aqui: sessão montada pelo SetSessionData (flush + actingAs) e
 * cabeçalhos POR CHAMADA (`withHeaders()` vaza para a requisição seguinte do mesmo teste).
 */
uses(DatabaseTransactions::class);

const CATD_BIZ_ALHEIO = 2;
const CATD_FLAG = 'useV2FinanceiroDespesas';
const CATD_AJAX = ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'];

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema UltimatePOS requer MySQL.');
    }
    if (! Schema::hasTable('expense_categories')) {
        $this->markTestSkipped('Tabela expense_categories ausente — rode com DB_CONNECTION=mysql.');
    }
    if (! DB::table('business')->where('id', CATD_BIZ_ALHEIO)->exists()) {
        $this->markTestSkipped('Sem business 2 no seed (pest-mysql-setup).');
    }

    $this->business = $this->seededTenant();
    $this->user = $this->usuarioComPermissoes(['expense.add', 'expense.edit'], $this->business);
    session()->flush(); // o SetSessionData monta a sessão (user, business, currency) como numa requisição real
    $this->actingAs($this->user);
    config(['feature-flags.forced_on' => CATD_FLAG]);
});

function catdCategoria(int $biz, string $nome, ?int $pai = null): int
{
    return DB::table('expense_categories')->insertGetId([
        'business_id' => $biz, 'name' => $nome, 'code' => 'C'.substr(md5($nome), 0, 6), 'parent_id' => $pai,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

function catdVivas(): \Illuminate\Database\Query\Builder
{
    return DB::table('expense_categories')->whereNull('deleted_at');
}

function catdInertiaVersion(): string
{
    try {
        return (string) (new \App\Http\Middleware\HandleInertiaRequests)->version(request());
    } catch (\Throwable $e) {
        return '';
    }
}

it('UC-CATD-01 · a lista nova mostra só as categorias do meu negócio', function () {
    $tag = 'CATD1'.uniqid();
    catdCategoria($this->business->id, "$tag-minha");
    catdCategoria(CATD_BIZ_ALHEIO, "$tag-alheia");

    $r = $this->get('/expense-categories', [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => catdInertiaVersion(),
        'X-Inertia-Partial-Data' => 'categorias',
        'X-Inertia-Partial-Component' => 'Financeiro/CategoriasDespesa/Index',
    ]);

    $r->assertOk()->assertJsonPath('component', 'Financeiro/CategoriasDespesa/Index');
    $nomes = collect($r->json('props.categorias'))->pluck('nome')->all();
    expect($nomes)->toContain("$tag-minha");        // controle positivo
    expect($nomes)->not->toContain("$tag-alheia");  // Tier 0
});

it('UC-CATD-02 · criar grava no negócio da sessão; como subcategoria, grava o pai', function () {
    $tag = 'CATD2'.uniqid();
    $pai = catdCategoria($this->business->id, "$tag-pai");

    $this->post('/expense-categories', ['name' => "$tag-nova", 'code' => 'X1'], CATD_AJAX)->assertOk()->assertJsonPath('success', true);
    $this->post('/expense-categories', ['name' => "$tag-sub", 'code' => 'X2', 'add_as_sub_cat' => 1, 'parent_id' => $pai], CATD_AJAX)
        ->assertOk()->assertJsonPath('success', true);

    $nova = catdVivas()->where('name', "$tag-nova")->first();
    $sub = catdVivas()->where('name', "$tag-sub")->first();
    expect((int) $nova->business_id)->toBe((int) $this->business->id);
    expect($nova->parent_id)->toBeNull();
    expect((int) $sub->business_id)->toBe((int) $this->business->id);
    expect((int) $sub->parent_id)->toBe($pai);
});

it('UC-CATD-03 · editar altera a minha categoria e não alcança a de outro negócio', function () {
    $tag = 'CATD3'.uniqid();
    $minha = catdCategoria($this->business->id, "$tag-minha");
    $alheia = catdCategoria(CATD_BIZ_ALHEIO, "$tag-alheia");

    $this->put("/expense-categories/{$minha}", ['name' => "$tag-renomeada", 'code' => 'R1'], CATD_AJAX)->assertOk()->assertJsonPath('success', true);
    $this->put("/expense-categories/{$alheia}", ['name' => "$tag-invadida", 'code' => 'R2'], CATD_AJAX)->assertOk()->assertJsonPath('success', false);

    expect(catdVivas()->where('id', $minha)->value('name'))->toBe("$tag-renomeada");  // controle positivo
    expect(catdVivas()->where('id', $alheia)->value('name'))->toBe("$tag-alheia");    // Tier 0
});

it('UC-CATD-04 · excluir leva as minhas subcategorias junto e não alcança outro negócio', function () {
    $tag = 'CATD4'.uniqid();
    $pai = catdCategoria($this->business->id, "$tag-pai");
    $sub = catdCategoria($this->business->id, "$tag-sub", $pai);
    // Categoria de OUTRO negócio apontando para o mesmo id de pai: só o filtro de business_id a protege.
    $alheiaFilha = catdCategoria(CATD_BIZ_ALHEIO, "$tag-alheia-filha", $pai);
    $alheia = catdCategoria(CATD_BIZ_ALHEIO, "$tag-alheia");

    $this->delete("/expense-categories/{$pai}", [], CATD_AJAX)->assertOk()->assertJsonPath('success', true);
    $this->delete("/expense-categories/{$alheia}", [], CATD_AJAX)->assertOk()->assertJsonPath('success', false);

    expect(catdVivas()->where('id', $pai)->exists())->toBeFalse();          // controle positivo
    expect(catdVivas()->where('id', $sub)->exists())->toBeFalse();          // a subcategoria vai junto
    expect(catdVivas()->where('id', $alheiaFilha)->exists())->toBeTrue();   // Tier 0
    expect(catdVivas()->where('id', $alheia)->exists())->toBeTrue();        // Tier 0
});

it('UC-CATD-05 · flag desligada ou ?classico=1 servem a Blade', function () {
    $this->get('/expense-categories?classico=1')->assertOk()->assertViewIs('expense_category.index');

    config(['feature-flags.forced_on' => '']);
    $this->mock(\App\Services\FeatureFlagService::class, fn ($mock) =>
        $mock->shouldReceive('isOn')->andReturn(false)); // todas as flags desligadas, inclusive a desta tela

    $this->get('/expense-categories')->assertOk()->assertViewIs('expense_category.index');
});
