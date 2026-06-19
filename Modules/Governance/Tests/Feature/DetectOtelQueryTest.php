<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Governance\Services\ScopedScorecardEvaluator;
use Tests\TestCase;

/**
 * DetectOtelQueryTest — Wave 26 Agent 5 (2026-05-17).
 *
 * Cobre upgrade de `ScopedScorecardEvaluator::detectOtelQuery()` Wave 26:
 *   - Path canon: `mcp_observability_aggregates_daily` (populado por
 *     ObservabilitySnapshotService Wave 26 Agent 3 — não toca aqui).
 *   - Path legacy: `mcp_observability_spans` (US-WA-083 raw spans) — fallback.
 *   - Pass-through fail-safe: nenhuma tabela → true (collector OTel não ativo).
 *   - Pass-through: sem dados na janela → true (não pontua, não bloqueia).
 *   - p99 ≤ threshold → true (passou); p99 > threshold → false (violou SLA).
 *   - window_days respeitado (filtro snapshot_date >= now-N days).
 *   - p99_threshold_ms explícito > regex de expect legacy ("p99 < 2000") > default 2000.
 *
 * Multi-tenant: agregado per-module global (não per-business). Cada span individual
 * tem business_id via spanBiz — aqui agrupamos.
 *
 * @see Modules\Governance\Services\ScopedScorecardEvaluator::detectOtelQuery
 */
uses(TestCase::class);

beforeEach(function () {
    // era-sqlite: cria schema manual (sqlite-friendly). No MySQL persistente do nightly
    // isso corrompe os testes irmãos (lever do floor SDD). Cobertura real é na lane
    // sqlite (per-PR); pula no MySQL.
    if (config('database.default') !== 'sqlite') {
        $this->markTestSkipped('era-sqlite: corruptor de schema compartilhado no MySQL — sqlite-only no burn-down do floor SDD.');
    }

    $this->eval = new ScopedScorecardEvaluator();
});

afterEach(function () {
    if (config('database.default') !== 'sqlite') {
        return;
    }

    // Limpa tabela de teste se criada (sqlite in-memory normalmente já drop ao fim).
    if (Schema::hasTable('mcp_observability_aggregates_daily')) {
        Schema::drop('mcp_observability_aggregates_daily');
    }
    if (Schema::hasTable('mcp_observability_spans')) {
        Schema::drop('mcp_observability_spans');
    }
});

// ============================================================================
// Pass-through fail-safe quando NENHUMA tabela presente
// ============================================================================

it('pass-through true quando nem aggregates_daily nem spans existem', function () {
    // Garante que ambas ausentes (afterEach drop)
    expect(Schema::hasTable('mcp_observability_aggregates_daily'))->toBeFalse();
    expect(Schema::hasTable('mcp_observability_spans'))->toBeFalse();

    $result = $this->eval->detectOtelQuery('Vestuario', [
        'window_days'      => 7,
        'p99_threshold_ms' => 2000,
    ]);

    expect($result)->toBeTrue();
});

// ============================================================================
// Path canônico Wave 26 — mcp_observability_aggregates_daily
// ============================================================================

it('aggregates_daily: p99 abaixo do threshold → true', function () {
    Schema::create('mcp_observability_aggregates_daily', function ($t) {
        $t->id();
        $t->string('module');
        $t->date('snapshot_date');
        $t->unsignedInteger('p99_ms');
        $t->timestamps();
    });

    DB::table('mcp_observability_aggregates_daily')->insert([
        'module'        => 'Sells',
        'snapshot_date' => now()->subDay()->toDateString(),
        'p99_ms'        => 800,
        'created_at'    => now(),
        'updated_at'    => now(),
    ]);

    $result = $this->eval->detectOtelQuery('Sells', [
        'window_days'      => 7,
        'p99_threshold_ms' => 2000,
    ]);

    expect($result)->toBeTrue();
});

it('aggregates_daily: p99 acima do threshold → false (violou SLA)', function () {
    Schema::create('mcp_observability_aggregates_daily', function ($t) {
        $t->id();
        $t->string('module');
        $t->date('snapshot_date');
        $t->unsignedInteger('p99_ms');
        $t->timestamps();
    });

    DB::table('mcp_observability_aggregates_daily')->insert([
        'module'        => 'Sells',
        'snapshot_date' => now()->subDay()->toDateString(),
        'p99_ms'        => 3500,
        'created_at'    => now(),
        'updated_at'    => now(),
    ]);

    $result = $this->eval->detectOtelQuery('Sells', [
        'window_days'      => 7,
        'p99_threshold_ms' => 2000,
    ]);

    expect($result)->toBeFalse();
});

