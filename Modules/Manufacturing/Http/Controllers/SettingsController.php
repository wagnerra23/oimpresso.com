<?php

namespace Modules\Manufacturing\Http\Controllers;

use App\Business;
use App\System;
use App\Utils\ModuleUtil;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Modules\Manufacturing\Concerns\LogsWithPiiRedactor;
use Modules\Manufacturing\Services\ProductionService;
use Modules\Manufacturing\Services\RecipeBomService;
use Modules\Manufacturing\Utils\ManufacturingUtil;

class SettingsController extends Controller
{
    use LogsWithPiiRedactor; // D7.a Wave 17 — wrap Log::emergency com PiiRedactor
    /**
     * All Utils instance.
     */
    protected $mfgUtil;

    protected $moduleUtil;

    /**
     * Constructor
     *
     * @param  ProductUtils  $product
     * @return void
     */
    public function __construct(ModuleUtil $moduleUtil, ManufacturingUtil $mfgUtil)
    {
        $this->moduleUtil = $moduleUtil;
        $this->mfgUtil = $mfgUtil;
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(ProductionService $productionService, RecipeBomService $bomService)
    {
        $business_id = request()->session()->get('user.business_id');
        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'manufacturing_module'))) {
            abort(403, 'Unauthorized action.');
        }

        // CUTOVER 2026-09-04 ([F], "módulo inteiro em produção, sem rotas alternativas"):
        // este endereço passa a servir a tela React. Mesmo padrão que `/manufacturing/recipe`
        // usa desde a US-MANU-001 — `?legacy=1` devolve o Blade antigo no MESMO endereço,
        // e NENHUMA rota foi removida (proibição §15.2 do handoff).
        //
        // Pré-condição medida antes de cortar: a regra "F5 CUTOVER sem aviso prévio cliente"
        // (proibicoes.md) nomeia a ROTA LIVRE — e [F] confirmou 2026-09-04 que ela não usa
        // Fabricação. Sem cliente na tela, a população da regra é vazia aqui.
        if (request()->boolean('legacy')) {
            $manufacturing_settings = $this->mfgUtil->getSettings($business_id);

            $version = System::getProperty('manufacturing_version');

            return view('manufacturing::settings.index')->with(compact('manufacturing_settings', 'version'));
        }

        return $this->indexV2($productionService, $bomService);
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
        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'manufacturing_module'))) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $settings = $request->only(['ref_no_prefix']);

            $settings['disable_editing_ingredient_qty'] = ! empty($request->input('disable_editing_ingredient_qty')) ? true : false;

            $settings['enable_updating_product_price'] = ! empty($request->input('enable_updating_product_price')) ? true : false;

            $business = Business::where('id', $business_id)
                                ->update(['manufacturing_settings' => json_encode($settings)]);

            $output = ['success' => 1,
                'msg' => __('lang_v1.updated_success'),
            ];
        } catch (\Exception $e) {
            $this->logSafeEmergency('settings.update', $e);

            $output = ['success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return redirect()->back()->with('status', $output);
    }

    /**
     * MWART US-MANU-003 (SPEC.md) — porte Inertia das Configurações do módulo. Rota
     * ADITIVA `/manufacturing/v2/settings`; `/manufacturing/settings` (Blade, acima)
     * segue intocado. Primeira tela da família que ESCREVE — o `store()` acima NÃO foi
     * alterado, o form novo posta no mesmo endpoint ({@see RUNBOOK-settings.md}).
     */
    public function indexV2(ProductionService $productionService, RecipeBomService $bomService)
    {
        $business_id = request()->session()->get('user.business_id');
        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'manufacturing_module'))) {
            abort(403, 'Unauthorized action.');
        }

        $manufacturing_settings = $this->mfgUtil->getSettings($business_id);
        $ordens = $productionService->summary($business_id);

        return Inertia::render('Manufacturing/Settings', [
            'settings' => [
                'ref_no_prefix' => $manufacturing_settings['ref_no_prefix'] ?? '',
                'disable_editing_ingredient_qty' => ! empty($manufacturing_settings['disable_editing_ingredient_qty']),
                'enable_updating_product_price' => ! empty($manufacturing_settings['enable_updating_product_price']),
            ],
            'version' => System::getProperty('manufacturing_version'),
            'permissions' => [
                'prod' => auth()->user()->can('manufacturing.access_production'),
            ],
            'producao' => [
                'total' => (int) $ordens['total_count'],
                'rascunhos' => (int) $ordens['pending_count'],
            ],
            'recipes_count' => $bomService->countRecipes($business_id),
        ]);
    }
}
