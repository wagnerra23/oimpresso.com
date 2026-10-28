<?php

namespace Modules\Spreadsheet\Http\Controllers;

use App\Category;
use App\User;
use App\Utils\ModuleUtil;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\Spreadsheet\Entities\Spreadsheet;
use Modules\Spreadsheet\Entities\SpreadsheetShare;
use Modules\Spreadsheet\Http\Requests\MoveToFolderRequest;
use Modules\Spreadsheet\Http\Requests\StoreFolderRequest;
use Modules\Spreadsheet\Http\Requests\UpdateSpreadsheetRequest;
use Modules\Spreadsheet\Notifications\SpreadsheetShared;
use Modules\Spreadsheet\Services\SpreadsheetService;
use Notification;
use App\Services\FeatureFlagService;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class SpreadsheetController extends Controller
{
    /** Tela React da lista (`Spreadsheet/Index`, RUNBOOK-spreadsheet-index). Ligada para todas no cutover [W] 2026-10-13; `?legacy=1` abre a Blade. */
    private const FLAG_INDEX = 'useV2SpreadsheetIndex';

    /**
     * All Utils instance.
     */
    protected $moduleUtil;

    /**
     * Service layer canônico (D4 architecture — Wave 16 governance v3).
     * Concentra criação/atualização/remoção de spreadsheets fora do Controller.
     */
    protected SpreadsheetService $service;

    /**
     * Constructor
     *
     * @param  ModuleUtil  $moduleUtil
     * @param  SpreadsheetService  $service  injetado via container Laravel (DI)
     * @return void
     */
    public function __construct(ModuleUtil $moduleUtil, SpreadsheetService $service)
    {
        $this->moduleUtil = $moduleUtil;
        $this->service    = $service;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\View\View|\Inertia\Response
     */
    public function index()
    {
        $business_id = request()->session()->get('user.business_id');

        if (! (auth()->user()->can('superadmin') || ($this->moduleUtil->hasThePermissionInSubscription($business_id, 'spreadsheet_module') && auth()->user()->can('access.spreadsheet')))) {
            abort(403, 'Unauthorized action.');
        }

        if ($this->usaIndexReact($business_id)) {
            return Inertia::render('Spreadsheet/Index', [
                'podeCriar' => auth()->user()->can('superadmin') || auth()->user()->can('create.spreadsheet'),
                'planilhas' => Inertia::defer(fn () => $this->buildPlanilhasPayload($business_id)),
                'pastas'    => Inertia::defer(fn () => $this->buildPastasPayload($business_id)),
            ]);
        }

        $user_id = request()->session()->get('user.id');
        $module_data = $this->moduleUtil->getModuleData('getAssignedTaskForUser', $user_id);
        $spreadsheets = $this->planilhasVisiveis($business_id);

        $todos = [];
        if (! empty($module_data['Essentials'])) {
            $todos = $module_data['Essentials'];
        }

        $users = User::forDropdown($business_id, false);
        $roles = $this->moduleUtil->getDropdownForRoles($business_id);

        foreach ($spreadsheets as $key => $value) {
            if (count($value->shares) > 0) {
                $shered_users = [];
                $shared_roles = [];
                $shared_todos = [];

                foreach ($value->shares as $share) {
                    if ($share->shared_with == 'user') {
                        $shered_users[] = $users[$share->shared_id] ?? '';
                    } elseif ($share->shared_with == 'role') {
                        $shared_roles[] = $roles[$share->shared_id] ?? '';
                    } elseif ($share->shared_with == 'role') {
                        $shared_todos[] = $todos[$share->shared_id] ?? '';
                    }
                }

                $spreadsheets[$key]->shered_users = $shered_users;
                $spreadsheets[$key]->shared_roles = $shared_roles;
                $spreadsheets[$key]->shared_todos = $shared_todos;
            }
        }

        $folders = Category::where('business_id', $business_id)
                        ->where('category_type', 'spreadsheet')
                        ->orderBy('name', 'asc')
                        ->get();

        return view('spreadsheet::sheet.index')
            ->with(compact('spreadsheets', 'folders'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Inertia\Response|\Illuminate\View\View
     */
    public function create()
    {
        $business_id = request()->session()->get('user.business_id');

        if (! (auth()->user()->can('superadmin') || ($this->moduleUtil->hasThePermissionInSubscription($business_id, 'spreadsheet_module') && auth()->user()->can('create.spreadsheet')))) {
            abort(403, 'Unauthorized action.');
        }
        $folder_id = request()->input('folder_id', null);

        // Editor em React (thread telas-soltas/04b, RUNBOOK-editor). `?legacy=1` serve a Blade até o F5.
        if (! request()->boolean('legacy')) {
            return Inertia::render('Spreadsheet/Editor', [
                'planilha'    => null,
                'pasta_id'    => $folder_id !== null && $folder_id !== '' ? (int) $folder_id : null,
                'pode_gravar' => true,
                'voltar'      => action([self::class, 'index']),
            ]);
        }

        return view('spreadsheet::sheet.create')->with(compact('folder_id'));
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

        if (! (auth()->user()->can('superadmin') || ($this->moduleUtil->hasThePermissionInSubscription($business_id, 'spreadsheet_module') && auth()->user()->can('create.spreadsheet')))) {
            abort(403, 'Unauthorized action.');
        }

        // D4 architecture: delega criação ao Service (multi-tenant Tier 0 explícito)
        $input = [
            'name'       => $request->input('name'),
            'sheet_data' => $request->input('sheet_data'),
            'folder_id'  => $request->input('folder_id'),
        ];

        $sheet = $this->service->createSpreadsheet($input, $business_id, auth()->user()->id);

        $output = $sheet !== null
            ? ['success' => true,  'msg' => __('lang_v1.success')]
            : ['success' => false, 'msg' => __('messages.something_went_wrong')];

        return redirect()
            ->action([\Modules\Spreadsheet\Http\Controllers\SpreadsheetController::class, 'index'])
            ->with('status', $output);
    }

    /**
     * Show the specified resource.
     *
     * @param  int  $id
     * @return \Inertia\Response|\Illuminate\View\View
     */
    public function show($id)
    {
        $business_id = request()->session()->get('user.business_id');

        if (! (auth()->user()->can('superadmin') || auth()->user()->can('create.spreadsheet') || auth()->user()->can('access.spreadsheet'))) {
            abort(403, 'Unauthorized action.');
        }

        $spreadsheet = $this->planilhaAcessivel((int) $business_id, (int) $id)
            ->select('sheet_spreadsheets.business_id', 'sheet_spreadsheets.id', 'sheet_spreadsheets.name', 'sheet_spreadsheets.sheet_data', 'sheet_spreadsheets.created_by')
            ->firstOrFail();

        if (! request()->boolean('legacy')) {
            return Inertia::render('Spreadsheet/Editor', $this->propsDoEditor($spreadsheet));
        }

        return view('spreadsheet::sheet.show')
            ->with(compact('spreadsheet'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function edit($id)
    {
        return view('spreadsheet::edit');
    }

    /**
     * Update the specified resource in storage.
     *
     * D8.c Security Wave 17 — validação via UpdateSpreadsheetRequest (name maxlen +
     * sheet_data type) preserva regras RBAC granulares (`create.spreadsheet`)
     * checadas abaixo.
     *
     * @param  UpdateSpreadsheetRequest  $request
     * @param  int  $id
     * @return Response
     */
    public function update(UpdateSpreadsheetRequest $request, $id)
    {
        $business_id = request()->session()->get('user.business_id');

        if (! (auth()->user()->can('superadmin') || auth()->user()->can('create.spreadsheet'))) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $validated = $request->validated();
            $input['name'] = ! empty($validated['name']) ? $validated['name'] : 'My Spreasheet';
            $input['sheet_data'] = json_encode($validated['sheet_data'] ?? $request->input('sheet_data'));

            DB::beginTransaction();
            $spreadsheet = $this->planilhaAcessivel((int) $business_id, (int) $id)
                                ->select('sheet_spreadsheets.business_id', 'sheet_spreadsheets.id', 'sheet_spreadsheets.name', 'sheet_spreadsheets.sheet_data')
                                ->firstOrFail();

            if (! empty($spreadsheet)) {
                Spreadsheet::where('business_id', $business_id)
                        ->where('id', $id)
                        ->update($input);
            }

            DB::commit();
            $output = [
                'success' => true,
                'msg' => __('lang_v1.success'),
            ];
        } catch (Exception $e) {
            DB::rollBack();

            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = [
                'success' => false,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return redirect()
            ->action([\Modules\Spreadsheet\Http\Controllers\SpreadsheetController::class, 'show'], [$id])
            ->with('status', $output);
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

        if (! (auth()->user()->can('superadmin') || auth()->user()->can('create.spreadsheet'))) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            try {
                DB::beginTransaction();

                Spreadsheet::where('business_id', $business_id)
                    ->where('created_by', auth()->user()->id)
                    ->where('id', $id)
                    ->delete();

                DB::commit();
                $output = [
                    'success' => true,
                    'msg' => __('lang_v1.success'),
                ];
            } catch (Exception $e) {
                DB::rollBack();

                \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

                $output = [
                    'success' => false,
                    'msg' => __('messages.something_went_wrong'),
                ];
            }
        }

        return $output;
    }

    public function getShareSpreadsheet(Request $request, $id)
    {
        $business_id = request()->session()->get('user.business_id');

        if (! (auth()->user()->can('superadmin') || ($this->moduleUtil->hasThePermissionInSubscription($business_id, 'spreadsheet_module')))) {
            abort(403, 'Unauthorized action.');
        }

        // Só quem criou a planilha (ou superadmin) mexe no compartilhamento, e só de planilha do negócio da sessão.
        $this->planilhaDoDono((int) $business_id, (int) $id);

        if ($request->ajax()) {
            $module_data = $this->moduleUtil->getModuleData('getTodosDropdown', $business_id);

            $todos = [];
            if (! empty($module_data['Essentials'])) {
                $todos = $module_data['Essentials'];
            }

            $users = User::forDropdown($business_id, false);
            $roles = $this->moduleUtil->getDropdownForRoles($business_id);

            $shared_spreadsheets = SpreadsheetShare::where('sheet_spreadsheet_id', $id)
                                    ->get()
                                    ->groupBy('shared_with');

            $shared_todos = [];
            $shared_roles = [];
            $shared_users = [];
            if (! empty($shared_spreadsheets['todo'])) {
                $shared_todos = $shared_spreadsheets['todo']->pluck('shared_id')->toArray();
            }

            if (! empty($shared_spreadsheets['role'])) {
                $shared_roles = $shared_spreadsheets['role']->pluck('shared_id')->toArray();
            }

            if (! empty($shared_spreadsheets['user'])) {
                $shared_users = $shared_spreadsheets['user']->pluck('shared_id')->toArray();
            }

            // Tela React (lista): as mesmas listas em JSON. A Blade segue recebendo o partial.
            if ($request->wantsJson()) {
                $opcoes = fn ($lista) => collect($lista)->map(fn ($nome, $id) => ['id' => (int) $id, 'nome' => trim((string) $nome)])
                    ->filter(fn ($o) => $o['id'] > 0)->values();

                return response()->json([
                    'usuarios' => $opcoes($users),
                    'funcoes' => $opcoes($roles),
                    'tarefas' => $opcoes($todos),
                    'selecionados' => [
                        'user' => array_map('intval', $shared_users),
                        'role' => array_map('intval', $shared_roles),
                        'todo' => array_map('intval', $shared_todos),
                    ],
                ]);
            }

            return view('spreadsheet::sheet.partials.share_sheet')
                ->with(compact('todos', 'id', 'shared_roles', 'roles', 'users', 'shared_todos', 'shared_users'));
        }
    }

    public function postShareSpreadsheet(Request $request)
    {
        $business_id = request()->session()->get('user.business_id');

        if (! (auth()->user()->can('superadmin') || ($this->moduleUtil->hasThePermissionInSubscription($business_id, 'spreadsheet_module')))) {
            abort(403, 'Unauthorized action.');
        }

        if ($request->ajax()) {
            // Antes de 2026-10-27 não havia conferência nenhuma do sheet_id: qualquer usuário com o módulo criava ou
            // apagava compartilhamento de qualquer planilha pelo id, inclusive de outro negócio. Agora só o dono, no
            // negócio da sessão, e só para usuário, função ou tarefa deste negócio.
            $sheet_id = (int) $this->planilhaDoDono((int) $business_id, (int) $request->input('sheet_id'))->id;
            $shares = $this->compartilhamentosDoNegocio((int) $business_id, (array) $request->input('share', []));

            try {
                DB::beginTransaction();

                //Update shared spreadsheets based on selected data
                    $notify_to_shared_ids = []; //send notification to users
                    if (! empty($shares)) {
                        foreach ($shares as $shared_with => $shared_ids) {
                            $existing_shared_ids = [];
                            foreach ($shared_ids as $id) {
                                $existing_shared_ids[] = $id;
                                $data = [
                                    'sheet_spreadsheet_id' => $sheet_id,
                                    'shared_with' => $shared_with,
                                    'shared_id' => $id,
                                ];

                                $sheet = SpreadsheetShare::firstOrCreate($data, $data);

                                if ($sheet->shared_with == 'user' && $sheet->wasRecentlyCreated) {
                                    $notify_to_shared_ids[] = $sheet->shared_id;
                                }

                                //get users of the role to notify
                                if ($sheet->shared_with == 'role' && $sheet->wasRecentlyCreated) {
                                    $role = Role::where('business_id', $business_id)
                                        ->find($sheet->shared_id);
                                    $users = User::role($role->name)->get()->pluck('id')->toArray();
                                    $notify_to_shared_ids = array_unique(array_merge($notify_to_shared_ids, $users));
                                }
                            }
                            SpreadsheetShare::where('sheet_spreadsheet_id', $sheet_id)
                                ->where('shared_with', $shared_with)
                                ->whereNotIn('shared_id', $existing_shared_ids)
                                ->delete();
                        }
                    }

                //if shared with is empty then delete whole rows of given sheet & shared with
                if (empty($shares['todo'])) {
                    SpreadsheetShare::where('sheet_spreadsheet_id', $sheet_id)
                            ->where('shared_with', 'todo')
                            ->delete();
                }

                if (empty($shares['user'])) {
                    SpreadsheetShare::where('sheet_spreadsheet_id', $sheet_id)
                            ->where('shared_with', 'user')
                            ->delete();
                }

                if (empty($shares['role'])) {
                    SpreadsheetShare::where('sheet_spreadsheet_id', $sheet_id)
                            ->where('shared_with', 'role')
                            ->delete();
                }

                if (! empty($notify_to_shared_ids)) {
                    $this->notifyUsersOfSharedSheets($business_id, $sheet_id, $notify_to_shared_ids);
                }
                DB::commit();
                $output = [
                    'success' => true,
                    'msg' => __('lang_v1.success'),
                ];
            } catch (Exception $e) {
                DB::rollBack();

                \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

                $output = [
                    'success' => false,
                    'msg' => __('messages.something_went_wrong'),
                ];
            }

            return $output;
        }
    }

    public function notifyUsersOfSharedSheets($business_id, $sheet_id, $shared_ids)
    {
        $users = User::where('business_id', $business_id)
                    ->find($shared_ids);

        Notification::send($users, new SpreadsheetShared($sheet_id));
    }

    public function addFolder(StoreFolderRequest $request)
    {
        // D8.c Security Wave 17 — validação centralizada (name required + maxlen,
        // folder_id integer + exists). Pattern UltimatePOS preservado.
        try {
            $input = $request->only(['name']);
            if (! empty($request->input('folder_id'))) {
                Category::where('business_id', $request->session()->get('user.business_id'))
                        ->where('id', $request->input('folder_id'))
                        ->where('category_type', 'spreadsheet')
                        ->update($input);
            } else {
                $input['parent_id'] = 0;
                $input['business_id'] = $request->session()->get('user.business_id');
                $input['created_by'] = $request->session()->get('user.id');
                $input['category_type'] = 'spreadsheet';

                Category::create($input);
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
                ->action([\Modules\Spreadsheet\Http\Controllers\SpreadsheetController::class, 'index'])
                ->with('status', $output);
    }

    public function moveToFolder(MoveToFolderRequest $request)
    {
        // D8.c Security Wave 17 — validação centralizada
        // (spreadsheet_id required+exists, move_to_folder required+integer).
        if (! empty($request->input('move_to_folder'))) {
            $business_id = $request->session()->get('user.business_id');

            Spreadsheet::where('business_id', $business_id)
                    ->where('created_by', auth()->user()->id)
                    ->where('id', $request->input('spreadsheet_id'))
                    ->update(['folder_id' => $request->input('move_to_folder')]);

            $output = ['success' => true,
                'msg' => __('lang_v1.success'),
            ];

            return redirect()
                ->action([\Modules\Spreadsheet\Http\Controllers\SpreadsheetController::class, 'index'])
                ->with('status', $output);
        }
    }

    /**
     * Flag ligada e sem `?legacy=1`. A flag decide no 1º GET de HTML (sem `X-Inertia`); o
     * `legacy=1` é a volta à Blade para as ações que ainda não estão na tela React.
     */
    private function usaIndexReact(int $business_id): bool
    {
        return ! request()->boolean('legacy')
            && app(FeatureFlagService::class)->isOn(self::FLAG_INDEX, ['business_id' => $business_id]);
    }

    /**
     * Planilha do negócio da sessão criada pelo usuário (superadmin: qualquer uma do negócio). 404 se não for.
     */
    private function planilhaDoDono(int $business_id, int $id): Spreadsheet
    {
        $query = Spreadsheet::where('business_id', $business_id)->where('id', $id);
        if (! auth()->user()->can('superadmin')) {
            $query->where('created_by', auth()->user()->id);
        }

        return $query->firstOrFail();
    }

    /**
     * Mantém só destinatários deste negócio: usuários e funções dos dropdowns do negócio e tarefas do Essentials dele.
     * Um id de fora some do pedido em vez de virar compartilhamento.
     */
    private function compartilhamentosDoNegocio(int $business_id, array $shares): array
    {
        $permitidos = [
            'user' => collect(User::forDropdown($business_id, false))->keys()->map(fn ($k) => (int) $k)->all(),
            'role' => collect($this->moduleUtil->getDropdownForRoles($business_id))->keys()->map(fn ($k) => (int) $k)->all(),
            'todo' => collect($this->moduleUtil->getModuleData('getTodosDropdown', $business_id)['Essentials'] ?? [])->keys()->map(fn ($k) => (int) $k)->all(),
        ];

        $filtrado = [];
        foreach ($permitidos as $tipo => $ids) {
            if (! empty($shares[$tipo])) {
                $filtrado[$tipo] = array_values(array_filter((array) $shares[$tipo], fn ($v) => in_array((int) $v, $ids, true)));
            }
        }

        return $filtrado;
    }

    /**
     * Props do editor React. DTO explícito: nada de business_id nem created_by cru.
     *
     * `sheet_data` é gravado como string JSON dentro de JSON (o cast `array` do Model codifica a string
     * que a tela manda), então a leitura devolve a string; linha antiga com array de verdade também vale.
     */
    private function propsDoEditor(Spreadsheet $spreadsheet): array
    {
        $minha = (int) $spreadsheet->created_by === (int) auth()->user()->id;
        // Valor cru do banco: o cast `array` devolve a string interna, e o Model declara array. Decodifica até
        // duas vezes (string JSON dentro de JSON, como a tela grava; ou array de verdade, linha antiga).
        $dados = json_decode((string) $spreadsheet->getRawOriginal('sheet_data'), true);
        if (is_string($dados)) {
            $dados = json_decode($dados, true);
        }

        $voltar = action([self::class, 'index']);
        if (! $minha) {
            // Mesmo destino da Blade para quem recebeu: as tarefas do Essentials, quando o módulo existe.
            try {
                $voltar = action([\Modules\Essentials\Http\Controllers\ToDoController::class, 'index']);
            } catch (\Throwable $e) {
                $voltar = action([self::class, 'index']);
            }
        }

        return [
            'planilha' => [
                'id'    => (int) $spreadsheet->id,
                'nome'  => (string) $spreadsheet->name,
                'dados' => is_array($dados) ? array_values($dados) : null,
                'minha' => $minha,
            ],
            'pasta_id'    => null,
            'pode_gravar' => auth()->user()->can('superadmin') || auth()->user()->can('create.spreadsheet'),
            'voltar'      => $voltar,
        ];
    }

    /**
     * Uma planilha do negócio que o usuário pode abrir: criou, ou recebeu por usuário, função ou tarefa.
     *
     * Antes de 2026-10-27 show() e update() montavam esta consulta à mão com `->where(created_by)
     * ->orWhere(...)` sem agrupar: o OR escapava do `business_id` e do `id`. No update() o join não
     * filtrava a planilha, então qualquer planilha compartilhada com o usuário fazia o `firstOrFail`
     * passar e o UPDATE (que filtra só business e id) gravava por cima de planilha de colega.
     * Agora o OR fica dentro de um grupo, como em planilhasVisiveis().
     */
    private function planilhaAcessivel(int $business_id, int $id)
    {
        $user_id = (int) request()->session()->get('user.id');
        $role_id = User::find($user_id)?->roles()->first()?->id;
        $todo_ids = $this->moduleUtil->getModuleData('getAssignedTaskForUser', $user_id)['Essentials'] ?? [];

        $query = Spreadsheet::where('sheet_spreadsheets.business_id', $business_id)
            ->where('sheet_spreadsheets.id', $id)
            ->leftJoin('sheet_spreadsheet_shares as SSS', function ($join) use ($id) {
                $join->on('sheet_spreadsheets.id', '=', 'SSS.sheet_spreadsheet_id')
                    ->where('SSS.sheet_spreadsheet_id', '=', $id);
            });

        if (! auth()->user()->can('superadmin')) {
            $query->where(function ($q) use ($user_id, $role_id, $todo_ids) {
                $q->where('sheet_spreadsheets.created_by', $user_id)
                    ->orWhere(fn ($q) => $q->where('SSS.shared_id', $user_id)->where('SSS.shared_with', 'user'));
                if ($role_id !== null) {
                    $q->orWhere(fn ($q) => $q->where('SSS.shared_id', $role_id)->where('SSS.shared_with', 'role'));
                }
                if (! empty($todo_ids)) {
                    $q->orWhere(fn ($q) => $q->whereIn('SSS.shared_id', $todo_ids)->where('SSS.shared_with', 'todo'));
                }
            });
        }

        return $query;
    }

    /**
     * Planilhas que o usuário da sessão vê no business: as que criou e as compartilhadas com ele
     * (usuário, função ou tarefa). Superadmin vê todas do business.
     *
     * Tier 0 (ADR 0093): o filtro de compartilhamento fica DENTRO de um grupo. A versão anterior
     * encadeava `orWhere` sem agrupar, e os ramos de compartilhamento escapavam do
     * `business_id`.
     */
    private function planilhasVisiveis(int $business_id)
    {
        $user_id = (int) request()->session()->get('user.id');
        $role_id = User::find($user_id)?->roles()->first()?->id;
        $todo_ids = $this->moduleUtil->getModuleData('getAssignedTaskForUser', $user_id)['Essentials'] ?? [];

        $query = Spreadsheet::with(['createdBy', 'shares'])
            ->where('sheet_spreadsheets.business_id', $business_id)
            ->leftJoin('sheet_spreadsheet_shares as SSS', 'sheet_spreadsheets.id', '=', 'SSS.sheet_spreadsheet_id');

        if (! auth()->user()->can('superadmin')) {
            $query->where(function ($q) use ($user_id, $role_id, $todo_ids) {
                $q->where('sheet_spreadsheets.created_by', $user_id)
                    ->orWhere(fn ($q) => $q->where('SSS.shared_id', $user_id)->where('SSS.shared_with', 'user'));
                if ($role_id !== null) {
                    $q->orWhere(fn ($q) => $q->where('SSS.shared_id', $role_id)->where('SSS.shared_with', 'role'));
                }
                if (! empty($todo_ids)) {
                    $q->orWhere(fn ($q) => $q->whereIn('SSS.shared_id', $todo_ids)->where('SSS.shared_with', 'todo'));
                }
            });
        }

        return $query->select('sheet_spreadsheets.id', 'name', 'sheet_spreadsheets.updated_at', 'created_by', 'folder_id')
            ->orderByDesc('sheet_spreadsheets.updated_at')
            ->groupBy('sheet_spreadsheets.id')
            ->get();
    }

    /** DTO da linha — sem `sheet_data`; compartilhamento como contagem, como a linha mostra. */
    private function buildPlanilhasPayload(int $business_id): array
    {
        $eu = (int) auth()->user()->id;

        return $this->planilhasVisiveis($business_id)->map(function ($s) use ($eu) {
            $conta = fn (string $tipo) => $s->shares->where('shared_with', $tipo)->count();

            return [
                'id'            => (int) $s->id,
                'nome'          => (string) $s->name,
                'pasta_id'      => $s->folder_id !== null ? (int) $s->folder_id : null,
                'atualizada_em' => optional($s->updated_at)->toIso8601String(),
                'criada_por'    => $s->createdBy?->user_full_name,
                'minha'         => (int) $s->created_by === $eu,
                'compartilhada' => ['usuarios' => $conta('user'), 'funcoes' => $conta('role'), 'tarefas' => $conta('todo')],
            ];
        })->values()->all();
    }

    private function buildPastasPayload(int $business_id): array
    {
        return Category::where('business_id', $business_id)
            ->where('category_type', 'spreadsheet')
            ->orderBy('name', 'asc')
            ->get(['id', 'name'])
            ->map(fn ($c) => ['id' => (int) $c->id, 'nome' => (string) $c->name])
            ->all();
    }
}
