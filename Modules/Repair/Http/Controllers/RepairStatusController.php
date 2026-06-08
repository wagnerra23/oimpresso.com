<?php

namespace Modules\Repair\Http\Controllers;

use App\Utils\ModuleUtil;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Modules\Repair\Concerns\LogsWithPiiRedactor;
use Modules\Repair\Entities\RepairStatus;
use Modules\Repair\Utils\RepairUtil;
use Yajra\DataTables\Facades\DataTables;

class RepairStatusController extends Controller
{
    use LogsWithPiiRedactor; // D7.a Wave 17 — wrap Log::emergency com PiiRedactor
    /**
     * All Utils instance.
     */
    protected $moduleUtil;

    /**
     * Constructor
     *
     * @param  ProductUtils  $product
     * @return void
     */
    public function __construct(ModuleUtil $moduleUtil, RepairUtil $repairUtil)
    {
        $this->moduleUtil = $moduleUtil;
        $this->repairUtil = $repairUtil;
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        $business_id = request()->session()->get('user.business_id');

        if (! (auth()->user()->can('superadmin') || ($this->moduleUtil->hasThePermissionInSubscription($business_id, 'repair_module') && auth()->user()->can('repair_status.access')))) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            $statuses = RepairStatus::where('business_id', $business_id)
                        ->select(['name', 'color', 'id', 'sort_order', 'is_completed_status']);

            return Datatables::of($statuses)
                ->editColumn('name', '
                    {{$name}}
                    @if($is_completed_status)
                        &nbsp;
                        <span data-toggle="tooltip" title="@lang(\'repair::lang.marked_as_completed\')">
                            <i class="fa fas fa-check-circle"></i>
                        </span>
                    @endif
                ')
                ->editColumn('color', '{{$color}} <b><span style="color: {{$color}};" >&bull;</span></b>')
                ->addColumn(
                    'action',
                    '<button data-href="{{action(\'\Modules\Repair\Http\Controllers\RepairStatusController@edit\', [$id])}}" class="btn btn-xs btn-primary btn-modal" data-container=".view_modal"><i class="glyphicon glyphicon-edit"></i> @lang("messages.edit")</button>'
                )
                ->removeColumn(['id', 'is_completed_status'])
                ->rawColumns([0, 1, 3])
                ->make(false);
        }

        // MWART-0002 (Sprint 2.5) — branch Inertia/React quando flag ativa.
        // Caminho Blade legacy continua intacto (Settings page inclui status/index.blade.php).
        if ($this->mwartEnabled('repair_status_index', (int) $business_id)) {
            return Inertia::render('Repair/Status/Index', [
                'statuses' => RepairStatus::where('business_id', $business_id)
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->get(['id', 'name', 'color', 'sort_order', 'is_completed_status']),
            ]);
        }

        // Default: caminho null — view é renderizada por settings (includeIf).
    }

    /**
     * MWART-0002 — verifica se flag MWART está habilitada pro business atual.
     * Mesmo padrão do RepairController::mwartEnabled.
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

        if (! (auth()->user()->can('superadmin') || ($this->moduleUtil->hasThePermissionInSubscription($business_id, 'repair_module') && auth()->user()->can('repair_status.access')))) {
            abort(403, 'Unauthorized action.');
        }

        $status_template_tags = $this->repairUtil->getRepairStatusTemplateTags();

        return view('repair::status.create')
            ->with(compact('status_template_tags'));
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

        if (! (auth()->user()->can('superadmin') || ($this->moduleUtil->hasThePermissionInSubscription($business_id, 'repair_module') && auth()->user()->can('repair_status.access')))) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $input = $request->only(['name', 'color', 'sort_order',
                'sms_template', 'email_subject', 'email_body', ]);
            $input['is_completed_status'] = ! empty($request->get('is_completed_status')) ? 1 : 0;
            $input['business_id'] = $business_id;

            $status = RepairStatus::create($input);

            $output = ['success' => true,
                'msg' => __('lang_v1.added_success'),
            ];
        } catch (\Exception $e) {
            $this->logSafeEmergency('repair_status', $e); // D7.a Wave 17 LGPD

            $output = ['success' => false,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return $output;
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @return Response
     */
    public function edit($id)
    {
        $business_id = request()->session()->get('user.business_id');

        if (! (auth()->user()->can('superadmin') || ($this->moduleUtil->hasThePermissionInSubscription($business_id, 'repair_module') && auth()->user()->can('repair_status.access')))) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            $status = RepairStatus::where('business_id', $business_id)->find($id);
            $status_template_tags = $this->repairUtil->getRepairStatusTemplateTags();

            return view('repair::status.edit')
                ->with(compact('status', 'status_template_tags'));
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function update(Request $request, $id)
    {
        $business_id = request()->session()->get('user.business_id');

        if (! (auth()->user()->can('superadmin') || ($this->moduleUtil->hasThePermissionInSubscription($business_id, 'repair_module') && auth()->user()->can('repair_status.access')))) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            try {
                $input = $request->only(['name', 'color', 'sort_order',
                    'sms_template', 'email_subject', 'email_body', ]);
                $input['is_completed_status'] = ! empty($request->get('is_completed_status')) ? 1 : 0;
                $status = RepairStatus::where('business_id', $business_id)->findOrFail($id);
                $status->update($input);

                $output = ['success' => true,
                    'msg' => __('lang_v1.updated_success'),
                ];
            } catch (\Exception $e) {
                $this->logSafeEmergency('repair_status', $e); // D7.a Wave 17 LGPD

                $output = ['success' => false,
                    'msg' => __('messages.something_went_wrong'),
                ];
            }

            return $output;
        }
    }
}
