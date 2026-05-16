<?php

declare(strict_types=1);

use Modules\Jana\Services\Privacy\PiiRedactor;
use Modules\RecurringBilling\Models\BoletoCredential;
use Modules\RecurringBilling\Models\ChargeAttempt;
use Modules\RecurringBilling\Models\Invoice;
use Modules\RecurringBilling\Models\Plan;
use Modules\RecurringBilling\Models\Subscription;
use Spatie\Activitylog\Traits\LogsActivity;

uses(Tests\TestCase::class);

/**
 * Testa D7 LGPD compliance do Módulo RecurringBilling — Wave 14 push (2026-05-16).
 *
 * Cobre 3 sub-dimensões da matriz Governance V3:
 * - D7.a (4 pts): PiiRedactor aplicado em webhooks de gateway
 * - D7.b (3 pts): LogsActivity trait em Models que tocam PII (5 Models)
 * - D7.c (3 pts): Retention policy declarada (config + module.json)
 *
 * Não testa enforcement (purge job ainda em backlog) — testa CONTRATO de declaração.
 *
 * Cross-tenant Tier 0 ([ADR 0093]): tests usam fixtures isoladas — sem biz=4 (ROTA LIVRE).
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0101-tests-business-id-1-nunca-cliente.md
 * @see Modules\RecurringBilling\Config\retention.php
 * @see Modules\Crm\Tests\Feature\LgpdComplianceTest.php (módulo referência)
 */

// ------------------------------------------------------------------
// D7.b — LogsActivity em todos os 5 Models PII-relevantes (3 pts)
// ------------------------------------------------------------------

dataset('rb_pii_models', [
    'Subscription'      => [Subscription::class],
    'Invoice'           => [Invoice::class],
    'ChargeAttempt'     => [ChargeAttempt::class],
    'Plan'              => [Plan::class],
    'BoletoCredential'  => [BoletoCredential::class],
]);

it('Model %s usa LogsActivity trait (D7.b LGPD audit trail)', function (string $modelClass) {
    $traits = class_uses_recursive($modelClass);

    expect($traits)
        ->toHaveKey(LogsActivity::class)
        ->and(method_exists($modelClass, 'getActivitylogOptions'))
        ->toBeTrue("Modelo {$modelClass} deve implementar getActivitylogOptions()");
})->with('rb_pii_models');

it('Model %s retorna LogOptions válido (D7.b nominal)', function (string $modelClass) {
    $instance = new $modelClass;

    $options = $instance->getActivitylogOptions();

    expect($options)->toBeInstanceOf(\Spatie\Activitylog\LogOptions::class);
})->with('rb_pii_models');

// ------------------------------------------------------------------
// D7.a — PiiRedactor existe e funciona + aplicado nos webhooks (4 pts)
// ------------------------------------------------------------------

it('PiiRedactor existe e redaciona CPF/CNPJ brasileiro', function () {
    $redactor = app(PiiRedactor::class);

    $input = 'pagador CPF 123.456.789-09 emitiu pix';
    $output = $redactor->redact($input);

    expect($output)
        ->toContain('[REDACTED:CPF]')
        ->and($output)
        ->not->toContain('123.456.789-09');
});

it('PiiRedactor redaciona email + telefone BR juntos (webhook payload típico)', function () {
    $redactor = app(PiiRedactor::class);

    $input = 'webhook asaas pagador@cliente.com.br (11) 98765-4321 valor R$ 99,90';
    $output = $redactor->redact($input);

    expect($output)
        ->toContain('[REDACTED:EMAIL]')
        ->toContain('[REDACTED:PHONE]')
        ->and($output)
        ->not->toContain('pagador@cliente.com.br')
        ->not->toContain('98765-4321');
});

it('PiiRedactor preserva texto sem PII (idempotência)', function () {
    $redactor = app(PiiRedactor::class);

    $input = 'subscription 42 paid successfully';
    $output = $redactor->redact($input);

    expect($output)->toBe($input);
});

dataset('rb_files_with_pii_redactor', [
    'AsaasWebhookController' => ['Modules/RecurringBilling/Http/Controllers/AsaasWebhookController.php'],
    'InterWebhookController' => ['Modules/RecurringBilling/Http/Controllers/InterWebhookController.php'],
]);

