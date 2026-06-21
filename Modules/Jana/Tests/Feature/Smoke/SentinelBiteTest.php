<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Modules\Jana\Console\Commands\HealthCheckCommand;
use Modules\Jana\Console\Commands\JanaDriftSentinelCommand;
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

// ── 3. drift-sentinel: skip-guard HONESTO (sem OPENAI_API_KEY = dormant, não ruído) ──
//
// O canary semanal precisa de OPENAI_API_KEY pra rodar real. SEM a chave ele NÃO pode
// medir — antes do skip-guard (2026-06-20) isso virava falso "DRIFT 100%" + onFailure
// do cron toda semana. Agora sai DORMANT (exit 0, status=dormant) → ⊘ no agregador.
// Estes testes provam que (a) a regra do veredito é a esperada e (b) o exit NÃO morde
// o cron sem chave — pegando "o guard sumiu" OU "o guard pula sempre" num refactor.

test('drift-sentinel veredito: sem mock E sem chave = DORMANT', function () {
    expect(JanaDriftSentinelCommand::isDormant(false, null))->toBeTrue();
    expect(JanaDriftSentinelCommand::isDormant(false, ''))->toBeTrue();
});

test('drift-sentinel veredito: mock OU chave presente = NÃO dormant', function () {
    expect(JanaDriftSentinelCommand::isDormant(true, null))->toBeFalse();    // mock dispensa chave
    expect(JanaDriftSentinelCommand::isDormant(false, 'sk-xxx'))->toBeFalse(); // chave presente
});

test('drift-sentinel: SEM OPENAI_API_KEY sai DORMANT (exit 0 — não morde o cron)', function () {
    // config() vence env() e config:cache — fonte cache-safe que o guard usa.
    config(['openai.api_key' => null, 'services.openai.api_key' => null]);

    $exit = Artisan::call('jana:drift-sentinel', ['--json' => true]);
    $json = biteJson(Artisan::output());

    expect($exit)->toBe(0);                  // não estoura o onFailure do schedule
    expect($json['status'])->toBe('dormant');
    expect($json['ok'])->toBeTrue();
});

test('drift-sentinel --status: armed quando a chave existe, dormant quando não', function () {
    config(['openai.api_key' => 'sk-test-armed', 'services.openai.api_key' => null]);
    $exitArmed = Artisan::call('jana:drift-sentinel', ['--status' => true, '--json' => true]);
    $armed = biteJson(Artisan::output());
    expect($exitArmed)->toBe(0);
    expect($armed['status'])->toBe('armed');
    expect($armed['armed'])->toBeTrue();

    config(['openai.api_key' => null, 'services.openai.api_key' => null]);
    $exitDormant = Artisan::call('jana:drift-sentinel', ['--status' => true, '--json' => true]);
    $dormant = biteJson(Artisan::output());
    expect($exitDormant)->toBe(0);
    expect($dormant['status'])->toBe('dormant');
    expect($dormant['armed'])->toBeFalse();
});

test('drift-sentinel: ARMADO roda o veredito (mock 0.85 vs baseline 0.85 = ok)', function () {
    // Prova que o guard NÃO pula quando dá pra rodar (mock dispensa rede/chave).
    $exit = Artisan::call('jana:drift-sentinel', ['--mock' => true, '--json' => true]);
    $json = biteJson(Artisan::output());

    expect($exit)->toBe(0);
    expect($json['status'])->toBe('ok');
    expect($json['ok'])->toBeTrue();
});
