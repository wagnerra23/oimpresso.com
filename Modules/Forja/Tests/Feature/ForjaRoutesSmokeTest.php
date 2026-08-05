<?php

declare(strict_types=1);

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Forja · smoke das 5 rotas GET de aba do cockpit (/forja, /backlog, /quadro,
 * /changelog, /mcp).
 *
 * Cobertura:
 *   - UC-FORJA-01 — cada rota autenticada responde 200 e renderiza o componente
 *     Inertia `team-mcp/Forja/Cockpit` com a prop `tab` correta (e
 *     tabLabel/subtitle/meta sempre presentes).
 *   - UC-FORJA-07 — acesso: anônimo é barrado pelo `auth` (302/401/403) E
 *     autenticado SEM `jana.mcp.usage.all` leva 403.
 *
 * ── Errata 2026-07-27 (este arquivo nunca havia rodado uma vez) ──────────────
 * O helper listava SEIS rotas, incluindo `forja.saude`. Ela NUNCA existiu:
 * Saúde foi fundida no Scorecard real (`/team-mcp/scorecard`) e o próprio
 * `Modules/Forja/Http/routes.php` registra isso em comentário — só há 5 GET de
 * aba, e `ForjaController::TABS` tem 5 entradas. Medido no CT 100 em 2026-07-27
 * (`vendor/bin/pest …ForjaRoutesSmokeTest.php`, DB_CONNECTION=mysql):
 * **7 failed, 5 passed** — 1 `RouteNotFoundException: Route [forja.saude] not
 * defined` + 6 `DatasetArgumentsMismatch` (o dataset era um array ASSOCIATIVO,
 * que em Pest vira 1 argumento — a chave é nome do caso — mas o `it()` declarava
 * dois parâmetros). Logo o happy-path jamais executou. Agora o dataset é
 * `nome do caso => [routeName, tab]`: nome legível E dois argumentos.
 *
 * ── Permissão: `jana.mcp.usage.all`, não `copiloto.*` ────────────────────────
 * O nome mudou no #4853 (`632c5182e2`) e a doc ficou pra trás. Fonte da verdade
 * é o construtor do `ForjaController` (`can:jana.mcp.usage.all`).
 *
 * ── Por que o 403 exige usuário NÃO-admin (senão é falso-verde) ──────────────
 * `Gate::before` em `app/Providers/AuthServiceProvider.php` devolve `true` pra
 * QUALQUER ability de quem tem o papel `Admin#{business_id}`. Um teste de "403
 * sem permissão" feito com admin passaria mesmo se o `can:` fosse removido.
 * Mesma trava do `Modules/Jana/Tests/Feature/JanaAccessGateTest.php` (#4859).
 *
 * Stack UltimatePOS (Modules/Forja/Http/routes.php): ['web','SetSessionData',
 * 'auth','language','timezone','AdminSidebarMenu','CheckUserLogin'] + can:.
 * Esses middlewares exigem schema MySQL (business/users/permissions) → skip
 * gracioso em sqlite :memory: (mesma estratégia de SmokeRoutesTest/TokensList).
 * NUNCA biz=4 (ROTA LIVRE prod) — ADR 0101 usa biz=1 canônico.
 *
 * `DatabaseTransactions`: os casos abaixo concedem/revogam permission pra montar
 * o cenário. Sem o rollback, o teste deixaria resíduo de RBAC no banco da lane.
 *
 * @see Modules\Forja\Http\Controllers\ForjaController
 * @see resources/js/Pages/team-mcp/Forja/Cockpit.casos.md
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0101-tests-business-id-1-nunca-cliente.md
 */

/** Permissão exigida pelo construtor do ForjaController (#4853 renomeou de copiloto.*). */
const FORJA_PERMISSION = 'jana.mcp.usage.all';

/**
 * As 5 abas: nome do caso => [route name, prop `tab` esperada no Cockpit].
 *
 * Lista (não mapa) de propósito: o `it()` recebe DOIS argumentos, e um dataset
 * associativo `chave => string` entrega só um (a chave vira nome do caso).
 *
 * @return array<string,array{0:string,1:string}>
 */
function forjaRotasAbas(): array
{
    return [
        '/forja'           => ['forja.triagem',   'triagem'],
        '/forja/backlog'   => ['forja.backlog',   'backlog'],
        '/forja/quadro'    => ['forja.quadro',    'quadro'],
        '/forja/changelog' => ['forja.changelog', 'changelog'],
        '/forja/mcp'       => ['forja.mcp',       'mcp'],
    ];
}

/**
 * Só os route names, preservando o nome do caso (dataset de 1 argumento).
 *
 * @return array<string,array{0:string}>
 */
function forjaRotasNomes(): array
{
    // array_map com UM array preserva as chaves — o nome do caso segue legível.
    return array_map(static fn (array $par): array => [$par[0]], forjaRotasAbas());
}

/** Guard comum: a stack UltimatePOS não sobe sem schema MySQL real. */
function forjaExigeSchemaMysql(): void
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
}

