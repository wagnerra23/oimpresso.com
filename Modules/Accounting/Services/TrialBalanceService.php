<?php

declare(strict_types=1);

namespace Modules\Accounting\Services;

use Illuminate\Support\Facades\DB;

/**
 * Service thin — extrai queries SQL dos relatórios contábeis core do ReportController
 * (Wave J D4.a — fat-controller → service testável).
 *
 * Cobre os 4 relatórios fundacionais:
 *  - trial_balance        (todas contas no período)
 *  - balance_sheet        (asset/equity/liability até end_date)
 *  - profit_and_loss      (income/expense no período)
 *  - cash_flow            (todas contas até end_date)
 *
 * Multi-tenant Tier 0 (ADR 0093): caller passa $businessId obrigatório.
 *
 * @see Modules/Accounting/Http/Controllers/ReportController.php
 * @see memory/requisitos/Accounting/BRIEFING.md
 */
class TrialBalanceService
{
    /**
     * Trial Balance — soma debit/credit por conta no período.
     *
     * @return \Illuminate\Support\Collection<int,\stdClass>
     */
    public function trialBalance(int $businessId, ?string $startDate, ?string $endDate, ?int $locationId = null)
    {
        return DB::table('chart_of_accounts')
            ->join('journal_entries', 'journal_entries.chart_of_account_id', 'chart_of_accounts.id')
            ->join('business_locations', 'journal_entries.location_id', 'business_locations.id')
            ->when($startDate, function ($query) use ($startDate, $endDate) {
                $query->whereBetween('journal_entries.date', [$startDate, $endDate]);
            })
            ->when($locationId, function ($query) use ($locationId) {
                $query->where('journal_entries.location_id', $locationId);
            })
            ->where('chart_of_accounts.active', 1)
            ->where('chart_of_accounts.business_id', $businessId)
            ->selectRaw('chart_of_accounts.name,chart_of_accounts.gl_code,chart_of_accounts.account_type,business_locations.name business_location,SUM(journal_entries.debit) debit,SUM(journal_entries.credit) credit')
            ->groupBy('chart_of_accounts.id')
            ->get();
    }

    /**
     * Balance Sheet — asset/equity/liability até end_date.
     *
     * @return \Illuminate\Support\Collection<int,\stdClass>
     */
    public function balanceSheet(int $businessId, string $endDate, ?int $locationId = null)
    {
        return DB::table('chart_of_accounts')
            ->leftJoin('journal_entries', function ($join) use ($endDate) {
                $join->on('journal_entries.chart_of_account_id', '=', 'chart_of_accounts.id')
                    ->where('journal_entries.date', '<=', $endDate);
            })
            ->leftJoin('business_locations', function ($join) use ($locationId) {
                $join->on('journal_entries.location_id', '=', 'business_locations.id')
                    ->when($locationId, function ($q) use ($locationId) {
                        $q->where('journal_entries.location_id', $locationId);
                    });
            })
            ->leftJoin('account_subtypes', 'account_subtypes.account_type', '=', 'chart_of_accounts.account_type')
            ->where('chart_of_accounts.active', 1)
            ->whereIn('chart_of_accounts.account_type', ['asset', 'equity', 'liability'])
            ->where('chart_of_accounts.business_id', $businessId)
            ->selectRaw('chart_of_accounts.name,chart_of_accounts.gl_code,chart_of_accounts.account_type,account_subtypes.id account_subtype_id,account_subtypes.name account_subtype,business_locations.name business_location,journal_entries.debit,journal_entries.credit')
            ->groupBy('chart_of_accounts.id')
            ->orderBy('account_type')
            ->get();
    }

    /**
     * Profit & Loss (Income Statement) — income/expense no período.
     *
     * @return \Illuminate\Support\Collection<int,\stdClass>
     */
    public function profitAndLoss(int $businessId, string $startDate, string $endDate, ?int $locationId = null)
    {
        return DB::table('chart_of_accounts')
            ->join('journal_entries', 'journal_entries.chart_of_account_id', 'chart_of_accounts.id')
            ->join('business_locations', 'journal_entries.location_id', 'business_locations.id')
            ->whereBetween('journal_entries.date', [$startDate, $endDate])
            ->when($locationId, function ($query) use ($locationId) {
                $query->where('journal_entries.location_id', $locationId);
            })
            ->where('chart_of_accounts.active', 1)
            ->whereIn('chart_of_accounts.account_type', ['income', 'expense'])
            ->where('chart_of_accounts.business_id', $businessId)
            ->selectRaw('chart_of_accounts.name,chart_of_accounts.gl_code,chart_of_accounts.account_type,business_locations.name business_location,SUM(journal_entries.debit) debit,SUM(journal_entries.credit) credit')
            ->groupBy('chart_of_accounts.id')
            ->orderBy('account_type')
            ->get();
    }

    /**
     * Cash Flow — todas contas ativas até end_date.
     *
     * @return \Illuminate\Support\Collection<int,\stdClass>
     */
    public function cashFlow(int $businessId, string $endDate, ?int $locationId = null)
    {
        return DB::table('chart_of_accounts')
            ->leftJoin('journal_entries', function ($join) use ($endDate) {
                $join->on('journal_entries.chart_of_account_id', '=', 'chart_of_accounts.id')
                    ->where('journal_entries.date', '<=', $endDate);
            })
            ->leftJoin('business_locations', function ($join) use ($locationId) {
                $join->on('journal_entries.location_id', '=', 'business_locations.id')
                    ->when($locationId, function ($q) use ($locationId) {
                        $q->where('journal_entries.location_id', $locationId);
                    });
            })
            ->where('chart_of_accounts.active', 1)
            ->where('chart_of_accounts.business_id', $businessId)
            ->selectRaw('chart_of_accounts.name,chart_of_accounts.gl_code,chart_of_accounts.account_type,business_locations.name business_location,SUM(journal_entries.debit) debit,SUM(journal_entries.credit) credit')
            ->groupBy('chart_of_accounts.id')
            ->orderBy('account_type')
            ->get();
    }
}
