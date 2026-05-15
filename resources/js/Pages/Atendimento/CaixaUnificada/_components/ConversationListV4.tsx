// ConversationListV4.tsx — coluna esquerda da Caixa Unificada V4.
//
// Replica visual `.om-list-c` / `.om-list` do Cowork (inbox-page.css L294-400):
//   - header com "Conversas" + count + dropdown status (Abertas/Pendentes/Aguardando/Resolvidas)
//   - busca inline com clear (✕)
//   - empty state PT-BR
//   - lista 36px avatar + nome/preview + assignee + unread badge
//   - linha tem border-left colorida pela fila (--om-q-color)
//   - preview-only convs ficam mais discretas (ghost)
//
// Não reusa @/Pages/Whatsapp/_components/ConversationList — shape diferente
// (channel polimórfico + queue derivada + preview_only) e visual Cowork ≠
// Cockpit V2. Reuso fica em payload backend.

import { useMemo, useState } from 'react';
import { router } from '@inertiajs/react';
import { Search, X } from 'lucide-react';
import { cn } from '@/Lib/utils';
import {
  type CaixaUnifConversation,
  type CaixaUnifStatus,
  type CaixaUnifTab,
  type ChannelCatalogItem,
  type Paginated,
  type CaixaUnifStats,
  initials,
  avatarHue,
  relativeTimeBR,
} from './helpers';

interface Props {
  conversations: Paginated<CaixaUnifConversation>;
  channels: ChannelCatalogItem[];
  stats: CaixaUnifStats | null;
  selectedId: number | null;
  // Wave 2 F1: `tab` substitui `status` (7 valores). Mantém prop `status`
  // como alias retrocompatível durante migração — Index passa o mesmo valor.
  status: CaixaUnifStatus | CaixaUnifTab;
  q: string;
  onSelect: (id: number) => void;
}

const TABS: { id: CaixaUnifTab; label: string; statKey?: keyof CaixaUnifStats; title?: string }[] = [
  { id: 'all', label: 'Todas' },
  { id: 'unread', label: 'Não lidas', statKey: 'unread' },
  { id: 'assigned', label: 'Minhas', statKey: 'assigned' },
  { id: 'bot', label: 'Bot', statKey: 'bot' },
  { id: 'awaiting_human', label: 'Aguardando', statKey: 'awaiting_human', title: 'Bot escalou pra humano — fila manual' },
  { id: 'resolved', label: 'Resolvidas' },
  { id: 'archived', label: 'Arquivadas', statKey: 'archived', title: 'Conversas arquivadas pelo atendente' },
];

