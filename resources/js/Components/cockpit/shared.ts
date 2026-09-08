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
  /** 'ativa' | 'arquivada' — filtro Todas|Arquivadas do Chat da Jana. */
  status?: string | null;
  ativa?: boolean;
  /**
   * Resumo de UMA linha da ultima mensagem da conversa (90 chars, reticencia).
   * Vem de `ChatController::resumirParaPreview` sobre `jana_mensagens`, NAO de
   * coluna de `jana_conversas` — provado por UC-JCHAT-12.
   */
  preview?: string | null;
  /**
   * ISO-8601 da ultima mensagem. CRU de proposito: formatar no servidor
   * herdaria o shift +3h que `format_date` aplica pra cliente legado (ADR 0066)
   * e o card mostraria hora errada. Quem formata e o browser, que sabe o fuso.
   */
  ultima_em?: string | null;
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
  /**
   * Atalho de teclado declarado pelo DataController do módulo (ADR 0180
   * Fase 4). Formato validado no backend por `App\Sidebar\SidebarMenuItem`:
   * `G X` ou `G X Y`. Chega ao React via pass-through do `LegacyMenuAdapter`.
   * Consumido por `useSidebarShortcut` (índice + listener) e pelo slot
   * `.sb-item-end` do `SidebarMenuItem` (dica visual no hover/foco).
   */
  shortcut?: string;
  /**
   * Sub-telas do item ("ghosts"), declaradas pelo DataController do módulo
   * (ADR 0180 Fase 4) e repassadas pelo `LegacyMenuAdapter`. O protótipo Cowork
   * as renderiza no SIDEBAR, sob o item ATIVO, com teto de 5 + "⋯ mais N", e usa
   * o total como contador no slot direito do item.
   * Ver ADR UI-0028 (protótipo soberano na FORMA).
   */
  ghosts?: Array<{ key?: string; label: string; href: string }>;
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

// ── Auto-rail responsivo (ADR UI-0030) ─────────────────────────────────────
// Até esta largura o shell NASCE em rail (56px) em vez de expandido (260px),
// como o shell do protótipo Cowork (`prototipo-ui/cowork/app.jsx`).
//
// Por que 1280 INCLUSIVE, se o protótipo usa `innerWidth < 1280` (rail só a
// partir de 1279)? Medido no espelho em 2026-09-02 (tabela na ADR UI-0030):
// a `cockpit.css` já trata `@media (max-width: 1280px)` como a banda estreita
// do shell — é onde o painel Linked colapsa pra 0. Dois limiares de "estreito"
// no mesmo shell (≤1280 pro painel, ≤1279 pro rail) seria drift; 1280 é a
// largura do monitor do [W], e é justamente o caso que motivou o pedido.
export const AUTO_RAIL_MAX_W = 1280;
export const AUTO_RAIL_MQ = `(max-width: ${AUTO_RAIL_MAX_W}px)`;

// Hue OKLCH por grupo (espelha GROUP_META do prototipo Cowork
// _cowork-export-2026-05-15/data.jsx). Aplicado via CSS var --gh nos
// elementos sb-group (dot + label) e sb-rail-group (tooltip + ícone).
//
// Sidebar v3 (ADR 0180, 2026-05-21): 8 keys canon (3 topo + 5 grupos).
// Keys v2 preservadas durante migração faseada — Sidebar.tsx normaliza
// via LEGACY_GROUP_MAP, mas alguns callers leem hue direto pela key
// declarada. Cleanup das keys v2 vai na Fase 9.
export const SIDEBAR_GROUP_HUE: Record<string, number> = {
  // ── Topo v3 (3 fixos) — cores brand fixas ──
  ia: 215,           // azul brand — calma/inteligência
  atendimento: 30,   // laranja — acolhedor
  equipe: 275,       // roxo — colaboração

  // ── 8 grupos canon Wagner 2026-05-22 — ESCALA CANON espaçada no círculo
  //     cromático pra distinção visual ≥25° entre cada grupo. Ordem semântica:
  //     vermelho (energia) → âmbar (vendas) → verde (dinheiro) → ciano (técnico)
  //     → azul (dados) → índigo (sistema) → magenta (organização).
  producao: 8,       // vermelho — energia/atividade intensa (fábrica/OS)
  comercial: 55,     // âmbar/ouro — dinheiro/vendas
  pessoas: 88,       // verde-limão — calor humano/crescimento (RH)
  financas: 145,     // verde — dinheiro/finance
  fiscal: 175,       // turquesa — oficial/técnico (NF-e/SPED)
  cadastro: 202,     // ciano — dados/registro
  sistema: 245,      // índigo — autoridade/configuração
  estoque: 315,      // magenta — organização/inventory

  // ── Legacy v2 aliases (preservadas durante migração — removíveis na F9) ──
  vender: 55,        // → comercial
  operar: 8,         // → producao

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

/**
 * Instante da ultima mensagem em rotulo curto, como a ancora (`t.quando`).
 *
 * Formatado AQUI e nao no servidor de proposito: `ChatController` manda
 * `ultima_em` em ISO-8601 cru porque formatar la herdaria o shift +3h que
 * `format_date` aplica pra cliente legado (ADR 0066) — o card mostraria hora
 * errada pra quem cair naquele caminho. O browser sabe o fuso e o locale.
 *
 * Escala da ancora: hoje -> "09:38" · ontem -> "ontem" · dentro da semana ->
 * dia curto ("ter") · mais velho -> "05/mai". Devolve null quando nao ha dado,
 * e o card simplesmente nao desenha a linha — nao inventa "agora".
 */
export function rotuloQuando(iso?: string | null, agora: Date = new Date()): string | null {
  if (!iso) return null;
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return null;

  const dia = (x: Date) => Math.floor((x.getTime() - x.getTimezoneOffset() * 60000) / 86400000);
  const delta = dia(agora) - dia(d);

  if (delta <= 0) return d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
  if (delta === 1) return 'ontem';
  if (delta < 7) return d.toLocaleDateString('pt-BR', { weekday: 'short' }).replace('.', '');
  // Montado a mao: `toLocaleDateString('pt-BR', {day, month:'short'})` devolve
  // "05 de mai." — MEDIDO — e a ancora escreve "05/mai". Formato longo num
  // rotulo de 10.5px estouraria a linha do titulo.
  const dd = String(d.getDate()).padStart(2, '0');
  const mes = d.toLocaleDateString('pt-BR', { month: 'short' }).replace('.', '');
  return `${dd}/${mes}`;
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
