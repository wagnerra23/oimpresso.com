<?php

declare(strict_types=1);

namespace Modules\TeamMcp\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Jana\Entities\Mcp\McpAuditLog;
use Modules\Jana\Entities\Mcp\McpCcSession;
use Modules\Jana\Entities\Mcp\McpMemoryDocument;
use Modules\Jana\Entities\Mcp\McpProject;
use Modules\Jana\Entities\Mcp\McpTask;
use Modules\Jana\Entities\Mcp\McpTaskEvent;
use Modules\Jana\Services\TaskRegistry\TaskCrudService;
use Modules\TeamMcp\Services\Forja\ForjaBacklogService;
use Modules\TeamMcp\Services\Forja\ForjaChangelogService;
use Modules\TeamMcp\Services\Forja\ForjaMcpService;
use Modules\TeamMcp\Services\Forja\ForjaQuadroService;
use Modules\TeamMcp\Services\HandoffLeverService;

/**
 * ForjaController — cockpit do cowork loop (/forja).
 *
 * Absorção em TeamMcp (NÃO é módulo novo — kickoff Forja). As 6 abas projetam
 * estado que JÁ EXISTE (mcp_tasks + git/PR/ADR/sessão + gates/memory-health) —
 * sem dado fantasma. Todas renderizam o mesmo shell `team-mcp/Forja/Cockpit`
 * com a aba ativa via prop `tab`; o topnav de 6 abas vem de
 * config/core_topnavs.php['Forja'] (useAutoModuleNav casa por 1º segmento /forja
 * — por isso a raiz é /forja e não /team-mcp/forja, que colidiria com o hub Equipe).
 *
 * Onda Forja PR-A → Triagem REAL (esta PR): a aba Triagem deixa de ser placeholder
 * e projeta `mcp_tasks` project=FORJA em estado de triagem (sem owner OU sem
 * priority OU backlog), com dossiê lateral (reusa o padrão Analista de ProjectMgmt:
 * valor×esforço, risco Tier-0, duplicatas, Aprovar/Rejeitar/Fundir). As outras 5
 * abas (backlog/quadro/changelog/mcp/saude) seguem placeholder (1 PR cada).
 * Referência aprovada (F1.5 ADR 0114):
 * memory/requisitos/TeamMcp/forja-cockpit-visual-comparison.md.
 *
 * Multi-tenant Tier 0: cockpit é repo-wide (governança cross-business do loop)
 * — sem filtro business_id, INTENCIONAL (ADR 0093), igual Scorecard/TriageController.
 *
 * Permissão: copiloto.mcp.usage.all (Wagner/superadmin), igual Scorecard/Team.
 */
class ForjaController extends Controller
{
    /** Key do project Jira-style do cockpit Forja (ADR 0070). */
    private const PROJECT_KEY = 'FORJA';

