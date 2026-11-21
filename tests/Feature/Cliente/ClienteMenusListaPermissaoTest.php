<?php

declare(strict_types=1);
// Cobre UC-CIDX-24 (Index.casos.md) - G-2 rastreabilidade caso-teste.

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;

/**
 * Menus da lista de clientes que dependem de permissão (paridade com o protótipo
 * `clientes-page.jsx`, onda 4, 2026-11-21):
 *
 *   - "Grupos de clientes" e "Mapa de clientes" (no "Mais ações") só aparecem para quem tem
 *     `customer.view`. As duas rotas exigem essa permissão: medido em 2026-11-21, quem só tinha
 *     `customer.view_own` via "Grupos de clientes" no menu e recebia 403 ao clicar.
 *   - "Ativar/Desativar cadastro" (no ⋮ da linha) só aparece para quem pode alterar cadastro
 *     (`customer.update` ou `supplier.update`) — a mesma regra do `updateStatus`.
 *
 * A tela decide pelas flags `permissions.view_all` e `permissions.update` da prop da página; este
 * teste prova que o servidor as calcula pela permissão do usuário. Tenant 98 (ADR 0358).
 */
uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasTable('contacts')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }
    $this->business = $this->seededTenant();
});

/** Permissões da página /cliente (visita Inertia completa, com os headers do @inertiajs/core). */
function cmlPermissoes($teste, array $permissoes): array
{
    $user = $teste->usuarioComPermissoes($permissoes, $teste->business);
    $teste->actingAs($user);
    session(['user.business_id' => $teste->business->id, 'user.id' => $user->id, 'business.id' => $teste->business->id]);

    $versao = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
    $r = $teste->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => $versao,
        'X-Requested-With' => 'XMLHttpRequest',
        'Accept' => 'text/html, application/xhtml+xml',
    ])->get('/cliente?type=customer');
    $r->assertOk();
    expect($r->json('component'))->toBe('Cliente/Index');

    return (array) $r->json('props.permissions');
}

test('UC-CIDX-24 quem só vê os próprios clientes não recebe Grupos, Mapa nem Ativar/Desativar', function () {
    $p = cmlPermissoes($this, ['customer.view_own']);

    expect($p)->toHaveKey('view_all')
        ->and($p['view_all'])->toBeFalse()
        ->and($p)->toHaveKey('update')
        ->and($p['update'])->toBeFalse();
});

test('UC-CIDX-24 com "ver todos os clientes" e "alterar cliente" os três itens aparecem', function () {
    $p = cmlPermissoes($this, ['customer.view', 'customer.update']);

    expect($p['view_all'])->toBeTrue()
        ->and($p['update'])->toBeTrue();
});

test('UC-CIDX-24 "alterar fornecedor" também libera Ativar/Desativar, como no updateStatus', function () {
    $p = cmlPermissoes($this, ['customer.view', 'supplier.update']);

    expect($p['update'])->toBeTrue();
});
