<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Entities\ChannelUserAccess;
use Modules\Whatsapp\Entities\Conversation;
use Modules\Whatsapp\Entities\Message;
use Modules\Whatsapp\Http\Controllers\Admin\InboxController;
use Modules\Whatsapp\Http\Controllers\Api\ChannelBaileysWebhookController;
use Modules\Whatsapp\Jobs\DownloadMediaJob;

uses(Tests\TestCase::class);

/**
 * R-WA-MEDIA-INBOUND — US-WA-043 PR-8 CYCLE-07.
 *
 * Garante 4 entregas chave:
 *   001. Webhook inbound type=image dispara DownloadMediaJob mesmo SEM
 *        media_url top-level (Baileys com payload aninhado .enc + mediaKey).
 *   002. Filtro `media_inbound_24h` retorna apenas conversations que
 *        receberam image/audio/video/document inbound nas últimas 24h.
 *   003. Conv SEM mídia recente NÃO aparece quando filtro ativo.
 *   004. Tier 0 (ADR 0093): cross-tenant biz=99 invisível mesmo com
 *        filtro `media_inbound_24h=true`.
 *
 * Pattern reusa schema/helper de `InboxFiltersTest` + `MediaMessageTest`.
 */
beforeEach(function () {
    foreach (['contacts', 'messages', 'channel_user_access', 'whatsapp_conversation_tags', 'whatsapp_tags', 'conversations', 'channels'] as $t) {
        Schema::dropIfExists($t);
    }

    // Tabela `contacts` stub — webhook chama ConversationContactLinker que faz
    // LIKE em mobile/landline. Stub mínimo só pra evitar "no such table".
    Schema::create('contacts', function ($table) {
        $table->increments('id');
        $table->unsignedInteger('business_id');
        $table->string('name', 100);
        $table->string('mobile', 30)->nullable();
        $table->string('landline', 30)->nullable();
        $table->string('alternate_number', 30)->nullable();
        $table->softDeletes();
        $table->timestamps();
    });

    Schema::create('whatsapp_tags', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedInteger('business_id');
        $table->string('slug', 40);
        $table->string('label', 80);
        $table->string('color', 20)->default('slate');
        $table->unsignedInteger('sort_order')->default(0);
        $table->timestamps();
        $table->unique(['business_id', 'slug'], 'wa_tags_biz_slug_uniq');
    });

    Schema::create('whatsapp_conversation_tags', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedBigInteger('conversation_id');
        $table->unsignedBigInteger('tag_id');
        $table->timestamp('created_at')->useCurrent();
        $table->timestamp('updated_at')->nullable();
        $table->unsignedInteger('created_by_user_id')->nullable();
        $table->unique(['conversation_id', 'tag_id'], 'wa_conv_tags_uniq');
    });

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

    Schema::create('channel_user_access', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedInteger('business_id');
        $table->unsignedBigInteger('channel_id');
        $table->unsignedInteger('user_id');
        $table->unsignedInteger('granted_by_user_id');
        $table->timestamp('granted_at');
        $table->timestamp('revoked_at')->nullable();
        $table->unsignedInteger('revoked_by_user_id')->nullable();
        $table->timestamps();
        $table->unique(['channel_id', 'user_id', 'revoked_at'], 'cua_channel_user_unq');
        $table->index(['business_id', 'user_id'], 'cua_biz_user_idx');
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
        // US-WA-072 cols
        $table->string('media_url', 500)->nullable();
        $table->string('media_mime', 100)->nullable();
        $table->unsignedBigInteger('media_size_bytes')->nullable();
        $table->unsignedSmallInteger('media_duration_s')->nullable();
        $table->string('media_thumbnail_url', 500)->nullable();
        $table->text('media_transcription')->nullable();
        $table->string('media_filename', 255)->nullable();
        $table->string('media_download_status', 30)->nullable();
        $table->unsignedInteger('media_download_attempts')->default(0);
        $table->timestamp('media_download_last_attempt_at')->nullable();
        $table->string('media_download_failed_reason', 255)->nullable();
        $table->timestamp('created_at')->useCurrent();
        $table->timestamp('updated_at')->nullable();
        $table->unique('provider_message_id', 'msgs_provider_msg_uniq_mip');
    });
});

/** Helper: usuário stub + grant canal (espelha helpers do InboxFiltersTest). */
function mipSetUserAndGrant(int $businessId, int $userId, array $channelIds): void
{
    $stub = new class extends \Illuminate\Foundation\Auth\User {
        protected $table = 'users';
        protected $guarded = [];
        public function can($abilities, $arguments = []): bool { return false; }
    };
    $stub->id = $userId;
    $stub->business_id = $businessId;
    auth()->setUser($stub);

    session()->put('user.business_id', $businessId);
    session()->put('user.id', $userId);
    app()->forgetInstance(ScopeByBusiness::class);

    foreach ($channelIds as $chId) {
        ChannelUserAccess::withoutGlobalScope(ScopeByBusiness::class)->create([
            'business_id' => $businessId,
            'channel_id' => $chId,
            'user_id' => $userId,
            'granted_by_user_id' => 1,
            'granted_at' => now(),
        ]);
    }
}

