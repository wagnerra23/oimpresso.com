<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Wave C-BE (ADR 0179) -- 5 endpoints PATCH cadastrais autosave + lookups.
 *
 * Cobre Modules/Crm/Http/Controllers/ClienteAutosaveController:
 *   - identificacao (tipo, name, fantasia, tax_number mod 11, ie, rg, nascimento, cargo)
 *   - contato (mobile, tel2, email, site_url, canal_preferido)
 *   - endereco (zip_code, address_line_1/2, neighborhood, city, state UF)
 *   - comercial (credit_limit, pay_term_number, tabela_preco_padrao, pgto_padrao, obs_comercial)
 *   - classificacao (segmento, tags whitelist 9, contact_status, vip)
 *
 * Multi-tenant Tier 0 (ADR 0093 IRREVOGAVEL): cross-tenant biz=1 vs biz=99
 * blocked com 404 (NAO 403 -- nao vaza existencia do recurso).
 *
 * PII LGPD: response NUNCA retorna tax_number plain -- sempre tax_number_masked.
 *
 * Skip-graceful em sqlite :memory: que nao roda migrations UPOS (CI).
 * Padrao copiado de tests/Feature/Cliente/BackfillCpfCnpjCommandTest.php.
 */

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasTable('contacts')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) -- rode com DB_CONNECTION=mysql (dev) ou CI integration job.');
    }
    if (! Schema::hasColumn('contacts', 'tipo')) {
        $this->markTestSkipped('Migration 2026_05_22_000000 (Wave B drawer) ainda nao rodou neste ambiente.');
    }

    $this->business = \App\Business::first();
    if (! $this->business) {
        $this->markTestSkipped('Sem business em DB.');
    }
    $this->user = \App\User::where('business_id', $this->business->id)->first();
    if (! $this->user) {
        $this->markTestSkipped('Sem user no business.');
    }

    // Cria contact base customer ativo no biz alvo do Wagner (biz=1 ou primeiro).
    $now = now();
    $this->contactId = DB::table('contacts')->insertGetId([
        'business_id' => $this->business->id,
        'created_by' => $this->user->id,
        'type' => 'customer',
        'name' => 'Cliente Wave C BE Test',
        'mobile' => '11999999999',
        'contact_status' => 'active',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    // Helper autentica como user do biz alvo + popula session business_id
    // (canon UPOS -- pattern de tests/Feature/Cliente/RedirectLegacyContactsTest).
    $this->actingAs($this->user);
    session(['user.business_id' => $this->business->id]);
});

// ---------------------------------------------------------------------
// Tab Identificacao
// ---------------------------------------------------------------------

