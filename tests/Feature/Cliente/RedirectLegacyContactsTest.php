<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Thread cliente/04 — aposentar o dual-render da LISTA: GET /contacts?type=X → /cliente?type=X.
 *
 * O dono do redirecionamento é o middleware App\Http\Middleware\RedirectLegacyContacts, que ficou
 * dormente (comentado no Kernel) desde o #1289 e foi ligado aqui pelas decisões D1/D3 do [W]:
 * a lista inteira vai para /cliente, inclusive fornecedores (?type=supplier). O redirect do SHOW
 * ficou DESLIGADO por decisão [W] 2026-10-13 (só a lista nesta thread).
 *
 * Esta versão substitui a antiga, que estava em quarentena: os casos "não é 301" passavam sem
 * medir nada (um 403 ou um 409 também "não é 301"). Aqui cada caso afirma o status e o destino, e
 * cada "não redireciona" vem junto do controle que redireciona.
 *
 * Tenant de teste 98 (ADR 0358). Prefixo `rlc` nas funções (escopo global do Pest).
 */

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasTable('contacts') || DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('Schema UltimatePOS + Spatie exigem MySQL (ADR 0358).');
    }

    $this->business = $this->seededTenant();
    $this->user = $this->usuarioComPermissoes(['customer.view', 'supplier.view', 'customer.create'], $this->business);
    $this->actingAs($this->user);
    session(['user.business_id' => $this->business->id, 'business.id' => $this->business->id, 'user.id' => $this->user->id]);
    $this->versaoInertia = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
});

function rlcFlagIndex(array $bizIds): void
{
    config(['mwart.cliente_index.enabled' => true, 'mwart.cliente_index.business_ids' => $bizIds]);
}

test('lista de clientes: /contacts?type=customer vai para /cliente?type=customer (302)', function () {
    rlcFlagIndex([(int) $this->business->id]);

    $r = $this->get('/contacts?type=customer');

    $r->assertStatus(302);
    $r->assertRedirect('/cliente?type=customer');
});

test('fornecedores (D3): /contacts?type=supplier vai para /cliente?type=supplier, com o resto da query', function () {
    rlcFlagIndex([]);

    $r = $this->get('/contacts?type=supplier&q=abc');

    $r->assertStatus(302);
    $r->assertRedirect('/cliente?type=supplier&q=abc');
});

test('o destino responde: /cliente?type=customer abre o Cliente/Index em Inertia (sem laço)', function () {
    rlcFlagIndex([]);

    $r = $this->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => $this->versaoInertia, 'X-Requested-With' => 'XMLHttpRequest'])
        ->get('/cliente?type=customer');

    $r->assertOk();
    expect($r->json('component'))->toBe('Cliente/Index');
    expect($r->json('props.activeType'))->toBe('customer');
});

test('flag desligada: /contacts?type=customer segue na casca Blade, sem redirecionar', function () {
    config(['mwart.cliente_index.enabled' => false]);

    $r = $this->get('/contacts?type=customer');

    $r->assertOk();
    expect($r->isRedirect())->toBeFalse();
});

test('empresa fora da lista da flag: /contacts?type=customer não redireciona', function () {
    rlcFlagIndex([999999]);

    $r = $this->get('/contacts?type=customer');

    $r->assertOk();
    expect($r->isRedirect())->toBeFalse();
});

test('partial reload do Inertia em /contacts não troca de URL no meio da página', function () {
    rlcFlagIndex([]);

    $r = $this->withHeaders([
        'X-Inertia' => 'true', 'X-Inertia-Version' => $this->versaoInertia, 'X-Requested-With' => 'XMLHttpRequest',
        'X-Inertia-Partial-Component' => 'Cliente/Index', 'X-Inertia-Partial-Data' => 'customers',
    ])->get('/contacts?type=customer');

    $r->assertOk();
    expect($r->json('component'))->toBe('Cliente/Index');
});

test('AJAX legado (sem X-Inertia) em /contacts não é redirecionado', function () {
    rlcFlagIndex([]);

    $r = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])->get('/contacts?type=customer');

    expect($r->isRedirect())->toBeFalse();
    // Controle: a mesma URL como navegação de página redireciona.
    $this->get('/contacts?type=customer')->assertRedirect('/cliente?type=customer');
});

test('tipo inválido ou ausente: o middleware não redireciona', function () {
    rlcFlagIndex([]);

    foreach (['/contacts', '/contacts?type=xpto'] as $url) {
        $location = (string) $this->get($url)->headers->get('Location');
        expect($location)->not->toContain('/cliente');
    }
});

test('POST /contacts nunca é redirecionado para /cliente', function () {
    rlcFlagIndex([]);

    $location = (string) $this->post('/contacts', ['type' => 'customer'])->headers->get('Location');

    expect($location)->not->toContain('/cliente?type=');
});

test('SHOW desligado (decisão [W] 2026-10-13): /contacts/{id} de cliente não vai para /cliente/{id}', function () {
    config([
        'mwart.cliente_index.enabled' => false,
        'mwart.cliente_show.enabled' => true, 'mwart.cliente_show.business_ids' => [],
    ]);
    // Cliente REAL do próprio negócio: é o caso que o middleware redirecionaria com o SHOW ligado.
    $id = DB::table('contacts')->insertGetId([
        'business_id' => $this->business->id, 'type' => 'customer', 'name' => 'Cliente RLC '.uniqid(),
        'mobile' => '0', 'created_by' => $this->user->id, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $location = (string) $this->get("/contacts/{$id}")->headers->get('Location');

    expect($location)->not->toEndWith("/cliente/{$id}");
});
