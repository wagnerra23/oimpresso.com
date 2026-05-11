// @memcofre
//   tela: /atendimento/inbox
//   stories: US-WA-063 (Inbox omnichannel — lê schema novo)
//   adrs: 0135 (omnichannel)
//   spec: memory/requisitos/Whatsapp/SPEC.md
//   status: implementada Fase 0 — lê Channel + Conversation + Message do schema novo
//   permissao: whatsapp.access
//
// Substitui /whatsapp/conversations long-term — coexiste durante PR B+C.
// Driver legacy (Z-API, Meta Cloud via WhatsappBusinessConfig) ainda usa
// /whatsapp/conversations. Channel novo (Baileys, futuros Insta/Email/ML)
// aparece aqui.

import { router } from '@inertiajs/react';
import { useState } from 'react';
import {
  Inbox as InboxIcon,
  MessageCircle,
  Search,
  ArrowLeft,
} from 'lucide-react';

import AppShellV2 from '@/Layouts/AppShellV2';
import PageHeader from '@/Components/shared/PageHeader';
import EmptyState from '@/Components/shared/EmptyState';
import { Card } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';

interface InboxConversation {
  id: number;
  channel_id: number;
  channel_label: string | null;
  channel_type: string | null;
  customer_external_id: string;
  contact_name: string;
  status: 'open' | 'awaiting_human' | 'resolved' | 'archived';
  unread_count: number;
  bot_handling: boolean;
  last_message_at: string | null;
  last_inbound_at: string | null;
  within_24h_window: boolean;
}

