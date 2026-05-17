import * as React from 'react';
import { Link } from '@inertiajs/react';
import { cn } from '@/Lib/utils';

/**
 * PageNavTabs — chips numerados de navegação intra-módulo dentro do PageHeader.
 *
 * Pattern Wagner aprovou 2026-05-17 (protótipo Cowork financeiro-app.jsx):
 *   [1] Lançamentos [27]   [2] Conciliação [18]   [3] Fluxo de caixa   [4] DRE
 *
 * - `hotkey` numerado opcional (1-9) — só visual por enquanto, dispatcher de
 *   atalho fica a cargo da página se quiser (event listener Key1..Key9).
 * - `count` opcional — badge à direita do label (pendentes, abertos, etc).
 * - `active` controla destaque visual; `href` faz o link.
 *
 * Usado como `nav` slot do PageHeader.
 */
export interface PageNavTabItem {
  label: string;
  href: string;
  count?: number;
  hotkey?: number;
  active?: boolean;
}

interface Props {
  items: PageNavTabItem[];
  className?: string;
}

export default function PageNavTabs({ items, className }: Props) {
  return (
    <nav
      aria-label="Navegação da página"
      data-slot="page-nav-tabs"
      className={cn(
        'inline-flex flex-wrap items-center gap-1 rounded-md border border-border bg-muted/40 p-0.5',
        className,
      )}
    >
      {items.map((item, idx) => (
        <Link
          key={`${item.href}-${idx}`}
          href={item.href}
          className={cn(
            'group inline-flex items-center gap-1.5 rounded-[5px] px-2.5 py-1 text-[12.5px] transition-colors',
            item.active
              ? 'bg-background text-foreground font-medium shadow-sm'
              : 'text-muted-foreground hover:text-foreground hover:bg-background/50',
          )}
        >
          {item.hotkey !== undefined && (
            <kbd
              aria-hidden
              className={cn(
                'inline-flex h-4 min-w-[16px] items-center justify-center rounded border px-1 text-[10px] font-mono font-medium leading-none',
                item.active
                  ? 'border-foreground/20 bg-foreground text-background'
                  : 'border-border bg-background text-muted-foreground group-hover:text-foreground',
              )}
            >
              {item.hotkey}
            </kbd>
          )}
          <span>{item.label}</span>
          {item.count !== undefined && item.count > 0 && (
            <span
              className={cn(
                'inline-flex h-4 min-w-[18px] items-center justify-center rounded-full px-1 text-[10px] font-medium tabular-nums',
                item.active
                  ? 'bg-primary/10 text-primary'
                  : 'bg-muted text-muted-foreground',
              )}
            >
              {item.count}
            </span>
          )}
        </Link>
      ))}
    </nav>
  );
}
