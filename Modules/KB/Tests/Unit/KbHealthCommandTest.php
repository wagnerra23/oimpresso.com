<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Modules\KB\Services\KbBridgeStateService;

/**
 * Wave 25 — KbHealthCommand specs (boost D9 KB §G saturação).
 *
 * Cobre 4 checks: corpus_size, bridge_freshness, retrieval_latency, editable_ratio.
 *
 * Estratégia: cria scenarios distintos (vazio, pequeno, saudável, stale bridge)
 * e valida shape JSON + overall + exit code. ZERO chamada LLM (sem custo).
 *
 * Multi-tenant Tier 0 (ADR 0093 + ADR 0101): biz=1 + biz=99 — NUNCA biz=4 (cliente prod).
 *
 * @see Modules/KB/Console/Commands/KbHealthCommand.php
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */

beforeEach(function () {
    kbBootstrapSchema();
    kbCreateBusinessRow(1);
    kbCreateBusinessRow(99);
});

afterEach(function () {
    kbTeardownSchema();
});

// ------------------------------------------------------------------
// Contract: --business-id obrigatório (Tier 0)
// ------------------------------------------------------------------

it('exige --business-id quando --all-businesses ausente (Tier 0)', function () {
    $exit = Artisan::call('kb:health-check');
    expect($exit)->toBe(1);

    $out = Artisan::output();
    expect($out)->toContain('--business-id obrigatório');
});

// ------------------------------------------------------------------
// Check 1: corpus_size — vazio → fail
// ------------------------------------------------------------------

it('check corpus_size retorna fail quando KB vazio no business', function () {
    // biz=1 SEM nodes
    $exit = Artisan::call('kb:health-check', [
        '--business-id' => 1,
        '--json'        => true,
    ]);

    $output = Artisan::output();
    $json = json_decode(trim($output), true);

    expect($json)->not->toBeNull();
    expect($json['business_id'])->toBe(1);
    expect($json['checks']['corpus_size']['status'])->toBe('fail');
    expect($json['checks']['corpus_size']['value'])->toBe(0);
    expect($json['overall'])->toBe('fail');
    expect($exit)->toBe(1);
});

// ------------------------------------------------------------------
// Check 1: corpus_size — pequeno (<10) → warn
// ------------------------------------------------------------------

