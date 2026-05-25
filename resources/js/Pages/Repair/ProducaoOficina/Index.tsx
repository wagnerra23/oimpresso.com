// @memcofre tela=/repair/producao-oficina module=Repair
// F3 baseada em prototipo-ui/prototipos/producao-oficina/F1.html — kanban
// 5 colunas Recepção→Diagnóstico→Aguardando peças→Em execução→Pronto.
// US-REPAIR-PROD-4 (2026-05-09): drag-and-drop entre colunas via HTML5 nativo.
//
// REFACTOR shared (audit 2026-05-10):
// - Tipos genéricos (code/item/usage_meter/slot/area/executor) — vertical
//   (OficinaAuto / ComunicacaoVisual / Vestuario) passa labelOverrides via
//   business.repair_settings.labels (props vindas do Controller).
// - Slot/area filters dinâmicos a partir de slot_config (vinda do Controller,
//   alimentada por business.repair_settings.slots[]). Default conservador
//   (B1..B4 + E1..E2) preserva UX atual quando biz não configurou ainda.

import AppShellV2 from '@/Layouts/AppShellV2';
import { router } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';

type Tone = 'slate' | 'blue' | 'amber' | 'violet' | 'emerald';

// Onda 5 — Integração Vendas × Oficina (ADR 0192).
// Quando OS está em coluna 'pronto' (= FSM `entregue_completo` · is_completed_status=true)
// AND tem Transaction derivada (source='oficina'), o Controller (Onda 2) anexa este
// shape ao card. Frontend renderiza card "Esta OS gerou venda" no drawer.
// Vocabulário shared (ADR 0121 §P8): zero termos automotivos · cross-vertical.
//
// FASE B (2026-05-25 · Wave Z-2 backend W2 mergeado em main `2f6f10fc8`):
// Payload expandido com items_list (breakdown produto/serviço), items_summary
// (counts/totals/tax/discount) e fiscal (NF-e status/modelo/chave/DANFE).
// Backward compat: keys core (id/invoice_no/final_total/transaction_date) preservadas.
// Empty states tolerantes: items_list=[] OR fiscal=null não quebram render.
interface VendaItem {
  type: 'product' | 'service';
  name: string;
  qty: number;
  unit_price: number;
  subtotal: number;
}

interface VendaItemsSummary {
  products_count: number;
  products_total: number;
  services_count: number;
  services_total: number;
  tax_total: number;
  discount_total: number;
}

interface VendaFiscal {
  status: 'autorizada' | 'pendente' | 'rejeitada';
  modelo: string | null;       // '55' | '65' | 'NFSe' (Cowork pode trazer '65' como int — cast string)
  chave: string | null;        // 44 dígitos SEFAZ
  danfe_url: string | null;    // '/danfe/{id}'
}

interface VendaDerivada {
  // Core (Onda 5 — sempre presente).
  id: number;
  invoice_no: string;
  final_total: number;
  transaction_date: string | null;  // ISO date 'YYYY-MM-DD'
  // Fase B — expandido (Wave Z-2 W2). Opcionais pra backward compat.
  items_list?: VendaItem[];
  items_summary?: VendaItemsSummary;
  fiscal?: VendaFiscal | null;
}

interface Card {
  id?: number;                    // presente em live data; ausente em mock (drag-drop só local)
  code: string;                   // genérico: placa (auto) | nº OS (com.visual) | código serviço (vestuario)
  item: string;                   // genérico: vehicle (auto) | arte/peça (com.visual) | roupa (vestuario)
  brand: string;                  // genérico já no BD
  usage_meter?: number | null;    // km (auto) | m² impresso (com.visual) | horas-máquina — pode ser null
  usage_unit?: string | null;     // 'km' | 'm²' | 'h' — vertical define
  executor: string | null;        // mecânico (auto) | designer (com.visual) | costureira (vestuario)
  executor_initials: string | null;
  wait?: string;
  slot?: string | null;           // box (auto) | mesa (vestuario) | bancada (com.visual)
  area?: string | null;           // elevador (auto) | área impressão (com.visual)
  eta?: string;
  pending_approval?: boolean;
  approved?: boolean;             // true quando coluna='pronto' (is_completed_status)
  status_label?: string;
  quote_total?: number;
  quote_items?: number;
  quote_status?: string;
  venda_derivada?: VendaDerivada | null;  // Onda 5 · ADR 0192
}

interface Column {
  id: string;
  label: string;
  tone: Tone;
  cards: Card[];
}

interface SlotGroup {
  key: string;            // 'slot' | 'area' | qualquer chave custom (ex: 'mesa', 'bancada')
  label: string;          // 'Box' | 'Elevador' | 'Mesa' | 'Bancada' | 'Máquina'
  options: string[];      // ['B1', 'B2', ...] — vertical configura
}

interface LabelOverrides {
  code?: string;          // 'Placa' (auto) | 'Nº OS' (com.visual) — null usa default genérico
  item?: string;          // 'Veículo' | 'Arte' | 'Peça'
  brand?: string;         // 'Marca' | 'Categoria'
  usage_meter?: string | null; // 'KM' | 'M²' — null omite render
  usage_unit?: string | null;
  executor?: string;      // 'Mecânico' | 'Designer' | 'Costureira'
}

