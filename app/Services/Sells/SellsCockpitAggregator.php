<?php

namespace App\Services\Sells;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * SellsCockpitAggregator — fonte única dos números do cockpit "Analista IA" (Jana V2).
 *
 * Consumido por:
 *  - App\Http\Controllers\SellController@index  (Sells/Index — payload sellKpis + coworkAggregates)
 *  - Modules\Jana\Http\Controllers\DashboardController@index  (/ia/dashboard — payload completo do cockpit)
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093): todo where() recebe ->where('business_id', $businessId)
 * explícito além do global scope (defesa em profundidade).
 *
 * Origem: extração do app/Http/Controllers/SellController (linhas 649-665 sellKpis + 863-972
 * buildCoworkAggregates). Sem mudança de comportamento — Pest tests devem continuar passando.
 */
class SellsCockpitAggregator
{
    /**
     * KPIs counters da grade Sells (Total / Paga / Pendente / Parcial / Estourada).
     *
     * Origem: extraído verbatim de SellController@index linhas 649-665.
     */
    public function buildSellKpis(int $businessId): array
    {
        $kpiBase = \App\Transaction::where('business_id', $businessId)
            ->where('type', 'sell')
            ->where('status', 'final')
            ->whereNull('sub_type');

        return [
            'total' => (clone $kpiBase)->count(),
            'paid' => (clone $kpiBase)->where('payment_status', 'paid')->count(),
            'due' => (clone $kpiBase)->where('payment_status', 'due')->count(),
            'partial' => (clone $kpiBase)->where('payment_status', 'partial')->count(),
            'overdue' => (clone $kpiBase)
                ->whereIn('payment_status', ['due', 'partial'])
                ->whereNotNull('pay_term_number')
                ->whereNotNull('pay_term_type')
                ->whereRaw("IF(pay_term_type='days', DATE_ADD(transaction_date, INTERVAL pay_term_number DAY) < CURDATE(), DATE_ADD(transaction_date, INTERVAL pay_term_number MONTH) < CURDATE())")
                ->count(),
        ];
    }

