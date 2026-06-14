<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tests pra `governance:sdd-scorecard-snapshot` (GT-G7 — ADR 0275).
 * Fixtures via --input/--baseline (CI safe — sem node, sem repo scan).
 *
 * @see Modules/Governance/Console/Commands/SddScorecardSnapshotCommand.php
 */

uses(Tests\TestCase::class, DatabaseTransactions::class);

function sddG7ScorecardFixture(float $ghost = 27.0, float $door = 63.9): string
{
    $path = sys_get_temp_dir().'/sdd-g7-scorecard.json';
    file_put_contents($path, json_encode(['metrics' => [
        'ghost_count'          => ['status' => 'measured', 'value' => $ghost, 'direction' => 'down', 'target' => 0],
        'front_door_coverage'  => ['status' => 'measured', 'value' => $door, 'direction' => 'up', 'target' => 100],
        'full_suite_pass_rate' => ['status' => 'not_yet_measured', 'value' => null, 'direction' => 'up', 'target' => 100],
    ]]));

    return $path;
}

function sddG7BaselineFixture(): string
{
    $path = sys_get_temp_dir().'/sdd-g7-baseline.json';
    file_put_contents($path, json_encode(['metrics' => [
        'ghost_count'          => ['status' => 'measured', 'value' => 27, 'direction' => 'down', 'armed' => true],
        'front_door_coverage'  => ['status' => 'measured', 'value' => 63.9, 'direction' => 'up', 'armed' => true],
        'full_suite_pass_rate' => ['status' => 'not_yet_measured', 'value' => null, 'direction' => 'up', 'armed' => false],
    ]]));

    return $path;
}

it('persiste 1 row/dia com composta v1 (média das armadas) + resumo no payload', function () {
    expect(Schema::hasTable('mcp_sdd_scorecard_history'))->toBeTrue();

    $this->artisan('governance:sdd-scorecard-snapshot', [
        '--input'    => sddG7ScorecardFixture(),
        '--baseline' => sddG7BaselineFixture(),
        '--date'     => '2026-06-12',
    ])->expectsOutputToContain('Snapshot SDD OK')->assertExitCode(0);

    $row = DB::table('mcp_sdd_scorecard_history')->where('snapshot_date', '2026-06-12')->first();
    expect($row)->not->toBeNull();
    // ghost 27→27 (0% do caminho) + door 63.9→63.9 (0%) → composta 0.0, k=2
    expect((float) $row->composta)->toBe(0.0);

    $payload = json_decode($row->payload, true);
    expect($payload['composta_k'])->toBe(2)
        ->and($payload['vivas'])->toBe(2)
        ->and($payload['metrics_total'])->toBe(3)
        ->and($payload['alerts'])->toBe([])
        ->and($payload['scorecard']['metrics'])->toHaveKey('ghost_count');
});

it('é idempotente por dia — re-run substitui a row e registra alerta de regressão armada', function () {
    $args = ['--baseline' => sddG7BaselineFixture(), '--date' => '2026-06-12'];

    $this->artisan('governance:sdd-scorecard-snapshot', $args + ['--input' => sddG7ScorecardFixture()])->assertExitCode(0);
    $this->artisan('governance:sdd-scorecard-snapshot', $args + ['--input' => sddG7ScorecardFixture()])->assertExitCode(0);
    expect(DB::table('mcp_sdd_scorecard_history')->count())->toBe(1);

    // ghost_count regrediu (27 → 30, armada "só desce") — re-run do dia substitui + alerta
    $this->artisan('governance:sdd-scorecard-snapshot', $args + ['--input' => sddG7ScorecardFixture(ghost: 30.0)])->assertExitCode(0);
    expect(DB::table('mcp_sdd_scorecard_history')->count())->toBe(1);

    $payload = json_decode(DB::table('mcp_sdd_scorecard_history')->value('payload'), true);
    expect($payload['alerts'])->toHaveCount(1)
        ->and($payload['alerts'][0])->toContain('ghost_count');
});

it('schedule governance:sdd-scorecard-snapshot registrado daily 07:10 BRT', function () {
    $schedule = app(\Illuminate\Console\Scheduling\Schedule::class);
    $event = collect($schedule->events())
        ->first(fn ($e) => str_contains((string) $e->command, 'governance:sdd-scorecard-snapshot'));

    expect($event)->not->toBeNull('Schedule não registrado em app/Console/Kernel.php');
    expect($event->expression)->toBe('10 7 * * *');
    expect($event->timezone)->toBe('America/Sao_Paulo');
});
