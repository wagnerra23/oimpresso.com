// @memcofre tela=/repair/producao-oficina module=Repair
// F3 baseada em prototipo-ui/prototipos/producao-oficina/F1.html — kanban
// 5 colunas Recepção→Diagnóstico→Aguardando peças→Em execução→Pronto.
// US-REPAIR-PROD-4 (2026-05-09): drag-and-drop entre colunas via HTML5 nativo.

import AppShellV2 from '@/Layouts/AppShellV2';
import { router } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';

type Tone = 'slate' | 'blue' | 'amber' | 'violet' | 'emerald';

interface Card {
  id?: number;                    // presente em live data; ausente em mock (drag-drop só local)
  plate: string;
  vehicle: string;
  brand: string;
  km: number;
  mecanico: string | null;
  mecanico_initials: string | null;
  wait?: string;
  box?: string | null;
  eta?: string;
  aprovacao_pendente?: boolean;
  aprovado?: boolean;
  status_label?: string;
  orcamento_total?: number;
  orcamento_pecas?: number;
  orcamento_status?: string;
}

interface Column {
  id: string;
  label: string;
  tone: Tone;
  cards: Card[];
}

interface PageProps {
  columns: Column[];
  totals: {
    os: number;
    aguardando_aprovacao: number;
  };
  data_source?: 'live' | 'mock';
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

const BOXES = ['B1', 'B2', 'B3', 'B4'] as const;
const ELEVADORES = ['E1', 'E2'] as const;

const formatKm = (km: number) => `KM ${km.toLocaleString('pt-BR')}`;

export default function ProducaoOficinaIndex({ columns, totals, data_source }: PageProps) {
  const [boxFilter, setBoxFilter] = useState<string>('all');
  const [elevadorFilter, setElevadorFilter] = useState<string>('all');
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

  const filtersActive = boxFilter !== 'all' || elevadorFilter !== 'all';

  const filteredColumns = useMemo(() => {
    if (!filtersActive) return localColumns;
    return localColumns.map((col) => ({
      ...col,
      cards: col.cards.filter((card) => {
        const matchBox = boxFilter === 'all' || card.box === boxFilter;
        const matchElev = elevadorFilter === 'all' || card.box === elevadorFilter;
        return matchBox && matchElev;
      }),
    }));
  }, [localColumns, boxFilter, elevadorFilter, filtersActive]);

  const filteredCounts = useMemo(() => {
    const all = filteredColumns.flatMap((c) => c.cards);
    return {
      os: all.length,
      aguardando: all.filter((c) => c.aprovacao_pendente).length,
    };
  }, [filteredColumns]);

  const clearFilters = () => {
    setBoxFilter('all');
    setElevadorFilter('all');
  };

  return (
    <div className="flex flex-col h-full bg-slate-50 text-slate-900">
      {/* Filter bar */}
      <div className="bg-white border-b border-slate-200 px-6 py-3 flex items-center gap-6 sticky top-0 z-10">
        <FilterChips
          label="Box"
          options={['Todos', ...BOXES]}
          value={boxFilter}
          onChange={setBoxFilter}
        />
        <div className="w-px h-6 bg-slate-200" />
        <FilterChips
          label="Elevador"
          options={['Todos', ...ELEVADORES]}
          value={elevadorFilter}
          onChange={setElevadorFilter}
        />
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
              <span className="font-medium text-slate-900">{filteredCounts.aguardando}</span> de{' '}
              {totals.aguardando_aprovacao} aguardando aprovação
            </>
          ) : (
            <>
              <span className="font-medium text-slate-900">{totals.os}</span> OS ·{' '}
              <span className="font-medium text-slate-900">{totals.aguardando_aprovacao}</span> aguardando aprovação
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
      {activeCard && <JobDrawer card={activeCard} onClose={() => setActiveCard(null)} />}
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
              key={card.plate}
              card={card}
              tone={column.tone}
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
  onClick,
  onDragStart,
  onDragEnd,
}: {
  card: Card;
  tone: Tone;
  onClick: () => void;
  onDragStart: () => void;
  onDragEnd: () => void;
}) {
  const isPending = card.aprovacao_pendente;
  const isDone = card.aprovado;

  const wrapperClass = isPending
    ? 'bg-amber-50 border-2 border-amber-200 hover:border-amber-500'
    : 'bg-slate-50 border border-slate-200 hover:border-slate-400';

  return (
    <article
      draggable
      onDragStart={(e) => {
        e.dataTransfer.effectAllowed = 'move';
        // Sentinel pra Firefox aceitar drag (sem isso onDragStart não dispara em alguns engines).
        e.dataTransfer.setData('text/plain', card.plate);
        onDragStart();
      }}
      onDragEnd={onDragEnd}
      className={`rounded p-3 cursor-grab active:cursor-grabbing transition ${wrapperClass} ${isDone ? 'opacity-90' : ''}`}
      onClick={onClick}
    >
      <div className="flex items-center justify-between mb-2">
        <span className="font-mono text-sm font-bold text-slate-900 tracking-wider">{card.plate}</span>
        {isPending ? (
          <span className="text-[10px] px-1.5 py-0.5 bg-amber-500 text-white rounded font-medium uppercase tracking-wide">
            Aprov?
          </span>
        ) : isDone ? (
          <span className="text-xs text-emerald-700">✓ aprovado</span>
        ) : card.box ? (
          <span className={`text-xs px-1.5 rounded ${TONE_BOX_BADGE[tone]}`}>{card.box}</span>
        ) : card.wait ? (
          <span className="text-xs text-slate-400">{card.wait}</span>
        ) : null}
      </div>
      <div className="text-xs text-slate-600 mb-1">
        {card.vehicle} · {card.brand}
      </div>
      <div className="text-xs text-slate-500">{formatKm(card.km)}</div>
      {isPending && card.orcamento_total ? (
        <div className="mt-2 pt-2 border-t border-amber-200 text-[11px] text-amber-800">
          R$ {card.orcamento_total.toLocaleString('pt-BR')} · {card.orcamento_pecas} peças ·{' '}
          {card.orcamento_status}
        </div>
      ) : isDone ? (
        <div className="mt-2 pt-2 border-t border-slate-200 text-xs text-slate-500">
          {card.status_label}
        </div>
      ) : card.mecanico ? (
        <div className="mt-2 pt-2 border-t border-slate-200 flex items-center gap-1.5">
          <div
            className={`w-5 h-5 rounded-full grid place-items-center text-[10px] font-bold ${
              tone === 'violet' ? 'bg-violet-300 text-violet-800' : 'bg-slate-300 text-slate-700'
            }`}
          >
            {card.mecanico_initials}
          </div>
          <span className="text-xs text-slate-600">
            {card.mecanico}
            {card.eta ? ` · ETA ${card.eta}` : ''}
          </span>
        </div>
      ) : null}
    </article>
  );
}

function JobDrawer({ card, onClose }: { card: Card; onClose: () => void }) {
  return (
    <aside className="fixed top-0 right-0 h-full w-[480px] bg-white border-l border-slate-200 shadow-2xl flex flex-col z-30">
      <header className="px-5 py-4 border-b border-slate-200 flex items-center justify-between flex-shrink-0">
        <div>
          <div className="flex items-center gap-2 mb-0.5">
            <span className="font-mono text-lg font-bold text-slate-900 tracking-wider">{card.plate}</span>
            {card.box && (
              <span className="text-xs px-1.5 py-0.5 bg-blue-50 text-blue-700 rounded">{card.box}</span>
            )}
          </div>
          <div className="text-sm text-slate-600">
            {card.vehicle} · {card.brand} · {formatKm(card.km)}
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

      {card.aprovacao_pendente && (
        <div className="px-5 py-3 bg-amber-50 border-b border-amber-200 flex items-center justify-between flex-shrink-0">
          <div>
            <div className="text-sm font-medium text-amber-800">Aguarda aprovação do cliente</div>
            <div className="text-xs text-amber-700/90">
              Orçamento R$ {card.orcamento_total?.toLocaleString('pt-BR')} enviado há 2h via WhatsApp
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

        <DrawerSection title="Peças sugeridas">
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
