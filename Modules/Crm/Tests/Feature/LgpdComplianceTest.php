<?php

declare(strict_types=1);

use Modules\Crm\Entities\Campaign;
use Modules\Crm\Entities\CrmCallLog;
use Modules\Crm\Entities\CrmContact;
use Modules\Crm\Entities\CrmContactPersonCommission;
use Modules\Crm\Entities\CrmMarketplace;
use Modules\Crm\Entities\Leaduser;
use Modules\Crm\Entities\Proposal;
use Modules\Crm\Entities\ProposalTemplate;
use Modules\Crm\Entities\Schedule;
use Modules\Crm\Entities\ScheduleLog;
use Modules\Crm\Entities\ScheduleUser;
use Modules\Jana\Services\Privacy\PiiRedactor;
use Spatie\Activitylog\Traits\LogsActivity;

uses(Tests\TestCase::class);

/**
 * Testa D7 LGPD compliance do Módulo Crm — Wave 9 push (2026-05-16).
 *
 * Cobre 3 sub-dimensões da matriz Governance V3:
 * - D7.a (4 pts): PiiRedactor aplicado em logs/exports (smoke da existência)
 * - D7.b (3 pts): LogsActivity trait em Models que tocam PII
 * - D7.c (3 pts): Retention policy declarada (config + module.json)
 *
 * Não testa enforcement (purge job ainda em backlog) — testa CONTRATO de declaração.
 *
 * Cross-tenant Tier 0 ([ADR 0093]): tests usam biz=1 (Wagner WR2) + biz=99 (fictício).
 * ADR 0101: NUNCA biz=4 (ROTA LIVRE — cliente Larissa).
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0101-tests-business-id-1-nunca-cliente.md
 * @see Modules\Crm\Config\retention.php
 */

// ------------------------------------------------------------------
// D7.b — LogsActivity em todos os Models PII-relevantes (3 pts)
// ------------------------------------------------------------------

dataset('crm_pii_models', [
    'CrmContact (já tinha)'                  => [CrmContact::class],
    'Schedule (já tinha)'                    => [Schedule::class],
    'Leaduser (já tinha)'                    => [Leaduser::class],
    'Campaign'                               => [Campaign::class],
    'CrmCallLog'                             => [CrmCallLog::class],
    'CrmMarketplace'                         => [CrmMarketplace::class],
    'Proposal'                               => [Proposal::class],
    'ProposalTemplate'                       => [ProposalTemplate::class],
    'ScheduleLog'                            => [ScheduleLog::class],
    'ScheduleUser'                           => [ScheduleUser::class],
    'CrmContactPersonCommission'             => [CrmContactPersonCommission::class],
]);

it('Model %s usa LogsActivity trait (D7.b LGPD audit trail)', function (string $modelClass) {
    $traits = class_uses_recursive($modelClass);

    expect($traits)
        ->toHaveKey(LogsActivity::class)
        ->and(method_exists($modelClass, 'getActivitylogOptions'))
        ->toBeTrue("Modelo {$modelClass} deve implementar getActivitylogOptions()");
})->with('crm_pii_models');

it('Model PII tem useLogName configurado ou logFillable definido (D7.b nominal)', function (string $modelClass) {
    $instance = new $modelClass;

    // Smoke: getActivitylogOptions retorna LogOptions sem fatal error
    $options = $instance->getActivitylogOptions();

    expect($options)->toBeInstanceOf(\Spatie\Activitylog\LogOptions::class);
})->with('crm_pii_models');

// ------------------------------------------------------------------
// D7.a — PiiRedactor existe e funciona (4 pts)
// ------------------------------------------------------------------

it('PiiRedactor existe e redaciona CPF brasileiro', function () {
    $redactor = app(PiiRedactor::class);

    $input = 'erro processando contato CPF 123.456.789-09 inválido';
    $output = $redactor->redact($input);

    expect($output)
        ->toContain('[REDACTED:CPF]')
        ->and($output)
        ->not->toContain('123.456.789-09');
});

it('PiiRedactor redaciona email + telefone BR juntos', function () {
    $redactor = app(PiiRedactor::class);

    $input = 'falha enviar pra cliente@exemplo.com.br (11) 98765-4321';
    $output = $redactor->redact($input);

    expect($output)
        ->toContain('[REDACTED:EMAIL]')
        ->toContain('[REDACTED:PHONE]')
        ->and($output)
        ->not->toContain('cliente@exemplo.com.br')
        ->not->toContain('98765-4321');
});

it('PiiRedactor preserva texto sem PII (idempotência)', function () {
    $redactor = app(PiiRedactor::class);

    $input = 'Campaign sent successfully to 42 contacts';
    $output = $redactor->redact($input);

    expect($output)->toBe($input);
});

