import * as React from 'react';
import { Link } from '@inertiajs/react';
import { MoreHorizontal } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import { SIDEBAR_GROUP_HUE } from '@/Components/cockpit/shared';
import { cn } from '@/Lib/utils';

/**
 * PageHeaderTabs — slot action canônico do PageHeader (ADR 0180, 2026-05-21).
 *
 * Layout:
 *   [+ Novo X]  Unificado · Pagar · Receber · Caixa · ⋯ Mais
 *    ▲ primary  ▲ ghost tabs (ARIA tablist)
 *
 * Componentização:
 *   - `primary` → botão colorido com hue OKLCH do grupo (atalho kbd opcional N)
 *   - `ghosts` → tabs ARIA tablist (role=tab/tabpanel, keyboard nav)
 *   - overflow `⋯ Mais` se ghosts.length > maxVisible (default 5)
 *
 * Casa com PageHeader shared via slot `action`. NÃO substitui PageHeaderActions
 * (que é pra ações iguais de mesmo peso) — uso diferente: primary action +
 * tab navigation.
 *
 * Espelha contrato PHP da Fase 1 (App\Sidebar\SidebarMenuItem.primary/ghosts).
 *
 * Uso:
 *   <PageHeader
 *     title="Financeiro"
 *     icon="dollar-sign"
 *     action={
 *       <PageHeaderTabs
 *         primary={{ label: 'Novo título', href: '/financeiro/create', shortcut: 'N' }}
 *         ghosts={[
 *           { key: 'unificado', label: 'Unificado', href: '/financeiro?tab=unificado' },
 *           { key: 'pagar', label: 'Pagar', href: '/financeiro?tab=pagar' },
 *           { key: 'receber', label: 'Receber', href: '/financeiro?tab=receber' },
 *         ]}
 *         activeGhostKey="unificado"
 *         group="financas"
 *       />
 *     }
 *   />
 */
export interface PageHeaderPrimary {
  label: string;
  href: string;
  /** Atalho kbd canon (ex 'N'). Apenas display — listener global em Fase 8. */
  shortcut?: string;
}

export interface PageHeaderGhost {
  /** kebab-case (espelha App\Sidebar\SidebarGhost). */
  key: string;
  label: string;
  href: string;
}

interface Props {
  primary?: PageHeaderPrimary;
  ghosts?: PageHeaderGhost[];
  activeGhostKey?: string;
  /** Key do grupo do SIDEBAR_GROUP_HUE — define hue OKLCH do primary + ghost ativo. */
  group?: string;
  /** Callback opcional quando ghost é clicado. Default: navegação Inertia via href. */
  onGhostChange?: (key: string) => void;
  /** Quantos ghosts visíveis inline antes do overflow ⋯ Mais. Default 5. */
  maxVisible?: number;
  className?: string;
}

