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
import { Check, ChevronDown, Clock, Filter, Paperclip, Search, Star, UserPlus, X } from 'lucide-react';
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from '@/Components/ui/popover';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import { Inline } from '@/Components/layout';
import { cn } from '@/Lib/utils';
import {
  type AccountItem,
  type CaixaUnifConversation,
  type CaixaUnifStatus,
  type CaixaUnifTab,
  type ChannelCatalogItem,
  type ConvTag,
  type Paginated,
  type CaixaUnifStats,
  type QueueConfig,
  initials,
  avatarHue,
  relativeTimeBR,
  slaState,
  slaWaitedMin,
  slaWaitedShort,
  SLA_META,
} from './helpers';
import { useInboxFavs } from './useInboxFavs';

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
  // Onda 2 (filtros 2-botões) — canal/conta/fila migraram da faixa pro popover Filtros
  channelTypeFilter?: string | null;
  accounts?: AccountItem[];
  accountFilter?: number | null;
  queues?: Record<string, QueueConfig>;
  queueFilter?: string | null;
  // Wave 5 F1 — filtros power-user (sincronizam URL)
  within24h?: boolean | null;
  unlinked?: boolean;
  mediaInbound24h?: boolean;
  inboundAging?: InboundAging;
  orderBy?: OrderBy;
  // Wave 5-B F1 — filtro tags multi-select
  availableTags?: ConvTag[];
  activeTagIds?: number[];
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
  channelTypeFilter = null, accounts = [], accountFilter = null,
  queues = {}, queueFilter = null,
  within24h = null, unlinked = false, mediaInbound24h = false,
  inboundAging = null, orderBy = 'last_message',
  availableTags = [], activeTagIds = [],
}: Props) {
  const [searchInput, setSearchInput] = useState(q);
  const tab = status as CaixaUnifTab;

  // Polish V2 §6 — favoritos localStorage ordenam no topo (ordem original preservada dentro dos grupos)
  const { isFav, toggleFav } = useInboxFavs();
  const orderedConvs = useMemo(() => {
    const data = conversations?.data ?? [];
    return [...data.filter(c => isFav(c.id)), ...data.filter(c => !isFav(c.id))];
  }, [conversations?.data, isFav]);

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
      channel: channelTypeFilter ?? undefined,
      account_id: accountFilter ?? undefined,
      queue: queueFilter ?? undefined,
      within24h: within24h !== null ? (within24h ? '1' : '0') : undefined,
      unlinked: unlinked ? '1' : undefined,
      media_inbound_24h: mediaInbound24h ? '1' : undefined,
      inbound_aging: inboundAging ?? undefined,
      order_by: orderBy !== 'last_message' ? orderBy : undefined,
      tags: activeTagIds.length > 0 ? activeTagIds : undefined,
      ...overrides,
    };
  }

  // Wave 5-B F1 — toggle tag no filtro
  function toggleTagFilter(tagId: number) {
    const next = activeTagIds.includes(tagId)
      ? activeTagIds.filter(id => id !== tagId)
      : [...activeTagIds, tagId];
    applyFilter({ tags: next.length > 0 ? next : undefined });
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
    !!channelTypeFilter,
    accountFilter !== null,
    !!queueFilter,
    within24h !== null,
    unlinked,
    mediaInbound24h,
    inboundAging !== null,
    orderBy !== 'last_message',
    activeTagIds.length > 0,
  ].filter(Boolean).length;

  const tagColorHue: Record<string, number> = {
    red: 0, emerald: 145, blue: 220, purple: 280, amber: 80, cyan: 200, slate: 60,
  };

  // Onda 2 — derivados pro dropdown Status + popover Filtros
  const activeTab = TABS.find(t => t.id === tab) ?? TABS[0]!;
  const activeTabCount = activeTab.statKey && stats ? stats[activeTab.statKey] : undefined;
  const visibleAccounts = channelTypeFilter
    ? accounts.filter(a => a.channel_type === channelTypeFilter)
    : accounts;
  const queueEntries = Object.entries(queues);
  function clearAllFilters() {
    applyFilter({
      channel: undefined, account_id: undefined, queue: undefined,
      within24h: undefined, unlinked: undefined, media_inbound_24h: undefined,
      inbound_aging: undefined, order_by: undefined, tags: undefined,
    });
  }

  return (
    <aside
      className="flex flex-col bg-card border-r min-h-0 min-w-0"
      aria-label="Lista de conversas"
    >
      {/* Header da coluna: título + count + Status (dropdown) + Filtros (popover) — Onda 2 */}
      <Inline align="center" justify="between" className="border-b px-3 pt-2.5 pb-2">
        <Inline align="baseline" className="min-w-0">
          <b className="text-[13px] font-semibold text-foreground">Conversas</b>
          <span className="font-mono text-[11px] text-muted-foreground">{conversations.total}</span>
        </Inline>

        <div className="flex items-center gap-1.5 shrink-0">
          {/* Status — 7 tabs canônicas num DropdownMenu (substitui a fileira de tabs) */}
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <button
                type="button"
                data-testid="caixa-unif-status-trigger"
                title="Filtrar por status"
                className="inline-flex items-center gap-1 h-7 px-2.5 rounded border border-border bg-card text-[11.5px] font-medium text-foreground hover:bg-muted transition-colors"
              >
                {activeTab.label}
                {activeTabCount !== undefined && activeTabCount > 0 && (
                  <span className="inline-flex items-center justify-center min-w-[15px] h-3.5 px-1 text-[9.5px] font-mono rounded-full bg-primary text-primary-foreground">
                    {activeTabCount > 99 ? '99+' : activeTabCount}
                  </span>
                )}
                <ChevronDown size={12} aria-hidden />
              </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start" className="w-48">
              {TABS.map(t => {
                const count = t.statKey && stats ? stats[t.statKey] : undefined;
                const isActive = tab === t.id;
                return (
                  <DropdownMenuItem
                    key={t.id}
                    onClick={() => applyTab(t.id)}
                    title={t.title}
                    data-testid={`caixa-unif-tab-${t.id}`}
                    className="flex items-center gap-2 text-[12px] cursor-pointer"
                  >
                    <Check size={13} className={cn('shrink-0', isActive ? 'opacity-100 text-primary' : 'opacity-0')} aria-hidden />
                    <span className="flex-1">{t.label}</span>
                    {count !== undefined && count > 0 && (
                      <span className="font-mono text-[10px] text-muted-foreground">{count > 99 ? '99+' : count}</span>
                    )}
                  </DropdownMenuItem>
                );
              })}
            </DropdownMenuContent>
          </DropdownMenu>

          {/* Filtros — popover flutuante (não empurra a lista) com todos os power-filters em grupos */}
          <Popover>
            <PopoverTrigger asChild>
              <button
                type="button"
                aria-pressed={activeFilterCount > 0}
                data-testid="caixa-unif-filtros-trigger"
                title="Filtros avançados"
                className={cn(
                  'inline-flex items-center gap-1 h-7 px-2.5 rounded border text-[11.5px] font-medium transition-colors',
                  activeFilterCount > 0
                    ? 'bg-primary/10 border-primary text-primary'
                    : 'border-border bg-card text-foreground hover:bg-muted',
                )}
              >
                <Filter size={12} aria-hidden />
                Filtros
                {activeFilterCount > 0 && (
                  <span className="inline-flex items-center justify-center min-w-[15px] h-3.5 px-1 text-[9.5px] font-mono rounded-full bg-primary text-primary-foreground">
                    {activeFilterCount}
                  </span>
                )}
              </button>
            </PopoverTrigger>
            <PopoverContent align="end" className="w-72 p-0">
              <Inline align="center" justify="between" className="px-3 py-2 border-b">
                <span className="text-[12px] font-semibold">Filtros</span>
                {activeFilterCount > 0 && (
                  <button
                    type="button"
                    onClick={clearAllFilters}
                    data-testid="caixa-unif-filter-clear"
                    className="inline-flex items-center gap-0.5 text-[10.5px] text-muted-foreground hover:text-foreground transition-colors"
                  >
                    <X size={11} aria-hidden /> Limpar ({activeFilterCount})
                  </button>
                )}
              </Inline>

              <div className="max-h-[60vh] overflow-auto p-2.5 flex flex-col gap-3">
                {channels.length > 0 && (
                  <FilterSection label="Canal">
                    <OptionRow
                      label="Todos os canais"
                      active={!channelTypeFilter}
                      onClick={() => applyFilter({ channel: undefined, account_id: undefined })}
                    />
                    {channels.map(c => (
                      <OptionRow
                        key={c.id}
                        label={c.short || c.label}
                        hue={c.hue}
                        count={c.count}
                        muted={c.status === 'em_breve'}
                        active={channelTypeFilter === c.id}
                        onClick={() => applyFilter({
                          channel: channelTypeFilter === c.id ? undefined : c.id,
                          account_id: undefined,
                        })}
                        testId={`caixa-unif-filter-channel-${c.id}`}
                      />
                    ))}
                  </FilterSection>
                )}

                {visibleAccounts.length > 0 && (
                  <FilterSection label="Conta">
                    <OptionRow
                      label="Todas as contas"
                      active={accountFilter === null}
                      onClick={() => applyFilter({ account_id: undefined })}
                    />
                    {visibleAccounts.map(a => (
                      <OptionRow
                        key={a.id}
                        label={a.label}
                        sub={a.handle}
                        count={a.count}
                        muted={a.status === 'em_breve'}
                        active={accountFilter === a.id}
                        onClick={() => applyFilter({ account_id: accountFilter === a.id ? undefined : a.id })}
                        testId={`caixa-unif-filter-account-${a.id}`}
                      />
                    ))}
                  </FilterSection>
                )}

                {queueEntries.length > 0 && (
                  <FilterSection label="Fila">
                    <OptionRow
                      label="Todas as filas"
                      active={!queueFilter}
                      onClick={() => applyFilter({ queue: undefined })}
                    />
                    {queueEntries.map(([slug, qc]) => (
                      <OptionRow
                        key={slug}
                        label={qc.label}
                        hue={qc.hue}
                        active={queueFilter === slug}
                        onClick={() => applyFilter({ queue: queueFilter === slug ? undefined : slug })}
                        testId={`caixa-unif-filter-queue-${slug}`}
                      />
                    ))}
                  </FilterSection>
                )}

                {availableTags.length > 0 && (
                  <FilterSection label="Tags">
                    {availableTags.map(t => (
                      <OptionRow
                        key={t.id}
                        label={t.label}
                        hue={tagColorHue[t.color] ?? 60}
                        active={activeTagIds.includes(t.id)}
                        onClick={() => toggleTagFilter(t.id)}
                        testId={`caixa-unif-filter-tag-${t.slug}`}
                      />
                    ))}
                  </FilterSection>
                )}

                <FilterSection label="Ordenar por">
                  <OptionRow
                    label="Última mensagem"
                    active={orderBy === 'last_message'}
                    onClick={() => applyFilter({ order_by: undefined })}
                  />
                  <OptionRow
                    label="Último recebido (inbound)"
                    active={orderBy === 'inbound'}
                    onClick={() => applyFilter({ order_by: 'inbound' })}
                  />
                </FilterSection>

                <FilterSection label="Esperando resposta há">
                  <OptionRow label="Qualquer tempo" active={!inboundAging} onClick={() => applyFilter({ inbound_aging: undefined })} />
                  {(['6h', '12h', '24h', '48h', '7d'] as const).map(a => (
                    <OptionRow
                      key={a}
                      label={a === '7d' ? '> 7 dias' : `> ${a}`}
                      active={inboundAging === a}
                      onClick={() => applyFilter({ inbound_aging: inboundAging === a ? undefined : a })}
                      testId={`caixa-unif-filter-aging-${a}`}
                    />
                  ))}
                </FilterSection>

                <FilterSection label="Outros">
                  <ToggleRow
                    icon={<UserPlus size={12} aria-hidden />}
                    label="Sem CRM vinculado"
                    active={unlinked}
                    onClick={() => applyFilter({ unlinked: !unlinked ? '1' : undefined })}
                    testId="caixa-unif-filter-unlinked"
                  />
                  <ToggleRow
                    icon={<Clock size={12} aria-hidden />}
                    label={within24h === false ? 'Janela 24h fechada' : within24h === true ? 'Janela 24h aberta' : 'Janela 24h (qualquer)'}
                    active={within24h !== null}
                    onClick={cycleWithin24h}
                    testId="caixa-unif-filter-within24h"
                  />
                  <ToggleRow
                    icon={<Paperclip size={12} aria-hidden />}
                    label="Mídia recebida 24h"
                    active={mediaInbound24h}
                    onClick={() => applyFilter({ media_inbound_24h: !mediaInbound24h ? '1' : undefined })}
                    testId="caixa-unif-filter-media24h"
                  />
                </FilterSection>
              </div>
            </PopoverContent>
          </Popover>
        </div>
      </Inline>

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
          {orderedConvs.map(conv => {
            const ch = conv.channel_type ? channelsById.get(conv.channel_type) : undefined;
            const isSel = selectedId === conv.id;
            const isGhost = conv.preview_only;
            const sla = slaState(conv);
            const fav = isFav(conv.id);
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

                {/* Lado direito — fav + tempo + SLA + unread */}
                <div className="flex flex-col items-end gap-0.5 flex-shrink-0">
                  <span className="inline-flex items-center gap-1">
                    {/* Polish V2 §6 — favorito (localStorage, ordena no topo) */}
                    <button
                      type="button"
                      onClick={(e) => { e.stopPropagation(); toggleFav(conv.id); }}
                      className={cn(
                        'p-0.5 rounded transition-colors',
                        !fav && 'text-muted-foreground/40 hover:text-muted-foreground',
                      )}
                      style={fav ? { color: 'oklch(0.75 0.15 80)' } : undefined}
                      title={fav ? 'Remover dos favoritos' : 'Favoritar (fica no topo da lista)'}
                      aria-pressed={fav}
                      data-testid={`caixa-unif-fav-${conv.id}`}
                    >
                      <Star size={11} fill={fav ? 'currentColor' : 'none'} aria-hidden />
                    </button>
                    <span className="text-[10px] text-muted-foreground font-mono">
                      {relativeTimeBR(conv.last_message_at)}
                    </span>
                  </span>
                  {/* Onda 2 — pill SLA 4 estados (fresh/aging/late/expired) + dot
                      animado; compacto na lista (dot + tempo, a cor diz o estado). */}
                  {sla && (() => {
                    const m = SLA_META[sla];
                    const waited = slaWaitedMin(conv);
                    return (
                      <span
                        className={cn('inline-block font-mono text-[9px] font-bold px-1.5 py-px rounded-full border', m.pillSm)}
                        title={`SLA ${conv.queue.sla} — ${m.label}`}
                        data-testid={`caixa-unif-sla-${conv.id}`}
                      >
                        <span className={cn('inline-block w-1 h-1 rounded-full align-middle mr-1', m.dot, m.pulse && 'animate-pulse')} aria-hidden />
                        {waited != null ? slaWaitedShort(waited) : m.label}
                      </span>
                    );
                  })()}
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

// Onda 2 — blocos do popover Filtros (seção + linha-opção single/multi + toggle)
function FilterSection({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div className="flex flex-col gap-0.5">
      <div className="text-[10px] uppercase tracking-[0.06em] text-muted-foreground font-semibold px-1 pb-0.5">
        {label}
      </div>
      {children}
    </div>
  );
}

function OptionRow({
  label, sub, hue, count, muted, active, onClick, testId,
}: {
  label: string;
  sub?: string;
  hue?: number;
  count?: number;
  muted?: boolean;
  active: boolean;
  onClick: () => void;
  testId?: string;
}) {
  return (
    <button
      type="button"
      onClick={onClick}
      data-testid={testId}
      aria-pressed={active}
      className={cn(
        'w-full flex items-center gap-2 px-2 py-1.5 rounded text-left text-[11.5px] transition-colors',
        active ? 'bg-primary/10 text-primary' : 'hover:bg-muted text-foreground',
        muted && !active && 'text-muted-foreground',
      )}
    >
      <Check size={13} className={cn('shrink-0', active ? 'opacity-100' : 'opacity-0')} aria-hidden />
      {hue !== undefined && (
        <span className="inline-block w-2 h-2 rounded-full shrink-0" style={{ background: `oklch(0.62 0.13 ${hue})` }} aria-hidden />
      )}
      <span className="flex-1 min-w-0 truncate">
        {label}
        {sub && <span className="text-muted-foreground font-mono"> · {sub}</span>}
      </span>
      {count !== undefined && count > 0 && (
        <span className="font-mono text-[10px] text-muted-foreground shrink-0">{count > 99 ? '99+' : count}</span>
      )}
      {muted && <span className="text-[9px] text-muted-foreground border rounded-full px-1 shrink-0">em breve</span>}
    </button>
  );
}

function ToggleRow({
  icon, label, active, onClick, testId,
}: {
  icon: React.ReactNode;
  label: string;
  active: boolean;
  onClick: () => void;
  testId?: string;
}) {
  return (
    <button
      type="button"
      onClick={onClick}
      data-testid={testId}
      aria-pressed={active}
      className={cn(
        'w-full flex items-center gap-2 px-2 py-1.5 rounded text-left text-[11.5px] transition-colors',
        active ? 'bg-primary/10 text-primary' : 'hover:bg-muted text-foreground',
      )}
    >
      <span className={cn('shrink-0', active ? 'text-primary' : 'text-muted-foreground')}>{icon}</span>
      <span className="flex-1 min-w-0 truncate">{label}</span>
      <Check size={13} className={cn('shrink-0', active ? 'opacity-100' : 'opacity-0')} aria-hidden />
    </button>
  );
}
