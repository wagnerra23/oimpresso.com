<?php

/*
|--------------------------------------------------------------------------
| Pest harness — Modules legados (batch 7)
|--------------------------------------------------------------------------
|
| Este arquivo configura Pest para os testes de regressão dos módulos
| legados (Repair, Help, Officeimpresso, Superadmin, Woocommerce).
|
| Pré-requisitos para rodar:
|   composer require --dev pestphp/pest pestphp/pest-plugin-laravel
|
| Execução por módulo:
|   vendor/bin/pest --filter=Repair
|   vendor/bin/pest --filter=Help
|   vendor/bin/pest --filter=Officeimpresso
|   vendor/bin/pest --filter=Superadmin
|   vendor/bin/pest --filter=Woocommerce
*/

uses(Tests\TestCase::class)
    ->in(__DIR__ . '/Feature/Modules');

uses(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in(__DIR__ . '/Feature/Modules');

/*
|--------------------------------------------------------------------------
| Helpers compartilhados
|--------------------------------------------------------------------------
*/

function moduleRoute(string $name): ?\Illuminate\Routing\Route
{
    return app('router')->getRoutes()->getByName($name);
}

function routeExists(string $uri, string $method = 'GET'): bool
{
    foreach (app('router')->getRoutes() as $route) {
        if ($route->uri() === ltrim($uri, '/') && in_array(strtoupper($method), $route->methods(), true)) {
            return true;
        }
    }
    return false;
}

function routeMiddleware(string $uri, string $method = 'GET'): array
{
    foreach (app('router')->getRoutes() as $route) {
        if ($route->uri() === ltrim($uri, '/') && in_array(strtoupper($method), $route->methods(), true)) {
            return $route->gatherMiddleware();
        }
    }
    return [];
}
