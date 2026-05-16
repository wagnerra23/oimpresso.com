<?php

namespace Modules\Repair\Http\Controllers;

use App\Brands;
use App\Business;
use App\BusinessLocation;
use App\Category;
use App\Contact;
use App\CustomerGroup;
use App\Media;
use App\Utils\CashRegisterUtil;
use App\Utils\ContactUtil;
use App\Utils\ModuleUtil;
use App\Utils\ProductUtil;
use App\Utils\Util;
use DB;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Modules\Repair\Entities\DeviceModel;
use Modules\Repair\Entities\JobSheet;
use Modules\Repair\Entities\RepairStatus;
use Modules\Repair\Utils\RepairUtil;
use Spatie\Activitylog\Models\Activity;
use Yajra\DataTables\Facades\DataTables;

class JobSheetController extends Controller
{
    /**
     * All Utils instance.
     */
    protected $repairUtil;

    protected $commonUtil;

    protected $cashRegisterUtil;

    protected $moduleUtil;

    protected $contactUtil;

    /**
     * Constructor
     *
     * @return void
     */
    public function __construct(RepairUtil $repairUtil, Util $commonUtil, CashRegisterUtil $cashRegisterUtil, ModuleUtil $moduleUtil,
        ContactUtil $contactUtil, ProductUtil $productUtil)
    {
        $this->repairUtil = $repairUtil;
        $this->commonUtil = $commonUtil;
        $this->cashRegisterUtil = $cashRegisterUtil;
        $this->moduleUtil = $moduleUtil;
        $this->contactUtil = $contactUtil;
        $this->productUtil = $productUtil;
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        $business_id = request()->session()->get('user.business_id');

        if (! (auth()->user()->can('superadmin') || ($this->moduleUtil->hasThePermissionInSubscription($business_id, 'repair_module') && (auth()->user()->can('job_sheet.view_assigned') || auth()->user()->can('job_sheet.view_all') || auth()->user()->can('job_sheet.create'))))) {
            abort(403, 'Unauthorized action.');
        }

        $is_user_admin = $this->commonUtil->is_admin(auth()->user(), $business_id);

        if (request()->ajax()) {
            $job_sheets = JobSheet::with('invoices')
                    ->leftJoin('contacts', 'repair_job_sheets.contact_id', '=', 'contacts.id')
                    ->leftJoin(
                        'repair_statuses AS rs',
                        'repair_job_sheets.status_id',
                        '=',
                        'rs.id'
                    )
                    ->leftJoin('users as technecian', 'repair_job_sheets.service_staff', '=', 'technecian.id')
                    ->leftJoin(
                        'repair_device_models as rdm',
                        'rdm.id',
                        '=',
                        'repair_job_sheets.device_model_id'
                    )
                    ->leftJoin(
                        'brands AS b',
                        'repair_job_sheets.brand_id',
                        '=',
                        'b.id'
                    )
                    ->leftJoin(
                        'business_locations AS bl',
                        'repair_job_sheets.location_id',
                        '=',
                        'bl.id'
                    )
                    ->leftJoin(
                        'categories as device',
                        'device.id',
                        '=',
                        'repair_job_sheets.device_id'
                    )
                    ->leftJoin('users', 'repair_job_sheets.created_by', '=', 'users.id')
                    ->where('repair_job_sheets.business_id', $business_id)
                    ->select('delivery_date', 'job_sheet_no', DB::raw("CONCAT(COALESCE(technecian.surname, ''),' ',COALESCE(technecian.first_name, ''),' ',COALESCE(technecian.last_name,'')) as technecian"), DB::raw("CONCAT(COALESCE(users.surname, ''),' ',COALESCE(users.first_name, ''),' ',COALESCE(users.last_name,'')) as added_by"), 'contacts.name as customer', 'b.name as brand', 'rdm.name as device_model', 'serial_no', 'estimated_cost', 'rs.name as status', 'repair_job_sheets.id as id', 'repair_job_sheets.created_at as created_at', 'service_type', 'rs.color as status_color', 'bl.name as location', 'rs.is_completed_status', 'device.name as device', 'repair_job_sheets.custom_field_1', 'repair_job_sheets.custom_field_2', 'repair_job_sheets.custom_field_3', 'repair_job_sheets.custom_field_4', 'repair_job_sheets.custom_field_5');

            //if user is not admin get only assgined/created_by job sheet
            if (! auth()->user()->can('job_sheet.view_all')) {
                if (! $is_user_admin) {
                    $user_id = auth()->user()->id;
                    $job_sheets->where(function ($query) use ($user_id) {
                        $query->where('repair_job_sheets.service_staff', $user_id)
                            ->orWhere('repair_job_sheets.created_by', $user_id);
                    });
                }
            }

            //if location is not all get only assgined location job sheet
            $permitted_locations = auth()->user()->permitted_locations();
            if ($permitted_locations != 'all') {
                $job_sheets->whereIn('repair_job_sheets.location_id', $permitted_locations);
            }

            //filter location
            if (! empty(request()->get('location_id'))) {
                $job_sheets->where('repair_job_sheets.location_id', request()->get('location_id'));
            }

            //filter by customer
            if (! empty(request()->contact_id)) {
                $job_sheets->where('repair_job_sheets.contact_id', request()->contact_id);
            }

            //filter by technecian
            if (! empty(request()->technician)) {
                $job_sheets->where('repair_job_sheets.service_staff', request()->technician);
            }

            //filter by status
            if (! empty(request()->status_id)) {
                $job_sheets->where('repair_job_sheets.status_id', request()->status_id);
            }

            //filter out mark as completed status
            if (request()->get('is_completed_status') === '1') {
                $job_sheets->where('rs.is_completed_status', 1);
            } else {
                $job_sheets->where(function ($q) {
                    $q->where('rs.is_completed_status', 0)
                        ->orWhereNull('rs.is_completed_status');
                });
            }

            return DataTables::of($job_sheets)
                ->addColumn('action', function ($row) {
                    $html = '<div class="btn-group">
                                <button class="btn btn-info dropdown-toggle btn-xs" type="button"  data-toggle="dropdown" aria-expanded="false">
                                    '.__('messages.action').'
                                    <span class="caret"></span>
                                    <span class="sr-only">
                                    '.__('messages.action').'
                                    </span>
                                </button>';

                    $html .= '<ul class="dropdown-menu dropdown-menu-left" role="menu">';

                    if (auth()->user()->can('job_sheet.view_assigned') || auth()->user()->can('job_sheet.view_all') || auth()->user()->can('job_sheet.create')) {
                        $html .= '<li>
                                <a href="'.action([\Modules\Repair\Http\Controllers\JobSheetController::class, 'show'], [$row->id]).'" class="cursor-pointer"><i class="fa fa-eye"></i> '.__('messages.view').'
                                </a>
                                </li>';
                    }

                    if (auth()->user()->can('repair.create')) {
                        $html .= '<li>
                                    <a href="'.action([\App\Http\Controllers\SellPosController::class, 'create']).'?sub_type=repair&job_sheet_id='.$row->id.'" class="cursor-pointer"><i class="fas fa-plus-circle"></i> '.__('repair::lang.add_invoice').'
                                    </a>
                                </li>';
                    }

                    if (auth()->user()->can('job_sheet.edit')) {
                        $html .= '<li>
                                    <a href="'.action([\Modules\Repair\Http\Controllers\JobSheetController::class, 'edit'], [$row->id]).'" class="cursor-pointer edit_job_sheet"><i class="fa fa-edit"></i> '.__('messages.edit').'
                                    </a>
                                </li>';

                        $html .= '<li>
                                    <a href="'.action([\Modules\Repair\Http\Controllers\JobSheetController::class, 'addParts'], [$row->id]).'" class="cursor-pointer">
                                        <i class="fas fa-toolbox"></i>
                                        '.__('repair::lang.add_parts').'
                                    </a>
                                </li>';

                        $html .= '<li>
                                    <a href="'.action([\Modules\Repair\Http\Controllers\JobSheetController::class, 'getUploadDocs'], [$row->id]).'" class="cursor-pointer">
                                        <i class="fas fa-file-alt"></i>
                                        '.__('repair::lang.upload_docs').'
                                    </a>
                                </li>';
                    }

                    $html .= '<li>
                                    <a href="'.action([\Modules\Repair\Http\Controllers\JobSheetController::class, 'print'], [$row->id]).'" target="_blank"><i class="fa fa-print"></i> '.__('messages.print').'
                                    </a>
                                </li>';

                    if (auth()->user()->can('job_sheet.create') || auth()->user()->can('job_sheet.edit')) {
                        $html .= '<li>
                                    <a data-href="'.action([\Modules\Repair\Http\Controllers\JobSheetController::class, 'editStatus'], [$row->id]).'" class="cursor-pointer edit_job_sheet_status">
                                        <i class="fa fa-edit"></i>
                                        '.__('repair::lang.change_status').'
                                    </a>
                                </li>';
                    }

                    if (auth()->user()->can('job_sheet.delete')) {
                        $html .= '<li>
                                    <a data-href="'.action([\Modules\Repair\Http\Controllers\JobSheetController::class, 'destroy'], [$row->id]).'"  id="delete_job_sheet" class="cursor-pointer">
                                        <i class="fas fa-trash"></i>
                                        '.__('messages.delete').'
                                    </a>
                                </li>';
                    }

                    $html .= '</ul>
                            </div>';

                    return $html;
                })
                ->editColumn('delivery_date',
                    '
                        @if($delivery_date)
                            {{@format_datetime($delivery_date)}}
                        @endif
                    '
                )
                ->editColumn('created_at',
                    '
                    {{@format_datetime($created_at)}}
                    '
                )
                ->editColumn('service_type', function ($row) {
                    return __('repair::lang.'.$row->service_type);
                })
                ->editColumn('estimated_cost', function ($row) {
                    $cost = '<span class="display_currency total-discount" data-currency_symbol="true" data-orig-value="'.$row->estimated_cost.'">'.$row->estimated_cost.'</span>';

                    return $cost;
                })
                ->editColumn('repair_no', function ($row) {
                    $invoice_no = [];
                    if ($row->invoices->count() > 0) {
                        foreach ($row->invoices as $key => $invoice) {
                            $invoice_no[] = $invoice->invoice_no;
                        }
                    }

                    $add_invoice = '';
                    if (auth()->user()->can('repair.create')) {
                        $add_invoice = '<br><a href="'.action([\App\Http\Controllers\SellPosController::class, 'create']).'?sub_type=repair&job_sheet_id='.$row->id.'" class="cursor-pointer" data-toggle="tooltip" title="'.__('repair::lang.add_invoice').'">
                                <i class="fas fa-plus-circle"></i>
                            </a>';
                    }

                    return implode(', ', $invoice_no).$add_invoice;
                })
                ->editColumn('status', function ($row) {
                    $html = '<a data-href="'.action([\Modules\Repair\Http\Controllers\JobSheetController::class, 'editStatus'], [$row->id]).'" class="edit_job_sheet_status cursor-pointer" data-orig-value="'.$row->status.'" data-status-name="'.$row->status.'">
                                <span class="label " style="background-color:'.$row->status_color.';" >
                                    '.$row->status.'
                                </span>
                            </a>
                        ';

                    return $html;
                })
                ->removeColumn('id')
                ->rawColumns(['action', 'service_type', 'delivery_date', 'repair_no', 'status', 'estimated_cost', 'created_at'])
                ->make(true);
        }

        $business_locations = BusinessLocation::forDropdown($business_id, false);
        $customers = Contact::customersDropdown($business_id, false);
        $status_dropdown = RepairStatus::forDropdown($business_id);
        $service_staffs = $this->commonUtil->serviceStaffDropdown($business_id);

        $user_role_as_service_staff = auth()->user()->roles()
                            ->where('is_service_staff', 1)
                            ->get()
                            ->toArray();
        $is_user_service_staff = false;
        if (! empty($user_role_as_service_staff) && ! $is_user_admin) {
            $is_user_service_staff = true;
        }

        $repair_settings = $this->repairUtil->getRepairSettings($business_id);

        // MWART-0002 (Sprint 2.5) — branch Inertia/React quando flag ativa.
        if ($this->mwartEnabled('repair_job_sheet_index', (int) $business_id)) {
            return Inertia::render('Repair/JobSheet/Index', [
                'filters' => [
                    'business_locations' => $business_locations,
                    'customers' => $customers,
                    'status_dropdown' => $status_dropdown,
                    'service_staffs' => $service_staffs,
                ],
                'flags' => [
                    'is_user_service_staff' => $is_user_service_staff,
                    'show_serial_no' => ! empty($repair_settings['show_serial_no_in_job_sheet_list']),
                    'enable_brand_in_job_sheet' => ! empty($repair_settings['enable_brand_in_job_sheet']),
                ],
                // Pipeline DataTables AJAX continua servindo blade legacy
                // até flag estabilizar — caminho idêntico ao MWART-0001 (PR #100).
                'datatable_url' => route('job-sheet.index'),
            ]);
        }

        return view('repair::job_sheet.index')
            ->with(compact('business_locations', 'customers', 'status_dropdown', 'service_staffs', 'is_user_service_staff', 'repair_settings'));
    }

