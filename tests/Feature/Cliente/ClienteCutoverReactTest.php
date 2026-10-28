<?php

declare(strict_types=1);
// Cobre UC-CCRE-04 (Create.casos.md) e UC-CLED-07 (Ledger.casos.md) - G-2 rastreabilidade caso-teste.

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cutover F5 de 2026-10-27: cadastro e extrato de contato servem só React. As chaves
 * MWART_CLIENTE_CREATE/LEDGER ficam DESLIGADAS aqui de propósito — se o ramo Blade
 * voltar, a resposta deixa de ser Inertia e estes casos caem.
 */
uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasTable('contacts')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }

    $this->business = $this->seededTenant(); // tenant de teste (ADR 0358)
    $this->user = $this->usuarioComPermissoes(['customer.create', 'customer.view'], $this->business);
    $this->headers = [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request()),
    ];

    config([
        'mwart.cliente_create.enabled' => false,
        'mwart.cliente_ledger.enabled' => false,
    ]);

    $this->actingAs($this->user);
    session([
        'user.business_id' => $this->business->id,
        'business.id' => $this->business->id,
        // getLedgerDetails formata valores com num_f, que lê a moeda da sessão (sem ela: 500).
        'currency' => ['id' => 1, 'code' => 'BRL', 'symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ','],
    ]);
});

test('UC-CCRE-04 — abrir o cadastro de contato serve a tela React mesmo com a chave desligada', function () {
    $response = $this->withHeaders($this->headers)->get('/contacts/create?type=customer');

    $response->assertStatus(200);
    $page = json_decode($response->getContent(), true);
    expect($page['component'] ?? null)->toBe('Cliente/Create');
    expect($page['props']['types'] ?? [])->toHaveKey('customer');
});

test('UC-CLED-07 — abrir o extrato serve a tela React mesmo com a chave desligada', function () {
    $id = (int) DB::table('contacts')->insertGetId([
        'business_id' => $this->business->id,
        'created_by' => $this->user->id, // FK NOT NULL para users
        'type' => 'customer',
        'name' => 'Cliente Cutover Ledger '.uniqid(),
        'contact_status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this->withHeaders($this->headers)->get('/contacts/ledger?contact_id='.$id);

    $response->assertStatus(200);
    $page = json_decode($response->getContent(), true);
    expect($page['component'] ?? null)->toBe('Cliente/Ledger');
    expect($page['props']['contact']['id'] ?? null)->toBe($id);
});
