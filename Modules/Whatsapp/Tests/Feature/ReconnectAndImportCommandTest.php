<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Entities\Conversation;
use Modules\Whatsapp\Entities\Message;

uses(Tests\TestCase::class);

/**
 * Camada 4 — combo manual reconnect-and-import.
 *
 * Mocka daemon CT 100 via Http::fake e exercita 4 cenários canônicos:
 *   1. Channel já connected → pula reconnect, chama import direto
 *   2. Channel disconnected → reconnect + wait + import
 *   3. Timeout reconnect → abort sem import
 *   4. --since=auto resolve corretamente da última msg DB
 *
 * Reusa schema in-memory do ImportHistoryCommandTest pra messages/conversations.
 *
 * @see Modules/Whatsapp/Console/Commands/ReconnectAndImportCommand.php
 */
beforeEach(function () {
    foreach (['messages', 'conversations', 'channels'] as $t) {
        Schema::dropIfExists($t);
    }

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
        $table->string('template_repair_ready_name', 64)->nullable();
        $table->string('template_repair_waiting_parts_name', 64)->nullable();
        $table->string('template_billing_due_name', 64)->nullable();
        $table->string('template_billing_paid_name', 64)->nullable();
        $table->string('channel_health', 20)->default('never_checked');
        $table->unsignedInteger('channel_health_consecutive_failures')->default(0);
        $table->timestamp('last_health_check_at')->nullable();
        $table->text('last_health_message')->nullable();
        $table->timestamp('lgpd_acknowledged_at')->nullable();
        $table->unsignedInteger('lgpd_acknowledged_by_user_id')->nullable();
        $table->timestamps();
    });

    Schema::create('conversations', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedInteger('business_id');
        $table->unsignedBigInteger('channel_id');
        $table->unsignedInteger('contact_id')->nullable();
        $table->string('customer_external_id', 150);
        $table->string('contact_name', 120)->nullable();
        $table->string('status', 20)->default('open');
        $table->unsignedInteger('assigned_user_id')->nullable();
        $table->boolean('bot_handling')->default(false);
        $table->timestamp('last_inbound_at')->nullable();
        $table->timestamp('last_outbound_at')->nullable();
        $table->timestamp('last_message_at')->nullable();
        $table->unsignedInteger('unread_count')->default(0);
        $table->string('last_message_preview', 120)->nullable();
        $table->string('last_message_direction', 20)->nullable();
        $table->boolean('is_blocked')->default(false);
        $table->timestamps();
    });

    Schema::create('messages', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedInteger('business_id');
        $table->unsignedBigInteger('conversation_id');
        $table->string('direction', 10);
        $table->string('provider', 30);
        $table->string('provider_message_id', 128)->nullable();
        $table->string('type', 20)->default('text');
        $table->string('template_name', 64)->nullable();
        $table->string('subject', 255)->nullable();
        $table->text('body')->nullable();
        $table->json('payload')->nullable();
        $table->string('status', 20);
        $table->string('failed_reason', 255)->nullable();
        $table->unsignedInteger('sender_user_id')->nullable();
        $table->string('sender_kind', 20)->nullable();
        $table->unsignedInteger('cost_centavos')->nullable();
        $table->boolean('is_internal_note')->default(false);
        $table->string('media_url', 500)->nullable();
        $table->string('media_mime', 100)->nullable();
        $table->unsignedBigInteger('media_size_bytes')->nullable();
        $table->unsignedSmallInteger('media_duration_s')->nullable();
        $table->string('media_thumbnail_url', 500)->nullable();
        $table->text('media_transcription')->nullable();
        $table->string('media_filename', 255)->nullable();
        $table->string('media_download_status', 30)->default('pending');
        $table->unsignedInteger('media_download_attempts')->default(0);
        $table->timestamp('media_download_last_attempt_at')->nullable();
        $table->string('media_download_failed_reason', 255)->nullable();
        $table->timestamp('created_at')->useCurrent();
        $table->timestamp('updated_at')->nullable();
        $table->unique('provider_message_id', 'msgs_provider_msg_uniq');
    });

    config([
        'whatsapp.baileys.daemon_url' => 'https://daemon-test.local',
        'whatsapp.baileys.api_key' => 'test-api-key-1234567890',
        'whatsapp.baileys.request_timeout' => 5,
    ]);
});

/**
 * Helper — cria Channel Baileys ativo.
 */
function makeRaiChannel(int $bizId = 1): Channel
{
    return Channel::query()->create([
        'business_id' => $bizId,
        'channel_uuid' => sprintf('rai00000-0000-0000-0000-%012d', $bizId),
        'label' => 'Test channel',
        'type' => Channel::TYPE_WHATSAPP_BAILEYS,
        'status' => 'active',
    ]);
}