    /**
     * MWART-0002 — verifica se flag MWART está habilitada pro business.
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
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        $business_id = request()->session()->get('user.business_id');

        if (! (auth()->user()->can('superadmin') || ($this->moduleUtil->hasThePermissionInSubscription($business_id, 'repair_module') && auth()->user()->can('job_sheet.create')))) {
            abort(403, 'Unauthorized action.');
        }

        $repair_statuses = RepairStatus::getRepairSatuses($business_id);
        $device_models = DeviceModel::forDropdown($business_id);
        $brands = Brands::forDropdown($business_id, false, true);
        $devices = Category::forDropdown($business_id, 'device');
        $repair_settings = $this->repairUtil->getRepairSettings($business_id);
        $business_locations = BusinessLocation::forDropdown($business_id);
        $types = Contact::getContactTypes();
        $customer_groups = CustomerGroup::forDropdown($business_id);
        $walk_in_customer = $this->contactUtil->getWalkInCustomer($business_id);
        $default_status = '';
        if (! empty($repair_settings['default_status'])) {
            $default_status = $repair_settings['default_status'];
        }

        //get service staff(technecians)
        $technecians = [];
        if ($this->commonUtil->isModuleEnabled('service_staff')) {
            $technecians = $this->commonUtil->serviceStaffDropdown($business_id);
        }

        // Wave 3 B6 MWART — branch Inertia (canary biz=1).
        if ($this->mwartEnabled('repair_job_sheet_create', (int) $business_id)) {
            return Inertia::render('Repair/JobSheet/Create', [
                'options' => Inertia::defer(fn () => [
                    'repair_statuses' => (array) $repair_statuses,
                    'device_models' => (array) $device_models,
                    'brands' => (array) $brands,
                    'devices' => (array) $devices,
                    'technecians' => (array) $technecians,
                    'business_locations' => (array) $business_locations,
                    'repair_settings' => is_array($repair_settings) ? $repair_settings : [],
                ]),
                'walk_in_customer' => $walk_in_customer ? [
                    'id' => (int) $walk_in_customer->id,
                    'name' => $walk_in_customer->name,
                ] : null,
                'default_status' => $default_status !== '' ? $default_status : '',
            ]);
        }

        return view('repair::job_sheet.create')
            ->with(compact('repair_statuses', 'device_models', 'brands', 'devices', 'default_status', 'technecians', 'business_locations', 'types', 'customer_groups', 'walk_in_customer', 'repair_settings'));
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

        if (! (auth()->user()->can('superadmin') || ($this->moduleUtil->hasThePermissionInSubscription($business_id, 'repair_module') && auth()->user()->can('job_sheet.create')))) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $input = $request->only('contact_id', 'service_type', 'brand_id', 'device_id', 'device_model_id', 'security_pwd', 'security_pattern', 'serial_no', 'status_id', 'delivery_date', 'estimated_cost', 'product_configuration', 'defects', 'product_condition', 'service_staff', 'location_id', 'pick_up_on_site_addr', 'comment_by_ss', 'custom_field_1', 'custom_field_2', 'custom_field_3', 'custom_field_4', 'custom_field_5');

            if (! empty($input['delivery_date'])) {
                $input['delivery_date'] = $this->commonUtil->uf_date($input['delivery_date'], true);
            }

            if (! empty($input['estimated_cost'])) {
                $input['estimated_cost'] = $this->commonUtil->num_uf($input['estimated_cost']);
            }

            if (! empty($request->input('repair_checklist'))) {
                $input['checklist'] = $request->input('repair_checklist');
            }

            DB::beginTransaction();

            //Generate reference number
            $ref_count = $this->commonUtil->setAndGetReferenceCount('job_sheet', $business_id);
            $business = Business::find($business_id);
            $repair_settings = json_decode($business->repair_settings, true);

            $job_sheet_prefix = '';
            if (isset($repair_settings['job_sheet_prefix'])) {
                $job_sheet_prefix = $repair_settings['job_sheet_prefix'];
            }

            $input['job_sheet_no'] = $this->commonUtil->generateReferenceNumber('job_sheet', $ref_count, null, $job_sheet_prefix);

            $input['created_by'] = $request->user()->id;
            $input['business_id'] = $business_id;

            $job_sheet = JobSheet::create($input);

            //upload media
            Media::uploadMedia($business_id, $job_sheet, $request, 'images');

            if (! empty($request->input('send_notification')) && in_array('sms', $request->input('send_notification'))) {
                $status = RepairStatus::where('business_id', $business_id)
                            ->find($job_sheet->status_id);
                if (! empty($status->sms_template)) {
                    $this->repairUtil->sendJobSheetUpdateSmsNotification($status->sms_template, $job_sheet);
                }
            }

            if (! empty($request->input('send_notification')) && in_array('email', $request->input('send_notification'))) {
                $status = RepairStatus::where('business_id', $business_id)
                            ->find($job_sheet->status_id);
                $notification = [
                    'subject' => $status->email_subject,
                    'body' => $status->email_body,
                ];

                //Set email configuration
                $notificationUtil = new \App\Utils\NotificationUtil();
                $notificationUtil->configureEmail();

                if (! empty($status->email_subject) && ! empty($status->email_body)) {
                    $this->repairUtil->sendJobSheetUpdateEmailNotification($notification, $job_sheet);
                }
            }

            DB::commit();

            if (! empty($request->input('submit_type')) && $request->input('submit_type') == 'save_and_add_parts') {
                return redirect()
                ->action([\Modules\Repair\Http\Controllers\JobSheetController::class, 'addParts'], [$job_sheet->id])
                ->with('status', ['success' => true,
                    'msg' => __('lang_v1.success'), ]);
            } elseif (! empty($request->input('submit_type')) && $request->input('submit_type') == 'save_and_upload_docs') {
                return redirect()
                    ->action([\Modules\Repair\Http\Controllers\JobSheetController::class, 'getUploadDocs'], [$job_sheet->id])
                    ->with('status', ['success' => true, 'msg' => __('lang_v1.success')]);
            }

            return redirect()
                ->action([\Modules\Repair\Http\Controllers\JobSheetController::class, 'show'], [$job_sheet->id])
                ->with('status', ['success' => true,
                    'msg' => __('lang_v1.success'), ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()
                ->with('status', ['success' => false,
                    'msg' => __('messages.something_went_wrong'),
                ]);
        }
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

        if (! (auth()->user()->can('superadmin') || ($this->moduleUtil->hasThePermissionInSubscription($business_id, 'repair_module') && (auth()->user()->can('job_sheet.view_assigned') || auth()->user()->can('job_sheet.view_all') || auth()->user()->can('job_sheet.create'))))) {
            abort(403, 'Unauthorized action.');
        }

        // Eager-load media (legacy) + arquivos (backbone) para o accessor `anexos` escolher a fonte.
        $query = JobSheet::with('customer',
                        'customer.business', 'technician',
                        'status', 'Brand', 'Device', 'deviceModel', 'businessLocation', 'invoices', 'media', 'arquivos')
                        ->where('business_id', $business_id);

        //if user is not admin or didn't have permission `job_sheet.view_all` get only assgined/created_by job sheet
        if (! ($this->commonUtil->is_admin(auth()->user(), $business_id) || auth()->user()->can('job_sheet.view_all'))) {
            $user_id = auth()->user()->id;
            $query->where(function ($q) use ($user_id) {
                $q->where('repair_job_sheets.service_staff', $user_id)
                    ->orWhere('repair_job_sheets.created_by', $user_id);
            });
        }

        $job_sheet = $query->findOrFail($id);

        $parts = $job_sheet->getPartsUsed();

        $business = Business::find($business_id);
        $repair_settings = json_decode($business->repair_settings, true);
        $jobsheet_settings = ! empty($business->repair_jobsheet_settings) ?
        json_decode($business->repair_jobsheet_settings, true) : [];

        $activities = Activity::forSubject($job_sheet)
           ->with(['causer', 'subject'])
           ->latest()
           ->get();

        // Coleção unificada via accessor — view usa $anexos em vez de $job_sheet->media
        $anexos = $job_sheet->anexos;

        // Wave 3 B6 MWART — branch Inertia (canary biz=1).
        if ($this->mwartEnabled('repair_job_sheet_show', (int) $business_id)) {
            return Inertia::render('Repair/JobSheet/Show', [
                'job_sheet' => $this->buildJobSheetShowPayload($job_sheet),
                'parts' => Inertia::defer(fn () => $this->buildJobSheetPartsPayload($parts)),
                'activities' => Inertia::defer(fn () => $this->buildJobSheetActivitiesPayload($activities)),
                'anexos' => Inertia::defer(fn () => $this->buildJobSheetAnexosPayload($anexos)),
                'fsm' => [
                    'in_pipeline' => $job_sheet->current_stage_id !== null,
                    'endpoints' => [
                        'actions' => "/api/repair/job-sheets/{$job_sheet->id}/fsm-actions",
                        'execute' => "/repair/job-sheets/{$job_sheet->id}/fsm-action",
                        'start_pipeline' => "/repair/job-sheets/{$job_sheet->id}/fsm-start-pipeline",
                    ],
                ],
                'permissions' => [
                    'edit' => (bool) auth()->user()->can('job_sheet.edit'),
                    'delete' => (bool) auth()->user()->can('job_sheet.delete'),
                    'print' => true,
                ],
            ]);
        }

        return view('repair::job_sheet.show')
            ->with(compact('job_sheet', 'repair_settings', 'parts', 'activities', 'jobsheet_settings', 'anexos'));
    }

    /**
     * Wave 3 B6 — payload de JobSheet pra Inertia Show.
     */
    private function buildJobSheetShowPayload(JobSheet $job_sheet): array
    {
        $currencySymbol = request()->session()->get('business.currency_symbol') ?? 'R$';

        return [
            'id' => (int) $job_sheet->id,
            'job_sheet_no' => $job_sheet->job_sheet_no,
            'contact_id' => $job_sheet->contact_id ? (int) $job_sheet->contact_id : null,
            'contact_name' => optional($job_sheet->customer)?->name,
            'service_type' => $job_sheet->service_type,
            'brand_name' => optional($job_sheet->Brand)?->name,
            'device_name' => optional($job_sheet->Device)?->name,
            'device_model_name' => optional($job_sheet->deviceModel)?->name,
            'serial_no' => $job_sheet->serial_no,
            'security_pwd' => $job_sheet->security_pwd,
            'security_pattern' => $job_sheet->security_pattern,
            'delivery_date' => optional($job_sheet->delivery_date)?->format('Y-m-d'),
            'estimated_cost' => $job_sheet->estimated_cost ? (float) $job_sheet->estimated_cost : null,
            'estimated_cost_formatted' => $job_sheet->estimated_cost
                ? $currencySymbol . ' ' . number_format((float) $job_sheet->estimated_cost, 2, ',', '.')
                : null,
            'defects' => $job_sheet->defects,
            'product_condition' => $job_sheet->product_condition,
            'product_configuration' => $job_sheet->product_configuration,
            'status' => [
                'id' => $job_sheet->status_id ? (int) $job_sheet->status_id : null,
                'name' => optional($job_sheet->status)?->name,
                'color' => optional($job_sheet->status)?->color,
            ],
            'technician' => $job_sheet->service_staff ? [
                'id' => (int) $job_sheet->service_staff,
                'name' => trim((optional($job_sheet->technician)?->first_name ?? '').' '.(optional($job_sheet->technician)?->last_name ?? '')),
            ] : ['id' => null, 'name' => null],
            'business_location' => [
                'id' => $job_sheet->location_id ? (int) $job_sheet->location_id : null,
                'name' => optional($job_sheet->businessLocation)?->name,
            ],
            'comment_by_ss' => $job_sheet->comment_by_ss,
            'checklist' => is_array($job_sheet->checklist) ? $job_sheet->checklist : null,
            'current_stage_id' => $job_sheet->current_stage_id ? (int) $job_sheet->current_stage_id : null,
            'created_at' => optional($job_sheet->created_at)?->toIso8601String(),
            'updated_at' => optional($job_sheet->updated_at)?->toIso8601String(),
        ];
    }

