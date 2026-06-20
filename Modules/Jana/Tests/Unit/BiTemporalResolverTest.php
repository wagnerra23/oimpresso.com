<?php

use Modules\Jana\Services\Memoria\BiTemporalResolver;

/**
 * Teste de LOGICA PURA (sem DB, sem app boot) do predicado event-time (ADR 0295, T4 slice 1).
 * Roda no CI porque so chama o metodo estatico com strings/null. A traducao pra SQL
 * (buscarHistorico) e exercitada no CT 100 nos slices seguintes.
 */
$T = '2026-04-15 10:00:00';   // instante-mundo de referencia

it('dentro da janela -> vigente', function () use ($T) {
    expect(BiTemporalResolver::vigenteEm('2026-04-10 00:00:00', '2026-04-20 00:00:00', $T))->toBeTrue();
});

it('antes do inicio -> NAO vigente', function () use ($T) {
    expect(BiTemporalResolver::vigenteEm('2026-04-16 00:00:00', null, $T))->toBeFalse();
});

it('no/depois do fim -> NAO vigente (fim exclusivo)', function () {
    $until = '2026-04-15 10:00:00';
    expect(BiTemporalResolver::vigenteEm('2026-04-01 00:00:00', $until, $until))->toBeFalse()
        ->and(BiTemporalResolver::vigenteEm('2026-04-01 00:00:00', $until, '2026-04-16 00:00:00'))->toBeFalse();
});

it('no inicio exato -> vigente (inicio inclusivo)', function () {
    $from = '2026-04-15 10:00:00';
    expect(BiTemporalResolver::vigenteEm($from, null, $from))->toBeTrue();
});

it('from null = desde sempre', function () use ($T) {
    expect(BiTemporalResolver::vigenteEm(null, '2026-04-20 00:00:00', $T))->toBeTrue();
});

it('until null = ainda vale', function () use ($T) {
    expect(BiTemporalResolver::vigenteEm('2026-01-01 00:00:00', null, $T))->toBeTrue();
});

it('ambos null = sempre vigente (fato legado sem event-time)', function () use ($T) {
    expect(BiTemporalResolver::vigenteEm(null, null, $T))->toBeTrue();
});

it('asOf null -> NAO afirma nada (false)', function () {
    expect(BiTemporalResolver::vigenteEm('2026-01-01 00:00:00', null, null))->toBeFalse();
});

it('data nao-parseavel -> tratada como null (FAILSAFE, nao lanca)', function () use ($T) {
    // from lixo -> tratado como null (desde sempre) -> vigente se antes do until
    expect(BiTemporalResolver::vigenteEm('xxx-nao-data', null, $T))->toBeTrue();
});
