<?php

declare(strict_types=1);

namespace Modules\PaymentGateway\Console\Commands;

use Illuminate\Console\Command;
use Modules\PaymentGateway\Jobs\RetryOrphanWebhookJob;
use Modules\PaymentGateway\Models\GatewayWebhookEvent;

/**
 * ADR 0170 Onda 4e — Cron entry-point pra RetryOrphanWebhookJob.
 *
 * Schedule (app/Console/Kernel.php) — REGISTRADO mas DORMENTE por flag:
 *   $schedule->command('paymentgateway:retry-orphan-webhooks')
 *       ->everyFiveMinutes()
 *       ->withoutOverlapping(10)
 *       ->environments(['live'])
 *       ->when(fn () => (bool) config('paymentgateway.retry_orphan_webhooks_enabled', false));
 *
 * ⚠️ default-OFF (PAYMENTGATEWAY_RETRY_ORPHAN_WEBHOOKS_ENABLED — REGRA MESTRE
 * valor/estoque): o Job quita título (CobrancaPaga → fin_titulo) = mexe em VALOR.
 * Habilitar SÓ após cutover dos webhooks genéricos (Onda 3) + linkage cobranca_id
 * no WebhookProcessor + dry-run aprovado pelo Wagner. Hoje gateway_webhook_events
 * nasce com cobranca_id NULL (WebhookProcessor não resolve a Cobrança), então a
 * branch de quitação do Job é INALCANÇÁVEL e o cron só marcaria still_orphan.
 * A quitação PIX biz=1 LIVE roda por OUTRO caminho (inter_webhook_log +
 * ProcessarWebhookPixInterJob), não por aqui.
 *
 * Race condition coberta (quando o linkage existir): webhook chegou antes da
 * Cobranca ser gravada. Detalhes na PHPDoc de `RetryOrphanWebhookJob`.
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

            // SUPERADMIN: comando CLI roda sem sessão web; --dry-run lista webhook events órfãos de todos os tenants pra Wagner inspecionar antes do cutover.
            $orphans = GatewayWebhookEvent::query()
                ->withoutGlobalScopes()
                ->whereNull('processed_at')
                ->where('created_at', '>', now()->subDay())
                ->where('created_at', '<', now()->subHour())
                ->orderBy('created_at')
                ->limit(50)
                ->get();

            $this->line(sprintf('%d órfão(s) na janela 1h..24h.', $orphans->count()));

            // REGRA MESTRE valor/estoque: mostra quantos já têm cobranca_id
            // linkado (= candidatos a disparar CobrancaPaga = quitam título) vs
            // ainda sem linkage. É o "antes->depois" no nível do webhook que o
            // Wagner inspeciona ANTES de ligar a flag. (O delta de fin_titulo em
            // si sai no cutover, com a tabela populada.)
            $linked = $orphans->whereNotNull('cobranca_id')->count();
            $this->line(sprintf(
                '  %d com cobranca_id linkado (candidatos a CobrancaPaga / quitação) · %d ainda sem linkage.',
                $linked,
                $orphans->count() - $linked,
            ));

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
