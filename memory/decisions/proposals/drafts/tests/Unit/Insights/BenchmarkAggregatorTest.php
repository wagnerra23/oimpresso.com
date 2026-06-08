<?php

/**
 * DRAFT — NÃO EXECUTAR DIRETO.
 *
 * Cobertura: testes UNITÁRIOS isolados de BenchmarkAggregator (sem DB real).
 * Mockery + Mocks pra DB queries.
 *
 * Edge cases cobertos:
 *   - ratio NULL → skip sem crash
 *   - divisão por zero → guard
 *   - n_businesses < 5 → silent skip
 *   - métrica desconhecida → throw InvalidArgumentException
 *   - period malformado → throw InvalidArgumentException
 */

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Insights\Services\BenchmarkAggregator;

beforeEach(function () {
    $this->aggregator = new BenchmarkAggregator();
});

// ---------------------------------------------------------------------------
// VALIDAÇÃO DE INPUT
// ---------------------------------------------------------------------------

it('throw InvalidArgumentException pra métrica desconhecida', function () {
    expect(fn () => $this->aggregator->compute(1, 'metrica_que_nao_existe', '2026-04'))
        ->toThrow(\InvalidArgumentException::class);
});

it('throw InvalidArgumentException pra period malformado', function () {
    expect(fn () => $this->aggregator->compute(1, 'ticket_medio', '2026/04'))
        ->toThrow(\InvalidArgumentException::class);

    expect(fn () => $this->aggregator->compute(1, 'ticket_medio', 'abril'))
        ->toThrow(\InvalidArgumentException::class);

    expect(fn () => $this->aggregator->compute(1, 'ticket_medio', ''))
        ->toThrow(\InvalidArgumentException::class);
});

it('aceita period formato YYYY-MM', function () {
    DB::shouldReceive('table->where->where->groupBy->get')
        ->andReturn(collect([]));

    $result = $this->aggregator->compute(1, 'ticket_medio', '2026-04');
    expect($result)->toBeArray();
});

// ---------------------------------------------------------------------------
// EDGE — ratio NULL
// ---------------------------------------------------------------------------

it('ratio NULL não crasha (skip silencioso)', function () {
    Log::spy();

    DB::shouldReceive('table')->andReturnSelf();
    DB::shouldReceive('select')->andReturnSelf();
    DB::shouldReceive('where')->andReturnSelf();
    DB::shouldReceive('groupBy')->andReturnSelf();
    DB::shouldReceive('having')->andReturnSelf();
    DB::shouldReceive('get')->andReturn(collect([
        (object) ['business_id' => 1, 'value' => null], // NULL
    ]));

    expect(fn () => $this->aggregator->compute(1, 'ticket_medio', '2026-04'))
        ->not->toThrow(\Throwable::class);
});

// ---------------------------------------------------------------------------
// EDGE — divisão por zero
// ---------------------------------------------------------------------------

it('divisão por zero retorna 0 (guard, não crash)', function () {
    $result = $this->aggregator->safeRatio(10.0, 0.0);

    expect($result)->toBe(0.0);
});

it('divisão normal funciona', function () {
    $result = $this->aggregator->safeRatio(100.0, 4.0);

    expect($result)->toBe(25.0);
});

it('divisão por null retorna 0', function () {
    $result = $this->aggregator->safeRatio(100.0, null);

    expect($result)->toBe(0.0);
});

// ---------------------------------------------------------------------------
// k-ANONYMITY GATE — < 5 silent skip
// ---------------------------------------------------------------------------

it('isAnonymitySafe(4) retorna false', function () {
    expect($this->aggregator->isAnonymitySafe(4))->toBeFalse();
});

it('isAnonymitySafe(5) retorna true', function () {
    expect($this->aggregator->isAnonymitySafe(5))->toBeTrue();
});

it('isAnonymitySafe(0) retorna false', function () {
    expect($this->aggregator->isAnonymitySafe(0))->toBeFalse();
});

it('isAnonymitySafe(1000) retorna true', function () {
    expect($this->aggregator->isAnonymitySafe(1000))->toBeTrue();
});

// ---------------------------------------------------------------------------
// PERCENTIS — p50, p90 corretos
// ---------------------------------------------------------------------------

it('percentile p50 calculado corretamente em dataset par', function () {
    $values = [10.0, 20.0, 30.0, 40.0, 50.0, 60.0]; // n=6

    $p50 = $this->aggregator->percentile($values, 0.50);

    // mediana: média entre 30 e 40 = 35.0
    expect($p50)->toBeGreaterThanOrEqual(30.0);
    expect($p50)->toBeLessThanOrEqual(40.0);
});

it('percentile p90 corretamente identifica cauda alta', function () {
    $values = [10.0, 20.0, 30.0, 40.0, 50.0, 60.0, 70.0, 80.0, 90.0, 1000.0];

    $p90 = $this->aggregator->percentile($values, 0.90);

    expect($p90)->toBeGreaterThanOrEqual(90.0);
});

it('percentile com array vazio retorna 0', function () {
    expect($this->aggregator->percentile([], 0.50))->toBe(0.0);
});

it('percentile com 1 elemento retorna esse elemento', function () {
    expect($this->aggregator->percentile([42.0], 0.50))->toBe(42.0);
    expect($this->aggregator->percentile([42.0], 0.90))->toBe(42.0);
});

// ---------------------------------------------------------------------------
// MÉTRICAS SUPORTADAS
// ---------------------------------------------------------------------------

it('métricas canônicas suportadas: ticket_medio, vendas_mes, cmv_pct, ticket_p90', function () {
    $supported = $this->aggregator->supportedMetrics();

    expect($supported)->toContain('ticket_medio');
    expect($supported)->toContain('vendas_mes');
    expect($supported)->toContain('cmv_pct');
    expect($supported)->toContain('ticket_p90');
});

it('cada métrica suportada tem unit definida (R$, %, count)', function () {
    foreach ($this->aggregator->supportedMetrics() as $metric) {
        expect($this->aggregator->metricUnit($metric))->toBeIn(['BRL', 'percent', 'count']);
    }
});
