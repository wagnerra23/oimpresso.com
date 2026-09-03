<?php

declare(strict_types=1);

namespace Modules\Jana\Tests\Feature\Mcp;

use Modules\Jana\Console\Commands\HealthCheckCommand;

uses(\Tests\TestCase::class);

/**
 * `ct100_reachability` — UM veredito no lugar de N sintomas soltos.
 *
 * CONTRATO (âncora — proibições: "teste sem âncora de contrato = rejeitado"):
 *   - ADR 0062 (Hostinger != CT 100): mcp, langfuse e meilisearch moram na MESMA
 *     máquina; os três fora ao mesmo tempo é UM fato, não três.
 *   - `proibicoes.md` §5 2026-07-09 ("duplica régua consolidada"): o consolidador
 *     NÃO sonda — correlaciona o que os donos de cada sinal já mediram no run.
 *
 * Função pura, sem DB e sem rede — mesmo padrão de `allChecksOk` e
 * `evaluateTraceUptime`, que este arquivo acompanha.
 */

it('consolida 2+ servicos fora num veredito so e rebaixa os sintomas', function () {
    $checks = [
        ['name' => 'memoria_recall_backend', 'ok' => false, 'message' => 'ALERTA: recall inacessível',
            'ct100' => ['service' => 'mcp', 'reachable' => false]],
        ['name' => 'langfuse_trace_uptime_24h', 'ok' => false, 'message' => 'ALERTA: API inacessível',
            'ct100' => ['service' => 'langfuse', 'reachable' => false]],
        ['name' => 'multi_tenant_isolation', 'ok' => true, 'message' => 'ok'],
    ];

    $out = HealthCheckCommand::consolidateCt100($checks, [
        'service' => 'meilisearch/sync', 'reachable' => false, 'since' => '2026-08-27T03:10:00+00:00',
    ]);

    $veredito = collect($out)->firstWhere('name', 'ct100_reachability');
    expect($veredito['ok'])->toBeFalse()
        ->and($veredito['message'])->toContain('desde 2026-08-27')
        ->and($veredito['message'])->toContain('3 de 3');

    // Os sintomas explicados param de pedir investigação própria...
    expect($out[0]['advisory'])->toBeTrue()->and($out[1]['advisory'])->toBeTrue();
    // ...e o check que nada tem a ver com CT 100 fica intocado (controle negativo).
    expect($out[2])->not->toHaveKey('advisory');
});

it('NAO consolida quando so UM servico esta fora (controle negativo)', function () {
    $checks = [
        ['name' => 'memoria_recall_backend', 'ok' => false, 'message' => 'ALERTA',
            'ct100' => ['service' => 'mcp', 'reachable' => false]],
        ['name' => 'langfuse_trace_uptime_24h', 'ok' => true, 'message' => 'vivo',
            'ct100' => ['service' => 'langfuse', 'reachable' => true]],
    ];

    $out = HealthCheckCommand::consolidateCt100($checks, ['service' => 'meilisearch/sync', 'reachable' => null, 'since' => null]);

    // Serviço sozinho fora é problema DELE: o dono segue duro, sem rebaixamento.
    expect(collect($out)->firstWhere('name', 'ct100_reachability')['ok'])->toBeTrue()
        ->and($out[0])->not->toHaveKey('advisory');
});

it('pula quando nenhuma perna foi medida — ausencia de medicao nao vira estado', function () {
    // Dev/CI sem token/keys: `reachable => null`. Um consolidador que lesse null
    // como "fora" inventaria uma queda do CT 100 em toda máquina de dev.
    $checks = [
        ['name' => 'memoria_recall_backend', 'ok' => true, 'message' => 'Skipped',
            'ct100' => ['service' => 'mcp', 'reachable' => null]],
    ];

    $out = HealthCheckCommand::consolidateCt100($checks, ['service' => 'meilisearch/sync', 'reachable' => null, 'since' => null]);

    $veredito = collect($out)->firstWhere('name', 'ct100_reachability');
    expect($veredito['ok'])->toBeTrue()->and($veredito['value'])->toBe('n/a');
});

it('nao inventa data quando o cron do sync nao registrou o inicio', function () {
    $checks = [
        ['name' => 'memoria_recall_backend', 'ok' => false, 'message' => 'ALERTA',
            'ct100' => ['service' => 'mcp', 'reachable' => false]],
        ['name' => 'langfuse_trace_uptime_24h', 'ok' => false, 'message' => 'ALERTA',
            'ct100' => ['service' => 'langfuse', 'reachable' => false]],
    ];

    $out = HealthCheckCommand::consolidateCt100($checks, ['service' => 'meilisearch/sync', 'reachable' => null, 'since' => null]);

    // "desde agora" seria falso todo dia às 06:00 — quem tem resolução de 5min é
    // o cron, e sem registro dele o check admite que não sabe.
    $msg = collect($out)->firstWhere('name', 'ct100_reachability')['message'];
    expect($msg)->toContain('início não registrado')->and($msg)->not->toContain('desde 20');
});

it('o veredito DURO derruba o exit code do health-check', function () {
    // Bite-test do acoplamento: se ct100_reachability nascesse advisory, ele
    // apareceria na tabela e não pagina ninguém.
    $checks = [
        ['name' => 'memoria_recall_backend', 'ok' => false, 'message' => 'x',
            'ct100' => ['service' => 'mcp', 'reachable' => false]],
        ['name' => 'langfuse_trace_uptime_24h', 'ok' => false, 'message' => 'y',
            'ct100' => ['service' => 'langfuse', 'reachable' => false]],
    ];

    $out = HealthCheckCommand::consolidateCt100($checks, ['service' => 'meilisearch/sync', 'reachable' => false, 'since' => null]);

    expect(HealthCheckCommand::allChecksOk($out))->toBeFalse();
});
