<?php

declare(strict_types=1);

use App\Http\Middleware\HandleInertiaRequests;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Forja · UC-FORJA-15 — o painel de handoffs volta pra DENTRO da aba MCP, sem
 * duplicar consulta e sem matar a tela própria (PARIDADE §11 Onda 8, ADR 0388).
 *
 * O QUE ESTE TESTE DEFENDE — e por que ele não é régua paralela ao `design-diff`.
 * A perna VISUAL da Onda 8 (classes `fj-*`, mono, cor, alinhamento) tem dono e é
 * medida por sonda nos dois renders; um Pest que assertasse className seria régua
 * duplicada, proibida por proibicoes.md §5 (2026-07-09). O que Pest defende aqui é
 * o COMPORTAMENTO que a onda introduziu no backend e que some em SILÊNCIO se
 * alguém reverter: `/forja/mcp` voltou a entregar `handoffs` + `heartbeat`.
 *
 * Sem isto, remover as duas linhas do `ForjaController@mcp` deixa a aba renderizando
 * o `<Deferred>` pra sempre (fallback eterno, sem erro no console) — falha muda, a
 * pior espécie. Foi exatamente o vetor que a saída de 2026-08-08 abriu e ninguém viu.
 *
 * AS DUAS PERNAS (a 2ª é o que distingue "voltou" de "mudou de lugar"):
 *   1. `/forja/mcp`      entrega `handoffs` + `heartbeat` (partial reload).
 *   2. `/forja/handoffs` SEGUE entregando as mesmas duas — a tela própria não morreu;
 *      é o MESMO componente e a MESMA projeção (`ForjaMcpService`), um dono só.
 *
 * POR QUE PARTIAL RELOAD, e não `AssertableInertia`: as props são `Inertia::defer`,
 * então NÃO vêm no primeiro paint por construção — pedi-las com
 * `X-Inertia-Partial-Data` é o único jeito de provar que a closure existe e resolve.
 * Mesmo idioma de tests/Feature/Produto/StockHistoryContratoTest.php.
 *
 * Stack UltimatePOS (Modules/Forja/Http/routes.php): ['web','SetSessionData','auth',
 * 'language','timezone','AdminSidebarMenu','CheckUserLogin'] + can: — exige schema
 * MySQL real, então em sqlite o teste PULA (mesma estratégia do ForjaRoutesSmokeTest).
 * ⚠️ skip sai exit 0: o veredito honesto se lê nas ASSERTIONS, nunca em "0 failed".
 *
 * NUNCA biz=4 (ROTA LIVRE, cliente em produção) — tenant canônico do helper.
 *
 * @see Modules\Forja\Http\Controllers\ForjaController::mcp()
 * @see Modules\Forja\Services\ForjaMcpService
 * @see Modules/Forja/Resources/js/Pages/team-mcp/Forja/Cockpit.casos.md (UC-FORJA-15)
 * @see memory/requisitos/Forja/PARIDADE-area-forja-diagnostico-e-ondas.md (§11 Onda 8)
 */

/** Permissão exigida pelo construtor do ForjaController (#4853 renomeou de copiloto.*). */
const FORJA_MCP_INLINE_PERMISSION = 'jana.mcp.usage.all';

/** A stack UltimatePOS não sobe sem schema MySQL real. */
function forjaInlineExigeSchemaMysql(): void
{
    if (DB::connection()->getDriverName() === 'sqlite') {
        test()->markTestSkipped(
            'SQLite-incompatível: middlewares UltimatePOS (SetSessionData/AdminSidebarMenu/'.
            'CheckUserLogin) exigem schema MySQL com business/users/permissions.'
        );
    }
    if (! Schema::hasTable('users') || ! Schema::hasTable('permissions')) {
        test()->markTestSkipped('Schema UltimatePOS ausente — rode com DB_CONNECTION=mysql.');
    }
}

