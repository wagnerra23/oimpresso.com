<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\LidPhoneMap;
use Modules\Whatsapp\Entities\Message;

uses(Tests\TestCase::class);

/**
 * R-WA-093-BACKFILL — GUARD tests pro LidBackfillCommand (US-WA-093 P1 #697).
 *
 * Varre `messages.payload` histórico procurando pares `(remoteJid@lid, senderPn)`
 * pra popular `whatsapp_lid_pn_map` retroativamente. Idempotente.
 *
 * Cobre:
 *  101. Backfill básico: 3 msgs (1 par válido, 1 sem senderPn, 1 sem LID) → 1 record
 *  102. --dry-run NÃO persiste em whatsapp_lid_pn_map
 *  103. Tier 0 cross-tenant — --business=99 não toca biz=1
 *  104. Idempotência — rodar 2× não duplica row (UNIQUE business+lid)
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        test()->markTestSkipped('era-sqlite: schema sintético manual incompatível com MySQL persistente — quarentena Onda 2 SDD floor; burn-down converte depois.');
    }

    foreach (['messages', 'whatsapp_lid_pn_map'] as $t) {
        Schema::dropIfExists($t);
    }

    Schema::create('whatsapp_lid_pn_map', function ($table) {
        $table->id();
        $table->unsignedInteger('business_id');
        $table->string('lid', 100);
        $table->string('phone_e164', 32)->nullable();
        $table->string('source', 30)->default('webhook_senderPn');
        $table->timestamp('first_seen_at')->useCurrent();
        $table->timestamp('last_seen_at')->useCurrent();
        $table->timestamps();
        $table->unique(['business_id', 'lid'], 'wa_lid_pn_business_lid_uniq');
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
        $table->boolean('is_internal_note')->default(false);
        $table->string('media_download_status', 30)->default('pending');
        $table->unsignedInteger('media_download_attempts')->default(0);
        $table->timestamps();
    });
});

/**
 * Helper: cria msg com payload arbitrário (sem passar por Persister).
 */
function makeMsgWithPayload(int $bizId, string $providerId, array $payload): Message
{
    return Message::query()
        ->withoutGlobalScope(ScopeByBusiness::class)
        ->create([
            'business_id' => $bizId,
            'conversation_id' => 1,
            'direction' => 'inbound',
            'provider' => 'whatsapp_baileys',
            'provider_message_id' => $providerId,
            'type' => 'text',
            'body' => 'msg test',
            'payload' => $payload,
            'status' => 'received',
        ]);
}

it('R-WA-093-101 — backfill recorda só par válido (LID + senderPn)', function () {
    // Msg 1: par válido (LID + senderPn)
    makeMsgWithPayload(1, 'MSG_VALID', [
        'key' => [
            'remoteJid' => '5196915463394@lid',
            'senderPn' => '5548999872822@s.whatsapp.net',
            'id' => 'MSG_VALID',
            'fromMe' => false,
        ],
        'message' => ['conversation' => 'oi'],
    ]);

    // Msg 2: LID mas SEM senderPn → não recordar
    makeMsgWithPayload(1, 'MSG_NO_PN', [
        'key' => [
            'remoteJid' => '7777777777777@lid',
            'id' => 'MSG_NO_PN',
            'fromMe' => false,
        ],
    ]);

    // Msg 3: sem LID (jid normal) → não recordar
    makeMsgWithPayload(1, 'MSG_NORMAL', [
        'key' => [
            'remoteJid' => '5548999999999@s.whatsapp.net',
            'id' => 'MSG_NORMAL',
            'fromMe' => false,
        ],
    ]);

    $exit = \Artisan::call('whatsapp:lid-backfill', ['--business' => 1]);
    expect($exit)->toBe(0);

    $rows = LidPhoneMap::query()
        ->withoutGlobalScope(ScopeByBusiness::class)
        ->where('business_id', 1)
        ->get();

    expect($rows)->toHaveCount(1);
    expect($rows->first()->lid)->toBe('5196915463394');
    expect($rows->first()->phone_e164)->toBe('+5548999872822');
});

it('R-WA-093-102 — --dry-run não persiste em whatsapp_lid_pn_map', function () {
    makeMsgWithPayload(1, 'MSG_DRY', [
        'key' => [
            'remoteJid' => '5196915463394@lid',
            'senderPn' => '5548999872822@s.whatsapp.net',
            'id' => 'MSG_DRY',
        ],
    ]);

    $exit = \Artisan::call('whatsapp:lid-backfill', [
        '--business' => 1,
        '--dry-run' => true,
    ]);
    expect($exit)->toBe(0);

    $count = LidPhoneMap::query()
        ->withoutGlobalScope(ScopeByBusiness::class)
        ->count();
    expect($count)->toBe(0);
});

it('R-WA-093-103 — Tier 0 cross-tenant: --business=99 NAO toca biz=1', function () {
    makeMsgWithPayload(1, 'BIZ1_MSG', [
        'key' => [
            'remoteJid' => '1111111111111@lid',
            'senderPn' => '5548111111111@s.whatsapp.net',
            'id' => 'BIZ1_MSG',
        ],
    ]);
    makeMsgWithPayload(99, 'BIZ99_MSG', [
        'key' => [
            'remoteJid' => '9999999999999@lid',
            'senderPn' => '5548999999999@s.whatsapp.net',
            'id' => 'BIZ99_MSG',
        ],
    ]);

    $exit = \Artisan::call('whatsapp:lid-backfill', ['--business' => 99]);
    expect($exit)->toBe(0);

    $allRows = LidPhoneMap::query()
        ->withoutGlobalScope(ScopeByBusiness::class)
        ->get();

    expect($allRows)->toHaveCount(1);
    expect($allRows->first()->business_id)->toBe(99);
    expect($allRows->first()->phone_e164)->toBe('+5548999999999');

    // biz=1 NÃO foi tocado
    $biz1Count = LidPhoneMap::query()
        ->withoutGlobalScope(ScopeByBusiness::class)
        ->where('business_id', 1)
        ->count();
    expect($biz1Count)->toBe(0);
});

it('R-WA-093-104 — idempotência: rodar 2x nao duplica row', function () {
    makeMsgWithPayload(1, 'MSG_IDEM', [
        'key' => [
            'remoteJid' => '5196915463394@lid',
            'senderPn' => '5548999872822@s.whatsapp.net',
            'id' => 'MSG_IDEM',
        ],
    ]);

    \Artisan::call('whatsapp:lid-backfill', ['--business' => 1]);
    \Artisan::call('whatsapp:lid-backfill', ['--business' => 1]);

    $count = LidPhoneMap::query()
        ->withoutGlobalScope(ScopeByBusiness::class)
        ->count();
    expect($count)->toBe(1);
});
