<?php

declare(strict_types=1);

use App\Http\Middleware\HandleInertiaRequests;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Modules\Forja\Database\Seeders\ForjaDemoTicketsSeeder;
use Modules\Jana\Entities\Mcp\McpProject;
use Modules\Jana\Entities\Mcp\McpTask;
use Modules\Jana\Entities\Mcp\McpTaskEvent;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Forja · Triagem (aba 1 do cockpit /forja) — contrato dos UC-FORJA-08, 09 e 10.
 *
 * Fonte dos asserts: o TEXTO dos UCs em
 * Modules/Forja/Resources/js/Pages/team-mcp/Forja/Cockpit.casos.md — não o código.
 *   - UC-FORJA-08: `/forja` projeta `mcp_tasks` project=FORJA em estado de triagem, via
 *     prop deferida `tickets`; após o `ForjaDemoTicketsSeeder` aparecem FORJA-152 (Tela),
 *     FORJA-151 (Bug) e FORJA-150 (Refino), selo [CC]; a aba mostra o badge da contagem.
 *   - UC-FORJA-09: o dossiê (`GET /forja/{id}/dossier`) carrega dados reais; Aprovar →
 *     backlog vira `todo` e EXIGE dono+prio; Rejeitar → `cancelled`; Fundir → duplicata
 *     (cancela + evento apontando o destino).
 *   - UC-FORJA-10: listar e abrir o dossiê é read-only; escrita só pelas rotas de ação.
 *     A perna "sob AlertDialog de confirmação [W]" é de UI e NÃO é provada aqui — ela
 *     segue manual no casos.md. O que o backend pode provar, este arquivo prova: GET não
 *     escreve, e as ações não respondem a GET (prefetch/navegação nunca muta).
 *
 * Discriminação (não é `isSuccessful` vácuo):
 *   - UC-08 inclui 3 fixtures que o filtro TEM de recusar: issue já triada do FORJA
 *     (FORJA-142), task FORJA em `done`, e task em triagem de OUTRO project. Um filtro
 *     que perdesse o `project_id` ou o `whereNotIn(done,cancelled)` reprova.
 *   - Toda recusa (422) é seguida, no MESMO caso, da ação válida dando certo — prova que
 *     o caminho de escrita está vivo e que o estado preservado veio da trava, não de um
 *     controller inerte (pré-condição anti-vácuo, mesma do AprovacoesMesaTest).
 *
 * ⚠️ `buildTriagemPayload` memoiza num `static $cache` por project id DENTRO do processo.
 * Por isso só UM caso deste arquivo pede a prop `tickets` — um segundo pedido no mesmo
 * processo (CT 100, banco persistente, mesmo id do FORJA) leria a lista do primeiro.
 *
 * Tenant: fictício 98 (`seededTenant()`, ADR 0358) — NUNCA biz=4. As tabelas `mcp_*` são
 * repo-wide por design (ADR 0070/0093); o tenant entra só pela stack UltimatePOS.
 * Stack exige schema MySQL → em sqlite PULA. Skip sai exit 0: leia assertions (LC-13).
 *
 * @see Modules\Forja\Http\Controllers\ForjaController (triagem · dossier · aprovar · rejeitar · fundir)
 */

const FORJA_TRIAGEM_PERMISSION = 'jana.mcp.usage.all';

function forjaTriagemExigeSchema(): void
{
    if (DB::connection()->getDriverName() === 'sqlite') {
        test()->markTestSkipped('SQLite-incompatível: stack UltimatePOS exige schema MySQL (ADR 0358).');
    }
    foreach (['users', 'permissions', 'mcp_tasks', 'mcp_task_events', 'mcp_jira_projects'] as $tabela) {
        if (! Schema::hasTable($tabela)) {
            test()->markTestSkipped("Tabela {$tabela} ausente — rode com DB_CONNECTION=mysql.");
        }
    }
}

/** Usuário NOVO do tenant fictício 98 com exatamente a permission da Forja. */
function forjaTriagemUsuario(): User
{
    forjaTriagemExigeSchema();

    $business = test()->seededTenant(); // biz=98 fictício (ADR 0358)
    $user = test()->usuarioComPermissoes([FORJA_TRIAGEM_PERMISSION], $business);
    // CheckUserLogin barra quem não é `user`/`allow_login=1` — não é o que se testa aqui.
    $user->forceFill(['user_type' => 'user', 'allow_login' => 1])->save();

    session([
        'user.business_id' => $business->id,
        'business.id'      => $business->id,
        'user.id'          => $user->id,
    ]);

    return $user;
}

function forjaTriagemTask(string $taskId, array $attrs = []): McpTask
{
    $task = new McpTask();
    $task->task_id = $taskId;
    $task->identifier = $taskId;
    $task->title = $attrs['title'] ?? 'Fixture triagem '.$taskId;
    $task->module = $attrs['module'] ?? 'ForjaT03';
    $task->status = $attrs['status'] ?? 'backlog';
    $task->type = 'task';
    $task->owner = $attrs['owner'] ?? null;
    $task->priority = $attrs['priority'] ?? null;
    if (array_key_exists('project_id', $attrs)) {
        $task->project_id = $attrs['project_id'];
    }
    $task->save();

    return $task->fresh();
}

