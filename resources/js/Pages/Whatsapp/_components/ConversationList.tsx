import { useEffect, useState } from 'react';
import { router } from '@inertiajs/react';
import {
  Search,
  ArrowUpRight,
  Bot,
  ChevronLeft,
  ChevronRight,
  MessageCircle,
  AlertTriangle,
} from 'lucide-react';

import { Card } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';

import Avatar from './Avatar';
import { LS, lsSet, relativeTime, type ListConversation } from './helpers';

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
  /** Quando fornecido, renderiza botão minimizar lateral (chama callback). */
  onCollapse?: () => void;
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
  onCollapse,
}: Props) {
  const [searchInput, setSearchInput] = useState(q);

  // Debounce search → server-side via Inertia partial reload + persistência localStorage.
  useEffect(() => {
    if (searchInput === q) return;
    const t = setTimeout(() => {
      lsSet(LS.Q, searchInput);
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
    lsSet(LS.TAB, newTab);
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

  // Atalhos teclado: J/K navega lista, "/" foca search (ADR 0039 §2).
  useEffect(() => {
    if (!onSelect) return; // só ativa em modo cockpit (Index)
    const select = onSelect; // narrowing pra closure
    function handler(e: KeyboardEvent) {
      // não interferir em inputs
      if (
        e.target instanceof HTMLInputElement ||
        e.target instanceof HTMLTextAreaElement ||
        (e.target instanceof HTMLElement && e.target.isContentEditable)
      ) return;

      if (e.key === '/') {
        e.preventDefault();
        const input = document.querySelector<HTMLInputElement>('[data-whatsapp-search]');
        input?.focus();
        return;
      }

      const list = conversations.data;
      if (list.length === 0) return;
      const currentIdx = selectedId ? list.findIndex((c) => c.id === selectedId) : -1;
      if (e.key === 'j' || e.key === 'J') {
        e.preventDefault();
        const next = list[Math.min(currentIdx + 1, list.length - 1)];
        if (next) select(next.id);
      }
      if (e.key === 'k' || e.key === 'K') {
        e.preventDefault();
        const prev = list[Math.max(currentIdx - 1, 0)];
        if (prev) select(prev.id);
      }
    }
    window.addEventListener('keydown', handler);
    return () => window.removeEventListener('keydown', handler);
  }, [onSelect, selectedId, conversations.data]);

  return (
    <Card className="flex flex-col overflow-hidden h-full py-0 gap-0">
      {/* Search + tabs */}
      <div className="border-b">
        <div className="p-2 flex items-center gap-1.5">
          <div className="relative flex-1">
            <Input
              type="search"
              data-whatsapp-search
              value={searchInput}
              onChange={(e) => setSearchInput(e.target.value)}
              placeholder="Buscar conversa…  (atalho /)"
              className="pl-8 h-9"
              aria-label="Buscar conversas"
            />
            <Search
              size={14}
              className="absolute left-2.5 top-1/2 -translate-y-1/2 text-muted-foreground pointer-events-none"
              aria-hidden
            />
          </div>
          {onCollapse && (
            <button
              type="button"
              onClick={onCollapse}
              className="shrink-0 w-7 h-7 rounded hover:bg-accent text-muted-foreground hover:text-foreground flex items-center justify-center transition-colors"
              title="Minimizar lista"
              aria-label="Minimizar lista de conversas"
            >
              <ChevronLeft size={14} />
            </button>
          )}
        </div>
        <div className="flex gap-0.5 px-2 pb-1 overflow-x-auto" role="tablist">
          <TabPill active={tab === 'all'} onClick={() => setTab('all')} label="Todas" />
          <TabPill active={tab === 'unread'} onClick={() => setTab('unread')} label="Não lidas" count={stats.unread} />
          <TabPill active={tab === 'assigned'} onClick={() => setTab('assigned')} label="Minhas" count={stats.assigned} />
          <TabPill active={tab === 'bot'} onClick={() => setTab('bot')} label="Bot" count={stats.bot} />
          <TabPill active={tab === 'resolved'} onClick={() => setTab('resolved')} label="Resolvidas" />
        </div>
      </div>

      {/* Lista */}
      <div className="flex-1 overflow-y-auto divide-y divide-border" role="list">
        {conversations.data.length === 0 ? (
          <div className="p-8 text-center text-sm text-muted-foreground flex flex-col items-center gap-2">
            <MessageCircle size={36} className="opacity-30" aria-hidden />
            <span>{q ? `Nenhuma conversa para "${q}"` : 'Nenhuma conversa nessa aba.'}</span>
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
            aria-label="Página anterior"
          >
            <ChevronLeft size={14} aria-hidden />
          </Button>
          <span className="text-xs text-muted-foreground">
            {conversations.current_page}/{conversations.last_page} · {conversations.total} total
          </span>
          <Button
            variant="ghost"
            size="sm"
            disabled={conversations.current_page === conversations.last_page}
            onClick={() => navigatePage(conversations.current_page + 1)}
            className="h-7 px-2"
            aria-label="Próxima página"
          >
            <ChevronRight size={14} aria-hidden />
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
      role="listitem"
      aria-current={selected ? 'true' : undefined}
      className={`flex items-center gap-3 px-3 py-2.5 transition cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-inset ${
        selected ? 'bg-accent' : hasUnread ? 'bg-primary/5 hover:bg-accent/60' : 'hover:bg-accent/60'
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
              <Badge
                variant="outline"
                className="border-purple-400 text-purple-700 dark:text-purple-300 dark:border-purple-500 bg-purple-50 dark:bg-purple-950/30 text-[10px] px-1 py-0 h-3.5 leading-none shrink-0 gap-0.5"
              >
                <Bot size={10} aria-hidden />
                bot
              </Badge>
            )}
            <StatusDot status={conv.status} />
          </div>
          <div className="text-xs text-muted-foreground shrink-0 tabular-nums">
            {conv.last_message_at && relativeTime(conv.last_message_at)}
          </div>
        </div>
        <div className="flex items-center justify-between gap-2 mt-0.5">
          <div className="text-xs text-muted-foreground truncate flex items-center gap-1">
            {conv.last_message_direction === 'outbound' && (
              <ArrowUpRight size={11} className="text-emerald-600 dark:text-emerald-400 shrink-0" aria-label="enviada" />
            )}
            <span className="truncate">
              {conv.last_message_preview ?? <span className="italic opacity-60">{conv.customer_phone}</span>}
            </span>
          </div>
          <div className="flex items-center gap-1 shrink-0">
            {!conv.within_24h_window && conv.status !== 'resolved' && conv.status !== 'archived' && (
              <span
                className="text-[10px] text-amber-700 dark:text-amber-400 bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-700 rounded px-1 py-0 inline-flex items-center gap-0.5"
                title="Janela 24h Meta fechada — Meta Cloud exige template"
              >
                <AlertTriangle size={9} aria-hidden />
                24h
              </span>
            )}
            {hasUnread && (
              <span className="bg-primary text-primary-foreground text-[10px] font-semibold rounded-full min-w-[18px] h-[18px] px-1 flex items-center justify-center">
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
  active, onClick, label, count,
}: {
  active: boolean;
  onClick: () => void;
  label: string;
  count?: number;
}) {
  return (
    <Button
      type="button"
      variant={active ? 'default' : 'ghost'}
      size="sm"
      onClick={onClick}
      role="tab"
      aria-selected={active}
      className="text-xs px-2.5 h-7 shrink-0 gap-1"
    >
      {label}
      {count !== undefined && count > 0 && (
        <span className={`inline-flex items-center justify-center min-w-[16px] h-4 px-1 text-[10px] rounded-full font-semibold ${
          active ? 'bg-primary-foreground/25 text-primary-foreground' : 'bg-primary text-primary-foreground'
        }`}>
          {count > 99 ? '99+' : count}
        </span>
      )}
    </Button>
  );
}

function StatusDot({ status }: { status: string }) {
  // Cores fixas semânticas (R-DS-002 exceção: status badges com cores semânticas constantes).
  const map: Record<string, { color: string; title: string }> = {
    open: { color: 'bg-blue-500', title: 'aberta' },
    awaiting_human: { color: 'bg-amber-500', title: 'aguardando humano' },
    resolved: { color: 'bg-emerald-500', title: 'resolvida' },
    archived: { color: 'bg-slate-400', title: 'arquivada' },
  };
  const conf = map[status] ?? map.open!;
  return <span className={`inline-block w-1.5 h-1.5 rounded-full shrink-0 ${conf.color}`} title={conf.title} aria-label={conf.title} />;
}