    /**
     * Agregados Cowork (sparkline 30d + deltas hoje vs ontem / semana vs anterior + topSeller mês + PIX hoje).
     *
     * Origem: extraído verbatim de SellController@buildCoworkAggregates linhas 863-972.
     */
    public function buildCoworkAggregates(int $businessId): array
    {
        $today = now()->startOfDay();
        $yesterday = (clone $today)->subDay();
        $start30 = (clone $today)->subDays(29);
        $monthStart = (clone $today)->startOfMonth();
        $lastWeekStart = (clone $today)->subDays(13);
        $lastWeekEnd = (clone $today)->subDays(7);
        $thisWeekStart = (clone $today)->subDays(6);

        // Sparkline — 30d revenue per day.
        $sparkRowsQ = \App\Transaction::where('business_id', $businessId)
            ->where('type', 'sell')
            ->where('status', 'final')
            ->whereNull('sub_type')
            ->whereBetween('transaction_date', [$start30, (clone $today)->endOfDay()])
            ->selectRaw('DATE(transaction_date) as d, SUM(final_total) as total')
            ->groupBy('d')
            ->orderBy('d');

        $sparkByDate = $sparkRowsQ->get()->keyBy('d')->map(fn ($r) => (float) $r->total);

        $sparkline = [];
        for ($i = 29; $i >= 0; $i--) {
            $date = (clone $today)->subDays($i)->format('Y-m-d');
            $sparkline[] = (float) ($sparkByDate[$date] ?? 0.0);
        }

        // Delta revenue hoje vs ontem.
        $revToday = \App\Transaction::where('business_id', $businessId)
            ->where('type', 'sell')
            ->where('status', 'final')
            ->whereNull('sub_type')
            ->whereDate('transaction_date', $today)
            ->sum('final_total');
        $revYesterday = \App\Transaction::where('business_id', $businessId)
            ->where('type', 'sell')
            ->where('status', 'final')
            ->whereNull('sub_type')
            ->whereDate('transaction_date', $yesterday)
            ->sum('final_total');

        $deltaRevenueVsYesterday = $revYesterday > 0
            ? (int) round((($revToday - $revYesterday) / $revYesterday) * 100)
            : null;

        // Delta ticket médio esta semana vs semana passada.
        $thisWeekRows = \App\Transaction::where('business_id', $businessId)
            ->where('type', 'sell')
            ->where('status', 'final')
            ->whereNull('sub_type')
            ->whereBetween('transaction_date', [$thisWeekStart, (clone $today)->endOfDay()])
            ->selectRaw('COUNT(*) as c, SUM(final_total) as s')
            ->first();
        $lastWeekRows = \App\Transaction::where('business_id', $businessId)
            ->where('type', 'sell')
            ->where('status', 'final')
            ->whereNull('sub_type')
            ->whereBetween('transaction_date', [$lastWeekStart, $lastWeekEnd])
            ->selectRaw('COUNT(*) as c, SUM(final_total) as s')
            ->first();

        $thisWeekTicket = ($thisWeekRows && $thisWeekRows->c > 0) ? $thisWeekRows->s / $thisWeekRows->c : 0.0;
        $lastWeekTicket = ($lastWeekRows && $lastWeekRows->c > 0) ? $lastWeekRows->s / $lastWeekRows->c : 0.0;

        $deltaTicketVsLastWeek = $lastWeekTicket > 0
            ? (int) round((($thisWeekTicket - $lastWeekTicket) / $lastWeekTicket) * 100)
            : null;

        // Top vendedor do mês (commission_agent).
        $topSellerRow = \App\Transaction::where('transactions.business_id', $businessId)
            ->where('transactions.type', 'sell')
            ->where('transactions.status', 'final')
            ->whereNull('transactions.sub_type')
            ->whereBetween('transactions.transaction_date', [$monthStart, (clone $today)->endOfDay()])
            ->whereNotNull('transactions.commission_agent')
            ->join('users', 'users.id', '=', 'transactions.commission_agent')
            ->selectRaw("CONCAT(COALESCE(users.first_name, ''), ' ', COALESCE(users.last_name, '')) as name, SUM(transactions.final_total) as total")
            ->groupBy('transactions.commission_agent', 'users.first_name', 'users.last_name')
            ->orderByDesc('total')
            ->limit(1)
            ->first();

        $topSeller = $topSellerRow && trim((string) $topSellerRow->name) !== ''
            ? ['name' => trim((string) $topSellerRow->name), 'total' => (float) $topSellerRow->total]
            : null;

        // PIX hoje — soma transaction_payments method='custom_pay_1' (canon UPOS).
        $pixHojeTotal = (float) DB::table('transaction_payments')
            ->join('transactions', 'transactions.id', '=', 'transaction_payments.transaction_id')
            ->where('transactions.business_id', $businessId)
            ->where('transactions.type', 'sell')
            ->where('transactions.status', 'final')
            ->whereNull('transactions.sub_type')
            ->whereDate('transactions.transaction_date', $today)
            ->where('transaction_payments.method', 'custom_pay_1')
            ->sum('transaction_payments.amount');

        return [
            'sparkline' => $sparkline,
            'deltaRevenueVsYesterday' => $deltaRevenueVsYesterday,
            'deltaTicketVsLastWeek' => $deltaTicketVsLastWeek,
            'topSeller' => $topSeller,
            'pixHojeTotal' => $pixHojeTotal,
            'faturadoHojeTotal' => (float) $revToday,
        ];
    }

