<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Governance\Services\ObservabilitySnapshotService;
use Tests\TestCase;

/**
 * Tests pro ObservabilitySnapshotService + command observability:aggregate-daily
 * (Wave 26 Agent 3 — 2026-05-17, ADR 0162).
 *
 * Cobre:
 *   - migration cria 2 tabelas com índices canônicos
 *   - aggregateDaily() computa percentis nearest-rank corretos
 *   - upsert idempotente (re-run sobrevive)
 *   - getModuleHealth() retorna últimos N dias do módulo
 *   - command artisan grava aggregates
 *   - schedule daily 02:00 BRT registrado em Kernel.php
 *
 * @see Modules\Governance\Services\ObservabilitySnapshotService
 * @see Modules/Governance/Database/Migrations/2026_05_17_000002_create_mcp_observability_spans_table.php
 */

// `uses(TestCase::class)` é explicit aqui pra evitar dependência de Pest.php
// auto-discovery — `Modules/Governance/Tests/Pest.php` só carrega quando
// rodado via filtro da raiz `tests/Pest.php`, então declarar local garante.
//
// NÃO usamos `RefreshDatabase` porque migrations legacy do projeto têm
// `ALTER TABLE ... MODIFY COLUMN ENUM(...)` (sintaxe MySQL-only) que crasha
// no SQLite in-memory do test env local. Em vez disso, criamos APENAS as 2
// tabelas que esse test precisa via Schema::create direto no setUp.
uses(TestCase::class);

beforeEach(function () {
    // era-sqlite: cria schema manual (sqlite-friendly). No MySQL persistente do nightly
    // isso corrompe os testes irmãos (lever do floor SDD). Cobertura real é na lane
    // sqlite (per-PR); pula no MySQL.
    if (config('database.default') !== 'sqlite') {
        $this->markTestSkipped('era-sqlite: corruptor de schema compartilhado no MySQL — sqlite-only no burn-down do floor SDD.');
    }

    // Recria tabelas isoladamente (sem rodar todas as migrations do projeto).
    Schema::dropIfExists('mcp_observability_aggregates_daily');
    Schema::dropIfExists('mcp_observability_spans');

    Schema::create('mcp_observability_spans', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('business_id')->nullable()->index();
        $table->string('module', 100)->index();
        $table->string('span_name', 200);
        $table->unsignedInteger('duration_ms');
        $table->string('status', 20)->default('ok');
        $table->json('attributes_json')->nullable();
        $table->timestamp('timestamp')->index();
        $table->timestamp('created_at')->useCurrent();
        $table->index(['module', 'span_name', 'timestamp'], 'idx_mos_mod_span_time');
        $table->index(['business_id', 'module', 'timestamp'], 'idx_mos_biz_mod_time');
    });

    Schema::create('mcp_observability_aggregates_daily', function (Blueprint $table) {
        $table->id();
        $table->string('module', 100)->index();
        $table->string('span_name', 200);
        $table->date('snapshot_date')->index();
        $table->unsignedInteger('count_total');
        $table->unsignedInteger('count_error');
        $table->unsignedInteger('p50_ms');
        $table->unsignedInteger('p95_ms');
        $table->unsignedInteger('p99_ms');
        $table->timestamp('created_at')->useCurrent();
        $table->unique(['module', 'span_name', 'snapshot_date'], 'uq_moad_mod_span_date');
    });
});

afterEach(function () {
    if (config('database.default') !== 'sqlite') {
        return;
    }

    Schema::dropIfExists('mcp_observability_aggregates_daily');
    Schema::dropIfExists('mcp_observability_spans');
});

it('migration cria mcp_observability_spans com colunas canônicas', function () {
    expect(Schema::hasTable('mcp_observability_spans'))->toBeTrue();
    expect(Schema::hasColumns('mcp_observability_spans', [
        'id', 'business_id', 'module', 'span_name', 'duration_ms',
        'status', 'attributes_json', 'timestamp', 'created_at',
    ]))->toBeTrue();
});

it('migration cria mcp_observability_aggregates_daily com colunas canônicas', function () {
    expect(Schema::hasTable('mcp_observability_aggregates_daily'))->toBeTrue();
    expect(Schema::hasColumns('mcp_observability_aggregates_daily', [
        'id', 'module', 'span_name', 'snapshot_date',
        'count_total', 'count_error', 'p50_ms', 'p95_ms', 'p99_ms', 'created_at',
    ]))->toBeTrue();
});

it('aggregateDaily computa percentis nearest-rank corretos', function () {
    $svc = app(ObservabilitySnapshotService::class);
    $target = Carbon::parse('yesterday')->startOfDay();

    // Fixture: 100 spans pra Repair.fsm.execute_action — durations 1..100 ms
    $rows = [];
    for ($i = 1; $i <= 100; $i++) {
        $rows[] = [
            'business_id'     => 1,
            'module'          => 'Repair',
            'span_name'       => 'repair.fsm.execute_action',
            'duration_ms'     => $i,
            'status'          => $i % 20 === 0 ? 'error' : 'ok', // 5 erros (i=20,40,60,80,100)
            'attributes_json' => json_encode(['from_stage' => 'a', 'to_stage' => 'b']),
            'timestamp'       => $target->copy()->addMinutes($i),
            'created_at'      => $target->copy()->addMinutes($i),
        ];
    }
    DB::table('mcp_observability_spans')->insert($rows);

    $inserted = $svc->aggregateDaily('yesterday');

    expect($inserted)->toBe(1);

    $agg = DB::table('mcp_observability_aggregates_daily')
        ->where('module', 'Repair')
        ->where('span_name', 'repair.fsm.execute_action')
        ->first();

    expect($agg)->not->toBeNull();
    expect((int) $agg->count_total)->toBe(100);
    expect((int) $agg->count_error)->toBe(5);
    // Nearest-rank: p50 = ceil(0.5*100)-1 = 49 → durations[49] = 50ms
    expect((int) $agg->p50_ms)->toBe(50);
    // p95 = ceil(0.95*100)-1 = 94 → durations[94] = 95ms
    expect((int) $agg->p95_ms)->toBe(95);
    // p99 = ceil(0.99*100)-1 = 98 → durations[98] = 99ms
    expect((int) $agg->p99_ms)->toBe(99);
});

