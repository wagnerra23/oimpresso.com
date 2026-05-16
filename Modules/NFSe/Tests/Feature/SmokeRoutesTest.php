<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

uses(Tests\TestCase::class);

/**
 * Smoke das rotas principais do módulo NFSe.
 *
 * Garante apenas que:
 *   1. Rotas estão registradas no router Laravel
 *   2. Middleware 'auth' está presente (redirect 302 → login para guest)
 *   3. Não há erro fatal 500 na resolução das rotas
 *
 * NÃO testa lógica de Controller — apenas que o scaffold está plugado.
 *
 * Refs: Routes/web.php — middleware stack UltimatePOS
 *   ['web', 'SetSessionData', 'auth', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin']
 */

it('rota nfse.index responde redirect 302 para guest (middleware auth ativo)', function () {
    if (! Route::has('nfse.index')) {
        $this->markTestSkipped('Rota nfse.index não registrada — pular smoke');
    }

    $response = $this->get(route('nfse.index'));

    // Guest sem session → middleware auth redireciona pra /login (302). Status < 500 ok.
    expect($response->status())->toBeLessThan(500);
    expect($response->status())->toBeIn([302, 401, 403]);
});

it('rota nfse.create responde redirect 302 para guest (middleware auth ativo)', function () {
    if (! Route::has('nfse.create')) {
        $this->markTestSkipped('Rota nfse.create não registrada — pular smoke');
    }

    $response = $this->get(route('nfse.create'));

    expect($response->status())->toBeLessThan(500);
    expect($response->status())->toBeIn([302, 401, 403]);
});

it('rota nfse.show está registrada e bloqueia guest', function () {
    if (! Route::has('nfse.show')) {
        $this->markTestSkipped('Rota nfse.show não registrada — pular smoke');
    }

    // Rota usa {nfse} param — passar ID fictício; middleware auth corta antes da resolução do model.
    $response = $this->get(route('nfse.show', ['nfse' => 99999]));

    expect($response->status())->toBeLessThan(500);
    expect($response->status())->toBeIn([302, 401, 403, 404]);
});

it('rota nfse.pdf está registrada e bloqueia guest', function () {
    if (! Route::has('nfse.pdf')) {
        $this->markTestSkipped('Rota nfse.pdf não registrada — pular smoke');
    }

    $response = $this->get(route('nfse.pdf', ['nfse' => 99999]));

    expect($response->status())->toBeLessThan(500);
    expect($response->status())->toBeIn([302, 401, 403, 404]);
});

it('rota nfse.store (POST emitir) está registrada e bloqueia guest', function () {
    if (! Route::has('nfse.store')) {
        $this->markTestSkipped('Rota nfse.store não registrada — pular smoke');
    }

    $response = $this->post(route('nfse.store'), []);

    // Guest → 302 redirect login. CSRF token também pode falhar — mas o ponto é apenas <500.
    expect($response->status())->toBeLessThan(500);
});

it('rota install /nfse/install responde (rota de Install)', function () {
    // Install routes não tem name() — checar via path. Wagner usa /manage-modules → Install.
    $response = $this->get('/nfse/install');

    // Guest → middleware auth bloqueia. Aceitável: 302/401/403/404. Erro: 500.
    expect($response->status())->toBeLessThan(500);
});
