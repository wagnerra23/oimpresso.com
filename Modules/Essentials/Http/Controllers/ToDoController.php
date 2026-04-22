<?php

namespace Modules\Essentials\Http\Controllers;

use App\Media;
use App\User;
use App\Utils\ModuleUtil;
use App\Utils\Util;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Essentials\Entities\EssentialsTodoComment;
use Modules\Essentials\Entities\ToDo;
use Modules\Essentials\Http\Requests\ToDoCommentRequest;
use Modules\Essentials\Http\Requests\ToDoStoreRequest;
use Modules\Essentials\Http\Requests\ToDoUpdateRequest;
use Modules\Essentials\Http\Requests\ToDoUploadDocumentRequest;
use Modules\Essentials\Notifications\NewTaskCommentNotification;
use Modules\Essentials\Notifications\NewTaskDocumentNotification;
use Modules\Essentials\Notifications\NewTaskNotification;
use Spatie\Activitylog\Models\Activity;

/**
 * ToDoController — versão Inertia (React).
 *
 * Migração das 9 views Blade para 4 Pages React:
 *   - Essentials/Todo/Index  (listagem + filtros + deletar + troca rápida de status)
 *   - Essentials/Todo/Create (form + anexo)
 *   - Essentials/Todo/Edit   (form + anexo)
 *   - Essentials/Todo/Show   (detalhe + tabs: comentários, anexos, atividades, docs compartilhados)
 *
 * Paridade com o Blade preservada (princípio "não perde nada"):
 *   - Comentários (add/delete)
 *   - Uploads de documentos + remoção
 *   - View de Spreadsheets compartilhados (via hook moduleViewPartials)
 *   - Notifications (NewTask, NewTaskComment, NewTaskDocument)
 *   - Activity log
 *   - Scope por business_id + filtro não-admin (só próprias OU atribuídas)
 *   - Permissões Spatie: essentials.{assign,add,edit,delete}_todos
 *   - task_id com prefixo configurável em essentials_settings
 */
class ToDoController extends Controller
{
    protected Util $commonUtil;

    protected ModuleUtil $moduleUtil;

    public function __construct(Util $commonUtil, ModuleUtil $moduleUtil)
    {
        $this->commonUtil = $commonUtil;
        $this->moduleUtil = $moduleUtil;
    }

