// @memcofre
//   modulo: Cockpit (Sidebar)
//   adrs: UI-0008 (cockpit como layout-mae), UI-0011 (sidebar single-pane)
//   nota: sidebar single-pane (260px). Toggle Chat/Menu REMOVIDO em 2026-05-05.
//         Conteúdo: CompanyPicker (topo) + SidebarMenu (corpo) + SidebarFooter
//         com user dropdown + Superadmin accordion (rodapé). SidebarChat e
//         SidebarTabs removidos — conv switcher migrou pra Pages/Copiloto/Chat.tsx.

import { useEffect, useRef, useState } from 'react';
import {
  ArrowRightLeft, BarChart3, Bell, Bot, Box, Calculator, Calendar, Check,
  ChevronDown, ChevronRight, ChevronUp, ClipboardList, CreditCard, FileSearch,
  Hash, Home, Inbox, Keyboard, LogOut, MessageSquare, Monitor, Moon, Package,
  PackageCheck, Plug, Receipt, Search, Settings, ShieldAlert, ShieldCheck,
  ShoppingCart, Sun, Users, Utensils, User, Wallet,
  type LucideIcon,
} from 'lucide-react';

import { useTheme } from '@/Hooks/useTheme';

// Mapa estático de ícones por label do shell.menu (LegacyMenuAdapter entrega
// items flat sem campo `icon`; resolvemos via lookup case-insensitive).
// Items não mapeados caem em Hash genérico. Adicionar aqui ao escalar.
const MENU_ICON_MAP: Record<string, LucideIcon> = {
  iniciar: Home, início: Home, home: Home, dashboard: Home,
  contatos: Users, clientes: Users, crm: Users,
  produtos: Package,
  compras: ShoppingCart,
  vender: Receipt, vendas: Receipt,
  'consulta de os': FileSearch,
  'ordens de serviço': FileSearch,
  despesas: CreditCard,
  'contas de pagamento': Wallet,
  accounting: Calculator, contabilidade: Calculator,
  relatórios: BarChart3,
  reservas: Calendar,
  cocina: Utensils,
  pedidos: ClipboardList,
  'modelos de notificação': Bell,
  configurações: Settings,
  copiloto: Bot,
  ads: ShieldCheck,
  conector: Plug,
  'transferências de ações': ArrowRightLeft,
  'ajuste de estoque': PackageCheck,
  'gestão de ativos': Box,
  'gerenciamento de usuários': Users,
};

function findMenuIcon(label: string): LucideIcon {
  return MENU_ICON_MAP[label.trim().toLowerCase()] ?? Hash;
}

import {
  BusinessOpt,
  LS,
  ShellMenuItem,
  gradientFor,
  isSuperadminMenu,
} from './shared';

// ── Mapeamento item → grupo (lookup table). Ratificado por Wagner 2026-05-05.
// Itens não mapeados caem no grupo "MAIS" (collapse fechado por default).
// Quando LegacyMenuAdapter ganhar campo `group` no MenuItem, esse mapping
// migra pro backend e este lookup é deletado.
const SIDEBAR_GROUPS: Array<{ key: string; label: string; items: string[] }> = [
  {
    key: 'office',
    label: 'OFFICEIMPRESSO',
    items: ['Iniciar', 'Início', 'Home', 'Dashboard', 'Consulta de OS', 'Ordens de Serviço', 'Contatos', 'Clientes', 'Produtos', 'Vender', 'vender', 'Vendas', 'Orçamentos'],
  },
  {
    key: 'fin',
    label: 'FINANCEIRO',
    items: ['Despesas', 'Contas de pagamento', 'Accounting', 'Contabilidade', 'Financeiro'],
  },
  {
    key: 'estoque',
    label: 'ESTOQUE',
    items: ['Compras', 'Transferências de ações', 'Ajuste de estoque', 'Gestão de ativos'],
  },
  {
    key: 'rel',
    label: 'RELATÓRIOS',
    items: ['Relatórios', 'Reservas', 'Pedidos', 'Cocina'],
  },
  {
    key: 'ia',
    label: 'IA & PRODUTIVIDADE',
    items: ['Copiloto', 'ADS', 'Conector', 'CRM', 'Crm'],
  },
  {
    key: 'config',
    label: 'CONFIGURAÇÕES',
    items: ['Gerenciamento de usuários', 'Configurações', 'Modelos de notificação'],
  },
];

