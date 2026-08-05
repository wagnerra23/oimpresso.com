<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class);

// @covers-us US-COPI-111 — Roadmap Gantt (leitura) + reschedule do prazo via drag-drop (B2): guard write, update de due_date pelo TaskCrudService canônico, validação e 403 sem permissão.

/**
 * Porte 1:1 de Modules/Jana/Tests/Feature/Roadmap/RoadmapControllerTest.php com as
 * URLs novas (/forja/roadmap-gantt), pela ADR 0366 §D-B + ADR 0367 D4 — a tela de
 * roadmap por TASK é da Forja, não do Jana.
 *
 * ⚠️ NÃO cobre `/project-mgmt/roadmap` (quarter view por EPIC). Aquela tela segue viva
 * por decisão [W] (ADR 0367 D7) e tem controller/testes próprios — as duas convivem.
 * Recibo da não-duplicação: memory/sessions/2026-08-05-duplicacao-roadmap-forja.md.
 *
 * Não usa RefreshDatabase: roda contra DB real (UltimatePOS tem 100+ migrations +
 * triggers que não migram bem em sqlite). Limpamos fixtures no afterEach.
 *
 * Marca {{ skipped }} se o DB não tiver business/user mínimos.
 *
 * ⛔ Não rodar local — Pest roda no CT 100 ou no CI (proibicoes.md §Ambiente).
 * E `0 failed` não prova execução: leia as ASSERTIONS.
 *
 * Ver memory/requisitos/Forja/RUNBOOK-gantt.md §8.
 */

/** Prefixo canônico da tela — muda numa linha só se a rota mudar. */
const RG_ROTA = '/forja/roadmap-gantt';

function roadmapGanttBootstrap(): array
{
    try {
        $business = Business::first();
    } catch (\Throwable $e) {
        test()->markTestSkipped('Tabela business indisponível: '.$e->getMessage());
    }

    if (! $business) {
        test()->markTestSkipped('Sem business no banco — rode seeder UltimatePOS antes.');
    }

    try {
        $user = User::where('business_id', $business->id)->first();
    } catch (\Throwable $e) {
        test()->markTestSkipped('Tabela users indisponível: '.$e->getMessage());
    }

    if (! $user) {
        test()->markTestSkipped('Sem user no business.');
    }

    // ⛔ Permissions INALTERADAS no porte Jana→Forja: seguem `jana.mcp.tasks.*`.
    // Permission Spatie vive por id de linha — renomear revoga acesso em silêncio
    // (ADR 0087). Rename é ADR + migration própria, nunca efeito colateral de um move.
    foreach (['jana.access', 'jana.mcp.tasks.read'] as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }

    session([
        'user.business_id' => $business->id,
        'user.id'          => $user->id,
        'business.id'      => $business->id,
        'business.name'    => $business->name,
        'is_admin'         => true,
    ]);

    return [$business, $user];
}

function roadmapGanttGivePerm(User $user): void
{
    $perm = Permission::where('name', 'jana.mcp.tasks.read')->first();
    if ($perm && ! $user->hasPermissionTo($perm)) {
        $user->givePermissionTo($perm);
    }
}

function roadmapGanttRevokePerm(User $user): void
{
    $perm = Permission::where('name', 'jana.mcp.tasks.read')->first();
    if ($perm && $user->hasPermissionTo($perm)) {
        $user->revokePermissionTo($perm);
    }
}

/** Versão do manifest Inertia — sem ela o request X-Inertia devolve 409. */
function roadmapGanttInertiaVersion(): string
{
    $manifestPath = public_path('build-inertia/manifest.json');

    return file_exists($manifestPath) ? md5_file($manifestPath) : '1';
}

afterEach(function () {
    try {
        DB::table('mcp_tasks')
            ->where('task_id', 'like', '__test_forja_gantt__%')
            ->delete();

        DB::table('mcp_cycles')
            ->where('key', 'like', '__TEST_FG_%')
            ->delete();
    } catch (\Throwable $e) {
        // sem tabelas (CI vazio) — nada a limpar
    }
});

