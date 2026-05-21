// @memcofre
//   modulo: Cockpit (Sidebar)
//   adrs: UI-0008 (cockpit como layout-mae), UI-0011 (sidebar single-pane)
//   nota: sidebar single-pane (260px). Toggle Chat/Menu REMOVIDO em 2026-05-05.
//         Conteúdo: CompanyPicker (topo) + SidebarMenu (corpo) + SidebarFooter
//         com user dropdown + Superadmin accordion (rodapé). SidebarChat e
//         SidebarTabs removidos — conv switcher migrou pra Pages/Copiloto/Chat.tsx.

import { useEffect, useRef, useState } from 'react';
import { usePage } from '@inertiajs/react';
import {
  ArrowRightLeft, BarChart3, Bell, BookOpen, Bot, Box, Calculator, Calendar,
  Check, ChevronDown, ChevronRight, ChevronUp, ClipboardList, Clock, CreditCard,
  FileSearch, FileSpreadsheet, FileText, FolderKanban, Hash, Home, Inbox, Keyboard, LogOut,
  MessageCircle, Monitor, Moon, Package, PackageCheck, Palette, Plug, Receipt,
  RefreshCw, Rocket, Search, Settings, Sheet, ShieldAlert, ShieldCheck, ShoppingCart, Sun,
  TrendingUp, UserCog, Users, Utensils, User, Vault, Wallet, Wrench,
  type LucideIcon,
} from 'lucide-react';

import { useTheme } from '@/Hooks/useTheme';
import { VIBES, type Vibe } from './shared';

/**
 * Sidebar counts reais (US-WA-083) — vem de
 * `HandleInertiaRequests::sidebarCounts()` via shared prop
 * `shell.sidebar_counts`. Pode ser null se módulo desinstalado ou page
 * não pediu lazy.
 */
interface SidebarCountsShared {
  atendimento: number;
  tarefas: number;
  chat: number;
}

/**
 * Sidebar shortcuts visibility (Wagner 2026-05-18) — vem de
 * `HandleInertiaRequests::sidebarShortcuts()` via shared prop
 * `shell.shortcuts`. Cliente sem o módulo → flag false → shortcut some
 * do topo da sidebar (regra "cliente não quer módulo → some do menu").
 *
 * Espelha o padrão Cockpit (`prototipo-ui/_cowork-export-2026-05-15/`):
 *  - IA (Jana) substitui label "Chat"
 *  - Tarefas universal (MCP)
 *  - Atendimento condicional ao módulo Whatsapp instalado
 *
 * Equipe (Slack interno) entra como shortcut quando `Modules/Equipe`
 * for criado em PR separado.
 */
interface SidebarShortcutsShared {
  ia: boolean;
  tarefas: boolean;
  atendimento: boolean;
}

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
  financeiro: Wallet,
  // Wagner 2026-05-18: 3 entradas novas top-level KB-9.75 Financeiro.
  // "Boletos" renomeado pra "Gateway de Pagamento" — ícone CreditCard
  // (mais inclusivo: Inter boleto + PIX + Asaas futuro).
  'fluxo de caixa': TrendingUp,
  'dre / relatórios': FileSpreadsheet,
  'gateway de pagamento': CreditCard,
  'cobrança recorrente': RefreshCw,
  relatórios: BarChart3,
  reservas: Calendar,
  cocina: Utensils,
  pedidos: ClipboardList,
  'modelos de notificação': Bell,
  configurações: Settings,
  copiloto: Bot,
  ads: ShieldCheck,
  conector: Plug,
  'office impresso': Plug,
  officeimpresso: Plug,
  'transferências de ações': ArrowRightLeft,
  'ajuste de estoque': PackageCheck,
  'gestão de ativos': Box,
  'gerenciamento de usuários': Users,
  hrm: UserCog,
  essenciais: Box,
  ponto: Clock,
  reparar: Wrench,
  'team mcp': Rocket,
  projeto: FolderKanban,
  'project mgmt': ClipboardList,
  nfse: FileText,
  'nf-e brasil': FileText,
  'cofre de memórias': Vault,
  'base de conhecimento': BookOpen,
  planilha: Sheet,
};

function findMenuIcon(label: string): LucideIcon {
  return MENU_ICON_MAP[label.trim().toLowerCase()] ?? Hash;
}

