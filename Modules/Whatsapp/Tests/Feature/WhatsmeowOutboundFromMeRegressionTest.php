<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Jobs\ProcessIncomingWebhookJob;

uses(Tests\TestCase::class);

/**
 * R-WA-WHATSMEOW-FROMME — anti-regressão: capturar mensagens enviadas pela
 * EQUIPE pelo WhatsApp oficial (Web/celular) no driver whatsmeow.
 *
 * Contexto (2026-05-30):
 *   - O mesmo número fica pareado ao oimpresso via daemon whatsmeow (QR), mas a
 *     equipe continua usando o WhatsApp Web oficial / app no celular. Quando
 *     respondem um cliente por fora do oimpresso, o multi-device replica esse
 *     envio pra sessão do daemon com `Info.IsFromMe = true`.
 *   - PRÉ-FIX: `ProcessIncomingWebhookJob::extractFromWhatsmeow` fazia
 *     `if (IsFromMe) return [];` → toda resposta da equipe sumia. A Caixa
 *     Unificada mostrava só o lado do cliente → conversa parecia sem resposta.
 *   - FIX: não descarta fromMe; persiste como `direction='outbound'`,
 *     `status='sent'`, `sender_kind='human'`. Idempotência por
 *     `provider_message_id` UNIQUE dedup o eco dos envios do próprio oimpresso
 *     (espelha o fix Baileys PR #688 / WebhookOutboundFromMeRegressionTest).
 *
 * Bug que esta suite protege:
 *   Próximo refactor no extractor whatsmeow pode re-introduzir o filtro
 *   `if (IsFromMe) skip` silenciosamente. Estes asserts quebram na hora.
 *
 * Estilo: espelha WhatsmeowBroadcastFilterTest — Schema::create direto, channel
 * via DB::table, instancia o Job e chama handle() sem HTTP real.
 *
 * @see Modules/Whatsapp/Jobs/ProcessIncomingWebhookJob.php (extractFromWhatsmeow / upsertMessageWhatsmeow)
 * @see Modules/Whatsapp/Tests/Feature/WebhookOutboundFromMeRegressionTest.php (equivalente Baileys, PR #688)
 * @see memory/decisions/0204-whatsmeow-driver-substituto-baileys.md
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        test()->markTestSkipped('era-sqlite: schema sintético manual incompatível com MySQL persistente — quarentena Onda 2 SDD floor; burn-down converte depois.');
    }

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
        $table->string('lid', 100)->nullable();
        $table->string('phone_e164', 30)->nullable();
        $table->string('bsuid', 100)->nullable();
        $table->string('contact_name', 120)->nullable();
        $table->string('status', 20)->default('open');
        $table->unsignedInteger('assigned_user_id')->nullable();
        $table->boolean('bot_handling')->default(false);
        $table->boolean('is_blocked')->default(false);
        $table->timestamp('last_inbound_at')->nullable();
        $table->timestamp('last_outbound_at')->nullable();
        $table->timestamp('last_message_at')->nullable();
        $table->unsignedInteger('unread_count')->default(0);
        $table->string('last_message_preview', 120)->nullable();
        $table->string('last_message_direction', 20)->nullable();
        $table->timestamps();

        $table->unique(
            ['business_id', 'channel_id', 'customer_external_id'],
            'conv_biz_ch_ext_uniq'
        );
    });

    Schema::create('messages', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedInteger('business_id');
        $table->unsignedBigInteger('conversation_id');
        $table->string('direction', 10);
        $table->string('provider', 30);
        $table->string('provider_message_id', 128)->nullable();
        $table->string('type', 20)->default('text');
        $table->text('body')->nullable();
        $table->json('payload')->nullable();
        $table->string('status', 20);
        $table->string('sender_kind', 20)->nullable();
        $table->string('media_mime', 100)->nullable();
        $table->unsignedBigInteger('media_size_bytes')->nullable();
        $table->string('media_filename', 255)->nullable();
        $table->timestamps();
        $table->unique('provider_message_id', 'msgs_provider_msg_uniq');
    });

    // Spatie ActivityLog trait em Channel insere aqui em qualquer create()/update().
    // `activity_log` é tabela CORE compartilhada (Spatie). NÃO dropar — em MySQL
    // persistente (nightly CT 100) dropar destrói o schema real e quebra testes
    // alheios. Cria só se não existe (sqlite fresco); no-op em MySQL já-migrado.
    if (! Schema::hasTable('activity_log')) {
        Schema::create('activity_log', function ($table) {
            $table->bigIncrements('id');
            $table->string('log_name')->nullable();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('causer_id')->nullable();
            $table->string('causer_type')->nullable();
            $table->string('event')->nullable();
            $table->uuid('batch_uuid')->nullable();
            $table->json('properties')->nullable();
            $table->timestamps();
        });
    }
});

function makeFromMeChannel(int $businessId, string $uuid, string $instanceName): Channel
{
    \DB::table('channels')->insert([
        'business_id' => $businessId,
        'channel_uuid' => $uuid,
        'label' => 'Suporte',
        'type' => Channel::TYPE_WHATSAPP_WHATSMEOW,
        'status' => 'active',
        'config_json' => json_encode(['whatsmeow' => ['user_name' => $instanceName]]),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return Channel::query()->withoutGlobalScope(ScopeByBusiness::class)
        ->where('channel_uuid', $uuid)
        ->firstOrFail();
}

/**
 * Monta o payload já-unwrapped que o WhatsmeowWebhookController entrega ao Job.
 */