/** Props deferidas da landing /forja (partial reload — `Inertia::defer` não vem no 1º paint). */
function forjaTriagemPropsDeferidas(User $user, string $only): array
{
    $response = test()->actingAs($user)->withHeaders([
        'X-Inertia'                   => 'true',
        'X-Requested-With'            => 'XMLHttpRequest', // o client Inertia manda sempre (§5 2026-09-08)
        'X-Inertia-Version'           => app(HandleInertiaRequests::class)->version(request()),
        'X-Inertia-Partial-Component' => 'team-mcp/Forja/Cockpit',
        'X-Inertia-Partial-Data'      => $only,
    ])->get('/forja');

    $response->assertStatus(200);
    $page = json_decode($response->getContent(), true);
    expect($page)->toBeArray();

    return $page['props'] ?? [];
}

// ---------------------------------------------------------------------------
// UC-FORJA-08 — a Triagem lista as propostas FORJA (e só elas)
// ---------------------------------------------------------------------------

it('UC-FORJA-08 · /forja lista as 3 propostas do seeder e recusa issue triada, done e outro project', function () {
    $user = forjaTriagemUsuario();

    (new ForjaDemoTicketsSeeder())->run();
    $forjaId = McpProject::where('key', 'FORJA')->value('id');
    expect($forjaId)->not->toBeNull('o seeder deveria garantir o project FORJA');

    // Discriminantes: as três TÊM de ficar fora.
    forjaTriagemTask('TRIT03-DONE', ['status' => 'done', 'project_id' => $forjaId]); // sem owner, mas fechada
    $outro = McpProject::updateOrCreate(['key' => 'TRIT03'], ['name' => 'Fixture UC-FORJA-08', 'status' => 'active']);
    forjaTriagemTask('TRIT03-OUTRO', ['project_id' => $outro->id]);                   // triagem, mas de outro project

    $props = forjaTriagemPropsDeferidas($user, 'tickets,triagemCount');

    expect($props)->toHaveKey('tickets');
    expect($props)->toHaveKey('triagemCount');
    $porId = collect($props['tickets'])->keyBy('display_id');

    // As 3 propostas, com o tipo do protótipo e o selo [CC] (UC-FORJA-08).
    $esperado = ['FORJA-152' => 'Tela', 'FORJA-151' => 'Bug', 'FORJA-150' => 'Refino'];
    foreach ($esperado as $id => $tipo) {
        expect($porId->has($id))->toBeTrue("{$id} é proposta em triagem e tem de aparecer na aba");
        expect($porId[$id]['forja_tipo'])->toBe($tipo);
        expect($porId[$id]['forja_papel'])->toBe('CC');
        expect($porId[$id]['needs_owner'])->toBeTrue();
    }

    // O que NÃO é triagem do FORJA não pode vazar pra aba.
    expect($porId->has('FORJA-142'))->toBeFalse('FORJA-142 já tem dono+prio+todo — é Backlog, não Triagem');
    expect($porId->has('TRIT03-DONE'))->toBeFalse('task done não volta pra triagem');
    expect($porId->has('TRIT03-OUTRO'))->toBeFalse('a aba é do project FORJA — outro project não entra');

    // O badge da aba conta a MESMA lista que ela desenha.
    expect($props['triagemCount'])->toBe(count($props['tickets']));
});

// ---------------------------------------------------------------------------
// UC-FORJA-09 — dossiê com dados reais + Aprovar / Rejeitar / Fundir
// ---------------------------------------------------------------------------

it('UC-FORJA-09 · o dossiê traz duplicata do módulo e a atividade real da task', function () {
    $user = forjaTriagemUsuario();

    $alvo = forjaTriagemTask('TRIT03-DOS', ['module' => 'ForjaT03Dos']);
    forjaTriagemTask('TRIT03-DOS-DUP', ['module' => 'ForjaT03Dos']);
    McpTaskEvent::log($alvo->task_id, 'field_updated', null, null, 'fixture', 'nota-fixture-UC-FORJA-09');

    $resp = $this->actingAs($user)->getJson('/forja/TRIT03-DOS/dossier');

    $resp->assertStatus(200);
    expect($resp->json('task.task_id'))->toBe('TRIT03-DOS');
    expect(collect($resp->json('duplicatas'))->pluck('task_id')->all())->toContain('TRIT03-DOS-DUP');
    expect(collect($resp->json('atividade'))->pluck('note')->all())->toContain('nota-fixture-UC-FORJA-09');
    // Sem dono nem prioridade, o dossiê não oferece Aprovar (UC-FORJA-09: "exige dono+prio").
    expect($resp->json('pode_aprovar'))->toBeFalse();

    $this->actingAs($user)->getJson('/forja/NAO-EXISTE-T03/dossier')->assertStatus(404);
});

