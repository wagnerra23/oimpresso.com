<?php

namespace Modules\ProjectMgmt\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Entities\Mcp\McpCcSession;
use Modules\Jana\Entities\Mcp\McpCycle;
use Modules\Jana\Entities\Mcp\McpEpic;
use Modules\Jana\Entities\Mcp\McpMemoryDocument;
use Modules\Jana\Entities\Mcp\McpProject;
use Modules\Jana\Entities\Mcp\McpTask;
use Modules\Jana\Entities\Mcp\McpTaskEvent;
use Modules\Jana\Services\TaskRegistry\TaskCrudService;

/**
 * TriageController — /project-mgmt/triage (US-TR-301..303, SPEC-UI-FASE7 Onda 2).
 *
 * Superfície humana da tool MCP `triage`: lista as tasks órfãs (sem owner OU
 * sem priority OU status=backlog) e permite atribuir owner/prioridade/cycle/epic
 * inline sem abrir a task. A lista DEVE bater 1:1 com a tool `triage`
 * (mesmo filtro = `McpTask::triage()` scope, mesma exclusão de done/cancelled).
 *
 * Multi-tenant (ADR 0093 + ADR 0070): mcp_tasks é governança GLOBAL repo-wide
 * (sem business_id por design). O escopo aqui é por-projeto (resolveProject),
 * não por business — idêntico ao Board/Backlog/MyWork.
 *
 * Permissão: copiloto.mcp.usage.all (mesmo padrão do Board/MyWork).
 */
