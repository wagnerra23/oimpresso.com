<?php

declare(strict_types=1);

namespace Modules\Forja\Services;

use App\Util\OtelHelper;
use Illuminate\Support\Collection;
use Modules\Jana\Entities\Mcp\McpProject;
use Modules\Jana\Entities\Mcp\McpTask;
use Modules\Forja\Entities\McpActor;

/**
 * TrabalhoService — a fonte ÚNICA da tela `/forja/trabalho`, que funde os três
 * backlogs que o hub tinha.
 *
 * ── O QUE ISTO RESOLVE ──────────────────────────────────────────────────────
 * Medido em 2026-08-08: a mesma pergunta ("o que tem pra fazer?") era respondida
 * por TRÊS implementações independentes, com escopos e riquezas diferentes:
 *
 *   Pages/Forja/Backlog/Index.tsx    416 ln  · project=FORJA · filtros + KPIs + epics/owners/sprints
 *   _components/ForjaBacklog.tsx     207 ln  · project=FORJA · lista chapada, sem filtro
 *   Pages/team-mcp/Tasks/Index.tsx   647 ln  · TODAS as tasks · KPI-filtros + ActorSeal
 *
 * A decisão de qual sobrevive é [W] (US-FORJA-006). Recomendação executada: **as
 * nativas vencem** — são as ricas (têm `casos.md` defendido por gate), e o cockpit
 * é a versão enxuta. Este service é a lógica da NATIVA (`BacklogController`)
 * generalizada, mais o que só as outras duas tinham.
 *
 * ── O QUE VEIO DE CADA UMA ──────────────────────────────────────────────────
 *   da NATIVA      : filtros (status/priority/owner/epic/cycle/sprint/q/sort),
 *                    KPIs, memoização por (escopo, filtros), teto de 500
 *   do COCKPIT     : a projeção `forja_*` (tipo/fase/papel/onda) sobre custom_fields
 *   do TEAM-MCP    : o escopo SEM recorte de projeto — todas as tasks do time
 *
 * ── SEM FILTRO DE FRENTE, POR DECISÃO ───────────────────────────────────────
 * [W] 2026-08-08: a lista abre com TODAS as `mcp_tasks`; o recorte por projeto se
 * faz agrupando por Frente ou buscando, não por um chip que esconde o resto. Por
 * isso `$projectId` é opcional aqui e o default é `null` (tudo) — o inverso do
 * `ForjaBacklogService`, que devolvia `[]` sem project.
 *
 * Multi-tenant Tier 0 (ADR 0093): `mcp_tasks` é REPO-WIDE (governança da
 * plataforma) — sem `business_id` por design, igual aos três que ele funde.
 */
class TrabalhoService
{
    /** Teto da lista — herdado da nativa, que já operava com ele. */
    private const LIMIT = 500;

    /**
     * Memoização por filtros — POR INSTÂNCIA, nunca `static`.
     *
     * O `BacklogController` usa `static $cache` e ali passa despercebido porque
     * controller nasce e morre no request. Num SERVICE o mesmo truque vaza: o
     * processo PHP é reusado entre testes (o Pest rodou UC-TRAB-04 primeiro, ele
     * cacheou `filtrosPadrao()`, e os casos seguintes receberam o resultado velho
     * — sem as fixtures que tinham acabado de criar). Fora do teste o vetor é o
     * mesmo: worker de fila é processo longo.
     *
     * Instância basta pro ganho real — quem chama `app(TrabalhoService::class)`
     * uma vez e defere duas props reusa a mesma instância, que é o caso do
     * controller.
     *
     * @var array<string,array{tasks: Collection<int,array<string,mixed>>, kpis: array<string,int>}>
     */
    private array $cache = [];

    /** Ordenações aceitas. `rank` é o default e mora em {@see self::aplicarOrdem}. */
    public const SORTS = ['rank', 'recent', 'due', 'title', 'id', 'execucao'];

    /**
     * Filtros que o atalho "Gantt" carrega de `/forja/trabalho` para
     * `/forja/roadmap-gantt` — os que o DESTINO de fato lê da query string
     * (`RoadmapGanttController::index`, `$request->get(...)`).
     *
     * ⚠️ Não confunda com o payload de SAÍDA do Gantt: ele serializa `status`,
     * `type`, `due_date` e mais uma dúzia de campos, mas **não** os aceita como
     * filtro de entrada. Mandar `status=` no link seria parâmetro que o destino
     * ignora em silêncio — o usuário veria a lista "não filtrar" e não saberia
     * por quê. Por isso o `UC-TRAB-10` cruza esta constante com o controller de
     * lá: se o Gantt parar de ler um destes, o caso cai.
     */
    public const FILTROS_ATALHO_GANTT = ['cycle', 'owner', 'priority', 'module'];