it('aggregates_daily: p99 exatamente igual ao threshold → true (<= é OK)', function () {
    Schema::create('mcp_observability_aggregates_daily', function ($t) {
        $t->id();
        $t->string('module');
        $t->date('snapshot_date');
        $t->unsignedInteger('p99_ms');
        $t->timestamps();
    });

    DB::table('mcp_observability_aggregates_daily')->insert([
        'module'        => 'Repair',
        'snapshot_date' => now()->toDateString(),
        'p99_ms'        => 2000,
        'created_at'    => now(),
        'updated_at'    => now(),
    ]);

    $result = $this->eval->detectOtelQuery('Repair', [
        'window_days'      => 7,
        'p99_threshold_ms' => 2000,
    ]);

    expect($result)->toBeTrue();
});

it('aggregates_daily: sem dados na janela → pass-through true (não pontua, não bloqueia)', function () {
    Schema::create('mcp_observability_aggregates_daily', function ($t) {
        $t->id();
        $t->string('module');
        $t->date('snapshot_date');
        $t->unsignedInteger('p99_ms');
        $t->timestamps();
    });

    // Dado existe MAS de 60 dias atrás — fora da janela de 7 dias
    DB::table('mcp_observability_aggregates_daily')->insert([
        'module'        => 'Sells',
        'snapshot_date' => now()->subDays(60)->toDateString(),
        'p99_ms'        => 9999,
        'created_at'    => now()->subDays(60),
        'updated_at'    => now()->subDays(60),
    ]);

    $result = $this->eval->detectOtelQuery('Sells', [
        'window_days'      => 7,
        'p99_threshold_ms' => 2000,
    ]);

    expect($result)->toBeTrue();
});

it('aggregates_daily: window_days=30 INCLUI dado de 15 dias atrás', function () {
    Schema::create('mcp_observability_aggregates_daily', function ($t) {
        $t->id();
        $t->string('module');
        $t->date('snapshot_date');
        $t->unsignedInteger('p99_ms');
        $t->timestamps();
    });

    DB::table('mcp_observability_aggregates_daily')->insert([
        'module'        => 'Jana',
        'snapshot_date' => now()->subDays(15)->toDateString(),
        'p99_ms'        => 4000, // acima do threshold 2000
        'created_at'    => now()->subDays(15),
        'updated_at'    => now()->subDays(15),
    ]);

    // window_days=30 inclui 15 dias atrás → encontra p99=4000 > 2000 → false
    $result = $this->eval->detectOtelQuery('Jana', [
        'window_days'      => 30,
        'p99_threshold_ms' => 2000,
    ]);

    expect($result)->toBeFalse();
});

// ============================================================================
// Threshold resolution: explicit > regex expect > default
// ============================================================================

it('threshold explicit p99_threshold_ms tem prioridade sobre expect regex', function () {
    Schema::create('mcp_observability_aggregates_daily', function ($t) {
        $t->id();
        $t->string('module');
        $t->date('snapshot_date');
        $t->unsignedInteger('p99_ms');
        $t->timestamps();
    });

    DB::table('mcp_observability_aggregates_daily')->insert([
        'module'        => 'Sells',
        'snapshot_date' => now()->toDateString(),
        'p99_ms'        => 1500,
        'created_at'    => now(),
        'updated_at'    => now(),
    ]);

    // Explicit threshold 1000 (estricto) vence sobre expect "p99 < 5000"
    $result = $this->eval->detectOtelQuery('Sells', [
        'p99_threshold_ms' => 1000,
        'expect'           => 'p99 < 5000',
    ]);

    expect($result)->toBeFalse(); // 1500 > 1000
});

it('threshold cai pra regex de expect quando p99_threshold_ms ausente', function () {
    Schema::create('mcp_observability_aggregates_daily', function ($t) {
        $t->id();
        $t->string('module');
        $t->date('snapshot_date');
        $t->unsignedInteger('p99_ms');
        $t->timestamps();
    });

    DB::table('mcp_observability_aggregates_daily')->insert([
        'module'        => 'Sells',
        'snapshot_date' => now()->toDateString(),
        'p99_ms'        => 800,
        'created_at'    => now(),
        'updated_at'    => now(),
    ]);

    $result = $this->eval->detectOtelQuery('Sells', [
        'expect' => 'p99 < 1000', // regex parsing
    ]);

    expect($result)->toBeTrue(); // 800 <= 1000
});

