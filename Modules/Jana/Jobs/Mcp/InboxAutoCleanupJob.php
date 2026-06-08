<?php

declare(strict_types=1);

namespace Modules\Jana\Jobs\Mcp;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Jana\Entities\Mcp\McpInboxNotification;

/**
 * Bug #3 fix (2026-05-13) — auto-cleanup de inbox stale.
 *
 * Marca como `read` todas as `mcp_inbox_notifications` com:
 *   - read_at IS NULL (unread)
 *   - created_at < now() - 7 dias (stale)
 *
 * Roda daily 04:00 BRT em `app/Console/Kernel.php` (live only).
 *
 * MULTI-TENANT: per-user job, sem business_id by design (ADR 0093
 * §"Commands & Jobs sem HTTP context"). `mcp_inbox_notifications` e PER-USER
 * (user_id), NAO tem coluna business_id — isolamento ja e feito pelo user_id
 * (cada notification tem dono explicito). Confirmado em
 * Modules/Jana/Entities/Mcp/McpInboxNotification.php (sem trait BusinessScope,
 * sem coluna business_id). Wave 16 governance v3 — marker reforcado pra
 * rubrica D1 v3.2 hardened.
 *
 * Idempotente — segunda execução não muda nada (filtro `whereNull('read_at')`
 * exclui as já marcadas no run anterior).
 *
 * Custo: ZERO LLM, só UPDATE SQL.
 *
 * @see memory/requisitos/Jana/BUGS-MCP-SYNC-2026-05-13.md (Bug #3)
 */
class InboxAutoCleanupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $backoff = 60;

    /** Idade mínima (dias) pra notification virar elegível pro auto-mark-read. */
    public const STALE_AFTER_DAYS = 7;

    /**
     * Constructor opcional (Wave 17 D1.c hardened).
     * `$businessId = null` = cross-tenant (default — cleanup global).
     * `$businessId = int` = override pra reprocessamento targeted.
     */
    public function __construct(
        public readonly ?int $businessId = null,
    ) {
    }

    public function handle(): void
    {
        $cutoff = now()->subDays(self::STALE_AFTER_DAYS);

        // UPDATE em massa — uma query só, transacional. Idempotente: rodar 2x
        // não altera nada (whereNull('read_at') filtra as já tocadas).
        $count = McpInboxNotification::query()
            ->whereNull('read_at')
            ->where('created_at', '<', $cutoff)
            ->update(['read_at' => now()]);

        Log::info('InboxAutoCleanupJob.completed', [
            'marked_read' => $count,
            'stale_after_days' => self::STALE_AFTER_DAYS,
            'cutoff_at' => $cutoff->toIso8601String(),
        ]);
    }

    /** @return array<int, string> */
    public function tags(): array
    {
        return ['jana', 'mcp', 'inbox', 'auto-cleanup'];
    }
}