    private function buildJobSheetPartsPayload($parts): array
    {
        if (! is_array($parts) && ! ($parts instanceof \Illuminate\Support\Collection)) {
            return [];
        }
        return collect($parts)->map(function ($p) {
            return [
                'id' => (int) ($p->id ?? $p['id'] ?? 0),
                'variation_name' => $p->variation_name ?? $p['variation_name'] ?? null,
                'quantity' => (float) ($p->quantity ?? $p['quantity'] ?? 0),
                'unit_price' => isset($p->unit_price) ? (float) $p->unit_price : (isset($p['unit_price']) ? (float) $p['unit_price'] : null),
                'unit' => $p->unit ?? $p['unit'] ?? null,
            ];
        })->toArray();
    }

    private function buildJobSheetActivitiesPayload($activities): array
    {
        if (! $activities) {
            return [];
        }
        return collect($activities)->take(50)->map(function ($a) {
            return [
                'id' => (int) $a->id,
                'description' => $a->description,
                'causer' => optional($a->causer)?->first_name ?? null,
                'created_at' => optional($a->created_at)?->toIso8601String(),
            ];
        })->values()->toArray();
    }

    private function buildJobSheetAnexosPayload($anexos): array
    {
        if (! $anexos) {
            return [];
        }
        return collect($anexos)->map(function ($a) {
            return [
                'id' => (int) ($a->id ?? 0),
                'url' => method_exists($a, 'getUrl') ? $a->getUrl() : ($a->url ?? ''),
                'name' => $a->name ?? $a->file_name ?? '—',
                'mime' => $a->mime_type ?? null,
            ];
        })->values()->toArray();
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

        if (! (auth()->user()->can('superadmin') || ($this->moduleUtil->hasThePermissionInSubscription($business_id, 'repair_module') && auth()->user()->can('job_sheet.edit')))) {
            abort(403, 'Unauthorized action.');
        }

        $job_sheet = JobSheet::where('business_id', $business_id)
                    ->findOrFail($id);

        $repair_statuses = RepairStatus::getRepairSatuses($business_id);
        $device_models = DeviceModel::forDropdown($business_id);
        $brands = Brands::forDropdown($business_id, false, true);
        $devices = Category::forDropdown($business_id, 'device');
        $repair_settings = $this->repairUtil->getRepairSettings($business_id);
        $types = Contact::getContactTypes();
        $customer_groups = CustomerGroup::forDropdown($business_id);
        $default_status = '';
        if (! empty($repair_settings['default_status'])) {
            $default_status = $repair_settings['default_status'];
        }

        //get service staff(technecians)
        $technecians = [];
        if ($this->commonUtil->isModuleEnabled('service_staff')) {
            $technecians = $this->commonUtil->serviceStaffDropdown($business_id);
        }

        // Wave 3 B6 MWART — branch Inertia (canary biz=1).
        if ($this->mwartEnabled('repair_job_sheet_edit', (int) $business_id)) {
            return Inertia::render('Repair/JobSheet/Edit', [
                'job_sheet' => $this->buildJobSheetEditPayload($job_sheet),
                'options' => Inertia::defer(fn () => $this->buildJobSheetEditOptions($business_id, $repair_statuses, $device_models, $brands, $devices, $technecians, $customer_groups, $repair_settings)),
            ]);
        }

        return view('repair::job_sheet.edit')
            ->with(compact('job_sheet', 'repair_statuses', 'device_models', 'brands', 'devices', 'default_status', 'technecians', 'types', 'customer_groups', 'repair_settings'));
    }

