<?php

namespace App\Services\Menu;

use Closure;
use Collective\Html\HtmlFacade as HTML;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Request;

/**
 * MenuItem — drop-in replacement do Nwidart\Menus\MenuItem.
 *
 * Reimplementação enxuta mantendo a MESMA API consumida pelo UltimatePOS
 * (propriedades públicas dinâmicas + métodos usados por LegacyMenuAdapter e
 * pelo AdminlteCustomPresenter). Substitui nwidart/laravel-menus que não
 * tem fork mantido pra Laravel 10+.
 *
 * @property string      $url
 * @property array|null  $route
 * @property string      $title
 * @property string      $name
 * @property string      $icon
 * @property int         $parent
 * @property array       $attributes
 * @property bool        $active
 * @property int         $order
 */
class MenuItem implements Arrayable
{
    protected array $properties = [];
    protected array $childs = [];
    protected ?Closure $hideWhen = null;

    protected array $fillable = [
        'url', 'route', 'title', 'name', 'icon', 'parent',
        'attributes', 'active', 'order', 'hideWhen',
    ];

    public function __construct(array $properties = [])
    {
        $this->properties = $properties;
        $this->fill($properties);
    }

    public static function make(array $properties): self
    {
        // Move attributes.icon para icon no topo (compat com nwidart)
        $icon = Arr::get($properties, 'attributes.icon');
        if (!is_null($icon)) {
            $properties['icon'] = $icon;
            Arr::forget($properties, 'attributes.icon');
        }
        return new self($properties);
    }

    public function fill(array $attributes): void
    {
        foreach ($attributes as $key => $value) {
            if (in_array($key, $this->fillable, true)) {
                $this->{$key} = $value;
            }
        }
    }

    // ---------- child builders (mesma API que MenuBuilder) ----------

    public function url($url, $title, $order = 0, array $attributes = []): self
    {
        if (func_num_args() === 3) {
            $args = func_get_args();
            return $this->add([
                'url'        => Arr::get($args, 0),
                'title'      => Arr::get($args, 1),
                'attributes' => Arr::get($args, 2),
            ]);
        }
        return $this->add(compact('url', 'title', 'order', 'attributes'));
    }

    public function route($route, $title, $parameters = [], $order = 0, array $attributes = []): self
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

    public function dropdown($title, Closure $callback, $order = 0, array $attributes = []): self
    {
        $properties = compact('title', 'order', 'attributes');
        if (func_num_args() === 3) {
            $args = func_get_args();
            $title = Arr::get($args, 0);
            $attributes = Arr::get($args, 2);
            $properties = compact('title', 'attributes');
        }
        $child = self::make($properties);
        $callback($child);
        $this->childs[] = $child;
        return $child;
    }

    public function add(array $properties): self
    {
        $item = self::make($properties);
        $this->childs[] = $item;
        return $item;
    }

    public function addDivider($order = null): self
    {
        $item = self::make(['name' => 'divider', 'order' => $order]);
        $this->childs[] = $item;
        return $item;
    }

    public function divider($order = null): self
    {
        return $this->addDivider($order);
    }

    public function addHeader($title): self
    {
        $item = self::make(['name' => 'header', 'title' => $title]);
        $this->childs[] = $item;
        return $item;
    }

    public function header($title): self
    {
        return $this->addHeader($title);
    }

    // ---------- getters usados por presenter + LegacyMenuAdapter ----------

    public function getChilds(): array
    {
        if (config('menus.ordering')) {
            return collect($this->childs)->sortBy('order')->all();
        }
        return $this->childs;
    }

    public function getUrl(): string
    {
        if ($this->route !== null) {
            return route($this->route[0], $this->route[1]);
        }
        if (empty($this->url)) {
            return url('/#');
        }
        return url($this->url);
    }

    public function getRequest(): string
    {
        return ltrim(str_replace(url('/'), '', $this->getUrl()), '/');
    }

    public function getIcon($default = null): ?string
    {
        if ($this->icon !== null && $this->icon !== '') {
            return '<i class="' . $this->icon . '"></i>';
        }
        if ($default === null) {
            return $default;
        }
        return '<i class="' . $default . '"></i>';
    }

    public function getProperties(): array
    {
        return $this->properties;
    }

    public function getAttributes(): string
    {
        $attributes = $this->attributes ?: [];
        Arr::forget($attributes, ['active', 'icon']);
        return HTML::attributes($attributes);
    }

    // ---------- type checks ----------

    public function isDivider(): bool { return $this->is('divider'); }
    public function isHeader(): bool  { return $this->is('header');  }
    public function is($name): bool   { return $this->name === $name; }
    public function hasSubMenu(): bool { return !empty($this->childs); }
    public function hasChilds(): bool  { return $this->hasSubMenu(); }

    // ---------- active state ----------

    public function hasActiveOnChild(): bool
    {
        if ($this->inactive()) {
            return false;
        }
        return $this->hasChilds() ? $this->getActiveStateFromChilds() : false;
    }

    public function getActiveStateFromChilds(): bool
    {
        foreach ($this->getChilds() as $child) {
            if ($child->inactive()) {
                continue;
            }
            if ($child->hasChilds()) {
                if ($child->getActiveStateFromChilds()) {
                    return true;
                }
            } elseif ($child->isActive()) {
                return true;
            } elseif ($child->hasRoute() && $child->getActiveStateFromRoute()) {
                return true;
            } elseif ($child->getActiveStateFromUrl()) {
                return true;
            }
        }
        return false;
    }

    public function inactive(): bool
    {
        $inactive = $this->getInactiveAttribute();
        if (is_bool($inactive)) {
            return $inactive;
        }
        if ($inactive instanceof Closure) {
            return (bool) $inactive();
        }
        return false;
    }

    public function getActiveAttribute()   { return Arr::get($this->attributes, 'active'); }
    public function getInactiveAttribute() { return Arr::get($this->attributes, 'inactive'); }

    public function isActive(): bool
    {
        if ($this->inactive()) {
            return false;
        }
        $active = $this->getActiveAttribute();
        if (is_bool($active)) {
            return $active;
        }
        if ($active instanceof Closure) {
            return (bool) $active();
        }
        if ($this->hasRoute()) {
            return $this->getActiveStateFromRoute();
        }
        return $this->getActiveStateFromUrl();
    }

    protected function hasRoute(): bool { return !empty($this->route); }

    protected function getActiveStateFromRoute(): bool
    {
        return Request::is(str_replace(url('/') . '/', '', $this->getUrl()));
    }

    protected function getActiveStateFromUrl(): bool
    {
        return Request::is($this->url);
    }

    // ---------- order / hide ----------

    public function order($order): self
    {
        $this->order = $order;
        return $this;
    }

    public function hideWhen(Closure $callback): self
    {
        $this->hideWhen = $callback;
        return $this;
    }

    public function hidden(): bool
    {
        if (is_null($this->hideWhen)) {
            return false;
        }
        return (bool) ($this->hideWhen)();
    }

    public function toArray(): array
    {
        return $this->getProperties();
    }

    public function __get($key)
    {
        return $this->{$key} ?? null;
    }
}
