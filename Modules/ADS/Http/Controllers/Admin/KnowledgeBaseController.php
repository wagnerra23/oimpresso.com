<?php

namespace Modules\ADS\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Modules\ADS\Services\DecisionLinksService;

/**
 * KB Obsidian-style — vincula docs (ADRs) com entidades ADS via backlinks.
 *
 * Fontes de dados:
 *   - mcp_memory_documents (376 docs sincronizados de memory/* via webhook GitHub)
 *   - mcp_decision_links (pivot polimórfica)
 *
 * Search: full-text MySQL (Meilisearch hybrid pode ser plugado depois).
 */
class KnowledgeBaseController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request): Response
    {
        $q = trim($request->get('q', ''));
        $type = $request->get('type', '');
        $module = $request->get('module', '');

        $query = DB::table('mcp_memory_documents');

        // Filtro por type (adr/session/spec/etc)
        if ($type !== '') {
            $query->where('type', $type);
        }
        if ($module !== '') {
            $query->where('module', $module);
        }

        // Search FULLTEXT-ish (LIKE com index)
        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('title', 'like', "%{$q}%")
                  ->orWhere('content_md', 'like', "%{$q}%")
                  ->orWhere('slug', 'like', "%{$q}%");
            });
        }

        $docs = $query->orderByDesc('updated_at')
            ->limit(50)
            ->get(['id', 'slug', 'title', 'type', 'module', 'updated_at']);

        // Para cada doc, conta backlinks
        $slugs = $docs->pluck('slug')->all();
        $linkCounts = DB::table('mcp_decision_links')
            ->whereIn('adr_slug', $slugs)
            ->select('adr_slug', DB::raw('count(*) as n'))
            ->groupBy('adr_slug')
            ->pluck('n', 'adr_slug')
            ->all();

        $documentsWithLinks = $docs->map(fn ($d) => [
            'id'           => $d->id,
            'slug'         => $d->slug,
            'title'        => $d->title,
            'type'         => $d->type,
            'module'       => $d->module,
            'status'       => null,
            'updated_at'   => $d->updated_at,
            'links_count'  => (int) ($linkCounts[$d->slug] ?? 0),
        ]);

        // Filtros disponíveis (distinct types/modules)
        $allTypes = DB::table('mcp_memory_documents')
            ->select('type', DB::raw('count(*) as n'))
            ->groupBy('type')
            ->orderByDesc('n')
            ->get()
            ->map(fn ($r) => ['value' => $r->type, 'count' => (int) $r->n])
            ->all();

        $allModules = DB::table('mcp_memory_documents')
            ->whereNotNull('module')
            ->select('module', DB::raw('count(*) as n'))
            ->groupBy('module')
            ->orderByDesc('n')
            ->limit(15)
            ->get()
            ->map(fn ($r) => ['value' => $r->module, 'count' => (int) $r->n])
            ->all();

        $kpis = [
            'total_docs'     => DB::table('mcp_memory_documents')->count(),
            'total_links'    => DB::table('mcp_decision_links')->count(),
            'orphan_count'   => DB::table('mcp_memory_documents')->count() - count(array_unique(DB::table('mcp_decision_links')->pluck('adr_slug')->all())),
            'most_linked'    => DB::table('mcp_decision_links')
                ->select('adr_slug', DB::raw('count(*) as n'))
                ->groupBy('adr_slug')
                ->orderByDesc('n')
                ->first()->adr_slug ?? null,
        ];

        return Inertia::render('ads/Admin/KnowledgeBase', [
            'documents' => $documentsWithLinks,
            'filters'   => [
                'q' => $q,
                'type' => $type,
                'module' => $module,
                'available_types'   => $allTypes,
                'available_modules' => $allModules,
            ],
            'kpis' => $kpis,
        ]);
    }

    public function show(Request $request, string $slug, DecisionLinksService $links): Response
    {
        $doc = DB::table('mcp_memory_documents')
            ->where('slug', $slug)
            ->firstOrFail();

        $backlinks = $links->backlinks($slug);

        return Inertia::render('ads/Admin/KnowledgeBaseShow', [
            'document'  => [
                'id'           => $doc->id,
                'slug'         => $doc->slug,
                'title'        => $doc->title,
                'type'         => $doc->type,
                'module'       => $doc->module,
                'status'       => null,
                'content_md'   => $doc->content_md ?? '',
                'git_path'     => $doc->git_path ?? null,
                'updated_at'   => $doc->updated_at,
            ],
            'backlinks' => $backlinks,
        ]);
    }
}
