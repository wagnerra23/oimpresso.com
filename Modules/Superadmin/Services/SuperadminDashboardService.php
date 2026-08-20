<?php

declare(strict_types=1);

namespace Modules\Superadmin\Services;

use App\Business;
use App\Util\OtelHelper;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\RecurringBilling\Repositories\SubscriptionRepository;
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

            // Série CONTÍNUA: os 12 meses nascem com 0.0 ANTES de somar. Sem isso o mês sem
            // nenhuma assinatura simplesmente não vira chave, e o gráfico ganha um buraco —
            // medido no smoke de 2026-08-19, que trouxe 11 pontos (faltava Oct-2025) num
            // eixo que se anuncia como 12 meses. Buraco em série temporal engana o olho:
            // o leitor lê "sem queda" onde havia mês zerado.
            $formatted = [];
            $cursor = $start->copy()->startOfMonth();
            $ultimo = $end->copy()->startOfMonth();

            while ($cursor->lessThanOrEqualTo($ultimo)) {
                $formatted[$cursor->format('M-Y')] = 0.0;
                $cursor->addMonth();
            }

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

    /**
     * MRR — receita recorrente mensal (SA-O1b, fonte corrigida em 2026-08-19).
     *
     * O número vem da COBRANÇA RECORRENTE (`Modules/RecurringBilling`), não do
     * licenciamento legado do UltimatePOS. Medido em prod: `packages`/`subscriptions`
     * têm 75 pacotes ativos TODOS com preço 0 — não cobram ninguém —, enquanto
     * `rb_plans`/`rb_subscriptions` têm 161 planos com valor e 109 assinaturas ativas.
     * A primeira versão deste método lia a fonte errada e devolvia zero.
     *
     * E o CÁLCULO é delegado ao dono (`SubscriptionRepository::mrrBaselineCached`), não
     * refeito aqui. Ele respeita duas regras que uma soma crua de `rb_plans.valor` erra:
     * o `metadata.valor` da assinatura SOBREPÕE o valor do plano (é onde mora o preço
     * negociado por empresa), e o ciclo normaliza pro mês. Medido no mesmo dia: canônico
     * R$ [redacted Tier 0] × soma crua R$ [redacted Tier 0] — ~4% de diferença por UMA assinatura com
     * preço próprio. Um segundo dono do mesmo número seria um segundo número.
     *
     * `canceladas` conta as saídas dos últimos 30 dias (`canceled_at`), que é o insumo do
     * churn — `rb_subscriptions` já traz `churn_reason` de fábrica.
     *
     * @param  int|null  $businessId  business dono da carteira; null usa o do usuário logado
     * @return array{mrr: float, assinaturas: int, canceladas: int, fonte: string}
     */
    public function calcularMrr(?int $businessId = null): array
    {
        return OtelHelper::spanBiz('superadmin.dashboard.mrr', function () use ($businessId): array {
            $biz = $businessId ?? (int) (auth()->user()->business_id ?? 0);

            if ($biz <= 0 || ! class_exists(SubscriptionRepository::class)) {
                return ['mrr' => 0.0, 'assinaturas' => 0, 'canceladas' => 0, 'fonte' => 'indisponivel'];
            }

            // O CÁLCULO é do RecurringBilling, não daqui. `mrrBaselineCached` respeita duas
            // coisas que uma soma crua de `rb_plans.valor` erra:
            //   · `metadata.valor` da assinatura SOBREPÕE o valor do plano (é onde mora o
            //     preço negociado por empresa);
            //   · o ciclo normaliza pro mês (trimestral/3, semestral/6, anual/12).
            // Medido em prod 2026-08-19: canônico R$ [redacted Tier 0] × soma crua R$ [redacted Tier 0] —
            // ~4% de diferença por UMA assinatura com preço próprio. Reimplementar aqui seria
            // um segundo dono do mesmo número, e o segundo dono estava errado.
            $mrr = app(SubscriptionRepository::class)->mrrBaselineCached($biz);

            $ativas = DB::table('rb_subscriptions')
                ->where('business_id', $biz)
                ->where('status', 'active')
                ->whereNull('deleted_at')
                ->count();

            // Churn: canceladas nos últimos 30 dias. `rb_subscriptions` já tem `canceled_at`
            // e `churn_reason` — não precisou de coluna nova.
            $canceladas = DB::table('rb_subscriptions')
                ->where('business_id', $biz)
                ->where('status', 'canceled')
                ->whereNotNull('canceled_at')
                ->where('canceled_at', '>=', Carbon::today()->subDays(30))
                ->count();

            return [
                'mrr' => round((float) $mrr, 2),
                'assinaturas' => $ativas,
                'canceladas' => $canceladas,
                'fonte' => 'recurring_billing',
            ];
        }, ['module' => 'Superadmin', 'service' => self::class]);
    }
}
