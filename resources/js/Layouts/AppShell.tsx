import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState, type ReactNode } from 'react';
import { toast } from 'sonner';
import {
  ChevronLeft,
  ChevronRight,
  LogOut,
  Menu as MenuIcon,
  Search,
  UserCircle2,
} from 'lucide-react';
import { Icon } from '@/Components/Icon';
import { ThemeToggle } from '@/Components/ThemeToggle';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Separator } from '@/Components/ui/separator';
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetTrigger } from '@/Components/ui/sheet';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import { Avatar, AvatarFallback } from '@/Components/ui/avatar';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/Components/ui/tooltip';
import { cn } from '@/Lib/utils';
import { useAuth, useBusiness, useFlash, usePageProps } from '@/Hooks/usePageProps';
import type { MenuItem } from '@/Types';

interface AppShellProps {
  title?: string;
  breadcrumb?: Array<{ label: string; href?: string }>;
  children: ReactNode;
}

export default function AppShell({ title, breadcrumb, children }: AppShellProps) {
  const { shell } = usePageProps();
  const auth = useAuth();
  const business = useBusiness();
  const flash = useFlash();
  const { url } = usePage();

  // Qual módulo está ativo (1ª coluna destaca; 2ª coluna mostra suas páginas)
  const activeModule = useMemo(
    () => findActiveModule(shell.menu, url),
    [shell.menu, url],
  );

  // Estado do drawer mobile
  const [mobileOpen, setMobileOpen] = useState(false);

  // Flash → toast
  useEffect(() => {
    if (flash.success) toast.success(flash.success);
    if (flash.error) toast.error(flash.error);
    if (flash.info) toast.info(flash.info);
  }, [flash.success, flash.error, flash.info]);

  const pages = activeModule?.children ?? [];
  const activeModuleLabel = activeModule?.label;

  return (
    <TooltipProvider delayDuration={150}>
      {title ? <Head title={title} /> : <Head />}

      <div className="flex h-screen overflow-hidden bg-muted/30 text-foreground">
        {/* ======================================================================
            Coluna 1 (desktop) — módulos (ícones sempre visíveis, 64px largura)
            ====================================================================== */}
        <aside className="hidden md:flex w-16 flex-col items-center border-r border-border bg-card py-3 gap-1 h-full overflow-hidden">
          {/* Logo/business avatar no topo */}
          <Tooltip>
            <TooltipTrigger asChild>
              <Link href="/home">
                <Avatar className="size-9 mb-2 cursor-pointer">
                  <AvatarFallback className="bg-primary text-primary-foreground text-xs font-bold">
                    {getInitials(business?.name ?? 'OI')}
                  </AvatarFallback>
                </Avatar>
              </Link>
            </TooltipTrigger>
            <TooltipContent side="right">{business?.name ?? 'OI Impresso'}</TooltipContent>
          </Tooltip>

          <Separator className="mb-1 w-8" />

          {/* Módulos top-level */}
          <nav className="flex flex-col items-center gap-1 flex-1 overflow-y-auto w-full px-1">
            {shell.menu.map((mod, i) => (
              <ModuleIcon
                key={`${mod.label}-${i}`}
                module={mod}
                active={mod.label === activeModule?.label}
              />
            ))}
          </nav>

          {/* Rodapé col 1: theme toggle + user avatar */}
          <div className="mt-auto flex flex-col items-center gap-1 pt-2 border-t border-border w-full px-1">
            <ThemeToggle variant="icon" align="end" />
            <UserAvatarMenu />
          </div>
        </aside>

        {/* ======================================================================
            Coluna 2 (desktop) — páginas do módulo ativo, sempre visível (256px)
            ====================================================================== */}
        <aside className="hidden md:flex w-64 flex-col border-r border-border bg-card h-full overflow-hidden">
          {/* Header da coluna: nome do módulo ativo */}
          <div className="flex h-12 items-center border-b border-border px-4">
            <span className="text-sm font-semibold tracking-tight">
              {activeModuleLabel ?? 'Início'}
            </span>
          </div>

          {/* Sub-páginas */}
          <nav className="flex-1 overflow-y-auto py-2">
            {pages.length > 0 ? (
              pages.map((page, i) => (
                <PageLink key={`${page.label}-${i}`} item={page} />
              ))
            ) : (
              <p className="px-4 py-3 text-xs text-muted-foreground">
                Selecione um módulo à esquerda.
              </p>
            )}
          </nav>

          {/* Rodapé col 2: infos do user (nome + email) */}
          {auth.user && (
            <div className="border-t border-border p-3">
              <div className="flex items-center gap-2">
                <Avatar className="size-8 shrink-0">
                  <AvatarFallback className="bg-primary text-primary-foreground text-xs font-medium">
                    {getInitials(auth.user.name)}
                  </AvatarFallback>
                </Avatar>
                <div className="flex flex-col min-w-0 flex-1">
                  <span className="text-xs font-medium truncate">{auth.user.name}</span>
                  <span className="text-[10px] text-muted-foreground truncate">
                    {auth.user.email}
                  </span>
                </div>
                <Tooltip>
                  <TooltipTrigger asChild>
                    <Button
                      variant="ghost"
                      size="icon"
                      className="size-7 shrink-0"
                      onClick={() => router.post('/logout')}
                      aria-label="Sair"
                    >
                      <LogOut size={14} />
                    </Button>
                  </TooltipTrigger>
                  <TooltipContent side="top">Sair</TooltipContent>
                </Tooltip>
              </div>
            </div>
          )}
        </aside>

        {/* ======================================================================
            Mobile sidebar (drawer) — col1+col2 empilhadas
            ====================================================================== */}
        <Sheet open={mobileOpen} onOpenChange={setMobileOpen}>
          <SheetContent side="left" className="w-72 p-0 flex flex-col">
            <SheetHeader className="border-b border-border px-4 py-3">
              <SheetTitle className="text-left">{business?.name ?? 'OI Impresso'}</SheetTitle>
            </SheetHeader>
            <MobileMenu menu={shell.menu} onNavigate={() => setMobileOpen(false)} />
            {auth.user && (
              <div className="border-t border-border p-3 mt-auto">
                <div className="flex items-center gap-2">
                  <Avatar className="size-8">
                    <AvatarFallback className="bg-primary text-primary-foreground text-xs">
                      {getInitials(auth.user.name)}
                    </AvatarFallback>
                  </Avatar>
                  <div className="flex flex-col min-w-0 flex-1">
                    <span className="text-xs font-medium truncate">{auth.user.name}</span>
                  </div>
                  <ThemeToggle variant="icon" align="end" />
                  <Button variant="ghost" size="icon" className="size-7"
                          onClick={() => router.post('/logout')} aria-label="Sair">
                    <LogOut size={14} />
                  </Button>
                </div>
              </div>
            )}
          </SheetContent>
        </Sheet>

        {/* ======================================================================
            Main column — topbar + content
            ====================================================================== */}
        <div className="flex flex-1 flex-col min-w-0 h-full overflow-hidden">
          <header className="sticky top-0 z-30 flex h-12 items-center gap-3 border-b border-border bg-background px-4">
            {/* Mobile menu trigger */}
            <Sheet open={mobileOpen} onOpenChange={setMobileOpen}>
              <SheetTrigger asChild>
                <Button variant="ghost" size="icon" className="md:hidden" aria-label="Abrir menu">
                  <MenuIcon size={18} />
                </Button>
              </SheetTrigger>
            </Sheet>

            {/* Search (stub Cmd+K) */}
            <div className="hidden md:flex flex-1 max-w-md items-center gap-2 rounded-md border border-input bg-muted/40 px-3 py-1 text-sm text-muted-foreground">
              <Search size={14} />
              <Input
                className="h-7 border-0 bg-transparent p-0 text-sm shadow-none focus-visible:ring-0"
                placeholder="Buscar… (Cmd+K em breve)"
              />
            </div>

            <div className="flex flex-1 md:flex-none" />
          </header>

          {/* Breadcrumb */}
          {breadcrumb && breadcrumb.length > 0 && (
            <div className="flex items-center gap-1 border-b border-border bg-background px-4 py-1.5 text-xs text-muted-foreground">
              {breadcrumb.map((crumb, i) => (
                <span key={i} className="flex items-center gap-1">
                  {i > 0 && <ChevronRight size={12} className="opacity-50" />}
                  {crumb.href ? (
                    <Link href={crumb.href} className="hover:text-foreground">
                      {crumb.label}
                    </Link>
                  ) : (
                    <span className="text-foreground">{crumb.label}</span>
                  )}
                </span>
              ))}
            </div>
          )}

          <main className="flex-1 overflow-auto">{children}</main>
        </div>
      </div>
    </TooltipProvider>
  );
}

