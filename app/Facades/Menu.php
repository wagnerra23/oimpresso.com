<?php

namespace App\Facades;

use Closure;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \App\Services\Menu\MenuBuilder make(string $name, Closure $callback)
 * @method static \App\Services\Menu\MenuBuilder create(string $name, Closure $resolver)
 * @method static bool has(string $name)
 * @method static \App\Services\Menu\MenuBuilder|null instance(string $name)
 * @method static void modify(string $name, Closure $callback)
 * @method static string|null get(string $name, string $presenter = null, array $bindings = [])
 * @method static string|null render(string $name, string $presenter = null, array $bindings = [])
 * @method static array all()
 * @method static int count()
 * @method static void destroy()
 *
 * @see \App\Services\Menu\Menu
 */
class Menu extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'menus';
    }
}
