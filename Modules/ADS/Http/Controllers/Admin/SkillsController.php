<?php

namespace Modules\ADS\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\ADS\Services\SkillsService;

/**
 * UI Skills MVP read-only — lista + detalhe markdown render.
 *
 * Lê .claude/skills/<slug>/SKILL.md direto do filesystem (sem DB).
 * Quando ADR 0076 Sprint A entregar mcp_skills, este controller vira
 * o ponto de evolução — body lê de DB em vez de filesystem.
 *
 * V1: só superadmin. V2 (Sprint B CYCLE-02): permission ads.admin.skills.read.
 *
 * @see memory/decisions/0076-skills-db-primary-git-destino-drift-alert.md
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
            abort(404, "Skill '{$slug}' não encontrada em .claude/skills/.");
        }

        return Inertia::render('ads/Admin/Skills/Show', [
            'skill' => $skill,
        ]);
    }
}
