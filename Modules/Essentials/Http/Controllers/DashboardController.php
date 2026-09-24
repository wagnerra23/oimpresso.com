<?php

namespace Modules\Essentials\Http\Controllers;

use App\Category;
use App\User;
use App\Utils\ModuleUtil;
use App\Utils\TransactionUtil;
use DB;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Modules\Essentials\Entities\EssentialsHoliday;
use Modules\Essentials\Entities\EssentialsLeave;
use Modules\Essentials\Entities\EssentialsUserSalesTarget;
use Modules\Essentials\Utils\EssentialsUtil;
use Yajra\DataTables\Facades\DataTables;

class DashboardController extends Controller
{
    /**
     * All Utils instance.
     */
    protected $moduleUtil;

    protected $essentialsUtil;

    protected $transactionUtil;

    /**
     * Constructor
     *
     * @param  ModuleUtil  $moduleUtil
     * @return void
     */
    public function __construct(ModuleUtil $moduleUtil,
        EssentialsUtil $essentialsUtil,
        TransactionUtil $transactionUtil)
    {
        $this->moduleUtil = $moduleUtil;
        $this->essentialsUtil = $essentialsUtil;
        $this->transactionUtil = $transactionUtil;
    }

    /**
     * Painel do HRM — MWART thread 06 (playbook hrm). Só os agregados que o método já
     * calculava para a Blade `dashboard/hrm_dashboard`; nenhuma query nova para encher card.
     *
     * Saíram de propósito, e o charter Painel.charter.md diz por quê:
     *  - `essentials_attendances` (presença de hoje): desde a D1 a jornada é do Ponto (ADR 0014,
     *    emenda 2026-09-05) — a tela aponta para `/ponto`, sem número;
     *  - realizado do mês (`getUserTotalSales` ×2): é caminho de VALOR e a Metas já o excluiu
     *    pela mesma razão (Metas.charter.md, Non-Goals). A tela mostra as faixas gravadas.
     *
     * Tier 0 (ADR 0093): toda query filtra `business_id` — usuários, setores, licenças,
     * feriados; as faixas de meta são do próprio usuário autenticado.
     *
     * @return \Inertia\Response
     */
    public function hrmDashboard()
    {
        $business_id = request()->session()->get('user.business_id');
        $is_admin = (bool) $this->moduleUtil->is_admin(auth()->user(), $business_id);

        return Inertia::render('Essentials/Painel', [
            'is_admin' => $is_admin,
            // Inertia::defer — 5 queries (regra inertia-defer-default).
            'painel' => Inertia::defer(fn () => $this->buildPainelPayload($business_id, $is_admin)),
        ]);
    }

    /** @return array<string, mixed> */
    private function buildPainelPayload(int $business_id, bool $is_admin): array
    {
        $user_id = auth()->user()->id;
        $today = \Carbon::today();
        $ate = \Carbon::now()->addMonth();

        $leaves = EssentialsLeave::where('business_id', $business_id)
            ->where('user_id', $user_id)
            ->where('status', 'approved')
            ->whereDate('end_date', '>=', $today->format('Y-m-d'))
            ->whereDate('start_date', '<=', $ate->format('Y-m-d'))
            ->with('leave_type')
            ->orderBy('start_date')
            ->get();

        $holidays = EssentialsHoliday::where('essentials_holidays.business_id', $business_id)
            ->whereDate('end_date', '>=', $today->format('Y-m-d'))
            ->whereDate('start_date', '<=', $ate->format('Y-m-d'))
            ->orderBy('start_date')
            ->with('location');
        $permitted_locations = auth()->user()->permitted_locations();
        if ($permitted_locations != 'all') {
            $holidays->where(function ($q) use ($permitted_locations) {
                $q->whereIn('essentials_holidays.location_id', $permitted_locations)
                    ->orWhereNull('essentials_holidays.location_id');
            });
        }

        $setores = [];
        $colaboradores = null;
        if ($is_admin) {
            $users = User::where('business_id', $business_id)->user()->get(['id', 'essentials_department_id']);
            $colaboradores = $users->count();
            $nomes = Category::where('business_id', $business_id)
                ->where('category_type', 'hrm_department')->pluck('name', 'id');
            foreach ($users->groupBy('essentials_department_id') as $dept => $grupo) {
                $setores[] = ['nome' => $nomes[$dept] ?? 'Sem setor', 'total' => $grupo->count()];
            }
            usort($setores, fn ($a, $b) => $b['total'] <=> $a['total']);
        }

        return [
            'colaboradores' => $colaboradores,
            'setores' => $setores,
            'minhas_licencas' => $leaves->map(fn ($l) => [
                'id' => $l->id,
                'inicio' => \Carbon::parse($l->start_date)->format('Y-m-d'),
                'fim' => \Carbon::parse($l->end_date)->format('Y-m-d'),
                'tipo' => $l->leave_type->leave_type ?? '—',
            ])->values(),
            'feriados' => $holidays->get()->map(fn ($h) => [
                'id' => $h->id,
                'nome' => $h->name,
                'inicio' => \Carbon::parse($h->start_date)->format('Y-m-d'),
                'fim' => \Carbon::parse($h->end_date)->format('Y-m-d'),
                'local' => $h->location->name ?? null,
            ])->values(),
            'faixas_meta' => EssentialsUserSalesTarget::where('user_id', $user_id)->get()
                ->map(fn ($f) => [
                    'inicio' => (string) $f->target_start,
                    'fim' => (string) $f->target_end,
                    'pct' => (string) $f->commission_percent,
                ])->values(),
        ];
    }

