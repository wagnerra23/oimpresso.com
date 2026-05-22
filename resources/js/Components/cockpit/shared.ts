// @memcofre
//   modulo: Cockpit (shared)
//   adrs: UI-0008 (cockpit como layout-mae)
//   nota: tipos + helpers + LS keys compartilhados entre Layouts/AppShellV2 e
//         Components/cockpit/*. Single source of truth pra dados do cockpit.

// ── tipos ───────────────────────────────────────────────────────────────

export interface ConversaResumo {
  id: string;
  titulo: string;
  unread?: number;
  origem?: string | null;
  ativa?: boolean;
}

export interface Rotina {
  id: string;
  titulo: string;
  frequencia: string;
}

export interface AvatarRef {
  iniciais: string;
  gradId: number;
}

export interface Mensagem {
  id: number;
  autor: 'me' | 'them';
  texto: string;
  hora: string;
  dia?: string;
  lida?: boolean;
  whoAvatar?: AvatarRef;
  whoNome?: string;
}

export interface OsContext {
  numero: string;
  cliente: string;
  estagio: string;
  prazo: string;
}

export interface FinContext {
  saldo: string;
  boletos: string;
}

export interface HistoricoEvent {
  quando: string;
  quem: string;
  oque: string;
}

export interface AnexoFile {
  nome: string;
  tamanho: string;
}

export interface ConversaFoco {
  id: string;
  titulo: string;
  tipo: string;
  online?: boolean;
  avatar?: AvatarRef;
  cliente?: {
    nome: string;
    telefone: string;
    ultimoContato: string;
  };
  os?: OsContext;
  financeiro?: FinContext;
  historico?: HistoricoEvent[];
  anexos?: AnexoFile[];
  mensagens: Mensagem[];
}

export interface BusinessOpt {
  id: number;
  nome: string;
  iniciais: string;
  ativa: boolean;
}

// MenuItem do shell global (vem via Inertia shared props)
export interface ShellMenuItem {
  label: string;
  href?: string;
  icon?: string;
  inertia?: boolean;
  /**
   * Grupo sidebar declarado pelo DataController do módulo (data['group']).
   * Quando presente, findGroupKey usa este valor em vez de match por label —
   * permite que o módulo declare seu grupo sem hardcode no frontend.
   * Valores canon: office | oficina-auto | fin | estoque | fiscal | rh |
   *                conhecimento | dashboard | jana | governanca | plataforma
   */
  group?: string;
  children?: ShellMenuItem[];
}

// Bundle de props "shell" pra paginas que usam AppShellV2
export interface CockpitShellProps {
  business: { nome: string; opcoes: BusinessOpt[] };
  user: {
    nome: string;
    nomeCurto: string;
    email: string;
    cargo: string;
    iniciais: string;
  };
  conversas: {
    fixadas: ConversaResumo[];
    rotinas: Rotina[];
    recentes: ConversaResumo[];
  };
}

// ── Vibes ───────────────────────────────────────────────────────────────

export type Vibe = 'workspace' | 'daylight' | 'focus';

export const VIBES: Array<{ id: Vibe; label: string }> = [
  { id: 'workspace', label: 'workspace' },
  { id: 'daylight', label: 'daylight' },
  { id: 'focus', label: 'focus' },
];

// ── Tabs do chat (tipos de conversa) ────────────────────────────────────

export const CHAT_TABS: Array<{ id: string; label: string }> = [
  { id: 'todos', label: 'Todos' },
  { id: 'os', label: 'OS' },
  { id: 'equipe', label: 'Equipe' },
  { id: 'clientes', label: 'Clientes' },
];

// ── localStorage keys (oimpresso.cockpit.*) ─────────────────────────────

export const LS = {
  TAB: 'oimpresso.cockpit.sidebar.tab',
  CHAT_TAB: 'oimpresso.cockpit.chat.tab',
  LINKED: 'oimpresso.cockpit.linked.collapsed',
  CONV: 'oimpresso.cockpit.conv',
  TW_VIBE: 'oimpresso.cockpit.tweaks.vibe',
  TW_DENSITY: 'oimpresso.cockpit.tweaks.density',
  TW_HUE: 'oimpresso.cockpit.tweaks.accentHue',
  TW_OPEN: 'oimpresso.cockpit.tweaks.open',
  SUPER_EXPANDED: 'oimpresso.cockpit.superadmin.expanded',
  SB_MODE: 'oimpresso.sb.mode',
} as const;

export type SidebarMode = 'expanded' | 'rail';

