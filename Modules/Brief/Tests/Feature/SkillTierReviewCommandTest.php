<?php

declare(strict_types=1);

use Modules\Brief\Console\Commands\SkillTierReviewCommand as Cmd;

uses(Tests\TestCase::class);

/**
 * Parte B do T7 — loop telemetria→tier (ADR 0095). Testa a régua de regras, o
 * parser-catraca ("0 skills sem tier"), idempotência e PII-guard via os métodos
 * PUROS do command — sem DB nem filesystem (hermético = roda no CT 100 sem prod).
 *
 * @see Modules/Brief/Console/Commands/SkillTierReviewCommand.php
 * @see memory/decisions/0095-skills-tiers-convencao-interna.md
 */
function tierRow(array $over = []): array
{
    return array_merge([
        'name' => 'skill-x', 'tier' => 'B', 'sessions_30d' => 0, 'sessions_60d' => 0, 'triggers_90d' => 0,
        'total_sessions_30d' => 100, 'total_sessions_60d' => 100, 'usage_30d_pct' => 0.0, 'usage_60d_pct' => 0.0,
    ], $over);
}

it('cenário 1: B→A com uso ≥80% em 30d é SUGESTÃO que exige ADR (nunca auto)', function () {
    $s = Cmd::evaluateRules([tierRow(['usage_30d_pct' => 85.0, 'sessions_30d' => 42, 'total_sessions_30d' => 50])]);
    expect($s)->toHaveCount(1);
    expect([$s[0]['to'], $s[0]['auto'], $s[0]['needsAdr']])->toBe(['A', false, true]);
});

it('cenário 2: B→C com uso <10% em 60d é rebaixamento trivial auto-aplicável', function () {
    $s = Cmd::evaluateRules([tierRow(['usage_30d_pct' => 4.0, 'usage_60d_pct' => 5.0, 'sessions_60d' => 2, 'total_sessions_60d' => 40])]);
    expect($s)->toHaveCount(1);
    expect([$s[0]['to'], $s[0]['auto']])->toBe(['C', true]);
});

it('cenário 3: C→arquivar sem uso >90d é SUGESTÃO que exige ADR HISTORICAL', function () {
    $s = Cmd::evaluateRules([tierRow(['tier' => 'C', 'triggers_90d' => 0])]);
    expect($s)->toHaveCount(1);
    expect([$s[0]['to'], $s[0]['auto'], $s[0]['needsHistorical']])->toBe(['arquivar', false, true]);
});

it('cenário 4: tier A nunca gera sugestão automática (A→B só por decisão do Wagner)', function () {
    expect(Cmd::evaluateRules([
        tierRow(['tier' => 'A', 'usage_30d_pct' => 2.0, 'usage_60d_pct' => 1.0, 'total_sessions_60d' => 80]),
        tierRow(['tier' => 'A', 'usage_30d_pct' => 99.0]),
    ]))->toBe([]);
});

it('cenário 5: tier B saudável (uso 10%–80%) não gera sugestão', function () {
    expect(Cmd::evaluateRules([tierRow(['usage_30d_pct' => 40.0, 'usage_60d_pct' => 35.0, 'total_sessions_60d' => 90])]))->toBe([]);
});

it('guard anti-ruído: poucas sessões (< MIN_SESSIONS) não rebaixa mesmo com 0% de uso', function () {
    expect(Cmd::evaluateRules([tierRow(['usage_60d_pct' => 0.0, 'total_sessions_30d' => 3, 'total_sessions_60d' => 3])]))->toBe([]);
});

it('idempotência: evaluateRules é determinística e applyDemotion B→C é no-op na 2ª passada', function () {
    $rows = [tierRow(['usage_30d_pct' => 90.0, 'sessions_30d' => 45, 'total_sessions_30d' => 50]), tierRow(['tier' => 'C', 'triggers_90d' => 0])];
    expect(Cmd::evaluateRules($rows))->toBe(Cmd::evaluateRules($rows));

    $once = Cmd::applyDemotion("---\nname: foo\ntier: B\n---\nbody", 'B', 'C');
    expect($once)->toContain('tier: C')->not->toContain('tier: B');
    expect(Cmd::applyDemotion($once, 'B', 'C'))->toBe($once);
});

it('PII-guard: telemetria nunca seleciona context_payload e o relatório só tem agregados', function () {
    expect(Cmd::telemetrySelectColumns())->not->toContain('context_payload');

    $report = Cmd::renderReportSection([
        'now_str' => '2026-06-20 06:40', 'quarter' => '2026-Q2', 'since' => 90, 'apply' => false,
        'total_skills' => 70, 'tier_counts' => ['A' => 8, 'B' => 51, 'C' => 11],
        'total_sessions_30d' => 40, 'total_sessions_60d' => 75, 'events' => 900, 'skills_with_use' => 33,
        'suggestions' => [['skill' => 'foo', 'from' => 'B', 'to' => 'C', 'rule' => 'B→C uso <10% 60d', 'reason' => 'uso 5% em 60d', 'auto' => true, 'needsAdr' => false, 'needsHistorical' => false]],
        'applied' => [],
    ]);
    expect($report)->toContain('LGPD');
    expect(substr_count($report, 'context_payload'))->toBe(1); // só na frase-guard, nunca como dado
});

it('catraca parser: tier ausente vira null e quarterLabel resolve o trimestre da run', function () {
    expect(Cmd::parseTier("---\nname: foo\ntier: A\n---\nx"))->toBe('A');
    expect(Cmd::parseTier("---\ntier:B\n---"))->toBe('B');
    expect(Cmd::parseTier("---\ntier: c\n---"))->toBe('C');           // case-insensitive
    expect(Cmd::parseTier("---\nname: foo\nowner: wagner\n---\nx"))->toBeNull();
    expect(Cmd::quarterLabel(new DateTimeImmutable('2026-06-20')))->toBe('2026-Q2');
});
