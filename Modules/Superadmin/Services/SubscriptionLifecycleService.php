<?php

declare(strict_types=1);

namespace Modules\Superadmin\Services;

use App\Util\OtelHelper;
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
 * Wave 25 SATURATION — D9 boost: spans OTel canônicos por transição.
 * Zero-cost se `otel.enabled=false`. Em CT 100 com OTel collector ativo,
 * exporta tracing pra dashboard SRE — slice por `lifecycle.action` permite
 * spotting de regressão de approve/expire/cancel.
 *
 * Cross-tenant intencional (Superadmin Wagner-only).
 *
 * Spatie LogsActivity em Subscription model já registra os deltas — Service
 * apenas orquestra UPDATEs com escrita coerente (DB::transaction).
 *
 * @see Modules\Superadmin\Entities\Subscription
 * @see app\Util\OtelHelper
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md (cross-tenant Superadmin)
 * @see memory/decisions/0155-module-grade-v3-anti-injustica-na-justified.md D9.a
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
        return OtelHelper::spanBiz('superadmin.subscription.approve', function () use ($subscription, $startDate): bool {
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
        }, [
            'module' => 'Superadmin',
            'service' => self::class,
            'subscription_id' => $subscription->id ?? 0,
            'target_biz' => $subscription->business_id ?? 0,
            'lifecycle.action' => 'approve',
        ]);
    }

    /**
     * Marca subscription como expirada (cron diário ou manual).
     */
    public function expire(Subscription $subscription): bool
    {
        return OtelHelper::spanBiz('superadmin.subscription.expire', function () use ($subscription): bool {
            if ($subscription->status === 'expired') {
                return false;  // idempotente
            }

            if ($subscription->end_date && $subscription->end_date->isFuture()) {
                return false;  // ainda válida
            }

            $subscription->status = 'expired';
            $subscription->save();

            return true;
        }, [
            'module' => 'Superadmin',
            'service' => self::class,
            'subscription_id' => $subscription->id ?? 0,
            'target_biz' => $subscription->business_id ?? 0,
            'lifecycle.action' => 'expire',
        ]);
    }

    /**
     * Cancela subscription (admin force; mantém audit + soft-delete).
     *
     * @param  string  $reason  motivo (vai pra log via Spatie LogsActivity properties).
     */
    public function cancel(Subscription $subscription, string $reason = ''): bool
    {
        return OtelHelper::spanBiz('superadmin.subscription.cancel', function () use ($subscription, $reason): bool {
            if (in_array($subscription->status, ['cancelled', 'expired'], true)) {
                return false;
            }

            return DB::transaction(function () use ($subscription, $reason) {
                // Properties extra Spatie via activity()->withProperties() — fora do escopo Service.
                $subscription->status = 'cancelled';
                $subscription->save();

                return true;
            });
        }, [
            'module' => 'Superadmin',
            'service' => self::class,
            'subscription_id' => $subscription->id ?? 0,
            'target_biz' => $subscription->business_id ?? 0,
            'lifecycle.action' => 'cancel',
            'reason_len' => strlen($reason),
        ]);
    }

    /**
     * Subscriptions com end_date no passado e status ainda approved (cron sweep).
     */
    public function findOverdueApproved(): \Illuminate\Database\Eloquent\Collection
    {
        return OtelHelper::spanBiz('superadmin.subscription.find_overdue', function (): \Illuminate\Database\Eloquent\Collection {
            // SUPERADMIN: cross-tenant intencional (cron sweep global).
            return Subscription::query()
                ->where('status', 'approved')
                ->whereDate('end_date', '<', now())
                ->get();
        }, ['module' => 'Superadmin', 'service' => self::class, 'lifecycle.action' => 'find_overdue']);
    }
}