    /**
     * Wave 3 B6 — payload pra Inertia Edit.
     */
    private function buildJobSheetEditPayload(JobSheet $job_sheet): array
    {
        return [
            'id' => (int) $job_sheet->id,
            'job_sheet_no' => $job_sheet->job_sheet_no,
            'contact_id' => $job_sheet->contact_id ? (int) $job_sheet->contact_id : null,
            'service_type' => $job_sheet->service_type,
            'brand_id' => $job_sheet->brand_id ? (int) $job_sheet->brand_id : null,
            'device_id' => $job_sheet->device_id ? (int) $job_sheet->device_id : null,
            'device_model_id' => $job_sheet->device_model_id ? (int) $job_sheet->device_model_id : null,
            'security_pwd' => $job_sheet->security_pwd,
            'security_pattern' => $job_sheet->security_pattern,
            'serial_no' => $job_sheet->serial_no,
            'status_id' => $job_sheet->status_id ? (int) $job_sheet->status_id : null,
            'delivery_date' => optional($job_sheet->delivery_date)?->format('Y-m-d'),
            'estimated_cost' => $job_sheet->estimated_cost ? (float) $job_sheet->estimated_cost : null,
            'product_configuration' => $job_sheet->product_configuration,
            'defects' => $job_sheet->defects,
            'product_condition' => $job_sheet->product_condition,
            'service_staff' => $job_sheet->service_staff ? (int) $job_sheet->service_staff : null,
            'pick_up_on_site_addr' => $job_sheet->pick_up_on_site_addr,
            'comment_by_ss' => $job_sheet->comment_by_ss,
            'custom_field_1' => $job_sheet->custom_field_1,
            'custom_field_2' => $job_sheet->custom_field_2,
            'custom_field_3' => $job_sheet->custom_field_3,
            'custom_field_4' => $job_sheet->custom_field_4,
            'custom_field_5' => $job_sheet->custom_field_5,
            'checklist' => is_array($job_sheet->checklist) ? $job_sheet->checklist : null,
        ];
    }

