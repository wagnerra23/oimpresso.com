<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Entities\HealthNarrative;
use Modules\Jana\Jobs\NarrarSaudeEcosistemaJob;
use Modules\Jana\Services\HealthNarratorService;
use Modules\Jana\Services\HealthSnapshotService;

uses(Tests\TestCase::class);

/**
 * US-COPI-100 — Job hourly orquestra snapshot → narrate → persist + escalation HITL.
 *
 * HealthSnapshotService e HealthNarratorService são `final` — usamos Mockery (que
 * suporta mock de final classes) em vez de anonymous extends.
 */

beforeEach(function () {
    // era-sqlite: cria schema mcp_*/jana_* manual (sqlite-friendly). No MySQL persistente
    // do nightly isso corrompe os testes irmãos (lever do floor SDD). Cobertura real é
    // na lane sqlite (per-PR); pula no MySQL.
    if (config('database.default') !== 'sqlite') {
        $this->markTestSkipped('era-sqlite: corruptor de schema compartilhado no MySQL — sqlite-only no burn-down do floor SDD.');
    }

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

function makeNarrative(string $severity, string $message): HealthNarrative
{
    return HealthNarrative::create([
        'generated_at' => now(),
        'severity' => $severity,
        'narrative' => $message,
        'snapshot_hash' => str_repeat('a', 64),
        'model' => 'gpt-4o-mini',
    ]);
}

test('handle persiste narrativa info quando severity=info', function () {
    $snap = Mockery::mock(HealthSnapshotService::class);
    $snap->shouldReceive('snapshot')->once()->andReturn(['health' => ['ok' => true]]);

    $narr = Mockery::mock(HealthNarratorService::class);
    $narr->shouldReceive('narrate')->once()->andReturn(makeNarrative('info', 'tudo OK'));

    (new NarrarSaudeEcosistemaJob)->handle($snap, $narr);

    expect(HealthNarrative::count())->toBe(1)
        ->and(HealthNarrative::first()->severity)->toBe('info');
});

test('severity=critical dispara ALERT log line (escalation HITL Wagner)', function () {
    $channelsCalled = [];
    Log::shouldReceive('channel')->andReturnUsing(function (string $name) use (&$channelsCalled) {
        $channelsCalled[] = $name;

        return Log::getFacadeRoot();
    });
    Log::shouldReceive('info');
    Log::shouldReceive('error');

    $snap = Mockery::mock(HealthSnapshotService::class);
    $snap->shouldReceive('snapshot')->once()->andReturn(['health' => ['ok' => false]]);

    $narr = Mockery::mock(HealthNarratorService::class);
    $narr->shouldReceive('narrate')->once()->andReturn(makeNarrative('critical', 'multi_tenant violado'));

    (new NarrarSaudeEcosistemaJob)->handle($snap, $narr);

    expect($channelsCalled)->toContain('copiloto-ai', 'single');
    expect(HealthNarrative::where('severity', 'critical')->count())->toBe(1);
});

test('severity=warning persiste mas NÃO escala critical', function () {
    $channelsCalled = [];
    Log::shouldReceive('channel')->andReturnUsing(function (string $name) use (&$channelsCalled) {
        $channelsCalled[] = $name;

        return Log::getFacadeRoot();
    });
    Log::shouldReceive('info');

    $snap = Mockery::mock(HealthSnapshotService::class);
    $snap->shouldReceive('snapshot')->once()->andReturn(['health' => ['ok' => false]]);

    $narr = Mockery::mock(HealthNarratorService::class);
    $narr->shouldReceive('narrate')->once()->andReturn(makeNarrative('warning', 'profile drift'));

    (new NarrarSaudeEcosistemaJob)->handle($snap, $narr);

    expect($channelsCalled)->toContain('copiloto-ai')
        ->and($channelsCalled)->not->toContain('single');
    expect(HealthNarrative::where('severity', 'warning')->count())->toBe(1);
});

test('Job tem tags canônicas Horizon', function () {
    expect((new NarrarSaudeEcosistemaJob)->tags())
        ->toBe(['copiloto', 'health', 'brain-a-narrator']);
});
