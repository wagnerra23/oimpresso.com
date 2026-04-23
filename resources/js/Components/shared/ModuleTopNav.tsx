import { Link, usePage } from '@inertiajs/react';
import { cn } from '@/Lib/utils';
import { Icon } from '@/Components/Icon';
import { Badge } from '@/Components/ui/badge';
import type { MenuItem } from '@/Types';

/**
 * Barra horizontal de sub-navegação no topo da página — estilo Blade antigo.
 *
 * Uso típico: páginas dentro de um módulo (Ponto/Espelho, Ponto/Intercorrencias…)
 * recebem os irmãos de módulo e renderizam como abas no topo.
 *
 * Opcional: AppShell aceita prop `moduleNav` com items+activeHref, e renderiza
 * esse componente ANTES do breadcrumb. Quando a página tem tabs internas ricas
 * (ex: DocVault/Modulo), simplesmente NÃO passa moduleNav e o bar não aparece.
 *
 * Contratos:
 * - `items`: array de `MenuItem` (mesmo shape do shell.menu)
 * - `activeHref`: URL corrente pra destacar a aba ativa. Opcional — se omitir,
 *   usa `usePage().url` como default.
 * - Scroll horizontal se houver muitos itens (overflow-x-auto).
 */
interface Props {
  items: MenuItem[];
  activeHref?: string;
  /** Opcional: título do módulo à esquerda da barra */
  moduleLabel?: string;
  /** Opcional: ícone do módulo (Lucide name) — aparece junto com moduleLabel */
  moduleIcon?: string;
}

export default function ModuleTopNav({ items, activeHref, moduleLabel, moduleIcon }: Props) {
  const { url } = usePage();
  const currentHref = activeHref ?? url;

  if (!items || items.length === 0) return null;

  return (
    <div className="border-b border-border bg-background">
      <div className="flex items-center gap-2 px-4">
        {moduleLabel && (
          <div className="flex items-center gap-1.5 pr-3 border-r border-border py-2 text-sm font-semibold text-foreground">
            {moduleIcon && <Icon name={moduleIcon} size={14} className="text-primary" />}
            {moduleLabel}
          </div>
        )}

        <nav className="flex gap-0.5 overflow-x-auto flex-1">
          {items.map((item, i) => (
            <TabLink key={`${item.label}-${i}`} item={item} currentHref={currentHref} />
          ))}
        </nav>
      </div>
    </div>
  );
}

function TabLink({ item, currentHref }: { item: MenuItem; currentHref: string }) {
  const href = item.href ?? '#';
  const isActive = href !== '#' && (
    currentHref === href ||
    currentHref.startsWith(href + '?') ||
    currentHref.startsWith(href + '/')
  );

  const className = cn(
    'flex items-center gap-1.5 px-3 py-2 text-sm border-b-2 -mb-px whitespace-nowrap transition-colors',
    isActive
      ? 'border-primary text-foreground font-medium'
      : 'border-transparent text-muted-foreground hover:text-foreground hover:bg-accent/30',
  );

  const content = (
    <>
      {item.icon && <Icon name={item.icon} size={13} />}
      <span>{item.label}</span>
      {item.badge != null && (
        <Badge variant="secondary" className="text-[10px] h-4">
          {item.badge}
        </Badge>
      )}
    </>
  );

  if (item.inertia) {
    return <Link href={href} className={className}>{content}</Link>;
  }
  return <a href={href} className={className}>{content}</a>;
}
