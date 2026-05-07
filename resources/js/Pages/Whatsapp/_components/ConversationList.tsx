import { useEffect, useState } from 'react';
import { router } from '@inertiajs/react';

import { Card } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';

import Avatar from './Avatar';
import { relativeTime, type ListConversation } from './helpers';

interface Paginated<T> {
  data: T[];
  current_page: number;
  last_page: number;
  total: number;
}

interface Props {
  conversations: Paginated<ListConversation>;
  tab: string;
  q: string;
  stats: { unread: number; assigned: number; bot: number };
  selectedId: number | null;
  /**
   * Quando setado, clicar uma conversa faz partial reload no Index (cockpit
   * split-view). Quando null, navega via Link tradicional pro permalink.
   */
  onSelect?: (id: number) => void;
  /** Endpoint base — fallback navigate via permalink quando onSelect não setado. */
  permalinkRouteName: string;
  /** Endpoint pra atualizar tab/search (Index). */
  routeName: string;
}

export default function ConversationList({
  conversations,
  tab,
  q,
  stats,
  selectedId,
  onSelect,
  permalinkRouteName,
  routeName,
}: Props) {
  const [searchInput, setSearchInput] = useState(q);

  // Debounce search → server-side via Inertia partial reload.
  useEffect(() => {
    if (searchInput === q) return;
    const t = setTimeout(() => {
      router.get(route(routeName), { tab, q: searchInput }, {
        preserveScroll: true,
        preserveState: true,
        only: ['conversations', 'q'],
        replace: true,
      });
    }, 250);
    return () => clearTimeout(t);
  }, [searchInput, q, tab, routeName]);

  function setTab(newTab: string) {
    router.get(route(routeName), { tab: newTab, q: searchInput }, {
      preserveScroll: true,
      preserveState: true,
      only: ['conversations', 'tab', 'stats'],
    });
  }

  function navigatePage(page: number) {
    router.get(route(routeName), { tab, q: searchInput, page }, {
      preserveScroll: true,
      preserveState: true,
      only: ['conversations'],
    });
  }

  return (
    <Card className="flex flex-col overflow-hidden h-full">
      {/* Search + tabs */}
      <div className="border-b">
        <div className="p-2">
          <div className="relative">
            <input
              type="search"
              value={searchInput}
              onChange={(e) => setSearchInput(e.target.value)}
              placeholder="Buscar conversa…"
              className="w-full text-sm rounded-md border bg-background px-3 py-2 pl-8 focus:outline-none focus:ring-2 focus:ring-primary/40"
              aria-label="Buscar conversas"
            />
            <span className="absolute left-2.5 top-1/2 -translate-y-1/2 text-muted-foreground pointer-events-none">🔍</span>
          </div>
        </div>
        <div className="flex gap-0.5 px-2 pb-1 overflow-x-auto">
          <TabPill active={tab === 'all'} onClick={() => setTab('all')} label="Todas" />
          <TabPill active={tab === 'unread'} onClick={() => setTab('unread')} label="Não lidas" count={stats.unread} accent="blue" />
          <TabPill active={tab === 'assigned'} onClick={() => setTab('assigned')} label="Minhas" count={stats.assigned} />
          <TabPill active={tab === 'bot'} onClick={() => setTab('bot')} label="Bot" count={stats.bot} accent="purple" />
          <TabPill active={tab === 'resolved'} onClick={() => setTab('resolved')} label="Resolvidas" />
        </div>
      </div>

      {/* Lista */}
      <div className="flex-1 overflow-y-auto divide-y">
        {conversations.data.length === 0 ? (
          <div className="p-8 text-center text-sm text-muted-foreground">
            <div className="text-4xl opacity-30 mb-2">💬</div>
            {q ? `Nenhuma conversa para "${q}"` : 'Nenhuma conversa nessa aba.'}
          </div>
        ) : (
          conversations.data.map((conv) => (
            <ConversationRow
              key={conv.id}
              conversation={conv}
              selected={selectedId === conv.id}
              onSelect={onSelect}
              permalinkRouteName={permalinkRouteName}
            />
          ))
        )}
      </div>

      {/* Paginação compacta */}
      {conversations.last_page > 1 && (
        <div className="border-t flex items-center justify-between gap-1 px-2 py-1.5 bg-muted/30">
          <Button
            variant="ghost"
            size="sm"
            disabled={conversations.current_page === 1}
            onClick={() => navigatePage(conversations.current_page - 1)}
            className="h-7 px-2"
          >
            ←
          </Button>
          <span className="text-[11px] text-muted-foreground">
            {conversations.current_page}/{conversations.last_page} · {conversations.total} total
          </span>
          <Button
            variant="ghost"
            size="sm"
            disabled={conversations.current_page === conversations.last_page}
            onClick={() => navigatePage(conversations.current_page + 1)}
            className="h-7 px-2"
          >
            →
          </Button>
        </div>
      )}
    </Card>
  );
}

