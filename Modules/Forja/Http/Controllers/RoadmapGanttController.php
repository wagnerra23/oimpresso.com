<?php

declare(strict_types=1);

namespace Modules\Forja\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Jana\Services\TaskRegistry\TaskCrudService;

/**
 * RoadmapGanttController — /forja/roadmap-gantt.
 *
 * Timeline cronológica das tasks do cycle (SVAR React Gantt MIT), com sub-issues
 * por módulo e dependency graph (`blocked_by[]`). Porte de
 * `Modules\Jana\Http\Controllers\Admin\RoadmapController` (Onda 5 V1).
 *
 * POR QUE MORA AQUI (ADR 0366 §D-B, ratificada por [W] 2026-08-03): o critério de
 * fronteira é a PERGUNTA que o módulo responde. Esta tela responde "o que a gente
 * está fazendo e o que vence quando" — pergunta da Forja (time interno), não do Jana
 * (produto do cliente). A ADR nomeia o destino textualmente: "usa TaskCrudService/
 * McpTask — é tasks, e tasks é Forja. Mandar pro Governance criaria a 3ª tela de
 * roadmap". Confirmado pela ADR 0367 D4 ("o Gantt vira aba da Forja").
 *
 * ⚠️ NÃO SUBSTITUI o quarter view. `Modules\Forja\Http\Controllers\RoadmapController`
 * (/project-mgmt/roadmap) agrupa EPICS por trimestre; este agrupa TASKS no tempo. A
 * ADR 0367 D7 decidiu que os dois convivem — o quarter view "só sai quando o Gantt
 * provar que substitui (filtro por cycle efetivo + volume domado)". Recibo da
 * não-duplicação: memory/sessions/2026-08-05-duplicacao-roadmap-forja.md.
 *
 * PERMISSÕES INALTERADAS no porte: seguem `jana.mcp.tasks.read` / `.write`, o mesmo
 * par do Jana. Permission Spatie vive por ID DE LINHA — renomear revoga acesso em
 * SILÊNCIO, sem erro e sem log (ADR 0087; reafirmado no §Custo de execução da 0367).
 * Rename é ADR + migration própria, nunca efeito colateral de um move.
 *
 * SCOPE: `mcp_tasks`/`mcp_cycles` são cache canon cross-business (a fonte de verdade é
 * o git, via SPEC.md por módulo) — sem coluna `business_id`, ADR 0093 §exceções
 * repo-wide, igual Triage/Scorecard/ForjaController. O isolamento é a PERMISSION.
 *
 * Ver:
 *  - memory/requisitos/Forja/RUNBOOK-gantt.md
 *  - resources/js/Pages/Forja/Roadmap/Gantt.charter.md
 *  - ADR 0070 (Jira-style tasks) · 0087 (URL/permission congeladas) · 0093 · 0366 · 0367
 */
class RoadmapGanttController extends Controller
{
    /** Teto de linhas por render — anti-cluttered em 1280px (Larissa-friendly). */
    private const MAX_TASKS = 500;

    public function __construct()
    {
        $this->middleware('auth');
        // Leitura do roadmap: permission canônica do task board MCP.
        $this->middleware('can:jana.mcp.tasks.read')->only('index');
        // Reschedule via drag-drop (US-COPI-111 B2, Wagner 2026-07-12): exige WRITE.
        $this->middleware('can:jana.mcp.tasks.write')->only('updateSchedule');
    }

