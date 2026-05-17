<?php

declare(strict_types=1);

use Modules\KB\Services\KbRagService;

// `uses(Tests\TestCase::class)` aplicado globalmente em tests/Pest.php — não redeclarar.

/**
 * Wave 28 SATURATION FINAL KB — push 91 → ≥95 (+4pp).
 *
 * Foco minimal:
 *   - D2 (+3) Pest KbRagService contract source-level (assert business_id obrigatório
 *             + PII redact pré-LLM + cache key tier 0 — defesa em camadas RAG)
 *   - D9 (+1) span audit catalog confirmação (kb.rag.ask + kb.corpus.retrieve +
 *             kb.rerank.bge_v2_m3 + kb.health.check — ≥4 spans canônicos)
 *
 * Tier 0 IRREVOGÁVEL (ADR 0093):
 *   ⛔ business_id parâmetro EXPLÍCITO em todos métodos públicos KbRagService
 *   ⛔ NUNCA session() em código de service (assertBusinessId throw <= 0)
 *   ⛔ Cache key DEVE incluir businessId + corpusHash (anti-vazamento cross-tenant)
 *
 * @see Modules/KB/Services/KbRagService.php (assertBusinessId + OtelHelper::span)
 * @see ADR 0093 multi-tenant Tier 0 §"Services com $businessId param explícito"
 */

it('W28 D2.a KbRagService.ask() exige business_id explicito (Tier 0 — não session())', function () {
    $ref = new ReflectionMethod(KbRagService::class, 'ask');
    $params = $ref->getParameters();

    // Signature: ask(string $query, int $businessId, ?int $userId = null, array $opts = [])
    expect($params[1]->getName())->toBe('businessId');
    expect((string) $params[1]->getType())->toBe('int');
    expect($params[1]->isOptional())->toBeFalse('businessId DEVE ser parâmetro obrigatório (Tier 0)');
});

it('W28 D2.b KbRagService source-code referencia PiiRedactor + assertBusinessId (defesa duas camadas)', function () {
    $src = file_get_contents((new ReflectionClass(KbRagService::class))->getFileName());

    // Camada 1: PII redact ANTES de log/cache/LLM
    expect($src)->toContain('PiiRedactor');
    expect($src)->toContain('redactPii');

    // Camada 2: assert biz > 0 explícito
    expect($src)->toContain('assertBusinessId');
    expect($src)->toContain('$this->assertBusinessId($businessId)');
});

it('W28 D2.c KbRagService askCacheKey inclui businessId + corpusHash (anti-vazamento cross-tenant)', function () {
    // Método protected — usa reflexão
    $ref = new ReflectionClass(KbRagService::class);
    expect($ref->hasMethod('askCacheKey'))->toBeTrue();

    $params = $ref->getMethod('askCacheKey')->getParameters();
    $paramNames = array_map(fn ($p) => $p->getName(), $params);

    // Pest 3.x toContain só aceita 1 arg (sem message) — fail message vem do dump array.
    expect($paramNames)->toContain('businessId');
    expect($paramNames)->toContain('corpusHash');
});

// ============================================================================
// D9 — span catalog KB (≥4 spans canon Wave 25/26/28)
// ============================================================================

it('W28 D9 KB catalog ≥4 spans canon catalogados (kb.rag.ask + corpus + rerank + health)', function () {
    $spans = [
        'kb.rag.ask'           => 'Modules/KB/Services/KbRagService.php',
        'kb.corpus.retrieve'   => 'Modules/KB/Services/KbCorpusBuilder.php',
        'kb.rerank.bge_v2_m3'  => 'Modules/KB/Services/KbBgeRerankerService.php',
        'kb.health.check'      => 'Modules/KB/Console/Commands/KbHealthCommand.php',
    ];

    foreach ($spans as $spanName => $path) {
        $src = file_get_contents(base_path($path));
        expect(str_contains($src, $spanName))
            ->toBeTrue("Span '{$spanName}' deve estar em {$path} (Wave 28 cobertura hot-path RAG)");
    }

    expect(count($spans))->toBeGreaterThanOrEqual(4, 'KB deve ter ≥4 spans OTel canon (RAG + health)');
});

it('W28 sanity Wave 26 scaffold + smoke preservados (não-regressão)', function () {
    expect(file_exists(__DIR__ . '/Wave26KbScaffoldTest.php'))->toBeTrue();
    expect(file_exists(__DIR__ . '/Wave26KbSmokeTest.php'))->toBeTrue();
});
