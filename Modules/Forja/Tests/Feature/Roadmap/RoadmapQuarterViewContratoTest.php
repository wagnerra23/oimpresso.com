<?php

declare(strict_types=1);

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class, DatabaseTransactions::class);

// @covers-us US-TR-203 — Roadmap view (epics em quarters).

/**
 * Contrato da tela /project-mgmt/roadmap (quarter view por EPIC) — `RoadmapController@index`.
 *
 * UC-RQV-01 `[T0]` — quem não é do time não vê o roadmap (anônimo → login; sem permissão → 403).
 * UC-RQV-02        — os epics chegam agrupados por quarter; epic sem quarter cai em "Sem quarter".
 * UC-RQV-03        — o progresso do epic é done/total das tasks dele, em %.
 *
 * Os UC derivam do `Index.charter.md` (Mission/Goals/Non-Goals), da US-TR-203
 * (memory/requisitos/TaskRegistry/SPEC.md) e da ADR 0367 D7 — nunca do `.tsx`.
 * Trio: Modules/Forja/Resources/js/Pages/Forja/Roadmap/{Index.charter.md,Index.casos.md}
 *
 * A tela CONVIVE com o Gantt (/forja/roadmap-gantt) por decisão registrada na ADR 0367 D7;
 * o Gantt tem casos/testes próprios (RoadmapGanttControllerTest) e este arquivo não o toca.
 *
 * Tenant: 98 (ADR 0358), nunca biz=4. `mcp_epics`/`mcp_tasks` não têm business_id — o
 * isolamento é a permission `jana.mcp.usage.all` (ADR 0093 §exceções), e é ela que o
 * UC-RQV-01 trava. DatabaseTransactions: nada persiste no CT 100.
 *
 * ⛔ Não rodar local — Pest roda no CT 100 ou no CI (proibicoes.md §Ambiente).
 * Skip sai exit 0: leia as ASSERTIONS, não "0 failed" (LC-13).
 */
const RQV_ROTA = '/project-mgmt/roadmap';
const RQV_COMPONENTE = 'Forja/Roadmap/Index';

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema UltimatePOS requer MySQL (ADR 0358).');
    }
    foreach (['mcp_jira_projects', 'mcp_epics', 'mcp_tasks'] as $tabela) {
        if (! Schema::hasTable($tabela)) {
            $this->markTestSkipped("Tabela {$tabela} ausente — schema baseline não aplicado.");
        }
    }
});

