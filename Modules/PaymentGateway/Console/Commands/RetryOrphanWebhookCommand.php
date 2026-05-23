<?php

declare(strict_types=1);

namespace Modules\PaymentGateway\Console\Commands;

use Illuminate\Console\Command;
use Modules\PaymentGateway\Jobs\RetryOrphanWebhookJob;
use Modules\PaymentGateway\Models\GatewayWebhookEvent;

/**
 * ADR 0170 Onda 4e — Cron entry-point pra RetryOrphanWebhookJob.
 *
 * Schedule (app/Console/Kernel.php):
 *   $schedule->command('paymentgateway:retry-orphan-webhooks')
 *       ->everyFiveMinutes()
 *       ->withoutOverlapping(10);
 *
 * Race condition coberta: webhook chegou antes da Cobranca ser gravada.
 * Detalhes na PHPDoc de `RetryOrphanWebhookJob`.
 *
 * `--dry-run` lista o que faria sem dispatchar Job (útil pra Wagner inspecionar
 * antes de cutover prod).
 */
class RetryOrphanWebhookCommand extends Command
{
    protected $signature = 'paymentgateway:retry-orphan-webhooks
                            {--dry-run : Mostra o que faria sem dispatchar}';

    protected $description = 'Re-processa gateway_webhook_events órfãos (processed_at NULL após 1h, dentro de 24h)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('[dry-run] Nenhum CobrancaPaga será dispatchado.');

            $orphans = GatewayWebhookEvent::query()
                ->withoutGlobalScopes()
                ->whereNull('processed_at')
                ->where('created_at', '>', now()->subDay())
                ->where('created_at', '<', now()->subHour())
                ->orderBy('created_at')
                ->limit(50)
                ->get();

            $this->line(sprintf('%d órfão(s) na janela 1h..24h.', $orphans->count()));

            foreach ($orphans as $orphan) {
                $this->line(sprintf(
                    '  #%d biz=%d gateway=%s evento=%s cobranca_id=%s created_at=%s',
                    $orphan->id,
                    $orphan->business_id,
                    $orphan->gateway_key,
                    $orphan->evento,
                    $orphan->cobranca_id ?? 'NULL',
                    $orphan->created_at->toIso8601String(),
                ));
            }

            return self::SUCCESS;
        }

        // Dispatch Job na queue paymentgateway (mesma queue do ProcessarWebhookPixInterJob).
        // RetryOrphanWebhookJob::dispatch() em vez de inline pra não bloquear cron tick.
        RetryOrphanWebhookJob::dispatch();
        $this->info('RetryOrphanWebhookJob dispatched (queue=paymentgateway).');

        return self::SUCCESS;
    }
}
