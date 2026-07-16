<?php

declare(strict_types=1);
// Cobre UC-CIMP-01 (Import.casos.md) - G-2 rastreabilidade caso-teste.

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;

/**
 * Lane de casos do Import (Fase 2 — "ligar a máquina do protocolo").
 *
 * Prova COMPORTAMENTAL de que a flag MWART `cliente_import` liga o Inertia render
 * (Cliente/Import) em vez do Blade legacy `contact.import` — âncora real, NÃO o
 * Wave1ImportInertiaTest (source-grep, em quarentena de suíte, fora de lane).
 *
 * Backend: ContactController::getImportContacts() (routes/web.php GET /contacts/import).
 * Gate de permissão: customer.create OU supplier.create (admin seedado biz=1 tem).
 *
 * Skip-graceful em sqlite :memory: sem schema UPOS (padrão ClienteEditInertiaTest).
 */

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasTable('contacts')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }

    $this->business = $this->seededTenant(); // biz=1 canônico (ADR 0101)
    $this->user = \App\User::where('business_id', $this->business->id)->first();
    if (! $this->user) {
        $this->markTestSkipped('Sem user no business.');
    }

    // Força o branch Inertia pra qualquer tenant (sem gate por biz).
    config(['mwart.cliente_import.enabled' => true, 'mwart.cliente_import.business_ids' => []]);

    $this->actingAs($this->user);
    session(['user.business_id' => $this->business->id, 'business.id' => $this->business->id]);
});

test('GET /contacts/import renderiza Inertia Cliente/Import quando a flag liga', function () {
    $response = $this->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => '1'])
        ->get('/contacts/import');

    $response->assertStatus(200);

    $page = $response->headers->get('X-Inertia')
        ? json_decode($response->getContent(), true)
        : null;

    expect($page)->not->toBeNull('Response não é Inertia — gate cliente_import pode estar off.');
    expect($page['component'] ?? null)->toBe('Cliente/Import');
    // O wizard sempre reporta se o PHP Zip está disponível (banner de aviso).
    expect($page['props'] ?? [])->toHaveKey('zip_available');
});
