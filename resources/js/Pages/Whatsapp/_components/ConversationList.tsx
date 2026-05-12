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
  Clock,
  UserPlus,
  X,
  ArrowDownUp,
} from 'lucide-react';

import { Card } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';

import Avatar from './Avatar';
import { LS, lsSet, relativeTime, type ListConversation } from './helpers';

type InboundAging = '6h' | '12h' | '24h' | '48h' | '7d' | null;
type OrderBy = 'last_message' | 'inbound';

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
  stats: {
    unread: number;
    assigned: number;
    bot: number;
    awaiting_human?: number;
    archived?: number;
  };
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
  /**
   * Filtros novos — todos opcionais pra back-compat com /whatsapp/conversations
   * legacy. Quando undefined, renderiza UI sem a linha de chips.
   */
  within24h?: boolean | null;
  unlinked?: boolean;
  inboundAging?: InboundAging;
  orderBy?: OrderBy;
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
  within24h,
  unlinked,
  inboundAging,
  orderBy,
}: Props) {
  const [searchInput, setSearchInput] = useState(q);

  // Constrói o objeto de query params preservando os filtros novos.
  // Centraliza a serialização pra evitar duplicação em cada `router.get`.
  function buildQuery(overrides: Record<string, unknown> = {}) {
    const params: Record<string, unknown> = {
      tab,
      q: searchInput,
    };
    if (within24h !== undefined && within24h !== null) params.within_24h = within24h ? 'true' : 'false';
    if (unlinked) params.unlinked = 'true';
    if (inboundAging) params.inbound_aging = inboundAging;
    if (orderBy && orderBy !== 'last_message') params.orderBy = orderBy;
    return { ...params, ...overrides };
  }

  // Debounce search → server-side via Inertia partial reload + persistência localStorage.
  useEffect(() => {
    if (searchInput === q) return;
    const t = setTimeout(() => {
      lsSet(LS.Q, searchInput);
      router.get(route(routeName), buildQuery({ q: searchInput }), {
        preserveScroll: true,
        preserveState: true,
        only: ['conversations', 'q'],
        replace: true,
      });
    }, 250);
    return () => clearTimeout(t);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [searchInput, q, tab, routeName]);

  function setTab(newTab: string) {
    lsSet(LS.TAB, newTab);
    router.get(route(routeName), buildQuery({ tab: newTab }), {
      preserveScroll: true,
      preserveState: true,
      only: ['conversations', 'tab', 'stats'],
    });
  }

  function navigatePage(page: number) {
    router.get(route(routeName), buildQuery({ page }), {
      preserveScroll: true,
      preserveState: true,
      only: ['conversations'],
    });
  }

  /**
   * Toggle within_24h tri-estado: null → true → false → null. Persiste em LS
   * pra preservar entre sessões (Wagner 2026-05-12: per-browser, sem perfil).
   */
  function cycleWithin24h() {
    let next: boolean | null;
    if (within24h === null || within24h === undefined) next = true;
    else if (within24h === true) next = false;
    else next = null;
    lsSet(LS.WITHIN_24H, next === null ? null : (next ? 'true' : 'false'));
    const params = buildQuery();
    if (next === null) delete (params as Record<string, unknown>).within_24h;
    else params.within_24h = next ? 'true' : 'false';
    router.get(route(routeName), params, {
      preserveScroll: true, preserveState: true,
      only: ['conversations', 'stats', 'within24h'],
    });
  }

  function toggleUnlinked() {
    const next = !unlinked;
    lsSet(LS.UNLINKED, next ? '1' : null);
    const params = buildQuery();
    if (next) params.unlinked = 'true';
    else delete (params as Record<string, unknown>).unlinked;
    router.get(route(routeName), params, {
      preserveScroll: true, preserveState: true,
      only: ['conversations', 'unlinked'],
    });
  }

  function setInboundAging(value: InboundAging) {
    lsSet(LS.INBOUND_AGING, value);
    const params = buildQuery();
    if (value) params.inbound_aging = value;
    else delete (params as Record<string, unknown>).inbound_aging;
    router.get(route(routeName), params, {
      preserveScroll: true, preserveState: true,
      only: ['conversations', 'inboundAging'],
    });
  }

  function setOrderBy(value: OrderBy) {
    lsSet(LS.ORDER_BY, value === 'last_message' ? null : value);
    const params = buildQuery();
    if (value === 'last_message') delete (params as Record<string, unknown>).orderBy;
    else params.orderBy = value;
    router.get(route(routeName), params, {
      preserveScroll: true, preserveState: true,
      only: ['conversations', 'orderBy'],
    });
  }

  function resetFilters() {
    [LS.WITHIN_24H, LS.UNLINKED, LS.INBOUND_AGING, LS.ORDER_BY].forEach((k) => lsSet(k, null));
    router.get(route(routeName), { tab, q: searchInput }, {
      preserveScroll: true, preserveState: true,
    });
  }

  // Conta filtros ativos pra mostrar badge no botão "Limpar"
  const activeFiltersCount = [
    within24h !== null && within24h !== undefined,
    unlinked,
    !!inboundAging,
    orderBy && orderBy !== 'last_message',
  ].filter(Boolean).length;

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
          <TabPill
            active={tab === 'awaiting_human'}
            onClick={() => setTab('awaiting_human')}
            label="Aguardando"
            count={stats.awaiting_human}
            title="Bot escalou pra humano — fila de atendimento manual"
          />
          <TabPill active={tab === 'resolved'} onClick={() => setTab('resolved')} label="Resolvidas" />
          <TabPill
            active={tab === 'archived'}
            onClick={() => setTab('archived')}
            label="Arquivadas"
            count={stats.archived}
            title="Conversas arquivadas pelo atendente"
          />
        </div>

        {/* Linha de filtros secundários — chips + dropdown. Só renderiza se o
            pai passou os props (within24h !== undefined indica modo Inbox).
            Mostra "Limpar" apenas quando há filtros ativos. */}
        {within24h !== undefined && (
          <div className="flex flex-wrap items-center gap-1 px-2 pb-1.5">
            <FilterChip
              active={unlinked === true}
              onClick={toggleUnlinked}
              icon={<UserPlus size={11} aria-hidden />}
              label="Sem contato CRM"
              title="Conversas sem Contact UltimatePOS vinculado (oportunidade de cadastro)"
            />
            <FilterChip
              active={within24h === true}
              onClick={cycleWithin24h}
              icon={<Clock size={11} aria-hidden />}
              label={within24h === false ? '24h fechada' : '24h aberta'}
              title="Janela 24h Meta — clique alterna: aberta → fechada → sem filtro"
            />
            {/* Aging dropdown — botão compacto que abre native select */}
            <div className="relative">
              <select
                value={inboundAging ?? ''}
                onChange={(e) => setInboundAging((e.target.value || null) as InboundAging)}
                aria-label="Esperando resposta há mais de"
                className={`text-[11px] h-6 px-1.5 pr-5 rounded border bg-card cursor-pointer hover:bg-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring appearance-none ${
                  inboundAging ? 'border-primary text-primary font-medium' : 'border-border text-muted-foreground'
                }`}
              >
                <option value="">Esperando há…</option>
                <option value="6h">&gt; 6h</option>
                <option value="12h">&gt; 12h</option>
                <option value="24h">&gt; 24h</option>
                <option value="48h">&gt; 48h</option>
                <option value="7d">&gt; 7 dias</option>
              </select>
              <Clock
                size={10}
                className="absolute right-1 top-1/2 -translate-y-1/2 text-muted-foreground pointer-events-none"
                aria-hidden
              />
            </div>
            {/* Ordenação — dropdown */}
            <div className="relative">
              <select
                value={orderBy ?? 'last_message'}
                onChange={(e) => setOrderBy(e.target.value as OrderBy)}
                aria-label="Ordenar por"
                className={`text-[11px] h-6 px-1.5 pr-5 rounded border bg-card cursor-pointer hover:bg-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring appearance-none ${
                  orderBy === 'inbound' ? 'border-primary text-primary font-medium' : 'border-border text-muted-foreground'
                }`}
              >
                <option value="last_message">Ordenar: última msg</option>
                <option value="inbound">Ordenar: última do cliente</option>
              </select>
              <ArrowDownUp
                size={10}
                className="absolute right-1 top-1/2 -translate-y-1/2 text-muted-foreground pointer-events-none"
                aria-hidden
              />
            </div>
            {activeFiltersCount > 0 && (
              <button
                type="button"
                onClick={resetFilters}
                className="text-[11px] h-6 px-1.5 rounded border border-border text-muted-foreground hover:text-foreground hover:bg-accent transition-colors inline-flex items-center gap-0.5"
                title="Limpar todos os filtros"
                aria-label="Limpar filtros"
              >
                <X size={11} aria-hidden />
                Limpar ({activeFiltersCount})
              </button>
            )}
          </div>
        )}
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
  active, onClick, label, count, title,
}: {
  active: boolean;
  onClick: () => void;
  label: string;
  count?: number;
  title?: string;
}) {
  return (
    <Button
      type="button"
      variant={active ? 'default' : 'ghost'}
      size="sm"
      onClick={onClick}
      role="tab"
      aria-selected={active}
      title={title}
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

/**
 * Chip de filtro toggle binário (Sem contato CRM, 24h aberta/fechada).
 * Visual: pill compacto h-6, ativo = border-primary + bg accent.
 */
function FilterChip({
  active, onClick, icon, label, title,
}: {
  active: boolean;
  onClick: () => void;
  icon: React.ReactNode;
  label: string;
  title?: string;
}) {
  return (
    <button
      type="button"
      onClick={onClick}
      title={title}
      aria-pressed={active}
      className={`text-[11px] h-6 px-1.5 rounded border transition-colors inline-flex items-center gap-1 cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring ${
        active
          ? 'border-primary bg-primary/10 text-primary font-medium'
          : 'border-border text-muted-foreground hover:text-foreground hover:bg-accent'
      }`}
    >
      {icon}
      {label}
    </button>
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
