<?php

namespace Modules\AssetManagement\Http\Controllers;

use App\User;
use App\Utils\ModuleUtil;
use App\Utils\Util;
use DB;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Modules\AssetManagement\Entities\Asset;
use Modules\AssetManagement\Entities\AssetTransaction;
use Modules\AssetManagement\Services\AssetAllocationService;
use Modules\AssetManagement\Utils\AssetUtil;
use Yajra\DataTables\Facades\DataTables;

/**
 * Wave 16 governance D4 Architecture: Controller magro — regras de
 * persistencia delegadas a AssetAllocationService.
 */
class AssetAllocationController extends Controller
{
    /**
     * All Utils instance.
     */
    protected $moduleUtil;

    protected $commonUtil;

    protected $assetUtil;

    protected $allocationService;

    /**
     * Constructor — DI Service + Utils legacy mantidos.
     */
    public function __construct(
        ModuleUtil $moduleUtil,
        Util $commonUtil,
        AssetUtil $assetUtil,
        AssetAllocationService $allocationService,
    ) {
        $this->moduleUtil = $moduleUtil;
        $this->commonUtil = $commonUtil;
        $this->assetUtil = $assetUtil;
        $this->allocationService = $allocationService;
    }

    /**
     * Display a listing of the resource.
     *
     * O `@return` deixou de ser `Response` em 2026-09-08: o metodo passou a ter DOIS retornos
     * reais e o docblock antigo nao descrevia nenhum dos dois. O ramo `$request->ajax()`
     * devolve o JSON do DataTables e o caminho da tela devolve `Inertia\Response`. Nao e
     * detalhe de estilo — foi o que o PHPStan pegou na tela irma (Bens) apos a mesma migracao.
     *
     * @return \Inertia\Response|\Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $business_id = request()->session()->get('user.business_id');

        if (! (auth()->user()->can('superadmin') || ($this->moduleUtil->hasThePermissionInSubscription($business_id, 'assetmanagement_module')))) {
            abort(403, 'Unauthorized action.');
        }

        if ($request->ajax()) {
            $asset_allocated = $this->baseAllocationsQuery($business_id);

            return Datatables::of($asset_allocated)
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

                    if ($row->revoked_quantity != $row->quantity) {
                        $html .= '<li>
                                    <a data-href="'.action([\Modules\AssetManagement\Http\Controllers\AssetAllocationController::class, 'edit'], [$row->id]).'" class="cursor-pointer edit_allocated_asset">
                                        <i class="fa fa-edit"></i>
                                            '.__('messages.edit').'
                                        </a>
                                    </li>';
                    }

                    $html .= '<li>
                                <a data-href="'.action([\Modules\AssetManagement\Http\Controllers\AssetAllocationController::class, 'destroy'], [$row->id]).'"  id="delete_allocated_asset" class="cursor-pointer">
                                    <i class="fas fa-trash"></i>
                                    '.__('messages.delete').'
                                </a>
                            </li>';

                    if ($row->revoked_quantity != $row->quantity) {
                        $html .= '<li>
                                <a data-href="'.action([\Modules\AssetManagement\Http\Controllers\RevokeAllocatedAssetController::class, 'create'], ['id' => $row->id]).'" class="cursor-pointer revoke_allocated_asset">
                                    <i class="fas fa-history"></i>
                                    '.__('assetmanagement::lang.revoke').'
                                </a>
                            </li>';
                    }

                    $html .= '</ul>
                            </div>';

                    return $html;
                })
                ->editColumn('allocated_at', '
                    @if(!empty($allocated_at))
                        {{@format_datetime($allocated_at)}}
                    @endif
                ')
                ->editColumn('allocated_upto', '
                    @if(!empty($allocated_upto))
                        {{@format_date($allocated_upto)}}
                    @endif
                ')
                ->editColumn('quantity', '
                    @if(!empty($quantity))
                        {{@format_quantity($quantity)}}
                    @endif
                ')
                ->editColumn('revoked_quantity', '
                    @if(!empty($revoked_quantity))
                        {{@format_quantity($revoked_quantity)}}
                    @endif
                ')
                ->removeColumn('id')
                ->rawColumns(['action', 'allocated_at', 'quantity', 'revoked_quantity'])
                ->make(true);
        }

        return Inertia::render('Patrimonio/Alocacoes', [
            'filtros' => [
                'q' => $request->input('q'),
                'situacao' => $this->situacaoFiltro($request),
            ],
            'permissoes' => [
                // Este controller NAO tem guarda `asset.*` em metodo nenhum — so o gate de
                // assinatura acima. A assimetria com o `AssetController::index()` (que ganhou
                // `asset.view` na thread 03) esta declarada no §9 do RUNBOOK: consertar aqui
                // mudaria QUEM enxerga a tela, e isso e decisao [W], nao conserto silencioso.
                // Enquanto isso, o que a tela desenha segue o mesmo criterio do Blade legado.
                'alocar' => true,
                'editar' => true,
                'excluir' => true,
                'devolver' => true,
            ],
            // Inertia::defer — a prop cara da tela (4 join + agregacao + groupBy + paginate).
            // Regra default do projeto pra prop com paginate/subquery:
            // RUNBOOK-inertia-defer-pattern.md
            'alocacoes' => Inertia::defer(fn () => $this->buildAlocacoesPayload($request, $business_id)),
        ]);
    }

    /**
     * Query base da listagem de alocacoes — UM dono so, lido pelos DOIS ramos do index()
     * (DataTables legado e Inertia). Antes deste PR a expressao existia uma vez, inline no
     * ramo ajax; extrai-la evita que a proxima correcao pouse em so um dos caminhos.
     *
     * ⚠️ RESIDUO Tier 0 PRESERVADO BYTE-A-BYTE: o leftJoin de `asset_transactions as PT`
     * NAO filtra `PT.business_id`, entao `revoked_quantity` agrega devolucao de qualquer
     * empresa quando a pre-condicao existe (linha filha com parent de outro tenant). E o
     * gemeo catalogado em `_saida-01.md §9(a)`, com thread dona. Nao e corrigido aqui por
     * duas leis que caem juntas: mexer em quantidade e REGRA MESTRE (prova por dois caminhos
     * + antes→depois pro [W]) e 1 PR = 1 intent. Ver §9 do RUNBOOK-alocacoes.md.
     */
    private function baseAllocationsQuery($business_id)
    {
        return AssetTransaction::join('assets',
                            'asset_transactions.asset_id', '=', 'assets.id')
                            ->join('users as receiver', 'asset_transactions.receiver', '=', 'receiver.id')
                            ->join('users as provider', 'asset_transactions.created_by', '=', 'provider.id')
                            ->leftJoin('categories as CAT', 'assets.category_id',
                                '=', 'CAT.id')
                            ->leftJoin('asset_transactions as PT',
                            'asset_transactions.id', '=', 'PT.parent_id')
                            ->where('asset_transactions.business_id', $business_id)
                            ->where('asset_transactions.transaction_type', 'allocate')
                            ->select('asset_transactions.ref_no as ref_no',
                            'asset_transactions.quantity as quantity',
                            'asset_transactions.transaction_datetime as allocated_at', 'asset_transactions.id as id',
                            'assets.name as asset', 'assets.model as model',
                            'CAT.name as category', DB::raw("CONCAT(COALESCE(receiver.surname, ''),' ',COALESCE(receiver.first_name, ''),' ',COALESCE(receiver.last_name,'')) as receiver_name"),
                            DB::raw("CONCAT(COALESCE(provider.surname, ''),' ',COALESCE(provider.first_name, ''),' ',COALESCE(provider.last_name,'')) as provider_name"),
                            DB::raw('SUM(COALESCE(PT.quantity, 0)) as revoked_quantity'),
                            'asset_transactions.reason as reason',
                            'asset_transactions.allocated_upto'
                            )
                            ->groupBy('asset_transactions.id');
    }

