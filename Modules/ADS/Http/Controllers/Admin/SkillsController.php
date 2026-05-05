<?php

namespace Modules\ADS\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\ADS\Services\SkillsService;
use Modules\Copiloto\Entities\Mcp\McpSkill;
use Modules\Copiloto\Entities\Mcp\McpSkillVersion;
use Symfony\Component\Yaml\Yaml;

/**
 * UI Skills (ADR 0076) — V2 com edição inline + version draft em DB.
 *
 * Fluxo Fase 2:
 *   GET /skills/{slug}/edit  → editor (Monaco-like textarea, V2 mínimo)
 *   POST /skills/{slug}      → cria version status=draft, origin=ui, com 4 rationales
 *
 * Drafts NÃO vão pra production automaticamente. Approval queue (Fase 4)
 * move label production pra version aprovada.
 *
 * V1: middleware('auth'). V2 (Sprint B futura): permission ads.admin.skills.edit.
 */
class SkillsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(SkillsService $service): Response
    {
        $skills = $service->listAll();

        $kpis = [
            'total'        => count($skills),
            'with_module'  => count(array_filter($skills, fn ($s) => ! empty($s['module']))),
            'avg_body'     => count($skills) > 0
                ? (int) round(array_sum(array_column($skills, 'body_chars')) / count($skills))
                : 0,
        ];

        return Inertia::render('ads/Admin/Skills/Index', [
            'skills' => $skills,
            'kpis'   => $kpis,
        ]);
    }

    public function show(string $slug, SkillsService $service): Response
    {
        $skill = $service->findBySlug($slug);

        if ($skill === null) {
            abort(404, "Skill '{$slug}' não encontrada.");
        }

        // Se vier do DB, expor timeline de versions pra UI
        $versions = [];
        $editable = false;
        if ($skill['source'] === 'db') {
            $skillModel = McpSkill::where('slug', $slug)->first();
            if ($skillModel) {
                $editable = true;
                $versions = $skillModel->versions()
                    ->orderByDesc('version')
                    ->limit(20)
                    ->get()
                    ->map(fn (McpSkillVersion $v) => [
                        'id'         => $v->id,
                        'version'    => $v->version,
                        'origin'     => $v->origin,
                        'status'     => $v->status,
                        'created_at' => $v->created_at?->format('Y-m-d H:i'),
                        'is_current' => $v->id === $skillModel->current_version_id,
                    ])
                    ->all();
            }
        }

        return Inertia::render('ads/Admin/Skills/Show', [
            'skill'    => $skill,
            'versions' => $versions,
            'editable' => $editable,
        ]);
    }

    public function edit(string $slug, SkillsService $service): Response
    {
        $skill = $service->findBySlug($slug);
        if ($skill === null || $skill['source'] !== 'db') {
            abort(404, "Skill '{$slug}' não está em DB. Rode mcp:skills:import-from-git primeiro.");
        }

        $skillModel = McpSkill::where('slug', $slug)->first();

        // Frontmatter pra YAML legível no textarea
        $frontmatterYaml = trim(Yaml::dump(
            $skill['frontmatter'],
            inline: 4,
            indent: 2,
            flags: Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK
        ));

        return Inertia::render('ads/Admin/Skills/Edit', [
            'skill'            => $skill,
            'frontmatterYaml'  => $frontmatterYaml,
            'currentVersion'   => $skillModel?->current_version_id
                ? $skillModel->currentVersion?->version
                : null,
        ]);
    }

    public function store(string $slug, Request $request, SkillsService $service): RedirectResponse
    {
        $data = $request->validate([
            'frontmatter_yaml'         => 'required|string|max:5000',
            'body_markdown'            => 'required|string|max:200000',
            'rationale_problem'        => 'required|string|min:10|max:2000',
            'rationale_hypothesis'     => 'required|string|min:10|max:2000',
            'rationale_success_metric' => 'required|string|min:10|max:2000',
            'rationale_rollback'       => 'required|string|min:10|max:2000',
        ]);

        $skillModel = McpSkill::where('slug', $slug)->first();
        if ($skillModel === null) {
            abort(404, "Skill '{$slug}' não encontrada em DB.");
        }

        // Parse YAML frontmatter
        try {
            $frontmatter = Yaml::parse($data['frontmatter_yaml']);
            if (! is_array($frontmatter)) {
                $frontmatter = [];
            }
        } catch (\Throwable $e) {
            return back()
                ->withErrors(['frontmatter_yaml' => 'YAML inválido: '.$e->getMessage()])
                ->withInput();
        }

        $latestVersion = $skillModel->versions()->orderByDesc('version')->first();
        $newVersionNumber = ($latestVersion->version ?? 0) + 1;

        McpSkillVersion::create([
            'skill_id'                 => $skillModel->id,
            'version'                  => $newVersionNumber,
            'body_markdown'            => $data['body_markdown'],
            'frontmatter_json'         => $frontmatter,
            'rationale_problem'        => $data['rationale_problem'],
            'rationale_hypothesis'     => $data['rationale_hypothesis'],
            'rationale_success_metric' => $data['rationale_success_metric'],
            'rationale_rollback'       => $data['rationale_rollback'],
            'origin'                   => 'ui',
            'status'                   => 'draft',
            'created_by'               => auth()->id(),
        ]);

        // Skill ganha status=review quando há draft pendente (será 'published' via approve em Fase 4)
        if ($skillModel->status === 'published') {
            $skillModel->status = 'review';
            $skillModel->save();
        }

        return redirect()
            ->route('ads.admin.skills.show', ['slug' => $slug])
            ->with('status', "Skill '$slug' v{$newVersionNumber} salva como draft. Approval queue (Fase 4) vai aprovar pra production.");
    }
}
