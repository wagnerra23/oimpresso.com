// Helpers compartilhados pelas páginas Whatsapp/Conversations (Index/Show).
// ADR 0039 (Chat Cockpit pattern), ADR 0096 (Whatsapp module).

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

export interface Message {
  id: number;
  direction: 'inbound' | 'outbound';
  provider: string;
  type: string;
  body: string | null;
  status: string;
  failed_reason: string | null;
  sender_kind: 'human' | 'bot' | 'system' | null;
  created_at: string;
}

export interface AssignedUser {
  id: number;
  name: string;
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
}

export interface CentrifugoConfig {
  wsUrl: string;
  token: string;
  channel: string;
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