function ConversationRow({
  conversation: conv, selected, onSelect, permalinkRouteName,
}: {
  conversation: ListConversation;
  selected: boolean;
  onSelect?: (id: number) => void;
  permalinkRouteName: string;
}) {
  const hasUnread = conv.unread_count > 0;
  const handleClick = (e: React.MouseEvent) => {
    if (!onSelect) return; // deixa Link nativo
    e.preventDefault();
    onSelect(conv.id);
  };

  return (
    <a
      href={route(permalinkRouteName, conv.id)}
      onClick={handleClick}
      className={`flex items-center gap-3 px-3 py-2.5 transition cursor-pointer ${
        selected ? 'bg-accent' : hasUnread ? 'bg-blue-50/40 hover:bg-accent/60' : 'hover:bg-accent/60'
      }`}
    >
      <Avatar name={conv.contact_name} ring={hasUnread} />

      <div className="flex-1 min-w-0">
        <div className="flex items-center justify-between gap-2">
          <div className="flex items-center gap-1.5 min-w-0">
            <span className={`truncate text-sm ${hasUnread ? 'font-semibold' : 'font-medium'}`}>
              {conv.contact_name}
            </span>
            {conv.bot_handling && (
              <Badge variant="outline" className="border-purple-400 text-purple-700 bg-purple-50 text-[9px] px-1 py-0 h-3.5 leading-none shrink-0">
                bot
              </Badge>
            )}
            <StatusDot status={conv.status} />
          </div>
          <div className="text-[11px] text-muted-foreground shrink-0 tabular-nums">
            {conv.last_message_at && relativeTime(conv.last_message_at)}
          </div>
        </div>
        <div className="flex items-center justify-between gap-2 mt-0.5">
          <div className="text-xs text-muted-foreground truncate flex items-center gap-1">
            {conv.last_message_direction === 'outbound' && (
              <span className="text-emerald-600 shrink-0" aria-label="enviada">↗</span>
            )}
            <span className="truncate">
              {conv.last_message_preview ?? <span className="italic opacity-60">{conv.customer_phone}</span>}
            </span>
          </div>
          <div className="flex items-center gap-1 shrink-0">
            {!conv.within_24h_window && conv.status !== 'resolved' && conv.status !== 'archived' && (
              <span
                className="text-[9px] text-amber-700 bg-amber-50 border border-amber-200 rounded px-1 py-0"
                title="Janela 24h Meta fechada — Meta Cloud exige template"
              >
                24h
              </span>
            )}
            {hasUnread && (
              <span className="bg-blue-600 text-white text-[10px] font-semibold rounded-full min-w-[18px] h-[18px] px-1 flex items-center justify-center">
                {conv.unread_count > 99 ? '99+' : conv.unread_count}
              </span>
            )}
          </div>
        </div>
      </div>
    </a>
  );
}

function TabPill({
  active, onClick, label, count, accent,
}: {
  active: boolean;
  onClick: () => void;
  label: string;
  count?: number;
  accent?: 'blue' | 'purple';
}) {
  const accentColor = accent === 'blue' ? 'bg-blue-600' : accent === 'purple' ? 'bg-purple-600' : 'bg-muted-foreground/40';
  return (
    <button
      type="button"
      onClick={onClick}
      className={`text-xs px-2.5 py-1 rounded-md transition shrink-0 flex items-center gap-1 ${
        active ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-accent hover:text-foreground'
      }`}
    >
      {label}
      {count !== undefined && count > 0 && (
        <span className={`inline-flex items-center justify-center min-w-[16px] h-[16px] px-1 text-[9px] rounded-full font-semibold text-white ${
          active ? 'bg-white/30' : accentColor
        }`}>
          {count > 99 ? '99+' : count}
        </span>
      )}
    </button>
  );
}

function StatusDot({ status }: { status: string }) {
  const map: Record<string, { color: string; title: string }> = {
    open: { color: 'bg-blue-500', title: 'aberta' },
    awaiting_human: { color: 'bg-amber-500', title: 'aguardando humano' },
    resolved: { color: 'bg-emerald-500', title: 'resolvida' },
    archived: { color: 'bg-slate-400', title: 'arquivada' },
  };
  const conf = map[status] ?? map.open!;
  return <span className={`inline-block w-1.5 h-1.5 rounded-full shrink-0 ${conf.color}`} title={conf.title} />;
}
