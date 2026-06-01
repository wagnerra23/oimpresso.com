<?php

namespace Modules\KB\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Inertia\Inertia;
use Inertia\Response;
use Modules\KB\Entities\KbCharterSuggestion;

/**
 * KbCharterController — tela /kb/charters (interface do Charter Governance, ADR 0243).
 *
 * Fonte de dados = FILESYSTEM (os *.charter.md vivem em resources/js/Pages/**, são
 * CÓDIGO, não memory/ — então não estão em mcp_memory_documents). Ler do disco garante
 * dados no deploy sem depender de sync/webhook. Mesmo padrão do RequirementsFileReader
 * (Modules/SRS) que lia memory/requisitos/ do disco.
 *
 * Read-only — o núcleo do charter é imutável (vem do git, ADR 0061). Governança
 * (sugestão → aprovação) vem em F1 (SPEC US-CHTR-001..003).
 *
 * Charters NÃO são dado de tenant (são contratos de tela, globais do projeto) —
 * logo sem business_id scope. Acesso protegido pelo middleware (Wagner-only).
 *
 * @see memory/requisitos/KB/INTERFACE-CHARTER-KB.md
 * @see memory/decisions/proposals/0243-charter-governance-kb.md
 */
class KbCharterController extends Controller
{
    private const PAGES_ROOT = 'resources/js/Pages';
    private const REPO = 'wagnerra23/oimpresso.com';

    public function __construct()
    {
        $this->middleware('auth');
        // Dívida técnica: mesma permissão canônica do KbController (rename pra
        // kb.charter.view em PR separado).
        $this->middleware('can:copiloto.mcp.memory.manage');
    }

    public function index(Request $request): Response
    {
        $charters = $this->scanCharters();

        $modulos = collect($charters)
            ->pluck('module')
            ->filter()
            ->countBy()
            ->sortDesc();

        return Inertia::render('kb/Charters/Index', [
            'filters'     => ['module' => $request->get('module'), 'q' => (string) $request->get('q', '')],
            'github_repo' => self::REPO,
            'charters'    => $charters,
            'kpis'        => [
                'total'         => count($charters),
                'modulos'       => $modulos->take(12)->all(),
                'modulos_total' => $modulos->count(),
            ],
        ]);
    }

    /**
     * Conteúdo de 1 charter. `path` deve ser relativo a resources/js/Pages e
     * terminar em .charter.md (anti path-traversal).
     */
    public function show(Request $request): JsonResponse
    {
        $path = (string) $request->get('path', '');
        $full = $this->safeCharterPath($path);

        if ($full === null || ! File::exists($full)) {
            return response()->json(['error' => 'Charter não encontrado'], 404);
        }

        return response()->json([
            'path'       => $path,
            'content_md' => File::get($full),
            'github_url' => 'https://github.com/'.self::REPO.'/blob/main/'.$path,
            'title'      => $this->frontmatter(File::get($full))['page'] ?? $path,
        ]);
    }

    // ── Governança (F1, ADR 0243): sugestão supervisionada + aprovação ───

    /** GET /kb/charters/suggestions?path=... — sugestões do charter (business scope). */
    public function suggestions(Request $request): JsonResponse
    {
        $path = (string) $request->get('path', '');
        if ($this->safeCharterPath($path) === null) {
            return response()->json(['error' => 'path inválido'], 422);
        }

        $rows = KbCharterSuggestion::query()
            ->forCharter($path)
            ->orderByDesc('id')
            ->get(['id', 'anchor', 'kind', 'text', 'status', 'author_user_id',
                   'resolved_by_user_id', 'resolution_note', 'created_at']);

        return response()->json(['suggestions' => $rows]);
    }

    /** POST /kb/charters/suggestions — propõe sugestão (NÃO edita o núcleo). */
    public function suggest(Request $request): JsonResponse
    {
        $data = $request->validate([
            'path'   => ['required', 'string'],
            'kind'   => ['nullable', 'in:'.implode(',', KbCharterSuggestion::KINDS)],
            'anchor' => ['nullable', 'string', 'max:255'],
            'text'   => ['required', 'string', 'min:3', 'max:5000'],
        ]);

        if ($this->safeCharterPath($data['path']) === null) {
            return response()->json(['error' => 'charter inexistente'], 404);
        }

        $s = KbCharterSuggestion::create([
            'charter_path'   => $data['path'],
            'anchor'         => $data['anchor'] ?? null,
            'kind'           => $data['kind'] ?? 'suggestion',
            'text'           => $data['text'],
            'status'         => 'proposed',
            'author_user_id' => Auth::id(),
        ]);

        return response()->json(['suggestion' => $s], 201);
    }

