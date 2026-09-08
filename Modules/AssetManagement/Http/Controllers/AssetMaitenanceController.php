<?php

namespace Modules\AssetManagement\Http\Controllers;

use App\Media;
use App\User;
use App\Utils\ModuleUtil;
use App\Utils\Util;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Modules\AssetManagement\Entities\Asset;
use Modules\AssetManagement\Entities\AssetMaintenance;
use Modules\AssetManagement\Services\AssetMaintenanceService;
use Modules\AssetManagement\Utils\AssetUtil;
use Yajra\DataTables\Facades\DataTables;

/**
 * Wave 16 governance D4 Architecture: Controller magro — regras de
 * persistencia delegadas a AssetMaintenanceService.
 *
 * AUTORIZACAO (corrigida em 2026-09-08 — eram DOIS defeitos no mesmo `if`).
 *
 * Ate aqui os 6 metodos guardados (index, create, store, edit, update, destroy)
 * usavam a forma:
 *
 *     if (! ((can('asset.view_all_maintenance') && can('asset.view_own_maintenance'))
 *            || hasThePermissionInSubscription($business_id, 'assetmanagement_module')))
 *
 * (a) O `&&` exigia as DUAS permissoes. Elas sao declaradas como `is_radio` com o
 *     mesmo `radio_input_name` = `view_maintenance` em DataController::user_permissions()
 *     (:51 e :58), ou seja, sao mutuamente exclusivas na UI de papeis: nenhum usuario
 *     nao-admin consegue marcar as duas. O gate era insatisfazivel por construcao — e
 *     barrava exatamente o perfil para o qual o filtro de escopo do proprio metodo foi
 *     escrito (index() :73 faz `(!view_all) && view_own`, o "vejo so as minhas").
 *
 * (b) O `|| subscription` anulava o gate inteiro: como o segundo operando e verdadeiro
 *     para todo usuario do business que assina o modulo, o `if` colapsava em "o modulo
 *     esta assinado". Por isso (a) nunca apareceu em producao — e consertar so o `&&`
 *     nao mudaria nada em runtime (LC-30: verde no CI, inerte no ar).
 *
 * A forma correta e a de AssetController::create() (:271) e index() (PR #7008):
 * permissao de TELA primeiro, gate de assinatura DEPOIS, dois `if` sequenciais — nunca
 * em `OR` um com o outro. A permissao NAO foi inventada: as duas ja estao registradas
 * em DataController::user_permissions() e sao as que o modulo declara para esta area.
 *
 * O dono do negocio nao depende de nenhuma das duas: o `Gate::before` de
 * App\Providers\AuthServiceProvider (:34-46) devolve `true` para quem tem o role
 * `Admin#{business_id}` em qualquer ability fora de backup/superadmin/manage_modules.
 *
 * RESIDUO DECLARADO, NAO CONSERTADO (decisao de produto — [W]): edit(), update() e
 * destroy() filtram apenas por `business_id`, nao por dono. Quem tem so
 * `view_own_maintenance` enxerga apenas as suas na listagem, mas pode editar/remover a
 * de outro se souber o id. Isso NAO e regressao deste conserto — hoje qualquer usuario
 * do business ja podia, por (b) — e fechar exigiria uma permissao de escrita que o
 * modulo nao declara.
 *
 * @see Modules/AssetManagement/Http/Controllers/AssetController::create()
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */
class AssetMaitenanceController extends Controller
{
    /**
     * All Utils instance.
     */
    protected $moduleUtil;

    protected $commonUtil;

    protected $assetUtil;

    protected $maintenanceService;

    /**
     * Constructor — DI Service + Utils legacy.
     */
    public function __construct(
        ModuleUtil $moduleUtil,
        Util $commonUtil,
        AssetUtil $assetUtil,
        AssetMaintenanceService $maintenanceService,
    ) {
        $this->moduleUtil = $moduleUtil;
        $this->commonUtil = $commonUtil;
        $this->assetUtil = $assetUtil;
        $this->maintenanceService = $maintenanceService;

        $this->maintenanceStatuses = $this->assetUtil->maintenanceStatuses();

        $this->maintenancePriorities = $this->assetUtil->maintenancePriorities();
    }

