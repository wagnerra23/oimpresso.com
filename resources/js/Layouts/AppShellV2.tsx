// @memcofre
//   layout: AppShellV2 (Cockpit)
//   adrs: UI-0008, ADR 0039 (cockpit como layout-mae do ERP)
//   nota: layout-mae 3-colunas (Sidebar 260 + Main 1fr + LinkedApps 320). Usa
//         shell.menu via Inertia shared props. Shell ÚNICO do ERP em React —
//         AppShell legado removido em 2026-05-04 (ver git log).

import { Head, usePage } from '@inertiajs/react';
import { ReactNode, useEffect, useRef, useState } from 'react';
import {
  Activity,
  AlertTriangle,
  Bell,
  BookOpen,
  Brain,
  Building2,
  CalendarRange,
  CheckSquare,
  ChevronDown,
  ClipboardList,
  Compass,
  Flag,
  FolderKanban,
  GitBranch,
  Inbox,
  KanbanSquare,
  LayoutDashboard,
  List,
  type LucideIcon,
  MessageSquare,
  Search,
  Settings,
  ShieldCheck,
  Smartphone,
  Target,
  TrendingDown,
  TrendingUp,
  Users,
  Wrench,
  Zap,
} from 'lucide-react';

// Mapa estático dos ícones usados em topnav.php — evita `import * as Lucide`
// que quebraria tree-shake e adicionaria ~330 KB ao bundle.
// Pra adicionar ícone novo: importar acima + adicionar entry aqui.
const TOPNAV_ICON_MAP: Record<string, LucideIcon> = {
  Activity,
  AlertTriangle,
  Bell,
  BookOpen,
  Brain,
  Building2,
  CalendarRange,
  CheckSquare,
  ClipboardList,
  Compass,
  Flag,
  FolderKanban,
  GitBranch,
  Inbox,
  KanbanSquare,
  LayoutDashboard,
  List,
  MessageSquare,
  Search,
  Settings,
  ShieldCheck,
  Smartphone,
  Target,
  TrendingDown,
  TrendingUp,
  Users,
  Wrench,
  Zap,
};

import { useAutoModuleNav } from '@/Hooks/usePageProps';
import CommandPalette from '@/Components/CommandPalette';

import '../../css/cockpit.css';

import {
  CompanyPicker,
  SidebarFooter,
  SidebarMenu,
} from '@/Components/cockpit/Sidebar';
import { LinkedAppsPanel } from '@/Components/cockpit/LinkedApps';
import { NfeCertBadge } from '@/Components/cockpit/NfeCertBadge';
import { TweaksPanel } from '@/Components/cockpit/TweaksPanel';
import {
  CockpitShellProps,
  ConversaFoco,
  LS,
  ShellMenuItem,
  Vibe,
  isSuperadminMenu,
  isUserMenuItem,
} from '@/Components/cockpit/shared';

interface AppShellV2Props {
  /** Título da aba do navegador */
  title?: string;
  /** Conteúdo do main column (chat / dashboard / lista CRUD / etc) */
  children: ReactNode;
  /**
   * Shell props (business + user). OPCIONAL — se omitido, lê de
   * `shell.cockpit` via Inertia shared props (HandleInertiaRequests::share).
   * Páginas que já têm os dados (ex.: Copiloto/Cockpit que recebe via Props
   * próprios) podem passar pra evitar dupla query.
   */
  business?: { nome: string; opcoes: Array<{ id: number; nome: string; iniciais: string; ativa: boolean }> };
  user?: { nome: string; nomeCurto: string; email: string; cargo: string; iniciais: string };
  /** Conversas pra Sidebar Chat. Opcional — default vazio (Chat tab fica só com atalhos). */
  conversas?: { fixadas: ConversaResumo[]; rotinas: Rotina[]; recentes: ConversaResumo[] };
  /** Conversa em foco (opcional) — alimenta LinkedAppsPanel + breadcrumb */
  conversaFoco?: ConversaFoco;
  /** Id da conversa ativa pra highlight na sidebar */
  activeConvId?: string;
  /** Handler quando usuário clica em conversa na sidebar */
  onSelectConv?: (id: string) => void;
  /** Override do breadcrumb — array de strings ou React nodes. Default: business / Chat / conversaFoco.titulo */
  breadcrumb?: ReactNode[];
  /** Alternativa ao `breadcrumb` em formato `{ label, href? }[]` (compat). Convertido internamente. */
  breadcrumbItems?: Array<{ label: string; href?: string }>;
  /**
   * Esconde a barra superior (breadcrumb + topnav do módulo) inteira.
   * Usado em telas com header próprio que torna a topbar redundante (ex.: Caixa Unificada V4).
   * Default: false.
   */
  hideTopbar?: boolean;
}