export default function PageHeaderTabs({
  primary,
  ghosts = [],
  activeGhostKey,
  group,
  onGhostChange,
  maxVisible = 5,
  className,
}: Props) {
  const hue = group ? SIDEBAR_GROUP_HUE[group] : undefined;
  const hueStyle = hue !== undefined ? ({ '--gh': hue } as React.CSSProperties) : undefined;

  const visibleGhosts = ghosts.slice(0, maxVisible);
  const overflowGhosts = ghosts.slice(maxVisible);

  // ── Keyboard nav (left/right/home/end) ──────────────────────────────
  const tablistRef = React.useRef<HTMLDivElement>(null);
  const onKeyDownTab = (e: React.KeyboardEvent<HTMLAnchorElement>, idx: number) => {
    const tabs = tablistRef.current?.querySelectorAll<HTMLAnchorElement>('[role="tab"]');
    if (!tabs?.length) return;
    let next = idx;
    switch (e.key) {
      case 'ArrowLeft':  next = (idx - 1 + tabs.length) % tabs.length; break;
      case 'ArrowRight': next = (idx + 1) % tabs.length; break;
      case 'Home':       next = 0; break;
      case 'End':        next = tabs.length - 1; break;
      default: return;
    }
    e.preventDefault();
    tabs[next].focus();
  };

  return (
    <div
      className={cn('flex items-center gap-2 flex-wrap md:flex-nowrap', className)}
      style={hueStyle}
    >
      {/* ── Primary: + Novo X colorido com hue OKLCH do grupo ── */}
      {primary && (
        <Button
          asChild
          size="sm"
          style={
            hue !== undefined
              ? {
                  backgroundColor: `oklch(0.6 0.15 ${hue})`,
                  color: 'oklch(0.99 0 0)',
                }
              : undefined
          }
          className="font-medium shrink-0"
        >
          <Link href={primary.href}>
            <span>+ {primary.label}</span>
            {primary.shortcut && (
              <kbd className="ml-2 px-1.5 py-0.5 text-[10px] font-mono rounded bg-black/20 border border-white/20">
                {primary.shortcut}
              </kbd>
            )}
          </Link>
        </Button>
      )}

      {/* ── Separador vertical entre primary e ghosts ── */}
      {primary && visibleGhosts.length > 0 && (
        <div className="hidden md:block w-px h-6 bg-border shrink-0" aria-hidden />
      )}

      {/* ── Ghost tabs ARIA tablist ── */}
      {visibleGhosts.length > 0 && (
        <div
          ref={tablistRef}
          role="tablist"
          aria-label="Visão da página"
          className={cn(
            'flex items-center gap-0.5 min-w-0',
            // Mobile: scroll-x snap quando ghosts não cabem (ADR 0180 mobile-aware)
            'overflow-x-auto md:overflow-visible',
            'snap-x snap-mandatory md:snap-none',
            '[scrollbar-width:none] [&::-webkit-scrollbar]:hidden',
          )}
        >
          {visibleGhosts.map((ghost, idx) => {
            const isActive = ghost.key === activeGhostKey;
            return (
              <Link
                key={ghost.key}
                href={ghost.href}
                role="tab"
                aria-selected={isActive}
                tabIndex={isActive ? 0 : -1}
                onKeyDown={(e) => onKeyDownTab(e, idx)}
                onClick={(e) => {
                  if (onGhostChange) {
                    e.preventDefault();
                    onGhostChange(ghost.key);
                  }
                }}
                className={cn(
                  'px-3 py-1.5 text-sm whitespace-nowrap snap-start rounded-md',
                  'transition-colors border-b-2 border-transparent',
                  'hover:bg-accent hover:text-accent-foreground',
                  'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',
                  isActive
                    ? [
                        'text-foreground font-medium',
                        hue !== undefined
                          ? `border-b-[oklch(0.55_0.15_var(--gh))]`
                          : 'border-b-primary',
                      ]
                    : 'text-muted-foreground',
                )}
                style={
                  isActive && hue !== undefined
                    ? { borderBottomColor: `oklch(0.55 0.15 ${hue})` }
                    : undefined
                }
              >
                {ghost.label}
              </Link>
            );
          })}

          {/* ── Overflow ⋯ Mais ── */}
          {overflowGhosts.length > 0 && (
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <button
                  type="button"
                  className={cn(
                    'px-2 py-1.5 text-sm text-muted-foreground rounded-md',
                    'hover:bg-accent hover:text-accent-foreground shrink-0',
                    'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',
                  )}
                  aria-label={`Mais ${overflowGhosts.length} visões`}
                >
                  <MoreHorizontal size={16} />
                </button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end" className="min-w-40">
                {overflowGhosts.map((ghost) => (
                  <DropdownMenuItem key={ghost.key} asChild>
                    <Link href={ghost.href}>{ghost.label}</Link>
                  </DropdownMenuItem>
                ))}
              </DropdownMenuContent>
            </DropdownMenu>
          )}
        </div>
      )}
    </div>
  );
}
