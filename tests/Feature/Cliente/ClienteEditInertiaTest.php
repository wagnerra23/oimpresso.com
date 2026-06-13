<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * GUARD — ContactController::edit() + update() Inertia path.
 *
 * Bug 2026-05-26 (reportado Wagner):
 * (1) edit() omitia campos BR (cpf_cnpj, IE, IM, nome_fantasia, etc.) no
 *     payload Inertia::render — frontend Edit.tsx exibia campos vazios mesmo
 *     com valor no banco;
 * (2) update() tinha TODO o corpo dentro de `if (isLegacyAjax())` — Inertia
 *     PUT retornava void e o client lançava "All Inertia requests must
 *     receive a valid Inertia response". Refator: processContactUpdate()
 *     helper + branch Inertia explícito.
 *
 * Multi-tenant Tier 0 (ADR 0093): cross-tenant biz=99 vs biz=1 retorna 404
 * (NÃO 403 — não vaza existência do recurso).
 *
 * Skip-graceful em sqlite :memory: que não roda migrations UPOS (CI).
 * Padrão copiado de tests/Feature/Cliente/ClienteDrawerCadastroAutosaveTest.php.
 */

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasTable('contacts')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }
    if (! Schema::hasColumn('contacts', 'cpf_cnpj')) {
        $this->markTestSkipped('Migration 2026_05_21_140000 (BR fields) ainda não rodou neste ambiente.');
    }

    $this->business = $this->seededTenant(); // biz=1 canônico (ADR 0101) — skip acionável se o seed faltar
    $this->user = \App\User::where('business_id', $this->business->id)->first();
    if (! $this->user) {
        $this->markTestSkipped('Sem user no business.');
    }

    config(['mwart.cliente_edit.enabled' => true]);

    $now = now();
    $this->contactId = DB::table('contacts')->insertGetId([
        'business_id' => $this->business->id,
        'created_by' => $this->user->id,
        'type' => 'customer',
        'contact_type' => 'business',
        'name' => 'Cliente Edit Inertia Test',
        'first_name' => 'Cliente Edit',
        'last_name' => 'Inertia Test',
        'mobile' => '11999990000',
        'contact_status' => 'active',
        // Campos BR que o bug fix preserva no payload edit().
        'cpf_cnpj' => '11222333000181',
        'rg' => null,
        'inscricao_estadual' => '111222333444',
        'inscricao_municipal' => 'IM-123',
        'indicador_ie' => 1,
        'nome_fantasia' => 'Fantasia LTDA',
        'consumidor_final' => false,
        'contribuinte' => true,
        'regime' => 'simples',
        'suframa' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $this->actingAs($this->user);
    session(['user.business_id' => $this->business->id]);
});

// Cleanup automático via DatabaseTransactions::class (rollback no teardown).

// ---------------------------------------------------------------------
// edit() — payload Inertia inclui campos BR (bug fix 2026-05-26 #1)
// ---------------------------------------------------------------------

test('GET /contacts/{id}/edit Inertia retorna campos BR no props.contact (não-null)', function () {
    $response = $this->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => '1'])
        ->get("/contacts/{$this->contactId}/edit");

    $response->assertStatus(200);

    $page = $response->headers->get('X-Inertia')
        ? json_decode($response->getContent(), true)
        : null;

    expect($page)->not->toBeNull('Response não é Inertia — gate cliente_edit pode estar off.');
    expect($page['component'] ?? null)->toBe('Cliente/Edit');

    $contact = $page['props']['contact'] ?? [];

    // Campos BR previously missing — guard contra regressão.
    expect($contact)->toHaveKey('cpf_cnpj');
    expect($contact['cpf_cnpj'])->toBe('11222333000181');
    expect($contact)->toHaveKey('inscricao_estadual');
    expect($contact['inscricao_estadual'])->toBe('111222333444');
    expect($contact)->toHaveKey('inscricao_municipal');
    expect($contact['inscricao_municipal'])->toBe('IM-123');
    expect($contact)->toHaveKey('indicador_ie');
    expect($contact['indicador_ie'])->toBe(1);
    expect($contact)->toHaveKey('nome_fantasia');
    expect($contact['nome_fantasia'])->toBe('Fantasia LTDA');
    expect($contact)->toHaveKey('consumidor_final');
    expect($contact['consumidor_final'])->toBeFalse();
    expect($contact)->toHaveKey('contribuinte');
    expect($contact['contribuinte'])->toBeTrue();
    expect($contact)->toHaveKey('regime');
    expect($contact['regime'])->toBe('simples');
    expect($contact)->toHaveKey('rg');
    expect($contact)->toHaveKey('suframa');
});

// ---------------------------------------------------------------------
// update() — Inertia PUT retorna redirect com flash (bug fix 2026-05-26 #2)
// ---------------------------------------------------------------------

test('PUT /contacts/{id} via Inertia atualiza cpf_cnpj e redireciona com flash', function () {
    $response = $this->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => '1'])
        ->put("/contacts/{$this->contactId}", [
            'type' => 'customer',
            'contact_type_radio' => 'business',
            'first_name' => 'Cliente Edit',
            'last_name' => 'Inertia Atualizado',
            'cpf_cnpj' => '11444777000161',
            'nome_fantasia' => 'Fantasia Renomeada',
            'regime' => 'lucro_presumido',
            'consumidor_final' => false,
            'contribuinte' => true,
            'opening_balance' => '0',
            'credit_limit' => '',
        ]);

    // Inertia redirect: 302 (Inertia client interpreta como navegação interna).
    $response->assertStatus(302);

    $contact = DB::table('contacts')->where('id', $this->contactId)->first();

    expect($contact->cpf_cnpj)->toBe('11444777000161');
    expect($contact->nome_fantasia)->toBe('Fantasia Renomeada');
    expect($contact->regime)->toBe('lucro_presumido');
});

// ---------------------------------------------------------------------
// Multi-tenant Tier 0 — biz=99 não vê edit de biz=1 (404)
// ---------------------------------------------------------------------

test('Tier 0 — user de outro business recebe 404 ao tentar GET edit', function () {
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

    $response = $this->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => '1'])
        ->get("/contacts/{$this->contactId}/edit");

    // findOrFail no edit() retorna 404 quando contact não pertence ao business da sessão.
    $response->assertStatus(404);
});
