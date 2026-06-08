<?php

declare(strict_types=1);

use Modules\SRS\Entities\DocEvidence;
use Modules\SRS\Entities\DocRequirement;
use Modules\SRS\Services\MemoryReader;
use Modules\SRS\Services\ModuleAuditor;

uses(Tests\TestCase::class);

/**
 * Wave 27 POLISH SRS — push 85 → ≥88.
 *
 * Cobre dimensoes restantes:
 *
 *   D1.A — Entities trait expand: DocRequirement + DocEvidence ganham LogsActivity
 *          (paridade com DocSource/DocChatMessage). Audit trail completo nas
 *          4 entities tenant-scoped.
 *
 *   D9.A — Spans canon expand: ModuleAuditor.audit + MemoryReader.listRoots
 *          ganham OtelHelper::spanBiz canon (paridade com ChatAssistant.ask +
 *          DocValidator.validate). 4/6 services principais agora instrumentados.
 *
 *   D7.A — LGPD push final: retention.php Wave 27 adiciona `base_legal` +
 *          `notice_period_days` + `hierarquia` + `strategy` + `entities` mapping.
 *          Audit fiscal-ready (Wagner pode mostrar compliance LGPD per-rotina).
 *
 * Tier 0 IRREVOGAVEL (ADR 0093):
 *   - LogsActivity nao quebra HasBusinessScope (traits independentes)
 *   - Spans canon NAO vazam business_id em rota repo-wide (SRS sem session externa)
 *   - retention.php declarativo — Wagner valida ANTES de purge automatico
 *
 * SQLite-friendly source-level (sem DB hits).
 *
 * @see Modules/SRS/Entities/DocRequirement.php (D1 Wave 27 LogsActivity)
 * @see Modules/SRS/Entities/DocEvidence.php (D1 Wave 27 LogsActivity)
 * @see Modules/SRS/Services/ModuleAuditor.php (D9 Wave 27 spanBiz)
 * @see Modules/SRS/Services/MemoryReader.php (D9 Wave 27 spanBiz)
 * @see Modules/SRS/Config/retention.php (D7 Wave 27 base_legal + hierarquia)
 */

// ============================================================================
// D1.A — Entities trait expand (LogsActivity em DocRequirement + DocEvidence)
// ============================================================================

it('D1.A DocRequirement usa LogsActivity trait (audit trail Wave 27 LGPD)', function () {
    expect(class_uses_recursive(DocRequirement::class))
        ->toContain(\Spatie\Activitylog\Traits\LogsActivity::class);
});

it('D1.A DocRequirement declara getActivitylogOptions() method (Spatie contract)', function () {
    expect(method_exists(DocRequirement::class, 'getActivitylogOptions'))->toBeTrue();
});

it('D1.A DocRequirement preserva HasBusinessScope (Wave 12 NAO quebrado)', function () {
    expect(class_uses_recursive(DocRequirement::class))
        ->toContain(\App\Concerns\HasBusinessScope::class);
});

it('D1.A DocEvidence usa LogsActivity trait (audit trail Wave 27)', function () {
    expect(class_uses_recursive(DocEvidence::class))
        ->toContain(\Spatie\Activitylog\Traits\LogsActivity::class);
});

it('D1.A DocEvidence declara getActivitylogOptions() method', function () {
    expect(method_exists(DocEvidence::class, 'getActivitylogOptions'))->toBeTrue();
});

it('D1.A DocEvidence preserva HasBusinessScope (Wave 12) + Searchable (Wave 16)', function () {
    $traits = class_uses_recursive(DocEvidence::class);
    expect($traits)->toContain(\App\Concerns\HasBusinessScope::class);
    expect($traits)->toContain(\Laravel\Scout\Searchable::class);
});

it('D1.A DocRequirement source documenta D7 LGPD governance audit motivacao', function () {
    $source = file_get_contents((new ReflectionClass(DocRequirement::class))->getFileName());

    expect($source)->toContain('Wave 27');
    expect($source)->toContain('audit trail LGPD');
    expect($source)->toContain('rastreabilidade');
});

it('D1.A DocEvidence source documenta D7 LGPD triagem motivacao', function () {
    $source = file_get_contents((new ReflectionClass(DocEvidence::class))->getFileName());

    expect($source)->toContain('Wave 27');
    expect($source)->toContain('audit trail LGPD');
    expect($source)->toContain('triagem');
});

// ============================================================================
// D9.A — Spans canon expand (ModuleAuditor + MemoryReader)
// ============================================================================

