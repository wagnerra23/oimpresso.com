<?php

declare(strict_types=1);

use App\Contact;
use App\ContactAddress;
use App\Concerns\BusinessScopeImpl;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;

/**
 * US-CRM-080 -- Feature test ContactAddress (multi-tenant Tier 0, ADR 0093 +
 * ADR 0101 biz=1, nunca cliente real).
 *
 * Cobre:
 *   - Isolamento cross-tenant: bizA nao ve endereco de bizB
 *   - Default unico: markAsDefault() desmarca os demais do mesmo contato
 *   - Accessor compat: Contact->shipping_address retorna string do default
 *   - PII: endereco NAO vai pra activity_log (model nao usa LogsActivity)
 *
 * Skip-graceful em SQLite memory seguindo pattern de
 * tests/Feature/Cliente/BackfillCpfCnpjCommandTest.php.
 */
uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasTable('contacts') || ! Schema::hasTable('contact_addresses')) {
        $this->markTestSkipped('Schema ausente (sqlite memory) -- rode com DB_CONNECTION=mysql ou CI integration.');
    }

    $this->business = \App\Business::first();
    if (! $this->business) {
        $this->markTestSkipped('Sem business em DB.');
    }
    $this->user = \App\User::where('business_id', $this->business->id)->first();
    if (! $this->user) {
        $this->markTestSkipped('Sem user no business.');
    }

    // Simula sessao biz ativa (BusinessScopeImpl le session('user.business_id')).
    session(['user.business_id' => $this->business->id, 'user.id' => $this->user->id]);
    $this->actingAs($this->user);

    $this->contact = Contact::create([
        'business_id' => $this->business->id,
        'created_by' => $this->user->id,
        'type' => 'customer',
        'name' => 'Cliente Teste Enderecos',
        'contact_status' => 'active',
    ]);
});

it('aplica global scope -- contato de outro business nao vaza', function () {
    // Endereco no business da sessao.
    ContactAddress::create([
        'contact_id' => $this->contact->id,
        'label' => 'Matriz',
        'address_line_1' => 'Rua A',
        'city' => 'Sao Paulo',
        'state' => 'SP',
        'is_default' => true,
    ]);

    // Endereco forjado em OUTRO business (bypass scope pra inserir).
    $otherBizId = $this->business->id + 9999;
    ContactAddress::withoutGlobalScope(BusinessScopeImpl::class)->create([
        'business_id' => $otherBizId,
        'contact_id' => $this->contact->id,
        'label' => 'Vazamento',
        'address_line_1' => 'Rua Secreta',
        'is_default' => true,
    ]);

    // Query normal (com scope) so ve o da sessao.
    $visiveis = ContactAddress::all();
    expect($visiveis)->toHaveCount(1);
    expect($visiveis->first()->business_id)->toBe($this->business->id);
    expect($visiveis->pluck('label'))->not->toContain('Vazamento');
});

it('auto-preenche business_id ao criar via sessao', function () {
    $addr = ContactAddress::create([
        'contact_id' => $this->contact->id,
        'address_line_1' => 'Rua B',
    ]);

    expect($addr->business_id)->toBe($this->business->id);
});

it('garante exatamente 1 default por contato via markAsDefault', function () {
    $a = ContactAddress::create([
        'contact_id' => $this->contact->id, 'label' => 'A',
        'address_line_1' => 'Rua A', 'is_default' => true,
    ]);
    $b = ContactAddress::create([
        'contact_id' => $this->contact->id, 'label' => 'B',
        'address_line_1' => 'Rua B', 'is_default' => false,
    ]);

    $b->markAsDefault();

    $defaults = ContactAddress::where('contact_id', $this->contact->id)
        ->where('is_default', true)->get();

    expect($defaults)->toHaveCount(1);
    expect($defaults->first()->id)->toBe($b->id);
    expect($a->fresh()->is_default)->toBeFalse();
});

it('accessor compat Contact->shipping_address retorna string do default', function () {
    ContactAddress::create([
        'contact_id' => $this->contact->id,
        'label' => 'Principal',
        'address_line_1' => 'Av Paulista',
        'numero' => '1578',
        'city' => 'Sao Paulo',
        'state' => 'SP',
        'zip_code' => '01310200',
        'is_default' => true,
    ]);

    $flat = $this->contact->fresh()->shipping_address;

    expect($flat)->toContain('Av Paulista');
    expect($flat)->toContain('1578');
    expect($flat)->toContain('Sao Paulo');
});

it('NAO grava endereco em activity_log (PII LGPD)', function () {
    if (! Schema::hasTable('activity_log')) {
        $this->markTestSkipped('Sem tabela activity_log.');
    }

    $countBefore = \DB::table('activity_log')->count();

    ContactAddress::create([
        'contact_id' => $this->contact->id,
        'address_line_1' => 'Rua Confidencial 999',
        'is_default' => true,
    ]);

    $countAfter = \DB::table('activity_log')->count();

    // ContactAddress nao usa LogsActivity -> 0 novas entries.
    expect($countAfter)->toBe($countBefore);
});
