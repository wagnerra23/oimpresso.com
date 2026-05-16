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
export const SIDEBAR_GROUP_HUE: Record<string, number> = {
  office: 60,
  oficina: 350,
  fin: 145,
  estoque: 30,
  fiscal: 200,
  rh: 295,
  conhecimento: 80,
  rel: 240,
  ia: 220,
  governanca: 270,
  plataforma: 200,
};

// ── helpers ─────────────────────────────────────────────────────────────

// Gradiente determinístico por id (mesma empresa = mesma cor)
export function gradientFor(id: number): string {
  const hue = (id * 47) % 360;
  return `linear-gradient(135deg, oklch(0.55 0.15 ${hue}), oklch(0.65 0.15 ${(hue + 60) % 360}))`;
}

// DEPRECATED 2026-05-10 — cascata "Superadmin" do user dropdown footer foi
// removida (Wagner). Admin de plataforma agora vive no sidebar principal:
// Officeimpresso em ACESSOS RÁPIDOS, demais (CMS/Conector/Backup/Módulos) em
// novo grupo "PLATAFORMA". SidebarMenu não filtra mais — itens caem no grupo
// canônico via SIDEBAR_GROUPS. Set mantido vazio + isSuperadminMenu sempre
// false pra preservar callers (SidebarMenu.principais filter, SidebarFooter
// hasSuperadmin) sem quebrar até refactor remover o code path.
export const SUPERADMIN_LABELS = new Set<string>();

export function isSuperadminMenu(_label: string): boolean {
  return false;
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