    public function getUserSalesTargets()
    {
        $business_id = request()->session()->get('user.business_id');

        $is_admin = $this->moduleUtil->is_admin(auth()->user(), $business_id);

        $user_id = auth()->user()->id;

        if (! $is_admin) {
            abort(403, 'Unauthorized action.');
        }

        $this_month_start_date = \Carbon::today()->startOfMonth()->format('Y-m-d');
        $this_month_end_date = \Carbon::today()->endOfMonth()->format('Y-m-d');
        $last_month_start_date = \Carbon::parse('first day of last month')->format('Y-m-d');
        $last_month_end_date = \Carbon::parse('last day of last month')->format('Y-m-d');

        $settings = $this->essentialsUtil->getEssentialsSettings();

        $query = User::where('users.business_id', $business_id)
                    ->join('transactions as t', 't.commission_agent', '=', 'users.id')
                    ->where('t.type', 'sell')
                    ->whereDate('transaction_date', '>=', $last_month_start_date)
                    ->where('t.status', 'final');

        if (! empty($settings['calculate_sales_target_commission_without_tax']) && $settings['calculate_sales_target_commission_without_tax'] == 1) {
            $query->select(
                DB::raw("CONCAT(COALESCE(surname, ''), ' ', COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) as full_name"),
                DB::raw("SUM(IF(DATE(transaction_date) BETWEEN '{$last_month_start_date}' AND '{$last_month_end_date}', total_before_tax - shipping_charges - (SELECT SUM(item_tax*quantity) FROM transaction_sell_lines as tsl WHERE tsl.transaction_id=t.id), 0) ) as total_sales_last_month"),
                DB::raw("SUM(IF(DATE(transaction_date) BETWEEN '{$this_month_start_date}' AND '{$this_month_end_date}', total_before_tax - shipping_charges - (SELECT SUM(item_tax*quantity) FROM transaction_sell_lines as tsl WHERE tsl.transaction_id=t.id), 0) ) as total_sales_this_month")
            );
        } else {
            $query->select(
                    DB::raw("CONCAT(COALESCE(surname, ''), ' ', COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) as full_name"),
                    DB::raw("SUM(IF(DATE(transaction_date) BETWEEN '{$last_month_start_date}' AND '{$last_month_end_date}', final_total, 0)) as total_sales_last_month"),
                    DB::raw("SUM(IF(DATE(transaction_date) BETWEEN '{$this_month_start_date}' AND '{$this_month_end_date}', final_total, 0)) as total_sales_this_month")
                );
        }

        $query->groupBy('users.id');

        return Datatables::of($query)
                ->editColumn('total_sales_this_month', function ($row) {
                    return $this->transactionUtil->num_f($row->total_sales_this_month, true);
                })
                ->editColumn('total_sales_last_month', function ($row) {
                    return $this->transactionUtil->num_f($row->total_sales_last_month, true);
                })
                ->make(false);
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function essentialsDashboard()
    {
        return view('essentials::dashboard.essentials_dashboard');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        return view('essentials::create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Show the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function show($id)
    {
        return view('essentials::show');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function edit($id)
    {
        return view('essentials::edit');
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @param  int  $id
     * @return Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function destroy($id)
    {
        //
    }
}
