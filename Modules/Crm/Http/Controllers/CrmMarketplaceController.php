<?php

namespace Modules\Crm\Http\Controllers;

use App\Category;
use App\User;
use App\Utils\Util;
use DB;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Modules\Crm\Entities\CrmContact;
use Modules\Crm\Entities\CrmMarketplace;
use Modules\Crm\Entities\Schedule;
use Modules\Crm\Utils\CrmUtil;
use App\Support\Privacy\PiiRedactor;
use App\Services\FeatureFlagService;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class CrmMarketplaceController extends Controller
{
    /** Tela React do Marketplace B2B (RUNBOOK-marketplace). `?classico=1` mantém a Blade. */
    private const FLAG_V2 = 'useV2CrmMarketplace';

    /**
     * All Utils instance.
     */
    protected $commonUtil;

    public function __construct(Util $commonUtil, CrmUtil $crmUtil)
    {
        $this->commonUtil = $commonUtil;
        $this->crmUtil = $crmUtil;
        $this->enable_b2b_marketplace = config('constants.enable_b2b_marketplace');
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        $business_id = request()->session()->get('user.business_id');
        $is_admin = $this->commonUtil->is_admin(auth()->user());

        if (! $is_admin || ! $this->enable_b2b_marketplace) {
            abort(403, 'Unauthorized action.');
        }

        $marketplace = CrmMarketplace::first();
        $sources = Category::forDropdown($business_id, 'source');

        $users = User::forDropdown($business_id, false);

        if (! request()->boolean('classico') && app(FeatureFlagService::class)->isOn(self::FLAG_V2, ['business_id' => (int) $business_id])) {
            $opcao = fn ($lista) => collect($lista)->map(fn ($label, $id) => ['value' => (string) $id, 'label' => (string) $label])->values()->all();

            return Inertia::render('Crm/Marketplace/Index', [
                'config' => $marketplace ? [
                    'chave' => (string) $marketplace->site_key,
                    'email' => (string) $marketplace->site_id,
                    'usuarios' => array_map('strval', (array) $marketplace->assigned_users),
                    'fonte' => $marketplace->crm_source_id ? (string) $marketplace->crm_source_id : '',
                ] : null,
                'usuarios' => $opcao($users),
                'fontes' => $opcao($sources),
            ]);
        }

        return view('crm::marketplace.index')->with(compact('marketplace', 'users', 'sources'));
    }

    /**
     * Saves marketplace details
     *
     * @return Response
     */
    public function save(Request $request)
    {
        $is_admin = $this->commonUtil->is_admin(auth()->user());

        if (! $is_admin || ! $this->enable_b2b_marketplace) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        // RUNBOOK-marketplace §11: usuários e fonte só do negócio da sessão — a importação cria lead e
        // acompanhamento atribuídos a eles (ADR 0093).
        $request->validate([
            'site_key' => ['required', 'string', 'max:191'],
            'site_id' => ['required', 'email', 'max:191'],
            'assigned_users' => ['required', 'array', 'min:1'],
            'assigned_users.*' => ['integer', Rule::exists('users', 'id')->where('business_id', $business_id)],
            'crm_source_id' => ['nullable', 'integer', Rule::exists('categories', 'id')
                ->where('business_id', $business_id)->where('category_type', 'source')],
        ]);

        CrmMarketplace::updateOrCreate(
            [
                'business_id' => $business_id,
                'marketplace' => $request->input('marketplace'),
            ],
            [
                'site_key' => $request->input('site_key'),
                'site_id' => $request->input('site_id'),
                'assigned_users' => $request->input('assigned_users'),
                'crm_source_id' => $request->input('crm_source_id'),
            ]
        );

        return redirect()->back()->with('status', ['success' => 1, 'msg' => __('lang_v1.updated_success')]);
    }

    /**
     * Fetches leads from api and creates leads and followups
     *
     * @param  Request  $request
     * @return Response
     */
    public function importLeads(Request $request)
    {
        $is_admin = $this->commonUtil->is_admin(auth()->user());

        if (! $is_admin || ! $this->enable_b2b_marketplace) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $business_id = request()->session()->get('user.business_id');
            $marketplace = CrmMarketplace::first();
            if (empty($marketplace)) {
                return redirect()->back()->with('status', ['success' => 0, 'msg' => 'Configure o marketplace (chave e e-mail) antes de importar leads.']);
            }

            $url = 'https://my.exportersindia.com/api-inquiry-detail.php?k='.$marketplace->site_key.'&email='.$marketplace->site_id;

            $response = file_get_contents($url);

            $data = json_decode($response, true);

            DB::beginTransaction();
            foreach ($data as $user_data) {
                if (empty($user_data['email'])) {
                    continue;
                }

                //check if email exists
                $lead = $this->buscarLeadPorEmail((int) $business_id, $user_data['email']);

                //if lead don't exist create one
                if (empty($lead)) {
                    $name = $user_data['name'];
                    $name_array = explode(' ', $name, 2);
                    $lead_data = [
                        'type' => 'lead',
                        'business_id' => $business_id,
                        'name' => $name,
                        'first_name' => $name_array[0] ?? '',
                        'last_name' => $name_array[1] ?? '',
                        'email' => $user_data['email'],
                        'supplier_business_name' => $user_data['company'] ?? '',
                        'city' => $user_data['city'] ?? '',
                        'country' => $user_data['country'] ?? '',
                        'state' => $user_data['state'] ?? '',
                        'mobile' => $user_data['mobile'] ?? '',
                        'created_by' => auth()->user()->id,
                        'crm_source' => $marketplace->crm_source_id,
                    ];

                    $lead = CrmContact::createNewLead($lead_data, $marketplace->assigned_users);
                }

                //check if same follow up exists, if not create new
                $inq_id = $user_data['inq_id'] ?? '';

                if (! empty($user_data['inq_id'])) {
                    $schedule = Schedule::where('business_id', $business_id)
                                        ->where('contact_id', $lead->id)
                                        ->where('title', 'like', "%{$inq_id}%")
                                        ->first();

                    if (! empty($schedule)) {
                        continue;
                    }
                }

                $subject = $user_data['subject'] ?? '';
                $product = $user_data['product'] ?? '';
                $detail_req = $user_data['detail_req'] ?? '';

                $title = 'ExportersIndia - '.$subject.' - '.$lead->name." (Inquiry id: {$inq_id})";

                $description = 'Subject: '.$subject;
                $description .= '<br>';

                $description .= 'Product: '.$product;
                $description .= '<br>';
                $description .= $detail_req;

                $follow_up_data = [
                    'business_id' => $business_id,
                    'contact_id' => $lead->id,
                    'title' => $title,
                    'status' => 'open',
                    'start_datetime' => \Carbon::now()->toDateTimeString(),
                    'end_datetime' => \Carbon::now()->addHours(1)->toDateTimeString(),
                    'schedule_type' => 'call',
                    'description' => $description,
                    'user_id' => $marketplace->assigned_users,
                ];

                $this->crmUtil->addFollowUp($follow_up_data, auth()->user());
            }

            DB::commit();

            $output = [
                'success' => true,
                'msg' => __('lang_v1.success'),
            ];
        } catch (\Exception $e) {
            DB::rollBack();

            // D7 LGPD: redaciona PII em mensagens de erro antes de logar (marketplace assigna users).
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.app(PiiRedactor::class)->redact($e->getMessage()));

            $output = [
                'success' => false,
                // RUNBOOK-marketplace §11: o detalhe da exceção fica no log (redigido), não na tela.
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return redirect()->back()->with(['status' => $output]);
    }

    /**
     * Lead já existente com este e-mail NESTA empresa (Tier 0, ADR 0093): o mesmo e-mail pode
     * existir em outra empresa e não pode ser reaproveitado aqui.
     */
    protected function buscarLeadPorEmail(int $business_id, string $email): ?CrmContact
    {
        return CrmContact::where('business_id', $business_id)
            ->where('email', $email)
            ->first();
    }
}
