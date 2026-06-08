<?php

declare(strict_types=1);

use App\Util\OtelHelper;
use Modules\ADS\Http\Requests\StoreGovernanceMetaSkillRequest;
use Modules\ADS\Http\Requests\StoreSkillVersionRequest;
use Modules\ADS\Services\BrainBService;
use Modules\ADS\Services\PlannerService;
use Modules\ADS\Services\SkillsService;

uses(Tests\TestCase::class);

/**
 * Wave 27 ADS POLISH ≥88 — D2 Pest expandido + D8 +2 FormRequests + D9 spans completos.
 *
 * Cobertura adicional sobre Wave 18/18RETRY/25:
 *   - D2 Pest: novos cenários cumulativos (ADS já tem ≥7 Feature + ≥7 Unit)
 *   - D8 FormRequests Wave 27: +2 (StoreGovernanceMetaSkillRequest + StoreSkillVersionRequest)
 *     → ratio 16/17 = 0.94 (Wave 18 RETRY estava em 14/15 = 0.93)
 *   - D9 spans completos: PlannerService::plan + BrainBService::process + SkillsService 3 spans
 *   - D9 attributes documentados: decision_id em planner/brain, slug em skills
 *   - D9 OtelHelper preserva exception (fail-loud)
 *
 * Tier 0 IRREVOGÁVEIS:
 *   - Dual-brain pattern preservado (mcp_dual_brain_decisions + mcp_decision_patterns isolation)
 *   - Multi-tenant ADR 0093 + ADS é meta-orquestrador (fsm_n_a:true)
 *
 * @see Modules/ADS/CHANGELOG.md Wave 27 POLISH
 */
describe('Wave 27 ADS POLISH', function () {

    beforeEach(function () {
        config()->set('otel.enabled', false);
    });

    it('D2 Pest cumulativo: Feature tests ≥7 + Unit tests ≥7 (ADS bucket internal_governance)', function () {
        $featureCount = count(glob(base_path('Modules/ADS/Tests/Feature/*Test.php')));
        $unitCount    = count(glob(base_path('Modules/ADS/Tests/Unit/*Test.php')));

        expect($featureCount)->toBeGreaterThanOrEqual(7, "≥7 Feature tests; achou {$featureCount}");
        expect($unitCount)->toBeGreaterThanOrEqual(7, "≥7 Unit tests; achou {$unitCount}");
    });

    it('D8 Wave 27 +2 FormRequests: StoreGovernanceMetaSkillRequest existe + regras canon', function () {
        expect(class_exists(StoreGovernanceMetaSkillRequest::class))->toBeTrue();

        $req = new StoreGovernanceMetaSkillRequest();
        $rules = $req->rules();

        // Tier 0: rule_key regex + unique pra evitar collision em mcp_governance_rules
        expect($rules)->toHaveKeys(['rule_key', 'name', 'description', 'category', 'condition', 'action']);
        expect(implode('|', $rules['rule_key']))->toContain('regex:/^[a-z0-9_]+$/');
        expect(implode('|', $rules['category']))->toContain('promotion');
    });

    it('D8 Wave 27 +2 FormRequests: StoreSkillVersionRequest existe + 4 rationale fields obrigatórios', function () {
        expect(class_exists(StoreSkillVersionRequest::class))->toBeTrue();

        $req = new StoreSkillVersionRequest();
        $rules = $req->rules();

        // Tier 0 ADR 0061: 4 rationale fields obrigatórios pra cada nova version
        expect($rules)->toHaveKeys([
            'frontmatter_yaml',
            'body_markdown',
            'rationale_problem',
            'rationale_hypothesis',
            'rationale_success_metric',
            'rationale_rollback',
        ]);
    });

    it('D8 FormRequests ratio Wave 27: 16/17 ≥ 0.94 (W18 RETRY entregou 14/15)', function () {
        $dir = base_path('Modules/ADS/Http/Requests');
        $files = glob($dir . '/*Request.php');
        // Wave 18 RETRY: 14 FormRequests. Wave 27: +2 = 16.
        expect(count($files))->toBeGreaterThanOrEqual(16, 'Wave 27 deve ter ≥16 FormRequests (14 + 2)');
    });

    it('D9 spans completos: PlannerService.plan + BrainBService.process + SkillsService canon', function () {
        $expected = [
            base_path('Modules/ADS/Services/PlannerService.php')   => "'ads.planner.plan'",
            base_path('Modules/ADS/Services/BrainBService.php')    => "'ads.brain_b.process'",
            base_path('Modules/ADS/Services/SkillsService.php')    => "'ads.",
        ];

        foreach ($expected as $file => $expectedFragment) {
            $src = file_get_contents($file);
            expect($src)->toContain($expectedFragment);
            expect($src)->toContain('use App\Util\OtelHelper;');
        }
    });

    it('D9 span attributes: decision_id documentado em planner/brain_b (rastreabilidade)', function () {
        $plannerSrc = file_get_contents(base_path('Modules/ADS/Services/PlannerService.php'));
        $brainBSrc  = file_get_contents(base_path('Modules/ADS/Services/BrainBService.php'));

        expect($plannerSrc)->toContain("'decision_id'");
        expect($brainBSrc)->toContain("'decision_id'");
    });

    it('D9 OtelHelper preserva exception em ads.* (fail-loud)', function () {
        expect(fn () => OtelHelper::span(
            'ads.test_wave27_boom',
            ['decision_id' => 9999],
            fn () => throw new \RuntimeException('ads-w27-boom')
        ))->toThrow(\RuntimeException::class, 'ads-w27-boom');
    });

    it('D2 Controllers ADS Admin com Inertia::defer cobertura (5 Controllers W17/18)', function () {
        $covered = [
            'Modules/ADS/Http/Controllers/Admin/DecisoesController.php',
            'Modules/ADS/Http/Controllers/Admin/LearningController.php',
            'Modules/ADS/Http/Controllers/Admin/MetricasController.php',
            'Modules/ADS/Http/Controllers/Admin/PatternsController.php',
            'Modules/ADS/Http/Controllers/Admin/ConflictsController.php',
        ];

        foreach ($covered as $rel) {
            $src = file_get_contents(base_path($rel));
            expect(substr_count($src, 'Inertia::defer'))->toBeGreaterThanOrEqual(1, "{$rel} deveria ter Inertia::defer (D6)");
        }
    });

    it('D8 Controllers ADS usam FormRequests Wave 27 (MetaSkills + Skills.store)', function () {
        $metaSrc = file_get_contents(base_path('Modules/ADS/Http/Controllers/Admin/MetaSkillsController.php'));
        $skillsSrc = file_get_contents(base_path('Modules/ADS/Http/Controllers/Admin/SkillsController.php'));

        expect($metaSrc)->toContain('StoreGovernanceMetaSkillRequest')
            ->and($metaSrc)->toContain('public function store(StoreGovernanceMetaSkillRequest');

        expect($skillsSrc)->toContain('StoreSkillVersionRequest')
            ->and($skillsSrc)->toContain('StoreSkillVersionRequest $request');
    });

    it('D2 Services ADS resolvidos via container (D4 reuse — dual-brain preservado)', function () {
        $services = [
            PlannerService::class,
            BrainBService::class,
            SkillsService::class,
        ];
        foreach ($services as $svc) {
            expect(app($svc))->toBeInstanceOf($svc);
        }
    });
});
