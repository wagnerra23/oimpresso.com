<?php

namespace Modules\Repair\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
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
            return Inertia::render('Repair/Dashboard/Index', [
                'kpis' => [
                    'total_repairs' => is_countable($job_sheets_by_status) ? count($job_sheets_by_status) : 0,
                    'service_staff_count' => is_countable($job_sheets_by_service_staff) ? count($job_sheets_by_service_staff) : 0,
                ],
                'job_sheets_by_status' => $job_sheets_by_status,
                'job_sheets_by_service_staff' => $job_sheets_by_service_staff,
                'trending_brand_chart' => $trending_brand_chart,
                'trending_devices_chart' => $trending_devices_chart,
                'trending_dm_chart' => $trending_dm_chart,
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
