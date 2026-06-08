<?php

declare(strict_types=1);

namespace Modules\Superadmin\Services;

use App\Business;
use App\Util\OtelHelper;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Superadmin\Entities\Subscription;

/**
 * SuperadminDashboardService — Wave 23 D4 + D9 SATURATION.
 *
 * Encapsula leitura dos KPIs da home `/superadmin` antes embutidos em
 * `SuperadminController::index()` + `SuperadminController::stats()`:
 *   - Not subscribed businesses count
 *   - Monthly subscription revenue (12m rolling)
 *   - Stats por período (subscriptions revenue + new registrations)
 *
 * Motivação D4:
 *   - Controller `SuperadminController` agregava lógica de query + view binding
 *     (DDD: regra de negócio "monthly_sell_data" misturada com presentation)
 *   - Service injetável habilita mock em Pest sem precisar fingir Subscription/Business
 *   - Separa lifecycle (data fetch) de presentation (chart binding)
 *
 * **Cross-tenant intencional** (ADR 0093 §exceções Superadmin):
 *   - Queries são GLOBAIS por design (Wagner enxerga todos businesses)
 *   - NÃO usar global scope multi-tenant
 *
 * D9 Obs: spans por método agregado pra dashboard SRE.
 *
 * @see Modules\Superadmin\Http\Controllers\SuperadminController
 * @see Modules\Superadmin\Services\PackageManagerService (sibling D4)
 * @see Modules\Superadmin\Services\SubscriptionLifecycleService (sibling D4)
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */
class SuperadminDashboardService
{
    /**
     * Conta businesses SEM subscription (target conversão).
     *
     * Cross-tenant intencional.
     */
    public function countNotSubscribedBusinesses(): int
    {
        return OtelHelper::spanBiz('superadmin.dashboard.not_subscribed', function (): int {
            // SUPERADMIN: agregação cross-tenant intencional (catálogo SaaS).
            return (int) Business::leftJoin('subscriptions AS s', 'business.id', '=', 's.business_id')
                ->whereNull('s.id')
                ->count();
        }, ['module' => 'Superadmin', 'service' => self::class]);
    }

    /**
     * Calcula receita mensal de subscriptions (12 meses rolling).
     *
     * @return array<string, float>  Mapa "Mon-YYYY" => sum(package_price)
     */
    public function buildMonthlyRevenueChart(): array
    {
        return OtelHelper::spanBiz('superadmin.dashboard.monthly_revenue', function (): array {
            $start = Carbon::today()->subYear();
            $end = Carbon::today();

            // SUPERADMIN: subscription é entity GLOBAL (ADR 0093 §exceções).
            $subscriptions = Subscription::whereRaw('DATE(created_at) BETWEEN ? AND ?', [$start, $end])
                ->select('package_price', 'created_at')
                ->orderBy('created_at')
                ->get();

            $formatted = [];
            foreach ($subscriptions as $sub) {
                $monthYear = Carbon::parse($sub->created_at)->format('M-Y');
                if (! isset($formatted[$monthYear])) {
                    $formatted[$monthYear] = 0.0;
                }
                $formatted[$monthYear] += (float) $sub->package_price;
            }

            return $formatted;
        }, ['module' => 'Superadmin', 'service' => self::class]);
    }

    /**
     * Stats por período (revenue subscriptions + new business registrations).
     *
     * @return array{new_subscriptions: float, new_registrations: int}
     */
    public function statsForPeriod(string $startDate, string $endDate): array
    {
        return OtelHelper::spanBiz('superadmin.dashboard.stats_period', function () use ($startDate, $endDate): array {
            // SUPERADMIN: cross-tenant intencional pra dashboard global.
            $revenue = (float) (Subscription::whereRaw('DATE(created_at) BETWEEN ? AND ?', [$startDate, $endDate])
                ->where('status', 'approved')
                ->select(DB::raw('SUM(package_price) as total'))
                ->first()->total ?? 0);

            $registrations = (int) (Business::whereRaw('DATE(created_at) BETWEEN ? AND ?', [$startDate, $endDate])
                ->select(DB::raw('COUNT(id) as total'))
                ->first()->total ?? 0);

            return [
                'new_subscriptions' => $revenue,
                'new_registrations' => $registrations,
            ];
        }, ['module' => 'Superadmin', 'service' => self::class]);
    }

    /**
     * Contagem agregada de businesses por status (active/inactive/sub_status).
     *
     * Útil pra Inertia::defer no Index do dashboard.
     *
     * @return array{active: int, inactive: int, total: int}
     */
    public function countBusinessesByStatus(): array
    {
        return OtelHelper::spanBiz('superadmin.dashboard.biz_by_status', function (): array {
            // SUPERADMIN: leitura cross-tenant intencional.
            $active = (int) Business::where('is_active', 1)->count();
            $inactive = (int) Business::where('is_active', 0)->count();

            return [
                'active'   => $active,
                'inactive' => $inactive,
                'total'    => $active + $inactive,
            ];
        }, ['module' => 'Superadmin', 'service' => self::class]);
    }
}
