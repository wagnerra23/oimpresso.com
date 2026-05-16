<?php

declare(strict_types=1);

use Modules\KB\Services\KbRagService;

/**
 * Wave 12 — KbRagService multi-tenant Tier 0 (ADR 0093) + cross-tenant biz=1 vs biz=99.
 *
 * Cobertura nova:
 *   1. assertBusinessId rejeita business_id inválido (0, negativo)
 *   2. cache keys são isoladas por business_id (biz=1 NUNCA reusa cache biz=99)
 *   3. estimateCostBrl é determinístico (vai pra audit log + monitoramento custo IA)
 *
 * NOTA: Não chama LLM real (sem mock mode — testa só pré-condições + cache keys
 * + cálculos puros). Pattern alinhado com helper KB e SCHEMA-DB-V1 §11.
 *
 * Tier 0 IRREVOGÁVEL: biz=1 + biz=99 — NUNCA biz=4 (ROTA LIVRE prod — ADR 0101).
 *
 * @see Modules/KB/Services/KbRagService.php
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0101-tests-business-id-1-nunca-cliente.md
 */

beforeEach(function () {
    $this->service = new KbRagService();
});

// ─── assertBusinessId — Tier 0 fail-fast ─────────────────────────────────────

it('rejeita business_id zero em ask() (Tier 0 — ADR 0093)', function () {
    expect(fn () => $this->service->ask('qualquer pergunta', 0))
        ->toThrow(\InvalidArgumentException::class, 'business_id positivo');
});

it('rejeita business_id negativo em ask() (Tier 0 — ADR 0093)', function () {
    expect(fn () => $this->service->ask('qualquer pergunta', -1))
        ->toThrow(\InvalidArgumentException::class, 'business_id positivo');
});

it('rejeita business_id zero em summarize() (Tier 0)', function () {
    expect(fn () => $this->service->summarize('qualquer-slug', 0))
        ->toThrow(\InvalidArgumentException::class, 'business_id positivo');
});

it('rejeita business_id zero em suggestMeta() (Tier 0)', function () {
    expect(fn () => $this->service->suggestMeta([], 0))
        ->toThrow(\InvalidArgumentException::class, 'business_id positivo');
});

// ─── Cache keys isolated cross-tenant (R5 ADR 0149) ──────────────────────────

it('cache key de ask é isolada entre biz=1 e biz=99 (cross-tenant)', function () {
    // Acessa askCacheKey via reflection (protected) — pattern aceitável em test interno.
    $ref = new ReflectionClass(KbRagService::class);
    $method = $ref->getMethod('askCacheKey');
    $method->setAccessible(true);

    $query = 'como funciona FSM no oimpresso';
    $corpusHash = 'abcd1234deadbeef';

    $key1  = $method->invoke($this->service, $query, 1,  $corpusHash, []);
    $key99 = $method->invoke($this->service, $query, 99, $corpusHash, []);

    // Mesma query, mesma corpus_hash, diferentes biz → keys DIFERENTES.
    expect($key1)->not->toBe($key99);
    expect($key1)->toContain(':biz:1');
    expect($key99)->toContain(':biz:99');
});

it('cache key de ask muda quando corpus_hash muda (cache invalidation)', function () {
    $ref = new ReflectionClass(KbRagService::class);
    $method = $ref->getMethod('askCacheKey');
    $method->setAccessible(true);

    $query = 'pergunta x';

    $keyA = $method->invoke($this->service, $query, 1, 'hash-corpus-v1', []);
    $keyB = $method->invoke($this->service, $query, 1, 'hash-corpus-v2', []);

    expect($keyA)->not->toBe($keyB);
});

it('idempotency_key sobrescreve cache key normal (path superadmin/replay)', function () {
    $ref = new ReflectionClass(KbRagService::class);
    $method = $ref->getMethod('askCacheKey');
    $method->setAccessible(true);

    $keyIdem = $method->invoke($this->service, 'q', 1, 'h1', ['idempotency_key' => 'fixed-id']);
    $keyNorm = $method->invoke($this->service, 'q', 1, 'h1', []);

    expect($keyIdem)->not->toBe($keyNorm);
    expect($keyIdem)->toContain(':idem:');
});

// ─── estimateCostBrl — determinístico + monitorável (ADR 0094 §4 custo IA) ──

it('estimateCostBrl retorna zero quando tokens são zero', function () {
    $ref = new ReflectionClass(KbRagService::class);
    $method = $ref->getMethod('estimateCostBrl');
    $method->setAccessible(true);

    $custo = $method->invoke($this->service, 0, 0);
    expect($custo)->toBe(0.0);
});

it('estimateCostBrl calcula custo coerente pra gpt-4o-mini', function () {
    $ref = new ReflectionClass(KbRagService::class);
    $method = $ref->getMethod('estimateCostBrl');
    $method->setAccessible(true);

    // 1M tokens IN = $0.15 USD; 1M tokens OUT = $0.60 USD.
    // Total USD = $0.75. Com USD_TO_BRL default 5.0 → R$ [redacted Tier 0]
    config(['kb.usd_to_brl' => 5.0]);
    $custo = $method->invoke($this->service, 1_000_000, 1_000_000);

    expect($custo)->toBeFloat();
    expect(round($custo, 4))->toBe(3.75);
});
