<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Entities\Conversation;
use Modules\Whatsapp\Entities\Message;
use Modules\Whatsapp\Entities\WhatsappReminder;
use Modules\Whatsapp\Jobs\ProcessRemindersJob;
use Modules\Whatsapp\Services\Centrifugo\CentrifugoPublisher;
use Modules\Whatsapp\Services\Notes\LembreteHandler;
use Modules\Whatsapp\Services\Notes\SlashCommandResult;

uses(Tests\TestCase::class);

/**
 * US-WA-076 (ADR 0142 §5) — Slash command `/lembrete` + ProcessRemindersJob.
 *
 * Cobre 9 dimensões:
 *
 *   1. Parser ISO `2026-05-20 cobrar boleto`
 *   2. Parser humano `amanhã pegar peça`
 *   3. Parser inválido `xyz blah` → error
 *   4. LembreteHandler — cria row em whatsapp_reminders
 *   5. ProcessRemindersJob — reminder due → publica Centrifugo + notified_at
 *   6. ProcessRemindersJob — reminder futuro → NÃO publica
 *   7. ProcessRemindersJob — reminder já notified → NÃO re-publica
 *   8. Multi-tenant Tier 0 — biz=99 não vê reminders biz=1
 *   9. Schema migration idempotente (Schema::hasTable guard)
 *
 * @see Modules\Whatsapp\Services\Notes\LembreteHandler
 * @see Modules\Whatsapp\Jobs\ProcessRemindersJob
 * @see memory/decisions/0142-notas-internas-sinal-treino-jana.md
 */
beforeEach(function () {
    foreach (['messages', 'conversations', 'channels', 'whatsapp_reminders'] as $t) {
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
        $table->timestamp('created_at')->useCurrent();
        $table->timestamp('updated_at')->nullable();
    });

    Schema::create('whatsapp_reminders', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedInteger('business_id');
        $table->unsignedBigInteger('conversation_id');
        $table->unsignedInteger('contact_id')->nullable();
        $table->unsignedInteger('atendente_user_id');
        $table->unsignedInteger('created_by_user_id');
        $table->timestamp('due_at');
        $table->text('body');
        $table->string('status', 20)->default('pending');
        $table->timestamp('notified_at')->nullable();
        $table->timestamp('completed_at')->nullable();
        $table->timestamp('created_at')->nullable();
        $table->timestamp('updated_at')->nullable();
        $table->index(['status', 'due_at'], 'wr_due_pending_idx');
        $table->index(['atendente_user_id', 'status'], 'wr_user_status_idx');
        $table->index('business_id', 'wr_biz_idx');
    });
});

function makeConvLembrete(int $businessId, string $uuid, ?int $contactId = null): array
{
    $channel = Channel::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => $businessId,
        'channel_uuid' => $uuid,
        'label' => 'Suporte',
        'type' => Channel::TYPE_WHATSAPP_BAILEYS,
        'status' => 'active',
    ]);

    $conv = Conversation::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => $businessId,
        'channel_id' => $channel->id,
        'contact_id' => $contactId,
        'customer_external_id' => '+5511999999999',
        'contact_name' => 'Cliente Teste',
        'status' => 'open',
    ]);

    return [$channel, $conv];
}

function makeNoteLembrete(int $businessId, int $convId, string $body, ?int $senderUserId = 7): Message
{
    $note = Message::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => $businessId,
        'conversation_id' => $convId,
        'direction' => 'outbound',
        'provider' => 'whatsapp_baileys',
        'type' => 'text',
        'body' => $body,
        'status' => 'sent',
        'sender_user_id' => $senderUserId,
        'sender_kind' => 'human',
        'is_internal_note' => true,
    ]);
    return $note;
}

// ─── 1. Parser ISO ──────────────────────────────────────────────────────────

it('LembreteHandler — parser ISO `2026-05-20 cobrar boleto` cria reminder com due_at correto', function () {
    [, $conv] = makeConvLembrete(1, 'aaaa-0000-0000-0000-iso', 42);
    $note = makeNoteLembrete(1, $conv->id, '/lembrete 2026-05-20 cobrar boleto');
    $note->setRelation('conversation', $conv);

    $handler = new LembreteHandler();
    $result = $handler->handle($note, '2026-05-20 cobrar boleto');

    expect($result->kind)->toBe(SlashCommandResult::KIND_SUCCESS);
    expect($result->badge)->toBe('⏰ lembrete agendado');
    expect($result->linkUrl)->toMatch('#^/atendimento/lembretes\?reminder_id=\d+$#');

    $reminder = WhatsappReminder::withoutGlobalScopes()->first();
    expect($reminder)->not->toBeNull();
    expect($reminder->business_id)->toBe(1);
    expect($reminder->conversation_id)->toBe($conv->id);
    expect($reminder->contact_id)->toBe(42);
    expect($reminder->atendente_user_id)->toBe(7);
    expect($reminder->created_by_user_id)->toBe(7);
    expect($reminder->body)->toBe('cobrar boleto');
    expect($reminder->status)->toBe('pending');
    expect($reminder->due_at->format('Y-m-d'))->toBe('2026-05-20');
    expect($reminder->due_at->format('H:i'))->toBe('09:00');
});