interface PageProps {
  columns: Column[];
  totals: {
    os: number;
    pending_approval: number;
  };
  data_source?: 'live' | 'mock';
  slot_config: SlotGroup[];        // antes hardcoded BOXES/ELEVADORES; agora vem do Controller
  label_overrides: LabelOverrides; // labels específicos por vertical
}

const TONE_DOT: Record<Tone, string> = {
  slate: 'bg-slate-400',
  blue: 'bg-blue-400',
  amber: 'bg-amber-500',
  violet: 'bg-violet-400',
  emerald: 'bg-emerald-400',
};

const TONE_BOX_BADGE: Record<Tone, string> = {
  slate: 'bg-slate-100 text-slate-700',
  blue: 'bg-blue-50 text-blue-700',
  amber: 'bg-amber-100 text-amber-800',
  violet: 'bg-violet-50 text-violet-700',
  emerald: 'bg-emerald-50 text-emerald-700',
};

// Formata medida de uso conforme unit configurada por vertical.
// Default: omitir se usage_meter null.
const formatUsage = (meter: number | null | undefined, unit: string | null | undefined) => {
  if (meter == null || meter === 0) return null;
  const u = unit ?? '';
  if (u === 'km') return `KM ${meter.toLocaleString('pt-BR')}`;
  if (u === 'm²' || u === 'm2') return `${meter.toLocaleString('pt-BR')} m²`;
  if (u === 'h') return `${meter.toLocaleString('pt-BR')} h`;
  return `${meter.toLocaleString('pt-BR')}${u ? ' ' + u : ''}`;
};

export default function ProducaoOficinaIndex({ columns, totals, data_source, slot_config, label_overrides }: PageProps) {
  // Filtros dinâmicos (1 por SlotGroup). Cada vertical configura quantos grupos quer.
  // Estado mantém um filter por group key — 'all' = sem filtro.
  const [slotFilters, setSlotFilters] = useState<Record<string, string>>(() =>
    Object.fromEntries(slot_config.map((g) => [g.key, 'all'])),
  );
  const [activeCard, setActiveCard] = useState<Card | null>(null);

  // US-REPAIR-PROD-4 — drag-and-drop state.
  // - localColumns: cópia editável (optimistic update visualiza drop antes do POST).
  // - dragging: card sendo arrastado (cardId+srcCol).
  // - hoverCol: coluna alvo destacada durante hover.
  const [localColumns, setLocalColumns] = useState<Column[]>(columns);
  const [dragging, setDragging] = useState<{ card: Card; srcColId: string } | null>(null);
  const [hoverCol, setHoverCol] = useState<string | null>(null);

  // Sincroniza state local quando props mudam (Inertia re-render após POST sucesso).
  useEffect(() => setLocalColumns(columns), [columns]);

  // Move card no state local (otimistic update). Para mock (card.id ausente)
  // só atualiza visualmente; pra live data dispara POST que persiste no backend.
  const handleDrop = (targetColId: string, card: Card, srcColId: string) => {
    setHoverCol(null);
    setDragging(null);

    if (targetColId === srcColId) return;

    // Move local (optimistic)
    setLocalColumns((prev) =>
      prev.map((c) => {
        if (c.id === srcColId) return { ...c, cards: c.cards.filter((x) => x !== card) };
        if (c.id === targetColId) return { ...c, cards: [card, ...c.cards] };
        return c;
      }),
    );

    // Live data → POST endpoint pra persistir.
    if (card.id && data_source === 'live') {
      router.post(
        `/repair/producao-oficina/${card.id}/move`,
        { column: targetColId },
        {
          preserveScroll: true,
          onError: () => {
            // Reverte optimistic update — re-sync com props originais.
            setLocalColumns(columns);
          },
        },
      );
    }
  };

  const filtersActive = Object.values(slotFilters).some((v) => v !== 'all');

  const filteredColumns = useMemo(() => {
    if (!filtersActive) return localColumns;
    return localColumns.map((col) => ({
      ...col,
      cards: col.cards.filter((card) => {
        // Cada SlotGroup filtra contra a key correspondente do card (slot|area|custom).
        return slot_config.every((group) => {
          const filterValue = slotFilters[group.key];
          if (filterValue === 'all') return true;
          // Card pode ter key dinâmica conforme group.key (slot, area, mesa, bancada).
          const cardValue = (card as Record<string, unknown>)[group.key] as string | null | undefined;
          return cardValue === filterValue;
        });
      }),
    }));
  }, [localColumns, slotFilters, filtersActive, slot_config]);

  const filteredCounts = useMemo(() => {
    const all = filteredColumns.flatMap((c) => c.cards);
    return {
      os: all.length,
      pending: all.filter((c) => c.pending_approval).length,
    };
  }, [filteredColumns]);

  const clearFilters = () => {
    setSlotFilters(Object.fromEntries(slot_config.map((g) => [g.key, 'all'])));
  };

  return (
    <div className="flex flex-col h-full bg-slate-50 text-slate-900">
      {/* Filter bar — dinâmica conforme slot_config */}
      <div className="bg-white border-b border-slate-200 px-6 py-3 flex items-center gap-6 sticky top-0 z-10 flex-wrap">
        {slot_config.map((group, idx) => (
          <div key={group.key} className="flex items-center gap-2">
            {idx > 0 && <div className="w-px h-6 bg-slate-200" />}
            <FilterChips
              label={group.label}
              options={['Todos', ...group.options]}
              value={slotFilters[group.key] ?? 'all'}
              onChange={(v) => setSlotFilters((prev) => ({ ...prev, [group.key]: v }))}
            />
          </div>
        ))}
        {filtersActive && (
          <button
            type="button"
            onClick={clearFilters}
            className="text-xs text-slate-500 hover:text-slate-900 underline underline-offset-2"
          >
            Limpar filtros
          </button>
        )}
        {data_source === 'mock' && (
          <span
            title="Business sem repair_statuses ou job_sheets — mostrando dados de exemplo. Configure status em /repair/status pra ver dados reais."
            className="text-[10px] uppercase tracking-wide px-1.5 py-0.5 bg-slate-200 text-slate-600 rounded font-medium"
          >
            mock
          </span>
        )}
        <div className="ml-auto text-sm text-slate-500">
          {filtersActive ? (
            <>
              <span className="font-medium text-slate-900">{filteredCounts.os}</span> de {totals.os} OS ·{' '}
              <span className="font-medium text-slate-900">{filteredCounts.pending}</span> de{' '}
              {totals.pending_approval} aguardando aprovação
            </>
          ) : (
            <>
              <span className="font-medium text-slate-900">{totals.os}</span> OS ·{' '}
              <span className="font-medium text-slate-900">{totals.pending_approval}</span> aguardando aprovação
            </>
          )}
        </div>
      </div>

      {/* Kanban */}
      <main className="flex-1 p-6 overflow-hidden">
        <div className="grid grid-cols-5 gap-4 h-full">
          {filteredColumns.map((col) => (
            <KanbanColumn
              key={col.id}
              column={col}
              isHover={hoverCol === col.id}
              isDraggingFrom={dragging?.srcColId === col.id}
              labelOverrides={label_overrides}
              onCardClick={setActiveCard}
              onCardDragStart={(card) => setDragging({ card, srcColId: col.id })}
              onCardDragEnd={() => { setDragging(null); setHoverCol(null); }}
              onDragOver={(e) => {
                if (dragging) { e.preventDefault(); setHoverCol(col.id); }
              }}
              onDragLeave={() => {
                if (hoverCol === col.id) setHoverCol(null);
              }}
              onDrop={(e) => {
                e.preventDefault();
                if (dragging) handleDrop(col.id, dragging.card, dragging.srcColId);
              }}
            />
          ))}
        </div>
      </main>

      {/* Drawer overlay */}
      {activeCard && <JobDrawer card={activeCard} labelOverrides={label_overrides} onClose={() => setActiveCard(null)} />}
    </div>
  );
}

