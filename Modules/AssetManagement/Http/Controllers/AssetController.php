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
use Modules\AssetManagement\Entities\AssetMaintenance;
use Modules\AssetManagement\Entities\AssetTransaction;
use Modules\AssetManagement\Http\Requests\StoreAssetRequest;
use Modules\AssetManagement\Http\Requests\UpdateAssetRequest;
use Modules\AssetManagement\Services\AssetMaintenanceService;
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
     * O `@return` deixou de ser `Response` (`Illuminate\Http\Response`) em 2026-09-08: o
     * metodo passou a ter DOIS retornos reais e o docblock antigo descrevia nenhum dos dois.
     * O ramo `$request->ajax()` devolve o JSON do DataTables (`Datatables::make(true)`) e o
     * caminho da tela devolve `Inertia\Response`. Nao e detalhe de estilo — o PHPStan pegou
     * exatamente isto na primeira execucao apos a migracao.
     *
     * @return \Inertia\Response|\Illuminate\Http\JsonResponse
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

        // `! inertia()` NAO e zelo: sem ele a tela Inertia NUNCA recebe a tabela.
        // `Request::ajax()` le `X-Requested-With`, e o cliente Inertia manda esse header
        // INCONDICIONALMENTE, junto com `X-Inertia` (@inertiajs/core, getHeaders()). Como
        // a prop `bens` e DEFERIDA, ela so chega por partial reload — e todo partial caia
        // aqui, no ramo do DataTables, devolvendo JSON nao-Inertia que o cliente descarta.
        // Efeito em producao: header e filtros pintam, o skeleton fica pra sempre.
        //
        // MEDIDO no CT 100 (2026-09-08), mesma rota, mesma sessao:
        //   sem X-Requested-With .. {"component":"Patrimonio/Bens","props":{...,"bens":{...}}}
        //   com X-Requested-With .. {"draw":0,"recordsTotal":234,...}   <- o browser recebia ISTO
        //
        // O `BensContratoTest` nao pegou porque montava o partial SEM `X-Requested-With`,
        // ou seja, media uma requisicao que o browser nunca envia. Corrigido no mesmo PR.
        // Padrao da casa, ja em producao: `EssentialsLeaveController:95`.
        if ($request->ajax() && ! $request->inertia()) {
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

        return $this->renderBens($request, (int) $business_id);
    }

    /**
     * A tela de Bens (Inertia) — dona unica do render, usada por `index`, `create` e `edit`.
     *
     * Thread 17 do Patrimonio ([W] 2026-09-30): `create`/`edit` deixaram de devolver o fragmento
     * de modal Blade (que so respondia sob `ajax()` e a lista React nao abria) e passaram a devolver
     * ESTA Page com o drawer aberto. `$extra` e o que muda entre as tres: nada (index),
     * `abrir_cadastro` (create) ou `edicao` (edit).
     */
    private function renderBens(Request $request, int $business_id, array $extra = [])
    {
        $purchase_types = $this->purchaseTypes;

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
        return Inertia::render('Patrimonio/Bens', array_merge([
            // Contador da aba "Manutencoes" (pill do PageHeaderTabs). DEFERIDO: o
            // CLAUDE.md manda `Inertia::defer` em toda prop que faz query, e o pill nao
            // participa do first paint. Cobre `asset-maintenance` e mais nada -- dos 5
            // contadores do prototipo, so este tem numero AUDITADO hoje: Garantias e
            // Auditoria nao tem aba (D-GARANTIAS/D-AUDITORIA) e Alocacoes depende de
            // `allocated_qty`/`revoked_qty`, que o `baseAssetsQuery()` declara nao serem
            // numeros auditados enquanto o residuo Tier 0 dela nao fechar.
            'abas_contadores' => Inertia::defer(fn () => [
                'asset-maintenance' => app(AssetMaintenanceService::class)->contarAbertas($business_id),
            ]),
            'filtros' => [
                'q' => $request->input('q'),
                'location_id' => $request->input('location_id'),
                'category_id' => $request->input('category_id'),
                'purchase_type' => $request->input('purchase_type'),
                'is_allocatable' => $request->input('is_allocatable'),
                // `null` quando e "todos": o front tira nulo da URL, e `?recorte=todos` so
                // poluiria o link compartilhado. Valor fora da whitelist tambem cai aqui.
                'recorte' => $this->recorteAtivo($request) === 'todos' ? null : $this->recorteAtivo($request),
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
            // Formato de data do negocio: o `store()` grava `purchase_date` e a data da
            // garantia via `Util::uf_date`, que faz `createFromFormat(business.date_format)`.
            // O drawer de cadastro converte o ISO do <input type=date> pra este formato
            // antes de postar -- ISO cru faz o createFromFormat lancar. Mesmo contrato da
            // tela de Licencas (`Pages/Essentials/Licencas/Index.tsx::paraFormatoDoNegocio`).
            'formato_data' => (string) session('business.date_format', 'd/m/Y'),
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
            // Contagem da pilula "Garantia critica" — vem do SERVIDOR sobre o conjunto, nao da
            // pagina que chegou (charter: contar 25 de N linhas mente). Independe dos filtros
            // escolhidos, como o `n` do prototipo (`patrimonio-page.jsx:357`, sobre `visiveis`),
            // mas respeita `permitted_locations()`: restricao de permissao nunca e opcional.
            'recortes_contagem' => Inertia::defer(fn () => [
                'garantia' => $this->contarRecorteGarantia($business_id),
                'manutencao' => $this->contarRecorte($business_id, fn ($q) => $this->aplicarRecorteManutencao($q, $business_id)),
            ]),
            // Drawer de DETALHE do bem (leitura) — decisao [W] 2026-09-30, `_saida-16b.md`:
            // o historico de devolucoes (1 alocacao : N devolucoes) mora aqui, na aba
            // Alocacoes. Abre por `?bem=ID` com partial reload (`only: ['bem_detalhe',
            // 'bem_selecionado']`), o mesmo desenho de `Compras/Index` — a tabela nao volta.
            'bem_selecionado' => $this->bemSelecionado($request),
            'bem_detalhe' => Inertia::defer(fn () => $this->bemSelecionado($request)
                ? $this->buildBemDetalhe($this->bemSelecionado($request), (int) $business_id)
                : null),
        ], $extra));
    }

    /**
     * Recortes da lista de Bens (D-GARANTIAS, [W] 2026-09-29: "Garantia critica" e filtro
     * DENTRO de Bens, sem tela propria). Whitelist: valor fora dela vira `todos` e nunca
     * chega ao SQL. "Em manutencao" entrou em 2026-09-30 ([W]), com a mesma forma.
     */
    private const RECORTES = ['todos', 'garantia', 'manutencao'];

    private function recorteAtivo(Request $request): string
    {
        $recorte = (string) $request->input('recorte', 'todos');

        return in_array($recorte, self::RECORTES, true) ? $recorte : 'todos';
    }

    /**
     * A garantia que vale para cada bem: a MAIS RECENTE, isto e, a que termina por ultimo
     * (`MAX(end_date)`) — [W] 2026-09-30. Uma linha por bem.
     *
     * Dono UNICO da regra: o recorte "Garantia critica" de Bens, o KPI e os 4 baldes do Painel
     * leem daqui. Antes cada um lia TODAS as garantias do bem, e dois defeitos saiam juntos:
     * bem com garantia velha vencida + renovacao vigente contava como critico, e o `SUM` de
     * valor dos baldes somava o bem uma vez por garantia (o `COUNT(DISTINCT)` de bens nao).
     *
     * Tier 0 (ADR 0093): `asset_warranties` NAO tem `business_id` — entra por join com `assets`
     * filtrando o business EXPLICITAMENTE, alem do filtro de cada consumidor.
     */
    private function ultimaGarantiaPorBem($business_id)
    {
        return DB::table('asset_warranties as UGW')
            ->join('assets as UGA', 'UGA.id', '=', 'UGW.asset_id')
            ->where('UGA.business_id', $business_id)
            ->groupBy('UGW.asset_id')
            ->select('UGW.asset_id', DB::raw('MAX(UGW.end_date) as fim'));
    }

    /**
     * Garantia critica = a garantia MAIS RECENTE do bem esta vencida ou vence em ate 30 dias.
     * Bem SEM registro de garantia NAO entra (vai para "sem garantia" no Painel — charter R3).
     */
    private function aplicarRecorteGarantia($assets, $business_id)
    {
        return $assets->whereIn('assets.id', DB::query()
            ->fromSub($this->ultimaGarantiaPorBem($business_id), 'UG')
            ->whereRaw('DATEDIFF(UG.fim, CURDATE()) <= 30')
            ->select('UG.asset_id'));
    }

    /**
     * Em manutencao = o bem tem manutencao EM ABERTO: status `new` ou `in_progress`, a mesma
     * lista fechada de `AssetMaintenanceService::contarAbertas()` (pill da aba Manutencoes) e
     * do selo "N em manutencao" por linha. Status NULL ou desconhecido nao conta.
     *
     * Tier 0 (ADR 0093): `asset_maintenances` TEM `business_id` — filtrado explicitamente,
     * alem do filtro de `assets` de cada consumidor.
     */
    private function aplicarRecorteManutencao($assets, $business_id)
    {
        return $assets->whereIn('assets.id', DB::table('asset_maintenances')
            ->where('business_id', $business_id)
            ->whereIn('status', ['new', 'in_progress'])
            ->select('asset_id'));
    }

    private function contarRecorteGarantia($business_id): int
    {
        return $this->contarRecorte($business_id, fn ($q) => $this->aplicarRecorteGarantia($q, $business_id));
    }

    /**
     * Contagem de um recorte sobre o CONJUNTO do business — independe dos filtros escolhidos
     * (como o `n` do prototipo), mas respeita `permitted_locations()`.
     */
    private function contarRecorte($business_id, callable $aplicar): int
    {
        $assets = Asset::where('assets.business_id', $business_id);

        $permitted_locations = auth()->user()->permitted_locations();
        if ($permitted_locations != 'all') {
            $assets->whereIn('assets.location_id', $permitted_locations);
        }

        return $aplicar($assets)->count();
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

        $recorte = $this->recorteAtivo($request);
        if ($recorte === 'garantia') {
            $this->aplicarRecorteGarantia($assets, $business_id);
        } elseif ($recorte === 'manutencao') {
            $this->aplicarRecorteManutencao($assets, $business_id);
        }

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

    private function bemSelecionado(Request $request): ?int
    {
        $id = (int) $request->input('bem', 0);

        return $id > 0 ? $id : null;
    }

    /**
     * Detalhe de UM bem pro drawer de leitura: identificacao + alocacoes, cada uma com a
     * lista das suas devolucoes (1 : N — devolucao parcial gera varias linhas `revoke`
     * com o mesmo `parent_id`; o prototipo modela 1 : 1, ver `_saida-16.md`).
     *
     * Tier 0 (ADR 0093): o bem, as alocacoes E as devolucoes filtram `business_id` cada
     * uma na PROPRIA linha. A soma de devolucoes da tela Alocacoes (`leftJoin ... as PT`)
     * nao filtra `PT.business_id` — residuo declarado no charter dela. Aqui a devolucao
     * e escopada nela mesma, entao revoke de outra empresa apontando pra alocacao desta
     * nao aparece nem entra no total devolvido. Bem de outra empresa, ou fora de
     * `permitted_locations()`, devolve `null` (o drawer mostra "nao encontrado").
     *
     * Leitura pura: nenhuma quantidade e recalculada fora do que a lista mostra —
     * `devolvido` e a soma das devolucoes LISTADAS, nada mais.
     */
    private function buildBemDetalhe(int $id, int $business_id): ?array
    {
        $bem = Asset::leftJoin('categories as CAT', 'assets.category_id', '=', 'CAT.id')
            ->leftJoin('business_locations as BL', 'assets.location_id', '=', 'BL.id')
            ->where('assets.business_id', $business_id)
            ->where('assets.id', $id)
            ->select('assets.*', 'CAT.name as categoria', 'BL.name as local')
            ->first();

        $permitidos = auth()->user()->permitted_locations();
        if (! $bem || ($permitidos != 'all' && ! in_array($bem->location_id, (array) $permitidos))) {
            return null;
        }

        $nome = fn (string $t) => DB::raw("TRIM(CONCAT(COALESCE($t.surname, ''),' ',COALESCE($t.first_name, ''),' ',COALESCE($t.last_name,''))) as {$t}_nome");

        $alocacoes = DB::table('asset_transactions as AL')
            ->leftJoin('users as receiver', 'AL.receiver', '=', 'receiver.id')
            ->leftJoin('users as provider', 'AL.created_by', '=', 'provider.id')
            ->where('AL.business_id', $business_id)
            ->where('AL.asset_id', $bem->id)
            ->where('AL.transaction_type', 'allocate')
            ->orderByDesc('AL.transaction_datetime')
            ->select('AL.id', 'AL.ref_no', 'AL.quantity', 'AL.transaction_datetime', 'AL.allocated_upto',
                'AL.reason', $nome('receiver'), $nome('provider'))
            ->get();

        $devolucoes = DB::table('asset_transactions as RV')
            ->leftJoin('users as provider', 'RV.created_by', '=', 'provider.id')
            ->where('RV.business_id', $business_id)
            ->where('RV.transaction_type', 'revoke')
            ->whereIn('RV.parent_id', $alocacoes->pluck('id')->all() ?: [0])
            ->orderBy('RV.transaction_datetime')
            ->select('RV.id', 'RV.parent_id', 'RV.ref_no', 'RV.quantity', 'RV.transaction_datetime',
                'RV.reason', $nome('provider'))
            ->get()
            ->groupBy('parent_id');

        return [
            'id' => $bem->id,
            'asset_code' => $bem->asset_code,
            'nome' => $bem->name,
            'modelo' => $bem->model,
            'serie' => $bem->serial_no,
            'categoria' => $bem->categoria,
            'local' => $bem->local,
            'tipo_compra' => $bem->purchase_type,
            'compra_em' => $bem->purchase_date ? $this->commonUtil->format_date($bem->purchase_date) : null,
            'alocavel' => (bool) $bem->is_allocatable,
            'quantidade' => (float) $bem->quantity,
            'valor_unitario' => (float) $bem->unit_price,
            'descricao' => $bem->description,
            'alocacoes' => $alocacoes->map(function ($a) use ($devolucoes) {
                $filhas = $devolucoes->get($a->id, collect());

                return [
                    'id' => $a->id,
                    'ref_no' => $a->ref_no,
                    'para' => $a->receiver_nome,
                    'por' => $a->provider_nome,
                    'quantidade' => (float) $a->quantity,
                    'em' => $this->commonUtil->format_date($a->transaction_datetime, true),
                    'ate' => $a->allocated_upto ? $this->commonUtil->format_date($a->allocated_upto) : null,
                    'motivo' => $a->reason,
                    'devolvido' => (float) $filhas->sum('quantity'),
                    'devolucoes' => $filhas->map(fn ($r) => [
                        'id' => $r->id,
                        'ref_no' => $r->ref_no,
                        'quantidade' => (float) $r->quantity,
                        'em' => $this->commonUtil->format_date($r->transaction_datetime, true),
                        'por' => $r->provider_nome,
                        'motivo' => $r->reason,
                    ])->values()->all(),
                ];
            })->values()->all(),
        ];
    }

    /**
     * Cadastro de bem: a tela de Bens com o drawer "Adicionar recurso" aberto.
     *
     * Ate 2026-09-30 devolvia o fragmento de modal `asset.create` so sob `ajax()` — e o cliente
     * Inertia manda `X-Requested-With` SEMPRE, entao o ramo nem pode conviver com a tela React:
     * uma visita Inertia cairia nele. O drawer (`_shared/CadastroBemDrawer`) ja posta no `store()`.
     *
     * @return \Inertia\Response
     */
    public function create(Request $request)
    {
        // `asset.view`: a Page carrega a lista de bens — quem nao ve a lista nao entra por aqui.
        if (! auth()->user()->can('asset.create') || ! auth()->user()->can('asset.view')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        if (! (auth()->user()->can('superadmin') || ($this->moduleUtil->hasThePermissionInSubscription($business_id, 'assetmanagement_module')))) {
            abort(403, 'Unauthorized action.');
        }

        return $this->renderBens($request, (int) $business_id, ['abrir_cadastro' => true]);
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
     * Edicao de bem: a tela de Bens com o drawer aberto em modo editar (thread 17, [W] 2026-09-30).
     *
     * Mesmo motivo do `create()`: o fragmento `asset.edit` so respondia sob `ajax()`, e toda
     * visita Inertia e ajax. O bem entra na prop `edicao` ja escopado por business (Tier 0) —
     * id de outra empresa da 404, como antes. O `update()` e o mesmo de sempre.
     *
     * @param  int  $id
     * @return \Inertia\Response
     */
    public function edit(Request $request, $id)
    {
        if (! auth()->user()->can('asset.update') || ! auth()->user()->can('asset.view')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = (int) request()->session()->get('user.business_id');

        if (! (auth()->user()->can('superadmin') || ($this->moduleUtil->hasThePermissionInSubscription($business_id, 'assetmanagement_module')))) {
            abort(403, 'Unauthorized action.');
        }

        $asset = Asset::where('business_id', $business_id)->findOrFail($id);

        return $this->renderBens($request, $business_id, ['edicao' => $this->buildEdicaoPayload($asset)]);
    }

    /**
     * O bem gravado no formato que o drawer preenche (`cadastroBem.ts::BemEdicao`).
     *
     * Numeros saem como NUMERO (nao string formatada): quem os formata pro envio e o
     * `paraNumUf` do front, o mesmo do cadastro — a REGRA MESTRE fica num caminho so.
     * Garantias da mais recente pra mais antiga: o drawer edita a primeira e reenvia as outras
     * intactas, porque o `AssetService::atualizar` apaga a que nao vier no envio.
     */
    private function buildEdicaoPayload(Asset $asset): array
    {
        // Pelo METODO da relacao, nao pelo atributo magico: o `Asset` nao declara `@property
        // $warranties` e o PHPStan (ratchet) reprova o acesso. O serviço ja usa `warranties()`.
        $garantias = $asset->warranties()
            ->orderByDesc('start_date')
            ->get()
            ->map(fn ($w) => [
                'id' => (int) $w->id,
                'inicio' => \Carbon::parse($w->start_date)->format('Y-m-d'),
                'meses' => (int) round(\Carbon::parse($w->start_date)->diffInMonths(\Carbon::parse($w->end_date))),
                'custo' => (float) $w->additional_cost,
                'nota' => $w->additional_note,
            ])
            ->all();

        return [
            'id' => (int) $asset->id,
            'asset_code' => (string) $asset->asset_code,
            'form' => [
                'nome' => (string) $asset->name,
                'categoriaId' => $asset->category_id ? (string) $asset->category_id : '',
                'localId' => $asset->location_id ? (string) $asset->location_id : '',
                'modelo' => (string) ($asset->model ?? ''),
                'serie' => (string) ($asset->serial_no ?? ''),
                'compraEm' => $asset->purchase_date ? \Carbon::parse($asset->purchase_date)->format('Y-m-d') : '',
                'tipoCompra' => (string) ($asset->purchase_type ?: 'owned'),
                'valorUnitario' => (float) $asset->unit_price,
                'quantidade' => (float) $asset->quantity,
                'depreciacao' => $asset->depreciation === null ? null : (float) $asset->depreciation,
                'alocavel' => (bool) $asset->is_allocatable,
                'descricao' => (string) ($asset->description ?? ''),
            ],
            'garantias' => $garantias,
        ];
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

    /**
     * Painel do Patrimonio — Inertia (MWART F1, ADR 0104 · endereco ADR 0394).
     *
     * Ate 2026-09-08 devolvia `view('assetmanagement::asset.dashboard')` com 3 cards e 2
     * tabelas. As agregacoes sao DEFERIDAS (RUNBOOK-inertia-defer-pattern): so `is_admin`,
     * permissoes e o carimbo de hora vao no 1o request.
     *
     * Tier 0 (ADR 0093): `asset_warranties` NAO tem `business_id` — toda leitura de garantia
     * entra por join com `assets` filtrando `assets.business_id`. As duas consultas do ramo
     * nao-admin filtravam so `receiver`; reescritas aqui, nascem com `business_id`.
     *
     * Dois numeros do prototipo NAO renderizam, e devolvem `null` (o front mostra "—"):
     * valor residual (depreciacao nunca e calculada — RESIDUO 6, decisao [W]) e custo de
     * manutencao (`asset_maintenances` nao tem coluna de valor — RESIDUO 3). Ver
     * memory/requisitos/AssetManagement/RUNBOOK-patrimonio-index.md §3.
     */
    public function dashboard()
    {
        $business_id = request()->session()->get('user.business_id');
        $user_id = auth()->user()->id;
        $is_admin = $this->commonUtil->is_admin(auth()->user());

        return Inertia::render('Patrimonio/Index', [
            // Contador da aba "Manutencoes" (pill do PageHeaderTabs). DEFERIDO: o
            // CLAUDE.md manda `Inertia::defer` em toda prop que faz query, e o pill nao
            // participa do first paint. Cobre `asset-maintenance` e mais nada -- dos 5
            // contadores do prototipo, so este tem numero AUDITADO hoje: Garantias e
            // Auditoria nao tem aba (D-GARANTIAS/D-AUDITORIA) e Alocacoes depende de
            // `allocated_qty`/`revoked_qty`, que o `baseAssetsQuery()` declara nao serem
            // numeros auditados enquanto o residuo Tier 0 dela nao fechar.
            'abas_contadores' => Inertia::defer(fn () => [
                'asset-maintenance' => app(AssetMaintenanceService::class)->contarAbertas($business_id),
            ]),
            'is_admin' => $is_admin,
            'pode' => [
                'ver' => auth()->user()->can('asset.view'),
                'criar' => auth()->user()->can('asset.create'),
            ],
            'apurado_em' => now()->toIso8601String(),

            'kpis' => Inertia::defer(fn () => $this->painelKpis($business_id, $is_admin)),
            'porCategoria' => Inertia::defer(fn () => $this->painelPorCategoria($business_id, $is_admin)),
            'garantia' => Inertia::defer(fn () => $this->painelGarantia($business_id, $is_admin)),
            'manutencoes' => Inertia::defer(fn () => $this->painelManutencoes($business_id, $is_admin)),
            'meusBens' => Inertia::defer(fn () => $this->painelMeusBens($business_id, $user_id)),
        ]);
    }

    /** Os 4 KPIs do topo. `valorResidual` e null DE PROPOSITO — ver docblock do dashboard(). */
    private function painelKpis($business_id, $is_admin)
    {
        if (! $is_admin) {
            return null;
        }

        $bens = Asset::where('business_id', $business_id)
            ->selectRaw('COUNT(*) as total_bens')
            ->selectRaw('COALESCE(SUM(quantity), 0) as unidades')
            ->selectRaw('COALESCE(SUM(quantity * unit_price), 0) as bruto')
            ->selectRaw('COALESCE(SUM(CASE WHEN is_allocatable = 1 THEN quantity ELSE 0 END), 0) as alocaveis')
            ->toBase()
            ->first();

        // allocate menos revoke — a MESMA conta do card legado. `quantity` e decimal(22,4)
        // e o card soma QUANTIDADE (nao conta registros): nao arredondar pra inteiro.
        $mov = AssetTransaction::where('business_id', $business_id)
            ->selectRaw("COALESCE(SUM(IF(transaction_type='allocate', quantity, 0)), 0) as alocado")
            ->selectRaw("COALESCE(SUM(IF(transaction_type='revoke', quantity, 0)), 0) as revogado")
            ->toBase()
            ->first();

        return [
            'bruto' => (float) $bens->bruto,
            'valorResidual' => null,
            'unidades' => (float) $bens->unidades,
            'totalBens' => (int) $bens->total_bens,
            'alocados' => (float) $mov->alocado - (float) $mov->revogado,
            'alocaveis' => (float) $bens->alocaveis,
            'garantiaCritica' => $this->contaGarantiaCritica($business_id),
        ];
    }

    /** Bens cuja garantia MAIS RECENTE esta vencida ou vence em ate 30 dias — o mesmo recorte de Bens. */
    private function contaGarantiaCritica($business_id)
    {
        return $this->aplicarRecorteGarantia(Asset::where('assets.business_id', $business_id), $business_id)
            ->count();
    }

    /** Patrimonio por categoria — SUM(quantity * unit_price) agrupado. */
    private function painelPorCategoria($business_id, $is_admin)
    {
        if (! $is_admin) {
            return null;
        }

        return Asset::where('assets.business_id', $business_id)
            ->leftJoin('categories as cat', 'assets.category_id', '=', 'cat.id')
            ->selectRaw('COALESCE(cat.name, ?) as categoria', [__('lang_v1.none')])
            ->selectRaw('COALESCE(SUM(assets.quantity), 0) as unidades')
            ->selectRaw('COALESCE(SUM(assets.quantity * assets.unit_price), 0) as valor')
            ->groupBy('cat.id', 'cat.name')
            ->orderByDesc('valor')
            ->toBase()
            ->get()
            ->map(fn ($r) => [
                'categoria' => $r->categoria,
                'unidades' => (float) $r->unidades,
                'valor' => (float) $r->valor,
            ]);
    }

    /**
     * Situacao da garantia em 4 baldes (charter R3): na garantia · vence em ate 30d ·
     * vencida · SEM REGISTRO. "Sem registro" nao e "vencida" — e a distincao que o
     * charter fixa e que o card legado nao fazia.
     */
    private function painelGarantia($business_id, $is_admin)
    {
        if (! $is_admin) {
            return null;
        }

        // Uma linha por bem (a garantia mais recente): sem isso o SUM de valor contava o bem
        // uma vez por garantia registrada.
        $baldes = Asset::where('assets.business_id', $business_id)
            ->leftJoinSub($this->ultimaGarantiaPorBem($business_id), 'aw', 'aw.asset_id', '=', 'assets.id')
            ->selectRaw("CASE
                WHEN aw.fim IS NULL THEN 'sem'
                WHEN DATEDIFF(aw.fim, CURDATE()) < 0 THEN 'vencida'
                WHEN DATEDIFF(aw.fim, CURDATE()) <= 30 THEN 'vencendo'
                ELSE 'vigente' END as balde")
            ->selectRaw('COUNT(DISTINCT assets.id) as bens')
            ->selectRaw('COALESCE(SUM(assets.quantity * assets.unit_price), 0) as valor')
            ->groupBy('balde')
            ->toBase()
            ->get()
            ->keyBy('balde');

        return collect(['vigente', 'vencendo', 'vencida', 'sem'])->map(fn ($k) => [
            'balde' => $k,
            'bens' => (int) ($baldes[$k]->bens ?? 0),
            'valor' => (float) ($baldes[$k]->valor ?? 0),
        ]);
    }

    /**
     * Manutencao em aberto. `custo` e null DE PROPOSITO: `asset_maintenances` nao tem
     * coluna de valor (o `additional_cost` mora em asset_warranties e e outra coisa).
     */
    private function painelManutencoes($business_id, $is_admin)
    {
        if (! $is_admin) {
            return null;
        }

        return AssetMaintenance::where('asset_maintenances.business_id', $business_id)
            ->whereNotIn('asset_maintenances.status', ['completed', 'cancelled'])
            ->leftJoin('assets', 'assets.id', '=', 'asset_maintenances.asset_id')
            ->select('asset_maintenances.id', 'asset_maintenances.status',
                'asset_maintenances.created_at', 'assets.name as bem', 'assets.asset_code')
            ->orderBy('asset_maintenances.created_at')
            ->limit(20)
            ->toBase()
            ->get()
            ->map(fn ($m) => [
                'id' => $m->id,
                'bem' => $m->bem,
                'codigo' => $m->asset_code,
                'status' => $m->status,
                'abertaEm' => optional($m->created_at)->toDateString(),
                'custo' => null,
            ]);
    }

    /** Ramo nao-admin: o que ESTE usuario tem alocado. Nascido com o filtro de tenant. */
    private function painelMeusBens($business_id, $user_id)
    {
        $totais = AssetTransaction::where('business_id', $business_id)
            ->where('receiver', $user_id)
            ->selectRaw("COALESCE(SUM(IF(transaction_type='allocate', quantity, 0)), 0) as alocado")
            ->selectRaw("COALESCE(SUM(IF(transaction_type='revoke', quantity, 0)), 0) as revogado")
            ->toBase()
            ->first();

        $porCategoria = AssetTransaction::where('asset_transactions.business_id', $business_id)
            ->where('asset_transactions.receiver', $user_id)
            ->leftJoin('assets as a', 'a.id', '=', 'asset_transactions.asset_id')
            ->leftJoin('categories as cat', 'a.category_id', '=', 'cat.id')
            ->selectRaw('COALESCE(cat.name, ?) as categoria', [__('lang_v1.none')])
            ->selectRaw("COALESCE(SUM(IF(asset_transactions.transaction_type='allocate', asset_transactions.quantity, -asset_transactions.quantity)), 0) as quantidade")
            ->groupBy('cat.id', 'cat.name')
            ->toBase()
            ->get()
            ->map(fn ($r) => ['categoria' => $r->categoria, 'quantidade' => (float) $r->quantidade])
            ->filter(fn ($r) => $r['quantidade'] > 0)
            ->values();

        return [
            'alocado' => (float) $totais->alocado - (float) $totais->revogado,
            'porCategoria' => $porCategoria,
        ];
    }
}