function findGroupKey(label: string): string {
  const norm = label.trim();
  for (const g of SIDEBAR_GROUPS) {
    if (g.items.some((i) => i.toLowerCase() === norm.toLowerCase())) return g.key;
  }
  return 'mais';  // fallback — group "MAIS" no final
}

// ── CompanyPicker ──────────────────────────────────────────────────────

export function CompanyPicker({
  businesses,
  fallbackNome,
}: {
  businesses: BusinessOpt[];
  fallbackNome: string;
}) {
  const [open, setOpen] = useState(false);
  const ref = useRef<HTMLDivElement>(null);
  const ativa = businesses.find((b) => b.ativa) ?? businesses[0];
  const nome = ativa?.nome ?? fallbackNome;
  const iniciais = ativa?.iniciais ?? fallbackNome.slice(0, 2).toUpperCase();
  const grad = ativa ? gradientFor(ativa.id) : gradientFor(1);

  useEffect(() => {
    if (!open) return;
    const handler = (e: MouseEvent) => {
      if (ref.current && !ref.current.contains(e.target as Node)) setOpen(false);
    };
    document.addEventListener('mousedown', handler);
    return () => document.removeEventListener('mousedown', handler);
  }, [open]);

  return (
    <div className="sb-cp" ref={ref}>
      <button
        className="sb-cp-btn"
        type="button"
        onClick={() => setOpen((v) => !v)}
      >
        <span className="avatar" style={{ background: grad }}>
          {iniciais}
        </span>
        <span className="name">{nome}</span>
        <ChevronDown size={14} />
      </button>
      {open && (
        <div className="sb-dd">
          <div className="sb-dd-h">EMPRESAS</div>
          {businesses.length === 0 && (
            <div className="sb-dd-empty">Nenhuma empresa disponível</div>
          )}
          {businesses.map((b) => (
            <div
              key={b.id}
              className={`sb-dd-i ${b.ativa ? 'active' : ''}`}
              onClick={() => {
                if (b.ativa) {
                  setOpen(false);
                  return;
                }
                // TODO Fase 4: POST /copiloto/cockpit/switch-business
                alert(`Switch para "${b.nome}" — em breve (Fase 4 do cockpit).`);
                setOpen(false);
              }}
            >
              <span className="avatar-sm" style={{ background: gradientFor(b.id) }}>
                {b.iniciais}
              </span>
              <span className="name">{b.nome}</span>
              {b.ativa && <Check size={14} className="check" />}
            </div>
          ))}
          <div className="sb-dd-sep" />
          <div className="sb-dd-foot">+ Adicionar empresa</div>
        </div>
      )}
    </div>
  );
}

// SidebarTabs + SidebarChat removidos em 2026-05-05 (UI-0011 single-pane).
// Histórico: dual Chat/Menu introduzido em UI-0008 (2026-04-27). Wagner pediu
// remoção em sessão 2026-05-05 — conteúdo de chat (atalhos + Fixadas + Rotinas
// + Recentes) migrou pra `Pages/Copiloto/Chat.tsx` como master/detail interno.

// ── SidebarMenuItem (recursivo p/ children) ─────────────────────────────

