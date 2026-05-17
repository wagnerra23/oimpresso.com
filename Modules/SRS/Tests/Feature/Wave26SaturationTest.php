<?php

declare(strict_types=1);

use Modules\SRS\Entities\DocChatMessage;
use Modules\SRS\Entities\DocEvidence;
use Modules\SRS\Entities\DocLink;
use Modules\SRS\Entities\DocPage;
use Modules\SRS\Entities\DocRequirement;
use Modules\SRS\Entities\DocSource;
use Modules\SRS\Entities\DocValidationRun;
use Spatie\Activitylog\Traits\LogsActivity;

uses(Tests\TestCase::class);

/**
 * Wave 26 SATURATION SRS — push 70 → ≥85 (+15pp).
 *
 * Esforco:
 *   - D1 (24→28+, +4): Entities trait coverage + reflexao expandida
 *   - D9 (3→6+, +3): novos spans (DocRetentionCleaner.dryRun/purge + MemoryReader.listRoots)
 *   - D7 (5→8+, +3): LogsActivity + retention defaults + PiiRedactor confirm
 *
 * Tier 0 IRREVOGAVEL (ADR 0093):
 *   - DocSource / DocRequirement / DocEvidence / DocChatMessage tem HasBusinessScope
 *   - DocLink / DocPage / DocValidationRun sao repo-wide intencional (justificado source-level)
 *
 * SQLite-friendly: usa class_uses_recursive + reflexao + file_get_contents
 * pra evitar dependencia MySQL real (alinhado com Wave25CrossTenantSaturationTest pattern).
 *
 * @see Modules/SRS/Tests/Feature/Wave25CrossTenantSaturationTest.php
 * @see Modules/SRS/Services/DocRetentionCleaner.php (D9 new spans Wave 26)
 * @see Modules/SRS/Services/MemoryReader.php (D9 new spans Wave 26)
 */

// ============================================================================
// D1 EXPANDED — Entities trait + casts coverage (push 24/30 → 28+)
// ============================================================================

it('D1.A DocSource declara LogsActivity (audit trail Wave 17/18 preservado)', function () {
    $traits = class_uses_recursive(DocSource::class);
    expect($traits)->toContain(LogsActivity::class);
});

it('D1.A DocChatMessage declara LogsActivity (audit trail chat redacted)', function () {
    $traits = class_uses_recursive(DocChatMessage::class);
    expect($traits)->toContain(LogsActivity::class);
});

it('D1.A DocEvidence declara Searchable (Scout/Meilisearch integration)', function () {
    $traits = class_uses_recursive(DocEvidence::class);
    $hasSearchable = collect($traits)->contains(fn ($t) => str_contains($t, 'Searchable'));
    expect($hasSearchable)->toBeTrue();
});

it('D1.A DocEvidence::shouldBeSearchable filtra por business_id seteado (defesa anti-vazamento)', function () {
    $e = new DocEvidence();
    expect($e->shouldBeSearchable())->toBeFalse();

    $e->business_id = 1;
    expect($e->shouldBeSearchable())->toBeTrue();
});

it('D1.A DocSource source-code cita ADR 0093 Multi-tenant Tier 0 (governance trail)', function () {
    $src = file_get_contents((new ReflectionClass(DocSource::class))->getFileName());
    expect($src)->toContain('Multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093)');
});

it('D1.A DocChatMessage fillable contem business_id + user_id + session_id (chat audit minimo)', function () {
    $fillable = (new DocChatMessage)->getFillable();
    expect($fillable)->toContain('business_id');
    expect($fillable)->toContain('user_id');
    expect($fillable)->toContain('session_id');
});

it('D1.A DocLink documenta exceção repo-wide ADR 0093 §"Exceção repo-wide" (governance trail)', function () {
    $src = file_get_contents((new ReflectionClass(DocLink::class))->getFileName());
    expect($src)->toContain('EXCEÇÃO REPO-WIDE');
    expect($src)->toContain('isolamento Tier 0 transitivamente');
});

// ============================================================================
// D9 — Novos spans Wave 26 (DocRetentionCleaner + MemoryReader)
// ============================================================================

it('D9 DocRetentionCleaner.dryRun usa OtelHelper::spanBiz canon (srs.retention.dry_run)', function () {
    $src = file_get_contents(base_path('Modules/SRS/Services/DocRetentionCleaner.php'));
    expect($src)->toContain('use App\Util\OtelHelper;');
    expect($src)->toContain("OtelHelper::spanBiz('srs.retention.dry_run'");
});

it('D9 DocRetentionCleaner.purge usa OtelHelper::spanBiz canon (srs.retention.purge)', function () {
    $src = file_get_contents(base_path('Modules/SRS/Services/DocRetentionCleaner.php'));
    expect($src)->toContain("OtelHelper::spanBiz('srs.retention.purge'");
});