it('arquivo %s importa PiiRedactor (D7.a aplicação em webhooks de gateway)', function (string $relativePath) {
    $absolutePath = base_path($relativePath);
    expect(file_exists($absolutePath))->toBeTrue("Arquivo {$relativePath} não encontrado");

    $contents = file_get_contents($absolutePath);

    expect($contents)
        ->toContain('use Modules\\Jana\\Services\\Privacy\\PiiRedactor;')
        ->and($contents)
        ->toContain('PiiRedactor::class');
})->with('rb_files_with_pii_redactor');

it('RecurringBilling não tem `Log::*` com payload bruto sem PiiRedactor (D7.a hardening)', function () {
    $files = collect(glob(base_path('Modules/RecurringBilling/Http/Controllers/*.php')))
        ->merge(glob(base_path('Modules/RecurringBilling/Services/*.php')));

    $rawLeaks = [];
    foreach ($files as $file) {
        $contents = file_get_contents($file);
        // Pattern raw: termina com $e->getMessage()) sem PiiRedactor antes
        if (preg_match_all('/\\\\?Log::(emergency|error|warning|info)\([^;]*\$e->getMessage\(\)\);/', $contents, $matches)) {
            foreach ($matches[0] as $match) {
                if (! str_contains($match, 'PiiRedactor') && ! str_contains($match, '[REDACTED]')) {
                    $rawLeaks[] = basename($file).' → '.substr($match, 0, 80);
                }
            }
        }
    }

    expect($rawLeaks)
        ->toBeEmpty('Esses Log::* ainda vazam PII raw: '.implode("\n", $rawLeaks));
});

// ------------------------------------------------------------------
// D7.c — Retention policy declarada (3 pts)
// ------------------------------------------------------------------

it('config/retention.php existe e é array válido (D7.c declaração)', function () {
    $configPath = base_path('Modules/RecurringBilling/Config/retention.php');

    expect(file_exists($configPath))->toBeTrue();

    $config = require $configPath;

    expect($config)
        ->toBeArray()
        ->toHaveKeys(['enabled', 'entities', 'strategy', 'notice_period_days']);
});

it('retention.php declara TTL pras 8 entidades (D7.c cobertura)', function () {
    $config = require base_path('Modules/RecurringBilling/Config/retention.php');

    $expectedEntities = [
        'subscription', 'invoice', 'charge_attempt', 'plan',
        'boleto_credential', 'subscription_event', 'subscription_note',
        'subscription_favorite',
    ];

    foreach ($expectedEntities as $entity) {
        expect(array_key_exists($entity, $config['entities']))
            ->toBeTrue("Entidade {$entity} sem TTL declarado em retention.php");
    }
});

it('retention.php declara strategy ∈ {soft_delete, hard_delete, anonymize}', function () {
    $config = require base_path('Modules/RecurringBilling/Config/retention.php');

    expect($config['strategy'])
        ->toBeIn(['soft_delete', 'hard_delete', 'anonymize']);
});

it('module.json declara retention_days + lgpd_compliance (D7.c metadata)', function () {
    $manifestPath = base_path('Modules/RecurringBilling/module.json');
    $manifest = json_decode(file_get_contents($manifestPath), true);

    expect($manifest)
        ->toHaveKey('retention_days')
        ->toHaveKey('lgpd_compliance');

    expect($manifest['lgpd_compliance'])
        ->toHaveKeys(['pii_fields_tracked', 'pii_redactor_enabled', 'activity_log_enabled']);

    expect($manifest['lgpd_compliance']['pii_redactor_enabled'])->toBeTrue();
    expect($manifest['lgpd_compliance']['activity_log_enabled'])->toBeTrue();
});

it('subscription/invoice tem retention >=1825 dias (Código Civil Art. 206 §5 III)', function () {
    $config = require base_path('Modules/RecurringBilling/Config/retention.php');

    expect((int) $config['entities']['subscription'])->toBeGreaterThanOrEqual(1825);
    expect((int) $config['entities']['invoice'])->toBeGreaterThanOrEqual(1825);
});

it('append-only entities tem TTL null (preserva audit financeiro)', function () {
    $config = require base_path('Modules/RecurringBilling/Config/retention.php');

    expect($config['entities']['charge_attempt'])->toBeNull();
    expect($config['entities']['subscription_event'])->toBeNull();
});