ProducaoOficinaIndex.layout = (page: React.ReactNode) => <AppShellV2>{page}</AppShellV2>;

// ─── sub-components ─────────────────────────────────────────────────────────

function FilterChips({
  label,
  options,
  value,
  onChange,
}: {
  label: string;
  options: readonly string[];
  value: string;
  onChange: (v: string) => void;
}) {
  return (
    <div className="flex items-center gap-2">
      <span className="text-xs uppercase tracking-wide text-slate-500 font-medium">{label}</span>
      <div className="flex gap-1">
        {options.map((opt) => {
          const slug = opt === 'Todos' ? 'all' : opt;
          const active = value === slug;
          return (
            <button
              key={opt}
              type="button"
              onClick={() => onChange(slug)}
              className={
                'px-2.5 py-1 text-sm rounded transition ' +
                (active
                  ? 'bg-slate-900 text-white'
                  : 'bg-slate-100 text-slate-700 hover:bg-slate-200')
              }
            >
              {opt}
            </button>
          );
        })}
      </div>
    </div>
  );
}

function KanbanColumn({
  column,
  isHover,
  isDraggingFrom,
  labelOverrides,
  onCardClick,
  onCardDragStart,
  onCardDragEnd,
  onDragOver,
  onDragLeave,
  onDrop,
}: {
  column: Column;
  isHover: boolean;
  isDraggingFrom: boolean;
  labelOverrides: LabelOverrides;
  onCardClick: (c: Card) => void;
  onCardDragStart: (card: Card) => void;
  onCardDragEnd: () => void;
  onDragOver: (e: React.DragEvent) => void;
  onDragLeave: (e: React.DragEvent) => void;
  onDrop: (e: React.DragEvent) => void;
}) {
  const ringClass = isHover
    ? 'ring-2 ring-slate-900 ring-offset-2 ring-offset-slate-50 border-slate-400'
    : isDraggingFrom
      ? 'border-dashed border-slate-300 opacity-70'
      : 'border-slate-200';

  return (
    <section
      className={`bg-white rounded-lg border flex flex-col min-h-0 transition-shadow ${ringClass}`}
      onDragOver={onDragOver}
      onDragLeave={onDragLeave}
      onDrop={onDrop}
    >
      <header className="px-3 py-2.5 border-b border-slate-200 flex items-center justify-between flex-shrink-0">
        <div className="flex items-center gap-2">
          <span className={`w-2 h-2 rounded-full ${TONE_DOT[column.tone]}`} />
          <h3 className="text-sm font-semibold text-slate-900">{column.label}</h3>
        </div>
        <span className="text-xs px-1.5 py-0.5 bg-slate-100 text-slate-600 rounded">
          {column.cards.length}
        </span>
      </header>
      <div className="p-2 space-y-2 flex-1 overflow-y-auto">
        {column.cards.length === 0 ? (
          <div className="text-xs text-slate-400 text-center py-8">
            {isHover ? 'Soltar aqui' : 'Nenhuma OS'}
          </div>
        ) : (
          column.cards.map((card) => (
            <JobCard
              key={card.code}
              card={card}
              tone={column.tone}
              labelOverrides={labelOverrides}
              onClick={() => onCardClick(card)}
              onDragStart={() => onCardDragStart(card)}
              onDragEnd={onCardDragEnd}
            />
          ))
        )}
      </div>
    </section>
  );
}

