<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Services\Drivers\WhatsmeowDriver;

/**
 * whatsapp:whatsmeow-resubscribe-events — Fase B: re-assina `LoggedOut` nos canais
 * whatsmeow já provisionados (que nasceram antes da Fase B, sem `LoggedOut`).
 *
 * O WuzAPI só repassa o webhook de eventos **assinados** (coluna `users.events`).
 * Canais provisionados antes da Fase B têm `events="Message,ReadReceipt,Connected,
 * Disconnected"` → um logout remoto ("logged out from another device") nunca chega
 * ao app e `channel_health` fica `healthy` eternamente (raiz do falso "fora do ar",
 * ADR 0286). Este comando aplica o **mecanismo (D)** (`POST /webhook` → UPDATE
 * `users.events` + refresh do cache no daemon) pra cada canal, incluindo `LoggedOut`,
 * **SEM reconectar nem re-parear** (zero impacto na sessão viva). Idempotente.
 *
 * Migração **one-off** (não-recorrente): canais NOVOS já nascem com `LoggedOut`
 * (`WhatsmeowDriver::provisionSession`). Mecanismo validado em canário 2026-06-18.
 *
 * Multi-tenant Tier 0 (ADR 0093): varre cross-business (CLI sem session) via
 * `withoutGlobalScope`; cada row carrega seu `business_id`; o driver nunca
 * atravessa fronteira de tenant (usa o token do próprio canal).
 *
 * Uso:
 *   php artisan whatsapp:whatsmeow-resubscribe-events --dry-run
 *   php artisan whatsapp:whatsmeow-resubscribe-events
 *   php artisan whatsapp:whatsmeow-resubscribe-events --business=1 --detail
 *
 * @see Modules\Whatsapp\Services\Drivers\WhatsmeowDriver::resubscribeEvents
 * @see memory/sessions/2026-06-18-arte-whatsapp-naoficiais.md (POC WAHA-GOWS / Fase B)
 */
class WhatsmeowResubscribeEventsCommand extends Command
{
    protected $signature = 'whatsapp:whatsmeow-resubscribe-events
                            {--business=all : Business ID ou "all" (default)}
                            {--dry-run : Preview sem chamar o daemon}
                            {--detail : Loga o resultado de cada canal}';

    protected $description = 'Re-assina LoggedOut nos canais whatsmeow já provisionados (Fase B · mecanismo D, sem reconnect/re-pair).';

    public function handle(WhatsmeowDriver $driver): int
    {
        $businessOption = (string) $this->option('business');
        $dryRun = (bool) $this->option('dry-run');

        // SUPERADMIN: migração cross-business — bypass scope explícito (ADR 0093).
        $query = Channel::query()
            ->withoutGlobalScope(ScopeByBusiness::class)
            ->where('type', Channel::TYPE_WHATSAPP_WHATSMEOW);

        if ($businessOption !== 'all') {
            $bizId = (int) $businessOption;
            if ($bizId <= 0) {
                $this->error("--business='{$businessOption}' inválido. Use 'all' ou ID numérico.");

                return self::FAILURE;
            }
            $query->where('business_id', $bizId);
        }

        $channels = $query->orderBy('business_id')->orderBy('id')->get();

        if ($channels->isEmpty()) {
            $this->info('Nenhum canal whatsmeow pra re-assinar.');

            return self::SUCCESS;
        }

        $ok = 0;
        $skipped = 0;
        $failed = 0;
        $rows = [];

        foreach ($channels as $channel) {
            $label = mb_strimwidth((string) $channel->label, 0, 24, '…');

            if ($dryRun) {
                $rows[] = [$channel->id, $channel->business_id, $label, '[dry-run]'];
                $skipped++;
                continue;
            }

            $result = $driver->resubscribeEvents($channel);
            $reason = $result['reason'] ?? null;

            if ($result['ok']) {
                $ok++;
                $outcome = 'ok';
            } elseif (in_array($reason, ['no_token', 'no_webhook_url'], true)) {
                // Canal ainda não provisionado no daemon — nada a re-assinar (nascerá certo).
                $skipped++;
                $outcome = "skip:{$reason}";
            } else {
                $failed++;
                $outcome = 'falha:'.($result['status'] ?? '?');
            }

            $rows[] = [$channel->id, $channel->business_id, $label, $outcome];

            if ($this->option('detail')) {
                $this->line(sprintf('  ch#%d biz=%d "%s" → %s', $channel->id, $channel->business_id, $channel->label, $outcome));
            }
        }

        $this->table(['ch_id', 'biz', 'label', 'resultado'], $rows);
        $this->info("Canais: {$channels->count()} · ok: {$ok} · skip: {$skipped} · falha: {$failed}".($dryRun ? ' (dry-run)' : ''));

        Log::info('whatsmeow.resubscribe_events.done', [
            'event' => 'whatsmeow.resubscribe_events.done',
            'total' => $channels->count(),
            'ok' => $ok,
            'skipped' => $skipped,
            'failed' => $failed,
            'dry_run' => $dryRun,
            'business_filter' => $businessOption,
        ]);

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
