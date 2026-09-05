<?php

namespace Modules\Essentials\Http\Controllers;

use App\User;
use App\Utils\ModuleUtil;
use DB;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Modules\Essentials\Entities\EssentialsLeave;
use Modules\Essentials\Entities\EssentialsLeaveType;
use Modules\Essentials\Http\Requests\StoreLeaveRequest;
use Modules\Essentials\Notifications\LeaveStatusNotification;
use Modules\Essentials\Notifications\NewLeaveNotification;
use Modules\Essentials\Services\LeaveBalanceService;
use Modules\Essentials\Services\LeaveRequestService;
use Spatie\Activitylog\Models\Activity;
use Yajra\DataTables\Facades\DataTables;

class EssentialsLeaveController extends Controller
{
    /**
     * All Utils instance.
     */
    protected $moduleUtil;

    protected $leave_statuses;

    /**
     * Wave J 2026-05-16: Service thin (D4.a — extrai scope + status map + auth do Controller).
     * Mantém compat: `$this->leave_statuses` segue acessível, agora populado via Service.
     *
     * @see Modules\Essentials\Services\LeaveRequestService
     */
    protected LeaveRequestService $leaveService;

    /**
     * HRM-O6 PR-3: dono único da regra de limite por tipo (achado A3).
     *
     * @see Modules\Essentials\Services\LeaveBalanceService
     */
    protected LeaveBalanceService $leaveBalance;