function JobCard({
  card,
  tone,
  labelOverrides,
  onClick,
  onDragStart,
  onDragEnd,
}: {
  card: Card;
  tone: Tone;
  labelOverrides: LabelOverrides;
  onClick: () => void;
  onDragStart: () => void;
  onDragEnd: () => void;
}) {
  const isPending = card.pending_approval;
  const isDone = card.approved;

  const wrapperClass = isPending
    ? 'bg-amber-50 border-2 border-amber-200 hover:border-amber-500'
    : 'bg-slate-50 border border-slate-200 hover:border-slate-400';

  // Slot/area badge — usa qualquer dos campos preenchidos (vertical define qual usa).
  const slotBadgeText = card.slot ?? card.area ?? null;
  const usageText = formatUsage(card.usage_meter, card.usage_unit);

  return (
    <article
      draggable
      onDragStart={(e) => {
        e.dataTransfer.effectAllowed = 'move';
        // Sentinel pra Firefox aceitar drag (sem isso onDragStart não dispara em alguns engines).
        e.dataTransfer.setData('text/plain', card.code);
        onDragStart();
      }}
      onDragEnd={onDragEnd}
      className={`rounded p-3 cursor-grab active:cursor-grabbing transition ${wrapperClass} ${isDone ? 'opacity-90' : ''}`}
      onClick={onClick}
    >
      <div className="flex items-center justify-between mb-2">
        <span className="font-mono text-sm font-bold text-slate-900 tracking-wider">{card.code}</span>
        {isPending ? (
          <span className="text-[10px] px-1.5 py-0.5 bg-amber-500 text-white rounded font-medium uppercase tracking-wide">
            Aprov?
          </span>
        ) : isDone ? (
          <span className="text-xs text-emerald-700">✓ aprovado</span>
        ) : slotBadgeText ? (
          <span className={`text-xs px-1.5 rounded ${TONE_BOX_BADGE[tone]}`}>{slotBadgeText}</span>
        ) : card.wait ? (
          <span className="text-xs text-slate-400">{card.wait}</span>
        ) : null}
      </div>
      <div className="text-xs text-slate-600 mb-1">
        {card.item} · {card.brand}
      </div>
      {usageText && <div className="text-xs text-slate-500">{usageText}</div>}
      {isPending && card.quote_total ? (
        <div className="mt-2 pt-2 border-t border-amber-200 text-[11px] text-amber-800">
          R$ {card.quote_total.toLocaleString('pt-BR')}
          {card.quote_items ? ` · ${card.quote_items} ${labelOverrides.item ? `${labelOverrides.item.toLowerCase()}s` : 'itens'}` : ''}
          {card.quote_status ? ` · ${card.quote_status}` : ''}
        </div>
      ) : isDone ? (
        <div className="mt-2 pt-2 border-t border-slate-200 text-xs text-slate-500">
          {card.status_label}
        </div>
      ) : card.executor ? (
        <div className="mt-2 pt-2 border-t border-slate-200 flex items-center gap-1.5">
          <div
            className={`w-5 h-5 rounded-full grid place-items-center text-[10px] font-bold ${
              tone === 'violet' ? 'bg-violet-300 text-violet-800' : 'bg-slate-300 text-slate-700'
            }`}
          >
            {card.executor_initials}
          </div>
          <span className="text-xs text-slate-600">
            {card.executor}
            {card.eta ? ` · ETA ${card.eta}` : ''}
          </span>
        </div>
      ) : null}
    </article>
  );
}

