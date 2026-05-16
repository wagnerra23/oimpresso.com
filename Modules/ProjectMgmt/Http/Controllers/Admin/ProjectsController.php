<?php

namespace Modules\ProjectMgmt\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\ADS\Services\ProjectDecomposerService;
use Modules\ProjectMgmt\Services\ProjectMgmtAuditService;
use Modules\ProjectMgmt\Services\ProjectService;

/**
 * ProjectsController — admin UI mcp_projects (D4 Wave 16 refatorado).
 *
 * Refatoração D4 SoC brutal (Wave 16 Governance):
 *   - Lógica de listagem/detalhe/criação extraída pra ProjectService
 *   - Controller agora é thin: valida input + delega + renderiza Inertia
 *   - Audit LGPD via ProjectMgmtAuditService (D7.b)
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL ([ADR 0093]):
 * `businessId` resolvido da session UltimatePOS e passado explícito no constructor
 * do Service via `app()->makeWith()` (Service não tem dep injection do biz).
 *
 * @see Modules\ProjectMgmt\Services\ProjectService
 * @see Modules\ProjectMgmt\Services\ProjectMgmtAuditService
 */
class ProjectsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request): Response
    {
        $service = $this->makeService($request);

        $projects = $service->list();
        $kpis     = $service->calculateKpis($projects);

        return Inertia::render('ads/Admin/Projects', [
            'projects' => $projects->values(),
            'kpis'     => $kpis,
        ]);
    }

    public function show(Request $request, int $id): Response
    {
        $service = $this->makeService($request);

        try {
            $detail = $service->findDetail($id);
        } catch (ModelNotFoundException $e) {
            abort(404, 'Project não encontrado');
        }

        return Inertia::render('ads/Admin/ProjectShow', [
            'project'   => $detail['project'],
            'parts'     => $detail['parts'],
            'decisions' => $detail['decisions'],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nome'           => 'required|string|max:200',
            'objetivo_macro' => 'required|string|max:2000',
            'codigo'         => 'sometimes|string|max:30|unique:mcp_projects,codigo',
        ]);

        $service = $this->makeService($request);
        $id = $service->create($data);

        // D7.b audit trail LGPD (sem PII em description — codigo+nome são metadata)
        $this->makeAuditService($request)->log(
            event: ProjectMgmtAuditService::EVENT_PROJECT_CREATED,
            description: "Project criado: {$data['nome']}",
            properties: [
                'project_id'     => $id,
                'nome'           => $data['nome'],
                'objetivo_macro' => $data['objetivo_macro'], // redacted pelo Service
            ],
            subjectType: 'mcp_projects',
            subjectId: $id,
        );

        return redirect("/ads/admin/projects/{$id}")->with('status', "Project criado com sucesso.");
    }

    public function decompose(Request $request, int $id, ProjectDecomposerService $service): RedirectResponse
    {
        $result = $service->decompose($id);

        if ($result['success']) {
            // D7.b audit trail LGPD da decomposição
            $this->makeAuditService($request)->log(
                event: ProjectMgmtAuditService::EVENT_PROJECT_DECOMPOSED,
                description: "Project {$id} decomposto em {$result['parts_created']} parts",
                properties: [
                    'project_id'        => $id,
                    'parts_created'     => $result['parts_created'],
                    'viability_overall' => $result['viability_overall'] ?? null,
                ],
                subjectType: 'mcp_projects',
                subjectId: $id,
            );

            return back()->with('status', "Project decomposto em {$result['parts_created']} parts. Viability geral: {$result['viability_overall']}%");
        }

        return back()->with('error', "Falha na decomposição: " . ($result['error'] ?? 'desconhecido'));
    }

    /**
     * Factory do ProjectService com $businessId resolvido da session UltimatePOS.
     *
     * Service exige `businessId` no constructor pra defesa-em-profundidade
     * multi-tenant Tier 0 — NUNCA usa `session()` internamente (ADR 0093).
     */
    private function makeService(Request $request): ProjectService
    {
        $businessId = (int) $request->session()->get('user.business_id', 1);

        return app()->makeWith(ProjectService::class, ['businessId' => $businessId]);
    }

    /**
     * Factory do ProjectMgmtAuditService com $businessId resolvido da session.
     */
    private function makeAuditService(Request $request): ProjectMgmtAuditService
    {
        $businessId = (int) $request->session()->get('user.business_id', 1);

        return app()->makeWith(ProjectMgmtAuditService::class, ['businessId' => $businessId]);
    }
}
