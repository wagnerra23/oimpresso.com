<?php

declare(strict_types=1);

namespace Modules\TeamMcp\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Entities\Mcp\McpInboxNotification;
use Modules\TeamMcp\Entities\CoworkHandoff;

/**
 * HandoffStaleAlertCommand — PR-4 Loop de Handoff Zero-Paste (Fase 0 · ADR 0283).
 *
 * Anti feedback-void: handoffs de design 'pending' há mais de N dias (default 3)
 * alertam o ops — pra reauditar/aplicar ou rejeitar antes que apodreçam.
 * Schedule daily 08:30 BRT (app/Console/Kernel.php).
 *
 * **"channel ops" da spec → modelo do main:** `McpInboxNotification` é per-user
 * com `type` enum (SEM canal). Então o alerta vira 1 notificação `type='due_soon'`
 * pro user de ops (`config('admin.wagner_user_id')`). Onde a spec diverge do main,
 * o main vence.
 *
 * **Idempotente:** 1 digest por dia (marker {@see self::MARKER} + `whereDate` hoje)
 * — evita spam diário; re-alerta no dia seguinte se ainda pendente (nag).
 *
 * Convenções ({@see .claude/rules/commands.md}): `--days`/`--dry-run` (não
 * `--verbose`), output PT-BR, exit 0/1.
 *
 * @see Modules\TeamMcp\Entities\CoworkHandoff
 * @see Modules\Jana\Entities\Mcp\McpInboxNotification
 * @see memory/decisions/0283-handoff-loop-zero-paste.md
 */
final class HandoffStaleAlertCommand extends Command
{
    /** Prefixo machine-detectável do body — usado no dedup diário. */
    private const MARKER = '[handoff-stale]';

    protected $signature = 'handoff:stale-alert
        {--days=3 : Idade mínima (dias) pra considerar um pending velho}
        {--dry-run : Mostra o que alertaria, não grava notificação}';

    protected $description = 'Alerta no inbox ops handoffs de design pendentes há > N dias (anti feedback-void, ADR 0283). Idempotente: 1 digest/dia.';

    public function handle(): int
    {
        if (! Schema::hasTable('cowork_handoffs')) {
            $this->error('Tabela cowork_handoffs não existe. Rode php artisan migrate primeiro.');

            return self::FAILURE;
        }

        $days = max(1, (int) $this->option('days'));
        $cutoff = now()->subDays($days);

        $stale = CoworkHandoff::query()
            ->where('status', 'pending')
            ->where('created_at', '<=', $cutoff)
            ->orderBy('created_at')
            ->get();

        if ($stale->isEmpty()) {
            $this->info("Nenhum handoff pendente há mais de {$days}d.");

            return self::SUCCESS;
        }

        $lines = $stale->map(function (CoworkHandoff $h) {
            $age = $h->created_at !== null ? (int) $h->created_at->diffInDays(now()) : 0;

            return "• {$h->slug} v{$h->version} ({$h->tela}) — pendente há {$age}d";
        });

        $body = self::MARKER . " ⏰ {$stale->count()} handoff(s) de design pendente(s) há > {$days}d (Cowork→Code). "
            . "Reaudite contra o main e aplique, ou rejeite com note:\n" . $lines->implode("\n");

        $payload = [
            'kind'  => 'handoff_stale',
            'days'  => $days,
            'count' => $stale->count(),
            'slugs' => $stale->pluck('slug')->all(),
        ];

        if ((bool) $this->option('dry-run')) {
            $this->info("[DRY RUN] alertaria {$stale->count()} handoff(s):");
            $this->line($body);

            return self::SUCCESS;
        }

        $opsUser = (int) config('admin.wagner_user_id', 1);

        // Idempotência: 1 digest por dia (evita spam). Re-alerta amanhã se persistir.
        $alreadyToday = McpInboxNotification::query()
            ->where('user_id', $opsUser)
            ->where('type', 'due_soon')
            ->where('body', 'like', self::MARKER . '%')
            ->whereDate('created_at', today())
            ->exists();

        if ($alreadyToday) {
            $this->info("Já alertado hoje ({$stale->count()} pendente(s) > {$days}d) — sem duplicar.");

            return self::SUCCESS;
        }

        McpInboxNotification::notify(
            userId: $opsUser,
            type: 'due_soon',
            body: $body,
            payload: $payload,
        );

        $this->info("Alertado: {$stale->count()} handoff(s) pendente(s) > {$days}d → inbox ops (user {$opsUser}).");

        return self::SUCCESS;
    }
}
