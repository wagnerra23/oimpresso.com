<?php

namespace Modules\Copiloto\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Copiloto\Entities\Mcp\McpMemoryDocument;

/**
 * MEM-KB-1 (ADR 0053) — KB browser dos documentos servidos via MCP server.
 *
 * Tela /copiloto/admin/memoria mostra os 100+ docs de mcp_memory_documents
 * (sincronizados de memory/decisions/, memory/sessions/, etc via webhook git).
 *
 * Diferente de:
 *   - /copiloto/memoria (runtime facts, LGPD opt-out usuário-final)
 *   - /memcofre/memoria (Cofre de Memórias / DocVault — workflow ingest→inbox)
 *
 * Permissão: `copiloto.mcp.memory.manage` (Wagner/superadmin v1).
 */
class MemoriaKbController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('can:copiloto.mcp.memory.manage');
    }

    public function index(Request $request): Response
    {
        $query = McpMemoryDocument::query()
            ->acessiveisPara($request->user())
            ->orderByDesc('indexed_at');

        // Filtros
        if ($type = $request->get('type')) {
            $query->doTipo($type);
        }
        if ($module = $request->get('module')) {
            $query->doModulo($module);
        }
        if ($search = trim((string) $request->get('q', ''))) {
            $query->buscarTexto($search);
        }
        if ($request->boolean('with_pii')) {
            $query->where('pii_redactions_count', '>', 0);
        }

        $page = (int) max(1, $request->get('page', 1));
        $perPage = 25;

        $paginator = $query
            ->select(['id', 'slug', 'type', 'module', 'title', 'scope_required',
                     'admin_only', 'git_sha', 'git_path', 'pii_redactions_count',
                     'indexed_at', 'updated_at', 'deleted_at'])
            ->selectRaw('CHAR_LENGTH(content_md) as size_chars')
            ->withTrashed()
            ->paginate($perPage, ['*'], 'page', $page)
            ->withQueryString();

        // KPIs globais (não filtrados por search — visão total)
        $kpis = [
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

        return Inertia::render('Copiloto/Admin/Memoria/Index', [
            'docs'    => $paginator,
            'filters' => [
                'type'     => $type,
                'module'   => $module,
                'q'        => $search,
                'with_pii' => $request->boolean('with_pii'),
            ],
            'kpis'    => $kpis,
            'github_repo' => 'wagnerra23/oimpresso.com',
        ]);
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