function SidebarMenuItem({ item }: { item: ShellMenuItem }) {
  const [expanded, setExpanded] = useState(false);
  const hasChildren = !!item.children?.length;
  const href = item.href ?? '#';
  const Icon = findMenuIcon(item.label);

  if (hasChildren) {
    return (
      <>
        <button
          type="button"
          className={`sb-item ${expanded ? 'is-open' : ''}`}
          onClick={() => setExpanded((v) => !v)}
          aria-expanded={expanded}
        >
          <Icon size={14} className="ic" />
          <span className="label">{item.label}</span>
          <ChevronDown
            size={11}
            className="sb-item-chev"
            style={{
              transform: expanded ? 'rotate(0)' : 'rotate(-90deg)',
              transition: 'transform 120ms',
              opacity: 0.6,
            }}
          />
        </button>
        {expanded &&
          item.children!.map((c, i) => (
            <a
              key={`${c.label}-${i}`}
              href={c.href ?? '#'}
              className="sb-item sb-item-child"
            >
              <span className="ic dot" />
              <span className="label">{c.label}</span>
            </a>
          ))}
      </>
    );
  }

  return (
    <a href={href} className="sb-item">
      <Icon size={14} className="ic" />
      <span className="label">{item.label}</span>
    </a>
  );
}

// ── SidebarShortcuts — Tarefas + Chat no topo (UI-0011) ─────────────────

function SidebarShortcuts({
  tarefasCount,
  chatCount,
}: {
  tarefasCount?: number;
  chatCount?: number;
}) {
  return (
    <div className="sb-shortcuts">
      <a href="/tarefas" className="sb-shortcut">
        <Inbox size={13} />
        <span className="label">Tarefas</span>
        {!!tarefasCount && <span className="badge">{tarefasCount}</span>}
      </a>
      <a href="/copiloto" className="sb-shortcut">
        <MessageSquare size={13} />
        <span className="label">Chat</span>
        {!!chatCount && <span className="badge">{chatCount}</span>}
      </a>
    </div>
  );
}

// ── SidebarGroup — header com chevron à esquerda + popover suspenso lateral
//    Canon Cowork: chevron antes do label, rotação -90deg fechado / 0 aberto.
//    UI-0011 refinement Wagner 2026-05-05: submenu vira popover suspenso (não
//    inline) pra otimizar espaço vertical. Apenas um grupo aberto por vez. ──

function SidebarGroup({
  groupKey,
  label,
  children,
  openKey,
  onToggle,
}: {
  groupKey: string;
  label: string;
  children: React.ReactNode;
  openKey: string | null;
  onToggle: (key: string | null) => void;
}) {
  const wrapRef = useRef<HTMLDivElement>(null);
  const buttonRef = useRef<HTMLButtonElement>(null);
  const [popPos, setPopPos] = useState<{ top: number; left: number } | null>(null);
  const isOpen = openKey === groupKey;

  // Calcula posição fixed do popover quando abre. Position fixed pra escapar do
  // overflow-y:auto da .sb-body que cortava a versão absolute.
  useEffect(() => {
    if (!isOpen) { setPopPos(null); return; }
    if (!buttonRef.current) return;
    const r = buttonRef.current.getBoundingClientRect();
    setPopPos({ top: r.top, left: r.right + 4 });
  }, [isOpen]);

  // Fecha popover ao clicar fora (considera tanto o group quanto o popover via portal)
  useEffect(() => {
    if (!isOpen) return;
    const handler = (e: MouseEvent) => {
      const target = e.target as Node;
      if (wrapRef.current?.contains(target)) return;
      // popover renderizado fora do wrap (fixed) — checar via classe
      const popoverEl = document.querySelector('.sb-group-popover');
      if (popoverEl?.contains(target)) return;
      onToggle(null);
    };
    document.addEventListener('mousedown', handler);
    return () => document.removeEventListener('mousedown', handler);
  }, [isOpen, onToggle]);

  // Grupo sem label (não usado mais — fallback) — renderiza inline
  if (!label) {
    return <div className="sb-group sb-group-noheader">{children}</div>;
  }

  return (
    <div className={`sb-group ${isOpen ? 'is-open' : ''}`} ref={wrapRef}>
      <button
        ref={buttonRef}
        type="button"
        className="sb-group-h"
        onClick={() => onToggle(isOpen ? null : groupKey)}
        aria-expanded={isOpen}
      >
        <ChevronDown
          size={10}
          className="chev"
          style={{
            transform: isOpen ? 'rotate(0)' : 'rotate(-90deg)',
            transition: 'transform 150ms',
          }}
        />
        <span>{label}</span>
      </button>
      {isOpen && popPos && (
        <div
          className="sb-group-popover"
          style={{ position: 'fixed', top: popPos.top, left: popPos.left }}
        >
          {children}
        </div>
      )}
    </div>
  );
}