interface InboxMessage {
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

interface InboxThread extends InboxConversation {
  created_at: string | null;
  messages_total: number;
}

interface Props {
  conversations: {
    data: InboxConversation[];
    current_page: number;
    last_page: number;
    total: number;
  };
  tab: 'all' | 'unread' | 'assigned' | 'bot' | 'resolved';
  q: string;
  channelFilter: string | null;
  stats: { unread: number; assigned: number; bot: number };
  businessId: number;
  thread: InboxThread | null;
  messages: InboxMessage[] | null;
  availableChannels: Array<{ id: number; label: string; type: string }>;
}

export default function InboxIndex({
  conversations, tab, q, channelFilter, stats, thread, messages, availableChannels,
}: Props) {
  const [searchInput, setSearchInput] = useState(q);

  function selectThread(id: number) {
    router.get(
      route('atendimento.inbox.index'),
      { tab, q, channel: channelFilter, thread: id },
      { preserveScroll: true, preserveState: true, only: ['thread', 'messages', 'conversations'] }
    );
  }

  function setTab(newTab: string) {
    router.get(route('atendimento.inbox.index'), { tab: newTab, q: searchInput, channel: channelFilter }, {
      preserveScroll: true,
      preserveState: true,
      only: ['conversations', 'tab', 'stats'],
    });
  }

  function submitSearch(e: React.FormEvent) {
    e.preventDefault();
    router.get(route('atendimento.inbox.index'), { tab, q: searchInput, channel: channelFilter }, {
      preserveScroll: true, only: ['conversations', 'q'],
    });
  }

  return (
    <div className="flex flex-col flex-1 min-h-0 gap-1 p-2">
      <PageHeader
        icon={InboxIcon}
        title="Inbox Atendimento"
        description="Mensagens recebidas em todos os canais (omnichannel). Lê schema novo — coexiste com /whatsapp/conversations legacy."
      />

      <div className="flex-1 flex flex-col lg:flex-row gap-2 min-h-0">
        {/* Painel ESQUERDO — lista */}
        <div className="lg:w-80 xl:w-96 shrink-0 min-h-0">
          <Card className="flex flex-col overflow-hidden h-full py-0 gap-0">
            <div className="border-b p-2 space-y-2">
              <form onSubmit={submitSearch} className="relative">
                <Input
                  type="search"
                  value={searchInput}
                  onChange={(e) => setSearchInput(e.target.value)}
                  placeholder="Buscar conversa..."
                  className="pl-8 h-9"
                />
                <Search size={14} className="absolute left-2.5 top-1/2 -translate-y-1/2 text-muted-foreground" aria-hidden />
              </form>

              <div className="flex gap-1 overflow-x-auto" role="tablist">
                <TabPill active={tab === 'all'} onClick={() => setTab('all')} label="Todas" total={conversations.total} />
                <TabPill active={tab === 'unread'} onClick={() => setTab('unread')} label="Não lidas" count={stats.unread} />
                <TabPill active={tab === 'assigned'} onClick={() => setTab('assigned')} label="Minhas" count={stats.assigned} />
                <TabPill active={tab === 'bot'} onClick={() => setTab('bot')} label="Bot" count={stats.bot} />
              </div>

              {availableChannels.length > 1 && (
                <div className="flex gap-1 overflow-x-auto">
                  <ChannelPill active={!channelFilter} onClick={() => router.get(route('atendimento.inbox.index'), { tab, q })} label="Todos canais" />
                  {availableChannels.map((ch) => (
                    <ChannelPill
                      key={ch.id}
                      active={channelFilter === ch.type}
                      onClick={() => router.get(route('atendimento.inbox.index'), { tab, q, channel: ch.type })}
                      label={ch.label}
                    />
                  ))}
                </div>
              )}
            </div>

            <div className="flex-1 overflow-y-auto divide-y">
              {conversations.data.length === 0 ? (
                <div className="p-4 text-center text-sm text-muted-foreground">
                  Nenhuma conversa.
                </div>
              ) : (
                conversations.data.map((c) => (
                  <button
                    key={c.id}
                    onClick={() => selectThread(c.id)}
                    className={`w-full text-left p-3 hover:bg-accent transition-colors ${thread?.id === c.id ? 'bg-accent' : ''}`}
                  >
                    <div className="flex items-start justify-between gap-2 min-w-0">
                      <div className="min-w-0 flex-1">
                        <div className="flex items-center gap-1.5">
                          <span className="font-medium text-sm truncate">{c.contact_name}</span>
                          {c.unread_count > 0 && (
                            <Badge variant="default" className="h-4 text-[10px] px-1.5">{c.unread_count}</Badge>
                          )}
                        </div>
                        {c.contact_name !== c.customer_external_id && (
                          <div className="text-[11px] text-muted-foreground truncate">{c.customer_external_id}</div>
                        )}
                        <div className="text-[10px] text-muted-foreground mt-0.5 flex items-center gap-1">
                          <Badge variant="outline" className="text-[9px] h-3 px-1">{c.channel_label || c.channel_type}</Badge>
                          {c.last_message_at && <span>{relativeTime(c.last_message_at)}</span>}
                        </div>
                      </div>
                    </div>
                  </button>
                ))
              )}
            </div>
          </Card>
        </div>

        {/* Painel CENTRO — thread */}
        <div className="flex-1 min-w-0 min-h-0">
          {thread && messages !== null ? (
            <Card className="flex flex-col overflow-hidden h-full min-w-0 py-0 gap-0">
              {/* Header thread */}
              <div className="border-b px-3 py-2 flex items-center gap-2.5">
                <Button variant="ghost" size="sm" onClick={() => router.visit(route('atendimento.inbox.index'))} className="lg:hidden">
                  <ArrowLeft size={16} />
                </Button>
                <MessageCircle size={20} className="text-primary shrink-0" aria-hidden />
                <div className="min-w-0">
                  <div className="font-semibold text-sm truncate">{thread.contact_name}</div>
                  {thread.contact_name !== thread.customer_external_id && (
                    <div className="text-xs text-muted-foreground truncate">{thread.customer_external_id}</div>
                  )}
                </div>
                <Badge variant="outline" className="ml-auto text-[10px]">{thread.channel_label || thread.channel_type}</Badge>
              </div>

              {/* Mensagens */}
              <div className="flex-1 overflow-y-auto p-4 space-y-2">
                {messages.length === 0 ? (
                  <div className="text-center text-sm text-muted-foreground py-8">
                    Sem mensagens nesta conversa ainda.
                  </div>
                ) : (
                  messages.map((m) => (
                    <MessageBubble key={m.id} message={m} />
                  ))
                )}
              </div>

              <div className="border-t p-3 bg-muted/30 text-center text-xs text-muted-foreground">
                Envio de mensagens pelo Inbox novo ainda não implementado (PR seguinte refator drivers pra consumir Channel).
              </div>
            </Card>
          ) : (
            <Card className="h-full flex items-center justify-center bg-muted/20">
              <EmptyState
                icon="message-circle"
                title="Selecione uma conversa"
                description={`${conversations.total} conversas no schema novo (omnichannel).`}
              />
            </Card>
          )}
        </div>
      </div>
    </div>
  );
}

function TabPill({ active, onClick, label, count, total }: { active: boolean; onClick: () => void; label: string; count?: number; total?: number }) {
  const num = count ?? total;
  return (
    <button
      onClick={onClick}
      className={`px-2.5 py-1 text-xs rounded-md transition-colors whitespace-nowrap ${
        active ? 'bg-primary text-primary-foreground' : 'bg-muted hover:bg-accent text-muted-foreground'
      }`}
    >
      {label}
      {num !== undefined && num > 0 && <span className="ml-1 opacity-75">{num}</span>}
    </button>
  );
}

function ChannelPill({ active, onClick, label }: { active: boolean; onClick: () => void; label: string }) {
  return (
    <button
      onClick={onClick}
      className={`px-2 py-0.5 text-[10px] rounded transition-colors whitespace-nowrap ${
        active ? 'bg-accent text-accent-foreground' : 'bg-muted/50 hover:bg-accent text-muted-foreground'
      }`}
    >
      {label}
    </button>
  );
}

function MessageBubble({ message }: { message: InboxMessage }) {
  const isOut = message.direction === 'outbound';
  const time = new Date(message.created_at).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });

  return (
    <div className={`flex ${isOut ? 'justify-end' : 'justify-start'}`}>
      <div className={`max-w-[75%] px-3 py-1.5 rounded-2xl shadow-sm ${
        isOut ? 'bg-primary text-primary-foreground rounded-br-md' : 'bg-card border rounded-bl-md'
      }`}>
        {message.body ? (
          <div className="whitespace-pre-wrap break-words text-sm">{message.body}</div>
        ) : (
          <em className="text-xs opacity-70">[{message.type}]</em>
        )}
        <div className="text-[10px] mt-0.5 opacity-70 text-right">
          {time}
          {isOut && <span className="ml-1">{message.status}</span>}
        </div>
      </div>
    </div>
  );
}

function relativeTime(iso: string): string {
  const d = new Date(iso);
  const now = new Date();
  const diffMin = Math.floor((now.getTime() - d.getTime()) / 60000);
  if (diffMin < 1) return 'agora';
  if (diffMin < 60) return `${diffMin}min`;
  if (d.toDateString() === now.toDateString()) {
    return d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
  }
  return d.toLocaleDateString('pt-BR', { day: '2-digit', month: 'short' });
}

InboxIndex.layout = (page: React.ReactElement) => <AppShellV2>{page}</AppShellV2>;
