// @memcofre
//   modulo: Cockpit (Sidebar)
//   adrs: UI-0008 (cockpit como layout-mae), UI-0011 (sidebar single-pane)
//   nota: sidebar single-pane (260px). Toggle Chat/Menu REMOVIDO em 2026-05-05.
//         Conteúdo: CompanyPicker (topo) + SidebarMenu (corpo) + SidebarFooter
//         com user dropdown + Superadmin accordion (rodapé). SidebarChat e
//         SidebarTabs removidos — conv switcher migrou pra Pages/Copiloto/Chat.tsx.

import { useEffect, useRef, useState } from 'react';
import {
  Check, ChevronDown, ChevronRight, ChevronUp, Inbox, Keyboard, LogOut,
  MessageSquare, Monitor, Moon, Search, ShieldAlert, Sun, User,
} from 'lucide-react';

import { useTheme } from '@/Hooks/useTheme';

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
    key: 'inicio',
    label: '',  // sem header (fica direto após shortcuts)
    items: ['Iniciar', 'Início', 'Home', 'Dashboard'],
  },
  {
    key: 'office',
    label: 'OFFICEIMPRESSO',
    items: ['Consulta de OS', 'Ordens de Serviço', 'Contatos', 'Clientes', 'Produtos', 'Vender', 'vender', 'Vendas', 'Orçamentos'],
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

  if (hasChildren) {
    return (
      <>
        <div className="sb-item" onClick={() => setExpanded((v) => !v)}>
          <ChevronDown
            size={12}
            className="ic"
            style={{
              transform: expanded ? 'rotate(0)' : 'rotate(-90deg)',
              transition: 'transform 120ms',
              opacity: 0.6,
            }}
          />
          <span className="label">{item.label}</span>
        </div>
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
      <span className="ic dot" />
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
      <div className="sb-sep" />
    </div>
  );
}

// ── SidebarGroup — header uppercase colapsável + items ──────────────────

function SidebarGroup({
  groupKey,
  label,
  children,
  defaultOpen = true,
}: {
  groupKey: string;
  label: string;
  children: React.ReactNode;
  defaultOpen?: boolean;
}) {
  const lsKey = `oimpresso.cockpit.group.${groupKey}.expanded`;
  const [expanded, setExpanded] = useState<boolean>(() => {
    if (typeof window === 'undefined') return defaultOpen;
    const v = localStorage.getItem(lsKey);
    return v === null ? defaultOpen : v === '1';
  });
  useEffect(() => {
    localStorage.setItem(lsKey, expanded ? '1' : '0');
  }, [expanded, lsKey]);

  // Grupo sem label (ex.: 'inicio') — renderiza items direto, sem header
  if (!label) {
    return <div className="sb-group sb-group-noheader">{children}</div>;
  }

  return (
    <div className="sb-group">
      <button
        type="button"
        className="sb-group-h"
        onClick={() => setExpanded((v) => !v)}
        aria-expanded={expanded}
      >
        <span className="sb-group-label">{label}</span>
        <ChevronDown
          size={11}
          className="sb-group-chev"
          style={{
            transform: expanded ? 'rotate(0)' : 'rotate(-90deg)',
            transition: 'transform 150ms',
          }}
        />
      </button>
      {expanded && <div className="sb-group-body">{children}</div>}
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

  return (
    <div className="sb-menu-grouped">
      <SidebarShortcuts tarefasCount={6} chatCount={3} />
      {groupsToRender.map((g) => (
        <SidebarGroup
          key={g.key}
          groupKey={g.key}
          label={g.label}
          defaultOpen={g.key !== 'mais'}
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
        <div className="um-item">
          <User size={14} className="ic" />
          <span className="label">Meu perfil</span>
        </div>

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
        <div className="um-item">
          <Keyboard size={14} className="ic" />
          <span className="label">Atalhos</span>
          <span className="kbd">⌘/</span>
        </div>
        <div className="um-item">
          <Search size={14} className="ic" />
          <span className="label">Central de ajuda</span>
        </div>
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
