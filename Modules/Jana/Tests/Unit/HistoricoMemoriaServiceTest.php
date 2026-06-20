<?php

use Illuminate\Support\Carbon;
use Modules\Jana\Services\Memoria\HistoricoMemoriaService;

/**
 * Teste de LOGICA PURA (sem DB, sem app boot) das helpers do slice 2 (ADR 0295, T4).
 * O predicado event-time ja e coberto pelo BiTemporalResolverTest (slice 1); aqui
 * cobrimos normalizarAsOf (FAILSAFE) + violacaoCrossTenant (multi-tenant Tier 0).
 * A parte que toca DB (buscarHistorico) roda na lane MySQL (jana-pest.yml).
 */
it('normalizarAsOf: string valida -> Carbon correspondente', function () {
    expect(HistoricoMemoriaService::normalizarAsOf('2026-04-15 10:00:00')->toDateTimeString())
        ->toBe('2026-04-15 10:00:00');
});

it('normalizarAsOf: null/vazio -> agora (estado atual)', function () {
    expect(HistoricoMemoriaService::normalizarAsOf(null))->toBeInstanceOf(Carbon::class)
        ->and(HistoricoMemoriaService::normalizarAsOf(null)->diffInMinutes(Carbon::now()))->toBeLessThan(1)
        ->and(HistoricoMemoriaService::normalizarAsOf(''))->toBeInstanceOf(Carbon::class);
});

it('normalizarAsOf: lixo nao-parseavel -> agora (FAILSAFE, nao lanca)', function () {
    expect(HistoricoMemoriaService::normalizarAsOf('xxx-nao-data'))->toBeInstanceOf(Carbon::class);
});

it('normalizarAsOf: DateTimeInterface -> Carbon equivalente', function () {
    $dt = new DateTimeImmutable('2026-01-02 03:04:05');
    expect(HistoricoMemoriaService::normalizarAsOf($dt)->toDateTimeString())->toBe('2026-01-02 03:04:05');
});

it('violacaoCrossTenant: mesmo business -> sem violacao', function () {
    expect(HistoricoMemoriaService::violacaoCrossTenant(4, 4, false))->toBeFalse();
});

it('violacaoCrossTenant: business diferente -> VIOLACAO', function () {
    expect(HistoricoMemoriaService::violacaoCrossTenant(4, 1, false))->toBeTrue();
});

it('violacaoCrossTenant: superadmin acessa qualquer business', function () {
    expect(HistoricoMemoriaService::violacaoCrossTenant(4, 1, true))->toBeFalse();
});

it('violacaoCrossTenant: user sem business (null) acessando biz real -> VIOLACAO', function () {
    expect(HistoricoMemoriaService::violacaoCrossTenant(null, 1, false))->toBeTrue();
});