    private function buildJobSheetEditOptions(int $business_id, $repair_statuses, $device_models, $brands, $devices, $technecians, $customer_groups, $repair_settings): array
    {
        return [
            'repair_statuses' => (array) $repair_statuses,
            'device_models' => (array) $device_models,
            'brands' => (array) $brands,
            'devices' => (array) $devices,
            'technecians' => (array) $technecians,
            'customer_groups' => (array) $customer_groups,
            'repair_settings' => is_array($repair_settings) ? $repair_settings : [],
        ];
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

        if (! (auth()->user()->can('superadmin') || ($this->moduleUtil->hasThePermissionInSubscription($business_id, 'repair_module') && auth()->user()->can('job_sheet.edit')))) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $input = $request->only('contact_id', 'service_type', 'brand_id', 'device_id', 'device_model_id', 'security_pwd', 'security_pattern', 'serial_no', 'status_id', 'delivery_date', 'estimated_cost', 'product_configuration', 'defects', 'product_condition', 'service_staff', 'pick_up_on_site_addr', 'comment_by_ss', 'custom_field_1', 'custom_field_2', 'custom_field_3', 'custom_field_4', 'custom_field_5');

            if (! empty($input['delivery_date'])) {
                $input['delivery_date'] = $this->commonUtil->uf_date($input['delivery_date'], true);
            }

            if (! empty($input['estimated_cost'])) {
                $input['estimated_cost'] = $this->commonUtil->num_uf($input['estimated_cost']);
            }

            if (! empty($request->input('repair_checklist'))) {
                $input['checklist'] = $request->input('repair_checklist');
            } else {
                $input['checklist'] = [];
            }

            DB::beginTransaction();

            $job_sheet = JobSheet::where('business_id', $business_id)
                            ->findOrFail($id);

            $oldStatusId = $job_sheet->status_id;

            $job_sheet->update($input);

            //upload media
            Media::uploadMedia($business_id, $job_sheet, $request, 'images');

            if (! empty($request->input('send_notification')) && in_array('sms', $request->input('send_notification'))) {
                $status = RepairStatus::where('business_id', $business_id)
                            ->find($job_sheet->status_id);
                if (! empty($status->sms_template)) {
                    $this->repairUtil->sendJobSheetUpdateSmsNotification($status->sms_template, $job_sheet);
                }
            }

            if (! empty($request->input('send_notification')) && in_array('email', $request->input('send_notification'))) {
                $status = RepairStatus::where('business_id', $business_id)
                            ->find($job_sheet->status_id);
                $notification = [
                    'subject' => $status->email_subject,
                    'body' => $status->email_body,
                ];

                //Set email configuration
                $notificationUtil = new \App\Utils\NotificationUtil();
                $notificationUtil->configureEmail();

                if (! empty($status->email_subject) && ! empty($status->email_body)) {
                    $this->repairUtil->sendJobSheetUpdateEmailNotification($notification, $job_sheet);
                }
            }

            DB::commit();

            // Plug Whatsapp (US-WA-004 / ADR Repair tech/0001) — quando status muda,
            // dispara RepairStatusChanged event que NotifyRepairCustomer Listener
            // (Modules/Whatsapp) traduz pra SendWhatsappMessageJob com template
            // configurado em whatsapp_business_configs.template_repair_*.
            // Listener faz no-op silencioso se business sem WhatsappBusinessConfig.
            if ((int) $oldStatusId !== (int) $job_sheet->status_id) {
                $newStatusEntity = RepairStatus::where('business_id', $business_id)
                    ->find($job_sheet->status_id);
                if ($newStatusEntity !== null) {
                    event(new \Modules\Repair\Events\RepairStatusChanged(
                        $job_sheet,
                        (string) $newStatusEntity->name,
                    ));
                }
            }

            if (! empty($request->input('submit_type')) && $request->input('submit_type') == 'save_and_add_parts') {
                return redirect()
                ->action([\Modules\Repair\Http\Controllers\JobSheetController::class, 'addParts'], [$job_sheet->id])
                ->with('status', ['success' => true,
                    'msg' => __('lang_v1.success'), ]);
            } elseif (! empty($request->input('submit_type')) && $request->input('submit_type') == 'save_and_upload_docs') {
                return redirect()
                    ->action([\Modules\Repair\Http\Controllers\JobSheetController::class, 'getUploadDocs'], [$job_sheet->id])
                    ->with('status', ['success' => true, 'msg' => __('lang_v1.success')]);
            }

            return redirect()
                ->action([\Modules\Repair\Http\Controllers\JobSheetController::class, 'show'], [$job_sheet->id])
                ->with('status', ['success' => true,
                    'msg' => __('lang_v1.success'), ]);
        } catch (\Exception $e) {
            DB::rollBack();

            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            return redirect()->back()
                ->with('status', ['success' => false,
                    'msg' => __('messages.something_went_wrong'),
                ]);
        }
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

