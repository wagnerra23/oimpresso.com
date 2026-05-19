// @memcofre
//   layout: AppShellV2 (Cockpit)
//   adrs: UI-0008 (cockpit como layout-mae do ERP)
//   nota: layout-mae 3-colunas (Sidebar 260 + Main 1fr + LinkedApps 320). Usa
//         shell.menu via Inertia shared props. Reusavel por qualquer pagina
//         core do ERP — substitui AppShell legado pra fluxo operacional.

import { Head, usePage } from '@inertiajs/react';
import { ReactNode, useEffect, useRef, useState } from 'react';
import { ChevronDown } from 'lucide-react';

import { useAutoModuleNav } from '@/Hooks/usePageProps';

import '../../css/cockpit.css';

import {
  CompanyPicker,
  SidebarChat,
  SidebarFooter,
  SidebarMenu,
  SidebarTabs,
} from '@/Components/cockpit/Sidebar';
import { LinkedAppsPanel } from '@/Components/cockpit/LinkedApps';
import { TweaksPanel } from '@/Components/cockpit/TweaksPanel';
import {
  CockpitShellProps,
  ConversaFoco,
  LS,
  ShellMenuItem,
  Vibe,
  isSuperadminMenu,
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
  /** Alternativa ao `breadcrumb` em formato AppShell legado (compat). Convertido internamente. */
  breadcrumbItems?: Array<{ label: string; href?: string }>;
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
          {items.map((it, i) => (
            <a
              key={i}
              href={it.href ?? '#'}
              className="bc-mod-dd-i"
            >
              {it.label}
            </a>
          ))}
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
  const conversas = conversasProp ?? { fixadas: [], rotinas: [], recentes: [] };

  // ── State do shell (sidebar tab + apps colapsado + conversa ativa fallback)
  const [tab, setTab] = useState<'chat' | 'menu'>(() => {
    if (typeof window === 'undefined') return 'chat';
    return (localStorage.getItem(LS.TAB) as 'chat' | 'menu') || 'chat';
  });
  const [linkedCollapsed, setLinkedCollapsed] = useState<boolean>(() => {
    if (typeof window === 'undefined') return false;
    return localStorage.getItem(LS.LINKED) === '1';
  });

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
  useEffect(() => { localStorage.setItem(LS.TAB, tab); }, [tab]);
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
  // Reusa o hook que ja existe pro AppShell legado — alimentado por
  // Resources/menus/topnav.php de cada modulo (ADR arq/0011).
  const moduleNav = useAutoModuleNav();
  const moduleSlug = moduleNav?.moduleLabel ?? 'Chat';
  const moduleItems = moduleNav?.items ?? [];

  // ── Breadcrumb computado
  let crumb: ReactNode[];
  if (breadcrumb) {
    crumb = breadcrumb;
  } else if (breadcrumbItems) {
    // Compat AppShell legado: { label, href? }[]
    // Heurística: PRIMEIRO item vira dropdown se houver topnav do módulo ativo
    // (segundo item do shell é o nome do módulo no padrão AppShell legado).
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
        {/* SIDEBAR */}
        <aside className="sb">
          <div className="sb-top">
            <CompanyPicker businesses={business.opcoes} fallbackNome={business.nome} />
          </div>
          <SidebarTabs tab={tab} onTab={setTab} />
          <div className="sb-body">
            {tab === 'chat' ? (
              <SidebarChat
                fixadas={conversas.fixadas}
                rotinas={conversas.rotinas}
                recentes={conversas.recentes}
                activeId={effectiveActiveConvId}
                onSelect={effectiveOnSelectConv}
              />
            ) : (
              <SidebarMenu items={shellMenu} />
            )}
          </div>
          <SidebarFooter
            nome={user.nome}
            nomeCurto={user.nomeCurto}
            email={user.email}
            cargo={user.cargo}
            iniciais={user.iniciais}
            superadminItems={superadminItems}
          />
        </aside>

        {/* MAIN COLUMN */}
        <div className="main">
          <header className="topbar">
            <div className="bc">
              {crumb.map((part, i) => (
                <span key={i} style={{ display: 'inline-flex', alignItems: 'center', gap: 8 }}>
                  {i > 0 && <span className="bc-sep">/</span>}
                  {typeof part === 'string' ? <span>{part}</span> : part}
                </span>
              ))}
            </div>
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
          </header>
          <div className="main-body">
            {children}
          </div>
        </div>

        {/* APPS VINCULADOS (opcional, só aparece se tem conversaFoco) */}
        {!linkedCollapsed && conversaFoco && <LinkedAppsPanel conv={conversaFoco} />}

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
