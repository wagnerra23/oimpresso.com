<?php

namespace Modules\Crm\Http\Controllers;

use App\Business;
use App\Services\FeatureFlagService;
use App\Utils\ModuleUtil;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Modules\Crm\Utils\CrmUtil;
use App\Support\Privacy\PiiRedactor;

class CrmSettingsController extends Controller
{
    /**
     * Flag do caminho React (thread crm/playbook/15, F3). Convenção `useV2<Modulo><Tela>`.
     * Sem a chave no GrowthBook o FeatureFlagService cai no fallbackDefaults, que não a lista:
     * default OFF, a Blade segue servindo. Ligar é toggle no GrowthBook (flag:set), não deploy.
     *
     * @see memory/requisitos/Crm/RUNBOOK-configuracoes.md
     */
    private const FLAG_V2 = 'useV2CrmConfiguracoes';

    /**
     * All Utils instance.
     */
    protected $crmUtil;

    protected $moduleUtil;

    /**
     * Constructor
     *
     * @return void
     */
    public function __construct(CrmUtil $crmUtil, ModuleUtil $moduleUtil)
    {
        $this->crmUtil = $crmUtil;
        $this->moduleUtil = $moduleUtil;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Contracts\View\View|\Inertia\Response
     */
    public function index()
    {
        $business_id = request()->session()->get('user.business_id');

        $is_admin = $this->moduleUtil->is_admin(auth()->user());

        if (! (auth()->user()->can('superadmin') || ($this->moduleUtil->hasThePermissionInSubscription($business_id, 'crm_module')) && $is_admin)) {
            abort(403, 'Unauthorized action.');
        }

        $crm_settings = $this->crmUtil->getCrmSettings($business_id);

        // Thread 15 (F3): a tela React grava pelo MESMO `update-settings` abaixo. Props eager: é uma
        // linha da tabela business, já lida acima para a Blade. `?classico=1` mantém a Blade.
        if (! request()->boolean('classico') && app(FeatureFlagService::class)->isOn(self::FLAG_V2, ['business_id' => $business_id])) {
            return Inertia::render('Crm/Configuracoes/Index', [
                'configuracoes' => [
                    'ativar_pedido' => ! empty($crm_settings['enable_order_request']),
                    'prefixo' => (string) ($crm_settings['order_request_prefix'] ?? ''),
                ],
            ]);
        }

        return view('crm::settings.index')
                ->with(compact('crm_settings'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function updateSettings(Request $request)
    {
        $business_id = request()->session()->get('user.business_id');

        $is_admin = $this->moduleUtil->is_admin(auth()->user());

        if (! (auth()->user()->can('superadmin') || ($this->moduleUtil->hasThePermissionInSubscription($business_id, 'crm_module')) && $is_admin)) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $input = $request->only(['order_request_prefix']);

            if ($request->has('enable_order_request')) {
                $input['enable_order_request'] = 1;
            }

            Business::where('id', $business_id)
                        ->update(['crm_settings' => json_encode($input)]);

            $output = ['success' => true,
                'msg' => __('lang_v1.updated_success'),
            ];
        } catch (\Exception $e) {
            // D7 LGPD: redaciona PII em mensagens de erro antes de logar (settings podem ter campos sensíveis).
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.app(PiiRedactor::class)->redact($e->getMessage()));

            $output = ['success' => false,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return redirect()->back()->with(['status' => $output]);
    }
}
