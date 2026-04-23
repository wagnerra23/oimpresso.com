<?php

namespace Modules\DocVault\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\DocVault\Entities\DocEvidence;
use Modules\DocVault\Entities\DocPage;
use Modules\DocVault\Entities\DocSource;
use Modules\DocVault\Entities\DocValidationRun;
use Modules\DocVault\Services\ModuleAuditor;
use Modules\DocVault\Services\RequirementsFileReader;

class DashboardController extends Controller
{
    protected function countRulesWithoutTest(array $m): int
    {
        // O reader não carrega rules detalhadas em listModules() — sempre recarrega.
        $data = app(RequirementsFileReader::class)->readModule($m['name']);
        if (! $data) return $m['rules_count'] ?? 0;
        return count(array_filter($data['rules'], fn ($r) => empty($r['testado_em'])));
    }

    public function index(Request $request, RequirementsFileReader $reader): Response
    {
        $businessId = (int) (session('business.id') ?: $request->session()->get('user.business_id'));

        // Módulos + stats (dos arquivos .md)
        $modules = $reader->listModules();

        // Agrega stats globais
        $totalStories = array_sum(array_column($modules, 'stories_count'));
        $totalRules   = array_sum(array_column($modules, 'rules_count'));
        $totalDod     = array_sum(array_column($modules, 'dod_total'));
        $doneDod      = array_sum(array_column($modules, 'dod_done'));
        $dodPct       = $totalDod > 0 ? round($doneDod / $totalDod * 100) : 0;

        // Evidências por status
        $evidencesByStatus = DocEvidence::where('business_id', $businessId)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        // Trace score por módulo (ADR 0005)
        $pagesByModule = DocPage::all()->groupBy('module');
        $lastValidationByModule = DocValidationRun::orderByDesc('run_at')
            ->get()
            ->unique('module')
            ->keyBy('module');

        // Fontes recentes (últimas 5)
        $recentSources = DocSource::where('business_id', $businessId)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn (DocSource $s) => [
                'id'         => $s->id,
                'type'       => $s->type,
                'title'      => $s->title ?? '(sem título)',
                'module'     => $s->module_target,
                'created_at' => optional($s->created_at)->format('Y-m-d H:i'),
                'created_at_human' => optional($s->created_at)->diffForHumans(),
            ])
            ->values();

        return Inertia::render('DocVault/Dashboard', [
            'stats' => [
                'modules_total'    => count($modules),
                'stories_total'    => $totalStories,
                'rules_total'      => $totalRules,
                'dod_total'        => $totalDod,
                'dod_done'         => $doneDod,
                'dod_pct'          => $dodPct,
                'sources_total'    => DocSource::where('business_id', $businessId)->count(),
                'evidences_pending' => $evidencesByStatus['pending'] ?? 0,
                'evidences_triaged' => $evidencesByStatus['triaged'] ?? 0,
                'evidences_applied' => $evidencesByStatus['applied'] ?? 0,
            ],
            'modules' => array_map(function ($m) use ($pagesByModule, $lastValidationByModule) {
                $pages = $pagesByModule->get($m['name'], collect());
                $validation = $lastValidationByModule->get($m['name']);
                $storiesInPages = $pages->flatMap(fn ($p) => $p->stories ?? [])->unique()->count();
                $rulesTested = ($m['rules_count'] ?? 0) > 0
                    ? (int) round((($m['rules_count'] - $this->countRulesWithoutTest($m)) / $m['rules_count']) * 100)
                    : 0;

                $traceScore = 0;
                if ($m['stories_count'] > 0 || $m['rules_count'] > 0) {
                    $storiesPct = $m['stories_count'] > 0 ? ($storiesInPages / $m['stories_count']) * 100 : 100;
                    $traceScore = (int) round(($storiesPct + $rulesTested) / 2);
                }

                // Audit score só pra módulos em formato pasta (rápido, cacheado numa request)
                $auditScore = null;
                if (($m['format'] ?? 'flat') === 'folder') {
                    $audit = app(ModuleAuditor::class)->audit($m['name']);
                    $auditScore = $audit['score'];
                }

                return [
                    'name'           => $m['name'],
                    'format'         => $m['format'] ?? 'flat',
                    'status'         => $m['frontmatter']['status'] ?? 'unknown',
                    'priority'       => $m['frontmatter']['migration_priority'] ?? 'média',
                    'stories_count'  => $m['stories_count'],
                    'rules_count'    => $m['rules_count'],
                    'dod_pct'        => $m['dod_pct'],
                    'coverage'       => $m['coverage'] ?? null,
                    'pages_count'    => $pages->count(),
                    'trace_score'    => $traceScore,
                    'health_score'   => $validation ? $validation->health_score : null,
                    'audit_score'    => $auditScore,
                ];
            }, $modules),
            'pages_total' => DocPage::count(),
            'coverage_summary' => [
                'folder_count' => count(array_filter($modules, fn ($m) => ($m['format'] ?? 'flat') === 'folder')),
                'flat_count'   => count(array_filter($modules, fn ($m) => ($m['format'] ?? 'flat') === 'flat')),
                'avg_score'    => count($modules) > 0
                    ? (int) round(array_sum(array_map(fn ($m) => $m['coverage']['score'] ?? 0, $modules)) / count($modules))
                    : 0,
                'total_adrs'   => array_sum(array_map(fn ($m) => $m['coverage']['adrs'] ?? 0, $modules)),
            ],
            'recent_sources' => $recentSources,
        ]);
    }
}