it('UC-FORJA-09 · Aprovar exige dono+prio (422 preserva o estado) e, com eles, leva o backlog pra todo', function () {
    $user = forjaTriagemUsuario();
    forjaTriagemTask('TRIT03-APR'); // backlog, sem dono, sem prio

    $this->actingAs($user)->postJson('/forja/TRIT03-APR/aprovar')->assertStatus(422);
    expect(McpTask::where('task_id', 'TRIT03-APR')->value('status'))->toBe('backlog');

    // Âncora positiva no MESMO caso: com dono+prio a mesma ação escreve.
    McpTask::where('task_id', 'TRIT03-APR')->update(['owner' => 'wagner', 'priority' => 'p2']);
    $this->actingAs($user)->postJson('/forja/TRIT03-APR/aprovar')->assertStatus(200);

    expect(McpTask::where('task_id', 'TRIT03-APR')->value('status'))->toBe('todo');
    expect(McpTaskEvent::where('task_id', 'TRIT03-APR')->where('event_type', 'status_changed')
        ->where('to_value', 'todo')->exists())->toBeTrue('a transição tem de ficar na trilha de eventos');
});

it('UC-FORJA-09 · Rejeitar cancela a proposta', function () {
    $user = forjaTriagemUsuario();
    forjaTriagemTask('TRIT03-REJ');

    $this->actingAs($user)->postJson('/forja/TRIT03-REJ/rejeitar')->assertStatus(200);

    expect(McpTask::where('task_id', 'TRIT03-REJ')->value('status'))->toBe('cancelled');
});

it('UC-FORJA-09 · Fundir exige destino válido e, com ele, cancela a duplicata e registra o destino', function () {
    $user = forjaTriagemUsuario();
    forjaTriagemTask('TRIT03-FUN');
    forjaTriagemTask('TRIT03-FUN-ALVO');

    // Sem destino e fundindo nela mesma: recusa, e nada muda.
    $this->actingAs($user)->postJson('/forja/TRIT03-FUN/fundir')->assertStatus(422);
    $this->actingAs($user)->postJson('/forja/TRIT03-FUN/fundir', ['target_task_id' => 'TRIT03-FUN'])->assertStatus(422);
    expect(McpTask::where('task_id', 'TRIT03-FUN')->value('status'))->toBe('backlog');

    // Âncora positiva: com destino válido a fusão acontece.
    $this->actingAs($user)->postJson('/forja/TRIT03-FUN/fundir', ['target_task_id' => 'TRIT03-FUN-ALVO'])
        ->assertStatus(200);

    expect(McpTask::where('task_id', 'TRIT03-FUN')->value('status'))->toBe('cancelled');
    expect(McpTask::where('task_id', 'TRIT03-FUN-ALVO')->value('status'))->toBe('backlog');
    expect(McpTaskEvent::where('task_id', 'TRIT03-FUN')->where('to_value', 'TRIT03-FUN-ALVO')->exists())
        ->toBeTrue('a fusão registra pra qual task a duplicata foi');
});

// ---------------------------------------------------------------------------
// UC-FORJA-10 — abrir o dossiê não escreve; ações não respondem a GET
// ---------------------------------------------------------------------------

it('UC-FORJA-10 · abrir o dossiê não muda a task nem cria evento', function () {
    $user = forjaTriagemUsuario();
    forjaTriagemTask('TRIT03-RO');

    $antes = (array) DB::table('mcp_tasks')->where('task_id', 'TRIT03-RO')
        ->first(['status', 'owner', 'priority', 'updated_at']);
    $eventosAntes = McpTaskEvent::where('task_id', 'TRIT03-RO')->count();

    $resp = $this->actingAs($user)->getJson('/forja/TRIT03-RO/dossier');

    // Âncora positiva: a requisição chegou ao dossiê DESTA task (não é um read-only por 403/404).
    $resp->assertStatus(200);
    expect($resp->json('task.task_id'))->toBe('TRIT03-RO');

    $depois = (array) DB::table('mcp_tasks')->where('task_id', 'TRIT03-RO')
        ->first(['status', 'owner', 'priority', 'updated_at']);
    expect($depois)->toEqual($antes);
    expect(McpTaskEvent::where('task_id', 'TRIT03-RO')->count())->toBe($eventosAntes);
});

it('UC-FORJA-10 · dossiê é GET-only e Aprovar/Rejeitar/Fundir são POST-only', function () {
    // Lê o registro de rotas — roda em qualquer driver, inclusive sqlite.
    $verbos = static function (string $nome): array {
        $rota = Route::getRoutes()->getByName($nome);
        expect($rota)->not->toBeNull("rota {$nome} deve estar registrada");

        return array_values(array_diff($rota->methods(), ['HEAD']));
    };

    expect($verbos('forja.dossier'))->toBe(['GET']);
    foreach (['forja.aprovar', 'forja.rejeitar', 'forja.fundir'] as $acao) {
        // Se uma ação aceitasse GET, um prefetch ou um link mutaria sem confirmação [W].
        expect($verbos($acao))->toBe(['POST']);
    }
});
