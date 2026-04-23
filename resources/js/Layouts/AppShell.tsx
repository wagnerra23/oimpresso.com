import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState, type ReactNode } from 'react';
import { toast } from 'sonner';
import {
  ChevronDown,
  ChevronRight,
  LogOut,
  Menu as MenuIcon,
  UserCircle2,
} from 'lucide-react';
import { Icon } from '@/Components/Icon';
import { ThemeToggle } from '@/Components/ThemeToggle';
import { Button } from '@/Components/ui/button';
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
import { useAuth, useAutoModuleNav, useBusiness, useFlash, usePageProps } from '@/Hooks/usePageProps';
import ModuleTopNav from '@/Components/shared/ModuleTopNav';
import type { MenuItem } from '@/Types';

/**
 * Layout principal — sidebar vertical com módulos accordion (estilo AdminLTE/Blade).
 *
 * Características (paridade com Blade original):
 * - Ordem dos módulos vem do backend (LegacyMenuAdapter já fez usort)
 * - Permissões Spatie já filtradas no backend (items que usuário não pode não chegam)
 * - Módulos com children: click expande/colapsa (chevron ▾/▸)
 * - Módulo contendo URL corrente: auto-expandido no mount
 * - Sub-items indentados com ícones próprios
 * - Ícones Lucide via componente Icon (nomes inferidos heuristicamente no backend)
 * - Rodapé: user (avatar + nome + email) + theme toggle + logout
 * - Mobile: Sheet drawer com mesma estrutura
 */