function fromMePayload(string $instanceName, array $info, string $body): array
{
    return [
        'instanceName' => $instanceName,
        'event' => [
            'Info' => $info,
            'Message' => ['conversation' => $body],
        ],
    ];
}

it('R-WA-WHATSMEOW-FROMME-001 — resposta da equipe pelo WhatsApp oficial persiste como outbound/sent/human', function () {
    $channel = makeFromMeChannel(1, 'ffff0001-0000-0000-0000-000000000001', 'ch-fromme-1');

    $payload = fromMePayload('ch-fromme-1', [
        'Chat' => '5548999990001@s.whatsapp.net',   // cliente (destinatário)
        'Sender' => '5548888880002@s.whatsapp.net', // nós
        'SenderAlt' => '5548888880002@s.whatsapp.net',
        'IsFromMe' => true,
        'ID' => 'WAMID.FROMME.001',
        'Type' => 'text',
        'PushName' => 'Atendente Loja', // nome do operador — NÃO deve virar contact_name
    ], 'Oi! Já separei seu pedido.');

    (new ProcessIncomingWebhookJob(1, 'whatsmeow', $payload))->handle();

    $msg = \DB::table('messages')->where('provider_message_id', 'WAMID.FROMME.001')->first();
    expect($msg)->not->toBeNull();
    expect($msg->direction)->toBe('outbound');
    expect($msg->status)->toBe('sent');
    expect($msg->body)->toBe('Oi! Já separei seu pedido.');

    // Conversa keyed no cliente (Chat), não no nosso número
    $conv = \DB::table('conversations')->where('id', $msg->conversation_id)->first();
    expect($conv->customer_external_id)->toBe('5548999990001@s.whatsapp.net');
    expect($conv->phone_e164)->toBe('+5548999990001');
    expect($conv->unread_count)->toBe(0);                 // outbound não conta como não-lida
    expect($conv->last_outbound_at)->not->toBeNull();
    expect($conv->last_inbound_at)->toBeNull();
    expect($conv->last_message_direction)->toBe('outbound');
    // contact_name = phone (não o PushName do operador)
    expect($conv->contact_name)->toBe('+5548999990001');
});

