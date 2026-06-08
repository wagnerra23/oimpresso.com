import * as React from 'react';
import { Link } from '@inertiajs/react';
import { MoreHorizontal } from 'lucide-react';
import { Icon } from '@/Components/Icon';
import { Button } from '@/Components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import { cn } from '@/Lib/utils';

/**
 * PageHeaderActions — barra de botões action do PageHeader com overflow auto.
 *
 * Pattern Wagner aprovou 2026-05-17 (preferiu sobre chips numerados — "mais clean"):
 *   [Dashboard] [Triagem 202] [Reload]      ←  inline lado a lado
 *   [Dashboard] [Triagem] [Reload] [•••]    ←  além de maxVisible vai pra popup
 *
 * Cada item é Button shadcn (variantes: default/outline/ghost/secondary).
 * `href` faz Inertia <Link>. `onClick` faz button comum. `count` opcional vira
 * badge à direita do label. `active` vira `variant=default` (preenchido).
 *
 * Overflow simples por `maxVisible` (default 4). Itens além disso entram no
 * DropdownMenu acionado pelo botão "•••".
 *
 * Usado como `action` slot do PageHeader.
 */
export type PageActionVariant = 'default' | 'outline' | 'ghost' | 'secondary';

export interface PageActionItem {
  label: string;
  icon?: string;
  href?: string;
  onClick?: () => void;
  variant?: PageActionVariant;
  count?: number;
  active?: boolean;
  /** Esconde do overflow popup quando não cabe inline. Default: false. */
  pinned?: boolean;
}

interface Props {
  items: PageActionItem[];
  maxVisible?: number;
  className?: string;
}

export default function PageHeaderActions({ items, maxVisible = 4, className }: Props) {
  const visible: PageActionItem[] = [];
  const overflow: PageActionItem[] = [];
  let inlineUsed = 0;
  for (const it of items) {
    if (it.pinned || inlineUsed < maxVisible) {
      visible.push(it);
      inlineUsed++;
    } else {
      overflow.push(it);
    }
  }

  return (
    <div className={cn('flex flex-wrap items-center gap-1.5', className)}>
      {visible.map((it, i) => (
        <ActionButton key={`${it.label}-${i}`} item={it} />
      ))}
      {overflow.length > 0 && (
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button
              variant="ghost"
              size="sm"
              className="h-8 w-8 p-0"
              aria-label="Mais ações"
            >
              <MoreHorizontal size={14} />
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end" className="min-w-[180px]">
            {overflow.map((it, i) => (
              <OverflowItem key={`ov-${it.label}-${i}`} item={it} />
            ))}
          </DropdownMenuContent>
        </DropdownMenu>
      )}
    </div>
  );
}

function ActionButton({ item }: { item: PageActionItem }) {
  const variant: PageActionVariant = item.active ? 'default' : item.variant ?? 'outline';
  const inner = (
    <span className="inline-flex items-center gap-1.5">
      {item.icon && <Icon name={item.icon} size={13} />}
      <span>{item.label}</span>
      {item.count !== undefined && item.count > 0 && (
        <span
          className={cn(
            'ml-1 inline-flex h-4 min-w-[18px] items-center justify-center rounded-full px-1 text-[10px] font-medium tabular-nums',
            item.active
              ? 'bg-background/20 text-primary-foreground'
              : 'bg-muted text-muted-foreground',
          )}
        >
          {item.count}
        </span>
      )}
    </span>
  );

  if (item.href) {
    return (
      <Button asChild variant={variant} size="sm" className="h-8 text-xs">
        <Link href={item.href}>{inner}</Link>
      </Button>
    );
  }
  return (
    <Button variant={variant} size="sm" className="h-8 text-xs" onClick={item.onClick}>
      {inner}
    </Button>
  );
}

function OverflowItem({ item }: { item: PageActionItem }) {
  const inner = (
    <span className="inline-flex items-center gap-2 w-full">
      {item.icon && <Icon name={item.icon} size={13} />}
      <span className="flex-1">{item.label}</span>
      {item.count !== undefined && item.count > 0 && (
        <span className="text-[10px] tabular-nums text-muted-foreground">{item.count}</span>
      )}
    </span>
  );
  if (item.href) {
    return (
      <DropdownMenuItem asChild>
        <Link href={item.href}>{inner}</Link>
      </DropdownMenuItem>
    );
  }
  return (
    <DropdownMenuItem onSelect={() => item.onClick?.()}>{inner}</DropdownMenuItem>
  );
}
