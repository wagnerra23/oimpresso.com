<?php

declare(strict_types=1);
// Cobre, na regra pura, bordas de UC-FPN-02, UC-FPN-03, UC-FPN-04 (Fisio/Painel.casos.md)
// e UC-FPP-18 (Fisio/Public/Portal.casos.md).

use Modules\Fisio\Services\AdesaoCalculator;
use Modules\Fisio\Services\AtencaoDetector;
use Modules\Fisio\Services\DiaLocal;
use Modules\Fisio\Services\SequenciaCalculator;

/**
 * Bordas que os quatro contratos de Unit não fixam: o fuso na virada do dia, a separação
 * das duas filas do painel e o dia repetido na sequência. Serviços PUROS, sem TestCase.
 *
 * Fuso: America/Sao_Paulo é UTC-3 o ano todo desde 2019 (sem horário de verão), logo
 * 02h30 UTC do dia 15 ainda é 23h30 do dia 14 para o paciente.
 */
it('UC-FPN-02 · virada do dia: 23h30 de Brasília é o dia 14, não o 15 do servidor', function () {
    expect(DiaLocal::de(new DateTimeImmutable('2026-10-15T02:30:00Z')))->toBe('2026-10-14')
        ->and(DiaLocal::de(new DateTimeImmutable('2026-10-15T03:00:00Z')))->toBe('2026-10-15')
        ->and(DiaLocal::de(new DateTimeImmutable('2026-10-15T02:30:00Z'), 'UTC'))->toBe('2026-10-15');
});

it('UC-FPN-02 · adesão com hoje e check-in no fuso do paciente: o registro das 23h30 conta no dia certo', function () {
    $agora = new DateTimeImmutable('2026-10-15T02:45:00Z');           // 14/10 23h45 em Brasília
    $hoje = DiaLocal::de($agora);
    $checkinNoite = DiaLocal::de(new DateTimeImmutable('2026-10-15T02:30:00Z'));
    $checkinBorda = DiaLocal::de(new DateTimeImmutable('2026-10-08T02:30:00Z')); // 07/10 local

    $calc = new AdesaoCalculator();
    expect($hoje)->toBe('2026-10-14')
        ->and($calc->calcular([['date' => $checkinNoite, 'done_count' => 1]], 1, $hoje))->toBe(100)
        ->and($calc->calcular([['date' => $checkinBorda, 'done_count' => 1]], 1, $hoje))->toBe(0);
});

it('UC-FPN-02 · meta 7 com os 7 dias feitos = 100; dia inexistente é recusado', function () {
    $sete = array_map(fn ($d) => ['date' => "2026-10-{$d}", 'done_count' => 1], ['08', '09', '10', '11', '12', '13', '14']);

    expect((new AdesaoCalculator())->calcular($sete, 7, '2026-10-14'))->toBe(100)
        ->and(fn () => DiaLocal::parse('2026-02-30'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => DiaLocal::parse('14/10/2026'))->toThrow(InvalidArgumentException::class);
});

it('UC-FPN-03 · fila de alerta clínico separada da fila de abandono', function () {
    $det = new AtencaoDetector();

    expect($det->separar(['dor_subiu', 'dor_alta', 'sem_registro', 'sinal_de_alerta']))
        ->toBe(['clinico' => ['dor_subiu', 'dor_alta', 'sinal_de_alerta'], 'abandono' => ['sem_registro']])
        ->and($det->separar(['sem_registro']))->toBe(['clinico' => [], 'abandono' => ['sem_registro']])
        ->and($det->separar([]))->toBe(['clinico' => [], 'abandono' => []]);
});

it('UC-FPN-04 · programa novo depois de registros antigos: conta desde starts_at, não desde o registro velho', function () {
    $det = new AtencaoDetector();
    $antigo = [['date' => '2026-09-01', 'pain' => 2, 'red_flags' => []]];

    expect($det->motivos($antigo, '2026-10-12', '2026-10-14'))->toBe([])
        ->and($det->motivos($antigo, '2026-10-10', '2026-10-14'))->toBe(['sem_registro']);
});

it('UC-FPN-04 · check-in com data futura (relógio errado) não conta como registro', function () {
    expect((new AtencaoDetector())->motivos([['date' => '2026-10-20', 'pain' => 2]], '2026-10-01', '2026-10-14'))
        ->toBe(['sem_registro']);
});

it('UC-FPP-18 · dia repetido na entrada soma uma vez só', function () {
    $dia = ['date' => '2026-10-14', 'done_count' => 1, 'pain' => 2, 'red_flags' => []];

    expect((new SequenciaCalculator())->calcular([$dia, $dia], 3, '2026-10-14'))->toBe(['sequencia' => 1, 'em_pausa' => false]);
});

it('UC-FPP-18 · corrente morta não fica "em pausa"', function () {
    $pausaAntiga = ['date' => '2026-10-09', 'done_count' => 0, 'pain' => 9, 'red_flags' => []];

    expect((new SequenciaCalculator())->calcular([$pausaAntiga], 3, '2026-10-14'))->toBe(['sequencia' => 0, 'em_pausa' => false]);
});
