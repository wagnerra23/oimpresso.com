import * as React from 'react';
import { Link, usePage } from '@inertiajs/react';
import { cn } from '@/Lib/utils';
import { Icon } from '@/Components/Icon';
import { Badge } from '@/Components/ui/badge';

/**
 * SubNav — sub-navegação contextual de baixo contraste.
 *
 * Duas variantes:
 *
 * • `underline` (padrão) — navbar secundária horizontal abaixo do título.
 *   Visual mais leve que ModuleTopNav: borda parcial, texto mais apagado no inativo.
 *   Ideal pra trocar de seção dentro de uma mesma página sem mudar a URL principal.
 *
 * • `segmented` — pill-style inline, sem bordas pesadas.
 *   Item ativo ganha fundo suave + shadow-sm. Ideal pra filtros de conteúdo
 *   (ex: "Resumo | Detalhe | RBAC") dentro de um card ou seção.
 *
 * Dois modos de operação:
 *
 * • **Controlado (state)** — `value` + `onChange`:
 *     <SubNav items={tabs} value={aba} onChange={setAba} />
 *
 * • **Navegação (href)** — items com `href` → `Link` Inertia ou `<a>`:
 *     <SubNav items={[{ label: 'Custos', href: '/copiloto/admin/custos', inertia: true }]} />
 *
 * Uso mínimo:
 *   const tabs = [
 *     { value: 'resumo',  label: 'Resumo',  icon: 'bar-chart-2' },
 *     { value: 'rbac',    label: 'RBAC',    icon: 'shield', badge: 3 },
 *     { value: 'logs',    label: 'Logs brutos' },
 *   ];
 *
 *   <SubNav items={tabs} value={aba} onChange={setAba} />
 *   <SubNav items={tabs} value={aba} onChange={setAba} variant="segmented" />
 */

export interface SubNavItem {
  /** Valor único — usado em modo controlado. Pode omitir em modo href-only. */
  value?: string;
  label: string;
  /** Nome do ícone lucide-react (R-DS-003). */
  icon?: string;
  /** Badge numérico ou string curta (ex: contagem, "novo"). */
  badge?: number | string;
  /** URL de destino — ativa modo navegação. */
  href?: string;
  /** Usar Link Inertia em vez de <a>. Default: false. */
  inertia?: boolean;
  /** Desabilitar o item. */
  disabled?: boolean;
  /** `data-testid` do elemento (locator E2E — NÚCLEO #7 anti-quebra-silenciosa). */
  testId?: string;
}

interface SubNavProps {
  items: SubNavItem[];
  /** `underline` — navbar secundária (padrão). `segmented` — pill inline. */
  variant?: 'underline' | 'segmented';
  /** Valor ativo em modo controlado. */
  value?: string;
  /** Callback em modo controlado. */
  onChange?: (value: string) => void;
  /** Nome acessível do tablist (`aria-label`). Default: "Seções da página". */
  ariaLabel?: string;
  className?: string;
}

export default function SubNav({
  items,
  variant = 'underline',
  value,
  onChange,
  ariaLabel,
  className,
}: SubNavProps) {
  if (variant === 'segmented') {
    return <Segmented items={items} value={value} onChange={onChange} ariaLabel={ariaLabel} className={className} />;
  }
  return <Underline items={items} value={value} onChange={onChange} ariaLabel={ariaLabel} className={className} />;
}

/* ─── Variante: underline ─────────────────────────────────────────────── */

function Underline({ items, value, onChange, ariaLabel, className }: Omit<SubNavProps, 'variant'>) {
  const { url } = usePage();

  return (
    <nav
      role="tablist"
      aria-label={ariaLabel ?? 'Seções da página'}
      className={cn('flex items-center gap-0.5 border-b border-border/60 overflow-x-auto', className)}
    >
      {items.map((item, i) => {
        const isActive = resolveActive(item, value, url);
        const key = item.value ?? item.href ?? String(i);

        const cls = cn(
          'flex items-center gap-1.5 px-3 py-2 text-sm border-b-2 -mb-px whitespace-nowrap',
          'transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',
          isActive
            ? 'border-primary/70 text-foreground font-medium'
            : 'border-transparent text-muted-foreground hover:text-foreground hover:border-border',
          item.disabled && 'pointer-events-none opacity-40',
        );

        const content = (
          <>
            {item.icon && <Icon name={item.icon} size={13} />}
            <span>{item.label}</span>
            {item.badge != null && (
              <Badge variant="secondary" className="text-[10px] h-4 px-1.5 min-w-4">
                {item.badge}
              </Badge>
            )}
          </>
        );

        if (item.href) {
          return item.inertia
            ? <Link key={key} href={item.href} data-testid={item.testId} role="tab" aria-selected={isActive} className={cls}>{content}</Link>
            : <a key={key} href={item.href} data-testid={item.testId} role="tab" aria-selected={isActive} className={cls}>{content}</a>;
        }

        return (
          <button
            key={key}
            data-testid={item.testId}
            role="tab"
            aria-selected={isActive}
            disabled={item.disabled}
            onClick={() => item.value && onChange?.(item.value)}
            className={cls}
          >
            {content}
          </button>
        );
      })}
    </nav>
  );
}

/* ─── Variante: segmented ─────────────────────────────────────────────── */

function Segmented({ items, value, onChange, ariaLabel, className }: Omit<SubNavProps, 'variant'>) {
  const { url } = usePage();

  return (
    <div
      role="tablist"
      aria-label={ariaLabel ?? 'Seções da página'}
      className={cn(
        'inline-flex items-center gap-0.5 bg-muted rounded-lg p-1',
        className,
      )}
    >
      {items.map((item, i) => {
        const isActive = resolveActive(item, value, url);
        const key = item.value ?? item.href ?? String(i);

        const cls = cn(
          'flex items-center gap-1.5 px-3 py-1.5 text-sm rounded-md whitespace-nowrap',
          'transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',
          isActive
            ? 'bg-background text-foreground shadow-sm font-medium'
            : 'text-muted-foreground hover:text-foreground',
          item.disabled && 'pointer-events-none opacity-40',
        );

        const content = (
          <>
            {item.icon && <Icon name={item.icon} size={13} />}
            <span>{item.label}</span>
            {item.badge != null && (
              <Badge
                variant={isActive ? 'default' : 'secondary'}
                className="text-[10px] h-4 px-1.5 min-w-4"
              >
                {item.badge}
              </Badge>
            )}
          </>
        );

        if (item.href) {
          return item.inertia
            ? <Link key={key} href={item.href} data-testid={item.testId} role="tab" aria-selected={isActive} className={cls}>{content}</Link>
            : <a key={key} href={item.href} data-testid={item.testId} role="tab" aria-selected={isActive} className={cls}>{content}</a>;
        }

        return (
          <button
            key={key}
            data-testid={item.testId}
            role="tab"
            aria-selected={isActive}
            disabled={item.disabled}
            onClick={() => item.value && onChange?.(item.value)}
            className={cls}
          >
            {content}
          </button>
        );
      })}
    </div>
  );
}

/* ─── helpers ─────────────────────────────────────────────────────────── */

function resolveActive(item: SubNavItem, value: string | undefined, currentUrl: string): boolean {
  if (item.href) {
    const clean = currentUrl.split('?')[0].split('#')[0];
    return clean === item.href || clean.startsWith(item.href + '/');
  }
  return item.value !== undefined && item.value === value;
}
