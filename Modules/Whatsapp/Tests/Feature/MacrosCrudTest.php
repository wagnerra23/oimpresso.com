<?php

declare(strict_types=1);

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Entities\Conversation;
use Modules\Whatsapp\Entities\Macro;
use Modules\Whatsapp\Entities\Message;
use Modules\Whatsapp\Entities\Tag;
use Modules\Whatsapp\Http\Controllers\Admin\MacrosController;
use Modules\Whatsapp\Services\Macros\MacroExecutor;

uses(Tests\TestCase::class);

/**
 * R-WA-048 — GUARD tests pra Macros (quick replies + automation actions).
 *
 * Cobre:
 *  001. CRUD basic (create + update + destroy)
 *  002. shortcut unique per-business + normalização (lowercase, sem barra)
 *  003. apply incrementa used_count + cria msg + aplica actions
 *  004. cross-tenant biz=99 (Tier 0 ADR 0093) — biz=1 não vê macro alheia
 *  005. macro deletada não afeta msgs históricas
 *
 * @see memory/requisitos/Whatsapp/COMPARATIVO-MERCADO-2026-05-12.md
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        test()->markTestSkipped('era-sqlite: schema sintético manual incompatível com MySQL persistente — quarentena Onda 2 SDD floor; burn-down converte depois.');
    }

    foreach (['macros', 'whatsapp_conversation_tags', 'whatsapp_tags', 'messages', 'conversations', 'channels'] as $t) {
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
        $table->boolean('is_blocked')->default(false);
        $table->timestamp('last_inbound_at')->nullable();
        $table->timestamp('last_outbound_at')->nullable();
        $table->timestamp('last_message_at')->nullable();
        $table->unsignedInteger('unread_count')->default(0);
        $table->string('last_message_preview', 200)->nullable();
        $table->string('last_message_direction', 10)->nullable();
        $table->timestamps();
    });

    Schema::create('messages', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedInteger('business_id');
        $table->unsignedBigInteger('conversation_id');
        $table->string('direction', 10);
        $table->string('provider', 30);
        $table->string('provider_message_id', 200)->nullable();
        $table->string('type', 30);
        $table->string('template_name', 64)->nullable();
        $table->string('subject', 200)->nullable();
        $table->text('body')->nullable();
        $table->json('payload')->nullable();
        $table->string('status', 20)->default('queued');
        $table->text('failed_reason')->nullable();
        $table->unsignedInteger('sender_user_id')->nullable();
        $table->string('sender_kind', 20)->nullable();
        $table->unsignedInteger('cost_centavos')->nullable();
        $table->boolean('is_internal_note')->default(false);
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
        $table->timestamp('updated_at')->useCurrent();
        $table->unsignedInteger('created_by_user_id')->nullable();
        $table->unique(['conversation_id', 'tag_id'], 'wa_conv_tags_uniq');
    });

    // Espelha migration 2026_05_13_000001_create_macros_table.php
    Schema::create('macros', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedInteger('business_id');
        $table->string('label', 80);
        $table->string('shortcut', 30)->nullable();
        $table->text('body');
        $table->json('actions_json')->nullable();
        $table->unsignedBigInteger('created_by_user_id')->nullable();
        $table->unsignedInteger('used_count')->default(0);
        $table->timestamps();
        $table->index(['business_id'], 'macros_business_idx');
        $table->unique(['business_id', 'shortcut'], 'macros_business_shortcut_uniq');
    });
});

it('R-WA-048-001 — CRUD basic (store, update, destroy)', function () {
    session()->put('user.business_id', 1);
    session()->put('user.id', 42);

    $controller = app(MacrosController::class);

    // STORE
    $req = Request::create('/test', 'POST', [
        'label' => 'Pedir CNPJ',
        'shortcut' => 'cnpj',
        'body' => 'Por favor envie seu CNPJ pra emitir NF.',
        'actions_json' => [],
    ]);
    $controller->store($req);

    $macro = Macro::query()->where('business_id', 1)->first();
    expect($macro)->not->toBeNull();
    expect($macro->label)->toBe('Pedir CNPJ');
    expect($macro->shortcut)->toBe('cnpj');
    expect($macro->created_by_user_id)->toBe(42);

    // UPDATE
    $reqUpd = Request::create('/test', 'PUT', [
        'label' => 'Pedir CNPJ atualizado',
        'shortcut' => 'cnpj',
        'body' => 'Texto novo.',
        'actions_json' => [],
    ]);
    $controller->update($reqUpd, $macro->id);
    $macro->refresh();
    expect($macro->label)->toBe('Pedir CNPJ atualizado');
    expect($macro->body)->toBe('Texto novo.');

    // DESTROY
    $controller->destroy(Request::create('/test', 'DELETE'), $macro->id);
    expect(Macro::query()->where('business_id', 1)->count())->toBe(0);
});

it('R-WA-048-002 — shortcut UNIQUE per-business + normalização (lowercase, sem barra leading)', function () {
    session()->put('user.business_id', 1);

    $controller = app(MacrosController::class);

    // Cria com shortcut "/CNPJ " — deve normalizar pra "cnpj"
    $controller->store(Request::create('/test', 'POST', [
        'label' => 'A',
        'shortcut' => '/CNPJ ',
        'body' => 'a',
        'actions_json' => [],
    ]));
    $first = Macro::query()->withoutGlobalScope(ScopeByBusiness::class)
        ->where('business_id', 1)->first();
    expect($first->shortcut)->toBe('cnpj');

    // 2ª macro com mesmo shortcut (formato diferente) → 422
    try {
        $controller->store(Request::create('/test', 'POST', [
            'label' => 'B',
            'shortcut' => 'CNPJ',
            'body' => 'b',
            'actions_json' => [],
        ]));
        $this->fail('Esperava 422 mas store passou');
    } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
        expect($e->getStatusCode())->toBe(422);
    }

    // Mesmo shortcut em business=2 OK (UNIQUE é per-business)
    session()->put('user.business_id', 2);
    $controller->store(Request::create('/test', 'POST', [
        'label' => 'Outro biz',
        'shortcut' => 'cnpj',
        'body' => 'x',
        'actions_json' => [],
    ]));
    $biz2Count = Macro::query()->withoutGlobalScope(ScopeByBusiness::class)
        ->where('business_id', 2)->count();
    expect($biz2Count)->toBe(1);
});

it('R-WA-048-003 — apply incrementa used_count + cria msg outbound + aplica actions (tag + status)', function () {
    session()->put('user.business_id', 1);
    session()->put('user.id', 42);

    $channel = Channel::query()->create([
        'business_id' => 1,
        'channel_uuid' => '55555555-0000-0000-0000-000000000001',
        'label' => 'X',
        'type' => Channel::TYPE_WHATSAPP_BAILEYS,
        'status' => 'active',
    ]);
    $conv = Conversation::query()->create([
        'business_id' => 1, 'channel_id' => $channel->id,
        'customer_external_id' => '+554899998888', 'status' => 'open',
    ]);
    $tag = Tag::query()->create([
        'business_id' => 1, 'slug' => 'vendas', 'label' => 'Vendas', 'color' => 'emerald',
    ]);

    $macro = Macro::query()->create([
        'business_id' => 1,
        'label' => 'Saudação inicial',
        'shortcut' => 'oi',
        'body' => 'Olá! Em que posso ajudar?',
        'actions_json' => [
            ['type' => 'add_tag', 'tag_id' => $tag->id],
            ['type' => 'set_status', 'status' => 'awaiting_human'],
        ],
        'used_count' => 0,
    ]);

    // Fake daemon Baileys success response
    Http::fake([
        '*/instances/*/text' => Http::response(['status' => 'sent', 'message_id' => 'wamid.X1'], 200),
    ]);

    $executor = app(MacroExecutor::class);
    $result = $executor->execute(1, $macro->id, $conv->id, 42);

    expect($result['message_id'])->not->toBeNull();
    expect($result['send_failed'])->toBeFalse();
    expect($result['actions_applied'])->toHaveCount(2);

    // Msg outbound persistida
    $msg = Message::query()->where('business_id', 1)->first();
    expect($msg)->not->toBeNull();
    expect($msg->direction)->toBe('outbound');
    expect($msg->body)->toBe('Olá! Em que posso ajudar?');
    expect($msg->sender_user_id)->toBe(42);
    expect($msg->is_internal_note)->toBeFalse();

    // Conv atualizada — tag aplicada + status mudado
    $conv->refresh()->load('tags');
    expect($conv->tags)->toHaveCount(1);
    expect($conv->tags->first()->id)->toBe($tag->id);
    expect($conv->status)->toBe('awaiting_human');

    // used_count incrementado
    $macro->refresh();
    expect($macro->used_count)->toBe(1);
});

it('R-WA-048-004 — Tier 0 (ADR 0093): biz=1 não enxerga macro de biz=99', function () {
    // Cria macro em biz=99 SEM autenticar
    $alien = new Macro([
        'business_id' => 99,
        'label' => 'Alien',
        'shortcut' => 'alien',
        'body' => 'cross-tenant',
    ]);
    $alien->save();

    // Autenticado em biz=1 — listar deve retornar VAZIO
    session()->put('user.business_id', 1);
    $count = Macro::query()->count();
    expect($count)->toBe(0);

    // findOrFail no Controller deve ModelNotFound (apply em macro cross-tenant)
    session()->put('user.id', 1);
    $controller = app(MacrosController::class);
    $executor = app(MacroExecutor::class);

    expect(fn () => $executor->execute(1, $alien->id, 1, 1))
        ->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
});

it('R-WA-048-005 — macro deletada não afeta msgs históricas (sem FK delete cascade)', function () {
    session()->put('user.business_id', 1);
    session()->put('user.id', 42);

    $channel = Channel::query()->create([
        'business_id' => 1,
        'channel_uuid' => '66666666-0000-0000-0000-000000000001',
        'label' => 'X',
        'type' => Channel::TYPE_WHATSAPP_BAILEYS,
        'status' => 'active',
    ]);
    $conv = Conversation::query()->create([
        'business_id' => 1, 'channel_id' => $channel->id,
        'customer_external_id' => '+554811112222', 'status' => 'open',
    ]);
    $macro = Macro::query()->create([
        'business_id' => 1,
        'label' => 'Teste delete',
        'shortcut' => 'del',
        'body' => 'Texto histórico.',
        'actions_json' => [],
    ]);

    Http::fake([
        '*/instances/*/text' => Http::response(['status' => 'sent', 'message_id' => 'wamid.X2'], 200),
    ]);

    $executor = app(MacroExecutor::class);
    $executor->execute(1, $macro->id, $conv->id, 42);

    $msgsBefore = Message::query()->where('business_id', 1)->count();
    expect($msgsBefore)->toBe(1);

    // Delete macro
    $macro->delete();
    expect(Macro::query()->where('id', $macro->id)->count())->toBe(0);

    // Msgs históricas seguem intactas
    $msgsAfter = Message::query()->where('business_id', 1)->count();
    expect($msgsAfter)->toBe(1);
    $msg = Message::query()->where('business_id', 1)->first();
    expect($msg->body)->toBe('Texto histórico.');
});