function JobDrawer({ card, labelOverrides, onClose }: { card: Card; labelOverrides: LabelOverrides; onClose: () => void }) {
  const slotBadge = card.slot ?? card.area ?? null;
  const usageText = formatUsage(card.usage_meter, card.usage_unit);

  return (
    <aside className="fixed top-0 right-0 h-full w-[480px] bg-white border-l border-slate-200 shadow-2xl flex flex-col z-30">
      <header className="px-5 py-4 border-b border-slate-200 flex items-center justify-between flex-shrink-0">
        <div>
          <div className="flex items-center gap-2 mb-0.5">
            <span className="font-mono text-lg font-bold text-slate-900 tracking-wider">{card.code}</span>
            {slotBadge && (
              <span className="text-xs px-1.5 py-0.5 bg-blue-50 text-blue-700 rounded">{slotBadge}</span>
            )}
          </div>
          <div className="text-sm text-slate-600">
            {card.item} · {card.brand}{usageText ? ` · ${usageText}` : ''}
          </div>
        </div>
        <button
          type="button"
          className="text-slate-400 hover:text-slate-700 text-2xl leading-none"
          onClick={onClose}
          aria-label="Fechar"
        >
          ×
        </button>
      </header>

      {card.pending_approval && (
        <div className="px-5 py-3 bg-amber-50 border-b border-amber-200 flex items-center justify-between flex-shrink-0">
          <div>
            <div className="text-sm font-medium text-amber-800">Aguarda aprovação do cliente</div>
            <div className="text-xs text-amber-700/90">
              Orçamento R$ {card.quote_total?.toLocaleString('pt-BR')} enviado há 2h via WhatsApp
            </div>
          </div>
          <button
            type="button"
            className="text-xs px-3 py-1.5 bg-amber-500 text-white rounded font-medium hover:bg-amber-600"
          >
            Reenviar
          </button>
        </div>
      )}

      <div className="flex-1 overflow-y-auto">
        {/* Onda 5 (ADR 0192) — Integração Vendas × Oficina A1 KB-9.75.
            Card renderiza quando OS está em coluna 'pronto' (= FSM `entregue_completo` ·
            is_completed_status=true) AND o JobSheetObserver criou Transaction derivada
            (source='oficina'). Loose coupling via window.CustomEvent — Sells/Index
            (Worker A Onda 4) registra listener pra abrir drawer SaleSheet. */}
        {card.approved && card.venda_derivada && (
          <VendaDerivadaCard venda={card.venda_derivada} />
        )}

        <DrawerSection title="Sintoma reportado">
          <p className="text-sm text-slate-800">
            Cliente relata barulho na suspensão dianteira ao passar em buraco. Volante puxa pra direita em
            velocidade acima de 80 km/h.
          </p>
        </DrawerSection>

        <DrawerSection title="Fotos & laudo">
          <div className="grid grid-cols-3 gap-2">
            {['entrada', 'frente', 'motor', 'susp.', 'pneu', '+ 2'].map((label) => (
              <div
                key={label}
                className="aspect-square rounded bg-slate-200 grid place-items-center text-slate-400 text-[10px]"
              >
                📷 {label}
              </div>
            ))}
          </div>
        </DrawerSection>

        <DrawerSection title={labelOverrides.item ? `${labelOverrides.item}s sugeridos` : 'Itens sugeridos'}>
          <ul className="space-y-1.5 text-sm">
            <PriceRow label="Bandeja dianteira esq." value={890} />
            <PriceRow label="Pivô inferior (par)" value={420} />
            <PriceRow label="Bieleta estabilizadora" value={280} />
            <PriceRow label="Mão de obra" value={890} />
          </ul>
          <div className="mt-3 pt-3 border-t border-slate-200 flex items-center justify-between">
            <span className="text-sm font-semibold text-slate-900">Total</span>
            <span className="text-base font-bold text-slate-900">
              R$ {(890 + 420 + 280 + 890).toLocaleString('pt-BR')}
            </span>
          </div>
        </DrawerSection>

        <DrawerSection title="Linha do tempo" last>
          <ol className="space-y-2.5 text-sm">
            <TimelineEvent dot="emerald" label="Recebido na recepção" meta="09 mai · 08:42 · Carlos R." />
            <TimelineEvent dot="emerald" label="Diagnóstico iniciado" meta="09 mai · 09:10 · João P." />
            <TimelineEvent dot="emerald" label="Orçamento enviado" meta="09 mai · 11:25 · WhatsApp" />
            <TimelineEvent dot="amber" label="Aguardando aprovação" meta="há 2h" emphasis />
          </ol>
        </DrawerSection>
      </div>
    </aside>
  );
}

function DrawerSection({
  title,
  children,
  last,
}: {
  title: string;
  children: React.ReactNode;
  last?: boolean;
}) {
  return (
    <section className={`px-5 py-4 ${last ? '' : 'border-b border-slate-200'}`}>
      <h4 className="text-xs uppercase tracking-wide text-slate-500 font-medium mb-2">{title}</h4>
      {children}
    </section>
  );
}

function PriceRow({ label, value }: { label: string; value: number }) {
  return (
    <li className="flex items-center justify-between text-slate-800">
      <span>{label}</span>
      <span className="text-slate-600">R$ {value.toLocaleString('pt-BR')}</span>
    </li>
  );
}

function TimelineEvent({
  dot,
  label,
  meta,
  emphasis,
}: {
  dot: 'emerald' | 'amber';
  label: string;
  meta: string;
  emphasis?: boolean;
}) {
  return (
    <li className="flex gap-3">
      <span
        className={`w-1.5 h-1.5 rounded-full mt-1.5 flex-shrink-0 ${
          dot === 'emerald' ? 'bg-emerald-500' : 'bg-amber-500'
        }`}
      />
      <div>
        <div className={`text-slate-800 ${emphasis ? 'font-medium' : ''}`}>{label}</div>
        <div className="text-xs text-slate-500">{meta}</div>
      </div>
    </li>
  );
}

