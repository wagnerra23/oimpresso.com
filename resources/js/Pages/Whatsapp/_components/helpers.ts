// Helpers compartilhados pelas páginas Whatsapp/Conversations (Index/Show).
// ADR 0039 (Chat Cockpit pattern), ADR 0096 (Whatsapp module).

/**
 * localStorage com prefixo `oimpresso.whatsapp.*` (ADR 0039 §4 + R-DS-012).
 * SSR-safe (typeof window check).
 */
export const LS = {
  TAB: 'oimpresso.whatsapp.tab',
  Q: 'oimpresso.whatsapp.q',
  THREAD: 'oimpresso.whatsapp.thread',
  SIDEBAR_COLLAPSED: 'oimpresso.whatsapp.sidebar_collapsed',
  LEFT_SIDEBAR_COLLAPSED: 'oimpresso.whatsapp.left_sidebar_collapsed',
  // Filtros novos do Inbox (US-WA-* — within_24h/unlinked/aging/orderBy).
  // Wagner 2026-05-12: persistência POR session/business, sem perfil global.
  // Não inclui o businessId no path porque LS é per-browser per-user já;
  // se time compartilhar máquina, cada login limpa via lsSet null.
  WITHIN_24H: 'oimpresso.whatsapp.within_24h',
  UNLINKED: 'oimpresso.whatsapp.unlinked',
  MEDIA_INBOUND_24H: 'oimpresso.whatsapp.media_inbound_24h',
  INBOUND_AGING: 'oimpresso.whatsapp.inbound_aging',
  ORDER_BY: 'oimpresso.whatsapp.order_by',
} as const;

export function lsGet(key: string, fallback = ''): string {
  if (typeof window === 'undefined') return fallback;
  return localStorage.getItem(key) ?? fallback;
}

export function lsSet(key: string, value: string | null): void {
  if (typeof window === 'undefined') return;
  if (value === null || value === '') localStorage.removeItem(key);
  else localStorage.setItem(key, value);
}

export function getInitials(name: string): string {
  const parts = name.trim().split(/\s+/).filter(Boolean);
  if (parts.length === 0) return '?';
  const first = parts[0]!;
  if (parts.length === 1) return first.slice(0, 2).toUpperCase();
  const last = parts[parts.length - 1]!;
  return ((first[0] ?? '') + (last[0] ?? '')).toUpperCase();
}

const AVATAR_COLORS = [
  'bg-blue-500', 'bg-emerald-500', 'bg-purple-500', 'bg-amber-500',
  'bg-pink-500', 'bg-cyan-500', 'bg-indigo-500', 'bg-rose-500',
  'bg-teal-500', 'bg-orange-500',
];

export function pickColor(seed: string): string {
  let h = 0;
  for (let i = 0; i < seed.length; i++) h = (h * 31 + seed.charCodeAt(i)) | 0;
  return AVATAR_COLORS[Math.abs(h) % AVATAR_COLORS.length] ?? 'bg-slate-500';
}

export function relativeTime(iso: string): string {
  const d = new Date(iso);
  const now = new Date();
  const diffMs = now.getTime() - d.getTime();
  const diffMin = Math.floor(diffMs / 60000);
  if (diffMin < 1) return 'agora';
  if (diffMin < 60) return `${diffMin}min`;
  if (d.toDateString() === now.toDateString()) {
    return d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
  }
  const yesterday = new Date(now); yesterday.setDate(yesterday.getDate() - 1);
  if (d.toDateString() === yesterday.toDateString()) return 'ontem';
  if (d.getFullYear() === now.getFullYear()) {
    return d.toLocaleDateString('pt-BR', { day: '2-digit', month: 'short' });
  }
  return d.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: '2-digit' });
}

export function formatDateTime(iso: string): string {
  return new Date(iso).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' });
}

/**
 * Formata bytes em string legível (B / KB / MB). Usado em MediaPreviewCard
 * (US-WA-042) + MessageBubble document (US-WA-072) + MediaContent
 * (ConversationThread, PR #707/#716). Exportado pra reuso cross-component.
 */
