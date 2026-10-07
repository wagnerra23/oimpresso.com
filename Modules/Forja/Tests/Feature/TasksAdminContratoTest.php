<?php

declare(strict_types=1);

use App\Http\Middleware\HandleInertiaRequests;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Entities\Mcp\McpTask;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Tarefas · contrato da tela `/team-mcp/tasks` (Backlog + Quadro + drawer de issue).
 *
 * Cobre os UC de `Modules/Forja/Resources/js/Pages/team-mcp/Tasks/Index.casos.md`.
 * Os UC saem do charter + `tasks-visual-comparison.md` (decisões [W] 2026-06-16) +
 * ADR 0070 + SDD do hub (CU-TEAM-09) — não do `.tsx`. O controller só confirma.
 *
 * DUAS FORÇAS (ADR 0264 G-7):
 *   - pernas de REGISTRO (rota, middleware, verbo) rodam em qualquer driver, inclusive
 *     na lane sqlite `PHP / Pest (Unit)`;
 *   - pernas de REQUEST (403, PATCH de status, evento, quadro) exigem a stack
 *     UltimatePOS e `FIELD()` do MySQL → só dão veredito na lane `forja-pest.yml`.
 *     Em sqlite elas PULAM, e skip não é cobertura.
 *
 * `mcp_tasks` é repo-wide POR DESIGN (ADR 0070/0093, governança da plataforma) — não há
 * caso de isolamento por business aqui. O tenant (biz=98, ADR 0358) só existe porque a
 * stack de rotas exige sessão de business. NUNCA biz=4.
 *
 * Fixtures com prefixo `TSKCT98-` e module `TSKCT98`: o filtro `?module=` isola o
 * cenário das tasks reais que existam no banco.
 *
 * @see Modules\Forja\Http\Controllers\TasksAdminController
 * @see memory/requisitos/TeamMcp/tasks-visual-comparison.md
 */

const TSK_PERMISSION = 'jana.mcp.usage.all';
const TSK_MODULO = 'TSKCT98';

/** Rotas `team-mcp.tasks.*` como o router as enxerga. */
function tskRotas(): \Illuminate\Support\Collection
{
    return collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($r): bool => str_starts_with((string) $r->getName(), 'team-mcp.tasks.'));
}

function tskExigeSchemaMysql(): void
{
    if (DB::connection()->getDriverName() === 'sqlite') {
        test()->markTestSkipped(
            'SQLite-incompatível: stack UltimatePOS + FIELD() do MySQL. '.
            'Esta perna só dá veredito na lane forja-pest.yml.'
        );
    }
    if (! Schema::hasTable('users') || ! Schema::hasTable('mcp_tasks') || ! Schema::hasTable('mcp_task_events')) {
        test()->markTestSkipped('Schema ausente (users/mcp_tasks/mcp_task_events) — rode com DB_CONNECTION=mysql.');
    }
}

/**
 * Usuário NOVO no tenant de teste com exatamente as permissões pedidas — sem `Admin#`
 * (o `Gate::before` liberaria tudo e o caso negativo viraria decorativo).
 *
 * @param  list<string>  $permissoes
 */
function tskUsuario(array $permissoes): User
{
    tskExigeSchemaMysql();

    try {
        $business = test()->seededTenant(); // biz=98 (ADR 0358) — NUNCA biz=4
    } catch (\Throwable $e) {
        test()->markTestSkipped('Tenant canônico ausente: '.$e->getMessage());
    }

    $user = test()->usuarioComPermissoes($permissoes, $business);

    session([
        'user.business_id' => $business->id,
        'business.id'      => $business->id,
        'user.id'          => $user->id,
    ]);

    return $user;
}

/** Fixture de task. Eloquent direto de propósito: é cenário, não o caminho sob teste. */
function tskTask(string $sufixo, string $status): McpTask
{
    $t = new McpTask();
    $t->task_id = 'TSKCT98-'.$sufixo;
    $t->identifier = 'TSKCT98-'.$sufixo;
    $t->title = 'Fixture contrato Tasks '.$sufixo;
    $t->module = TSK_MODULO;
    $t->status = $status;
    $t->type = 'story';
    $t->priority = 'p2';
    $t->save();

    return $t;
}