/** Tenant canônico biz=1 (ADR 0101) + sessão UltimatePOS semeada. */
function forjaSmokeTenant(): \App\Business
{
    forjaExigeSchemaMysql();

    try {
        $business = test()->seededTenant(); // biz=1 canônico (ADR 0101)
    } catch (\Throwable $e) {
        test()->markTestSkipped('Tenant canônico ausente: '.$e->getMessage());
    }

    session([
        'user.business_id' => $business->id,
        'business.id'      => $business->id,
    ]);

    return $business;
}

/** Bootstrap do happy-path: user COM a permission (admin serve — ele passa de todo jeito). */
function forjaSmokeBootstrap(): User
{
    $business = forjaSmokeTenant();

    $user = User::where('business_id', $business->id)
        ->where('user_type', '!=', 'user_customer')
        ->first();
    if (! $user) {
        test()->markTestSkipped('Sem user não-customer no business pra autenticar.');
    }

    Permission::findOrCreate(FORJA_PERMISSION, 'web');
    $user->givePermissionTo(FORJA_PERMISSION);
    $user->forgetCachedPermissions();

    session(['user.id' => $user->id]);

    return $user;
}

/**
 * Bootstrap do caso negativo: user NÃO-admin e SEM a permission.
 *
 * O `hasRole('Admin#…')` é filtrado porque o `Gate::before` libera admin em
 * qualquer ability — com admin, o 403 nunca aconteceria e o teste seria decorativo.
 */
function forjaUsuarioSemPermissao(): User
{
    $business = forjaSmokeTenant();

    $user = User::where('business_id', $business->id)
        ->where('user_type', '!=', 'user_customer')
        ->get()
        ->first(static fn (User $u): bool => ! $u->hasRole('Admin#'.$business->id));

    if (! $user) {
        test()->markTestSkipped(
            "Sem usuário NÃO-admin em business_id={$business->id} — com admin o ".
            'Gate::before libera qualquer ability e o 403 seria falso-verde.'
        );
    }

    Permission::findOrCreate(FORJA_PERMISSION, 'web');
    $user->revokePermissionTo(FORJA_PERMISSION);
    $user->forgetCachedPermissions();

    session(['user.id' => $user->id]);

    return $user;
}

// -------------------------------------------------------------------------
// UC-FORJA-01 — cada rota autenticada → 200 + componente Cockpit + prop tab
// -------------------------------------------------------------------------

