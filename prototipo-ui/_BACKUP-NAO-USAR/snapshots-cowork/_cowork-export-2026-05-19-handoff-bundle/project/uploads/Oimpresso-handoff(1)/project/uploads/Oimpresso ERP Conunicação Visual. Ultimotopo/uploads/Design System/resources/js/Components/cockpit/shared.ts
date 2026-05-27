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
} as const;

// ── helpers ─────────────────────────────────────────────────────────────

// Gradiente determinístico por id (mesma empresa = mesma cor)
export function gradientFor(id: number): string {
  const hue = (id * 47) % 360;
  return `linear-gradient(135deg, oklch(0.55 0.15 ${hue}), oklch(0.65 0.15 ${(hue + 60) % 360}))`;
}

// Labels de menu items que pertencem ao "rodape superadmin"
// (heuristica por enquanto — TODO Fase 5: virar flag is_superadmin no MenuItem
//  do LegacyMenuAdapter pra ficar declarativo)
export const SUPERADMIN_LABELS = new Set<string>([
  'Backup', 'CMS', 'Connector', 'Office Impresso', 'Officeimpresso',
  'Módulos', 'Modulos', 'Manage Modules', 'Personalizar', 'Memória', 'MemCofre',
]);

export function isSuperadminMenu(label: string): boolean {
  const norm = label.trim();
  if (SUPERADMIN_LABELS.has(norm)) return true;
  // matching parcial pra labels longos
  return /superadmin|module|backup|connector|cms\b/i.test(norm);
}