it('threshold default 2000 quando nem explicit nem expect parseable', function () {
    Schema::create('mcp_observability_aggregates_daily', function ($t) {
        $t->id();
        $t->string('module');
        $t->date('snapshot_date');
        $t->unsignedInteger('p99_ms');
        $t->timestamps();
    });

    DB::table('mcp_observability_aggregates_daily')->insert([
        'module'        => 'Sells',
        'snapshot_date' => now()->toDateString(),
        'p99_ms'        => 1900,
        'created_at'    => now(),
        'updated_at'    => now(),
    ]);

    $result = $this->eval->detectOtelQuery('Sells', [
        // sem p99_threshold_ms, sem expect parseable
    ]);

    expect($result)->toBeTrue(); // 1900 <= default 2000
});

// ============================================================================
// Path legacy — mcp_observability_spans fallback
// ============================================================================

it('fallback spans: usa raw spans quando aggregates_daily ausente', function () {
    // Apenas spans, sem aggregates
    Schema::create('mcp_observability_spans', function ($t) {
        $t->id();
        $t->string('module');
        $t->unsignedInteger('duration_ms');
        $t->timestamps();
    });

    // 100 spans com duração ~500ms (cenário saudável).
    // Approximation p99: offset = floor(100 * 0.01) = 1 → segundo span ordenado desc.
    for ($i = 0; $i < 100; $i++) {
        DB::table('mcp_observability_spans')->insert([
            'module'      => 'Vestuario',
            'duration_ms' => 500,
            'created_at'  => now()->subHours(2),
            'updated_at'  => now()->subHours(2),
        ]);
    }

    $result = $this->eval->detectOtelQuery('Vestuario', [
        'window_days'      => 1,
        'p99_threshold_ms' => 2000,
    ]);

    // p99 aproximado ≈ 500ms <= threshold 2000 → true (sistema saudável)
    expect($result)->toBeTrue();
});

it('fallback spans: detecta SLA violation quando >1% spans acima do threshold', function () {
    Schema::create('mcp_observability_spans', function ($t) {
        $t->id();
        $t->string('module');
        $t->unsignedInteger('duration_ms');
        $t->timestamps();
    });

    // 90 spans rápidos + 10 spans lentos (10% acima threshold = top 1% afetado)
    for ($i = 0; $i < 90; $i++) {
        DB::table('mcp_observability_spans')->insert([
            'module'      => 'Vestuario',
            'duration_ms' => 300,
            'created_at'  => now()->subHours(2),
            'updated_at'  => now()->subHours(2),
        ]);
    }
    for ($i = 0; $i < 10; $i++) {
        DB::table('mcp_observability_spans')->insert([
            'module'      => 'Vestuario',
            'duration_ms' => 5000, // outliers consistentes
            'created_at'  => now()->subHours(2),
            'updated_at'  => now()->subHours(2),
        ]);
    }

    // Approximation: count=100, offset=floor(100*0.01)=1 → ordenado desc pega 5000 (2º outlier).
    // 5000 > 2000 → false
    $result = $this->eval->detectOtelQuery('Vestuario', [
        'window_days'      => 1,
        'p99_threshold_ms' => 2000,
    ]);

    expect($result)->toBeFalse();
});

it('fallback spans: sem dados na janela → pass-through true', function () {
    Schema::create('mcp_observability_spans', function ($t) {
        $t->id();
        $t->string('module');
        $t->unsignedInteger('duration_ms');
        $t->timestamps();
    });

    // Sem inserts
    $result = $this->eval->detectOtelQuery('Vestuario', [
        'window_days'      => 7,
        'p99_threshold_ms' => 2000,
    ]);

    expect($result)->toBeTrue();
});

// ============================================================================
// Dispatcher integration — detectRule routes otel_query
// ============================================================================

it('detectRule dispatcher roteia tipo otel_query', function () {
    // Sem tabelas → pass-through true (já validado acima, agora via dispatcher)
    $result = $this->eval->detectRule('Vestuario', [
        'tipo'             => 'otel_query',
        'window_days'      => 7,
        'p99_threshold_ms' => 2000,
    ]);

    expect($result)->toBeTrue();
});