// ── BreadcrumbModuleDropdown ──────────────────────────────────────────
// Segundo item do breadcrumb vira dropdown listando outras telas do módulo.
// Alimentado pelo hook useAutoModuleNav() que lê de shell.topnavs[Modulo]
// (Modules/<Nome>/Resources/menus/topnav.php — ADR arq/0011).
function BreadcrumbModuleDropdown({
  label,
  items,
}: {
  label: string;
  items: Array<{ label: string; href?: string; icon?: string }>;
}) {
  const [open, setOpen] = useState(false);
  const ref = useRef<HTMLSpanElement>(null);
  useEffect(() => {
    if (!open) return;
    const handler = (e: MouseEvent) => {
      if (ref.current && !ref.current.contains(e.target as Node)) setOpen(false);
    };
    document.addEventListener('mousedown', handler);
    return () => document.removeEventListener('mousedown', handler);
  }, [open]);

  // Sem items: renderiza estático (não vira dropdown)
  if (!items || items.length === 0) {
    return <span className="bc-cur">{label}</span>;
  }

  return (
    <span className="bc-mod" ref={ref} style={{ position: 'relative' }}>
      <button
        type="button"
        className="bc-mod-btn"
        onClick={() => setOpen((v) => !v)}
        aria-expanded={open}
      >
        {label}
        <ChevronDown size={11} style={{ marginLeft: 3, opacity: 0.6 }} />
      </button>
      {open && (
        <div className="bc-mod-dd">
          {items.map((it, i) => {
            const IconCmp = it.icon ? TOPNAV_ICON_MAP[it.icon] : undefined;
            return (
              <a
                key={i}
                href={it.href ?? '#'}
                className="bc-mod-dd-i"
              >
                {IconCmp ? <IconCmp size={14} /> : <span style={{ width: 14, display: 'inline-block' }} />}
                <span>{it.label}</span>
              </a>
            );
          })}
        </div>
      )}
    </span>
  );
}

interface CockpitShellPropsRaw {
  businessNome?: string;
  businesses?: Array<{ id: number; nome: string; iniciais: string; ativa: boolean }>;
  usuarioNome?: string;
  usuarioNomeCurto?: string;
  usuarioEmail?: string;
  usuarioCargo?: string;
  usuarioIniciais?: string;
}