it('R-WA-WHATSMEOW-FROMME-002 — idempotência: mesmo provider_message_id 2× = 1 row', function () {
    $channel = makeFromMeChannel(1, 'ffff0002-0000-0000-0000-000000000002', 'ch-fromme-2');

    $payload = fromMePayload('ch-fromme-2', [
        'Chat' => '5548999990001@s.whatsapp.net',
        'Sender' => '5548888880002@s.whatsapp.net',
        'SenderAlt' => '5548888880002@s.whatsapp.net',
        'IsFromMe' => true,
        'ID' => 'WAMID.FROMME.DUP',
        'Type' => 'text',
        'PushName' => 'Atendente',
    ], 'Mensagem de teste');

    (new ProcessIncomingWebhookJob(1, 'whatsmeow', $payload))->handle();
    (new ProcessIncomingWebhookJob(1, 'whatsmeow', $payload))->handle(); // replay/eco

    $count = \DB::table('messages')->where('provider_message_id', 'WAMID.FROMME.DUP')->count();
    expect($count)->toBe(1);
});

it('R-WA-WHATSMEOW-FROMME-003 — inbound (fromMe=false) continua inbound/received (não-regressão)', function () {
    $channel = makeFromMeChannel(1, 'ffff0003-0000-0000-0000-000000000003', 'ch-fromme-3');

    $payload = fromMePayload('ch-fromme-3', [
        'Chat' => '5548999990003@s.whatsapp.net',
        'Sender' => '5548999990003@s.whatsapp.net',
        'SenderAlt' => '5548999990003@s.whatsapp.net',
        'IsFromMe' => false,
        'ID' => 'WAMID.IN.003',
        'Type' => 'text',
        'PushName' => 'Cliente Maria',
    ], 'Oi, meu pedido chegou?');

    (new ProcessIncomingWebhookJob(1, 'whatsmeow', $payload))->handle();

    $msg = \DB::table('messages')->where('provider_message_id', 'WAMID.IN.003')->first();
    expect($msg->direction)->toBe('inbound');
    expect($msg->status)->toBe('received');
    expect($msg->sender_kind)->toBeNull();

    $conv = \DB::table('conversations')->where('id', $msg->conversation_id)->first();
    expect($conv->unread_count)->toBe(1);
    expect($conv->last_inbound_at)->not->toBeNull();
    expect($conv->contact_name)->toBe('Cliente Maria');
});

it('R-WA-WHATSMEOW-FROMME-004 — fromMe em conversa existente não zera unread nem sobrescreve o nome do cliente', function () {
    $channel = makeFromMeChannel(1, 'ffff0004-0000-0000-0000-000000000004', 'ch-fromme-4');

    $chat = '5548999990004@s.whatsapp.net';

    // 1) cliente manda inbound → cria conversa (nome "Cliente João", unread=1)
    (new ProcessIncomingWebhookJob(1, 'whatsmeow', fromMePayload('ch-fromme-4', [
        'Chat' => $chat, 'Sender' => $chat, 'SenderAlt' => $chat,
        'IsFromMe' => false, 'ID' => 'WAMID.IN.004', 'Type' => 'text',
        'PushName' => 'Cliente João',
    ], 'Bom dia, tem disponível?')))->handle();

    // 2) equipe responde pelo WhatsApp oficial → outbound na MESMA conversa
    (new ProcessIncomingWebhookJob(1, 'whatsmeow', fromMePayload('ch-fromme-4', [
        'Chat' => $chat, 'Sender' => '5548888880002@s.whatsapp.net', 'SenderAlt' => '5548888880002@s.whatsapp.net',
        'IsFromMe' => true, 'ID' => 'WAMID.OUT.004', 'Type' => 'text',
        'PushName' => 'Atendente',
    ], 'Bom dia! Tem sim.')))->handle();

    // 1 conversa, 2 mensagens
    $convs = \DB::table('conversations')->where('customer_external_id', $chat)->get();
    expect($convs->count())->toBe(1);
    $conv = $convs->first();

    expect(\DB::table('messages')->where('conversation_id', $conv->id)->count())->toBe(2);
    // nome do cliente preservado (não vira "Atendente")
    expect($conv->contact_name)->toBe('Cliente João');
    // unread não incrementa no outbound (continua 1 do inbound)
    expect($conv->unread_count)->toBe(1);
    expect($conv->last_outbound_at)->not->toBeNull();
    expect($conv->last_message_direction)->toBe('outbound');

    $out = \DB::table('messages')->where('provider_message_id', 'WAMID.OUT.004')->first();
    expect($out->direction)->toBe('outbound');
});
