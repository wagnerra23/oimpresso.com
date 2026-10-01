<?php

namespace Modules\Crm\Http\Controllers;

use App\Category;
use App\Contact;
use App\User;
use DB;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Modules\Crm\Entities\CrmCallLog;
use Modules\Crm\Entities\CrmContact;
use Modules\Crm\Entities\Schedule;
use Modules\Crm\Utils\CrmUtil;

class CrmDashboardController extends Controller
{
    protected $crmUtil;

    /**
     * Constructor
     *
     * @param  Util  $commonUtil
     * @return void
     */
    public function __construct(CrmUtil $crmUtil)
    {
        $this->crmUtil = $crmUtil;
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        $business_id = request()->session()->get('user.business_id');

        // Thread Crm/04: o painel abre em Inertia (PT-04). `?classico=1` mantém a Blade.
        if (! request()->boolean('classico')) {
            return $this->painelInertia($business_id);
        }

        $contacts = Contact::where('business_id', $business_id)
                    ->Active()
                    ->get();

        $customers = $contacts->whereIn('type', ['customer', 'both']);

        $leads = $contacts->where('type', 'lead');

        $total_customers = $customers->count();

        $total_leads = $leads->count();
        $sources = Category::where('business_id', $business_id)
                                ->where('category_type', 'source')
                                ->get();
        $total_sources = $sources->count();

        $life_stages = Category::where('business_id', $business_id)
                                ->where('category_type', 'life_stage')
                                ->get();

        $total_life_stage = $life_stages->count();
        $leads_by_life_stage = $leads->groupBy('crm_life_stage');

        $contacts_count_by_source = CrmContact::getContactsCountBySourceOfGivenTyps($business_id);

        $leads_count_by_source = CrmContact::getContactsCountBySourceOfGivenTyps($business_id, ['lead']);

        $customers_count_by_source = CrmContact::getContactsCountBySourceOfGivenTyps($business_id, ['customer', 'both']);

        $todays_birthdays = array_merge($this->getBirthdays($customers)['todays_birthdays'], $this->getBirthdays($leads)['todays_birthdays']);

        $upcoming_birthdays = array_merge($this->getBirthdays($customers)['upcoming_birthdays'], $this->getBirthdays($leads)['upcoming_birthdays']);

        $my_follow_ups = $this->myFollowUps();

        $my_follow_ups_arr = [];
        foreach ($my_follow_ups as $follow_up) {
            if (! empty($follow_up->status)) {
                $my_follow_ups_arr[$follow_up->status] = $follow_up->total_follow_ups;
            } else {
                $my_follow_ups_arr['__other'] = $follow_up->total_follow_ups;
            }
        }

        $statuses = Schedule::statusDropdown();

        $my_leads = $this->myLeads();

        $my_conversion = $this->myConversion();

        $todays_followups = $this->todaysFollowUp();

        $my_call_logs = [];
        if (config('constants.enable_crm_call_log')) {
            $my_call_logs = $this->getMyCallLogs();
        }

        $followup_category = Category::forDropdown($business_id, 'followup_category');

        $is_admin = $this->crmUtil->is_admin(auth()->user());

        return view('crm::crm_dashboard.index')->with(compact('total_customers', 'total_leads', 'total_sources', 'total_life_stage', 'leads_by_life_stage', 'sources', 'life_stages', 'todays_birthdays', 'upcoming_birthdays', 'leads_count_by_source', 'contacts_count_by_source', 'customers_count_by_source', 'my_follow_ups_arr', 'statuses', 'my_leads', 'my_conversion', 'todays_followups', 'my_call_logs', 'followup_category', 'is_admin'));
    }

    /**
     * Function to fetch all the followups of the logged in user
     */
    private function myFollowUps()
    {
        $my_follow_ups = User::user()
                    ->where('users.id', auth()->user()->id)
                    ->join('crm_schedule_users as su', 'su.user_id', '=', 'users.id')
                    ->join('crm_schedules as follow_ups', 'follow_ups.id', '=', 'su.schedule_id')
                    ->where('follow_ups.business_id', request()->session()->get('user.business_id'))
                    ->select(
                        'follow_ups.status',
                        DB::raw('COUNT(su.id) as total_follow_ups')
                    )->groupBy('follow_ups.status')->get();

        return $my_follow_ups;
    }

    /**
     * Function to fetch call log statistic of the logged in user
     */
    private function getMyCallLogs()
    {
        $business_id = request()->session()->get('user.business_id');

        $today = \Carbon::now()->format('Y-m-d');
        $yesterday = \Carbon::yesterday()->format('Y-m-d');
        $first_day_of_month = \Carbon::now()->startOfMonth()->format('Y-m-d');

        $my_call_logs = CrmCallLog::where('business_id', $business_id)
                ->where('created_by', auth()->user()->id)
                ->whereDate('start_time', '>=', $first_day_of_month)
                ->select(
                    DB::raw("SUM(IF(DATE(start_time)='{$today}', 1, 0)) as calls_today"),
                    DB::raw("SUM(IF(DATE(start_time)='{$yesterday}', 1, 0)) as calls_yesterday"),
                    DB::raw('COUNT(id) as calls_this_month')
                )->first();

        return $my_call_logs;
    }

