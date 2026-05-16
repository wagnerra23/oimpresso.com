<?php

declare(strict_types=1);

namespace Modules\Governance\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Governance\Services\ModuleGradeService;

/**
 * Module Grades dashboard — rubrica oficial module-grade-v3 (ADR 0155).
 *
 * Rotas:
 *   GET /governance/module-grades         → Index (tabela 34 módulos ranqueada, 9 dims)
 *   GET /governance/module-grades/{name}  → Show (drill-down 9 dimensões + gaps + Evoluir)
 *
 * Cache 5min em gradeAllModules() — Service faz I/O filesystem 1-2s × 34 módulos.
 *
 * @see memory/decisions/0155-module-grade-v3-sub-dimensoes-gate-ci.md
 * @see memory/requisitos/Governance/RUNBOOK-module-grades.md
 */
class ModuleGradeController extends Controller
{
    private const CACHE_TTL_SECONDS = 300;
    private const CACHE_KEY_ALL = 'governance.module_grades.all';

    public function __construct(private readonly ModuleGradeService $service)
    {
        $this->middleware('auth');
    }

    public function index(Request $request): Response
    {
        // Inertia::defer pra coleta filesystem (1-2s × 34 módulos)
        $grades = Inertia::defer(fn () => $this->buildAllGradesPayload());

        $kpis = Inertia::defer(fn () => $this->buildKpisPayload());

        return Inertia::render('governance/ModuleGrades/Index', [
            'grades' => $grades,
            'kpis'   => $kpis,
        ]);
    }

    public function show(Request $request, string $name): Response
    {
        try {
            $grade = Cache::remember(
                "governance.module_grades.{$name}",
                self::CACHE_TTL_SECONDS,
                fn () => $this->service->gradeModule($name),
            );
        } catch (\InvalidArgumentException $e) {
            abort(404, $e->getMessage());
        }

        // ADR 0155 v3 + RUNBOOK-inertia-defer-pattern.md — history é query SQL
        // (até 7 rows × 34 módulos) — defer pra não bloquear render inicial.
        $history = Inertia::defer(fn () => $this->buildHistoryPayload($name));

        return Inertia::render('governance/ModuleGrades/Show', [
            'grade'   => $grade,
            'history' => $history,
        ]);
    }

    /**
     * Últimos 7 snapshots da nota do módulo (sparkline 7d).
     * Alimentado por `php artisan module:grade-snapshot` (cron daily 06:05 BRT).
     *
     * @return array<int, array{score: int, bucket: string, snapshot_at: string}>
     */
    private function buildHistoryPayload(string $name): array
    {
        // Tolerante a ambientes sem a migration aplicada ainda (CI / dev fresh)
        if (! \Illuminate\Support\Facades\Schema::hasTable('mcp_module_grades_history')) {
            return [];
        }

        return DB::table('mcp_module_grades_history')
            ->select('score', 'bucket', 'snapshot_at')
            ->where('module', $name)
            ->orderByDesc('snapshot_at')
            ->limit(7)
            ->get()
            ->reverse() // ordem cronológica pra sparkline ler da esquerda pra direita
            ->values()
            ->map(fn ($row) => [
                'score'       => (int) $row->score,
                'bucket'      => (string) $row->bucket,
                'snapshot_at' => (string) $row->snapshot_at,
            ])
            ->all();
    }

    /**
     * Payload da listagem Index (34 módulos × campos compactos).
     *
     * ADR 0155 v3 — repassa 9 dimensões + `score_v3_raw`/`score_v3_normalized`/`weights_v3_total`
     * pra Index.tsx renderizar colunas D1-D9 (antes só D1-D5 — D6-D9 ficavam `—` permanente).
     *
     * Cada dimensão vem como string `"score/max"` consistente com Show.tsx + back-compat v1.
     *
     * @return array<int, array>
     */
    private function buildAllGradesPayload(): array
    {
        return Cache::remember(
            self::CACHE_KEY_ALL,
            self::CACHE_TTL_SECONDS,
            fn () => $this->service->gradeAllModules()
                ->map(fn ($g) => [
                    'module'              => $g['module'],
                    // `score` sinônimo de score_v3_normalized — back-compat v1/v2
                    'score'               => $g['score'],
                    // ADR 0155 v3 — chaves explícitas (marca audit + permite UI v3 destacar)
                    'score_v3_normalized' => $g['score_v3_normalized'] ?? $g['score'],
                    'score_v3_raw'        => $g['score_v3_raw'] ?? null,
                    'weights_v3_total'    => $g['weights_v3_total'] ?? null,
                    'bucket'              => $g['bucket'],
                    'color'               => $g['color'],
                    'dimensions'          => [
                        // D1-D5 (v1/v2 — preservadas)
                        'multi_tenant'  => "{$g['dimensions']['multi_tenant']['score']}/{$g['dimensions']['multi_tenant']['max']}",
                        'pest_coverage' => "{$g['dimensions']['pest_coverage']['score']}/{$g['dimensions']['pest_coverage']['max']}",
                        'documentation' => "{$g['dimensions']['documentation']['score']}/{$g['dimensions']['documentation']['max']}",
                        'architecture'  => "{$g['dimensions']['architecture']['score']}/{$g['dimensions']['architecture']['max']}",
                        'client_real'   => "{$g['dimensions']['client_real']['score']}/{$g['dimensions']['client_real']['max']}",
                        // D6-D9 (ADR 0155 v3 — antes ausentes na payload Index, Index.tsx renderizava '—')
                        'performance'   => "{$g['dimensions']['performance']['score']}/{$g['dimensions']['performance']['max']}",
                        'lgpd'          => "{$g['dimensions']['lgpd']['score']}/{$g['dimensions']['lgpd']['max']}",
                        'security'      => "{$g['dimensions']['security']['score']}/{$g['dimensions']['security']['max']}",
                        'observability' => "{$g['dimensions']['observability']['score']}/{$g['dimensions']['observability']['max']}",
                    ],
                ])
                ->all()
        );
    }

    /**
     * @return array{average: float, total: int, by_bucket: array<string, int>}
     */
    private function buildKpisPayload(): array
    {
        $all = Cache::get(self::CACHE_KEY_ALL) ?? [];
        if (empty($all)) {
            $all = $this->buildAllGradesPayload();
        }

        $scores = array_column($all, 'score');
        $byBucket = [];
        foreach ($all as $g) {
            $byBucket[$g['bucket']] = ($byBucket[$g['bucket']] ?? 0) + 1;
        }

        return [
            'average'   => count($scores) > 0 ? round(array_sum($scores) / count($scores), 1) : 0,
            'total'     => count($all),
            'by_bucket' => $byBucket,
        ];
    }
}