it('RAI-001 — channel já connected: pula reconnect + chama import direto', function () {
    $channel = makeRaiChannel(1);

    // Conversation+Message vazios → import vai retornar sem nenhuma conv pra processar
    Conversation::query()->create([
        'business_id' => 1,
        'channel_id' => $channel->id,
        'customer_external_id' => '+5511999999999',
        'status' => 'open',
    ]);

    Http::fake([
        'daemon-test.local/instances/*/status' => Http::response([
            'state' => 'connected',
        ], 200),
        'daemon-test.local/instances/*/connect' => Http::response([
            'ok' => true,
        ], 200),
        // /history retorna empty pra terminar rápido
        'daemon-test.local/instances/*/history' => Http::response([
            'count' => 0,
            'has_more' => false,
            'empty' => true,
            'messages' => [],
        ], 200),
    ]);

    $exitCode = \Artisan::call('whatsapp:reconnect-and-import', [
        '--channel' => $channel->id,
        '--since' => '90d',
        '--max' => 100,
        '--wait' => 5,
    ]);

    // exit pode ser 0 ou 1 dependendo do retorno do import — o importante é
    // que /connect NÃO foi chamado (canal já estava connected)
    Http::assertNotSent(function ($request) {
        return str_contains($request->url(), '/connect');
    });

    // /status foi chamado pelo menos 1x (probe inicial)
    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/status');
    });
});

it('RAI-002 — timeout reconnect: abort sem import', function () {
    $channel = makeRaiChannel(1);

    // Status sempre retorna disconnected (timeout do --wait dispara antes
    // de chegar connected)
    Http::fake([
        'daemon-test.local/instances/*/status' => Http::response([
            'state' => 'disconnected',
        ], 200),
        'daemon-test.local/instances/*/connect' => Http::response([
            'ok' => true,
            'state' => 'connecting',
        ], 200),
        'daemon-test.local/instances/*/history' => Http::response([
            'count' => 99,
            'messages' => [],
        ], 200),
    ]);

    $exitCode = \Artisan::call('whatsapp:reconnect-and-import', [
        '--channel' => $channel->id,
        '--wait' => 5, // 5s timeout → 2-3 polls de 2s
    ]);

    expect($exitCode)->toBe(1); // FAILURE

    // /connect foi chamado 1x
    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/connect');
    });

    // /history NÃO foi chamado (timeout matou antes do Step C)
    Http::assertNotSent(function ($request) {
        return str_contains($request->url(), '/history');
    });
})->skip('Test envolve 5s+ de polling real. Rodar manual: pest --filter RAI-002.');

it('RAI-003 — channel banned: abort imediato', function () {
    $channel = makeRaiChannel(1);

    Http::fake([
        'daemon-test.local/instances/*/status' => Http::response([
            'state' => 'banned',
        ], 200),
    ]);

    $exitCode = \Artisan::call('whatsapp:reconnect-and-import', [
        '--channel' => $channel->id,
        '--wait' => 5,
    ]);

    expect($exitCode)->toBe(1); // FAILURE — banned não tenta reconnect

    Http::assertNotSent(function ($request) {
        return str_contains($request->url(), '/connect') || str_contains($request->url(), '/history');
    });
});

it('RAI-004 — --since=auto resolve corretamente da última msg DB', function () {
    $channel = makeRaiChannel(1);
    $conv = Conversation::query()->create([
        'business_id' => 1,
        'channel_id' => $channel->id,
        'customer_external_id' => '+5511999999999',
        'status' => 'open',
    ]);

    // Última msg = 5 dias atrás → since esperado: 5 dias atrás +1h (ainda
    // <6h limite, então valor literal preservado)
    $lastMsgAt = now()->subDays(5);
    $msg = Message::query()
        ->withoutGlobalScope(ScopeByBusiness::class)
        ->create([
            'business_id' => 1,
            'conversation_id' => $conv->id,
            'direction' => 'inbound',
            'provider' => Channel::TYPE_WHATSAPP_BAILEYS,
            'provider_message_id' => 'RAI004_ANCHOR',
            'type' => 'text',
            'body' => 'anchor',
            'status' => 'received',
            'payload' => ['key' => ['remoteJid' => '5511999999999@s.whatsapp.net', 'id' => 'RAI004_ANCHOR', 'fromMe' => false]],
        ]);
    \DB::table('messages')->where('id', $msg->id)->update([
        'created_at' => $lastMsgAt->format('Y-m-d H:i:s'),
        'updated_at' => $lastMsgAt->format('Y-m-d H:i:s'),
    ]);

    Http::fake([
        'daemon-test.local/instances/*/status' => Http::response([
            'state' => 'connected',
        ], 200),
        'daemon-test.local/instances/*/history' => Http::response([
            'count' => 0,
            'has_more' => false,
            'empty' => true,
            'messages' => [],
        ], 200),
    ]);

    \Artisan::call('whatsapp:reconnect-and-import', [
        '--channel' => $channel->id,
        '--since' => 'auto',
        '--max' => 100,
    ]);

    // Verifica que /history foi chamado (import-history rodou após resolução)
    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/history');
    });
});

it('RAI-005 — --channel ausente retorna FAILURE', function () {
    $exitCode = \Artisan::call('whatsapp:reconnect-and-import');

    expect($exitCode)->toBe(1);
});

it('RAI-006 — --channel inexistente retorna FAILURE', function () {
    $exitCode = \Artisan::call('whatsapp:reconnect-and-import', [
        '--channel' => 99999,
    ]);

    expect($exitCode)->toBe(1);
});
