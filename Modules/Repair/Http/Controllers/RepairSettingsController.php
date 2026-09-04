<?php

namespace Modules\Repair\Http\Controllers;

use App\Barcode;
use App\Brands;
use App\Business;
use App\Category;
use App\Utils\ModuleUtil;
use App\Variation;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Modules\Repair\Concerns\LogsWithPiiRedactor;
use Modules\Repair\Entities\RepairStatus;
use Modules\Repair\Utils\RepairUtil;

class RepairSettingsController extends Controller
{
    use LogsWithPiiRedactor; // D7.a Wave 17 — wrap Log::emergency com PiiRedactor
    /**
     * All Utils instance.
     */
    protected $repairUtil;

    protected $moduleUtil;

    /**
     * Constructor
     *
     * @param  RepairUtil  $repairUtil
     * @return void
     */
    public function __construct(RepairUtil $repairUtil, ModuleUtil $moduleUtil)
    {
        $this->repairUtil = $repairUtil;
        $this->moduleUtil = $moduleUtil;
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        $business_id = request()->session()->get('user.business_id');

        if (! (auth()->user()->can('superadmin') || ($this->moduleUtil->hasThePermissionInSubscription($business_id, 'repair_module') && auth()->user()->can('repair.create')))) {
            abort(403, 'Unauthorized action.');
        }

        $barcode_settings = Barcode::where('business_id', $business_id)
                                ->orWhereNull('business_id')
                                ->pluck('name', 'id');

        $repair_settings = $this->repairUtil->getRepairSettings($business_id);

        $jobsheet_pdf_settings = $this->repairUtil->getJobsheetPdfSettings($business_id);

        $default_product_name = __('repair::lang.no_default_product_selected');
        if (! empty($repair_settings['default_product'])) {
            $default_product = Variation::where('id', $repair_settings['default_product'])
                        ->with(['product_variation', 'product'])
                        ->first();

            $default_product_name = $default_product->product->type == 'single' ? $default_product->product->name.' - '.$default_product->product->sku : $default_product->product->name.' ('.$default_product->name.') - '.$default_product->sub_sku;
        }

        //barcode types
        $barcode_types = $this->moduleUtil->barcode_types();
        $repair_statuses = RepairStatus::getRepairSatuses($business_id);

        $brands = Brands::forDropdown($business_id, false, true);
        $devices = Category::forDropdown($business_id, 'device');
        $module_category_data = $this->moduleUtil->getTaxonomyData('device');

        // Onda 1 do export Repair (2026-09-04) — coexistência opt-in MWART (ADR 0104).
        // Flag OFF (default) => Blade legado intacto. O cutover é decisão [W] após
        // smoke real (R1), nunca efeito colateral de deploy.
        if ($this->mwartEnabled('repair_settings_index', (int) $business_id)) {
            // `contact_custom_fields` e `custom_labels` são consumidas pela aba de
            // impressão e NÃO existiam no compact() do Blade — o partial
            // (jobsheet_settings_tab.blade.php:56) dereferencia
            // $contact_custom_fields sem que ninguém a defina. Varredura contada no
            // repo: nenhum View::share. Aqui elas passam a ser fornecidas de fato.
            // `(string)` de propósito: `session(...)` volta null em sessão nova, e passar null
            // pro json_decode é deprecado no PHP 8.1+ (viraria warning em prod, não erro).
            $custom_labels = json_decode((string) session('business.custom_labels'), true) ?: [];
            $contact_custom_fields = $jobsheet_pdf_settings['contact_custom_fields'] ?? [];
            if (! is_array($contact_custom_fields)) {
                $contact_custom_fields = [];
            }

            return Inertia::render('Repair/Settings/Index', [
                'barcodeSettings' => $barcode_settings,
                'repairSettings' => (object) $repair_settings,
                'defaultProductName' => $default_product_name,
                'barcodeTypes' => $barcode_types,
                'repairStatuses' => $repair_statuses,
                'jobsheetPdfSettings' => (object) $jobsheet_pdf_settings,
                'contactCustomFields' => array_values($contact_custom_fields),
                'customLabels' => $custom_labels,
            ]);
        }

        return view('repair::settings.index')
                ->with(compact('barcode_settings', 'repair_settings', 'default_product_name',
                'barcode_types', 'repair_statuses', 'brands', 'devices',
                'module_category_data', 'jobsheet_pdf_settings'));
    }

    /**
     * Coexistência MWART (ADR 0104): flag global + whitelist opcional por business.
     * Espelha DeviceModelController::mwartEnabled — mesmo contrato, mesma leitura.
     */
    private function mwartEnabled(string $key, int $business_id): bool
    {
        if (! config("mwart.{$key}.enabled")) {
            return false;
        }

        $beta = (array) config("mwart.{$key}.business_ids", []);

        return empty($beta) || in_array($business_id, $beta, true);
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

        if (! (auth()->user()->can('superadmin') || ($this->moduleUtil->hasThePermissionInSubscription($business_id, 'repair_module') && auth()->user()->can('repair.create')))) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $input = $request->only(['barcode_id', 'default_product', 'barcode_type', 'repair_tc_condition', 'job_sheet_prefix', 'problem_reported_by_customer', 'product_condition', 'product_configuration', 'job_sheet_custom_field_1', 'job_sheet_custom_field_2', 'job_sheet_custom_field_3', 'job_sheet_custom_field_4', 'job_sheet_custom_field_5', 'default_repair_checklist']);

            $default_status = $request->get('default_status');
            if (! empty($default_status) && is_numeric($default_status)) {
                $input['default_status'] = $default_status;
            } else {
                $input['default_status'] = '';
            }
            Business::where('id', $business_id)
                        ->update(['repair_settings' => json_encode($input)]);

            $output = ['success' => true,
                'msg' => __('lang_v1.updated_success'),
            ];
        } catch (\Exception $e) {
            $this->logSafeEmergency('repair_settings', $e); // D7.a Wave 17 LGPD

            $output = ['success' => false,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return redirect()->back()->with(['status' => $output]);
    }

    public function updateJobsheetSettings(Request $request)
    {
        $business_id = request()->session()->get('user.business_id');

        if (! (auth()->user()->can('superadmin') ||
        ($this->moduleUtil->hasThePermissionInSubscription($business_id, 'repair_module') && auth()->user()->can('repair.create')))) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $input = $request->only(['customer_label', 'client_id_label', 'client_tax_label', 'label_width', 'label_height']);

            $checkboxes = ['contact_custom_fields', 'show_customer', 'show_client_id',
                'show_customer_name_in_label', 'show_customer_address_in_label', 'show_customer_phone_in_label',
                'show_customer_alt_phone_in_label', 'show_customer_email_in_label', 'show_sales_person_in_label',
                'show_barcode_in_label', 'show_status_in_label', 'show_due_date_in_label', 'show_technician_in_label',
                'show_problem_in_label', 'show_sr_no_in_label', 'show_brand_in_label', 'show_location_in_label',
                'show_password_in_label', ];
            foreach ($checkboxes as $checkbox) {
                if ($request->has($checkbox)) {
                    $input[$checkbox] = $request->input($checkbox);
                }
            }

            Business::where('id', $business_id)
                        ->update(['repair_jobsheet_settings' => json_encode($input)]);

            $output = ['success' => true,
                'msg' => __('lang_v1.updated_success'),
            ];
        } catch (\Exception $e) {
            $this->logSafeEmergency('repair_settings', $e); // D7.a Wave 17 LGPD

            $output = ['success' => false,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return redirect()->back()->with(['status' => $output]);
    }
}
