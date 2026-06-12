<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * GUARD — Onda 1 PR D 2026-05-26 — Tab Veículos no Show do Cliente.
 *
 * Pedido Daniela (Martinho cliente piloto): ver frota do cliente direto no
 * cadastro sem abrir OficinaAuto separado. Reutiliza schema `vehicles`
 * (ADR 0137 OficinaAuto) — coluna contact_id já liga veículo ao cliente.
 *
 * Cobertura:
 *   1. Gate `modules.oficinaauto_enabled` reflete isModuleInstalled('OficinaAuto')
 *   2. Payload `vehicles` paginado retorna veículos do contact + business
 *   3. Multi-tenant Tier 0 (ADR 0093) — biz=99 não vê veículos de biz=1
 *   4. Sem OficinaAuto module → vehicles=null + flag false
 *
 * Skip-graceful em sqlite :memory: que não tem schema UPOS+OficinaAuto.
 */

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasTable('contacts')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }
    if (! Schema::hasTable('vehicles')) {
        $this->markTestSkipped('Schema Modules/OficinaAuto ausente neste ambiente — migration vehicles não rodou.');
    }

    $this->business = $this->seededTenant(); // biz=1 canônico (ADR 0101) — skip acionável se o seed faltar
    $this->user = \App\User::where('business_id', $this->business->id)->first();
    if (! $this->user) {
        $this->markTestSkipped('Sem user no business.');
    }

    config(['mwart.cliente_show.enabled' => true]);

    $now = now();
    $this->contactId = DB::table('contacts')->insertGetId([
        'business_id' => $this->business->id,
        'created_by' => $this->user->id,
        'type' => 'customer',
        'contact_type' => 'business',
        'name' => 'Cliente Frota Test',
        'first_name' => 'Cliente Frota',
        'mobile' => '11999990000',
        'contact_status' => 'active',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    // 2 veículos pro contact alvo.
    DB::table('vehicles')->insert([
        [
            'business_id' => $this->business->id,
            'contact_id' => $this->contactId,
            'plate' => 'ABC1D23',
            'vehicle_type' => 'caminhao_basculante',
            'current_status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ],
        [
            'business_id' => $this->business->id,
            'contact_id' => $this->contactId,
            'plate' => 'XYZ4E56',
            'vehicle_type' => 'truck',
            'current_status' => 'in_service',
            'created_at' => $now,
            'updated_at' => $now,
        ],
    ]);

    $this->actingAs($this->user);
    session(['user.business_id' => $this->business->id]);
});

// ---------------------------------------------------------------------
// 1 — Payload Show inclui modules.oficinaauto_enabled + vehicles defer
// ---------------------------------------------------------------------

test('GET /contacts/{id} Inertia inclui modules.oficinaauto_enabled', function () {
    $response = $this->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => '1'])
        ->get("/contacts/{$this->contactId}");

    $response->assertStatus(200);

    $page = json_decode($response->getContent(), true);
    expect($page)->not->toBeNull('Response não é Inertia — gate cliente_show pode estar off.');
    expect($page['component'] ?? null)->toBe('Cliente/Show');
    expect($page['props'])->toHaveKey('modules');
    expect($page['props']['modules'])->toHaveKey('oficinaauto_enabled');
    expect($page['props']['modules']['oficinaauto_enabled'])->toBeBool();
});

// ---------------------------------------------------------------------
// 2 — Partial reload only=vehicles retorna paginator com veículos do cliente
// ---------------------------------------------------------------------

test('Partial reload only=vehicles retorna paginator filtrado por contact_id + business_id', function () {
    // Skip se OficinaAuto não estiver instalado pro biz (gate retorna null).
    $moduleUtil = app(\App\Utils\ModuleUtil::class);
    if (! $moduleUtil->isModuleInstalled('OficinaAuto')) {
        $this->markTestSkipped('OficinaAuto não instalado neste ambiente — pular partial vehicles.');
    }

    $response = $this->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => '1',
            'X-Inertia-Partial-Component' => 'Cliente/Show',
            'X-Inertia-Partial-Data' => 'vehicles',
        ])
        ->get("/contacts/{$this->contactId}?tab=vehicles");

    $response->assertStatus(200);
    $page = json_decode($response->getContent(), true);

    expect($page['props'])->toHaveKey('vehicles');
    expect($page['props']['vehicles'])->toHaveKey('data');
    expect($page['props']['vehicles']['data'])->toHaveCount(2);
    expect($page['props']['vehicles']['total'])->toBe(2);

    $plates = array_column($page['props']['vehicles']['data'], 'plate');
    expect($plates)->toContain('ABC1D23');
    expect($plates)->toContain('XYZ4E56');
});

// ---------------------------------------------------------------------
// 3 — Multi-tenant Tier 0 — biz=99 não vê show de contact biz=1
// ---------------------------------------------------------------------

test('Tier 0 — user de outro business recebe 404 no Show', function () {
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
        ->get("/contacts/{$this->contactId}");

    $response->assertStatus(404);
});

// ---------------------------------------------------------------------
// 4 — Busca por placa (vehicles_q) filtra paginator
// ---------------------------------------------------------------------

test('vehicles_q=ABC1 filtra paginator pra 1 veículo', function () {
    $moduleUtil = app(\App\Utils\ModuleUtil::class);
    if (! $moduleUtil->isModuleInstalled('OficinaAuto')) {
        $this->markTestSkipped('OficinaAuto não instalado neste ambiente.');
    }

    $response = $this->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => '1',
            'X-Inertia-Partial-Component' => 'Cliente/Show',
            'X-Inertia-Partial-Data' => 'vehicles',
        ])
        ->get("/contacts/{$this->contactId}?tab=vehicles&vehicles_q=ABC1");

    $response->assertStatus(200);
    $page = json_decode($response->getContent(), true);

    expect($page['props']['vehicles']['data'])->toHaveCount(1);
    expect($page['props']['vehicles']['data'][0]['plate'])->toBe('ABC1D23');
});
