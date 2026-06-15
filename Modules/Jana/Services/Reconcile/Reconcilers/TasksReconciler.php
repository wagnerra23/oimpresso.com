<?php

declare(strict_types=1);

namespace Modules\Jana\Services\Reconcile\Reconcilers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Contracts\Reconciler;
use Modules\Jana\Entities\Mcp\McpTask;
use Modules\Jana\Services\Reconcile\ReconcileDrift;
use Modules\Jana\Services\Reconcile\ReconcileResult;
use Modules\Jana\Services\WorkLease\WorkLeaseService;

/**
 * TasksReconciler — faceta 'tasks' do loop `jana:reconcile` (ADR 0237).
 *
 * Garante que o estado vivo das tasks (`mcp_tasks`, cache Jira-style ADR 0070) é
 * COERENTE com os invariantes de governança do backlog. NÃO compara git × DB de
 * conteúdo (isso é a faceta 'content'); compara o estado declarado de cada task
 * com os sinais de coordenação/aceitação que DEVERIAM acompanhá-lo.
 *
 * ── DETECT-ONLY (alerta-only) — todos os drifts healable=false ──────────────
 * Cada faceta aponta uma INCOERÊNCIA cuja resolução é decisão humana (R10): mexer
 * em status/owner/blocked_by de uma task é ato de planejamento, não cura mecânica
 * segura. Logo a faceta DETECTA + ALERTA; humano (ou um workflow gated) decide.
 * Espelha a postura `healable=false` do {@see ContentReconciler} (delete inseguro)
 * — aqui o motivo é semântico: não há fonte-de-verdade única pra auto-corrigir.
 *
 * ── 3 facetas (observed = `mcp_tasks` + leases ativos) ──────────────────────
 *   R-A  doing-órfã          : task status='doing' SEM lease ativo correspondente
 *                              (mcp_work_leases). Quem está fazendo? Coordenação
 *                              perdida (crash/timeout que não renovou heartbeat, ou
 *                              status movido à mão sem claim). ADR 0278 (work-lease).
 *   R-B  done-sem-acceptance : task status='done' com `acceptance_ref` NULL ou ''.
 *                              Fechou sem prova de DoD (ADR 0278 Fase 2). Furo de
 *                              rastreabilidade (RTM) — done sem evidência.
 *   R-E  blocked_by-resolvido: task cujo `blocked_by[]` referencia uma task que JÁ
 *                              está fechada (status done/cancelled, {@see McpTask::isClosed}).
 *                              O bloqueio "venceu" mas a referência ficou stale — a
 *                              task pode estar represada sem motivo.
 *
 * ── Núcleo PURO injetável ────────────────────────────────────────────────────
 * {@see analisar()} recebe as linhas de tasks JÁ materializadas + a lista de
 * task_ids com lease ativo e devolve os drifts SEM tocar DB — determinístico,
 * testável sem I/O (mesmo padrão de IndexReconciler::analisar / DeployDriftChecker).
 * `reconcile()` faz a coleta de I/O (DB::table('mcp_tasks') + WorkLeaseService) e
 * delega ao núcleo puro.
 *
 * ── Multi-tenant Tier 0 (ADR 0093 / 0280) ───────────────────────────────────
 * As tabelas `mcp_tasks` / `mcp_work_leases` são Grupo A (governança da
 * PLATAFORMA, ADR 0280) — SEM `business_id` por design (planejamento Jira-style é
 * cross-tenant intencional, ADR 0070). Esta faceta NÃO aplica escopo de business.
 *
 * Refs:
 *   - ADR 0237 (jana:reconcile loop único — contrato Reconciler)
 *   - ADR 0070 (mcp_tasks Jira-style — repo-wide, sem business_id)
 *   - ADR 0278 (acceptance_ref Fase 2 + work-lease D1)
 *   - ADR 0280 (tabelas mcp_* Grupo A — sem business_id by design)
 */
final class TasksReconciler implements Reconciler
{
    // ── Chaves canônicas dos drifts (target prefix) ──────────────────────────
    private const T_DOING_ORFA = 'tasks.doing_orfa';
    private const T_DONE_SEM_ACCEPTANCE = 'tasks.done_sem_acceptance';
    private const T_BLOCKED_BY_RESOLVIDO = 'tasks.blocked_by_resolvido';

    /** Status que contam como "fechado" pra R-E (espelha McpTask::isClosed()). */
    private const CLOSED_STATUSES = ['done', 'cancelled'];

    private readonly WorkLeaseService $leases;

    public function __construct(?WorkLeaseService $leases = null)
    {
        // WorkLeaseService injetável pra teste; resolve via app() no caminho real.
        $this->leases = $leases ?? app(WorkLeaseService::class);
    }

