<?php

namespace Modules\Forja\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Forja\Services\ToolRegistry;

class ToolsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request, ToolRegistry $registry): Response
    {
        // D7 (thread 11): o audit é da empresa da sessão — a tela não mostra
        // execuções de outro business (antes listava a tabela inteira).
        $businessId = $this->businessIdDaSessao($request);

        // Wave 11 D6.a — Inertia::defer pra props caras: tools_by_category (iter
        // sobre registry inteiro + agrupamento), recent_executions (1 query DB),
        // kpis (3 counts registry + 1 count DB). Closures executam em background
        // após first paint — frontend mostra skeleton até resolverem.
        return Inertia::render('ads/Admin/Tools', [
            'tools_by_category' => Inertia::defer(fn () => $this->buildToolsByCategoryPayload($registry)),
            'recent_executions' => Inertia::defer(fn () => $this->buildRecentExecutionsPayload($businessId)),
            'kpis'              => Inertia::defer(fn () => $this->buildKpisPayload($registry, $businessId)),
        ]);
    }

    /**
     * Builder tools agrupadas por categoria (Wave 11 D6.a defer).
     *
     * @return array<int, array<string, mixed>>
     */
    protected function buildToolsByCategoryPayload(ToolRegistry $registry): array
    {
        return collect($registry->all())
            ->map(fn ($t) => [
                'name'         => $t->name(),
                'description'  => $t->description(),
                'category'     => $t->category(),
                'is_read_only' => $t->isReadOnly(),
                'input_schema' => $t->inputSchema(),
            ])
            ->groupBy('category')
            ->map(fn ($group, $cat) => [
                'category' => $cat,
                'tools'    => $group->values(),
            ])
            ->values()
            ->toArray();
    }

    /**
     * Builder execuções recentes (últimas 20) — audit log Tier 0 (Wave 11 D6.a defer).
     *
     * @return array<int, array<string, mixed>>
     */
    protected function buildRecentExecutionsPayload(int $businessId): array
    {
        return DB::table('mcp_tool_executions')
            ->where('business_id', $businessId)
            ->orderByDesc('id')
            ->limit(20)
            ->get(['id', 'tool_name', 'is_read_only', 'ok', 'error', 'duration_ms', 'triggered_by', 'created_at'])
            ->map(fn ($r) => [
                'id'           => $r->id,
                'tool_name'    => $r->tool_name,
                'is_read_only' => (bool) $r->is_read_only,
                'ok'           => (bool) $r->ok,
                'error'        => $r->error,
                'duration_ms'  => $r->duration_ms,
                'triggered_by' => $r->triggered_by,
                'created_at'   => $r->created_at,
            ])
            ->toArray();
    }

    /**
     * Builder KPIs registry + audit count (Wave 11 D6.a defer).
     *
     * @return array<string, int>
     */
    protected function buildKpisPayload(ToolRegistry $registry, int $businessId): array
    {
        $all = $registry->all();
        $categoriasCount = collect($all)->groupBy(fn ($t) => $t->category())->count();

        return [
            'total'         => count($all),
            'read_only'     => count($registry->readOnly()),
            'write'         => count($registry->writeOnly()),
            'categories'    => $categoriasCount,
            'executions_7d' => DB::table('mcp_tool_executions')
                ->where('business_id', $businessId)
                ->where('created_at', '>=', now()->subDays(7))
                ->count(),
        ];
    }

    /**
     * POST /ads/admin/tools/{name}/execute
     *
     * Wagner clica "Try it" → endpoint executa Tool com input + audit log.
     * Tools de escrita exigem confirmação no UI antes de chegar aqui.
     */
    public function execute(Request $request, string $name, ToolRegistry $registry): JsonResponse
    {
        // D7 (thread 11): permissão própria — antes bastava estar logado.
        abort_unless($request->user()->can('forja.tools.execute'), 403);

        $tool = $registry->get($name);
        if (! $tool) {
            return response()->json(['ok' => false, 'error' => 'tool_not_found'], 404);
        }

        $input = $request->input('input', []);
        if (! is_array($input)) $input = [];

        $businessId = $this->businessIdDaSessao($request);
        $startedAt = microtime(true);

        $result = $registry->execute($name, $input);

        $durationMs = (int) ((microtime(true) - $startedAt) * 1000);

        // Audit log persistente
        DB::table('mcp_tool_executions')->insert([
            'business_id'  => $businessId,
            'decision_id'  => null, // V2: linkar com decision se disparada por uma
            'tool_name'    => $name,
            'is_read_only' => $tool->isReadOnly(),
            'input'        => json_encode($input, JSON_UNESCAPED_UNICODE),
            'ok'           => $result['ok'] ?? false,
            'output'       => isset($result['output']) ? json_encode($result['output'], JSON_UNESCAPED_UNICODE) : null,
            'error'        => $result['error'] ?? null,
            'duration_ms'  => $durationMs,
            // D7: autor real (coluna varchar(50)), nunca 'wagner' fixo.
            'triggered_by' => mb_substr((string) $request->user()->username, 0, 50),
            'created_at'   => now(),
        ]);

        return response()->json($result);
    }

    /** Business da sessão; sem sessão, o do próprio usuário — nunca um default fixo. */
    private function businessIdDaSessao(Request $request): int
    {
        return (int) $request->session()->get('user.business_id', $request->user()->business_id);
    }
}
