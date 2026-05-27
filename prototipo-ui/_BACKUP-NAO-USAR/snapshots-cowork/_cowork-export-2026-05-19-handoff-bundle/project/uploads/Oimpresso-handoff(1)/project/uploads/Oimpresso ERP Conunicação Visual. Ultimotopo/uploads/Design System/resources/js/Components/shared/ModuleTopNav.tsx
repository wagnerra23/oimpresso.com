import { Link, usePage } from '@inertiajs/react';
import { cn } from '@/Lib/utils';
import { Icon } from '@/Components/Icon';
import { Badge } from '@/Components/ui/badge';
import type { MenuItem } from '@/Types';

/**
 * Barra horizontal de sub-navegação — estilo Blade `nav.blade.php`.
 *
 * Fonte INDEPENDENTE da sidebar. Alimentada por
 * `Modules/<Nome>/Resources/menus/topnav.php` → backend filtra Spatie →
 * Inertia expõe em `shell.topnavs[<Nome>]` → hook `useModuleNav(key)` →
 * page passa como prop pro `<AppShell moduleNav={...}>`.
 *
 * Sidebar accordion (DataController::modifyAdminMenu) continua funcionando
 * paralelamente e independente — os 2 sistemas não se comunicam.
 */
interface Props {
  items: MenuItem[];
  activeHref?: string;
  moduleLabel?: string;
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
          <div className="flex items-center gap-1.5 pr-3 border-r border-border py-2 text-sm font-semibold text-foreground whitespace-nowrap">
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
  const current = currentHref.split('?')[0]?.split('#')[0] ?? currentHref;
  const isActive = href !== '#' && (
    current === href ||
    current.startsWith(href + '?') ||
    current.startsWith(href + '/')
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
