<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

uses(Tests\TestCase::class);

/**
 * Smoke routes — valida que as rotas principais do módulo Essentials estão
 * registradas e protegidas por auth.
 *
 * NÃO usa banco — só roda contra container de rotas. Detecta regressão silenciosa
 * tipo: dev derruba `Route::resource('todo', ...)` sem perceber.
 *
 * Roda em qualquer driver (não requer MySQL).
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/requisitos/Essentials/SPEC.md
 */

it('rota /essentials/dashboard está registrada', function () {
    expect(Route::has('essentials.dashboard') || routeExists('essentials/dashboard', 'GET'))->toBeTrue();
});

it('rota /essentials/todo (resource) está registrada', function () {
    expect(routeExists('essentials/todo', 'GET'))->toBeTrue();
    expect(routeExists('essentials/todo', 'POST'))->toBeTrue();
});

it('rota /essentials/todo/{todo}/edit (resource) está registrada', function () {
    expect(routeExists('essentials/todo/{todo}/edit', 'GET'))->toBeTrue();
});

it('rota /essentials/todo/add-comment está registrada', function () {
    expect(routeExists('essentials/todo/add-comment', 'POST'))->toBeTrue();
});

it('rota /essentials/document (resource) está registrada', function () {
    expect(routeExists('essentials/document', 'GET'))->toBeTrue();
    expect(routeExists('essentials/document', 'POST'))->toBeTrue();
});

it('rota /essentials/reminder (resource) está registrada', function () {
    expect(routeExists('essentials/reminder', 'GET'))->toBeTrue();
});

it('rota /essentials/install está registrada', function () {
    expect(routeExists('essentials/install', 'GET'))->toBeTrue();
});

it('rota /essentials/install/update está registrada', function () {
    expect(routeExists('essentials/install/update', 'GET'))->toBeTrue();
});

it('rota /essentials/install/uninstall está registrada', function () {
    expect(routeExists('essentials/install/uninstall', 'GET'))->toBeTrue();
});

it('GET /essentials/todo sem auth redireciona pra /login', function () {
    $response = $this->get('/essentials/todo');
    $response->assertRedirect('/login');
});

it('GET /essentials/dashboard sem auth redireciona pra /login', function () {
    $response = $this->get('/essentials/dashboard');
    $response->assertRedirect('/login');
});

// ------------------------------------------------------------------
// Helper local — não polui ServiceProvider
// ------------------------------------------------------------------

function routeExists(string $uri, string $method): bool
{
    foreach (Route::getRoutes() as $route) {
        if ($route->uri() === $uri && in_array($method, $route->methods(), true)) {
            return true;
        }
    }
    return false;
}
