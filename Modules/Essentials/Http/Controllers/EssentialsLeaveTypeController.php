<?php

namespace Modules\Essentials\Http\Controllers;

use App\Utils\ModuleUtil;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Modules\Essentials\Entities\EssentialsLeave;
use Modules\Essentials\Entities\EssentialsLeaveType;
use Yajra\DataTables\Facades\DataTables;

class EssentialsLeaveTypeController extends Controller
{
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
    public function __construct(ModuleUtil $moduleUtil)
    {
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

        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'essentials_module'))) {
            abort(403, 'Unauthorized action.');
        }

        if (! auth()->user()->can('essentials.crud_leave_type')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            $leave_types = EssentialsLeaveType::where('business_id', $business_id)
                        ->select(['leave_type', 'max_leave_count', 'id']);

            return Datatables::of($leave_types)
                ->addColumn(
                    'action',
                    '<button data-href="{{action(\'\Modules\Essentials\Http\Controllers\EssentialsLeaveTypeController@edit\', [$id])}}" class="btn btn-xs btn-primary btn-modal" data-container=".view_modal"><i class="glyphicon glyphicon-edit"></i> @lang("messages.edit")</button>'
                )
                ->removeColumn('id')
                ->rawColumns([2])
                ->make(false);
        }

        return view('essentials::leave_type.index');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        if (! auth()->user()->can('essentials.crud_leave_type')) {
            abort(403, 'Unauthorized action.');
        }

        return view('essentials::create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(Request $request)
    {
        $business_id = $request->session()->get('user.business_id');

        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'essentials_module'))) {
            abort(403, 'Unauthorized action.');
        }

        if (! auth()->user()->can('essentials.crud_leave_type')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $input = $request->only(['leave_type', 'max_leave_count', 'leave_count_interval']);

            $input['business_id'] = $business_id;

            EssentialsLeaveType::create($input);
            $output = ['success' => true,
                'msg' => __('lang_v1.added_success'),
            ];
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => false,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return $output;
    }

    /**
     * Show the specified resource.
     *
     * @return Response
     */
    public function show()
    {
        return view('essentials::show');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @return Response
     */
    public function edit($id)
    {
        $business_id = request()->session()->get('user.business_id');

        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'essentials_module'))) {
            abort(403, 'Unauthorized action.');
        }

        if (! auth()->user()->can('essentials.crud_leave_type')) {
            abort(403, 'Unauthorized action.');
        }

        $leave_type = EssentialsLeaveType::where('business_id', $business_id)
                                        ->find($id);

        return view('essentials::leave_type.edit')->with(compact('leave_type'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function update(Request $request, $id)
    {
        $business_id = $request->session()->get('user.business_id');
        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'essentials_module'))) {
            abort(403, 'Unauthorized action.');
        }

        if (! auth()->user()->can('essentials.crud_leave_type')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $input = $request->only(['leave_type', 'max_leave_count',
                'leave_count_interval', ]);

            $input['business_id'] = $business_id;

            EssentialsLeaveType::where('business_id', $business_id)
                            ->where('id', $id)
                            ->update($input);

            $output = ['success' => true,
                'msg' => __('lang_v1.updated_success'),
            ];
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => false,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return $output;
    }

    /**
     * Remove the specified resource from storage.
     *
     * Antes deste método o corpo era vazio (nem recebia $id): a rota
     * `DELETE /hrm/leave-type/{id}` do Route::resource existia e devolvia 200 sem
     * apagar nada. Achado A4 do F1 do Cowork (`hrm-page.jsx`, Nota "Tipo de licença
     * não pode ser excluído" — "o cadastro só cresce").
     *
     * Guarda de uso: `essentials_leaves.essentials_leave_type_id` é `integer index`
     * SEM constraint de FK (migration 2019_05_17_175921) — o banco não recusa nada,
     * então apagar um tipo em uso deixaria licença órfã apontando pro nada. A contagem
     * é feita aqui e devolvida no 422 pra dizer QUANTAS licenças travam.
     *
     * @param  int  $id
     * @return Response
     */
    public function destroy($id)
    {
        $business_id = request()->session()->get('user.business_id');

        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'essentials_module'))) {
            abort(403, 'Unauthorized action.');
        }

        if (! auth()->user()->can('essentials.crud_leave_type')) {
            abort(403, 'Unauthorized action.');
        }

        // Tier 0 (ADR 0093): o id chega CRU da rota — resolver dentro do business.
        // Tipo de outro tenant é 404, nunca "não travado".
        $leave_type = EssentialsLeaveType::where('business_id', $business_id)
                                        ->find($id);

        if (empty($leave_type)) {
            abort(404);
        }

        $licencas = EssentialsLeave::where('business_id', $business_id)
                                ->where('essentials_leave_type_id', $leave_type->id)
                                ->count();

        if ($licencas > 0) {
            return response()->json([
                'success' => false,
                'msg' => __('essentials::lang.leave_type_em_uso', ['count' => $licencas]),
                'blocked_by' => ['leaves' => $licencas],
            ], 422);
        }

        try {
            $leave_type->delete();

            $output = ['success' => true,
                'msg' => __('lang_v1.deleted_success'),
            ];
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => false,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return $output;
    }
}
