<?php

namespace Modules\Crm\Http\Controllers;

use App\Contact;
use App\Services\FeatureFlagService;
use App\User;
use App\Utils\Util;
use Carbon\CarbonInterval;
use DB;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Modules\Crm\Entities\CrmCallLog;
use Modules\Crm\Http\Requests\MassDestroyCallLogRequest;
use Yajra\DataTables\Facades\DataTables;

class CallLogController extends Controller
{
    /** Tela React do Registro de chamadas (thread Crm/12). Default OFF: ligar é o cutover do [W]. */
    private const FLAG_CHAMADAS = 'useV2CrmChamadas';

    /**
     * All Utils instance.
     */
    protected $commonUtil;

    public function __construct(Util $commonUtil)
    {
        $this->commonUtil = $commonUtil;
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        if ((! auth()->user()->can('crm.view_all_call_log') && ! auth()->user()->can('crm.view_own_call_log')) || ! config('constants.enable_crm_call_log')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        // Thread Crm/12: com a flag ligada a lista abre em Inertia — o portão de config e de permissão
        // acima segue valendo. Decidir pelo `X-Inertia`, não só por `ajax()` (§5 2026-09-08).
        $inertia = ! request()->boolean('classico')
            && app(FeatureFlagService::class)->isOn(self::FLAG_CHAMADAS, ['business_id' => $business_id])
            && (! request()->ajax() || request()->header('X-Inertia'));

        if (request()->ajax() || $inertia) {
            $query = CrmCallLog::where('crm_call_logs.business_id', $business_id)
                            ->leftJoin('contacts as c', 'crm_call_logs.contact_id', '=', 'c.id')
                            ->leftJoin('users as u', 'crm_call_logs.user_id', '=', 'u.id')
                            ->leftJoin('users as created_users', 'crm_call_logs.created_by', '=', 'created_users.id')
                            ->select(
                                'crm_call_logs.*',
                                'c.name as customer_name',
                                'c.supplier_business_name',
                                DB::raw("CONCAT(COALESCE(u.surname, ''), ' ', COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) as user_full_name"),
                                DB::raw("CONCAT(COALESCE(created_users.surname, ''), ' ', COALESCE(created_users.first_name, ''), ' ', COALESCE(created_users.last_name, '')) as created_user_name")
                            );

            if (! auth()->user()->can('crm.view_all_call_log')) {
                $query->where('crm_call_logs.created_by', auth()->user()->id);
            }

            if (! empty(request()->get('contact_id'))) {
                $query->where('crm_call_logs.contact_id', request()->get('contact_id'));
            }

            if (! empty(request()->get('user_id'))) {
                $query->where('crm_call_logs.created_by', request()->get('user_id'));
            }

            if (! empty(request()->input('start_time')) && ! empty(request()->input('end_time'))) {
                $start_time = request()->input('start_time');
                $end_time = request()->input('end_time');
                $query->whereDate('crm_call_logs.start_time', '>=', $start_time)
                    ->whereDate('crm_call_logs.start_time', '<=', $end_time);
            }

            if ($inertia) {
                return $this->chamadasInertia($business_id, $query);
            }

            return Datatables::of($query)
                ->editColumn('start_time', '@if(!empty($start_time)) {{@format_datetime($start_time)}} @endif')
                ->editColumn('end_time', '@if(!empty($end_time)) {{@format_datetime($end_time)}} @endif')
                ->editColumn('duration', function ($row) {
                    $duration = ! empty($row->duration) ? CarbonInterval::seconds($row->duration)->cascade()->forHumans() : '';

                    return $duration;
                })
                ->addColumn('contact_number', '{{$mobile_number}} @if(!empty($mobile_name))
                <br> ({{$mobile_name}}) @endif')
                ->addColumn('contact_name', '@if(!empty($supplier_business_name)) {{$supplier_business_name}} <br> @endif {{$customer_name}}')
                ->addColumn('mass_delete', function ($row) {
                    return  '<input type="checkbox" class="row-select" value="'.$row->id.'">';
                })
                ->rawColumns(['contact_name', 'contact_number', 'mass_delete'])
                ->filterColumn('user_full_name', function ($query, $keyword) {
                    $query->whereRaw("CONCAT(COALESCE(u.surname, ''), ' ', COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) like ?", ["%{$keyword}%"]);
                })
                ->filterColumn('created_user_name', function ($query, $keyword) {
                    $query->whereRaw("CONCAT(COALESCE(created_users.surname, ''), ' ', COALESCE(created_users.first_name, ''), ' ', COALESCE(created_users.last_name, '')) like ?", ["%{$keyword}%"]);
                })
                ->filterColumn('contact_name', function ($query, $keyword) {
                    $query->where(function ($q) use ($keyword) {
                        $q->where('c.name', 'like', "%{$keyword}%")
                            ->orWhere('c.supplier_business_name', 'like', "%{$keyword}%");
                    });
                })
                ->filterColumn('contact_number', function ($query, $keyword) {
                    $query->where(function ($q) use ($keyword) {
                        $q->where('mobile_number', 'like', "%{$keyword}%")
                            ->orWhere('mobile_name', 'like', "%{$keyword}%");
                    });
                })
                ->make(true);
        }

        $contacts = Contact::contactDropdown($business_id, false, false);
        $users = User::forDropdown($business_id, false);

        $is_admin = $this->commonUtil->is_admin(auth()->user());

        return view('crm::call_logs.index')->with(compact('contacts', 'users', 'is_admin'));
    }

    /**
     * Mass deletes call logs.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function massDestroy(MassDestroyCallLogRequest $request)
    {
        // Wave 15 D8 Security — authorize() + validacao + normalizacao IDs centralizados.
        $business_id = $request->session()->get('user.business_id');

        // Mesmo escopo da lista (achado B2, thread Crm/12): sem permissão de chamada, 403;
        // quem só vê os próprios apaga só o que criou. Antes bastava `crm_module`.
        $pode_todos = auth()->user()->can('crm.view_all_call_log');
        if (! $pode_todos && ! auth()->user()->can('crm.view_own_call_log')) {
            abort(403, 'Unauthorized action.');
        }

        $selected_rows = $request->ids();

        if (! empty($selected_rows)) {
            $query = CrmCallLog::where('business_id', $business_id)
                    ->whereIn('id', $selected_rows);

            if (! $pode_todos) {
                $query->where('created_by', auth()->user()->id);
            }

            $query->delete();
        }
        $output = ['success' => 1,
            'msg' => __('lang_v1.deleted_success'),
        ];

        return redirect()->back()->with(['status' => $output]);
    }

    public function allUsersCallLog()
    {
        if (request()->ajax()) {
            $business_id = request()->session()->get('user.business_id');

            $today = \Carbon::now()->format('Y-m-d');
            $yesterday = \Carbon::yesterday()->format('Y-m-d');

            $query = CrmCallLog::where('crm_call_logs.business_id', $business_id)
                    ->join('users as u', 'crm_call_logs.created_by', '=', 'u.id')
                    ->select(
                        'u.username',
                        DB::raw("SUM(IF(DATE(start_time)='{$today}', 1, 0)) as calls_today"),
                        DB::raw("SUM(IF(DATE(start_time)='{$yesterday}', 1, 0)) as calls_yesterday"),
                        DB::raw('COUNT(crm_call_logs.id) as all_calls')
                    )->groupBy('u.id');

            return Datatables::of($query)
                        ->make(true);
        }
    }
    /**
     * Payload da tela React (thread Crm/12). A lista sai da MESMA consulta do DataTables (business_id,
     * "só os meus", contato, autor, datas). "Excluir selecionados" só para quem a Blade mostra o botão.
     */
    private function chamadasInertia($business_id, $query)
    {
        $busca = trim((string) request()->input('q', ''));
        if ($busca !== '') {
            $query->where(function ($w) use ($busca) {
                $w->where('c.name', 'like', "%{$busca}%")
                    ->orWhere('c.supplier_business_name', 'like', "%{$busca}%")
                    ->orWhere('crm_call_logs.mobile_number', 'like', "%{$busca}%")
                    ->orWhere('crm_call_logs.mobile_name', 'like', "%{$busca}%");
            });
        }

        $data = fn ($d) => empty($d) ? null : \Carbon\Carbon::parse($d)->format('d/m/Y H:i');
        $duracao = fn ($s) => empty($s) ? null : gmdate('H:i:s', (int) $s);
        $lista = fn ($mapa) => collect($mapa)->map(fn ($label, $value) => ['value' => (string) $value, 'label' => (string) $label])->values();
        $linha = fn ($r) => [
            'id' => $r->id,
            'inicio' => $data($r->start_time),
            'fim' => $data($r->end_time),
            'duracao' => $duracao($r->duration),
            'tipo' => $r->call_type,
            'numero' => $r->mobile_number,
            'nome_no_celular' => $r->mobile_name,
            'contato' => trim(($r->supplier_business_name ? $r->supplier_business_name.', ' : '').$r->customer_name) ?: null,
            'usuario' => trim((string) $r->user_full_name) ?: null,
            'criado_por' => trim((string) $r->created_user_name) ?: null,
        ];

        return Inertia::render('Crm/Chamadas/Index', [
            'filtros' => request()->only(['contact_id', 'user_id', 'start_time', 'end_time', 'q']),
            'pode_excluir' => (bool) $this->commonUtil->is_admin(auth()->user()),
            'opcoes' => Inertia::defer(fn () => [
                'contatos' => $lista(Contact::contactDropdown($business_id, false, false)),
                'usuarios' => $lista(User::forDropdown($business_id, false)),
            ]),
            'chamadas' => Inertia::defer(fn () => (clone $query)
                ->orderByDesc('crm_call_logs.start_time')
                ->paginate(25)
                ->withQueryString()
                ->through($linha)),
        ]);
    }
}
