<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tests pra `php artisan module:grade-snapshot` (cron daily 06:05 BRT).
 *
 * @see Modules/Governance/Console/Commands/ModuleGradeSnapshotCommand.php
 * @see Modules/Governance/Database/Migrations/2026_05_16_120000_create_mcp_module_grades_history_table.php
 */

uses(RefreshDatabase::class);

it('module:grade-snapshot cria rows em mcp_module_grades_history (1 por módulo detectado)', function () {
    expect(Schema::hasTable('mcp_module_grades_history'))->toBeTrue();

    DB::table('mcp_module_grades_history')->truncate();

    $this->artisan('module:grade-snapshot')
        ->expectsOutputToContain('Snapshot OK')
        ->assertExitCode(0);

    $count = DB::table('mcp_module_grades_history')->count();

    // ≥30 módulos detectados em Modules/ (target: ~34 — tolerância pra adições)
    expect($count)->toBeGreaterThanOrEqual(30);

    // Sanity: cada row tem campos obrigatórios + score 0-100 válido
    $row = DB::table('mcp_module_grades_history')->first();
    expect($row->module)->toBeString()->not->toBeEmpty();
    expect((int) $row->score)->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(100);
    expect($row->bucket)->toBeString()->not->toBeEmpty();
    expect($row->snapshot_at)->not->toBeNull();
    // JSON dimensions parseável
    expect(json_decode($row->dimensions, true))->toBeArray();
});

it('module:grade-snapshot é idempotente — rodar 2x simplesmente acumula histórico', function () {
    DB::table('mcp_module_grades_history')->truncate();

    $this->artisan('module:grade-snapshot')->assertExitCode(0);
    $afterFirst = DB::table('mcp_module_grades_history')->count();

    $this->artisan('module:grade-snapshot')->assertExitCode(0);
    $afterSecond = DB::table('mcp_module_grades_history')->count();

    // Segunda execução duplica volume (cada run = snapshot novo, append-only)
    expect($afterSecond)->toBe($afterFirst * 2);

    // Mesmo módulo aparece em 2 timestamps distintos (sanity)
    $firstModule = DB::table('mcp_module_grades_history')->value('module');
    $countPerModule = DB::table('mcp_module_grades_history')
        ->where('module', $firstModule)
        ->count();
    expect($countPerModule)->toBe(2);
});

it('schedule module:grade-snapshot está registrado daily 06:05 BRT (live env)', function () {
    /** @var \Illuminate\Console\Scheduling\Schedule $schedule */
    $schedule = app(\Illuminate\Console\Scheduling\Schedule::class);
    $events = collect($schedule->events());

    $snapshotEvent = $events->first(function ($e) {
        return str_contains((string) $e->command, 'module:grade-snapshot');
    });

    expect($snapshotEvent)->not->toBeNull('Schedule pra module:grade-snapshot não está registrado em app/Console/Kernel.php');
    expect($snapshotEvent->expression)->toBe('5 6 * * *'); // daily 06:05
    expect($snapshotEvent->timezone)->toBe('America/Sao_Paulo');
});

it('module:grade-snapshot --detail mostra log linha por módulo', function () {
    $this->artisan('module:grade-snapshot --detail')
        ->expectsOutputToContain('Snapshot OK')
        ->assertExitCode(0);
});
