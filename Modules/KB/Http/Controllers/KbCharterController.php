<?php

namespace Modules\KB\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Jana\Entities\Mcp\McpMemoryDocument;

/**
 * KbCharterController — tela /kb/charters (interface do Charter Governance, ADR 0243).
 *
 * MVP (leitura): lista os charters (*.charter.md sincronizados em
 * mcp_memory_documents via webhook git) reusando o tri-pane do KbController.
 * Read-only — o núcleo do charter vem do git (ADR 0061, invariante is_editable=false).
 * Camada de governança (sugestão → aprovação) vem em F1 (SPEC US-CHTR-001..003).
 *
 * O preview do conteúdo reusa o endpoint existente GET /kb/{slug}/show.
 *
 * @see memory/requisitos/KB/INTERFACE-CHARTER-KB.md
 * @see memory/decisions/proposals/0243-charter-governance-kb.md
 * @see memory/requisitos/KB/SPEC-CHARTER-GOVERNANCE.md
 */
class KbCharterController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        // Dívida técnica: mesma permissão canônica do KbController. Rename pra
        // kb.charter.view em PR separado (igual ao plano do KbController).
        $this->middleware('can:copiloto.mcp.memory.manage');
    }

    public function index(Request $request): Response
    {
        $module = $request->get('module');
        $search = trim((string) $request->get('q', ''));

        // NÃO usar Inertia::defer aqui — KbController teve rollback (Wave L/W7 PR #963):
        // defer quebrava Pages com initial render undefined. Eager (≈30 charters, barato).
        return Inertia::render('kb/Charters/Index', [
            'filters'     => ['module' => $module, 'q' => $search],
            'github_repo' => 'wagnerra23/oimpresso.com',
            'charters'    => $this->buildChartersPayload($request->user(), $module, $search),
            'kpis'        => $this->buildKpisPayload($request->user()),
        ]);
    }

    /**
     * Lista charters mapeando git_path → módulo/tela.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function buildChartersPayload($user, ?string $module, string $search): array
    {
        $rows = $this->baseQuery($user)
            ->select(['id', 'slug', 'type', 'title', 'git_path', 'git_sha', 'updated_at', 'indexed_at'])
            ->selectRaw('CHAR_LENGTH(content_md) as size_chars')
            ->orderBy('git_path')
            ->get();

        return $rows
            ->map(function ($doc) {
                $meta = $this->parsePath($doc->git_path);

                return [
                    'slug'       => $doc->slug,
                    'title'      => $doc->title,
                    'module'     => $meta['module'],
                    'screen'     => $meta['screen'],
                    'level'      => $meta['is_module'] ? 'module' : 'page',
                    'git_path'   => $doc->git_path,
                    'git_sha'    => $doc->git_sha,
                    'size_chars' => (int) $doc->size_chars,
                    'updated_at' => optional($doc->updated_at)->toIso8601String(),
                ];
            })
            ->when($module, fn ($c) => $c->where('module', $module))
            ->when($search !== '', fn ($c) => $c->filter(
                fn ($r) => str_contains(
                    mb_strtolower(($r['title'] ?? '').' '.($r['git_path'] ?? '')),
                    mb_strtolower($search)
                )
            ))
            ->values()
            ->all();
    }

    /**
     * KPIs: total de charters + cobertura por módulo.
     *
     * @return array<string, mixed>
     */
    protected function buildKpisPayload($user): array
    {
        // total real (independe de o git_path resolver um módulo)
        $total = $this->baseQuery($user)->count();

        $modulos = $this->baseQuery($user)
            ->select(['git_path'])
            ->get()
            ->map(fn ($d) => $this->parsePath($d->git_path)['module'])
            ->filter()
            ->countBy()
            ->sortDesc();

        return [
            'total'         => $total,
            'modulos'       => $modulos->take(12)->all(),
            'modulos_total' => $modulos->count(),
        ];
    }

    /**
     * Charters = arquivos *.charter.md sincronizados em mcp_memory_documents.
     * Filtra por type='charter' OU git_path terminando em .charter.md (robusto a
     * como o sync classificou o type).
     */
    protected function baseQuery($user)
    {
        return McpMemoryDocument::query()
            ->acessiveisPara($user)
            ->where(function ($q) {
                $q->where('type', 'charter')
                  ->orWhere('git_path', 'like', '%.charter.md');
            });
    }

    /**
     * resources/js/Pages/Cliente/Index.charter.md → [module: Cliente, screen: Index].
     * Heurística is_module: charter de tela "Index" do topo do módulo ~= visão de módulo.
     *
     * @return array{module:?string, screen:?string, is_module:bool}
     */
    protected function parsePath(?string $path): array
    {
        if (! $path) {
            return ['module' => null, 'screen' => null, 'is_module' => false];
        }
        $p = preg_replace('#^.*/Pages/#', '', $path);
        $p = preg_replace('#\.charter\.md$#', '', (string) $p);
        $parts = array_values(array_filter(explode('/', (string) $p)));
        $module = $parts[0] ?? null;
        $screen = count($parts) > 1 ? implode('/', array_slice($parts, 1)) : $module;

        return [
            'module'    => $module,
            'screen'    => $screen,
            'is_module' => count($parts) === 1, // ex: Pages/Manufacturing.charter.md (raro)
        ];
    }
}