    public function name(): string
    {
        return 'tasks';
    }

    public function description(): string
    {
        return 'Detecta incoerências de governança em mcp_tasks (doing órfã sem lease, done sem acceptance_ref, blocked_by já resolvido) — alerta-only';
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        return ['tier_0', 'governance', 'tasks'];
    }

    public function reconcile(array $opts = []): ReconcileResult
    {
        $start = microtime(true);

        $heal = ($opts['heal'] ?? false) === true;
        $dryRun = ($opts['dry_run'] ?? false) === true;

        // Sem o cache de tasks migrado não há o que reconciliar — synced honesto
        // (degrada gracioso, como WorkLeaseService::taskExists faz).
        if (! Schema::hasTable('mcp_tasks')) {
            return ReconcileResult::synced(
                $this->name(),
                (int) ((microtime(true) - $start) * 1000),
                ['heal' => $heal, 'dry_run' => $dryRun, 'reason' => 'mcp_tasks ausente'],
            );
        }

        $tasksRows = $this->coletarTasks();
        $activeLeaseTaskIds = $this->coletarActiveLeaseTaskIds();

        $drifts = $this->analisar($tasksRows, $activeLeaseTaskIds);

        $durationMs = (int) ((microtime(true) - $start) * 1000);

        return ReconcileResult::from(
            name: $this->name(),
            drifts: $drifts,
            durationMs: $durationMs,
            metadata: [
                'heal' => $heal,
                'dry_run' => $dryRun,
                'detect_only' => true,
                'tasks_observed' => count($tasksRows),
                'active_leases' => count($activeLeaseTaskIds),
            ],
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // NÚCLEO PURO — sem I/O. Recebe tasks + leases ativos e devolve os drifts.
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Compara o estado das tasks com os invariantes de governança e devolve os
     * drifts. PURO + determinístico: nada de DB/clock aqui — tudo injetado.
     *
     * Forma esperada de cada item de $tasksRows (chaves toleradas; ausência = vazio):
     *   - task_id:        string  identificador da task (chave do drift).
     *   - status:         string  status canônico (doing/done/cancelled/...).
     *   - acceptance_ref: ?string prova de DoD (R-B: NULL/'' = furo).
     *   - blocked_by:     list<string>  task_ids que bloqueiam esta (R-E).
     *
     * @param array<int, array{
     *     task_id?: string,
     *     status?: string,
     *     acceptance_ref?: ?string,
     *     blocked_by?: list<string>
     * }> $tasksRows
     * @param list<string> $activeLeaseTaskIds task_ids com lease ATIVO agora.
     * @return array<int, ReconcileDrift>
     */
    public function analisar(array $tasksRows, array $activeLeaseTaskIds): array
    {
        $leaseSet = array_fill_keys($activeLeaseTaskIds, true);

        // Mapa task_id → status pra resolver R-E (a task referenciada está fechada?).
        $statusPorTask = [];
        foreach ($tasksRows as $row) {
            $tid = (string) ($row['task_id'] ?? '');
            if ($tid === '') {
                continue;
            }
            $statusPorTask[$tid] = (string) ($row['status'] ?? '');
        }

        $drifts = [];

        foreach ($tasksRows as $row) {
            $taskId = (string) ($row['task_id'] ?? '');
            if ($taskId === '') {
                continue;
            }
            $status = (string) ($row['status'] ?? '');

            // ── R-A: doing SEM lease ativo ────────────────────────────────────
            if ($status === 'doing' && ! isset($leaseSet[$taskId])) {
                $drifts[] = new ReconcileDrift(
                    target: self::T_DOING_ORFA.':'.$taskId,
                    detail: "Task '{$taskId}' está em 'doing' SEM lease ativo (mcp_work_leases). "
                        .'Coordenação perdida — quem está executando? (ADR 0278 work-lease). '
                        .'Resolver (re-claim ou mover status) é decisão humana.',
                    desired: "doing ⇒ lease ativo pra '{$taskId}'",
                    observed: 'doing sem lease ativo',
                    healable: false,
                );
            }

            // ── R-B: done SEM acceptance_ref ──────────────────────────────────
            if ($status === 'done') {
                $ref = $row['acceptance_ref'] ?? null;
                $refStr = is_string($ref) ? trim($ref) : '';
                if ($refStr === '') {
                    $drifts[] = new ReconcileDrift(
                        target: self::T_DONE_SEM_ACCEPTANCE.':'.$taskId,
                        detail: "Task '{$taskId}' está 'done' sem `acceptance_ref` (prova de DoD, ADR 0278 Fase 2). "
                            .'Furo de rastreabilidade — done sem evidência. Preencher é decisão humana.',
                        desired: 'done ⇒ acceptance_ref preenchido',
                        observed: $ref === null ? 'acceptance_ref NULL' : "acceptance_ref vazio ('')",
                        healable: false,
                    );
                }
            }

            // ── R-E: blocked_by referencia task JÁ fechada ────────────────────
            foreach ($this->normalizarBlockedBy($row['blocked_by'] ?? []) as $bloqueador) {
                $statusBloqueador = $statusPorTask[$bloqueador] ?? null;
                if ($statusBloqueador !== null && in_array($statusBloqueador, self::CLOSED_STATUSES, true)) {
                    $drifts[] = new ReconcileDrift(
                        target: self::T_BLOCKED_BY_RESOLVIDO.':'.$taskId.':'.$bloqueador,
                        detail: "Task '{$taskId}' está bloqueada por '{$bloqueador}', que já está "
                            ."'{$statusBloqueador}' (fechada). O bloqueio venceu mas a referência ficou stale — "
                            .'task pode estar represada sem motivo. Desbloquear é decisão humana.',
                        desired: "blocked_by['{$bloqueador}'] removido (bloqueador fechado)",
                        observed: "blocked_by ainda referencia '{$bloqueador}' ({$statusBloqueador})",
                        healable: false,
                    );
                }
            }
        }

        return $drifts;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // COLETA DE ESTADO (I/O) — observed do DB + leases ativos do Service.
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Materializa as tasks relevantes (status que importa pras 3 facetas) em linhas
     * normalizadas pro núcleo puro. `blocked_by` é coluna JSON (cast array em
     * {@see McpTask}); aqui decodificamos do raw string da query builder.
     *
     * @return array<int, array{task_id: string, status: string, acceptance_ref: ?string, blocked_by: list<string>}>
     */
    private function coletarTasks(): array
    {
        // Só precisamos de tasks que possam disparar alguma faceta: 'doing'/'done'
        // (R-A/R-B) + qualquer uma com blocked_by não-nulo (R-E). Mas pra resolver
        // R-E precisamos do status do BLOQUEADOR também → trazemos o conjunto que
        // cobre tudo: status em doing/done OU blocked_by preenchido. Pra o mapa de
        // status do bloqueador ser completo, trazemos também as fechadas referenciadas;
        // o jeito honesto e simples é trazer todas as linhas com os 4 campos (a tabela
        // é cache de planejamento, não tem volume de tabela transacional).
        $rows = DB::table('mcp_tasks')
            ->select(['task_id', 'status', 'acceptance_ref', 'blocked_by'])
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $taskId = is_string($row->task_id ?? null) ? $row->task_id : '';
            if ($taskId === '') {
                continue;
            }

            $out[] = [
                'task_id' => $taskId,
                'status' => is_string($row->status ?? null) ? $row->status : '',
                'acceptance_ref' => is_string($row->acceptance_ref ?? null) ? $row->acceptance_ref : null,
                'blocked_by' => $this->decodificarBlockedBy($row->blocked_by ?? null),
            ];
        }

        return $out;
    }

    /**
     * task_ids com lease ATIVO agora (released_at NULL + dentro do TTL). Espelha
     * {@see WorkLeaseService::activeLeases}.
     *
     * @return list<string>
     */
    private function coletarActiveLeaseTaskIds(): array
    {
        $out = [];
        foreach ($this->leases->activeLeases(PHP_INT_MAX) as $lease) {
            $tid = is_object($lease) && isset($lease->task_id) && is_string($lease->task_id) ? $lease->task_id : '';
            if ($tid !== '') {
                $out[] = $tid;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * Decodifica a coluna JSON `blocked_by` (raw da query builder pode vir string
     * JSON, array já-decodificado, ou null) numa list<string> de task_ids.
     *
     * @return list<string>
     */
    private function decodificarBlockedBy(mixed $raw): array
    {
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            $raw = $decoded;
        }

        return $this->normalizarBlockedBy(is_array($raw) ? $raw : []);
    }

    /**
     * Normaliza uma lista de bloqueadores pra list<string> (só task_ids não-vazios,
     * deduplicada, ordem preservada). PURO.
     *
     * @param array<int|string, mixed> $valores
     * @return list<string>
     */
    private function normalizarBlockedBy(array $valores): array
    {
        $out = [];
        foreach ($valores as $valor) {
            if (! is_scalar($valor)) {
                continue;
            }
            $tid = trim((string) $valor);
            if ($tid !== '' && ! in_array($tid, $out, true)) {
                $out[] = $tid;
            }
        }

        return $out;
    }
}
