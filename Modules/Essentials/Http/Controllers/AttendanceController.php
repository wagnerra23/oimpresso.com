<?php

namespace Modules\Essentials\Http\Controllers;

use App\User;
use App\Utils\ModuleUtil;
use DB;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Modules\Essentials\Entities\EssentialsAttendance;
use Modules\Essentials\Entities\Shift;
use Modules\Essentials\Jobs\ImportarPresencaJob;
use Modules\Essentials\Services\AttendanceImportService;
use Modules\Essentials\Utils\EssentialsUtil;
use Spatie\Permission\Models\Permission;
use Yajra\DataTables\Facades\DataTables;

class AttendanceController extends Controller
{
    /**
     * All Utils instance.
     */
    protected $moduleUtil;

    protected $essentialsUtil;

    /**
     * Constructor
     *
     * @param  ProductUtils  $product
     * @return void
     */
    public function __construct(ModuleUtil $moduleUtil, EssentialsUtil $essentialsUtil)
    {
        $this->moduleUtil = $moduleUtil;
        $this->essentialsUtil = $essentialsUtil;
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
        $can_crud_all_attendance = auth()->user()->can('essentials.crud_all_attendance');
        $can_view_own_attendance = auth()->user()->can('essentials.view_own_attendance');

        if (! $can_crud_all_attendance && ! $can_view_own_attendance) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            $attendance = EssentialsAttendance::where('essentials_attendances.business_id', $business_id)
                            ->join('users as u', 'u.id', '=', 'essentials_attendances.user_id')
                            ->leftjoin('essentials_shifts as es', 'es.id', '=', 'essentials_attendances.essentials_shift_id')
                            ->select([
                                'essentials_attendances.id',
                                'clock_in_time',
                                'clock_out_time',
                                'clock_in_note',
                                'clock_out_note',
                                'ip_address',
                                DB::raw('DATE(clock_in_time) as date'),
                                DB::raw("CONCAT(COALESCE(u.surname, ''), ' ', COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) as user"),
                                'es.name as shift_name', 'clock_in_location', 'clock_out_location',
                            ])->groupBy('essentials_attendances.id');

            $permitted_locations = auth()->user()->permitted_locations();

            if ($permitted_locations != 'all') {
                $permitted_locations_array = [];

                foreach ($permitted_locations as $loc_id) {
                    $permitted_locations_array[] = 'location.'.$loc_id;
                }
                $permission_ids = Permission::whereIn('name', $permitted_locations_array)
                                        ->pluck('id');

                $attendance->join('model_has_permissions as mhp', 'mhp.model_id', '=', 'u.id')->whereIn('mhp.permission_id', $permission_ids);
            }

            if (! empty(request()->input('employee_id'))) {
                $attendance->where('essentials_attendances.user_id', request()->input('employee_id'));
            }
            if (! empty(request()->start_date) && ! empty(request()->end_date)) {
                $start = request()->start_date;
                $end = request()->end_date;
                $attendance->whereDate('clock_in_time', '>=', $start)
                            ->whereDate('clock_in_time', '<=', $end);
            }

            if (! $can_crud_all_attendance && $can_view_own_attendance) {
                $attendance->where('essentials_attendances.user_id', auth()->user()->id);
            }

            return Datatables::of($attendance)
                    ->addColumn(
                        'action',
                        '@can("essentials.crud_all_attendance") <button data-href="{{action(\'\Modules\Essentials\Http\Controllers\AttendanceController@edit\', [$id])}}" class="btn btn-xs btn-primary btn-modal" data-container="#edit_attendance_modal"><i class="glyphicon glyphicon-edit"></i> @lang("messages.edit")</button>
                        <button class="btn btn-xs btn-danger delete-attendance" data-href="{{action(\'\Modules\Essentials\Http\Controllers\AttendanceController@destroy\', [$id])}}"><i class="fa fa-trash"></i> @lang("messages.delete")</button> @endcan
                        '
                    )
                    ->editColumn('work_duration', function ($row) {
                        $clock_in = \Carbon::parse($row->clock_in_time);
                        if (! empty($row->clock_out_time)) {
                            $clock_out = \Carbon::parse($row->clock_out_time);
                        } else {
                            $clock_out = \Carbon::now();
                        }

                        $html = $clock_in->diffForHumans($clock_out, true, true, 2);

                        return $html;
                    })
                    ->editColumn('clock_in', function ($row) {
                        $html = $this->moduleUtil->format_date($row->clock_in_time, true);
                        if (! empty($row->clock_in_location)) {
                            $html .= '<br>'.$row->clock_in_location.'<br>';
                        }

                        if (! empty($row->clock_in_note)) {
                            $html .= '<br>'.$row->clock_in_note.'<br>';
                        }

                        return $html;
                    })
                    ->editColumn('clock_out', function ($row) {
                        $html = $this->moduleUtil->format_date($row->clock_out_time, true);
                        if (! empty($row->clock_out_location)) {
                            $html .= '<br>'.$row->clock_out_location.'<br>';
                        }

                        if (! empty($row->clock_out_note)) {
                            $html .= '<br>'.$row->clock_out_note.'<br>';
                        }

                        return $html;
                    })
                    ->editColumn('date', '{{@format_date($date)}}')
                    ->rawColumns(['action', 'clock_in', 'work_duration', 'clock_out'])
                    ->filterColumn('user', function ($query, $keyword) {
                        $query->whereRaw("CONCAT(COALESCE(u.surname, ''), ' ', COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) like ?", ["%{$keyword}%"]);
                    })
                    ->make(true);
        }