// ── SidebarMenu (agrupa shell.menu por scope — UI-0011) ─────────────────

export function SidebarMenu({ items }: { items: ShellMenuItem[] }) {
  if (!items?.length) {
    return (
      <div className="sb-menu-stub">
        <p>Menu vazio — sem items disponíveis.</p>
      </div>
    );
  }
  // Filtra superadmin (vão pro user dropdown no rodapé)
  const principais = items.filter((i) => !isSuperadminMenu(i.label));

  // Agrupa principais por lookup table (preservando ordem dentro do grupo)
  const groupedItems: Record<string, ShellMenuItem[]> = {};
  for (const item of principais) {
    const key = findGroupKey(item.label);
    if (!groupedItems[key]) groupedItems[key] = [];
    groupedItems[key].push(item);
  }

  // Items não mapeados caem em "mais"
  const groupsToRender = [
    ...SIDEBAR_GROUPS.filter((g) => groupedItems[g.key]?.length),
    ...(groupedItems.mais?.length
      ? [{ key: 'mais', label: 'MAIS', items: [] }]
      : []),
  ];

  return <SidebarMenuGrouped groupsToRender={groupsToRender} groupedItems={groupedItems} />;
}

// ── SidebarMenuGrouped — wrapper que gerencia o openKey (radio behavior) ──
function SidebarMenuGrouped({
  groupsToRender,
  groupedItems,
}: {
  groupsToRender: Array<{ key: string; label: string; items: string[] }>;
  groupedItems: Record<string, ShellMenuItem[]>;
}) {
  const [openKey, setOpenKey] = useState<string | null>(null);
  return (
    <div className="sb-menu-grouped">
      <SidebarShortcuts tarefasCount={6} chatCount={3} />
      {groupsToRender.map((g) => (
        <SidebarGroup
          key={g.key}
          groupKey={g.key}
          label={g.label}
          openKey={openKey}
          onToggle={setOpenKey}
        >
          {(groupedItems[g.key] ?? []).map((item, idx) => (
            <SidebarMenuItem key={`${item.label}-${idx}`} item={item} />
          ))}
        </SidebarGroup>
      ))}
    </div>
  );
}

// ── SidebarUserMenu (popup completo: perfil/disponivel/aparencia/etc) ──