it('check corpus_size retorna warn quando KB pequeno (<10 nodes)', function () {
    // Cria 3 nodes biz=1
    foreach (range(1, 3) as $i) {
        \DB::table('kb_nodes')->insert([
            'business_id' => 1,
            'type' => 'article',
            'slug' => "warn-test-{$i}",
            'title' => "Warn {$i}",
            'is_editable' => true,
            'body_blocks' => json_encode([['kind' => 'para', 'text' => 'x']]),
            'status' => 'ok',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    Artisan::call('kb:health-check', [
        '--business-id' => 1,
        '--json'        => true,
    ]);

    $json = json_decode(trim(Artisan::output()), true);
    expect($json['checks']['corpus_size']['status'])->toBe('warn');
    expect($json['checks']['corpus_size']['value'])->toBe(3);
});

// ------------------------------------------------------------------
// Check 1: corpus_size — saudável (≥10) → ok
// ------------------------------------------------------------------

it('check corpus_size retorna ok quando KB saudável (≥10 nodes)', function () {
    foreach (range(1, 12) as $i) {
        \DB::table('kb_nodes')->insert([
            'business_id' => 1,
            'type' => 'article',
            'slug' => "ok-test-{$i}",
            'title' => "OK {$i}",
            'is_editable' => true,
            'body_blocks' => json_encode([['kind' => 'para', 'text' => 'x']]),
            'status' => 'ok',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    Artisan::call('kb:health-check', [
        '--business-id' => 1,
        '--json'        => true,
    ]);

    $json = json_decode(trim(Artisan::output()), true);
    expect($json['checks']['corpus_size']['status'])->toBe('ok');
    expect($json['checks']['corpus_size']['value'])->toBe(12);
});

// ------------------------------------------------------------------
// Check 2: bridge_freshness — nunca rodou → warn
// ------------------------------------------------------------------

it('check bridge_freshness retorna warn quando bridge nunca rodou', function () {
    // Sem markRun chamado pra biz=1
    \DB::table('kb_nodes')->insert([
        'business_id' => 1,
        'type' => 'article', 'slug' => 'x', 'title' => 'x', 'is_editable' => true,
        'body_blocks' => json_encode([['kind' => 'para', 'text' => 'x']]),
        'status' => 'ok', 'created_at' => now(), 'updated_at' => now(),
    ]);

    Artisan::call('kb:health-check', [
        '--business-id' => 1,
        '--json'        => true,
    ]);

    $json = json_decode(trim(Artisan::output()), true);
    expect($json['checks']['bridge_freshness']['status'])->toBe('warn');
    expect($json['checks']['bridge_freshness']['value'])->toBeNull();
    expect($json['checks']['bridge_freshness']['note'])->toContain('nunca rodou');
});

// ------------------------------------------------------------------
// Check 2: bridge_freshness — recente → ok
// ------------------------------------------------------------------

it('check bridge_freshness retorna ok quando bridge rodou recentemente', function () {
    app(KbBridgeStateService::class)->markRun(1, 5, 10, null);

    \DB::table('kb_nodes')->insert([
        'business_id' => 1,
        'type' => 'article', 'slug' => 'fresh', 'title' => 'fresh', 'is_editable' => true,
        'body_blocks' => json_encode([['kind' => 'para', 'text' => 'x']]),
        'status' => 'ok', 'created_at' => now(), 'updated_at' => now(),
    ]);

    Artisan::call('kb:health-check', [
        '--business-id' => 1,
        '--json'        => true,
    ]);

    $json = json_decode(trim(Artisan::output()), true);
    expect($json['checks']['bridge_freshness']['status'])->toBe('ok');
    expect($json['checks']['bridge_freshness']['value'])->toBeLessThan(2);
});

// ------------------------------------------------------------------
// Check 4: editable_ratio — boundary 5-60% saudável
// ------------------------------------------------------------------

it('check editable_ratio retorna ok com mix saudável editable+bridge', function () {
    // 5 editable + 5 bridge canon
    foreach (range(1, 5) as $i) {
        \DB::table('kb_nodes')->insert([
            'business_id' => 1,
            'type' => 'article', 'slug' => "ed{$i}", 'title' => "ed{$i}",
            'is_editable' => true,
            'body_blocks' => json_encode([['kind' => 'para', 'text' => 'x']]),
            'status' => 'ok', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
    foreach (range(1, 5) as $i) {
        \DB::table('kb_nodes')->insert([
            'business_id' => 1,
            'type' => 'adr', 'slug' => "br{$i}", 'title' => "br{$i}",
            'is_editable' => false, // bridge canon
            'body_blocks' => null,
            'status' => 'ok', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    Artisan::call('kb:health-check', [
        '--business-id' => 1,
        '--json'        => true,
    ]);

    $json = json_decode(trim(Artisan::output()), true);
    expect($json['checks']['editable_ratio']['status'])->toBe('ok');
    expect($json['checks']['editable_ratio']['value'])->toBe(0.5);
});

// ------------------------------------------------------------------
// Multi-tenant isolation (Tier 0)
// ------------------------------------------------------------------

it('biz=99 NÃO vê nodes biz=1 (isolation Tier 0 ADR 0093)', function () {
    // 12 nodes biz=1 (saudável)
    foreach (range(1, 12) as $i) {
        \DB::table('kb_nodes')->insert([
            'business_id' => 1,
            'type' => 'article', 'slug' => "iso{$i}", 'title' => "iso{$i}",
            'is_editable' => true,
            'body_blocks' => json_encode([['kind' => 'para', 'text' => 'x']]),
            'status' => 'ok', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    // biz=99 deveria ver KB vazio → fail
    Artisan::call('kb:health-check', [
        '--business-id' => 99,
        '--json'        => true,
    ]);

    $json = json_decode(trim(Artisan::output()), true);
    expect($json['business_id'])->toBe(99);
    expect($json['checks']['corpus_size']['value'])->toBe(0);
    expect($json['checks']['corpus_size']['status'])->toBe('fail');
});

// ------------------------------------------------------------------
// Output: shape estável (chaves canônicas)
// ------------------------------------------------------------------

it('JSON output tem shape estável (chaves canônicas pra dashboard ingestão)', function () {
    Artisan::call('kb:health-check', [
        '--business-id' => 1,
        '--json'        => true,
    ]);

    $json = json_decode(trim(Artisan::output()), true);
    expect($json)->toHaveKeys(['business_id', 'overall', 'checks']);
    expect($json['checks'])->toHaveKeys([
        'corpus_size', 'bridge_freshness', 'retrieval_latency', 'editable_ratio',
    ]);

    foreach ($json['checks'] as $name => $c) {
        expect($c)->toHaveKeys(['status', 'value', 'note'])
            ->and($c['status'])->toBeIn(['ok', 'warn', 'fail']);
    }
});

// ------------------------------------------------------------------
// Wave 25 — OtelHelper wrap zero-cost path quando otel.enabled=false
// ------------------------------------------------------------------

it('OtelHelper::span fail-safe: command roda mesmo com otel.enabled=false (default)', function () {
    config(['otel.enabled' => false]);

    \DB::table('kb_nodes')->insert([
        'business_id' => 1,
        'type' => 'article', 'slug' => 'otel-off', 'title' => 'x', 'is_editable' => true,
        'body_blocks' => json_encode([['kind' => 'para', 'text' => 'x']]),
        'status' => 'ok', 'created_at' => now(), 'updated_at' => now(),
    ]);

    $exit = Artisan::call('kb:health-check', [
        '--business-id' => 1,
        '--json'        => true,
    ]);

    // Exit 1 ok aqui (1 node < threshold 10 → warn no corpus, mas geral pode ser warn)
    expect($exit)->toBeIn([0, 1]);
    $json = json_decode(trim(Artisan::output()), true);
    expect($json)->not->toBeNull();
});