it('UC-RGT-01 · redireciona pra login se usuário não estiver autenticado', function () {
    // Sem actingAs — request anônima
    $response = $this->get(RG_ROTA);

    // Padrão Laravel: 302 redirect pra /login
    expect($response->status())->toBeIn([302, 401]);
});

it('UC-RGT-02 · responde 403 pra usuário sem permission jana.mcp.tasks.read', function () {
    [, $user] = roadmapGanttBootstrap();
    roadmapGanttRevokePerm($user);

    $this->actingAs($user);
    $response = $this->get(RG_ROTA);

    expect($response->status())->toBe(403);
});

it('UC-RGT-03 · responde 200 e renderiza Inertia component Forja/Roadmap/Gantt com permission', function () {
    [, $user] = roadmapGanttBootstrap();
    roadmapGanttGivePerm($user);

    $this->actingAs($user);

    $response = $this->withHeaders([
        'X-Inertia'         => 'true',
        'X-Inertia-Version' => roadmapGanttInertiaVersion(),
        'Accept'            => 'text/html',
    ])->get(RG_ROTA);

    expect($response->status())->toBe(200);

    $payload = json_decode($response->getContent(), true);
    expect($payload)->toBeArray()
        // Component NOVO — o `Jana/Admin/Roadmap` sai com o redirect 301 do porte.
        ->and($payload['component'] ?? null)->toBe('Forja/Roadmap/Gantt');

    $props = $payload['props'] ?? [];
    expect($props)->toHaveKeys([
        'cycles', 'tasks', 'filters', 'owners', 'modules', 'active_cycle_id',
    ]);
    expect($props['filters'])->toHaveKeys(['cycle', 'owner', 'priority', 'module']);

    // ⚠️ owners/modules são CLOSURE (não Inertia::defer) POR DESENHO — o .tsx
    // desestrutura direto e chama .map(); com defer chegavam `undefined` no 1º paint
    // e estouravam TypeError em PROD (HOTFIX Wagner 2026-05-25). No load cheio eles
    // TÊM que vir resolvidos como array. Ver RUNBOOK-gantt.md §3.
    expect($props['owners'])->toBeArray();
    expect($props['modules'])->toBeArray();
});

