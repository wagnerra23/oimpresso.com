<?php

declare(strict_types=1);

namespace Modules\RecurringBilling\Repositories;

use App\Util\OtelHelper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\RecurringBilling\Models\Subscription;

/**
 * Repository — agrupa intent comum de Subscription (rb_subscriptions).
 *
 * Wave 18 D4 saturação RecurringBilling (69→95) — SoC brutal (Constituição v2 §5).
 * Controllers/Services consumidores: RecurringBillingController, AssinaturaCobrancaService,
 * RecurringHealthCommand, Jobs de billing run.
 *
 * Multi-tenant Tier 0 (ADR 0093): businessId explícito + BusinessScope global.
 * Tests biz=1 (ADR 0101) — NUNCA biz=4 (ROTA LIVRE prod).
 *
 * @see Modules\RecurringBilling\Models\Subscription
 * @see Modules\RecurringBilling\Services\AssinaturaCobrancaService
 */
class SubscriptionRepository
{
    /**
     * Lista paginada de assinaturas com filtros canônicos.
     *
     * Filtros aceitos (opcionais):
     *   status: 'active'|'trialing'|'past_due'|'paused'|'canceled'
     *   contact_id: int
     *   plan_id: int
     *   vence_ate: Y-m-d (próximo vencimento)
     *   busca: numero/nome cliente
     *
     * @param  array<string, mixed>  $filtros
     */
    public function listarPaginado(int $businessId, array $filtros = [], int $perPage = 50): LengthAwarePaginator
    {
        return OtelHelper::spanBiz('rb.subscription.repo.listar', function () use ($businessId, $filtros, $perPage) {
            return $this->aplicarFiltros($this->base($businessId), $filtros)
                ->with(['plan', 'contact:id,business_id,name'])
                ->orderBy('next_due_date', 'asc')
                ->orderBy('id', 'desc')
                ->paginate($perPage);
        }, [
            'module'      => 'RecurringBilling',
            'op'          => 'subscription.repo.listar',
            'business_id' => $businessId,
        ]);
    }

    /**
     * Total ativos (active|trialing|past_due) — KPI dashboard.
     */
    public function contarAtivas(int $businessId): int
    {
        return (int) $this->base($businessId)->ativas()->count();
    }

    /**
     * MRR baseline cached — soma metadata.valor das ativas com ciclo mensal,
     * + trimestral/3, + semestral/6, + anual/12. Cache leve em runtime per request.
     */
    public function mrrBaselineCached(int $businessId): float
    {
        return OtelHelper::spanBiz('rb.subscription.repo.mrr', function () use ($businessId) {
            $assinaturas = $this->base($businessId)
                ->ativas()
                ->with(['plan:id,valor,ciclo'])
                ->get(['id', 'plan_id', 'metadata', 'total_revenue_cached']);

            return (float) $assinaturas->sum(function ($s) {
                $metadata = $s->metadata ?? [];
                $valor = (float) ($metadata['valor'] ?? $s->plan?->valor ?? 0);
                $ciclo = $metadata['ciclo'] ?? $s->plan?->ciclo ?? 'mensal';

                return match ($ciclo) {
                    'mensal'     => $valor,
                    'trimestral' => $valor / 3,
                    'semestral'  => $valor / 6,
                    'anual'      => $valor / 12,
                    default      => $valor,
                };
            });
        }, [
            'module'      => 'RecurringBilling',
            'op'          => 'subscription.repo.mrr',
            'business_id' => $businessId,
        ]);
    }

    /**
     * Vencendo nos próximos N dias — usado pra warm cobrança + dunning soft.
     *
     * @return Collection<int, Subscription>
     */
    public function vencendoNoIntervalo(int $businessId, int $diasFrente = 7): Collection
    {
        $hoje = now()->toDateString();
        $limite = now()->addDays($diasFrente)->toDateString();

        return $this->base($businessId)
            ->ativas()
            ->whereBetween('next_due_date', [$hoje, $limite])
            ->orderBy('next_due_date', 'asc')
            ->get();
    }

    /**
     * Acha por business + id — guard explícito (substitui ::find() ad-hoc).
     */
    public function acharPorId(int $businessId, int $id): ?Subscription
    {
        return $this->base($businessId)->whereKey($id)->first();
    }

    /**
     * Builder base — escopo explícito por business_id (defesa em profundidade).
     */
    private function base(int $businessId): Builder
    {
        return Subscription::query()->where('business_id', $businessId);
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function aplicarFiltros(Builder $q, array $filtros): Builder
    {
        if (! empty($filtros['status'])) {
            $statusValidos = ['active', 'trialing', 'past_due', 'paused', 'canceled'];
            if (in_array($filtros['status'], $statusValidos, true)) {
                $q->where('status', $filtros['status']);
            }
        }

        if (! empty($filtros['contact_id'])) {
            $q->where('contact_id', (int) $filtros['contact_id']);
        }

        if (! empty($filtros['plan_id'])) {
            $q->where('plan_id', (int) $filtros['plan_id']);
        }

        if (! empty($filtros['vence_ate'])) {
            $q->whereDate('next_due_date', '<=', $filtros['vence_ate']);
        }

        if (! empty($filtros['busca'])) {
            $busca = '%' . trim((string) $filtros['busca']) . '%';
            $q->whereHas('contact', function ($sub) use ($busca) {
                $sub->where('name', 'like', $busca)
                    ->orWhere('tax_number', 'like', $busca);
            });
        }

        return $q;
    }
}
