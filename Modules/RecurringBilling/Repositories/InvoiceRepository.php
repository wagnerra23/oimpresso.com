<?php

declare(strict_types=1);

namespace Modules\RecurringBilling\Repositories;

use App\Util\OtelHelper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Modules\RecurringBilling\Models\Invoice;

/**
 * Repository — invoices (rb_invoices) — agrupa intent comum.
 *
 * Wave 18 D4 saturação RecurringBilling (69→95). Consumers:
 *   - InvoiceController (listagem + paginação cockpit)
 *   - AssinaturaCobrancaService (cancel + lookup)
 *   - RecurringHealthCommand (KPIs faturas atrasadas)
 *   - Jobs de cobrança recorrente
 *
 * Multi-tenant Tier 0 (ADR 0093): businessId explícito + BusinessScope global.
 * Tests biz=1 (ADR 0101).
 *
 * @see Modules\RecurringBilling\Models\Invoice
 */
class InvoiceRepository
{
    /**
     * Lista paginada de faturas.
     *
     * Filtros aceitos:
     *   status: 'open'|'paid'|'overdue'|'canceled'
     *   subscription_id: int
     *   contact_id: int
     *   vence_de / vence_ate: Y-m-d
     *   gateway: 'asaas'|'inter'|'c6'
     *
     * @param  array<string, mixed>  $filtros
     */
    public function listarPaginado(int $businessId, array $filtros = [], int $perPage = 50): LengthAwarePaginator
    {
        return OtelHelper::spanBiz('rb.invoice.repo.listar', function () use ($businessId, $filtros, $perPage) {
            return $this->aplicarFiltros($this->base($businessId), $filtros)
                ->with(['subscription:id,business_id,plan_id', 'contact:id,business_id,name'])
                ->orderBy('vencimento', 'desc')
                ->orderBy('id', 'desc')
                ->paginate($perPage);
        }, [
            'module'      => 'RecurringBilling',
            'op'          => 'invoice.repo.listar',
            'business_id' => $businessId,
        ]);
    }

    /**
     * Lista paginada de faturas — Onda 7 v9,75 (Page Inertia Faturas).
     *
     * Eager loads: subscription.plan + contact (id,name,tax_number scoped pra evitar full row).
     * Order canônico: overdue → open → paid → canceled → refunded, depois vencimento ASC.
     *
     * Filtros aceitos:
     *   status:  'open'|'paid'|'overdue'|'canceled'|'refunded'|'all'
     *   gateway: 'inter'|'c6'|'asaas'|'all'
     *   periodo: 'mes_atual'|'proximo_mes'|'atrasados'|'all'
     *   busca:   string (LIKE em contact.name OR numero_documento)
     *
     * @param  array<string, mixed>  $filtros
     */
    public function paginatedForIndex(int $businessId, array $filtros = [], int $perPage = 50): LengthAwarePaginator
    {
        return OtelHelper::spanBiz('rb.invoice.repo.paginatedForIndex', function () use ($businessId, $filtros, $perPage) {
            $q = $this->aplicarFiltrosIndex($this->base($businessId), $filtros);

            // SQLite (testes) não suporta FIELD(); fallback: order_by status alpha + vencimento.
            // Em MySQL prod usa-se FIELD() pra prioridade visual canônica.
            $driver = $q->getModel()->getConnection()->getDriverName();
            if ($driver === 'mysql') {
                $q->orderByRaw("FIELD(status, 'overdue', 'open', 'paid', 'canceled', 'refunded')");
            } else {
                // SQLite — ordena por CASE WHEN equivalente
                $q->orderByRaw(
                    "CASE status "
                    ."WHEN 'overdue' THEN 1 "
                    ."WHEN 'open' THEN 2 "
                    ."WHEN 'paid' THEN 3 "
                    ."WHEN 'canceled' THEN 4 "
                    ."WHEN 'refunded' THEN 5 "
                    ."ELSE 9 END"
                );
            }

            return $q->with([
                    'subscription:id,business_id,plan_id',
                    'subscription.plan:id,name,ciclo',
                    'contact:id,business_id,name,tax_number',
                ])
                ->orderBy('vencimento', 'asc')
                ->orderBy('id', 'desc')
                ->paginate($perPage);
        }, [
            'module'      => 'RecurringBilling',
            'op'          => 'invoice.repo.paginatedForIndex',
            'business_id' => $businessId,
        ]);
    }

    /**
     * KPIs do header Faturas Page (Onda 7).
     *
     * Calcula em queries leves + cache-friendly (aggregations apenas em business_id+status+period).
     * Pago este mês: soma valor onde status=paid AND pago_em entre [primeiro_dia_mes, hoje].
     * Pendente: soma valor onde status IN (open).
     * Atrasado: soma valor onde status=overdue OR (status=open AND vencimento < hoje).
     * Count overdue: count(*) onde status=overdue OR (status=open AND vencimento < hoje).
     * Total faturas: count(*) all-time.
     *
     * @return array{
     *   total_pago_mes: float,
     *   total_pendente: float,
     *   total_atrasado: float,
     *   count_overdue: int,
     *   total_faturas: int,
     * }
     */
    public function kpisForIndex(int $businessId): array
    {
        return OtelHelper::spanBiz('rb.invoice.repo.kpisForIndex', function () use ($businessId) {
            $inicioMes = Carbon::now()->startOfMonth()->toDateString();
            $hoje = Carbon::now()->toDateString();

            $pagoMes = (float) $this->base($businessId)
                ->where('status', 'paid')
                ->whereBetween('pago_em', [$inicioMes.' 00:00:00', $hoje.' 23:59:59'])
                ->sum('valor');

            $pendente = (float) $this->base($businessId)
                ->where('status', 'open')
                ->where('vencimento', '>=', $hoje)
                ->sum('valor');

            $atrasadoQuery = function () use ($businessId, $hoje) {
                return $this->base($businessId)->where(function ($q) use ($hoje) {
                    $q->where('status', 'overdue')
                        ->orWhere(function ($sub) use ($hoje) {
                            $sub->where('status', 'open')
                                ->where('vencimento', '<', $hoje);
                        });
                });
            };

            $totalAtrasado = (float) $atrasadoQuery()->sum('valor');
            $countOverdue = (int) $atrasadoQuery()->count();

            $totalFaturas = (int) $this->base($businessId)->count();

            return [
                'total_pago_mes' => $pagoMes,
                'total_pendente' => $pendente,
                'total_atrasado' => $totalAtrasado,
                'count_overdue'  => $countOverdue,
                'total_faturas'  => $totalFaturas,
            ];
        }, [
            'module'      => 'RecurringBilling',
            'op'          => 'invoice.repo.kpisForIndex',
            'business_id' => $businessId,
        ]);
    }

