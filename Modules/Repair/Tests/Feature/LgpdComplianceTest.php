<?php

declare(strict_types=1);

use Modules\Jana\Services\Privacy\PiiRedactor;

uses(Tests\TestCase::class);

/**
 * Testa D7 LGPD compliance do Módulo Repair — Wave 17 push (2026-05-16).
 *
 * Cobre 3 sub-dimensões da matriz Governance V3:
 * - D7.a (4 pts): PiiRedactor aplicado em logs de exception (RepairFsmActionController)
 * - D7.b (3 pts): Spatie ActivityLog em Entities sensíveis (JobSheet, DeviceModel, RepairStatus)
 *                 + sale_stage_history FSM canon (ADR 0143)
 * - D7.c (3 pts): module.json declara lgpd_compliance + retention_days/retention_config
 *
 * Não testa enforcement (purge job ainda em backlog) — testa CONTRATO de declaração
 * e presença dos hooks de redaction nos pontos críticos.
 *
 * Cross-tenant Tier 0 ([ADR 0093]): smoke usa biz=1 (Wagner WR2) + biz=99 (fictício).
 * ADR 0101: NUNCA biz=4 (ROTA LIVRE — cliente Larissa).
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0101-tests-business-id-1-nunca-cliente.md
 * @see memory/decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md
 */

// ------------------------------------------------------------------
// D7.a — PiiRedactor wrap nos Controllers que logam exception (4 pts)
// ------------------------------------------------------------------

it('PiiRedactor é resolvível via container Laravel (sanity)', function () {
    $redactor = app(PiiRedactor::class);

    expect($redactor)->toBeInstanceOf(PiiRedactor::class);
});

it('PiiRedactor redaciona CPF brasileiro em mensagem de exception Repair', function () {
    $redactor = app(PiiRedactor::class);

    $input = 'falha update job_sheet contact CPF 123.456.789-09 device defeito X';
    $output = $redactor->redact($input);

    expect($output)
        ->toContain('[REDACTED:CPF]')
        ->and($output)
        ->not->toContain('123.456.789-09');
});

it('PiiRedactor redaciona email + telefone do cliente Repair', function () {
    $redactor = app(PiiRedactor::class);

    $input = 'OS#42 contact cliente@exemplo.com.br whatsapp (11) 98765-4321 prazo entrega';
    $output = $redactor->redact($input);

    expect($output)
        ->toContain('[REDACTED:EMAIL]')
        ->toContain('[REDACTED:PHONE]')
        ->and($output)
        ->not->toContain('cliente@exemplo.com.br')
        ->not->toContain('98765-4321');
});

dataset('repair_controllers_with_pii_redactor', [
    'RepairFsmActionController' => ['Modules/Repair/Http/Controllers/RepairFsmActionController.php'],
]);

it('controller %s importa PiiRedactor (D7.a aplicação em logs exception)', function (string $relativePath) {
    $absolutePath = base_path($relativePath);
    expect(file_exists($absolutePath))->toBeTrue("Arquivo {$relativePath} não encontrado");

    $contents = file_get_contents($absolutePath);

    expect($contents)
        ->toContain('use Modules\\Jana\\Services\\Privacy\\PiiRedactor;')
        ->and($contents)
        ->toContain('PiiRedactor::class');
})->with('repair_controllers_with_pii_redactor');

it('Repair Controllers não vazam $e->getMessage() raw em Log:: sem PiiRedactor (D7.a hardening)', function () {
    $files = collect(glob(base_path('Modules/Repair/Http/Controllers/*.php')));

    $rawLeaks = [];
    foreach ($files as $file) {
        $contents = file_get_contents($file);
        // Heurística: Log::*( ... $e->getMessage() ... ) sem PiiRedactor importado no mesmo arquivo
        if (preg_match('/Log::[a-z]+\([^;]*\$e->getMessage\(\)/i', $contents)
            && ! str_contains($contents, 'PiiRedactor')) {
            $rawLeaks[] = basename($file);
        }
    }

    expect($rawLeaks)
        ->toBeEmpty('Controllers Repair ainda vazam $e->getMessage() raw: '.implode(', ', $rawLeaks));
});

// ------------------------------------------------------------------
// D7.b — Audit trail via Spatie ActivityLog (3 pts)
// ------------------------------------------------------------------

