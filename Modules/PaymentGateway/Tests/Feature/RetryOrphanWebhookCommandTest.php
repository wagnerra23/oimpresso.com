<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\PaymentGateway\Jobs\RetryOrphanWebhookJob;
use Modules\PaymentGateway\Models\GatewayWebhookEvent;

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\DatabaseTransactions::class);

/**
 * ADR 0170 Onda 4e — Cobertura do COMMAND paymentgateway:retry-orphan-webhooks
 * + regressão anti-"ghost-scheduled" (o docblock prometia um schedule que não
 * existia no Kernel — censo artisan 2026-06-24).
 *
 * GUARDs:
 *   1. Schedule REGISTRADO no Kernel (some do limbo ghost) — sem este teste o
 *      cron pode voltar a sumir silenciosamente.
 *   2. Flag default-OFF (REGRA MESTRE valor/estoque — o Job quita título).
 *   3. Comando (sem --dry-run) dispatcha RetryOrphanWebhookJob.
 *   4. Comando --dry-run NÃO dispatcha + lista órfãos (read-only).
 *
 * Pattern ADR 0101: biz=1, NUNCA biz=4 cliente real.
 */

// ── GUARD 1: schedule registrado (não precisa de DB) ────────────────────────
it('GUARD 1: registra paymentgateway:retry-orphan-webhooks no schedule do Kernel', function () {
    $kernel = app(\App\Console\Kernel::class);

    $schedule = new \Illuminate\Console\Scheduling\Schedule();
    $method = new ReflectionMethod($kernel, 'schedule');
    $method->setAccessible(true);
    $method->invoke($kernel, $schedule);

    $commands = array_map(
        static fn ($event) => (string) $event->command,
        $schedule->events(),
    );

    $found = collect($commands)->contains(
        static fn (string $cmd) => str_contains($cmd, 'paymentgateway:retry-orphan-webhooks'),
    );

    expect($found)->toBeTrue();
});

// ── GUARD 2: flag default-OFF (REGRA MESTRE valor/estoque) ──────────────────
it('GUARD 2: a flag retry_orphan_webhooks_enabled nasce OFF (default seguro)', function () {
    // O Config/config.php define env('PAYMENTGATEWAY_RETRY_ORPHAN_WEBHOOKS_ENABLED', false).
    // Sem o env setado, o default que SOBE é false — o cron quita título (mexe em
    // VALOR), então NUNCA pode ligar sozinho por deploy. O ->when() do Kernel lê
    // exatamente esta chave.
    expect((bool) config('paymentgateway.retry_orphan_webhooks_enabled'))->toBeFalse();
});

// ── GUARD 3: comando dispatcha o Job (não precisa de DB) ─────────────────────
it('GUARD 3: comando sem --dry-run dispatcha RetryOrphanWebhookJob', function () {
    Bus::fake([RetryOrphanWebhookJob::class]);

    $exit = $this->artisan('paymentgateway:retry-orphan-webhooks')->run();

    expect($exit)->toBe(0);
    Bus::assertDispatched(RetryOrphanWebhookJob::class);
});

// ── GUARD 4: --dry-run é read-only (precisa da tabela; sintético em sqlite) ──
function setupOrphanWebhookCommandSchema(): void
{
    if (! Schema::hasTable('gateway_webhook_events')) {
        Schema::create('gateway_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id')->index();
            $table->unsignedBigInteger('payment_gateway_credential_id')->nullable();
            $table->string('gateway_key', 20)->index();
            $table->string('evento', 60)->index();
            $table->string('gateway_event_id', 191);
            $table->unsignedBigInteger('cobranca_id')->nullable()->index();
            $table->json('payload');
            $table->boolean('signature_valid')->default(false);
            $table->timestamp('processed_at')->nullable()->index();
            $table->text('error_message')->nullable();
            $table->timestamps();
            $table->unique(['business_id', 'gateway_key', 'gateway_event_id'], 'gw_wh_cmd_biz_key_extid_unique');
        });
    }
}

it('GUARD 4: --dry-run lista órfãos na janela 1h..24h e NÃO dispatcha Job', function () {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        test()->markTestSkipped('era-sqlite: schema sintético manual incompatível com MySQL persistente (espelha RetryOrphanWebhookJobTest).');
    }

    setupOrphanWebhookCommandSchema();
    session(['business.id' => 1]);
    Bus::fake([RetryOrphanWebhookJob::class]);

    // Órfão na janela (3h atrás) — DEVE aparecer no dry-run
    $naJanela = GatewayWebhookEvent::query()->create([
        'business_id' => 1,
        'gateway_key' => 'inter',
        'evento' => 'cob.paid',
        'gateway_event_id' => 'evt-cmd-001',
        'cobranca_id' => null,
        'payload' => ['raw' => 'paid'],
        'signature_valid' => true,
    ]);
    $naJanela->created_at = now()->subHours(3);
    $naJanela->saveQuietly();

    // Fora da janela (10min atrás) — NÃO deve contar
    $foraJanela = GatewayWebhookEvent::query()->create([
        'business_id' => 1,
        'gateway_key' => 'inter',
        'evento' => 'cob.paid',
        'gateway_event_id' => 'evt-cmd-002',
        'cobranca_id' => null,
        'payload' => ['raw' => 'paid'],
        'signature_valid' => true,
    ]);
    $foraJanela->created_at = now()->subMinutes(10);
    $foraJanela->saveQuietly();

    $this->artisan('paymentgateway:retry-orphan-webhooks', ['--dry-run' => true])
        ->expectsOutputToContain('1 órfão(s) na janela 1h..24h.')
        ->assertExitCode(0);

    Bus::assertNotDispatched(RetryOrphanWebhookJob::class);

    Schema::dropIfExists('gateway_webhook_events');
});
