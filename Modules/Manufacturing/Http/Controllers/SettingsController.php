<?php

namespace Modules\Manufacturing\Http\Controllers;

use App\Business;
use App\System;
use App\Utils\ModuleUtil;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Modules\Manufacturing\Concerns\LogsWithPiiRedactor;
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
    public function index()
    {
        $business_id = request()->session()->get('user.business_id');
        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'manufacturing_module'))) {
            abort(403, 'Unauthorized action.');
        }
        $manufacturing_settings = $this->mfgUtil->getSettings($business_id);

        $version = System::getProperty('manufacturing_version');

        return view('manufacturing::settings.index')->with(compact('manufacturing_settings', 'version'));
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
}