    /**
     * Total bruto + count por status — KPI dashboard.
     *
     * @return array{count: int, total: float}
     */
    public function totaisPorStatus(int $businessId, string $status): array
    {
        $q = $this->base($businessId)->where('status', $status);

        return [
            'count' => (int) (clone $q)->count(),
            'total' => (float) (clone $q)->sum('valor'),
        ];
    }

    /**
     * Atrasadas (overdue OU open vencido) > N dias.
     *
     * @return Collection<int, Invoice>
     */
    public function atrasadasAntigas(int $businessId, int $diasMin = 7): Collection
    {
        $cutoff = now()->subDays($diasMin)->toDateString();

        return $this->base($businessId)
            ->where(function ($q) {
                $q->where('status', 'overdue')
                    ->orWhere(function ($sub) {
                        $sub->where('status', 'open')
                            ->whereDate('vencimento', '<', now()->toDateString());
                    });
            })
            ->whereDate('vencimento', '<', $cutoff)
            ->orderBy('vencimento', 'asc')
            ->get();
    }

    /**
     * Acha por gateway_ref (idempotency dos webhooks de pagamento).
     */
    public function acharPorGatewayRef(int $businessId, string $gateway, string $ref): ?Invoice
    {
        return $this->base($businessId)
            ->where('gateway', $gateway)
            ->where('gateway_ref', $ref)
            ->first();
    }

    /**
     * Acha por (business + id) com guard explícito.
     */
    public function acharPorId(int $businessId, int $id): ?Invoice
    {
        return $this->base($businessId)->whereKey($id)->first();
    }

    private function base(int $businessId): Builder
    {
        return Invoice::query()->where('business_id', $businessId);
    }

    /**
     * Filtros canônicos da Page Inertia Faturas (Onda 7).
     *
     * @param  array<string, mixed>  $filtros
     */
    private function aplicarFiltrosIndex(Builder $q, array $filtros): Builder
    {
        $status = $filtros['status'] ?? 'all';
        if ($status !== 'all' && in_array($status, ['open', 'paid', 'overdue', 'canceled', 'refunded'], true)) {
            $q->where('status', $status);
        }

        $gateway = $filtros['gateway'] ?? 'all';
        if ($gateway !== 'all' && in_array($gateway, ['inter', 'c6', 'asaas'], true)) {
            $q->where('gateway', $gateway);
        }

        $periodo = $filtros['periodo'] ?? 'all';
        if ($periodo === 'mes_atual') {
            $q->whereBetween('vencimento', [
                Carbon::now()->startOfMonth()->toDateString(),
                Carbon::now()->endOfMonth()->toDateString(),
            ]);
        } elseif ($periodo === 'proximo_mes') {
            $proximo = Carbon::now()->addMonthNoOverflow();
            $q->whereBetween('vencimento', [
                $proximo->copy()->startOfMonth()->toDateString(),
                $proximo->copy()->endOfMonth()->toDateString(),
            ]);
        } elseif ($periodo === 'atrasados') {
            $hoje = Carbon::now()->toDateString();
            $q->where(function ($sub) use ($hoje) {
                $sub->where('status', 'overdue')
                    ->orWhere(function ($inner) use ($hoje) {
                        $inner->where('status', 'open')
                            ->where('vencimento', '<', $hoje);
                    });
            });
        }

        $busca = trim((string) ($filtros['busca'] ?? ''));
        if ($busca !== '') {
            $like = '%'.$busca.'%';
            $q->where(function ($sub) use ($like) {
                $sub->where('numero_documento', 'like', $like)
                    ->orWhereHas('contact', function ($c) use ($like) {
                        $c->where('name', 'like', $like)
                            ->orWhere('tax_number', 'like', $like);
                    });
            });
        }

        return $q;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function aplicarFiltros(Builder $q, array $filtros): Builder
    {
        if (! empty($filtros['status'])) {
            $statusValidos = ['open', 'paid', 'overdue', 'canceled'];
            if (in_array($filtros['status'], $statusValidos, true)) {
                $q->where('status', $filtros['status']);
            }
        }

        if (! empty($filtros['subscription_id'])) {
            $q->where('subscription_id', (int) $filtros['subscription_id']);
        }

        if (! empty($filtros['contact_id'])) {
            $q->where('contact_id', (int) $filtros['contact_id']);
        }

        if (! empty($filtros['vence_de'])) {
            $q->whereDate('vencimento', '>=', $filtros['vence_de']);
        }

        if (! empty($filtros['vence_ate'])) {
            $q->whereDate('vencimento', '<=', $filtros['vence_ate']);
        }

        if (! empty($filtros['gateway'])) {
            $q->where('gateway', $filtros['gateway']);
        }

        return $q;
    }
}