it('UC-FORJA-01 · rota Forja renderiza team-mcp/Forja/Cockpit com a prop tab certa', function (string $routeName, string $tab) {
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
// UC-FORJA-07 — acesso: anônimo barrado + autenticado sem permissão → 403
// -------------------------------------------------------------------------

it('UC-FORJA-07 · rota Forja bloqueia acesso anônimo via middleware auth (302/401/403)', function (string $routeName) {
    // Sem tenant/sessão de propósito: o `auth` tem que barrar antes de qualquer coisa.
    // Guard DELIBERADAMENTE mais frouxo que o `forjaExigeSchemaMysql()` dos outros
    // casos: este não toca users/permissions (o redirect do `auth` acontece antes),
    // então roda em qualquer MySQL — inclusive num staging sem schema UltimatePOS.
    // É o que dá pra provar sem lane: `route()` resolve as 5 abas e nenhuma some.
    if (DB::connection()->getDriverName() === 'sqlite') {
        test()->markTestSkipped('SQLite-incompatível: middlewares UltimatePOS exigem MySQL (ADR 0101).');
    }

    $response = $this->get(route($routeName));
    $status = $response->getStatusCode();

    expect(in_array($status, [302, 401, 403], true))->toBeTrue(
        "Esperado 302/401/403 (auth) em {$routeName} — recebeu {$status}. ".
        'Cockpit Forja sem auth = exposição de governança cross-business (Tier 0).'
    );
})->with(forjaRotasNomes());

it('UC-FORJA-07 · autenticado SEM jana.mcp.usage.all leva 403 na rota Forja', function (string $routeName) {
    $user = forjaUsuarioSemPermissao();

    // Se isto virar 200, o `can:jana.mcp.usage.all` do construtor sumiu — e o
    // cockpit (repo-wide cross-business por design, ADR 0093) passou a servir
    // governança de TODOS os businesses pra qualquer funcionário logado.
    $this->actingAs($user)->get(route($routeName))->assertStatus(403);
})->with(forjaRotasNomes());

// -------------------------------------------------------------------------
// UC-FORJA-05 — read-only: as abas do shell não mutam nada
// -------------------------------------------------------------------------
//
// Roda em QUALQUER driver (inclusive sqlite): inspeciona o registro de rotas,
// não faz request. É de propósito — os casos acima só PULAM em sqlite, e skip
// não é cobertura (o veredito que chega ao manifesto por-UC é `skip`, nunca
// `pass`). Este prova o contrato na lane que de fato executa.

it('UC-FORJA-05 · rota de aba da Forja é GET-only (o shell não escreve estado)', function (string $routeName) {
    $route = Route::getRoutes()->getByName($routeName);

    expect($route)->not->toBeNull("rota {$routeName} deve estar registrada");

    // HEAD é derivado do GET pelo próprio Laravel — não conta como verbo de escrita.
    $verbos = array_values(array_diff($route->methods(), ['HEAD']));

    expect($verbos)->toBe(['GET'],
        "A aba {$routeName} aceita ".implode('/', $verbos).'. As abas do cockpit são de '.
        'LEITURA (UC-FORJA-05): qualquer mutação tem que passar pelas rotas POST '.
        'dedicadas (lever/aprovar/rejeitar/fundir), sob confirmação [W] — nunca por um GET '.
        'de render, que navegador e prefetch disparam sozinhos.'
    );
})->with(forjaRotasNomes());

// -------------------------------------------------------------------------
// UC-FORJA-02 — topnav do hub: 9 itens, nenhum apontando pra rota fantasma
// -------------------------------------------------------------------------
//
// NÃO é tautológico: cruza DUAS fontes independentes — `config/core_topnavs.php`
// (o que a nav promete) contra o registro de rotas do Laravel (o que existe de
// fato). É exatamente a classe do `forja.saude`, que ficou listado num teste por
// meses apontando pra uma rota que nunca existiu (#4887). Testar o config contra
// ele mesmo é que seria tautologia (§5 proibicoes.md, 2026-06-05).

it('UC-FORJA-02 · topnav do hub tem 9 itens (5 Forja + 4 TeamMcp absorvidos)', function () {
    $items = config('core_topnavs.Forja.items');

    expect($items)->toBeArray();
    expect($items)->toHaveCount(9,
        'Fusão de 2026-06-16: `config/core_topnavs.php[Forja]` é o ÚNICO grupo que casa '.
        '/team-mcp/* no useAutoModuleNav, então carrega as 5 abas próprias MAIS as 4 telas '.
        'absorvidas (Tarefas · Equipe · CC Sessions · Saúde). Mudou a conta? O hub ganhou ou '.
        'perdeu tela — atualize Cockpit.casos.md e o §5.3 F6 do SDD junto.'
    );
});

it('UC-FORJA-02 · todo href do topnav resolve pra uma rota GET registrada', function () {
    $items = config('core_topnavs.Forja.items');

    foreach ($items as $item) {
        $href = $item['href'] ?? null;
        $label = $item['label'] ?? '(sem label)';

        expect($href)->not->toBeNull("item '{$label}' do topnav sem href");

        try {
            Route::getRoutes()->match(Request::create($href, 'GET'));
        } catch (\Throwable $e) {
            $this->fail(
                "Item '{$label}' do topnav aponta pra {$href}, que NÃO resolve pra rota ".
                'registrada ('.$e::class.'). Item fantasma na nav = 404 no clique do [W]. '.
                'Foi assim que `forja.saude` sobreviveu meses num teste que nunca rodou (#4887).'
            );
        }
    }
});