// ============================================================================
// Col 1 — ícone de módulo
// ============================================================================
function ModuleIcon({ module, active }: { module: MenuItem; active: boolean }) {
  const firstChildHref = useMemo(() => firstLeafHref(module), [module]);
  const href = module.href ?? firstChildHref ?? '#';
  const asInertia = module.inertia ?? !module.children?.length;

  const className = cn(
    'flex size-10 items-center justify-center rounded-md transition-colors',
    active
      ? 'bg-primary/15 text-primary'
      : 'text-muted-foreground hover:bg-accent hover:text-accent-foreground',
  );

  const content = <Icon name={module.icon} size={18} />;

  const link = asInertia ? (
    <Link href={href} className={className}>{content}</Link>
  ) : (
    <a href={href} className={className}>{content}</a>
  );

  return (
    <Tooltip>
      <TooltipTrigger asChild>
        <div>{link}</div>
      </TooltipTrigger>
      <TooltipContent side="right" className="font-medium">
        {module.label}
      </TooltipContent>
    </Tooltip>
  );
}

// ============================================================================
// Col 2 — link da sub-página
// ============================================================================
function PageLink({ item }: { item: MenuItem }) {
  const { url } = usePage();
  const isActive = item.href ? url === item.href || url.startsWith(item.href + '?') : false;

  const className = cn(
    'flex items-center gap-2 px-3 py-2 mx-2 my-0.5 rounded-md text-sm transition-colors',
    isActive
      ? 'bg-primary/10 text-primary font-medium'
      : 'text-muted-foreground hover:bg-accent hover:text-accent-foreground',
  );

  const content = (
    <>
      <Icon name={item.icon} size={15} />
      <span className="flex-1 truncate">{item.label}</span>
      {item.badge != null && (
        <span className="rounded-full bg-destructive px-1.5 py-0.5 text-[10px] font-medium text-destructive-foreground">
          {item.badge}
        </span>
      )}
    </>
  );

  if (item.inertia) {
    return <Link href={item.href ?? '#'} className={className}>{content}</Link>;
  }
  return <a href={item.href ?? '#'} className={className}>{content}</a>;
}