/** Projeto Jira-style isolado: key única por teste (o controller resolve por `?project=`). */
function rqvProjeto(): array
{
    $key = 'TRQV'.strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
    $id = DB::table('mcp_jira_projects')->insertGetId([
        'key'        => $key,
        'name'       => "Projeto teste {$key}",
        'status'     => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return [$id, $key];
}

function rqvEpic(int $projectId, string $key, ?string $quarter, string $status = 'active'): int
{
    return DB::table('mcp_epics')->insertGetId([
        'project_id'     => $projectId,
        'key'            => $key,
        'title'          => "Epic {$key}",
        'target_quarter' => $quarter,
        'status'         => $status,
        'created_at'     => now(),
        'updated_at'     => now(),
    ]);
}

function rqvTask(int $epicId, string $sufixo, string $status): void
{
    $taskId = '__test_rqv__'.$sufixo.'_'.bin2hex(random_bytes(3));
    DB::table('mcp_tasks')->insert([
        'task_id'     => $taskId,
        'module'      => 'Forja',
        'title'       => "Task {$taskId}",
        'status'      => $status,
        'epic_id'     => $epicId,
        'source_path' => "memory/requisitos/Forja/SPEC.md#{$taskId}",
        'parsed_at'   => now(),
        'created_at'  => now(),
        'updated_at'  => now(),
    ]);
}

function rqvVersao(): string
{
    $manifest = public_path('build-inertia/manifest.json');

    return file_exists($manifest) ? md5_file($manifest) : '1';
}

/** Resolve as props deferidas `quarters`/`kpis` com os headers que o navegador manda. */
function rqvPayload($test, string $projectKey): array
{
    $resposta = $test->withHeaders([
        'X-Requested-With'            => 'XMLHttpRequest',
        'Accept'                      => 'text/html, application/xhtml+xml',
        'X-Inertia'                   => 'true',
        'X-Inertia-Version'           => rqvVersao(),
        'X-Inertia-Partial-Component' => RQV_COMPONENTE,
        'X-Inertia-Partial-Data'      => 'quarters,kpis',
    ])->get(RQV_ROTA.'?project='.$projectKey);

    $resposta->assertStatus(200);
    $resposta->assertJsonPath('component', RQV_COMPONENTE);

    return $resposta->json('props') ?? [];
}

it('UC-RQV-01 · quem não é do time não vê o roadmap: anônimo vai pro login e logado sem permissão recebe 403', function () {
    // Anônimo — o roadmap expõe o planejamento do time; não é público.
    expect($this->get(RQV_ROTA)->status())->toBeIn([302, 401]);

    // Logado no tenant 98, SEM `jana.mcp.usage.all`. Garante que a permission existe no
    // banco (senão o `can:` responderia 403 por ausência da linha, não por falta de grant).
    Permission::findOrCreate('jana.mcp.usage.all', 'web');
    $semPermissao = $this->usuarioComPermissoes([]);
    expect($semPermissao->can('jana.mcp.usage.all'))->toBeFalse();

    $this->actingAs($semPermissao);
    expect($this->get(RQV_ROTA)->status())->toBe(403);

    // Controle positivo: com a permission a MESMA rota abre — o 403 acima é do gate.
    $comPermissao = $this->usuarioComPermissoes(['jana.mcp.usage.all']);
    $this->actingAs($comPermissao);
    $ok = $this->withHeaders([
        'X-Requested-With'  => 'XMLHttpRequest',
        'X-Inertia'         => 'true',
        'X-Inertia-Version' => rqvVersao(),
    ])->get(RQV_ROTA);
    expect($ok->status())->toBe(200);
    expect($ok->json('component'))->toBe(RQV_COMPONENTE);
});

it('UC-RQV-02 · os epics chegam agrupados por quarter e o epic sem quarter cai em "Sem quarter"', function () {
    [$projectId, $key] = rqvProjeto();
    rqvEpic($projectId, 'RQV-A', 'Q1-2031', 'active');
    rqvEpic($projectId, 'RQV-B', 'Q1-2031', 'planning');
    rqvEpic($projectId, 'RQV-C', 'Q3-2031', 'done');
    rqvEpic($projectId, 'RQV-D', null, 'planning');

    $this->actingAs($this->usuarioComPermissoes(['jana.mcp.usage.all']));
    $props = rqvPayload($this, $key);

    $porQuarter = collect($props['quarters'] ?? [])
        ->mapWithKeys(fn ($q) => [$q['key'] => collect($q['epics'])->pluck('key')->sort()->values()->all()])
        ->all();

    expect($porQuarter)->toHaveKey('Q1-2031');
    expect($porQuarter['Q1-2031'])->toBe(['RQV-A', 'RQV-B']);
    expect($porQuarter)->toHaveKey('Q3-2031');
    expect($porQuarter['Q3-2031'])->toBe(['RQV-C']);
    expect($porQuarter)->toHaveKey('Sem quarter');
    expect($porQuarter['Sem quarter'])->toBe(['RQV-D']);

    // Cada epic aparece em UMA coluna só (agrupar não duplica).
    $todos = collect($porQuarter)->flatten()->all();
    expect(count($todos))->toBe(4);
    expect(count(array_unique($todos)))->toBe(4);
});

it('UC-RQV-03 · o progresso do epic é done/total das tasks dele, em porcentagem', function () {
    [$projectId, $key] = rqvProjeto();
    $epic = rqvEpic($projectId, 'RQV-P', 'Q2-2031', 'active');
    $outro = rqvEpic($projectId, 'RQV-Q', 'Q2-2031', 'active');

    // Epic P: 4 tasks — 1 done, 1 cancelada, 2 abertas → 1/4 = 25%.
    rqvTask($epic, 'p_done', 'done');
    rqvTask($epic, 'p_cancel', 'cancelled');
    rqvTask($epic, 'p_todo1', 'todo');
    rqvTask($epic, 'p_doing', 'doing');
    // Epic Q: 2 de 2 done → 100%. Valor DIFERENTE do P de propósito: um agregado que
    // ignorasse o vínculo epic→task devolveria o mesmo número nos dois (§5 2026-09-05).
    rqvTask($outro, 'q_done1', 'done');
    rqvTask($outro, 'q_done2', 'done');

    $this->actingAs($this->usuarioComPermissoes(['jana.mcp.usage.all']));
    $props = rqvPayload($this, $key);

    $epics = collect($props['quarters'] ?? [])->flatMap(fn ($q) => $q['epics'])->keyBy('key')->all();

    expect($epics)->toHaveKey('RQV-P');
    expect($epics['RQV-P']['tasks']['total'])->toBe(4);
    expect($epics['RQV-P']['tasks']['done'])->toBe(1);
    expect($epics['RQV-P']['tasks']['percent'])->toBe(25);

    expect($epics)->toHaveKey('RQV-Q');
    expect($epics['RQV-Q']['tasks']['total'])->toBe(2);
    expect($epics['RQV-Q']['tasks']['done'])->toBe(2);
    expect($epics['RQV-Q']['tasks']['percent'])->toBe(100);
});
