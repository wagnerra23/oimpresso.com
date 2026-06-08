<?php

declare(strict_types=1);

use App\Contact;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Modules\Whatsapp\Services\Contacts\ConversationContactLinker;

uses(Tests\TestCase::class);

/**
 * Regression E2E pro fix cache stale descoberto no smoke 2026-05-15:
 *
 * Cenário Eliana-Wagner reproduzível:
 *  1. Eliana (Contact CRM) tem `alternate_number=48999872822` (= número Wagner errado)
 *  2. Wagner manda msg WhatsApp → daemon Baileys 7.x resolve phone real
 *  3. ConversationContactLinker.attemptLink() cacheia mapping LID→Eliana (1h TTL)
 *  4. Operador limpa Eliana.alternate_number=NULL via SQL/UI
 *  5. Wagner manda OUTRA msg → cache HIT na Eliana → cross-contact persistido por até 1h
 *
 * Fix: ContactObserver detecta mudança em phone fields e invalida cache via
 * `ConversationContactLinker::forgetAttemptLinkCache()`.
 *
 * @see Modules/Whatsapp/Observers/ContactObserver.php
 * @see Modules/Whatsapp/Services/Contacts/ConversationContactLinker.php::forgetAttemptLinkCache
 */
beforeEach(function () {
    Schema::dropIfExists('contacts');
    Schema::create('contacts', function ($table) {
        $table->increments('id');
        $table->unsignedInteger('business_id');
        $table->string('contact_id', 191)->nullable();
        $table->string('name', 191);
        $table->string('mobile', 191)->nullable();
        $table->string('landline', 191)->nullable();
        $table->string('alternate_number', 191)->nullable();
        $table->string('type', 191)->default('customer');
        $table->string('contact_status', 20)->default('active');
        $table->unsignedInteger('created_by')->nullable();
        $table->timestamp('deleted_at')->nullable();
        $table->timestamps();
    });
    Cache::flush();
});

// ============================================================================
// E2E 1 — Reprodução EXATA do cenário Eliana-Wagner cache stale
// ============================================================================

it('R-WA-CONTACT-CACHE-01 — UPDATE Contact phone field INVALIDA cache attemptLink (fix cross-contact 2026-05-15)', function () {
    $eliana = Contact::create([
        'business_id' => 1, 'name' => 'ELIANA',
        'mobile' => '48996483100',
        'alternate_number' => '48999872822', // Wagner phone errado salvo nela
        'type' => 'customer', 'contact_id' => 'CO_E',
    ]);

    // Simula populate cache (Linker.attemptLink já rodou pra phone Wagner)
    $phoneDigits = '554899872822';
    $cacheKey = "whatsapp.auto_link:1:{$phoneDigits}";
    Cache::put($cacheKey, $eliana->id, now()->addHour());
    expect(Cache::get($cacheKey))->toBe($eliana->id, 'baseline: cache aponta Eliana');

    // Wagner descobre erro, limpa alternate via UI
    $eliana->alternate_number = null;
    $eliana->save();

    // Cache deve estar invalidado (Observer rodou no save)
    expect(Cache::has($cacheKey))->toBeFalse(
        'REGRESSÃO: ContactObserver não invalidou cache → cross-contact recorrente Eliana-Wagner repete em prod'
    );
});

it('R-WA-CONTACT-CACHE-02 — invalida cache pro phone OLD e NEW quando mobile muda', function () {
    $c = Contact::create([
        'business_id' => 1, 'name' => 'X',
        'mobile' => '+5548999111111',
        'type' => 'customer', 'contact_id' => 'CO_X',
    ]);

    // Cache populado pra ambos old + new
    $oldKey = 'whatsapp.auto_link:1:5548999111111';
    $newKey = 'whatsapp.auto_link:1:5548999222222';
    Cache::put($oldKey, $c->id, now()->addHour());
    Cache::put($newKey, 9999, now()->addHour()); // outro contact

    // Atualiza mobile
    $c->mobile = '+5548999222222';
    $c->save();

    expect(Cache::has($oldKey))->toBeFalse('cache do phone OLD deve sumir');
    expect(Cache::has($newKey))->toBeFalse('cache do phone NEW também (re-evaluation segura)');
});

it('R-WA-CONTACT-CACHE-03 — phone fields intactos NÃO invalidam cache (só name mudou)', function () {
    $c = Contact::create([
        'business_id' => 1, 'name' => 'Antigo',
        'mobile' => '+5548999000000',
        'type' => 'customer', 'contact_id' => 'CO_N',
    ]);

    $cacheKey = 'whatsapp.auto_link:1:5548999000000';
    Cache::put($cacheKey, $c->id, now()->addHour());

    // Mudança só no nome — phone fields intactos
    $c->name = 'Novo Nome';
    $c->save();

    expect(Cache::has($cacheKey))->toBeTrue(
        'Cache não deveria ter sido invalidado — só name mudou (não phone field)'
    );
});

it('R-WA-CONTACT-CACHE-04 — Tier 0: cache de biz=1 NÃO sofre quando Contact biz=99 muda', function () {
    $c99 = Contact::create([
        'business_id' => 99, 'name' => 'Y',
        'mobile' => '+5548999111111',
        'type' => 'customer', 'contact_id' => 'CO_Y',
    ]);

    $cache1 = 'whatsapp.auto_link:1:5548999111111';
    $cache99 = 'whatsapp.auto_link:99:5548999111111';
    Cache::put($cache1, 100, now()->addHour()); // biz=1
    Cache::put($cache99, $c99->id, now()->addHour()); // biz=99

    $c99->mobile = '+5548999222222';
    $c99->save();

    expect(Cache::has($cache1))->toBeTrue('cache biz=1 preservado (ADR 0093 Tier 0)');
    expect(Cache::has($cache99))->toBeFalse('cache biz=99 invalidado (mudança própria)');
});

// ============================================================================
// CONVENTION — Observer registrado + não faz delete
// ============================================================================

it('R-WA-CONTACT-CACHE-CONV-01 — ContactObserver source NUNCA deleta nem trunca', function () {
    $source = file_get_contents(
        base_path('Modules/Whatsapp/Observers/ContactObserver.php')
    );

    expect($source)->not->toMatch('/->delete\(\)/',
        'REGRESSÃO: Observer ganhou ->delete(). Wagner regra Tier 0 "nunca perca mensagem" preservativo.');
    expect($source)->not->toMatch('/->truncate\(\)/', 'truncate proibido em observer.');
    expect($source)->toContain('forgetAttemptLinkCache',
        'REGRESSÃO: chamada forgetAttemptLinkCache removida — fix cache invalidation desfeito.');
});

it('R-WA-CONTACT-CACHE-CONV-02 — Observer registrado em WhatsappServiceProvider', function () {
    $providerSrc = file_get_contents(
        base_path('Modules/Whatsapp/Providers/WhatsappServiceProvider.php')
    );

    expect($providerSrc)->toContain('Contact::observe(\Modules\Whatsapp\Observers\ContactObserver::class)',
        'REGRESSÃO: ContactObserver não registrado — fix dormente, cache stale volta a vazar.');
});