test('PATCH /cliente/{id}/identificacao -- payload valido retorna 200 + persiste tipo + fantasia', function () {
    $response = $this->patchJson("/cliente/{$this->contactId}/identificacao", [
        'tipo' => 'PJ',
        'fantasia' => 'Loja Acme Comercio',
        'cargo' => 'Diretor Comercial',
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('contact.tipo', 'PJ')
        ->assertJsonPath('contact.fantasia', 'Loja Acme Comercio')
        ->assertJsonPath('contact.cargo', 'Diretor Comercial');

    $this->assertDatabaseHas('contacts', [
        'id' => $this->contactId,
        'tipo' => 'PJ',
        'fantasia' => 'Loja Acme Comercio',
    ]);
});

test('PATCH /cliente/{id}/identificacao -- tax_number mod 11 invalido retorna 422', function () {
    $response = $this->patchJson("/cliente/{$this->contactId}/identificacao", [
        'tax_number' => '111.111.111-11', // sequencia trivial CPF -- rejeita
    ]);

    $response->assertStatus(422)
        ->assertJsonStructure(['errors' => ['tax_number']]);
});

test('PATCH /cliente/{id}/identificacao -- CPF valido mod 11 aceito', function () {
    $response = $this->patchJson("/cliente/{$this->contactId}/identificacao", [
        'tipo' => 'PF',
        'tax_number' => '111.444.777-35', // CPF valido mod 11
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('contact.tipo', 'PF');

    // PII: response NUNCA inclui tax_number plain.
    $body = $response->json();
    expect($body['contact'])->not->toHaveKey('tax_number');
    expect($body['contact'])->toHaveKey('tax_number_masked');
});

test('PATCH /cliente/{id}/identificacao -- CNPJ invalido enum tipo retorna 422', function () {
    $response = $this->patchJson("/cliente/{$this->contactId}/identificacao", [
        'tipo' => 'XX', // fora enum PF/PJ
    ]);

    $response->assertStatus(422)
        ->assertJsonStructure(['errors' => ['tipo']]);
});

// ---------------------------------------------------------------------
// Tab Contato
// ---------------------------------------------------------------------

test('PATCH /cliente/{id}/contato -- email + canal valido retorna 200', function () {
    $response = $this->patchJson("/cliente/{$this->contactId}/contato", [
        'email' => 'cliente@acme.com.br',
        'canal_preferido' => 'whatsapp',
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('contact.email', 'cliente@acme.com.br')
        ->assertJsonPath('contact.canal_preferido', 'whatsapp');
});

test('PATCH /cliente/{id}/contato -- email invalido retorna 422', function () {
    $response = $this->patchJson("/cliente/{$this->contactId}/contato", [
        'email' => 'nao-eh-email',
    ]);

    $response->assertStatus(422)
        ->assertJsonStructure(['errors' => ['email']]);
});

test('PATCH /cliente/{id}/contato -- canal fora enum retorna 422', function () {
    $response = $this->patchJson("/cliente/{$this->contactId}/contato", [
        'canal_preferido' => 'telegrama', // fora enum
    ]);

    $response->assertStatus(422);
});

// ---------------------------------------------------------------------
// Tab Endereco
// ---------------------------------------------------------------------

test('PATCH /cliente/{id}/endereco -- UF valido + CEP 8 digitos persiste', function () {
    $response = $this->patchJson("/cliente/{$this->contactId}/endereco", [
        'zip_code' => '01310-100',
        'address_line_1' => 'Av Paulista, 1578',
        'city' => 'Sao Paulo',
        'state' => 'SP',
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('contact.state', 'SP')
        ->assertJsonPath('contact.city', 'Sao Paulo');
});

// Cobertura per-campo do bug fix 2026-05-22 — frontend EnderecoTab faz autosave
// field-a-field; cada um precisa REALMENTE persistir no DB (nao so retornar
// 200 vazio). Regression do bug: validator descartava nomes PT-BR e o usuario
// via "Salvo" sem nada indo pra DB.

test('PATCH /endereco field-a-field -- zip_code persiste no DB', function () {
    $response = $this->patchJson("/cliente/{$this->contactId}/endereco", [
        'zip_code' => '01310-100',
    ]);
    $response->assertStatus(200)->assertJsonPath('contact.zip_code', '01310-100');
    $this->assertDatabaseHas('contacts', ['id' => $this->contactId, 'zip_code' => '01310-100']);
});

test('PATCH /endereco field-a-field -- address_line_1 persiste no DB', function () {
    $response = $this->patchJson("/cliente/{$this->contactId}/endereco", [
        'address_line_1' => 'Av Paulista',
    ]);
    $response->assertStatus(200)->assertJsonPath('contact.address_line_1', 'Av Paulista');
    $this->assertDatabaseHas('contacts', ['id' => $this->contactId, 'address_line_1' => 'Av Paulista']);
});

test('PATCH /endereco field-a-field -- address_line_2 persiste no DB', function () {
    $response = $this->patchJson("/cliente/{$this->contactId}/endereco", [
        'address_line_2' => 'Conj 1502, bloco B',
    ]);
    $response->assertStatus(200)->assertJsonPath('contact.address_line_2', 'Conj 1502, bloco B');
    $this->assertDatabaseHas('contacts', ['id' => $this->contactId, 'address_line_2' => 'Conj 1502, bloco B']);
});

test('PATCH /endereco field-a-field -- numero persiste no DB (canon BR coluna)', function () {
    // Skip se migration 2026_05_22_120000 ainda nao rodou neste ambiente.
    if (! \Illuminate\Support\Facades\Schema::hasColumn('contacts', 'numero')) {
        $this->markTestSkipped('Migration 2026_05_22_120000_add_numero_to_contacts ainda nao rodou.');
    }

    $response = $this->patchJson("/cliente/{$this->contactId}/endereco", [
        'numero' => '1578',
    ]);
    $response->assertStatus(200)->assertJsonPath('contact.numero', '1578');
    $this->assertDatabaseHas('contacts', ['id' => $this->contactId, 'numero' => '1578']);
});

test('PATCH /endereco field-a-field -- numero aceita formato BR nao-numerico (s/n, km, Lt)', function () {
    if (! \Illuminate\Support\Facades\Schema::hasColumn('contacts', 'numero')) {
        $this->markTestSkipped('Migration 2026_05_22_120000_add_numero_to_contacts ainda nao rodou.');
    }

    // BR aceita "s/n", "km 8", "Lt 12", "1578-A". Validator e nullable|string|max:20.
    $response = $this->patchJson("/cliente/{$this->contactId}/endereco", [
        'numero' => 's/n',
    ]);
    $response->assertStatus(200)->assertJsonPath('contact.numero', 's/n');
});

test('PATCH /endereco field-a-field -- neighborhood persiste no DB + retorna na response', function () {
    $response = $this->patchJson("/cliente/{$this->contactId}/endereco", [
        'neighborhood' => 'Bela Vista',
    ]);
    $response->assertStatus(200)
        // shapeContactResponse deve incluir neighborhood (bug colateral fixado).
        ->assertJsonPath('contact.neighborhood', 'Bela Vista');
    $this->assertDatabaseHas('contacts', ['id' => $this->contactId, 'neighborhood' => 'Bela Vista']);
});

test('PATCH /endereco field-a-field -- city persiste no DB', function () {
    $response = $this->patchJson("/cliente/{$this->contactId}/endereco", [
        'city' => 'Sao Paulo',
    ]);
    $response->assertStatus(200)->assertJsonPath('contact.city', 'Sao Paulo');
    $this->assertDatabaseHas('contacts', ['id' => $this->contactId, 'city' => 'Sao Paulo']);
});

test('PATCH /endereco field-a-field -- state persiste no DB', function () {
    $response = $this->patchJson("/cliente/{$this->contactId}/endereco", [
        'state' => 'SP',
    ]);
    $response->assertStatus(200)->assertJsonPath('contact.state', 'SP');
    $this->assertDatabaseHas('contacts', ['id' => $this->contactId, 'state' => 'SP']);
});

test('PATCH /endereco -- naming PT-BR (cep, endereco, bairro, cidade, uf) e descartado silenciosamente (regression)', function () {
    // Documenta o bug original — frontend antigo enviava nomes PT-BR. Backend
    // valida apenas canon (whitelist), entao Validator::validated() retorna
    // array vazio e nada persiste. Response e 200 OK (validator nao falha porque
    // todos os campos canon sao nullable), mas DB nao muda. Frontend 2026-05-22
    // renomeia state pra canon e elimina o gap.
    $response = $this->patchJson("/cliente/{$this->contactId}/endereco", [
        'cep' => '01310-100',
        'endereco' => 'Av Paulista',
        'bairro' => 'Bela Vista',
        'cidade' => 'Sao Paulo',
        'uf' => 'SP',
    ]);

    $response->assertStatus(200);
    // Nada persistiu — todos os 5 nomes PT-BR foram ignorados.
    $this->assertDatabaseHas('contacts', [
        'id' => $this->contactId,
        'zip_code' => null,
        'address_line_1' => null,
        'neighborhood' => null,
        'city' => null,
        'state' => null,
    ]);
});

test('PATCH /cliente/{id}/endereco -- UF invalida (XX) retorna 422', function () {
    $response = $this->patchJson("/cliente/{$this->contactId}/endereco", [
        'state' => 'XX', // fora enum 27 UFs
    ]);

    $response->assertStatus(422)
        ->assertJsonStructure(['errors' => ['state']]);
});

test('PATCH /cliente/{id}/endereco -- CEP com menos de 8 digitos retorna 422', function () {
    $response = $this->patchJson("/cliente/{$this->contactId}/endereco", [
        'zip_code' => '123', // muito curto
    ]);

    $response->assertStatus(422)
        ->assertJsonStructure(['errors' => ['zip_code']]);
});

// ---------------------------------------------------------------------
// Tab Comercial
// ---------------------------------------------------------------------

test('PATCH /cliente/{id}/comercial -- credit_limit + pay_term + tabela_preco valido', function () {
    $response = $this->patchJson("/cliente/{$this->contactId}/comercial", [
        'credit_limit' => 5000.50,
        'pay_term_number' => 30,
        'tabela_preco_padrao' => 'atacado',
        'pgto_padrao' => 'pix',
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('contact.tabela_preco_padrao', 'atacado')
        ->assertJsonPath('contact.pgto_padrao', 'pix');
});

test('PATCH /cliente/{id}/comercial -- pgto_padrao fora enum retorna 422', function () {
    $response = $this->patchJson("/cliente/{$this->contactId}/comercial", [
        'pgto_padrao' => 'crypto', // fora enum
    ]);

    $response->assertStatus(422);
});

// ---------------------------------------------------------------------
// Tab Classificacao
// ---------------------------------------------------------------------

test('PATCH /cliente/{id}/classificacao -- segmento + tags + vip persiste', function () {
    $response = $this->patchJson("/cliente/{$this->contactId}/classificacao", [
        'segmento' => 'corporativo',
        'tags' => ['vip', 'fiel'],
        'vip' => true,
        'contact_status' => 'active',
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('contact.segmento', 'corporativo')
        ->assertJsonPath('contact.vip', true);

    $tags = $response->json('contact.tags');
    expect($tags)->toBeArray();
    expect($tags)->toContain('vip');
    expect($tags)->toContain('fiel');
});

test('PATCH /cliente/{id}/classificacao -- segmento fora enum 6 valores retorna 422', function () {
    $response = $this->patchJson("/cliente/{$this->contactId}/classificacao", [
        'segmento' => 'industrial', // fora enum (varejo/atacado/agencia/corporativo/evento/governo)
    ]);

    $response->assertStatus(422)
        ->assertJsonStructure(['errors' => ['segmento']]);
});

test('PATCH /cliente/{id}/classificacao -- tag fora whitelist 9 valores retorna 422', function () {
    $response = $this->patchJson("/cliente/{$this->contactId}/classificacao", [
        'tags' => ['vip', 'invented_tag'], // segunda fora whitelist
    ]);

    $response->assertStatus(422);
});

// ---------------------------------------------------------------------
// Multi-tenant Tier 0 (ADR 0093 IRREVOGAVEL)
// ---------------------------------------------------------------------

test('PATCH cross-tenant biz=99 retorna 404 (nao 403 -- nao vaza existencia)', function () {
    // Cria contact em biz inexistente (99) -- nao deve aparecer pro user de biz=1.
    $foreignBizId = 99999;
    $now = now();
    $foreignContactId = DB::table('contacts')->insertGetId([
        'business_id' => $foreignBizId,
        'created_by' => $this->user->id,
        'type' => 'customer',
        'name' => 'Foreign biz contact',
        'mobile' => '11000000000',
        'contact_status' => 'active',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    // Tenta PATCH como user de biz=$this->business->id (nao biz=99).
    $response = $this->patchJson("/cliente/{$foreignContactId}/identificacao", [
        'tipo' => 'PJ',
    ]);

    $response->assertStatus(404);

    // DB NAO mudou no foreign biz (nao tinha tipo, continua sem).
    $this->assertDatabaseHas('contacts', [
        'id' => $foreignContactId,
        'business_id' => $foreignBizId,
        'tipo' => null,
    ]);
});

test('PATCH em contact inexistente retorna 404', function () {
    $response = $this->patchJson('/cliente/9999999/identificacao', [
        'tipo' => 'PF',
    ]);

    $response->assertStatus(404);
});

// ---------------------------------------------------------------------
// Permission gate matricial
// ---------------------------------------------------------------------

test('PATCH retorna 403 quando user sem permission customer.update', function () {
    // Cria user "minimal" sem permission customer.update no mesmo biz.
    // Usa pattern de RedirectLegacyContactsTest: actingAs user de biz=1 que
    // ja tem permission garantida. Aqui criamos um user sem permission.
    $weakUser = \App\User::factory()->create([
        'business_id' => $this->business->id,
    ]);

    // Garante que weakUser NAO tem can('customer.update').
    if ($weakUser->can('customer.update') || $weakUser->can('supplier.update')) {
        $this->markTestSkipped('User factory ja vem com customer.update/supplier.update -- ambiente de DB seedeado nao bloqueia esse teste.');
    }

    $this->actingAs($weakUser);
    session(['user.business_id' => $this->business->id]);

    $response = $this->patchJson("/cliente/{$this->contactId}/identificacao", [
        'tipo' => 'PJ',
    ]);

    $response->assertStatus(403)
        ->assertJsonStructure(['message']);
});