    /**
     * Display a listing of the resource.
     *
     * Dois ramos: `ajax` devolve o DataTables do Blade legado (JsonResponse); o normal
     * devolve a tela Inertia `Patrimonio/Manutencoes` desde 2026-09-08 (MWART F3).
     *
     * @return \Inertia\Response|\Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $business_id = request()->session()->get('user.business_id');
        // Permissao de TELA antes do gate de assinatura (ver docblock da classe).
        // As duas permissoes sao `is_radio` do mesmo `view_maintenance`, logo `||`.
        if (! (auth()->user()->can('asset.view_all_maintenance') || auth()->user()->can('asset.view_own_maintenance'))) {
            abort(403, 'Unauthorized action.');
        }

        if (! (auth()->user()->can('superadmin') || ($this->moduleUtil->hasThePermissionInSubscription($business_id, 'assetmanagement_module')))) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            $query = AssetMaintenance::with(['asset', 'asset.warranties'])
                        ->where('asset_maintenances.business_id', $business_id)
                        ->leftJoin('users as u', 'u.id', '=', 'asset_maintenances.assigned_to')
                        ->leftJoin('users as u1', 'u1.id', '=', 'asset_maintenances.created_by');

            if (! auth()->user()->can('asset.view_all_maintenance') && auth()->user()->can('asset.view_own_maintenance')) {
                $query->where(function ($q) {
                    $q->where('asset_maintenances.created_by', auth()->user()->id)
                    ->orWhere('asset_maintenances.assigned_to', auth()->user()->id);
                });
            }

            if (! empty(request()->input('status'))) {
                $query->where('asset_maintenances.status', request()->input('status'));
            }

            if (! empty(request()->input('priority'))) {
                $query->where('asset_maintenances.priority', request()->input('priority'));
            }

            if (! empty(request()->input('assigned_to'))) {
                $query->where('asset_maintenances.assigned_to', request()->input('assigned_to'));
            }

            $asset_maintenances = $query->select([
                'asset_maintenances.asset_id',
                'asset_maintenances.maitenance_id',
                'asset_maintenances.status',
                'asset_maintenances.priority',
                'asset_maintenances.id',
                'asset_maintenances.details',
                'asset_maintenances.created_at',
                'u.id as assigned_user_id',
                DB::raw("CONCAT(COALESCE(u.surname, ''), ' ', COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) as assigned_to_user"),
                DB::raw("CONCAT(COALESCE(u1.surname, ''), ' ', COALESCE(u1.first_name, ''), ' ', COALESCE(u1.last_name, '')) as created_by_user"),
            ]);

            $now = \Carbon::now();

            return Datatables::of($asset_maintenances)
                ->addColumn('asset_name', function ($row) {
                    return $row->asset->name ?? '';
                })
                ->addColumn('warranty', function ($row) use ($now) {
                    $warranty = null;

                    $html = '';
                    foreach ($row->asset->warranties as $w) {
                        $start_date = \Carbon::parse($w->start_date);
                        $end_date = \Carbon::parse($w->end_date);
                        if ($now->between($start_date, $end_date)) {
                            $warranty = $w;

                            $html = '<span class="label bg-green">'.__('assetmanagement::lang.in_warranty').'</span><br>';

                            $html .= '<small>'.$this->commonUtil->format_date($w->start_date).' ~ '.$this->commonUtil->format_date($w->end_date).'</br>';
                            $html .= '('.$now->diffInDays($end_date, false).' '.__('assetmanagement::lang.days_left').') </small>';

                            break;
                        }
                    }

                    if (empty($warranty)) {
                        $html = '<span class="label bg-red">'.__('assetmanagement::lang.not_in_warranty').'</span>';
                    }

                    return $html;
                })
                ->editColumn('assigned_to_user', '@if(empty($assigned_user_id))<small class="label bg-lightgray text-danger">@lang("assetmanagement::lang.unassigned")</small> @else {{$assigned_to_user}} @endif')
                ->filterColumn('assigned_to_user', function ($query, $keyword) {
                    $query->whereRaw("CONCAT(COALESCE(u.surname, ''), ' ', COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) like ?", ["%{$keyword}%"]);
                })
                ->filterColumn('created_by_user', function ($query, $keyword) {
                    $query->whereRaw("CONCAT(COALESCE(u1.surname, ''), ' ', COALESCE(u1.first_name, ''), ' ', COALESCE(u1.last_name, '')) like ?", ["%{$keyword}%"]);
                })
                ->editColumn('status', function ($row) {
                    $statuses = $this->maintenanceStatuses;
                    $html = '';

                    if (! empty($statuses[$row->status])) {
                        $html = '<span class="label '.$statuses[$row->status]['class'].'" >'.$statuses[$row->status]['label'].'</span>';
                    }

                    return $html;
                })
                ->editColumn('priority', function ($row) {
                    $priorities = $this->maintenancePriorities;
                    $html = '';

                    if (! empty($priorities[$row->priority])) {
                        $html = '<span class="label '.$priorities[$row->priority]['class'].'" >'.$priorities[$row->priority]['label'].'</span>';
                    }

                    return $html;
                })
                ->editColumn('created_at', function ($row) {
                    $datetime = $this->commonUtil->format_date($row->created_at, true);
                    $datetime .= '<br><small class="text-muted">'.\Carbon::parse($row->created_at)->diffForHumans().'</small>';

                    return $datetime;
                })
                ->addColumn('action', function ($row) {
                    $html = '<button type="button" class="btn btn-primary btn-xs edit_maintenance" data-href="'.action([\Modules\AssetManagement\Http\Controllers\AssetMaitenanceController::class, 'edit'], [$row->id]).'"><i class="fas fa-edit"></i> '.__('messages.edit').'</button>';

                    $html .= ' <button type="button" data-href="'.action([\Modules\AssetManagement\Http\Controllers\AssetMaitenanceController::class, 'destroy'], [$row->id]).'"  id="delete_asset_maintenance" class="btn btn-danger btn-xs">
                                    <i class="fas fa-trash"></i>
                                    '.__('messages.delete').'
                                </button>';

                    return $html;
                })
                ->removeColumn('id')
                ->rawColumns(['status', 'priority', 'action', 'created_at',
                    'assigned_to_user', 'warranty', ])
                ->make(true);
        }

        $statuses = [];
        foreach ($this->maintenanceStatuses as $key => $value) {
            $statuses[$key] = $value['label'];
        }

        $priorities = [];
        foreach ($this->maintenancePriorities as $key => $value) {
            $priorities[$key] = $value['label'];
        }

        $users = User::forDropdown($business_id, false);

        // MWART F3 (ADR 0104) - a tela de Manutencoes virou Inertia em 2026-09-08, no
        // endereco `Pages/Patrimonio/**` (ADR 0394). O ramo `ajax` acima FICA: ele serve o
        // DataTables do Blade legado, que outras telas ainda chamam.
        //
        // PARIDADE e o contrato desta onda: as colunas e os 3 filtros sao os do Blade. NAO
        // entra custo - a tabela `asset_maintenances` nao tem coluna de valor e o Blade nao
        // mostra nenhuma (decisao [W] 2026-09-08). O `UC-MANU-03` guarda esse contrato.
        return Inertia::render('Patrimonio/Manutencoes', [
            'filtros' => [
                'q' => $request->input('q'),
                'status' => $request->input('status'),
                'priority' => $request->input('priority'),
                'assigned_to' => $request->input('assigned_to'),
                'sort' => $request->input('sort'),
                'dir' => $request->input('dir'),
            ],
            'opcoes' => [
                'status' => $statuses,
                'prioridades' => $priorities,
                'responsaveis' => $users,
            ],
            'permissoes' => [
                // `false` => o usuario tem so `view_own_maintenance` e a lista vem recortada
                // por dono. A tela DIZ isso; o Blade recortava calado.
                'vejo_todas' => auth()->user()->can('asset.view_all_maintenance'),
            ],
            // Deferida: e a prop cara (paginate + joins + garantias). Primeiro paint sai com
            // cabecalho, sub-nav e filtros; a lista chega depois (RUNBOOK-inertia-defer).
            'manutencoes' => Inertia::defer(fn () => $this->buildManutencoesPayload($request, $business_id)),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        $business_id = request()->session()->get('user.business_id');
        // Permissao de TELA antes do gate de assinatura (ver docblock da classe).
        // As duas permissoes sao `is_radio` do mesmo `view_maintenance`, logo `||`.
        if (! (auth()->user()->can('asset.view_all_maintenance') || auth()->user()->can('asset.view_own_maintenance'))) {
            abort(403, 'Unauthorized action.');
        }

        if (! (auth()->user()->can('superadmin') || ($this->moduleUtil->hasThePermissionInSubscription($business_id, 'assetmanagement_module')))) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            $asset_id = request()->input('asset_id');

            $asset = Asset::with(['warranties'])
                        ->where('business_id', $business_id)
                        ->findOrfail($asset_id);

            $statuses = [];
            foreach ($this->maintenanceStatuses as $key => $value) {
                $statuses[$key] = $value['label'];
            }

            $priorities = [];
            foreach ($this->maintenancePriorities as $key => $value) {
                $priorities[$key] = $value['label'];
            }

            return view('assetmanagement::asset_maintenance.create')
                    ->with(compact('asset', 'statuses', 'priorities'));
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(Request $request)
    {
        $business_id = request()->session()->get('user.business_id');
        // Permissao de TELA antes do gate de assinatura (ver docblock da classe).
        // As duas permissoes sao `is_radio` do mesmo `view_maintenance`, logo `||`.
        if (! (auth()->user()->can('asset.view_all_maintenance') || auth()->user()->can('asset.view_own_maintenance'))) {
            abort(403, 'Unauthorized action.');
        }

        if (! (auth()->user()->can('superadmin') || ($this->moduleUtil->hasThePermissionInSubscription($business_id, 'assetmanagement_module')))) {
            abort(403, 'Unauthorized action.');
        }

        try {
            // Wave 16 D4 — criacao + notificacao delegada a AssetMaintenanceService.
            $this->maintenanceService->criar($request, (int) $business_id, (int) auth()->user()->id);
            $output = ['success' => true, 'msg' => __('lang_v1.success')];
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.app(\App\Support\Privacy\PiiRedactor::class)->redact($e->getMessage()));

            $output = [
                'success' => false,
                'msg' => 'File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage(),
            ];
        }

        return redirect()
            ->action([\Modules\AssetManagement\Http\Controllers\AssetMaitenanceController::class, 'index'])
            ->with('status', $output);
    }

    /**
     * Show the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function show($id)
    {
        return view('assetmanagement::show');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function edit($id)
    {
        $business_id = request()->session()->get('user.business_id');
        // Permissao de TELA antes do gate de assinatura (ver docblock da classe).
        // As duas permissoes sao `is_radio` do mesmo `view_maintenance`, logo `||`.
        if (! (auth()->user()->can('asset.view_all_maintenance') || auth()->user()->can('asset.view_own_maintenance'))) {
            abort(403, 'Unauthorized action.');
        }

        if (! (auth()->user()->can('superadmin') || ($this->moduleUtil->hasThePermissionInSubscription($business_id, 'assetmanagement_module')))) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            $maintenance = AssetMaintenance::where('business_id', $business_id)
                        ->with(['media'])
                        ->findOrfail($id);

            $statuses = [];
            foreach ($this->maintenanceStatuses as $key => $value) {
                $statuses[$key] = $value['label'];
            }

            $priorities = [];
            foreach ($this->maintenancePriorities as $key => $value) {
                $priorities[$key] = $value['label'];
            }

            $users = User::forDropdown($business_id, false);

            return view('assetmanagement::asset_maintenance.edit')
                    ->with(compact('maintenance', 'statuses', 'priorities', 'users'));
        }
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
        $business_id = request()->session()->get('user.business_id');
        // Permissao de TELA antes do gate de assinatura (ver docblock da classe).
        // As duas permissoes sao `is_radio` do mesmo `view_maintenance`, logo `||`.
        if (! (auth()->user()->can('asset.view_all_maintenance') || auth()->user()->can('asset.view_own_maintenance'))) {
            abort(403, 'Unauthorized action.');
        }

        if (! (auth()->user()->can('superadmin') || ($this->moduleUtil->hasThePermissionInSubscription($business_id, 'assetmanagement_module')))) {
            abort(403, 'Unauthorized action.');
        }

        try {
            // Wave 16 D4 — atualizacao + notificacao delegada a AssetMaintenanceService.
            $this->maintenanceService->atualizar($request, (int) $id, (int) $business_id);
            $output = ['success' => true, 'msg' => __('lang_v1.success')];
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.app(\App\Support\Privacy\PiiRedactor::class)->redact($e->getMessage()));

            $output = [
                'success' => false,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return redirect()
            ->action([\Modules\AssetManagement\Http\Controllers\AssetMaitenanceController::class, 'index'])
            ->with('status', $output);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function destroy($id)
    {
        $business_id = request()->session()->get('user.business_id');
        // Permissao de TELA antes do gate de assinatura (ver docblock da classe).
        // As duas permissoes sao `is_radio` do mesmo `view_maintenance`, logo `||`.
        if (! (auth()->user()->can('asset.view_all_maintenance') || auth()->user()->can('asset.view_own_maintenance'))) {
            abort(403, 'Unauthorized action.');
        }

        if (! (auth()->user()->can('superadmin') || ($this->moduleUtil->hasThePermissionInSubscription($business_id, 'assetmanagement_module')))) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            try {
                // Wave 16 D4 — remocao delegada a AssetMaintenanceService.
                $this->maintenanceService->remover((int) $id, (int) $business_id);
                $output = ['success' => true, 'msg' => __('lang_v1.success')];
            } catch (\Exception $e) {
                \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.app(\App\Support\Privacy\PiiRedactor::class)->redact($e->getMessage()));
                $output = ['success' => false, 'msg' => __('messages.something_went_wrong')];
            }

            return $output;
        }
    }

    /**
     * Paginator da tela Inertia de Manutencoes (`Pages/Patrimonio/Manutencoes.tsx`).
     *
     * Busca e paginacao existem SO neste ramo: o DataTables do Blade faz a propria busca, e
     * aplica-las tambem la mudaria a tela legada, que nao e o escopo desta onda.
     *
     * TIER 0 (ADR 0093): `AssetMaintenance` NAO tem global scope - o filtro de
     * `business_id` e explicito, e e o MESMO do ramo ajax. O recorte por dono tambem e o
     * mesmo (`:73`): quem tem so `view_own_maintenance` ve onde e `created_by` ou
     * `assigned_to`. Duplicar a regra com outro predicado aqui criaria duas verdades.
     */
    private function buildManutencoesPayload(Request $request, $business_id)
    {
        // A relacao EXISTE (`AssetMaintenance::asset()`, belongsTo em Entities:44) — o
        // larastan e que nao a resolve neste Model, e o mesmo eager load ja e feito no ramo
        // ajax logo acima. Ignore local em vez de crescer o phpstan-baseline.neon.
        /** @phpstan-ignore-next-line larastan.relationExistence */
        $query = AssetMaintenance::with(['asset', 'asset.warranties'])
            ->where('asset_maintenances.business_id', $business_id)
            ->leftJoin('users as u', 'u.id', '=', 'asset_maintenances.assigned_to')
            ->leftJoin('users as u1', 'u1.id', '=', 'asset_maintenances.created_by');

        // Recorte por dono - a precedencia do `!` em PHP torna isto `(!view_all) && view_own`,
        // que e o perfil "vejo so as minhas". Identico ao ramo ajax.
        if (! auth()->user()->can('asset.view_all_maintenance') && auth()->user()->can('asset.view_own_maintenance')) {
            $query->where(function ($q) {
                $q->where('asset_maintenances.created_by', auth()->user()->id)
                    ->orWhere('asset_maintenances.assigned_to', auth()->user()->id);
            });
        }

        // Os TRES filtros que o Blade ja oferecia, e so eles.
        if (! empty($request->input('status'))) {
            $query->where('asset_maintenances.status', $request->input('status'));
        }
        if (! empty($request->input('priority'))) {
            $query->where('asset_maintenances.priority', $request->input('priority'));
        }
        if (! empty($request->input('assigned_to'))) {
            $query->where('asset_maintenances.assigned_to', $request->input('assigned_to'));
        }

        $q = trim((string) $request->input('q', ''));
        if ($q !== '') {
            $termo = '%'.$q.'%';
            $query->where(function ($sub) use ($termo) {
                // O erro do larastan ancora no INICIO da cadeia fluente, nao na linha do
                // orWhereHas abaixo — por isso o ignore vem aqui, e nao ao lado da relacao.
                // Medido: com o ignore no orWhereHas o PHPStan seguia acusando a linha 526.
                /** @phpstan-ignore-next-line larastan.relationExistence */
                $sub->where('asset_maintenances.maitenance_id', 'like', $termo)
                    ->orWhere('asset_maintenances.details', 'like', $termo)
                    ->orWhere('asset_maintenances.maintenance_note', 'like', $termo)
                    ->orWhereHas('asset', function ($a) use ($termo) {
                        $a->where('assets.name', 'like', $termo)
                            ->orWhere('assets.asset_code', 'like', $termo);
                    });
            });
        }

        $manutencoes = $query
            ->select([
                'asset_maintenances.id',
                'asset_maintenances.asset_id',
                'asset_maintenances.maitenance_id',
                'asset_maintenances.status',
                'asset_maintenances.priority',
                'asset_maintenances.details',
                'asset_maintenances.maintenance_note',
                'asset_maintenances.created_at',
                DB::raw("CONCAT(COALESCE(u.surname, ''), ' ', COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) as assigned_to_user"),
                DB::raw("CONCAT(COALESCE(u1.surname, ''), ' ', COALESCE(u1.first_name, ''), ' ', COALESCE(u1.last_name, '')) as created_by_user"),
            ])
            ->orderByDesc('asset_maintenances.created_at')
            ->paginate(25)
            ->withQueryString();

        // `$this->assetUtil->...()` em vez de `$this->maintenanceStatuses`: a propriedade e
        // dinamica (setada no constructor sem declaracao) e o PHPStan nao a ve. O Util e a
        // MESMA fonte que o constructor usa — nao e outra verdade, e o mesmo array.
        $statuses = $this->assetUtil->maintenanceStatuses();
        $priorities = $this->assetUtil->maintenancePriorities();
        $now = \Carbon::now();

        $manutencoes->getCollection()->transform(function ($m) use ($statuses, $priorities, $now) {
            // Garantia: a MESMA leitura do ramo ajax - vigente se hoje esta na janela.
            $garantia = null;
            foreach (optional($m->getAttribute('asset'))->warranties ?? [] as $w) {
                $inicio = \Carbon::parse($w->start_date);
                $fim = \Carbon::parse($w->end_date);
                $garantia = [
                    'inicio' => $inicio->format('d/m/Y'),
                    'fim' => $fim->format('d/m/Y'),
                    'vigente' => $now->between($inicio, $fim),
                ];
                if ($garantia['vigente']) {
                    break;
                }
            }

            // `getAttribute`: sao ALIASES do select (`DB::raw(... as assigned_to_user)`), nao
            // colunas do Model — o PHPStan nao tem como saber que existem.
            $atribuido = trim((string) $m->getAttribute('assigned_to_user'));
            $criador = trim((string) $m->getAttribute('created_by_user'));

            // SEM campo de valor, de proposito: a tabela nao tem coluna de custo e o Blade
            // nao mostra nenhuma. Non-Goal do charter, guardado pelo UC-MANU-03.
            return [
                'id' => $m->id,
                'codigo' => $m->maitenance_id,
                'bem' => optional($m->getAttribute('asset'))->name ?? '',
                'bem_id' => $m->asset_id,
                'status' => $m->status,
                'status_label' => $statuses[$m->status]['label'] ?? (string) $m->status,
                'prioridade' => $m->priority,
                'prioridade_label' => $priorities[$m->priority]['label'] ?? (string) $m->priority,
                'garantia' => $garantia,
                'detalhes' => $m->details,
                'nota' => $m->maintenance_note,
                'criado_em' => $m->created_at ? \Carbon::parse($m->created_at)->format('d/m/Y H:i') : '',
                'criado_ha' => $m->created_at ? \Carbon::parse($m->created_at)->diffForHumans() : '',
                'atribuido_a' => $atribuido !== '' ? $atribuido : null,
                'criado_por' => $criador !== '' ? $criador : null,
            ];
        });

        return $manutencoes;
    }
}