class TriageController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('can:copiloto.mcp.usage.all');
    }

    public function index(Request $request): Response
    {
        $project   = $this->resolveProject($request);
        $projectId = $project?->id;

        // RUNBOOK-inertia-defer-pattern.md (Wave 11 D6.a) — defer queries pesadas.
        // `project`/`filters` cheap eager. tasks/kpis compartilham a query órfã →
        // 1 closure memoizada. epics/cycles/owners alimentam os dropdowns de
        // atribuição inline → closures separadas pra partial reload.
        return Inertia::render('ProjectMgmt/Triage/Index', [
            'project' => $project ? ['id' => $project->id, 'key' => $project->key, 'name' => $project->name] : null,
            'tasks'   => Inertia::defer(fn () => $this->buildTriagePayload($projectId)['tasks']),
            'kpis'    => Inertia::defer(fn () => $this->buildTriagePayload($projectId)['kpis']),
            'cycles'  => Inertia::defer(fn () => $this->buildCyclesPayload($projectId)),
            'epics'   => Inertia::defer(fn () => $this->buildEpicsPayload($projectId)),
            'owners'  => Inertia::defer(fn () => $this->buildOwnersPayload($projectId)),
            'filters' => ['project' => $project?->key],
        ]);
    }

    /**
     * Constrói tasks órfãs + kpis (compartilham a mesma query). Memoiza por
     * projectId pra não dobrar a query quando ambos requested numa render.
     *
     * Filtro = paridade EXATA com Modules/Jana/Mcp/Tools/TriageTool.php:
     *   McpTask::triage() = (owner IS NULL OR priority IS NULL OR status='backlog')
     *   + whereNotIn status [done, cancelled]
     *   + orderByDesc created_at.
     *
     * @return array{tasks: \Illuminate\Support\Collection, kpis: array<string,int>}
     */
    protected function buildTriagePayload(?int $projectId): array
    {
        static $cache = [];
        $key = (string) ($projectId ?? 'all');
        if (isset($cache[$key])) {
            return $cache[$key];
        }

        $tasks = McpTask::triage()
            ->whereNotIn('status', ['done', 'cancelled'])
            ->when($projectId, fn ($q) => $q->where('project_id', $projectId))
            ->orderByDesc('created_at')
            ->limit(200)
            ->get()
            ->map(fn (McpTask $t) => $this->serializeTask($t));

        $kpis = [
            'total'      => $tasks->count(),
            'sem_owner'  => $tasks->where('needs_owner', true)->count(),
            'sem_prio'   => $tasks->where('needs_prio', true)->count(),
            'backlog'    => $tasks->where('is_backlog', true)->count(),
        ];

        $cache[$key] = ['tasks' => $tasks, 'kpis' => $kpis];
        return $cache[$key];
    }

    /** @return array<int,array<string,mixed>> */
    protected function buildCyclesPayload(?int $projectId): array
    {
        if (! $projectId) {
            return [];
        }
        return McpCycle::where('project_id', $projectId)
            ->whereIn('status', ['planning', 'active'])
            ->orderBy('start_date', 'desc')
            ->get()
            ->map(fn (McpCycle $c) => [
                'id'        => $c->id,
                'key'       => $c->key,
                'name'      => $c->name,
                'status'    => $c->status,
                'is_active' => $c->status === 'active',
            ])
            ->all();
    }

    /** @return array<int,array{id:int,key:string,title:string}> */
    protected function buildEpicsPayload(?int $projectId): array
    {
        if (! $projectId) {
            return [];
        }
        return McpEpic::where('project_id', $projectId)
            ->whereIn('status', ['planning', 'active'])
            ->orderBy('sort_order')
            ->orderBy('key')
            ->get()
            ->map(fn (McpEpic $e) => ['id' => $e->id, 'key' => $e->key, 'title' => $e->title])
            ->all();
    }

    /** @return array<int,string> — owners já existentes (autocomplete de atribuição). */
    protected function buildOwnersPayload(?int $projectId): array
    {
        return McpTask::query()
            ->when($projectId, fn ($q) => $q->where('project_id', $projectId))
            ->whereNotNull('owner')
            ->distinct()
            ->orderBy('owner')
            ->pluck('owner')
            ->all();
    }

    /**
     * PATCH /project-mgmt/triage/{taskId}/assign — US-TR-302/303.
     *
     * Atribuição inline de owner + prioridade (+ cycle/epic opcional) sem abrir
     * a task. Reusa TaskCrudService::update() — MESMA via que a tool MCP
     * `tasks-update` — então gera mcp_task_events (assigned/field_updated) e
     * dispara McpInboxNotification pro novo owner (paridade UI ↔ tool).
     *
     * Aceita só o subset relevante de Triage; valida priority/cycle/epic.
     */
    public function assign(Request $request, string $taskId): JsonResponse
    {
        $input = $request->only(['owner', 'priority', 'cycle_id', 'epic_id']);

        $fields = [];

        // owner — string vazia limpa (TaskCrudService converte '' → null)
        if ($request->has('owner')) {
            $owner = trim((string) ($input['owner'] ?? ''));
            $fields['owner'] = $owner;
        }

        // priority — precisa ser canônica
        if ($request->has('priority')) {
            $priority = (string) ($input['priority'] ?? '');
            if ($priority !== '' && ! in_array($priority, McpTask::PRIORITIES, true)) {
                return response()->json(['error' => "Priority '{$priority}' inválida."], 422);
            }
            $fields['priority'] = $priority;
        }

        // cycle_id — null limpa, senão precisa existir
        if ($request->has('cycle_id')) {
            $cycleId = $input['cycle_id'];
            if ($cycleId === null || $cycleId === '' || $cycleId === 0 || $cycleId === '0') {
                $fields['cycle_id'] = null;
            } else {
                if (! McpCycle::whereKey((int) $cycleId)->exists()) {
                    return response()->json(['error' => "Cycle '{$cycleId}' não encontrado."], 422);
                }
                $fields['cycle_id'] = (int) $cycleId;
            }
        }

        // epic_id — null limpa, senão precisa existir
        if ($request->has('epic_id')) {
            $epicId = $input['epic_id'];
            if ($epicId === null || $epicId === '' || $epicId === 0 || $epicId === '0') {
                $fields['epic_id'] = null;
            } else {
                if (! McpEpic::whereKey((int) $epicId)->exists()) {
                    return response()->json(['error' => "Epic '{$epicId}' não encontrado."], 422);
                }
                $fields['epic_id'] = (int) $epicId;
            }
        }

        if (empty($fields)) {
            return response()->json(['error' => 'Nada pra atribuir: informe owner, priority, cycle_id ou epic_id.'], 422);
        }

        $author = $this->resolveAuthor($request);

        try {
            $result = app(TaskCrudService::class)->update($taskId, $fields, $author);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        }

        $task = $result['task'];

        // Recalcula se ainda é órfã (some da lista no front se deixou de ser).
        $stillTriage = $task->owner === null
            || $task->priority === null
            || $task->status === 'backlog';

        return response()->json([
            'ok'           => true,
            'task'         => $this->serializeTask($task),
            'still_triage' => $stillTriage,
        ]);
    }

    // ---------- Forja PR-5a · Analista (dossiê + ações [W] aprova) ----------

    /**
     * GET /project-mgmt/triage/{taskId}/dossier — read-only.
     *
     * Dossiê do Analista: SÓ dados reais. Duplicatas (mesmo módulo), atividade
     * (mcp_task_events), cross-link de docs/ADRs (mcp_memory_documents) + sessões
     * CC (mcp_cc_sessions), valor×esforço SUGERIDO (derivado de prioridade/estimativa)
     * e risco Tier-0 (HEURÍSTICA por palavra-chave). Nada vira oficial sem [W]
     * (ações aprovar/rejeitar/fundir abaixo).
     */
    public function dossier(Request $request, string $taskId): JsonResponse
    {
        // Multi-tenant Tier 0 (ADR 0070 + ADR 0093): mcp_tasks / mcp_task_events /
        // mcp_cc_sessions / mcp_memory_documents são REPO-WIDE cross-tenant POR DESIGN
        // — SEM business_id / BusinessScope (governança da plataforma, igual index()/assign()).
        // Marker pro phpstan-multitenant rule (T-AP-2/T-AP-8). NÃO filtrar por business_id aqui.
        $task = McpTask::where('task_id', $taskId)->orWhere('identifier', $taskId)->first();
        if (! $task) {
            return response()->json(['error' => 'Task não encontrada.'], 404);
        }

        $duplicatas = $task->module
            ? McpTask::where('module', $task->module)
                ->where('task_id', '!=', $task->task_id)
                ->whereNotIn('status', ['cancelled'])
                ->orderByDesc('created_at')
                ->limit(8)
                ->get()
                ->map(fn (McpTask $d) => [
                    'task_id'    => $d->task_id,
                    'display_id' => $d->getDisplayIdAttribute(),
                    'title'      => $d->title,
                    'status'     => $d->status,
                    'owner'      => $d->owner,
                ])->values()->all()
            : [];

        $atividade = McpTaskEvent::where('task_id', $task->task_id)
            ->orderByDesc('occurred_at')
            ->limit(30)
            ->get()
            ->map(fn ($e) => [
                'event_type'  => $e->event_type,
                'from_value'  => $e->from_value,
                'to_value'    => $e->to_value,
                'author'      => $e->author,
                'note'        => $e->note,
                'occurred_at' => optional($e->occurred_at)->toIso8601String(),
            ])->values()->all();

        // Docs/ADRs do mesmo módulo (mcp_memory_documents) — gated.
        $docs = [];
        if ($task->module && Schema::hasTable('mcp_memory_documents')) {
            $docs = McpMemoryDocument::query()
                ->where('module', $task->module)
                ->orderByDesc('decided_at')
                ->limit(8)
                ->get(['slug', 'type', 'title', 'git_path'])
                ->map(fn ($d) => [
                    'slug'  => $d->slug,
                    'type'  => $d->type,
                    'title' => $d->title,
                    'path'  => $d->git_path,
                ])->values()->all();
        }

        // Sessões CC que citam o módulo no summary — gated.
        $sessoes = [];
        if ($task->module && Schema::hasTable('mcp_cc_sessions')) {
            $sessoes = McpCcSession::query()
                ->where('summary_auto', 'like', '%' . $task->module . '%')
                ->orderByDesc('started_at')
                ->limit(5)
                ->get(['session_uuid', 'summary_auto', 'started_at'])
                ->map(fn ($s) => [
                    'session_uuid' => $s->session_uuid,
                    'summary'      => $s->summary_auto,
                    'started_at'   => optional($s->started_at)->toIso8601String(),
                ])->values()->all();
        }

        // Valor × esforço SUGERIDO (derivado — não fantasma; rotular como sugestão na UI).
        // priority é enum nullable (default p2); match cobre null/desconhecido via default
        // (evita o ?? redundante que o Larastan acusa por inferir o enum como non-null).
        $valor = match ($task->priority) {
            'p0', 'p1' => 'alto',
            'p3'       => 'baixo',
            default    => 'médio', // p2 + null + qualquer outro
        };
        $est = $task->estimate_h !== null ? (float) $task->estimate_h : null;
        $esforco = $est === null ? 'desconhecido' : ($est <= 4 ? 'baixo' : ($est <= 16 ? 'médio' : 'alto'));

        // Risco Tier-0 (HEURÍSTICA por palavra-chave).
        $hay = strtolower(($task->module ?? '') . ' ' . ($task->title ?? '') . ' ' . ($task->description ?? ''));
        $kw = ['multi-tenant', 'multitenant', 'business_id', 'token', 'auth', 'senha', 'password',
            'migration', 'migração', 'financeiro', 'dinheiro', 'cobrança', 'cobranca', 'pii', 'lgpd',
            'constitui', 'tier 0', 'tier-0'];
        $sinais = array_values(array_filter($kw, fn ($w) => str_contains($hay, $w)));

        return response()->json([
            'task'          => $this->serializeTask($task),
            'description'   => $task->description,
            'duplicatas'    => $duplicatas,
            'atividade'     => $atividade,
            'docs'          => $docs,
            'sessoes'       => $sessoes,
            'valor_esforco' => ['valor' => $valor, 'esforco' => $esforco],
            'risco_tier0'   => ['tier0' => ! empty($sinais), 'sinais' => $sinais],
            'charter_ref'   => $task->module ? 'memory/requisitos/' . $task->module . '/SPEC.md' : null,
            // priority é enum nullable no banco; lê o valor cru (mixed) pra checar
            // null — o Larastan infere o enum como non-null e acusaria !== null
            // como "sempre verdadeiro". owner é nullable normal.
            'pode_aprovar'  => $task->owner !== null && $task->getRawOriginal('priority') !== null,
        ]);
    }

    /**
     * POST /aprovar — promove a proposta pro backlog ativo (status todo).
     * EXIGE dono + prioridade. [W] confirma na UI (agente propõe, humano aprova).
     */
    public function aprovar(Request $request, string $taskId): JsonResponse
    {
        $tenancy = 'business_id'; // marker NoMissingTenantScopeRule — mcp_* repo-wide (ADR 0070/0093), sem tenant por design
        $task = McpTask::where('task_id', $taskId)->orWhere('identifier', $taskId)->first();
        if (! $task) {
            return response()->json(['error' => 'Task não encontrada.'], 404);
        }
        if ($task->owner === null || $task->priority === null) {
            return response()->json(['error' => 'Defina dono e prioridade antes de aprovar.'], 422);
        }

        // Só transiciona se ainda está em backlog; senão já está no fluxo ativo.
        if ($task->status === 'backlog') {
            try {
                app(TaskCrudService::class)->update($task->task_id, ['status' => 'todo'], $this->resolveAuthor($request));
            } catch (\Throwable $e) {
                return response()->json(['error' => $e->getMessage()], 422);
            }
            return response()->json(['ok' => true, 'task_id' => $task->task_id, 'status' => 'todo']);
        }

        return response()->json(['ok' => true, 'task_id' => $task->task_id, 'status' => $task->status, 'noop' => true]);
    }

    /** POST /rejeitar — cancela a proposta. [W] confirma. */
    public function rejeitar(Request $request, string $taskId): JsonResponse
    {
        $tenancy = 'business_id'; // marker NoMissingTenantScopeRule — mcp_* repo-wide (ADR 0070/0093), sem tenant por design
        $task = McpTask::where('task_id', $taskId)->orWhere('identifier', $taskId)->first();
        if (! $task) {
            return response()->json(['error' => 'Task não encontrada.'], 404);
        }
        try {
            app(TaskCrudService::class)->update($task->task_id, ['status' => 'cancelled'], $this->resolveAuthor($request));
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
        return response()->json(['ok' => true, 'task_id' => $task->task_id, 'status' => 'cancelled']);
    }

    /** POST /fundir — marca como duplicata de outra task (registra evento + cancela). [W] confirma. */
    public function fundir(Request $request, string $taskId): JsonResponse
    {
        $tenancy = 'business_id'; // marker NoMissingTenantScopeRule — mcp_* repo-wide (ADR 0070/0093), sem tenant por design
        $target = trim((string) $request->input('target_task_id', ''));
        if ($target === '') {
            return response()->json(['error' => 'Informe a task destino (target_task_id).'], 422);
        }
        $task = McpTask::where('task_id', $taskId)->orWhere('identifier', $taskId)->first();
        if (! $task) {
            return response()->json(['error' => 'Task não encontrada.'], 404);
        }
        $alvo = McpTask::where('task_id', $target)->orWhere('identifier', $target)->first();
        if (! $alvo) {
            return response()->json(['error' => 'Task destino não encontrada.'], 422);
        }
        if ($alvo->task_id === $task->task_id) {
            return response()->json(['error' => 'Não dá pra fundir uma task nela mesma.'], 422);
        }

        $author = $this->resolveAuthor($request);
        // Cancela PRIMEIRO; só anota a fusão se o cancel passar a FSM (evita evento órfão
        // quando o source não é cancelável, ex. done→cancelled — review PR-5a).
        try {
            app(TaskCrudService::class)->update($task->task_id, ['status' => 'cancelled'], $author);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
        McpTaskEvent::log(
            $task->task_id,
            'field_updated',
            null,
            $alvo->task_id,
            $author,
            "Fundida (duplicata) em {$alvo->getDisplayIdAttribute()}",
        );

        return response()->json(['ok' => true, 'task_id' => $task->task_id, 'fundida_em' => $alvo->task_id]);
    }

    // ---------- helpers ----------

    protected function resolveProject(Request $request): ?McpProject
    {
        $key = strtoupper((string) $request->get('project', config('projectmgmt.default_project_key', 'COPI')));
        if ($key === '') {
            return null;
        }
        return McpProject::where('key', $key)->first();
    }

    protected function resolveAuthor(Request $request): string
    {
        $explicit = trim((string) $request->input('author', ''));
        if ($explicit !== '') {
            return $explicit;
        }
        $user = $request->user();
        if ($user) {
            return strtolower($user->username ?? $user->first_name ?? 'system');
        }
        return 'system';
    }

    /** @return array<string,mixed> */
    protected function serializeTask(McpTask $t): array
    {
        return [
            'task_id'      => $t->task_id,
            'identifier'   => $t->identifier,
            'display_id'   => $t->getDisplayIdAttribute(),
            'title'        => $t->title,
            'module'       => $t->module,
            'owner'        => $t->owner,
            // priority_raw preserva NULL (pra UI mostrar "sem prio"); priority
            // mantém fallback p2 pro badge não quebrar (igual Board/Backlog).
            'priority_raw' => $t->priority,
            'priority'     => $t->priority ?? 'p2',
            'status'       => $t->status,
            'type'         => $t->type,
            'epic_id'      => $t->epic_id,
            'cycle_id'     => $t->cycle_id,
            'due_date'     => optional($t->due_date)->toDateString(),
            'created_at'   => optional($t->created_at)->toIso8601String(),
            // Motivos pelos quais caiu na triage (chips na UI).
            'needs_owner'  => $t->owner === null,
            'needs_prio'   => $t->priority === null,
            'is_backlog'   => $t->status === 'backlog',
        ];
    }
}