    /**
     * Constructor
     *
     * @param  ProductUtils  $product
     * @return void
     */
    public function __construct(
        ModuleUtil $moduleUtil,
        ?LeaveRequestService $leaveService = null,
        ?LeaveBalanceService $leaveBalance = null
    ) {
        $this->moduleUtil = $moduleUtil;
        $this->leaveService = $leaveService ?? new LeaveRequestService($moduleUtil);
        $this->leaveBalance = $leaveBalance ?? new LeaveBalanceService;
        $this->leave_statuses = $this->leaveService->statusMap();
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        $business_id = request()->session()->get('user.business_id');

        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'essentials_module'))) {
            abort(403, 'Unauthorized action.');
        }
        $can_crud_all_leave = auth()->user()->can('essentials.crud_all_leave');
        $can_crud_own_leave = auth()->user()->can('essentials.crud_own_leave');

        if (! $can_crud_all_leave && ! $can_crud_own_leave) {
            abort(403, 'Unauthorized action.');
        }

        // HRM-O7 PR-9: o ramo DataTables FICA até as blades saírem (HRM-O8). Ele é
        // consumido hoje por `leave/index.blade.php:93` — medido 2026-09-05, é o
        // ÚNICO consumidor (varredura contada: 12 referências ao controller, e só
        // essa chama o `index` por XHR; as outras são links de menu/notificação).
        //
        // `! request()->inertia()` NÃO é zelo redundante: o cliente Inertia usa axios,
        // que em algumas versões manda `X-Requested-With: XMLHttpRequest` — exatamente
        // o header que `request()->ajax()` lê. Sem esta segunda perna, um partial
        // reload da tela nova cairia no JSON do DataTables em vez do payload Inertia,
        // e o sintoma seria a tela "não atualizar o filtro" sem erro nenhum.
        if (request()->ajax() && ! request()->inertia()) {
            $leaves = EssentialsLeave::where('essentials_leaves.business_id', $business_id)
                        ->join('users as u', 'u.id', '=', 'essentials_leaves.user_id')
                        ->join('essentials_leave_types as lt', 'lt.id', '=', 'essentials_leaves.essentials_leave_type_id')
                        ->select([
                            'essentials_leaves.id',
                            DB::raw("CONCAT(COALESCE(u.surname, ''), ' ', COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) as user"),
                            'lt.leave_type',
                            'start_date',
                            'end_date',
                            'ref_no',
                            'essentials_leaves.status',
                            'essentials_leaves.business_id',
                            'reason',
                            'status_note',
                        ]);

            if (! empty(request()->input('user_id'))) {
                $leaves->where('essentials_leaves.user_id', request()->input('user_id'));
            }

            if (! $can_crud_all_leave && $can_crud_own_leave) {
                $leaves->where('essentials_leaves.user_id', auth()->user()->id);
            }

            if (! empty(request()->input('status'))) {
                $leaves->where('essentials_leaves.status', request()->input('status'));
            }

            if (! empty(request()->input('leave_type'))) {
                $leaves->where('essentials_leaves.essentials_leave_type_id', request()->input('leave_type'));
            }

            if (! empty(request()->start_date) && ! empty(request()->end_date)) {
                $start = request()->start_date;
                $end = request()->end_date;
                $leaves->whereDate('essentials_leaves.start_date', '>=', $start)
                            ->whereDate('essentials_leaves.start_date', '<=', $end);
            }

            return Datatables::of($leaves)
                ->addColumn(
                    'action',
                    function ($row) {
                        $html = '';
                        if (auth()->user()->can('essentials.crud_all_leave')) {
                            $html .= '<button class="btn btn-xs btn-danger delete-leave" data-href="'.action([\Modules\Essentials\Http\Controllers\EssentialsLeaveController::class, 'destroy'], [$row->id]).'"><i class="fa fa-trash"></i> '.__('messages.delete').'</button>';
                        }

                        $html .= '&nbsp;<button class="btn btn-xs btn-info btn-modal" data-container=".view_modal"  data-href="'.action([\Modules\Essentials\Http\Controllers\EssentialsLeaveController::class, 'activity'], [$row->id]).'"><i class="fa fa-edit"></i> '.__('essentials::lang.activity').'</button>';

                        return $html;
                    }
                )
                ->editColumn('start_date', function ($row) {
                    $start_date = \Carbon::parse($row->start_date);
                    $end_date = \Carbon::parse($row->end_date);

                    $diff = $start_date->diffInDays($end_date);
                    $diff += 1;
                    $start_date_formated = $this->moduleUtil->format_date($start_date);
                    $end_date_formated = $this->moduleUtil->format_date($end_date);

                    return $start_date_formated.' - '.$end_date_formated.' ('.$diff.\Str::plural(__('lang_v1.day'), $diff).')';
                })
                ->editColumn('status', function ($row) {
                    $status = '<span class="label '.$this->leave_statuses[$row->status]['class'].'">'
                    .$this->leave_statuses[$row->status]['name'].'</span>';

                    if (auth()->user()->can('essentials.crud_all_leave') || auth()->user()->can('essentials.approve_leave')) {
                        $status = '<a href="#" class="change_status" data-status_note="'.$row->status_note.'" data-leave-id="'.$row->id.'" data-orig-value="'.$row->status.'" data-status-name="'.$this->leave_statuses[$row->status]['name'].'"> '.$status.'</a>';
                    }

                    return $status;
                })
                ->filterColumn('user', function ($query, $keyword) {
                    $query->whereRaw("CONCAT(COALESCE(u.surname, ''), ' ', COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) like ?", ["%{$keyword}%"]);
                })
                ->removeColumn('id')
                ->rawColumns(['action', 'status'])
                ->make(true);
        }
        $podeVerTodos = $can_crud_all_leave || auth()->user()->can('essentials.approve_leave');

        $filtros = [
            'busca'      => request()->string('busca')->toString() ?: null,
            'status'     => request()->string('status')->toString() ?: null,
            'leave_type' => request()->integer('leave_type') ?: null,
            'user_id'    => $podeVerTodos ? (request()->integer('user_id') ?: null) : null,
            'start_date' => request()->string('start_date')->toString() ?: null,
            'end_date'   => request()->string('end_date')->toString() ?: null,
        ];

        // Inertia::defer é DEFAULT pra prop cara (RUNBOOK-inertia-defer-pattern):
        // `licencas` pagina + join, `tipos`/`colaboradores` batem no banco, `kpis` e
        // `saldos` agregam. `filtros` e `permissoes` ficam eager — são UI state e bool.
        return Inertia::render('Essentials/Licencas/Index', [
            'licencas' => Inertia::defer(function () use ($business_id, $filtros) {
                $pagina = $this->queryLicencas($business_id, $filtros)
                    ->orderByDesc('essentials_leaves.start_date')
                    ->paginate(25)
                    ->withQueryString();

                $pagina->getCollection()->transform(fn ($linha) => $this->formatoDaLinha($linha));

                return $pagina;
            }),

            'tipos' => Inertia::defer(fn () => EssentialsLeaveType::where('business_id', $business_id)
                ->orderBy('leave_type')
                ->get(['id', 'leave_type', 'max_leave_count', 'leave_count_interval'])
                ->map(fn ($t) => [
                    'id'       => (int) $t->id,
                    'label'    => (string) $t->leave_type,
                    'limite'   => (int) ($t->max_leave_count ?? 0),
                    'intervalo' => $t->leave_count_interval,
                ])->values()->all()),

            'colaboradores' => Inertia::defer(fn () => $podeVerTodos
                ? collect(User::forDropdown($business_id, false))
                    ->map(fn ($label, $id) => ['id' => (int) $id, 'label' => (string) $label])
                    ->values()->all()
                : []),

            'kpis' => Inertia::defer(fn () => [
                'pendentes'  => (int) $this->queryLicencasSemFiltro($business_id)
                    ->where('essentials_leaves.status', LeaveRequestService::STATUS_PENDING)->count(),
                'aprovadas'  => (int) $this->queryLicencasSemFiltro($business_id)
                    ->where('essentials_leaves.status', LeaveRequestService::STATUS_APPROVED)->count(),
                'tipos'      => (int) EssentialsLeaveType::where('business_id', $business_id)->count(),
            ]),

            'saldos' => Inertia::defer(fn () => $this->saldoPorTipo($business_id)),

            'filtros' => $filtros,

            'permissoes' => [
                'ver_todos' => $podeVerTodos,
                'aprovar'   => (bool) auth()->user()->can('essentials.approve_leave'),
                'excluir'   => (bool) $can_crud_all_leave,
                'criar_para_terceiros' => (bool) $can_crud_all_leave,
            ],

            'situacoes' => collect($this->leave_statuses)
                ->map(fn ($v, $k) => ['valor' => $k, 'label' => $v['name']])
                ->values()->all(),

            'hoje' => now()->format('Y-m-d'),

            // O `store()` grava via `ModuleUtil::uf_date()`, que parseia com o formato
            // do NEGÓCIO (default do schema: m/d/Y) — e o `StoreLeaveRequest` valida
            // chamando o mesmo conversor, de propósito. Um <input type="date"> devolve
            // ISO; postar ISO cru faria o `createFromFormat` lançar, o try/catch do
            // controller engoliria e a tela veria "algo deu errado" sem dizer o quê.
            // Por isso o formato desce e a conversão acontece na borda do POST.
            'date_format' => (string) (session('business.date_format') ?: 'm/d/Y'),
        ]);
    }

    /**
     * Query base das licenças do negócio — Tier 0 (ADR 0093): `business_id` sempre
     * no WHERE, e o recorte por `crud_own_leave` é do SERVIDOR, nunca da UI (R3).
     *
     * @param  array<string, mixed>  $filtros
     */
    private function queryLicencas(int|string $business_id, array $filtros): \Illuminate\Database\Eloquent\Builder
    {
        $query = $this->queryLicencasSemFiltro($business_id);

        if (! empty($filtros['user_id'])) {
            $query->where('essentials_leaves.user_id', $filtros['user_id']);
        }
        if (! empty($filtros['status'])) {
            $query->where('essentials_leaves.status', $filtros['status']);
        }
        if (! empty($filtros['leave_type'])) {
            $query->where('essentials_leaves.essentials_leave_type_id', $filtros['leave_type']);
        }
        if (! empty($filtros['start_date']) && ! empty($filtros['end_date'])) {
            $query->whereDate('essentials_leaves.start_date', '>=', $filtros['start_date'])
                ->whereDate('essentials_leaves.start_date', '<=', $filtros['end_date']);
        }
        if (! empty($filtros['busca'])) {
            $termo = '%'.$filtros['busca'].'%';
            $query->where(function ($q) use ($termo) {
                $q->where('essentials_leaves.ref_no', 'like', $termo)
                    ->orWhere('essentials_leaves.reason', 'like', $termo)
                    ->orWhereHas('leave_type', fn ($lt) => $lt->where('leave_type', 'like', $termo))
                    ->orWhereHas('user', fn ($u) => $u->whereRaw(
                        "CONCAT(COALESCE(surname, ''), ' ', COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) like ?",
                        [$termo]
                    ));
            });
        }

        return $query;
    }

    private function queryLicencasSemFiltro(int|string $business_id): \Illuminate\Database\Eloquent\Builder
    {
        $query = EssentialsLeave::where('essentials_leaves.business_id', $business_id)
            ->with(['leave_type:id,leave_type', 'user:id,surname,first_name,last_name']);

        // R3: quem só tem `crud_own_leave` vê apenas as próprias. O filtro é do
        // controller — a UI apenas esconde o seletor, o que não é defesa.
        if (! auth()->user()->can('essentials.crud_all_leave')
            && auth()->user()->can('essentials.crud_own_leave')) {
            $query->where('essentials_leaves.user_id', auth()->user()->id);
        }

        return $query;
    }

    /**
     * Forma da linha da grade. `days` e o rótulo do período são calculados no
     * SERVIDOR: `format_date` respeita a configuração do negócio (inclusive o
     * shift do ADR 0066), e recalcular no front divergiria do que o Blade mostra.
     *
     * @return array<string, mixed>
     */
    private function formatoDaLinha(EssentialsLeave $linha): array
    {
        $inicio = \Carbon::parse($linha->start_date);
        $fim = \Carbon::parse($linha->end_date);
        $dias = $inicio->diffInDays($fim) + 1; // R6: inclusivo nas duas pontas

        return [
            'id'            => (int) $linha->id,
            'ref_no'        => $linha->ref_no,
            'tipo'          => optional($linha->leave_type)->leave_type,
            'tipo_id'       => (int) $linha->essentials_leave_type_id,
            'colaborador'   => trim(sprintf(
                '%s %s %s',
                optional($linha->user)->surname ?? '',
                optional($linha->user)->first_name ?? '',
                optional($linha->user)->last_name ?? ''
            )),
            'user_id'       => (int) $linha->user_id,
            'start_date'    => $inicio->format('Y-m-d'),
            'end_date'      => $fim->format('Y-m-d'),
            'periodo_label' => $this->moduleUtil->format_date($inicio).' – '.$this->moduleUtil->format_date($fim),
            'dias'          => $dias,
            'motivo'        => $linha->reason,
            'status'        => $linha->status,
            'status_label'  => $this->leave_statuses[$linha->status]['name'] ?? $linha->status,
            'status_note'   => $linha->status_note,
        ];
    }

    /**
     * Saldo por tipo (aba "Saldo por tipo"). Reusa o `LeaveBalanceService`, que já é
     * o dono da regra de limite no `store`/`changeStatus` — dois cálculos de saldo
     * divergiriam no primeiro ajuste (LC-19: estender o dono, não abrir paralelo).
     *
     * @return list<array<string, mixed>>
     */
    private function saldoPorTipo(int|string $business_id): array
    {
        $hoje = now()->format('Y-m-d');

        return EssentialsLeaveType::where('business_id', $business_id)
            ->orderBy('leave_type')
            ->get()
            ->map(function (EssentialsLeaveType $tipo) use ($business_id, $hoje) {
                $janela = $this->leaveBalance->janela($tipo->leave_count_interval, $hoje);

                $contar = function (string $status) use ($tipo, $business_id, $janela) {
                    $q = EssentialsLeave::where('business_id', $business_id)
                        ->where('essentials_leave_type_id', $tipo->id)
                        ->where('status', $status);

                    if ($janela !== null) {
                        $q->whereDate('start_date', '>=', $janela[0])
                            ->whereDate('start_date', '<=', $janela[1]);
                    }

                    return $q->get(['start_date', 'end_date'])->sum(
                        fn ($l) => \Carbon::parse($l->start_date)->diffInDays(\Carbon::parse($l->end_date)) + 1
                    );
                };

                $aprovado = (int) $contar(LeaveRequestService::STATUS_APPROVED);
                $emAnalise = (int) $contar(LeaveRequestService::STATUS_PENDING);
                $limite = (int) ($tipo->max_leave_count ?? 0);

                return [
                    'id'         => (int) $tipo->id,
                    'tipo'       => (string) $tipo->leave_type,
                    // R8/UC-HRM-19: limite 0 é "sem limite", não "zero dias permitidos".
                    'limite'     => $limite > 0 ? $limite : null,
                    'intervalo'  => $tipo->leave_count_interval,
                    'aprovado'   => $aprovado,
                    'em_analise' => $emAnalise,
                    'consumo'    => $limite > 0 ? round((($aprovado + $emAnalise) / $limite) * 100) : null,
                    'risco'      => $limite > 0 && ($aprovado + $emAnalise) > $limite,
                ];
            })->values()->all();
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        $business_id = request()->session()->get('user.business_id');

        $leave_types = EssentialsLeaveType::forDropdown($business_id);

        $settings = request()->session()->get('business.essentials_settings');
        $settings = ! empty($settings) ? json_decode($settings, true) : [];

        $instructions = ! empty($settings['leave_instructions']) ? $settings['leave_instructions'] : '';

        $employees = [];
        if (auth()->user()->can('essentials.crud_all_leave')) {
            $employees = User::forDropdown($business_id, false, false, false, true);
        }

        return view('essentials::leave.create')->with(compact('leave_types', 'instructions', 'employees'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * Validação em `StoreLeaveRequest` (HRM-O6 PR-2, achado A2) e limite do tipo em
     * `LeaveBalanceService` (PR-3, achado A3). Antes disso o método aceitava período
     * invertido, motivo vazio, tipo de outro tenant e `employees[]` de outro negócio.
     *
     * @return \Illuminate\Http\JsonResponse|array<string, mixed>
     */
    public function store(StoreLeaveRequest $request)
    {
        $business_id = request()->session()->get('user.business_id');

        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'essentials_module'))) {
            abort(403, 'Unauthorized action.');
        }
        $can_crud_all_leave = auth()->user()->can('essentials.crud_all_leave');
        $can_crud_own_leave = auth()->user()->can('essentials.crud_own_leave');

        if (! $can_crud_all_leave && ! $can_crud_own_leave) {
            abort(403, 'Unauthorized action.');
        }

        // Tier 0 (ADR 0093): reconfirma na hora do INSERT. O `exists` escopado do
        // FormRequest valida a leitura; o global scope não alcança o INSERT.
        $request->confirmarColaboradoresDoTenant();

        $criar_para_terceiros = auth()->user()->can('essentials.crud_all_leave')
            && ! empty($request->input('employees'));

        try {
            $input = $request->only(['essentials_leave_type_id', 'start_date', 'end_date', 'reason']);

            $input['business_id'] = $business_id;
            $input['status'] = 'pending';
            $input['start_date'] = $this->moduleUtil->uf_date($input['start_date']);
            $input['end_date'] = $this->moduleUtil->uf_date($input['end_date']);

            $destinatarios = $criar_para_terceiros
                ? $request->colaboradoresDoTenant()
                : [(int) request()->session()->get('user.id')];

            // PR-3 (achado A3): limite do tipo, por colaborador. Recusa ANTES de abrir
            // a transação e diz o saldo restante (UC-HRM-03).
            $tipo = EssentialsLeaveType::where('business_id', $business_id)
                ->findOrFail($input['essentials_leave_type_id']);

            foreach ($destinatarios as $user_id) {
                $saldo = $this->leaveBalance->avaliar(
                    $tipo,
                    (int) $business_id,
                    (int) $user_id,
                    (string) $input['start_date'],
                    (string) $input['end_date']
                );

                if (! $saldo['cabe']) {
                    return response()->json([
                        'success' => false,
                        'msg'     => $saldo['mensagem'],
                        'errors'  => ['essentials_leave_type_id' => [$saldo['mensagem']]],
                    ], 422);
                }
            }

            DB::beginTransaction();
            if ($criar_para_terceiros) {
                foreach ($destinatarios as $user_id) {
                    $this->__addLeave($input, $user_id);
                }
            } else {
                $this->__addLeave($input);
            }

            DB::commit();

            $output = ['success' => true,
                'msg' => __('lang_v1.added_success'),
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => false,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return $output;
    }

    private function __addLeave($input, $user_id = null)
    {
        $input['user_id'] = ! empty($user_id) ? $user_id : request()->session()->get('user.id');
        //Update reference count
        $ref_count = $this->moduleUtil->setAndGetReferenceCount('leave');
        //Generate reference number
        if (empty($input['ref_no'])) {
            $settings = request()->session()->get('business.essentials_settings');
            $settings = ! empty($settings) ? json_decode($settings, true) : [];
            $prefix = ! empty($settings['leave_ref_no_prefix']) ? $settings['leave_ref_no_prefix'] : '';
            $input['ref_no'] = $this->moduleUtil->generateReferenceNumber('leave', $ref_count, null, $prefix);
        }

        $leave = EssentialsLeave::create($input);

        $admins = $this->moduleUtil->get_admins($input['business_id']);

        \Notification::send($admins, new NewLeaveNotification($leave));
    }

    /**
     * Show the specified resource.
     *
     * R10 do charter: isto retornava `view('essentials::show')`, uma view que NÃO
     * existe — a rota do resource respondia 500. O detalhe da licença vive no drawer
     * do Index (PT-02 dentro do PT-01), então a rota redireciona, que é o mesmo
     * caminho já tomado pelo `EssentialsHolidayController` irmão.
     */
    public function show()
    {
        return redirect('/hrm/leave');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * R9 + R10: licença criada não se edita (o `update()` abaixo é vazio de
     * propósito) e a view `essentials::edit` não existe. Redireciona em vez de 500.
     */
    public function edit()
    {
        return redirect('/hrm/leave');
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function update(Request $request)
    {
    }

    /**
     * Remove the specified resource from storage.
     *
     * @return Response
     */
    public function destroy($id)
    {
        $business_id = request()->session()->get('user.business_id');

        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'essentials_module'))) {
            abort(403, 'Unauthorized action.');
        }

        if (! auth()->user()->can('essentials.crud_all_leave')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            try {
                EssentialsLeave::where('business_id', $business_id)->where('id', $id)->delete();

                $output = ['success' => true,
                    'msg' => __('lang_v1.deleted_success'),
                ];
            } catch (\Exception $e) {
                \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

                $output = ['success' => false,
                    'msg' => __('messages.something_went_wrong'),
                ];
            }

            return $output;
        }
    }

    public function changeStatus(Request $request)
    {
        $business_id = request()->session()->get('user.business_id');

        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'essentials_module')) || ! auth()->user()->can('essentials.approve_leave')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $input = $request->only(['status', 'leave_id', 'status_note']);

            $leave = EssentialsLeave::where('business_id', $business_id)
                            ->find($input['leave_id']);

            // PR-3 (achado A3): aprovar também pode estourar o limite do tipo.
            // Só barra a APROVAÇÃO — cancelar libera saldo e nunca é recusado.
            // A própria licença é excluída da soma (`$leave->id`): ela já está contada
            // como `pending` e contaria em dobro (UC-HRM-09).
            if ($leave !== null && $input['status'] === LeaveRequestService::STATUS_APPROVED) {
                $tipo = EssentialsLeaveType::where('business_id', $business_id)
                    ->find($leave->essentials_leave_type_id);

                if ($tipo !== null) {
                    $saldo = $this->leaveBalance->avaliar(
                        $tipo,
                        (int) $business_id,
                        (int) $leave->user_id,
                        (string) $leave->start_date,
                        (string) $leave->end_date,
                        (int) $leave->id
                    );

                    if (! $saldo['cabe']) {
                        return response()->json([
                            'success' => false,
                            'msg'     => $saldo['mensagem'],
                        ], 422);
                    }
                }
            }

            $leave->status = $input['status'];
            $leave->status_note = $input['status_note'];
            $leave->save();

            $leave->status = $this->leave_statuses[$leave->status]['name'];

            $leave->changed_by = auth()->user()->id;

            $leave->user->notify(new LeaveStatusNotification($leave));

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
     * Function to show activity log related to a leave
     *
     * @return Response
     */
    public function activity($id)
    {
        $business_id = request()->session()->get('user.business_id');

        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'essentials_module'))) {
            abort(403, 'Unauthorized action.');
        }

        $leave = EssentialsLeave::where('business_id', $business_id)
                                ->find($id);

        $activities = Activity::forSubject($leave)
                           ->with(['causer', 'subject'])
                           ->latest()
                           ->get();

        return view('essentials::leave.activity_modal')->with(compact('leave', 'activities'));
    }

    /**
     * Function to get leave summary of a user
     *
     * @return Response
     */
    public function getUserLeaveSummary()
    {
        $business_id = request()->session()->get('user.business_id');

        $is_admin = $this->moduleUtil->is_admin(auth()->user(), $business_id);

        $user_id = $is_admin ? request()->input('user_id') : auth()->user()->id;

        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'essentials_module'))) {
            abort(403, 'Unauthorized action.');
        }

        if (empty($user_id)) {
            return '';
        }

        $query = EssentialsLeave::where('business_id', $business_id)
                            ->where('user_id', $user_id)
                            ->with(['leave_type'])
                            ->select(
                                'status',
                                'essentials_leave_type_id',
                                'start_date',
                                'end_date'
                            );

        if (! empty(request()->start_date) && ! empty(request()->end_date)) {
            $start = request()->start_date;
            $end = request()->end_date;
            $query->whereDate('start_date', '>=', $start)
                        ->whereDate('start_date', '<=', $end);
        }
        $leaves = $query->get();
        $statuses = $this->leave_statuses;
        $leaves_summary = [];
        $status_summary = [];

        foreach ($statuses as $key => $value) {
            $status_summary[$key] = 0;
        }
        foreach ($leaves as $leave) {
            $start_date = \Carbon::parse($leave->start_date);
            $end_date = \Carbon::parse($leave->end_date);
            $diff = $start_date->diffInDays($end_date) + 1;

            $leaves_summary[$leave->essentials_leave_type_id][$leave->status] =
            isset($leaves_summary[$leave->essentials_leave_type_id][$leave->status]) ?
            $leaves_summary[$leave->essentials_leave_type_id][$leave->status] + $diff : $diff;

            $status_summary[$leave->status] = isset($status_summary[$leave->status]) ? ($status_summary[$leave->status] + $diff) : $diff;
        }

        $leave_types = EssentialsLeaveType::where('business_id', $business_id)
                                    ->get();
        $user = User::where('business_id', $business_id)
                    ->find($user_id);

        return view('essentials::leave.user_leave_summary')->with(compact('leaves_summary', 'leave_types', 'statuses', 'user', 'status_summary'));
    }
}