// ─── 2. Parser humano ───────────────────────────────────────────────────────

it('LembreteHandler — parser humano `amanhã pegar peça` → due_at = +1 day', function () {
    Carbon::setTestNow(Carbon::parse('2026-05-12 14:00:00'));

    [, $conv] = makeConvLembrete(1, 'aaaa-0000-0000-0000-hum', 99);
    $note = makeNoteLembrete(1, $conv->id, '/lembrete amanhã pegar peça');
    $note->setRelation('conversation', $conv);

    $handler = new LembreteHandler();
    $result = $handler->handle($note, 'amanhã pegar peça');

    expect($result->isSuccess())->toBeTrue();

    $reminder = WhatsappReminder::withoutGlobalScopes()->first();
    expect($reminder->due_at->format('Y-m-d'))->toBe('2026-05-13');
    expect($reminder->body)->toBe('pegar peça');

    Carbon::setTestNow();
});

it('LembreteHandler — parser humano `daqui 3 dias retornar` → +3 days', function () {
    Carbon::setTestNow(Carbon::parse('2026-05-12 14:00:00'));

    [, $conv] = makeConvLembrete(1, 'aaaa-0000-0000-0000-daq', 99);
    $note = makeNoteLembrete(1, $conv->id, '/lembrete daqui 3 dias retornar');
    $note->setRelation('conversation', $conv);

    $result = (new LembreteHandler())->handle($note, 'daqui 3 dias retornar');
    expect($result->isSuccess())->toBeTrue();

    $reminder = WhatsappReminder::withoutGlobalScopes()->first();
    expect($reminder->due_at->format('Y-m-d'))->toBe('2026-05-15');
    expect($reminder->body)->toBe('retornar');

    Carbon::setTestNow();
});

// ─── 3. Parser inválido ─────────────────────────────────────────────────────

it('LembreteHandler — parser inválido `xyz blah` → SlashCommandResult::error', function () {
    [, $conv] = makeConvLembrete(1, 'aaaa-0000-0000-0000-inv');
    $note = makeNoteLembrete(1, $conv->id, '/lembrete xyz blah');
    $note->setRelation('conversation', $conv);

    $handler = new LembreteHandler();
    $result = $handler->handle($note, 'xyz blah');

    expect($result->isError())->toBeTrue();
    expect($result->errorMessage)->toContain('Data inválida');
    expect(WhatsappReminder::withoutGlobalScopes()->count())->toBe(0);
});

it('LembreteHandler — gate Tier 0: is_internal_note=false → error', function () {
    [, $conv] = makeConvLembrete(1, 'aaaa-0000-0000-0000-gate');
    $note = Message::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'conversation_id' => $conv->id,
        'direction' => 'outbound',
        'provider' => 'whatsapp_baileys',
        'type' => 'text',
        'body' => 'qualquer coisa',
        'status' => 'sent',
        'sender_kind' => 'human',
        'is_internal_note' => false,
    ]);
    $note->setRelation('conversation', $conv);

    $result = (new LembreteHandler())->handle($note, '2026-05-20 algo');
    expect($result->isError())->toBeTrue();
    expect(WhatsappReminder::withoutGlobalScopes()->count())->toBe(0);
});

// ─── 4. ProcessRemindersJob — reminder due ─────────────────────────────────

it('ProcessRemindersJob — reminder due publica Centrifugo + notified_at preenchido', function () {
    Carbon::setTestNow(Carbon::parse('2026-05-20 10:00:00'));

    [, $conv] = makeConvLembrete(1, 'aaaa-0000-0000-0000-due', 99);
    $reminder = WhatsappReminder::create([
        'business_id' => 1,
        'conversation_id' => $conv->id,
        'contact_id' => 99,
        'atendente_user_id' => 7,
        'created_by_user_id' => 7,
        'due_at' => Carbon::parse('2026-05-20 09:00:00'),
        'body' => 'cobrar boleto',
        'status' => 'pending',
    ]);

    // Mock Centrifugo
    $publisher = Mockery::mock(CentrifugoPublisher::class);
    $publisher->shouldReceive('publish')
        ->once()
        ->with('user:7', Mockery::on(function ($payload) use ($reminder) {
            return ($payload['type'] ?? null) === 'reminder'
                && ($payload['reminder_id'] ?? null) === $reminder->id
                && ($payload['body'] ?? null) === 'cobrar boleto'
                && ($payload['conversation_id'] ?? null) === $reminder->conversation_id;
        }))
        ->andReturn(true);
    app()->instance(CentrifugoPublisher::class, $publisher);

    $job = new ProcessRemindersJob();
    $job->handle($publisher);

    $reminder->refresh();
    expect($reminder->notified_at)->not->toBeNull();
    expect($reminder->status)->toBe('notified');

    Carbon::setTestNow();
});