    public function index(Request $request): Response
    {
        // Filtros via query params (preserve-state ao trocar).
        $cycleFilter    = $request->get('cycle');     // 'current' | <id> | null
        $ownerFilter    = $request->get('owner');     // username | null
        $priorityFilter = $request->get('priority');  // 'p0' | 'p1' | 'p2' | 'p3' | null
        $moduleFilter   = $request->get('module');    // 'Jana' | 'Repair' | ... | null

        // 1) Cycles disponíveis (active + planning + closed mais recentes pro filtro).
        $cycles = DB::table('mcp_cycles')
            ->select(['id', 'project_id', 'key', 'name', 'start_date', 'end_date', 'status', 'goal'])
            ->whereNull('deleted_at')
            ->orderByRaw("FIELD(status, 'active', 'planning', 'closed')")
            ->orderByDesc('start_date')
            ->limit(20)
            ->get();

        // 2) Resolve cycle ativo padrão se filtro = 'current' (ou null).
        $activeCycle = $cycles->firstWhere('status', 'active');
        $selectedCycleId = null;

        if ($cycleFilter === 'current' || $cycleFilter === null) {
            $selectedCycleId = $activeCycle?->id;
        } elseif (is_numeric($cycleFilter)) {
            $selectedCycleId = (int) $cycleFilter;
        }

        // 3) Query tasks com filtros aplicados.
        $tasksQuery = DB::table('mcp_tasks')
            ->select([
                'id',
                'task_id',
                'identifier',
                'module',
                'title',
                'description',
                'status',
                'owner',
                'priority',
                'estimate_h',
                'story_points',
                'blocked_by',
                'parent_task_id',
                'cycle_id',
                'project_id',
                'type',
                'due_date',
                'started_at',
                'completed_at',
                'created_at',
                'updated_at',
            ])
            ->whereNotIn('status', ['cancelled']);

        if ($selectedCycleId !== null) {
            $tasksQuery->where('cycle_id', $selectedCycleId);
        }

        if ($ownerFilter) {
            $tasksQuery->where('owner', $ownerFilter);
        }

        if ($priorityFilter && in_array($priorityFilter, ['p0', 'p1', 'p2', 'p3'], true)) {
            $tasksQuery->where('priority', $priorityFilter);
        }

        if ($moduleFilter) {
            $tasksQuery->where('module', $moduleFilter);
        }

        $tasks = $tasksQuery->orderBy('module')
            ->orderByRaw("FIELD(priority, 'p0', 'p1', 'p2', 'p3')")
            ->orderBy('due_date')
            ->limit(self::MAX_TASKS)
            ->get();

        // ⛔ DESENHO CONSCIENTE — NÃO trocar estas closures por Inertia::defer.
        //
        // Wagner 2026-05-25 HOTFIX (herdado do Jana, PR #1550/#1552): com defer, os
        // dropdowns chegavam `undefined` no 1º paint e o .tsx — que desestrutura
        // direto e chama .map() — estourava `TypeError: undefined.map` em PRODUÇÃO.
        //
        // A CLOSURE dá o melhor dos dois mundos (padrão D-14, ref PR #3889): roda no
        // load cheio (1º render nunca vê undefined) e é PULADA no partial reload
        // (`only: ['tasks','filters']`), que é o caminho quente do filtro. Deferir
        // exigiria antes dar default-guard no destructuring do .tsx — as duas
        // mudanças andam juntas ou nenhuma. Ver RUNBOOK-gantt.md §3.
        $owners = fn () => DB::table('mcp_tasks')
            ->select('owner')
            ->whereNotNull('owner')
            ->distinct()
            ->orderBy('owner')
            ->pluck('owner');

        $modules = fn () => DB::table('mcp_tasks')
            ->select('module')
            ->whereNotNull('module')
            ->distinct()
            ->orderBy('module')
            ->pluck('module');

        return Inertia::render('Forja/Roadmap/Gantt', [
            'cycles' => $cycles->map(function ($c) {
                return [
                    'id'         => (int) $c->id,
                    'key'        => $c->key,
                    'name'       => $c->name,
                    'status'     => $c->status,
                    'start_date' => $c->start_date,
                    'end_date'   => $c->end_date,
                    'goal'       => $c->goal,
                ];
            })->values(),
            'tasks' => $tasks->map(function ($t) {
                return [
                    'id'             => (int) $t->id,
                    'task_id'        => $t->task_id,
                    'identifier'     => $t->identifier,
                    'module'         => $t->module,
                    'title'          => $t->title,
                    'description'    => $t->description,
                    'status'         => $t->status,
                    'owner'          => $t->owner,
                    'priority'       => $t->priority,
                    'type'           => $t->type,
                    'estimate_h'     => $t->estimate_h !== null ? (float) $t->estimate_h : null,
                    'story_points'   => $t->story_points !== null ? (float) $t->story_points : null,
                    'parent_task_id' => $t->parent_task_id !== null ? (int) $t->parent_task_id : null,
                    'cycle_id'       => $t->cycle_id !== null ? (int) $t->cycle_id : null,
                    'project_id'     => $t->project_id !== null ? (int) $t->project_id : null,
                    'blocked_by'     => $this->decodeJsonArray($t->blocked_by),
                    'due_date'       => $t->due_date,
                    'started_at'     => $t->started_at,
                    'completed_at'   => $t->completed_at,
                    'created_at'     => $t->created_at,
                    'updated_at'     => $t->updated_at,
                ];
            })->values(),
            'filters' => [
                'cycle'    => $selectedCycleId,
                'owner'    => $ownerFilter,
                'priority' => $priorityFilter,
                'module'   => $moduleFilter,
            ],
            'owners'  => $owners,
            'modules' => $modules,
            'active_cycle_id' => $activeCycle?->id,
            // Drag-drop reschedule só habilita se o user tem write (US-COPI-111 B2).
            'can_edit' => (bool) $request->user()?->can('jana.mcp.tasks.write'),
        ]);
    }

    /**
     * Reschedule via drag-drop no Gantt (US-COPI-111 B2 — Wagner 2026-07-12).
     *
     * Move APENAS o `due_date` (prazo) — a ponta arrastável da barra. `started_at`
     * é lifecycle-managed (auto-setado quando task→'doing', ADR 0070), NÃO editável
     * manualmente: por isso o drag reagenda o PRAZO, não o início. Reusa o
     * `TaskCrudService::update` canônico (mesma via do MCP `tasks-update`): atômico
     * (lock de linha), audita via OTel + McpTaskEvent, allowlist de campos.
     *
     * O Service permanece em `Modules\Jana` — a Forja só IMPORTA. Mesmo precedente do
     * `Forja\RoadmapController`, que importa `Modules\Jana\Entities\Mcp\McpTask`. Mover
     * as 30 `Mcp*` é o item #4 da ADR 0366 §D-C, que aquela ADR NÃO autoriza.
     *
     * Recebe o `task_id` STRING (ex US-COPI-110), não o id numérico do Gantt — o
     * frontend mapeia via `$payload.task_id` antes do PATCH.
     */
    public function updateSchedule(Request $request, string $taskId): RedirectResponse
    {
        $data = $request->validate([
            'due_date' => ['required', 'date'],
        ]);

        $user = $request->user();

        app(TaskCrudService::class)->update(
            $taskId,
            ['due_date' => $data['due_date']],
            author: $user?->name ?? 'web',
            principal: $user?->email ?? $user?->name,
        );

        // Inertia: volta e re-busca só `tasks` (partial reload no frontend).
        return back();
    }

    /**
     * Decodifica coluna JSON `blocked_by` com segurança (vem como string do PDO).
     */
    protected function decodeJsonArray(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (is_array($value)) {
            return $value;
        }

        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : [];
    }
}
