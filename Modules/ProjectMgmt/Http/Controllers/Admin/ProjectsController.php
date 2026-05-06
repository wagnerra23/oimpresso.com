<?php

namespace Modules\ProjectMgmt\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Modules\ADS\Services\ProjectDecomposerService;

class ProjectsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request): Response
    {
        $businessId = (int) $request->session()->get('user.business_id', 1);

        $projects = DB::table('mcp_projects')
            ->where('business_id', $businessId)
            ->orderByDesc('id')
            ->get()
            ->map(function ($p) {
                $partsCount = DB::table('mcp_project_parts')->where('project_id', $p->id)->count();
                $partsDone  = DB::table('mcp_project_parts')->where('project_id', $p->id)->where('status', 'done')->count();
                return [
                    'id'                  => $p->id,
                    'codigo'              => $p->codigo,
                    'nome'                => $p->nome,
                    'objetivo_macro'      => $p->objetivo_macro,
                    'status'              => $p->status,
                    'decision'            => $p->decision,
                    'viability_score'     => $p->viability_score !== null ? (int) $p->viability_score : null,
                    'custo_estimado_brl'  => $p->custo_estimado_brl !== null ? (float) $p->custo_estimado_brl : null,
                    'prazo_estimado_dias' => $p->prazo_estimado_dias !== null ? (int) $p->prazo_estimado_dias : null,
                    'parts_total'         => $partsCount,
                    'parts_done'          => $partsDone,
                    'progress_pct'        => $partsCount > 0 ? round(($partsDone / $partsCount) * 100, 1) : 0,
                    'created_at'          => $p->created_at,
                ];
            });

        $kpis = [
            'total'    => $projects->count(),
            'active'   => $projects->where('status', 'active')->count(),
            'draft'    => $projects->where('status', 'draft')->count(),
            'completed' => $projects->where('status', 'completed')->count(),
        ];

        return Inertia::render('ads/Admin/Projects', [
            'projects' => $projects->values(),
            'kpis'     => $kpis,
        ]);
    }

    public function show(Request $request, int $id): Response
    {
        $businessId = (int) $request->session()->get('user.business_id', 1);

        $project = DB::table('mcp_projects')
            ->where('id', $id)
            ->where('business_id', $businessId)
            ->firstOrFail();

        $parts = DB::table('mcp_project_parts')
            ->where('project_id', $id)
            ->orderBy('ordem')
            ->get()
            ->map(fn ($p) => [
                'id'                 => $p->id,
                'ordem'              => (int) $p->ordem,
                'codigo'             => $p->codigo,
                'nome'                => $p->nome,
                'objetivo'           => $p->objetivo,
                'dependencias'       => json_decode($p->dependencias ?? '[]', true),
                'arquivos_estimados' => json_decode($p->arquivos_estimados ?? '[]', true),
                'status'             => $p->status,
                'viability_score'    => $p->viability_score !== null ? (int) $p->viability_score : null,
                'risco'              => $p->risco !== null ? (int) $p->risco : null,
                'estimativa_horas'   => $p->estimativa_horas,
                'valor_estimado_brl' => $p->valor_estimado_brl !== null ? (float) $p->valor_estimado_brl : null,
            ])
            ->all();

        // Decisions linkadas a esse project
        $decisions = DB::table('mcp_dual_brain_decisions')
            ->where('project_id', $id)
            ->orderBy('id')
            ->get(['id', 'event_type', 'domain', 'destination', 'outcome', 'review_score'])
            ->all();

        return Inertia::render('ads/Admin/ProjectShow', [
            'project' => [
                'id'                  => $project->id,
                'codigo'              => $project->codigo,
                'nome'                => $project->nome,
                'objetivo_macro'      => $project->objetivo_macro,
                'metricas_sucesso'    => json_decode($project->metricas_sucesso ?? '[]', true),
                'constraints'         => json_decode($project->constraints ?? '{}', true),
                'status'              => $project->status,
                'decision'            => $project->decision,
                'viability_score'     => $project->viability_score !== null ? (int) $project->viability_score : null,
                'viability_factors'   => json_decode($project->viability_factors ?? '{}', true),
                'custo_estimado_brl'  => $project->custo_estimado_brl !== null ? (float) $project->custo_estimado_brl : null,
                'valor_estimado_brl'  => $project->valor_estimado_brl !== null ? (float) $project->valor_estimado_brl : null,
                'prazo_estimado_dias' => $project->prazo_estimado_dias,
                'owner'               => $project->owner,
                'created_at'          => $project->created_at,
            ],
            'parts'     => $parts,
            'decisions' => $decisions,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $businessId = (int) $request->session()->get('user.business_id', 1);

        $data = $request->validate([
            'nome'           => 'required|string|max:200',
            'objetivo_macro' => 'required|string|max:2000',
            'codigo'         => 'sometimes|string|max:30|unique:mcp_projects,codigo',
        ]);

        $codigo = $data['codigo'] ?? 'PROJ-' . date('Ym') . '-' . str_pad((string) (DB::table('mcp_projects')->count() + 1), 3, '0', STR_PAD_LEFT);

        $id = DB::table('mcp_projects')->insertGetId([
            'business_id'      => $businessId,
            'codigo'           => $codigo,
            'nome'             => $data['nome'],
            'objetivo_macro'   => $data['objetivo_macro'],
            'metricas_sucesso' => json_encode([]),
            'constraints'      => json_encode([]),
            'status'           => 'draft',
            'decision'         => 'pending',
            'owner'            => 'wagner',
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        return redirect("/ads/admin/projects/{$id}")->with('status', "Project {$codigo} criado.");
    }

    public function decompose(Request $request, int $id, ProjectDecomposerService $service): RedirectResponse
    {
        $result = $service->decompose($id);

        if ($result['success']) {
            return back()->with('status', "Project decomposto em {$result['parts_created']} parts. Viability geral: {$result['viability_overall']}%");
        }

        return back()->with('error', "Falha na decomposição: " . ($result['error'] ?? 'desconhecido'));
    }
}
