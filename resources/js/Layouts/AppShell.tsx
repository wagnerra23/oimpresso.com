import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState, type ReactNode } from 'react';
import { toast } from 'sonner';
import {
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
import ModuleTopNav from '@/Components/shared/ModuleTopNav';
import type { MenuItem } from '@/Types';

/**
 * Layout principal — sidebar 1 coluna com módulos flat + topbar + main.
 *
 * Cada página escolhe se quer barra de sub-navegação horizontal no topo do
 * main content (`moduleNav`) OU se usa tabs internas próprias (ex: DocVault
 * Modulo que tem 7 tabs contextuais).
 *
 * Decisão arquitetural (2026-04-23): sub-menu estilo Blade (topo da página)
 * + sidebar flat, ao invés de accordion expansível. Permissões e ordem do
 * backend preservadas via LegacyMenuAdapter.
 */
interface ModuleNavProp {
  items: MenuItem[];
  activeHref?: string;
  moduleLabel?: string;
  moduleIcon?: string;
}

interface AppShellProps {
  title?: string;
  breadcrumb?: Array<{ label: string; href?: string }>;
  /** Barra horizontal de sub-navegação no topo. Opcional — se omitir, nada aparece */
  moduleNav?: ModuleNavProp;
  children: ReactNode;
}

export default function AppShell({ title, breadcrumb, moduleNav, children }: AppShellProps) {
  const { shell } = usePageProps();
  const auth = useAuth();
  const business = useBusiness();
  const flash = useFlash();
  const { url } = usePage();

  const activeModule = useMemo(
    () => findActiveModule(shell.menu, url),
    [shell.menu, url],
  );

  const [mobileOpen, setMobileOpen] = useState(false);

  useEffect(() => {
    if (flash.success) toast.success(flash.success);
    if (flash.error) toast.error(flash.error);
    if (flash.info) toast.info(flash.info);
  }, [flash.success, flash.error, flash.info]);

  return (
    <TooltipProvider delayDuration={150}>
      {title ? <Head title={title} /> : <Head />}

      <div className="flex h-screen overflow-hidden bg-muted/30 text-foreground">
        {/* ======================================================================
            Sidebar desktop — 1 coluna flat (256px) · módulos + user rodapé
            ====================================================================== */}
        <aside className="hidden md:flex w-60 flex-col border-r border-border bg-card h-full overflow-hidden">
          {/* Topo: business */}
          <div className="flex h-12 items-center gap-2 border-b border-border px-3">
            <Link href="/home" className="flex items-center gap-2 min-w-0 flex-1 hover:opacity-80">
              <Avatar className="size-8 shrink-0">
                <AvatarFallback className="bg-primary text-primary-foreground text-[11px] font-bold">
                  {getInitials(business?.name ?? 'OI')}
                </AvatarFallback>
              </Avatar>
              <span className="text-sm font-semibold tracking-tight truncate">
                {business?.name ?? 'OI Impresso'}
              </span>
            </Link>
          </div>

          {/* Módulos top-level flat */}
          <nav className="flex-1 overflow-y-auto py-2">
            {shell.menu.map((mod, i) => (
              <ModuleFlatLink
                key={`${mod.label}-${i}`}
                module={mod}
                isActive={mod.label === activeModule?.label}
              />
            ))}
          </nav>

          {/* Rodapé: user + theme + logout */}
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
                <ThemeToggle variant="icon" align="end" />
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
            Mobile drawer
            ====================================================================== */}
        <Sheet open={mobileOpen} onOpenChange={setMobileOpen}>
          <SheetContent side="left" className="w-72 p-0 flex flex-col">
            <SheetHeader className="border-b border-border px-4 py-3">
              <SheetTitle className="text-left">{business?.name ?? 'OI Impresso'}</SheetTitle>
            </SheetHeader>
            <nav className="flex-1 overflow-y-auto py-2">
              {shell.menu.map((mod, i) => (
                <ModuleFlatLink
                  key={`${mod.label}-${i}`}
                  module={mod}
                  isActive={mod.label === activeModule?.label}
                  onNavigate={() => setMobileOpen(false)}
                />
              ))}
            </nav>
            {auth.user && (
              <div className="border-t border-border p-3">
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
            Main column — topbar + (moduleNav opcional) + breadcrumb + content
            ====================================================================== */}
        <div className="flex flex-1 flex-col min-w-0 h-full overflow-hidden">
          <header className="sticky top-0 z-30 flex h-12 items-center gap-3 border-b border-border bg-background px-4">
            <Sheet open={mobileOpen} onOpenChange={setMobileOpen}>
              <SheetTrigger asChild>
                <Button variant="ghost" size="icon" className="md:hidden" aria-label="Abrir menu">
                  <MenuIcon size={18} />
                </Button>
              </SheetTrigger>
            </Sheet>

            <div className="hidden md:flex flex-1 max-w-md items-center gap-2 rounded-md border border-input bg-muted/40 px-3 py-1 text-sm text-muted-foreground">
              <Search size={14} />
              <Input
                className="h-7 border-0 bg-transparent p-0 text-sm shadow-none focus-visible:ring-0"
                placeholder="Buscar… (Cmd+K em breve)"
              />
            </div>

            <div className="flex flex-1 md:flex-none" />

            <UserQuickMenu />
          </header>

          {/* Module top nav (opcional — cada página decide) */}
          {moduleNav && (
            <ModuleTopNav
              items={moduleNav.items}
              activeHref={moduleNav.activeHref}
              moduleLabel={moduleNav.moduleLabel ?? activeModule?.label}
              moduleIcon={moduleNav.moduleIcon ?? activeModule?.icon}
            />
          )}

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
// Módulo flat na sidebar (1 coluna, sem expansão)
// ============================================================================
function ModuleFlatLink({
  module,
  isActive,
  onNavigate,
}: {
  module: MenuItem;
  isActive: boolean;
  onNavigate?: () => void;
}) {
  const href = module.href ?? firstLeafHref(module) ?? '#';
  const asInertia = module.inertia ?? !module.children?.length;

  const className = cn(
    'flex items-center gap-2.5 px-3 py-2 mx-1 my-0.5 rounded-md text-sm transition-colors',
    isActive
      ? 'bg-primary/10 text-primary font-medium'
      : 'text-muted-foreground hover:bg-accent hover:text-accent-foreground',
  );

  const content = (
    <>
      <Icon name={module.icon} size={16} className="shrink-0" />
      <span className="flex-1 truncate">{module.label}</span>
      {module.badge != null && (
        <span className="rounded-full bg-destructive px-1.5 py-0.5 text-[10px] font-medium text-destructive-foreground">
          {module.badge}
        </span>
      )}
    </>
  );

  if (asInertia && href !== '#') {
    return <Link href={href} onClick={onNavigate} className={className}>{content}</Link>;
  }
  return <a href={href} onClick={onNavigate} className={className}>{content}</a>;
}

// ============================================================================
// User quick menu (topbar direita — atalho redundante com o rodapé da sidebar,
// útil em desktop pequeno ou quando sidebar está escondida)
// ============================================================================
function UserQuickMenu() {
  const auth = useAuth();
  if (!auth.user) return null;

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button variant="ghost" size="icon" className="rounded-full" aria-label={`Menu: ${auth.user.name}`}>
          <Avatar className="size-7">
            <AvatarFallback className="bg-muted text-foreground text-[10px] font-medium">
              {getInitials(auth.user.name)}
            </AvatarFallback>
          </Avatar>
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end" className="w-56">
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
// Helpers
// ============================================================================

function getInitials(name: string): string {
  return name.split(' ').filter(Boolean).slice(0, 2).map((s) => s[0]?.toUpperCase() ?? '').join('');
}

function findActiveModule(menu: MenuItem[], url: string): MenuItem | null {
  const currentPath = url.split('?')[0]?.split('#')[0] ?? url;

  const hrefMatches = (href: string | undefined, target: string): boolean => {
    if (!href || href === '#') return false;
    return target === href || target.startsWith(href + '?') || target.startsWith(href + '/');
  };

  for (const mod of menu) {
    if (hrefMatches(mod.href, currentPath)) return mod;
    if (mod.children?.some((c) => hrefMatches(c.href, currentPath))) return mod;
  }

  const rootSegment = currentPath.split('/').filter(Boolean)[0];
  if (rootSegment) {
    const rootPrefix = `/${rootSegment}`;
    for (const mod of menu) {
      const modStarts = mod.href && mod.href !== '#' && mod.href.startsWith(rootPrefix);
      const childStarts = mod.children?.some((c) => c.href && c.href.startsWith(rootPrefix));
      if (modStarts || childStarts) return mod;
    }
  }

  return menu.find((m) => m.children?.length) ?? menu[0] ?? null;
}

function firstLeafHref(module: MenuItem): string | null {
  if (module.href) return module.href;
  for (const child of module.children ?? []) {
    if (child.href) return child.href;
    const nested = firstLeafHref(child);
    if (nested) return nested;
  }
  return null;
}
