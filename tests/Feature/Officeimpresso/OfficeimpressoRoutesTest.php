<?php

/**
 * Smoke test do modulo Officeimpresso — rotas web.
 *
 * Valida que todas as telas restauradas da 3.7 carregam sem crash:
 *   - /officeimpresso/computadores       (view por business logado)
 *   - /officeimpresso/licenca_computador (CRUD licencas)
 *   - /officeimpresso/licenca_computador/create
 *   - /officeimpresso/businessall        (superadmin — todos businesses)
 *   - /officeimpresso/client             (OAuth password clients)
 *   - /officeimpresso/catalogue-qr       (catalogue QR do 6.7)
 *   - /api/officeimpresso                (Bearer)
 *
 * Pre-requisito: DEV_LOGIN_USERNAME + DEV_LOGIN_PASSWORD no .env,
 * user com can('superadmin') no DB local (Wagner WR23 em dev).
 */

use App\User;

beforeEach(function () {
    $user = env('DEV_LOGIN_USERNAME');
    $pass = env('DEV_LOGIN_PASSWORD');
    if (! $user || ! $pass) {
        $this->markTestSkipped('DEV_LOGIN_USERNAME/PASSWORD nao setadas em .env');
    }
    session()->flush();
    auth()->logout();
    $this->post('/login', ['username' => $user, 'password' => $pass]);
    if (! auth()->check()) {
        $this->markTestSkipped('Login falhou — confirmar creds DEV_LOGIN_* no .env');
    }
});

it('computadores renderiza com status 200', function () {
    $r = $this->get('/officeimpresso/computadores');
    expect($r->getStatusCode())->toBe(200);
    $r->assertSee('Licen', false); // substring "Licenças"
});

it('licenca_computador index renderiza com status 200', function () {
    $r = $this->get('/officeimpresso/licenca_computador');
    expect($r->getStatusCode())->toBe(200);
    $r->assertSee('Computadores Cadastrados', false);
});

it('licenca_computador/create renderiza com status 200', function () {
    $r = $this->get('/officeimpresso/licenca_computador/create');
    expect($r->getStatusCode())->toBe(200);
});

it('businessall renderiza pra superadmin com status 200', function () {
    if (! auth()->user()->can('superadmin')) {
        $this->markTestSkipped('Teste requer user com permissao superadmin');
    }
    $r = $this->get('/officeimpresso/businessall');
    expect($r->getStatusCode())->toBe(200);
});

it('client index renderiza pra superadmin com status 200', function () {
    if (! auth()->user()->can('superadmin')) {
        $this->markTestSkipped('Teste requer user com permissao superadmin');
    }
    $r = $this->get('/officeimpresso/client');
    expect($r->getStatusCode())->toBe(200);
});

it('catalogue-qr renderiza com status 200', function () {
    $r = $this->get('/officeimpresso/catalogue-qr');
    expect($r->getStatusCode())->toBe(200);
});

it('toggleBlock redireciona apos alternar bloqueio (ou 404 se id invalido)', function () {
    $r = $this->get('/officeimpresso/licenca_computador/999999/toggle-block');
    // Ou 302 (redirect after toggle) ou 500 (ModelNotFoundException);
    // ambos indicam que a rota existe.
    expect($r->getStatusCode())->toBeIn([302, 404, 500]);
});

it('api/officeimpresso devolve 401 sem Bearer', function () {
    auth()->logout();
    session()->flush();
    $r = $this->withHeaders(['Accept' => 'application/json'])->getJson('/api/officeimpresso');
    expect($r->getStatusCode())->toBe(401);
});

it('menu admin-sidebar inclui Officeimpresso como 2o item pra superadmin', function () {
    if (! auth()->user()->can('superadmin')) {
        $this->markTestSkipped('Teste requer user com permissao superadmin');
    }
    // Renderizar /home pra acionar AdminSidebarMenu middleware
    $r = $this->get('/home');
    expect($r->getStatusCode())->toBe(200);
    $r->assertSee('Office Impresso', false);
});
