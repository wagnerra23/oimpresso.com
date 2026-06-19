<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * GUARD — Onda 1 PR B' 2026-05-26 — Daniela @ Martinho.
 *
 * Cobertura:
 *   1. Migration adiciona email_billing + email_nfe em `contacts` (idempotente)
 *   2. PATCH /cliente/{id}/contato aceita alternate_number, email_billing, email_nfe
 *   3. shapeContactResponse retorna os 3 campos
 *   4. Tier 0 multi-tenant — biz=99 não atualiza contact biz=1 (404)
 *
 * Skip-graceful em sqlite memory.
 */

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasTable('contacts')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }

    $this->business = $this->seededTenant(); // biz=1 canônico (ADR 0101) — skip acionável se o seed faltar
    $this->user = \App\User::where('business_id', $this->business->id)->first();
    if (! $this->user) {
        $this->markTestSkipped('Sem user no business.');
    }

    $now = now();
    $this->contactId = DB::table('contacts')->insertGetId([
        'business_id' => $this->business->id,
        'created_by' => $this->user->id,
        'type' => 'customer',
        'name' => 'Cliente Contato Extras Test',
        'first_name' => 'Cliente',
        'mobile' => '11900000001',
        'contact_status' => 'active',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $this->actingAs($this->user);
    session(['user.business_id' => $this->business->id]);
});

// ---------------------------------------------------------------------
// 1 — Schema guard: colunas existem (migration rodou)
// ---------------------------------------------------------------------

test('migration adiciona email_billing + email_nfe em contacts', function () {
    expect(Schema::hasColumn('contacts', 'email_billing'))->toBeTrue();
    expect(Schema::hasColumn('contacts', 'email_nfe'))->toBeTrue();
});

// ---------------------------------------------------------------------
// 2 — PATCH /cliente/{id}/contato persiste 3 campos novos
// ---------------------------------------------------------------------

test('PATCH alternate_number persiste 3º telefone', function () {
    $response = $this->patchJson("/cliente/{$this->contactId}/contato", [
        'alternate_number' => '11900000003',
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('contact.alternate_number', '11900000003');

    $contact = DB::table('contacts')->where('id', $this->contactId)->first();
    expect($contact->alternate_number)->toBe('11900000003');
});

test('PATCH email_billing persiste email comercial', function () {
    $response = $this->patchJson("/cliente/{$this->contactId}/contato", [
        'email_billing' => 'vendedor@martinho.com.br',
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('contact.email_billing', 'vendedor@martinho.com.br');

    $contact = DB::table('contacts')->where('id', $this->contactId)->first();
    expect($contact->email_billing)->toBe('vendedor@martinho.com.br');
});

test('PATCH email_nfe persiste email do contador', function () {
    $response = $this->patchJson("/cliente/{$this->contactId}/contato", [
        'email_nfe' => 'contador@martinho.com.br',
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('contact.email_nfe', 'contador@martinho.com.br');

    $contact = DB::table('contacts')->where('id', $this->contactId)->first();
    expect($contact->email_nfe)->toBe('contador@martinho.com.br');
});

test('PATCH email_billing inválido retorna 422', function () {
    $response = $this->patchJson("/cliente/{$this->contactId}/contato", [
        'email_billing' => 'nao-eh-email',
    ]);

    $response->assertStatus(422);
});

// ---------------------------------------------------------------------
// 3 — Tier 0 multi-tenant (ADR 0093)
// ---------------------------------------------------------------------

test('Tier 0 — user de outro business recebe 404 ao tentar PATCH contato', function () {
    $otherBusiness = DB::table('business')->where('id', '!=', $this->business->id)->first();
    if (! $otherBusiness) {
        $this->markTestSkipped('Sem 2º business pra teste cross-tenant.');
    }
    $otherUser = \App\User::where('business_id', $otherBusiness->id)->first();
    if (! $otherUser) {
        $this->markTestSkipped('Sem user no business secundário.');
    }

    $this->actingAs($otherUser);
    session(['user.business_id' => $otherBusiness->id]);

    $response = $this->patchJson("/cliente/{$this->contactId}/contato", [
        'email_billing' => 'cross-tenant@evil.com',
    ]);

    $response->assertStatus(404);

    // Confirma que NÃO foi persistido.
    $contact = DB::table('contacts')->where('id', $this->contactId)->first();
    expect($contact->email_billing)->not->toBe('cross-tenant@evil.com');
});