export function formatBytes(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
  return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}

/**
 * Detecta se `phone` parece ser um LID (Linked ID Multi-Device) em vez de
 * phone real. WhatsApp envia LID como remoteJid quando cliente fala via
 * Click-to-Chat / Status / Ads — Baileys 6.7.9 não decodifica (workaround
 * em `LidPhoneResolver` cobre quando senderPn vem; quando não vem, mostra
 * badge "número oculto" na UI).
 *
 * Critérios (espelha `LidPhoneResolver::isLid` PHP):
 *  - Contém sufixo `@lid`.
 *  - String só dígitos com 14+ chars E não começa com DDI BR (55).
 *
 * Falso positivo: phones internacionais 14+ dígitos sem DDI BR. Aceitável
 * em ROTA LIVRE biz=1 (todos BR); reavaliar quando onboardar tenants
 * internacionais.
 */
export function isLikelyLid(phone: string | null | undefined): boolean {
  if (!phone) return false;
  if (phone.includes('@lid')) return true;
  const digits = phone.replace(/\D+/g, '');
  if (digits.length >= 14 && !digits.startsWith('55')) return true;
  return false;
}

export interface Message {
  id: number;
  direction: 'inbound' | 'outbound';
  provider: string;
  type: string;
  body: string | null;
  status: string;
  failed_reason: string | null;
  sender_kind: 'human' | 'bot' | 'system' | null;
  /**
   * Nome curto do atendente que enviou (web UI). Populado em outbound
   * com `sender_kind='human'` quando enviado via `/atendimento/inbox`.
   * Null em inbound, outbound de chip externo (celular), ou bot. US-WA-077.
   */
  sender_user_name?: string | null;
  /**
   * US-WA-071 (ADR 0142): true = nota interna estilo Chatwoot. Não foi
   * enviada pro WhatsApp do cliente — só atendentes do business veem.
   * Backend gateia dispatch driver com `is_internal_note=false`.
   */
  is_internal_note?: boolean;
  /**
   * US-WA-072 — mídia (image/audio/document/video/sticker). URLs resolvidas
   * server-side via Storage::temporaryUrl (S3) ou Storage::url (local public).
   * Thumbnail only para image (256x256 jpeg). Transcription only para audio
   * (Whisper output, ~10s após inbound). Filename only para document.
   */
  media_url?: string | null;
  media_mime?: string | null;
  media_size_bytes?: number | null;
  media_duration_s?: number | null;
  media_thumbnail_url?: string | null;
  media_transcription?: string | null;
  media_filename?: string | null;
  created_at: string;
}

export interface AssignedUser {
  id: number;
  name: string;
}

/**
 * Tag classificadora de conversa (US-WA-063). Multi-tenant Tier 0 — tags
 * vivem em `whatsapp_tags.business_id`, frontend só vê as do business
 * atual. `color` é key Tailwind palette (resolvido pra classes via
 * mapping interno em ConversationSidebar).
 */
export interface ConvTag {
  id: number;
  slug: string;
  label: string;
  color: string;
}

/**
 * Contact UltimatePOS vinculado à Conversation (US-WA-064). Multi-tenant
 * Tier 0 — backend só retorna Contact do business_id atual. Não inclui
 * PII LGPD sensível (tax_number/cpf_cnpj) — só dados de display.
 */
export interface LinkedContact {
  id: number;
  name: string;
  mobile: string | null;
  landline: string | null;
  email: string | null;
  type: string;
  /** URL pra editar no UltimatePOS legacy (`/contacts/{id}`) */
  edit_url: string;
}

/**
 * Search result no ContactPickerModal (US-WA-064). Shape menor que
 * `LinkedContact` (sem edit_url — gerado client-side se necessário).
 */
export interface ContactSearchResult {
  id: number;
  name: string;
  mobile: string | null;
  landline: string | null;
  email: string | null;
  type: string;
  supplier_business_name: string | null;
}

