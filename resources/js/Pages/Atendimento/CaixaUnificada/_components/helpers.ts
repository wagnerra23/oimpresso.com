// helpers.ts — types + utilitários compartilhados Caixa Unificada V4.
// ADR 0114 (Cowork loop) · ADR 0135 (omnichannel) · ADR 0110 (Cockpit V2).
//
// Tipos espelham shape dos payloads do CaixaUnificadaController. Mantém
// compat parcial com helpers.ts do legacy /Pages/Whatsapp/_components/
// (ListConversation/ThreadConversation/Message) sem importar diretamente —
// minimizar acoplamento durante coexistência canary 7d com /atendimento/inbox.

/**
 * Status da conversa — 4 canônicos do Cowork (mapping pro enum DB):
 *   abertas    → status != 'resolved'
 *   pendentes  → unread_count > 0
 *   aguardando → atendente respondeu por último, esperando cliente
 *   resolvidas → status == 'resolved'
 */
export type CaixaUnifStatus = 'abertas' | 'pendentes' | 'aguardando' | 'resolvidas';

/**
 * Wave 2 F1: 7 tabs canônicas paridade Inbox legacy (`InboxController::index`).
 * Substitui dropdown 4-status.
 *
 *   all            → todas exceto archived
 *   unread         → unread_count > 0
 *   assigned       → assigned_user_id == auth user
 *   bot            → bot_handling == true
 *   awaiting_human → status == 'awaiting_human' (escalada bot→humano)
 *   resolved       → status == 'resolved'
 *   archived       → status == 'archived'
 */
export type CaixaUnifTab = 'all' | 'unread' | 'assigned' | 'bot' | 'awaiting_human' | 'resolved' | 'archived';

/**
 * Catálogo canônico de canais (mesmo array do Controller pra paridade).
 * `id` = `Channel::type` do schema novo (ADR 0135).
 */
export interface ChannelCatalogItem {
  id: string;
  label: string;
  short: string;
  hue: number;
  glyph: string;
  status: 'ativo' | 'em_breve';
  count: number;
}

/**
 * Conta = Channel instance (instância concreta de um type).
 * Permite múltiplas contas por type (3× Baileys, 2× Email, etc).
 */
export interface AccountItem {
  id: number;
  channel_type: string;
  label: string;
  handle: string;
  status: 'ativo' | 'em_breve';
  owner: string | null;
  channel_health: string;
  count: number;
}

/**
 * US-WA-308 — canal ATIVO cuja sessão caiu (banner "religar" no topo da Caixa).
 * Prop eager `unhealthyChannels` — health convergido pelo cron `whatsmeow:health-probe`.
 */
export interface UnhealthyChannel {
  id: number;
  label: string;
  type: string;
  channel_health: string;
  last_health_message: string | null;
  last_health_check_at: string | null;
}

/**
 * Fila — derivada (não persiste em DB). Heurística tag → fila no Controller.
 */
export interface QueueDerived {
  slug: string;
  label: string;
  hue: number;
  sla: string | null;
}

export interface QueueConfig {
  label: string;
  hue: number;
  sla: string | null;
  trigger_tags?: string[];
}

/**
 * US-WA-301 (ADR 0267) — row completa de `whatsapp_queues` pro painel Filas
 * (QueuesSheet CRUD). `dist`/`members` persistidos; roteamento automático é
 * US futura.
 */
export interface QueueAdminItem {
  id: number;
  slug: string;
  label: string;
  hue: number;
  sla_minutes: number | null;
  sla: string | null;
  dist: string;
  trigger_tags: string[];
  sort_order: number;
  is_default: boolean;
}

export interface ConvTag {
  id: number;
  slug: string;
  label: string;
  color: string;
}

/**
 * US-WA-302 — operador atribuível (assignee picker da sidebar).
 * Payload `availableAssignees` do CaixaUnificadaController (Tier 0 — só
 * users do business atual com acesso whatsapp).
 */
export interface AssigneeItem {
  id: number;
  name: string;
}