/** Usuário do tenant canônico, com a permission concedida (rollback pelo DatabaseTransactions). */
function forjaInlineUsuario(): User
{
    forjaInlineExigeSchemaMysql();

    try {
        $business = test()->seededTenant(); // tenant canônico — NUNCA biz=4
    } catch (\Throwable $e) {
        test()->markTestSkipped('Tenant canônico ausente: '.$e->getMessage());
    }

    session([
        'user.business_id' => $business->id,
        'business.id'      => $business->id,
    ]);

    $user = User::where('business_id', $business->id)
        ->where('user_type', '!=', 'user_customer')
        ->first();

    if ($user === null) {
        test()->markTestSkipped('Tenant canônico sem usuário utilizável.');
    }

    // O `can:jana.mcp.usage.all` do construtor do ForjaController barra com 403 quem
    // não tem a permission — e o usuário do seed não tem. As 3 linhas são o mesmo
    // bootstrap do ForjaRoutesSmokeTest: criar a permission, conceder, e LIMPAR o
    // cache do Spatie (sem o forget, a checagem lê a permissão antiga e o 403 fica).
    // `DatabaseTransactions` faz o rollback — nenhum resíduo de RBAC no banco da lane.
    Permission::findOrCreate(FORJA_MCP_INLINE_PERMISSION, 'web');
    $user->givePermissionTo(FORJA_MCP_INLINE_PERMISSION);
    $user->forgetCachedPermissions();

    session(['user.id' => $user->id]);

    return $user;
}

/**
 * Versão de asset que o Inertia espera no header.
 *
 * PERGUNTA AO MIDDLEWARE DO APP em vez de reimplementar o `md5_file(manifest)`:
 * o `HandleInertiaRequests::version()` é o dono da regra, e duplicá-la aqui faria
 * o teste passar a mentir no dia em que ela mudasse. Sem este header o Inertia
 * responde **409** (version mismatch) ANTES de o controller rodar — foi o que
 * derrubou as 3 asserções na primeira execução desta lane (run 33676378598).
 */
function forjaInlineVersaoInertia(): ?string
{
    return app(HandleInertiaRequests::class)->version(request());
}

/**
 * Pede as props DEFERIDAS de uma rota do cockpit e devolve o `props` do page object.
 *
 * @return array<string,mixed>
 */
function forjaInlinePropsDeferidas(User $user, string $url): array
{
    $response = test()->actingAs($user)->withHeaders([
        'X-Inertia'                   => 'true',
        'X-Inertia-Version'           => forjaInlineVersaoInertia(),
        'X-Inertia-Partial-Component' => 'team-mcp/Forja/Cockpit',
        'X-Inertia-Partial-Data'      => 'handoffs,heartbeat',
    ])->get($url);

    // 409 aqui = version mismatch (header acima), NÃO o "409" de lever idempotente.
    $response->assertStatus(200);

    $page = json_decode($response->getContent(), true);
    expect($page)->toBeArray();

    return $page['props'] ?? [];
}

it('UC-FORJA-15 · /forja/mcp entrega handoffs e heartbeat (o painel voltou pra dentro da aba)', function () {
    $user = forjaInlineUsuario();

    $props = forjaInlinePropsDeferidas($user, '/forja/mcp');

    // O contrato: as DUAS props resolvem. Sem elas o <Deferred> nunca sai do fallback.
    expect($props)->toHaveKey('handoffs');
    expect($props)->toHaveKey('heartbeat');
    expect($props['handoffs'])->toBeArray();

    // heartbeat é o objeto do ingest — `silent` é o campo que vira "sem sinal" na tela.
    expect($props['heartbeat'])->toBeArray();
    expect($props['heartbeat'])->toHaveKey('silent');
});

it('UC-FORJA-15 · /forja/handoffs segue entregando as mesmas props (a tela própria não morreu)', function () {
    $user = forjaInlineUsuario();

    $props = forjaInlinePropsDeferidas($user, '/forja/handoffs');

    expect($props)->toHaveKey('handoffs');
    expect($props)->toHaveKey('heartbeat');
    expect($props['handoffs'])->toBeArray();
});

it('UC-FORJA-15 · as duas rotas servem a MESMA projeção (um dono, dois pontos de render)', function () {
    $user = forjaInlineUsuario();

    $naAba  = forjaInlinePropsDeferidas($user, '/forja/mcp');
    $naTela = forjaInlinePropsDeferidas($user, '/forja/handoffs');

    // Mesma fonte (ForjaMcpService) ⇒ mesma lista. Se um dia divergirem, alguém
    // duplicou a consulta — que é exatamente o que esta onda se comprometeu a NÃO fazer.
    expect($naAba['handoffs'])->toEqual($naTela['handoffs']);
});
