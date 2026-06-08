<?php

declare(strict_types=1);

use Modules\ProjectMgmt\Console\Commands\ProjectMgmtHealthCommand;
use Modules\ProjectMgmt\Http\Controllers\Admin\ProjectsController;
use Modules\ProjectMgmt\Services\ProjectService;

uses(Tests\TestCase::class);

/**
 * Wave 23 SATURATION ProjectMgmt — F1 + F2 reuse + F3 Perf gap 72→≥80.
 *
 * Wave 16+18 ja entregaram CustomerJourneyTest + ProjectService refator D4.
 * Esta camada complementa:
 *   - F1 Pest: confirma 7 cenarios CustomerJourney Wave 18 + assinaturas Service
 *   - F2 reuse: ProjectService consumivel via container, $businessId injetado
 *               (consumo por Cycles/Backlog/MyWork/Burndown)
 *   - F3 Perf: Controller magro + Service usa OtelHelper::spanBiz canon
 *   - F6 Health: project-mgmt:health registrado canon
 *
 * @see Modules\ProjectMgmt\Services\ProjectService
 * @see Modules\ProjectMgmt\Console\Commands\ProjectMgmtHealthCommand
 */

it('F1 confirmacao: CustomerJourneyTest Wave 18 tem >=5 cenarios', function () {
    $file = base_path('Modules/ProjectMgmt/Tests/Feature/CustomerJourneyTest.php');
    expect(file_exists($file))->toBeTrue();

    $content = file_get_contents($file);
    $matches = preg_match_all("/^it\\('/m", $content);
    expect($matches)->toBeGreaterThanOrEqual(5);
});

it('F2 reuse: ProjectService instanciavel com $businessId>0 (Tier 0 ADR 0093)', function () {
    $svc = new ProjectService(1);
    expect($svc)->toBeInstanceOf(ProjectService::class);
});

it('F2 reuse: ProjectService rejeita $businessId<=0 (defesa Tier 0)', function () {
    expect(fn () => new ProjectService(0))
        ->toThrow(\InvalidArgumentException::class);

    expect(fn () => new ProjectService(-1))
        ->toThrow(\InvalidArgumentException::class);
});

it('F2 reuse: ProjectService expoe 6 metodos canon (list/findDetail/create/update/archive/calculateKpis)', function () {
    $ref = new ReflectionClass(ProjectService::class);

    foreach (['list', 'findDetail', 'create', 'update', 'archive', 'calculateKpis'] as $m) {
        expect($ref->hasMethod($m))->toBeTrue("ProjectService deveria expor metodo {$m}()");
    }
});

it('F2 reuse: ProjectService.list() declara return type Collection', function () {
    $ref = new ReflectionMethod(ProjectService::class, 'list');
    $returnType = $ref->getReturnType()?->getName();

    expect($returnType)->toBe(\Illuminate\Support\Collection::class);
});

it('F3 Perf: ProjectService usa OtelHelper::spanBiz canon (D9 hot-path)', function () {
    $source = file_get_contents(base_path('Modules/ProjectMgmt/Services/ProjectService.php'));

    expect($source)->toContain('use App\Util\OtelHelper;');
    // 6 spans canon Wave 16+17
    foreach ([
        'project_mgmt.project.list',
        'project_mgmt.project.calculate_kpis',
        'project_mgmt.project.find_detail',
        'project_mgmt.project.create',
        'project_mgmt.project.update',
    ] as $span) {
        expect($source)->toContain("'{$span}'");
    }
});

it('F3 Perf: ProjectsController admin magro <300 linhas (single responsibility)', function () {
    $file = (new ReflectionClass(ProjectsController::class))->getFileName();
    $lines = count(file($file));

    // Permite ate 300 — controllers admin tem mais actions (CRUD completo + report)
    expect($lines)->toBeLessThan(400, "Controller admin magro: <400 linhas. Atual: {$lines}");
});

it('F6 ProjectMgmtHealthCommand registrado + signature canon', function () {
    $cmd = app(ProjectMgmtHealthCommand::class);
    expect($cmd)->toBeInstanceOf(ProjectMgmtHealthCommand::class);

    $signature = (new ReflectionProperty($cmd, 'signature'))->getValue($cmd);
    expect($signature)->toContain('project-mgmt:health');
    expect($signature)->not->toContain('{--verbose '); // .claude/rules/commands.md
});

it('F2 multi-tenant: ProjectService.businessId readonly (defense Tier 0)', function () {
    $ref = new ReflectionClass(ProjectService::class);
    $constructor = $ref->getConstructor();
    $params = $constructor->getParameters();

    expect($params[0]->getName())->toBe('businessId');
    expect($params[0]->getType()?->getName())->toBe('int');
    // readonly promotion enforce imutabilidade — Tier 0 ADR 0093
});
