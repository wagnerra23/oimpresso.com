<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\Channel;

uses(Tests\TestCase::class);

/**
 * WhatsmeowResubscribeEventsCommandTest — Fase B (mecanismo D).
 *
 * Re-assina `LoggedOut` nos canais whatsmeow já provisionados via POST /webhook
 * (UPDATE users.events no daemon), SEM reconnect/re-pair. Mocka o daemon via
 * Http::fake. Schema sintético espelha HealthProbeChannelsCommandTest.
 *
 * @see Modules\Whatsapp\Console\Commands\WhatsmeowResubscribeEventsCommand
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        test()->markTestSkipped('schema sintético manual incompatível com MySQL persistente.');
    }

    Schema::dropIfExists('channels');
    Schema::create('channels', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedInteger('business_id');
        $table->uuid('channel_uuid')->unique();
        $table->string('label', 80);
        $table->string('type', 30);
        $table->string('status', 20)->default('setup');
        $table->string('display_identifier', 100)->nullable();
        $table->text('config_json')->nullable();
        $table->boolean('handles_repair_status')->default(false);
        $table->boolean('handles_billing')->default(false);
        $table->boolean('handles_jana_bot')->default(true);
        $table->boolean('handles_outbound_default')->default(false);
        $table->boolean('bot_enabled')->default(false);
        $table->string('channel_health', 20)->default('never_checked');
        $table->unsignedInteger('channel_health_consecutive_failures')->default(0);
        $table->timestamp('last_health_check_at')->nullable();
        $table->text('last_health_message')->nullable();
        $table->timestamp('lgpd_acknowledged_at')->nullable();
        $table->unsignedInteger('lgpd_acknowledged_by_user_id')->nullable();
        $table->timestamps();
    });

    config([
        'whatsapp.whatsmeow.daemon_url' => 'https://whatsapp-whatsmeow.oimpresso.com',
        'whatsapp.whatsmeow.api_key' => 'admin_token_fake',
        'whatsapp.whatsmeow.request_timeout' => 5,
    ]);
});

function makeResubWmChannel(int $bizId, bool $provisioned = true): Channel
{
    $cfg = $provisioned ? [
        'whatsmeow_user_token' => 'tok-'.$bizId,
        'whatsmeow_user_name' => 'ch-'.$bizId,
        'whatsmeow_webhook_url' => "https://oimpresso.com/api/whatsapp/webhook/whatsmeow/biz-{$bizId}",
    ] : [];

    return Channel::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => $bizId,
        'channel_uuid' => sprintf('rsub%04d-0000-0000-0000-%012d', $bizId, random_int(1, 999999)),
        'label' => "Canal {$bizId}",
        'type' => Channel::TYPE_WHATSAPP_WHATSMEOW,
        'status' => 'active',
        'config_json' => $cfg,
    ]);
}

it('re-assina LoggedOut via POST /webhook (mecanismo D, sem reconnect)', function () {
    makeResubWmChannel(1);
    Http::fake(['*/webhook' => Http::response(['code' => 200, 'success' => true], 200)]);

    $exit = \Artisan::call('whatsapp:whatsmeow-resubscribe-events', ['--business' => '1']);

    expect($exit)->toBe(0);
    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/webhook')
            && str_contains($request->body(), 'LoggedOut');
    });
});

it('multi-tenant Tier 0: --business=99 não toca biz=1', function () {
    makeResubWmChannel(1);
    makeResubWmChannel(99);
    Http::fake(['*/webhook' => Http::response(['code' => 200], 200)]);

    \Artisan::call('whatsapp:whatsmeow-resubscribe-events', ['--business' => '99']);

    Http::assertSentCount(1);
    Http::assertSent(fn ($r) => str_contains($r->body(), 'biz-99'));
});

it('--dry-run não chama o daemon', function () {
    makeResubWmChannel(1);
    Http::fake();

    $exit = \Artisan::call('whatsapp:whatsmeow-resubscribe-events', ['--dry-run' => true]);

    expect($exit)->toBe(0);
    Http::assertNothingSent();
});

it('canal sem token é pulado (skip), comando não falha', function () {
    makeResubWmChannel(1, provisioned: false);
    Http::fake();

    $exit = \Artisan::call('whatsapp:whatsmeow-resubscribe-events', ['--business' => '1']);

    expect($exit)->toBe(0); // skip não é falha
    Http::assertNothingSent();
});
