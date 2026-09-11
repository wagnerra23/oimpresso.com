<?php

declare(strict_types=1);

use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class);

/**
 * R-MANU-002 a R-MANU-005 (SPEC.md §4) — autorização Spatie por recurso do módulo
 * Manufacturing. Mesmo padrão de Modules/Ponto/Tests/Feature/SpatiePermissionsTest.php.
 *
 * Regra base:
 *   Dado que um usuário NÃO tem a permissão `manufacturing.{recurso}`
 *   Quando ele tenta acessar a funcionalidade correspondente
 *   Então recebe 403 (o controller faz `abort(403, ...)` atrás de um OR com o gate de
 *   subscription `manufacturing_module` — "sem a permissão" pode estourar por qualquer um
 *   dos dois lados, e o teste aceita qualquer status != 200, igual ao irmão do Ponto).
 *
 * R-MANU-001 (isolamento multi-tenant) já está coberto por MultiTenantIsolationTest —
 * não duplicado aqui (§5 2026-07-09 "duplica régua consolidada").
 *
 * Tenant: resolvido pelo trait `WithSeededTenant` (`test()->seededTenant()`) — biz=98, a
 * empresa FICTÍCIA não-operadora que a [ADR 0358](../../../../memory/decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md)
 * tornou canônica; sem o seed, cai no primeiro business. NUNCA biz de cliente real.
 *
 * Roda só na lane MySQL (schema UltimatePOS real) — auto-skip em sqlite, igual ao bloco de
 * rota do Wave29RecipeInertiaTest.
 *
 * @covers-us US-MANU-001
 *
 * @see memory/requisitos/Manufacturing/SPEC.md R-MANU-002..005
 */
function mfgPermissionsBootstrap(): array
{
    if (DB::connection()->getDriverName() === 'sqlite') {
        test()->markTestSkipped('SQLite-incompatível: depende do schema MySQL UltimatePOS (business/users/roles).');
    }

    // Tenant canônico de teste — trait `WithSeededTenant`, aplicado em `Tests\TestCase`.
    // Resolve biz=98 (empresa FICTÍCIA, ADR 0358) quando o seed canônico rodou e cai no
    // primeiro business quando não rodou, que era exatamente o comportamento anterior; o
    // skip que ele já traz diz COMO seedar, em vez de "sem business no banco". Por isso as
    // duas guardas abaixo saíram: viraram redundantes, não foram afrouxadas.
    try {
        $business = test()->seededTenant();
    } catch (\Illuminate\Database\QueryException $e) {
        test()->markTestSkipped('Tabela business indisponível: '.$e->getMessage());
    }

    try {
        $user = User::where('business_id', $business->id)->first();
    } catch (\Throwable $e) {
        test()->markTestSkipped('Tabela users indisponível: '.$e->getMessage());
    }

    if (! $user) {
        test()->markTestSkipped('Sem user no business.');
    }

    session([
        'user.business_id' => $business->id,
        'user.id'          => $user->id,
        'business.id'      => $business->id,
        'business.name'    => $business->name,
        'is_admin'         => false,
    ]);

    return [$business, $user];
}

dataset('manufacturing_permission_routes', [
    'R-MANU-002 manufacturing.access_recipe (GET /manufacturing/recipe)'         => ['manufacturing.access_recipe', '/manufacturing/recipe'],
    'R-MANU-003 manufacturing.add_recipe (GET /manufacturing/recipe/create)'     => ['manufacturing.add_recipe', '/manufacturing/recipe/create'],
    'R-MANU-005 manufacturing.access_production (GET /manufacturing/production)' => ['manufacturing.access_production', '/manufacturing/production'],
]);

it('usuário SEM a permissão não vê 200 na rota protegida', function (string $permission, string $url) {
    [, $user] = mfgPermissionsBootstrap();

    Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);

    if ($user->hasPermissionTo($permission)) {
        $user->revokePermissionTo($permission);
    }
    foreach ($user->roles as $role) {
        if ($role->hasPermissionTo($permission)) {
            $role->revokePermissionTo($permission);
        }
    }

    if ($user->fresh()->can('superadmin')) {
        test()->markTestSkipped("User de teste é superadmin — o controller dá bypass explícito (can('superadmin')), fora do que esta regra testa.");
    }

    $response = test()->actingAs($user)->get($url);

    expect($response->status())->not->toBe(
        200,
        "Rota {$url} respondeu 200 pra user sem permissão '{$permission}' — vazamento de autorização (SPEC.md R-MANU §4)."
    );
})->with('manufacturing_permission_routes');

it('usuário COM a permissão não recebe erro de servidor', function (string $permission, string $url) {
    [, $user] = mfgPermissionsBootstrap();

    Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    if (! $user->hasPermissionTo($permission)) {
        $user->givePermissionTo($permission);
    }

    $response = test()->actingAs($user)->get($url);

    if ($response->status() === 403) {
        test()->markTestSkipped("Gate de subscription 'manufacturing_module' bloqueia neste ambiente — a permissão em si foi concedida; não é o que esta regra testa.");
    }

    expect($response->status())->toBeLessThan(
        500,
        "Rota {$url} deu erro de servidor pra user com a permissão '{$permission}' concedida."
    );
})->with('manufacturing_permission_routes');

// R-MANU-004 · manufacturing.edit_recipe — achado, não suposição: Routes/web.php:19 faz
// `Route::resource('/recipe', ...)->except('edit', 'update')`, e nenhuma outra rota do módulo
// referencia UpdateRecipeRequest (grep confirma: só Wave18FormRequestsTest, via reflection).
// O gate em UpdateRecipeRequest::authorize() é código real — só não tem porta HTTP que o
// alcance hoje. Testar via rota seria inventar um caminho que não existe (§5 2026-06-05,
// teste tautológico); o teste honesto é o par permission+can(), como o "com_permissao_acessa"
// do Ponto, mais uma trava que avisa se a rota reaparecer.
it('R-MANU-004 manufacturing.edit_recipe é permissão Spatie real, hoje sem rota que a alcance', function () {
    [, $user] = mfgPermissionsBootstrap();

    $permission = 'manufacturing.edit_recipe';
    Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);

    if ($user->hasPermissionTo($permission)) {
        $user->revokePermissionTo($permission);
    }
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