/** Pede SÓ a prop deferida pedida, como o Inertia faz no partial reload. */
function tskPropDeferida(User $user, string $prop, string $query): mixed
{
    $r = test()->actingAs($user)->withHeaders([
        'X-Inertia'                   => 'true',
        'X-Requested-With'            => 'XMLHttpRequest',
        'X-Inertia-Version'           => app(HandleInertiaRequests::class)->version(request()),
        'X-Inertia-Partial-Component' => 'team-mcp/Tasks/Index',
        'X-Inertia-Partial-Data'      => $prop,
    ])->get('/team-mcp/tasks'.$query);

    $r->assertStatus(200);

    return json_decode($r->getContent(), true)['props'][$prop] ?? null;
}

// ---------------------------------------------------------------------------
// UC-TSK-01 — a rota abre a tela (a Page existe)
// ---------------------------------------------------------------------------

it('UC-TSK-01 · a rota da tela está registrada e aponta pro TasksAdminController@index', function () {
    $route = Route::getRoutes()->getByName('team-mcp.tasks.index');

    expect($route)->not->toBeNull();
    expect($route->uri())->toBe('team-mcp/tasks');
    expect($route->getActionName())->toEndWith('TasksAdminController@index');
});

it('UC-TSK-01 · o componente Inertia que o controller renderiza existe em disco', function () {
    $controller = file_get_contents(base_path('Modules/Forja/Http/Controllers/TasksAdminController.php'));

    expect($controller)->toContain("Inertia::render('team-mcp/Tasks/Index'");
    expect(file_exists(base_path('Modules/Forja/Resources/js/Pages/team-mcp/Tasks/Index.tsx')))->toBeTrue(
        'O controller renderiza team-mcp/Tasks/Index mas o .tsx não existe — Inertia 500.'
    );
});

// ---------------------------------------------------------------------------
// UC-TSK-02 — acesso: auth + jana.mcp.usage.all em TODA rota (inclusive o drawer)
// ---------------------------------------------------------------------------

it('UC-TSK-02 · toda rota team-mcp.tasks.* exige auth + can:jana.mcp.usage.all no registro', function () {
    $rotas = tskRotas();

    // index + update-status + detail. Menos que 3 = alguém sumiu com uma rota da tela.
    expect($rotas->count())->toBeGreaterThanOrEqual(3);

    foreach ($rotas as $r) {
        $mw = $r->gatherMiddleware();

        expect(in_array('auth', $mw, true))->toBeTrue("Rota {$r->getName()} sem `auth`.");

        $exige = collect($mw)->contains(fn ($m): bool => is_string($m) && str_contains($m, TSK_PERMISSION));
        expect($exige)->toBeTrue(
            "Rota {$r->getName()} não exige `".TSK_PERMISSION.'`. O charter manda a permissão '.
            'em TODAS as ações, incluindo o show() do drawer. Middleware visto: '.json_encode($mw)
        );
    }
});

it('UC-TSK-02 · autenticado SEM jana.mcp.usage.all leva 403 na tela e no drawer', function () {
    $user = tskUsuario([]);

    $this->actingAs($user)->get('/team-mcp/tasks')->assertStatus(403);
    $this->actingAs($user)->getJson('/team-mcp/tasks/QUALQUER-1/detail')->assertStatus(403);
});

// ---------------------------------------------------------------------------
// UC-TSK-03 — só os 6 status canônicos; fora disso, 422 e nada muda
// ---------------------------------------------------------------------------

it('UC-TSK-03 · mover pra status fora dos 6 canônicos responde 422 e a task não muda', function () {
    $user = tskUsuario([TSK_PERMISSION]);
    $task = tskTask('FASE', 'todo');

    // "F2" é exatamente a fase inventada que o charter proíbe (Non-Goal: "Inventar fases
    // F0..F4 — usa os 6 status canônicos (ADR 0070)").
    $this->actingAs($user)
        ->patchJson("/team-mcp/tasks/{$task->task_id}/status", ['status' => 'F2'])
        ->assertStatus(422);

    expect(McpTask::where('task_id', $task->task_id)->value('status'))->toBe('todo');
});

