<?php

declare(strict_types=1);

namespace App\Sidebar;

use InvalidArgumentException;

/**
 * Sidebar v3 — botão "+ Novo X" colorido do PageHeader.
 *
 * Renderizado pelo `PageHeader` (ADR 0180) à esquerda dos ghost tabs.
 * Hue do botão segue o grupo do MenuItem pai (OKLCH var --gh).
 */
final readonly class SidebarPrimaryAction
{
    public function __construct(
        public string  $label,
        public string  $href,
        public ?string $shortcut = null,
    ) {
        if (trim($label) === '') {
            throw new InvalidArgumentException('SidebarPrimaryAction: label cannot be empty');
        }

        if (! self::isValidHref($href)) {
            throw new InvalidArgumentException(
                "SidebarPrimaryAction: href must be URL or absolute path, got: {$href}"
            );
        }

        if ($shortcut !== null && ! preg_match('/^[A-Z]( [A-Z])*$/', $shortcut)) {
            throw new InvalidArgumentException(
                "SidebarPrimaryAction: shortcut must match 'X' or 'X Y' pattern, got: {$shortcut}"
            );
        }
    }

    public function toArray(): array
    {
        return array_filter([
            'label'    => $this->label,
            'href'     => $this->href,
            'shortcut' => $this->shortcut,
        ], static fn ($v) => $v !== null);
    }

    private static function isValidHref(string $href): bool
    {
        return str_starts_with($href, '/')
            || filter_var($href, FILTER_VALIDATE_URL) !== false;
    }
}
