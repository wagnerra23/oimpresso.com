<?php

declare(strict_types=1);

namespace Modules\KB\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\KB\Entities\KbNode;
use Modules\KB\Entities\KbNodeVersion;
use Modules\KB\Http\Requests\StoreKbNodeRequest;
use Modules\KB\Services\KbArticleService;

/**
 * KbNodeController — CRUD de kb_nodes (artigos editáveis + leitura de bridges).
 *
 * Contrato endpoints: memory/requisitos/KB/SCHEMA-DB-V1.md §11
 *
 * Permissions (Spatie via PermissionRegistry):
 *   - GET index/show → kb.view
 *   - POST/PUT       → kb.write
 *   - DELETE         → kb.softdelete
 *   - POST restore   → kb.restore
 *
 * Dívida técnica preservada (legacy): middleware `can:copiloto.mcp.memory.manage`
 * fica no KbController até PR de rename Spatie.
 */
class KbNodeController extends Controller
{
    public function __construct(
        private readonly KbArticleService $articles,
    ) {
        $this->middleware('auth');
        // V1 reusa permission canon "copiloto.mcp.memory.manage" pra .view e ações.
        // TODO[CL]: rename pra `kb.view`/`kb.write`/`kb.softdelete` em PR Spatie separado.
        $this->middleware('can:copiloto.mcp.memory.manage');
    }

    /**
     * GET /kb/nodes — paginação JSON de kb_nodes (?type, ?category, ?q, ?cursor).
     *
     * Filter/paginate logic delegada a {@see KbArticleService} (Wave J 2026-05-16 —
     * thin extraction, zero regressão de payload).
     */
    public function index(Request $request): JsonResponse
    {
        return response()->json($this->articles->paginate($request));
    }

    /**
     * GET /kb/nodes/{slug} — detalhe + body (com JOIN mcp se bridge).
     */
    public function show(Request $request, string $slug): JsonResponse
    {
        $node = KbNode::query()
            ->where('slug', $slug)
            ->with(['sourceDoc:id,content_md,git_path,git_sha,metadata'])
            ->firstOrFail();

        // Métricas: reads_count++ atomic (sem race condition em concurrent reads).
        DB::table('kb_nodes')->where('id', $node->id)->increment('reads_count');

        return response()->json([
            'node' => $node,
            'content_md' => $node->isBridge()
                ? optional($node->sourceDoc)->content_md
                : null,
            'github_url' => $node->isBridge() && $node->sourceDoc?->git_path
                ? "https://github.com/wagnerra23/oimpresso.com/blob/main/{$node->sourceDoc->git_path}"
                : null,
        ]);
    }

    /**
     * POST /kb/nodes — cria artigo (type=article, is_editable=true).
     *
     * Validation via {@see StoreKbNodeRequest} (D8.c Security 2026-05-16) — rules
     * preservadas idênticas ao inline original; authorize() reusa
     * 'copiloto.mcp.memory.manage'.
     */
    public function store(StoreKbNodeRequest $request): JsonResponse
    {
        $data = $request->validated();

        $node = new KbNode();
        $node->fill(array_merge($data, [
            'type'           => $data['type'] ?? 'article',
            'slug'           => $data['slug'] ?? Str::slug($data['title']),
            'is_editable'    => true,
            'status'         => $data['status'] ?? 'ok',
            'author_user_id' => Auth::id(),
        ]));
        $node->save();

        return response()->json(['node' => $node], 201);
    }

    /**
     * PUT /kb/nodes/{slug} — edita artigo (autosnap → kb_node_versions via Observer).
     */
    public function update(Request $request, string $slug): JsonResponse
    {
        $node = KbNode::query()->where('slug', $slug)->firstOrFail();

        if (! $node->is_editable) {
            return response()->json([
                'ok' => false,
                'error' => 'NODE_NOT_EDITABLE',
                'message' => 'Este é um nó canônico (bridge). Edite o arquivo em memory/* e re-rode bridge.',
            ], 422);
        }

        $data = $request->validate([
            'title'          => 'sometimes|string|max:255',
            'excerpt'        => 'nullable|string|max:500',
            'body_blocks'    => 'nullable|array',
            'category_id'    => 'nullable|integer|exists:kb_categories,id',
            'subcategory_id' => 'nullable|integer|exists:kb_subcategories,id',
            'nivel'          => 'nullable|string|in:iniciante,intermediario,avancado',
            'equip'          => 'nullable|string|max:80',
            'tags'           => 'nullable|array',
            'pinned'         => 'sometimes|boolean',
            'status'         => 'sometimes|string|in:draft,ok,outdated,deprecated',
            'read_time_min'  => 'nullable|integer|min:1|max:600',
            'change_reason'  => 'nullable|string|max:255',
        ]);

        // KbNodeObserver::updating() cria snapshot antes de aplicar.
        $node->fill($data);
        $node->save();

        return response()->json(['node' => $node->fresh()]);
    }

    /**
     * DELETE /kb/nodes/{slug} — soft-delete (LGPD).
     */
    public function destroy(Request $request, string $slug): JsonResponse
    {
        $request->validate(['confirm' => 'required|in:CONFIRMO']);

        $node = KbNode::query()->where('slug', $slug)->firstOrFail();
        $node->delete();

        return response()->json([
            'ok' => true,
            'message' => "Node '{$slug}' soft-deleted. Restaurável em 30 dias.",
        ]);
    }

    /**
     * POST /kb/nodes/{slug}/restore — restore soft-deleted.
     */
    public function restore(Request $request, string $slug): JsonResponse
    {
        $node = KbNode::onlyTrashed()->where('slug', $slug)->firstOrFail();
        $node->restore();

        return response()->json(['ok' => true, 'node' => $node->fresh()]);
    }

    /**
     * POST /kb/nodes/{slug}/reverify — botão "Re-verificar".
     *
     * Marca last_verified_at=now() pra UI mostrar "Última revisão por <user> em <data>".
     * Não muda body. Usado pelo dono pra confirmar frescor.
     */
    public function reverify(Request $request, string $slug): JsonResponse
    {
        $node = KbNode::query()->where('slug', $slug)->firstOrFail();
        $node->last_verified_at = now();
        $node->save();

        return response()->json(['ok' => true, 'last_verified_at' => $node->last_verified_at]);
    }
}