import {
  BusinessOpt,
  LS,
  ShellMenuItem,
  SIDEBAR_GROUP_HUE,
  SidebarMode,
  gradientFor,
  isSuperadminMenu,
  isUserMenuItem,
} from './shared';

// ── Sidebar v3 — 5 grupos canon + 3 topo (ADR 0180, 2026-05-21) ────────
//
// Substitui o sidebar v2 (11 keys: office/oficina/fin-op/fin-analise/
// fin-config/fin/estoque/fiscal/rh/conhecimento/rel/governanca/plataforma)
// — score 58/100 vs Linear 93/100 (Hick's Law violado, ~50 labels visíveis).
//
// v3 canon: 14 labels visíveis (3 topo + 11 destinos em 5 grupos), score 91/100.
// Mental model: VENDER → OPERAR → FINANÇAS → PESSOAS → SISTEMA (verbos PT-BR
// Larissa-friendly, universal pros 4 verticais).
//
// Wagner regra 2026-05-19: DataController declara `data['group']`, frontend
// NUNCA hardcode. `items[]` aqui só pra compat com módulos não-migrados.
const SIDEBAR_GROUPS: Array<{ key: string; label: string; items: string[] }> = [
  // ── Topo (3 fixos, sempre visíveis) ──
  {
    key: 'ia',
    label: 'IA',
    items: ['Copiloto', 'Jana', 'Cofre de Memórias', 'SRS', 'Sistema de Regras',
            'Base de Conhecimento', 'KB', 'Planilha', 'Notas', 'Brief',
            'Iniciar', 'Início', 'Home', 'Dashboard', 'Relatórios',
            'Projeto', 'Project Mgmt', 'Project'],
  },
  {
    key: 'atendimento',
    label: 'ATENDIMENTO',
    items: ['WhatsApp', 'Whatsapp', 'Atendimento', 'Inbox', 'Tickets', 'Consulta de OS'],
  },
  {
    key: 'equipe',
    label: 'EQUIPE',
    items: ['Team MCP', 'TeamMcp', 'Equipe'],
  },

  // ── 5 grupos canônicos v3 ──
  {
    key: 'vender',
    label: 'VENDER',
    items: ['Vender', 'vender', 'Vendas', 'Orçamentos', 'Clientes', 'Contatos',
            'Produtos', 'Catálogo', 'CRM', 'Crm',
            'Office Impresso', 'Officeimpresso',
            'WooCommerce', 'Woocommerce'],
  },
  {
    key: 'operar',
    label: 'OPERAR',
    items: ['Ordens de Serviço', 'Reparar', 'Oficina Auto', 'Comunicação Visual',
            'Produção', 'Manufacturing',
            'Compras', 'Transferências de ações', 'Ajuste de estoque',
            'Gestão de ativos', 'Estoque', 'Inventário',
            'Reservas', 'Pedidos', 'Cocina'],
  },
  {
    key: 'financas',
    label: 'FINANÇAS',
    items: ['Financeiro', 'Despesas', 'Contas de pagamento', 'Accounting',
            'Contabilidade', 'Gateway de Pagamento', 'Cobrança Recorrente',
            'Fiscal', 'NF-e Brasil', 'NFSe', 'NFC-e', 'Certificado Digital'],
  },
  {
    key: 'pessoas',
    label: 'PESSOAS',
    items: ['RH', 'HRM', 'Essenciais', 'Ponto', 'Folha', 'Colaboradores'],
  },
  {
    key: 'sistema',
    label: 'SISTEMA',
    items: ['Governança', 'Governance', 'ADS', 'Adaptive Decision',
            'CMS', 'Conector', 'Connector', 'Backup',
            'Módulos', 'Modulos', 'Manage Modules', 'Personalizar'],
  },
];

/**
 * LEGACY_GROUP_MAP — converte as 11 keys do sidebar v2 pros 5 grupos v3.
 *
 * Permite migração modulo-a-modulo: DataControllers não-migrados ainda
 * declaram `'group' => 'office'|'fin-op'|...` e caem no grupo canônico v3
 * correto. Espelha `App\Sidebar\SidebarGroup::fromLegacy()` (Fase 1).
 *
 * Removível na Fase 9 (cleanup), quando todos os 17 DataControllers tiverem
 * migrado pro contrato v3 (Fase 4).
 */
