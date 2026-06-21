<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Http\Controllers\Api\WhatsmeowWebhookController;
use Modules\Whatsapp\Jobs\ProcessIncomingWebhookJob;
use Modules\Whatsapp\Services\Centrifugo\CentrifugoPublisher;
use Modules\Whatsapp\Services\WhatsmeowReconciler;

uses(Tests\TestCase::class);

/**
 * R-WA-WHATSMEOW-BROADCAST — anti-regressão incident 2026-05-27 prod biz=1.
 *
 * Sintoma reportado por Wagner: "na tela não está aparecendo as mensagens
 * recebidas". Diagnóstico:
 *
 *   1) daemon Whatsmeow envia webhook pra `status@broadcast` (Stories alheios
 *      do WhatsApp) — não é msg de conversa. Controller dispachava o job mesmo
 *      assim.
 *
 *   2) Job upsertMessageWhatsmeow NÃO preenchia `customer_external_id` no
 *      INSERT da conversation. Coluna é NOT NULL → row criada com '' (default).
 *
 *   3) Combinação (1)+(2): primeira "msg" de status@broadcast criava
 *      conversation lixo `(biz=1, channel=11, customer_external_id='')`.
 *      Próxima msg (real ou status) batia em UNIQUE `conv_biz_ch_ext_uniq`
 *      com Duplicate entry '1-11-'. 5 failed jobs documentados.
 *
 * Fix aplicado:
 *   - WhatsmeowWebhookController: filtra Chat = status@broadcast / @g.us /
 *     @broadcast / @newsletter ANTES de dispatch (padrão portado de
 *     ChannelBaileysWebhookController.php:295-313).
 *   - extractFromWhatsmeow: capturar `external_id` (Chat ou Sender).
 *   - upsertMessageWhatsmeow: defense-in-depth — reject se external_id vazio;
 *     INSERT preenche customer_external_id; WHERE usa external_id (não phone).
 *
 * @see Modules/Whatsapp/Http/Controllers/Api/ChannelBaileysWebhookController.php:295
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

/**
 * INSERT channel via DB::table pra evitar Spatie ActivityLog overhead e
 * permitir tests rodarem sem schema completo de activity_log. Retorna Channel
 * model carregado pra match com controller (que aceita Channel via attribute).
 */