// ============================================================================
// User menu (col 1 rodapé) — botão redondo com avatar + dropdown
// ============================================================================
function UserAvatarMenu() {
  const auth = useAuth();
  if (!auth.user) return null;

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button
          variant="ghost"
          size="icon"
          className="rounded-full"
          aria-label={`Menu do usuário: ${auth.user.name}`}
          title={auth.user.name}
        >
          <Avatar className="size-8">
            <AvatarFallback className="bg-muted text-foreground text-xs font-medium">
              {getInitials(auth.user.name)}
            </AvatarFallback>
          </Avatar>
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end" side="right" className="w-56">
        <DropdownMenuLabel>
          <div className="flex flex-col">
            <span className="text-sm font-medium truncate">{auth.user.name}</span>
            <span className="text-xs text-muted-foreground truncate">{auth.user.email}</span>
          </div>
        </DropdownMenuLabel>
        <DropdownMenuSeparator />
        <DropdownMenuItem asChild>
          <a href="/user/profile" className="cursor-pointer">
            <UserCircle2 size={14} className="mr-2" />
            Meu perfil
          </a>
        </DropdownMenuItem>
        <DropdownMenuSeparator />
        <DropdownMenuItem
          onClick={() => router.post('/logout')}
          className="cursor-pointer text-destructive"
        >
          <LogOut size={14} className="mr-2" />
          Sair
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  );
}

