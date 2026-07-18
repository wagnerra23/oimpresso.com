<?php

namespace Modules\KB\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Jana\Entities\Mcp\McpMemoryDocument;
use Modules\KB\Entities\KbCategory;
use Modules\KB\Entities\KbSubcategory;
use Modules\KB\Services\KbArticleService;

/**
 * KbController — Knowledge Base browser dos documentos servidos via MCP server.
 *
 * Tela /kb mostra os docs de mcp_memory_documents (sincronizados de
 * memory/decisions/, memory/sessions/, memory/comparativos/, etc via webhook
 * git → POST /api/mcp/sync-memory).
 *
 * Histórico (Etapa 2 modularização — 2026-05-03):
 *   - Antes: Modules\Jana\Http\Controllers\Admin\MemoriaKbController @ /copiloto/admin/memoria
 *   - Agora: Modules\KB\Http\Controllers\KbController @ /kb
 *
 * Diferente de:
 *   - /copiloto/memoria (runtime facts, LGPD opt-out usuário-final, fica no Copiloto)
 *   - /memcofre/memoria (Cofre de Memórias / DocVault — workflow ingest→inbox)
 *
 * Permissão Spatie atual: `copiloto.mcp.memory.manage` (mantida pra evitar
 * migration de rename — dívida técnica registrada pra rename em PR separado
 * pra `kb.manage` ou `kb.softdelete`).
 *
 * O contrato de permissions novo (`Modules/KB/Resources/permissions.php`)
 * declara as chaves `kb.view`, `kb.softdelete`, `kb.restore`,
 * `kb.history.view` apenas pra agregação visual no PermissionRegistry.
 */