function mipMakeChannel(int $businessId, string $uuid): Channel
{
    return Channel::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => $businessId,
        'channel_uuid' => $uuid,
        'label' => 'Suporte',
        'type' => Channel::TYPE_WHATSAPP_BAILEYS,
        'status' => 'active',
    ]);
}

function mipMakeConv(int $businessId, int $channelId, array $attrs = []): Conversation
{
    return Conversation::withoutGlobalScope(ScopeByBusiness::class)->create(array_merge([
        'business_id' => $businessId,
        'channel_id' => $channelId,
        'customer_external_id' => '+5511' . str_pad((string) random_int(1, 99999999), 8, '0', STR_PAD_LEFT),
        'contact_name' => 'Cliente',
        'status' => 'open',
        'last_message_at' => now(),
    ], $attrs));
}

function mipMakeInboundMedia(int $businessId, int $convId, string $type = 'image', ?string $createdAt = null): int
{
    // DB::table direto pra controlar `created_at` preciso (Eloquent reseta
    // pra now() ignorando timestamps custom mesmo passando no array).
    $ts = $createdAt ?? now()->toDateTimeString();
    return (int) \DB::table('messages')->insertGetId([
        'business_id' => $businessId,
        'conversation_id' => $convId,
        'direction' => 'inbound',
        'provider' => 'whatsapp_baileys',
        'provider_message_id' => 'msg-' . random_int(1, 999999999),
        'type' => $type,
        'body' => null,
        'status' => 'received',
        'media_mime' => $type === 'image' ? 'image/jpeg' : ($type === 'audio' ? 'audio/ogg' : 'application/pdf'),
        'created_at' => $ts,
        'updated_at' => $ts,
    ]);
}

function mipBuildRequest(array $query = []): Request
{
    $qs = $query ? '?' . http_build_query($query) : '';
    $request = Request::create('/atendimento/inbox' . $qs, 'GET');
    $request->setLaravelSession(app('session.store'));
    return $request;
}

function mipIndexProps(InboxController $controller, Request $request): array
{
    $token = Mockery::mock(\Modules\Whatsapp\Services\Centrifugo\CentrifugoTokenIssuer::class);
    $token->shouldReceive('issue')->andReturn(null);

    $response = $controller->index($request, $token);

    $reflection = new \ReflectionClass($response);
    $propsProp = $reflection->getProperty('props');
    $propsProp->setAccessible(true);

    return $propsProp->getValue($response);
}

function mipConvIds(array $props): array
{
    $data = $props['conversations']['data'] ?? [];
    if (is_callable($data)) $data = $data();
    if ($data instanceof \Illuminate\Support\Collection) $data = $data->all();
    return collect($data)->pluck('id')->map(fn ($id) => (int) $id)->all();
}

// ============================================================================
// 001 — Webhook dispatcha DownloadMediaJob mesmo SEM media_url top-level (Baileys aninhado)
// ============================================================================

it('R-WA-MEDIA-INBOUND-001 — Inbound type=image SEM media_url dispatcha DownloadMediaJob (Baileys aninhado)', function () {
    Bus::fake();
    $ch = mipMakeChannel(1, 'mip-001-uuid');

    // Payload Baileys com imageMessage aninhado (.enc + mediaKey) — caso típico
    // antes do daemon C normalizar. media_url top-level vem null.
    $payload = [
        'event' => 'message',
        'data' => [
            'key' => [
                'remoteJid' => '5548999999999@s.whatsapp.net',
                'id' => 'BAILEYS_MID_IMG_TEST_001',
                'fromMe' => false,
            ],
            'message' => [
                'imageMessage' => [
                    'url' => 'https://mmg.whatsapp.net/v/t62.7118-24/encrypted.enc',
                    'mimetype' => 'image/jpeg',
                    'fileLength' => 12345,
                    'mediaKey' => base64_encode(random_bytes(32)),
                ],
            ],
            'push_name' => 'Cliente Test',
        ],
    ];

    $request = Request::create('/api/atendimento/channels/baileys/' . $ch->channel_uuid, 'POST', $payload);
    $controller = new ChannelBaileysWebhookController();
    $response = $controller->handle($request, $ch->channel_uuid);

    expect($response->getStatusCode())->toBe(200);

    // Verifica: msg persistida com type=image E DownloadMediaJob dispatched.
    $msg = Message::withoutGlobalScope(ScopeByBusiness::class)
        ->where('provider_message_id', 'BAILEYS_MID_IMG_TEST_001')
        ->first();
    expect($msg)->not->toBeNull();
    expect($msg->type)->toBe('image');
    expect($msg->media_mime)->toBe('image/jpeg');
    expect($msg->media_url)->toBeNull(); // .enc não decifrado ainda

    Bus::assertDispatched(DownloadMediaJob::class, function (DownloadMediaJob $job) use ($msg) {
        return $job->businessId === 1
            && $job->messageId === $msg->id
            && $job->expectedMime === 'image/jpeg';
    });
});

