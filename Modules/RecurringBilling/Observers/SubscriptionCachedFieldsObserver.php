<?php

declare(strict_types=1);

namespace Modules\RecurringBilling\Observers;

use Modules\RecurringBilling\Models\Invoice;
use Modules\RecurringBilling\Models\Subscription;

/**
 * Observer — recalcula campos cached (denormalizados) em rb_subscriptions
 * quando Invoice muda status. Onda 2 do plano v9,75
 * (memory/requisitos/RecurringBilling/Index-visual-comparison.md).
 *
 * Mantém em sincronia:
 *   - total_paid_cached    ← count(invoices where status=paid)
 *   - failed_count_cached  ← count(invoices where status=overdue) — heurística
 *   - total_revenue_cached ← sum(invoices.valor where status=paid)
 *   - contact_phone_cached ← contact.mobile (atualizado em created/saved)
 *
 * Pattern: registrado em RecurringBillingServiceProvider::boot() via
 * Invoice::observe() + Subscription::saving() pra denormalizar contact_phone.
 *
 * Multi-tenant Tier 0: Subscription tem HasBusinessScope automático,
 * Invoice tem HasBusinessScope automático — recalc é per-subscription
 * por business_id implicitamente.
 *
 * Backfill bulk: comando `rb:backfill-cached-fields` recalcula histórico.
 *
 * @see Modules\RecurringBilling\Console\Commands\BackfillCachedFieldsCommand
 */
class SubscriptionCachedFieldsObserver
{
    /**
     * Quando Invoice é criada/atualizada/deletada, recalcular Subscription pai.
     */
    public function invoiceSaved(Invoice $invoice): void
    {
        if (! $invoice->subscription_id) {
            return; // fatura avulsa — sem subscription pra recalc
        }

        $this->recomputeForSubscription($invoice->subscription_id);
    }

    public function invoiceDeleted(Invoice $invoice): void
    {
        if (! $invoice->subscription_id) {
            return;
        }

        $this->recomputeForSubscription($invoice->subscription_id);
    }

    /**
     * Quando Subscription é salva (created/updated), denormaliza contact_phone.
     */
    public function subscriptionSaving(Subscription $sub): void
    {
        if ($sub->isDirty('contact_id') || ! $sub->contact_phone_cached) {
            $contact = $sub->contact()->first();
            if ($contact) {
                $sub->contact_phone_cached = $contact->mobile ?? $contact->landline ?? null;
            }
        }
    }

    /**
     * Recompute counters cached pra uma subscription específica.
     */
    public function recomputeForSubscription(int $subscriptionId): void
    {
        /** @var Subscription|null $sub */
        $sub = Subscription::query()->whereKey($subscriptionId)->first();
        if (! $sub) {
            return;
        }

        $invoices = Invoice::query()
            ->where('subscription_id', $subscriptionId)
            ->get(['id', 'status', 'valor']);

        $paid = $invoices->where('status', 'paid');
        $overdue = $invoices->where('status', 'overdue');

        $sub->total_paid_cached = $paid->count();
        $sub->failed_count_cached = $overdue->count();
        $sub->total_revenue_cached = (float) $paid->sum('valor');

        // Skip observer recursion: use saveQuietly se trigger vier de invoice
        // (evita loop quando Subscription save chama subscriptionSaving).
        $sub->saveQuietly();
    }
}
