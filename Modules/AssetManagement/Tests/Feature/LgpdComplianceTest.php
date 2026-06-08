<?php

declare(strict_types=1);

use Modules\AssetManagement\Entities\Asset;
use Modules\AssetManagement\Entities\AssetMaintenance;
use Modules\AssetManagement\Entities\AssetTransaction;
use Modules\AssetManagement\Entities\AssetWarranty;
use Modules\Jana\Services\Privacy\PiiRedactor;
use Spatie\Activitylog\Traits\LogsActivity;

uses(Tests\TestCase::class);

/**
 * Testa D7 LGPD compliance do Módulo AssetManagement — Wave 15 push (2026-05-16).
 *
 * Cobre 3 sub-dimensões da matriz Governance V3:
 * - D7.a (4 pts): PiiRedactor aplicado em logs/exports (smoke da existência)
 * - D7.b (3 pts): LogsActivity trait em Entities patrimoniais
 * - D7.c (3 pts): Retention policy declarada (config + module.json)
 *
 * Não testa enforcement (purge job ainda em backlog) — testa CONTRATO de declaração.
 *
 * Cross-tenant Tier 0 ([ADR 0093]): tests usam biz=1 (Wagner WR2) + biz=99 (fictício).
 * ADR 0101: NUNCA biz=4 (ROTA LIVRE — cliente Larissa).
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0101-tests-business-id-1-nunca-cliente.md
 * @see Modules\AssetManagement\Config\retention.php
 */

// ------------------------------------------------------------------
// D7.b — LogsActivity em todas as Entities patrimoniais (3 pts)
// ------------------------------------------------------------------

dataset('assetmanagement_entities', [
    'Asset'             => [Asset::class],
    'AssetMaintenance'  => [AssetMaintenance::class],
    'AssetTransaction'  => [AssetTransaction::class],
    'AssetWarranty'     => [AssetWarranty::class],
]);

it('Entity %s usa LogsActivity trait (D7.b LGPD audit trail)', function (string $modelClass) {
    $traits = class_uses_recursive($modelClass);

    expect($traits)
        ->toHaveKey(LogsActivity::class)
        ->and(method_exists($modelClass, 'getActivitylogOptions'))
        ->toBeTrue("Entity {$modelClass} deve implementar getActivitylogOptions()");
})->with('assetmanagement_entities');

it('Entity %s retorna LogOptions válido (D7.b nominal)', function (string $modelClass) {
    $instance = new $modelClass;

    $options = $instance->getActivitylogOptions();

    expect($options)->toBeInstanceOf(\Spatie\Activitylog\LogOptions::class);
})->with('assetmanagement_entities');

// ------------------------------------------------------------------
// D7.a — PiiRedactor existe e funciona (4 pts)
// ------------------------------------------------------------------

it('PiiRedactor existe e redaciona CPF brasileiro', function () {
    $redactor = app(PiiRedactor::class);

    $input = 'erro processando alocacao asset CPF 123.456.789-09 inválido';
    $output = $redactor->redact($input);

    expect($output)
        ->toContain('[REDACTED:CPF]')
        ->and($output)
        ->not->toContain('123.456.789-09');
});

it('PiiRedactor redaciona email + telefone BR juntos', function () {
    $redactor = app(PiiRedactor::class);

    $input = 'falha notificar tecnico@oficina.com.br (11) 98765-4321';
    $output = $redactor->redact($input);

    expect($output)
        ->toContain('[REDACTED:EMAIL]')
        ->toContain('[REDACTED:PHONE]')
        ->and($output)
        ->not->toContain('tecnico@oficina.com.br')
        ->not->toContain('98765-4321');
});

it('PiiRedactor preserva texto sem PII (idempotência)', function () {
    $redactor = app(PiiRedactor::class);

    $input = 'Asset alocado com sucesso (qty=3)';
    $output = $redactor->redact($input);

    expect($output)->toBe($input);
});

dataset('assetmanagement_controllers', [
    'AssetController'              => ['Modules/AssetManagement/Http/Controllers/AssetController.php'],
    'AssetSettingsController'      => ['Modules/AssetManagement/Http/Controllers/AssetSettingsController.php'],
    'AssetAllocationController'    => ['Modules/AssetManagement/Http/Controllers/AssetAllocationController.php'],
    'AssetMaitenanceController'    => ['Modules/AssetManagement/Http/Controllers/AssetMaitenanceController.php'],
    'RevokeAllocatedAssetController' => ['Modules/AssetManagement/Http/Controllers/RevokeAllocatedAssetController.php'],
]);

it('Controller %s aplica PiiRedactor em Log::emergency (D7.a hardening)', function (string $relativePath) {
    $absolutePath = base_path($relativePath);
    expect(file_exists($absolutePath))->toBeTrue("Arquivo {$relativePath} não encontrado");

    $contents = file_get_contents($absolutePath);

    expect($contents)->toContain('PiiRedactor');
})->with('assetmanagement_controllers');

it('AssetManagement não tem mais `Log::emergency` raw com $e->getMessage() (D7.a hardening)', function () {
    $files = glob(base_path('Modules/AssetManagement/Http/Controllers/*.php'));

    $rawLeaks = [];
    foreach ($files as $file) {
        $contents = file_get_contents($file);
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
    $configPath = base_path('Modules/AssetManagement/Config/retention.php');

    expect(file_exists($configPath))->toBeTrue();

    $config = require $configPath;

    expect($config)
        ->toBeArray()
        ->toHaveKeys(['enabled', 'entities', 'strategy', 'notice_period_days']);
});

it('retention.php declara TTL pras 4 entidades patrimoniais (D7.c cobertura)', function () {
    $config = require base_path('Modules/AssetManagement/Config/retention.php');

    $expectedEntities = [
        'am_assets',
        'am_asset_transactions',
        'am_maintenance_logs',
        'am_warranties',
    ];

    foreach ($expectedEntities as $entity) {
        expect(array_key_exists($entity, $config['entities']))
            ->toBeTrue("Entidade {$entity} sem TTL declarado em retention.php");
    }
});

it('retention.php declara strategy ∈ {soft_delete, hard_delete, anonymize}', function () {
    $config = require base_path('Modules/AssetManagement/Config/retention.php');

    expect($config['strategy'])
        ->toBeIn(['soft_delete', 'hard_delete', 'anonymize']);
});

it('am_assets tem retention >=3650 dias (10 anos fiscal/contábil)', function () {
    $config = require base_path('Modules/AssetManagement/Config/retention.php');

    expect((int) $config['entities']['am_assets'])->toBeGreaterThanOrEqual(3650);
});

it('module.json declara retention_days + lgpd_compliance (D7.c metadata)', function () {
    $manifestPath = base_path('Modules/AssetManagement/module.json');
    $manifest = json_decode(file_get_contents($manifestPath), true);

    expect($manifest)
        ->toHaveKey('retention_days')
        ->toHaveKey('lgpd_compliance');

    expect($manifest['lgpd_compliance'])
        ->toHaveKeys(['pii_fields_tracked', 'pii_redactor_enabled', 'activity_log_enabled']);

    expect($manifest['lgpd_compliance']['pii_redactor_enabled'])->toBeTrue();
    expect($manifest['lgpd_compliance']['activity_log_enabled'])->toBeTrue();
});
