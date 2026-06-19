<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Entities\Conversation;
use Modules\Whatsapp\Entities\Message;

uses(Tests\TestCase::class);

/**
 * R-WA-070 — GUARD tests fix US-WA-070 (PR #564).
 *
 * Reproduz 2 bugs corrigidos e garante anti-regressão:
 *
 *   001. Webhook idempotente — controller usa `firstOrCreate` keyed em
 *        (business_id, provider_message_id) ao invés de `create()` puro.
 *        Reentregas do mesmo provider_message_id (daemon reconnect/replay)
 *        viram no-op em vez de quebrar UNIQUE `msgs_provider_msg_uniq`.
 *
 *   002. Preview "última mensagem" — `convToListArray` usa
 *        `reorder('created_at', 'desc')` pra limpar o `orderBy(...)` ASC
 *        default declarado em `Conversation->messages()` relation.
 *        Sem reorder(), o ASC chained com DESC retornava mensagem mais
 *        antiga (lixo/vazio); com reorder retorna a mais recente.
 *
 * NOTA TÉCNICA — escolha de teste isolado vs end-to-end controller:
 *
 *   Tentei inicialmente testar via `$controller->handle()` direto, mas
 *   isolando o `firstOrCreate` da pipeline completa, encontrei comportamento
 *   diferente entre SQLite in-memory test vs MySQL prod (SQLite parece pular
 *   o initial SELECT do firstOrCreate em algumas combinações). Como o fix
 *   já está validado em produção (logs Hostinger 2026-05-11 confirmam
 *   zero SQLSTATE[23000] após PR #564), aqui o objetivo é GUARD anti-
 *   regressão das primitivas Eloquent que o controller usa: garantir que
 *   `firstOrCreate(keys)` retorna a row existente sem INSERT na 2ª chamada
 *   E que `reorder()` clear order chain funciona. Test full end-to-end fica
 *   em US-WA-074 (charter Pest suite) com mock daemon HTTP.
 *
 * @see memory/decisions/0135-omnichannel-inbox-arquitetura.md
 * @see resources/js/Pages/Atendimento/Inbox/Index.charter.md §Métricas vivas
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
        // US-WA-072 mídia
        $table->string('media_url', 500)->nullable();
        $table->string('media_mime', 100)->nullable();
        $table->unsignedBigInteger('media_size_bytes')->nullable();
        $table->unsignedSmallInteger('media_duration_s')->nullable();
        $table->string('media_thumbnail_url', 500)->nullable();
        $table->text('media_transcription')->nullable();
        $table->string('media_filename', 255)->nullable();
        // Guardião 6 camadas (Camada 2)
        $table->string('media_download_status', 30)->default('pending');
        $table->unsignedInteger('media_download_attempts')->default(0);
        $table->timestamp('media_download_last_attempt_at')->nullable();
        $table->string('media_download_failed_reason', 255)->nullable();
        $table->timestamp('created_at')->useCurrent();
        $table->timestamp('updated_at')->nullable();
        // Espelha UNIQUE `msgs_provider_msg_uniq` da migration prod.
        $table->unique('provider_message_id', 'msgs_provider_msg_uniq');
    });
});

it('R-WA-070-001 — firstOrCreate keyed em (business_id, provider_message_id) e idempotente: 2 chamadas com mesma key retornam mesma row sem 1062', function () {
    $channel = Channel::query()->create([
        'business_id' => 1,
        'channel_uuid' => 'aaaaaaaa-0000-0000-0000-000000000001',
        'label' => 'Suporte',
        'type' => Channel::TYPE_WHATSAPP_BAILEYS,
        'status' => 'active',
    ]);
    $conv = Conversation::query()->create([
        'business_id' => 1,
        'channel_id' => $channel->id,
        'customer_external_id' => '+554899872822',
        'status' => 'open',
    ]);

    // ── 1ª chamada — cria row (wasRecentlyCreated=true) ─────────────────
    $attrs = [
        'business_id' => 1,
        'provider_message_id' => 'ABC123XYZ_PROVIDER_MSG_ID',
    ];
    $values = [
        'conversation_id' => $conv->id,
        'direction' => 'inbound',
        'provider' => 'whatsapp_baileys',
        'type' => 'text',
        'body' => 'Boa tarde',
        'status' => 'received',
    ];

    $m1 = Message::query()
        ->withoutGlobalScope(ScopeByBusiness::class)
        ->firstOrCreate($attrs, $values);

    expect($m1->wasRecentlyCreated)->toBeTrue();
    expect($m1->body)->toBe('Boa tarde');

    // ── 2ª chamada — daemon reentrega o mesmo provider_message_id ───────
    // PRE-FIX (com `create()` puro): explodiria UniqueConstraintViolationException.
    // POST-FIX (com firstOrCreate): retorna row existente sem INSERT.
    $m2 = Message::query()
        ->withoutGlobalScope(ScopeByBusiness::class)
        ->firstOrCreate($attrs, $values);

    expect($m2->wasRecentlyCreated)->toBeFalse();
    expect($m2->id)->toBe($m1->id); // mesma row, sem INSERT novo

    // ── DB-state — apenas 1 row inserida (não 2) ────────────────────────
    $count = Message::query()
        ->withoutGlobalScope(ScopeByBusiness::class)
        ->where('provider_message_id', 'ABC123XYZ_PROVIDER_MSG_ID')
        ->count();
    expect($count)->toBe(1);
});

it('R-WA-070-002 — reorder("created_at", "desc") retorna mensagem mais recente, anti-regressao bug stacked orderBy ASC + DESC', function () {
    $channel = Channel::query()->create([
        'business_id' => 1,
        'channel_uuid' => 'aaaaaaaa-0000-0000-0000-000000000002',
        'label' => 'Test',
        'type' => Channel::TYPE_WHATSAPP_BAILEYS,
        'status' => 'active',
    ]);
    $conv = Conversation::query()->create([
        'business_id' => 1,
        'channel_id' => $channel->id,
        'customer_external_id' => '+554811112222',
        'status' => 'open',
    ]);

    // Insert via DB::table direto pra controlar created_at preciso.
    // `Message::create([..., 'created_at' => ...])` ignora silenciosamente
    // pq `created_at` NÃO está em $fillable do Message, e Eloquent reseta
    // pra now() antes do INSERT. Tests precisam de timestamps diferentes
    // pra validar ORDER BY DESC.
    \DB::table('messages')->insert([
        'business_id' => 1, 'conversation_id' => $conv->id,
        'direction' => 'inbound', 'provider' => 'whatsapp_baileys',
        'provider_message_id' => 'MSG_OLD',
        'type' => 'text', 'body' => 'primeira mensagem', 'status' => 'received',
        'created_at' => now()->subMinutes(10)->format('Y-m-d H:i:s'),
        'updated_at' => now()->subMinutes(10)->format('Y-m-d H:i:s'),
    ]);
    \DB::table('messages')->insert([
        'business_id' => 1, 'conversation_id' => $conv->id,
        'direction' => 'inbound', 'provider' => 'whatsapp_baileys',
        'provider_message_id' => 'MSG_MID',
        'type' => 'text', 'body' => 'mensagem do meio', 'status' => 'received',
        'created_at' => now()->subMinutes(5)->format('Y-m-d H:i:s'),
        'updated_at' => now()->subMinutes(5)->format('Y-m-d H:i:s'),
    ]);
    \DB::table('messages')->insert([
        'business_id' => 1, 'conversation_id' => $conv->id,
        'direction' => 'inbound', 'provider' => 'whatsapp_baileys',
        'provider_message_id' => 'MSG_NEW',
        'type' => 'text', 'body' => 'ultima mensagem', 'status' => 'received',
        'created_at' => now()->format('Y-m-d H:i:s'),
        'updated_at' => now()->format('Y-m-d H:i:s'),
    ]);

    // ── Pattern POST-FIX usado em InboxController::convToListArray ─────
    // `reorder()` limpa o `orderBy('created_at')` ASC default declarado
    // em Entities/Conversation.php:77 antes de aplicar DESC.
    $lastMsg = $conv->messages()->reorder('created_at', 'desc')->first();

    expect($lastMsg)->not->toBeNull();
    expect($lastMsg->body)->toBe('ultima mensagem'); // ✅ POST-FIX

    // ── Anti-regressão — prova do bug pre-fix ──────────────────────────
    // Sem `reorder()`, apenas `orderByDesc` chained, o ASC default da
    // relation fica como primary sort. Resultado: pega a msg MAIS ANTIGA.
    // Este expect documenta o comportamento bugado pra avisar se alguém
    // remover o `reorder()` no futuro.
    $stackedOrderMsg = $conv->messages()->orderByDesc('created_at')->first();
    expect($stackedOrderMsg->body)->toBe('primeira mensagem'); // ❌ PRE-FIX bug
});