// ============================================================================
// 002 — Filtro media_inbound_24h retorna apenas convs com mídia <24h
// ============================================================================

it('R-WA-MEDIA-INBOUND-002 — filtro media_inbound_24h retorna convs com mídia inbound nas últimas 24h', function () {
    $ch = mipMakeChannel(1, 'mip-002-uuid');

    $convWithImage = mipMakeConv(1, $ch->id);
    mipMakeInboundMedia(1, $convWithImage->id, 'image');

    $convWithAudio = mipMakeConv(1, $ch->id);
    mipMakeInboundMedia(1, $convWithAudio->id, 'audio');

    $convNoMedia = mipMakeConv(1, $ch->id);
    Message::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'conversation_id' => $convNoMedia->id,
        'direction' => 'inbound',
        'provider' => 'whatsapp_baileys',
        'provider_message_id' => 'msg-only-text',
        'type' => 'text',
        'body' => 'olá',
        'status' => 'received',
    ]);

    mipSetUserAndGrant(1, 10, [$ch->id]);

    $props = mipIndexProps(new InboxController(), mipBuildRequest(['media_inbound_24h' => 'true']));

    $ids = mipConvIds($props);
    expect($ids)->toHaveCount(2)
        ->and($ids)->toContain($convWithImage->id, $convWithAudio->id)
        ->and($ids)->not->toContain($convNoMedia->id)
        ->and($props['mediaInbound24h'])->toBeTrue();
});

// ============================================================================
// 003 — Mídia inbound > 24h NÃO conta
// ============================================================================

it('R-WA-MEDIA-INBOUND-003 — conv com mídia inbound > 24h NÃO aparece no filtro', function () {
    $ch = mipMakeChannel(1, 'mip-003-uuid');

    $convFresh = mipMakeConv(1, $ch->id);
    mipMakeInboundMedia(1, $convFresh->id, 'image', now()->subHours(2)->toDateTimeString());

    $convStale = mipMakeConv(1, $ch->id);
    mipMakeInboundMedia(1, $convStale->id, 'image', now()->subDays(3)->toDateTimeString());

    mipSetUserAndGrant(1, 10, [$ch->id]);

    $props = mipIndexProps(new InboxController(), mipBuildRequest(['media_inbound_24h' => 'true']));

    $ids = mipConvIds($props);
    expect($ids)->toBe([$convFresh->id])
        ->and($ids)->not->toContain($convStale->id);
});

// ============================================================================
// 004 — Tier 0 cross-tenant isolation
// ============================================================================

it('R-WA-MEDIA-INBOUND-004 — Tier 0: biz=99 invisível mesmo com media_inbound_24h=true', function () {
    $ch1 = mipMakeChannel(1, 'mip-004-biz1-uuid');
    $ch99 = mipMakeChannel(99, 'mip-004-biz99-uuid');

    $convBiz1 = mipMakeConv(1, $ch1->id);
    mipMakeInboundMedia(1, $convBiz1->id, 'image');

    $convBiz99 = mipMakeConv(99, $ch99->id);
    mipMakeInboundMedia(99, $convBiz99->id, 'image');

    // User logado em biz=1 com grant só no canal biz=1
    mipSetUserAndGrant(1, 10, [$ch1->id]);

    $props = mipIndexProps(new InboxController(), mipBuildRequest(['media_inbound_24h' => 'true']));

    $ids = mipConvIds($props);
    expect($ids)->toBe([$convBiz1->id])
        ->and($ids)->not->toContain($convBiz99->id);
});

// ============================================================================
// 005 — last_message_type exposto pra UI
// ============================================================================

it('R-WA-MEDIA-INBOUND-005 — last_message_type exposto no convToListArray pra ícone semântico', function () {
    $ch = mipMakeChannel(1, 'mip-005-uuid');

    $conv = mipMakeConv(1, $ch->id);
    mipMakeInboundMedia(1, $conv->id, 'audio');

    mipSetUserAndGrant(1, 10, [$ch->id]);

    $props = mipIndexProps(new InboxController(), mipBuildRequest());

    $data = $props['conversations']['data'];
    if ($data instanceof \Illuminate\Support\Collection) $data = $data->all();
    $row = collect($data)->firstWhere('id', $conv->id);

    expect($row)->not->toBeNull()
        ->and($row['last_message_type'])->toBe('audio');
});
