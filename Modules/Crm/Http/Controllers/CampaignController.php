<?php

namespace Modules\Crm\Http\Controllers;

use App\Business;
use App\Services\FeatureFlagService;
use App\Transaction;
use App\Utils\ModuleUtil;
use App\Utils\NotificationUtil;
use Carbon\Carbon;
use DB;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Modules\Crm\Entities\Campaign;
use Modules\Crm\Entities\CrmContact;
use Modules\Crm\Http\Requests\StoreCampaignRequest;
use Modules\Crm\Notifications\SendCampaignNotification;
use Modules\Crm\Services\CampaignService;
use App\Support\Privacy\PiiRedactor;
use Notification;
use Yajra\DataTables\Facades\DataTables;

class CampaignController extends Controller
{
    /** Tela React de Campanhas (thread Crm/11). Default OFF: ligar é o cutover do [W] (RUNBOOK-campanhas §2). */
    private const FLAG_CAMPANHAS = 'useV2CrmCampanhas';

    protected $notificationUtil;

    protected $moduleUtil;

    protected CampaignService $campaignService;

    /**
     * Constructor
     *
     * @param  NotificationUtil  $notificationUtil
     * @return void
     */
    public function __construct(NotificationUtil $notificationUtil, ModuleUtil $moduleUtil, CampaignService $campaignService)
    {
        $this->notificationUtil = $notificationUtil;
        $this->moduleUtil = $moduleUtil;
        $this->campaignService = $campaignService;
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        $business_id = request()->session()->get('user.business_id');

        $can_access_all_campaigns = auth()->user()->can('crm.access_all_campaigns');
        $can_access_own_campaigns = auth()->user()->can('crm.access_own_campaigns');

        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'crm_module')) || ! ($can_access_all_campaigns || $can_access_own_campaigns)) {
            abort(403, 'Unauthorized action.');
        }

        // Thread Crm/11: com a flag ligada a lista abre em Inertia. O Inertia manda `X-Requested-With`
        // junto do `X-Inertia`, então decidir só por `ajax()` mandaria a visita pro DataTables
        // (§5 2026-09-08). `?classico=1` mantém a Blade, que segue com criar/editar/excluir/enviar.
        $inertia = ! request()->boolean('classico')
            && app(FeatureFlagService::class)->isOn(self::FLAG_CAMPANHAS, ['business_id' => $business_id])
            && (! request()->ajax() || request()->header('X-Inertia'));

        if (request()->ajax() || $inertia) {
            $campaigns = Campaign::with('createdBy')
                        ->where('business_id', $business_id)
                        ->select('*');

            if (! $can_access_all_campaigns && $can_access_own_campaigns) {
                $campaigns->where('created_by', auth()->user()->id);
            }

            if (! empty(request()->get('campaign_type'))) {
                $campaigns->where('campaign_type', request()->get('campaign_type'));
            }

            if ($inertia) {
                return $this->campanhasInertia($campaigns);
            }

            return Datatables::of($campaigns)
                    ->addColumn('action', function ($row) {
                        $html = '<a data-href="'.action([\Modules\Crm\Http\Controllers\CampaignController::class, 'show'], ['campaign' => $row->id]).'" class="cursor-pointer view_a_campaign btn btn-xs btn-info m-2">
                            <i class="fa fa-eye"></i>
                            '.__('messages.view').'
                            </a>';

                        if (empty($row->sent_on)) {
                            $html .= '
                            <a href="'.action([\Modules\Crm\Http\Controllers\CampaignController::class, 'edit'], ['campaign' => $row->id]).'"class="cursor-pointer btn btn-xs btn-primary m-2">
                                <i class="fa fa-edit"></i>
                                '.__('messages.edit').'
                            </a>';
                        }

                        $html .= '<a data-href="'.action([\Modules\Crm\Http\Controllers\CampaignController::class, 'destroy'], ['campaign' => $row->id]).'" class="cursor-pointer delete_a_campaign btn btn-xs btn-danger m-2">
                            <i class="fas fa-trash"></i>
                            '.__('messages.delete').'
                            </a>';

                        if (empty($row->sent_on)) {
                            $html .= '<a data-href="'.action([\Modules\Crm\Http\Controllers\CampaignController::class, 'sendNotification'], ['id' => $row->id]).'" class="cursor-pointer send_campaign_notification btn btn-xs btn-warning m-2">
                                <i class="fas fa-envelope-square"></i>
                                '.__('crm::lang.send_notification').'
                            </a>';
                        }

                        return $html;
                    })
                    ->editColumn('campaign_type', '
                        @if($campaign_type == "sms")
                            {{__("crm::lang.sms")}}
                        @elseif($campaign_type == "email")
                            {{__("business.email")}}
                        @endif
                    ')
                    ->editColumn('created_at', '
                        {{@format_date($created_at)}}
                    ')
                    ->editColumn('name', function ($row) {
                        $is_notified = '';
                        if (! empty($row->sent_on)) {
                            $is_notified = '<br> <span class="label label-success">'.
                                                __('crm::lang.sent').
                                            '</span>';
                        }

                        return $row->name.$is_notified;
                    })
                    ->editColumn('createdBy', function ($row) {
                        return $row->createdBy?->user_full_name;
                    })
                    ->removeColumn('id')
                    ->rawColumns(['action', 'name', 'campaign_type', 'createdBy', 'created_at'])
                    ->make(true);
        }

        return view('crm::campaign.index');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create(Request $request)
    {
        $business_id = request()->session()->get('user.business_id');
        $can_access_all_campaigns = auth()->user()->can('crm.access_all_campaigns');
        $can_access_own_campaigns = auth()->user()->can('crm.access_own_campaigns');

        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'crm_module')) || ! ($can_access_all_campaigns || $can_access_own_campaigns)) {
            abort(403, 'Unauthorized action.');
        }

        $tags = Campaign::getTags();
        $leads = CrmContact::leadsDropdown($business_id, false);
        $customers = CrmContact::customersDropdown($business_id, false);
        $contact_ids = $request->get('contact_ids', '');

        $contacts = [];
        foreach ($leads as $key => $lead) {
            $contacts[$key] = $lead;
        }

        foreach ($customers as $key => $customer) {
            $contacts[$key] = $customer;
        }

        return view('crm::campaign.create')
            ->with(compact('tags', 'leads', 'customers', 'contact_ids', 'contacts'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(StoreCampaignRequest $request)
    {
        // Permissão crm_module validada em StoreCampaignRequest::authorize().
        $business_id = request()->session()->get('user.business_id');

        try {
            $base = $request->only('name', 'campaign_type', 'subject', 'email_body', 'sms_body');
            $customers = $request->input('contact_id', []);
            $leads = $request->input('lead_id', []);
            $contacts = $request->input('contact', []); //birthday_wishes
            $additionalInfo = [
                'to' => $request->input('to'),
                'trans_activity' => $request->input('trans_activity'),
                'in_days' => $request->input('in_days'),
            ];

            $campaign = $this->campaignService->createCampaign(
                $base,
                (int) $business_id,
                (int) $request->user()->id,
                $customers,
                $leads,
                $contacts,
                $additionalInfo,
            );

            if ($request->get('send_notification') && ! empty($campaign)) {
                $this->__sendCampaignNotification($campaign->id, $business_id);
            }

            $output = [
                'success' => true,
                'msg' => __('lang_v1.success'),
            ];
        } catch (Exception $e) {
            // D7 LGPD: redaciona PII em mensagens de erro antes de logar (email/sms_body podem conter contato).
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.app(PiiRedactor::class)->redact($e->getMessage()));

            $output = [
                'success' => false,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return redirect()
            ->action([\Modules\Crm\Http\Controllers\CampaignController::class, 'index'])
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
        $business_id = request()->session()->get('user.business_id');
        $can_access_all_campaigns = auth()->user()->can('crm.access_all_campaigns');
        $can_access_own_campaigns = auth()->user()->can('crm.access_own_campaigns');

        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'crm_module')) || ! ($can_access_all_campaigns || $can_access_own_campaigns)) {
            abort(403, 'Unauthorized action.');
        }

        $query = Campaign::with('createdBy')
                    ->where('business_id', $business_id);

        if (! $can_access_all_campaigns && $can_access_own_campaigns) {
            $query->where('created_by', auth()->user()->id);
        }

        $campaign = $query->findOrFail($id);

        // Só contatos do negócio da sessão: `Contact` não tem escopo global e campanha antiga pode
        // guardar id de outro negócio (achado A1, thread Crm/11 — ADR 0093).
        $notifiable_users = CrmContact::where('business_id', $business_id)
            ->whereIn('id', (array) $campaign->contact_ids)
            ->get();

        return view('crm::campaign.show')
            ->with(compact('campaign', 'notifiable_users'));
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
        $can_access_all_campaigns = auth()->user()->can('crm.access_all_campaigns');
        $can_access_own_campaigns = auth()->user()->can('crm.access_own_campaigns');

        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'crm_module')) || ! ($can_access_all_campaigns || $can_access_own_campaigns)) {
            abort(403, 'Unauthorized action.');
        }

        $query = Campaign::where('business_id', $business_id);

        if (! $can_access_all_campaigns && $can_access_own_campaigns) {
            $query->where('created_by', auth()->user()->id);
        }

        $campaign = $query->findOrFail($id);

        $tags = Campaign::getTags();
        $leads = CrmContact::leadsDropdown($business_id, false);
        $customers = CrmContact::customersDropdown($business_id, false);

        $contacts = [];
        foreach ($leads as $key => $lead) {
            $contacts[$key] = $lead;
        }

        foreach ($customers as $key => $customer) {
            $contacts[$key] = $customer;
        }

        return view('crm::campaign.edit')
            ->with(compact('tags', 'campaign', 'leads', 'customers', 'contacts'));
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
        $can_access_all_campaigns = auth()->user()->can('crm.access_all_campaigns');
        $can_access_own_campaigns = auth()->user()->can('crm.access_own_campaigns');

        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'crm_module')) || ! ($can_access_all_campaigns || $can_access_own_campaigns)) {
            abort(403, 'Unauthorized action.');
        }

        // Mesma regra do StoreCampaignRequest: destinatário só do negócio da sessão (A1).
        $request->validate(StoreCampaignRequest::regrasDeContato((int) $business_id));

        try {
            $base = $request->only('name', 'campaign_type', 'subject', 'email_body', 'sms_body');
            $customers = $request->input('contact_id', []);
            $leads = $request->input('lead_id', []);
            $contacts = $request->input('contact', []); //birthday_wishes
            $additionalInfo = [
                'to' => $request->input('to'),
                'trans_activity' => $request->input('trans_activity'),
                'in_days' => $request->input('in_days'),
            ];

            $query = Campaign::where('business_id', $business_id);

            if (! $can_access_all_campaigns && $can_access_own_campaigns) {
                $query->where('created_by', auth()->user()->id);
            }

            $campaign = $query->findOrFail($id);

            $campaign = $this->campaignService->updateCampaign(
                $campaign,
                $base,
                $customers,
                $leads,
                $contacts,
                $additionalInfo,
            );

            if ($request->get('send_notification') && ! empty($campaign)) {
                $this->__sendCampaignNotification($campaign->id, $business_id);
            }

            $output = [
                'success' => true,
                'msg' => __('lang_v1.success'),
            ];
        } catch (Exception $e) {
            // D7 LGPD: redaciona PII em mensagens de erro antes de logar (email/sms_body podem conter contato).
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.app(PiiRedactor::class)->redact($e->getMessage()));

            $output = [
                'success' => false,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return redirect()
            ->action([\Modules\Crm\Http\Controllers\CampaignController::class, 'index'])
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
        $can_access_all_campaigns = auth()->user()->can('crm.access_all_campaigns');
        $can_access_own_campaigns = auth()->user()->can('crm.access_own_campaigns');

        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'crm_module')) || ! ($can_access_all_campaigns || $can_access_own_campaigns)) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            try {
                $query = Campaign::where('business_id', $business_id);

                if (! $can_access_all_campaigns && $can_access_own_campaigns) {
                    $query->where('created_by', auth()->user()->id);
                }

                $query->where('id', $id)
                    ->delete();

                $output = [
                    'success' => true,
                    'msg' => __('lang_v1.success'),
                ];
            } catch (Exception $e) {
                // D7 LGPD: redaciona PII em mensagens de erro antes de logar (email/sms_body podem conter contato).
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.app(PiiRedactor::class)->redact($e->getMessage()));

                $output = [
                    'success' => false,
                    'msg' => __('messages.something_went_wrong'),
                ];
            }

            return $output;
        }
    }

    public function sendNotification($id)
    {
        $business_id = request()->session()->get('user.business_id');
        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'crm_module'))) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            $output = $this->__sendCampaignNotification($id, $business_id);

            return $output;
        }
    }

    public function __sendCampaignNotification($campaign_id, $business_id)
    {
        try {
            $campaign = Campaign::where('business_id', $business_id)
                            ->findOrFail($campaign_id);

            $business = Business::findOrFail($business_id);

            if ((! empty($campaign->additional_info['to']) && in_array($campaign->additional_info['to'], ['lead', 'customer', 'contact']))
                || (! isset($campaign->additional_info['to']) && ! empty($campaign->contact_ids))) {
                $contact_ids = $campaign->contact_ids;
            } elseif (! empty($campaign->additional_info['to']) && in_array($campaign->additional_info['to'], ['transaction_activity'])) {
                $day = Carbon::now()->subDays($campaign->additional_info['in_days'])->toDateTimeString();
                $query = Transaction::where('business_id', $business_id)
                                ->select('contact_id', DB::raw('MAX(transaction_date) as last_shoped'));

                if ($campaign->additional_info['trans_activity'] == 'has_transactions') {
                    $query->having('last_shoped', '>=', $day);
                } elseif ($campaign->additional_info['trans_activity'] == 'has_no_transactions') {
                    $query->having('last_shoped', '<=', $day);
                }

                $transactions = $query
                                ->groupBy('contact_id')
                                ->get();

                $contact_ids = $transactions->pluck('contact_id')->toArray();
            }

            // Envio só para contato do próprio negócio (achado A1, thread Crm/11 — ADR 0093).
            $notifiable_users = CrmContact::where('business_id', $business_id)
                ->whereIn('id', (array) $contact_ids)
                ->get();

            if ($notifiable_users->isNotEmpty() && $campaign->campaign_type == 'sms') {
                $notification_data['sms_settings'] = request()->session()->get('business.sms_settings');
                foreach ($notifiable_users as $user) {
                    $notification_data['mobile_number'] = $user->mobile;
                    $notification_data['sms_body'] = preg_replace(['/{contact_name}/', '/{campaign_name}/', '/{business_name}/'], [$user->name, $campaign->name, $business->name], $campaign->sms_body);

                    $this->notificationUtil->sendSms($notification_data);
                }
            } elseif ($notifiable_users->isNotEmpty() && $campaign->campaign_type == 'email') {
                Notification::send($notifiable_users, new SendCampaignNotification($campaign, $business));
            }

            DB::beginTransaction();

            $campaign->sent_on = Carbon::now();
            $campaign->save();

            DB::commit();

            $output = [
                'success' => true,
                'msg' => __('lang_v1.success'),
            ];
        } catch (Exception $e) {
            DB::rollBack();

            // D7 LGPD: redaciona PII em mensagens de erro antes de logar (email/sms_body podem conter contato).
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.app(PiiRedactor::class)->redact($e->getMessage()));

            $output = [
                'success' => false,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return $output;
    }
    /**
     * Payload da tela React (thread Crm/11). Lista e drawer saem da MESMA consulta do DataTables
     * (business_id + "só as minhas" + tipo), para não divergir de escopo. O corpo vai como texto:
     * a Blade imprime HTML cru (`{!! !!}`), a tela nova não.
     */
    private function campanhasInertia($campaigns)
    {
        $busca = trim((string) request()->input('q', ''));
        if ($busca !== '') {
            $campaigns->where('name', 'like', "%{$busca}%");
        }

        $data = fn ($d) => empty($d) ? null : Carbon::parse($d)->format('d/m/Y H:i');
        $linha = fn ($c) => [
            'id' => $c->id,
            'nome' => $c->name,
            'tipo' => $c->campaign_type,
            'contatos' => count((array) $c->contact_ids),
            'criado_por' => $c->createdBy?->user_full_name,
            'criado_em' => $data($c->created_at),
            'enviada_em' => $data($c->sent_on),
            'assunto' => $c->subject,
            'corpo' => trim(html_entity_decode(strip_tags((string) ($c->campaign_type === 'sms' ? $c->sms_body : $c->email_body)))),
        ];

        $campanhaId = (int) request()->input('campanha');

        return Inertia::render('Crm/Campanhas/Index', [
            'filtros' => request()->only(['campaign_type', 'q']),
            'campanhas' => Inertia::defer(fn () => (clone $campaigns)
                ->orderByDesc('created_at')
                ->paginate(25)
                ->withQueryString()
                ->through($linha)),
            // Drawer: campanha de outro negócio ou de colega (para quem só vê as próprias) dá null.
            'campanha' => fn () => $campanhaId > 0
                ? optional((clone $campaigns)->where('id', $campanhaId)->first(), $linha)
                : null,
        ]);
    }
}
