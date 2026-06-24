<?php

declare(strict_types=1);
// @covers-us US-CRM-078

use App\Business;
use App\Contact;
use App\ContactAddress;
use App\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Scopes\ScopeByBusiness;

/**
 * US-CRM-078 — múltiplos endereços por contato (cross-tenant Tier 0).
 *
 * Roda NO CT 100 contra o MySQL real (schema UPos completo) — NUNCA sqlite:
 *   docker exec -e DB_CONNECTION=mysql oimpresso-staging php artisan test --filter=ContactAddressMultiTenant
 * (proibicoes.md §Ambiente). DatabaseTransactions → rollback (não polui staging).
 * Skip-graceful em sqlite/CI sem schema UPos (modules-pest.yml). Asserts escopados
 * aos contatos criados aqui (resistente a dados de staging — biz=1 dogfooding).
 *
 * biz sintéticos (não biz=4 cliente — R6 / ADR 0101).
 *
 * @see app/ContactAddress.php
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasTable('contacts') || ! Schema::hasTable('contact_addresses')) {
        $this->markTestSkipped('Schema UPos/contact_addresses ausente — rode no CT 100 (MySQL real) com a migration aplicada. NAO sqlite.');
    }
});

function ca078mkBiz(): Business
{
    return Business::create([
        'name' => 'Biz US078 '.uniqid(),
        'currency_id' => 1,
        'start_date' => '2026-01-01',
        'default_profit_percent' => 25.0,
        'owner_id' => 1,
    ]);
}

function ca078mkContact(int $bizId, array $o = []): Contact
{
    $c = new Contact();
    $c->business_id = $bizId;
    $c->type = $o['type'] ?? 'customer';
    $c->name = $o['name'] ?? 'Cliente US078';
    $c->contact_status = 'active';
    $c->created_by = 1; // NOT NULL no MySQL real (sqlite mascarava) — user 1 existe
    foreach ($o as $k => $v) {
        $c->{$k} = $v;
    }
    $c->save();

    return $c;
}

function ca078mkAddr(int $bizId, int $contactId, array $o = []): ContactAddress
{
    $a = new ContactAddress();
    $a->business_id = $bizId;
    $a->contact_id = $contactId;
    $a->fill($o);
    $a->save();

    return $a;
}

test('relacoes Contact/ContactAddress definidas', function () {
    $c = new Contact();
    expect($c->addresses())->toBeInstanceOf(HasMany::class);
    expect($c->defaultAddress())->toBeInstanceOf(HasOne::class);
    expect($c->shippingAddress())->toBeInstanceOf(HasOne::class);
    expect((new ContactAddress())->contact())->toBeInstanceOf(BelongsTo::class);
});

test('contato tem muitos enderecos com default + shipping', function () {
    $biz = ca078mkBiz();
    $contact = ca078mkContact($biz->id);

    ca078mkAddr($biz->id, $contact->id, [
        'label' => 'Matriz', 'is_default' => true, 'is_shipping' => true,
        'city' => 'Tubarão', 'state' => 'SC',
    ]);
    ca078mkAddr($biz->id, $contact->id, ['label' => 'Obra', 'city' => 'Criciúma', 'state' => 'SC']);

    $this->actingAs(User::factory()->create(['business_id' => $biz->id]));
    session(['user.business_id' => $biz->id]);

    expect($contact->addresses()->count())->toBe(2);
    expect($contact->defaultAddress->label)->toBe('Matriz');
    expect($contact->shippingAddress->label)->toBe('Matriz');
});

test('isolamento cross-tenant biz=1 vs biz=99 via global scope (ADR 0093)', function () {
    $biz1 = ca078mkBiz();
    $biz99 = ca078mkBiz();

    $c1 = ca078mkContact($biz1->id, ['name' => 'Cliente biz1']);
    $c99 = ca078mkContact($biz99->id, ['name' => 'Cliente biz99']);

    $a1 = ca078mkAddr($biz1->id, $c1->id, ['label' => 'Matriz biz1']);
    $a99 = ca078mkAddr($biz99->id, $c99->id, ['label' => 'Matriz biz99']);

    $this->actingAs(User::factory()->create(['business_id' => $biz1->id]));
    session(['user.business_id' => $biz1->id]);

    // Scope ativo: o endereço de biz1 é visível; o de biz99 NÃO (escopado aos meus ids).
    expect(ContactAddress::whereIn('id', [$a1->id, $a99->id])->pluck('id')->all())->toBe([$a1->id]);
    expect(ContactAddress::find($a99->id))->toBeNull();

    // Nem via relação a partir do contact de biz99.
    expect($c99->addresses()->count())->toBe(0);

    // withoutGlobalScope enxerga os 2 — prova que o filtro é do scope.
    expect(
        ContactAddress::withoutGlobalScope(ScopeByBusiness::class)
            ->whereIn('id', [$a1->id, $a99->id])->count()
    )->toBe(2);
});

test('backfill inline idempotente preserva business_id', function () {
    $biz = ca078mkBiz();

    $withAddr = ca078mkContact($biz->id, [
        'name' => 'Com endereço',
        'zip_code' => '88701-000',
        'address_line_1' => 'Av Marcolino Martins Cabral',
        'numero' => '1578',
        'city' => 'Tubarão',
        'state' => 'SC',
    ]);
    ca078mkContact($biz->id, ['name' => 'Sem endereço']);

    // Escopado ao biz sintético (não toca contatos de staging).
    expect(ContactAddress::backfillInline($biz->id))->toBe(1);

    $addr = ContactAddress::withoutGlobalScope(ScopeByBusiness::class)
        ->where('contact_id', $withAddr->id)->first();
    expect($addr)->not->toBeNull();
    expect($addr->is_default)->toBeTrue();
    expect($addr->is_shipping)->toBeTrue();
    expect($addr->label)->toBe('Principal');
    expect($addr->numero)->toBe('1578');
    expect((int) $addr->business_id)->toBe($biz->id);

    // Re-run idempotente.
    expect(ContactAddress::backfillInline($biz->id))->toBe(0);
});
