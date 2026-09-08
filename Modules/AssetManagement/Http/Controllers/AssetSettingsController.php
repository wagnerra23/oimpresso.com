<?php

namespace Modules\AssetManagement\Http\Controllers;

use App\Business;
use App\NotificationTemplate;
use App\User;
use App\Utils\ModuleUtil;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Modules\AssetManagement\Utils\AssetUtil;

class AssetSettingsController extends Controller
{
    /**
     * All Utils instance.
     */
    protected $moduleUtil;

    protected $assetUtil;

    /**
     * Constructor
     */
    public function __construct(ModuleUtil $moduleUtil, AssetUtil $assetUtil)
    {
        $this->moduleUtil = $moduleUtil;
        $this->assetUtil = $assetUtil;
    }

    /**
     * Display a listing of the resource.
     *
     * MWART F3 (ADR 0104): passou a devolver Inertia. O `use Illuminate\Http\Response`
     * do topo continua servindo os demais metodos deste controller.
     *
     * @return \Inertia\Response
     */
    public function index(Request $request)
    {
        $business_id = request()->session()->get('user.business_id');

        $is_admin = $this->moduleUtil->is_admin(auth()->user());

        if (! (auth()->user()->can('superadmin') || ($this->moduleUtil->hasThePermissionInSubscription($business_id, 'assetmanagement_module'))) || ! $is_admin) {
            abort(403, 'Unauthorized action.');
        }

        $asset_settings = $this->assetUtil->getAssetSettings($business_id);

        $send_for_maintenance_template = NotificationTemplate::where('business_id',
                                            $business_id)
                                            ->where('template_for', 'send_for_maintenance')
                                            ->first();

        if (empty($send_for_maintenance_template)) {
            $send_for_maintenance_template['subject'] = 'Asset {asset_code} sent for maintaiaince';
            $send_for_maintenance_template['email_body'] =
                '<p>Asset {asset_code} sent for maintenance by {created_by}</p>
                <p>Maintenance ID: {maintenance_id}</p>
                <p>Status: {status}</p>
                <p>Priority: {priority}</p>
                <p>Details: {details}</p>';
        } else {
            $send_for_maintenance_template->toArray();
        }

        $assigned_for_maintenance_template = NotificationTemplate::where('business_id',
                                            $business_id)
                                            ->where('template_for', 'assigned_for_maintenance')
                                            ->first();

        if (empty($assigned_for_maintenance_template)) {
            $assigned_for_maintenance_template['subject'] = 'Asset {asset_code} assigned for maintaiaince';
            $assigned_for_maintenance_template['email_body'] =
                '<p>Asset {asset_code} assigned for maintenance</p>
                <p>Maintenance ID: {maintenance_id}</p>
                <p>Status: {status}</p>
                <p>Priority: {priority}</p>
                <p>Details: {details}</p>';
        } else {
            $assigned_for_maintenance_template->toArray();
        }

        $users = User::forDropdown($business_id, false);

        // MWART F3 (ADR 0104) — a tela virou Inertia; a URL NAO mudou (`settings.index`).
        // O `store()` abaixo segue INTOCADO: o contrato de gravacao e o mesmo do Blade,
        // inclusive o `$request->has()` dos checkboxes (ver RUNBOOK-configuracoes §8).
        return Inertia::render('Patrimonio/Configuracoes', [
            // Prefixos + interruptores + destinatarios, na forma EXATA em que o `store()`
            // regrava o JSON. Chave ausente = desligado/vazio — nao inventamos default aqui,
            // porque o default de verdade e a ausencia (o backend le com `!empty()`).
            'settings' => [
                'asset_code_prefix' => $asset_settings['asset_code_prefix'] ?? '',
                'allocation_code_prefix' => $asset_settings['allocation_code_prefix'] ?? '',
                'revoke_code_prefix' => $asset_settings['revoke_code_prefix'] ?? '',
                'asset_maintenance_prefix' => $asset_settings['asset_maintenance_prefix'] ?? '',
                // O typo `maintenence` e CONTRATO GRAVADO na coluna — nao se conserta aqui
                // (RUNBOOK §11). Vem como lista de ids de `users`.
                'send_for_maintenence_recipients' => array_values(array_map(
                    'strval',
                    (array) ($asset_settings['send_for_maintenence_recipients'] ?? [])
                )),
                'enable_asset_send_for_maintenance_email' => ! empty($asset_settings['enable_asset_send_for_maintenance_email']),
                'enable_asset_assigned_for_maintenance_email' => ! empty($asset_settings['enable_asset_assigned_for_maintenance_email']),
            ],

            // Os dois templates ja vem preenchidos com o texto-padrao quando nao existem no
            // banco (montado acima) — normalizados para array porque nesse caso sao array e
            // no outro sao Model.
            'templates' => [
                'send_for_maintenance' => [
                    'subject' => $send_for_maintenance_template['subject'] ?? '',
                    'email_body' => $send_for_maintenance_template['email_body'] ?? '',
                ],
                'assigned_for_maintenance' => [
                    'subject' => $assigned_for_maintenance_template['subject'] ?? '',
                    'email_body' => $assigned_for_maintenance_template['email_body'] ?? '',
                ],
            ],

            // DEFERIDA — e a UNICA prop desta tela que cresce com o tamanho do tenant
            // (RUNBOOK-inertia-defer-pattern). As outras sao um `value()` de coluna e dois
            // `first()`: deferir tambem essas so somaria um ida-e-volta pra economizar ~1ms.
            // `forDropdown` devolve [id => nome]; o React precisa de lista ordenavel.
            'usuarios' => Inertia::defer(fn () => collect($users)
                ->map(fn ($nome, $id) => ['id' => (string) $id, 'nome' => (string) $nome])
                ->values()
                ->all()),

            // As tags sao o contrato de `AssetUtil::replaceEmailTags()` e NAO coincidem entre
            // os dois blocos — vem do backend para nao virarem lista decorativa no front.
            'tags' => [
                'send_for_maintenance' => ['{asset_code}', '{created_by}', '{maintenance_id}', '{status}', '{priority}', '{maintenance_note}'],
                'assigned_for_maintenance' => ['{asset_code}', '{created_by}', '{maintenance_id}', '{status}', '{priority}', '{send_for_maintenance_details}'],
            ],
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        return view('assetmanagement::create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(Request $request)
    {
        $is_admin = $this->moduleUtil->is_admin(auth()->user());
        $business_id = request()->session()->get('user.business_id');

        if (! (auth()->user()->can('superadmin') || ($this->moduleUtil->hasThePermissionInSubscription($business_id, 'assetmanagement_module'))) || ! $is_admin) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $input = $request->only('asset_code_prefix', 'allocation_code_prefix', 'revoke_code_prefix', 'asset_maintenance_prefix', 'send_for_maintenence_recipients');

            if ($request->has('enable_asset_send_for_maintenance_email')) {
                $input['enable_asset_send_for_maintenance_email'] = 1;
            }

            if ($request->has('enable_asset_assigned_for_maintenance_email')) {
                $input['enable_asset_assigned_for_maintenance_email'] = 1;
            }

            Business::where('id', $business_id)
                ->update(['asset_settings' => json_encode($input)]);

            if (! empty($request->input('send_for_maintenance'))) {
                NotificationTemplate::updateOrCreate([
                    'business_id' => $business_id,
                    'template_for' => 'send_for_maintenance',
                ],
                    [
                        'email_body' => $request->input('send_for_maintenance')['email_body'],
                        'subject' => $request->input('send_for_maintenance')['subject'],
                    ]
                );
            }

            if (! empty($request->input('assigned_for_maintenance'))) {
                NotificationTemplate::updateOrCreate([
                    'business_id' => $business_id,
                    'template_for' => 'assigned_for_maintenance',
                ],
                    [
                        'email_body' => $request->input('assigned_for_maintenance')['email_body'],
                        'subject' => $request->input('assigned_for_maintenance')['subject'],
                    ]
                );
            }

            $output = ['success' => true,
                'msg' => __('lang_v1.updated_success'),
            ];
        } catch (Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.app(\App\Support\Privacy\PiiRedactor::class)->redact($e->getMessage()));

            $output = ['success' => false,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return redirect()->back()->with(['status' => $output]);
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
        return view('assetmanagement::edit');
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
