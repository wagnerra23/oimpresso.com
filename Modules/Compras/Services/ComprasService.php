<?php

namespace Modules\Compras\Services;

use App\Transaction;
use App\Utils\TransactionUtil;
use Carbon\Carbon;

/**
 * ComprasService — wrapper canônico do módulo Compras.
 *
 * Wave 3 MVP — wrapper fino sobre `TransactionUtil` legacy + agregadores
 * KPI próprios. Refactor pra service nativo é roadmap (não bloqueia MVP).
 *
 * Tier 0 ADR 0093 — todo método recebe `$business_id` explícito. Caller é
 * responsável por passar o `session('user.business_id')` corretamente.
 *
 * @see memory/requisitos/Compras/SPEC.md US-COM-001 R-COM-003
 * @see app/Utils/TransactionUtil.php::getListPurchases
 */
class ComprasService
{
    public function __construct(protected TransactionUtil $transactionUtil) {}

    /**
     * Lista paginada de compras com filtros + joins canônicos.
     *
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder
     */
    public function listarCompras(int $businessId, array $filters = [])
    {
        $query = $this->transactionUtil->getListPurchases($businessId);

        if (! empty($filters['q'])) {
            $q = $filters['q'];
            $query->where(function ($w) use ($q) {
                $w->where('transactions.ref_no', 'like', "%{$q}%")
                    ->orWhere('contacts.name', 'like', "%{$q}%")
                    ->orWhere('contacts.supplier_business_name', 'like', "%{$q}%");
            });
        }

        if (! empty($filters['stage']) && $filters['stage'] !== 'all') {
            $query->where('transactions.status', $filters['stage']);
        }

        return $query->orderBy('transactions.transaction_date', 'desc');
    }

    /**
     * 4 KPIs do cockpit Compras — agregados scopados por business.
     *
     * - aberto: compras com payment_status != 'paid'
     * - transito: compras com status in ('ordered','pending') — pedido pendente recebimento
     * - mes: soma final_total de compras transaction_date dentro do mês corrente
     * - fornec: contagem distinct contact_id usado em compras
     *
     * Tier 0 ADR 0093 — todos COUNT/SUM filtrados por business_id.
     */
    public function calcularKpis(int $businessId): array
    {
        $base = Transaction::where('business_id', $businessId)
            ->where('type', 'purchase');

        $aberto = (clone $base)
            ->whereIn('payment_status', ['due', 'partial'])
            ->count();

        $transito = (clone $base)
            ->whereIn('status', ['ordered', 'pending'])
            ->count();

        $inicioMes = Carbon::now()->startOfMonth();
        $fimMes = Carbon::now()->endOfMonth();
        $mes = (clone $base)
            ->whereBetween('transaction_date', [$inicioMes, $fimMes])
            ->sum('final_total');

        $fornec = (clone $base)
            ->whereNotNull('contact_id')
            ->distinct('contact_id')
            ->count('contact_id');

        return [
            'aberto' => (int) $aberto,
            'transito' => (int) $transito,
            'mes' => (float) $mes,
            'fornec' => (int) $fornec,
        ];
    }
}