function createWhatsmeowChannelDb(int $businessId, string $uuid, string $label, string $instanceName): Channel
{
    \DB::table('channels')->insert([
        'business_id' => $businessId,
        'channel_uuid' => $uuid,
        'label' => $label,
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
 * Cria channel whatsmeow + monta Request com payload + attribute resolved
 * (simula middleware whatsapp.whatsmeow.signature que normalmente popularia).
 */
function makeWhatsmeowRequest(int $businessId, string $chat, string $providerMessageId = 'WAMID.TEST.001'): array
{
    $channel = createWhatsmeowChannelDb(
        $businessId,
        'cccccccc-0000-0000-0000-' . str_pad((string) $businessId, 12, '0', STR_PAD_LEFT) . str_pad((string) crc32($chat), 4, '0', STR_PAD_LEFT),
        'Suporte',
        'ch-test-' . $businessId,
    );

    $jsonData = json_encode([
        'event' => [
            'Info' => [
                'Chat' => $chat,
                'Sender' => $chat,
                'IsFromMe' => false,
                'IsGroup' => str_contains($chat, '@g.us'),
                'ID' => $providerMessageId,
                'Type' => 'text',
                'PushName' => 'Cliente Teste',
                'Timestamp' => '2026-05-28T10:00:00-03:00',
            ],
            'Message' => ['conversation' => 'Mensagem real de teste'],
        ],
        'type' => 'Message',
    ]);

    $request = Request::create('/api/whatsapp/webhook/whatsmeow/uuid', 'POST', [], [], [], [], json_encode([
        'instanceName' => 'ch-test-' . $businessId,
        'jsonData' => $jsonData,
    ]));
    $request->headers->set('Content-Type', 'application/json');
    $request->setJson(new \Symfony\Component\HttpFoundation\InputBag([
        'instanceName' => 'ch-test-' . $businessId,
        'jsonData' => $jsonData,
    ]));
    $request->attributes->set('whatsapp.business_id', $businessId);
    $request->attributes->set('whatsapp.channel', $channel);

    return ['request' => $request, 'channel' => $channel];
}

function makeController(): WhatsmeowWebhookController
{
    return new WhatsmeowWebhookController(
        app(CentrifugoPublisher::class),
        app(WhatsmeowReconciler::class),
    );
}

it('R-WA-WHATSMEOW-BROADCAST-001 — Chat=status@broadcast NÃO dispatch ProcessIncomingWebhookJob', function () {
    Bus::fake();

    $ctx = makeWhatsmeowRequest(1, 'status@broadcast');
    $response = makeController()->handle($ctx['request']);

    expect($response->getStatusCode())->toBe(200);
    expect(json_decode($response->getContent(), true)['note'])->toBe('status_broadcast_dropped');

    Bus::assertNotDispatched(ProcessIncomingWebhookJob::class);
});

it('R-WA-WHATSMEOW-BROADCAST-002 — Chat=*@g.us (grupo) NÃO dispatch ProcessIncomingWebhookJob', function () {
    Bus::fake();

    $ctx = makeWhatsmeowRequest(1, '120363100100100100@g.us');
    $response = makeController()->handle($ctx['request']);

    expect($response->getStatusCode())->toBe(200);
    expect(json_decode($response->getContent(), true)['note'])->toBe('group_or_broadcast_dropped');

    Bus::assertNotDispatched(ProcessIncomingWebhookJob::class);
});

it('R-WA-WHATSMEOW-BROADCAST-003 — Chat=*@newsletter (canal) NÃO dispatch', function () {
    Bus::fake();

    $ctx = makeWhatsmeowRequest(1, '120363012345@newsletter');
    $response = makeController()->handle($ctx['request']);

    expect($response->getStatusCode())->toBe(200);
    expect(json_decode($response->getContent(), true)['note'])->toBe('group_or_broadcast_dropped');

    Bus::assertNotDispatched(ProcessIncomingWebhookJob::class);
});

it('R-WA-WHATSMEOW-BROADCAST-004 — Chat=@s.whatsapp.net (msg normal) DISPATCH com provider whatsmeow', function () {
    Bus::fake();

    $ctx = makeWhatsmeowRequest(1, '554899872822@s.whatsapp.net');
    $response = makeController()->handle($ctx['request']);

    expect($response->getStatusCode())->toBe(200);
    Bus::assertDispatched(ProcessIncomingWebhookJob::class, fn ($job) => $job->businessId === 1 && $job->provider === 'whatsmeow');
});

it('R-WA-WHATSMEOW-BROADCAST-005 — Job rejeita external_id vazio sem criar row lixo (defense-in-depth)', function () {
    $channel = createWhatsmeowChannelDb(1, 'dddddddd-0000-0000-0000-000000000001', 'Defense', 'ch-defense');

    // Payload onde Chat E Sender vazios (cenário patológico após filtro do
    // controller falhar OU caminho legado). external_id resolve pra '' no extractor.
    $payload = [
        'instanceName' => 'ch-defense',
        'event' => [
            'Info' => [
                'Chat' => '',  // vazio
                'Sender' => '', // vazio
                'IsFromMe' => false,
                'ID' => 'WAMID.EMPTY.001',
                'Type' => 'text',
                'PushName' => 'Anônimo',
            ],
            'Message' => ['conversation' => 'msg sem JID'],
        ],
    ];

    $job = new ProcessIncomingWebhookJob(1, 'whatsmeow', $payload);
    $job->handle();

    // Anti-regressão crítica: nenhuma conversation lixo criada
    $count = \DB::table('conversations')
        ->where('business_id', 1)
        ->where('channel_id', $channel->id)
        ->count();
    expect($count)->toBe(0);

    $msgCount = \DB::table('messages')->where('business_id', 1)->count();
    expect($msgCount)->toBe(0);
});

it('R-WA-WHATSMEOW-BROADCAST-006 — 2 msgs do mesmo Chat NÃO quebram UNIQUE (incident 2026-05-27 anti-regressão)', function () {
    $channel = createWhatsmeowChannelDb(1, 'eeeeeeee-0000-0000-0000-000000000001', 'IncidentGuard', 'ch-incident');

    $chat = '554899872822@s.whatsapp.net';

    $mkPayload = fn (string $msgId, string $body) => [
        'instanceName' => 'ch-incident',
        'event' => [
            'Info' => [
                'Chat' => $chat,
                'Sender' => $chat,
                'SenderAlt' => $chat,
                'IsFromMe' => false,
                'ID' => $msgId,
                'Type' => 'text',
                'PushName' => 'Cliente Real',
            ],
            'Message' => ['conversation' => $body],
        ],
    ];

    // PRE-FIX: 1ª criava conversation com customer_external_id='', 2ª batia em
    // Duplicate entry '1-{channel}-' for key 'conv_biz_ch_ext_uniq'.
    // POST-FIX: external_id capturado do Chat, INSERT preenche corretamente,
    // 2ª msg encontra conversation existente.
    (new ProcessIncomingWebhookJob(1, 'whatsmeow', $mkPayload('WAMID.001', 'primeira')))->handle();
    (new ProcessIncomingWebhookJob(1, 'whatsmeow', $mkPayload('WAMID.002', 'segunda')))->handle();

    // 1 conversation, 2 messages
    $convs = \DB::table('conversations')->where('business_id', 1)->where('channel_id', $channel->id)->get();
    expect($convs->count())->toBe(1);
    expect($convs->first()->customer_external_id)->toBe($chat);

    $msgs = \DB::table('messages')->where('business_id', 1)->orderBy('id')->get();
    expect($msgs->count())->toBe(2);
    expect($msgs[0]->body)->toBe('primeira');
    expect($msgs[1]->body)->toBe('segunda');
});
