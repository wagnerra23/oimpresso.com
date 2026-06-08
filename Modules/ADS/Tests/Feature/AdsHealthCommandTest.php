<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

uses(Tests\TestCase::class);

/**
 * ads:health — Wave 23 D9.c (Bucket functional_horizontal).
 *
 * Cobertura mínima:
 *   1. Command registrado em artisan list
 *   2. --json retorna JSON válido com schema esperado
 *   3. Output contém os 6 checks canônicos Brain B autonomous
 *
 * Multi-tenant Tier 0 (ADR 0093) — health command é read-only cross-tenant.
 *
 * @see Modules/ADS/Console/Commands/AdsHealthCommand.php
 * @see memory/decisions/0155-module-grade-v3.md D9.c
 */

it('ads:health command registrado em artisan list', function () {
    $list = Artisan::all();
    expect($list)->toHaveKey('ads:health');
});

it('ads:health --json retorna estrutura válida', function () {
    $code = Artisan::call('ads:health', ['--json' => true]);
    $output = trim(Artisan::output());

    expect($code)->toBe(0); // sem --alert sempre 0

    $data = json_decode($output, true);
    expect($data)->toBeArray();
    expect($data)->toHaveKey('module');
    expect($data['module'])->toBe('ADS');
    expect($data)->toHaveKey('summary');
    expect($data['summary']['total'])->toBe(6);
});

it('ads:health contém os 6 checks canônicos Brain B', function () {
    Artisan::call('ads:health', ['--json' => true]);
    $data = json_decode(trim(Artisan::output()), true);

    $names = collect($data['checks'])->pluck('name')->toArray();

    foreach ([
        'ads_decisions_table_present',
        'ads_skills_table_present',
        'brainb_processed_24h',
        'decisions_pending_review',
        'policy_blocked_ratio_24h',
        'skills_unpublished_overdue',
    ] as $expected) {
        expect($names)->toContain($expected);
    }
});