    /**
     * Function to fetch all the followups of the logged in user
     */
    private function todaysFollowUp()
    {
        $todays_followups = Schedule::where('business_id', request()->session()->get('user.business_id'))
                    ->whereHas('users', function ($q) {
            $q->where('user_id', auth()->user()->id);
        })
                    ->whereIn('status', ['open', 'scheduled'])
                    ->whereDate('start_datetime', \Carbon::now()->format('Y-m-d'))
                    ->count();

        return $todays_followups;
    }

    /**
     * Function to count all the leads of the logged in user
     */
    private function myLeads()
    {
        $business_id = request()->session()->get('user.business_id');

        $total_leads = CrmContact::where('contacts.business_id', $business_id)
                        ->where('contacts.type', 'lead')
                        ->whereHas('leadUsers', function ($q) {
                            $q->where('user_id', auth()->user()->id);
                        })->count();

        return $total_leads;
    }

    /**
     * Function to count all the leads to customer conversion of the logged in user
     */
    private function myConversion()
    {
        $count = Contact::where('business_id', request()->session()->get('user.business_id'))
                        ->where('converted_by', auth()->user()->id)
                        ->count();

        return $count;
    }

    private function getBirthdays($contacts)
    {
        $todays_birthdays = [];
        $upcoming_birthdays = [];

        $today = \Carbon::now();
        $thirty_days_from_today = \Carbon::now()->addDays(30)->format('Y-m-d');
        foreach ($contacts as $contact) {
            if (empty($contact->dob)) {
                continue;
            }

            $dob = \Carbon::parse($contact->dob);
            $dob_md = $dob->format('m-d');

            $next_birthday = \Carbon::parse($today->format('Y').'-'.$dob_md);
            if ($next_birthday->lt($today)) {
                $next_birthday->addYear();
            }

            if ($today->format('m-d') == $dob->format('m-d')) {
                $todays_birthdays[] = ['id' => $contact->id, 'name' => $contact->name];
            } elseif ($next_birthday->between($today->format('Y-m-d'), $thirty_days_from_today)) {
                $upcoming_birthdays[] = ['name' => $contact->name, 'id' => $contact->id, 'dob' => $dob->format('m-d')];
            }
        }

        return [
            'todays_birthdays' => $todays_birthdays,
            'upcoming_birthdays' => $upcoming_birthdays,
        ];
    }

    /**
     * Painel do CRM em Inertia (thread Crm/04, PT-04). Toda contagem é deste `business_id`.
     * Seções seguem o mesmo gate da Blade: acompanhamentos e leads por permissão, chamadas
     * pela config, e o bloco do negócio só para Admin — quem não vê, não recebe o dado.
     */
    private function painelInertia($business_id)
    {
        $user = auth()->user();
        $perm = [
            'acompanhamentos' => $user->can('crm.access_all_schedule') || $user->can('crm.access_own_schedule'),
            'leads' => $user->can('crm.access_all_leads') || $user->can('crm.access_own_leads'),
            'chamadas' => (bool) config('constants.enable_crm_call_log'),
            'admin' => (bool) $this->crmUtil->is_admin($user),
        ];

        return Inertia::render('Crm/Painel/Index', [
            'permissoes' => $perm,
            'pessoal' => Inertia::defer(fn () => $this->painelPessoal($perm)),
            'negocio' => $perm['admin'] ? Inertia::defer(fn () => $this->painelNegocio($business_id, $perm['chamadas'])) : null,
        ]);
    }

    private function painelPessoal(array $perm): array
    {
        $porStatus = null;
        if ($perm['acompanhamentos']) {
            $contagem = $this->myFollowUps()->mapWithKeys(fn ($r) => [($r->status ?: '__other') => (int) $r->total_follow_ups]);
            $porStatus = [];
            foreach (Schedule::statusDropdown() as $k => $rotulo) {
                $porStatus[] = ['rotulo' => $rotulo, 'total' => $contagem[$k] ?? 0];
            }
            $porStatus[] = ['rotulo' => __('lang_v1.others'), 'total' => $contagem['__other'] ?? 0];
        }
        $chamadas = $perm['chamadas'] ? $this->getMyCallLogs() : null;

        return [
            'hoje' => $perm['acompanhamentos'] ? $this->todaysFollowUp() : null,
            'meus_leads' => $perm['leads'] ? $this->myLeads() : null,
            'convertidos' => $this->myConversion(),
            'por_status' => $porStatus,
            'chamadas' => $chamadas ? ['hoje' => (int) $chamadas->calls_today, 'ontem' => (int) $chamadas->calls_yesterday, 'mes' => (int) $chamadas->calls_this_month] : null,
        ];
    }

