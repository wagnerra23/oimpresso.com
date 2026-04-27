// @memcofre
//   layout: AppShellV2 (Cockpit)
//   adrs: UI-0008 (cockpit como layout-mae do ERP)
//   nota: layout-mae 3-colunas (Sidebar 260 + Main 1fr + LinkedApps 320). Usa
//         shell.menu via Inertia shared props. Reusavel por qualquer pagina
//         core do ERP — substitui AppShell legado pra fluxo operacional.

import { Head, usePage } from '@inertiajs/react';
import { ReactNode, useEffect, useState } from 'react';
import { ChevronDown } from 'lucide-react';

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

interface AppShellV2Props extends CockpitShellProps {
  /** Título da aba do navegador */
  title?: string;
  /** Conteúdo do main column (chat / dashboard / lista CRUD / etc) */
  children: ReactNode;
  /** Conversa em foco (opcional) — alimenta LinkedAppsPanel + breadcrumb */
  conversaFoco?: ConversaFoco;
  /** Id da conversa ativa pra highlight na sidebar */
  activeConvId?: string;
  /** Handler quando usuário clica em conversa na sidebar */
  onSelectConv?: (id: string) => void;
  /** Override do breadcrumb — array de strings ou React nodes. Default: business / Chat / conversaFoco.titulo */
  breadcrumb?: ReactNode[];
}

export default function AppShellV2({
  title,
  children,
  business,
  user,
  conversas,
  conversaFoco,
  activeConvId,
  onSelectConv,
  breadcrumb,
}: AppShellV2Props) {
  // Pega o menu compartilhado do shell (LegacyMenuAdapter via Inertia share)
  const page = usePage();
  const shellMenu: ShellMenuItem[] =
    ((page.props as { shell?: { menu?: ShellMenuItem[] } })?.shell?.menu) ?? [];
  const superadminItems = shellMenu.filter((i) => isSuperadminMenu(i.label));

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

  // ── Breadcrumb default (business / Chat / titulo da conversa em foco)
  const defaultCrumb: ReactNode[] = [
    business.nome,
    <span className="bc-cur" key="cur">Chat</span>,
    ...(conversaFoco ? [conversaFoco.titulo] : []),
  ];
  const crumb = breadcrumb ?? defaultCrumb;

  return (
    <>
      {title && <Head title={title} />}
      <div
        className="cockpit"
        data-linked={linkedCollapsed ? 'off' : 'on'}
        data-vibe={vibe}
        data-density={densityLabel}
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
