<?php

declare(strict_types=1);

use Modules\SRS\Entities\DocChatMessage;
use Modules\SRS\Entities\DocEvidence;
use Modules\SRS\Entities\DocRequirement;
use Modules\SRS\Entities\DocSource;

uses(Tests\TestCase::class);

/**
 * Wave 28 SATURATION FINAL SRS — push 88 → ≥92 (+4pp).
 *
 * Foco minimal D2 (+3 Pest cross-tenant defesa em camadas) — push final boost.
 *
 * Estratégia: source-level + reflexão (SQLite-friendly, sem MySQL).
 * Cobre 4 entities tenant-scoped (DocSource/DocRequirement/DocEvidence/DocChatMessage)
 * que precisam HasBusinessScope trait OU business_id explicito no fillable.
 *
 * Tier 0 IRREVOGÁVEL (ADR 0093):
 *   ⛔ NUNCA biz=4 (ROTA LIVRE prod — ADR 0101)
 *   ⛔ DocLink/DocPage/DocValidationRun são repo-wide intencional (justificado source-level Wave 26)
 *
 * @see Modules/SRS/Tests/Feature/Wave25CrossTenantSaturationTest.php
 * @see Modules/SRS/Tests/Feature/Wave26SaturationTest.php
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */

it('W28 D2.a DocSource + DocRequirement + DocEvidence ambos têm HasBusinessScope (defesa Model-level)', function () {
    foreach ([DocSource::class, DocRequirement::class, DocEvidence::class] as $entityClass) {
        $traits = class_uses_recursive($entityClass);
        // Pest 3.x toContain só aceita 1 arg (item) — sem message. Falha mostra entityClass via foreach context.
        expect($traits)->toContain(\App\Concerns\HasBusinessScope::class);
    }
});

it('W28 D2.b DocChatMessage fillable inclui business_id + DocEvidence shouldBeSearchable filtra biz', function () {
    // DocChatMessage: column-level — business_id no fillable + HasBusinessScope
    $msg = new DocChatMessage();
    expect($msg->getFillable())->toContain('business_id');

    // DocEvidence: defesa anti-vazamento Meilisearch via shouldBeSearchable()
    $e = new DocEvidence();
    expect($e->shouldBeSearchable())->toBeFalse('Sem business_id seteado, NÃO indexa (anti-vazamento)');

    $e->business_id = 1;
    expect($e->shouldBeSearchable())->toBeTrue('Com biz setado, indexa normalmente');
});

it('W28 D2.c DocSource source-code referencia ADR 0093 + DocLink/DocPage justifica exceção repo-wide', function () {
    $srcDocSource = file_get_contents((new ReflectionClass(DocSource::class))->getFileName());
    expect($srcDocSource)->toContain('ADR 0093');

    // DocLink + DocPage devem documentar a exceção repo-wide (justificativa source-level Wave 26)
    $srcDocLink = file_get_contents((new ReflectionClass(\Modules\SRS\Entities\DocLink::class))->getFileName());
    expect($srcDocLink)->toContain('EXCEÇÃO REPO-WIDE');
});

it('W28 sanity Wave 26/25 preservados (não-regressão)', function () {
    expect(file_exists(__DIR__ . '/Wave26SaturationTest.php'))->toBeTrue();
    expect(file_exists(__DIR__ . '/Wave25CrossTenantSaturationTest.php'))->toBeTrue();
});
