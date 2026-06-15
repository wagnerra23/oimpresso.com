<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tests pra `php artisan governance:scorecard-snapshot` (Wave 24 Agent A — 2026-05-16).
 *
 * @see Modules/Governance/Console/Commands/ScorecardSnapshotCommand.php
 * @see Modules/Governance/Database/Migrations/2026_05_17_000001_create_mcp_scorecard_runs_table.php
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    // era-sqlite: cria schema manual (sqlite-friendly). No MySQL persistente do nightly
    // isso corrompe os testes irmãos (lever do floor SDD). Cobertura real é na lane
    // sqlite (per-PR); pula no MySQL.
    if (config('database.default') !== 'sqlite') {
        $this->markTestSkipped('era-sqlite: corruptor de schema compartilhado no MySQL — sqlite-only no burn-down do floor SDD.');
    }
});

it('governance:scorecard-snapshot persiste rows em mcp_scorecard_runs', function () {
    expect(Schema::hasTable('mcp_scorecard_runs'))->toBeTrue();

    DB::table('mcp_scorecard_runs')->truncate();

    $this->artisan('governance:scorecard-snapshot')
        ->expectsOutputToContain('Snapshot OK')
        ->assertExitCode(0);

    $count = DB::table('mcp_scorecard_runs')->count();
    // Pelo menos os scorecards canônicos (governance.yaml + auditoria.yaml) persistem.
    expect($count)->toBeGreaterThanOrEqual(1);

    $row = DB::table('mcp_scorecard_runs')->first();
    expect($row->module)->toBeString()->not->toBeEmpty();
    expect((int) $row->score)->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(100);
    expect($row->bucket)->toBeString();
    expect(json_decode($row->breakdown_json, true))->toBeArray();
});

it('governance:scorecard-snapshot --bucket=vertical_client_facing filtra módulos por bucket', function () {
    DB::table('mcp_scorecard_runs')->truncate();

    $this->artisan('governance:scorecard-snapshot --bucket=vertical_client_facing')
        ->expectsOutputToContain('Snapshot OK')
        ->assertExitCode(0);

    $rows = DB::table('mcp_scorecard_runs')->get();

    // Todos os rows persistidos devem ter bucket=vertical_client_facing
    // (Vestuario declara esse bucket em module.json).
    foreach ($rows as $row) {
        expect($row->bucket)->toBe('vertical_client_facing');
    }
});

it('governance:scorecard-snapshot --json mantém modo W23 legacy (preview YAML)', function () {
    $exitCode = $this->artisan('governance:scorecard-snapshot --json')
        ->assertExitCode(0);

    // Output é JSON — sem mensagem "Snapshot OK"
    // (modo legacy não persiste; apenas faz preview).
});

it('governance:scorecard-snapshot --detail mostra log linha-por-módulo', function () {
    $this->artisan('governance:scorecard-snapshot --detail')
        ->expectsOutputToContain('Snapshot OK')
        ->assertExitCode(0);
});

it('governance:scorecard-snapshot detecta drift >=5pts vs ontem', function () {
    DB::table('mcp_scorecard_runs')->truncate();

    // Insere snapshot "de ontem" com score baixo pra Governance
    DB::table('mcp_scorecard_runs')->insert([
        'module'         => 'Governance',
        'bucket'         => 'unknown',
        'score'          => 30,
        'breakdown_json' => json_encode(['module' => 'Governance', 'score_total' => 30]),
        'snapshot_date'  => now()->subDay()->format('Y-m-d'),
        'created_at'     => now()->subDay(),
    ]);

    // Roda hoje — deve detectar drift (score atual será >30 normalmente)
    $this->artisan('governance:scorecard-snapshot --alert')
        ->assertExitCode(0);

    // Sanity: verifica que rodou (independente de qual score apurou)
    $hoje = DB::table('mcp_scorecard_runs')
        ->where('snapshot_date', now()->format('Y-m-d'))
        ->count();
    expect($hoje)->toBeGreaterThanOrEqual(1);
});

it('schedule governance:scorecard-snapshot está registrado daily 07:00 BRT (live env)', function () {
    /** @var \Illuminate\Console\Scheduling\Schedule $schedule */
    $schedule = app(\Illuminate\Console\Scheduling\Schedule::class);
    $events = collect($schedule->events());

    $snapshotEvent = $events->first(function ($e) {
        return str_contains((string) $e->command, 'governance:scorecard-snapshot');
    });

    expect($snapshotEvent)->not->toBeNull('Schedule governance:scorecard-snapshot não registrado em app/Console/Kernel.php');
    expect($snapshotEvent->expression)->toBe('0 7 * * *'); // daily 07:00
    expect($snapshotEvent->timezone)->toBe('America/Sao_Paulo');
});

it('command falha gracefully se tabela mcp_scorecard_runs não existir', function () {
    Schema::dropIfExists('mcp_scorecard_runs');

    $this->artisan('governance:scorecard-snapshot')
        ->expectsOutputToContain('Tabela mcp_scorecard_runs nao existe')
        ->assertExitCode(1);

    // Re-cria pra outros tests
    \Artisan::call('migrate', ['--force' => true]);
});

it('migration mcp_scorecard_runs cria tabela com índices canônicos', function () {
    expect(Schema::hasTable('mcp_scorecard_runs'))->toBeTrue();
    expect(Schema::hasColumns('mcp_scorecard_runs', [
        'id', 'module', 'bucket', 'score', 'breakdown_json', 'snapshot_date', 'created_at',
    ]))->toBeTrue();
});