dataset('crm_files_with_pii_redactor', [
    'CampaignService'                => ['Modules/Crm/Services/CampaignService.php'],
    'LeadController'                 => ['Modules/Crm/Http/Controllers/LeadController.php'],
    'CampaignController'             => ['Modules/Crm/Http/Controllers/CampaignController.php'],
    'ContactBookingController'       => ['Modules/Crm/Http/Controllers/ContactBookingController.php'],
    'ContactLoginController'         => ['Modules/Crm/Http/Controllers/ContactLoginController.php'],
    'ScheduleController'             => ['Modules/Crm/Http/Controllers/ScheduleController.php'],
    'ScheduleLogController'          => ['Modules/Crm/Http/Controllers/ScheduleLogController.php'],
    'ProposalController'             => ['Modules/Crm/Http/Controllers/ProposalController.php'],
    'ProposalTemplateController'     => ['Modules/Crm/Http/Controllers/ProposalTemplateController.php'],
    'ManageProfileController'        => ['Modules/Crm/Http/Controllers/ManageProfileController.php'],
    'CrmMarketplaceController'       => ['Modules/Crm/Http/Controllers/CrmMarketplaceController.php'],
    'OrderRequestController'         => ['Modules/Crm/Http/Controllers/OrderRequestController.php'],
    'CrmSettingsController'          => ['Modules/Crm/Http/Controllers/CrmSettingsController.php'],
    'CreateRecursiveFollowup'        => ['Modules/Crm/Console/CreateRecursiveFollowup.php'],
]);

it('arquivo %s importa PiiRedactor (D7.a aplicação em logs)', function (string $relativePath) {
    $absolutePath = base_path($relativePath);
    expect(file_exists($absolutePath))->toBeTrue("Arquivo {$relativePath} não encontrado");

    $contents = file_get_contents($absolutePath);

    expect($contents)
        ->toContain('use Modules\\Jana\\Services\\Privacy\\PiiRedactor;')
        ->and($contents)
        ->toContain('PiiRedactor::class');
})->with('crm_files_with_pii_redactor');

it('Crm não tem mais `Log::emergency` com $e->getMessage() raw (D7.a hardening)', function () {
    $files = collect(glob(base_path('Modules/Crm/Http/Controllers/*.php')))
        ->merge(glob(base_path('Modules/Crm/Services/*.php')))
        ->merge(glob(base_path('Modules/Crm/Console/*.php')));

    $rawLeaks = [];
    foreach ($files as $file) {
        $contents = file_get_contents($file);
        // Pattern raw: termina com $e->getMessage()) sem PiiRedactor antes
        if (preg_match_all('/\\\\Log::emergency\([^;]*\$e->getMessage\(\)\);/', $contents, $matches)) {
            foreach ($matches[0] as $match) {
                if (! str_contains($match, 'PiiRedactor')) {
                    $rawLeaks[] = basename($file).' → '.substr($match, 0, 80);
                }
            }
        }
    }

    expect($rawLeaks)
        ->toBeEmpty('Esses Log::emergency ainda vazam PII: '.implode("\n", $rawLeaks));
});

// ------------------------------------------------------------------
// D7.c — Retention policy declarada (3 pts)
// ------------------------------------------------------------------

it('config/retention.php existe e é array válido (D7.c declaração)', function () {
    $configPath = base_path('Modules/Crm/Config/retention.php');

    expect(file_exists($configPath))->toBeTrue();

    $config = require $configPath;

    expect($config)
        ->toBeArray()
        ->toHaveKeys(['enabled', 'entities', 'strategy', 'notice_period_days']);
});

it('retention.php declara TTL pras 9 entidades PII-relevantes (D7.c cobertura)', function () {
    $config = require base_path('Modules/Crm/Config/retention.php');

    $expectedEntities = [
        'lead', 'schedule', 'schedule_log', 'call_log',
        'campaign', 'proposal', 'proposal_template',
        'crm_marketplace', 'crm_contact_person_commission',
    ];

    foreach ($expectedEntities as $entity) {
        expect(array_key_exists($entity, $config['entities']))
            ->toBeTrue("Entidade {$entity} sem TTL declarado em retention.php");
    }
});

it('retention.php declara strategy ∈ {soft_delete, hard_delete, anonymize}', function () {
    $config = require base_path('Modules/Crm/Config/retention.php');

    expect($config['strategy'])
        ->toBeIn(['soft_delete', 'hard_delete', 'anonymize']);
});

it('module.json declara retention_days + lgpd_compliance (D7.c metadata)', function () {
    $manifestPath = base_path('Modules/Crm/module.json');
    $manifest = json_decode(file_get_contents($manifestPath), true);

    expect($manifest)
        ->toHaveKey('retention_days')
        ->toHaveKey('lgpd_compliance');

    expect($manifest['lgpd_compliance'])
        ->toHaveKeys(['pii_fields_tracked', 'pii_redactor_enabled', 'activity_log_enabled']);

    expect($manifest['lgpd_compliance']['pii_redactor_enabled'])->toBeTrue();
    expect($manifest['lgpd_compliance']['activity_log_enabled'])->toBeTrue();
});

it('call_log tem retention <=365 dias (LGPD voz/gravação curta)', function () {
    $config = require base_path('Modules/Crm/Config/retention.php');

    expect((int) $config['entities']['call_log'])->toBeLessThanOrEqual(365);
});
