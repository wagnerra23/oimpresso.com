<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class);

/**
 * Onda 5 V1 — Roadmap timeline (SVAR React Gantt MIT).
 *
 * Não usa RefreshDatabase: roda contra DB dev real (UltimatePOS tem 100+
 * migrations + triggers que não migram bem em sqlite). Limpamos fixtures
 * no afterEach.
 *
 * Marca {{ skipped }} se DB não tiver business/user mínimos.
 *
 * Ver memory/requisitos/Jana/ONDA-5-DOSSIER-2026-05-13.md §V1.
 */

function roadmapBootstrap(): array
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

function roadmapGivePerm(User $user): void
{
    $perm = Permission::where('name', 'jana.mcp.tasks.read')->first();
    if ($perm && ! $user->hasPermissionTo($perm)) {
        $user->givePermissionTo($perm);
    }
}

function roadmapRevokePerm(User $user): void
{
    $perm = Permission::where('name', 'jana.mcp.tasks.read')->first();
    if ($perm && $user->hasPermissionTo($perm)) {
        $user->revokePermissionTo($perm);
    }
}

afterEach(function () {
    try {
        DB::table('mcp_tasks')
            ->where('task_id', 'like', '__test_roadmap_v1__%')
            ->delete();

        DB::table('mcp_cycles')
            ->where('key', 'like', '__TEST_RDM_%')
            ->delete();
    } catch (\Throwable $e) {
        // sem tabelas (CI vazio) — nada a limpar
    }
});

it('redireciona pra login se usuário não estiver autenticado', function () {
    // Sem actingAs — request anônima
    $response = $this->get('/jana/admin/roadmap');

    // Padrão Laravel: 302 redirect pra /login
    expect($response->status())->toBeIn([302, 401]);
});

it('responde 403 pra usuário sem permission jana.mcp.tasks.read', function () {
    [, $user] = roadmapBootstrap();
    roadmapRevokePerm($user);

    $this->actingAs($user);
    $response = $this->get('/jana/admin/roadmap');

    expect($response->status())->toBe(403);
});

it('responde 200 e renderiza Inertia component Jana/Admin/Roadmap com permission', function () {
    [, $user] = roadmapBootstrap();
    roadmapGivePerm($user);

    $this->actingAs($user);

    $manifestPath = public_path('build-inertia/manifest.json');
    $version = file_exists($manifestPath) ? md5_file($manifestPath) : '1';

    $response = $this->withHeaders([
        'X-Inertia'         => 'true',
        'X-Inertia-Version' => $version,
        'Accept'            => 'text/html',
    ])->get('/jana/admin/roadmap');

    expect($response->status())->toBe(200);

    $payload = json_decode($response->getContent(), true);
    expect($payload)->toBeArray()
        ->and($payload['component'] ?? null)->toBe('Jana/Admin/Roadmap');

    $props = $payload['props'] ?? [];
    expect($props)->toHaveKeys([
        'cycles', 'tasks', 'filters', 'owners', 'modules', 'active_cycle_id',
    ]);
    expect($props['filters'])->toHaveKeys(['cycle', 'owner', 'priority', 'module']);
});

