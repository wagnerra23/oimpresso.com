<?php

declare(strict_types=1);

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * R-MANU-002 a R-MANU-005 (SPEC.md §4) — autorização Spatie por recurso do módulo
 * Manufacturing. Mesmo padrão de Modules/Ponto/Tests/Feature/SpatiePermissionsTest.php.
 *
 * Regra base:
 *   Dado que um usuário NÃO tem a permissão `manufacturing.{recurso}`
 *   Quando ele tenta acessar a funcionalidade correspondente
 *   Então recebe 403; e com a permissão, a mesma rota responde 200 (âncora positiva).
 *
 * O controller faz `abort(403)` se faltar (superadmin OU pacote `manufacturing_module`) OU a
 * permissão do recurso. Até 2026-11-11 este arquivo usava "o primeiro usuário da empresa" e
 * aceitava qualquer status != 200 sem permissão e < 500 com: um 403 do pacote passava pelos dois
 * lados, e o caso com permissão pulava quando o pacote barrava. Agora o usuário é criado aqui e
 * entra na lista de administradores, o que resolve o lado do pacote (Gate `superadmin`) sem dar a
 * permissão do recurso: o `Gate::before` só libera `superadmin`, `backup`, `manage_modules` e as
 * permissões de plataforma (AuthServiceProvider). Assim o 403 só pode vir da permissão testada.
 *
 * R-MANU-001 (isolamento multi-tenant) é do MultiTenantIsolationTest e dos testes 98×99.
 *
 * Tenant 98 (fictício, ADR 0358). NUNCA biz=4. Tudo dentro de transação desfeita.
 *
 * @covers-us US-MANU-001
 *
 * @see memory/requisitos/Manufacturing/SPEC.md R-MANU-002..005
 */
function mfgPermUsuario(string $sufixo): User
{
    if (DB::connection()->getDriverName() === 'sqlite') {
        test()->markTestSkipped('SQLite-incompatível: depende do schema MySQL UltimatePOS (business/users/roles).');
    }

    $biz = 98;
    if (! DB::table('business')->where('id', $biz)->exists()) {
        $dono = (int) DB::table('users')->min('id');
        (new App\Business)->forceFill(['id' => $biz, 'name' => 'Tenant fictício mfg 98', 'currency_id' => 1, 'owner_id' => $dono])->save();
    }

    $username = 'mfg_perm_'.$sufixo;
    $user = User::firstOrCreate(['username' => $username], [
        'email' => $username.'@test.local', 'password' => bcrypt('secret'),
        'business_id' => $biz, 'first_name' => 'Permissao', 'last_name' => 'Fabricacao',
        'user_type' => 'user', 'allow_login' => 1,
    ]);
    config(['constants.administrator_usernames' => $username]);

    return $user;
}

function mfgPermGet(User $user, string $url)
{
    $sessao = ['currency' => ['code' => 'BRL', 'symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ',']];

    return test()->actingAs($user)->withSession($sessao)->get($url);
}

dataset('manufacturing_permission_routes', [
    'R-MANU-002 manufacturing.access_recipe (GET /manufacturing/recipe)'         => ['manufacturing.access_recipe', '/manufacturing/recipe'],
    // `recipe/create` saiu no cutover de 2026-10-27; a busca da janela "Nova receita" é a rota
    // GET que hoje exige `add_recipe` (o `store()` também exige, via StoreRecipeRequest).
    'R-MANU-003 manufacturing.add_recipe (GET /manufacturing/nova-receita/produtos)' => ['manufacturing.add_recipe', '/manufacturing/nova-receita/produtos'],
    'R-MANU-005 manufacturing.access_production (GET /manufacturing/production)' => ['manufacturing.access_production', '/manufacturing/production'],
]);

it('usuário SEM a permissão recebe 403 na rota protegida', function (string $permission, string $url) {
    $user = mfgPermUsuario('sem');
    Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);

    expect($user->fresh()->can($permission))->toBeFalse();

    mfgPermGet($user, $url)->assertForbidden();
})->with('manufacturing_permission_routes');

it('usuário COM a permissão recebe 200 na mesma rota', function (string $permission, string $url) {
    $user = mfgPermUsuario('com');
    $user->givePermissionTo(Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']));

    mfgPermGet($user, $url)->assertOk();
})->with('manufacturing_permission_routes');

// R-MANU-004 · manufacturing.edit_recipe — achado, não suposição: Routes/web.php faz
// `Route::resource('/recipe', ...)->except('edit', 'update')`, e nenhuma outra rota do módulo
// referencia UpdateRecipeRequest. O gate em UpdateRecipeRequest::authorize() é código real — só
// não tem porta HTTP que o alcance hoje. Testar via rota seria inventar um caminho que não existe
// (§5 2026-06-05, teste tautológico); o teste honesto é o par permission+can(), mais uma trava
// que avisa se a rota reaparecer.
it('R-MANU-004 manufacturing.edit_recipe é permissão Spatie real, hoje sem rota que a alcance', function () {
    $user = mfgPermUsuario('edit');

    $permission = 'manufacturing.edit_recipe';
    Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);

    expect($user->fresh()->can($permission))->toBeFalse();

    $user->givePermissionTo($permission);
    expect($user->fresh()->can($permission))->toBeTrue();

    // Se uma rota PUT/PATCH /manufacturing/recipe/{id} reaparecer no registry, este teste
    // quebra — sinal de que ele precisa virar um assertForbidden()/assertOk() real por HTTP.
    $rotaUpdate = collect(Route::getRoutes()->getRoutes())
        ->first(fn ($r) => str_starts_with($r->uri(), 'manufacturing/recipe/')
            && (in_array('PUT', $r->methods(), true) || in_array('PATCH', $r->methods(), true)));

    expect($rotaUpdate)->toBeNull(
        'Uma rota PUT/PATCH /manufacturing/recipe/{id} apareceu no registry — R-MANU-004 precisa de teste HTTP real agora, não mais deste par permission+can().'
    );
});
