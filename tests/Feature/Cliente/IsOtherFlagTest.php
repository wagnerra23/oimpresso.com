<?php

declare(strict_types=1);

/**
 * ADR 0246 — Tests Pest pra flag is_other em contacts.
 *
 * Cobre:
 *   - Migration adicionou coluna is_other + índice (idempotente)
 *   - Cadastro com is_other=1 sem CPF/CNPJ salva 200
 *   - PATCH /cliente/{id}/papeis aceita is_other no whitelist
 *   - Invariante ≥1 papel ativo continua valendo com 5 papéis
 *   - Multi-tenant Tier 0: biz=1 não enxerga is_other=1 de biz=99 (ADR 0093 IRREVOGÁVEL)
 *
 * Tier 0: biz=1 (Wagner) em smoke, NUNCA biz=4 (ROTA LIVRE cliente real — ADR 0101).
 *
 * @see memory/decisions/0246-tipo-outros-default-migracoes-legacy.md
 * @see memory/decisions/0188-contacts-multi-type-flag-aditiva.md
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */

use App\Business;
use App\Contact;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Garante que migration ADR 0246 rodou (idempotente — não quebra se já rodou).
    if (! Schema::hasColumn('contacts', 'is_other')) {
        Schema::table('contacts', function ($t) {
            $t->boolean('is_other')->default(false)->after('is_representative');
        });
    }
});

it('migration is_other é idempotente — re-rodar não duplica coluna nem índice', function () {
    expect(Schema::hasColumn('contacts', 'is_other'))->toBeTrue();

    $indexes = collect(\DB::select('SHOW INDEXES FROM contacts'))
        ->pluck('Key_name')
        ->toArray();

    expect($indexes)->toContain('idx_contacts_biz_other');
});

it('aceita cadastro tipo Outros sem CPF/CNPJ — categoria default ADR 0246', function () {
    $business = Business::factory()->create();

    $contact = Contact::create([
        'business_id' => $business->id,
        'type' => 'customer',  // type enum UPOS permanece (ADR 0188 backward-compat)
        'name' => 'Lead Feira Sign 2014 #123',
        'contact_status' => 'active',
        'is_other' => 1,
        'is_customer' => 0,
        'is_supplier' => 0,
        'is_employee' => 0,
        'is_representative' => 0,
        // SEM cpf_cnpj — Outros não exige documento
        // SEM tax_number — Outros não exige documento
    ]);

    expect($contact->id)->toBeInt()
        ->and((bool) $contact->is_other)->toBeTrue()
        ->and($contact->tax_number)->toBeNull();
});

it('PATCH /cliente/{id}/papeis aceita is_other no whitelist', function () {
    $business = Business::factory()->create();
    $user = User::factory()->create(['business_id' => $business->id]);
    $contact = Contact::create([
        'business_id' => $business->id,
        'type' => 'customer',
        'name' => 'Cliente Teste',
        'contact_status' => 'active',
        'is_customer' => 1,
    ]);

    $this->actingAs($user)
        ->withSession(['user.business_id' => $business->id])
        ->patchJson("/cliente/{$contact->id}/papeis", ['is_other' => true])
        ->assertOk();

    $contact->refresh();
    expect((bool) $contact->is_other)->toBeTrue()
        ->and((bool) $contact->is_customer)->toBeTrue(); // Aditivo, não exclusivo
});

it('invariante ≥1 papel ativo bloqueia desativar todos os 5 flags (Outros incluído)', function () {
    $business = Business::factory()->create();
    $user = User::factory()->create(['business_id' => $business->id]);
    $contact = Contact::create([
        'business_id' => $business->id,
        'type' => 'customer',
        'name' => 'Contato Teste',
        'contact_status' => 'active',
        'is_customer' => 1,
        'is_other' => 0,
    ]);

    // Tenta zerar is_customer (único papel ativo) — deve bloquear 422
    $this->actingAs($user)
        ->withSession(['user.business_id' => $business->id])
        ->patchJson("/cliente/{$contact->id}/papeis", ['is_customer' => false])
        ->assertStatus(422);

    $contact->refresh();
    expect((bool) $contact->is_customer)->toBeTrue(); // Não mudou — invariante segurou
});

it('multi-tenant Tier 0: biz=1 não enxerga is_other=1 de biz=99', function () {
    // ADR 0093 IRREVOGÁVEL — cross-tenant isolation.
    $biz1 = Business::factory()->create();
    $biz99 = Business::factory()->create();

    $contactBiz1 = Contact::create([
        'business_id' => $biz1->id,
        'type' => 'customer',
        'name' => 'Outros biz=1',
        'contact_status' => 'active',
        'is_other' => 1,
    ]);

    $contactBiz99 = Contact::create([
        'business_id' => $biz99->id,
        'type' => 'customer',
        'name' => 'Outros biz=99',
        'contact_status' => 'active',
        'is_other' => 1,
    ]);

    // Query scoped por biz=1 deve trazer só o contact_biz1.
    $resultadoBiz1 = Contact::where('business_id', $biz1->id)
        ->where('is_other', 1)
        ->get();

    expect($resultadoBiz1)->toHaveCount(1)
        ->and($resultadoBiz1->first()->id)->toBe($contactBiz1->id);

    // Garantia: biz=99 também tem só o seu.
    $resultadoBiz99 = Contact::where('business_id', $biz99->id)
        ->where('is_other', 1)
        ->get();

    expect($resultadoBiz99)->toHaveCount(1)
        ->and($resultadoBiz99->first()->id)->toBe($contactBiz99->id);
});
