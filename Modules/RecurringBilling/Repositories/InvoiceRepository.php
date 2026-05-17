<?php

declare(strict_types=1);

namespace Modules\RecurringBilling\Repositories;

use App\Util\OtelHelper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
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
