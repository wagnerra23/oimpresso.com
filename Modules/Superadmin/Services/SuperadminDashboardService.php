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
     * MRR — receita recorrente mensal (SA-O1b).
     *
     * Regra R1 do F1: **só pacote recorrente com preço > 0**. Gratuito e avulso
     * (`packages.is_one_time`) entram no caixa do mês, nunca na recorrência.
     *
     * Três decisões que o número esconde, e que mudam o resultado:
     *
     * 1. **Só assinatura VIGENTE.** `status = approved` E (`end_date` no futuro OU nula).
     *    Sem o recorte de vigência o número infla: medido em prod 2026-08-19 há 126
     *    assinaturas `approved` e apenas 13 vigentes — as outras 113 venceram e nunca
     *    foram expiradas (o sweep de `findOverdueApproved()` não tem invocador). Somar
     *    todas daria um MRR ~10× maior que a realidade.
     *
     * 2. **O valor vem de `subscriptions.package_price`** (congelado na contratação), não
     *    de `packages.price`. O cliente paga o que contratou; mudar o preço do pacote não
     *    reescreve o passado.
     *
     * 3. **O intervalo vem de `packages`** (atual), e isso é uma limitação assumida:
     *    `subscriptions.package_details` congela só `location_count`, `user_count`,
     *    `product_count`, `invoice_count` e `name` (medido em prod) — não guarda `interval`
     *    nem `is_one_time`. Se um pacote virar de mensal para anual, as assinaturas antigas
     *    passam a ser normalizadas pelo intervalo novo. Congelar isso exige migration e é
     *    decisão [W].
     *
     * Normalização para o mês: `years` divide por 12×N, `months` por N, `days` multiplica
     * por 30/N. `interval_count` 0 ou negativo é ignorado em vez de dividir por zero.
     *
     * @return array{mrr: float, assinaturas: int, sem_preco: int}
     */
    public function calcularMrr(): array
    {
        return OtelHelper::spanBiz('superadmin.dashboard.mrr', function (): array {
            // SUPERADMIN: leitura GLOBAL cross-tenant intencional (ADR 0093 §exceções).
            $vigentes = DB::table('subscriptions')
                ->join('packages', 'packages.id', '=', 'subscriptions.package_id')
                ->where('subscriptions.status', 'approved')
                ->where(function ($q) {
                    $q->whereNull('subscriptions.end_date')
                        ->orWhereDate('subscriptions.end_date', '>=', Carbon::today());
                })
                ->get([
                    'subscriptions.package_price',
                    'packages.interval',
                    'packages.interval_count',
                    'packages.is_one_time',
                ]);

            $mrr = 0.0;
            $contadas = 0;
            $semPreco = 0;

            foreach ($vigentes as $linha) {
                $preco = (float) $linha->package_price;

                if ((int) $linha->is_one_time === 1) {
                    continue; // avulso não é recorrência (R1)
                }

                if ($preco <= 0) {
                    $semPreco++;

                    continue; // gratuito não entra no MRR (R1)
                }

                $n = (int) $linha->interval_count;

                if ($n <= 0) {
                    continue; // intervalo inválido: fora da conta, nunca divisão por zero
                }

                $mrr += match ($linha->interval) {
                    'years' => $preco / (12 * $n),
                    'months' => $preco / $n,
                    'days' => $preco * (30 / $n),
                    default => 0.0,
                };

                $contadas++;
            }

            return [
                // 2 casas: o consumidor é KPI de tela, e float longo vira dízima na UI.
                'mrr' => round($mrr, 2),
                'assinaturas' => $contadas,
                // Quantas vigentes ficaram FORA por não ter preço — é o que explica um MRR
                // zero sem a tela parecer quebrada (em prod 2026-08-19: nenhum pacote tem
                // preço cadastrado, então todas caem aqui).
                'sem_preco' => $semPreco,
            ];
        }, ['module' => 'Superadmin', 'service' => self::class]);
    }
}
