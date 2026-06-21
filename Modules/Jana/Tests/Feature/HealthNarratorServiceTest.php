<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Entities\HealthNarrative;
use Modules\Jana\Services\HealthNarratorService;

uses(Tests\TestCase::class);

/**
 * US-COPI-099 — HealthNarratorService persiste narrativa + degrada gracioso.
 *
 * Default dry_run=true em test → fixture sem chamar LLM. Cobertura:
 *   - persiste linha em jana_health_narratives com shape correto
 *   - severity determinado pelo health.ok do snapshot em fallback
 *   - hash determinístico pro mesmo snapshot
 *   - reducer payload_summary pega só campos chave
 */

beforeEach(function () {
    // era-sqlite: cria schema mcp_*/jana_* manual (sqlite-friendly). No MySQL persistente
    // do nightly isso corrompe os testes irmãos (lever do floor SDD). Cobertura real é
    // na lane sqlite (per-PR); pula no MySQL.
    if (config('database.default') !== 'sqlite') {
        $this->markTestSkipped('era-sqlite: corruptor de schema compartilhado no MySQL — sqlite-only no burn-down do floor SDD.');
    }

    config()->set('copiloto.dry_run', true);

    Schema::dropIfExists('jana_health_narratives');
    Schema::create('jana_health_narratives', function (Blueprint $t) {
        $t->id();
        $t->timestamp('generated_at')->index();
        $t->string('severity', 20)->default('info')->index();
        $t->text('narrative');
        $t->string('snapshot_hash', 64)->index();
        $t->string('model', 50)->default('gpt-4o-mini');
        $t->unsignedInteger('tokens_in')->nullable();
        $t->unsignedInteger('tokens_out')->nullable();
        $t->decimal('custo_brl', 10, 6)->nullable();
        $t->json('payload_summary')->nullable();
        $t->timestamp('created_at')->useCurrent();
    });
});

afterEach(function () {
    if (config('database.default') !== 'sqlite') {
        return;
    }

    Schema::dropIfExists('jana_health_narratives');
});

test('persiste narrativa com severity info quando snapshot health.ok=true (fixture dry_run)', function () {
    $snapshot = [
        'generated_at' => now()->toIso8601String(),
        'health' => ['ok' => true, 'checks' => []],
        'queues' => ['available' => true, 'failed_24h' => 0, 'failed_total' => 0],
        'mcp' => ['available' => true, 'requests_24h' => 100, 'errors_24h' => 0, 'taxa_erro' => 0.0, 'custo_brl_24h' => 1.5],
        'brain_b' => ['available' => true, 'tokens_in_24h' => 0, 'tokens_out_24h' => 0, 'custo_brl_24h' => 0.0],
    ];

    $narrative = (new HealthNarratorService)->narrate($snapshot);

    expect($narrative)->toBeInstanceOf(HealthNarrative::class);
    expect($narrative->severity)->toBe('info');
    expect($narrative->narrative)->toContain('checks SQL passaram');
    expect($narrative->model)->toBe('gpt-4o-mini');
    expect($narrative->snapshot_hash)->toHaveLength(64);
});

test('severity warning quando snapshot health.ok=false (fixture dry_run)', function () {
    $snapshot = [
        'health' => ['ok' => false, 'checks' => [['name' => 'profile_distiller_drift', 'ok' => false, 'value' => 3]]],
    ];

    $narrative = (new HealthNarratorService)->narrate($snapshot);

    expect($narrative->severity)->toBe('warning');
    expect($narrative->narrative)->toContain('investigar logs');
});

test('hash do snapshot é determinístico — mesmo input gera mesmo hash', function () {
    $snapshot = ['health' => ['ok' => true]];

    $a = (new HealthNarratorService)->narrate($snapshot);
    $b = (new HealthNarratorService)->narrate($snapshot);

    expect($a->snapshot_hash)->toBe($b->snapshot_hash);
});

test('payload_summary captura apenas campos chave do snapshot', function () {
    $snapshot = [
        'health' => ['ok' => true, 'checks' => [['enorme_array' => 'cortado']]],
        'queues' => ['failed_24h' => 7],
        'mcp' => ['taxa_erro' => 0.02],
        'brain_b' => ['custo_brl_24h' => 4.2],
    ];

    $narrative = (new HealthNarratorService)->narrate($snapshot);

    expect($narrative->payload_summary)->toBe([
        'health_ok' => true,
        'queues_failed_24h' => 7,
        'mcp_taxa_erro' => 0.02,
        'brain_b_custo_brl_24h' => 4.2,
    ]);
});

test('em dry_run não chama LLM — tokens e custo ficam null', function () {
    $narrative = (new HealthNarratorService)->narrate(['health' => ['ok' => true]]);

    expect($narrative->tokens_in)->toBeNull();
    expect($narrative->tokens_out)->toBeNull();
    expect($narrative->custo_brl)->toBeNull();
});
