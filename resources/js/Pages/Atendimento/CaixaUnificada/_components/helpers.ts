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

export interface ConvTag {
  id: number;
  slug: string;
  label: string;
  color: string;
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
  last_inbound_at: string | null;
  last_message_at: string | null;
  created_at: string | null;
  tags: ConvTag[];
  queue: QueueDerived;
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
 */
export function initials(name: string): string {
  const parts = (name || '?').trim().split(/\s+/).filter(Boolean);
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
} as const;

export function cuLsGet(key: string, fallback = ''): string {
  if (typeof window === 'undefined') return fallback;
  return localStorage.getItem(key) ?? fallback;
}

export function cuLsSet(key: string, value: string | null): void {
  if (typeof window === 'undefined') return;
  if (value === null || value === '') localStorage.removeItem(key);
  else localStorage.setItem(key, value);
}