// ─── 5. ProcessRemindersJob — reminder futuro NÃO publica ──────────────────

it('ProcessRemindersJob — reminder futuro NÃO publica Centrifugo', function () {
    Carbon::setTestNow(Carbon::parse('2026-05-20 10:00:00'));

    [, $conv] = makeConvLembrete(1, 'aaaa-0000-0000-0000-fut');
    $reminder = WhatsappReminder::create([
        'business_id' => 1,
        'conversation_id' => $conv->id,
        'contact_id' => null,
        'atendente_user_id' => 7,
        'created_by_user_id' => 7,
        'due_at' => Carbon::parse('2026-05-25 09:00:00'), // futuro
        'body' => 'algo',
        'status' => 'pending',
    ]);

    $publisher = Mockery::mock(CentrifugoPublisher::class);
    $publisher->shouldNotReceive('publish');
    app()->instance(CentrifugoPublisher::class, $publisher);

    (new ProcessRemindersJob())->handle($publisher);

    $reminder->refresh();
    expect($reminder->notified_at)->toBeNull();
    expect($reminder->status)->toBe('pending');

    Carbon::setTestNow();
});

// ─── 6. ProcessRemindersJob — já notified NÃO re-publica ───────────────────

it('ProcessRemindersJob — reminder já notified NÃO re-publica', function () {
    Carbon::setTestNow(Carbon::parse('2026-05-20 10:00:00'));

    [, $conv] = makeConvLembrete(1, 'aaaa-0000-0000-0000-not');
    $reminder = WhatsappReminder::create([
        'business_id' => 1,
        'conversation_id' => $conv->id,
        'contact_id' => null,
        'atendente_user_id' => 7,
        'created_by_user_id' => 7,
        'due_at' => Carbon::parse('2026-05-20 09:00:00'),
        'body' => 'algo',
        'status' => 'notified', // já notificado
        'notified_at' => Carbon::parse('2026-05-20 09:30:00'),
    ]);

    $publisher = Mockery::mock(CentrifugoPublisher::class);
    $publisher->shouldNotReceive('publish');
    app()->instance(CentrifugoPublisher::class, $publisher);

    (new ProcessRemindersJob())->handle($publisher);

    // Estado preservado — sem re-publish
    $reminder->refresh();
    expect($reminder->status)->toBe('notified');

    Carbon::setTestNow();
});

// ─── 7. Multi-tenant Tier 0 ─────────────────────────────────────────────────

it('Multi-tenant Tier 0 — biz=99 NÃO vê reminders criados em biz=1 via global scope', function () {
    [, $conv1] = makeConvLembrete(1, 'aaaa-0000-0000-0000-biz1', 50);
    WhatsappReminder::withoutGlobalScopes()->create([
        'business_id' => 1,
        'conversation_id' => $conv1->id,
        'contact_id' => 50,
        'atendente_user_id' => 1,
        'created_by_user_id' => 1,
        'due_at' => now()->addDay(),
        'body' => 'segredo biz=1',
        'status' => 'pending',
    ]);

    [, $conv99] = makeConvLembrete(99, 'aaaa-0000-0000-0000-biz99', 50);
    WhatsappReminder::withoutGlobalScopes()->create([
        'business_id' => 99,
        'conversation_id' => $conv99->id,
        'contact_id' => 50,
        'atendente_user_id' => 1,
        'created_by_user_id' => 1,
        'due_at' => now()->addDay(),
        'body' => 'reminder biz=99',
        'status' => 'pending',
    ]);

    expect(WhatsappReminder::withoutGlobalScopes()->count())->toBe(2);

    // Visão biz=99 (global scope ativo) vê só o dele
    session(['user.business_id' => 99]);
    $visible = WhatsappReminder::all();
    expect($visible)->toHaveCount(1);
    expect($visible->first()->body)->toBe('reminder biz=99');
    expect($visible->pluck('body')->toArray())->not->toContain('segredo biz=1');
});

// ─── 8. Schema migration idempotente ────────────────────────────────────────

it('Schema migration — idempotente (Schema::hasTable guard previne re-run)', function () {
    // Tabela já criada em beforeEach. Roda o close de migration de novo:
    $migration = require __DIR__ . '/../../Database/Migrations/2026_05_12_180000_create_whatsapp_reminders_table.php';

    expect(Schema::hasTable('whatsapp_reminders'))->toBeTrue();

    // up() de novo: o guard `Schema::hasTable` retorna sem erro
    expect(fn () => $migration->up())->not->toThrow(\Exception::class);
    expect(Schema::hasTable('whatsapp_reminders'))->toBeTrue();
});