// Hue OKLCH por grupo (espelha GROUP_META do prototipo Cowork
// _cowork-export-2026-05-15/data.jsx). Aplicado via CSS var --gh nos
// elementos sb-group (dot + label) e sb-rail-group (tooltip + ícone).
//
// Sidebar v3 (ADR 0180, 2026-05-21): 8 keys canon (3 topo + 5 grupos).
// Keys v2 preservadas durante migração faseada — Sidebar.tsx normaliza
// via LEGACY_GROUP_MAP, mas alguns callers leem hue direto pela key
// declarada. Cleanup das keys v2 vai na Fase 9.
export const SIDEBAR_GROUP_HUE: Record<string, number> = {
  // ── Topo v3 (3 fixos) ──
  ia: 220,           // azul — Copiloto/Jana
  atendimento: 30,   // laranja — WhatsApp/Inbox
  equipe: 270,       // roxo — Team MCP

  // ── 5 grupos canônicos v3 ──
  vender: 60,        // amarelo — energia comercial (legacy)
  operar: 350,       // magenta — OS/Produção/Estoque (legacy)
  financas: 145,     // verde — financeiro + fiscal
  pessoas: 295,      // roxo claro — RH (legacy key)
  sistema: 200,      // azul-acinzentado — governança + plataforma

  // ── 8 grupos canon Wagner 2026-05-22 (CADASTRO·COMERCIAL·FINANÇAS·FISCAL·
  //     PRODUÇÃO·ESTOQUE·RH·SISTEMA). Cores semanticamente mapeadas pra mental
  //     model PME-BR.
  cadastro: 220,     // azul — dados/registro
  comercial: 60,     // amarelo — energia comercial (= vender legacy)
  fiscal: 165,       // verde-azulado — NF-e/NFSe/SPED (distinto de financas)
  producao: 350,     // magenta — fábrica/atividade (= operar legacy)
  estoque: 280,      // roxo profundo — caixas/inventory

  // ── Legacy v2 (preservadas durante migração faseada — removidas na F9) ──
  office: 60,             // → vender
  oficina: 350,           // → operar
  'fin-op': 145,          // → financas
  'fin-analise': 155,     // → financas
  'fin-config': 135,      // → financas
  fin: 145,               // → financas
  estoque: 350,           // → operar
  fiscal: 145,            // → financas (era 200, conflitava com sistema)
  rh: 295,                // → pessoas
  conhecimento: 220,      // → ia
  rel: 220,               // → ia
  governanca: 200,        // → sistema (era 270, conflitava com equipe)
  plataforma: 200,        // → sistema
};

// ── helpers ─────────────────────────────────────────────────────────────

// Gradiente determinístico por id (mesma empresa = mesma cor)
export function gradientFor(id: number): string {
  const hue = (id * 47) % 360;
  return `linear-gradient(135deg, oklch(0.55 0.15 ${hue}), oklch(0.65 0.15 ${(hue + 60) % 360}))`;
}

// Wagner 2026-05-22 REVIVED: cascata "Superadmin" do user dropdown footer
// RESTAURADA. Admin de plataforma (Módulos/Backup/CMS/Conector/Office Impresso/
// Personalizar) sai do menu principal e vai pra cascade no rodapé esquerdo
// (avatar user). Mantém menu principal limpo + agrupa admin onde faz sentido.
//
// Histórico:
// - 2026-04-27: SUPERADMIN_LABELS criado pra filtrar items pro user dropdown
// - 2026-05-10: Wagner removeu (admin de plataforma virou grupo PLATAFORMA)
// - 2026-05-22: Wagner reviveu (sidebar v3 — admin volta pra cascade rodapé)
//
// SidebarMenu.principais filter usa isSuperadminMenu() pra excluir esses
// labels do menu principal. SidebarUserMenu renderiza cascade lateral.
export const SUPERADMIN_LABELS = new Set<string>([
  'Módulos', 'Modulos', 'Manage Modules',
  'Backup',
  'CMS', 'Cms',
  'Conector', 'Connector',
  'Office Impresso', 'Officeimpresso',
  'Personalizar',
]);

export function isSuperadminMenu(label: string): boolean {
  return SUPERADMIN_LABELS.has(label.trim());
}

// Items que vão pro user dropdown footer (botão de avatar/usuário no rodapé)
// em vez de aparecerem no menu principal — Wagner 2026-05-05.
// Mantém shell.menu canônico (LegacyMenuAdapter); o SidebarMenu filtra
// e o SidebarUserMenu renderiza dentro do dropdown.
export const USER_MENU_LABELS = new Set<string>([
  'Gerenciamento de usuários', 'Gerenciamento de usuario',
  'User Management', 'Configurações', 'Settings',
]);

export function isUserMenuItem(label: string): boolean {
  return USER_MENU_LABELS.has(label.trim());
}
