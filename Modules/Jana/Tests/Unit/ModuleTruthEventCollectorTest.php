<?php

declare(strict_types=1);

use Modules\Jana\Services\Memoria\ModuleTruthEventCollector;

uses(Tests\TestCase::class);

/**
 * PR-B do keystone distiller-módulo-verdade (ADR 0291 D-A).
 *
 * A decisão de "o que distilar" é PURA e testável: dada uma lista de eventos
 * (sessions/handoffs/PRs/audits) + a porta (`lastDistilledAt`) + a janela,
 * devolve o subconjunto relevante ao módulo, ordenado. SEM LLM, SEM filesystem,
 * SEM DB — só a regra de seleção. Espelha a separação ProfileDistiller ×
 * ContextSnapshotService (o cálculo separado da chamada cara).
 *
 * Regra da janela (ADR 0291 D-A): "desde o último distilled_at OU 30 dias, o
 * que for MAIOR" — janela maior = começa mais cedo = pega mais eventos.
 */

$NOW = '2026-06-19';

/** Helper compacto pra montar evento de teste. */
function ev(string $type, string $ref, ?string $date, array $modules, string $title = ''): array
{
    return ['type' => $type, 'ref' => $ref, 'date' => $date, 'modules' => $modules, 'title' => $title];
}

// ---------------------------------------------------------------------------
// windowStart — a regra "o que for maior"
// ---------------------------------------------------------------------------

test('windowStart usa 30d quando nunca destilou (lastDistilledAt null)', function () use ($NOW) {
    expect(ModuleTruthEventCollector::windowStart(null, 30, $NOW))->toBe('2026-05-20');
});

test('windowStart estende pra trás até lastDistilledAt quando ele é mais velho que a janela', function () use ($NOW) {
    // destilado há 60d → janela "desde destilado" (60d) é MAIOR que 30d → começa em 2026-04-20
    expect(ModuleTruthEventCollector::windowStart('2026-04-20', 30, $NOW))->toBe('2026-04-20');
});

test('windowStart mantém o piso de 30d quando lastDistilledAt é recente', function () use ($NOW) {
    // destilado há 5d → 30d é MAIOR → mantém o piso 2026-05-20 (não 2026-06-14)
    expect(ModuleTruthEventCollector::windowStart('2026-06-14', 30, $NOW))->toBe('2026-05-20');
});

// ---------------------------------------------------------------------------
// isRelevant — pertinência por módulo (case-insensitive)
// ---------------------------------------------------------------------------

test('isRelevant casa o módulo independente de caixa', function () {
    $e = ev('session', 's1.md', '2026-06-10', ['Financeiro', 'Sells']);
    expect(ModuleTruthEventCollector::isRelevant($e, 'financeiro'))->toBeTrue();
    expect(ModuleTruthEventCollector::isRelevant($e, 'SELLS'))->toBeTrue();
});

test('isRelevant é falso quando o módulo não está citado ou a chave falta', function () {
    expect(ModuleTruthEventCollector::isRelevant(ev('pr', '#1', '2026-06-10', ['Crm']), 'Financeiro'))->toBeFalse();
    expect(ModuleTruthEventCollector::isRelevant(['type' => 'pr', 'ref' => '#1'], 'Financeiro'))->toBeFalse();
});

// ---------------------------------------------------------------------------
// select — a seleção completa
// ---------------------------------------------------------------------------

test('select pega evento relevante dentro da janela', function () use ($NOW) {
    $events = [ev('session', 's1.md', '2026-06-10', ['Financeiro'], 'fix baixa')];
    $out = ModuleTruthEventCollector::select($events, 'Financeiro', null, 30, $NOW);
    expect($out)->toHaveCount(1);
    expect($out[0]['ref'])->toBe('s1.md');
});

test('select exclui evento que não cita o módulo', function () use ($NOW) {
    $events = [ev('session', 's1.md', '2026-06-10', ['Crm'])];
    expect(ModuleTruthEventCollector::select($events, 'Financeiro', null, 30, $NOW))->toBe([]);
});

test('select exclui evento relevante mais velho que a janela (sem lastDistilledAt)', function () use ($NOW) {
    $events = [ev('handoff', 'h1.md', '2026-04-01', ['Financeiro'])]; // > 30d
    expect(ModuleTruthEventCollector::select($events, 'Financeiro', null, 30, $NOW))->toBe([]);
});

test('select inclui evento entre lastDistilledAt antigo e o piso de 30d (janela estendida)', function () use ($NOW) {
    $events = [ev('audit', 'a1.md', '2026-04-25', ['Financeiro'])]; // fora dos 30d, mas após destilado 04-20
    $out = ModuleTruthEventCollector::select($events, 'Financeiro', '2026-04-20', 30, $NOW);
    expect($out)->toHaveCount(1);
    expect($out[0]['ref'])->toBe('a1.md');
});

test('select inclui evento relevante sem data (undated não é silenciosamente descartado)', function () use ($NOW) {
    $events = [ev('pr', '#42', null, ['Financeiro'])];
    $out = ModuleTruthEventCollector::select($events, 'Financeiro', null, 30, $NOW);
    expect($out)->toHaveCount(1);
});

test('select ordena do mais recente pro mais antigo, com undated no topo', function () use ($NOW) {
    $events = [
        ev('session', 'velho.md', '2026-06-01', ['Financeiro']),
        ev('session', 'novo.md', '2026-06-15', ['Financeiro']),
        ev('pr', 'sem-data', null, ['Financeiro']),
    ];
    $out = ModuleTruthEventCollector::select($events, 'Financeiro', null, 30, $NOW);
    expect(array_column($out, 'ref'))->toBe(['sem-data', 'novo.md', 'velho.md']);
});

test('select respeita o teto maxEvents mantendo os mais recentes', function () use ($NOW) {
    $events = [
        ev('session', 'd1', '2026-06-10', ['Financeiro']),
        ev('session', 'd2', '2026-06-12', ['Financeiro']),
        ev('session', 'd3', '2026-06-18', ['Financeiro']),
    ];
    $out = ModuleTruthEventCollector::select($events, 'Financeiro', null, 30, $NOW, 2);
    expect(array_column($out, 'ref'))->toBe(['d3', 'd2']);
});

test('select devolve vazio quando não há evento do módulo', function () use ($NOW) {
    expect(ModuleTruthEventCollector::select([], 'Financeiro', null, 30, $NOW))->toBe([]);
});
