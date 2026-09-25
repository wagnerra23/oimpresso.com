<?php

declare(strict_types=1);

namespace App\Sidebar;

use InvalidArgumentException;

/**
 * Sidebar v3 — ghost tab individual no PageHeader.
 *
 * Renderizado como `<button role="tab">` dentro de `<div role="tablist">`
 * (ADR 0180 — ARIA WCAG). Active state via `aria-selected="true"` +
 * border-bottom 2px com hue do grupo pai (OKLCH var --gh).
 *
 * Default tab por convenção: o ghost com `key === 'unificado'`.
 *
 * `icon` (opcional, thread 14 do playbook da sidebar · [W] 2026-09-25): nome do vocabulário
 * de ícones do protótipo (`data.jsx`: cash, list, plus, chart…). O front resolve pelo mapa
 * `GHOST_ICON_MAP` do `Sidebar.tsx`; nome desconhecido = linha sem ícone. `null` não sai no
 * `toArray()` — o shape de quem não declara ícone fica idêntico ao de antes.
 */
final readonly class SidebarGhost
{
    public function __construct(
        public string $key,
        public string $label,
        public string $href,
        public ?string $icon = null,
    ) {
        if (! preg_match('/^[a-z][a-z0-9-]*$/', $key)) {
            throw new InvalidArgumentException(
                "SidebarGhost: key must be kebab-case (lowercase, starts with letter), got: {$key}"
            );
        }

        if (trim($label) === '') {
            throw new InvalidArgumentException('SidebarGhost: label cannot be empty');
        }

        if ($icon !== null && ! preg_match('/^[a-z][a-z0-9-]*$/', $icon)) {
            throw new InvalidArgumentException(
                "SidebarGhost: icon must be kebab-case (lowercase, starts with letter), got: {$icon}"
            );
        }

        if (! self::isValidHref($href)) {
            throw new InvalidArgumentException(
                "SidebarGhost: href must be URL or absolute path, got: {$href}"
            );
        }
    }

    public function toArray(): array
    {
        $arr = [
            'key'   => $this->key,
            'label' => $this->label,
            'href'  => $this->href,
        ];

        if ($this->icon !== null) {
            $arr['icon'] = $this->icon;
        }

        return $arr;
    }

    private static function isValidHref(string $href): bool
    {
        return str_starts_with($href, '/')
            || filter_var($href, FILTER_VALIDATE_URL) !== false;
    }
}