export interface ThreadConversation {
  id: number;
  customer_phone: string;
  contact_name: string;
  status: 'open' | 'awaiting_human' | 'resolved' | 'archived';
  bot_handling: boolean;
  within_24h_window: boolean;
  last_inbound_at: string | null;
  last_message_at: string | null;
  created_at: string | null;
  assigned_user: AssignedUser | null;
  messages_total: number;
  /** US-WA-063: tags aplicadas à conversa */
  tags?: ConvTag[];
  /** US-WA-064: Contact UltimatePOS vinculado (CRM). Null se não vinculado. */
  linked_contact?: LinkedContact | null;
  /** US-WA-066: contato bloqueado — webhook inbound dropa msgs novas,
   * composer fica disabled, badge "BLOQUEADO" no header. */
  is_blocked?: boolean;
}

export interface CentrifugoConfig {
  wsUrl: string;
  token: string;
  channel: string;
}

export interface ReadyTemplate {
  id: number;
  name: string;
  language: string;
  category: 'UTILITY' | 'MARKETING' | 'AUTHENTICATION';
  provider: 'meta_cloud' | 'zapi' | 'baileys';
  status: 'LOCAL' | 'APPROVED';
  body: string; // Body cru com placeholders {{1}}, {{2}}
}

/**
 * Extrai placeholders únicos {{N}} ou {{nome}} do body do template,
 * preservando ordem de primeira ocorrência.
 *
 * Ex: "Olá {{1}}, OS #{{2}} pronta. Att, {{1}}" → ['1', '2']
 */
export function extractPlaceholders(body: string): string[] {
  const regex = /\{\{([^{}]+)\}\}/g;
  const seen: string[] = [];
  let match: RegExpExecArray | null;
  while ((match = regex.exec(body)) !== null) {
    const key = match[1]!.trim();
    if (!seen.includes(key)) seen.push(key);
  }
  return seen;
}

/**
 * Substitui placeholders pelos valores em params (suporta {{1}} e {{nome}}).
 */
export function expandTemplateBody(body: string, params: Record<string, string>): string {
  let out = body;
  for (const [key, value] of Object.entries(params)) {
    out = out.replaceAll(`{{${key}}}`, value);
  }
  return out;
}

export interface ListConversation {
  id: number;
  customer_phone: string;
  contact_name: string;
  status: 'open' | 'awaiting_human' | 'resolved' | 'archived';
  unread_count: number;
  bot_handling: boolean;
  last_message_at: string | null;
  last_inbound_at: string | null;
  within_24h_window: boolean;
  last_message_preview: string | null;
  last_message_direction: 'inbound' | 'outbound' | null;
  /** US-WA-043: type da última msg (text/image/audio/video/document/...) pra UI
   *  mostrar ícone semântico ao lado do preview. Subquery scalar em
   *  `InboxController::index()` — null se conv vazia. */
  last_message_type?: string | null;
  /** US-WA-063: tags aplicadas — eager-loaded no InboxController */
  tags?: ConvTag[];
}

/**
 * Agrupa mensagens por dia (Hoje / Ontem / DD MMM).
 * Dentro de um grupo, marca quando o sender muda — usado para esconder
 * timestamp em mensagens consecutivas do mesmo lado (estilo WhatsApp).
 */
export function groupByDay(messages: Message[]): Array<{ label: string; messages: Message[] }> {
  const today = new Date(); today.setHours(0, 0, 0, 0);
  const yesterday = new Date(today); yesterday.setDate(yesterday.getDate() - 1);

  const groups = new Map<string, Message[]>();
  for (const m of messages) {
    const d = new Date(m.created_at); d.setHours(0, 0, 0, 0);
    let label: string;
    if (d.getTime() === today.getTime()) label = 'Hoje';
    else if (d.getTime() === yesterday.getTime()) label = 'Ontem';
    else label = d.toLocaleDateString('pt-BR', {
      day: '2-digit',
      month: 'short',
      year: d.getFullYear() !== today.getFullYear() ? 'numeric' : undefined,
    });
    const arr = groups.get(label) ?? [];
    arr.push(m);
    groups.set(label, arr);
  }
  return Array.from(groups, ([label, messages]) => ({ label, messages }));
}
