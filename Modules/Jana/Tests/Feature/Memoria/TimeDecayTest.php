<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\Jana\Entities\MemoriaFato;
use Modules\Jana\Services\Memoria\MeilisearchDriver;

uses(Tests\TestCase::class);

/**
 * K1 — Time-decay weighting recall (Onda 5 dossier 2026-05-13).
 *
 * Cobertura:
 *   - Fórmula canônica: score_base × ((1 - w) + w × 0.5^(age/half_life)) × status_mult
 *   - half_life per doc_type (adr=365, spec=180, session=30, handoff=14)
 *   - status_multipliers (accepted=1.2, historical=0.5, superseded=0.3, default=1.0)
 *   - Feature flag JANA_TIME_DECAY_ENABLED=false → bypass (back-compat)
 *   - Edge case: doc sem date → score base preservado (não crasha)
 *   - Edge case: doc_type desconhecido → fallback half_life=default
 *   - Edge case: status desconhecido → fallback multiplier=1.0
 *   - Multi-tenant Tier 0: time-decay aplica per-query, sem business scope
 *
 * Testa applyTimeDecay() via reflection — método private, sem precisar bootar
 * Scout/Meilisearch (lentos em CI). Defaults da config exercitados pelos testes.
 */

// ── helpers ──────────────────────────────────────────────────────────────

/**
 * Cria MemoriaFato instância (sem persist no DB) com metadata + age controlado.
 */
function decayMakeFato(
    int $id,
    string $fato = 'doc texto',
    array $metadata = [],
    ?Carbon $publishedAt = null,
    int $ageDays = 0,
): MemoriaFato {
    $model = new MemoriaFato();
    // Bypass mass-assignment guard pra setar id + datas sem migration.
    $model->setRawAttributes([
        'id'          => $id,
        'business_id' => 1,
        'user_id'     => 1,
        'fato'        => $fato,
        'metadata'    => json_encode($metadata),
        'valid_from'  => $publishedAt?->toDateTimeString() ?? now()->subDays($ageDays)->toDateTimeString(),
        'valid_until' => null,
        'created_at'  => $publishedAt?->toDateTimeString() ?? now()->subDays($ageDays)->toDateTimeString(),
        'updated_at'  => now()->toDateTimeString(),
    ]);

    return $model;
}

/**
 * Invoca applyTimeDecay() via reflection. Retorna array<int, array{id, snippet, score}>.
 */
function decayInvoke(MeilisearchDriver $driver, Collection $hits): array
{
    $ref    = new ReflectionClass($driver);
    $method = $ref->getMethod('applyTimeDecay');
    $method->setAccessible(true);

    return $method->invoke($driver, $hits);
}

/**
 * Mapa id → score pra asserts.
 */
function decayScoreMap(array $candidatos): array
{
    $map = [];
    foreach ($candidatos as $c) {
        $map[$c['id']] = $c['score'];
    }
    return $map;
}

// ── 1. Fórmula canônica + multipliers ─────────────────────────────────────

it('ADR recente (2026, age=30d) ranka mais alto que ADR antigo (2024, age=730d)', function () {
    config([
        'copiloto.time_decay.enabled'         => true,
        'copiloto.time_decay.temporal_weight' => 0.4,
        'copiloto.time_decay.half_life'       => ['adr' => 365, 'default' => 180],
        'copiloto.time_decay.status_multipliers' => ['accepted' => 1.2, 'default' => 1.0],
    ]);

    $driver = new MeilisearchDriver();

    $hits = collect([
        decayMakeFato(1, 'ADR antigo 2024', ['doc_type' => 'adr', 'status' => 'accepted'], ageDays: 730),
        decayMakeFato(2, 'ADR recente 2026', ['doc_type' => 'adr', 'status' => 'accepted'], ageDays: 30),
    ]);

    $out      = decayInvoke($driver, $hits);
    $scoreMap = decayScoreMap($out);

    expect($scoreMap[2])->toBeGreaterThan($scoreMap[1]);
});

it('status historical ranka abaixo de accepted no mesmo age', function () {
    config([
        'copiloto.time_decay.enabled'         => true,
        'copiloto.time_decay.temporal_weight' => 0.4,
        'copiloto.time_decay.half_life'       => ['adr' => 365],
        'copiloto.time_decay.status_multipliers' => [
            'accepted'   => 1.2,
            'historical' => 0.5,
            'default'    => 1.0,
        ],
    ]);

    $driver = new MeilisearchDriver();

    $hits = collect([
        decayMakeFato(10, 'historical', ['doc_type' => 'adr', 'status' => 'historical'], ageDays: 100),
        decayMakeFato(20, 'accepted',   ['doc_type' => 'adr', 'status' => 'accepted'],   ageDays: 100),
    ]);

    $out      = decayInvoke($driver, $hits);
    $scoreMap = decayScoreMap($out);

    expect($scoreMap[20])->toBeGreaterThan($scoreMap[10]);
    // accepted=1.2, historical=0.5 → razão 2.4×
    expect($scoreMap[20] / $scoreMap[10])->toBeGreaterThan(2.0);
});