        $settings = request()->session()->get('business.essentials_settings');
        $settings = ! empty($settings) ? json_decode($settings, true) : [];

        $is_employee_allowed = auth()->user()->can('essentials.allow_users_for_attendance_from_web');
        $clock_in = EssentialsAttendance::where('business_id', $business_id)
                                ->where('user_id', auth()->user()->id)
                                ->whereNull('clock_out_time')
                                ->first();
        $employees = [];
        if ($can_crud_all_attendance) {
            $employees = User::forDropdown($business_id, false);
        }

        $days = $this->moduleUtil->getDays();

        // Último relatório de import deste negócio (HRM-O6 / PR-6). Existe porque, com
        // `QUEUE_CONNECTION=database`, o Job termina DEPOIS do redirect: sem esta chave
        // estável o relatório só viveria no flash do request que enviou o arquivo, e a
        // mensagem "o relatório aparece nesta tela ao terminar" seria falsa.
        $import_presenca_relatorio = $this->ultimoRelatorioDeImport((int) $business_id);

        return view('essentials::attendance.index')
            ->with(compact('is_employee_allowed', 'clock_in', 'employees', 'days', 'import_presenca_relatorio'));
    }

    /**
     * Lê o último relatório de import de presença do negócio, escopado por business_id
     * na própria chave de cache (ADR 0093 vale também pro cache).
     *
     * @return array<string, mixed>|null
     */
    private function ultimoRelatorioDeImport(int $businessId): ?array
    {
        $relatorio = Cache::get(ImportarPresencaJob::chaveDeCache($businessId, ImportarPresencaJob::TOKEN_ULTIMO));

        return is_array($relatorio) ? $relatorio : null;
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        $business_id = request()->session()->get('user.business_id');
        $is_admin = $this->moduleUtil->is_admin(auth()->user(), $business_id);

        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'essentials_module')) && ! $is_admin) {
            abort(403, 'Unauthorized action.');
        }

        $employees = User::forDropdown($business_id, false);

        return view('essentials::attendance.create')->with(compact('employees'));
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
        $is_admin = $this->moduleUtil->is_admin(auth()->user(), $business_id);

        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'essentials_module') || $is_admin)) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $attendance = $request->input('attendance');
            $ip_address = $this->moduleUtil->getUserIpAddr();
            if (! empty($attendance)) {
                foreach ($attendance as $user_id => $value) {
                    $data = [
                        'business_id' => $business_id,
                        'user_id' => $user_id,
                    ];

                    if (! empty($value['clock_in_time'])) {
                        $data['clock_in_time'] = $this->moduleUtil->uf_date($value['clock_in_time'], true);
                    }
                    if (! empty($value['id'])) {
                        $data['id'] = $value['id'];
                    }
                    EssentialsAttendance::updateOrCreate(
                        $data,
                        [
                            'clock_out_time' => ! empty($value['clock_out_time']) ? $this->moduleUtil->uf_date($value['clock_out_time'], true) : null,
                            'ip_address' => ! empty($value['ip_address']) ? $value['ip_address'] : $ip_address,
                            'clock_in_note' => $value['clock_in_note'],
                            'clock_out_note' => $value['clock_out_note'],
                            'essentials_shift_id' => $value['essentials_shift_id'],
                        ]
                    );
                }
            }

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
     * Show the form for editing the specified resource.
     *
     * @return Response
     */
    public function edit($id)
    {
        $business_id = request()->session()->get('user.business_id');
        $is_admin = $this->moduleUtil->is_admin(auth()->user(), $business_id);

        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'essentials_module') || $is_admin)) {
            abort(403, 'Unauthorized action.');
        }

        $attendance = EssentialsAttendance::where('business_id', $business_id)
                                    ->with(['employee'])
                                    ->find($id);

        return view('essentials::attendance.edit')->with(compact('attendance'));
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
        $is_admin = $this->moduleUtil->is_admin(auth()->user(), $business_id);

        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'essentials_module') || $is_admin)) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $input = $request->only(['clock_in_time', 'clock_out_time', 'ip_address', 'clock_in_note', 'clock_out_note']);

            $input['clock_in_time'] = $this->moduleUtil->uf_date($input['clock_in_time'], true);
            $input['clock_out_time'] = ! empty($input['clock_out_time']) ? $this->moduleUtil->uf_date($input['clock_out_time'], true) : null;

            $attendance = EssentialsAttendance::where('business_id', $business_id)
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
     * @return Response
     */
    public function destroy($id)
    {
        $business_id = request()->session()->get('user.business_id');
        $is_admin = $this->moduleUtil->is_admin(auth()->user(), $business_id);

        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'essentials_module'))) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            try {
                EssentialsAttendance::where('business_id', $business_id)->where('id', $id)->delete();

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

    /**
     * Clock in / Clock out the logged in user.
     *
     * @return Response
     */
    public function clockInClockOut(Request $request)
    {
        $business_id = $request->session()->get('user.business_id');

        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'essentials_module'))) {
            abort(403, 'Unauthorized action.');
        }

        //Check if employees allowed to add their own attendance
        $settings = request()->session()->get('business.essentials_settings');
        $settings = ! empty($settings) ? json_decode($settings, true) : [];
        if (! auth()->user()->can('essentials.allow_users_for_attendance_from_web')) {
            return ['success' => false,
                'msg' => __('essentials::lang.not_allowed'),
            ];
        } elseif ((! empty($settings['is_location_required']) && $settings['is_location_required']) && empty($request->input('clock_in_out_location'))) {
            return ['success' => false,
                'msg' => __('essentials::lang.you_must_enable_location'),
            ];
        }

        try {
            $type = $request->input('type');

            if ($type == 'clock_in') {
                $data = [
                    'business_id' => $business_id,
                    'user_id' => auth()->user()->id,
                    'clock_in_time' => \Carbon::now(),
                    'clock_in_note' => $request->input('clock_in_note'),
                    'ip_address' => $this->moduleUtil->getUserIpAddr(),
                    'clock_in_location' => $request->input('clock_in_out_location'),
                ];

                $output = $this->essentialsUtil->clockin($data, $settings);
            } elseif ($type == 'clock_out') {
                $data = [
                    'business_id' => $business_id,
                    'user_id' => auth()->user()->id,
                    'clock_out_time' => \Carbon::now(),
                    'clock_out_note' => $request->input('clock_out_note'),
                    'clock_out_location' => $request->input('clock_in_out_location'),
                ];

                $output = $this->essentialsUtil->clockout($data, $settings);
            }
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => false,
                'msg' => __('messages.something_went_wrong'),
                'type' => $type,
            ];
        }

        return $output;
    }

    /**
     * Function to get attendance summary of a user
     *
     * @return Response
     */
    public function getUserAttendanceSummary()
    {
        $business_id = request()->session()->get('user.business_id');

        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'essentials_module'))) {
            abort(403, 'Unauthorized action.');
        }

        $is_admin = $this->moduleUtil->is_admin(auth()->user(), $business_id);
        $user_id = $is_admin ? request()->input('user_id') : auth()->user()->id;

        if (empty($user_id)) {
            return '';
        }

        $start_date = ! empty(request()->start_date) ? request()->start_date : null;
        $end_date = ! empty(request()->end_date) ? request()->end_date : null;

        $total_work_duration = $this->essentialsUtil->getTotalWorkDuration('hour', $user_id, $business_id, $start_date, $end_date);

        return $total_work_duration;
    }

    /**
     * Function to validate clock in and clock out time
     *
     * O predicado de sobreposição mora em `AttendanceImportService` — ÚNICA
     * implementação, compartilhada com o import de planilha (HRM-O6 / PR-6, achado A7).
     * Antes o SQL vivia só aqui, e por isso o import não o aplicava.
     *
     * A conversão de formato continua sendo `uf_date`: o formulário manda a data no
     * formato de exibição do negócio (sessão). O import NÃO passa por aqui — ele já
     * recebe `Y-m-d H:i:s` do arquivo e roda em fila, onde não existe sessão.
     *
     * @return string
     */
    public function validateClockInClockOut(Request $request, AttendanceImportService $importService)
    {
        $business_id = $request->session()->get('user.business_id');
        $user_ids = explode(',', $request->input('user_ids'));
        $clock_in_time = $request->input('clock_in_time');
        $clock_out_time = $request->input('clock_out_time');
        $attendance_id = $request->input('attendance_id');

        if (! empty($clock_in_time)) {
            $clock_in_time = $this->essentialsUtil->uf_date($clock_in_time, true);
        }

        if (! empty($clock_out_time)) {
            $clock_out_time = $this->essentialsUtil->uf_date($clock_out_time, true);
        }

        $sobrepoe = $importService->sobrepoeMarcacaoExistente(
            (int) $business_id,
            $user_ids,
            ! empty($clock_in_time) ? $clock_in_time : null,
            ! empty($clock_out_time) ? $clock_out_time : null,
            $attendance_id
        );

        return $sobrepoe ? 'false' : 'true';
    }

    /**
     * Get attendance summary by shift
     */
    public function getAttendanceByShift()
    {
        $business_id = request()->session()->get('user.business_id');
        $is_admin = $this->moduleUtil->is_admin(auth()->user(), $business_id);

        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'essentials_module') || $is_admin)) {
            abort(403, 'Unauthorized action.');
        }

        $date = $this->moduleUtil->uf_date(request()->input('date'));

        $attendance_data = EssentialsAttendance::where('business_id', $business_id)
                                ->whereDate('clock_in_time', $date)
                                ->whereNotNull('essentials_shift_id')
                                ->with(['shift', 'shift.user_shifts', 'shift.user_shifts.user', 'employee'])
                                ->get();
        $attendance_by_shift = [];
        $date_obj = \Carbon::parse($date);
        foreach ($attendance_data as $data) {
            if (empty($attendance_by_shift[$data->essentials_shift_id])) {
                //Calculate total users in the shift
                $total_users = 0;
                $all_users = [];
                foreach ($data->shift->user_shifts as $user_shift) {
                    if (! empty($user_shift->start_date) && ! empty($user_shift->end_date) && $date_obj->between(\Carbon::parse($user_shift->start_date), \Carbon::parse($user_shift->end_date))) {
                        $total_users++;
                        $all_users[] = $user_shift->user->user_full_name;
                    }
                }
                $attendance_by_shift[$data->essentials_shift_id] = [
                    'present' => 1,
                    'shift' => $data->shift->name,
                    'total' => $total_users,
                    'present_users' => [$data->employee->user_full_name],
                    'all_users' => $all_users,
                ];
            } else {
                if (! in_array($data->employee->user_full_name, $attendance_by_shift[$data->essentials_shift_id]['present_users'])) {
                    $attendance_by_shift[$data->essentials_shift_id]['present']++;
                    $attendance_by_shift[$data->essentials_shift_id]['present_users'][] = $data->employee->user_full_name;
                }
            }
        }

        return view('essentials::attendance.attendance_by_shift_data')->with(compact('attendance_by_shift'));
    }

    /**
     * Get attendance summary by date
     */
    public function getAttendanceByDate()
    {
        $business_id = request()->session()->get('user.business_id');
        $is_admin = $this->moduleUtil->is_admin(auth()->user(), $business_id);

        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'essentials_module') || $is_admin)) {
            abort(403, 'Unauthorized action.');
        }

        $start_date = request()->input('start_date');
        $end_date = request()->input('end_date');

        $attendance_data = EssentialsAttendance::where('business_id', $business_id)
                                ->whereDate('clock_in_time', '>=', $start_date)
                                ->whereDate('clock_in_time', '<=', $end_date)
                                ->select(
                                    'essentials_attendances.*',
                                    DB::raw('COUNT(DISTINCT essentials_attendances.user_id) as total_present'),
                                    DB::raw('CAST(clock_in_time AS DATE) as clock_in_date')
                                )
                                ->groupBy(DB::raw('CAST(clock_in_time AS DATE)'))
                                ->get();

        $all_users = User::where('business_id', $business_id)
                        ->user()
                        ->count();

        $attendance_by_date = [];
        foreach ($attendance_data as $data) {
            $total_present = ! empty($data->total_present) ? $data->total_present : 0;
            $attendance_by_date[] = [
                'present' => $total_present,
                'absent' => $all_users - $total_present,
                'date' => $data->clock_in_date,
            ];
        }

        return view('essentials::attendance.attendance_by_date_data')->with(compact('attendance_by_date'));
    }

    /**
     * Function to import attendance.
     *
     * HRM-O6 / PR-6 (achado A7). O que mudou em relação ao legado:
     *
     *  1. **Validação linha a linha** — cada linha passa pela MESMA checagem de
     *     sobreposição do `validateClockInClockOut` (agora em `AttendanceImportService`).
     *     Antes o formulário validava e o import não, e o insert entrava cru.
     *  2. **Relatório de recusadas em vez de rollback total** — o legado dava `break` no
     *     primeiro defeito e `DB::rollBack()` no lote inteiro: uma linha ruim na posição
     *     900 descartava 899 marcações boas. Agora as boas entram e as ruins voltam
     *     listadas com número da linha e motivo.
     *  3. **`ini_set('max_execution_time', 0)` removido** — o trabalho foi pra fila
     *     (`ImportarPresencaJob`), e os SELECT por linha (N+1) viraram 2 queries em lote.
     *
     * Tier 0 (ADR 0093): o `business_id` da sessão é resolvido AQUI e entregue ao Job
     * pelo construtor — `session()` não existe no worker. Linha cujo e-mail pertence a
     * colaborador de outro negócio é recusada pelo service, nunca importada.
     *
     * @param  Request  $request
     * @return Response
     */
    public function importAttendance(Request $request)
    {
        $business_id = request()->session()->get('user.business_id');
        $is_admin = $this->moduleUtil->is_admin(auth()->user(), $business_id);

        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'essentials_module') || $is_admin)) {
            abort(403, 'Unauthorized action.');
        }

        $notAllowed = $this->moduleUtil->notAllowedInDemo();
        if (! empty($notAllowed)) {
            return $notAllowed;
        }

        // `extensions` (e não `mimes`) de propósito: o próprio template do módulo é um
        // .xls gerado pelo Calc, cujo MIME adivinhado varia — `mimes` recusaria arquivo
        // legítimo. A extensão é declarada pelo cliente, então a proteção real é o
        // arquivo ir pra disco privado, ser parseado e apagado em seguida.
        $request->validate([
            'attendance' => ['required', 'file', 'extensions:xls,xlsx,csv,txt', 'max:5120'],
        ]);

        try {
            $arquivo = $request->file('attendance');
            $token = (string) Str::uuid();

            // Disco privado (`storage/app`), nunca `public`: a planilha traz e-mail e
            // jornada de colaborador. O Job apaga o arquivo ao terminar.
            $caminho = $arquivo->storeAs(
                'essentials/import-presenca',
                $token.'.'.$arquivo->getClientOriginalExtension(),
                'local'
            );

            ImportarPresencaJob::dispatch(
                businessId: (int) $business_id,
                caminhoArquivo: $caminho,
                chaveRelatorio: $token,
                userId: auth()->id(),
                ipPadrao: $this->moduleUtil->getUserIpAddr(),
                nomeOriginal: $arquivo->getClientOriginalName(),
            );
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            return redirect()->back()->with('notification', [
                'success' => 0,
                'msg' => $e->getMessage(),
            ]);
        }

        // Com `QUEUE_CONNECTION=sync` o Job já rodou inline e o relatório está pronto
        // agora; com `database` ele chega depois, e aí quem o exibe é o `index()`, que lê
        // a chave estável do último relatório. Nada é prometido além disso — não existe
        // notificação nem e-mail neste caminho.
        $relatorio = Cache::get(ImportarPresencaJob::chaveDeCache((int) $business_id, $token));
        $relatorio = is_array($relatorio) ? $relatorio : null;

        return redirect()->back()
            ->with('status', $this->mensagemDoImport($relatorio))
            ->with('import_presenca_relatorio', $relatorio);
    }

    /**
     * Monta a mensagem de retorno do import a partir do relatório (ou da ausência dele).
     *
     * @param  array<string, mixed>|null  $relatorio
     * @return array{success:int, msg:string}
     */
    private function mensagemDoImport(?array $relatorio): array
    {
        if ($relatorio === null) {
            return [
                'success' => 1,
                'msg' => __('essentials::lang.import_enfileirado'),
            ];
        }

        if (($relatorio['estado'] ?? null) === 'erro') {
            return [
                'success' => 0,
                'msg' => $relatorio['mensagem'] ?? __('messages.something_went_wrong'),
            ];
        }

        $recusadas = count($relatorio['recusadas'] ?? []);

        if ($recusadas === 0) {
            return [
                'success' => 1,
                'msg' => __('essentials::lang.import_concluido', ['inseridas' => $relatorio['inseridas'] ?? 0]),
            ];
        }

        // Sucesso PARCIAL é reportado como aviso, não como sucesso limpo: o operador
        // precisa saber que ficaram linhas de fora — silêncio aqui é o estrago do A7.
        return [
            'success' => 0,
            'msg' => __('essentials::lang.import_parcial', [
                'inseridas' => $relatorio['inseridas'] ?? 0,
                'recusadas' => $recusadas,
            ]),
        ];
    }

    /**
     * Adds attendance row for an employee on add latest attendance form
     *
     * @param  int  $user_id
     * @return Response
     */
    public function getAttendanceRow($user_id)
    {
        $business_id = request()->session()->get('user.business_id');
        $is_admin = $this->moduleUtil->is_admin(auth()->user(), $business_id);

        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'essentials_module') || $is_admin)) {
            abort(403, 'Unauthorized action.');
        }

        $user = User::where('business_id', $business_id)
                    ->findOrFail($user_id);

        $attendance = EssentialsAttendance::where('business_id', $business_id)
                                        ->where('user_id', $user_id)
                                        ->whereNotNull('clock_in_time')
                                        ->whereNull('clock_out_time')
                                        ->first();

        $shifts = Shift::join('essentials_user_shifts as eus', 'eus.essentials_shift_id', '=', 'essentials_shifts.id')
                    ->where('essentials_shifts.business_id', $business_id)
                    ->where('eus.user_id', $user_id)
                    ->where('eus.start_date', '<=', \Carbon::now()->format('Y-m-d'))
                    ->pluck('essentials_shifts.name', 'essentials_shifts.id');

        return view('essentials::attendance.attendance_row')->with(compact('attendance', 'shifts', 'user'));
    }
}