export interface CaixaUnifConversation {
  id: number;
  channel_id: number | null;
  channel_label: string | null;
  channel_type: string | null;
  channel_status: 'ativo' | 'em_breve' | 'setup' | 'active' | string;
  channel_health: string;
  customer_external_id: string;
  contact_name: string;
  status: string;
  unread_count: number;
  last_message_at: string | null;
  last_message_preview: string | null;
  last_message_direction: 'inbound' | 'outbound' | null;
  last_inbound_at: string | null;
  tags: ConvTag[];
  queue: QueueDerived;
  preview_only: boolean;
}

export interface CaixaUnifThread {
  id: number;
  channel_id: number | null;
  channel_label: string | null;
  channel_type: string | null;
  channel_status: string;
  channel_handle: string | null;
  channel_health: string;
  customer_external_id: string;
  contact_name: string;
  status: string;
  is_blocked: boolean;
  /** US-WA-302 — assignee picker (null = sem atribuição) */
  assigned_user_id: number | null;
  assigned_user_name: string | null;
  last_inbound_at: string | null;
  last_message_at: string | null;
  created_at: string | null;
  tags: ConvTag[];
  queue: QueueDerived;
  /** US-WA-305 — true quando a fila vem de override manual (não da heurística). */
  queue_is_override: boolean;
  preview_only: boolean;
}

export interface CaixaUnifMessage {
  id: number;
  direction: 'inbound' | 'outbound';
  provider: string;
  type: string;
  body: string | null;
  status: string;
  failed_reason: string | null;
  sender_kind: 'human' | 'bot' | 'system' | null;
  sender_user_name: string | null;
  is_internal_note: boolean;
  created_at: string;
  // M6 fix 2026-05-28: media fields expostos pro componente renderizar
  // thumb/player. msgToUiArray no backend agora retorna.
  media_url?: string | null;
  media_thumbnail_url?: string | null;
  media_mime?: string | null;
  media_size_bytes?: number | null;
  media_filename?: string | null;
  media_transcription?: string | null;
  media_download_status?: 'pending' | 'downloading' | 'success' | 'failed_permanent' | null;
}

export interface Paginated<T> {
  data: T[];
  current_page: number;
  last_page: number;
  total: number;
}

export interface CaixaUnifStats {
  abertas: number;
  pendentes: number;
  aguardando: number;
  resolvidas: number;
  unread: number;
  active_accounts: number;
  queues_count: number;
  // Wave 2 F1 — counts paridade Inbox legacy (7 tabs)
  assigned: number;
  bot: number;
  awaiting_human: number;
  archived: number;
}

export interface CentrifugoConfig {
  wsUrl: string;
  token: string;
  channel: string;
}

/**
 * Iniciais do nome contato/canal pra avatar circular.
 *
 * Para nomes de pessoa: pega 1ª letra do 1º e último nome (ex: "Renato Lopes" → "RL").
 * Para nomes 1-palavra: 2 primeiras letras (ex: "Maiara" → "MA").
 * Para números de phone (sem nome): últimos 2 dígitos (ex: "+554896486699" → "99")
 *   — evita "+5" feio quando contato CRM não vinculado.
 */
export function initials(name: string): string {
  const raw = (name || '?').trim();
  if (raw === '' || raw === '?') return '?';

  // Phone-like: começa com + ou é majoritariamente dígitos
  const digitsOnly = raw.replace(/\D/g, '');
  const isPhoneLike = (raw.startsWith('+') || digitsOnly.length >= 8) && digitsOnly.length > 4;
  if (isPhoneLike) {
    return digitsOnly.slice(-2);
  }

  const parts = raw.split(/\s+/).filter(Boolean);
  if (parts.length === 0) return '?';
  const first = parts[0]!;
  if (parts.length === 1) return first.slice(0, 2).toUpperCase();
  const last = parts[parts.length - 1]!;
  return ((first[0] ?? '') + (last[0] ?? '')).toUpperCase();
}

/**
 * Hue determinístico a partir de uma seed string (nome do contato).
 * Permite avatares coloridos consistentes entre sessões. Range 0-360 OKLCH.
 */
export function avatarHue(seed: string): number {
  let h = 0;
  for (let i = 0; i < seed.length; i++) h = (h * 31 + seed.charCodeAt(i)) | 0;
  return Math.abs(h) % 360;
}