    /**
     * PATCH /kb/charters/suggestions/{id} — owner aprova/rejeita (comentário obrigatório).
     * F1 para em accepted/rejected; promoção pro git (.charter.md) é F3 (US-CHTR-020).
     */
    public function resolve(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'status'          => ['required', 'in:accepted,rejected,under_review'],
            'resolution_note' => ['required_if:status,accepted,rejected', 'nullable', 'string', 'max:500'],
        ]);

        $s = KbCharterSuggestion::query()->findOrFail($id); // global scope = só do business

        $s->update([
            'status'              => $data['status'],
            'resolution_note'     => $data['resolution_note'] ?? null,
            'resolved_by_user_id' => in_array($data['status'], ['accepted', 'rejected'], true)
                ? Auth::id() : $s->resolved_by_user_id,
        ]);

        return response()->json(['suggestion' => $s->fresh()]);
    }

    // ─────────────────────────────────────────────────────────────────────

    /**
     * Varre resources/js/Pages/** por *.charter.md (exceto _components/_lib).
     *
     * @return array<int, array<string, mixed>>
     */
    protected function scanCharters(): array
    {
        $root = base_path(self::PAGES_ROOT);
        if (! is_dir($root)) {
            return [];
        }

        $out = [];
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($it as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.charter.md')) {
                continue;
            }
            $rel = self::PAGES_ROOT.'/'.str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
            if (preg_match('#/_(components|lib)/#', $rel)) {
                continue;
            }

            $meta = $this->parsePath($rel);
            $head = (string) @file_get_contents($file->getPathname(), false, null, 0, 1500);
            $fm   = $this->frontmatter($head);

            $out[] = [
                'path'       => $rel,
                'title'      => $fm['page'] ?? ($meta['module'].' · '.$meta['screen']),
                'module'     => $meta['module'],
                'screen'     => $meta['screen'],
                'level'      => $meta['is_module'] ? 'module' : 'page',
                'status'     => $fm['status'] ?? null,
                'tier'       => $fm['tier'] ?? null,
                'owner'      => $fm['owner'] ?? null,
                'size_chars' => $file->getSize(),
            ];
        }

        usort($out, fn ($a, $b) => strcmp((string) $a['module'], (string) $b['module']) ?: strcmp($a['path'], $b['path']));

        return $out;
    }

    /**
     * Valida + resolve o path absoluto, barrando traversal. Retorna null se inválido.
     */
    protected function safeCharterPath(string $path): ?string
    {
        if ($path === '' || ! str_ends_with($path, '.charter.md') || str_contains($path, '..')) {
            return null;
        }
        $rootReal = realpath(base_path(self::PAGES_ROOT));
        $fullReal = realpath(base_path($path));
        if ($rootReal === false || $fullReal === false) {
            return null;
        }
        // O arquivo resolvido tem que estar DENTRO de resources/js/Pages.
        return str_starts_with($fullReal, $rootReal.DIRECTORY_SEPARATOR) ? $fullReal : null;
    }

    /**
     * resources/js/Pages/Cliente/Index.charter.md → [module: Cliente, screen: Index].
     *
     * @return array{module:?string, screen:?string, is_module:bool}
     */
    protected function parsePath(string $path): array
    {
        $p = preg_replace('#^.*/Pages/#', '', $path);
        $p = preg_replace('#\.charter\.md$#', '', (string) $p);
        $parts = array_values(array_filter(explode('/', (string) $p)));
        $module = $parts[0] ?? null;
        $screen = count($parts) > 1 ? implode('/', array_slice($parts, 1)) : $module;

        return ['module' => $module, 'screen' => $screen, 'is_module' => count($parts) === 1];
    }

    /**
     * Parse simples do frontmatter YAML (chave: valor de 1 linha).
     *
     * @return array<string, string>
     */
    protected function frontmatter(string $content): array
    {
        if (! preg_match('/^---\s*\n(.*?)\n---\s*\n/s', $content, $m)) {
            return [];
        }
        $out = [];
        foreach (preg_split('/\R/', $m[1]) as $line) {
            if (preg_match('/^(\w[\w\-]*):\s*(.+)$/', trim($line), $mm)) {
                $out[$mm[1]] = trim($mm[2]);
            }
        }

        return $out;
    }
}
