<?php

namespace Modules\ADS\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\ADS\Http\Requests\StoreSkillVersionRequest;
use Inertia\Inertia;
use Inertia\Response;
use Modules\ADS\Services\SkillsService;
use Modules\Jana\Entities\Conversa;
use Modules\Jana\Entities\Mcp\McpSkill;
use Modules\Jana\Entities\Mcp\McpSkillApproval;
use Modules\Jana\Entities\Mcp\McpSkillLabel;
use Modules\Jana\Entities\Mcp\McpSkillTestRun;
use Modules\Jana\Entities\Mcp\McpSkillVersion;
use Modules\Jana\Entities\Mensagem;
use Modules\Jana\Services\Skills\PublicarSkillNoGitService;
use Modules\Jana\Services\Skills\SkillTestRunnerService;
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

    public function store(string $slug, StoreSkillVersionRequest $request, SkillsService $service): RedirectResponse
    {
        // Wave 27 D8.c — validação extraída pra StoreSkillVersionRequest.
        $data = $request->validated();

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

    public function test(string $slug, SkillsService $service): Response
    {
        $skill = $service->findBySlug($slug);
        if ($skill === null || $skill['source'] !== 'db') {
            abort(404, "Skill '{$slug}' não está em DB.");
        }

        $skillModel = McpSkill::where('slug', $slug)->first();
        $currentVersion = $skillModel?->currentVersion;

        $recentRuns = [];
        if ($skillModel) {
            $recentRuns = McpSkillTestRun::whereIn(
                'version_id',
                $skillModel->versions()->pluck('id')
            )
                ->orderByDesc('executed_at')
                ->limit(10)
                ->get()
                ->map(fn (McpSkillTestRun $r) => [
                    'id'             => $r->id,
                    'version_id'     => $r->version_id,
                    'prompt_preview' => mb_substr((string) ($r->input_json['prompt'] ?? ''), 0, 80),
                    'output_preview' => mb_substr((string) ($r->output ?? ''), 0, 200),
                    'latency_ms'     => $r->latency_ms,
                    'output_tokens'  => $r->output_tokens,
                    'pii_count'      => $r->pii_redactions_count,
                    'executed_at'    => $r->executed_at?->format('Y-m-d H:i:s'),
                ])
                ->all();
        }

        return Inertia::render('ads/Admin/Skills/Test', [
            'skill'          => $skill,
            'currentVersion' => $currentVersion?->version,
            'currentVersionId' => $currentVersion?->id,
            'recentRuns'     => $recentRuns,
            'dryRun'         => (bool) config('copiloto.dry_run', false),
        ]);
    }

    public function runTest(string $slug, Request $request, SkillTestRunnerService $runner): RedirectResponse
    {
        $data = $request->validate([
            'source'           => 'sometimes|in:manual,real_conversations',
            'prompt'           => 'required_if:source,manual|nullable|string|min:3|max:8000',
            'real_count'       => 'sometimes|integer|min:1|max:50',
            'real_business_id' => 'sometimes|nullable|integer',
        ]);

        $skillModel = McpSkill::where('slug', $slug)->first();
        if ($skillModel === null || $skillModel->current_version_id === null) {
            abort(404, "Skill '{$slug}' sem version current.");
        }

        $version = McpSkillVersion::find($skillModel->current_version_id);
        if ($version === null) {
            abort(404, 'Version current não encontrada.');
        }

        $source = $data['source'] ?? 'manual';

        if ($source === 'real_conversations') {
            $bizId = $data['real_business_id'] ?? $request->session()->get('user.business_id');
            $count = (int) ($data['real_count'] ?? 5);
            $runs = $this->runAgainstRealConversations($runner, $version, (int) $bizId, $count);

            return back()->with('status', count($runs).' test runs executados contra conversas reais (business_id='.$bizId.').');
        }

        $run = $runner->run(
            $version,
            (string) $data['prompt'],
            $request->session()->get('user.business_id'),
            auth()->id(),
        );

        return back()
            ->with('status', "Test run #{$run->id} concluído ({$run->latency_ms}ms, {$run->output_tokens} tokens out)");
    }

    /**
     * Item #15 — roda skill contra últimas N mensagens de user em conversas reais
     * do business_id. PII redactor obrigatório.
     *
     * @return array<int, McpSkillTestRun>
     */
    private function runAgainstRealConversations(
        SkillTestRunnerService $runner,
        McpSkillVersion $version,
        int $businessId,
        int $count
    ): array {
        $userMessages = Mensagem::query()
            ->whereHas('conversa', fn ($q) => $q->where('business_id', $businessId))
            ->where('role', 'user')
            ->orderByDesc('id')
            ->limit($count)
            ->pluck('content');

        $runs = [];
        foreach ($userMessages as $content) {
            $prompt = trim((string) $content);
            if (mb_strlen($prompt) < 3) {
                continue;
            }
            try {
                $runs[] = $runner->run($version, $prompt, $businessId, auth()->id());
            } catch (\Throwable $e) {
                // continua mesmo com erro em uma mensagem específica
            }
        }
        return $runs;
    }

    /**
     * Item #8 — fila de approval (versions com status=draft).
     */
    public function review(): Response
    {
        $drafts = McpSkillVersion::with(['skill'])
            ->where('status', 'draft')
            ->whereHas('skill') // evita órfãos
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(function (McpSkillVersion $v) {
                $testRunsCount = McpSkillTestRun::where('version_id', $v->id)->count();
                $testRunsPass = McpSkillTestRun::where('version_id', $v->id)->where('passed', true)->count();
                return [
                    'id'                  => $v->id,
                    'skill_slug'          => $v->skill->slug,
                    'skill_name'          => $v->frontmatter_json['name'] ?? $v->skill->slug,
                    'version'             => $v->version,
                    'origin'              => $v->origin,
                    'rationale_problem'   => mb_substr((string) $v->rationale_problem, 0, 200),
                    'rationale_hypothesis'=> mb_substr((string) $v->rationale_hypothesis, 0, 200),
                    'created_at'          => $v->created_at?->format('Y-m-d H:i'),
                    'test_runs_count'     => $testRunsCount,
                    'test_runs_pass'      => $testRunsPass,
                ];
            });

        return Inertia::render('ads/Admin/Skills/Review', [
            'drafts' => $drafts,
        ]);
    }

    /**
     * Item #8 + #12 — approve version: status=published, label production move.
     */
    public function approve(int $versionId, Request $request): RedirectResponse
    {
        $data = $request->validate([
            'comment' => 'nullable|string|max:2000',
        ]);

        $version = McpSkillVersion::with('skill')->findOrFail($versionId);
        $skill = $version->skill;

        if ($version->status !== 'draft') {
            return back()->withErrors(['decision' => "Version já está '{$version->status}', não pode ser aprovada."]);
        }

        $testRunsCount = McpSkillTestRun::where('version_id', $version->id)->count();
        $testRunsPass = McpSkillTestRun::where('version_id', $version->id)->where('passed', true)->count();

        // Registra approval
        McpSkillApproval::create([
            'version_id'      => $version->id,
            'approver_id'     => auth()->id(),
            'decision'        => 'approve',
            'comment'         => $data['comment'] ?? null,
            'decided_at'      => now(),
            'test_runs_count' => $testRunsCount,
            'test_runs_pass'  => $testRunsPass,
        ]);

        // Version → published
        $version->status = 'published';
        $version->save();

        // Skill → published; current_version_id move
        $previousCurrentVersionId = $skill->current_version_id;
        $skill->status = 'published';
        $skill->current_version_id = $version->id;
        $skill->save();

        // Label production move
        $existingLabel = McpSkillLabel::where('skill_id', $skill->id)
            ->where('label', 'production')
            ->first();
        if ($existingLabel) {
            $existingLabel->update([
                'previous_version_id' => $existingLabel->version_id,
                'version_id'          => $version->id,
                'moved_by'            => auth()->id(),
                'moved_at'            => now(),
                'reason'              => 'Approved via UI (approval id futuro)',
            ]);
        } else {
            McpSkillLabel::create([
                'skill_id'   => $skill->id,
                'label'      => 'production',
                'version_id' => $version->id,
                'moved_by'   => auth()->id(),
                'moved_at'   => now(),
                'reason'     => 'Approved via UI (primeiro production)',
            ]);
        }

        $autoPublishMsg = '';
        if ($skill->auto_publish_to_git) {
            $autoPublishMsg = ' auto_publish_to_git=true; rode "Publish to git" pra criar PR.';
        }

        return redirect()->route('ads.admin.skills.show', ['slug' => $skill->slug])
            ->with('status', "Skill '{$skill->slug}' v{$version->version} aprovada. Label production movida.{$autoPublishMsg}");
    }

    /**
     * Item #8 — reject version: status=archived.
     */
    public function reject(int $versionId, Request $request): RedirectResponse
    {
        $data = $request->validate([
            'comment' => 'required|string|min:5|max:2000',
        ]);

        $version = McpSkillVersion::with('skill')->findOrFail($versionId);

        McpSkillApproval::create([
            'version_id'      => $version->id,
            'approver_id'     => auth()->id(),
            'decision'        => 'reject',
            'comment'         => $data['comment'],
            'decided_at'      => now(),
            'test_runs_count' => McpSkillTestRun::where('version_id', $version->id)->count(),
            'test_runs_pass'  => McpSkillTestRun::where('version_id', $version->id)->where('passed', true)->count(),
        ]);

        $version->status = 'archived';
        $version->save();

        return back()->with('status', "Version v{$version->version} rejeitada e arquivada.");
    }

    /**
     * Item #9 — Publish to git: gera SKILL.md + cria PR via GitHub API.
     */
    public function publish(int $versionId, PublicarSkillNoGitService $publisher): RedirectResponse
    {
        $version = McpSkillVersion::with('skill')->findOrFail($versionId);

        if ($version->status !== 'published') {
            return back()->withErrors(['publish' => "Version precisa estar 'published' (aprovada). Atual: '{$version->status}'."]);
        }

        try {
            $result = $publisher->publish($version);
        } catch (\Throwable $e) {
            return back()->withErrors(['publish' => 'Falha: '.$e->getMessage()]);
        }

        $msg = $result['dry_run']
            ? "DRY_RUN: skill geraria PR em branch {$result['branch']} (sem GITHUB_API_TOKEN)."
            : "PR #{$result['pr_number']} criado em {$result['branch']}: {$result['pr_url']}";

        return back()->with('status', $msg);
    }

    /**
     * Item #12 — promove staging → production OU rollback (mover label pra version anterior).
     */
    public function moveLabel(string $slug, Request $request): RedirectResponse
    {
        $data = $request->validate([
            'label'      => 'required|in:production,staging,dev',
            'version_id' => 'required|integer|exists:mcp_skill_versions,id',
            'reason'     => 'nullable|string|max:500',
        ]);

        $skillModel = McpSkill::where('slug', $slug)->firstOrFail();
        $version = McpSkillVersion::findOrFail($data['version_id']);
        if ($version->skill_id !== $skillModel->id) {
            abort(422, 'Version pertence a outra skill.');
        }

        $existing = McpSkillLabel::where('skill_id', $skillModel->id)
            ->where('label', $data['label'])
            ->first();

        if ($existing) {
            $existing->update([
                'previous_version_id' => $existing->version_id,
                'version_id'          => $version->id,
                'moved_by'            => auth()->id(),
                'moved_at'            => now(),
                'reason'              => $data['reason'] ?? "Moved via UI",
            ]);
        } else {
            McpSkillLabel::create([
                'skill_id'   => $skillModel->id,
                'label'      => $data['label'],
                'version_id' => $version->id,
                'moved_by'   => auth()->id(),
                'moved_at'   => now(),
                'reason'     => $data['reason'] ?? "Moved via UI (primeiro {$data['label']})",
            ]);
        }

        // Se moveu production, atualiza skill.current_version_id
        if ($data['label'] === 'production') {
            $skillModel->current_version_id = $version->id;
            $skillModel->save();
        }

        return back()->with('status', "Label '{$data['label']}' movida pra v{$version->version}.");
    }
}
