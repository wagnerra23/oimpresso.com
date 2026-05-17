<?php

declare(strict_types=1);

namespace Modules\RecurringBilling\Http\Presenters;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Modules\RecurringBilling\Models\ChargeAttempt;
use Modules\RecurringBilling\Models\Subscription;

/**
 * Presenter — Subscription → DTO consumido por Pages/RecurringBilling/Index.tsx.
 *
 * Tradução do schema canônico (`rb_subscriptions.status` enum
 * `trialing|active|paused|canceled|past_due`) pro vocabulário visual do
 * prototipo Cowork (`em_dia|retentando|falhou|pausada|cancelada`).
 *
 * Mapeamento canon (Index.charter.md §Goals):
 *   em_dia      ← status IN (active, trialing) + zero invoice overdue
 *   retentando  ← status=past_due + max(charge_attempts.attempt_n) < 3
 *   falhou      ← status=past_due + max(charge_attempts.attempt_n) >= 3
 *   pausada     ← status=paused
 *   cancelada   ← status=canceled
 *
 * Stateless puro (zero IO interno) — consume Subscription com relations
 * eager (`plan`, `contact`, `lastInvoice`, `pinnedNote`) carregadas pelo
 * Repository pra evitar N+1.
 *
 * @see Modules\RecurringBilling\Repositories\SubscriptionRepository::paginatedForIndex
 * @see Pages/RecurringBilling/Index.tsx
 */
class SubscriptionIndexPresenter
{
    /** @return array<string, mixed> */
    public static function toListRow(Subscription $sub): array
    {
        $status = self::deriveVisualStatus($sub);
        $methodMap = ['boleto' => 'boleto', 'pix' => 'pix', 'card' => 'card'];
        $method = $methodMap[$sub->payment_method ?? ''] ?? 'boleto';

        return [
            'id'         => $sub->id,
            'client'     => $sub->contact?->name ?? '—',
            'cnpj'       => $sub->contact?->tax_number ?? null,
            'plan_id'    => $sub->plan_id,
            'plan_name'  => $sub->plan?->name ?? '—',
            'plan_cycle' => self::translateCycle($sub->plan?->ciclo ?? 'monthly'),
            'since'      => optional($sub->start_date)->toDateString(),
            'method'     => $method,
            'status'     => $status,
            'retry'      => in_array($status, ['retentando', 'falhou'], true) ? self::lastAttemptCount($sub) : null,
            'retry_max'  => 3,
            'next_at'    => optional($sub->next_due_date)->toDateString(),
            'next_value' => self::nextValueCents($sub),
            'os'         => $sub->last_jobsheet_id ? '#'.$sub->last_jobsheet_id : null,
            'is_pinned'  => $sub->pinnedNote !== null,
            'paid'       => (int) ($sub->total_paid_cached ?? 0),
            'missed'     => (int) ($sub->failed_count_cached ?? 0),
            'ltv'        => (float) ($sub->total_revenue_cached ?? 0),
        ];
    }

    /** @return array<string, mixed> */
    public static function toDrawerPayload(Subscription $sub): array
    {
        $row = self::toListRow($sub);

        $row['contact'] = [
            'name'  => $sub->contact?->name ?? '—',
            'phone' => $sub->contact_phone_cached ?? $sub->contact?->mobile ?? $sub->contact?->landline ?? '—',
            'email' => $sub->contact?->email ?? null,
        ];

        $row['note'] = $sub->pinnedNote ? [
            'body' => $sub->pinnedNote->body,
            'by'   => 'sistema',
            'at'   => $sub->pinnedNote->updated_at?->toIso8601String(),
        ] : null;

        $row['fiscal'] = [
            'type'     => $sub->plan?->fiscal_type ?? 'none',
            'channels' => [],
            'last_nf'  => null,
        ];

        $row['churn_reason'] = $sub->churn_reason;
        $row['paused_until'] = optional($sub->paused_until)->toDateString();
        $row['canceled_at']  = optional($sub->canceled_at)->toIso8601String();

        return $row;
    }

    /**
     * @param  Collection<int, Subscription>  $subs
     * @return array<string, mixed>
     */
    public static function computeKpis(Collection $subs): array
    {
        $now = Carbon::now();
        $start = $now->copy()->startOfMonth();

        $active = $subs->filter(fn ($s) => in_array($s->status, ['active', 'trialing', 'past_due'], true));
        $mrr = $active->sum(fn ($s) => self::monthlyEquivalent($s));

        $churnMonth = $subs->filter(fn ($s) => $s->status === 'canceled' && $s->canceled_at?->gte($start))->count();
        $totalEverActive = $subs->whereNotIn('status', ['trialing'])->count();
        $churnRate = $totalEverActive > 0 ? round(($churnMonth / $totalEverActive) * 100, 1) : 0.0;

        $tomorrow = $now->copy()->addDay()->toDateString();
        $nextCharges = $active->filter(fn ($s) => optional($s->next_due_date)->toDateString() === $tomorrow);

        $failed = $subs->filter(fn ($s) => self::deriveVisualStatus($s) === 'falhou')->count();
        $retrying = $subs->filter(fn ($s) => self::deriveVisualStatus($s) === 'retentando')->count();

        return [
            'mrr'               => round($mrr, 2),
            'mrr_delta'         => 0.0,
            'churn_count'       => $churnMonth,
            'churn_rate'        => $churnRate,
            'next_charge_when'  => $nextCharges->isNotEmpty() ? 'amanhã' : 'sem cobrança próxima',
            'next_charge_value' => round($nextCharges->sum(fn ($s) => self::nextValueCents($s)), 2),
            'next_charge_count' => $nextCharges->count(),
            'failed_count'      => $failed,
            'retrying_count'    => $retrying,
            'active_count'      => $active->count(),
            'paused_count'      => $subs->where('status', 'paused')->count(),
            'total_ltv'         => round($subs->sum('total_revenue_cached'), 2),
        ];
    }

    public static function deriveVisualStatus(Subscription $sub): string
    {
        if ($sub->status === 'canceled') {
            return 'cancelada';
        }
        if ($sub->status === 'paused') {
            return 'pausada';
        }
        if ($sub->status === 'past_due') {
            return self::lastAttemptCount($sub) >= 3 ? 'falhou' : 'retentando';
        }

        return 'em_dia';
    }

    private static function lastAttemptCount(Subscription $sub): int
    {
        if (! $sub->lastInvoice) {
            return 0;
        }

        return (int) (ChargeAttempt::where('invoice_id', $sub->lastInvoice->id)->max('attempt_n') ?? 0);
    }

    private static function nextValueCents(Subscription $sub): float
    {
        $meta = $sub->metadata ?? [];

        return (float) ($meta['valor'] ?? $sub->plan?->valor ?? 0);
    }

    private static function monthlyEquivalent(Subscription $sub): float
    {
        $value = self::nextValueCents($sub);
        $cycle = $sub->plan?->ciclo ?? 'monthly';

        return match ($cycle) {
            'monthly'    => $value,
            'quarterly'  => $value / 3,
            'semiannual' => $value / 6,
            'yearly'     => $value / 12,
            default      => $value,
        };
    }

    private static function translateCycle(string $cycle): string
    {
        return match ($cycle) {
            'monthly'    => 'mensal',
            'quarterly'  => 'trimestral',
            'semiannual' => 'semestral',
            'yearly'     => 'anual',
            'custom'     => 'customizado',
            default      => $cycle,
        };
    }
}
