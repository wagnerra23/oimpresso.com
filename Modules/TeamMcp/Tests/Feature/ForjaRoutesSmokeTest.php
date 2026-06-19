<?php

declare(strict_types=1);

use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class);

/**
 * Forja · smoke das 6 rotas GET do cockpit (/forja, /backlog, /quadro,
 * /changelog, /mcp, /saude).
 *
 * Cobertura (Onda Forja — código novo sem teste):
 *   - cada rota autenticada (permission copiloto.mcp.usage.all) responde 200
 *     e renderiza o componente Inertia `team-mcp/Forja/Cockpit` com a prop `tab`
 *     correta (e tabLabel/subtitle/meta sempre presentes).
 *   - acesso anônimo → bloqueado pelo middleware auth (302/401/403).
 *
 * Stack UltimatePOS (Modules/TeamMcp/Http/routes.php): ['web','SetSessionData',
 * 'auth','language','timezone','AdminSidebarMenu','CheckUserLogin'] + can:.
 * Esses middlewares exigem schema MySQL (business/users/permissions) → skip
 * gracioso em sqlite :memory: (mesma estratégia de SmokeRoutesTest/TokensList).
 * NUNCA biz=4 (ROTA LIVRE prod) — ADR 0101 usa biz=1 canônico.
 *
 * @see Modules\TeamMcp\Http\Controllers\ForjaController
 * @see memory/decisions/0070-jira-style-task-management.md
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0101-tests-business-id-1-nunca-cliente.md
 */

/** As 6 abas: route name → prop `tab` esperada no componente Cockpit. */
function forjaRotasAbas(): array
{
    return [
        'forja.triagem'   => 'triagem',
        'forja.backlog'   => 'backlog',
        'forja.quadro'    => 'quadro',
        'forja.changelog' => 'changelog',
        'forja.mcp'       => 'mcp',
        'forja.saude'     => 'saude',
    ];
}

/** Bootstrap: tenant canônico + user com copiloto.mcp.usage.all + sessão UltimatePOS. */
function forjaSmokeBootstrap(): User
{
    if (DB::connection()->getDriverName() === 'sqlite') {
        test()->markTestSkipped(
            'SQLite-incompatível: middlewares UltimatePOS (SetSessionData/AdminSidebarMenu/'.
            'CheckUserLogin) exigem schema MySQL com business/users/permissions (ADR 0101).'
        );
    }
    if (! Schema::hasTable('users') || ! Schema::hasTable('permissions')) {
        test()->markTestSkipped('Schema UltimatePOS ausente — rode com DB_CONNECTION=mysql.');
    }

    try {
        $business = test()->seededTenant(); // biz=1 canônico (ADR 0101)
    } catch (\Throwable $e) {
        test()->markTestSkipped('Tenant canônico ausente: '.$e->getMessage());
    }

    $user = User::where('business_id', $business->id)
        ->where('user_type', '!=', 'user_customer')
        ->first();
    if (! $user) {
        test()->markTestSkipped('Sem user não-customer no business pra autenticar.');
    }

    // Garante a permission canon (Wagner/superadmin) pra passar o can: middleware.
    Permission::firstOrCreate(['name' => 'copiloto.mcp.usage.all', 'guard_name' => 'web']);
    if (! $user->hasPermissionTo('copiloto.mcp.usage.all')) {
        $user->givePermissionTo('copiloto.mcp.usage.all');
    }

    session([
        'user.id'          => $user->id,
        'user.business_id' => $business->id,
        'business.id'      => $business->id,
    ]);

    return $user;
}

// -------------------------------------------------------------------------
// 1. Cada rota autenticada → 200 + componente Cockpit + prop tab
// -------------------------------------------------------------------------

it('cada rota Forja renderiza team-mcp/Forja/Cockpit com a prop tab certa', function (string $routeName, string $tab) {
    $user = forjaSmokeBootstrap();

    $response = $this->actingAs($user)->get(route($routeName));

    $response->assertStatus(200);
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('team-mcp/Forja/Cockpit')
        ->where('tab', $tab)
        ->has('tabLabel')
        ->has('subtitle')
        ->has('meta')
    );
})->with(forjaRotasAbas());

// -------------------------------------------------------------------------
// 2. Acesso anônimo bloqueado pelo middleware auth
// -------------------------------------------------------------------------

it('rota Forja bloqueia acesso anônimo via middleware auth (302/401/403)', function (string $routeName) {
    // Mesmo guard de schema/sqlite do happy-path, mas SEM autenticar nem semear sessão.
    if (DB::connection()->getDriverName() === 'sqlite') {
        test()->markTestSkipped('SQLite-incompatível: middlewares UltimatePOS exigem MySQL (ADR 0101).');
    }

    $response = $this->get(route($routeName));
    $status = $response->getStatusCode();

    expect(in_array($status, [302, 401, 403], true))->toBeTrue(
        "Esperado 302/401/403 (auth) em {$routeName} — recebeu {$status}. ".
        'Cockpit Forja sem auth = exposição de governança cross-business (Tier 0).'
    );
})->with(array_keys(forjaRotasAbas()));
