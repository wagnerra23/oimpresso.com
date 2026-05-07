<?php

namespace Modules\Repair\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Modules\Repair\Entities\JobSheet;
use Modules\Repair\Utils\RepairUtil;

class DashboardController extends Controller
{
    /**
     * All Utils instance.
     */
    protected $repairUtil;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(RepairUtil $repairUtil)
    {
        $this->repairUtil = $repairUtil;
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        $business_id = request()->session()->get('user.business_id');
        $job_sheets_by_status = $this->repairUtil->getRepairByStatus($business_id);
        $job_sheets_by_service_staff = $this->repairUtil->getRepairByServiceStaff($business_id);
        $trending_brand_chart = $this->repairUtil->getTrendingRepairBrands($business_id);
        $trending_devices_chart = $this->repairUtil->getTrendingDevices($business_id);
        $trending_dm_chart = $this->repairUtil->getTrendingDeviceModels($business_id);

        // MWART-0002 (Sprint 2.5) — branch Inertia/React quando flag ativa.
        if ($this->mwartEnabled('repair_dashboard_index', (int) $business_id)) {
            // Util methods retornam CommonChart (objeto Highcharts) — incompatível com TSX que espera arrays.
            // Re-query inline pra entregar shape limpo {label,count}.
            $statusRows = collect($job_sheets_by_status)->map(fn ($r) => [
                'status' => $r->status_name ?? '—',
                'count' => (int) $r->total_job_sheets,
            ])->values()->all();

            $staffRows = collect($job_sheets_by_service_staff)->map(fn ($r) => [
                'staff' => trim($r->service_staff ?? '—') ?: '—',
                'count' => (int) $r->total_job_sheets,
            ])->values()->all();

            $trendingBrands = JobSheet::leftJoin('brands', 'repair_job_sheets.brand_id', '=', 'brands.id')
                ->where('repair_job_sheets.business_id', $business_id)
                ->whereNotNull('repair_job_sheets.brand_id')
                ->select('brands.name as brand', DB::raw('COUNT(repair_job_sheets.id) as count'))
                ->groupBy('brands.id')
                ->orderBy('count', 'desc')
                ->limit(10)
                ->get()
                ->toArray();

            $trendingModels = JobSheet::leftJoin('repair_device_models as RDM', 'repair_job_sheets.device_model_id', '=', 'RDM.id')
                ->where('repair_job_sheets.business_id', $business_id)
                ->whereNotNull('repair_job_sheets.device_model_id')
                ->select('RDM.name as model', DB::raw('COUNT(repair_job_sheets.id) as count'))
                ->groupBy('RDM.id')
                ->orderBy('count', 'desc')
                ->limit(10)
                ->get()
                ->toArray();

            return Inertia::render('Repair/Dashboard/Index', [
                'kpis' => [
                    'total_repairs' => is_countable($job_sheets_by_status) ? count($job_sheets_by_status) : 0,
                    'service_staff_count' => is_countable($job_sheets_by_service_staff) ? count($job_sheets_by_service_staff) : 0,
                ],
                'job_sheets_by_status' => $statusRows,
                'job_sheets_by_service_staff' => $staffRows,
                'trending_brand_chart' => $trendingBrands,
                'trending_devices_chart' => [],
                'trending_dm_chart' => $trendingModels,
            ]);
        }

        return view('repair::dashboard.index')
            ->with(compact('job_sheets_by_status', 'job_sheets_by_service_staff', 'trending_devices_chart', 'trending_dm_chart', 'trending_brand_chart'));
    }

    /**
     * MWART-0002 — verifica se flag MWART está habilitada pro business.
     */
    private function mwartEnabled(string $key, int $business_id): bool
    {
        if (! config("mwart.{$key}.enabled")) {
            return false;
        }
        $beta = (array) config("mwart.{$key}.business_ids", []);
        return empty($beta) || in_array($business_id, $beta, true);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        return view('repair::create');
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
        return view('repair::show');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function edit($id)
    {
        return view('repair::edit');
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
