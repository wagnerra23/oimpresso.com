<?php

// @covers-us US-COPI-138

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Modules\Jana\Console\Commands\HealthCheckCommand;

uses(Tests\TestCase::class);

/** Roda o health-check e devolve só o check do heartbeat (JSON do comando REAL). */
function langfuseCheck(): ?array
{
    Artisan::call('jana:health-check', ['--json' => true]);
    $out = Artisan::output();
    $start = strpos($out, '{');
    $json = $start === false ? [] : json_decode(substr($out, (int) $start), true);

    return collect($json['checks'] ?? [])->firstWhere('name', 'langfuse_trace_uptime_24h');
}

/** Configura credencial fake + resposta fake da API pública do Langfuse. */
function fakeLangfuse(array $response, int $status = 200): void
{
    config([
        'langfuse.enabled' => true,
        'langfuse.host' => 'https://langfuse.test',
        'langfuse.public_key' => 'pk-test',
        'langfuse.secret_key' => 'sk-test',
    ]);
    Http::fake(['*/api/public/traces*' => Http::response($response, $status)]);
}

/**
 * Heartbeat da telemetria LLM — US-COPI-138 (dead man's switch do Langfuse).
 *
 * Contrato defendido aqui (vem da US + do incidente, NÃO da implementação — a
 * lápide §5 2026-06-05 proíbe teste derivado do código): o Langfuse está LIVE
 * desde 2026-07-02 e não tinha heartbeat; se parasse de receber trace ninguém
 * descobria — foi assim que um buraco de 7 semanas passou (grade de réguas
 * 2026-07-17). O monitor que existia (`observability_pipeline` do
 * jana:system-audit) mede a VIA (/api/public/health == 200): servidor de pé
 * recebendo ZERO trace responde 200 e pinta verde. Este mede o FLUXO.
 *
 * A lógica vive em evaluateTraceUptime (estática, sem HTTP nem relógio real —
 * mesmo padrão de evaluateCanaryFreshness). O último teste prova que o check
 * está REGISTRADO no jana:health-check e é HARD: mecanismo correto que ninguém
 * invoca é defesa de mentira (lápide §5 2026-07-09).
 *
 * @see Modules/Jana/Console/Commands/HealthCheckCommand.php::checkLangfuseTraceUptime24h
 * @see Modules/Jana/Services/Telemetry/LangfuseClient.php (lado da emissão)
 */

test('não-configurado em dev/CI (env não-prod) pula silencioso', function () {
    $r = HealthCheckCommand::evaluateTraceUptime(false, false, null, 1, isProd: false);
    expect($r['ok'])->toBeTrue();
    expect($r['state'])->toBe('nao-configurado');
    expect($r['advisory'] ?? false)->toBeFalse();
});

// Residual fechado (US-COPI-138): flag caída num deploy que resetou o .env é um modo
// de falha real (classe ext-sodium/classmap). Advisory — visível, NÃO pagina (pode ser
// desligamento intencional). O que distingue de dev é SÓ o ambiente.
test('desligado EM PRODUÇÃO = advisory (visível, não derruba o cron)', function () {
    $r = HealthCheckCommand::evaluateTraceUptime(false, false, null, 1, isProd: true);
    expect($r['ok'])->toBeFalse();          // aparece como não-ok na tabela
    expect($r['state'])->toBe('desligado-prod');
    expect($r['advisory'])->toBeTrue();     // ...mas advisory: allChecksOk NÃO derruba por ele
});

// A distinção acidente-vs-intenção não existe sem estado histórico; por isso é advisory,
// não duro. Mesmo desligado, um flanco fica coberto: o observability_pipeline do
// jana:system-audit exige LANGFUSE_HOST setado.

test('trace chegando nas últimas 24h = ok (fluxo vivo)', function () {
    $r = HealthCheckCommand::evaluateTraceUptime(true, true, 137);
    expect($r['ok'])->toBeTrue();
    expect($r['state'])->toBe('vivo');
    expect($r['count'])->toBe(137);
});

