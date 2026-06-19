<?php

declare(strict_types=1);

use Modules\Jana\Console\Commands\HealthCheckCommand;

uses(Tests\TestCase::class);

/**
 * PR-D do keystone distiller-módulo-verdade (ADR 0291 D-D · peça 3).
 *
 * Testa o gêmeo DURO de freshness do jana:health-check pela sua parte PURA
 * (HealthCheckCommand::distillerFreshnessStats) — sem tocar o filesystem, igual
 * a parseLessonLedger. O check runtime usa "hoje"; aqui injetamos $now fixo.
 *
 * Limite: distilled_at exatamente 7d atrás = fresco (alarme só dispara > 7d).
 */

$NOW = '2026-06-19';

/** Monta o conteúdo de uma BRIEFING.md com (ou sem) carimbo distilled_at. */
function briefingContent(?string $distilledAt): string
{
    $fm = $distilledAt ? "distilled_at: \"{$distilledAt}\"\n" : '';

    return "---\nslug: porta\n{$fm}---\n# Porta\nestado atual...\n";
}

test('sem distilled_at → zero carimbadas, zero stale', function () use ($NOW) {
    $r = HealthCheckCommand::distillerFreshnessStats([briefingContent(null), briefingContent(null)], $NOW);
    expect($r['total'])->toBe(2);
    expect($r['stamped'])->toBe(0);
    expect($r['stale'])->toBe(0);
});

test('porta fresca (1d) não é stale', function () use ($NOW) {
    $r = HealthCheckCommand::distillerFreshnessStats([briefingContent('2026-06-18')], $NOW);
    expect($r['stamped'])->toBe(1);
    expect($r['stale'])->toBe(0);
});

test('limite exato de 7d ainda é fresco', function () use ($NOW) {
    $r = HealthCheckCommand::distillerFreshnessStats([briefingContent('2026-06-12')], $NOW);
    expect($r['stale'])->toBe(0);
});

test('porta > 7d é stale e reporta stalest_days', function () use ($NOW) {
    $r = HealthCheckCommand::distillerFreshnessStats([briefingContent('2026-05-01')], $NOW);
    expect($r['stamped'])->toBe(1);
    expect($r['stale'])->toBe(1);
    expect($r['stalest_days'])->toBe(49);
});

test('mistura conta só carimbadas e só stale, com a mais velha', function () use ($NOW) {
    $r = HealthCheckCommand::distillerFreshnessStats([
        briefingContent('2026-06-18'), // 1d  — fresca
        briefingContent('2026-06-11'), // 8d  — stale
        briefingContent('2026-05-01'), // 49d — stale
        briefingContent(null),         // sem carimbo — cobertura pendente
    ], $NOW);
    expect($r['total'])->toBe(4);
    expect($r['stamped'])->toBe(3);
    expect($r['stale'])->toBe(2);
    expect($r['stalest_days'])->toBe(49);
});

test('ignora conteúdo sem linha distilled_at no frontmatter', function () use ($NOW) {
    $r = HealthCheckCommand::distillerFreshnessStats(['# só um título sem frontmatter'], $NOW);
    expect($r['stamped'])->toBe(0);
    expect($r['total'])->toBe(1);
});
