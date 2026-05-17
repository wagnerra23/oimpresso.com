<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

uses(Tests\TestCase::class);

/**
 * assetmanagement:health — Wave 23 D9.c (Bucket functional_horizontal).
 *
 * Cobertura mínima:
 *   1. Command registrado em artisan list
 *   2. --json retorna JSON válido com schema esperado (timestamp + module + checks + summary)
 *   3. Exit code respeita semântica (sem --alert sempre 0)
 *   4. Output contém os 7 checks canônicos
 *
 * Multi-tenant Tier 0 (ADR 0093) — health command é read-only cross-tenant,
 * sem PII em output. Testa via Artisan::call().
 *
 * @see Modules/AssetManagement/Console/Commands/AssetManagementHealthCommand.php
 * @see memory/decisions/0155-module-grade-v3.md D9.c
 */

it('assetmanagement:health command registrado em artisan list', function () {
    $list = Artisan::all();
    expect($list)->toHaveKey('assetmanagement:health');
});

it('assetmanagement:health --json retorna estrutura válida', function () {
    $code = Artisan::call('assetmanagement:health', ['--json' => true]);
    $output = trim(Artisan::output());

    expect($code)->toBe(0); // sem --alert sempre 0

    $data = json_decode($output, true);
    expect($data)->toBeArray();
    expect($data)->toHaveKey('timestamp');
    expect($data)->toHaveKey('module');
    expect($data['module'])->toBe('AssetManagement');
    expect($data)->toHaveKey('checks');
    expect($data)->toHaveKey('summary');
    expect($data['summary'])->toHaveKeys(['ok', 'warn', 'fail', 'total']);
    expect($data['summary']['total'])->toBe(7);
});

it('assetmanagement:health contém os 7 checks canônicos', function () {
    Artisan::call('assetmanagement:health', ['--json' => true]);
    $output = Artisan::output();
    $data = json_decode(trim($output), true);

    $names = collect($data['checks'])->pluck('name')->toArray();

    foreach ([
        'assets_table_present',
        'allocations_table_present',
        'maintenances_table_present',
        'assets_active_24h',
        'orphan_allocations',
        'orphan_maintenances',
        'warranties_expired_overdue',
    ] as $expected) {
        expect($names)->toContain($expected);
    }
});

it('assetmanagement:health respeita --alert exit code semantics', function () {
    // Sem --alert SEMPRE retorna 0 (info-only)
    $code = Artisan::call('assetmanagement:health');
    expect($code)->toBe(0);
});
