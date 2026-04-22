<?php

namespace Modules\DocVault\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\DocVault\Entities\DocEvidence;
use Modules\DocVault\Entities\DocSource;
use Modules\DocVault\Services\RequirementsFileReader;

class DashboardController extends Controller
{
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
            'modules' => array_map(fn ($m) => [
                'name'          => $m['name'],
                'status'        => $m['frontmatter']['status'] ?? 'unknown',
                'priority'      => $m['frontmatter']['migration_priority'] ?? 'média',
                'stories_count' => $m['stories_count'],
                'rules_count'   => $m['rules_count'],
                'dod_pct'       => $m['dod_pct'],
            ], $modules),
            'recent_sources' => $recentSources,
        ]);
    }
}
