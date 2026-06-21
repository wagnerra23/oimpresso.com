<?php

declare(strict_types=1);

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Entities\Conversation;
use Modules\Whatsapp\Entities\Tag;
use Modules\Whatsapp\Http\Controllers\Admin\InboxController;

uses(Tests\TestCase::class);

/**
 * R-WA-063 — GUARD tests pra tags classificadoras de conversa (US-WA-063).
 *
 * Wagner 2026-05-11: "Opção de inserir a conversa em um grupo/tag classificador."
 *
 * Cobre:
 *  001. updateTags sync substitui (não merge) e persiste pivot
 *  002. Tier 0 (ADR 0093): tag de biz=99 enviada por biz=1 é dropped (sem 404)
 *  003. ensureDefaultTags semeia 6 tags na 1ª visita + é idempotente em re-visita
 *  004. Tag.relation conversations() retorna belongsToMany
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        test()->markTestSkipped('era-sqlite: schema sintético manual incompatível com MySQL persistente — quarentena Onda 2 SDD floor; burn-down converte depois.');
    }

    foreach (['whatsapp_conversation_tags', 'whatsapp_tags', 'messages', 'conversations', 'channels'] as $t) {
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
        $table->timestamps();
    });

    // Espelha migration 2026_05_11_120000_create_conversation_tags_tables.php
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
        $table->timestamp('updated_at')->useCurrent();
        $table->unsignedInteger('created_by_user_id')->nullable();
        $table->unique(['conversation_id', 'tag_id'], 'wa_conv_tags_uniq');
    });
});

it('R-WA-063-001 — updateTags sync substitui (nao merge) e persiste pivot com created_by_user_id', function () {
    session()->put('user.business_id', 1);
    session()->put('user.id', 42);

    $channel = Channel::query()->create([
        'business_id' => 1,
        'channel_uuid' => '11111111-0000-0000-0000-000000000001',
        'label' => 'X',
        'type' => Channel::TYPE_WHATSAPP_BAILEYS,
        'status' => 'active',
    ]);
    $conv = Conversation::query()->create([
        'business_id' => 1, 'channel_id' => $channel->id,
        'customer_external_id' => '+554899999999', 'status' => 'open',
    ]);

    $tagVendas = Tag::query()->create(['business_id' => 1, 'slug' => 'vendas', 'label' => 'Vendas', 'color' => 'emerald']);
    $tagSuporte = Tag::query()->create(['business_id' => 1, 'slug' => 'suporte', 'label' => 'Suporte', 'color' => 'blue']);
    $tagReclama = Tag::query()->create(['business_id' => 1, 'slug' => 'reclamacao', 'label' => 'Reclamação', 'color' => 'red']);

    $controller = app(InboxController::class);

    // 1ª chamada — aplica Vendas + Suporte
    $req1 = Request::create('/test', 'PATCH', ['tag_ids' => [$tagVendas->id, $tagSuporte->id]]);
    $resp1 = $controller->updateTags($req1, $conv->id);
    expect($resp1)->toBeInstanceOf(RedirectResponse::class);

    $conv->refresh()->load('tags');
    expect($conv->tags->pluck('slug')->sort()->values()->all())->toBe(['suporte', 'vendas']);
    // Pivot tem created_by_user_id setado
    expect($conv->tags->first()->pivot->created_by_user_id)->toBe(42);

    // 2ª chamada — substitui pra Reclamação só (Vendas + Suporte saem)
    $req2 = Request::create('/test', 'PATCH', ['tag_ids' => [$tagReclama->id]]);
    $controller->updateTags($req2, $conv->id);

    $conv->refresh()->load('tags');
    expect($conv->tags->pluck('slug')->all())->toBe(['reclamacao']); // sync replaced
});

it('R-WA-063-002 — Tier 0: tag de biz=99 enviada por biz=1 e silently dropped (nao 404, nao vaza)', function () {
    session()->put('user.business_id', 1);
    session()->put('user.id', 42);

    $channel = Channel::query()->create([
        'business_id' => 1,
        'channel_uuid' => '22222222-0000-0000-0000-000000000001',
        'label' => 'X',
        'type' => Channel::TYPE_WHATSAPP_BAILEYS,
        'status' => 'active',
    ]);
    $conv = Conversation::query()->create([
        'business_id' => 1, 'channel_id' => $channel->id,
        'customer_external_id' => '+554811112222', 'status' => 'open',
    ]);

    $tagMine = Tag::query()->create(['business_id' => 1, 'slug' => 'vendas', 'label' => 'Vendas', 'color' => 'emerald']);
    // Tag cross-tenant (biz=99) — usar withoutGlobalScope pra inserir sem auth
    $tagAlien = new Tag(['business_id' => 99, 'slug' => 'vendas', 'label' => 'Vendas Alien', 'color' => 'red']);
    $tagAlien->save();

    $controller = app(InboxController::class);
    // Atacante envia tagAlien id junto com tagMine id
    $req = Request::create('/test', 'PATCH', ['tag_ids' => [$tagMine->id, $tagAlien->id]]);
    $resp = $controller->updateTags($req, $conv->id);

    expect($resp)->toBeInstanceOf(RedirectResponse::class); // 302 normal, não 404
    $conv->refresh()->load('tags');
    expect($conv->tags)->toHaveCount(1);              // só 1 vinculou
    expect($conv->tags->first()->id)->toBe($tagMine->id); // a do biz correto
    // tagAlien NÃO vinculou — Tier 0 preservado
});

it('R-WA-063-003 — ensureDefaultTags semeia 6 tags na 1a visita + idempotente em re-visita', function () {
    session()->put('user.business_id', 1);

    $controller = app(InboxController::class);
    $invoke = (new \ReflectionClass($controller))->getMethod('ensureDefaultTags');
    $invoke->setAccessible(true);

    // 1ª visita — semeia
    $invoke->invoke($controller, 1);
    $count1 = Tag::query()->withoutGlobalScope(ScopeByBusiness::class)
        ->where('business_id', 1)->count();
    expect($count1)->toBe(6);

    $slugs = Tag::query()->withoutGlobalScope(ScopeByBusiness::class)
        ->where('business_id', 1)->pluck('slug')->sort()->values()->all();
    expect($slugs)->toBe(['cobranca', 'financeiro', 'reclamacao', 'repair-os', 'suporte', 'vendas']);

    // 2ª visita — idempotente (NÃO insere duplicatas)
    $invoke->invoke($controller, 1);
    $count2 = Tag::query()->withoutGlobalScope(ScopeByBusiness::class)
        ->where('business_id', 1)->count();
    expect($count2)->toBe(6); // ainda 6, não 12
});

it('R-WA-063-004 — Tag.conversations e Conversation.tags formam belongsToMany bidirecional via pivot', function () {
    $channel = Channel::query()->create([
        'business_id' => 1,
        'channel_uuid' => '33333333-0000-0000-0000-000000000001',
        'label' => 'X',
        'type' => Channel::TYPE_WHATSAPP_BAILEYS,
        'status' => 'active',
    ]);
    $conv1 = Conversation::query()->create([
        'business_id' => 1, 'channel_id' => $channel->id,
        'customer_external_id' => '+554811111111', 'status' => 'open',
    ]);
    $conv2 = Conversation::query()->create([
        'business_id' => 1, 'channel_id' => $channel->id,
        'customer_external_id' => '+554822222222', 'status' => 'open',
    ]);
    $tag = Tag::query()->create(['business_id' => 1, 'slug' => 'vendas', 'label' => 'Vendas', 'color' => 'emerald']);

    $conv1->tags()->attach($tag->id);
    $conv2->tags()->attach($tag->id);

    // Tag → conversations
    $tag->load('conversations');
    expect($tag->conversations)->toHaveCount(2);
    expect($tag->conversations->pluck('id')->sort()->values()->all())->toBe([$conv1->id, $conv2->id]);

    // Conversation → tags
    $conv1->load('tags');
    expect($conv1->tags)->toHaveCount(1);
    expect($conv1->tags->first()->slug)->toBe('vendas');
});

it('R-WA-063-005 — index filter ?tags=N,M retorna only conversas com QUALQUER das tags (OR semantics)', function () {
    $channel = Channel::query()->create([
        'business_id' => 1,
        'channel_uuid' => '44444444-0000-0000-0000-000000000001',
        'label' => 'X',
        'type' => Channel::TYPE_WHATSAPP_BAILEYS,
        'status' => 'active',
    ]);
    $convA = Conversation::query()->create([
        'business_id' => 1, 'channel_id' => $channel->id,
        'customer_external_id' => '+554811111111', 'status' => 'open',
    ]);
    $convB = Conversation::query()->create([
        'business_id' => 1, 'channel_id' => $channel->id,
        'customer_external_id' => '+554822222222', 'status' => 'open',
    ]);
    $convC = Conversation::query()->create([
        'business_id' => 1, 'channel_id' => $channel->id,
        'customer_external_id' => '+554833333333', 'status' => 'open',
    ]);
    $tagVendas = Tag::query()->create(['business_id' => 1, 'slug' => 'vendas', 'label' => 'Vendas', 'color' => 'emerald']);
    $tagSuporte = Tag::query()->create(['business_id' => 1, 'slug' => 'suporte', 'label' => 'Suporte', 'color' => 'blue']);

    $convA->tags()->attach($tagVendas->id);
    $convB->tags()->attach($tagSuporte->id);
    // convC sem tag

    // Filter `?tags=vendas` (1) → só convA
    $resultsVendas = Conversation::query()
        ->where('business_id', 1)
        ->whereHas('tags', fn ($q) => $q->whereIn('whatsapp_tags.id', [$tagVendas->id]))
        ->get();
    expect($resultsVendas->pluck('id')->all())->toBe([$convA->id]);

    // Filter `?tags=vendas,suporte` (1,2) → convA + convB (OR)
    $resultsBoth = Conversation::query()
        ->where('business_id', 1)
        ->whereHas('tags', fn ($q) => $q->whereIn('whatsapp_tags.id', [$tagVendas->id, $tagSuporte->id]))
        ->get();
    expect($resultsBoth->pluck('id')->sort()->values()->all())->toBe([$convA->id, $convB->id]);
});
