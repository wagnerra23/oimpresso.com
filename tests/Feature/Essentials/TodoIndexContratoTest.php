<?php

declare(strict_types=1);

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(DatabaseTransactions::class);

/**
 * Contrato da tela Essentials/Todo/Index — resources/js/Pages/Essentials/Todo/Index.casos.md.
 *
 * Os UC derivam do charter (Goals/Non-Goals) + do Controller real
 * (`Modules\Essentials\Http\Controllers\ToDoController@index` e `@update` com `only_status`),
 * nunca do protótipo. Cada `it()` cita o UC-id no título (casos-gate G-2).
 *
 * Tenant: 98 (canônico, empresa FICTÍCIA) vs 99 (adversário cross-tenant) — ADR 0358.
 * NUNCA biz=4. ADR 0093 Tier 0 IRREVOGÁVEL.
 *
 * Fixtures por `DB::table` (não pelo Model): `ToDo` usa `HasBusinessScope`, e o que está
 * sob teste é o filtro do CONTROLLER — o fixture não pode depender do escopo que se mede.
 * `DatabaseTransactions`, nunca `RefreshDatabase` (MySQL semeado é compartilhado).
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema UltimatePOS requer MySQL (ADR 0358).');
    }
    foreach (['essentials_to_dos', 'essentials_todos_users'] as $tbl) {
        if (! Schema::hasTable($tbl)) {
            $this->markTestSkipped("Tabela {$tbl} ausente — rode migrate Modules/Essentials.");
        }
    }

    $this->tenant = $this->seededTenant();
    $this->adversario = $this->seededSupportClientTenant();

    $user = User::where('business_id', $this->tenant->id)->first();
    if (! $user) {
        $this->markTestSkipped('Sem user no tenant canônico — seed mínimo não rodou.');
    }
    $this->actor = $user;

    // Admin do tenant: o index() não aplica o filtro "só próprias/atribuídas"
    // (ToDoController@index, ramo `! $isAdmin`) — isola a pergunta de TENANT.
    $role = Role::firstOrCreate(
        ['name' => 'Admin#'.$this->tenant->id, 'guard_name' => 'web'],
        ['business_id' => $this->tenant->id]
    );
    if (! $user->hasRole($role->name)) {
        $user->assignRole($role->name);
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    session()->flush();
    $this->actingAs($user);
});

function etodoInertiaVersion(): string
{
    $manifest = public_path('build-inertia/manifest.json');

    return file_exists($manifest) ? md5_file($manifest) : '1';
}

function etodoCriar(int $bizId, int $createdBy, string $task, string $status = 'new'): int
{
    return (int) DB::table('essentials_to_dos')->insertGetId([
        'business_id' => $bizId,
        'task' => $task,
        'task_id' => 'CT-'.substr(md5($task.uniqid('', true)), 0, 10),
        'date' => now(),
        'status' => $status,
        'priority' => 'medium',
        'created_by' => $createdBy,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/** Resolve o `Inertia::defer` de `todos` — no first render a prop não vem. */
function etodoLinhas($test, array $query = []): array
{
    $url = '/essentials/todo'.($query ? '?'.http_build_query($query) : '');
    $response = $test->withHeaders([
        'X-Inertia' => 'true',
        'X-Requested-With' => 'XMLHttpRequest',
        'X-Inertia-Version' => etodoInertiaVersion(),
        'X-Inertia-Partial-Data' => 'todos',
        'X-Inertia-Partial-Component' => 'Essentials/Todo/Index',
    ])->get($url);

    $response->assertStatus(200);

    return $response->json('props.todos.data') ?? [];
}

it('UC-ETODO-01: a lista abre e traz a tarefa do meu business com nome e status', function () {
    $task = 'UC01 conferir estoque '.uniqid();
    $id = etodoCriar($this->tenant->id, $this->actor->id, $task, 'in_progress');

    $first = $this->withHeaders([
        'X-Inertia' => 'true',
        'X-Requested-With' => 'XMLHttpRequest',
        'X-Inertia-Version' => etodoInertiaVersion(),
    ])->get('/essentials/todo');
    $first->assertStatus(200);
    expect($first->json('component'))->toBe('Essentials/Todo/Index');

    $linha = collect(etodoLinhas($this))->firstWhere('id', $id);
    expect($linha)->not->toBeNull();
    expect($linha['task'])->toBe($task);
    expect($linha['status'])->toBe('in_progress');
});

it('UC-ETODO-02: [T0] tarefa de outro business nunca aparece na lista', function () {
    $minha = etodoCriar($this->tenant->id, $this->actor->id, 'UC02 minha '.uniqid());
    $alheia = etodoCriar($this->adversario->id, $this->actor->id, 'UC02 alheia '.uniqid());

    $ids = collect(etodoLinhas($this))->pluck('id')->all();

    // pré-condição anti-vácuo: a lista não está vazia por outro motivo
    expect($ids)->toContain($minha);
    expect($ids)->not->toContain($alheia);
});

it('UC-ETODO-03: filtrar por status devolve só as tarefas daquele status', function () {
    $concluida = etodoCriar($this->tenant->id, $this->actor->id, 'UC03 concluida '.uniqid(), 'completed');
    $nova = etodoCriar($this->tenant->id, $this->actor->id, 'UC03 nova '.uniqid(), 'new');

    $linhas = collect(etodoLinhas($this, ['status' => 'completed']));
    $ids = $linhas->pluck('id')->all();

    expect($ids)->toContain($concluida);
    expect($ids)->not->toContain($nova);
    expect($linhas->pluck('status')->unique()->values()->all())->toBe(['completed']);
});

it('UC-ETODO-04: trocar o status pelo modal grava o novo status', function () {
    $id = etodoCriar($this->tenant->id, $this->actor->id, 'UC04 trocar '.uniqid(), 'new');

    $resp = $this->put("/essentials/todo/{$id}", [
        'only_status' => 1,
        'status' => 'completed',
    ]);

    // Sucesso e recusa são ambos redirect num form PUT — o que prova é o EFEITO.
    $resp->assertSessionHasNoErrors();
    expect(DB::table('essentials_to_dos')->where('id', $id)->value('status'))->toBe('completed');
});

it('UC-ETODO-05: [T0] trocar status de tarefa de outro business não muda nada', function () {
    $alheia = etodoCriar($this->adversario->id, $this->actor->id, 'UC05 alheia '.uniqid(), 'new');

    $resp = $this->put("/essentials/todo/{$alheia}", [
        'only_status' => 1,
        'status' => 'completed',
    ]);

    expect($resp->status())->toBe(404);
    expect(DB::table('essentials_to_dos')->where('id', $alheia)->value('status'))->toBe('new');
});