function SidebarUserMenu({
  open,
  onClose,
  nome,
  email,
  iniciais,
  superadminItems,
}: {
  open: boolean;
  onClose: () => void;
  nome: string;
  email: string;
  iniciais: string;
  superadminItems: ShellMenuItem[];
}) {
  const ref = useRef<HTMLDivElement>(null);
  useEffect(() => {
    if (!open) return;
    const handler = (e: MouseEvent) => {
      if (ref.current && !ref.current.contains(e.target as Node)) onClose();
    };
    document.addEventListener('mousedown', handler);
    return () => document.removeEventListener('mousedown', handler);
  }, [open, onClose]);

  // Superadmin separado: header + children
  const headerSuper = superadminItems.find((i) => /^superadmin$/i.test(i.label.trim()));
  const childrenSuper = superadminItems.filter((i) => !/^superadmin$/i.test(i.label.trim()));
  const hasSuperadmin = !!(headerSuper || childrenSuper.length > 0);

  const [superExpanded, setSuperExpanded] = useState<boolean>(() => {
    if (typeof window === 'undefined') return false;
    return localStorage.getItem(LS.SUPER_EXPANDED) === '1';
  });
  useEffect(() => {
    localStorage.setItem(LS.SUPER_EXPANDED, superExpanded ? '1' : '0');
  }, [superExpanded]);

  // Estado da cascata: qual sub-menu está ativo (null = só painel principal)
  const [activeSub, setActiveSub] = useState<'superadmin' | 'disponivel' | 'aparencia' | null>(null);

  // Reset cascade quando fechar o menu
  useEffect(() => {
    if (!open) setActiveSub(null);
  }, [open]);

  // Suprime warning de superExpanded não-usado (mantido por compat)
  void superExpanded; void setSuperExpanded;

  if (!open) return null;
  return (
    <div className="user-menu user-menu-cascade" ref={ref}>
      {/* PAINEL PRINCIPAL */}
      <div className="user-menu-main">
        <div className="user-menu-head">
          <span className="avatar">{iniciais}</span>
          <div className="meta">
            <b>{nome}</b>
            <small>{email}</small>
          </div>
        </div>
        <a href="/user/profile" className="um-item">
          <User size={14} className="ic" />
          <span className="label">Meu perfil</span>
        </a>

        {/* Superadmin — abre cascata lateral à direita */}
        {hasSuperadmin && (
          <button
            type="button"
            className={`um-item um-cascade-trigger ${activeSub === 'superadmin' ? 'active' : ''}`}
            onClick={() => setActiveSub((s) => (s === 'superadmin' ? null : 'superadmin'))}
            aria-expanded={activeSub === 'superadmin'}
          >
            <ShieldAlert size={14} className="ic" />
            <span className="label">{headerSuper?.label ?? 'Superadmin'}</span>
            <ChevronRight size={12} className="um-cascade-arrow" />
          </button>
        )}

        <button
          type="button"
          className={`um-item um-cascade-trigger ${activeSub === 'disponivel' ? 'active' : ''}`}
          onClick={() => setActiveSub((s) => (s === 'disponivel' ? null : 'disponivel'))}
        >
          <span
            className="um-status"
            style={{ background: 'oklch(0.72 0.18 145)' }}
          />
          <span className="label">Disponível</span>
          <ChevronRight size={12} className="um-cascade-arrow" />
        </button>

        <button
          type="button"
          className={`um-item um-cascade-trigger ${activeSub === 'aparencia' ? 'active' : ''}`}
          onClick={() => setActiveSub((s) => (s === 'aparencia' ? null : 'aparencia'))}
        >
          <Moon size={14} className="ic" />
          <span className="label">Aparência</span>
          <ChevronRight size={12} className="um-cascade-arrow" />
        </button>

        <div className="um-sep" />
        <a href="/business/settings#pos" className="um-item" title="Configuração de atalhos: aba POS em Settings">
          <Keyboard size={14} className="ic" />
          <span className="label">Atalhos</span>
          <span className="kbd">⌘/</span>
        </a>
        <a href="/business/settings" className="um-item">
          <Search size={14} className="ic" />
          <span className="label">Central de ajuda</span>
        </a>
        <div className="um-sep" />
        <a href="/logout" className="um-item">
          <LogOut size={14} className="ic" />
          <span className="label">Sair</span>
        </a>
      </div>

      {/* SUBPAINEL CASCATA — desliza da direita quando activeSub != null */}
      {activeSub === 'superadmin' && hasSuperadmin && (
        <div className="user-menu-sub">
          <div className="um-sub-h">
            <ShieldAlert size={14} className="ic" />
            <span>{headerSuper?.label ?? 'Superadmin'}</span>
          </div>
          {childrenSuper.map((item, idx) => (
            <a
              key={`super-${idx}`}
              href={item.href ?? '#'}
              className="um-item"
              title={item.label}
            >
              <span className="ic dot" />
              <span className="label">{item.label}</span>
            </a>
          ))}
          {headerSuper && headerSuper.href && headerSuper.href !== '#' && (
            <a
              href={headerSuper.href}
              className="um-item"
              title="Acessar tela Superadmin"
            >
              <span className="ic dot" />
              <span className="label">Acessar Superadmin ›</span>
            </a>
          )}
        </div>
      )}

      {activeSub === 'disponivel' && (
        <div className="user-menu-sub">
          <div className="um-sub-h">
            <span style={{ display: 'inline-block', width: 8, height: 8, borderRadius: '50%', background: 'oklch(0.72 0.18 145)' }} />
            <span>Status</span>
          </div>
          <div className="um-item"><span className="um-status" style={{ background: 'oklch(0.72 0.18 145)' }} /> <span className="label">Disponível</span></div>
          <div className="um-item"><span className="um-status" style={{ background: 'oklch(0.78 0.15 80)' }} /> <span className="label">Ausente</span></div>
          <div className="um-item"><span className="um-status" style={{ background: 'oklch(0.55 0.20 25)' }} /> <span className="label">Não perturbe</span></div>
        </div>
      )}

      {activeSub === 'aparencia' && <ThemeSubpanel />}
    </div>
  );
}

