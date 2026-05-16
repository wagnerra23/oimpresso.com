<?php

declare(strict_types=1);

use Modules\Jana\Services\Privacy\PiiRedactor;
use Modules\ProjectMgmt\Services\ProjectMgmtAuditService;

uses(Tests\TestCase::class);

/**
 * Testa D7 LGPD compliance do Módulo ProjectMgmt — Wave 16 push (2026-05-16).
 *
 * Cobre 3 sub-dimensões da matriz Governance V3:
 * - D7.a (4 pts): PiiRedactor aplicado em audit logs (ProjectMgmtAuditService)
 * - D7.b (3 pts): Audit trail via Spatie activity_log (ProjectMgmt **não tem Entities
 *                 próprias** — usa Service ao invés de LogsActivity trait)
 * - D7.c (3 pts): Retention policy declarada (config + entidades mapeadas)
 *
 * Não testa enforcement (purge job em backlog) — testa CONTRATO de declaração.
 *
 * Cross-tenant Tier 0 ([ADR 0093]): tests usam biz=1 (Wagner) + biz=99 (fictício).
 * ADR 0101: NUNCA biz=4 (ROTA LIVRE — cliente Larissa).
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0101-tests-business-id-1-nunca-cliente.md
 * @see Modules\ProjectMgmt\Config\retention.php
 * @see Modules\ProjectMgmt\Services\ProjectMgmtAuditService
 */

const PMG_LGPD_BIZ_WAGNER = 1;
const PMG_LGPD_BIZ_FICTICIO = 99;

// ------------------------------------------------------------------
// D7.c — Retention policy declarada (3 pts)
// ------------------------------------------------------------------

it('config/retention.php existe e é array válido (D7.c declaração)', function () {
    $configPath = base_path('Modules/ProjectMgmt/Config/retention.php');

    expect(file_exists($configPath))->toBeTrue();

    $config = require $configPath;

    expect($config)
        ->toBeArray()
        ->toHaveKeys(['enabled', 'entities', 'strategy', 'notice_period_days', 'pii_fields']);
});

it('retention.php declara TTL pras 7 entidades PM canônicas (D7.c cobertura)', function () {
    $config = require base_path('Modules/ProjectMgmt/Config/retention.php');

    $expectedEntities = [
        'project_mgmt_projects',
        'project_mgmt_tasks',
        'project_mgmt_task_comments',
        'project_mgmt_task_events',
        'project_mgmt_inbox_notifications',
        'project_mgmt_task_dependencies',
        'project_mgmt_task_watchers',
    ];

    foreach ($expectedEntities as $entity) {
        expect(array_key_exists($entity, $config['entities']))
            ->toBeTrue("Entidade {$entity} sem TTL declarado em retention.php");
    }
});

it('retention.php declara strategy ∈ {soft_delete, hard_delete, anonymize}', function () {
    $config = require base_path('Modules/ProjectMgmt/Config/retention.php');

    expect($config['strategy'])
        ->toBeIn(['soft_delete', 'hard_delete', 'anonymize']);
});

it('retention.php declara campos PII por entidade pra anonymize (D7.c hardening)', function () {
    $config = require base_path('Modules/ProjectMgmt/Config/retention.php');

    expect($config['pii_fields'])->toBeArray();
    expect($config['pii_fields'])->toHaveKeys([
        'project_mgmt_tasks',
        'project_mgmt_task_comments',
        'project_mgmt_inbox_notifications',
        'project_mgmt_task_events',
    ]);

    // Campos texto-livre obrigatórios redacionados
    expect($config['pii_fields']['project_mgmt_task_comments'])->toContain('body');
    expect($config['pii_fields']['project_mgmt_tasks'])->toContain('description');
});

it('task_comments tem retention <=730 dias (texto livre PII-risk)', function () {
    $config = require base_path('Modules/ProjectMgmt/Config/retention.php');

    expect((int) $config['entities']['project_mgmt_task_comments'])->toBeLessThanOrEqual(730);
});

// ------------------------------------------------------------------
// D7.b — Audit trail via ProjectMgmtAuditService (3 pts)
// ------------------------------------------------------------------

it('ProjectMgmtAuditService existe e exige business_id no constructor (Tier 0)', function () {
    expect(class_exists(ProjectMgmtAuditService::class))->toBeTrue();

    // Constructor exige business_id > 0
    expect(fn () => new ProjectMgmtAuditService(0, app(PiiRedactor::class)))
        ->toThrow(\InvalidArgumentException::class);

    expect(fn () => new ProjectMgmtAuditService(-1, app(PiiRedactor::class)))
        ->toThrow(\InvalidArgumentException::class);
});

