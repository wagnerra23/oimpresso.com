<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Modules\Whatsapp\Http\Middleware\EnforceWebhookBackpressure;

uses(Tests\TestCase::class);

/**
 * Regression test pro middleware backpressure (US-WA-084 — gap analysis 2026-05-14).
 *
 * Garante:
 *   1. backpressure desabilitado → next() sempre
 *   2. queue depth < max → next()
 *   3. queue depth >= max → 429 com Retry-After header
 *   4. queue_max_depth=0 → next() (config defensive)
 *   5. Cache 10s evita SELECT COUNT em burst
 *
 * @see Modules/Whatsapp/Http/Middleware/EnforceWebhookBackpressure.php
 */
beforeEach(function () {
    Cache::flush();

    if (! Schema::hasTable('jobs')) {
        Schema::create('jobs', function ($table) {
            $table->bigIncrements('id');
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });
    } else {
        DB::table('jobs')->truncate();
    }

    config([
        'whatsapp.backpressure.enabled' => true,
        'whatsapp.backpressure.queue_max_depth' => 5,
        'whatsapp.backpressure.queue_name' => 'whatsapp-history',
        'whatsapp.backpressure.retry_after_seconds' => 30,
    ]);
});

function seedJobs(int $count, string $queue = 'whatsapp-history'): void
{
    $now = time();
    for ($i = 0; $i < $count; $i++) {
        DB::table('jobs')->insert([
            'queue' => $queue,
            'payload' => '{}',
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => $now,
            'created_at' => $now,
        ]);
    }
}

it('R-WA-BACKPRESSURE-001 — desabilitado → next()', function () {
    config(['whatsapp.backpressure.enabled' => false]);
    seedJobs(100); // bem acima do max

    $request = \Illuminate\Http\Request::create('/api/atendimento/channels/baileys/aaa', 'POST');
    $middleware = new EnforceWebhookBackpressure();
    $response = $middleware->handle($request, fn ($r) => response()->json(['next' => true], 200));

    expect($response->getStatusCode())->toBe(200);
});

it('R-WA-BACKPRESSURE-002 — depth < max → next()', function () {
    seedJobs(3); // < 5

    $request = \Illuminate\Http\Request::create('/api/atendimento/channels/baileys/aaa', 'POST');
    $middleware = new EnforceWebhookBackpressure();
    $response = $middleware->handle($request, fn ($r) => response()->json(['next' => true], 200));

    expect($response->getStatusCode())->toBe(200);
});

it('R-WA-BACKPRESSURE-003 — depth >= max → 429 com Retry-After', function () {
    seedJobs(5); // == max

    $request = \Illuminate\Http\Request::create('/api/atendimento/channels/baileys/aaa', 'POST');
    $middleware = new EnforceWebhookBackpressure();
    $response = $middleware->handle($request, fn ($r) => response()->json(['next' => true], 200));

    expect($response->getStatusCode())->toBe(429);
    expect($response->headers->get('Retry-After'))->toBe('30');

    $body = json_decode($response->getContent(), true);
    expect($body['error'])->toBe('queue_backpressure');
    expect($body['queue_depth'])->toBe(5);
});

it('R-WA-BACKPRESSURE-004 — queue_max_depth=0 (defensive) → next()', function () {
    config(['whatsapp.backpressure.queue_max_depth' => 0]);
    seedJobs(1000);

    $request = \Illuminate\Http\Request::create('/api/atendimento/channels/baileys/aaa', 'POST');
    $middleware = new EnforceWebhookBackpressure();
    $response = $middleware->handle($request, fn ($r) => response()->json(['next' => true], 200));

    expect($response->getStatusCode())->toBe(200);
});

it('R-WA-BACKPRESSURE-005 — só conta a fila configurada (não outras queues)', function () {
    seedJobs(10, 'default'); // outra queue, não conta

    $request = \Illuminate\Http\Request::create('/api/atendimento/channels/baileys/aaa', 'POST');
    $middleware = new EnforceWebhookBackpressure();
    $response = $middleware->handle($request, fn ($r) => response()->json(['next' => true], 200));

    expect($response->getStatusCode())->toBe(200);
});

it('R-WA-BACKPRESSURE-006 — depth cache 10s evita SELECT em burst', function () {
    seedJobs(3);

    $request = \Illuminate\Http\Request::create('/api/atendimento/channels/baileys/aaa', 'POST');
    $middleware = new EnforceWebhookBackpressure();

    // 1ª chamada popula cache
    $middleware->handle($request, fn ($r) => response()->json(['next' => true], 200));

    // Simula burst: agora inserimos 10 jobs novos (acima do max). Mas cache
    // ainda tem o valor antigo (3) → próxima request passa.
    seedJobs(10);

    $response = $middleware->handle($request, fn ($r) => response()->json(['next' => true], 200));
    expect($response->getStatusCode())->toBe(200); // cache antigo (=3) ainda válido
});
