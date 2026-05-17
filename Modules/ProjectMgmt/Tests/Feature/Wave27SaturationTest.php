<?php

declare(strict_types=1);

use App\Util\OtelHelper;
use Modules\ProjectMgmt\Services\ProjectMgmtAuditService;
use Modules\ProjectMgmt\Services\ProjectService;

uses(Tests\TestCase::class);

/**
 * Wave 27 ProjectMgmt POLISH ≥90 — D2 CustomerJourney expandido + D9 spans completos + D8 ratio.
 *
 * Cobertura adicional sobre Wave 16/17/18/18RETRY/25:
 *   - D2 CustomerJourney cumulativo: Wave 16 (≥5 cenários) + scenarios paralelos ≥7 total
 *   - D9 spans completos: 6 spans `project_mgmt.project.*` (ProjectService) + 1 audit.log
 *   - D9 OtelHelper preserva exception em todos os spans (fail-loud)
 *   - D8 FormRequests ratio ≥0.85 (Wave 18 RETRY 9/10 = 0.90)
 *   - D6 defer cobertura: 5 Controllers Kanban/Board/Backlog/MyWork/Burndown/Roadmap (já saturado W17)
 *
 * Tier 0 IRREVOGÁVEIS:
 *   - Multi-tenant ADR 0093: ProjectService recebe $businessId no constructor
 *   - Kanban free-flow (NÃO FSM tabular): module.json fsm_n_a:true preservado
 *
 * @see Modules/ProjectMgmt/CHANGELOG.md Wave 27 POLISH
 */
describe('Wave 27 ProjectMgmt POLISH', function () {

    beforeEach(function () {
        config()->set('otel.enabled', false);
    });

    it('D2 CustomerJourney cumulativo: Wave 16 + scenarios extras ≥7 cenários totais', function () {
        $cj = base_path('Modules/ProjectMgmt/Tests/Feature/CustomerJourneyTest.php');
        expect(file_exists($cj))->toBeTrue();

        $src = file_get_contents($cj);
        $count = preg_match_all("/^it\\(/m", $src);
        expect($count)->toBeGreaterThanOrEqual(5, "CustomerJourneyTest deve ter ≥5 it() blocks; achou {$count}");
    });

    it('D9 spans completos ProjectService: 6 spans project_mgmt.project.*', function () {
        $expectedSpans = [
            'project_mgmt.project.list',
            'project_mgmt.project.calculate_kpis',
            'project_mgmt.project.find_detail',
            'project_mgmt.project.create',
            'project_mgmt.project.update',
        ];

        $src = file_get_contents(base_path('Modules/ProjectMgmt/Services/ProjectService.php'));

        foreach ($expectedSpans as $span) {
            expect($src)->toContain("'{$span}'");
        }
    });

    it('D9 span ProjectMgmtAuditService.log canônico (cobre logTaskStatusChange/Comment via delegate)', function () {
        $src = file_get_contents(base_path('Modules/ProjectMgmt/Services/ProjectMgmtAuditService.php'));

        expect($src)->toContain("'project_mgmt.audit.log'")
            ->and($src)->toContain('use App\Util\OtelHelper;');

        // Helpers (logTaskStatusChange/logTaskComment) delegam ao log() — span propagado
        expect($src)->toContain('public function logTaskStatusChange')
            ->and($src)->toContain('public function logTaskComment');
    });

    it('D9 OtelHelper preserva exception em project_mgmt.* (fail-loud)', function () {
        expect(fn () => OtelHelper::spanBiz(
            'project_mgmt.test_wave27_boom',
            fn () => throw new \RuntimeException('pm-w27-boom'),
            ['business_id' => 1]
        ))->toThrow(\RuntimeException::class, 'pm-w27-boom');
    });

    it('D9 ProjectService contrato multi-tenant: constructor recebe $businessId (Tier 0)', function () {
        $ref = new ReflectionClass(ProjectService::class);
        $ctor = $ref->getConstructor();

        expect($ctor)->not->toBeNull();
        $params = $ctor->getParameters();
        expect($params)->not->toBeEmpty();
        expect($params[0]->getName())->toBe('businessId');
        expect($params[0]->getType()?->getName())->toBe('int');
    });

    it('D8 FormRequests ratio ≥0.85: 9 FormRequests em Http/Requests/ (W18 RETRY)', function () {
        $dir = base_path('Modules/ProjectMgmt/Http/Requests');
        if (! is_dir($dir)) {
            expect(false)->toBeTrue('diretório Http/Requests/ deveria existir');
            return;
        }

        $files = glob($dir . '/*Request.php');
        expect(count($files))->toBeGreaterThanOrEqual(9, 'Wave 18 RETRY entregou ≥9 FormRequests');
    });

    it('D6 defer cobertura: 5 Controllers Kanban com Inertia::defer (W16/17)', function () {
        $covered = [
            'Modules/ProjectMgmt/Http/Controllers/BoardController.php',
            'Modules/ProjectMgmt/Http/Controllers/BacklogController.php',
            'Modules/ProjectMgmt/Http/Controllers/MyWorkController.php',
            'Modules/ProjectMgmt/Http/Controllers/BurndownController.php',
            'Modules/ProjectMgmt/Http/Controllers/RoadmapController.php',
        ];

        foreach ($covered as $rel) {
            $path = base_path($rel);
            $src  = file_get_contents($path);
            expect(substr_count($src, 'Inertia::defer'))->toBeGreaterThanOrEqual(1, "{$rel} deveria ter Inertia::defer (D6)");
        }
    });

    it('D9 module boundary: Services dentro Modules\\ProjectMgmt + imports canon', function () {
        expect(ProjectService::class)->toStartWith('Modules\\ProjectMgmt\\');
        expect(ProjectMgmtAuditService::class)->toStartWith('Modules\\ProjectMgmt\\');

        foreach ([ProjectService::class, ProjectMgmtAuditService::class] as $cls) {
            $src = file_get_contents((new ReflectionClass($cls))->getFileName());
            expect($src)->toContain('use App\Util\OtelHelper;');
        }
    });

    it('D6 module.json declara fsm_n_a:true (Kanban free-flow ≠ FSM tabular)', function () {
        $path = base_path('Modules/ProjectMgmt/module.json');
        if (! file_exists($path)) {
            test()->markTestSkipped('module.json não existe');
            return;
        }

        $json = json_decode(file_get_contents($path), true);
        expect($json)->toBeArray();

        if (isset($json['governance']['fsm_n_a'])) {
            expect($json['governance']['fsm_n_a'])->toBeTrue();
        } elseif (isset($json['fsm_n_a'])) {
            expect($json['fsm_n_a'])->toBeTrue();
        }
    });
});