// Onda 5 (ADR 0192) — Integração Vendas × Oficina A1 KB-9.75.
//
// Card "Esta OS gerou venda #V-NNNN" renderizado no topo do drawer body quando
// `card.approved === true` AND `card.venda_derivada !== null`. Mapeamento direto
// do protótipo Cowork `oficina-page.jsx` linhas 392-458 + `oficina-page.css`
// linhas 465-598 (.ofc-venda-* tokens preservados verbatim).
//
// Vocabulário shared (ADR 0121 §P8): zero termos automotivos.
// Multi-tenant Tier 0 (ADR 0093): payload já scopado backend, frontend só lê.
//
// 3 CTAs:
//  1. "Abrir #V-NNNN" → dispatch CustomEvent 'oimpresso:open-venda' (Worker A
//     Sells/Index listener Onda 4 abre drawer SaleSheet · loose coupling)
//  2. "Imprimir recibo" → window.open rota Blade legacy preservada
//  3. "Compartilhar" → Web Share API nativa (mobile/PWA) + fallback
//     navigator.clipboard.writeText() + toast Sonner (desktop · pattern Repair/JobSheet)
//
// FASE B (2026-05-25 · pós Wave Z-2 backend W2 `2f6f10fc8`):
// Card evoluído pra exibir breakdown peças/serviço + badge fiscal NF-e + lista
// items_list collapsable. Tokens `.ofc-venda-grid` + `.ofc-vc` + `.ofc-fb-*`
// mapeados verbatim do protótipo Cowork. Empty states tolerantes:
//   - items_list ausente/[] → não renderiza breakdown nem lista expandível
//   - fiscal null → renderiza badge "Sem nota fiscal" sutil (slate · OS informal)
//   - fiscal.status 'autorizada' → verde + link DANFE clicável
//   - fiscal.status 'pendente'   → amber "NF-e pendente SEFAZ"
//   - fiscal.status 'rejeitada'  → rose "NF-e rejeitada"
// Items list collapsed por default; user clica ▾ pra expandir. Max 10 visíveis
// + "+N mais" sumário se exceder. Prefixo textual "Peça" / "Serviço" em vez de
// emoji (skill pageheader-canon · ZERO emoji em UI).
function VendaDerivadaCard({ venda }: { venda: VendaDerivada }) {
  const [itemsExpanded, setItemsExpanded] = useState(false);
  const handleAbrir = () => {
    window.dispatchEvent(
      new CustomEvent('oimpresso:open-venda', {
        detail: { venda_id: venda.id },
      }),
    );
  };

  const handleImprimirRecibo = () => {
    window.open(`/sells/${venda.id}/print`, '_blank', 'noopener,noreferrer');
  };

  // Onda 5 follow-up (Worker 3 · 2026-05-25) — share real.
  // Decisão Wagner: Web Share API nativa (mobile/PWA share-sheet) + fallback
  // navigator.clipboard.writeText() + toast Sonner (pattern já em uso no projeto,
  // ver Modules/Repair Pages/Repair/JobSheet/Show.tsx). Sem dependência nova.
  // - AbortError (user cancelou share-sheet) NÃO loga erro (UX silencioso).
  // - canShare check protege Safari iOS quirks (sem text-only sem url).
  const handleCompartilhar = async () => {
    const url = `${window.location.origin}/sells/${venda.id}`;
    const text = `Venda #${venda.invoice_no} · ${venda.final_total.toLocaleString('pt-BR', {
      style: 'currency',
      currency: 'BRL',
    })}${venda.transaction_date ? ` · ${new Date(venda.transaction_date + 'T00:00:00').toLocaleDateString('pt-BR')}` : ''}`;
    const shareData = { title: `Venda #${venda.invoice_no}`, text, url };

    if (typeof navigator !== 'undefined' && typeof navigator.share === 'function') {
      const canShare = typeof navigator.canShare === 'function' ? navigator.canShare(shareData) : true;
      if (canShare) {
        try {
          await navigator.share(shareData);
          return;
        } catch (err) {
          // AbortError = user dismissed share-sheet · não logar nem mostrar toast erro.
          if ((err as DOMException)?.name === 'AbortError') return;
          // Outros erros → cair pro fallback clipboard.
          console.error('Web Share falhou, caindo pro clipboard:', err);
        }
      }
    }

    // Fallback clipboard + toast Sonner (pattern projeto).
    try {
      await navigator.clipboard.writeText(`${text}\n${url}`);
      toast.success('Link da venda copiado');
    } catch (err) {
      console.error('Clipboard falhou:', err);
      toast.error('Não foi possível copiar o link');
    }
  };

  const fmtBRL = (n: number) =>
    n.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

  const totalBR = fmtBRL(venda.final_total);

  const dataBR = venda.transaction_date
    ? new Date(venda.transaction_date + 'T00:00:00').toLocaleDateString('pt-BR')
    : '—';

  // FASE B — empty states tolerantes (Wave Z-2 W2 backward compat).
  const itemsList = venda.items_list ?? [];
  const summary = venda.items_summary;
  const hasBreakdown = !!summary && itemsList.length > 0;
  const subtotal = summary
    ? Number((summary.products_total + summary.services_total).toFixed(2))
    : 0;
  const fiscal = venda.fiscal ?? null;

  // Max 10 items visíveis na lista expandida — resto vira "+N mais".
  const VISIBLE_ITEMS_CAP = 10;
  const visibleItems = itemsList.slice(0, VISIBLE_ITEMS_CAP);
  const hiddenItemsCount = Math.max(0, itemsList.length - VISIBLE_ITEMS_CAP);

  return (
    <div className="ofc-venda-card relative mx-5 mt-4 mb-2 rounded-[10px] border border-emerald-600/70 bg-gradient-to-br from-emerald-50 to-amber-50/30 px-5 pt-5 pb-4">
      <div className="ofc-venda-flag absolute -top-2.5 left-4 rounded-full bg-emerald-600 px-2.5 py-0.5 font-mono text-[9px] font-bold uppercase tracking-wider text-white">
        Integração Vendas × Oficina
      </div>

      <div className="ofc-venda-head mb-3">
        <div className="text-sm font-bold leading-tight text-slate-900">
          Esta OS gerou a venda{' '}
          <code className="ml-0.5 rounded border border-emerald-200 bg-white px-2 py-0.5 font-mono text-[12.5px] font-bold text-emerald-700">
            #{venda.invoice_no}
          </code>
        </div>
        <div className="mt-1 text-[11px] font-medium text-emerald-800/80">
          Auto-criada na transição para "Pronto" (ADR 0192)
        </div>
      </div>

      <div className="ofc-venda-grid mb-3 grid grid-cols-2 gap-2">
        <div className="rounded-md bg-white/65 px-3 py-2">
          <div className="text-[9.5px] font-bold uppercase tracking-wider text-slate-500">
            Total
          </div>
          <div className="mt-0.5 text-sm font-semibold text-slate-900">{totalBR}</div>
        </div>
        <div className="rounded-md bg-white/65 px-3 py-2">
          <div className="text-[9.5px] font-bold uppercase tracking-wider text-slate-500">
            Data
          </div>
          <div className="mt-0.5 text-sm font-semibold text-slate-900">{dataBR}</div>
        </div>
      </div>

      {/* FASE B — Breakdown peças vs serviço (renderiza só se W2 backend entregou).
          Grid 2-col responsive (empilha sm:grid-cols-2 → grid-cols-1 abaixo) +
          linha subtotal · desconto · impostos quando aplicáveis. */}
      {hasBreakdown && summary && (
        <div className="ofc-venda-breakdown mb-3 rounded-md bg-white/65 px-3 py-2.5">
          <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
            <div>
              <div className="text-[9.5px] font-bold uppercase tracking-wider text-slate-500">
                Peças
              </div>
              <div className="mt-0.5 text-[12.5px] font-semibold text-slate-900">
                {summary.products_count} {summary.products_count === 1 ? 'item' : 'itens'} ·{' '}
                {fmtBRL(summary.products_total)}
              </div>
            </div>
            <div>
              <div className="text-[9.5px] font-bold uppercase tracking-wider text-slate-500">
                Serviços
              </div>
              <div className="mt-0.5 text-[12.5px] font-semibold text-slate-900">
                {summary.services_count} {summary.services_count === 1 ? 'item' : 'itens'} ·{' '}
                {fmtBRL(summary.services_total)}
              </div>
            </div>
          </div>

          <div className="mt-2 space-y-0.5 border-t border-emerald-100 pt-2 text-[11px] text-slate-700">
            <div className="flex items-center justify-between">
              <span>Subtotal</span>
              <span className="font-mono font-semibold text-slate-900">{fmtBRL(subtotal)}</span>
            </div>
            {summary.discount_total > 0 && (
              <div className="flex items-center justify-between text-rose-700">
                <span>Desconto</span>
                <span className="font-mono font-semibold">-{fmtBRL(summary.discount_total)}</span>
              </div>
            )}
            {summary.tax_total > 0 && (
              <div className="flex items-center justify-between text-slate-700">
                <span>Impostos</span>
                <span className="font-mono font-semibold">+{fmtBRL(summary.tax_total)}</span>
              </div>
            )}
          </div>
        </div>
      )}

      {/* FASE B — Badge fiscal (NF-e). 4 estados: autorizada (verde + DANFE link) ·
          pendente (amber) · rejeitada (rose) · null (slate sutil "Sem nota fiscal").
          Vocabulário shared: usa "NF-e" genérico (cross-vertical · OficinaAuto / ComVisual / Vestuario). */}
      <div className="ofc-venda-fiscal mb-3 flex flex-wrap gap-1.5">
        {fiscal === null && (
          <span className="ofc-fb ofc-fb-na inline-flex items-center gap-1 rounded border border-slate-200 bg-white px-2 py-0.5 text-[11px] font-semibold text-slate-500">
            Sem nota fiscal
          </span>
        )}
        {fiscal?.status === 'autorizada' && (
          <>
            <span className="ofc-fb ofc-fb-ok inline-flex items-center gap-1 rounded border border-emerald-200 bg-white px-2 py-0.5 text-[11px] font-semibold text-emerald-700">
              NF-e{fiscal.modelo ? ` ${fiscal.modelo}` : ''} autorizada
            </span>
            {fiscal.danfe_url && (
              <button
                type="button"
                onClick={() => window.open(fiscal.danfe_url!, '_blank', 'noopener,noreferrer')}
                aria-label={`Abrir DANFE da venda ${venda.invoice_no}`}
                className="inline-flex items-center gap-1 rounded border border-emerald-200 bg-white px-2 py-0.5 text-[11px] font-semibold text-emerald-700 hover:bg-emerald-50 focus:outline-none focus:ring-2 focus:ring-emerald-500/40"
              >
                DANFE ↗
              </button>
            )}
          </>
        )}
        {fiscal?.status === 'pendente' && (
          <span className="ofc-fb ofc-fb-wait inline-flex items-center gap-1 rounded border border-amber-200 bg-white px-2 py-0.5 text-[11px] font-semibold text-amber-700">
            NF-e{fiscal.modelo ? ` ${fiscal.modelo}` : ''} pendente SEFAZ
          </span>
        )}
        {fiscal?.status === 'rejeitada' && (
          <span className="ofc-fb ofc-fb-bad inline-flex items-center gap-1 rounded border border-rose-200 bg-white px-2 py-0.5 text-[11px] font-semibold text-rose-700">
            NF-e{fiscal.modelo ? ` ${fiscal.modelo}` : ''} rejeitada
          </span>
        )}
      </div>

      {/* FASE B — Lista items expandível (collapsed por default · disclosure pattern).
          Prefix textual "Peça" / "Serviço" (skill pageheader-canon: ZERO emoji em UI). */}
      {hasBreakdown && (
        <div className="ofc-venda-items-toggle mb-3">
          <button
            type="button"
            onClick={() => setItemsExpanded((v) => !v)}
            aria-expanded={itemsExpanded}
            aria-controls={`venda-items-${venda.id}`}
            className="inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-800 hover:text-emerald-900 focus:outline-none"
          >
            <span aria-hidden="true">{itemsExpanded ? '▾' : '▸'}</span>
            {itemsExpanded ? 'Ocultar' : 'Ver'} {itemsList.length}{' '}
            {itemsList.length === 1 ? 'item da venda' : 'itens da venda'}
          </button>
          {itemsExpanded && (
            <ul
              id={`venda-items-${venda.id}`}
              className="ofc-venda-items mt-2 space-y-1 rounded-md bg-white/65 px-3 py-2 text-[11.5px] text-slate-800"
            >
              {visibleItems.map((item, idx) => (
                <li
                  key={`${item.type}-${item.name}-${idx}`}
                  className="flex items-baseline justify-between gap-2"
                >
                  <span className="truncate">
                    <span className="font-semibold text-slate-600">
                      {item.type === 'service' ? 'Serviço' : 'Peça'}
                    </span>
                    <span className="text-slate-400"> · </span>
                    <span>{item.name}</span>
                    <span className="text-slate-400">
                      {' · '}
                      {item.qty.toLocaleString('pt-BR')}× {fmtBRL(item.unit_price)}
                    </span>
                  </span>
                  <span className="font-mono font-semibold text-slate-900">
                    {fmtBRL(item.subtotal)}
                  </span>
                </li>
              ))}
              {hiddenItemsCount > 0 && (
                <li className="pt-1 text-[11px] italic text-slate-500">
                  + {hiddenItemsCount} {hiddenItemsCount === 1 ? 'item' : 'itens'} adicionais
                </li>
              )}
            </ul>
          )}
        </div>
      )}

      <div className="ofc-venda-actions flex flex-wrap gap-1.5">
        <button
          type="button"
          onClick={handleAbrir}
          aria-label={`Abrir venda ${venda.invoice_no}`}
          className="ofc-venda-cta primary inline-flex items-center gap-1 rounded-[5px] border border-emerald-600 bg-emerald-600 px-3 py-1.5 text-[11.5px] font-semibold text-white hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500/40"
        >
          Abrir #{venda.invoice_no} ↗
        </button>
        <button
          type="button"
          onClick={handleImprimirRecibo}
          className="ofc-venda-cta inline-flex items-center gap-1 rounded-[5px] border border-emerald-200 bg-white px-3 py-1.5 text-[11.5px] font-semibold text-emerald-700 hover:bg-emerald-50 focus:outline-none focus:ring-2 focus:ring-emerald-500/40"
        >
          Imprimir recibo
        </button>
        <button
          type="button"
          onClick={handleCompartilhar}
          aria-label={`Compartilhar venda ${venda.invoice_no}`}
          className="ofc-venda-cta inline-flex items-center gap-1 rounded-[5px] border border-emerald-200 bg-white px-3 py-1.5 text-[11.5px] font-semibold text-emerald-700 hover:bg-emerald-50 focus:outline-none focus:ring-2 focus:ring-emerald-500/40"
        >
          Compartilhar
        </button>
      </div>
    </div>
  );
}
