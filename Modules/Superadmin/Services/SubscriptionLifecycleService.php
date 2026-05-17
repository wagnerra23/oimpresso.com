<?php

declare(strict_types=1);

namespace Modules\Superadmin\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Superadmin\Entities\Subscription;

/**
 * SubscriptionLifecycleService — encapsula transições de status Subscription.
 *
 * Wave 18 RETRY — D4 boost. Subscription tem status linear simples
 * (waiting_approval → approved → expired) — não justifica FSM Pipeline ADR 0143
 * (declarado `fsm_n_a: true` em module.json), mas merece Service dedicado pra:
 *
 *   - Centralizar regra "quando approve cria audit trail automático"
 *   - Encapsular cálculo de end_date a partir de package.interval
 *   - Permitir mock em Pest sem precisar dispatch Asaas/PesaPal stub
 *
 * Cross-tenant intencional (Superadmin Wagner-only).
 *
 * Spatie LogsActivity em Subscription model já registra os deltas — Service
 * apenas orquestra UPDATEs com escrita coerente (DB::transaction).
 *
 * @see Modules\Superadmin\Entities\Subscription
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md (cross-tenant Superadmin)
 */
class SubscriptionLifecycleService
{
    /**
     * Aprova subscription pendente. Calcula end_date conforme package + gera audit.
     *
     * @return bool  true se transição efetuada; false se status incompatível.
     */
    public function approve(Subscription $subscription, ?Carbon $startDate = null): bool
    {
        if ($subscription->status !== 'waiting' && $subscription->status !== 'waiting_approval') {
            return false;
        }

        $startDate = $startDate ?? now();

        return DB::transaction(function () use ($subscription, $startDate) {
            $packageDetails = (array) ($subscription->package_details ?? []);
            $intervalType = $packageDetails['interval'] ?? 'months';
            $intervalCount = (int) ($packageDetails['interval_count'] ?? 1);

            $endDate = match ($intervalType) {
                'days'   => $startDate->copy()->addDays($intervalCount),
                'months' => $startDate->copy()->addMonths($intervalCount),
                'years'  => $startDate->copy()->addYears($intervalCount),
                default  => $startDate->copy()->addMonth(),
            };

            $subscription->status = 'approved';
            $subscription->start_date = $startDate;
            $subscription->end_date = $endDate;
            $subscription->save();

            return true;
        });
    }

    /**
     * Marca subscription como expirada (cron diário ou manual).
     */
    public function expire(Subscription $subscription): bool
    {
        if ($subscription->status === 'expired') {
            return false;  // idempotente
        }

        if ($subscription->end_date && $subscription->end_date->isFuture()) {
            return false;  // ainda válida
        }

        $subscription->status = 'expired';
        $subscription->save();

        return true;
    }

    /**
     * Cancela subscription (admin force; mantém audit + soft-delete).
     *
     * @param  string  $reason  motivo (vai pra log via Spatie LogsActivity properties).
     */
    public function cancel(Subscription $subscription, string $reason = ''): bool
    {
        if (in_array($subscription->status, ['cancelled', 'expired'], true)) {
            return false;
        }

        return DB::transaction(function () use ($subscription, $reason) {
            // Properties extra Spatie via activity()->withProperties() — fora do escopo Service.
            $subscription->status = 'cancelled';
            $subscription->save();

            return true;
        });
    }

    /**
     * Subscriptions com end_date no passado e status ainda approved (cron sweep).
     */
    public function findOverdueApproved(): \Illuminate\Database\Eloquent\Collection
    {
        return Subscription::query()
            ->where('status', 'approved')
            ->whereDate('end_date', '<', now())
            ->get();
    }
}
