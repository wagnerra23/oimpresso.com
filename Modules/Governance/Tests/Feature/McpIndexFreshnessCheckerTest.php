<?php

declare(strict_types=1);

use Carbon\Carbon;
use Modules\Governance\Contracts\DriftChecker;
use Modules\Governance\Services\Checkers\McpIndexFreshnessChecker;
use Modules\Governance\Services\DriftFinding;

uses(Tests\TestCase::class);

/**
 * McpIndexFreshnessChecker (Onda 1 — sentinela transporte) — índice MCP
 * (mcp_memory_documents) defasado vs último commit em git memory/. Pega o sync
 * IndexarMemoryGitParaDb falhando CALADO. analisar() é puro/testável (sem DB/git).
 */

it('implementa DriftChecker + registrado em governance.drift_checkers', function () {
    expect(new McpIndexFreshnessChecker())->toBeInstanceOf(DriftChecker::class)
        ->and((new McpIndexFreshnessChecker())->name())->toBe('mcp_index_freshness')
        ->and((new McpIndexFreshnessChecker())->severity())->toBe('high')
        ->and((new McpIndexFreshnessChecker())->enforcement())->toBe('warn')
        ->and((new McpIndexFreshnessChecker())->cadence())->toBe('daily')
        ->and((array) config('governance.drift_checkers'))->toContain(McpIndexFreshnessChecker::class);
});

it('analisar: índice mais novo que git memory/ → clean', function () {
    $git = Carbon::parse('2026-06-20T10:00:00Z');
    $index = Carbon::parse('2026-06-20T10:30:00Z'); // 30min depois — fresco
    expect((new McpIndexFreshnessChecker())->analisar($index, $git, 6))->toBeEmpty();
});

it('analisar: índice atrás do git dentro do limite → clean', function () {
    $git = Carbon::parse('2026-06-20T10:00:00Z');
    $index = Carbon::parse('2026-06-20T06:00:00Z'); // 4h atrás, limite 6h → ok
    expect((new McpIndexFreshnessChecker())->analisar($index, $git, 6))->toBeEmpty();
});

it('analisar: índice atrás do git > limite → drifted high', function () {
    $git = Carbon::parse('2026-06-20T10:00:00Z');
    $index = Carbon::parse('2026-06-19T20:00:00Z'); // 14h atrás, limite 6h → drift
    $f = (new McpIndexFreshnessChecker())->analisar($index, $git, 6);
    expect($f)->toHaveCount(1)
        ->and($f[0])->toBeInstanceOf(DriftFinding::class)
        ->and($f[0]->severity)->toBe('high')
        ->and($f[0]->message)->toContain('Índice MCP defasado')
        ->and($f[0]->message)->toContain('IndexarMemoryGitParaDb')
        ->and($f[0]->evidence['lag_hours'])->toBe(14);
});

it('analisar: git indisponível → info, sem throw', function () {
    $index = Carbon::parse('2026-06-20T10:00:00Z');
    $f = (new McpIndexFreshnessChecker())->analisar($index, null, 6);
    expect($f)->toHaveCount(1)
        ->and($f[0]->severity)->toBe('info')
        ->and($f[0]->message)->toContain('git indisponível');
});

it('analisar: índice vazio / DB indisponível → info', function () {
    $git = Carbon::parse('2026-06-20T10:00:00Z');
    $f = (new McpIndexFreshnessChecker())->analisar(null, $git, 6);
    expect($f)->toHaveCount(1)
        ->and($f[0]->severity)->toBe('info')
        ->and($f[0]->message)->toContain('Índice MCP vazio');
});
