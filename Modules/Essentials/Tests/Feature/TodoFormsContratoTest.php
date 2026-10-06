<?php

declare(strict_types=1);

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Contrato das telas Essentials/Todo/{Create,Edit,Show} — os três `.casos.md` ao lado dos
 * `.tsx`. Os UC derivam dos charters + `ToDoController` (create/store/edit/update/show) e da
 * US-ESS-002/003 do SPEC, nunca do `.tsx` (§5 2026-06-05). Cada `it()` cita o UC-id (G-2).
 *
 * Tier 0 (ADR 0093 + ADR 0358): tenant 98 (fictício) × 2 (alheio do seed). NUNCA biz=4.
 * Admin do tenant: o `scopedQueryForUser` não aplica o filtro "só próprias/atribuídas" —
 * isola a pergunta de TENANT. Fixtures por `DB::table` (o `ToDo` usa `HasBusinessScope`).
 */
const ETDF_BIZ = 98;
const ETDF_BIZ_ALHEIO = 2;
const ETDF_OUTRO_AUTOR = 987654321; // `comment_by` sem FK — autor que não sou eu

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema UltimatePOS requer MySQL.');
    }
    foreach (['essentials_to_dos', 'essentials_todos_users', 'essentials_todo_comments'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Tabela {$t} ausente — rode o migrate do Essentials.");
        }
    }
    $user = User::where('business_id', ETDF_BIZ)->first();
    if (! $user) {
        $this->markTestSkipped('Sem user em business_id=98 — o seed canônico não rodou.');
    }
    $role = Role::firstOrCreate(['name' => 'Admin#'.ETDF_BIZ, 'guard_name' => 'web'], ['business_id' => ETDF_BIZ]);
    if (! $user->hasRole($role->name)) {
        $user->assignRole($role);
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Notification::fake();
    $this->tUser = $user;
    session()->flush();
    $this->actingAs($user);
});

function etdfTarefa(int $biz, int $autor, string $task): int
{
    return (int) DB::table('essentials_to_dos')->insertGetId([
        'business_id' => $biz, 'task' => $task, 'task_id' => 'CT-'.substr(md5(uniqid('', true)), 0, 10),
        'date' => now(), 'status' => 'new', 'priority' => 'medium',
        'created_by' => $autor, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function etdfGet($test, string $url)
{
    try {
        $versao = (string) (new \App\Http\Middleware\HandleInertiaRequests)->version(request());
    } catch (\Throwable $e) {
        $versao = '';
    }

    return $test->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => $versao])->get($url);
}

it('UC-ETDC-01 · criar sem escolher atribuídos deixa a tarefa com o autor, no meu business', function () {
    $task = 'ETDC01 '.uniqid();

    $this->post('/essentials/todo', ['task' => $task, 'date' => now()->format('Y-m-d'), 'priority' => 'high'])
        ->assertSessionHasNoErrors()->assertRedirect();

    $todo = DB::table('essentials_to_dos')->where('task', $task)->first();
    expect($todo)->not->toBeNull();
    expect((int) $todo->business_id)->toBe(ETDF_BIZ);
    expect($todo->status)->toBe('new');
    expect((string) $todo->task_id)->not->toBe('');
    expect(DB::table('essentials_todos_users')->where('todo_id', $todo->id)->pluck('user_id')->map('intval')->all())
        ->toBe([(int) $this->tUser->id]);
});

it('UC-ETDC-02 · o form de criação recebe os status e as prioridades do servidor', function () {
    $res = etdfGet($this, '/essentials/todo/create');

    $res->assertStatus(200)->assertJsonPath('component', 'Essentials/Todo/Create');
    expect(array_column($res->json('props.statuses'), 'value'))->toBe(['new', 'in_progress', 'on_hold', 'completed']);
    expect(array_column($res->json('props.priorities'), 'value'))->toBe(['low', 'medium', 'high', 'urgent']);
});

it('UC-ETDE-01 · salvar a edição grava título e prioridade e volta pro detalhe', function () {
    $id = etdfTarefa(ETDF_BIZ, (int) $this->tUser->id, 'ETDE01 antes '.uniqid());

    etdfGet($this, "/essentials/todo/{$id}/edit")->assertStatus(200)
        ->assertJsonPath('component', 'Essentials/Todo/Edit')->assertJsonPath('props.todo.id', $id);

    $this->put("/essentials/todo/{$id}", [
        'task' => 'ETDE01 depois', 'date' => now()->format('Y-m-d'), 'priority' => 'urgent', 'status' => 'in_progress',
    ])->assertSessionHasNoErrors()->assertRedirect("/essentials/todo/{$id}");

    $row = DB::table('essentials_to_dos')->where('id', $id)->first();
    expect($row->task)->toBe('ETDE01 depois');
    expect($row->priority)->toBe('urgent');
    expect($row->status)->toBe('in_progress');
});

it('UC-ETDE-02 · [T0] editar tarefa de outro business devolve 404 e não muda nada', function () {
    $alheia = etdfTarefa(ETDF_BIZ_ALHEIO, (int) $this->tUser->id, 'ETDE02 alheia '.uniqid());

    expect(etdfGet($this, "/essentials/todo/{$alheia}/edit")->status())->toBe(404);
    $res = $this->put("/essentials/todo/{$alheia}", ['task' => 'invadida', 'date' => now()->format('Y-m-d')]);

    expect($res->status())->toBe(404);
    expect(DB::table('essentials_to_dos')->where('id', $alheia)->value('task'))->not->toBe('invadida');
});

it('UC-ETDS-01 · o detalhe traz os comentários e só o meu pode ser removido', function () {
    $id = etdfTarefa(ETDF_BIZ, (int) $this->tUser->id, 'ETDS01 '.uniqid());
    foreach ([[(int) $this->tUser->id, 'meu'], [ETDF_OUTRO_AUTOR, 'de colega']] as [$autor, $texto]) {
        DB::table('essentials_todo_comments')->insert([
            'task_id' => $id, 'comment' => $texto, 'comment_by' => $autor, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    $res = etdfGet($this, "/essentials/todo/{$id}");
    $res->assertStatus(200)->assertJsonPath('component', 'Essentials/Todo/Show');
    $porTexto = collect($res->json('props.comments'))->keyBy('comment');
    expect($porTexto->keys()->sort()->values()->all())->toBe(['de colega', 'meu']);
    expect($porTexto['meu']['can_delete'])->toBeTrue();
    expect($porTexto['de colega']['can_delete'])->toBeFalse();
});

it('UC-ETDS-02 · [T0] tarefa de outro business não abre', function () {
    $minha = etdfTarefa(ETDF_BIZ, (int) $this->tUser->id, 'ETDS02 minha '.uniqid());
    $alheia = etdfTarefa(ETDF_BIZ_ALHEIO, (int) $this->tUser->id, 'ETDS02 alheia '.uniqid());

    etdfGet($this, "/essentials/todo/{$minha}")->assertStatus(200); // controle positivo
    expect(etdfGet($this, "/essentials/todo/{$alheia}")->status())->toBe(404);
});
