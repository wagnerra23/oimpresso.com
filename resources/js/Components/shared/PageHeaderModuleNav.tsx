import { useEffect, useRef, useState } from 'react';
import { ChevronDown } from 'lucide-react';
import { Icon } from '@/Components/Icon';
import { useAutoModuleNav } from '@/Hooks/usePageProps';
import { usePage } from '@inertiajs/react';
import { cn } from '@/Lib/utils';

/**
 * PageHeaderModuleNav — dropdown compacto de navegação intra-módulo.
 *
 * Wagner 2026-05-17: substitui o topnav horizontal de 44px após `hideTopbar=true`
 * virar default no AppShellV2. Renderiza um botão pequeno (⌄) ao lado do título
 * da página; clica → flutuante lista as outras telas do módulo (lido via
 * `useAutoModuleNav` que consome `shell.topnavs[Modulo]` — Resources/menus/topnav.php).
 *
 * Silencioso: se módulo não tem topnav configurado, retorna null (nada renderiza).
 *
 * Uso típico via PageHeader:
 *   <PageHeader title="Lista de vendas" moduleNav description="..." />
 *
 * Uso standalone:
 *   <PageHeader title="..." action={<PageHeaderModuleNav />} />
 */
interface Props {
  /** Override do label exibido no botão. Default: nome do módulo detectado (ex "Vendas"). */
  label?: string;
  className?: string;
}

export default function PageHeaderModuleNav({ label, className }: Props) {
  const moduleNav = useAutoModuleNav();
  const page = usePage();
  const [open, setOpen] = useState(false);
  const ref = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (!open) return;
    const handler = (e: MouseEvent) => {
      if (ref.current && !ref.current.contains(e.target as Node)) setOpen(false);
    };
    document.addEventListener('mousedown', handler);
    return () => document.removeEventListener('mousedown', handler);
  }, [open]);

  if (!moduleNav || moduleNav.items.length === 0) return null;

  const currentPath = (page.url.split('?')[0]?.split('#')[0] ?? page.url) as string;
  const displayLabel = label ?? moduleNav.moduleLabel;

  return (
    <div ref={ref} className={cn('relative inline-flex', className)}>
      <button
        type="button"
        onClick={() => setOpen((v) => !v)}
        aria-expanded={open}
        aria-haspopup="menu"
        title={`Navegar em ${displayLabel}`}
        className={cn(
          'inline-flex items-center gap-1.5 rounded-md border border-border/60 bg-background',
          'px-2.5 py-1.5 text-xs font-medium text-muted-foreground',
          'hover:bg-muted hover:text-foreground transition-colors',
          open && 'bg-muted text-foreground',
        )}
      >
        <span>{displayLabel}</span>
        <ChevronDown size={12} className={cn('transition-transform', open && 'rotate-180')} />
      </button>

      {open && (
        <div
          role="menu"
          className={cn(
            'absolute right-0 top-[calc(100%+6px)] z-50 min-w-[220px]',
            'rounded-lg border border-border bg-popover shadow-lg',
            'p-1 flex flex-col gap-0.5',
          )}
        >
          {moduleNav.items.map((item, i) => {
            const href = item.href ?? '#';
            const itemRoot = '/' + (href.split('/').slice(1, 3).join('/'));
            const isActive = currentPath.startsWith(itemRoot) && itemRoot !== '/';
            return (
              <a
                key={i}
                href={href}
                role="menuitem"
                onClick={() => setOpen(false)}
                className={cn(
                  'flex items-center gap-2 px-3 py-2 rounded-md text-sm transition-colors',
                  isActive
                    ? 'bg-primary/10 text-primary font-medium'
                    : 'text-foreground hover:bg-muted',
                )}
              >
                {item.icon && <Icon name={item.icon} size={14} />}
                <span className="truncate">{item.label}</span>
              </a>
            );
          })}
        </div>
      )}
    </div>
  );
}