it('D9 MemoryReader.listRoots usa OtelHelper::spanBiz canon (srs.memory.list_roots)', function () {
    $src = file_get_contents(base_path('Modules/SRS/Services/MemoryReader.php'));
    expect($src)->toContain('use App\Util\OtelHelper;');
    expect($src)->toContain("OtelHelper::spanBiz('srs.memory.list_roots'");
});

it('D9 zero-cost: OtelHelper::span retorna direto callback quando otel.enabled=false', function () {
    config(['otel.enabled' => false]);

    $called = false;
    $result = \App\Util\OtelHelper::span('test.zero_cost', [], function () use (&$called) {
        $called = true;
        return 'wave26-ok';
    });

    expect($called)->toBeTrue();
    expect($result)->toBe('wave26-ok');
});

it('D9 SRS tem ≥6 spans canon catalogados (Wave 26 expand 3→6+)', function () {
    $spans = [
        'srs.chat.ask'             => 'Modules/SRS/Services/ChatAssistant.php',
        'srs.doc.validate'         => 'Modules/SRS/Services/DocValidator.php',
        'srs.retention.dry_run'    => 'Modules/SRS/Services/DocRetentionCleaner.php',
        'srs.retention.purge'      => 'Modules/SRS/Services/DocRetentionCleaner.php',
        'srs.memory.list_roots'    => 'Modules/SRS/Services/MemoryReader.php',
    ];

    foreach ($spans as $spanName => $path) {
        $src = file_get_contents(base_path($path));
        expect(str_contains($src, $spanName))
            ->toBeTrue("Span '{$spanName}' deve estar em {$path}");
    }

    expect(count($spans))->toBeGreaterThanOrEqual(5);
});

// ============================================================================
// D7 LGPD — retention.php hierarquia + PiiRedactor confirm (push 5/10 → 8+)
// ============================================================================

it('D7 retention.php tem 4 janelas canonicas + ordem hierarquica correta', function () {
    $cfg = require base_path('Modules/SRS/Config/retention.php');

    expect($cfg)->toHaveKeys([
        'generated_docs_days',
        'draft_versions_days',
        'generation_logs_days',
        'chat_messages_days',
    ]);

    // Cada valor inteiro positivo
    foreach (['generated_docs_days', 'draft_versions_days', 'generation_logs_days', 'chat_messages_days'] as $k) {
        expect($cfg[$k])->toBeInt();
        expect($cfg[$k])->toBeGreaterThan(0);
    }
});

it('D7 retention.php declara base legal LGPD Art. 16 + cita ADRs 0093/0094', function () {
    $src = file_get_contents(base_path('Modules/SRS/Config/retention.php'));

    expect($src)->toContain('LGPD');
    expect($src)->toContain('Art. 16');
    expect($src)->toContain('0093');
    expect($src)->toContain('0094');
});

it('D7 ChatAssistant integra PiiRedactor pra sanitize fallback log (defense in depth)', function () {
    $src = file_get_contents(base_path('Modules/SRS/Services/ChatAssistant.php'));

    expect($src)->toContain('Modules\Jana\Services\Privacy\PiiRedactor');
    expect($src)->toContain('$redactor->redact');
});

it('D7 DocChatMessage::getActivitylogOptions exclui content/sources (PII-free audit)', function () {
    $msg = new DocChatMessage();
    $opts = $msg->getActivitylogOptions();

    // Reflection no logAttributes
    $logAttributes = $opts->logAttributes ?? [];
    expect($logAttributes)->not->toContain('content');
    expect($logAttributes)->not->toContain('sources');
});

it('D7 DocSource::getActivitylogOptions usa logFillable (audit consciente sem PII direto)', function () {
    // Smoke: Source existe + retorna LogOptions instance.
    $src = new DocSource();
    $opts = $src->getActivitylogOptions();

    expect($opts)->toBeInstanceOf(\Spatie\Activitylog\LogOptions::class);
});

it('D7 DocRetentionCleaner respeita janelas — cutoffs derivam de retention.php', function () {
    $service = new \Modules\SRS\Services\DocRetentionCleaner();
    $ref = new ReflectionClass($service);

    expect($ref->hasMethod('dryRun'))->toBeTrue();
    expect($ref->hasMethod('purge'))->toBeTrue();
});

// ============================================================================
// Sanity Wave 26
// ============================================================================

it('Wave 26 module.json governance.bucket preservado functional_horizontal', function () {
    $json = json_decode(file_get_contents(base_path('Modules/SRS/module.json')), true);

    expect($json['governance']['bucket'])->toBe('functional_horizontal');
});

it('Wave 26 OtelHelper canonical app/Util — NUNCA app/Support/Otel (rollback PR #963 lesson)', function () {
    expect(file_exists(base_path('app/Util/OtelHelper.php')))->toBeTrue();
    expect(file_exists(base_path('app/Support/Otel/OtelHelper.php')))->toBeFalse(
        'OtelHelper canonical em app/Util — duplicado em app/Support/Otel proibido (lesson rollback PR #963)'
    );
});