it('half_life curto (handoff=14d) — doc de 28d tem decay agressivo ~0.25 do base', function () {
    config([
        'copiloto.time_decay.enabled'         => true,
        'copiloto.time_decay.temporal_weight' => 0.4,
        'copiloto.time_decay.half_life'       => ['handoff' => 14, 'default' => 180],
        'copiloto.time_decay.status_multipliers' => ['default' => 1.0],
    ]);

    $driver = new MeilisearchDriver();

    // age=28d, half_life=14d → 28/14=2 → decay=0.5^2=0.25
    // temporal_factor = (1-0.4) + 0.4×0.25 = 0.6 + 0.1 = 0.70
    // score_base=1.0, status_mult=1.0 → score_final ≈ 0.70
    //
    // Nota: a missão pediu "score ~0.25" — esse 0.25 é o pure decay term 0.5^(28/14).
    // O score final é (1-w) + w×decay = 0.70 (não 0.25), porque temporal_weight=0.4
    // mistura 60% meaning + 40% time. Asserts ambas formas pra documentar.
    $hits = collect([
        decayMakeFato(100, 'handoff 28d', ['doc_type' => 'handoff'], ageDays: 28),
    ]);

    $out   = decayInvoke($driver, $hits);
    $score = $out[0]['score'];

    // Pure decay term: 0.5^(28/14) = 0.25
    $pureDecay = pow(0.5, 28 / 14);
    expect($pureDecay)->toEqualWithDelta(0.25, 0.01);

    // Score final com weight=0.4: 0.7
    expect($score)->toEqualWithDelta(0.70, 0.02);

    // Cross-check: doc mesmíssimo handoff de 0d deve dar score ≈ 1.0
    $hitsFresh  = collect([decayMakeFato(101, 'handoff fresco', ['doc_type' => 'handoff'], ageDays: 0)]);
    $outFresh   = decayInvoke($driver, $hitsFresh);
    expect($outFresh[0]['score'])->toEqualWithDelta(1.0, 0.01);
});

// ── 2. Feature flag bypass ────────────────────────────────────────────────

it('JANA_TIME_DECAY_ENABLED=false bypassa decay (back-compat — score base 1.0)', function () {
    config([
        'copiloto.time_decay.enabled'         => false,
        // valores abaixo seriam dramáticos SE flag ligada — bom canary
        'copiloto.time_decay.temporal_weight' => 0.9,
        'copiloto.time_decay.half_life'       => ['adr' => 1],
        'copiloto.time_decay.status_multipliers' => ['historical' => 0.1, 'default' => 1.0],
    ]);

    $driver = new MeilisearchDriver();

    $hits = collect([
        decayMakeFato(1, 'antiga historical', ['doc_type' => 'adr', 'status' => 'historical'], ageDays: 9999),
        decayMakeFato(2, 'recente accepted',  ['doc_type' => 'adr', 'status' => 'accepted'],   ageDays: 0),
    ]);

    $out = decayInvoke($driver, $hits);

    // Com flag off, todos scores = 1.0 (back-compat estrito do código legado)
    expect($out[0]['score'])->toBe(1.0);
    expect($out[1]['score'])->toBe(1.0);
});

// ── 3. Edge cases (não crashar) ───────────────────────────────────────────

it('doc sem metadata + sem datas → score base preservado (no crash)', function () {
    config([
        'copiloto.time_decay.enabled'         => true,
        'copiloto.time_decay.temporal_weight' => 0.4,
        'copiloto.time_decay.half_life'       => ['default' => 180],
        'copiloto.time_decay.status_multipliers' => ['default' => 1.0],
    ]);

    $driver = new MeilisearchDriver();

    // Fato sem metadata, sem valid_from, sem created_at — força fallback temporal=1.0
    $model = new MemoriaFato();
    $model->setRawAttributes([
        'id'          => 999,
        'business_id' => 1,
        'user_id'     => 1,
        'fato'        => 'sem datas',
        'metadata'    => null,
        'valid_from'  => null,
        'valid_until' => null,
        'created_at'  => null,
        'updated_at'  => null,
    ]);

    $hits = collect([$model]);
    $out  = decayInvoke($driver, $hits);

    expect($out)->toHaveCount(1);
    expect($out[0]['id'])->toBe(999);
    // temporal_factor=1.0 (sem data) × status_default=1.0 × base=1.0 → score=1.0
    expect($out[0]['score'])->toEqualWithDelta(1.0, 0.001);
});