    /**
     * Listagem paginada com filtros. Substitui o DataTables AJAX da versão Blade.
     */
    public function index(Request $request): Response
    {
        $businessId = $this->currentBusinessId();
        $this->authorizeAccess($businessId);

        $authId = auth()->user()->id;
        $isAdmin = $this->moduleUtil->is_admin(auth()->user(), $businessId);

        $query = ToDo::where('business_id', $businessId)
            ->with([
                'users:id,first_name,last_name,username',
                'assigned_by:id,first_name,last_name,username',
            ]);

        // Não-admin só vê próprias OU atribuídas a ele
        if (! $isAdmin) {
            $query->where(function ($q) use ($authId) {
                $q->where('created_by', $authId)
                    ->orWhereHas('users', function ($inner) use ($authId) {
                        $inner->where('user_id', $authId);
                    });
            });
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->string('priority'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        if ($request->filled('user_id')) {
            $userId = $request->integer('user_id');
            $query->whereHas('users', fn ($q) => $q->where('user_id', $userId));
        }
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereDate('date', '>=', $request->string('start_date'))
                ->whereDate('date', '<=', $request->string('end_date'));
        }

        $paginated = $query->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        $paginated->getCollection()->transform(fn (ToDo $t) => $this->toRowShape($t));

        $assignableUsers = [];
        if (auth()->user()->can('essentials.assign_todos')) {
            $assignableUsers = $this->dropdownUsers($businessId);
        }

        return Inertia::render('Essentials/Todo/Index', [
            'todos'            => $paginated,
            'filtros'          => [
                'status'     => $request->string('status')->toString() ?: null,
                'priority'   => $request->string('priority')->toString() ?: null,
                'user_id'    => $request->integer('user_id') ?: null,
                'start_date' => $request->string('start_date')->toString() ?: null,
                'end_date'   => $request->string('end_date')->toString() ?: null,
            ],
            'assignableUsers'  => $assignableUsers,
            'statuses'         => $this->statusOptions(),
            'priorities'       => $this->priorityOptions(),
            'can'              => $this->policies(),
        ]);
    }

    public function create(): Response
    {
        $businessId = $this->currentBusinessId();
        $this->authorizeAdd($businessId);

        $users = [];
        if (auth()->user()->can('essentials.assign_todos') && empty(request()->input('from_calendar'))) {
            $users = $this->dropdownUsers($businessId);
        }

        return Inertia::render('Essentials/Todo/Create', [
            'users'      => $users,
            'statuses'   => $this->statusOptions(),
            'priorities' => $this->priorityOptions(),
            'can'        => $this->policies(),
        ]);
    }

    public function store(ToDoStoreRequest $request): RedirectResponse
    {
        $businessId = $this->currentBusinessId();
        $this->authorizeAdd($businessId);

        $createdBy = $request->user()->id;
        $data = $request->validated();

        $data['date']        = $this->parseDate($data['date']);
        $data['end_date']    = ! empty($data['end_date']) ? $this->parseDate($data['end_date']) : null;
        $data['business_id'] = $businessId;
        $data['created_by']  = $createdBy;
        $data['status']      = $data['status'] ?? 'new';

        $assignees = $data['users'] ?? [];
        unset($data['users']);

        if (! auth()->user()->can('essentials.assign_todos') || empty($assignees)) {
            $assignees = [$createdBy];
        }

        $refCount = $this->commonUtil->setAndGetReferenceCount('essentials_todos');
        $settings = request()->session()->get('business.essentials_settings');
        $settings = ! empty($settings) ? json_decode($settings, true) : [];
        $prefix   = ! empty($settings['essentials_todos_prefix']) ? $settings['essentials_todos_prefix'] : '';
        $data['task_id'] = $this->commonUtil->generateReferenceNumber('essentials_todos', $refCount, null, $prefix);

        $todo = ToDo::create($data);
        $todo->users()->sync($assignees);

        $this->commonUtil->activityLog($todo, 'added');

        $toNotify = $todo->users->filter(fn ($u) => $u->id !== $createdBy);
        \Notification::send($toNotify, new NewTaskNotification($todo));

        return redirect()
            ->route('todo.show', $todo->id)
            ->with('success', __('essentials::lang.task') . ' ' . $todo->task_id . ' ' . __('lang_v1.added'));
    }

    public function show($id): Response
    {
        $businessId = $this->currentBusinessId();
        $this->authorizeAccess($businessId);

        $todo = $this->scopedQueryForUser($businessId)
            ->with([
                'assigned_by:id,first_name,last_name,username',
                'users:id,first_name,last_name,username',
                'comments.added_by:id,first_name,last_name,username',
                'media.uploaded_by_user:id,first_name,last_name,username',
            ])
            ->findOrFail($id);

        $activities = Activity::forSubject($todo)
            ->with(['causer:id,first_name,last_name,username'])
            ->latest()
            ->get()
            ->map(fn (Activity $a) => [
                'id'          => $a->id,
                'description' => $a->description,
                'causer_name' => optional($a->causer)->user_full_name ?? '—',
                'created_at'  => optional($a->created_at)->format('Y-m-d H:i'),
            ]);

        return Inertia::render('Essentials/Todo/Show', [
            'todo'       => $this->toDetailShape($todo),
            'comments'   => $todo->comments->map(fn ($c) => $this->toCommentShape($c))->values(),
            'documents'  => $todo->media->map(fn ($m) => $this->toMediaShape($m, $todo))->values(),
            'activities' => $activities,
            'statuses'   => $this->statusOptions(),
            'priorities' => $this->priorityOptions(),
            'can'        => $this->policies(),
        ]);
    }

    public function edit($id): Response
    {
        $businessId = $this->currentBusinessId();
        $this->authorizeEdit($businessId);

        $todo = $this->scopedQueryForUser($businessId)
            ->with(['users:id,first_name,last_name,username'])
            ->findOrFail($id);

        $users = [];
        if (auth()->user()->can('essentials.assign_todos')) {
            $users = $this->dropdownUsers($businessId);
        }

        return Inertia::render('Essentials/Todo/Edit', [
            'todo'       => array_merge($this->toDetailShape($todo), [
                'assigned_user_ids' => $todo->users->pluck('id')->all(),
            ]),
            'users'      => $users,
            'statuses'   => $this->statusOptions(),
            'priorities' => $this->priorityOptions(),
            'can'        => $this->policies(),
        ]);
    }

    public function update(ToDoUpdateRequest $request, $id): RedirectResponse
    {
        $businessId = $this->currentBusinessId();
        $onlyStatus = $request->boolean('only_status');

        if ($onlyStatus) {
            $this->authorizeAccess($businessId);
        } else {
            $this->authorizeEdit($businessId);
        }

        $todo = $this->scopedQueryForUser($businessId)->findOrFail($id);
        $before = $todo->replicate();

        if ($onlyStatus) {
            $todo->update(['status' => $request->string('status')]);
        } else {
            $data = $request->validated();
            $data['date']     = $this->parseDate($data['date']);
            $data['end_date'] = ! empty($data['end_date']) ? $this->parseDate($data['end_date']) : null;
            $data['status']   = $data['status'] ?? 'new';

            $assignees = $data['users'] ?? null;
            unset($data['users']);

            $todo->update($data);

            if (auth()->user()->can('essentials.assign_todos') && $assignees !== null) {
                $todo->users()->sync($assignees);
            }
        }

        $this->commonUtil->activityLog($todo, 'edited', $before);

        $redirect = $onlyStatus
            ? back()
            : redirect()->route('todo.show', $todo->id);

        return $redirect->with('success', __('lang_v1.updated_success'));
    }

    public function destroy($id): RedirectResponse
    {
        $businessId = $this->currentBusinessId();
        $this->authorizeDelete($businessId);

        $isAdmin = $this->moduleUtil->is_admin(auth()->user(), $businessId);
        $query = ToDo::where('business_id', $businessId)->where('id', $id);
        if (! $isAdmin) {
            $query->where('created_by', auth()->user()->id);
        }
        $query->delete();

        return redirect()
            ->route('todo.index')
            ->with('success', __('lang_v1.deleted_success'));
    }

    public function addComment(ToDoCommentRequest $request): RedirectResponse
    {
        $businessId = $this->currentBusinessId();
        $this->authorizeAccess($businessId);

        $authId = auth()->user()->id;
        $todo = $this->scopedQueryForUser($businessId)
            ->with('users')
            ->findOrFail($request->integer('task_id'));

        $comment = EssentialsTodoComment::create([
            'task_id'    => $todo->id,
            'comment'    => $request->string('comment'),
            'comment_by' => $authId,
        ]);

        $toNotify = $todo->users->filter(fn ($u) => $u->id !== $authId);
        \Notification::send($toNotify, new NewTaskCommentNotification($comment));

        return back()->with('success', __('lang_v1.added'));
    }

    public function deleteComment($id): RedirectResponse
    {
        $this->authorizeAccess($this->currentBusinessId());

        EssentialsTodoComment::where('comment_by', auth()->user()->id)
            ->where('id', $id)
            ->delete();

        return back()->with('success', __('lang_v1.deleted_success'));
    }

    public function uploadDocument(ToDoUploadDocumentRequest $request): RedirectResponse
    {
        $businessId = $this->currentBusinessId();
        $this->authorizeAccess($businessId);

        $authId = auth()->user()->id;
        $todo = $this->scopedQueryForUser($businessId)
            ->with('users')
            ->findOrFail($request->integer('task_id'));

        Media::uploadMedia($todo->business_id, $todo, $request, 'documents');

        $toNotify = $todo->users->filter(fn ($u) => $u->id !== $authId);
        \Notification::send($toNotify, new NewTaskDocumentNotification([
            'task_id'                => $todo->task_id,
            'uploaded_by'            => $authId,
            'id'                     => $todo->id,
            'uploaded_by_user_name'  => auth()->user()->user_full_name,
        ]));

        return back()->with('success', __('lang_v1.added'));
    }

    public function deleteDocument($id): RedirectResponse
    {
        $this->authorizeAccess($this->currentBusinessId());

        $media = Media::findOrFail($id);
        if ($media->model_type === ToDo::class) {
            $todo = ToDo::findOrFail($media->model_id);
            $authId = auth()->user()->id;

            // Só quem subiu ou o criador da task podem remover
            if (in_array($authId, [$media->uploaded_by, $todo->created_by], true)) {
                if (is_file((string) $media->display_path)) {
                    @unlink($media->display_path);
                }
                $media->delete();
            }
        }

        return back()->with('success', __('lang_v1.deleted_success'));
    }

    /**
     * Spreadsheets compartilhadas com a tarefa via hook moduleViewPartials.
     * Retorna JSON pra ser consumido por um Dialog do React (não Inertia render —
     * o Dialog é local à página).
     */
    public function viewSharedDocs($id): JsonResponse
    {
        $businessId = $this->currentBusinessId();
        $this->authorizeAccess($businessId);

        $todo = ToDo::where('business_id', $businessId)->findOrFail($id);

        $moduleData = $this->moduleUtil->getModuleData('getSharedSpreadsheetForGivenData', [
            'business_id' => $businessId,
            'shared_with' => 'todo',
            'shared_id'   => $id,
        ]);

        $sheets = [];
        if (! empty($moduleData['Spreadsheet']) && is_iterable($moduleData['Spreadsheet'])) {
            foreach ($moduleData['Spreadsheet'] as $sheet) {
                $sheets[] = [
                    'id'         => $sheet->id ?? null,
                    'name'       => $sheet->name ?? (string) ($sheet->title ?? '—'),
                    'url'        => $sheet->url ?? null,
                    'created_at' => isset($sheet->created_at) ? (string) $sheet->created_at : null,
                ];
            }
        }

        return response()->json([
            'task_id' => $todo->task_id,
            'sheets'  => $sheets,
        ]);
    }

    // ------------------------------------------------------------------------
    // Helpers privados
    // ------------------------------------------------------------------------

    protected function currentBusinessId(): int
    {
        return (int) (session('business.id') ?: request()->session()->get('user.business_id'));
    }

    /**
     * Converte a data do input (ISO Y-m-d, opcionalmente com hora) em DATETIME MySQL.
     * Tolera formatos configurados no business via uf_date como fallback.
     */
    protected function parseDate(?string $date): ?string
    {
        if ($date === null || $date === '') {
            return null;
        }

        try {
            return Carbon::parse($date)->format('Y-m-d H:i:s');
        } catch (\Throwable $e) {
            // Fallback para formato configurado no business
            try {
                return $this->commonUtil->uf_date($date, true);
            } catch (\Throwable $e2) {
                return null;
            }
        }
    }

    protected function authorizeAccess(int $businessId): void
    {
        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($businessId, 'essentials_module'))) {
            abort(403, 'Unauthorized action.');
        }
    }

    protected function authorizeAdd(int $businessId): void
    {
        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($businessId, 'essentials_module')) && ! auth()->user()->can('essentials.add_todos')) {
            abort(403, 'Unauthorized action.');
        }
    }

    protected function authorizeEdit(int $businessId): void
    {
        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($businessId, 'essentials_module')) && ! auth()->user()->can('essentials.edit_todos')) {
            abort(403, 'Unauthorized action.');
        }
    }

    protected function authorizeDelete(int $businessId): void
    {
        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($businessId, 'essentials_module')) && ! auth()->user()->can('essentials.delete_todos')) {
            abort(403, 'Unauthorized action.');
        }
    }

    protected function scopedQueryForUser(int $businessId)
    {
        $query = ToDo::where('business_id', $businessId);
        $isAdmin = $this->moduleUtil->is_admin(auth()->user(), $businessId);

        if (! $isAdmin) {
            $query->where(function ($q) {
                $q->where('created_by', auth()->user()->id)
                    ->orWhereHas('users', function ($inner) {
                        $inner->where('user_id', auth()->user()->id);
                    });
            });
        }

        return $query;
    }

    protected function dropdownUsers(int $businessId): array
    {
        $raw = User::forDropdown($businessId, false);
        // forDropdown retorna array id => nome. Converte pro shape de select.
        return collect($raw)->map(fn ($label, $id) => [
            'id'    => (int) $id,
            'label' => (string) $label,
        ])->values()->all();
    }

    protected function statusOptions(): array
    {
        $map = ToDo::getTaskStatus();
        $out = [];
        foreach ($map as $value => $label) {
            $out[] = ['value' => $value, 'label' => (string) $label];
        }
        return $out;
    }

    protected function priorityOptions(): array
    {
        $map = ToDo::getTaskPriorities();
        $out = [];
        foreach ($map as $value => $label) {
            $out[] = ['value' => $value, 'label' => (string) $label];
        }
        return $out;
    }

    protected function policies(): array
    {
        $u = auth()->user();
        return [
            'add'    => (bool) $u?->can('essentials.add_todos'),
            'edit'   => (bool) $u?->can('essentials.edit_todos'),
            'delete' => (bool) $u?->can('essentials.delete_todos'),
            'assign' => (bool) $u?->can('essentials.assign_todos'),
        ];
    }

    protected function toRowShape(ToDo $t): array
    {
        return [
            'id'               => $t->id,
            'task_id'          => $t->task_id,
            'task'             => $t->task,
            'status'           => $t->status,
            'priority'         => $t->priority,
            'date'             => optional($t->date)->format('Y-m-d H:i'),
            'end_date'         => optional($t->end_date)->format('Y-m-d H:i'),
            'estimated_hours'  => $t->estimated_hours,
            'assigned_by'      => optional($t->assigned_by)->user_full_name,
            'users'            => $t->users->map(fn ($u) => [
                'id'   => $u->id,
                'name' => $u->user_full_name,
            ])->values(),
            'created_at_human' => optional($t->created_at)->diffForHumans(),
            'created_by'       => $t->created_by,
        ];
    }

    protected function toDetailShape(ToDo $t): array
    {
        return [
            'id'              => $t->id,
            'task_id'         => $t->task_id,
            'task'            => $t->task,
            'description'     => $t->description,
            'status'          => $t->status,
            'priority'        => $t->priority,
            'date'            => optional($t->date)->format('Y-m-d H:i'),
            'end_date'        => optional($t->end_date)->format('Y-m-d H:i'),
            'estimated_hours' => $t->estimated_hours,
            'created_by'      => $t->created_by,
            'created_at'      => optional($t->created_at)->format('Y-m-d H:i'),
            'updated_at'      => optional($t->updated_at)->format('Y-m-d H:i'),
            'assigned_by'     => [
                'id'   => optional($t->assigned_by)->id,
                'name' => optional($t->assigned_by)->user_full_name,
            ],
            'users'           => $t->users->map(fn ($u) => [
                'id'   => $u->id,
                'name' => $u->user_full_name,
            ])->values(),
        ];
    }

    protected function toCommentShape(EssentialsTodoComment $c): array
    {
        return [
            'id'               => $c->id,
            'comment'          => $c->comment,
            'author_id'        => $c->comment_by,
            'author_name'      => optional($c->added_by)->user_full_name ?? '—',
            'created_at'       => optional($c->created_at)->format('Y-m-d H:i'),
            'created_at_human' => optional($c->created_at)->diffForHumans(),
            'can_delete'       => $c->comment_by === auth()->user()->id,
        ];
    }

    protected function toMediaShape(Media $m, ToDo $todo): array
    {
        $authId = auth()->user()->id;
        return [
            'id'            => $m->id,
            'name'          => $m->display_name,
            'description'   => $m->description,
            'url'           => $m->display_url,
            'uploaded_by'   => optional($m->uploaded_by_user)->user_full_name ?? '',
            'uploaded_at'   => optional($m->created_at)->format('Y-m-d H:i'),
            'can_delete'    => in_array($authId, [$m->uploaded_by, $todo->created_by], true),
        ];
    }
}