const LEGACY_GROUP_MAP: Record<string, string> = {
  // v2 → v3
  office:       'vender',
  oficina:      'operar',
  estoque:      'operar',
  fin:          'financas',
  'fin-op':     'financas',
  'fin-analise':'financas',
  'fin-config': 'financas',
  fiscal:       'financas',
  rh:           'pessoas',
  conhecimento: 'ia',
  rel:          'ia',
  governanca:   'sistema',
  plataforma:   'sistema',
};

/**
 * Resolve grupo do sidebar pra um menu item.
 *
 * Prioridade (Wagner regra 2026-05-19 — DataController declara, frontend não hardcode):
 *  1. item.group v3 (declarado pelo DataController via `data['group']`)
 *  2. item.group v2 legacy mapeada via LEGACY_GROUP_MAP
 *  3. Match por label string em SIDEBAR_GROUPS.items[] (compat módulos não-migrados)
 *  4. Fallback 'mais' (collapse fechado por default)
 */
function findGroupKey(item: ShellMenuItem | string): string {
  // String legacy pra cobertura backwards-compat (alguns callers passam só label)
  if (typeof item === 'string') {
    const norm = item.trim();
    for (const g of SIDEBAR_GROUPS) {
      if (g.items.some((i) => i.toLowerCase() === norm.toLowerCase())) return g.key;
    }
    return 'mais';
  }

  // 1. group v3 nativo (uma das 8 keys: ia/atendimento/equipe + 5 canon)
  if (item.group && SIDEBAR_GROUPS.some((g) => g.key === item.group)) {
    return item.group;
  }

  // 2. group v2 legacy → mapear pra key v3 (migração faseada)
  if (item.group && LEGACY_GROUP_MAP[item.group]) {
    return LEGACY_GROUP_MAP[item.group];
  }

  // 3. fallback label match (módulos que ainda não declararam group)
  const norm = item.label.trim();
  for (const g of SIDEBAR_GROUPS) {
    if (g.items.some((i) => i.toLowerCase() === norm.toLowerCase())) return g.key;
  }
  return 'mais';
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
  const buttonRef = useRef<HTMLButtonElement>(null);
  const [popPos, setPopPos] = useState<{ top: number; left: number } | null>(null);
  const [isOpen, setIsOpen] = useState(false);
  const hasChildren = !!item.children?.length;
  const href = item.href ?? '#';
  const Icon = findMenuIcon(item.label);

  // Calcula posição fixed do popover-2 quando abre. Cascata lateral à direita
  // do popover-1 (UI-0011 segundo nível, Wagner 2026-05-05).
  useEffect(() => {
    if (!isOpen) { setPopPos(null); return; }
    if (!buttonRef.current) return;
    const r = buttonRef.current.getBoundingClientRect();
    setPopPos({ top: r.top, left: r.right + 4 });
  }, [isOpen]);

  // Click fora fecha popover-2 (considera tanto o button quanto o popover via classe)
  useEffect(() => {
    if (!isOpen) return;
    const handler = (e: MouseEvent) => {
      const target = e.target as Node;
      if (buttonRef.current?.contains(target)) return;
      const popoverEl = document.querySelector('.sb-item-popover');
      if (popoverEl?.contains(target)) return;
      setIsOpen(false);
    };
    document.addEventListener('mousedown', handler);
    return () => document.removeEventListener('mousedown', handler);
  }, [isOpen]);

  if (hasChildren) {
    return (
      <>
        <button
          ref={buttonRef}
          type="button"
          className={`sb-item ${isOpen ? 'is-open' : ''}`}
          onClick={() => setIsOpen((v) => !v)}
          aria-expanded={isOpen}
        >
          <Icon size={14} className="ic" />
          <span className="label">{item.label}</span>
          <ChevronRight size={11} className="sb-item-chev" style={{ opacity: 0.5 }} />
        </button>
        {isOpen && popPos && (
          <div
            className="sb-item-popover"
            style={{ position: 'fixed', top: popPos.top, left: popPos.left }}
          >
            {item.children!.map((c, i) => (
              <a
                key={`${c.label}-${i}`}
                href={c.href ?? '#'}
                className="sb-item"
              >
                <span className="ic dot" />
                <span className="label">{c.label}</span>
              </a>
            ))}
          </div>
        )}
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

// ── SidebarShortcuts — Tarefas + IA + Atendimento no topo (UI-0011) ──────
// Wagner 2026-05-08: "o whatszap precisa ir abaixo do chat" — entrypoint
// rápido pra Inbox ao lado do chat com a Jana.
//
// Renomeado 2026-05-11 (US-WA-082): "WhatsApp" → "Atendimento" pra refletir
// arquitetura omnichannel (ADR 0135). Hoje só Whatsapp Baileys/Meta/Z-API;
// amanhã Instagram DM, Messenger, Email, Mercado Livre — tudo entra no
// mesmo Inbox `/atendimento/inbox`. URL atualizada de `/whatsapp/conversations`
// legacy pra `/atendimento/inbox` (topnav já tinha mudado em US-WA-067).
//
// Renomeado 2026-05-18 (Wagner): "Chat" → "IA" pra refletir entry-point
// universal pra Jana (assistente IA). Ícone MessageSquare → Bot. Espelha
// `prototipo-ui/_cowork-export-2026-05-15/data.jsx` que tem `chat` =
// "Jana · Analista" no topo do sidebar Cockpit.
//
// Visibilidade condicional via `shell.shortcuts` shared prop: cliente sem
// o módulo (Jana/Whatsapp) NÃO vê o shortcut topo correspondente (regra
// "cliente não quer módulo → some do menu"). Default true preserva back-
// compat quando shared prop ainda não foi requisitada (lazy).
//
// Equipe (Slack interno) entra como 4º shortcut quando `Modules/Equipe`
// for criado (PR separado — sessão 2026-05-18 deixou plano A1).

function SidebarShortcuts({
  tarefasCount,
  chatCount,
  atendimentoCount,
  shortcuts,
}: {
  tarefasCount?: number;
  chatCount?: number;
  atendimentoCount?: number;
  shortcuts?: SidebarShortcutsShared;
}) {
  // Default true (back-compat) — quando shared prop não veio ainda
  const showTarefas = shortcuts?.tarefas ?? true;
  const showIa = shortcuts?.ia ?? true;
  const showAtendimento = shortcuts?.atendimento ?? true;

  return (
    <div className="sb-shortcuts">
      {showTarefas && (
        <a href="/tarefas" className="sb-shortcut">
          <Inbox size={13} />
          <span className="label">Tarefas</span>
          {!!tarefasCount && <span className="badge">{tarefasCount}</span>}
        </a>
      )}
      {showIa && (
        <a href="/jana" className="sb-shortcut">
          <Bot size={13} />
          <span className="label">IA</span>
          {!!chatCount && <span className="badge">{chatCount}</span>}
        </a>
      )}
      {showAtendimento && (
        <a href="/atendimento/inbox" className="sb-shortcut">
          <MessageCircle size={13} />
          <span className="label">Atendimento</span>
          {!!atendimentoCount && <span className="badge">{atendimentoCount}</span>}
        </a>
      )}
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
  defaultOpen = false,
}: {
  groupKey: string;
  label: string;
  children: React.ReactNode;
  defaultOpen?: boolean;
}) {
  // Inline accordion (não popover lateral) — Wagner 2026-05-05.
  // Persistência por grupo em LS pra não recarregar entre navegações.
  // Múltiplos grupos podem estar abertos simultaneamente.
  const lsKey = `oimpresso.cockpit.group.${groupKey}.expanded`;
  const [expanded, setExpanded] = useState<boolean>(() => {
    if (typeof window === 'undefined') return defaultOpen;
    const v = localStorage.getItem(lsKey);
    return v === null ? defaultOpen : v === '1';
  });
  useEffect(() => {
    localStorage.setItem(lsKey, expanded ? '1' : '0');
  }, [expanded, lsKey]);

  // Hue OKLCH por grupo (canon Cowork) — aplica no dot + label header.
  // Items não mapeados (ex. 'mais') ficam neutros (sem var --gh).
  const hue = SIDEBAR_GROUP_HUE[groupKey];
  const groupStyle = hue !== undefined ? ({ ['--gh' as never]: String(hue) } as React.CSSProperties) : undefined;

  if (!label) {
    return <div className="sb-group sb-group-noheader">{children}</div>;
  }

  return (
    <div className={`sb-group ${expanded ? 'is-open' : ''}`} style={groupStyle}>
      <button
        type="button"
        className="sb-group-h"
        onClick={() => setExpanded((v) => !v)}
        aria-expanded={expanded}
      >
        <ChevronDown
          size={10}
          className="chev"
          style={{
            transform: expanded ? 'rotate(0)' : 'rotate(-90deg)',
            transition: 'transform 150ms',
          }}
        />
        {hue !== undefined && <span className="sb-group-dot" aria-hidden="true" />}
        <span className="sb-group-l">{label}</span>
      </button>
      {expanded && <div className="sb-group-body">{children}</div>}
    </div>
  );
}

// ── SidebarMenu (agrupa shell.menu por scope — UI-0011) ─────────────────

export function SidebarMenu({ items, mode = 'expanded' }: { items: ShellMenuItem[]; mode?: SidebarMode }) {
  if (!items?.length) {
    return (
      <div className="sb-menu-stub">
        <p>Menu vazio — sem items disponíveis.</p>
      </div>
    );
  }
  // Filtra superadmin + user-menu items (Gerenciamento de usuários,
  // Configurações) — todos vão pro user dropdown no rodapé via SidebarFooter.
  const principais = items.filter(
    (i) => !isSuperadminMenu(i.label) && !isUserMenuItem(i.label)
  );

  // Agrupa principais por (1) item.group declarado pelo DataController OU
  // (2) lookup table label match (legacy compat)
  const groupedItems: Record<string, ShellMenuItem[]> = {};
  for (const item of principais) {
    const key = findGroupKey(item);
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

  // US-WA-083: counts reais via Inertia shared prop `shell.sidebar_counts`
  // (HandleInertiaRequests::sidebarCounts). Fallback pra 0 se shared
  // prop ainda não foi requisitada (lazy) ou se módulo desinstalado.
  // Wagner 2026-05-18: shared prop `shell.shortcuts` controla visibilidade
  // dos shortcuts topo baseado em módulos instalados por business.
  const sharedShell = (usePage().props as any)?.shell as {
    sidebar_counts?: SidebarCountsShared | null;
    shortcuts?: SidebarShortcutsShared | null;
  } | undefined;
  const counts = sharedShell?.sidebar_counts ?? { atendimento: 0, tarefas: 0, chat: 0 };
  const shortcuts = sharedShell?.shortcuts ?? undefined;

  if (mode === 'rail') {
    return (
      <SidebarMenuRail
        groupsToRender={groupsToRender}
        groupedItems={groupedItems}
        counts={counts}
        shortcuts={shortcuts}
      />
    );
  }

  return (
    <div className="sb-menu-grouped">
      <SidebarShortcuts
        tarefasCount={counts.tarefas}
        chatCount={counts.chat}
        atendimentoCount={counts.atendimento}
        shortcuts={shortcuts}
      />
      {groupsToRender.map((g) => (
        <SidebarGroup
          key={g.key}
          groupKey={g.key}
          label={g.label}
          defaultOpen={['ia', 'atendimento', 'equipe', 'vender', 'operar', 'financas'].includes(g.key)}
        >
          {(groupedItems[g.key] ?? []).map((item, idx) => (
            <SidebarMenuItem key={`${item.label}-${idx}`} item={item} />
          ))}
        </SidebarGroup>
      ))}
    </div>
  );
}

// ── SidebarMenuRail (rail compacto — só ícones + flyout grupo) ──────────
// Espelha SidebarMenuRail do prototipo Cowork (sidebar.jsx:286-385).
// Wagner 2026-05-16: rail mode + cores hue por grupo.

function SidebarMenuRail({
  groupsToRender,
  groupedItems,
  counts,
  shortcuts,
}: {
  groupsToRender: Array<{ key: string; label: string }>;
  groupedItems: Record<string, ShellMenuItem[]>;
  counts: SidebarCountsShared;
  shortcuts?: SidebarShortcutsShared;
}) {
  const showTarefas = shortcuts?.tarefas ?? true;
  const showIa = shortcuts?.ia ?? true;
  const showAtendimento = shortcuts?.atendimento ?? true;
  const [flyout, setFlyout] = useState<string | null>(null);
  const [flyoutPos, setFlyoutPos] = useState<{ top: number; left: number }>({ top: 0, left: 0 });
  const flyoutRef = useRef<HTMLDivElement>(null);
  const anchorRefs = useRef<Record<string, HTMLButtonElement | null>>({});

  useEffect(() => {
    if (!flyout) return;
    const handler = (e: MouseEvent) => {
      const target = e.target as Node;
      if (flyoutRef.current?.contains(target)) return;
      const anchor = anchorRefs.current[flyout];
      if (anchor?.contains(target)) return;
      setFlyout(null);
    };
    document.addEventListener('mousedown', handler);
    return () => document.removeEventListener('mousedown', handler);
  }, [flyout]);

  const openFlyout = (key: string) => {
    const anchor = anchorRefs.current[key];
    if (!anchor) { setFlyout(null); return; }
    const r = anchor.getBoundingClientRect();
    setFlyoutPos({ top: r.top, left: r.right + 6 });
    setFlyout(key);
  };

  return (
    <div className="sb-menu-rail">
      {showTarefas && (
        <a
          href="/tarefas"
          className="sb-rail-btn"
          data-tip="Tarefas"
          onClick={() => setFlyout(null)}
        >
          <Inbox size={18} className="ic" />
          {!!counts.tarefas && <span className="sb-rail-dot-badge" />}
        </a>
      )}
      {showIa && (
        <a
          href="/jana"
          className="sb-rail-btn"
          data-tip="IA"
          onClick={() => setFlyout(null)}
        >
          <Bot size={18} className="ic" />
          {!!counts.chat && <span className="sb-rail-dot-badge" />}
        </a>
      )}
      {showAtendimento && (
        <a
          href="/atendimento/inbox"
          className="sb-rail-btn"
          data-tip="Atendimento"
          onClick={() => setFlyout(null)}
        >
          <MessageCircle size={18} className="ic" />
          {!!counts.atendimento && <span className="sb-rail-dot-badge" />}
        </a>
      )}

      <div className="sb-rail-sep" />

      {groupsToRender.map((g) => {
        const hue = SIDEBAR_GROUP_HUE[g.key];
        const railStyle = hue !== undefined ? ({ ['--gh' as never]: String(hue) } as React.CSSProperties) : undefined;
        const isOpen = flyout === g.key;
        // Pega ícone do primeiro item do grupo (representativo)
        const firstItem = groupedItems[g.key]?.[0];
        const Icon = firstItem ? findMenuIcon(firstItem.label) : Hash;
        return (
          <button
            key={g.key}
            ref={(el) => { anchorRefs.current[g.key] = el; }}
            type="button"
            className={`sb-rail-btn sb-rail-group ${isOpen ? 'open' : ''}`}
            data-tip={g.label}
            style={railStyle}
            onClick={() => (isOpen ? setFlyout(null) : openFlyout(g.key))}
            aria-expanded={isOpen}
          >
            <Icon size={18} className="ic" />
          </button>
        );
      })}

      {flyout && (() => {
        const g = groupsToRender.find((x) => x.key === flyout);
        if (!g) return null;
        const hue = SIDEBAR_GROUP_HUE[g.key];
        const flyoutStyle: React.CSSProperties = {
          position: 'fixed',
          top: flyoutPos.top,
          left: flyoutPos.left,
          ...(hue !== undefined ? { ['--gh' as never]: String(hue) } : {}),
        };
        return (
          <div className="sb-rail-flyout" ref={flyoutRef} style={flyoutStyle}>
            <div className="sb-rail-flyout-h">
              {hue !== undefined && <span className="sb-group-dot" aria-hidden="true" />}
              <span className="sb-rail-flyout-l">{g.label}</span>
              <span className="sb-group-n">{groupedItems[g.key]?.length ?? 0}</span>
            </div>
            {(groupedItems[g.key] ?? []).map((item, idx) => (
              <div key={`${item.label}-${idx}`} onClick={() => setFlyout(null)}>
                <SidebarMenuItem item={item} />
              </div>
            ))}
          </div>
        );
      })()}
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
  userMenuItems,
  vibe,
  onVibe,
}: {
  open: boolean;
  onClose: () => void;
  nome: string;
  email: string;
  iniciais: string;
  superadminItems: ShellMenuItem[];
  userMenuItems: ShellMenuItem[];
  vibe?: Vibe;
  onVibe?: (v: Vibe) => void;
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
  const [activeSub, setActiveSub] = useState<'superadmin' | 'disponivel' | 'aparencia' | 'vibes' | null>(null);

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

        {/* User menu items extraídos do shell.menu (Gerenciamento de usuários,
            Configurações) — Wagner 2026-05-05 moveu pro footer */}
        {userMenuItems.map((item, idx) => {
          const lower = item.label.toLowerCase();
          const Icon = lower.startsWith('config') ? Settings : Users;
          return (
            <a
              key={`um-${idx}`}
              href={item.href ?? '#'}
              className="um-item"
              title={item.label}
            >
              <Icon size={14} className="ic" />
              <span className="label">{item.label}</span>
            </a>
          );
        })}

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

        {/* Modo de trabalho (Vibes) — promovido do TweaksPanel pra user dropdown.
            ADR UI-0008 §5: workspace/daylight/focus reescreve atmosfera do shell. */}
        {vibe !== undefined && onVibe && (
          <button
            type="button"
            className={`um-item um-cascade-trigger ${activeSub === 'vibes' ? 'active' : ''}`}
            onClick={() => setActiveSub((s) => (s === 'vibes' ? null : 'vibes'))}
          >
            <Palette size={14} className="ic" />
            <span className="label">Modo de trabalho</span>
            <ChevronRight size={12} className="um-cascade-arrow" />
          </button>
        )}

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

      {activeSub === 'vibes' && vibe !== undefined && onVibe && (
        <VibesSubpanel vibe={vibe} onVibe={onVibe} />
      )}
    </div>
  );
}

// ── VibesSubpanel — Modo de trabalho (workspace/daylight/focus) ─────────
//
// Promove os 3 vibes do TweaksPanel pro user dropdown (recomendação P2 #7
// auditoria 2026-05-07). Mesma state vive no AppShellV2 — passada via prop.

function VibesSubpanel({
  vibe,
  onVibe,
}: {
  vibe: Vibe;
  onVibe: (v: Vibe) => void;
}) {
  const descriptions: Record<Vibe, string> = {
    workspace: 'Denso e formal — padrão',
    daylight:  'Tons quentes, mais ar',
    focus:     'Alto contraste, monocromático',
  };

  return (
    <div className="user-menu-sub">
      <div className="um-sub-h">
        <Palette size={14} className="ic" />
        <span>Modo de trabalho</span>
      </div>
      {VIBES.map((v) => {
        const active = vibe === v.id;
        return (
          <button
            key={v.id}
            type="button"
            className={`um-item um-cascade-trigger ${active ? 'active' : ''}`}
            onClick={() => onVibe(v.id)}
            aria-pressed={active}
            title={descriptions[v.id]}
          >
            <span
              className="ic"
              style={{
                width: 14,
                height: 14,
                borderRadius: '50%',
                background: vibeAccent(v.id),
                display: 'inline-block',
              }}
              aria-hidden
            />
            <span className="label">
              {v.label}
              <span style={{
                display: 'block',
                fontSize: '10.5px',
                color: 'var(--text-mute)',
                marginTop: 2,
                lineHeight: 1.2,
              }}>
                {descriptions[v.id]}
              </span>
            </span>
            {active && <Check size={14} className="um-cascade-arrow" style={{ opacity: 1, color: 'var(--accent)' }} />}
          </button>
        );
      })}
    </div>
  );
}

/** Cor visual do dot por vibe — usa oklch idêntico ao cockpit.css */
function vibeAccent(vibe: Vibe): string {
  switch (vibe) {
    case 'workspace': return 'oklch(0.58 0.09 220)'; // accent default azul
    case 'daylight':  return 'oklch(0.72 0.13 60)';  // âmbar quente
    case 'focus':     return 'oklch(0.45 0.02 240)'; // cinza-azul dessaturado
  }
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
  userMenuItems = [],
  vibe,
  onVibe,
}: {
  nome: string;
  nomeCurto: string;
  email: string;
  cargo: string;
  iniciais: string;
  superadminItems: ShellMenuItem[];
  userMenuItems?: ShellMenuItem[];
  /** Vibes — quando ambos passados, exibe entrada "Modo de trabalho" no menu user. */
  vibe?: Vibe;
  onVibe?: (v: Vibe) => void;
}) {
  const [openUser, setOpenUser] = useState(false);

  // Sprint 0 (2026-04-27): items superadmin migraram pro user dropdown
  // (logo abaixo de "Meu perfil") — antes ficavam separados num accordion
  // standalone acima do user button, agora consolidados pra rodapé limpo.
  // 2026-05-07: Vibes (Modo de trabalho) também migrou pro user dropdown
  // (recomendação P2 #7 auditoria) — antes só ficava no Tweaks FAB.
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
          userMenuItems={userMenuItems}
          vibe={vibe}
          onVibe={onVibe}
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