    /**
     * Normaliza o recorte de situacao. Whitelist: valor fora dela vira o default, entao a
     * query string nao escolhe SQL.
     */
    private function situacaoFiltro(Request $request): string
    {
        $situacao = (string) $request->input('situacao', 'ativas');

        return in_array($situacao, ['ativas', 'devolvidas', 'todas'], true) ? $situacao : 'ativas';
    }

    /**
     * Payload da listagem — o que a tela recebe no partial reload.
     */
    private function buildAlocacoesPayload(Request $request, $business_id)
    {
        $alocacoes = $this->baseAllocationsQuery($business_id);

        // Busca — os campos que identificam a LINHA: o codigo da alocacao, o bem (nome e
        // modelo) e quem recebeu. Server-side de proposito: busca no cliente so enxerga as
        // 25 linhas que ja chegaram, entao parece funcionar com pouco dado e mente com muito.
        $q = trim((string) $request->input('q', ''));
        if ($q !== '') {
            $alocacoes->where(function ($query) use ($q) {
                $termo = '%'.$q.'%';
                $query->where('asset_transactions.ref_no', 'like', $termo)
                    ->orWhere('assets.name', 'like', $termo)
                    ->orWhere('assets.model', 'like', $termo)
                    ->orWhere('receiver.first_name', 'like', $termo)
                    ->orWhere('receiver.last_name', 'like', $termo)
                    ->orWhere('receiver.surname', 'like', $termo);
            });
        }

        // Recorte por situacao no SERVIDOR. Sobre a agregacao (`HAVING`, nao `WHERE`),
        // porque `revoked_quantity` e SUM de linhas filhas. Recortar no cliente faria a
        // aba mentir: ela julgaria 25 linhas de N.
        $situacao = $this->situacaoFiltro($request);
        if ($situacao === 'ativas') {
            $alocacoes->havingRaw('SUM(COALESCE(PT.quantity, 0)) < asset_transactions.quantity');
        } elseif ($situacao === 'devolvidas') {
            $alocacoes->havingRaw('SUM(COALESCE(PT.quantity, 0)) >= asset_transactions.quantity');
        }

        // Mesmo default do Blade legado (`aaSorting:[[7,'desc']]` = allocated_at desc): a
        // alocacao mais recente e a que se procura.
        $alocacoes->orderBy('asset_transactions.transaction_datetime', 'desc');

        $hoje = \Carbon::now()->startOfDay();

        return $alocacoes->paginate(25)->withQueryString()->through(function ($row) use ($hoje) {
            $quantidade = (float) $row->quantity;
            // Herda o residuo Tier 0 do §9 do RUNBOOK — NAO e numero auditado.
            $devolvido = (float) $row->revoked_quantity;

            $prazo = $row->allocated_upto ? \Carbon::parse($row->allocated_upto)->startOfDay() : null;

            // "vencido" e decidido AQUI, com o relogio do servidor. Derivar no browser faria
            // o veredito depender do fuso da maquina de quem olha.
            $vencido = $prazo !== null && $devolvido < $quantidade && $prazo->lt($hoje);

            if ($devolvido >= $quantidade) {
                $situacaoLinha = 'devolvida';
            } elseif ($devolvido > 0) {
                $situacaoLinha = 'parcial';
            } else {
                $situacaoLinha = 'em_uso';
            }

            return [
                'id' => $row->id,
                'ref_no' => $row->ref_no,
                'bem' => $row->asset,
                'modelo' => $row->model,
                'categoria' => $row->category,
                'recebido_por' => trim((string) $row->receiver_name),
                'alocado_por' => trim((string) $row->provider_name),
                'quantidade' => $quantidade,
                'devolvido' => $devolvido,
                'alocado_em' => $row->allocated_at
                    ? $this->commonUtil->format_date($row->allocated_at, true)
                    : null,
                'prazo' => $row->allocated_upto
                    ? $this->commonUtil->format_date($row->allocated_upto)
                    : null,
                'motivo' => $row->reason,
                'situacao' => $situacaoLinha,
                'vencido' => $vencido,
            ];
        });
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        $business_id = request()->session()->get('user.business_id');

        if (! (auth()->user()->can('superadmin') || ($this->moduleUtil->hasThePermissionInSubscription($business_id, 'assetmanagement_module')))) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            $users = User::forDropdown($business_id, false);
            $assets = Asset::forDropdown($business_id, true, false);
            $asset_id = request()->get('asset_id', null);

            return view('assetmanagement::asset_allocation.create')
                ->with(compact('users', 'assets', 'asset_id'));
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

        if (! (auth()->user()->can('superadmin') || ($this->moduleUtil->hasThePermissionInSubscription($business_id, 'assetmanagement_module')))) {
            abort(403, 'Unauthorized action.');
        }

        try {
            // Wave 16 D4 — criacao delegada a AssetAllocationService.
            $this->allocationService->criar(
                $request,
                (int) $business_id,
                (int) request()->session()->get('user.id')
            );

            return redirect()
                ->action([\Modules\AssetManagement\Http\Controllers\AssetAllocationController::class, 'index'])
                ->with('status', ['success' => true,
                    'msg' => __('lang_v1.success'), ]);
        } catch (\Exception $e) {
            DB::rollBack();

            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.app(\App\Support\Privacy\PiiRedactor::class)->redact($e->getMessage()));

            return redirect()->back()
                ->with('status', ['success' => false,
                    'msg' => __('messages.something_went_wrong'),
                ]);
        }
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

