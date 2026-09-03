<?php

declare(strict_types=1);

use App\Http\Middleware\HandleInertiaRequests;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Forja\Services\ForjaAprovacoesService;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Forja · UC-FORJA-19 — o badge de pendências existe em TODA tela do hub, não só na mesa.
 *
 * O ALVO (§3.1 do export da Forja, medido no protótipo em 2026-09-03): "badge de
 * pendências no destino Aprovações". No `forja-page.jsx:1123` o badge é renderizado no
 * destino `hoje` em QUALQUER view — `pendencias` é estado da página inteira, não da aba
 * aberta. É o que avisa que há algo esperando decisão enquanto você está em OUTRA tela;
 * na própria mesa ele é redundante, porque a fila já está na sua frente.
 *
 * O DEFEITO QUE ISTO FECHA: até aqui só `Forja/Aprovacoes/Index` passava a prop, então o
 * badge aparecia exatamente na única tela onde não servia para nada, e sumia nas outras
 * oito. O `forja-cockpit-visual-comparison.md` já tinha MEDIDO o efeito colateral disso
 * sem nomear a causa: os −35px de largura do nav entre protótipo e produção, que ele
 * classificou como "dado (badge de pendências), não CSS".
 *
 * POR QUE ISTO É PEST, e não régua paralela ao `design-diff`: a perna visual (classe
 * `fj-tab-badge`, cor, raio) tem dono e é medida por sonda nos dois renders — assertar
 * className aqui seria régua duplicada, proibida por proibicoes.md §5 (2026-07-09). O que
 * Pest defende é o COMPORTAMENTO de backend que some em SILÊNCIO: basta alguém apagar uma
 * linha de um controller para aquela tela perder o badge, sem erro, sem console, sem
 * vermelho. Falha muda — a pior espécie, e o mesmo vetor do UC-FORJA-15.
 *
 * NÃO É TAUTOLÓGICO: cruza DUAS fontes independentes — o que a rota entrega no partial
 * reload contra o que `ForjaAprovacoesService::contagem()` calcula. Assertar o controller
 * contra ele mesmo é que seria tautologia (§5 proibicoes.md, 2026-06-05).
 *
 * POR QUE PARTIAL RELOAD, e não `AssertableInertia`: a prop é `Inertia::defer`, logo NÃO
 * vem no primeiro paint por construção — pedi-la com `X-Inertia-Partial-Data` é o único
 * jeito de provar que a closure existe e RESOLVE. Mesmo idioma do UC-FORJA-15.
 *
 * ⚠️ `Forja/Aprovacoes/Index` fica DE FORA do dataset de propósito: lá a prop chega pela
 * Page (`pendencias={naMesa}`, do `contagem` que a mesa já pede), e o `ForjaHub` dá
 * precedência à explícita para não fazer a tela esperar um 2º round-trip por um número
 * que ela já tem em mãos. Incluí-la aqui mediria outro caminho e diria "passou" sobre
 * coisa diferente.
 *
 * Stack UltimatePOS (Modules/Forja/Http/routes.php) exige schema MySQL real, então em
 * sqlite o teste PULA — mesma estratégia do ForjaRoutesSmokeTest.
 * ⚠️ skip sai exit 0: o veredito honesto se lê nas ASSERTIONS, nunca em "0 failed".
 *
 * NUNCA biz=4 (ROTA LIVRE, cliente em produção) — tenant canônico do helper.
 *
 * @see Modules/Forja/Resources/js/Pages/team-mcp/Forja/_components/ForjaHub.tsx (naMesa)
 * @see Modules\Forja\Services\ForjaAprovacoesService::contagem()
 * @see memory/requisitos/TeamMcp/forja-cockpit-visual-comparison.md
 */

/**
 * As permissões dos 7 controllers do hub — MEDIDAS no `can:` de cada construtor,
 * não adivinhadas. Não é uma só: o `RoadmapGanttController` pede
 * `jana.mcp.tasks.read|write` e o `CcSessionsController` pede `jana.cc.read.team`.
 * Conceder só `usage.all` deu **403 em 2 dos 7 casos** no primeiro run desta lane
 * (33792099424) — o teste discriminou certo, o bootstrap é que estava curto.
 *
 * Conceder todas NÃO afrouxa nada aqui: RBAC deste hub é o **UC-FORJA-07**, que
 * prova o 403 do usuário sem permissão. Este UC mede a PROP, e um 403 no meio do
 * caminho mediria outra coisa.
 *
 * @var list<string>
 */
