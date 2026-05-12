<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\LidPhoneMap;
use Modules\Whatsapp\Services\Contacts\LidPhoneResolver;

uses(Tests\TestCase::class);

/**
 * R-WA-093 — GUARD tests pro LID Resolver Custom (US-WA-093).
 *
 * Workaround LID (Linked ID Multi-Device) pré-Baileys 7.x. WhatsApp manda
 * `remoteJid@lid` quando cliente fala via Click-to-Chat / Status / Ads;
 * ÀS VEZES `senderPn` vem com phone real ao lado — quando vem, gravamos
 * o par aqui. Próximas msgs do mesmo LID resolvem cache.
 *
 * Cobre:
 *  001. record com phone=null cria row com phone=null
 *  002. record com phone preenche row criada antes sem phone
 *  003. record duas vezes com phones diferentes — last write wins
 *  004. resolve retorna phone OU null sem ambiguidade
 *  005. Tier 0: record biz=99 NÃO vaza pra biz=1 (UNIQUE business+lid)
 *  006. isLid detecta @lid suffix + 14+ dígitos sem DDI BR
 */
beforeEach(function () {
    Schema::dropIfExists('whatsapp_lid_pn_map');

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
});

it('R-WA-093-001 — record(biz=1, lid, phone=null) cria row com phone=null', function () {
    $resolver = app(LidPhoneResolver::class);
    $row = $resolver->record(1, '5196915463394@lid', null);

    expect($row)->not->toBeNull();
    expect($row->business_id)->toBe(1);
    expect($row->phone_e164)->toBeNull();
    expect($row->lid)->toBe('5196915463394'); // normalize remove @lid

    // SUPERADMIN: assert row physically in DB (ADR 0093 — sem session user)
    $count = LidPhoneMap::query()
        ->withoutGlobalScope(ScopeByBusiness::class)
        ->where('business_id', 1)
        ->count();
    expect($count)->toBe(1);
});

it('R-WA-093-002 — record(biz=1, lid, phone) preenche phone quando descoberto depois', function () {
    $resolver = app(LidPhoneResolver::class);
    // Primeiro hit: só LID
    $resolver->record(1, '5196915463394@lid', null);
    // Segundo hit: WhatsApp finalmente enviou senderPn → preenche
    $row = $resolver->record(1, '5196915463394@lid', '+5548999872822@s.whatsapp.net');

    expect($row)->not->toBeNull();
    expect($row->phone_e164)->toBe('+5548999872822');

    // Continua 1 só row (UNIQUE constraint)
    $count = LidPhoneMap::query()
        ->withoutGlobalScope(ScopeByBusiness::class)
        ->where('business_id', 1)
        ->count();
    expect($count)->toBe(1);
});

it('R-WA-093-003 — record duas vezes com phones diferentes — last write wins', function () {
    $resolver = app(LidPhoneResolver::class);
    $resolver->record(1, '5196915463394@lid', '+5548888887777');
    $row = $resolver->record(1, '5196915463394@lid', '+5548999872822');

    expect($row->phone_e164)->toBe('+5548999872822');

    $count = LidPhoneMap::query()
        ->withoutGlobalScope(ScopeByBusiness::class)
        ->where('business_id', 1)
        ->count();
    expect($count)->toBe(1); // UPDATE in place, not new row
});

it('R-WA-093-004 — resolve retorna phone correto OU null sem ambiguidade', function () {
    $resolver = app(LidPhoneResolver::class);
    $resolver->record(1, '5196915463394@lid', '+5548999872822');

    // Resolve com formato @lid funciona
    expect($resolver->resolve(1, '5196915463394@lid'))->toBe('+5548999872822');
    // Resolve com formato normalizado também funciona (mesmo normalize)
    expect($resolver->resolve(1, '+5196915463394'))->toBe('+5548999872822');
    // LID nunca visto retorna null
    expect($resolver->resolve(1, '9999999999999@lid'))->toBeNull();
    // Resolve em LID conhecido mas com phone=null retorna null
    $resolver->record(1, 'aaaa999999999@lid', null);
    expect($resolver->resolve(1, 'aaaa999999999@lid'))->toBeNull();
});

it('R-WA-093-005 — Tier 0: record(biz=99) NAO vaza pra biz=1 (cross-tenant defense)', function () {
    $resolver = app(LidPhoneResolver::class);
    $resolver->record(1, '5196915463394@lid', '+5548111111111');
    $resolver->record(99, '5196915463394@lid', '+5548999999999');

    // Mesmo LID em business distintos = 2 rows independentes
    $countTotal = LidPhoneMap::query()
        ->withoutGlobalScope(ScopeByBusiness::class)
        ->count();
    expect($countTotal)->toBe(2);

    // biz=1 vê SEU phone, não o do biz=99
    expect($resolver->resolve(1, '5196915463394@lid'))->toBe('+5548111111111');
    expect($resolver->resolve(99, '5196915463394@lid'))->toBe('+5548999999999');
});

it('R-WA-093-006 — isLid detecta @lid suffix + 14+ digitos sem DDI BR', function () {
    $resolver = app(LidPhoneResolver::class);

    // Casos LID (true)
    expect($resolver->isLid('5196915463394@lid'))->toBeTrue();
    expect($resolver->isLid('+519691546333945'))->toBeTrue(); // 15 dígitos sem DDI BR — caso real prod
    expect($resolver->isLid('98765432109876@lid'))->toBeTrue();

    // Casos phone normal BR (false)
    expect($resolver->isLid('+5548999872822'))->toBeFalse(); // 13 dígitos com DDI BR
    expect($resolver->isLid('+554899987282'))->toBeFalse();  // 12 dígitos com DDI BR
    expect($resolver->isLid('5548999872822@s.whatsapp.net'))->toBeFalse(); // formato JID phone real

    // Edge: string vazia
    expect($resolver->isLid(''))->toBeFalse();
});