export default function AppShellV2({
  title,
  children,
  business: businessProp,
  user: userProp,
  conversas: conversasProp,
  conversaFoco,
  activeConvId,
  onSelectConv,
  breadcrumb,
  breadcrumbItems,
  hideTopbar = false,
}: AppShellV2Props) {
  // Pega o menu compartilhado do shell (LegacyMenuAdapter via Inertia share)
  const page = usePage();
  const allProps = page.props as {
    shell?: {
      menu?: ShellMenuItem[];
      cockpit?: CockpitShellPropsRaw;
    };
    auth?: { user?: { ui_theme?: 'light' | 'dark' | null } };
  };
  const shellProps = allProps?.shell;
  const shellMenu: ShellMenuItem[] = shellProps?.menu ?? [];
  // Tema do user — aplicado no .cockpit pra cores ficarem coerentes com
  // shadcn (que usa dark mode automatico via classe 'dark' no <html>).
  const userTheme = allProps?.auth?.user?.ui_theme ?? 'light';
  const superadminItems = shellMenu.filter((i) => isSuperadminMenu(i.label));
  const userMenuItems = shellMenu.filter((i) => isUserMenuItem(i.label));

  // Fallback pra business + user via shell.cockpit (Inertia shared) quando a
  // página não passa via props. Isso permite páginas MemCofre/Financeiro/etc
  // usar <AppShellV2 title="...">{content}</...> sem boilerplate.
  const cockpitShared = shellProps?.cockpit;
  const business = businessProp ?? {
    nome: cockpitShared?.businessNome ?? 'Oimpresso',
    opcoes: cockpitShared?.businesses ?? [],
  };
  const user = userProp ?? {
    nome: cockpitShared?.usuarioNome ?? 'Usuário',
    nomeCurto: cockpitShared?.usuarioNomeCurto ?? 'Usuário',
    email: cockpitShared?.usuarioEmail ?? '',
    cargo: cockpitShared?.usuarioCargo ?? 'Usuário',
    iniciais: cockpitShared?.usuarioIniciais ?? '?',
  };
  // `conversas` aceito por compat — sidebar single-pane (UI-0011) NÃO renderiza
  // mais conversas. Páginas que precisam do conv switcher devem renderizar lista
  // própria dentro do main column (Pages/Copiloto/Chat.tsx faz isso).
  // eslint-disable-next-line @typescript-eslint/no-unused-vars
  const _conversasIgnored = conversasProp;

  // ── State do shell (apps colapsado + conversa ativa fallback)
  // Sidebar tab REMOVIDO em 2026-05-05 — sidebar agora é single-pane com Menu.
  const [linkedCollapsed, setLinkedCollapsed] = useState<boolean>(() => {
    if (typeof window === 'undefined') return false;
    return localStorage.getItem(LS.LINKED) === '1';
  });

  // ── Command Palette global (PMG-002, ADR 0100) — atalho Cmd/Ctrl+K
  const [paletteOpen, setPaletteOpen] = useState<boolean>(false);

  useEffect(() => {
    function onKey(e: KeyboardEvent) {
      // Cmd+K (Mac) ou Ctrl+K (Windows/Linux)
      if ((e.metaKey || e.ctrlKey) && (e.key === 'k' || e.key === 'K')) {
        e.preventDefault();
        setPaletteOpen((v) => !v);
      }
    }
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, []);

  // Activeconv-id fallback se a página não fornecer (controlado externamente)
  const [internalActiveConv, setInternalActiveConv] = useState<string>(() => {
    if (typeof window === 'undefined') return conversaFoco?.id ?? '';
    return localStorage.getItem(LS.CONV) || conversaFoco?.id || '';
  });
  const effectiveActiveConvId = activeConvId ?? internalActiveConv;
  const effectiveOnSelectConv = onSelectConv ?? setInternalActiveConv;

  // ── Tweaks state (vibe / densidade / accent hue)
  const [tweaksOpen, setTweaksOpen] = useState<boolean>(() => {
    if (typeof window === 'undefined') return false;
    return localStorage.getItem(LS.TW_OPEN) === '1';
  });
  const [vibe, setVibe] = useState<Vibe>(() => {
    if (typeof window === 'undefined') return 'workspace';
    return (localStorage.getItem(LS.TW_VIBE) as Vibe) || 'workspace';
  });
  const [density, setDensity] = useState<number>(() => {
    if (typeof window === 'undefined') return 50;
    const v = Number(localStorage.getItem(LS.TW_DENSITY));
    return isFinite(v) && v > 0 ? v : 50;
  });
  const [accentHue, setAccentHue] = useState<number>(() => {
    if (typeof window === 'undefined') return 220;
    const v = Number(localStorage.getItem(LS.TW_HUE));
    return isFinite(v) && v > 0 ? v : 220;
  });

  // ── Persistência localStorage
  // (LS.TAB removido — sidebar single-pane não tem toggle Chat/Menu mais)
  useEffect(() => { localStorage.setItem(LS.LINKED, linkedCollapsed ? '1' : '0'); }, [linkedCollapsed]);
  useEffect(() => {
    if (effectiveActiveConvId) localStorage.setItem(LS.CONV, effectiveActiveConvId);
  }, [effectiveActiveConvId]);
  useEffect(() => { localStorage.setItem(LS.TW_VIBE, vibe); }, [vibe]);
  useEffect(() => { localStorage.setItem(LS.TW_DENSITY, String(density)); }, [density]);
  useEffect(() => { localStorage.setItem(LS.TW_HUE, String(accentHue)); }, [accentHue]);
  useEffect(() => { localStorage.setItem(LS.TW_OPEN, tweaksOpen ? '1' : '0'); }, [tweaksOpen]);

  // ── CSS vars dinâmicas (densidade + accentHue)
  const cockpitStyle: React.CSSProperties = {
    ['--row-h' as never]: `${26 + (density / 100) * 16}px`,
    ['--card-pad' as never]: `${8 + (density / 100) * 8}px`,
    ['--accent' as never]: `oklch(0.58 0.12 ${accentHue})`,
    ['--accent-2' as never]: `oklch(0.66 0.12 ${accentHue})`,
    ['--accent-soft' as never]: `oklch(0.94 0.04 ${accentHue})`,
    ['--bubble-me' as never]: `oklch(0.58 0.12 ${accentHue})`,
  };
  const densityLabel = density < 30 ? 'skim' : density > 70 ? 'briefing' : 'normal';

  // ── Module nav (auto-detecta o módulo ativo via URL e popula dropdown)
  // Hook compartilhado — alimentado por Resources/menus/topnav.php
  // de cada modulo (ADR arq/0011).
  const moduleNav = useAutoModuleNav();
  const moduleSlug = moduleNav?.moduleLabel ?? 'Chat';
  const moduleItems = moduleNav?.items ?? [];
  // Path atual normalizado pra match de active state no topnav horizontal
  const currentPath = (page.url.split('?')[0]?.split('#')[0] ?? page.url) as string;

  // ── Breadcrumb computado
  let crumb: ReactNode[];
  if (breadcrumb) {
    crumb = breadcrumb;
  } else if (breadcrumbItems) {
    // Formato compat: { label, href? }[]
    // Heurística: PRIMEIRO item vira dropdown se houver topnav do módulo ativo
    // (primeiro item costuma ser o nome do módulo).
    crumb = breadcrumbItems.map((b, i) => {
      const isFirst = i === 0;
      const isCurrent = i === breadcrumbItems.length - 1;
      if (isFirst && moduleItems.length > 0) {
        return <BreadcrumbModuleDropdown key={i} label={b.label} items={moduleItems} />;
      }
      return b.href ? (
        <a key={i} href={b.href} className="bc-link">{b.label}</a>
      ) : (
        <span key={i} className={isCurrent ? 'bc-cur' : ''}>{b.label}</span>
      );
    });
  } else {
    // Default: business / [moduleSlug com dropdown se houver items] / [conversaFoco.titulo]
    crumb = [
      business.nome,
      <BreadcrumbModuleDropdown
        key="mod"
        label={moduleSlug}
        items={moduleItems}
      />,
      ...(conversaFoco ? [conversaFoco.titulo] : []),
    ];
  }

  return (
    <>
      {title && <Head title={title} />}
      <div
        className="cockpit"
        data-linked={!conversaFoco || linkedCollapsed ? 'off' : 'on'}
        data-vibe={vibe}
        data-density={densityLabel}
        data-theme={userTheme}
        style={cockpitStyle}
      >
        {/* SIDEBAR — single-pane (UI-0011, 2026-05-05). Toggle Chat/Menu removido. */}
        <aside className="sb">
          <div className="sb-top">
            <CompanyPicker businesses={business.opcoes} fallbackNome={business.nome} />
          </div>
          {/* Alerta cert NFe vencendo/vencido (US-NFE-001 último item) — só renderiza
              em estados críticos via shared prop shell.nfe_cert_status. Silencioso
              quando OK ou business não emite NFe. */}
          <NfeCertBadge />
          <div className="sb-body">
            <SidebarMenu items={shellMenu} />
          </div>
          <SidebarFooter
            nome={user.nome}
            nomeCurto={user.nomeCurto}
            email={user.email}
            cargo={user.cargo}
            iniciais={user.iniciais}
            superadminItems={superadminItems}
            userMenuItems={userMenuItems}
            vibe={vibe}
            onVibe={setVibe}
          />
        </aside>

        {/* MAIN COLUMN */}
        <div className="main">
          {!hideTopbar && (
          <header className="topbar">
            <div className="bc">
              {crumb.map((part, i) => (
                <span key={i} style={{ display: 'inline-flex', alignItems: 'center', gap: 8 }}>
                  {i > 0 && <span className="bc-sep">/</span>}
                  {typeof part === 'string' ? <span>{part}</span> : part}
                </span>
              ))}
            </div>

            {/* TopNav horizontal do módulo INLINE com breadcrumb (Wagner 2026-05-08:
                "fica mais fluidico").
                Auto-detect via useAutoModuleNav() lendo shell.topnavs[Module]
                alimentado por Modules/<Mod>/Resources/menus/topnav.php
                e config/core_topnavs.php (ADR 0107 §gap topnav). */}
            {moduleItems.length > 0 && (
              <>
                <span className="bc-sep" style={{ margin: '0 4px' }}>|</span>
                <nav
                  className="topnav-module-inline"
                  aria-label="Navegação do módulo"
                  style={{ display: 'flex', alignItems: 'center', gap: 4, flexWrap: 'wrap' }}
                >
                  {moduleItems.map((item, i) => {
                    const href = item.href ?? '#';
                    const itemRoot = '/' + (href.split('/').slice(1, 3).join('/'));
                    const isActive = currentPath.startsWith(itemRoot) && itemRoot !== '/';
                    const Icon = item.icon ? TOPNAV_ICON_MAP[item.icon] : undefined;
                    return (
                      <a
                        key={i}
                        href={href}
                        className={`topnav-chip${isActive ? ' active' : ''}`}
                        style={{
                          display: 'inline-flex',
                          alignItems: 'center',
                          gap: 5,
                          padding: '4px 10px',
                          borderRadius: 6,
                          fontSize: 12.5,
                          fontWeight: isActive ? 600 : 500,
                          color: isActive ? 'var(--text)' : 'var(--text-dim)',
                          background: isActive ? 'var(--surface-2)' : 'transparent',
                          textDecoration: 'none',
                          transition: 'background 120ms, color 120ms',
                          whiteSpace: 'nowrap',
                        }}
                        onMouseEnter={(e) => {
                          if (!isActive) e.currentTarget.style.background = 'var(--surface-2)';
                        }}
                        onMouseLeave={(e) => {
                          if (!isActive) e.currentTarget.style.background = 'transparent';
                        }}
                      >
                        {Icon && <Icon size={13} />}
                        <span>{item.label}</span>
                      </a>
                    );
                  })}
                </nav>
              </>
            )}

            {/* Toggle LinkedApps — só renderiza quando há painel pra alternar.
               Sem `conversaFoco`, o LinkedAppsPanel não é montado (linha ~487)
               então o botão chevron ficava órfão e confundia o usuário com o
               chevron de páginas que têm sidebar própria (ex: Whatsapp UX
               polish round 2 — Wagner 2026-05-11). */}
            {conversaFoco && (
              <div style={{ marginLeft: 'auto', display: 'flex', gap: 8 }}>
                <button
                  className="icon-btn"
                  type="button"
                  title={linkedCollapsed ? 'Mostrar Apps' : 'Esconder Apps'}
                  onClick={() => setLinkedCollapsed((v) => !v)}
                  style={{
                    background: 'transparent', border: 'none', cursor: 'pointer',
                    width: 28, height: 28, borderRadius: 6,
                    display: 'grid', placeItems: 'center', color: 'var(--text-dim)',
                  }}
                >
                  <ChevronDown
                    size={14}
                    style={{ transform: linkedCollapsed ? 'rotate(90deg)' : 'rotate(-90deg)' }}
                  />
                </button>
              </div>
            )}
          </header>
          )}

          <div className="main-body">
            {children}
          </div>
        </div>

        {/* APPS VINCULADOS (opcional, só aparece se tem conversaFoco) */}
        {!linkedCollapsed && conversaFoco && <LinkedAppsPanel conv={conversaFoco} />}

        {/* COMMAND PALETTE (Cmd+K global — PMG-002, ADR 0100) */}
        <CommandPalette open={paletteOpen} onOpenChange={setPaletteOpen} />

        {/* TWEAKS PANEL (flutuante, fora do grid) */}
        <TweaksPanel
          vibe={vibe}
          onVibe={setVibe}
          density={density}
          onDensity={setDensity}
          hue={accentHue}
          onHue={setAccentHue}
          open={tweaksOpen}
          onToggle={() => setTweaksOpen((v) => !v)}
        />
      </div>
    </>
  );
}