const FORJA_BADGE_PERMISSIONS = [
    'jana.mcp.usage.all',   // ForjaController · Trabalho · Scorecard · TasksAdmin · Team
    'jana.mcp.tasks.read',  // RoadmapGanttController
    'jana.mcp.tasks.write', // RoadmapGanttController
    'jana.cc.read.team',    // CcSessionsController
];

/**
 * As telas do hub que passam a servir a prop — UMA por controller tocado.
 *
 * Cobrir as 15 rotas seria redundante: as 8 abas do Cockpit saem todas do MESMO
 * `tabPayload()`, então provar uma prova as oito. O que precisa de um caso cada é o
 * CONTROLLER, porque é nele que a linha pode ser apagada isoladamente.
 */
dataset('telas do hub', [
    'Cockpit (as 8 abas saem do mesmo tabPayload)' => ['/forja', 'team-mcp/Forja/Cockpit'],
    'Trabalho'                                     => ['/forja/trabalho', 'Forja/Trabalho/Index'],
    'Roadmap · Gantt'                              => ['/forja/roadmap-gantt', 'Forja/Roadmap/Gantt'],
    'Scorecard'                                    => ['/team-mcp/scorecard', 'team-mcp/Scorecard/Index'],
    'Tarefas'                                      => ['/team-mcp/tasks', 'team-mcp/Tasks/Index'],
    'Equipe'                                       => ['/team-mcp/team', 'team-mcp/Team/Index'],
    'CC Sessions'                                  => ['/team-mcp/cc-sessions', 'team-mcp/CcSessions/Index'],
]);

/** A stack UltimatePOS não sobe sem schema MySQL real. */
function forjaBadgeExigeSchemaMysql(): void
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
function forjaBadgeUsuario(): User
{
    forjaBadgeExigeSchemaMysql();

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

    // Sem o forget o Spatie lê a permissão antiga e o `can:` do controller devolve 403.
    foreach (FORJA_BADGE_PERMISSIONS as $permissao) {
        Permission::findOrCreate($permissao, 'web');
        $user->givePermissionTo($permissao);
    }
    $user->forgetCachedPermissions();

    session(['user.id' => $user->id]);

    return $user;
}

/**
 * Pede SÓ a prop deferida `pendencias` e devolve o valor que a rota entregou.
 *
 * O header de versão PERGUNTA AO MIDDLEWARE em vez de reimplementar o `md5_file`:
 * sem ele o Inertia responde 409 ANTES de o controller rodar.
 */
function forjaBadgePendenciasDaRota(User $user, string $url, string $componente): mixed
{
    $response = test()->actingAs($user)->withHeaders([
        'X-Inertia'                   => 'true',
        'X-Inertia-Version'           => app(HandleInertiaRequests::class)->version(request()),
        'X-Inertia-Partial-Component' => $componente,
        'X-Inertia-Partial-Data'      => 'pendencias',
    ])->get($url);

    $response->assertStatus(200);

    $page = json_decode($response->getContent(), true);
    expect($page)->toBeArray();
    expect($page)->toHaveKey('props');

    return $page['props']['pendencias'] ?? null;
}

it('UC-FORJA-19 · toda tela do hub serve `pendencias` — o badge não é privilégio da mesa',
    function (string $url, string $componente) {
        $user = forjaBadgeUsuario();

        $daRota = forjaBadgePendenciasDaRota($user, $url, $componente);

        expect($daRota)->not->toBeNull(
            "A rota {$url} não entregou `pendencias` no partial reload. Sem essa prop o ".
            '`ForjaHub` renderiza o topnav SEM o badge nesta tela — e a falha é muda: '.
            'nenhum erro, nenhum console, só o aviso sumindo. O alvo §3.1 do export pede o '.
            'badge no destino Aprovações em TODA view, como no protótipo. Apagou a linha do '.
            'controller? Reponha, ou mude o alvo por decisão [W] — não deixe divergir calado.'
        );

        expect($daRota)->toBeInt("`pendencias` de {$url} não é inteiro — o badge espera um número.");

        // A 2ª fonte: o valor tem que ser O do dono do dado, não um número que a rota
        // tenha calculado por conta. É isto que separa este teste de uma tautologia.
        expect($daRota)->toBe(
            app(ForjaAprovacoesService::class)->contagem(),
            "A rota {$url} entregou um `pendencias` que NÃO é o de ".
            '`ForjaAprovacoesService::contagem()`. O badge tem um dono só; duas contagens '.
            'divergem na primeira mudança de regra do funil (ADR 0368).'
        );
    }
)->with('telas do hub');