class KbController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('can:copiloto.mcp.memory.manage');
    }

    public function index(Request $request): Response
    {
        $type = $request->get('type');
        $module = $request->get('module');
        $search = trim((string) $request->get('q', ''));
        $withPii = $request->boolean('with_pii');
        $page = (int) max(1, $request->get('page', 1));

        // ROLLBACK Wave L/W7 PR #963: Inertia::defer quebrava Pages (initial render undefined).
        return Inertia::render('kb/Index', [
            'filters' => [
                'type'     => $type,
                'module'   => $module,
                'q'        => $search,
                'with_pii' => $withPii,
            ],
            'github_repo' => 'wagnerra23/oimpresso.com',
            'docs'    => $this->buildDocsPayload(
                $request->user(), $type, $module, $search, $withPii, $page
            ),
            'kpis'    => $this->buildKpisPayload(),
        ]);
    }

    /**
     * indexV2 — a tela tri-pane /kb/v2 (+ alias /sops) servindo DADO REAL de kb_nodes.
     *
     * Substitui as closures `Inertia::render('kb/Index.v2')` sem props (que deixavam
     * a tela em MOCK — charter §8-bis passo 2). Agora passa `nodes` + `categories` +
     * `subcategories` + `business`, e a tela sai do mock (`usingMock = !props.nodes`).
     *
     * Tier 0 (ADR 0093): web request → o global scope de KbNode/KbCategory/KbSubcategory
     * resolve o business_id da SESSÃO. Nenhuma query cruza tenant; sem withoutGlobalScopes.
     *
     * NOVO-A (charter §2-bis, [W] 2026-07-17): `business.name` alimenta o rótulo
     * "Buscando em: «empresa ativa»" ao lado da busca — leitura, não seletor.
     *
     * LIMITES HONESTOS (follow-ups, não bloqueiam a lista):
     *  - Carrega TODOS os nós do business em 1 payload (a tela filtra client-side, como o
     *    mock fazia). Leve hoje (biz=1: 543 nós, ~251 KB — corpo vive na fonte, não em
     *    kb_nodes). NÃO escala pra 10k+ nós: aí precisa paginação+filtro server-side (exige
     *    mudar o .tsx → gate visreg). Cap explícito de 2000 abaixo — se exceder, o
     *    paginator.total mostra o número real e a lista fica curta (visível, não silencioso).
     *  - O LEITOR (pane direito) mostra título+excerpt mas não o corpo completo dos docs
     *    bridgeados (o `KbBridgeFromMcpJob` copia metadata, não `body_blocks`). Bridge do
     *    corpo OU fetch-on-click = próximo passo.
     */
    public function indexV2(Request $request, KbArticleService $articles): Response
    {
        $user = $request->user();

        // buildListQuery já é scopado pelo global scope + aplica os filtros da query.
        // paginate(2000): 1 página com todos os nós do business (a tela filtra no cliente).
        $nodes = $articles->buildListQuery($request)
            ->orderByDesc('pinned')
            ->orderByDesc('updated_at')
            ->paginate(2000)
            ->withQueryString();

        return Inertia::render('kb/Index.v2', [
            'filters' => [
                'q'           => $request->string('q')->toString() ?: null,
                'category'    => $request->string('category')->toString() ?: null,
                'subcategory' => $request->string('subcategory')->toString() ?: null,
                'type'        => $request->string('type')->toString() ?: null,
            ],
            // NOVO-A: rótulo da empresa ativa (a troca de empresa vive na Sidebar; aqui é leitura).
            'business' => [
                'id'   => (int) (session('user.business_id') ?? $user?->business_id ?? 0),
                'name' => (string) (session('business.name') ?? ''),
            ],
            'categories'    => KbCategory::query()->orderBy('sort')->orderBy('label')->get(),
            'subcategories' => KbSubcategory::query()->get(),
            'nodes'         => $nodes,
            'can' => [
                'write'                => (bool) ($user?->can('kb.write') ?? false),
                'favorite'             => true,
                'comment'              => (bool) ($user?->can('kb.comment') ?? false),
                'ai_ask'               => (bool) ($user?->can('kb.ai') ?? false),
                'graph_view'           => true,
                'publish_path'         => (bool) ($user?->can('kb.write') ?? false),
                'publish_troubleshoot' => (bool) ($user?->can('kb.write') ?? false),
            ],
        ]);
    }

    /**
     * D-14 perf — paginator paginate(25) + withTrashed + selectRaw CHAR_LENGTH.
     * Movido pra closure defer no `index()`.
     */
    protected function buildDocsPayload($user, ?string $type, ?string $module, string $search, bool $withPii, int $page)
    {
        $query = McpMemoryDocument::query()
            ->acessiveisPara($user)
            ->orderByDesc('indexed_at');

        if ($type) {
            $query->doTipo($type);
        }
        if ($module) {
            $query->doModulo($module);
        }
        if ($search !== '') {
            $query->buscarTexto($search);
        }
        if ($withPii) {
            $query->where('pii_redactions_count', '>', 0);
        }

        return $query
            ->select(['id', 'slug', 'type', 'module', 'title', 'scope_required',
                     'admin_only', 'git_sha', 'git_path', 'pii_redactions_count',
                     'indexed_at', 'updated_at', 'deleted_at'])
            ->selectRaw('CHAR_LENGTH(content_md) as size_chars')
            ->withTrashed()
            ->paginate(25, ['*'], 'page', $page)
            ->withQueryString();
    }

    /**
     * D-14 perf — 5 queries agregadas (count/groupBy/max). Movido pra closure defer.
     */
    protected function buildKpisPayload(): array
    {
        return [
            'total'          => McpMemoryDocument::count(),
            'soft_deleted'   => McpMemoryDocument::onlyTrashed()->count(),
            'com_pii'        => McpMemoryDocument::where('pii_redactions_count', '>', 0)->count(),
            'tipos'          => McpMemoryDocument::query()
                ->select('type')
                ->selectRaw('COUNT(*) as c')
                ->groupBy('type')
                ->pluck('c', 'type')
                ->all(),
            'modulos'        => McpMemoryDocument::query()
                ->whereNotNull('module')
                ->select('module')
                ->selectRaw('COUNT(*) as c')
                ->groupBy('module')
                ->orderByDesc('c')
                ->limit(15)
                ->pluck('c', 'module')
                ->all(),
            'ultimo_sync'    => McpMemoryDocument::max('indexed_at'),
        ];
    }

    /**
     * Retorna conteúdo completo de 1 doc (markdown + metadata).
     * Usado pelo Sheet preview via fetch.
     */
    public function show(Request $request, string $slug): JsonResponse
    {
        $doc = McpMemoryDocument::query()
            ->withTrashed()
            ->where('slug', $slug)
            ->firstOrFail();

        return response()->json([
            'id'                   => $doc->id,
            'slug'                 => $doc->slug,
            'type'                 => $doc->type,
            'module'               => $doc->module,
            'title'                => $doc->title,
            'content_md'           => $doc->content_md,
            'scope_required'       => $doc->scope_required,
            'admin_only'           => (bool) $doc->admin_only,
            'metadata'             => $doc->metadata,
            'git_sha'              => $doc->git_sha,
            'git_path'             => $doc->git_path,
            'pii_redactions_count' => (int) $doc->pii_redactions_count,
            'indexed_at'           => optional($doc->indexed_at)->toIso8601String(),
            'updated_at'           => optional($doc->updated_at)->toIso8601String(),
            'deleted_at'           => optional($doc->deleted_at)->toIso8601String(),
            'history_count'        => $doc->history()->count(),
            'github_url'           => $doc->git_path
                ? "https://github.com/wagnerra23/oimpresso.com/blob/main/{$doc->git_path}"
                : null,
        ]);
    }

    /**
     * Soft-delete LGPD ("esquecer este doc"). Mantém auditoria
     * em mcp_audit_log e history. Pode ser restaurado via restore().
     */
    public function softDelete(Request $request, string $slug): JsonResponse
    {
        $request->validate([
            'confirm' => 'required|in:CONFIRMO',
        ], [
            'confirm.in' => 'Digite CONFIRMO pra confirmar a exclusão.',
        ]);

        $doc = McpMemoryDocument::query()->where('slug', $slug)->firstOrFail();
        $doc->delete();

        return response()->json([
            'ok'      => true,
            'message' => "Doc '{$slug}' soft-deleted. Pode restaurar em até 30 dias.",
        ]);
    }

    /**
     * Restaura doc soft-deletado.
     */
    public function restore(Request $request, string $slug): JsonResponse
    {
        $doc = McpMemoryDocument::onlyTrashed()->where('slug', $slug)->firstOrFail();
        $doc->restore();

        return response()->json(['ok' => true, 'message' => "Doc '{$slug}' restaurado."]);
    }

    /**
     * Lista revisões anteriores de 1 doc.
     */
    public function history(Request $request, string $slug): JsonResponse
    {
        $doc = McpMemoryDocument::withTrashed()->where('slug', $slug)->firstOrFail();
        $rows = $doc->history()
            ->orderByDesc('changed_at')
            ->limit(50)
            ->get(['id', 'git_sha', 'title', 'changed_at', 'changed_by_user_id', 'change_reason']);

        return response()->json([
            'slug'     => $slug,
            'current'  => [
                'git_sha'    => $doc->git_sha,
                'title'      => $doc->title,
                'updated_at' => optional($doc->updated_at)->toIso8601String(),
            ],
            'versions' => $rows,
        ]);
    }
}
