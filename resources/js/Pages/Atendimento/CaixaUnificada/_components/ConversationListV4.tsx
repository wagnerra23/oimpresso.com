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
import { Clock, Paperclip, Search, UserPlus, X } from 'lucide-react';
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

type InboundAging = '6h' | '12h' | '24h' | '48h' | '7d' | null;
type OrderBy = 'last_message' | 'inbound';

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
  // Wave 5 F1 — filtros power-user (sincronizam URL)
  within24h?: boolean | null;
  unlinked?: boolean;
  mediaInbound24h?: boolean;
  inboundAging?: InboundAging;
  orderBy?: OrderBy;
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
  within24h = null, unlinked = false, mediaInbound24h = false,
  inboundAging = null, orderBy = 'last_message',
}: Props) {
  const [searchInput, setSearchInput] = useState(q);
  const tab = status as CaixaUnifTab;

  const channelsById = useMemo(() => {
    const map = new Map<string, ChannelCatalogItem>();
    for (const c of channels) map.set(c.id, c);
    return map;
  }, [channels]);

  // Helper Wave 5 F1: preserva todos filtros ao navegar
  function buildQuery(overrides: Record<string, unknown> = {}) {
    return {
      tab,
      q: q || undefined,
      within24h: within24h !== null ? (within24h ? '1' : '0') : undefined,
      unlinked: unlinked ? '1' : undefined,
      media_inbound_24h: mediaInbound24h ? '1' : undefined,
      inbound_aging: inboundAging ?? undefined,
      order_by: orderBy !== 'last_message' ? orderBy : undefined,
      ...overrides,
    };
  }

  function applyTab(next: CaixaUnifTab) {
    router.get(
      route('atendimento.caixa-unificada.index'),
      buildQuery({ tab: next }),
      { preserveScroll: true, preserveState: true, only: ['conversations', 'stats'] },
    );
  }

  function applySearch(value: string) {
    router.get(
      route('atendimento.caixa-unificada.index'),
      buildQuery({ q: value || undefined }),
      { preserveScroll: true, preserveState: true, only: ['conversations', 'stats'], replace: true },
    );
  }

  function applyFilter(overrides: Record<string, unknown>) {
    router.get(
      route('atendimento.caixa-unificada.index'),
      buildQuery(overrides),
      { preserveScroll: true, preserveState: true, only: ['conversations', 'stats'] },
    );
  }

  // Tri-estado within24h: null → true → false → null
  function cycleWithin24h() {
    const next = within24h === null ? true : within24h === true ? false : null;
    applyFilter({ within24h: next === null ? undefined : next ? '1' : '0' });
  }

  const activeFilterCount = [
    within24h !== null,
    unlinked,
    mediaInbound24h,
    inboundAging !== null,
    orderBy !== 'last_message',
  ].filter(Boolean).length;

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

      {/* Wave 5 F1 — Filtros power-user (3 chips + 2 dropdowns) */}
      <div className="flex flex-wrap items-center gap-1 px-2 py-1.5 border-b text-[11px]">
        <FilterChip
          active={unlinked}
          onClick={() => applyFilter({ unlinked: !unlinked ? '1' : undefined })}
          icon={<UserPlus size={11} aria-hidden />}
          label="Sem CRM"
          title="Conversas sem Contact CRM vinculado (oportunidade de cadastro)"
        />
        <FilterChip
          active={within24h !== null}
          onClick={cycleWithin24h}
          icon={<Clock size={11} aria-hidden />}
          label={within24h === false ? '24h fechada' : within24h === true ? '24h aberta' : 'Janela 24h'}
          title="Janela Meta 24h — clique alterna aberta → fechada → sem filtro"
        />
        <FilterChip
          active={mediaInbound24h}
          onClick={() => applyFilter({ media_inbound_24h: !mediaInbound24h ? '1' : undefined })}
          icon={<Paperclip size={11} aria-hidden />}
          label="Mídia 24h"
          title="Só conversas com foto/áudio/vídeo/doc recebidos nas últimas 24h"
        />

        {/* Aging dropdown */}
        <select
          value={inboundAging ?? ''}
          onChange={e => applyFilter({ inbound_aging: e.target.value || undefined })}
          aria-label="Esperando resposta há mais de"
          data-testid="caixa-unif-filter-aging"
          className={cn(
            'h-6 px-1.5 text-[11px] rounded border bg-card cursor-pointer hover:bg-muted focus:outline-none focus:border-primary',
            inboundAging ? 'border-primary text-primary font-medium' : 'border-border text-muted-foreground',
          )}
        >
          <option value="">Esperando há…</option>
          <option value="6h">&gt; 6h</option>
          <option value="12h">&gt; 12h</option>
          <option value="24h">&gt; 24h</option>
          <option value="48h">&gt; 48h</option>
          <option value="7d">&gt; 7 dias</option>
        </select>

        {/* OrderBy dropdown */}
        <select
          value={orderBy}
          onChange={e => applyFilter({ order_by: e.target.value === 'last_message' ? undefined : e.target.value })}
          aria-label="Ordenar por"
          data-testid="caixa-unif-filter-orderby"
          className={cn(
            'h-6 px-1.5 text-[11px] rounded border bg-card cursor-pointer hover:bg-muted focus:outline-none focus:border-primary',
            orderBy !== 'last_message' ? 'border-primary text-primary font-medium' : 'border-border text-muted-foreground',
          )}
        >
          <option value="last_message">Última msg</option>
          <option value="inbound">Último inbound</option>
        </select>

        {activeFilterCount > 0 && (
          <button
            type="button"
            onClick={() => applyFilter({
              within24h: undefined, unlinked: undefined, media_inbound_24h: undefined,
              inbound_aging: undefined, order_by: undefined,
            })}
            className="ml-auto inline-flex items-center gap-0.5 text-[10.5px] text-muted-foreground hover:text-foreground transition-colors"
            data-testid="caixa-unif-filter-clear"
            title={`Limpar ${activeFilterCount} filtro${activeFilterCount > 1 ? 's' : ''}`}
          >
            <X size={11} aria-hidden /> limpar
          </button>
        )}
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

// Wave 5 F1 — FilterChip compacto (pattern Inbox legacy ConversationList.tsx)
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
      className={cn(
        'inline-flex items-center gap-1 h-6 px-2 rounded-full border text-[10.5px] font-medium transition-colors',
        active
          ? 'bg-primary/10 border-primary text-primary'
          : 'bg-card border-border text-muted-foreground hover:text-foreground hover:border-muted-foreground',
      )}
    >
      {icon}
      {label}
    </button>
  );
}