// ---------------------------------------------------------------------------
// UC-TSK-04 — mover status persiste e vira atividade real no drawer
// ---------------------------------------------------------------------------

it('UC-TSK-04 · PATCH de status persiste, grava mcp_task_events e o drawer mostra a atividade', function () {
    $user = tskUsuario([TSK_PERMISSION]);
    $task = tskTask('MOVE', 'todo');

    $eventosAntes = DB::table('mcp_task_events')->where('task_id', $task->task_id)->count();

    $this->actingAs($user)
        ->patchJson("/team-mcp/tasks/{$task->task_id}/status", ['status' => 'doing'])
        ->assertStatus(200)
        ->assertJsonPath('status', 'doing');

    expect(McpTask::where('task_id', $task->task_id)->value('status'))->toBe('doing');
    expect(DB::table('mcp_task_events')->where('task_id', $task->task_id)->count())
        ->toBeGreaterThan($eventosAntes);

    // Segunda fonte: o que o drawer lê é o MESMO evento que o PATCH escreveu — atividade
    // real, não montada na tela (charter: "Atividade (`mcp_task_events` real)").
    $detalhe = $this->actingAs($user)->getJson("/team-mcp/tasks/{$task->task_id}/detail")->assertStatus(200);

    expect($detalhe->json('task.status'))->toBe('doing');
    $paraDoing = collect($detalhe->json('events'))->filter(fn ($e): bool => ($e['to_value'] ?? null) === 'doing');
    expect($paraDoing->count())->toBeGreaterThanOrEqual(1);
});

// ---------------------------------------------------------------------------
// UC-TSK-05 — a única escrita da tela é o PATCH de status
// ---------------------------------------------------------------------------

it('UC-TSK-05 · a única rota de escrita da tela é o PATCH de status; o resto é GET', function () {
    $escrita = [];

    foreach (tskRotas() as $r) {
        $verbos = array_values(array_diff($r->methods(), ['HEAD']));

        if ($verbos === ['GET']) {
            continue;
        }

        $escrita[] = $r->getName().' '.implode('/', $verbos);
    }

    expect($escrita)->toBe(['team-mcp.tasks.update-status PATCH'],
        'O charter (Anti-hooks) permite escrever SÓ o status. Criar/editar task na tela é '.
        'Non-Goal (criação via mcp:tasks:sync / tasks-create MCP). Rotas de escrita vistas: '.
        json_encode($escrita)
    );
});

// ---------------------------------------------------------------------------
// UC-TSK-06 — o Quadro tem as 4 colunas todo/doing/review/done
// ---------------------------------------------------------------------------

it('UC-TSK-06 · o Quadro agrupa só todo/doing/review/done; task blocked não vira coluna', function () {
    $user = tskUsuario([TSK_PERMISSION]);

    tskTask('QDR-TODO', 'todo');
    tskTask('QDR-REV', 'review');
    $travada = tskTask('QDR-BLOQ', 'blocked');

    $kanban = tskPropDeferida($user, 'kanban', '?module='.TSK_MODULO);

    expect($kanban)->toBeArray('o partial reload não entregou `kanban`');

    $colunas = array_keys($kanban);
    expect(array_values(array_diff($colunas, ['todo', 'doing', 'review', 'done'])))->toBe([],
        'O Quadro só tem 4 colunas (charter: "kanban todo/doing/review/done"). Colunas vistas: '.json_encode($colunas)
    );

    // Controle: as fixtures entraram nas colunas certas — sem isto um kanban vazio passaria.
    expect(array_column($kanban['todo'] ?? [], 'task_id'))->toBe(['TSKCT98-QDR-TODO']);
    expect(array_column($kanban['review'] ?? [], 'task_id'))->toBe(['TSKCT98-QDR-REV']);

    $todosIds = collect($kanban)->flatten(1)->pluck('task_id')->all();
    expect(in_array($travada->task_id, $todosIds, true))->toBeFalse('task blocked apareceu no Quadro');
});
