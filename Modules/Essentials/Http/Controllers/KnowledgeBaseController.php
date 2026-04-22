<?php

namespace Modules\Essentials\Http\Controllers;

use App\User;
use App\Utils\ModuleUtil;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Essentials\Entities\KnowledgeBase;

/**
 * KnowledgeBaseController — versão Inertia.
 *
 * Hierarquia em 3 níveis:
 *   knowledge_base (livro)
 *     └── section (capítulo)
 *           └── article (artigo)
 *
 * Paridade com Blade preservada:
 *   - Scope por business_id
 *   - Visibilidade: público, só criador, ou lista de users (share_with=only_with)
 *   - CRUD completo
 *   - Hierarquia eager-loaded (children + children.children)
 */
class KnowledgeBaseController extends Controller
{
    protected ModuleUtil $moduleUtil;

    public function __construct(ModuleUtil $moduleUtil)
    {
        $this->moduleUtil = $moduleUtil;
    }

    public function index(): Response
    {
        $businessId = $this->currentBusinessId();
        $this->authorizeAccess($businessId);

        $userId = auth()->user()->id;

        $books = KnowledgeBase::where('business_id', $businessId)
            ->where('kb_type', 'knowledge_base')
            ->whereNull('parent_id')
            ->with(['children', 'children.children'])
            ->where(function ($query) use ($userId) {
                $query->whereHas('users', function ($q) use ($userId) {
                    $q->where('user_id', $userId);
                })
                    ->orWhere('created_by', $userId)
                    ->orWhere('share_with', 'public');
            })
            ->orderBy('title')
            ->get()
            ->map(fn (KnowledgeBase $k) => $this->toBookShape($k));

        return Inertia::render('Essentials/Knowledge/Index', [
            'books' => $books->values(),
        ]);
    }

