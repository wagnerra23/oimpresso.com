<?php

declare(strict_types=1);

namespace Modules\Jana\Services\TaskRegistry;

use Illuminate\Support\Facades\Log;
use Modules\Jana\Entities\Mcp\McpTask;
use Modules\Jana\Entities\Mcp\McpTaskEvent;

/**
 * HitlEscalationService — o elo que faltava entre DETECTAR e DECIDIR.
 *
 * ── O PROBLEMA (medido 2026-07-27, não suposto) ─────────────────────────────
 * O sistema tem detecção excelente e fechamento zero. Doze sentinelas agendados
 * (`handoff:stale-alert`, `*:health-check`, `governance:detect-drift`, …) foram
 * varridos: **12 de 12 criam ZERO task**. Todos notificam (`McpInboxNotification`
 * type=`due_soon`) ou logam, e param aí. Resultado observado ao vivo: o
 * `handoff:stale-alert` repetia o MESMO alerta havia **38 dias** (30d → 33d →
 * 35d → 36d → 38d) sem nunca virar decisão de ninguém. Nag perpétuo é ruído com
 * cara de vigilância.
 *
 * O canal de decisão JÁ EXISTE e o [W] o lê em toda sessão: a procedure do brief
 * (`2026_05_06_172445_fix_brief_procedure_real_schema.php`) define
 * `HITL pending Wagner` = `mcp_tasks WHERE status='blocked' AND owner='wagner'`,
 * e o `brief-fetch` imprime o topo inline. Faltava **transporte**: ninguém
 * escrevia lá a partir de um sentinela.
 *
 * ── O QUE ESTE SERVIÇO É (e o que NÃO é) ────────────────────────────────────
 * É TRANSPORTE idempotente: pega uma pendência que um sentinela JÁ detectou e a
 * materializa como UMA task `blocked`/`wagner` no canal que o brief lê. Não
 * detecta nada, não julga nada, não computa cobertura, não vira gate.
 *
 * NÃO É catraca nem presence-gate (proibicoes §5): não mede presença de campo,
 * não bloqueia merge, não tem exit code. E não é canal novo — o `mcp_tasks` é o
 * dono do tema (ADR 0070: estado vivo de tasks é MCP, nunca markdown).
 *
 * ── A REGRA QUE IMPEDE A MÁQUINA DE BRIGAR COM O HUMANO ─────────────────────
 * `task_id` é DETERMINÍSTICO (`HITL-<CHAVE>`), então re-escalar atualiza a MESMA
 * task em vez de criar a 39ª. E o estado humano vence:
 *
 *   inexistente          → cria `blocked`/`wagner`      (entra no brief)
 *   `blocked`            → atualiza descrição + evento  (1 item, não 38)
 *   `todo|doing|review`  → NÃO mexe   (alguém está tratando — não rebaixa)
 *   `done|cancelled`     → NÃO reabre (o humano decidiu — só loga)
 *
 * O último caso é o que separa transporte de teimosia: um sentinela que reabre
 * o que o dono fechou é pior que um que não avisa.
 *
 * ── TIER 0 ──────────────────────────────────────────────────────────────────
 * `mcp_tasks` é tabela de GOVERNANÇA (sem `business_id` — verificado na
 * migration `2026_04_30_180001`), não de negócio. Nenhum dado de tenant passa
 * por aqui. O `descricao` vem do sentinela: quem chamar é responsável por não
 * colar PII nem valor BRL (proibicoes §Memória/governança).
 *
 * @see Modules\TeamMcp\Console\Commands\HandoffStaleAlertCommand  1º consumidor
 * @see database/migrations/2026_05_06_172445_fix_brief_procedure_real_schema.php
 * @see memory/decisions/0070-jira-style-task-management-current-md-removed.md
 */
final class HitlEscalationService
{
    /** Prefixo do `task_id` determinístico — torna o item rastreável e não-duplicável. */
    public const PREFIXO = 'HITL-';

    /** Estados em que alguém já está tratando: o sentinela NÃO rebaixa pra blocked. */
    private const EM_TRATAMENTO = ['todo', 'doing', 'review'];

    /** Estados fechados pelo humano: o sentinela NÃO reabre. */
    private const FECHADOS = ['done', 'cancelled'];

    /**
     * Materializa (ou atualiza) UMA pendência no canal HITL do brief.
     *
     * @param  string $chave     identificador estável da pendência, ex: 'HANDOFF-STALE'.
     *                           Vira `HITL-HANDOFF-STALE`. NÃO inclua data/contagem aqui —
     *                           a chave tem que ser a mesma amanhã, senão vira spam.
     * @param  string $titulo    linha que o [W] lê no brief.
     * @param  string $descricao o estado atual + as saídas possíveis. Sem PII, sem BRL.
     * @param  string $modulo    módulo de origem (só rótulo).
     * @param  string $prioridade p0..p3.
     * @param  string $origem    quem escalou (nome do command) — vai pro evento de auditoria.
     * @return McpTask|null      a task viva, ou null quando o humano já fechou o assunto.
     */
    public function escalar(
        string $chave,
        string $titulo,
        string $descricao,
        string $modulo,
        string $prioridade = 'p2',
        string $origem = 'sentinela',
    ): ?McpTask {
        $taskId = self::PREFIXO . strtoupper(trim($chave));
        $task = McpTask::where('task_id', $taskId)->first();

        if ($task === null) {
            return $this->criar($taskId, $titulo, $descricao, $modulo, $prioridade, $origem);
        }

        if (in_array($task->status, self::FECHADOS, true)) {
            Log::channel('single')->info('hitl.escalation.suprimida_fechada', [
                'task_id' => $taskId,
                'status' => $task->status,
                'origem' => $origem,
                'motivo' => 'humano fechou o assunto — sentinela não reabre',
            ]);

            return null;
        }

        if (in_array($task->status, self::EM_TRATAMENTO, true)) {
            Log::channel('single')->info('hitl.escalation.suprimida_em_tratamento', [
                'task_id' => $taskId,
                'status' => $task->status,
                'origem' => $origem,
            ]);

            return $task;
        }

        // status === 'blocked': o item segue aberto — atualiza o retrato, não duplica.
        $task->update([
            'title' => $titulo,
            'description' => $descricao,
            'priority' => $this->prioridadeValida($prioridade),
        ]);

        McpTaskEvent::log($taskId, 'updated', null, null, $origem, 'HITL re-escalado (pendência persiste)');

        return $task->refresh();
    }

    private function criar(
        string $taskId,
        string $titulo,
        string $descricao,
        string $modulo,
        string $prioridade,
        string $origem,
    ): McpTask {
        $task = McpTask::create([
            'task_id' => $taskId,
            'identifier' => $taskId,
            'module' => $modulo,
            'title' => $titulo,
            'description' => $descricao,
            'status' => 'blocked',
            'type' => 'chore',
            'owner' => 'wagner',
            'priority' => $this->prioridadeValida($prioridade),
            'source_path' => 'hitl-escalation',
            'parsed_at' => now(),
        ]);

        McpTaskEvent::log($taskId, 'created', null, 'blocked', $origem, 'HITL escalado por sentinela');

        Log::channel('single')->warning('hitl.escalation.criada', [
            'task_id' => $taskId,
            'origem' => $origem,
            'modulo' => $modulo,
        ]);

        return $task;
    }

    private function prioridadeValida(string $p): string
    {
        return in_array($p, ['p0', 'p1', 'p2', 'p3'], true) ? $p : 'p2';
    }
}
