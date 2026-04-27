<?php

/**
 * Modules\Superadmin — middleware 'superadmin' (App\Http\Middleware\Superadmin).
 *
 * Regra: só usernames listados em config('constants.administrator_usernames')
 * (env ADMINISTRATOR_USERNAMES, default WR23) acessam /superadmin/*.
 * Resto recebe 403.
 */

use App\User;

beforeEach(function () {
    config(['constants.administrator_usernames' => 'WR23']);
});

it('aplica middleware superadmin no grupo /superadmin', function () {
    $middleware = routeMiddleware('superadmin', 'GET');
    expect($middleware)->toContain('superadmin')
        ->and($middleware)->toContain('auth')
        ->and($middleware)->toContain('AdminSidebarMenu');
});

it('redireciona convidado para login ao tentar acessar /superadmin', function () {
    $response = $this->get('/superadmin');
    expect($response->status())->toBeIn([302, 401]);
});

it('bloqueia (403) usuário comum autenticado fora da allowlist', function () {
    $user = $this->makeUser([
        'username' => 'usuario_comum_' . uniqid(),
    ]);

    $response = $this->actingAs($user)->get('/superadmin');
    $response->assertStatus(403);
});

it('libera usuário cujo username está em ADMINISTRATOR_USERNAMES', function () {
    $admin = $this->makeUser(['username' => 'WR23']);

    $response = $this->actingAs($admin)
        ->withSession(['user' => ['business_id' => $admin->business_id]])
        ->get('/superadmin');

    expect($response->status())->not->toBe(403);
});

it('expõe rotas redesenhadas de pricing/business/packages', function () {
    expect(routeExists('pricing', 'GET'))->toBeTrue()
        ->and(routeExists('superadmin/business', 'GET'))->toBeTrue()
        ->and(routeExists('superadmin/packages', 'GET'))->toBeTrue()
        ->and(routeExists('superadmin/communicator', 'GET'))->toBeTrue();
});

it('mantém rotas externas portainer/painel atrás do superadmin guard', function () {
    foreach (['superadmin.portainer', 'superadmin.painel'] as $name) {
        $route = moduleRoute($name);
        expect($route)->not->toBeNull();
        expect($route->gatherMiddleware())->toContain('superadmin');
    }
});
