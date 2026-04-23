<?php

namespace App\Services\Menu;

use Closure;
use Countable;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Arr;
use Illuminate\View\Factory as ViewFactory;

/**
 * MenuBuilder — drop-in replacement do Nwidart\Menus\MenuBuilder.
 *
 * Armazena items de um menu nomeado e os renderiza via Presenter. API
 * idêntica à lib original nas chamadas usadas pelo UltimatePOS.
 */
class MenuBuilder implements Countable
{
    protected string $menu;
    protected array $items = [];
    protected ?string $presenter = null;
    protected array $bindings = [];
    protected ?ViewFactory $views = null;
    protected bool $ordering = false;
    protected ?string $view = null;
    protected ?string $prefixUrl = null;

    public function __construct(string $menu, protected Repository $config)
    {
        $this->menu = $menu;
    }

    public function getName(): string
    {
        return $this->menu;
    }

    public function setViewFactory(ViewFactory $views): self
    {
        $this->views = $views;
        return $this;
    }

    public function setPrefixUrl(string $prefixUrl): self
    {
        $this->prefixUrl = $prefixUrl;
        return $this;
    }

    public function setPresenter(string $presenter): void
    {
        $this->presenter = $presenter;
    }

    public function setBindings(array $bindings): self
    {
        $this->bindings = $bindings;
        return $this;
    }

    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * Encontra MenuItem top-level por propriedade. Usado pelos
     * DataControllers que injetam sub-itens em menus já existentes
     * (ex.: Superadmin/Crm adicionam entradas sob "Settings").
     */
    public function findBy(string $key, $value): ?MenuItem
    {
        return collect($this->items)->first(fn (MenuItem $i) => $i->{$key} == $value);
    }

    /**
     * Encontra item por título e passa pro callback pra estender.
     * Compat com Nwidart\Menus\MenuBuilder::whereTitle.
     */
    public function whereTitle(string $title, ?callable $callback = null)
    {
        $item = $this->findBy('title', $title);
        if (is_callable($callback)) {
            return $callback($item);
        }
        return $item;
    }

    public function toCollection()
    {
        return collect($this->items);
    }

    public function toArray(): array
    {
        return $this->toCollection()->toArray();
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function destroy(): self
    {
        $this->items = [];
        return $this;
    }

    public function enableOrdering(): self  { $this->ordering = true;  return $this; }
    public function disableOrdering(): self { $this->ordering = false; return $this; }

    // ---------- item creation ----------

    public function add(array $attributes = []): MenuItem
    {
        $item = MenuItem::make($attributes);
        $this->items[] = $item;
        return $item;
    }

    public function url($url, $title, $order = 0, array $attributes = []): MenuItem
    {
        if (func_num_args() === 3) {
            $args = func_get_args();
            return $this->add([
                'url'        => $this->formatUrl(Arr::get($args, 0)),
                'title'      => Arr::get($args, 1),
                'attributes' => Arr::get($args, 2),
            ]);
        }
        $url = $this->formatUrl($url);
        return $this->add(compact('url', 'title', 'order', 'attributes'));
    }

    public function route($route, $title, $parameters = [], $order = 0, array $attributes = []): MenuItem
    {
        if (func_num_args() === 4) {
            $args = func_get_args();
            return $this->add([
                'route'      => [Arr::get($args, 0), Arr::get($args, 2)],
                'title'      => Arr::get($args, 1),
                'attributes' => Arr::get($args, 3),
            ]);
        }
        $route = [$route, $parameters];
        return $this->add(compact('route', 'title', 'order', 'attributes'));
    }

    public function dropdown(string $title, Closure $callback, $order = null, array $attributes = []): MenuItem
    {
        $properties = compact('title', 'order', 'attributes');
        if (func_num_args() === 3) {
            $args = func_get_args();
            $title = Arr::get($args, 0);
            $attributes = Arr::get($args, 2);
            $properties = compact('title', 'attributes');
        }
        $item = MenuItem::make($properties);
        $callback($item);
        $this->items[] = $item;
        return $item;
    }

    public function addDivider($order = null): self
    {
        $this->items[] = new MenuItem(['name' => 'divider', 'order' => $order]);
        return $this;
    }

    public function divider(): self
    {
        return $this->addDivider();
    }

    public function addHeader(string $title, $order = null): self
    {
        $this->items[] = new MenuItem(['name' => 'header', 'title' => $title, 'order' => $order]);
        return $this;
    }

    public function header(string $title): self
    {
        return $this->addHeader($title);
    }

    // ---------- render ----------

    public function getOrderedItems(): array
    {
        if (config('menus.ordering') || $this->ordering) {
            return $this->toCollection()->sortBy(fn ($i) => $i->order)->all();
        }
        return $this->items;
    }

    public function render($presenter = null): string
    {
        $this->resolveItems($this->items);

        if (!is_null($presenter)) {
            // Aceita alias de config/menus.php styles OU classe FQCN direta
            $styles = $this->config->get('menus.styles', []);
            $resolved = $styles[$presenter] ?? $presenter;
            $this->setPresenter($resolved);
        }

        $presenterClass = $this->presenter ?? Presenter::class;
        /** @var Presenter $instance */
        $instance = new $presenterClass();

        $menu = $instance->getOpenTagWrapper();
        foreach ($this->getOrderedItems() as $item) {
            if ($item->hidden()) continue;

            if ($item->hasSubMenu()) {
                $menu .= $instance->getMenuWithDropDownWrapper($item);
            } elseif ($item->isHeader()) {
                $menu .= $instance->getHeaderWrapper($item);
            } elseif ($item->isDivider()) {
                $menu .= $instance->getDividerWrapper();
            } else {
                $menu .= $instance->getMenuWithoutDropdownWrapper($item);
            }
        }
        $menu .= $instance->getCloseTagWrapper();

        return $menu;
    }

    // ---------- helpers ----------

    protected function formatUrl($url): string
    {
        $uri = !is_null($this->prefixUrl) ? $this->prefixUrl . $url : $url;
        return $uri === '/' ? '/' : ltrim(rtrim($uri, '/'), '/');
    }

    protected function resolveItems(array &$items): void
    {
        $resolver = fn ($p) => $this->resolve($p) ?: $p;
        foreach ($items as $item) {
            $item->fill(array_map($resolver, $item->getProperties()));
        }
    }

    protected function resolve($key)
    {
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                $key[$k] = $this->resolve($v);
            }
        } elseif (is_string($key)) {
            preg_match_all('/{[\s]*?([^\s]+)[\s]*?}/i', $key, $matches, PREG_SET_ORDER);
            foreach ($matches as $m) {
                if (array_key_exists($m[1], $this->bindings)) {
                    $key = preg_replace('/' . $m[0] . '/', $this->bindings[$m[1]], $key, 1);
                }
            }
        }
        return $key;
    }
}