/**
 * Prop opcional pra renderizar barra horizontal entre topbar e breadcrumb.
 * Alimentada pelo hook useModuleNav(moduleKey).
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
  moduleNav?: ModuleNavProp;
  children: ReactNode;
}

export default function AppShell({ title, breadcrumb, moduleNav, children }: AppShellProps) {
  const { shell } = usePageProps();
  const auth = useAuth();
  const business = useBusiness();
  const flash = useFlash();
  const { url } = usePage();

  // Auto-detecta topnav se page não passou moduleNav explícito
  const autoModuleNav = useAutoModuleNav();
  const effectiveModuleNav = moduleNav ?? autoModuleNav;

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
            Sidebar desktop — accordion
            ====================================================================== */}
        <aside className="hidden md:flex w-64 flex-col border-r border-border bg-card h-full overflow-hidden">
          {/* Topo: brand business + status */}
          <div className="h-14 flex items-center gap-2 border-b border-border px-4 bg-primary/5">
            <Link href="/home" className="flex items-center gap-2 min-w-0 flex-1 hover:opacity-80">
              <span className="text-base font-bold tracking-tight truncate">
                {business?.name ?? 'OI Impresso'}
              </span>
              <span className="size-2 rounded-full bg-emerald-500 shrink-0" title="Online" />
            </Link>
          </div>

          {/* Menu accordion */}
          <nav className="flex-1 overflow-y-auto py-2">
            <MenuList items={shell.menu} currentUrl={url} />
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
            Mobile drawer — mesma estrutura accordion
            ====================================================================== */}
        <Sheet open={mobileOpen} onOpenChange={setMobileOpen}>
          <SheetContent side="left" className="w-72 p-0 flex flex-col">
            <SheetHeader className="border-b border-border px-4 py-3 bg-primary/5">
              <SheetTitle className="text-left flex items-center gap-2">
                {business?.name ?? 'OI Impresso'}
                <span className="size-2 rounded-full bg-emerald-500" />
              </SheetTitle>
            </SheetHeader>
            <nav className="flex-1 overflow-y-auto py-2">
              <MenuList
                items={shell.menu}
                currentUrl={url}
                onNavigate={() => setMobileOpen(false)}
              />
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
            Main
            ====================================================================== */}
        <div className="flex flex-1 flex-col min-w-0 h-full overflow-hidden">
          <header className="flex h-12 items-center gap-3 border-b border-border bg-background px-4">
            <Sheet open={mobileOpen} onOpenChange={setMobileOpen}>
              <SheetTrigger asChild>
                <Button variant="ghost" size="icon" className="md:hidden" aria-label="Abrir menu">
                  <MenuIcon size={18} />
                </Button>
              </SheetTrigger>
            </Sheet>
            <div className="flex-1" />
            <UserQuickMenu />
          </header>

          {/* ModuleTopNav: barra horizontal com sub-items do módulo — fonte
              independente da sidebar, alimentada por Resources/menus/topnav.php */}
          {effectiveModuleNav && effectiveModuleNav.items.length > 0 && (
            <ModuleTopNav
              items={effectiveModuleNav.items}
              activeHref={effectiveModuleNav.activeHref}
              moduleLabel={effectiveModuleNav.moduleLabel}
              moduleIcon={effectiveModuleNav.moduleIcon}
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
// MenuList — lista de módulos accordion
// ============================================================================
function MenuList({
  items,
  currentUrl,
  onNavigate,
}: {
  items: MenuItem[];
  currentUrl: string;
  onNavigate?: () => void;
}) {
  return (
    <ul className="flex flex-col gap-0.5 px-2">
      {items.map((mod, i) => (
        <MenuEntry
          key={`${mod.label}-${i}`}
          item={mod}
          currentUrl={currentUrl}
          onNavigate={onNavigate}
        />
      ))}
    </ul>
  );
}

function MenuEntry({
  item,
  currentUrl,
  onNavigate,
  depth = 0,
}: {
  item: MenuItem;
  currentUrl: string;
  onNavigate?: () => void;
  depth?: number;
}) {
  const hasChildren = (item.children?.length ?? 0) > 0;
  const currentPath = currentUrl.split('?')[0]?.split('#')[0] ?? currentUrl;

  const hrefMatches = (candidate: string | undefined): boolean => {
    if (!candidate || candidate === '#') return false;
    return currentPath === candidate
      || currentPath.startsWith(candidate + '?')
      || currentPath.startsWith(candidate + '/');
  };

  const isSelfActive = hrefMatches(item.href);
  const childActive = useMemo(
    () => (item.children ?? []).some((c) => hrefMatches(c.href)),
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [item.children, currentUrl],
  );
  const isActive = isSelfActive || childActive;

  const [expanded, setExpanded] = useState(isActive);
  useEffect(() => {
    if (isActive) setExpanded(true);
  }, [isActive]);

  const paddingClass = depth === 0 ? 'px-3 py-2' : 'pl-9 pr-3 py-1.5';
  const iconSize = depth === 0 ? 16 : 14;

  // Sem children — link direto
  if (!hasChildren) {
    const href = item.href ?? '#';
    const asInertia = item.inertia ?? true;
    const className = cn(
      'flex items-center gap-2 rounded-md text-sm transition-colors',
      paddingClass,
      isSelfActive
        ? 'bg-primary/10 text-primary font-medium'
        : 'text-foreground/80 hover:bg-accent hover:text-accent-foreground',
    );
    const content = (
      <>
        <Icon name={item.icon} size={iconSize} className="shrink-0" />
        <span className="flex-1 truncate">{item.label}</span>
        {item.badge != null && (
          <span className="rounded-full bg-destructive px-1.5 py-0.5 text-[10px] font-medium text-destructive-foreground">
            {item.badge}
          </span>
        )}
      </>
    );
    return (
      <li>
        {asInertia && href !== '#' ? (
          <Link href={href} onClick={onNavigate} className={className}>{content}</Link>
        ) : (
          <a href={href} onClick={onNavigate} className={className}>{content}</a>
        )}
      </li>
    );
  }

  // Com children — botão expansível
  return (
    <li>
      <button
        type="button"
        onClick={() => setExpanded((v) => !v)}
        className={cn(
          'w-full flex items-center gap-2 rounded-md text-sm transition-colors',
          paddingClass,
          isActive
            ? 'text-primary font-medium'
            : 'text-foreground/80 hover:bg-accent hover:text-accent-foreground',
        )}
        aria-expanded={expanded}
      >
        <Icon name={item.icon} size={iconSize} className="shrink-0" />
        <span className="flex-1 text-left truncate">{item.label}</span>
        {item.badge != null && (
          <span className="rounded-full bg-destructive px-1.5 py-0.5 text-[10px] font-medium text-destructive-foreground">
            {item.badge}
          </span>
        )}
        {expanded ? (
          <ChevronDown size={14} className="shrink-0 opacity-60" />
        ) : (
          <ChevronRight size={14} className="shrink-0 opacity-60" />
        )}
      </button>
      {expanded && (
        <ul className="flex flex-col gap-0.5 mt-0.5">
          {item.children!.map((child, i) => (
            <MenuEntry
              key={`${child.label}-${i}`}
              item={child}
              currentUrl={currentUrl}
              onNavigate={onNavigate}
              depth={depth + 1}
            />
          ))}
        </ul>
      )}
    </li>
  );
}

// ============================================================================
// UserQuickMenu (topbar direita — redundante com rodapé, útil em mobile)
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

function getInitials(name: string): string {
  return name.split(' ').filter(Boolean).slice(0, 2).map((s) => s[0]?.toUpperCase() ?? '').join('');
}
