<?php

namespace App\Providers;

use App\Services\Menu\Menu;
use Illuminate\Support\ServiceProvider;

/**
 * Registra o binding 'menus' usado pela Facade App\Facades\Menu.
 *
 * Substitui Nwidart\Menus\MenusServiceProvider (lib abandonada,
 * incompatível com Laravel 10+). A API pública permanece idêntica.
 */
class MenuServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('menus', function ($app) {
            return new Menu($app['view'], $app['config']);
        });
    }
}
