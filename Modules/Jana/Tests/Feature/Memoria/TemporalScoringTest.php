<?php

declare(strict_types=1);

// @covers-us US-COPI-110 — contrato FIM-DE-ESTÁGIO do time-decay (K1): a saída do
// par decay→reranker (como o buscarInterno encadeia) tem ADR canônica RECENTE na
// frente da antiga/superseded. Âncora de contrato: US-COPI-110 SPEC ("documentos
// canônicos recentes vencerem antigos no recall") + ADR 0256 (teste ancorado em
// contrato, não na implementação — lápide §5 "teste tautológico" proibicoes.md).

use Illuminate\Support\Collection;
use Modules\Jana\Entities\MemoriaFato;
use Modules\Jana\Services\Memoria\MeilisearchDriver;
use Modules\Jana\Services\Retrieval\Reranker;

uses(Tests\TestCase::class);

/**
 * TemporalScoringTest — nome pedido nominalmente pelo DoD da US-COPI-110.
 *
 * Por que este teste existe (reconciliação 2026-07-12): o TimeDecayTest cobria a
 * FÓRMULA do applyTimeDecay isolada, mas o RrfReranker default (caso single-source)
 * ranqueia por POSIÇÃO de entrada e ignora `score` — o decay era calculado e
 * DESCARTADO. Este teste exercita o pipeline decay→reranker ENCADEADO (mesma
 * sequência do MeilisearchDriver::buscarInterno L194→L211) com o Reranker REAL
 * resolvido do container, e asserta a ORDEM FINAL. Ele FALHA no código pré-fix
 * (recente ficava atrás) e trava o contrato contra regressão.
 */

// ── helpers ──────────────────────────────────────────────────────────────

function temporalMakeFato(int $id, string $fato, array $metadata, int $ageDays): MemoriaFato
{
    $model = new MemoriaFato();
    $model->setRawAttributes([
        'id'          => $id,
        'business_id' => 1,
        'user_id'     => 1,
        'fato'        => $fato,
        'metadata'    => json_encode($metadata),
        'valid_from'  => now()->subDays($ageDays)->toDateTimeString(),
        'valid_until' => null,
        'created_at'  => now()->subDays($ageDays)->toDateTimeString(),
        'updated_at'  => now()->toDateTimeString(),
    ]);

    return $model;
}

/**
 * Reproduz o estágio pós-recall do buscarInterno: applyTimeDecay (reflection,
 * private) seguido do Reranker canônico resolvido do container (driver default).
 *
 * @return array<int, array{id:int, snippet:string, score:float}>
 */
function temporalDecayEntaoRerank(Collection $hits, int $topK = 5): array
{
    $driver = new MeilisearchDriver();

    $ref    = new ReflectionClass($driver);
    $method = $ref->getMethod('applyTimeDecay');
    $method->setAccessible(true);

    /** @var array $candidatos */
    $candidatos = $method->invoke($driver, $hits);

    /** @var Reranker $reranker */
    $reranker = app(Reranker::class);

    return $reranker->reranquear('qual a regra vigente de multi-tenant?', $candidatos, $topK);
}

beforeEach(function () {
    config([
        'copiloto.time_decay.enabled'         => true,
        'copiloto.time_decay.temporal_weight' => 0.4,
        'copiloto.time_decay.half_life'       => ['adr' => 365, 'default' => 180],
        'copiloto.time_decay.status_multipliers' => [
            'accepted'   => 1.2,
            'historical' => 0.5,
            'superseded' => 0.3,
            'default'    => 1.0,
        ],
        // Garante o driver default (rrf) — o mesmo que roda em prod sem env override.
        'copiloto.reranker.driver'  => 'rrf',
        'copiloto.reranker.enabled' => true,
    ]);
});

// ── contrato principal (DoD US-COPI-110, nominal) ─────────────────────────

it('ranks recent accepted ADR above old superseded ADR for same query', function () {
    // Input ADVERSO: a ADR antiga superseded entra na FRENTE (posição 0 = mais
    // relevante pro Meilisearch). Contrato: na saída FINAL do reranker a ADR
    // accepted recente vence — é exatamente o caso que o pipeline pré-fix perdia.
    $hits = collect([
        temporalMakeFato(100, 'ADR 0010 regra antiga superseded', ['doc_type' => 'adr', 'status' => 'superseded'], ageDays: 700),
        temporalMakeFato(200, 'ADR 0093 regra vigente accepted',  ['doc_type' => 'adr', 'status' => 'accepted'],   ageDays: 20),
    ]);

    $final = temporalDecayEntaoRerank($hits);

    expect(array_column($final, 'id'))->toBe([200, 100]);
});

it('ranks recent accepted ADR above old accepted ADR (mesmo lifecycle, só idade)', function () {
    $hits = collect([
        temporalMakeFato(1, 'ADR accepted de 2024', ['doc_type' => 'adr', 'status' => 'accepted'], ageDays: 730),
        temporalMakeFato(2, 'ADR accepted de 2026', ['doc_type' => 'adr', 'status' => 'accepted'], ageDays: 30),
    ]);

    $final = temporalDecayEntaoRerank($hits);

    expect(array_column($final, 'id'))->toBe([2, 1]);
});

// ── back-compat: flag off = pipeline legado byte-idêntico ─────────────────

it('com JANA_TIME_DECAY_ENABLED=false a ordem de entrada (relevância RRF) é preservada', function () {
    config(['copiloto.time_decay.enabled' => false]);

    $hits = collect([
        temporalMakeFato(100, 'ADR antiga superseded', ['doc_type' => 'adr', 'status' => 'superseded'], ageDays: 700),
        temporalMakeFato(200, 'ADR recente accepted',  ['doc_type' => 'adr', 'status' => 'accepted'],   ageDays: 20),
    ]);

    $final = temporalDecayEntaoRerank($hits);

    // Kill-switch: sem decay, quem entra primeiro (mais relevante) sai primeiro.
    expect(array_column($final, 'id'))->toBe([100, 200]);
});

// ── Tier 0 defensiva (DoD: preserves business_id scope) ───────────────────

it('preserves business_id scope — estágio não recebe nem altera tenant (material já scoped upstream)', function () {
    // ADR 0093: o scope multi-tenant é aplicado no filtro Meilisearch do
    // buscarInterno (business_id = N). O estágio decay→rerank opera sobre
    // material já filtrado e não pode reintroduzir dados de outro tenant:
    // output ⊆ input (mesmos ids, nenhum id novo).
    $hits = collect([
        temporalMakeFato(10, 'fato biz 1 — a', ['doc_type' => 'session'], ageDays: 5),
        temporalMakeFato(20, 'fato biz 1 — b', ['doc_type' => 'session'], ageDays: 50),
    ]);

    $final = temporalDecayEntaoRerank($hits);

    $ids = array_column($final, 'id');
    sort($ids);
    expect($ids)->toBe([10, 20]); // mesmos ids, nenhum id novo (nada cross-tenant entra aqui)
});
