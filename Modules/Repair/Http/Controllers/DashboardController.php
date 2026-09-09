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
            //
            // ⚠️ A chave É `label` nas CINCO séries, e isso é contrato com o consumidor:
            // `BarChartCard` (Index.tsx) lê `r.label`. Até 2026-09-09 este bloco mandava
            // `status`/`staff`/`brand`/`model` — os 4 gráficos renderizavam o rótulo VAZIO
            // em produção, enquanto o `.tsx:17` afirmava que "toda série é normalizada
            // pelo Controller pra {label,count}". O comentário estava certo sobre a
            // intenção e errado sobre o fato; agora os dois batem. Renomear aqui é seguro:
            // nenhum teste asserta a chave interna (todos usam `sum('count')`).
            $statusRows = collect($job_sheets_by_status)->map(fn ($r) => [
                'label' => $r->status_name ?? '—',
                'count' => (int) $r->total_job_sheets,
            ])->values()->all();

            $staffRows = collect($job_sheets_by_service_staff)->map(fn ($r) => [
                'label' => trim($r->service_staff ?? '—') ?: '—',
                'count' => (int) $r->total_job_sheets,
            ])->values()->all();

            $trendingBrands = JobSheet::leftJoin('brands', 'repair_job_sheets.brand_id', '=', 'brands.id')
                ->where('repair_job_sheets.business_id', $business_id)
                ->whereNotNull('repair_job_sheets.brand_id')
                ->select('brands.name as label', DB::raw('COUNT(repair_job_sheets.id) as count'))
                ->groupBy('brands.id')
                ->orderBy('count', 'desc')
                ->limit(10)
                ->get()
                ->toArray();

            $trendingModels = JobSheet::leftJoin('repair_device_models as RDM', 'repair_job_sheets.device_model_id', '=', 'RDM.id')
                ->where('repair_job_sheets.business_id', $business_id)
                ->whereNotNull('repair_job_sheets.device_model_id')
                ->select('RDM.name as label', DB::raw('COUNT(repair_job_sheets.id) as count'))
                ->groupBy('RDM.id')
                ->orderBy('count', 'desc')
                ->limit(10)
                ->get()
                ->toArray();

            // Re-query inline, no MESMO padrao dos dois irmaos acima — e nao
            // `collect(getTrendingDevices(...))`. O Util devolve um CommonChart
            // (objeto Highcharts), nao linhas: iterar sobre ele nao produz {device,count},
            // e o proprio comentario no topo deste bloco ja avisava disso. O ramo Blade
            // continua consumindo o CommonChart da linha 41, intacto.
            $trendingDevices = JobSheet::leftJoin('categories as CAT', 'repair_job_sheets.device_id', '=', 'CAT.id')
                ->where('repair_job_sheets.business_id', $business_id)
                ->whereNotNull('repair_job_sheets.device_id')
                ->select('CAT.name as label', DB::raw('COUNT(repair_job_sheets.id) as count'))
                ->groupBy('CAT.id')
                ->orderBy('count', 'desc')
                ->limit(10)
                ->get()
                ->toArray();

            return Inertia::render('Repair/Dashboard/Index', [
                'kpis' => $this->buildDashboardKpis((int) $business_id),
                'job_sheets_by_status' => $statusRows,
                'job_sheets_by_service_staff' => $staffRows,
                'trending_brand_chart' => $trendingBrands,
                'trending_devices_chart' => $trendingDevices,
                'trending_dm_chart' => $trendingModels,
            ]);
        }

        return view('repair::dashboard.index')
            ->with(compact('job_sheets_by_status', 'job_sheets_by_service_staff', 'trending_devices_chart', 'trending_dm_chart', 'trending_brand_chart'));
    }

    /**
     * KPIs de OPERAÇÃO da oficina — os que o protótipo (repair-page.jsx, região `Painel`)
     * põe no topo: quantas folhas estão abertas, quantas fecharam e quantas passaram do
     * prazo prometido no balcão.
     *
     * Substitui `count($job_sheets_by_status)`, que contava as LINHAS do agrupamento (=
     * quantos status distintos aparecem) e ficava preso em ~6 para sempre, com 3 ou 3.000
     * OS. Ver RUNBOOK-repair-dashboard.md §"Os dois defeitos que esta onda conserta".
     *
     * Uma agregada só, com `leftJoin` no catálogo: folha cujo status foi apagado no legado
     * conta como PENDENTE, que é o lado seguro — some da tela é pior que aparecer aberta.
     *
     * Multi-tenant: `business_id` explícito (ADR 0093 Tier 0 — JobSheet não tem global scope).
     */
    private function buildDashboardKpis(int $business_id): array
    {
        $linha = JobSheet::leftJoin('repair_statuses as RS', 'repair_job_sheets.status_id', '=', 'RS.id')
            ->where('repair_job_sheets.business_id', $business_id)
            ->selectRaw('
                SUM(CASE WHEN COALESCE(RS.is_completed_status, 0) = 1 THEN 1 ELSE 0 END) as concluidas,
                SUM(CASE WHEN COALESCE(RS.is_completed_status, 0) = 0 THEN 1 ELSE 0 END) as pendentes,
                SUM(CASE WHEN COALESCE(RS.is_completed_status, 0) = 0
                         AND COALESCE(repair_job_sheets.service_staff, 0) = 0 THEN 1 ELSE 0 END) as sem_tecnico,
                SUM(CASE WHEN COALESCE(RS.is_completed_status, 0) = 0
                         AND repair_job_sheets.delivery_date IS NOT NULL
                         AND repair_job_sheets.delivery_date < ? THEN 1 ELSE 0 END) as vencidas
            ', [now()])
            // `toBase()` antes do first(): sem ele o Eloquent devolve um JobSheet e os
            // aliases da agregada (`pendentes`, `vencidas`...) viram propriedade dinamica
            // num Model — que o PHPStan reprova com razao, e que arma a mina do
            // "atributo persistivel" descrita em proibicoes.md §FSM. Aqui o resultado e
            // uma linha de agregacao, nao uma entidade: stdClass e o tipo honesto.
            ->toBase()
            ->first();

        return [
            'pending' => (int) ($linha->pendentes ?? 0),
            'pending_unassigned' => (int) ($linha->sem_tecnico ?? 0),
            'completed' => (int) ($linha->concluidas ?? 0),
            'overdue' => (int) ($linha->vencidas ?? 0),
        ];
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