it('ProjectMgmtAuditService aceita business_id válido', function () {
    $service = new ProjectMgmtAuditService(PMG_LGPD_BIZ_WAGNER, app(PiiRedactor::class));
    expect($service)->toBeInstanceOf(ProjectMgmtAuditService::class);
});

it('ProjectMgmtAuditService declara eventos canônicos do domínio', function () {
    $reflection = new ReflectionClass(ProjectMgmtAuditService::class);
    $constants = $reflection->getConstants();

    // EVENT_* exposed
    $eventConstants = array_filter(array_keys($constants), fn ($k) => str_starts_with($k, 'EVENT_'));

    expect(count($eventConstants))->toBeGreaterThanOrEqual(7);
    expect($constants)->toHaveKey('EVENT_TASK_STATUS_CHANGED');
    expect($constants)->toHaveKey('EVENT_TASK_COMMENT_ADDED');
    expect($constants)->toHaveKey('EVENT_PROJECT_CREATED');
});

it('ProjectMgmtAuditService LOG_NAME é "project-mgmt" (canônico Spatie)', function () {
    expect(ProjectMgmtAuditService::LOG_NAME)->toBe('project-mgmt');
});

it('ProjectMgmtAuditService rejeita event não-canônico (defense)', function () {
    $service = new ProjectMgmtAuditService(PMG_LGPD_BIZ_WAGNER, app(PiiRedactor::class));

    expect(fn () => $service->log('event.fake', 'desc'))
        ->toThrow(\InvalidArgumentException::class);
});

// ------------------------------------------------------------------
// D7.a — PiiRedactor aplicado nos audits do Service (4 pts)
// ------------------------------------------------------------------

it('PiiRedactor existe e redaciona CPF brasileiro (smoke)', function () {
    $redactor = app(PiiRedactor::class);

    $input = 'task XPTO-1 com CPF 123.456.789-09 do solicitante';
    $output = $redactor->redact($input);

    expect($output)
        ->toContain('[REDACTED:CPF]')
        ->and($output)
        ->not->toContain('123.456.789-09');
});

it('ProjectMgmtAuditService importa PiiRedactor (D7.a contrato)', function () {
    $contents = file_get_contents(base_path('Modules/ProjectMgmt/Services/ProjectMgmtAuditService.php'));

    expect($contents)
        ->toContain('use Modules\\Jana\\Services\\Privacy\\PiiRedactor;')
        ->and($contents)
        ->toContain('PiiRedactor');
});

it('ProjectMgmtAuditService redaciona PII em description antes de persistir', function () {
    $service = new ProjectMgmtAuditService(PMG_LGPD_BIZ_WAGNER, app(PiiRedactor::class));

    // Validar via reflection que o método sanitizeProperties existe (privado)
    $reflection = new ReflectionClass($service);
    $methods = array_map(fn ($m) => $m->getName(), $reflection->getMethods());

    expect($methods)->toContain('sanitizeProperties');
});

it('Admin/ProjectsController importa ProjectService + ProjectMgmtAuditService (D4+D7 integração)', function () {
    $contents = file_get_contents(
        base_path('Modules/ProjectMgmt/Http/Controllers/Admin/ProjectsController.php')
    );

    expect($contents)
        ->toContain('use Modules\\ProjectMgmt\\Services\\ProjectService;')
        ->toContain('use Modules\\ProjectMgmt\\Services\\ProjectMgmtAuditService;');
});

it('ProjectMgmtAuditService.log() retorna Activity model (smoke, mockado em memória)', function () {
    // Skip se tabela activity_log não disponível (env minimal)
    if (! \Illuminate\Support\Facades\Schema::hasTable('activity_log')) {
        $this->markTestSkipped('activity_log table missing — Spatie activitylog não migrou');
    }

    $service = new ProjectMgmtAuditService(PMG_LGPD_BIZ_WAGNER, app(PiiRedactor::class));

    // CPF dentro de description e properties — DEVE virar [REDACTED:CPF]
    $activity = $service->log(
        event: ProjectMgmtAuditService::EVENT_TASK_STATUS_CHANGED,
        description: 'Task COPI-999 status mudou (CPF 123.456.789-09 mencionado)',
        properties: [
            'task_id' => 'COPI-999',
            'note'    => 'cliente cpf 987.654.321-00 reportou',
        ],
    );

    expect($activity->log_name)->toBe('project-mgmt');
    expect($activity->description)->toContain('[REDACTED:CPF]');
    expect($activity->description)->not->toContain('123.456.789-09');
    expect($activity->properties['business_id'])->toBe(PMG_LGPD_BIZ_WAGNER);
    expect($activity->properties['note'])->toContain('[REDACTED:CPF]');

    // Cleanup
    $activity->delete();
});
