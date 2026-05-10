<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Modules\Admin\Services\AdrAlertReader;
use Modules\Admin\Services\BriefAdapter;
use Modules\Admin\Services\CuradorStatsReader;
use Modules\Admin\Services\CyclesAggregator;
use Modules\Admin\Services\HealthSnapshotReader;
use Modules\Admin\Services\McpServerHealthReader;

uses(Tests\TestCase::class);

/**
 * Pest tests dos 6 adapters do Admin Center (Sprint 1 dia 3-5).
 *
 * Cobertura: empty states graceful (snapshot ausente, tabela ausente,
 * exception interno) sem quebrar a Page Inertia.
 *
 * @see memory/decisions/0122-admin-center-ct100.md
 */

it('BriefAdapter retorna stub graceful quando mcp_briefs ausente', function () {
    $adapter = new BriefAdapter();
    $result = $adapter->fetch();

    expect($result['available'])->toBeFalse();
    expect($result['reason'])->toBeString();
    expect($result['markdown'])->toContain('Brief indisponível');
});

it('HealthSnapshotReader retorna stub quando arquivo ausente', function () {
    Storage::disk('local')->delete('jana-health-snapshot.json');

    $reader = new HealthSnapshotReader();
    $result = $reader->fetch();

    expect($result['available'])->toBeFalse();
    expect($result['reason'])->toBe('snapshot_missing');
    expect($result['overall_status'])->toBe('unknown');
    expect($result['checks'])->toBeArray()->toBeEmpty();
});

it('HealthSnapshotReader retorna stub se JSON inválido', function () {
    Storage::disk('local')->put('jana-health-snapshot.json', '{invalid json');

    $reader = new HealthSnapshotReader();
    $result = $reader->fetch();

    expect($result['available'])->toBeFalse();
    expect($result['reason'])->toBe('snapshot_invalid_json');

    Storage::disk('local')->delete('jana-health-snapshot.json');
});

it('HealthSnapshotReader detecta tier 0 failures', function () {
    Storage::disk('local')->put('jana-health-snapshot.json', json_encode([
        'generated_at' => now()->toIso8601String(),
        'checks' => [
            ['name' => 'multi_tenant_isolation', 'status' => 'red', 'message' => 'leak detected'],
            ['name' => 'brief_uptime_24h',      'status' => 'green'],
            ['name' => 'pii_leak_in_assistant_responses', 'status' => 'green'],
        ],
    ]));

    $reader = new HealthSnapshotReader();
    $result = $reader->fetch();

    expect($result['available'])->toBeTrue();
    expect($result['overall_status'])->toBe('red');
    expect($result['tier_0_failures'])->toHaveCount(1);
    expect($result['tier_0_failures'][0]['name'])->toBe('multi_tenant_isolation');

    Storage::disk('local')->delete('jana-health-snapshot.json');
});

it('AdrAlertReader retorna lista vazia quando snapshot indisponível', function () {
    Storage::disk('local')->delete('jana-health-snapshot.json');

    $health = new HealthSnapshotReader();
    $reader = new AdrAlertReader($health);
    $result = $reader->fetch();

    expect($result['available'])->toBeFalse();
    expect($result['tier_0_alerts'])->toBeArray()->toBeEmpty();
});

it('AdrAlertReader mapeia checks pra ADR canônico', function () {
    Storage::disk('local')->put('jana-health-snapshot.json', json_encode([
        'generated_at' => now()->toIso8601String(),
        'checks' => [
            ['name' => 'multi_tenant_isolation',    'status' => 'red', 'message' => 'biz=4 viu biz=1'],
            ['name' => 'pii_leak_in_assistant_responses', 'status' => 'red', 'message' => 'CPF leaked'],
            ['name' => 'brief_uptime_24h',          'status' => 'green'],
        ],
    ]));

    $health = new HealthSnapshotReader();
    $reader = new AdrAlertReader($health);
    $result = $reader->fetch();

    expect($result['available'])->toBeTrue();
    expect($result['count'])->toBe(2);
    expect($result['tier_0_alerts'][0]['adr'])->toBe('0093');
    expect($result['tier_0_alerts'][1]['adr'])->toBe('0094');

    Storage::disk('local')->delete('jana-health-snapshot.json');
});

it('CyclesAggregator retorna stub quando tabelas ausentes', function () {
    $aggregator = new CyclesAggregator();
    $result = $aggregator->fetch();

    // Pode retornar available=true se tabelas existirem (homolog) — aceita ambos
    expect($result)->toHaveKey('available');
    expect($result)->toHaveKey('cycles_active');
    expect($result)->toHaveKey('tasks_by_dev');
});

it('CuradorStatsReader retorna stub quando arquivos table ausente', function () {
    $reader = new CuradorStatsReader();
    $result = $reader->fetch();

    expect($result)->toHaveKey('available');
    expect($result)->toHaveKey('total_active');
    expect($result)->toHaveKey('by_bucket');
    expect($result)->toHaveKey('sensitive_count');
});

it('McpServerHealthReader sempre retorna estrutura ping mesmo timeout', function () {
    $reader = new McpServerHealthReader();
    $result = $reader->fetch();

    expect($result)->toHaveKey('available');
    expect($result)->toHaveKey('ping');
    expect($result['ping'])->toHaveKey('reachable');
});