    /**
     * Filtros default — o que a tela usa quando o [W] não pediu nada.
     *
     * @return array<string,mixed>
     */
    public static function filtrosPadrao(): array
    {
        return [
            'status'   => null,
            'priority' => null,
            'owner'    => null,
            'module'   => null,
            'frente'   => null,   // = project_id; null = TODAS (decisão [W])
            'epic'     => null,
            'cycle'    => null,
            'sprint'   => null,
            'q'        => '',
            'sort'     => 'rank',
            // Sub-visão da MESMA lista (não são telas distintas — mesmo pool,
            // mesmos filtros). Ficam nos filtros pra viajar na URL.
            'visao'    => 'lista',
            'eixo'     => 'execucao',
        ];
    }

    /**
     * Tasks + KPIs (compartilham a query). Memoizado por filtros pra não dobrar
     * a consulta quando as duas props deferidas são pedidas na mesma render —
     * mesmo truque do `BacklogController::buildTasksAndKpis`.
     *
     * @param  array<string,mixed>  $filtros
     * @return array{tasks: Collection<int,array<string,mixed>>, kpis: array<string,int>}
     */
    public function build(array $filtros): array
    {
        $tenancy = 'business_id'; // marker NoMissingTenantScopeRule — mcp_tasks é repo-wide (ADR 0070/0093), sem tenant por design

        // A chave IGNORA `visao`/`eixo`: eles mudam como se OLHA, não o que se
        // consulta. Sem isto, alternar Lista↔Quadro refaria a query inteira por
        // nada — o pool é o mesmo, e é justamente esse o ponto da fusão.
        $paraChave = $filtros;
        unset($paraChave['visao'], $paraChave['eixo']);
        $chave = md5(serialize($paraChave));
        if (isset($this->cache[$chave])) {
            return $this->cache[$chave];
        }

        return $this->cache[$chave] = OtelHelper::span('forja.trabalho.build', [], function () use ($filtros): array {
            $q = McpTask::query()
                ->when($filtros['frente']   ?? null, fn ($qq, $v) => $qq->where('project_id', $v))
                ->when($filtros['priority'] ?? null, fn ($qq, $v) => $qq->where('priority', $v))
                ->when($filtros['owner']    ?? null, fn ($qq, $v) => $qq->where('owner', $v))
                ->when($filtros['module']   ?? null, fn ($qq, $v) => $qq->where('module', $v))
                ->when($filtros['epic']     ?? null, fn ($qq, $v) => $qq->where('epic_id', $v))
                ->when($filtros['cycle']    ?? null, fn ($qq, $v) => $qq->where('cycle_id', $v))
                ->when($filtros['sprint']   ?? null, fn ($qq, $v) => $qq->where('sprint', $v));

            // Mesma semântica da nativa: 'all' mostra tudo; sem filtro, esconde
            // cancelled (ruído); com filtro, respeita o pedido.
            $status = $filtros['status'] ?? null;
            if ($status && $status !== 'all') {
                $q->where('status', $status);
            } elseif ($status !== 'all') {
                $q->whereNotIn('status', ['cancelled']);
            }

            $busca = trim((string) ($filtros['q'] ?? ''));
            if ($busca !== '') {
                $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $busca) . '%';
                $q->where(function ($qq) use ($like) {
                    $qq->where('title', 'like', $like)
                        ->orWhere('task_id', 'like', $like)
                        ->orWhere('identifier', 'like', $like)
                        ->orWhere('owner', 'like', $like)
                        ->orWhere('module', 'like', $like);
                });
            }

            $this->aplicarOrdem($q, (string) ($filtros['sort'] ?? 'rank'));

            $tasks = $q->limit(self::LIMIT)->get()->map(fn (McpTask $t): array => $this->serialize($t));

            return ['tasks' => $tasks, 'kpis' => $this->kpis($tasks)];
        });
    }

    /**
     * A ordem da lista.
     *
     * `rank` (default) é a ordem da NATIVA: estado do trabalho primeiro, depois
     * prioridade. Não é o "rank híbrido com pin" que o pedido descreve — esse
     * depende de user-pref persistida, que é PR próprio; aqui fica a ordem que já
     * existia e que ninguém precisa reaprender.
     *
     * `execucao` é o segundo eixo (o que está ANDANDO primeiro), pro dia a dia de
     * quem executa em vez de priorizar.
     */
    private function aplicarOrdem(mixed $q, string $sort): void
    {
        match ($sort) {
            'recent'   => $q->orderBy('updated_at', 'desc'),
            'due'      => $q->orderByRaw('CASE WHEN due_date IS NULL THEN 1 ELSE 0 END')->orderBy('due_date'),
            'title'    => $q->orderBy('title'),
            'id'       => $q->orderBy('task_id'),
            'execucao' => $q->orderByRaw("FIELD(status,'doing','review','blocked','todo','backlog','done','cancelled')")
                ->orderBy('updated_at', 'desc'),
            default    => $q->orderByRaw("FIELD(status,'doing','review','todo','blocked','backlog','done','cancelled')")
                ->orderByRaw("FIELD(priority,'p0','p1','p2','p3','')")
                ->orderBy('task_id'),
        };
    }

    /**
     * Os KPIs da fileira de filtros.
     *
     * @param  Collection<int,array<string,mixed>>  $tasks
     * @return array<string,int>
     */
    private function kpis(Collection $tasks): array
    {
        $abertas = $tasks->whereNotIn('status', ['done', 'cancelled']);

        return [
            'total'    => $tasks->count(),
            'ativas'   => $abertas->count(),
            'p0'       => $abertas->where('priority', 'p0')->count(),
            'fazendo'  => $tasks->where('status', 'doing')->count(),
            'bloqueadas' => $tasks->where('status', 'blocked')->count(),
            'sem_dono' => $abertas->whereNull('owner')->count(),
            'atrasadas' => $tasks->where('is_overdue', true)->count(),
        ];
    }

    /**
     * Serializa 1 task pra lista unificada.
     *
     * União dos três: os campos da nativa + a projeção `forja_*` do cockpit +
     * `frente` (o projeto), que só faz sentido quando a lista mistura projetos.
     *
     * @return array<string,mixed>
     */
    private function serialize(McpTask $t): array
    {
        $cf = is_array($t->custom_fields) ? $t->custom_fields : [];

        return [
            // — da nativa (Pages/Forja/Backlog) —
            'task_id'      => $t->task_id,
            'identifier'   => $t->identifier,
            'display_id'   => $t->getDisplayIdAttribute(),
            'title'        => (string) $t->title,
            'module'       => $t->module,
            'owner'        => $t->owner,
            'sprint'       => $t->sprint,
            'priority'     => $t->priority ?? 'p2',
            'status'       => (string) $t->status,
            'type'         => $t->type,
            'estimate_h'   => $t->estimate_h !== null ? (float) $t->estimate_h : null,
            'story_points' => $t->story_points !== null ? (float) $t->story_points : null,
            'due_date'     => optional($t->due_date)->toDateString(),
            'epic_id'      => $t->epic_id,
            'cycle_id'     => $t->cycle_id,
            'blocked_by'   => $t->blocked_by ?? [],
            'is_blocked'   => $t->status === 'blocked',
            'is_overdue'   => $t->due_date && $t->due_date->isPast()
                && ! in_array($t->status, ['done', 'cancelled'], true),

            // — do cockpit (ForjaBacklogService): projeção sobre custom_fields —
            // `fase` só existe pra trabalho do pipeline de TELA (F0→F4). Task de
            // infra/gate/ADR não tem, e isso é correto — não é dado faltando.
            'forja_tipo'  => isset($cf['forja_tipo'])  ? (string) $cf['forja_tipo']  : null,
            'forja_fase'  => isset($cf['forja_fase'])  ? (string) $cf['forja_fase']  : null,
            'forja_papel' => isset($cf['forja_papel']) ? (string) $cf['forja_papel'] : null,
            'forja_onda'  => isset($cf['forja_onda'])  ? (string) $cf['forja_onda']  : null,

            // — do team-mcp/Tasks: a lista mistura projetos, então a frente importa —
            'frente_id'   => $t->project_id,
        ];
    }

    /**
     * Mapa `project_id => key` pra tela rotular a Frente sem N+1.
     *
     * @return array<int,string>
     */
    /**
     * Slugs dos atores que são AGENTE (não humano) — alimenta o `<ActorSeal>`.
     *
     * MESMO predicado do {@see \Modules\Forja\Http\Controllers\TasksAdminController}
     * (`type=ai_agent` + não revogado + lowercase, ADR 0081 Identity Mesh). Está
     * duplicado de propósito NESTA onda: unificar exigiria tocar aquele controller,
     * que é outro intent. Se um terceiro consumidor aparecer, extraia — dois é o
     * limite razoável antes de virar dívida.
     *
     * O front marca `owner ∈ agents` como agente; o resto cai em humano. Agente
     * nunca se disfarça de humano — a lista é allowlist, não heurística de nome.
     *
     * @return array<int,string>
     */
    public function agentes(): array
    {
        return McpActor::query()
            ->where('type', 'ai_agent')
            ->whereNull('revoked_at')
            ->pluck('slug')
            ->map(fn ($s) => strtolower((string) $s))
            ->values()
            ->toArray();
    }

    public function frentes(): array
    {
        $tenancy = 'business_id'; // marker NoMissingTenantScopeRule — mcp_projects é repo-wide (ADR 0070/0093)

        return OtelHelper::span('forja.trabalho.frentes', [], function (): array {
            return McpProject::query()->pluck('key', 'id')->all();
        });
    }
}