        if (! (auth()->user()->can('superadmin') || ($this->moduleUtil->hasThePermissionInSubscription($business_id, 'assetmanagement_module')))) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            $asset_allocated = AssetTransaction::with('asset', 'revokeTransaction')
                                ->where('business_id', $business_id)
                                ->findOrfail($id);

            $users = User::forDropdown($business_id, false);
            $assets = Asset::forDropdown($business_id, true, false);
            // Wave 16 D4 — calculo de disponivel via Service.
            $total_available_asset = $this->allocationService->quantidadeDisponivel($asset_allocated);

            return view('assetmanagement::asset_allocation.edit')
                ->with(compact('users', 'assets', 'asset_allocated',
                'total_available_asset'));
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

        if (! (auth()->user()->can('superadmin') || ($this->moduleUtil->hasThePermissionInSubscription($business_id, 'assetmanagement_module')))) {
            abort(403, 'Unauthorized action.');
        }

        try {
            // Wave 16 D4 — atualizacao delegada a AssetAllocationService.
            $this->allocationService->atualizar($request, (int) $id, (int) $business_id);

            return redirect()
                ->action([\Modules\AssetManagement\Http\Controllers\AssetAllocationController::class, 'index'])
                ->with('status', ['success' => true,
                    'msg' => __('lang_v1.success'), ]);
        } catch (\Exception $e) {
            DB::rollBack();

            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.app(\App\Support\Privacy\PiiRedactor::class)->redact($e->getMessage()));

            return redirect()->back()
                ->with('status', ['success' => false,
                    'msg' => __('messages.something_went_wrong'),
                ]);
        }
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

        if (! (auth()->user()->can('superadmin') || ($this->moduleUtil->hasThePermissionInSubscription($business_id, 'assetmanagement_module')))) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            try {
                // Wave 16 D4 — remocao delegada a AssetAllocationService.
                $this->allocationService->remover((int) $id, (int) $business_id);
                $output = ['success' => true, 'msg' => __('lang_v1.success')];
            } catch (\Exception $e) {
                \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.app(\App\Support\Privacy\PiiRedactor::class)->redact($e->getMessage()));
                $output = ['success' => false, 'msg' => __('messages.something_went_wrong')];
            }

            return $output;
        }
    }
}