    /**
     * Pré-agregações que o JanaCockpitV2 antes computava no frontend a partir de `rows`.
     *
     * Pré-computar server-side elimina dependência do componente ser embutido em uma tela
     * que já tenha os rows filtrados carregados (necessário pra `/ia/dashboard` standalone).
     *
     * Retorna:
     *   - overdueCount: int — vendas atrasadas (paid != true && days_to_due < 0)
     *   - overdueValue: float — soma final_total das overdue
     *   - ageingBuckets: ['0-30d','30-90d','90-365d','>365d'] => float (overdue por idade)
     *   - methodsAgg: array<{method, total}> (top 5 por payment_method_label) — método da última payment
     *   - topClientes: array<{name, total}> (top 5 customer_name pra base atual)
     *   - topDevedor: {name, total}|null — maior venda overdue do tenant
     *   - ticketMedio: float — média final_total das vendas final
     *   - totalAReceber: float — sum final_total onde payment_status != 'paid'
     */
    public function buildInsightsAggregates(int $businessId): array
    {
        $today = now()->startOfDay();

        // Base — vendas final do tenant. Multi-tenant Tier 0.
        // Wagner 2026-05-27 HOTFIX: colunas prefixadas com `transactions.` porque
        // mais abaixo (linha 218 + 285) há leftJoin('contacts', ...) e a tabela
        // contacts TAMBÉM tem `business_id` + `type` — sem prefix MySQL retorna
        // "Column 'business_id' in where clause is ambiguous", quebra
        // PDO->prepare() e gera 500 em /ia/dashboard (Larissa @ Rota Livre).
        $base = \App\Transaction::where('transactions.business_id', $businessId)
            ->where('transactions.type', 'sell')
            ->where('transactions.status', 'final')
            ->whereNull('transactions.sub_type');

        // Computa due_date e days_to_due via subquery — apenas vendas com pay_term completo.
        // sla_kind='overdue' espelha lógica do SellController@getListSells (linha 1425):
        //   paid → paid; sem pay_term → fresh; days < 0 → overdue; <=7 → warning; >7 → fresh.
        //
        // Overdue = não-paga + tem pay_term + due_date < hoje.
        $overdueSelect = "
            transactions.id,
            transactions.final_total,
            transactions.transaction_date,
            transactions.payment_status,
            transactions.pay_term_number,
            transactions.pay_term_type,
            CASE
                WHEN transactions.pay_term_type='days' THEN DATE_ADD(transactions.transaction_date, INTERVAL transactions.pay_term_number DAY)
                WHEN transactions.pay_term_type='months' THEN DATE_ADD(transactions.transaction_date, INTERVAL transactions.pay_term_number MONTH)
                ELSE NULL
            END as due_date_calc,
            CASE
                WHEN transactions.pay_term_type='days' THEN DATEDIFF(DATE_ADD(transactions.transaction_date, INTERVAL transactions.pay_term_number DAY), CURDATE())
                WHEN transactions.pay_term_type='months' THEN DATEDIFF(DATE_ADD(transactions.transaction_date, INTERVAL transactions.pay_term_number MONTH), CURDATE())
                ELSE NULL
            END as days_to_due_calc,
            COALESCE(contacts.supplier_business_name, contacts.name) as customer_label
        ";

        $overdueRows = (clone $base)
            ->whereIn('transactions.payment_status', ['due', 'partial'])
            ->whereNotNull('transactions.pay_term_number')
            ->whereNotNull('transactions.pay_term_type')
            ->whereRaw("IF(transactions.pay_term_type='days', DATE_ADD(transactions.transaction_date, INTERVAL transactions.pay_term_number DAY) < CURDATE(), DATE_ADD(transactions.transaction_date, INTERVAL transactions.pay_term_number MONTH) < CURDATE())")
            ->leftJoin('contacts', 'contacts.id', '=', 'transactions.contact_id')
            ->selectRaw($overdueSelect)
            ->get();

        $overdueCount = $overdueRows->count();
        $overdueValue = (float) $overdueRows->sum('final_total');

        // Ageing buckets — agrupa por abs(days_to_due) (já negativo nas overdue).
        $ageingBuckets = ['0-30d' => 0.0, '30-90d' => 0.0, '90-365d' => 0.0, '>365d' => 0.0];
        foreach ($overdueRows as $row) {
            $days = abs((int) ($row->days_to_due_calc ?? 0));
            $v = (float) $row->final_total;
            if ($days <= 30) {
                $ageingBuckets['0-30d'] += $v;
            } elseif ($days <= 90) {
                $ageingBuckets['30-90d'] += $v;
            } elseif ($days <= 365) {
                $ageingBuckets['90-365d'] += $v;
            } else {
                $ageingBuckets['>365d'] += $v;
            }
        }

        // Top devedor — maior venda overdue do tenant.
        $topDevedor = null;
        $topRow = $overdueRows->sortByDesc(fn ($r) => (float) $r->final_total)->first();
        if ($topRow) {
            $name = trim((string) ($topRow->customer_label ?? '')) ?: 'Cliente padrão';
            $topDevedor = ['name' => $name, 'total' => (float) $topRow->final_total];
        }

        // Métodos de pagamento — top 5 por SUM(amount) usando o método da ÚLTIMA payment
        // de cada venda final (espelha lógica do SellController.getListSells linha 1462).
        // Mapeia chaves curtas pra labels PT-BR.
        $methodsRaw = DB::table('transaction_payments as tp')
            ->join('transactions', 'transactions.id', '=', 'tp.transaction_id')
            ->where('transactions.business_id', $businessId)
            ->where('transactions.type', 'sell')
            ->where('transactions.status', 'final')
            ->whereNull('transactions.sub_type')
            ->selectRaw('tp.method as method, SUM(tp.amount) as total')
            ->groupBy('tp.method')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        $methodLabel = fn (string $m): string => match ($m) {
            'cash'         => 'Dinheiro',
            'card'         => 'Cartão',
            'bank_transfer'=> 'Transferência',
            'cheque'       => 'Cheque',
            'other'        => 'Outro',
            'custom_pay_1' => 'PIX',
            'custom_pay_2' => 'Boleto',
            'custom_pay_3' => 'Crediário',
            ''             => 'Outros',
            default        => ucfirst($m),
        };

        $methodsAgg = $methodsRaw->map(fn ($r) => [
            'method' => $methodLabel((string) ($r->method ?? '')),
            'total'  => (float) $r->total,
        ])->values()->all();

        // Top 5 clientes — sum final_total agrupado por contact.
        $topClientesRaw = (clone $base)
            ->leftJoin('contacts', 'contacts.id', '=', 'transactions.contact_id')
            ->selectRaw("COALESCE(NULLIF(TRIM(COALESCE(contacts.supplier_business_name, contacts.name)), ''), 'Cliente padrão') as name, SUM(transactions.final_total) as total")
            ->groupBy('name')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        $topClientes = $topClientesRaw->map(fn ($r) => [
            'name'  => (string) $r->name,
            'total' => (float) $r->total,
        ])->values()->all();

        // Ticket médio + total a receber.
        $aggregates = (clone $base)
            ->selectRaw('COUNT(*) as c, SUM(final_total) as s, SUM(CASE WHEN payment_status != \'paid\' THEN final_total ELSE 0 END) as a_receber')
            ->first();

        $ticketMedio = ($aggregates && $aggregates->c > 0) ? (float) ($aggregates->s / $aggregates->c) : 0.0;
        $totalAReceber = $aggregates ? (float) $aggregates->a_receber : 0.0;

        return [
            'overdueCount'   => $overdueCount,
            'overdueValue'   => $overdueValue,
            'ageingBuckets'  => $ageingBuckets,
            'methodsAgg'     => $methodsAgg,
            'topClientes'    => $topClientes,
            'topDevedor'     => $topDevedor,
            'ticketMedio'    => $ticketMedio,
            'totalAReceber'  => $totalAReceber,
        ];
    }
}
