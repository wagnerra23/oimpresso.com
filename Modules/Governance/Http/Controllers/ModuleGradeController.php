<?php

declare(strict_types=1);

namespace Modules\Governance\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Governance\Services\ModuleGradeService;

/**
 * Module Grades dashboard — rubrica oficial module-grade-v1 (ADR 0153).
 *
 * Rotas:
 *   GET /governance/module-grades         → Index (tabela 34 módulos ranqueada)
 *   GET /governance/module-grades/{name}  → Show (drill-down 5 dimensões + gaps + Evoluir)
 *
 * Cache 5min em gradeAllModules() — Service faz I/O filesystem 1-2s × 34 módulos.
 *
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

        return Inertia::render('governance/ModuleGrades/Show', [
            'grade' => $grade,
        ]);
    }

    /**
     * @return array<int, array>
     */
    private function buildAllGradesPayload(): array
    {
        return Cache::remember(
            self::CACHE_KEY_ALL,
            self::CACHE_TTL_SECONDS,
            fn () => $this->service->gradeAllModules()
                ->map(fn ($g) => [
                    'module'     => $g['module'],
                    'score'      => $g['score'],
                    'bucket'     => $g['bucket'],
                    'color'      => $g['color'],
                    'dimensions' => [
                        'multi_tenant'  => "{$g['dimensions']['multi_tenant']['score']}/{$g['dimensions']['multi_tenant']['max']}",
                        'pest_coverage' => "{$g['dimensions']['pest_coverage']['score']}/{$g['dimensions']['pest_coverage']['max']}",
                        'documentation' => "{$g['dimensions']['documentation']['score']}/{$g['dimensions']['documentation']['max']}",
                        'architecture'  => "{$g['dimensions']['architecture']['score']}/{$g['dimensions']['architecture']['max']}",
                        'client_real'   => "{$g['dimensions']['client_real']['score']}/{$g['dimensions']['client_real']['max']}",
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
