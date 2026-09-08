<?php

namespace Modules\AssetManagement\Http\Controllers;

use App\BusinessLocation;
use App\Category;
use App\Media;
use App\Utils\ModuleUtil;
use App\Utils\Util;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Modules\AssetManagement\Entities\Asset;
use Modules\AssetManagement\Entities\AssetTransaction;
use Modules\AssetManagement\Entities\AssetWarranty;
use Modules\AssetManagement\Http\Requests\StoreAssetRequest;
use Modules\AssetManagement\Http\Requests\UpdateAssetRequest;
use Modules\AssetManagement\Services\AssetService;
use Modules\AssetManagement\Utils\AssetUtil;
use Yajra\DataTables\Facades\DataTables;

/**
 * Wave 16 governance D4 Architecture: Controller magro — regras de criacao /
 * atualizacao / remocao delegadas a AssetService. Index/dashboard mantidos
 * (queries de leitura + datatables permanecem aqui por enquanto — extraidos
 * em wave futura).
 */
class AssetController extends Controller
{
    /**
     * All Utils instance.
     */
    protected $moduleUtil;

    protected $commonUtil;

    protected $assetUtil;

    protected $assetService;

    protected $purchaseTypes;

    /**
     * Constructor — DI Tier 0: ModuleUtil/Util/AssetUtil mantidos pra
     * compatibilidade com index/dashboard; AssetService novo pra store/update/destroy.
     */
    public function __construct(
        ModuleUtil $moduleUtil,
        Util $commonUtil,
        AssetUtil $assetUtil,
        AssetService $assetService,
    ) {
        $this->moduleUtil = $moduleUtil;
        $this->commonUtil = $commonUtil;
        $this->assetUtil = $assetUtil;
        $this->assetService = $assetService;

        $this->purchaseTypes = [
            'owned' => __('assetmanagement::lang.owned'),
            'rented' => __('assetmanagement::lang.rented'),
            'leased' => __('assetmanagement::lang.leased'),
        ];
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        // Permissão de TELA antes do gate de assinatura — mesmo formato de
        // create() (:271), edit() e destroy(). Até 2026-09-08 o index() checava
        // só a assinatura do módulo, então qualquer usuário da empresa com o
        // módulo assinado listava o patrimônio inteiro; as checagens de
        // `asset.update`/`asset.delete` mais abaixo só desenham botão de linha,
        // e botão escondido não é autorização.
        // `asset.view` é a permissão que o próprio módulo já declara para esta
        // tela: registrada em DataController::user_permissions() e usada no
        // `@can('asset.view')` que envolve o link "Ativos" na nav do módulo.
        if (! auth()->user()->can('asset.view')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        if (! (auth()->user()->can('superadmin') || ($this->moduleUtil->hasThePermissionInSubscription($business_id, 'assetmanagement_module')))) {
            abort(403, 'Unauthorized action.');
        }

        $purchase_types = $this->purchaseTypes;

        if ($request->ajax()) {
            $assets = $this->baseAssetsQuery($business_id);

            $this->applyAssetFilters($assets);

            $now = \Carbon::now();

            return Datatables::of($assets)
                ->addColumn('action', function ($row) {
                    $html = '<div class="btn-group">
                                    <button class="btn btn-info dropdown-toggle btn-xs" type="button"  data-toggle="dropdown" aria-expanded="false">
                                        '.__('messages.action').'
                                        <span class="caret"></span>
                                        <span class="sr-only">
                                        '.__('messages.action').'
                                        </span>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-left" role="menu">
                                    ';

                    if ($row->is_allocatable && (($row->allocated_qty - $row->revoked_qty) != $row->quantity)) {
                        $html .= '<li>
                                    <a data-href="'.action([\Modules\AssetManagement\Http\Controllers\AssetAllocationController::class, 'create'], ['asset_id' => $row->id]).'" class="cursor-pointer" id="allocate_asset">
                                        <i class="fas fa-plus-circle"></i>
                                    '.__('assetmanagement::lang.allocate_asset').'
                                    </a>
                                    </li>';
                    }

                    if (auth()->user()->can('asset.view_all_maintenance') || auth()->user()->can('asset.view_own_maintenance')) {
                        $html .= '<li>
                                        <a data-href="'.route('asset-maintenance.create').'?asset_id='.$row->id.'" class="cursor-pointer send_to_maintenance">
                                            <i class="fas fa-tools"></i>
                                        '.__('assetmanagement::lang.send_to_maintenance').'
                                        </a>
                                        </li>';
                    }

                    if (auth()->user()->can('asset.update')) {
                        $html .= '
                                <li>
                                    <a data-href="'.action([\Modules\AssetManagement\Http\Controllers\AssetController::class, 'edit'], [$row->id]).'" class="cursor-pointer edit_asset">
                                        <i class="fa fa-edit"></i>
                                        '.__('messages.edit').'
                                    </a>
                                </li>';
                    }
                    if (auth()->user()->can('asset.delete')) {
                        $html .= '<li>
                                    <a data-href="'.action([\Modules\AssetManagement\Http\Controllers\AssetController::class, 'destroy'], [$row->id]).'"  id="delete_asset" class="cursor-pointer">
                                        <i class="fas fa-trash"></i>
                                        '.__('messages.delete').'
                                    </a>
                                </li>
                                </ul>';
                    }

                    $html .= '
                            </div>';

                    return $html;
                })
                ->editColumn('purchase_date', '
                    @if(!empty($purchase_date))
                        {{@format_date($purchase_date)}}
                    @endif
                ')
                ->editColumn('is_allocatable', function ($row) {
                    if ($row->is_allocatable) {
                        return '<i class="fas fa-check-circle text-success"></i>';
                    } else {
                        return '<i class="fas fa-times-circle text-danger"></i>';
                    }
                })
                ->editColumn('quantity', '
                    @if(!empty($quantity))
                        {{@format_quantity($quantity)}}
                    @endif
                ')
                ->editColumn('allocated_qty', '
                    @if(!empty($allocated_qty))
                        {{@format_quantity($allocated_qty - $revoked_qty)}}
                    @endif
                ')
                ->editColumn('unit_price', function ($row) {
                    return '<span class="display_currency total-discount" data-currency_symbol="true" data-orig-value="'.$row->unit_price.'">'.$row->unit_price.'</span>';
                })
                ->addColumn('image', function ($row) {
                    $html = '';
                    if (! empty($row->media->first())) {
                        $url = $row->media->first()->display_url;
                        $html = '<a href="'.$url.'" target="_blank"><i class="fas fa-eye fa-lg"></i></a>';
                    }

                    return $html;
                })
                ->addColumn('warranty', function ($row) use ($now) {
                    $warranty = null;

                    $html = '';
                    foreach ($row->warranties as $w) {
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
                ->editColumn('asset', function ($row) {
                    $html = $row->asset;

                    foreach ($row->maintenances as $maintenance) {
                        if (in_array($maintenance->status, ['new', 'in_progress'])) {
                            $count = $row->maintenances->whereIn('status', ['new', 'in_progress'])->count();
                            $html .= '<br><span class="label bg-red">'.__('assetmanagement::lang.n_in_maintenance', ['n' => $count]).'</span>';
                            break;
                        }
                    }

                    return $html;
                })
                ->removeColumn('id')
                ->removeColumn('maintenances')
                ->rawColumns(['action', 'is_allocatable', 'purchase_date',
                    'quantity', 'allocated_qty', 'unit_price', 'warranty_period', 'image', 'warranty', 'asset', ])
                ->make(true);
        }

        $business_locations = BusinessLocation::forDropdown($business_id);
        $asset_category = Category::forDropdown($business_id, 'asset');

        // MWART F3 (ADR 0104) — a tela de Bens virou Inertia em 2026-09-08, no endereco
        // decidido pela ADR 0394 (`Pages/Patrimonio/**`, modulo proprio). A URL NAO muda:
        // continua sendo a rota `assets.index` (`GET /asset/assets`) declarada pelo
        // Route::resource — rota nova seria um segundo dono da mesma tela.
        //
        // O ramo `$request->ajax()` acima continua servindo o DataTables do Blade e fica
        // INERTE para esta tela (o React recebe o paginator por prop). Remove-lo, junto
        // com `Resources/views/asset/index.blade.php`, e cutover (F5) — nao frontend.
        // Runbook: memory/requisitos/AssetManagement/RUNBOOK-bens.md
        return Inertia::render('Patrimonio/Bens', [
            'filtros' => [
                'q' => $request->input('q'),
                'location_id' => $request->input('location_id'),
                'category_id' => $request->input('category_id'),
                'purchase_type' => $request->input('purchase_type'),
                'is_allocatable' => $request->input('is_allocatable'),
                'sort' => $request->input('sort'),
                'dir' => $request->input('dir'),
            ],
            // Eager de proposito: sao dois dropdowns pequenos e indexados, e o filtro
            // nascer vazio no primeiro paint seria pior que o custo deles.
            'opcoes' => [
                'locais' => $business_locations,
                'categorias' => $asset_category,
                'tipos_compra' => $purchase_types,
            ],
            'permissoes' => [
                'criar' => auth()->user()->can('asset.create'),
                'editar' => auth()->user()->can('asset.update'),
                'excluir' => auth()->user()->can('asset.delete'),
                'manutencao' => auth()->user()->can('asset.view_all_maintenance')
                    || auth()->user()->can('asset.view_own_maintenance'),
            ],
            // Inertia::defer — a prop cara da tela (3 leftJoin + agregacao + eager-load
            // de media/warranties/maintenances). Regra default do projeto pra prop com
            // paginate/with/subquery: RUNBOOK-inertia-defer-pattern.md
            'bens' => Inertia::defer(fn () => $this->buildBensPayload($request, $business_id)),
        ]);
    }

    /**
     * Query base da listagem de bens — dono UNICO da agregacao.
     *
     * Extraida em 2026-09-08 do proprio `index()`, sem alterar uma virgula do SELECT,
     * para que o ramo AJAX (DataTables do Blade) e o ramo Inertia leiam a MESMA
     * expressao. Duas copias da mesma agregacao envelheceriam separadas, e a proxima
     * correcao pousaria em so uma delas.
     *
     * ⚠️ RESIDUO Tier 0 HERDADO — nao introduzido aqui, e deliberadamente NAO corrigido
     * nesta onda: nem o join `AT` (allocate) nem a subconsulta `AR` (revoke) filtram por
     * `business_id`, entao as duas agregam transacao de QUALQUER empresa. E o gemeo ja
     * catalogado em `_saida-01.md §9(a)` ("o gemeo AssetController:97 e o mesmo defeito e
     * tem MAIOR alcance — e o indice"), que tem thread dona. Corrigir aqui esbarraria em
     * duas leis que caem juntas: mexer em QUANTIDADE e REGRA MESTRE Tier 0 (prova por dois
     * caminhos + antes→depois apresentado ao [W]) e 1 PR = 1 intent. A expressao fica
     * byte-a-byte como estava; a medicao antes→depois esta pronta no `_saida-06-bens.md`.
     * Enquanto nao fechar, `allocated_qty`/`revoked_qty` NAO sao numeros auditados.
     */
    private function baseAssetsQuery($business_id)
    {
        return Asset::with(['media', 'warranties', 'maintenances'])
                    ->leftJoin('categories as CAT', 'assets.category_id',
                        '=', 'CAT.id')
                    ->leftJoin('business_locations as BL', 'assets.location_id',
                        '=', 'BL.id')
                    ->leftJoin('asset_transactions as AT', function ($join) {
                        $join->on('assets.id', '=', 'AT.asset_id')
                            ->where('transaction_type', 'allocate');
                    })
                    ->where('assets.business_id', $business_id)
                    ->select('asset_code', 'assets.name as asset', 'assets.quantity as quantity',
                    'model', 'purchase_date',
                    'unit_price', 'is_allocatable',
                    'CAT.name as category', 'BL.name as location',
                    'assets.id as id', DB::raw('SUM(COALESCE(AT.quantity, 0)) as allocated_qty'),
                    DB::raw('(SELECT SUM(COALESCE(AR.quantity, 0)) FROM asset_transactions AS AR WHERE(AR.asset_id=assets.id AND AR.transaction_type=\'revoke\')) as revoked_qty'),
                    'assets.description as description'
                    )
                    ->groupBy('id');
    }

    /**
     * Filtros compartilhados pelos dois ramos — mesma ordem e mesmos predicados de antes.
     *
     * `permitted_locations()` vem PRIMEIRO de proposito: e restricao de permissao, nao
     * escolha do usuario, e nao pode ser afrouxada por parametro de query.
     */
    private function applyAssetFilters($assets)
    {
        $permitted_locations = auth()->user()->permitted_locations();

        if ($permitted_locations != 'all') {
            $assets->whereIn('assets.location_id', $permitted_locations);
        }

        if (! empty(request()->input('location_id'))) {
            $assets->where('assets.location_id', request()->input('location_id'));
        }
        if (! empty(request()->input('category_id'))) {
            $assets->where('assets.category_id', request()->input('category_id'));
        }
        if (! empty(request()->input('purchase_type'))) {
            $assets->where('assets.purchase_type', request()->input('purchase_type'));
        }
        if (! empty(request()->input('is_allocatable'))) {
            $assets->where('assets.is_allocatable', 1);
        }

        return $assets;
    }

    /**
     * Paginator da tela Inertia de Bens (`Pages/Patrimonio/Bens.tsx`).
     *
     * Busca e ordenacao existem SO neste ramo: o DataTables do Blade faz a propria busca,
     * e aplica-las tambem la mudaria o comportamento da tela legada, que nao e o escopo.
     */
    private function buildBensPayload(Request $request, $business_id)
    {
        $assets = $this->baseAssetsQuery($business_id);

        $this->applyAssetFilters($assets);

        // Busca — os 4 campos que identificam o bem, os mesmos que o prototipo procura
        // (`patrimonio-page.jsx:275`: id + nome + modelo + serie).
        $q = trim((string) $request->input('q', ''));
        if ($q !== '') {
            $assets->where(function ($query) use ($q) {
                $termo = '%'.$q.'%';
                $query->where('assets.name', 'like', $termo)
                    ->orWhere('assets.asset_code', 'like', $termo)
                    ->orWhere('assets.model', 'like', $termo)
                    ->orWhere('assets.serial_no', 'like', $termo);
            });
        }

        // Whitelist de ordenacao: o `sort` chega da query string, entao coluna fora desta
        // lista nao vira SQL. As chaves sao as do payload; os valores, a coluna real.
        $ordenaveis = [
            'asset_code' => 'assets.asset_code',
            'nome' => 'assets.name',
            'categoria' => 'CAT.name',
            'local' => 'BL.name',
            'quantidade' => 'assets.quantity',
            'valor_unitario' => 'assets.unit_price',
            'compra_em' => 'assets.purchase_date',
        ];
        $sort = (string) $request->input('sort', '');
        $dir = strtolower((string) $request->input('dir', 'asc')) === 'desc' ? 'desc' : 'asc';
        $assets->orderBy($ordenaveis[$sort] ?? 'assets.name', isset($ordenaveis[$sort]) ? $dir : 'asc');

        $now = \Carbon::now();

        return $assets->paginate(25)->withQueryString()->through(function ($row) use ($now) {
            $garantia = null;
            foreach ($row->warranties as $w) {
                $inicio = \Carbon::parse($w->start_date);
                $fim = \Carbon::parse($w->end_date);
                if ($now->between($inicio, $fim)) {
                    $garantia = [
                        'inicio' => $this->commonUtil->format_date($w->start_date),
                        'fim' => $this->commonUtil->format_date($w->end_date),
                        'dias_restantes' => (int) $now->diffInDays($fim, false),
                    ];
                    break;
                }
            }

            $em_manutencao = $row->maintenances
                ->whereIn('status', ['new', 'in_progress'])
                ->count();

            $midia = $row->media->first();

            return [
                'id' => $row->id,
                'asset_code' => $row->asset_code,
                'nome' => $row->asset,
                'modelo' => $row->model,
                'categoria' => $row->category,
                'local' => $row->location,
                'quantidade' => (float) $row->quantity,
                // Herdam o residuo Tier 0 do §9 do RUNBOOK — nao sao numeros auditados.
                'alocado' => (float) $row->allocated_qty - (float) $row->revoked_qty,
                'alocavel' => (bool) $row->is_allocatable,
                'valor_unitario' => (float) $row->unit_price,
                'compra_em' => $row->purchase_date
                    ? $this->commonUtil->format_date($row->purchase_date)
                    : null,
                'garantia' => $garantia,
                'em_manutencao' => $em_manutencao,
                'imagem_url' => $midia ? $midia->display_url : null,
            ];
        });
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create(Request $request)
    {
        if (! auth()->user()->can('asset.create')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        if (! (auth()->user()->can('superadmin') || ($this->moduleUtil->hasThePermissionInSubscription($business_id, 'assetmanagement_module')))) {
            abort(403, 'Unauthorized action.');
        }

        if ($request->ajax()) {
            $asset_category = Category::forDropdown($business_id, 'asset');
            $business_locations = BusinessLocation::forDropdown($business_id);

            $purchase_types = $this->purchaseTypes;

            return view('assetmanagement::asset.create')
                ->with(compact('asset_category', 'business_locations', 'purchase_types'));
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(StoreAssetRequest $request)
    {
        // D8.c Wave 10 — validation + permission checks (asset.create + subscription)
        // movidos pra StoreAssetRequest::authorize/rules.
        // Wave 16 D4 — regra de criacao + media + warranties extraida pra AssetService.
        $business_id = request()->session()->get('user.business_id');
        $user_id = request()->session()->get('user.id');

        try {
            $this->assetService->criar($request, (int) $business_id, (int) $user_id);
            $output = ['success' => true, 'msg' => __('lang_v1.success')];
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.app(\App\Support\Privacy\PiiRedactor::class)->redact($e->getMessage()));
            $output = ['success' => false, 'msg' => __('messages.something_went_wrong')];
        }

        return redirect()
            ->action([\Modules\AssetManagement\Http\Controllers\AssetController::class, 'index'])
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
        if (! auth()->user()->can('asset.update')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        if (! (auth()->user()->can('superadmin') || ($this->moduleUtil->hasThePermissionInSubscription($business_id, 'assetmanagement_module')))) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            $asset = Asset::with(['warranties'])
                        ->where('business_id', $business_id)
                        ->findOrfail($id);

            $asset_category = Category::forDropdown($business_id, 'asset');
            $business_locations = BusinessLocation::forDropdown($business_id);

            $purchase_types = $this->purchaseTypes;

            return view('assetmanagement::asset.edit')
                ->with(compact('asset_category', 'business_locations', 'asset', 'purchase_types'));
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @param  int  $id
     * @return Response
     */
    public function update(UpdateAssetRequest $request, $id)
    {
        // D8.c Wave 10 — validation + permission checks via FormRequest.
        // Wave 16 D4 — regra de update + warranties + media delegada a AssetService.
        $business_id = request()->session()->get('user.business_id');

        try {
            $this->assetService->atualizar($request, (int) $id, (int) $business_id);
            $output = ['success' => true, 'msg' => __('lang_v1.success')];
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.app(\App\Support\Privacy\PiiRedactor::class)->redact($e->getMessage()));
            $output = ['success' => false, 'msg' => __('messages.something_went_wrong')];
        }

        return redirect()
            ->action([\Modules\AssetManagement\Http\Controllers\AssetController::class, 'index'])
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
        if (! auth()->user()->can('asset.delete')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        if (! (auth()->user()->can('superadmin') || ($this->moduleUtil->hasThePermissionInSubscription($business_id, 'assetmanagement_module')))) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            try {
                // Wave 16 D4 — remocao delegada a AssetService.
                $this->assetService->remover((int) $id, (int) $business_id);
                $output = ['success' => true, 'msg' => __('lang_v1.success')];
            } catch (\Exception $e) {
                \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.app(\App\Support\Privacy\PiiRedactor::class)->redact($e->getMessage()));
                $output = ['success' => false, 'msg' => __('messages.something_went_wrong')];
            }

            return $output;
        }
    }

    public function dashboard()
    {
        $business_id = request()->session()->get('user.business_id');

        $allocated_assets = AssetTransaction::where('receiver', auth()->user()->id)
                                            ->select(
                                                DB::raw('SUM(quantity) as total_quantity_allocated'),
                                                DB::raw('(SELECT SUM(quantity) FROM asset_transactions as AT WHERE AT.parent_id=asset_transactions.id AND AT.transaction_type="revoke") as total_revoked_quantity')
                                            )->first();

        $total_assets_allocated = $allocated_assets->total_quantity_allocated - $allocated_assets->total_revoked_quantity;

        $asset_allocation_by_category = AssetTransaction::where('asset_transactions.receiver',
                                                    auth()->user()->id)
                            ->leftJoin('assets as a', 'a.id',
                            '=', 'asset_transactions.asset_id')
                            ->leftJoin('categories as cat', 'a.category_id',
                            '=', 'cat.id')
                                            ->select(
                                                DB::raw("SUM(COALESCE(asset_transactions.quantity, 0) - (SELECT SUM(quantity) FROM asset_transactions as AT WHERE AT.parent_id=asset_transactions.id AND AT.transaction_type='revoke')) as total_quantity_allocated"),
                                                'cat.name as category'
                                            )->groupBy('cat.id')->get();

        $is_admin = $this->commonUtil->is_admin(auth()->user());

        $total_assets = 0;
        $assets_by_category = null;
        $total_assets_allocated_for_all_users = 0;
        $expiring_assets = null;

        if ($is_admin) {
            $total_assets = Asset::where('business_id', $business_id)
                                ->select(DB::raw('SUM(quantity) as total_quantity'))
                                ->first()->total_quantity;

            $assets_by_category = Asset::where('assets.business_id', $business_id)
                                    ->leftJoin('categories as cat', 'assets.category_id', '=', 'cat.id')
                                ->select(
                                        DB::raw('SUM(quantity) as total_quantity'),
                                        'cat.name as category'
                                    )
                                ->groupBy('cat.id')
                                ->get();

            $expiring_assets = Asset::where('assets.business_id', $business_id)
                                    ->leftjoin('asset_warranties as aw', 'aw.asset_id', '=', 'assets.id')
                                    // O `orWhereNull` ficava FORA deste closure. Como AND liga
                                    // mais forte que OR, o SQL virava
                                    //   (assets.business_id = X AND datas…) OR (aw.end_date IS NULL)
                                    // e o OR escapava do filtro de tenant: todo bem SEM garantia,
                                    // de QUALQUER empresa, entrava nesta lista — e o select traz
                                    // `assets.name` e `asset_code`. Não dependia de dado corrompido;
                                    // vazava sempre. Trazer o `orWhereNull` para dentro do closure
                                    // mantém a intenção (garantia vencendo em 30d OU bem sem
                                    // garantia registrada) com o `business_id` aplicado aos dois
                                    // lados do OR. ADR 0093 — multi-tenant Tier 0 IRREVOGÁVEL.
                                    ->where(function ($q) {
                                        $q->where(function ($sub) {
                                            $sub->whereRaw('CURDATE() BETWEEN start_date AND end_date')
                                                ->whereRaw('DATEDIFF(end_date, CURDATE()) <= 30')
                                                ->whereRaw('DATEDIFF(end_date, CURDATE()) > 0');
                                        })->orWhereNull('aw.end_date');
                                    })
                                    ->select('assets.name', 'asset_code', 'end_date')
                                    ->get();

            $allocated_assets_for_all_users = AssetTransaction::where('business_id', $business_id)
                                            ->select(
                                                DB::raw("SUM(IF(transaction_type='allocate', quantity, 0)) as total_quantity_allocated"),
                                                DB::raw("SUM(IF(transaction_type='revoke', quantity, 0)) as total_revoked_quantity")
                                            )->first();

            $total_assets_allocated_for_all_users = $allocated_assets_for_all_users->total_quantity_allocated - $allocated_assets_for_all_users->total_revoked_quantity;
        }

        return view('assetmanagement::asset.dashboard')
                ->with(compact('total_assets_allocated', 'asset_allocation_by_category',
                    'is_admin', 'total_assets', 'assets_by_category', 'expiring_assets', 'total_assets_allocated_for_all_users'));
    }
}
