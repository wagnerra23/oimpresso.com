<?php

namespace App\Services\Menu;

use Closure;
use Countable;
use Illuminate\Contracts\Config\Repository;
use Illuminate\View\Factory as ViewFactory;

/**
 * Menu — manager singleton. Drop-in replacement do Nwidart\Menus\Menu.
 *
 * Registrado no container como 'menus' via MenuServiceProvider; a Facade
 * App\Facades\Menu expõe com mesmo alias global 'Menu' usado pelo código
 * legado (AdminSidebarMenu middleware + DataController de cada módulo).
 */
class Menu implements Countable
{
    /** @var MenuBuilder[] */
    protected array $menus = [];

    public function __construct(protected ViewFactory $views, protected Repository $config)
    {
    }

    public function make(string $name, Closure $callback): MenuBuilder
    {
        return $this->create($name, $callback);
    }

    public function create(string $name, Closure $resolver): MenuBuilder
    {
        $builder = new MenuBuilder($name, $this->config);
        $builder->setViewFactory($this->views);
        $this->menus[$name] = $builder;
        $resolver($builder);
        return $builder;
    }

    public function has(string $name): bool
    {
        return array_key_exists($name, $this->menus);
    }

    public function instance(string $name): ?MenuBuilder
    {
        return $this->has($name) ? $this->menus[$name] : null;
    }

    public function modify(string $name, Closure $callback): void
    {
        $menu = $this->instance($name);
        if ($menu !== null) {
            $callback($menu);
        }
    }

    public function get(string $name, ?string $presenter = null, array $bindings = []): ?string
    {
        return $this->has($name)
            ? $this->menus[$name]->setBindings($bindings)->render($presenter)
            : null;
    }

    public function render(string $name, ?string $presenter = null, array $bindings = []): ?string
    {
        return $this->get($name, $presenter, $bindings);
    }

    /** @return MenuBuilder[] */
    public function all(): array
    {
        return $this->menus;
    }

    public function count(): int
    {
        return count($this->menus);
    }

    public function destroy(): void
    {
        $this->menus = [];
    }
}