dataset('repair_entities_with_logs_activity', [
    'JobSheet'     => ['Modules/Repair/Entities/JobSheet.php'],
    'DeviceModel'  => ['Modules/Repair/Entities/DeviceModel.php'],
    'RepairStatus' => ['Modules/Repair/Entities/RepairStatus.php'],
]);

it('entity %s usa Spatie LogsActivity trait (D7.b audit trail)', function (string $relativePath) {
    $absolutePath = base_path($relativePath);
    expect(file_exists($absolutePath))->toBeTrue("Arquivo {$relativePath} não encontrado");

    $contents = file_get_contents($absolutePath);

    expect($contents)
        ->toContain('use Spatie\\Activitylog\\Traits\\LogsActivity;')
        ->and($contents)
        ->toContain('use LogsActivity')
        ->and($contents)
        ->toContain('getActivitylogOptions');
})->with('repair_entities_with_logs_activity');

it('JobSheet declara logOnly() com whitelist (D7.b — sem PII livre)', function () {
    $contents = file_get_contents(base_path('Modules/Repair/Entities/JobSheet.php'));

    expect($contents)
        ->toContain("->logOnly([")
        ->toContain("'status_id'")
        ->toContain("->logOnlyDirty()")
        ->toContain("->dontSubmitEmptyLogs()");

    // Whitelist NÃO inclui colunas com PII livre (contact_id é FK ok, mas defects é texto)
    // defects é incluído porque é diagnóstico operacional — não PII estruturada.
    expect($contents)->not->toContain("'notes'"); // notes livre não está na whitelist
});

it('module.json declara estratégia de audit trail (D7.b transparência)', function () {
    $manifestPath = base_path('Modules/Repair/module.json');
    $manifest = json_decode(file_get_contents($manifestPath), true);

    expect($manifest)
        ->toHaveKey('lgpd_compliance');

    expect($manifest['lgpd_compliance'])
        ->toHaveKeys(['pii_fields_tracked', 'pii_redactor_enabled', 'activity_log_enabled']);

    expect($manifest['lgpd_compliance']['pii_redactor_enabled'])->toBeTrue();
    expect($manifest['lgpd_compliance']['activity_log_enabled'])->toBeTrue();
});

it('sale_stage_history (FSM canon) está disponível pra audit JobSheet (D7.b complemento)', function () {
    if (\DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema MySQL UltimatePOS');
    }
    if (! \Schema::hasTable('sale_stage_history')) {
        $this->markTestSkipped('Tabela sale_stage_history ausente — rode migrate FSM canon ADR 0143');
    }

    expect(\Schema::hasColumn('sale_stage_history', 'subject_id'))->toBeTrue();
    expect(\Schema::hasColumn('sale_stage_history', 'subject_type'))->toBeTrue();
    expect(\Schema::hasColumn('sale_stage_history', 'business_id'))->toBeTrue();
});

// ------------------------------------------------------------------
// D7.c — Retention policy declarada via module.json (3 pts)
// ------------------------------------------------------------------

it('module.json declara retention_days + retention_config (D7.c metadata)', function () {
    $manifestPath = base_path('Modules/Repair/module.json');
    $manifest = json_decode(file_get_contents($manifestPath), true);

    expect($manifest)
        ->toHaveKey('retention_days')
        ->toHaveKey('retention_config')
        ->toHaveKey('lgpd_compliance');

    expect($manifest['retention_config'])->toBe('Modules/Repair/Config/retention.php');

    // OS Repair tem retenção fiscal mais longa (CC Art. 206 — prescrição 5 anos
    // pra cobrança trabalho realizado + LGPD Art. 16 finalidade legítima)
    expect((int) $manifest['retention_days'])->toBeGreaterThanOrEqual(1825); // ≥5 anos
});

// ------------------------------------------------------------------
// Cross-tenant Tier 0 smoke (ADR 0093) — defesa em profundidade
// ------------------------------------------------------------------

it('JobSheet usa HasBusinessScope trait (Tier 0 multi-tenant)', function () {
    $contents = file_get_contents(base_path('Modules/Repair/Entities/JobSheet.php'));

    expect($contents)
        ->toContain('use App\\Concerns\\HasBusinessScope;')
        ->toContain('use HasBusinessScope');
});

it('DeviceModel usa HasBusinessScope trait (Tier 0 multi-tenant)', function () {
    $contents = file_get_contents(base_path('Modules/Repair/Entities/DeviceModel.php'));

    expect($contents)
        ->toContain('use App\\Concerns\\HasBusinessScope;')
        ->toContain('use HasBusinessScope');
});