        if (! (auth()->user()->can('superadmin') || ($this->moduleUtil->hasThePermissionInSubscription($business_id, 'repair_module') && auth()->user()->can('job_sheet.delete')))) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            try {
                $job_sheet = JobSheet::where('business_id', $business_id)
                    ->findOrFail($id);

                $job_sheet->delete();
                $job_sheet->media()->delete();

                $output = ['success' => true,
                    'msg' => __('lang_v1.success'),
                ];
            } catch (\Exception $e) {
                $output = ['success' => false,
                    'msg' => __('messages.something_went_wrong'),
                ];
            }

            return $output;
        }
    }

    /**
     * Show the form for editing the status
     *
     * @param  int  $id
     * @return Response
     */
    public function editStatus($id)
    {
        $business_id = request()->session()->get('user.business_id');

        if (! (auth()->user()->can('superadmin') || ($this->moduleUtil->hasThePermissionInSubscription($business_id, 'repair_module') && (auth()->user()->can('job_sheet.create') || auth()->user()->can('job_sheet.edit'))))) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            $job_sheet = JobSheet::where('business_id', $business_id)->with(['status'])->findOrFail($id);

            $status_dropdown = RepairStatus::forDropdown($business_id, true);
            $status_template_tags = $this->repairUtil->getRepairStatusTemplateTags();

            return view('repair::job_sheet.partials.edit_status')
                ->with(compact('job_sheet', 'status_dropdown', 'status_template_tags'));
        }
    }

    private function updateJobsheetStatus($input, $jobsheet_id)
    {
        $job_sheet = JobSheet::where('business_id', $input['business_id'])->findOrFail($jobsheet_id);
        $job_sheet->status_id = $input['status_id'];
        $job_sheet->save();

        $status = RepairStatus::where('business_id', $input['business_id'])->findOrFail($input['status_id']);

        //send job sheet updates
        if (! empty($input['send_sms'])) {
            $sms_body = $input['sms_body'];
            $response = $this->repairUtil->sendJobSheetUpdateSmsNotification($sms_body, $job_sheet);
        }

        if (! empty($input['send_email'])) {
            $subject = $input['email_subject'];
            $body = $input['email_body'];
            $notification = [
                'subject' => $subject,
                'body' => $body,
            ];

            //Set email configuration
            $notificationUtil = new \App\Utils\NotificationUtil();
            $notificationUtil->configureEmail();

            if (! empty($subject) && ! empty($body)) {
                $this->repairUtil->sendJobSheetUpdateEmailNotification($notification, $job_sheet);
            }
        }

        activity()
            ->performedOn($job_sheet)
            ->withProperties(['update_note' => $input['update_note'], 'updated_status' => $status->name])
            ->log('status_changed');
    }

    public function updateStatus(Request $request, $id)
    {
        $business_id = request()->session()->get('user.business_id');

        if (! (auth()->user()->can('superadmin') || ($this->moduleUtil->hasThePermissionInSubscription($business_id, 'repair_module') && (auth()->user()->can('job_sheet.create') || auth()->user()->can('job_sheet.edit'))))) {
            abort(403, 'Unauthorized action.');
        }

        if ($request->ajax()) {
            try {
                $input = $request->only([
                    'status_id',
                    'update_note',
                ]);

                $input['business_id'] = $business_id;

                if (! empty($request->input('send_sms'))) {
                    $input['send_sms'] = true;
                    $input['sms_body'] = $request->input('sms_body');
                }
                if (! empty($request->input('send_email'))) {
                    $input['send_email'] = true;
                    $input['email_body'] = $request->input('email_body');
                    $input['email_subject'] = $request->input('email_subject');
                }
                $status_id = $request->input('status_id');

                $status = RepairStatus::find($status_id);

                if ($status->is_completed_status == 1) {
                    $input['job_sheet_id'] = $id;
                    $request->session()->put('repair_status_update_data', $input);

                    return $output = ['success' => true];
                }

                $this->updateJobsheetStatus($input, $id);

                $output = ['success' => true,
                    'msg' => __('lang_v1.success'),
                ];
            } catch (Exception $e) {
                $output = ['success' => false,
                    'msg' => __('messages.something_went_wrong'),
                ];
            }

            return $output;
        }
    }

    public function deleteJobSheetImage(Request $request, $id)
    {
        $business_id = request()->session()->get('user.business_id');

        if (! (auth()->user()->can('superadmin') || ($this->moduleUtil->hasThePermissionInSubscription($business_id, 'repair_module') && (auth()->user()->can('job_sheet.view_assigned') || auth()->user()->can('job_sheet.view_all') || auth()->user()->can('job_sheet.create'))))) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            try {
                Media::deleteMedia($business_id, $id);

                $output = ['success' => true,
                    'msg' => __('lang_v1.success'),
                ];
            } catch (\Exception $e) {
                $output = ['success' => false,
                    'msg' => __('messages.something_went_wrong'),
                ];
            }

            return $output;
        }
    }

    public function addParts($id)
    {
        $business_id = request()->session()->get('user.business_id');

        if (! (auth()->user()->can('superadmin') || ($this->moduleUtil->hasThePermissionInSubscription($business_id, 'repair_module') && (auth()->user()->can('job_sheet.create') || auth()->user()->can('job_sheet.edit'))))) {
            abort(403, 'Unauthorized action.');
        }

        $status_update_data = request()->session()->get('repair_status_update_data');

        $job_sheet = JobSheet::where('business_id', $business_id)->findOrFail($id);

        $parts = $job_sheet->getPartsUsed();

        $status_dropdown = RepairStatus::forDropdown($business_id, true);
        $status_template_tags = $this->repairUtil->getRepairStatusTemplateTags();

        // Wave 3 B6 MWART — branch Inertia (canary biz=1).
        if ($this->mwartEnabled('repair_job_sheet_add_parts', (int) $business_id)) {
            return Inertia::render('Repair/JobSheet/AddParts', [
                'job_sheet' => [
                    'id' => (int) $job_sheet->id,
                    'job_sheet_no' => $job_sheet->job_sheet_no,
                    'contact_name' => optional($job_sheet->customer)?->name,
                ],
                'parts' => $this->buildJobSheetPartsPayload($parts),
                'status_update_data' => $status_update_data,
                'status_dropdown' => (array) $status_dropdown,
                'status_template_tags' => is_array($status_template_tags) ? $status_template_tags : [],
            ]);
        }

        return view('repair::job_sheet.add_parts')
            ->with(compact('job_sheet', 'parts', 'status_update_data', 'status_dropdown', 'status_template_tags'));
    }

    public function saveParts(Request $request, $id)
    {
        $business_id = request()->session()->get('user.business_id');

        if (! (auth()->user()->can('superadmin') || ($this->moduleUtil->hasThePermissionInSubscription($business_id, 'repair_module') && (auth()->user()->can('job_sheet.create') || auth()->user()->can('job_sheet.edit'))))) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $parts = $request->input('parts');
            $job_sheet = JobSheet::where('business_id', $business_id)->findOrFail($id);
            $job_sheet->parts = ! empty($parts) ? $parts : null;
            $job_sheet->save();

            if (! empty($request->session()->get('repair_status_update_data')) && ! empty($request->input('status_id'))) {
                $input = $request->only([
                    'status_id',
                    'update_note',
                ]);

                $input['business_id'] = $business_id;

                if (! empty($request->input('send_sms'))) {
                    $input['send_sms'] = true;
                    $input['sms_body'] = $request->input('sms_body');
                }
                if (! empty($request->input('send_email'))) {
                    $input['send_email'] = true;
                    $input['email_body'] = $request->input('email_body');
                    $input['email_subject'] = $request->input('email_subject');
                }

                $this->updateJobsheetStatus($input, $job_sheet->id);

                $request->session()->forget('repair_status_update_data');
            }

            $output = ['success' => true,
                'msg' => __('lang_v1.success'),
            ];
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());
        }

        return redirect()
                ->action([\Modules\Repair\Http\Controllers\JobSheetController::class, 'show'], [$job_sheet->id])
                ->with('status', ['success' => true,
                    'msg' => __('lang_v1.success'), ]);
    }

    public function jobsheetPartRow(Request $request)
    {
        if (request()->ajax()) {
            $variation_id = $request->input('variation_id');

            $business_id = $request->session()->get('user.business_id');
            $product = $this->productUtil->getDetailsFromVariation($variation_id, $business_id);

            $variation_name = $product->product_name.' - '.$product->sub_sku;
            $variation_id = $product->variation_id;
            $quantity = 1;
            $unit = $product->unit;

            return view('repair::job_sheet.partials.job_sheet_part_row')
            ->with(compact('variation_name', 'variation_id', 'quantity', 'unit'));
        }
    }

    /**
     * Show the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function print($id)
    {
        $business_id = request()->session()->get('user.business_id');

        if (! (auth()->user()->can('superadmin') || ($this->moduleUtil->hasThePermissionInSubscription($business_id, 'repair_module') && (auth()->user()->can('job_sheet.view_assigned') || auth()->user()->can('job_sheet.view_all') || auth()->user()->can('job_sheet.create'))))) {
            abort(403, 'Unauthorized action.');
        }

        // Eager-load media (legacy) + arquivos (backbone) — apenas pra manter consistência eager-load.
        $query = JobSheet::with('customer',
                        'customer.business', 'technician',
                        'status', 'Brand', 'Device', 'deviceModel', 'businessLocation', 'invoices', 'media', 'arquivos')
                        ->where('business_id', $business_id);

        //if user is not admin or didn't have permission `job_sheet.view_all` get only assgined/created_by job sheet
        if (! ($this->commonUtil->is_admin(auth()->user(), $business_id) || auth()->user()->can('job_sheet.view_all'))) {
            $user_id = auth()->user()->id;
            $query->where(function ($q) use ($user_id) {
                $q->where('repair_job_sheets.service_staff', $user_id)
                    ->orWhere('repair_job_sheets.created_by', $user_id);
            });
        }

        $job_sheet = $query->findOrFail($id);

        $parts = $job_sheet->getPartsUsed();

        $business = Business::find($business_id);
        $repair_settings = json_decode($business->repair_settings, true);

        $jobsheet_settings = ! empty($business->repair_jobsheet_settings) ?
        json_decode($business->repair_jobsheet_settings, true) : [];

        $html = view('repair::job_sheet.print_pdf')
            ->with(compact('job_sheet', 'repair_settings', 'parts', 'jobsheet_settings'))->render();
        $mpdf = new \Mpdf\Mpdf(['tempDir' => public_path('uploads/temp'),
            'mode' => 'utf-8',
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
            'autoVietnamese' => true,
            'autoArabic' => true,
            'margin_top' => 8,
            'margin_bottom' => 8,
        ]);
        $mpdf->useSubstitutions = true;
        $mpdf->SetTitle(__('repair::lang.job_sheet').' | '.$job_sheet->job_sheet_no);
        $mpdf->WriteHTML($html);
        $mpdf->Output('job_sheet.pdf', 'I');

        return view('repair::job_sheet.print_pdf')
            ->with(compact('job_sheet', 'repair_settings', 'parts'));
    }

    /**
     * Print label.
     *
     * @param  int  $id
     * @return Response
     */
    public function printLabel($id)
    {
        $business_id = request()->session()->get('user.business_id');

        if (! (auth()->user()->can('superadmin') || ($this->moduleUtil->hasThePermissionInSubscription($business_id, 'repair_module') && (auth()->user()->can('job_sheet.view_assigned') || auth()->user()->can('job_sheet.view_all') || auth()->user()->can('job_sheet.create'))))) {
            abort(403, 'Unauthorized action.');
        }

        $query = JobSheet::with(
            'customer',
            'customer.business',
            'technician',
            'status',
            'Brand',
            'Device',
            'deviceModel',
            'businessLocation',
            'createdBy'
        )
            ->where('business_id', $business_id);

        //if user is not admin or didn't have permission `job_sheet.view_all` get only assgined/created_by job sheet
        if (! ($this->commonUtil->is_admin(auth()->user(), $business_id) || auth()->user()->can('job_sheet.view_all'))) {
            $user_id = auth()->user()->id;
            $query->where(function ($q) use ($user_id) {
                $q->where('repair_job_sheets.service_staff', $user_id)
                    ->orWhere('repair_job_sheets.created_by', $user_id);
            });
        }

        $job_sheet = $query->findOrFail($id);

        $business = Business::find($business_id);
        $repair_settings = json_decode($business->repair_settings, true);

        $jobsheet_settings = ! empty($business->repair_jobsheet_settings) ?
            json_decode($business->repair_jobsheet_settings, true) : [];

        $label_width = isset($jobsheet_settings['label_width']) ? $jobsheet_settings['label_width'] : 75;
        $label_height = isset($jobsheet_settings['label_height']) ? $jobsheet_settings['label_height'] : 50;

        $html = view('repair::job_sheet.print_label')
        ->with(compact('job_sheet', 'repair_settings', 'jobsheet_settings'))->render();
        $mpdf = new \Mpdf\Mpdf([
            'format' => [$label_width, $label_height],
            'tempDir' => public_path('uploads/temp'),
            'mode' => 'utf-8',
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
            'autoVietnamese' => true,
            'autoArabic' => true,
            'margin_top' => 4,
            'margin_left' => 4,
            'margin_right' => 4,
            'margin_bottom' => 4,
        ]);
        $mpdf->useSubstitutions = true;
        $mpdf->SetTitle(__('repair::lang.job_sheet_label').' | '.$job_sheet->job_sheet_no);
        $mpdf->WriteHTML($html);
        $mpdf->Output('job_sheet_label.pdf', 'I');
    }

    public function getUploadDocs($id)
    {
        $business_id = request()->session()->get('user.business_id');

        if (! (auth()->user()->can('superadmin') || ($this->moduleUtil->hasThePermissionInSubscription($business_id, 'repair_module') && (auth()->user()->can('job_sheet.create') || auth()->user()->can('job_sheet.edit'))))) {
            abort(403, 'Unauthorized action.');
        }

        // Carrega media (legacy) + arquivos (backbone ADR 0123 §2) pro accessor `anexos`
        // escolher a fonte correta. Eager-load ambas relações para evitar N+1 na view.
        $job_sheet = JobSheet::with(['media', 'arquivos'])
                        ->where('business_id', $business_id)
                        ->findOrFail($id);

        // Passa coleção unificada via accessor — view usa $anexos em vez de $job_sheet->media
        $anexos = $job_sheet->anexos;

        return view('repair::job_sheet.upload_doc', compact('job_sheet', 'anexos'));
    }

    public function postUploadDocs(Request $request)
    {
        $business_id = request()->session()->get('user.business_id');

        if (! (auth()->user()->can('superadmin') || ($this->moduleUtil->hasThePermissionInSubscription($business_id, 'repair_module') && (auth()->user()->can('job_sheet.create') || auth()->user()->can('job_sheet.edit'))))) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $images = json_decode($request->input('images'), true);

            $job_sheet = JobSheet::where('business_id', $business_id)
                        ->findOrFail($request->input('job_sheet_id'));

            if (! empty($images) && ! empty($job_sheet)) {
                Media::attachMediaToModel($job_sheet, $business_id, $images);
            }

            $output = ['success' => true,
                'msg' => __('lang_v1.success'),
            ];
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());
            $output = ['success' => false,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return redirect()
            ->action([\Modules\Repair\Http\Controllers\JobSheetController::class, 'show'], [$job_sheet->id])
            ->with('status', ['success' => true,
                'msg' => __('lang_v1.success'), ]);
    }
}
