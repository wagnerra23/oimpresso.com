<?php

declare(strict_types=1);
// Cobre UC-CMAP-01, UC-CMAP-02 (Map.casos.md) - G-2 rastreabilidade caso-teste.

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lane de casos do Map (Fase 2 — "ligar a máquina do protocolo").
 *
 * Prova COMPORTAMENTAL de que a flag MWART `cliente_map` liga o Inertia render
 * (Cliente/Map) em vez do Blade legacy `contact.contact_map` + que a lista de
 * clientes do mapa é isolada por business_id (Tier 0, ADR 0093) — âncora real,
 * NÃO o Wave1MapInertiaTest (source-grep, em quarentena de suíte).
 *
 * Backend: ContactController::contactMap() (routes/web.php GET /contacts/map).
 * Gate de permissão: customer.view OU supplier.view (admin seedado biz=1 tem).
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

    config(['mwart.cliente_map.enabled' => true, 'mwart.cliente_map.business_ids' => []]);

    $this->actingAs($this->user);
    session(['user.business_id' => $this->business->id, 'business.id' => $this->business->id]);
});

test('GET /contacts/map renderiza Inertia Cliente/Map com contacts/all_contacts', function () {
    $response = $this->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => '1'])
        ->get('/contacts/map');

    $response->assertStatus(200);

    $page = $response->headers->get('X-Inertia')
        ? json_decode($response->getContent(), true)
        : null;

    expect($page)->not->toBeNull('Response não é Inertia — gate cliente_map pode estar off.');
    expect($page['component'] ?? null)->toBe('Cliente/Map');
    expect($page['props'] ?? [])->toHaveKey('contacts');
    expect($page['props'] ?? [])->toHaveKey('all_contacts');
});

test('Tier 0 — o mapa não lista cliente de outro business', function () {
    $otherBusiness = DB::table('business')->where('id', '!=', $this->business->id)->first();
    if (! $otherBusiness) {
        $this->markTestSkipped('Sem 2º business pra teste cross-tenant.');
    }

    $otherName = 'Cliente Estrangeiro Map '.uniqid();
    DB::table('contacts')->insert([
        'business_id' => $otherBusiness->id,
        'type' => 'customer',
        'name' => $otherName,
        'contact_status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => '1'])
        ->get('/contacts/map');

    $response->assertStatus(200);
    $page = json_decode($response->getContent(), true);

    $names = collect($page['props']['all_contacts'] ?? [])->pluck('name')->all();
    expect($names)->not->toContain($otherName); // global scope biz esconde o estrangeiro
});