    public function create(Request $request): Response
    {
        $businessId = $this->currentBusinessId();
        $this->authorizeAccess($businessId);

        $parent = null;
        $users = [];

        if ($request->filled('parent')) {
            $parent = KnowledgeBase::where('business_id', $businessId)
                ->findOrFail($request->input('parent'));
        } else {
            $users = $this->dropdownUsers($businessId);
        }

        return Inertia::render('Essentials/Knowledge/Create', [
            'parent' => $parent ? [
                'id'      => $parent->id,
                'title'   => $parent->title,
                'kb_type' => $parent->kb_type,
            ] : null,
            'users'  => $users,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $businessId = $this->currentBusinessId();
        $this->authorizeAccess($businessId);

        $validated = $request->validate([
            'title'      => 'required|string|max:255',
            'content'    => 'nullable|string',
            'kb_type'    => 'nullable|in:knowledge_base,section,article',
            'parent_id'  => 'nullable|integer|exists:essentials_kb,id',
            'share_with' => 'nullable|in:public,only_with',
            'user_ids'   => 'nullable|array',
            'user_ids.*' => 'integer|exists:users,id',
        ]);

        $data = [
            'business_id' => $businessId,
            'created_by'  => $request->user()->id,
            'title'       => $validated['title'],
            'content'     => $validated['content'] ?? null,
            'kb_type'     => $validated['kb_type'] ?? 'knowledge_base',
            'parent_id'   => $validated['parent_id'] ?? null,
            'share_with'  => $validated['share_with'] ?? null,
        ];

        $kb = KnowledgeBase::create($data);

        if ($kb->kb_type === 'knowledge_base' && $kb->share_with === 'only_with') {
            $kb->users()->sync($validated['user_ids'] ?? []);
        }

        return redirect()
            ->route('knowledge-base.show', $kb->id)
            ->with('success', __('lang_v1.success'));
    }

    public function show($id): Response
    {
        $businessId = $this->currentBusinessId();
        $this->authorizeAccess($businessId);

        $obj = KnowledgeBase::where('business_id', $businessId)
            ->with(['children', 'children.children', 'users'])
            ->findOrFail($id);

        // Descobre qual é o "livro" de topo para renderizar o sidebar
        if ($obj->kb_type === 'knowledge_base') {
            $book = $obj;
            $sectionId = null;
            $articleId = null;
        } elseif ($obj->kb_type === 'section') {
            $book = KnowledgeBase::where('business_id', $businessId)
                ->with(['children', 'children.children'])
                ->findOrFail($obj->parent_id);
            $sectionId = $obj->id;
            $articleId = null;
        } else { // article
            $section = KnowledgeBase::where('business_id', $businessId)
                ->findOrFail($obj->parent_id);
            $book = KnowledgeBase::where('business_id', $businessId)
                ->with(['children', 'children.children'])
                ->findOrFail($section->parent_id);
            $sectionId = $section->id;
            $articleId = $obj->id;
        }

        return Inertia::render('Essentials/Knowledge/Show', [
            'item' => [
                'id'         => $obj->id,
                'title'      => $obj->title,
                'content'    => $obj->content,
                'kb_type'    => $obj->kb_type,
                'share_with' => $obj->share_with,
                'shared_users' => $obj->users->map(fn ($u) => $u->user_full_name)->values(),
                'created_by' => $obj->created_by,
                'parent_id'  => $obj->parent_id,
            ],
            'book'      => $this->toBookShape($book),
            'sectionId' => $sectionId,
            'articleId' => $articleId,
        ]);
    }

    public function edit($id): Response
    {
        $businessId = $this->currentBusinessId();
        $this->authorizeAccess($businessId);

        $kb = KnowledgeBase::where('business_id', $businessId)
            ->with(['users:id,first_name,last_name,username'])
            ->findOrFail($id);

        $users = [];
        if ($kb->kb_type === 'knowledge_base') {
            $users = $this->dropdownUsers($businessId);
        }

        return Inertia::render('Essentials/Knowledge/Edit', [
            'kb' => [
                'id'              => $kb->id,
                'title'           => $kb->title,
                'content'         => $kb->content,
                'kb_type'         => $kb->kb_type,
                'parent_id'       => $kb->parent_id,
                'share_with'      => $kb->share_with,
                'assigned_user_ids' => $kb->users->pluck('id')->all(),
            ],
            'users' => $users,
        ]);
    }

    public function update(Request $request, $id): RedirectResponse
    {
        $businessId = $this->currentBusinessId();
        $this->authorizeAccess($businessId);

        $validated = $request->validate([
            'title'      => 'required|string|max:255',
            'content'    => 'nullable|string',
            'share_with' => 'nullable|in:public,only_with',
            'user_ids'   => 'nullable|array',
            'user_ids.*' => 'integer|exists:users,id',
        ]);

        $kb = KnowledgeBase::where('business_id', $businessId)->findOrFail($id);
        $kb->update([
            'title'      => $validated['title'],
            'content'    => $validated['content'] ?? null,
            'share_with' => $validated['share_with'] ?? null,
        ]);

        if ($kb->kb_type === 'knowledge_base' && $kb->share_with === 'only_with') {
            $kb->users()->sync($validated['user_ids'] ?? []);
        }

        return redirect()
            ->route('knowledge-base.show', $kb->id)
            ->with('success', __('lang_v1.success'));
    }

    public function destroy(Request $request, $id): RedirectResponse
    {
        $businessId = $this->currentBusinessId();
        $this->authorizeAccess($businessId);

        KnowledgeBase::where('business_id', $businessId)
            ->where('id', $id)
            ->delete();

        return redirect()
            ->route('knowledge-base.index')
            ->with('success', __('lang_v1.deleted_success'));
    }

    // ------------------------------------------------------------------------

    protected function toBookShape(KnowledgeBase $k): array
    {
        return [
            'id'          => $k->id,
            'title'       => $k->title,
            'content'     => $k->content,
            'kb_type'     => $k->kb_type,
            'share_with'  => $k->share_with,
            'children'    => $k->relationLoaded('children')
                ? $k->children->map(fn ($section) => [
                    'id'       => $section->id,
                    'title'    => $section->title,
                    'content'  => $section->content,
                    'kb_type'  => $section->kb_type,
                    'children' => $section->relationLoaded('children')
                        ? $section->children->map(fn ($article) => [
                            'id'      => $article->id,
                            'title'   => $article->title,
                            'kb_type' => $article->kb_type,
                        ])->values()
                        : [],
                ])->values()
                : [],
        ];
    }

    protected function dropdownUsers(int $businessId): array
    {
        return collect(User::forDropdown($businessId, false))
            ->map(fn ($label, $id) => ['id' => (int) $id, 'label' => (string) $label])
            ->values()->all();
    }

    protected function currentBusinessId(): int
    {
        return (int) (session('business.id') ?: request()->session()->get('user.business_id'));
    }

    protected function authorizeAccess(int $businessId): void
    {
        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($businessId, 'essentials_module'))) {
            abort(403, 'Unauthorized action.');
        }
    }
}
