<?php

declare(strict_types=1);
// Cobre, na regra pura, UC-FPN-03 e UC-FPN-04 (Fisio/Painel.casos.md).

use Modules\Fisio\Services\AtencaoDetector;

/**
 * Detector "precisa de atenção" — serviço PURO (sem DB, "hoje" por parâmetro). Sem TestCase.
 *
 * Contrato (handoff F1 + atualização de escopo):
 *   'dor_subiu'      — entre os 2 últimos registros COM dor informada, a dor subiu ≥ 2.
 *                      ("e persiste" = é medido nos 2 MAIS RECENTES; se o último voltou a cair,
 *                       não entra.)
 *   'dor_alta'       — último registro com dor ≥ limiar (padrão 7; o do programa, se definido).
 *   'sem_registro'   — programa ativo e ≥ 4 dias desde o último check-in; sem check-in nenhum,
 *                      conta desde programa.starts_at.
 *   'sinal_de_alerta'— o último check-in tem red_flags preenchido.
 *   Sem programa ativo: só 'sinal_de_alerta' e as regras de dor valem; 'sem_registro' nunca.
 *   Saída: lista de motivos na ordem acima, sem repetição; vazia = não entra na fila.
 *
 * Assinatura assumida:
 *   AtencaoDetector::motivos(list<array{date:string, pain:?int, red_flags?:list<string>}> $checkins,
 *                            ?string $programaStartsAt, string $hoje, int $limiarDor = 7): list<string>
 *   ($programaStartsAt null = sem programa ativo)
 */
function uatMotivos(array $checkins, ?string $inicio = '2026-10-01', int $limiar = 7): array
{
    return (new AtencaoDetector())->motivos($checkins, $inicio, '2026-10-14', $limiar);
}

function uatCk(string $date, ?int $pain, array $flags = []): array
{
    return ['date' => $date, 'pain' => $pain, 'red_flags' => $flags];
}

it('UC-FPN-03 · dor subiu exatamente 2 entre os 2 últimos → dor_subiu', function () {
    expect(uatMotivos([uatCk('2026-10-12', 3), uatCk('2026-10-13', 5)]))->toBe(['dor_subiu']);
});

it('UC-FPN-03 · subir 1, manter ou cair → nada', function () {
    expect(uatMotivos([uatCk('2026-10-12', 3), uatCk('2026-10-13', 4)]))->toBe([])
        ->and(uatMotivos([uatCk('2026-10-12', 5), uatCk('2026-10-13', 5)]))->toBe([])
        ->and(uatMotivos([uatCk('2026-10-12', 6), uatCk('2026-10-13', 2)]))->toBe([]);
});

it('UC-FPN-03 · "persiste": subiu antes mas o último voltou a cair → não entra', function () {
    expect(uatMotivos([uatCk('2026-10-11', 2), uatCk('2026-10-12', 6), uatCk('2026-10-13', 3)]))->toBe([]);
});

it('UC-FPN-03 · ordem da entrada não importa — o detector ordena por data', function () {
    expect(uatMotivos([uatCk('2026-10-13', 5), uatCk('2026-10-12', 3)]))->toBe(['dor_subiu']);
});

it('UC-FPN-03 · registros sem dor informada são pulados na comparação', function () {
    expect(uatMotivos([uatCk('2026-10-11', 2), uatCk('2026-10-12', null), uatCk('2026-10-13', 4)]))->toBe(['dor_subiu']);
});

it('UC-FPN-03 · um registro só não tem "subida"', function () {
    expect(uatMotivos([uatCk('2026-10-13', 5)]))->toBe([]);
});

it('UC-FPN-03 · dor ≥ limiar no último → dor_alta; limiar do programa vale', function () {
    expect(uatMotivos([uatCk('2026-10-13', 7)]))->toBe(['dor_alta'])
        ->and(uatMotivos([uatCk('2026-10-13', 6)]))->toBe([])
        ->and(uatMotivos([uatCk('2026-10-13', 5)], '2026-10-01', 5))->toBe(['dor_alta']);
});

it('UC-FPN-03 · subiu e ficou alta → os dois motivos, na ordem do contrato', function () {
    expect(uatMotivos([uatCk('2026-10-12', 4), uatCk('2026-10-13', 8)]))->toBe(['dor_subiu', 'dor_alta']);
});

it('UC-FPN-04 · ≥4 dias sem registro com programa ativo → sem_registro; 3 dias não', function () {
    expect(uatMotivos([uatCk('2026-10-10', 2)]))->toBe(['sem_registro'])
        ->and(uatMotivos([uatCk('2026-10-11', 2)]))->toBe([]);
});

it('UC-FPN-04 · nunca registrou: conta desde starts_at', function () {
    expect(uatMotivos([], '2026-10-10'))->toBe(['sem_registro'])
        ->and(uatMotivos([], '2026-10-11'))->toBe([])
        ->and(uatMotivos([], '2026-10-20'))->toBe([]); // programa futuro
});

it('UC-FPN-04 · sem programa ativo nunca dá sem_registro', function () {
    expect(uatMotivos([uatCk('2026-09-01', 2)], null))->toBe([])
        ->and(uatMotivos([], null))->toBe([]);
});

it('UC-FPN-04 · sinal de alerta no último check-in → sinal_de_alerta, mesmo com dor 0 e sem programa', function () {
    expect(uatMotivos([uatCk('2026-10-14', 0, ['febre'])]))->toBe(['sinal_de_alerta'])
        ->and(uatMotivos([uatCk('2026-10-14', 0, ['febre'])], null))->toBe(['sinal_de_alerta']);
});

it('UC-FPN-04 · sinal só num check-in antigo (já superado por um limpo) não fica preso na fila', function () {
    expect(uatMotivos([uatCk('2026-10-12', 1, ['febre']), uatCk('2026-10-13', 1)]))->toBe([]);
});

it('UC-FPN-04 · tudo junto: ordem fixa e sem repetição', function () {
    expect(uatMotivos([uatCk('2026-10-05', 3), uatCk('2026-10-09', 9, ['queda'])]))
        ->toBe(['dor_subiu', 'dor_alta', 'sem_registro', 'sinal_de_alerta']);
});