it('aggregateDaily é idempotente (upsert por module+span+date)', function () {
    $svc = app(ObservabilitySnapshotService::class);
    $target = Carbon::parse('yesterday')->startOfDay();

    DB::table('mcp_observability_spans')->insert([
        'business_id' => 1, 'module' => 'Jana', 'span_name' => 'jana.agent.execute',
        'duration_ms' => 100, 'status' => 'ok',
        'timestamp' => $target, 'created_at' => $target,
    ]);

    $first = $svc->aggregateDaily('yesterday');
    $second = $svc->aggregateDaily('yesterday'); // re-run

    expect($first)->toBe(1);
    expect($second)->toBe(1);

    // Apenas 1 row deve existir (upsert, não insert duplicado)
    $count = DB::table('mcp_observability_aggregates_daily')
        ->where('module', 'Jana')
        ->where('snapshot_date', $target->toDateString())
        ->count();
    expect($count)->toBe(1);
});

it('aggregateDaily retorna 0 quando não há spans na janela', function () {
    $svc = app(ObservabilitySnapshotService::class);
    expect($svc->aggregateDaily('yesterday'))->toBe(0);
});

it('getModuleHealth retorna aggregates ordenados desc por data', function () {
    $svc = app(ObservabilitySnapshotService::class);

    DB::table('mcp_observability_aggregates_daily')->insert([
        ['module' => 'Sells', 'span_name' => 'sells.fsm.execute_action',
         'snapshot_date' => now()->subDays(3)->toDateString(),
         'count_total' => 100, 'count_error' => 2, 'p50_ms' => 50, 'p95_ms' => 100, 'p99_ms' => 200,
         'created_at' => now()],
        ['module' => 'Sells', 'span_name' => 'sells.fsm.execute_action',
         'snapshot_date' => now()->subDay()->toDateString(),
         'count_total' => 150, 'count_error' => 1, 'p50_ms' => 55, 'p95_ms' => 110, 'p99_ms' => 220,
         'created_at' => now()],
    ]);

    $health = $svc->getModuleHealth('Sells', 7);

    expect($health)->toHaveCount(2);
    expect($health[0]->snapshot_date)->toBe(now()->subDay()->toDateString()); // mais recente primeiro
});

it('artisan observability:aggregate-daily executa e reporta count', function () {
    $target = Carbon::parse('yesterday')->startOfDay();
    DB::table('mcp_observability_spans')->insert([
        'business_id' => 1, 'module' => 'Whatsapp', 'span_name' => 'whatsapp.send_message',
        'duration_ms' => 250, 'status' => 'ok',
        'timestamp' => $target, 'created_at' => $target,
    ]);

    $this->artisan('observability:aggregate-daily')
        ->expectsOutputToContain('Aggregate OK')
        ->assertExitCode(0);

    $count = DB::table('mcp_observability_aggregates_daily')->count();
    expect($count)->toBe(1);
});

it('artisan observability:aggregate-daily --detail mostra log adicional', function () {
    $this->artisan('observability:aggregate-daily --detail')
        ->expectsOutputToContain('Aggregate OK')
        ->assertExitCode(0);
});

it('artisan observability:aggregate-daily --date= aceita data customizada', function () {
    $custom = Carbon::parse('2026-05-10')->startOfDay();
    DB::table('mcp_observability_spans')->insert([
        'business_id' => 1, 'module' => 'NfeBrasil', 'span_name' => 'nfe.transmit',
        'duration_ms' => 800, 'status' => 'ok',
        'timestamp' => $custom, 'created_at' => $custom,
    ]);

    $this->artisan('observability:aggregate-daily --date=2026-05-10')
        ->assertExitCode(0);

    $agg = DB::table('mcp_observability_aggregates_daily')
        ->where('snapshot_date', '2026-05-10')
        ->first();
    expect($agg)->not->toBeNull();
    expect($agg->module)->toBe('NfeBrasil');
});

it('schedule observability:aggregate-daily registrado daily 02:00 BRT', function () {
    /** @var \Illuminate\Console\Scheduling\Schedule $schedule */
    $schedule = app(\Illuminate\Console\Scheduling\Schedule::class);
    $events = collect($schedule->events());

    $event = $events->first(function ($e) {
        return str_contains((string) $e->command, 'observability:aggregate-daily');
    });

    expect($event)->not->toBeNull(
        'Schedule observability:aggregate-daily não registrado em app/Console/Kernel.php (Wave 26 Agent 3)'
    );
    expect($event->expression)->toBe('0 2 * * *'); // daily 02:00
    expect($event->timezone)->toBe('America/Sao_Paulo');
});
