<?php

namespace Modules\Essentials\Http\Controllers;

use App\Utils\ModuleUtil;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Modules\Essentials\Entities\EssentialsLeave;
use Modules\Essentials\Entities\EssentialsLeaveType;
use Yajra\DataTables\Facades\DataTables;

class EssentialsLeaveTypeController extends Controller
{
    /**
     * All Utils instance.
     */
    protected $moduleUtil;

    /**
     * Constructor
     *
     * @param  ProductUtils  $product
     * @return void
     */
    public function __construct(ModuleUtil $moduleUtil)
    {
        $this->moduleUtil = $moduleUtil;
    }

    /**
     * Display a listing of the resource.
     *
     * DOIS caminhos, e é por isso que não há `@return` declarado aqui: com
     * `request()->ajax()` devolve o payload do DataTables (Blade legado, coexistência
     * F5); no caminho normal devolve `Inertia\Response`. A anotação `@return Response`
     * que existia era do `Illuminate\Http\Response` e já não descrevia nem o `view()`
     * anterior — com o Inertia ela virou erro de PHPStan, não só imprecisão.
     * O nível 5 (phpstan.neon.dist) não exige tipo de retorno, então a ausência é
     * honesta: união inferida > declaração falsa.
     */
    public function index()
    {
        $business_id = request()->session()->get('user.business_id');

        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'essentials_module'))) {
            abort(403, 'Unauthorized action.');
        }

        if (! auth()->user()->can('essentials.crud_leave_type')) {
            abort(403, 'Unauthorized action.');
        }

        // `! request()->inertia()`: o cliente Inertia v3 manda `X-Requested-With` em TODA
        // visita (@inertiajs/core getHeaders), e é o header que `ajax()` lê. Sem esta
        // perna, o partial reload da prop adiada caía no JSON do DataTables e a lista
        // ficava no skeleton para sempre (medido no staging em 2026-09-23). O DataTables
        // do Blade legado não manda `X-Inertia`, então segue neste ramo.
        if (request()->ajax() && ! request()->inertia()) {
            $leave_types = EssentialsLeaveType::where('business_id', $business_id)
                        ->select(['leave_type', 'max_leave_count', 'id']);

            return Datatables::of($leave_types)
                ->addColumn(
                    'action',
                    '<button data-href="{{action(\'\Modules\Essentials\Http\Controllers\EssentialsLeaveTypeController@edit\', [$id])}}" class="btn btn-xs btn-primary btn-modal" data-container=".view_modal"><i class="glyphicon glyphicon-edit"></i> @lang("messages.edit")</button>'
                )
                ->removeColumn('id')
                ->rawColumns([2])
                ->make(false);
        }

        // MWART F3 — a lista vira Inertia (HRM-O7 PR-9). O ramo `request()->ajax()`
        // acima FICA: o Blade legado (`leave_type/index.blade.php`) ainda o consome,
        // e a coexistência da F5 depende dele. RUNBOOK-tipos.md.
        return Inertia::render('Essentials/Tipos', [
            // Inertia::defer — prop cara (2 queries + agregação). Regra `inertia-defer-default`.
            'tipos' => Inertia::defer(fn () => $this->buildTiposPayload($business_id)),
            'can_manage' => auth()->user()->can('essentials.crud_leave_type'),
        ]);
    }

    /**
     * Tipos do business + quantos pedidos de licença cada um teve NO ANO CORRENTE
     * (coluna "Pedidos no ano" do protótipo).
     *
     * A contagem sai de uma query agrupada em vez de `withCount`, porque `withCount`
     * exigiria uma relação `leaves()` que `EssentialsLeaveType` não tem — criá-la só
     * para isto alargaria a Entity além do que esta tela precisa.
     *
     * Tier 0 (ADR 0093): AS DUAS queries filtram por `business_id`. A de contagem
     * também — senão o total exibido somaria licenças do vizinho num tipo do tenant.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildTiposPayload(int $business_id): array
    {
        $tipos = EssentialsLeaveType::where('business_id', $business_id)
                                ->orderBy('leave_type')
                                ->get(['id', 'leave_type', 'max_leave_count', 'leave_count_interval', 'ferias_clt']);

        $pedidosNoAno = EssentialsLeave::where('business_id', $business_id)
                                ->whereYear('start_date', now()->year)
                                ->selectRaw('essentials_leave_type_id, COUNT(*) as total')
                                ->groupBy('essentials_leave_type_id')
                                ->pluck('total', 'essentials_leave_type_id');

        return $tipos->map(fn ($t) => [
            'id' => (int) $t->id,
            'leave_type' => (string) $t->leave_type,
            'max_leave_count' => $t->max_leave_count !== null ? (int) $t->max_leave_count : null,
            'leave_count_interval' => $t->leave_count_interval,
            'leaves_count' => (int) ($pedidosNoAno[$t->id] ?? 0),
            // US-PONTO-018: licença aprovada deste tipo abate o saldo de férias no Ponto.
            'ferias_clt' => (bool) $t->ferias_clt,
        ])->values()->all();
    }

    /**
     * O formulário de criar mora no painel lateral da lista (Essentials/Tipos).
     *
     * Antes devolvia `view('essentials::create')`, view que não existe no módulo: o botão
     * "Novo tipo" da tela Inertia levava a um erro 500. Link antigo cai na lista, onde o
     * botão abre o painel.
     */
    public function create(): RedirectResponse
    {
        if (! auth()->user()->can('essentials.crud_leave_type')) {
            abort(403, 'Unauthorized action.');
        }

        return redirect('/hrm/leave-type');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(Request $request)
    {
        $business_id = $request->session()->get('user.business_id');

        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'essentials_module'))) {
            abort(403, 'Unauthorized action.');
        }

        if (! auth()->user()->can('essentials.crud_leave_type')) {
            abort(403, 'Unauthorized action.');
        }

        $input = $this->validarTipo($request);
        $input['business_id'] = $business_id;

        try {
            EssentialsLeaveType::create($input);
            $output = ['success' => true,
                'msg' => __('lang_v1.added_success'),
            ];
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => false,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return $output;
    }

    /**
     * Show the specified resource.
     *
     * @return Response
     */
    public function show()
    {
        return view('essentials::show');
    }

    /**
     * O formulário de editar mora no painel lateral da lista, que já tem os dados da linha.
     * O fragmento de modal Blade que vivia aqui abria sem layout quando a tela Inertia
     * navegava para ele.
     */
    public function edit($id): RedirectResponse
    {
        $business_id = request()->session()->get('user.business_id');

        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'essentials_module'))) {
            abort(403, 'Unauthorized action.');
        }

        if (! auth()->user()->can('essentials.crud_leave_type')) {
            abort(403, 'Unauthorized action.');
        }

        return redirect('/hrm/leave-type');
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function update(Request $request, $id)
    {
        $business_id = $request->session()->get('user.business_id');
        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'essentials_module'))) {
            abort(403, 'Unauthorized action.');
        }

        if (! auth()->user()->can('essentials.crud_leave_type')) {
            abort(403, 'Unauthorized action.');
        }

        $input = $this->validarTipo($request);
        $input['business_id'] = $business_id;

        try {

            EssentialsLeaveType::where('business_id', $business_id)
                            ->where('id', $id)
                            ->update($input);

            $output = ['success' => true,
                'msg' => __('lang_v1.updated_success'),
            ];
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => false,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return $output;
    }

    /**
     * Remove the specified resource from storage.
     *
     * Antes deste método o corpo era vazio (nem recebia $id): a rota
     * `DELETE /hrm/leave-type/{id}` do Route::resource existia e devolvia 200 sem
     * apagar nada. Achado A4 do F1 do Cowork (`hrm-page.jsx`, Nota "Tipo de licença
     * não pode ser excluído" — "o cadastro só cresce").
     *
     * Guarda de uso: `essentials_leaves.essentials_leave_type_id` é `integer index`
     * SEM constraint de FK (migration 2019_05_17_175921) — o banco não recusa nada,
     * então apagar um tipo em uso deixaria licença órfã apontando pro nada. A contagem
     * é feita aqui e devolvida no 422 pra dizer QUANTAS licenças travam.
     *
     * @param  int  $id
     */
    public function destroy($id): JsonResponse
    {
        $business_id = request()->session()->get('user.business_id');

        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'essentials_module'))) {
            abort(403, 'Unauthorized action.');
        }

        if (! auth()->user()->can('essentials.crud_leave_type')) {
            abort(403, 'Unauthorized action.');
        }

        // Tier 0 (ADR 0093): o id chega CRU da rota — resolver dentro do business.
        // Tipo de outro tenant é 404, nunca "não travado".
        $leave_type = EssentialsLeaveType::where('business_id', $business_id)
                                        ->find($id);

        if (empty($leave_type)) {
            abort(404);
        }

        $licencas = EssentialsLeave::where('business_id', $business_id)
                                ->where('essentials_leave_type_id', $leave_type->id)
                                ->count();

        if ($licencas > 0) {
            return response()->json([
                'success' => false,
                'msg' => __('essentials::lang.leave_type_em_uso', ['count' => $licencas]),
                'blocked_by' => ['leaves' => $licencas],
            ], 422);
        }

        try {
            $leave_type->delete();

            $output = ['success' => true,
                'msg' => __('lang_v1.deleted_success'),
            ];
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => false,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return response()->json($output);
    }

    /**
     * Campos do tipo, validados. Antes store/update gravavam `$request->only()` cru: nome
     * vazio passava, e limite em branco chegava como '' numa coluna int.
     *
     * Limite vazio vira `null` ("sem limite"), nunca 0 — 0 diria "zero dias permitidos"
     * (charter, Anti-hooks). Sem limite, o intervalo também fica nulo.
     *
     * @return array<string, mixed>
     */
    private function validarTipo(Request $request): array
    {
        $dados = $request->validate([
            'leave_type' => ['required', 'string', 'max:191'],
            'max_leave_count' => ['nullable', 'integer', 'min:1', 'max:366'],
            'leave_count_interval' => ['nullable', 'in:month,year'],
        ], [], [
            'leave_type' => 'nome do tipo',
            'max_leave_count' => 'limite',
            'leave_count_interval' => 'intervalo',
        ]);

        $limite = $dados['max_leave_count'] ?? null;

        return [
            'leave_type' => trim((string) $dados['leave_type']),
            'max_leave_count' => $limite !== null ? (int) $limite : null,
            'leave_count_interval' => $limite !== null ? ($dados['leave_count_interval'] ?? null) : null,
            // US-PONTO-018: o tipo diz por dado se consome o saldo de férias da CLT.
            // Checkbox desmarcado não vem no POST, logo `boolean()` grava `false`.
            'ferias_clt' => $request->boolean('ferias_clt'),
        ];
    }
}