// ============================================================================
// Mobile drawer — mostra tudo empilhado (módulo → sub-páginas)
// ============================================================================
function MobileMenu({ menu, onNavigate }: { menu: MenuItem[]; onNavigate: () => void }) {
  return (
    <nav className="flex-1 overflow-y-auto">
      {menu.map((mod, i) => (
        <div key={`${mod.label}-${i}`} className="py-1">
          <div className="px-4 py-1.5 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
            {mod.label}
          </div>
          {(mod.children ?? [mod]).map((page, j) => (
            <MobilePageLink key={`${page.label}-${j}`} item={page} onNavigate={onNavigate} />
          ))}
        </div>
      ))}
    </nav>
  );
}

function MobilePageLink({ item, onNavigate }: { item: MenuItem; onNavigate: () => void }) {
  const className = 'flex items-center gap-3 px-4 py-2.5 text-sm hover:bg-accent';
  const content = (
    <>
      <Icon name={item.icon} size={16} className="text-muted-foreground" />
      <span>{item.label}</span>
    </>
  );
  if (item.inertia) {
    return (
      <Link href={item.href ?? '#'} onClick={onNavigate} className={className}>
        {content}
      </Link>
    );
  }
  return (
    <a href={item.href ?? '#'} onClick={onNavigate} className={className}>
      {content}
    </a>
  );
}

// ============================================================================
// Helpers
// ============================================================================

function getInitials(name: string): string {
  return name
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((s) => s[0]?.toUpperCase() ?? '')
    .join('');
}

/**
 * Encontra o módulo ativo baseado na URL atual.
 *
 * Estratégia em camadas (primeira correspondência ganha):
 *   1. Match exato de href (folha ou parent).
 *   2. Algum child tem href que casa exato com URL.
 *   3. Match por root path (ex.: URL /ponto/relatorios → módulo cujo href
 *      ou qualquer child começa com /ponto/). Útil quando a tela atual
 *      não está listada no menu (ex.: nossa tela React nova).
 *   4. Fallback: primeiro módulo com children.
 */
function findActiveModule(menu: MenuItem[], url: string): MenuItem | null {
  const currentPath = url.split('?')[0]?.split('#')[0] ?? url;

  const hrefMatches = (href: string | undefined, target: string): boolean => {
    if (!href || href === '#') return false;
    return target === href || target.startsWith(href + '?') || target.startsWith(href + '/');
  };

  // 1 + 2: exact matches
  for (const mod of menu) {
    if (hrefMatches(mod.href, currentPath)) return mod;
    if (mod.children?.some((c) => hrefMatches(c.href, currentPath))) return mod;
  }

  // 3: match por root path (primeiro segmento)
  const rootSegment = currentPath.split('/').filter(Boolean)[0];
  if (rootSegment) {
    const rootPrefix = `/${rootSegment}`;
    for (const mod of menu) {
      const modStarts = mod.href && mod.href !== '#' && mod.href.startsWith(rootPrefix);
      const childStarts = mod.children?.some((c) => c.href && c.href.startsWith(rootPrefix));
      if (modStarts || childStarts) return mod;
    }
  }

  // 4: fallback
  return menu.find((m) => m.children?.length) ?? menu[0] ?? null;
}

/**
 * Descobre a primeira folha clicável do módulo para usar como destino default
 * quando o usuário clica no ícone do módulo na coluna 1.
 */
function firstLeafHref(module: MenuItem): string | null {
  if (module.href) return module.href;
  for (const child of module.children ?? []) {
    if (child.href) return child.href;
    const nested = firstLeafHref(child);
    if (nested) return nested;
  }
  return null;
}
