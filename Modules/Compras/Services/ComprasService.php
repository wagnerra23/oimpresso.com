<?php

namespace Modules\Compras\Services;

use App\Transaction;
use App\Utils\TransactionUtil;
use Carbon\Carbon;
use Spatie\Activitylog\Models\Activity;

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
     * Mapping coluna virtual frontend → coluna SQL real (segurança anti SQL injection).
     *
     * SOMENTE colunas dessa lista podem entrar em orderBy. Caller passa nome
     * de coluna virtual; service decide qual coluna SQL real usar.
     */
    private const SORT_MAP = [
        'transaction_date' => 'transactions.transaction_date',
        'ref_no' => 'transactions.ref_no',
        'contact_name' => 'contacts.supplier_business_name',
        'location_name' => 'BS.name',
        'status' => 'transactions.status',
        'payment_status' => 'transactions.payment_status',
        'final_total' => 'transactions.final_total',
    ];

    /**
     * Lista paginada de compras com filtros + sort dinâmico + joins canônicos.
     *
     * @param  array{q?:string, stage?:string, sort?:string, dir?:string}  $filters
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

        $sortCol = $filters['sort'] ?? 'transaction_date';
        $sortDir = strtolower($filters['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $sqlCol = self::SORT_MAP[$sortCol] ?? 'transactions.transaction_date';

        return $query->orderBy($sqlCol, $sortDir);
    }

    /**
     * Sumário agregado pra footer da tabela — total / pago / a pagar / reembolsado.
     *
     * Tier 0 ADR 0093 — business_id scope explícito.
     */
    public function calcularSummary(int $businessId, array $filters = []): array
    {
        $base = Transaction::where('business_id', $businessId)
            ->where('type', 'purchase');

        // Aplica mesmos filtros que listarCompras (q + stage) pra coerência
        if (! empty($filters['q'])) {
            $q = $filters['q'];
            $base->where(function ($w) use ($q) {
                $w->where('ref_no', 'like', "%{$q}%");
            });
        }
        if (! empty($filters['stage']) && $filters['stage'] !== 'all') {
            $base->where('status', $filters['stage']);
        }

        $total = (clone $base)->sum('final_total');

        $totalPago = (float) (clone $base)
            ->leftJoin('transaction_payments AS TP', 'transactions.id', '=', 'TP.transaction_id')
            ->where(function ($w) {
                $w->whereNull('TP.is_return')->orWhere('TP.is_return', 0);
            })
            ->sum('TP.amount');

        $totalReembolso = (float) (clone $base)
            ->leftJoin('transaction_payments AS TP', 'transactions.id', '=', 'TP.transaction_id')
            ->where('TP.is_return', 1)
            ->sum('TP.amount');

        $aPagar = max(0, ((float) $total) - $totalPago);

        return [
            'total' => (float) $total,
            'pago' => $totalPago,
            'a_pagar' => $aPagar,
            'reembolso' => $totalReembolso,
        ];
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

    /**
     * Detalhe completo de UMA compra pra DrawerView 5 tabs (Resumo/Itens/Docs/Pagamentos/Histórico).
     *
     * Tier 0 ADR 0093 — `where('business_id', $businessId)` ANTES de find pra evitar leak.
     *
     * Retorna null se não achar (caller responde 404).
     */
    public function buscarDetalhe(int $id, int $businessId): ?array
    {
        $compra = Transaction::where('business_id', $businessId)
            ->where('id', $id)
            ->whereIn('type', ['purchase', 'purchase_order', 'purchase_return'])
            ->with([
                'contact',
                'location',
                'purchase_lines',
                'purchase_lines.product',
                'purchase_lines.product.unit',
                'purchase_lines.variations',
                'purchase_lines.variations.product_variation',
                'payment_lines',
            ])
            ->first();

        if (! $compra) {
            return null;
        }

        $lines = collect($compra->purchase_lines ?? [])->map(function ($line) {
            $variationName = null;
            if ($line->variations) {
                $sub = $line->variations->product_variation->name ?? null;
                $var = $line->variations->name ?? null;
                $variationName = trim(($sub ?? '').' '.($var ?? '')) ?: null;
            }

            return [
                'id' => $line->id,
                'product_name' => $line->product->name ?? '—',
                'product_sku' => $line->product->sku ?? null,
                'variation_name' => $variationName,
                'quantity' => (float) ($line->quantity ?? 0),
                'unit_name' => $line->product->unit->short_name ?? $line->product->unit->actual_name ?? null,
                'purchase_price' => (float) ($line->purchase_price ?? 0),
                'purchase_price_inc_tax' => (float) ($line->purchase_price_inc_tax ?? 0),
                'item_tax' => (float) ($line->item_tax ?? 0),
                'line_total' => (float) (($line->quantity ?? 0) * ($line->purchase_price_inc_tax ?? 0)),
                'lot_number' => $line->lot_number ?? null,
            ];
        })->all();

        $payments = collect($compra->payment_lines ?? [])->map(function ($p) {
            return [
                'id' => $p->id,
                'paid_on' => optional($p->paid_on)->toIso8601String(),
                'amount' => (float) ($p->amount ?? 0),
                'method' => $p->method ?? null,
                'card_transaction_number' => $p->card_transaction_number ?? null,
                'cheque_number' => $p->cheque_number ?? null,
                'bank_account_number' => $p->bank_account_number ?? null,
                'note' => $p->note ?? null,
                'is_return' => (bool) ($p->is_return ?? false),
            ];
        })->all();

        $timeline = Activity::forSubject($compra)
            ->with('causer')
            ->latest()
            ->limit(50)
            ->get()
            ->map(function ($a) {
                return [
                    'id' => $a->id,
                    'description' => $a->description,
                    'causer_name' => optional($a->causer)->first_name
                        ? trim(($a->causer->surname ?? '').' '.($a->causer->first_name ?? '').' '.($a->causer->last_name ?? ''))
                        : 'Sistema',
                    'created_at' => $a->created_at?->toIso8601String(),
                    'properties' => $a->properties?->toArray(),
                ];
            })
            ->all();

        $contact = $compra->contact;

        return [
            'id' => $compra->id,
            'ref_no' => $compra->ref_no,
            'document' => $compra->document,
            'transaction_date' => optional($compra->transaction_date)->toIso8601String(),
            'type' => $compra->type,
            'status' => $compra->status,
            'payment_status' => $compra->payment_status,
            'final_total' => (float) ($compra->final_total ?? 0),
            'total_before_tax' => (float) ($compra->total_before_tax ?? 0),
            'tax_amount' => (float) ($compra->tax_amount ?? 0),
            'discount_amount' => (float) ($compra->discount_amount ?? 0),
            'shipping_charges' => (float) ($compra->shipping_charges ?? 0),
            'pay_term_number' => $compra->pay_term_number,
            'pay_term_type' => $compra->pay_term_type,
            'additional_notes' => $compra->additional_notes,
            'contact' => $contact ? [
                'id' => $contact->id,
                'name' => $contact->name,
                'supplier_business_name' => $contact->supplier_business_name,
                'tax_number' => $contact->tax_number,
                'city' => $contact->city,
                'mobile' => $contact->mobile,
                'email' => $contact->email,
            ] : null,
            'location' => $compra->location ? [
                'id' => $compra->location->id,
                'name' => $compra->location->name,
            ] : null,
            'lines' => $lines,
            'payments' => $payments,
            'timeline' => $timeline,
            'amount_paid' => array_sum(array_column($payments, 'amount')),
            'amount_due' => max(0, ((float) $compra->final_total) - array_sum(array_column($payments, 'amount'))),
        ];
    }
}
