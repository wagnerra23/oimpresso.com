<?php

declare(strict_types=1);

namespace Modules\RecurringBilling\Services;

use App\Util\OtelHelper;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\RecurringBilling\Models\Invoice;
use Modules\RecurringBilling\Models\Plan;
use Modules\RecurringBilling\Models\Subscription;
use Modules\RecurringBilling\Models\SubscriptionEvent;
use Throwable;

/**
 * US-RB-003 · Geração de faturas recorrentes (job diário).
 *
 * Pra cada Subscription ativa cujo `next_due_date <= today + leadDays`:
 *
 *   1. Idempotência — SKIP se já existe Invoice mesma competência (YYYY-MM)
 *      pra essa subscription (status != canceled).
 *   2. Cria Invoice (status=open, vencimento=next_due_date, valor=plan.valor,
 *      conta_bancaria_id da subscription, numero_documento RB-{id}-{YYYY-MM}).
 *   3. Avança Subscription.next_due_date += ciclo (monthly/quarterly/semiannual/
 *      yearly) usando addMonth*NoOverflow (Carbon — preserva anchor dia 31 → fev).
 *   4. Logga SubscriptionEvent kind=event-charge pra timeline append-only.
 *
 * Tudo dentro de DB::transaction per-subscription pra atomicidade.
 *
 * Multi-tenant Tier 0 ([ADR 0093](../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)):
 *   - businessId 1º arg sempre (commands/jobs sem session())
 *   - Models usam HasBusinessScope trait → global scope automático
 *   - NUNCA withoutGlobalScopes
 *
 * Tests biz=1 ([ADR 0101](../../../memory/decisions/0101-tests-business-id-1-nunca-cliente.md)):
 *   biz=99 NUNCA vaza dados de biz=1 (Pest valida).
 *
 * @see Modules\RecurringBilling\Console\Commands\GenerateInvoicesCommand
 * @see memory/requisitos/RecurringBilling/SPEC.md (US-RB-003)
 */
class InvoiceGeneratorService
{
    /**
     * Gera faturas pendentes pra todas Subscriptions ativas do business.
     *
     * @param  int          $businessId  Multi-tenant Tier 0 obrigatório
     * @param  string|null  $date        YYYY-MM-DD pra simular "hoje" (default: hoje real)
     * @param  bool         $dryRun      true = não escreve nada, só conta
     * @param  int          $leadDays    Antecipação em dias (default 0 = só vencidas hoje ou antes)
     *
     * @return array{generated:int, skipped:int, errors:int, advanced:int}
     */
    public function run(int $businessId, ?string $date = null, bool $dryRun = false, int $leadDays = 0): array
    {
        return OtelHelper::spanBiz('rb.invoice.gerador.run', function () use ($businessId, $date, $dryRun, $leadDays): array {
            return $this->runInternal($businessId, $date, $dryRun, $leadDays);
        }, [
            'module'      => 'RecurringBilling',
            'op'          => 'invoice.gerador.run',
            'business_id' => $businessId,
            'dry_run'     => $dryRun,
            'lead_days'   => $leadDays,
        ]);
    }

    /**
     * @return array{generated:int, skipped:int, errors:int, advanced:int}
     */
    private function runInternal(int $businessId, ?string $date, bool $dryRun, int $leadDays): array
    {
        $today = $date !== null && $date !== '' ? Carbon::parse($date) : Carbon::today();
        $cutoff = $today->copy()->addDays(max(0, $leadDays))->toDateString();

        $candidates = Subscription::query()
            ->where('business_id', $businessId)
            ->where('status', 'active')
            ->whereDate('next_due_date', '<=', $cutoff)
            ->with('plan')
            ->orderBy('id')
            ->get();

        $stats = ['generated' => 0, 'skipped' => 0, 'errors' => 0, 'advanced' => 0];

        foreach ($candidates as $sub) {
            try {
                $stats = $this->processarSubscription($sub, $businessId, $dryRun, $stats);
            } catch (Throwable $e) {
                $stats['errors']++;
                Log::channel('single')->error('rb:generate-invoices falhou pra subscription', [
                    'subscription_id' => $sub->id,
                    'business_id'     => $businessId,
                    'error'           => $e->getMessage(),
                ]);
            }
        }

        return $stats;
    }

