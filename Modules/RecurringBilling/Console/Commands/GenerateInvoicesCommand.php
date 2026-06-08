<?php

declare(strict_types=1);

namespace Modules\RecurringBilling\Console\Commands;

use App\Business;
use Illuminate\Console\Command;
use Modules\RecurringBilling\Services\InvoiceGeneratorService;

/**
 * rb:generate-invoices — Gera faturas recorrentes pendentes.
 *
 * US-RB-003: pra cada Subscription ativa com `next_due_date <= hoje + lead-days`:
 *   1. Cria Invoice (status=open, vencimento=next_due_date, conta_bancaria_id
 *      da subscription, numero_documento RB-{id}-{YYYY-MM}).
 *   2. Avança Subscription.next_due_date += ciclo do plan.
 *   3. Logga SubscriptionEvent kind=event-charge na timeline.
 *
 * Idempotente — SKIP se já existe Invoice mesma competência (YYYY-MM)
 * pra essa subscription (status != canceled).
 *
 * Schedule: daily 03:00 BRT em env=live
 * (registrado em `RecurringBillingServiceProvider::registerCommandSchedules`).
 *
 * Multi-tenant Tier 0 ([ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)):
 *   - Sem --business: itera todos businesses ativos
 *   - Com --business=N: filtra explicitamente
 *   - Sem session()/auth() — commands CLI passam $businessId direto
 *
 * Uso:
 *   php artisan rb:generate-invoices                          # todos businesses, hoje
 *   php artisan rb:generate-invoices --business=1             # só biz=1
 *   php artisan rb:generate-invoices --date=2026-07-15        # simular data
 *   php artisan rb:generate-invoices --lead-days=3            # antecipa 3 dias
 *   php artisan rb:generate-invoices --dry-run                # não escreve
 *
 * Exit code: 0 OK, 1 se houver erros.
 *
 * @see Modules\RecurringBilling\Services\InvoiceGeneratorService
 * @see memory/requisitos/RecurringBilling/SPEC.md (US-RB-003)
 */
class GenerateInvoicesCommand extends Command
{
    protected $signature = 'rb:generate-invoices
        {--business= : business_id (default: todos businesses ativos)}
        {--date= : Y-m-d pra simular "hoje" (default: hoje real)}
        {--lead-days=0 : antecipação em dias (default: 0)}
        {--dry-run : não escreve nada — só reporta o que faria}
        {--detail : log detalhado por subscription processada}';

    protected $description = 'Gera rb_invoices das rb_subscriptions ativas com next_due_date <= hoje (US-RB-003).';

    public function handle(InvoiceGeneratorService $service): int
    {
        $businessOpt = $this->option('business');
        $date        = $this->option('date');
        $leadDays    = (int) ($this->option('lead-days') ?? 0);
        $dryRun      = (bool) $this->option('dry-run');
        $detail      = (bool) $this->option('detail');

        if ($businessOpt !== null && $businessOpt !== '') {
            $bizIds = [(int) $businessOpt];
        } else {
            // Multi-tenant Tier 0 — itera todos businesses ativos
            $bizIds = Business::query()->pluck('id')->map(fn ($i) => (int) $i)->all();
        }

        $totals = ['generated' => 0, 'skipped' => 0, 'errors' => 0, 'advanced' => 0];
        $rows = [];

        foreach ($bizIds as $bizId) {
            $stats = $service->run($bizId, $date !== null && $date !== '' ? (string) $date : null, $dryRun, $leadDays);
            $totals['generated'] += $stats['generated'];
            $totals['skipped']   += $stats['skipped'];
            $totals['errors']    += $stats['errors'];
            $totals['advanced']  += $stats['advanced'];

            // Só lista businesses que tiveram algum movimento (evita poluir output em prod com 100+ tenants)
            if ($detail || $stats['generated'] > 0 || $stats['skipped'] > 0 || $stats['errors'] > 0) {
                $rows[] = [
                    'biz'       => $bizId,
                    'geradas'   => $stats['generated'],
                    'puladas'   => $stats['skipped'],
                    'erros'     => $stats['errors'],
                    'avancadas' => $stats['advanced'],
                ];
            }
        }

        if (! empty($rows)) {
            $this->table(['biz', 'geradas', 'puladas', 'erros', 'avancadas'], $rows);
        }

        $prefix = $dryRun ? '[DRY-RUN] ' : '';
        $this->info(sprintf(
            '%sTOTAL: %d faturas geradas · %d puladas (já existiam) · %d erros · %d assinaturas avançadas (em %d businesses).',
            $prefix,
            $totals['generated'],
            $totals['skipped'],
            $totals['errors'],
            $totals['advanced'],
            count($bizIds),
        ));

        return $totals['errors'] > 0 ? 1 : 0;
    }
}