/**
 * Tempo relativo PT-BR pra rolagem da lista ("agora", "15min", "ontem").
 */
export function relativeTimeBR(iso: string | null | undefined): string {
  if (!iso) return '';
  const d = new Date(iso);
  if (isNaN(d.getTime())) return '';
  const now = new Date();
  const diffMin = Math.floor((now.getTime() - d.getTime()) / 60000);
  if (diffMin < 1) return 'agora';
  if (diffMin < 60) return `${diffMin}min`;
  if (d.toDateString() === now.toDateString()) {
    return d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
  }
  const yesterday = new Date(now); yesterday.setDate(yesterday.getDate() - 1);
  if (d.toDateString() === yesterday.toDateString()) return 'ontem';
  return d.toLocaleDateString('pt-BR', { day: '2-digit', month: 'short' });
}

/**
 * Label PT-BR pra agrupador de dia (Hoje / Ontem / data BR).
 */
export function dayGroupLabel(iso: string): string {
  const d = new Date(iso); d.setHours(0, 0, 0, 0);
  const today = new Date(); today.setHours(0, 0, 0, 0);
  const yesterday = new Date(today); yesterday.setDate(yesterday.getDate() - 1);
  if (d.getTime() === today.getTime()) return 'Hoje';
  if (d.getTime() === yesterday.getTime()) return 'Ontem';
  return d.toLocaleDateString('pt-BR', { day: '2-digit', month: 'short' });
}

/**
 * Conta filtros ativos pra badge no botão Filtros do header.
 */
export function countActiveFilters(params: {
  channelType?: string | null;
  accountId?: number | null;
  queue?: string | null;
  status?: string;
}): number {
  let n = 0;
  if (params.channelType) n++;
  if (params.accountId) n++;
  if (params.queue) n++;
  if (params.status && params.status !== 'abertas') n++;
  return n;
}

/**
 * localStorage helpers SSR-safe.
 */
export const CU_LS = {
  STATUS: 'oimpresso.caixa-unif.status',
  CHANNEL_TYPE: 'oimpresso.caixa-unif.channel_type',
  ACCOUNT_ID: 'oimpresso.caixa-unif.account_id',
  QUEUE: 'oimpresso.caixa-unif.queue',
  SEARCH: 'oimpresso.caixa-unif.search',
  SIDEBAR_COLLAPSED: 'oimpresso.caixa-unif.sidebar_collapsed',
  /** Polish V2 §6 — favoritos per-user per-browser (sem DB, sem leakage cross-tenant) */
  FAVORITES: 'oimpresso.caixa-unif.favorites',
} as const;

/**
 * Polish V2 §1 — inverso de WhatsappQueue::slaHuman ("1h"/"4h"/"45min"/"1h30" → minutos).
 */
export function parseSlaMinutes(sla: string | null | undefined): number | null {
  if (!sla) return null;
  const m = sla.trim().match(/^(?:(\d+)h)?(?:(\d+)(?:min)?)?$/);
  if (!m) return null;
  const minutes = (Number(m[1] ?? 0)) * 60 + Number(m[2] ?? 0);
  return minutes > 0 ? minutes : null;
}

// Onda 2 — 4 níveis espelhando `.om-sla-pill` do protótipo (fresh/aging/late/expired).
export type SlaState = 'fresh' | 'aging' | 'late' | 'expired' | null;

/**
 * Minutos que o cliente está esperando 1ª resposta. `null` quando não está
 * esperando (atendente respondeu por último) ou sem timestamp inbound.
 */
export function slaWaitedMin(conv: {
  last_message_direction: 'inbound' | 'outbound' | null;
  last_inbound_at: string | null;
}): number | null {
  if (conv.last_message_direction !== 'inbound' || !conv.last_inbound_at) return null;
  return (Date.now() - new Date(conv.last_inbound_at).getTime()) / 60000;
}

/** Duração compacta pra pill SLA: "12min" / "3h" / "2d". */
export function slaWaitedShort(min: number): string {
  if (min < 60) return `${Math.max(1, Math.round(min))}min`;
  if (min < 1440) return `${Math.round(min / 60)}h`;
  return `${Math.round(min / 1440)}d`;
}

