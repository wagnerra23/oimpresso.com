<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\WhatsappMessage;

uses(Tests\TestCase::class);

/**
 * R-WA-Observer · WhatsappMessageObserver append-only enforcement.
 *
 * Cobre:
 * - INSERT permitido (não dispara saving check)
 * - UPDATE em status/failed_reason permitido (delivery flow)
 * - UPDATE em IMMUTABLE_COLUMNS bloqueado (body, conversation_id, etc.)
 * - Exceção one-time set: provider_message_id null|'' → valor real OK
 * - Re-update provider_message_id após setado = bloqueado
 * - DELETE bloqueado
 */

beforeEach(function () {
    // era-sqlite: este teste cria schema manual (sqlite-friendly). No MySQL persistente
    // do nightly isso DROPA tabelas reais → corrompe os testes irmãos (lever do floor SDD).
    // Cobertura real é na lane sqlite (per-PR); pula no MySQL.
    if (config('database.default') !== 'sqlite') {
        $this->markTestSkipped('era-sqlite: corruptor de schema compartilhado no MySQL — sqlite-only no burn-down do floor SDD.');
    }
    Schema::dropIfExists('whatsapp_messages');
    Schema::create('whatsapp_messages', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedInteger('business_id');
        $table->unsignedBigInteger('conversation_id');
        $table->string('direction', 10)->nullable();
        $table->string('provider', 20)->nullable();
        $table->string('provider_message_id', 128)->nullable();
        $table->string('type', 20)->nullable();
        $table->string('template_name', 64)->nullable();
        $table->text('body')->nullable();
        $table->json('payload')->nullable();
        $table->string('status', 20)->nullable();
        $table->text('failed_reason')->nullable();
        $table->unsignedInteger('sender_user_id')->nullable();
        $table->string('sender_kind', 20)->nullable();
        $table->integer('cost_centavos')->nullable();
        $table->timestamps();
    });
});

function makeMessage(array $overrides = []): WhatsappMessage
{
    return WhatsappMessage::query()
        ->withoutGlobalScope(ScopeByBusiness::class)
        ->create(array_merge([
            'business_id' => 1,
            'conversation_id' => 1,
            'direction' => 'outbound',
            'provider' => 'zapi',
            'type' => 'text',
            'body' => 'hello',
            'status' => 'queued',
            'sender_kind' => 'system',
        ], $overrides));
}

it('permite UPDATE em status (delivery flow)', function () {
    $msg = makeMessage(['status' => 'queued']);

    $msg->update(['status' => 'sent']);

    expect($msg->fresh()->status)->toBe('sent');
});

it('bloqueia UPDATE em body (IMMUTABLE_COLUMNS)', function () {
    $msg = makeMessage(['body' => 'original']);

    expect(fn () => $msg->update(['body' => 'modificado']))
        ->toThrow(\DomainException::class, 'append-only violation');
});

it('bloqueia UPDATE em direction (IMMUTABLE_COLUMNS)', function () {
    $msg = makeMessage(['direction' => 'outbound']);

    expect(fn () => $msg->update(['direction' => 'inbound']))
        ->toThrow(\DomainException::class, 'append-only violation');
});

it('permite one-time set provider_message_id null → valor real', function () {
    $msg = makeMessage(['provider_message_id' => null]);

    $msg->update(['provider_message_id' => 'wamid.REAL123']);

    expect($msg->fresh()->provider_message_id)->toBe('wamid.REAL123');
});

it('permite one-time set provider_message_id "" → valor real', function () {
    $msg = makeMessage(['provider_message_id' => '']);

    $msg->update(['provider_message_id' => '3EB0XXX_REAL']);

    expect($msg->fresh()->provider_message_id)->toBe('3EB0XXX_REAL');
});

it('bloqueia re-UPDATE provider_message_id após one-time set (preserva append-only)', function () {
    $msg = makeMessage(['provider_message_id' => 'wamid.FIRST']);

    expect(fn () => $msg->update(['provider_message_id' => 'wamid.SECOND']))
        ->toThrow(\DomainException::class, 'append-only violation');
});

it('bloqueia DELETE direto', function () {
    $msg = makeMessage();

    expect(fn () => $msg->delete())
        ->toThrow(\DomainException::class, 'hard-delete bloqueado');
});