// ── ThemeSubpanel — plug do useTheme no subpainel Aparência (UI-0011) ─

function ThemeSubpanel() {
  const { mode, setTheme } = useTheme();
  // mode: 'light' | 'dark' | null (sistema)
  const options: Array<{
    key: 'light' | 'dark' | null;
    label: string;
    Icon: typeof Sun;
  }> = [
    { key: 'light', label: 'Claro', Icon: Sun },
    { key: 'dark', label: 'Escuro', Icon: Moon },
    { key: null, label: 'Sistema', Icon: Monitor },
  ];

  return (
    <div className="user-menu-sub">
      <div className="um-sub-h">
        <Moon size={14} className="ic" />
        <span>Aparência</span>
      </div>
      {options.map((o) => {
        const active = mode === o.key;
        return (
          <button
            key={String(o.key)}
            type="button"
            className={`um-item um-cascade-trigger ${active ? 'active' : ''}`}
            onClick={() => setTheme(o.key)}
            aria-pressed={active}
          >
            <o.Icon size={14} className="ic" />
            <span className="label">{o.label}</span>
            {active && <Check size={14} className="um-cascade-arrow" style={{ opacity: 1, color: 'var(--accent)' }} />}
          </button>
        );
      })}
    </div>
  );
}

// ── SidebarFooter (superadmin accordion + user dropdown) ────────────────

export function SidebarFooter({
  nome,
  nomeCurto,
  email,
  cargo,
  iniciais,
  superadminItems,
}: {
  nome: string;
  nomeCurto: string;
  email: string;
  cargo: string;
  iniciais: string;
  superadminItems: ShellMenuItem[];
}) {
  const [openUser, setOpenUser] = useState(false);

  // Sprint 0 (2026-04-27): items superadmin migraram pro user dropdown
  // (logo abaixo de "Meu perfil") — antes ficavam separados num accordion
  // standalone acima do user button, agora consolidados pra rodapé limpo.
  return (
    <div className="sb-user-wrap">
      {/* User dropdown — agora inclui Superadmin entre Meu perfil e Disponível */}
      <div className="sb-user" style={{ position: 'relative' }}>
        <SidebarUserMenu
          open={openUser}
          onClose={() => setOpenUser(false)}
          nome={nome}
          email={email}
          iniciais={iniciais}
          superadminItems={superadminItems}
        />
        <button
          className="sb-user-btn"
          type="button"
          onClick={() => setOpenUser((v) => !v)}
        >
          <span className="avatar">{iniciais}</span>
          <div className="who">
            <b>{nomeCurto}</b>
            <small>{cargo}</small>
          </div>
          <ChevronUp size={12} />
        </button>
      </div>
    </div>
  );
}