it('UC-RGT-04 · aceita filtro por cycle_id via query param', function () {
    [, $user] = roadmapGanttBootstrap();
    roadmapGanttGivePerm($user);

    // Cria 1 cycle de teste
    $cycleId = DB::table('mcp_cycles')->insertGetId([
        'project_id' => 1,
        'key'        => '__TEST_FG_C1__',
        'name'       => 'Cycle test Gantt Forja',
        'start_date' => now()->startOfWeek()->toDateString(),
        'end_date'   => now()->endOfWeek()->toDateString(),
        'status'     => 'active',
        'goal'       => 'Testar Roadmap filtro',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Cria 2 tasks no cycle
    DB::table('mcp_tasks')->insert([
        [
            'task_id'     => '__test_forja_gantt__t1',
            'module'      => 'Forja',
            'title'       => 'Task 1 do roadmap test',
            'status'      => 'doing',
            'priority'    => 'p1',
            'cycle_id'    => $cycleId,
            'source_path' => 'memory/requisitos/Forja/SPEC.md#__test_forja_gantt__t1',
            'parsed_at'   => now(),
            'created_at'  => now(),
            'updated_at'  => now(),
        ],
        [
            'task_id'     => '__test_forja_gantt__t2',
            'module'      => 'Repair',
            'title'       => 'Task 2 do roadmap test',
            'status'      => 'todo',
            'priority'    => 'p2',
            'cycle_id'    => $cycleId,
            'source_path' => 'memory/requisitos/Repair/SPEC.md#__test_forja_gantt__t2',
            'parsed_at'   => now(),
            'created_at'  => now(),
            'updated_at'  => now(),
        ],
    ]);

    $this->actingAs($user);

    $response = $this->withHeaders([
        'X-Inertia'         => 'true',
        'X-Inertia-Version' => roadmapGanttInertiaVersion(),
        'Accept'            => 'text/html',
    ])->get(RG_ROTA.'?cycle='.$cycleId);

    expect($response->status())->toBe(200);

    $payload = json_decode($response->getContent(), true);
    $props = $payload['props'] ?? [];

    $tasksRetornadas = collect($props['tasks'])
        ->where('cycle_id', $cycleId)
        ->values();

    // Asserção forte: filtrou pelo cycle_id (as 2 fixtures criadas estão lá)
    expect($tasksRetornadas->count())->toBeGreaterThanOrEqual(2);
    expect($props['filters']['cycle'])->toBe($cycleId);
});

it('UC-RGT-05 · filtra tasks por module via query param', function () {
    [, $user] = roadmapGanttBootstrap();
    roadmapGanttGivePerm($user);

    DB::table('mcp_tasks')->insert([
        [
            'task_id'     => '__test_forja_gantt__mod_a',
            'module'      => 'ModuloAlpha',
            'title'       => 'Task módulo Alpha',
            'status'      => 'todo',
            'source_path' => 'memory/requisitos/ModuloAlpha/SPEC.md#__test_forja_gantt__mod_a',
            'parsed_at'   => now(),
            'created_at'  => now(),
            'updated_at'  => now(),
        ],
        [
            'task_id'     => '__test_forja_gantt__mod_b',
            'module'      => 'ModuloBeta',
            'title'       => 'Task módulo Beta',
            'status'      => 'todo',
            'source_path' => 'memory/requisitos/ModuloBeta/SPEC.md#__test_forja_gantt__mod_b',
            'parsed_at'   => now(),
            'created_at'  => now(),
            'updated_at'  => now(),
        ],
    ]);

    $this->actingAs($user);

    $response = $this->withHeaders([
        'X-Inertia'         => 'true',
        'X-Inertia-Version' => roadmapGanttInertiaVersion(),
        'Accept'            => 'text/html',
    ])->get(RG_ROTA.'?module=ModuloAlpha');

    expect($response->status())->toBe(200);

    $payload = json_decode($response->getContent(), true);
    $tasks = collect($payload['props']['tasks'] ?? []);

    $alphaCount = $tasks->where('module', 'ModuloAlpha')
        ->where('task_id', '__test_forja_gantt__mod_a')
        ->count();
    $betaCount = $tasks->where('module', 'ModuloBeta')
        ->where('task_id', '__test_forja_gantt__mod_b')
        ->count();

    expect($alphaCount)->toBe(1);
    expect($betaCount)->toBe(0); // Beta filtrado fora
    expect($payload['props']['filters']['module'])->toBe('ModuloAlpha');
});

it('UC-RGT-06 · respeita global scope multi-tenant (mcp_tasks é canon cross-business — não vaza dados de outro business pra UI sem permission)', function () {
    [$business, $user] = roadmapGanttBootstrap();

    $outroBusiness = Business::where('id', '!=', $business->id)->first();
    if (! $outroBusiness) {
        test()->markTestSkipped('Precisa de >1 business pra teste cross-tenant.');
    }

    // mcp_tasks é cache cross-business (ADR 0093 §exceções) — não tem business_id.
    // O isolamento se dá VIA PERMISSION (jana.mcp.tasks.read).
    // Asserção: user de outro business SEM permission não acessa.
    $outroUser = User::where('business_id', $outroBusiness->id)->first();
    if (! $outroUser) {
        test()->markTestSkipped('Sem user no outro business pra teste cross-tenant.');
    }

    roadmapGanttRevokePerm($outroUser);

    $this->actingAs($outroUser);
    session([
        'user.business_id' => $outroBusiness->id,
        'business.id'      => $outroBusiness->id,
    ]);

    $response = $this->get(RG_ROTA);

    // User de biz=outro sem permission → 403 (não vê roadmap canon)
    expect($response->status())->toBe(403);
});

it('UC-RGT-07 · renderiza com lista de tasks vazia sem quebrar (estado inicial DB limpo)', function () {
    [, $user] = roadmapGanttBootstrap();
    roadmapGanttGivePerm($user);

    $this->actingAs($user);

    // Filtro impossível pra forçar zero tasks
    $response = $this->withHeaders([
        'X-Inertia'         => 'true',
        'X-Inertia-Version' => roadmapGanttInertiaVersion(),
        'Accept'            => 'text/html',
    ])->get(RG_ROTA.'?owner=__nonexistent_owner_xyz__');

    expect($response->status())->toBe(200);

    $payload = json_decode($response->getContent(), true);
    $tasks = $payload['props']['tasks'] ?? [];

    expect($tasks)->toBeArray();
    expect(count($tasks))->toBe(0);
});

// ---------------------------------------------------------------------------
// US-COPI-111 B2 (Wagner 2026-07-12) — reschedule do prazo via drag-drop.
// Endpoint PATCH /forja/roadmap-gantt/tasks/{taskId}/schedule, gated write.
// ---------------------------------------------------------------------------

function roadmapGanttEnsureWritePerm(App\User $user, bool $grant): void
{
    $perm = Permission::firstOrCreate(['name' => 'jana.mcp.tasks.write', 'guard_name' => 'web']);
    // Read é pré-req do fluxo; write é o gate do reschedule.
    roadmapGanttGivePerm($user);
    if ($grant && ! $user->hasPermissionTo($perm)) {
        $user->givePermissionTo($perm);
    }
    if (! $grant && $user->hasPermissionTo($perm)) {
        $user->revokePermissionTo($perm);
    }
    // Reset do cache de permissions do Spatie (o método vive no registrar, não no user).
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
}

it('UC-RGT-08 · responde 403 no reschedule sem permission jana.mcp.tasks.write', function () {
    [, $user] = roadmapGanttBootstrap();
    roadmapGanttEnsureWritePerm($user, grant: false);

    $this->actingAs($user);
    $response = $this->patch(RG_ROTA.'/tasks/__test_forja_gantt__resched/schedule', [
        'due_date' => now()->addWeek()->toDateString(),
    ]);

    expect($response->status())->toBe(403);
});

it('UC-RGT-09 · valida due_date obrigatório no reschedule (422 sem data)', function () {
    [, $user] = roadmapGanttBootstrap();
    roadmapGanttEnsureWritePerm($user, grant: true);

    $this->actingAs($user);
    $response = $this->from(RG_ROTA)
        ->patch(RG_ROTA.'/tasks/__test_forja_gantt__resched/schedule', []);

    expect($response->status())->toBe(302); // redirect back com erros de validação
    $response->assertSessionHasErrors('due_date');
});

it('UC-RGT-10 · reagenda o due_date da task via TaskCrudService (biz=1)', function () {
    [, $user] = roadmapGanttBootstrap();
    roadmapGanttEnsureWritePerm($user, grant: true);

    DB::table('mcp_tasks')->insert([
        'task_id'     => '__test_forja_gantt__resched',
        'module'      => 'Forja',
        'title'       => 'Task reschedule B2',
        'status'      => 'todo',
        'priority'    => 'p2',
        'due_date'    => now()->toDateString(),
        'source_path' => 'memory/requisitos/Forja/SPEC.md#__test_forja_gantt__resched',
        'parsed_at'   => now(),
        'created_at'  => now(),
        'updated_at'  => now(),
    ]);

    $novoPrazo = now()->addWeeks(2)->toDateString();

    $this->actingAs($user);
    $response = $this->patch(RG_ROTA.'/tasks/__test_forja_gantt__resched/schedule', [
        'due_date' => $novoPrazo,
    ]);

    expect($response->status())->toBeIn([302, 303]); // back()

    $persisted = DB::table('mcp_tasks')
        ->where('task_id', '__test_forja_gantt__resched')
        ->value('due_date');

    // due_date persistido = novo prazo (compara só a parte de data).
    expect(substr((string) $persisted, 0, 10))->toBe($novoPrazo);
});