it('doc_type desconhecido cai pra half_life default', function () {
    config([
        'copiloto.time_decay.enabled'         => true,
        'copiloto.time_decay.temporal_weight' => 0.4,
        'copiloto.time_decay.half_life'       => ['default' => 180],
        'copiloto.time_decay.status_multipliers' => ['default' => 1.0],
    ]);

    $driver = new MeilisearchDriver();

    // doc_type='exotic' não existe na map → default=180
    $hits = collect([
        decayMakeFato(1, 'exotic doc', ['doc_type' => 'exotic'], ageDays: 180),
    ]);

    $out   = decayInvoke($driver, $hits);
    // age=180d, half_life=180 → decay=0.5; temporal=0.6+0.4×0.5=0.8
    expect($out[0]['score'])->toEqualWithDelta(0.80, 0.02);
});

it('status desconhecido cai pra multiplier default (1.0)', function () {
    config([
        'copiloto.time_decay.enabled'         => true,
        'copiloto.time_decay.temporal_weight' => 0.4,
        'copiloto.time_decay.half_life'       => ['adr' => 365, 'default' => 180],
        'copiloto.time_decay.status_multipliers' => ['accepted' => 1.2, 'default' => 1.0],
    ]);

    $driver = new MeilisearchDriver();

    $hits = collect([
        decayMakeFato(1, 'sem status', ['doc_type' => 'adr', 'status' => 'frankenstein'], ageDays: 0),
    ]);

    $out = decayInvoke($driver, $hits);
    // age=0 → temporal=1.0; status_unknown=1.0; base=1.0 → score=1.0
    expect($out[0]['score'])->toEqualWithDelta(1.0, 0.01);
});

it('half_life <= 0 (config corrompida) cai pro default sem dividir por zero', function () {
    config([
        'copiloto.time_decay.enabled'         => true,
        'copiloto.time_decay.temporal_weight' => 0.4,
        'copiloto.time_decay.half_life'       => ['adr' => 0, 'default' => 180],
        'copiloto.time_decay.status_multipliers' => ['default' => 1.0],
    ]);

    $driver = new MeilisearchDriver();

    $hits = collect([
        decayMakeFato(1, 'config corrompida', ['doc_type' => 'adr'], ageDays: 90),
    ]);

    // Não deve crashar (no division by zero). Fallback default=180.
    // age=90, half_life=180 → decay=0.5^0.5≈0.707; temporal=0.6+0.4×0.707≈0.883
    $out = decayInvoke($driver, $hits);
    expect($out[0]['score'])->toBeFloat();
    expect($out[0]['score'])->toBeGreaterThan(0.0);
    expect($out[0]['score'])->toEqualWithDelta(0.883, 0.05);
});

// ── 4. Output shape — contrato com Reranker ──────────────────────────────

it('output preserva ordem do input + shape {id, snippet, score}', function () {
    config([
        'copiloto.time_decay.enabled'         => true,
        'copiloto.time_decay.temporal_weight' => 0.4,
        'copiloto.time_decay.half_life'       => ['default' => 180],
        'copiloto.time_decay.status_multipliers' => ['default' => 1.0],
    ]);

    $driver = new MeilisearchDriver();

    $hits = collect([
        decayMakeFato(11, 'doc um',   ageDays: 10),
        decayMakeFato(22, 'doc dois', ageDays: 20),
        decayMakeFato(33, 'doc três', ageDays: 30),
    ]);

    $out = decayInvoke($driver, $hits);

    expect($out)->toHaveCount(3);
    expect(array_column($out, 'id'))->toBe([11, 22, 33]);  // ordem RRF preservada

    foreach ($out as $c) {
        expect($c)->toHaveKeys(['id', 'snippet', 'score']);
        expect($c['score'])->toBeFloat();
        expect($c['snippet'])->toBeString();
    }
});

it('snippet é truncado a 300 chars (mb_substr)', function () {
    config([
        'copiloto.time_decay.enabled' => true,
    ]);

    $driver = new MeilisearchDriver();
    $longFato = str_repeat('á', 500); // 500 chars unicode

    $hits = collect([decayMakeFato(1, $longFato)]);
    $out  = decayInvoke($driver, $hits);

    expect(mb_strlen($out[0]['snippet']))->toBe(300);
});

// ── 5. Multi-tenant Tier 0 defensiva ──────────────────────────────────────

it('time-decay aplica per-query sem business scope (Tier 0 — material assumido scoped pelo caller)', function () {
    // Defensiva ADR 0093: applyTimeDecay recebe só Collection já filtered.
    // Nenhum parâmetro $businessId no signature do método.
    $ref    = new ReflectionClass(MeilisearchDriver::class);
    $method = $ref->getMethod('applyTimeDecay');

    $paramNames = array_map(fn ($p) => $p->getName(), $method->getParameters());
    expect($paramNames)->not->toContain('businessId');
    expect($paramNames)->not->toContain('userId');
    expect($paramNames)->toContain('hits');
});