/**
 * Estado do SLA de 1ª resposta (4 níveis). Só conta enquanto o cliente espera
 * (`last_message_direction === 'inbound'`) — quando o atendente responde, o
 * relógio para. Limiares sobre o SLA da fila: fresh <60% · aging 60–90% ·
 * late 90–100% · expired ≥100%.
 */
export function slaState(conv: {
  last_message_direction: 'inbound' | 'outbound' | null;
  last_inbound_at: string | null;
  queue: { sla: string | null };
}): SlaState {
  const waitedMin = slaWaitedMin(conv);
  if (waitedMin === null) return null;
  const slaMin = parseSlaMinutes(conv.queue.sla);
  if (slaMin === null) return null;
  const pct = waitedMin / slaMin;
  if (pct >= 1) return 'expired';
  if (pct >= 0.9) return 'late';
  if (pct >= 0.6) return 'aging';
  return 'fresh';
}

/**
 * Meta visual da pill SLA por estado — classes Tailwind dark-safe via tokens.
 * `late` (laranja, hue 30) não tem token → oklch arbitrário dark-aware (flipa
 * via ADR 0281); os demais usam success/warning/destructive. `pill` traz só
 * bg+texto+cor-da-borda (o `border` em si vem no render).
 */
// `pill` = header (pastel SÓLIDO, canon `.om-sla-pill`); `pillSm` = lista
// (SÓ contorno, sem fundo — canon `.om-list li .om-sla-pill.sm`). O `bg/15`
// translúcido do expired deixava a pill lavada/"desfocada" na lista.
export const SLA_META: Record<Exclude<SlaState, null>, { label: string; pill: string; pillSm: string; dot: string; pulse: boolean }> = {
  fresh: {
    label: 'no prazo',
    pill: 'bg-success-soft text-success-fg border-success/40',
    pillSm: 'text-success-fg border-success/50',
    dot: 'bg-success',
    pulse: false,
  },
  aging: {
    label: 'atenção',
    pill: 'bg-warning-soft text-warning-fg border-warning/40',
    pillSm: 'text-warning-fg border-warning/50',
    dot: 'bg-warning',
    pulse: true,
  },
  late: {
    label: 'atrasando',
    pill: 'bg-[oklch(0.94_0.07_30)] text-[oklch(0.45_0.16_30)] border-[oklch(0.84_0.09_30)] dark:bg-[oklch(0.30_0.06_30)] dark:text-[oklch(0.82_0.13_30)] dark:border-[oklch(0.42_0.08_30)]',
    pillSm: 'text-[oklch(0.45_0.16_30)] border-[oklch(0.80_0.12_30)] dark:text-[oklch(0.82_0.13_30)] dark:border-[oklch(0.45_0.10_30)]',
    dot: 'bg-[oklch(0.62_0.18_30)]',
    pulse: true,
  },
  expired: {
    label: 'estourado',
    pill: 'bg-destructive-soft text-destructive-fg border-destructive/40',
    pillSm: 'text-destructive-fg border-destructive/50',
    dot: 'bg-destructive',
    pulse: true,
  },
};

// Onda 3 — contexto comercial do cliente da conversa (Saldo + Histórico),
// agregado server-side de `transactions` (CaixaUnificadaController). `linked`
// false = conversa sem Contact CRM vinculado.
export interface CustomerContext {
  linked: boolean;
  sells_count: number;
  ltv: number;
  saldo_aberto: number;
}

/** Formata BRL pt-BR sem centavos (valores agregados): 1420 → "R$ 1.420". */
export function formatBRL(value: number): string {
  return value.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL', maximumFractionDigits: 0 });
}

export function cuLsGet(key: string, fallback = ''): string {
  if (typeof window === 'undefined') return fallback;
  return localStorage.getItem(key) ?? fallback;
}

export function cuLsSet(key: string, value: string | null): void {
  if (typeof window === 'undefined') return;
  if (value === null || value === '') localStorage.removeItem(key);
  else localStorage.setItem(key, value);
}
