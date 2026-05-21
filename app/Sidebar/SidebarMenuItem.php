<?php

declare(strict_types=1);

namespace App\Sidebar;

use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Sidebar v3 — Item canônico declarado por DataController de módulo.
 *
 * ADR 0180 (2026-05-21) define este contrato. Substitui o array bruto
 * que `Modules/<X>/Http/Controllers/DataController::modifyAdminMenu()`
 * passa hoje pra `Menu::modify('admin-sidebar-menu', ...)`.
 *
 * Wagner regra 2026-05-19: backend declara, frontend (Sidebar.tsx) não
 * hardcode labels nem grupos.
 *
 * Multi-tenant Tier 0 (ADR 0093): DataController deve filtrar por
 * `business_id` ANTES de construir este item — não é responsabilidade
 * deste DTO checar tenant.
 *
 * Exemplo:
 *
 * ```php
 * new SidebarMenuItem(
 *     label:    'Financeiro',
 *     href:     route('financeiro.index'),
 *     group:    SidebarGroup::Financas,
 *     icon:     'currency-dollar',
 *     shortcut: 'G F',
 *     primary:  new SidebarPrimaryAction('Novo título', route('financeiro.create'), 'N'),
 *     ghosts:   [
 *         new SidebarGhost('unificado',   'Unificado',       '/financeiro?tab=unificado'),
 *         new SidebarGhost('pagar',       'Pagar',           '/financeiro?tab=pagar'),
 *         new SidebarGhost('receber',     'Receber',         '/financeiro?tab=receber'),
 *     ],
 * );
 * ```
 */
final readonly class SidebarMenuItem
{
    /** @var Collection<int, SidebarGhost> */
    public Collection $ghosts;

    /**
     * @param  iterable<SidebarGhost>  $ghosts
     */
    public function __construct(
        public string                 $label,
        public string                 $href,
        public SidebarGroup           $group,
        public ?string                $icon = null,
        public ?string                $shortcut = null,
        public ?SidebarPrimaryAction  $primary = null,
        iterable                      $ghosts = [],
    ) {
        if (trim($label) === '') {
            throw new InvalidArgumentException('SidebarMenuItem: label cannot be empty');
        }

        if (! self::isValidHref($href)) {
            throw new InvalidArgumentException(
                "SidebarMenuItem: href must be URL or absolute path, got: {$href}"
            );
        }

        // Atalho kbd canon "G X" ou "G X Y" — sequência inicia em G + 1 ou 2 letras.
        if ($shortcut !== null && ! preg_match('/^G [A-Z]( [A-Z])?$/', $shortcut)) {
            throw new InvalidArgumentException(
                "SidebarMenuItem: shortcut must match 'G X' or 'G X Y' pattern, got: {$shortcut}"
            );
        }

        $this->ghosts = collect($ghosts)
            ->each(static function ($g): void {
                if (! $g instanceof SidebarGhost) {
                    throw new InvalidArgumentException(
                        'SidebarMenuItem: ghosts must be SidebarGhost instances, got: '
                        . get_debug_type($g)
                    );
                }
            })
            ->values();
    }

    /**
     * Serialização canônica enviada ao frontend via Inertia shared props
     * (ShellMenuItem em resources/js/Components/cockpit/shared.ts).
     */
    public function toArray(): array
    {
        return array_filter([
            'label'    => $this->label,
            'href'     => $this->href,
            'group'    => $this->group->value,
            'icon'     => $this->icon,
            'shortcut' => $this->shortcut,
            'primary'  => $this->primary?->toArray(),
            'ghosts'   => $this->ghosts->isNotEmpty()
                ? $this->ghosts->map(static fn (SidebarGhost $g) => $g->toArray())->all()
                : null,
        ], static fn ($v) => $v !== null);
    }

    private static function isValidHref(string $href): bool
    {
        return str_starts_with($href, '/')
            || filter_var($href, FILTER_VALIDATE_URL) !== false;
    }
}