test('1 trace basta (espelha o threshold de brief_uptime_24h)', function () {
    $r = HealthCheckCommand::evaluateTraceUptime(true, true, 1);
    expect($r['ok'])->toBeTrue();
    expect($r['state'])->toBe('vivo');
});

// O buraco de 7 semanas: Langfuse de pé (200 no /health) porém MUDO.
test('ZERO traces em 24h = ALERTA (o buraco de 7 semanas)', function () {
    $r = HealthCheckCommand::evaluateTraceUptime(true, true, 0);
    expect($r['ok'])->toBeFalse();
    expect($r['state'])->toBe('mudo');
    expect($r['count'])->toBe(0);
});

test('API do Langfuse inacessível = ALERTA (sem resposta não há prova de fluxo)', function () {
    $r = HealthCheckCommand::evaluateTraceUptime(true, false, null);
    expect($r['ok'])->toBeFalse();
    expect($r['state'])->toBe('inacessivel');
});

// Honestidade da causa: shape da API mudou ≠ "zero traces". Um monitor que
// apodrece deve dizer que apodreceu, não inventar o número que não leu.
test('respondeu sem meta.totalItems = ilegível (NÃO finge que é zero)', function () {
    $r = HealthCheckCommand::evaluateTraceUptime(true, true, null);
    expect($r['ok'])->toBeFalse();
    expect($r['state'])->toBe('ilegivel');
    expect($r['state'])->not->toBe('mudo');
});

// ── Fiação do check (controle-negativo do caminho HTTP real) ──────────────────
//
// Os testes acima provam a REGRA (evaluateTraceUptime) e o de baixo prova a
// PRESENÇA. Nenhum dos dois pega uma fiação quebrada — um checkLangfuseTraceUptime24h
// que sempre passasse configured=false ficaria verde eterno com os dois. Estes dois
// exercitam o método REAL contra uma API fake: verde quando chega trace, VERMELHO
// quando o Langfuse responde 200 e mudo (o buraco de 7 semanas, ponta a ponta).

test('FIAÇÃO: Langfuse responde 200 porém MUDO (0 traces) = check VERMELHO', function () {
    fakeLangfuse(['data' => [], 'meta' => ['totalItems' => 0]]);

    $check = langfuseCheck();
    expect($check)->not->toBeNull();
    expect($check['ok'])->toBeFalse();
    expect($check['value'])->toBe(0);
    expect($check['message'])->toContain('ZERO traces');
});

test('FIAÇÃO: Langfuse recebendo traces = check VERDE (não morde à toa)', function () {
    fakeLangfuse(['data' => [['id' => 'abc']], 'meta' => ['totalItems' => 42]]);

    $check = langfuseCheck();
    expect($check)->not->toBeNull();
    expect($check['ok'])->toBeTrue();
    expect($check['value'])->toBe(42);
});

test('langfuse_trace_uptime_24h está registrado no jana:health-check e é hard no fluxo', function () {
    // Sem credencial o check pula (ok=true) e NÃO faz HTTP real — determinístico no CI.
    // O env de teste NÃO é prod, então cai em 'nao-configurado' (não 'desligado-prod').
    config(['langfuse.enabled' => false]);

    Artisan::call('jana:health-check', ['--json' => true]);
    $out = Artisan::output();
    $start = strpos($out, '{');
    $json = $start === false ? [] : json_decode(substr($out, (int) $start), true);

    $check = collect($json['checks'] ?? [])->firstWhere('name', 'langfuse_trace_uptime_24h');
    expect($check)->not->toBeNull('check langfuse_trace_uptime_24h ausente do jana:health-check');
    // No caso de FLUXO (mudo/inacessível/ilegível) o check é HARD — derruba exit + ALERT.
    // Só o caso desligado-prod é advisory (coberto pelo teste puro acima). Em CI (env de
    // teste) o campo advisory sai false.
    expect($check['advisory'] ?? false)->toBeFalse();
    expect($check)->toHaveKeys(['name', 'ok', 'value', 'threshold', 'message']);
});
