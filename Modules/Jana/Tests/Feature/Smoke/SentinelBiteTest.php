<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Modules\Jana\Console\Commands\HealthCheckCommand;
use Modules\Jana\Console\Commands\SystemAuditCommand;

uses(Tests\TestCase::class);

/**
 * Bite-tests dos sentinelas de governança (auditoria de sentinelas 2026-06-20).
 *
 * Problema "a suite mente": jana:health-check e jana:system-audit MORDEM no código
 * (return FAILURE quando um check duro falha), mas nenhum teste PROVAVA a mordida —
 * o smoke (JanaHealthCheckTest) só assere exit ∈ {0,1} e o system-audit tinha ZERO
 * testes. Um refactor poderia silenciar o exit code sem nenhum teste ficar vermelho.
 *
 * Estratégia DB-agnóstica (o schema completo só roda em MySQL no CI, não SQLite):
 *   1. UNIT do veredito (allChecksOk) — prova a REGRA check→ok, incluindo a sutileza
 *      advisory, sem tocar DB.
 *   2. INTEGRAÇÃO — roda o comando REAL e exige exit == veredito(checks do JSON).
 *      Pega "sempre retorna 0" (ou "sempre 1") em qualquer estado de DB.
 */

// ── 1. UNIT do veredito (determinístico, sem DB) ─────────────────────────────

test('health-check veredito: check duro ok=false DERRUBA o gate', function () {
    expect(HealthCheckCommand::allChecksOk([
        ['name' => 'a', 'ok' => true],
        ['name' => 'b', 'ok' => false], // duro falho
    ]))->toBeFalse();
});

test('health-check veredito: tudo ok = passa', function () {
    expect(HealthCheckCommand::allChecksOk([
        ['name' => 'a', 'ok' => true],
        ['name' => 'b', 'ok' => true],
    ]))->toBeTrue();
});

test('health-check veredito: advisory ok=false NÃO derruba o gate', function () {
    expect(HealthCheckCommand::allChecksOk([
        ['name' => 'a', 'ok' => true],
        ['name' => 'charter_missing', 'ok' => false, 'advisory' => true],
    ]))->toBeTrue();
});

test('system-audit veredito: qualquer check ok=false derruba (sem advisory)', function () {
    expect(SystemAuditCommand::allChecksOk([
        ['name' => 'a', 'ok' => true],
        ['name' => 'b', 'ok' => false],
    ]))->toBeFalse();

    expect(SystemAuditCommand::allChecksOk([
        ['name' => 'a', 'ok' => true],
    ]))->toBeTrue();
});

// ── 2. INTEGRAÇÃO: exit code == veredito (prova que NÃO é constante) ──────────

/** Extrai o bloco JSON do output do comando (pode haver linhas de debug antes). */
function biteJson(string $output): array
{
    $start = strpos($output, '{');
    expect($start)->not->toBeFalse('Output não contém JSON');

    return json_decode(substr($output, (int) $start), true);
}

test('jana:health-check: exit code BATE com o veredito dos checks', function () {
    $exit = Artisan::call('jana:health-check', ['--json' => true]);
    $json = biteJson(Artisan::output());

    $esperaFalha = collect($json['checks'])
        ->contains(fn ($c) => ! ($c['ok'] ?? false) && ! ($c['advisory'] ?? false));

    expect($exit)->toBe($esperaFalha ? 1 : 0);
});

test('jana:system-audit: exit code BATE com o veredito dos checks', function () {
    // Evita HTTP real no check de observability (rápido + sem flakiness de rede).
    putenv('LANGFUSE_HOST');
    unset($_ENV['LANGFUSE_HOST'], $_SERVER['LANGFUSE_HOST']);

    $exit = Artisan::call('jana:system-audit', ['--json' => true]);
    $json = biteJson(Artisan::output());

    $esperaFalha = collect($json['checks'])->contains(fn ($c) => ! ($c['ok'] ?? false));

    expect($exit)->toBe($esperaFalha ? 1 : 0);
});

test('jana:system-audit registrado no artisan list', function () {
    Artisan::call('list');
    expect(Artisan::output())->toContain('jana:system-audit');
});
