<?php

namespace Modules\Crm\Http\Controllers;

use App\Contact;
use App\Http\Controllers\Controller;
use App\Utils\ModuleUtil;
use App\Utils\Util;
use DB;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Modules\Crm\Entities\CrmCallLog;
use Modules\Crm\Entities\Schedule;
use Modules\Crm\Entities\ScheduleLog;
use App\Support\Privacy\PiiRedactor;

class ScheduleLogController extends Controller
{
    /**
     * All Utils instance.
     */
    protected $commonUtil;

    protected $moduleUtil;

    /**
     * Constructor
     *
     * @param CommonUtil
     * @return void
     */
    public function __construct(Util $commonUtil, ModuleUtil $moduleUtil)
    {
        $this->commonUtil = $commonUtil;
        $this->moduleUtil = $moduleUtil;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $business_id = request()->session()->get('user.business_id');
        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'crm_module'))) {
            abort(403, 'Unauthorized action.');
        }

        $schedule_id = $request->get('schedule_id');
        $modal_content = $request->get('modal_content') == 'false' ? false : true;

        if (request()->ajax()) {
            // Negócio (404) e escopo de quem lê (403) antes do try: o catch genérico engoliria o 403.
            $this->acompanhamentoNoEscopo($business_id, $schedule_id);

            try {
                $schedule = Schedule::with(['invoices', 'invoices.payment_lines'])
                        ->where('business_id', $business_id)
                        ->findOrFail($schedule_id);

                $schedule_logs = ScheduleLog::with('createdBy')
                                ->where('schedule_id', $schedule_id)
                                ->latest()->get();
                //->simplePaginate(10);

                // Drawer de detalhe da tela Inertia (thread Crm/07): os registros em lista, sem o HTML
                // do modal Blade. O acompanhamento já foi buscado no negócio da sessão (findOrFail acima).
                if ($request->boolean('lista')) {
                    $quando = fn ($d) => empty($d) ? null : \Carbon::parse($d)->format('d/m/Y H:i');

                    $output = ['success' => true, 'registros' => $schedule_logs->map(function ($l) use ($quando) {
                        $autor = $l->getRelation('createdBy');

                        return [
                            'id' => (int) $l->id,
                            'assunto' => (string) $l->subject,
                            'tipo' => (string) $l->log_type,
                            'inicio' => $quando($l->start_datetime),
                            'fim' => $quando($l->end_datetime),
                            'descricao' => strip_tags((string) $l->description),
                            'por' => $autor ? trim($autor->user_full_name) : null,
                        ];
                    })->values()->all()];
                } else {
                //if call log is enabled
                $call_logs = [];
                if (config('constants.enable_crm_call_log')) {
                    $call_logs = CrmCallLog::leftJoin('users as created_users', 'crm_call_logs.created_by', '=', 'created_users.id')
                                    ->where('contact_id', $schedule->contact_id)
                                    ->whereIn('created_by', $schedule->users->pluck('id')->toArray())
                                    ->where('start_time', '>=', $schedule->start_datetime)
                                    ->where('end_time', '<=', $schedule->end_datetime)
                                    ->latest()
                                    ->select('crm_call_logs.*',
                                        DB::raw("CONCAT(COALESCE(created_users.surname, ''), ' ', COALESCE(created_users.first_name, ''), ' ', COALESCE(created_users.last_name, '')) as created_user_name")
                                    )
                                    ->get();
                }

                $logs_html = view('crm::schedule_log.partial.log')
                                ->with(compact('schedule_logs', 'modal_content', 'schedule', 'call_logs'))
                                ->render();

                $output = [
                    'success' => true,
                    'msg' => __('lang_v1.success'),
                    'log' => $logs_html,
                ];
                }
            } catch (ModelNotFoundException $e) {
                // Registro fora do negócio da sessão (ou inexistente): 404, não JSON de erro (UC-CRMACO-20).
                throw $e;
            } catch (Exception $e) {
                // D7 LGPD: redaciona PII em mensagens de erro antes de logar (call logs gravam telefone/contato).
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.app(PiiRedactor::class)->redact($e->getMessage()));

                $output = [
                    'success' => false,
                    'msg' => __('messages.something_went_wrong'),
                ];
            }

            return $output;
        }
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $business_id = request()->session()->get('user.business_id');
        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'crm_module'))) {
            abort(403, 'Unauthorized action.');
        }

        $id = request()->get('schedule_id');
        $schedule = $this->acompanhamentoNoEscopo($business_id, $id);
        $customers = Contact::customersDropdown($business_id, false);
        $statuses = Schedule::statusDropdown();

        return view('crm::schedule_log.create')
            ->with(compact('schedule', 'customers', 'statuses'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $business_id = request()->session()->get('user.business_id');
        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'crm_module'))) {
            abort(403, 'Unauthorized action.');
        }

        // D5 ([W] 2026-10-07, thread Crm/10): registrar segue o escopo da leitura. Fora do try:
        // o catch genérico abaixo transformaria o 403 em `success: false` com HTTP 200.
        $schedule = $this->acompanhamentoNoEscopo($business_id, $request->get('schedule_id'));

        try {
            $input = $request->only('log_type', 'subject', 'description');
            // Aceita o ISO do modal Inertia (thread Crm/07, PR-b) e o formato da empresa da Blade.
            $input['start_datetime'] = $this->commonUtil->uf_datetime_input($request->input('start_datetime'));
            $input['end_datetime'] = $this->commonUtil->uf_datetime_input($request->input('end_datetime'));
            $input['created_by'] = $request->user()->id;

            //update schedule status
            if (! empty($request->input('status'))) {
                $schedule->status = $request->input('status');
                $schedule->save();
            }

            $schedule_log = $schedule->scheduleLog()->create($input);

            $output = [
                'success' => true,
                'msg' => __('lang_v1.success'),
            ];
        } catch (ModelNotFoundException $e) {
            // Registro fora do negócio da sessão (ou inexistente): 404, não JSON de erro (UC-CRMACO-20).
            throw $e;
        } catch (Exception $e) {
            // D7 LGPD: redaciona PII em mensagens de erro antes de logar (call logs gravam telefone/contato).
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.app(PiiRedactor::class)->redact($e->getMessage()));

            $output = [
                'success' => false,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return $output;
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $business_id = request()->session()->get('user.business_id');
        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'crm_module'))) {
            abort(403, 'Unauthorized action.');
        }

        $schedule_id = request()->get('schedule_id');
        $this->acompanhamentoNoEscopo($business_id, $schedule_id);

        $schedule_log = ScheduleLog::with('schedule')
                        ->where('schedule_id', $schedule_id)
                        ->findOrFail($id);

        return view('crm::schedule_log.show')
            ->with(compact('schedule_log'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $business_id = request()->session()->get('user.business_id');
        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'crm_module'))) {
            abort(403, 'Unauthorized action.');
        }

        $schedule_id = request()->get('schedule_id');

        $schedule = $this->acompanhamentoNoEscopo($business_id, $schedule_id);

        $schedule_log = ScheduleLog::where('schedule_id', $schedule_id)
                            ->findOrFail($id);

        $customers = Contact::customersDropdown($business_id, false);
        $statuses = Schedule::statusDropdown();

        return view('crm::schedule_log.edit')
            ->with(compact('schedule', 'customers', 'schedule_log', 'statuses'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $business_id = request()->session()->get('user.business_id');
        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'crm_module'))) {
            abort(403, 'Unauthorized action.');
        }

        // Antes, o acompanhamento só era conferido quando vinha `status`: sem ele, o registro de
        // outro negócio era alterado [T0]. Agora negócio (404) e escopo (403) vêm primeiro, fora do try.
        $schedule_id = $request->get('schedule_id');
        $schedule = $this->acompanhamentoNoEscopo($business_id, $schedule_id);

        try {
            $input = $request->only('log_type', 'subject', 'description');
            $input['start_datetime'] = $this->commonUtil->uf_date($request->input('start_datetime'), true);
            $input['end_datetime'] = $this->commonUtil->uf_date($request->input('end_datetime'), true);

            $schedule_log = ScheduleLog::where('schedule_id', $schedule_id)
                            ->findOrFail($id);

            //update schedule status
            if (! empty($request->input('status'))) {
                $schedule->status = $request->input('status');
                $schedule->save();
            }

            $schedule_log->update($input);

            $output = [
                'success' => true,
                'msg' => __('lang_v1.success'),
            ];
        } catch (ModelNotFoundException $e) {
            // Registro fora do negócio da sessão (ou inexistente): 404, não JSON de erro (UC-CRMACO-20).
            throw $e;
        } catch (Exception $e) {
            // D7 LGPD: redaciona PII em mensagens de erro antes de logar (call logs gravam telefone/contato).
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.app(PiiRedactor::class)->redact($e->getMessage()));

            $output = [
                'success' => false,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return $output;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $business_id = request()->session()->get('user.business_id');
        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'crm_module'))) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            // Excluir não conferia o negócio do acompanhamento [T0]: negócio (404) e escopo (403) primeiro.
            $schedule_id = request()->get('schedule_id');
            $this->acompanhamentoNoEscopo($business_id, $schedule_id);

            try {
                $schedule_log = ScheduleLog::where('schedule_id', $schedule_id)
                                    ->findOrFail($id);

                $schedule_log->delete();

                $output = [
                    'success' => true,
                    'msg' => __('lang_v1.success'),
                ];
            } catch (ModelNotFoundException $e) {
                // Registro fora do negócio da sessão (ou inexistente): 404, não JSON de erro (UC-CRMACO-20).
                throw $e;
            } catch (Exception $e) {
                // D7 LGPD: redaciona PII em mensagens de erro antes de logar (call logs gravam telefone/contato).
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.app(PiiRedactor::class)->redact($e->getMessage()));

                $output = [
                    'success' => false,
                    'msg' => __('messages.something_went_wrong'),
                ];
            }

            return $output;
        }
    }

    /**
     * O acompanhamento no negócio da sessão E no escopo de quem pede (D5, [W] 2026-10-07 —
     * thread Crm/10): o mesmo recorte da leitura de um acompanhamento em
     * `ScheduleController@edit/show` — com só `crm.access_own_schedule`, vale o que me foi
     * atribuído ou o que eu criei. Fora do negócio: 404 (findOrFail). No negócio, fora do
     * escopo: 403. Sem chave de configuração: o dono decide pelo papel.
     */
    private function acompanhamentoNoEscopo(int $business_id, $schedule_id): Schedule
    {
        $schedule = Schedule::where('business_id', $business_id)->findOrFail($schedule_id);

        $user = auth()->user();
        if ($user->can('superadmin') || $user->can('crm.access_all_schedule')) {
            return $schedule;
        }

        $meu = $user->can('crm.access_own_schedule')
            && ((int) $schedule->created_by === (int) $user->id
                || $schedule->users()->where('user_id', $user->id)->exists());

        if (! $meu) {
            abort(403, 'Unauthorized action.');
        }

        return $schedule;
    }
}
