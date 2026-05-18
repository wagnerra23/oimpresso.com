<?php

declare(strict_types=1);

namespace Modules\RecurringBilling\Console\Commands;

use Illuminate\Console\Command;
use Modules\RecurringBilling\Models\Subscription;
use Modules\RecurringBilling\Observers\SubscriptionCachedFieldsObserver;

/**
 * Backfill — recalcula campos cached em rb_subscriptions a partir do estado
 * REAL de rb_invoices. Onda 2 do plano v9,75 RecurringBilling.
 *
 * Útil pós-migration v975 OU se Observer perdeu eventos (ex: Job assíncrono
 * que escreveu Invoice sem trigger observable em fila com cache miss).
 *
 * Multi-tenant Tier 0: opcionalmente scope por --business={id}; sem flag
 * roda em todos businesses (modo admin).
 *
 * Idempotente: re-rodar não causa drift; recomputa sempre baseado em invoices.
 *
 * Uso:
 *   php artisan rb:backfill-cached-fields                # todos businesses
 *   php artisan rb:backfill-cached-fields --business=1   # só biz=1
 *   php artisan rb:backfill-cached-fields --dry-run      # mostra sem persistir
 */
class BackfillCachedFieldsCommand extends Command
{
    protected $signature = 'rb:backfill-cached-fields
                            {--business= : Filtrar por business_id específico (default: todos)}
                            {--dry-run : Mostrar antes/depois sem persistir}';

    protected $description = 'Recalcula campos cached em rb_subscriptions (Onda 2 v9,75)';

    public function handle(SubscriptionCachedFieldsObserver $observer): int
    {
        $businessId = $this->option('business');
        $dryRun = (bool) $this->option('dry-run');

        $query = Subscription::query();
        if ($businessId !== null && $businessId !== '') {
            $query->where('business_id', (int) $businessId);
            $this->info(sprintf('Backfill scopado biz=%d', (int) $businessId));
        } else {
            $this->info('Backfill TODOS businesses');
        }

        $total = (clone $query)->count();
        if ($total === 0) {
            $this->warn('Zero subscriptions encontradas — nada a backfillar.');

            return self::SUCCESS;
        }

        $this->info(sprintf('%d subscriptions pra processar%s.', $total, $dryRun ? ' (DRY RUN)' : ''));
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $touched = 0;
        $query->chunkById(200, function ($subs) use ($observer, $dryRun, &$touched, $bar) {
            foreach ($subs as $sub) {
                $before = [
                    'paid'    => $sub->total_paid_cached,
                    'failed'  => $sub->failed_count_cached,
                    'revenue' => $sub->total_revenue_cached,
                ];

                if ($dryRun) {
                    // simula recompute sem persistir
                    $invoices = $sub->invoices()->get(['id', 'status', 'valor']);
                    $paid = $invoices->where('status', 'paid');
                    $overdue = $invoices->where('status', 'overdue');
                    $after = [
                        'paid'    => $paid->count(),
                        'failed'  => $overdue->count(),
                        'revenue' => (float) $paid->sum('valor'),
                    ];
                    if ($before !== $after) {
                        $touched++;
                    }
                } else {
                    $observer->recomputeForSubscription($sub->id);
                    $sub->refresh();
                    $after = [
                        'paid'    => $sub->total_paid_cached,
                        'failed'  => $sub->failed_count_cached,
                        'revenue' => $sub->total_revenue_cached,
                    ];
                    if ($before !== $after) {
                        $touched++;
                    }
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        if ($dryRun) {
            $this->warn(sprintf('DRY RUN — %d/%d subscriptions ficariam atualizadas (NADA persistido)', $touched, $total));
        } else {
            $this->info(sprintf('Backfill OK — %d/%d subscriptions atualizadas (%d já estavam consistentes)', $touched, $total, $total - $touched));
        }

        return self::SUCCESS;
    }
}
