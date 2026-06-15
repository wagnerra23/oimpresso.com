<?php

declare(strict_types=1);

namespace Modules\Jana\Services\WorkLease;

use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * WorkLeaseService — lógica do lease de coordenação (D1, ADR 0278 / proposal #2766).
 *
 * SoC brutal (Princípio 5): toda a regra de TTL/expiração/compare-and-set vive AQUI,
 * não no schema (coluna gerada não pode usar now()) nem nas tools (que só adaptam
 * o protocolo MCP). O invariante "1 lease ativo por task" é enforçado pelo UNIQUE
 * (task_id, active_marker) da tabela — este service o respeita e o expõe.
 *
 * Lease "ativo" = released_at IS NULL AND expires_at > now. A coluna gerada só
 * conhece released_at (determinística); a janela de TTL é checada em runtime aqui.
 */
class WorkLeaseService
{
    /** TTL do lease em minutos (ADR 0119 — alinhado à janela do whats-active). */
    public const TTL_MINUTES = 30;

    /**
     * Expira (seta released_at) leases ainda-ativos cujo expires_at já passou.
     * Idempotente. Chamado antes de cada claim/listagem pra que o UNIQUE não barre
     * um re-claim legítimo de task cujo dono anterior sumiu (crash/timeout).
     */
    public function sweepExpired(): int
    {
        return DB::table('mcp_work_leases')
            ->whereNull('released_at')
            ->where('expires_at', '<', now())
            ->update(['released_at' => now(), 'updated_at' => now()]);
    }

    /** Lease ATIVO de uma task (released_at NULL e dentro do TTL), ou null. */
    public function activeLeaseFor(string $taskId): ?object
    {
        return DB::table('mcp_work_leases')
            ->where('task_id', $taskId)
            ->whereNull('released_at')
            ->where('expires_at', '>', now())
            ->first();
    }

    /**
     * task_id existe no cache mcp_tasks? Validação de runtime (não há FK — mcp_tasks
     * é cache git-synced). Em ambiente sem o cache migrado, não bloqueia (degrada
     * gracioso, como WhatsActiveTool faz com mcp_cc_sessions).
     */
    public function taskExists(string $taskId): bool
    {
        if (! Schema::hasTable('mcp_tasks')) {
            return true;
        }

        return DB::table('mcp_tasks')->where('task_id', $taskId)->exists();
    }

    /**
     * Claim compare-and-set de uma task.
     *
     * @return array{ok: bool, lease?: object|null, holder?: object|null, reason?: string, renewed?: bool}
     *   ok=true  → lease adquirido (ou renovado, se já era do mesmo principal)
     *   ok=false → reason 'held' (outro dono ativo) | 'race' (perdeu corrida pro UNIQUE)
     */
    public function claim(string $taskId, string $humanPrincipal, ?string $agentId = null, ?string $session = null): array
    {
        $this->sweepExpired();

        $existing = $this->activeLeaseFor($taskId);
        if ($existing !== null) {
            // Mesmo dono pega de novo → trata como heartbeat (renova), não conflito.
            if ($existing->human_principal === $humanPrincipal) {
                $this->heartbeat($taskId, $humanPrincipal);

                return ['ok' => true, 'lease' => $this->activeLeaseFor($taskId), 'renewed' => true];
            }

            return ['ok' => false, 'reason' => 'held', 'holder' => $existing];
        }

        try {
            DB::table('mcp_work_leases')->insert([
                'task_id' => $taskId,
                'human_principal' => $humanPrincipal,
                'agent_id' => $agentId,
                'claude_code_session' => $session,
                'acquired_at' => now(),
                'heartbeat_at' => now(),
                'expires_at' => now()->addMinutes(self::TTL_MINUTES),
                'released_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (QueryException $e) {
            // Corrida concorrente perdida pro UNIQUE(task_id, active_marker) — re-lê
            // o vencedor pra reportar quem segura (em vez de estourar exceção).
            $holder = $this->activeLeaseFor($taskId);
            if ($holder === null) {
                throw $e; // não foi colisão de invariante — propaga
            }

            return ['ok' => false, 'reason' => 'race', 'holder' => $holder];
        }

        return ['ok' => true, 'lease' => $this->activeLeaseFor($taskId)];
    }

    /** Estende o TTL (heartbeat). Só age se o principal segura o lease ativo. */
    public function heartbeat(string $taskId, string $humanPrincipal): bool
    {
        $affected = DB::table('mcp_work_leases')
            ->where('task_id', $taskId)
            ->where('human_principal', $humanPrincipal)
            ->whereNull('released_at')
            ->update([
                'heartbeat_at' => now(),
                'expires_at' => now()->addMinutes(self::TTL_MINUTES),
                'updated_at' => now(),
            ]);

        return $affected > 0;
    }

    /** Libera o lease (release explícito). Só o próprio principal libera. */
    public function release(string $taskId, string $humanPrincipal): bool
    {
        $affected = DB::table('mcp_work_leases')
            ->where('task_id', $taskId)
            ->where('human_principal', $humanPrincipal)
            ->whereNull('released_at')
            ->update(['released_at' => now(), 'updated_at' => now()]);

        return $affected > 0;
    }

    /** Leases ATIVOS no momento (não-liberados, dentro do TTL). */
    public function activeLeases(int $limit = 30): Collection
    {
        $this->sweepExpired();

        return DB::table('mcp_work_leases')
            ->whereNull('released_at')
            ->where('expires_at', '>', now())
            ->orderBy('acquired_at')
            ->limit($limit)
            ->get();
    }
}
