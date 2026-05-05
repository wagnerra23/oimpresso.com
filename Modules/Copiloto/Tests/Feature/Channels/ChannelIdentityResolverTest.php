<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Copiloto\Services\Channels\ChannelIdentityResolver;
use Modules\Copiloto\Support\Channels\IncomingMessage;

uses(Tests\TestCase::class)->in(__DIR__);

/**
 * ADRs 0074 + 0075 — guardrail multi-tenant do channel adapter.
 *
 * Foco: garantir que `resolve()` NUNCA cruza wire_id de um business em
 * outro, e que estados (opt-in / revoked) se comportam como esperado.
 */

beforeEach(function () {
    if (! Schema::hasTable('copiloto_channel_identity')) {
        Schema::create('copiloto_channel_identity', function ($t) {
            $t->bigIncrements('id');
            $t->string('channel', 30);
            $t->string('wire_id', 60);
            $t->unsignedInteger('business_id');
            $t->unsignedBigInteger('user_id');
            $t->timestamp('opted_in_at')->nullable();
            $t->timestamp('revoked_at')->nullable();
            $t->timestamp('first_seen_at')->useCurrent();
            $t->timestamp('last_seen_at')->useCurrent();
            $t->timestamps();
            $t->unique(['channel', 'wire_id']);
        });
    }

    DB::table('copiloto_channel_identity')->truncate();
});

it('retorna null quando wire_id é desconhecido', function () {
    $resolver = new ChannelIdentityResolver();

    $msg = new IncomingMessage(
        channel: 'evolution',
        providerMessageId: 'M1',
        wireId: '+5511999999999',
        text: 'oi',
    );

    expect($resolver->resolve($msg))->toBeNull();
});

it('resolve identity existente com opt-in preenchido', function () {
    DB::table('copiloto_channel_identity')->insert([
        'channel'       => 'evolution',
        'wire_id'       => '+5511999999999',
        'business_id'   => 4,
        'user_id'       => 42,
        'opted_in_at'   => now(),
        'first_seen_at' => now(),
        'last_seen_at'  => now(),
        'created_at'    => now(),
        'updated_at'    => now(),
    ]);

    $resolver = new ChannelIdentityResolver();

    $msg = new IncomingMessage(
        channel: 'evolution',
        providerMessageId: 'M2',
        wireId: '+5511999999999',
        text: 'oi',
    );

    $r = $resolver->resolve($msg);

    expect($r)->not->toBeNull()
        ->and($r['business_id'])->toBe(4)
        ->and($r['user_id'])->toBe(42)
        ->and($r['opted_in'])->toBeTrue();
});

it('NUNCA retorna identity de outro business pelo mesmo wire_id', function () {
    // Edge case multi-tenant: dois businesses, mesmo wire_id em canais diferentes.
    DB::table('copiloto_channel_identity')->insert([
        ['channel' => 'evolution', 'wire_id' => '+5511A', 'business_id' => 4, 'user_id' => 42, 'opted_in_at' => now(), 'first_seen_at' => now(), 'last_seen_at' => now(), 'created_at' => now(), 'updated_at' => now()],
        ['channel' => 'evolution', 'wire_id' => '+5511B', 'business_id' => 7, 'user_id' => 99, 'opted_in_at' => now(), 'first_seen_at' => now(), 'last_seen_at' => now(), 'created_at' => now(), 'updated_at' => now()],
    ]);

    $resolver = new ChannelIdentityResolver();

    $r = $resolver->resolve(new IncomingMessage(
        channel: 'evolution',
        providerMessageId: 'M3',
        wireId: '+5511A',
        text: 'oi',
    ));

    expect($r['business_id'])->toBe(4)
        ->and($r['business_id'])->not->toBe(7);
});

it('retorna null quando identity está revoked', function () {
    DB::table('copiloto_channel_identity')->insert([
        'channel'       => 'evolution',
        'wire_id'       => '+5511999999999',
        'business_id'   => 4,
        'user_id'       => 42,
        'opted_in_at'   => now(),
        'revoked_at'    => now(),
        'first_seen_at' => now(),
        'last_seen_at'  => now(),
        'created_at'    => now(),
        'updated_at'    => now(),
    ]);

    $resolver = new ChannelIdentityResolver();

    $r = $resolver->resolve(new IncomingMessage(
        channel: 'evolution',
        providerMessageId: 'M4',
        wireId: '+5511999999999',
        text: 'oi',
    ));

    expect($r)->toBeNull();
});

it('markOptIn é idempotente', function () {
    DB::table('copiloto_channel_identity')->insert([
        'channel'       => 'evolution',
        'wire_id'       => '+5511999999999',
        'business_id'   => 4,
        'user_id'       => 42,
        'opted_in_at'   => null,
        'first_seen_at' => now(),
        'last_seen_at'  => now(),
        'created_at'    => now(),
        'updated_at'    => now(),
    ]);

    $resolver = new ChannelIdentityResolver();
    $resolver->markOptIn('evolution', '+5511999999999');

    $first = DB::table('copiloto_channel_identity')->first()->opted_in_at;
    expect($first)->not->toBeNull();

    // segunda chamada não deve mudar o timestamp
    sleep(1);
    $resolver->markOptIn('evolution', '+5511999999999');
    $second = DB::table('copiloto_channel_identity')->first()->opted_in_at;

    expect($second)->toBe($first);
});

it('revoke marca timestamp e impede resolve subsequente', function () {
    DB::table('copiloto_channel_identity')->insert([
        'channel'       => 'evolution',
        'wire_id'       => '+5511999999999',
        'business_id'   => 4,
        'user_id'       => 42,
        'opted_in_at'   => now(),
        'first_seen_at' => now(),
        'last_seen_at'  => now(),
        'created_at'    => now(),
        'updated_at'    => now(),
    ]);

    $resolver = new ChannelIdentityResolver();

    // antes: resolve OK
    expect($resolver->resolve(new IncomingMessage('evolution', 'M1', '+5511999999999', 'oi')))
        ->not->toBeNull();

    // revogou
    $resolver->revoke('evolution', '+5511999999999');

    // depois: silêncio total
    expect($resolver->resolve(new IncomingMessage('evolution', 'M2', '+5511999999999', 'oi')))
        ->toBeNull();
});