    /**
     * Abas do cockpit (label + intro). Ordem = ordem do topnav.
     *
     * @var array<string,array{label:string,subtitle:string}>
     */
    private const TABS = [
        'triagem'   => ['label' => 'Triagem',   'subtitle' => 'Tickets propostos aguardando o analista [AN] enriquecer e sua aprovação. Entram no backlog só depois — é o F0 do protocolo, formalizado.'],
        'backlog'   => ['label' => 'Backlog',   'subtitle' => 'Issues agrupáveis por Onda / Fase / Papel / Prioridade / Módulo.'],
        'quadro'    => ['label' => 'Quadro',    'subtitle' => 'Fluxo do cowork loop por fase: F0 Brief → F1 Design → F1.5 Critique → F2 Screenshot → F3 Code → F3.5 A11y.'],
        'changelog' => ['label' => 'Changelog', 'subtitle' => 'O que shippou — PRs, ADRs, sessões e ondas.'],
        'mcp'       => ['label' => 'MCP',       'subtitle' => 'Contrato de ferramentas, tokens e auditoria — design; o enforce real é do servidor TeamMcp.'],
    ];

    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('can:copiloto.mcp.usage.all');
    }

    public function triagem(): Response
    {
        // Triagem REAL: projeta mcp_tasks project=FORJA em estado de triagem via
        // Inertia::defer (espelha TriageController). As props pesadas só rodam quando
        // o partial reload as pede; 1º paint serve só `tab`/`tabLabel`/`subtitle`/`meta`.
        $projectId = $this->resolveForjaProjectId();

        return Inertia::render('team-mcp/Forja/Cockpit', array_merge(
            $this->tabPayload('triagem'),
            [
                'tickets'      => Inertia::defer(fn () => $this->buildTriagemPayload($projectId)['tickets']),
                'triagemCount' => Inertia::defer(fn () => $this->buildTriagemPayload($projectId)['count']),
            ],
        ));
    }

    public function backlog(): Response
    {
        $projectId = $this->resolveForjaProjectId();

        return Inertia::render('team-mcp/Forja/Cockpit', array_merge(
            $this->tabPayload('backlog'),
            ['backlog' => Inertia::defer(fn () => app(ForjaBacklogService::class)->build($projectId))],
        ));
    }

    public function quadro(): Response
    {
        $projectId = $this->resolveForjaProjectId();

        return Inertia::render('team-mcp/Forja/Cockpit', array_merge(
            $this->tabPayload('quadro'),
            ['quadro' => Inertia::defer(fn () => app(ForjaQuadroService::class)->build($projectId))],
        ));
    }

    public function changelog(): Response
    {
        return Inertia::render('team-mcp/Forja/Cockpit', array_merge(
            $this->tabPayload('changelog'),
            ['changelog' => Inertia::defer(fn () => app(ForjaChangelogService::class)->build())],
        ));
    }

    public function mcp(): Response
    {
        // Fase 1 (ADR 0283): a aba MCP deixa de ser 100% mock — projeta os handoffs
        // REAIS de `cowork_handoffs` (+ heartbeat do ingest) via Inertia::defer,
        // espelhando triagem()/quadro(). Contrato/tokens/auditoria seguem MOCKADO
        // (vitrine de design); só a seção Handoffs é dado vivo. Sem auto-merge: as
        // levers roteiam pelas tools MCP e o merge é o 1-clique do [W] (ADR 0283).
        $svc = app(ForjaMcpService::class);

        return Inertia::render('team-mcp/Forja/Cockpit', array_merge(
            $this->tabPayload('mcp'),
            [
                'handoffs'  => Inertia::defer(fn () => $svc->handoffs()),
                'heartbeat' => Inertia::defer(fn () => $svc->heartbeat()),
            ],
        ));
    }

    /**
     * POST /forja/handoff/{slug}/lever — opera uma lever do loop de handoff
     * (re-disparar/devolver/supersede) sobre cowork_handoffs. Fecha o Gap 3 do
     * adversário: as levers da aba MCP deixam de ser `disabled "em breve"`.
     *
     * MESMA mutação GOVERNADA do tool MCP `handoff-lever` — ambos delegam pra
     * {@see HandoffLeverService} (fonte única, append-only · ADR 0283). Aqui o ator
     * é o [W] na sessão web (gate copiloto.mcp.usage.all no __construct); lá é o
     * agente via scope fino jana.mcp.handoff.lever. SEM auto-merge: o merge segue o
     * 1-clique do [W] no GitHub. Audit em mcp_audit_log (best-effort).
     *
     * Delega 100% ao service (sem Eloquent direto aqui) — repo-wide por design
     * (cowork_handoffs sem business_id, Tier 0 ADR 0093), igual mcp()/dossier().
     */
    public function handoffLever(Request $request, string $slug): JsonResponse
    {
        $action = trim((string) $request->input('action', ''));
        if (! in_array($action, HandoffLeverService::ACTIONS, true)) {
            return response()->json(['error' => 'Ação inválida.'], 422);
        }

        $versionInput = $request->input('version');
        $expected = ($versionInput !== null && $versionInput !== '') ? (int) $versionInput : null;

        $result = app(HandoffLeverService::class)->apply($action, $slug, $expected);

        $this->auditHandoffLever($request, $action, $slug, $result);

        if ($result['outcome'] === 'rejected') {
            $status = match ($result['reason']) {
                'not_found'  => 404,
                'stale_view' => 409,
                default      => 422,
            };

            return response()->json([
                'error'  => $this->leverErrorMessage($action, (string) $result['reason'], (int) $result['version']),
                'reason' => $result['reason'],
            ], $status);
        }

        return response()->json([
            'ok'                 => true,
            'action'             => $action,
            'slug'               => $result['slug'],
            'version'            => (int) $result['version'],
            'outcome'            => $result['outcome'],
            'superseded_version' => $result['superseded_version'],
        ]);
    }

    /** Mensagem de recusa legível (espelha HandoffLeverTool::rejectMessage). */
    private function leverErrorMessage(string $action, string $reason, int $version): string
    {
        return match ($reason) {
            'not_found'  => 'Handoff não encontrado.',
            'stale_view' => "A fila mudou — a versão atual é v{$version}. Recarregue antes de operar.",
            'state'      => match ($action) {
                're-disparar' => 're-disparar só vale pra um handoff pendente (parado).',
                'devolver'    => 'devolver só vale pra um handoff rejeitado.',
                'supersede'   => 'supersede só vale pra um handoff pendente ou aplicado.',
                default       => 'Lever fora do estado esperado.',
            },
            default      => 'Ação inválida.',
        };
    }

    /**
     * Audit best-effort da lever no mcp_audit_log (origem web/cockpit) — espelha
     * HandoffLeverTool::audit; não trava a resposta.
     *
     * @param  array{outcome:string,reason:string|null,slug:string,version:int,superseded_version:int|null}  $result
     */
    private function auditHandoffLever(Request $request, string $action, string $slug, array $result): void
    {
        try {
            $user = $request->user();
            McpAuditLog::registrar([
                'user_id'          => $user !== null ? (int) $user->getAuthIdentifier() : 0,
                'endpoint'         => 'web/forja',
                'tool_or_resource' => 'handoff-lever',
                'status'           => $result['outcome'] === 'rejected' ? 'denied' : 'ok',
                'payload_summary'  => [
                    'action'  => $action,
                    'slug'    => $slug,
                    'version' => (int) $result['version'],
                    'outcome' => $result['outcome'],
                    'reason'  => $result['reason'],
                    'origin'  => 'forja-web',
                ],
            ]);
        } catch (\Throwable) {
            // best-effort
        }
    }

    // ---------- Triagem · payload ----------

    /**
     * Constrói as propostas em triagem (project=FORJA) + a contagem (badge da aba).
     * Memoiza por projectId pra não dobrar a query quando ambos requested numa render.
     *
     * Filtro = paridade EXATA com TriageController/TriageTool:
     *   McpTask::triage() = (owner IS NULL OR priority IS NULL OR status='backlog')
     *   + whereNotIn status [done, cancelled] + orderByDesc created_at.
     *
     * @return array{tickets: \Illuminate\Support\Collection<int,array<string,mixed>>, count: int}
     */
    protected function buildTriagemPayload(?int $projectId): array
    {
        $tenancy = 'business_id'; // marker NoMissingTenantScopeRule — mcp_* repo-wide (ADR 0070/0093), sem tenant por design

        static $cache = [];
        $key = (string) ($projectId ?? 'none');
        if (isset($cache[$key])) {
            return $cache[$key];
        }

        // Sem project FORJA semeado → fila vazia (sem dado fantasma). O front mostra
        // o empty-state ("Nada pra triar"). Roda o seeder ForjaDemoTicketsSeeder.
        if (! $projectId) {
            return $cache[$key] = ['tickets' => collect(), 'count' => 0];
        }

        $tickets = McpTask::triage()
            ->whereNotIn('status', ['done', 'cancelled'])
            ->where('project_id', $projectId)
            ->orderByDesc('created_at')
            ->limit(200)
            ->get()
            ->map(fn (McpTask $t) => $this->serializeTicket($t));

        return $cache[$key] = ['tickets' => $tickets, 'count' => $tickets->count()];
    }

    // ---------- Triagem · dossiê + ações [W] aprova (espelho do TriageController) ----------

    /**
     * GET /forja/{taskId}/dossier — read-only.
     *
     * Dossiê do Analista: SÓ dados reais. Duplicatas (mesmo módulo), atividade
     * (mcp_task_events), cross-link de docs/ADRs (mcp_memory_documents) + sessões
     * CC (mcp_cc_sessions), valor×esforço SUGERIDO (derivado de prioridade/estimativa)
     * e risco Tier-0 (HEURÍSTICA por palavra-chave). Nada vira oficial sem [W].
     *
     * Espelha Modules\ProjectMgmt\Http\Controllers\TriageController@dossier — só muda
     * a rota; a lógica é a mesma (agente propõe, [W] aprova).
     */
    public function dossier(Request $request, string $taskId): JsonResponse
    {
        // Multi-tenant Tier 0 (ADR 0070 + ADR 0093): mcp_tasks / mcp_task_events /
        // mcp_cc_sessions / mcp_memory_documents são REPO-WIDE cross-tenant POR DESIGN
        // — SEM business_id / BusinessScope (governança da plataforma).
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
            'task'          => $this->serializeTicket($task),
            'description'   => $task->description,
            'duplicatas'    => $duplicatas,
            'atividade'     => $atividade,
            'docs'          => $docs,
            'sessoes'       => $sessoes,
            'valor_esforco' => ['valor' => $valor, 'esforco' => $esforco],
            'risco_tier0'   => ['tier0' => ! empty($sinais), 'sinais' => $sinais],
            'charter_ref'   => $task->module ? 'memory/requisitos/' . $task->module . '/SPEC.md' : null,
            // priority é enum nullable no banco; lê o valor cru (mixed) pra checar null
            // — o Larastan infere o enum como non-null e acusaria !== null como
            // "sempre verdadeiro". owner é nullable normal.
            'pode_aprovar'  => $task->owner !== null && $task->getRawOriginal('priority') !== null,
        ]);
    }

    /**
     * POST /forja/{taskId}/aprovar — promove a proposta pro backlog ativo (status todo).
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

    /** POST /forja/{taskId}/rejeitar — cancela a proposta. [W] confirma. */
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

    /** POST /forja/{taskId}/fundir — marca como duplicata de outra task (evento + cancela). [W] confirma. */
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

    /**
     * Props base de toda aba (tab/label/subtitle/meta). Triagem e MCP adicionam
     * suas props deferidas (tickets/triagemCount · handoffs/heartbeat) por cima.
     *
     * @return array<string,mixed>
     */
    private function tabPayload(string $tab): array
    {
        $meta = self::TABS[$tab] ?? self::TABS['triagem'];

        return [
            'tab'      => $tab,
            'tabLabel' => $meta['label'],
            'subtitle' => $meta['subtitle'],
            'meta'     => [
                'generated_at' => now()->toIso8601String(),
                'onda'         => 'Forja',
            ],
        ];
    }

    /** Resolve o id do project FORJA (null se ainda não semeado — fila vazia). */
    protected function resolveForjaProjectId(): ?int
    {
        $tenancy = 'business_id'; // marker NoMissingTenantScopeRule — mcp_* repo-wide (ADR 0070/0093), sem tenant por design
        return McpProject::where('key', self::PROJECT_KEY)->value('id');
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

    /**
     * Serializa 1 proposta da Triagem. Espelha TriageController::serializeTask +
     * acrescenta os campos Forja (forja_tipo/forja_papel) lidos de custom_fields.
     *
     * @return array<string,mixed>
     */
    protected function serializeTicket(McpTask $t): array
    {
        $cf = is_array($t->custom_fields) ? $t->custom_fields : [];

        return [
            'task_id'      => $t->task_id,
            'identifier'   => $t->identifier,
            'display_id'   => $t->getDisplayIdAttribute(),
            'title'        => $t->title,
            'module'       => $t->module,
            'owner'        => $t->owner,
            // priority_raw preserva NULL (UI mostra "sem prio"); priority mantém
            // fallback p2 pro badge não quebrar (igual Board/Backlog/Triage).
            'priority_raw' => $t->priority,
            'priority'     => $t->priority ?? 'p2',
            'status'       => $t->status,
            'type'         => $t->type,
            // Campos Forja (projeção sobre custom_fields — não é schema novo, Tier 0).
            'forja_tipo'   => isset($cf['forja_tipo']) ? (string) $cf['forja_tipo'] : null,
            'forja_papel'  => isset($cf['forja_papel']) ? (string) $cf['forja_papel'] : null,
            'forja_onda'   => isset($cf['forja_onda']) ? (string) $cf['forja_onda'] : null,
            'created_at'   => optional($t->created_at)->toIso8601String(),
            // Motivos pelos quais caiu na triagem (chips na UI).
            'needs_owner'  => $t->owner === null,
            'needs_prio'   => $t->priority === null,
            'is_backlog'   => $t->status === 'backlog',
        ];
    }
}