    /**
     * @param  array{generated:int, skipped:int, errors:int, advanced:int}  $stats
     *
     * @return array{generated:int, skipped:int, errors:int, advanced:int}
     */
    private function processarSubscription(Subscription $sub, int $businessId, bool $dryRun, array $stats): array
    {
        /** @var Plan|null $plan */
        $plan = $sub->plan;
        if ($plan === null) {
            $stats['errors']++;
            Log::channel('single')->error('rb:generate-invoices subscription sem plan', [
                'subscription_id' => $sub->getKey(),
                'plan_id'         => $sub->getAttribute('plan_id'),
                'business_id'     => $businessId,
            ]);

            return $stats;
        }

        $vencimento = Carbon::parse($sub->getAttribute('next_due_date'));
        $planId      = (int) $plan->getKey();
        $planValor   = (float) $plan->getAttribute('valor');
        $planNome    = (string) $plan->getAttribute('name');
        $planCiclo   = (string) $plan->getAttribute('ciclo');
        $subId       = (int) $sub->getKey();

        // Idempotência — invoice mesma competência (YYYY-MM) já existe?
        $exists = Invoice::query()
            ->where('business_id', $businessId)
            ->where('subscription_id', $subId)
            ->whereYear('vencimento', $vencimento->year)
            ->whereMonth('vencimento', $vencimento->month)
            ->whereNotIn('status', ['canceled'])
            ->exists();

        if ($exists) {
            $stats['skipped']++;

            return $stats;
        }

        if ($dryRun) {
            $stats['generated']++;

            return $stats;
        }

        $proximo = $this->avancarCiclo($vencimento, $planCiclo);
        $numeroDocumento = sprintf('RB-%d-%s', $subId, $vencimento->format('Y-m'));

        DB::transaction(function () use ($sub, $businessId, $subId, $planId, $planValor, $planNome, $planCiclo, $vencimento, $proximo, $numeroDocumento): void {
            Invoice::create([
                'business_id'       => $businessId,
                'subscription_id'   => $subId,
                'contact_id'        => $sub->getAttribute('contact_id'),
                'numero_documento'  => $numeroDocumento,
                'valor'             => $planValor,
                'status'            => 'open',
                'vencimento'        => $vencimento->toDateString(),
                'conta_bancaria_id' => $sub->getAttribute('conta_bancaria_id'),
                'metadata'          => [
                    'generated_by'  => 'rb:generate-invoices',
                    'generated_at'  => now()->toIso8601String(),
                    'plan_id'       => $planId,
                    'plan_name'     => $planNome,
                    'plan_ciclo'    => $planCiclo,
                    'us'            => 'US-RB-003',
                ],
            ]);

            $sub->update(['next_due_date' => $proximo]);

            SubscriptionEvent::create([
                'business_id'     => $businessId,
                'subscription_id' => $subId,
                'kind'            => SubscriptionEvent::KIND_CHARGE,
                'by_actor'        => 'system:rb:generate-invoices',
                'body'            => sprintf(
                    'Fatura %s gerada — vencimento %s — R$ %s. Próximo ciclo: %s.',
                    $numeroDocumento,
                    $vencimento->format('d/m/Y'),
                    number_format($planValor, 2, ',', '.'),
                    Carbon::parse($proximo)->format('d/m/Y'),
                ),
                'occurred_at'     => now(),
            ]);
        });

        $stats['generated']++;
        $stats['advanced']++;

        return $stats;
    }

    /**
     * Avança a data conforme o ciclo do plano. Carbon addMonthsNoOverflow
     * preserva o anchor dia 31 (jan 31 + 1 mês = fev 28, depois fev 28 + 1 = mar 28
     * — anchor "vira" 28, é o comportamento esperado pra cobrança recorrente real).
     *
     * Enum rb_plans.ciclo: monthly|quarterly|semiannual|yearly|custom
     */
    private function avancarCiclo(Carbon $base, string $ciclo): string
    {
        return match ($ciclo) {
            'monthly'    => $base->copy()->addMonthNoOverflow()->toDateString(),
            'quarterly'  => $base->copy()->addMonthsNoOverflow(3)->toDateString(),
            'semiannual' => $base->copy()->addMonthsNoOverflow(6)->toDateString(),
            'yearly'     => $base->copy()->addYearNoOverflow()->toDateString(),
            'custom'     => $base->copy()->addMonthNoOverflow()->toDateString(), // fallback monthly
            default      => $base->copy()->addMonthNoOverflow()->toDateString(),
        };
    }
}