it('D9.A ModuleAuditor.audit() envolve em OtelHelper::spanBiz canon', function () {
    $source = file_get_contents((new ReflectionClass(ModuleAuditor::class))->getFileName());

    expect($source)->toContain('use App\\Util\\OtelHelper;');
    expect($source)->toContain("OtelHelper::spanBiz('srs.audit.module'");
});

it('D9.A ModuleAuditor expoe auditInterno() para separar contrato de span (Wave 27)', function () {
    expect(method_exists(ModuleAuditor::class, 'audit'))->toBeTrue();
    expect(method_exists(ModuleAuditor::class, 'auditInterno'))->toBeTrue();
});

it('D9.A MemoryReader.listRoots() envolve em OtelHelper::spanBiz canon', function () {
    $source = file_get_contents((new ReflectionClass(MemoryReader::class))->getFileName());

    expect($source)->toContain('use App\\Util\\OtelHelper;');
    expect($source)->toContain("OtelHelper::spanBiz('srs.memory.list_roots'");
});

it('D9.A MemoryReader source documenta motivacao OTel (I/O-heavy notar regressao)', function () {
    $source = file_get_contents((new ReflectionClass(MemoryReader::class))->getFileName());

    expect($source)->toContain('Wave 27 D9');
    expect($source)->toContain('I/O-heavy');
    expect($source)->toContain('regressao');
});

// ============================================================================
// D7.A — LGPD push final (retention.php Wave 27)
// ============================================================================

it('D7.A retention.php declara base_legal LGPD (Art. 7º II + IX)', function () {
    $cfg = require base_path('Modules/SRS/Config/retention.php');

    expect($cfg)->toHaveKey('base_legal');
    expect($cfg['base_legal'])->toHaveKeys(['art', 'finalidade', 'titular_pertence_a']);
    expect($cfg['base_legal']['art'])->toContain('LGPD Art. 7º');
    expect($cfg['base_legal']['art'])->toContain('cumprimento obrigação legal');
});

it('D7.A retention.php declara notice_period_days configuravel (env)', function () {
    $cfg = require base_path('Modules/SRS/Config/retention.php');

    expect($cfg)->toHaveKey('notice_period_days');
    expect($cfg['notice_period_days'])->toBeInt();
    expect($cfg['notice_period_days'])->toBeGreaterThanOrEqual(0);
});

it('D7.A retention.php declara hierarquia LGPD (drafts < chat = logs < generated)', function () {
    $cfg = require base_path('Modules/SRS/Config/retention.php');

    expect($cfg)->toHaveKey('hierarquia');
    expect($cfg['hierarquia']['drafts'])->toBe(90);
    expect($cfg['hierarquia']['chat_messages'])->toBe(365);
    expect($cfg['hierarquia']['logs_validation'])->toBe(365);
    expect($cfg['hierarquia']['generated_docs'])->toBe(1825);

    // Validacao ordem crescente
    expect($cfg['hierarquia']['drafts'])->toBeLessThan($cfg['hierarquia']['chat_messages']);
    expect($cfg['hierarquia']['chat_messages'])->toBeLessThan($cfg['hierarquia']['generated_docs']);
});

it('D7.A retention.php declara strategy (hard|soft) configuravel via env', function () {
    $cfg = require base_path('Modules/SRS/Config/retention.php');

    expect($cfg)->toHaveKey('strategy');
    expect($cfg['strategy'])->toBeIn(['hard', 'soft']);
});

it('D7.A retention.php declara entities mapping (DocChatMessage + DocValidationRun cobertos)', function () {
    $cfg = require base_path('Modules/SRS/Config/retention.php');

    expect($cfg)->toHaveKey('entities');
    expect($cfg['entities'])->toHaveKeys(['DocChatMessage', 'DocValidationRun']);
    expect($cfg['entities']['DocChatMessage'])->toBe(365);
    expect($cfg['entities']['DocValidationRun'])->toBe(365);
});

it('D7.A retention.php justificativa generated_docs 1825d cita CLT + Receita Federal', function () {
    $source = file_get_contents(base_path('Modules/SRS/Config/retention.php'));

    expect($source)->toContain('CLT Art. 11');
    expect($source)->toContain('Receita Federal');
    expect($source)->toContain('5 anos');
});

it('D7.A retention.php preserva chaves Wave 12-18 legadas (back-compat)', function () {
    $cfg = require base_path('Modules/SRS/Config/retention.php');

    expect($cfg)->toHaveKeys([
        'generated_docs_days',
        'draft_versions_days',
        'generation_logs_days',
        'chat_messages_days',
    ]);
});