it('aceita filtro por cycle_id via query param', function () {
    [, $user] = roadmapBootstrap();
    roadmapGivePerm($user);

    // Cria 1 cycle de teste
    $cycleId = DB::table('mcp_cycles')->insertGetId([
        'project_id' => 1,
        'key'        => '__TEST_RDM_V1__',
        'name'       => 'Cycle test V1',
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
            'task_id'     => '__test_roadmap_v1__t1',
            'module'      => 'Jana',
            'title'       => 'Task 1 do roadmap test',
            'status'      => 'doing',
            'priority'    => 'p1',
            'cycle_id'    => $cycleId,
            'source_path' => 'memory/requisitos/Jana/SPEC.md#__test_roadmap_v1__t1',
            'parsed_at'   => now(),
            'created_at'  => now(),
            'updated_at'  => now(),
        ],
        [
            'task_id'     => '__test_roadmap_v1__t2',
            'module'      => 'Repair',
            'title'       => 'Task 2 do roadmap test',
            'status'      => 'todo',
            'priority'    => 'p2',
            'cycle_id'    => $cycleId,
            'source_path' => 'memory/requisitos/Repair/SPEC.md#__test_roadmap_v1__t2',
            'parsed_at'   => now(),
            'created_at'  => now(),
            'updated_at'  => now(),
        ],
    ]);

    $this->actingAs($user);

    $manifestPath = public_path('build-inertia/manifest.json');
    $version = file_exists($manifestPath) ? md5_file($manifestPath) : '1';

    $response = $this->withHeaders([
        'X-Inertia'         => 'true',
        'X-Inertia-Version' => $version,
        'Accept'            => 'text/html',
    ])->get('/jana/admin/roadmap?cycle='.$cycleId);

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

it('filtra tasks por module via query param', function () {
    [, $user] = roadmapBootstrap();
    roadmapGivePerm($user);

    DB::table('mcp_tasks')->insert([
        [
            'task_id'     => '__test_roadmap_v1__mod_a',
            'module'      => 'ModuloAlpha',
            'title'       => 'Task módulo Alpha',
            'status'      => 'todo',
            'source_path' => 'memory/requisitos/ModuloAlpha/SPEC.md#__test_roadmap_v1__mod_a',
            'parsed_at'   => now(),
            'created_at'  => now(),
            'updated_at'  => now(),
        ],
        [
            'task_id'     => '__test_roadmap_v1__mod_b',
            'module'      => 'ModuloBeta',
            'title'       => 'Task módulo Beta',
            'status'      => 'todo',
            'source_path' => 'memory/requisitos/ModuloBeta/SPEC.md#__test_roadmap_v1__mod_b',
            'parsed_at'   => now(),
            'created_at'  => now(),
            'updated_at'  => now(),
        ],
    ]);

    $this->actingAs($user);

    $manifestPath = public_path('build-inertia/manifest.json');
    $version = file_exists($manifestPath) ? md5_file($manifestPath) : '1';

    $response = $this->withHeaders([
        'X-Inertia'         => 'true',
        'X-Inertia-Version' => $version,
        'Accept'            => 'text/html',
    ])->get('/jana/admin/roadmap?module=ModuloAlpha');

    expect($response->status())->toBe(200);

    $payload = json_decode($response->getContent(), true);
    $tasks = collect($payload['props']['tasks'] ?? []);

    $alphaCount = $tasks->where('module', 'ModuloAlpha')
        ->where('task_id', '__test_roadmap_v1__mod_a')
        ->count();
    $betaCount = $tasks->where('module', 'ModuloBeta')
        ->where('task_id', '__test_roadmap_v1__mod_b')
        ->count();

    expect($alphaCount)->toBe(1);
    expect($betaCount)->toBe(0); // Beta filtrado fora
    expect($payload['props']['filters']['module'])->toBe('ModuloAlpha');
});

it('respeita global scope multi-tenant (mcp_tasks é canon cross-business — não vaza dados de outro business pra UI sem permission)', function () {
    [$business, $user] = roadmapBootstrap();

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

    roadmapRevokePerm($outroUser);

    $this->actingAs($outroUser);
    session([
        'user.business_id' => $outroBusiness->id,
        'business.id'      => $outroBusiness->id,
    ]);

    $response = $this->get('/jana/admin/roadmap');

    // User de biz=outro sem permission → 403 (não vê roadmap canon)
    expect($response->status())->toBe(403);
});

it('renderiza com lista de tasks vazia sem quebrar (estado inicial DB limpo)', function () {
    [, $user] = roadmapBootstrap();
    roadmapGivePerm($user);

    $this->actingAs($user);

    $manifestPath = public_path('build-inertia/manifest.json');
    $version = file_exists($manifestPath) ? md5_file($manifestPath) : '1';

    // Filtro impossível pra forçar zero tasks
    $response = $this->withHeaders([
        'X-Inertia'         => 'true',
        'X-Inertia-Version' => $version,
        'Accept'            => 'text/html',
    ])->get('/jana/admin/roadmap?owner=__nonexistent_owner_xyz__');

    expect($response->status())->toBe(200);

    $payload = json_decode($response->getContent(), true);
    $tasks = $payload['props']['tasks'] ?? [];

    expect($tasks)->toBeArray();
    expect(count($tasks))->toBe(0);
});