    private function painelNegocio($business_id, bool $comChamadas): array
    {
        $ativos = fn () => Contact::where('contacts.business_id', $business_id)->Active();
        $fontes = Category::where('business_id', $business_id)->where('category_type', 'source')->get(['id', 'name']);
        $fases = Category::where('business_id', $business_id)->where('category_type', 'life_stage')->get(['id', 'name']);
        $leadsPorFonte = CrmContact::getContactsCountBySourceOfGivenTyps($business_id, ['lead']);
        $clientesPorFonte = CrmContact::getContactsCountBySourceOfGivenTyps($business_id, ['customer', 'both']);
        $todosPorFonte = CrmContact::getContactsCountBySourceOfGivenTyps($business_id);
        $leadsPorFase = $ativos()->where('type', 'lead')->groupBy('crm_life_stage')
            ->selectRaw('crm_life_stage, COUNT(*) as total')->get()->pluck('total', 'crm_life_stage');
        $aniv = $this->getBirthdays($ativos()->whereIn('type', ['customer', 'both', 'lead'])->whereNotNull('dob')->get(['id', 'name', 'dob']));
        $nome = "TRIM(CONCAT(COALESCE(users.surname, ''), ' ', COALESCE(users.first_name, ''), ' ', COALESCE(users.last_name, '')))";

        $porUsuario = User::where('users.business_id', $business_id)->user()->where('is_cmmsn_agnt', 0)
            ->join('crm_schedule_users as su', 'su.user_id', '=', 'users.id')
            ->join('crm_schedules as f', 'f.id', '=', 'su.schedule_id')
            ->where('f.business_id', $business_id)
            ->selectRaw("{$nome} as usuario, COUNT(su.id) as total, SUM(IF(f.status IS NULL, 1, 0)) as nenhum")
            ->groupBy('users.id', 'users.surname', 'users.first_name', 'users.last_name');
        $status = Schedule::statusDropdown();
        foreach (array_keys($status) as $k) {
            $porUsuario->selectRaw("SUM(IF(f.status = ?, 1, 0)) as `st_{$k}`", [$k]);
        }

        $hoje = now()->format('Y-m-d');
        $mes = now()->startOfMonth()->format('Y-m-d');

        return [
            'clientes' => $ativos()->whereIn('type', ['customer', 'both'])->count(),
            'leads' => $ativos()->where('type', 'lead')->count(),
            'total_fontes' => $fontes->count(),
            'total_fases' => $fases->count(),
            'status' => collect($status)->map(fn ($l, $v) => ['value' => $v, 'label' => $l])->values(),
            'por_fonte' => $fontes->map(fn ($f) => [
                'fonte' => $f->name,
                'total' => (int) ($leadsPorFonte[$f->id]['count'] ?? 0),
                'conversao' => empty($todosPorFonte[$f->id]['count']) ? 0
                    : round(($clientesPorFonte[$f->id]['count'] ?? 0) / $todosPorFonte[$f->id]['count'] * 100, 1),
            ])->values(),
            'por_fase' => $fases->map(fn ($f) => ['fase' => $f->name, 'total' => (int) ($leadsPorFase[$f->id] ?? 0)])->values(),
            'aniversarios' => ['hoje' => $aniv['todays_birthdays'], 'proximos' => $aniv['upcoming_birthdays']],
            'por_usuario' => $porUsuario->toBase()->get()->map(fn ($r) => collect(array_keys($status))
                ->mapWithKeys(fn ($k) => [$k => (int) $r->{"st_{$k}"}])
                ->merge(['usuario' => $r->usuario, 'nenhum' => (int) $r->nenhum, 'total' => (int) $r->total]))->values(),
            'conversao' => User::where('users.business_id', $business_id)->user()->where('is_cmmsn_agnt', 0)
                ->join('contacts as c', 'c.converted_by', '=', 'users.id')->where('c.business_id', $business_id)
                ->selectRaw("{$nome} as usuario, COUNT(c.id) as total")
                ->groupBy('users.id', 'users.surname', 'users.first_name', 'users.last_name')->toBase()->get()
                ->map(fn ($r) => ['usuario' => $r->usuario, 'total' => (int) $r->total])->values(),
            'chamadas' => ! $comChamadas ? null : CrmCallLog::where('crm_call_logs.business_id', $business_id)
                ->join('users as u', 'crm_call_logs.created_by', '=', 'u.id')
                ->selectRaw('u.username as usuario, SUM(IF(DATE(start_time) = ?, 1, 0)) as hoje, SUM(IF(DATE(start_time) >= ?, 1, 0)) as mes, COUNT(crm_call_logs.id) as todas', [$hoje, $mes])
                ->groupBy('u.id', 'u.username')->toBase()->get()
                ->map(fn ($r) => ['usuario' => $r->usuario, 'hoje' => (int) $r->hoje, 'mes' => (int) $r->mes, 'todas' => (int) $r->todas])->values(),
        ];
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        return view('crm::create');
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
        return view('crm::show');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function edit($id)
    {
        return view('crm::edit');
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