export default function ConversationListV4({
  conversations, channels, stats, selectedId, status, q, onSelect,
}: Props) {
  const [searchInput, setSearchInput] = useState(q);
  const tab = status as CaixaUnifTab;

  const channelsById = useMemo(() => {
    const map = new Map<string, ChannelCatalogItem>();
    for (const c of channels) map.set(c.id, c);
    return map;
  }, [channels]);

  function applyTab(next: CaixaUnifTab) {
    router.get(
      route('atendimento.caixa-unificada.index'),
      { tab: next, q: q || undefined },
      { preserveScroll: true, preserveState: true, only: ['conversations', 'stats'] },
    );
  }

  function applySearch(value: string) {
    router.get(
      route('atendimento.caixa-unificada.index'),
      { tab, q: value || undefined },
      { preserveScroll: true, preserveState: true, only: ['conversations', 'stats'], replace: true },
    );
  }

  return (
    <aside
      className="flex flex-col bg-card border-r min-h-0 min-w-0"
      aria-label="Lista de conversas"
    >
      {/* Header coluna */}
      <div className="flex items-baseline gap-2 border-b px-3.5 pt-3 pb-2">
        <b className="text-[13px] font-semibold text-foreground">Conversas</b>
        <span className="font-mono text-[11px] text-muted-foreground">{conversations.total}</span>
      </div>

      {/* Wave 2 F1 — 7 tabs canônicas paridade Inbox legacy */}
      <div
        className="flex gap-0.5 px-2 py-1.5 border-b overflow-x-auto"
        role="tablist"
        aria-label="Filtrar conversas por tab"
      >
        {TABS.map(t => {
          const count = t.statKey && stats ? stats[t.statKey] : undefined;
          const isActive = tab === t.id;
          return (
            <button
              key={t.id}
              type="button"
              role="tab"
              aria-selected={isActive}
              onClick={() => applyTab(t.id)}
              title={t.title}
              data-testid={`caixa-unif-tab-${t.id}`}
              className={cn(
                'inline-flex items-center gap-1 px-2 h-7 rounded text-[11.5px] font-medium transition-colors shrink-0',
                isActive
                  ? 'bg-primary text-primary-foreground'
                  : 'text-muted-foreground hover:text-foreground hover:bg-muted',
              )}
            >
              {t.label}
              {count !== undefined && count > 0 && (
                <span
                  className={cn(
                    'inline-flex items-center justify-center min-w-[15px] h-3.5 px-1 text-[9.5px] font-mono rounded-full',
                    isActive
                      ? 'bg-primary-foreground/25 text-primary-foreground'
                      : 'bg-primary text-primary-foreground',
                  )}
                >
                  {count > 99 ? '99+' : count}
                </span>
              )}
            </button>
          );
        })}
      </div>

      {/* Busca inline */}
      <div className="relative border-b px-3.5 py-2">
        <Search size={12} className="absolute left-5 top-1/2 -translate-y-1/2 text-muted-foreground" aria-hidden />
        <input
          type="text"
          className="w-full h-8 pl-7 pr-7 text-[12.5px] bg-muted/30 border rounded focus:bg-card focus:border-primary outline-none"
          placeholder="Buscar nome, número, texto…"
          value={searchInput}
          onChange={e => setSearchInput(e.target.value)}
          onKeyDown={e => {
            if (e.key === 'Enter') applySearch(searchInput);
            if (e.key === 'Escape') { setSearchInput(''); applySearch(''); }
          }}
          data-testid="caixa-unif-search-input"
          data-caixa-unif-search
          aria-label="Buscar conversas (atalho /)"
        />
        {searchInput && (
          <button
            type="button"
            onClick={() => { setSearchInput(''); applySearch(''); }}
            className="absolute right-5 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
            title="Limpar busca"
            aria-label="Limpar busca"
          >
            <X size={12} />
          </button>
        )}
      </div>

      {/* Lista */}
      {conversations.data.length === 0 ? (
        <div className="flex-1 flex flex-col items-center justify-center gap-1 p-6 text-center text-muted-foreground">
          <b className="text-[12.5px] font-semibold text-foreground">Nenhuma conversa</b>
          <small className="text-[11px]">Tente outro filtro ou limpe a busca.</small>
        </div>
      ) : (
        <ul
          className="flex-1 overflow-auto p-1.5 flex flex-col gap-0.5"
          role="listbox"
          aria-label="Conversas"
        >
          {conversations.data.map(conv => {
            const ch = conv.channel_type ? channelsById.get(conv.channel_type) : undefined;
            const isSel = selectedId === conv.id;
            const isGhost = conv.preview_only;
            return (
              <li
                key={conv.id}
                role="option"
                aria-selected={isSel}
                tabIndex={0}
                data-testid={`caixa-unif-conv-row-${conv.id}`}
                onClick={() => onSelect(conv.id)}
                onKeyDown={e => {
                  if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    onSelect(conv.id);
                  }
                }}
                className={cn(
                  'grid grid-cols-[36px_1fr_auto] gap-2.5 items-center px-2.5 py-2 rounded cursor-pointer transition-colors border-l-2',
                  isSel ? 'bg-primary/10' : 'hover:bg-muted/50',
                  isGhost && 'opacity-70',
                )}
                style={{
                  borderLeftColor: `oklch(0.62 0.13 ${conv.queue.hue})`,
                }}
              >
                {/* Avatar com badge canal */}
                <div className="relative w-9 h-9 flex-shrink-0">
                  <div
                    className="w-9 h-9 rounded-full grid place-items-center text-white text-[12px] font-bold"
                    style={{ background: `oklch(0.60 0.12 ${avatarHue(conv.contact_name || conv.customer_external_id)})` }}
                    aria-hidden
                  >
                    {initials(conv.contact_name || conv.customer_external_id)}
                  </div>
                  {ch && (
                    <span
                      className="absolute -bottom-0.5 -right-0.5 w-4 h-4 rounded-full grid place-items-center text-white text-[9px] font-bold border-2 border-card"
                      style={{ background: `oklch(0.62 0.14 ${ch.hue})` }}
                      title={`${ch.label} · ${conv.channel_label ?? ''}`}
                      aria-hidden
                    >
                      {ch.glyph}
                    </span>
                  )}
                </div>

                {/* Nome + preview */}
                <div className="min-w-0 flex flex-col gap-px">
                  <b className={cn(
                    'flex items-center gap-1.5 text-[12.5px] font-semibold truncate',
                    isSel && 'text-primary',
                    isGhost && 'text-muted-foreground',
                  )}>
                    <span className="truncate">{conv.contact_name || conv.customer_external_id}</span>
                    {conv.preview_only && (
                      <span
                        className="inline-flex text-[9px] font-medium text-muted-foreground bg-muted border rounded-full px-1.5 flex-shrink-0"
                        title="Canal em homologação — apenas prévia"
                      >
                        em breve
                      </span>
                    )}
                  </b>
                  <small className={cn('text-[11px] truncate', isGhost ? 'text-muted-foreground/80' : 'text-muted-foreground')}>
                    {conv.last_message_direction === 'outbound' ? 'Você: ' : ''}
                    {conv.last_message_preview ?? '—'}
                  </small>
                </div>

                {/* Lado direito — tempo + unread */}
                <div className="flex flex-col items-end gap-0.5 flex-shrink-0">
                  <span className="text-[10px] text-muted-foreground font-mono">
                    {relativeTimeBR(conv.last_message_at)}
                  </span>
                  {conv.unread_count > 0 && (
                    <span
                      className="bg-primary text-primary-foreground font-mono text-[10px] font-bold px-1.5 py-px rounded-full"
                      data-testid={`caixa-unif-unread-${conv.id}`}
                    >
                      {conv.unread_count}
                    </span>
                  )}
                </div>
              </li>
            );
          })}
        </ul>
      )}
    </aside>
  );
}
