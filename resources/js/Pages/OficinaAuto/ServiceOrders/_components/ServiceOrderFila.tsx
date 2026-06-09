// View "Fila" (master-detail) das Ordens de Serviço — 2ª view in-page da Index
// (toggle Lista ↔ Fila; o Quadro/Kanban segue em página separada `/board`).
//
// Tradução fiel do protótipo Cowork `oficina-fila.jsx` (handoff Claude Design
// 2026-06-03, PROMPT_PARA_CODE_OFICINA-DARK-STAGE-DS Parte 4): lista persistente
// à esquerda + detalhe inline ao centro + rail "Apps vinculados" à direita.
//
// Decisões de fidelidade ao repo (≠ protótipo) — ancoradas no charter da Index:
//   • Non-Goal "edição inline na listagem" → o detalhe é READ-ONLY; toda edição
//     e transição FSM acontece em "Abrir OS completa" (ServiceOrderSheet canônico).
//     Não duplica DviEditor/ItemsEditor/StageGate (instrução explícita do handoff).
//   • Non-Goal "trigger manual de WhatsApp (US-OFICINA-006)" → o rail NÃO traz o
//     botão WhatsApp do protótipo nem telefone mock; só dados reais da OS/contato.
//   • Anti-pattern "cor crua" → cores de etapa via `toneForColor` (boardTone.ts),
//     neutros via tokens DS (bg-card/border-border/muted), dark-aware.
//
// Reusa: ServiceOrderStatusBadge, ServiceOrderStagePipeline (live /fsm/actions),
//        ServiceOrderTimeline (live /fsm/history), ServiceOrderSheet (via onOpenFull).

import { useMemo, useState } from 'react';
import { Car, ExternalLink, User2 } from 'lucide-react';
import { cn } from '@/Lib/utils';
import ServiceOrderStatusBadge from './ServiceOrderStatusBadge';
import ServiceOrderStagePipeline from './ServiceOrderStagePipeline';
import ServiceOrderTimeline from './ServiceOrderTimeline';

// Subconjunto de ServiceOrder (Index.tsx) usado pela Fila — campos da listagem.
export interface FilaOrder {
  id: number;
  number?: string | null;
  status: string;
  // ADR 0265: order_type ∈ {manutencao, mecanica}
  order_type?: 'manutencao' | 'mecanica' | null;
  // Atraso de reparo: só expected_completion (campos de locação erradicados, ADR 0265).
  expected_completion?: string | null;
  entered_at?: string | null;
  started_at?: string | null;
  // Sintoma/defeito relatado na entrada — exibido na linha secundária da fila.
  notes?: string | null;
  vehicle?: {
    id: number;
    plate: string;
    vehicle_type: string;
    vehicle_number?: string | null;
    capacity_m3?: number | string | null;
  } | null;
  contact?: { id: number; name: string } | null;
  is_overdue?: boolean;
  valor_receber?: number | string | null;
}

interface Props {
  orders: FilaOrder[];
  /** Reaproveita os helpers da Index pra não duplicar formatação. */
  isOverdue: (o: FilaOrder) => boolean;
  formatBRDate: (v?: string | null) => string;
  formatBRL: (v: number | string | null | undefined) => string;
  /** Abre o drawer canônico (ServiceOrderSheet) pra edição/ações FSM. */
  onOpenFull: (id: number) => void;
}

// 1ª linha do sintoma/defeito relatado (linha secundária da fila).
function defeitoLine(notes?: string | null): string {
  if (!notes) return '';
  return notes.split(/\r?\n/)[0]?.trim() ?? '';
}

function vehicleLabel(o: FilaOrder): string {
  if (!o.vehicle) return 'Sem veículo';
  const head = o.vehicle.vehicle_number ?? o.vehicle.plate;
  return o.vehicle.vehicle_number ? `${head} · ${o.vehicle.plate}` : head;
}

function typeLabel(t: FilaOrder['order_type']): string {
  if (t === 'mecanica') return 'Mecânica';
  if (t === 'manutencao') return 'Manutenção';
  return '—';
}

// ──────── lista (coluna esquerda) ────────
function FilaItem({
  o,
  active,
  overdue,
  onSelect,
  formatBRDate,
}: {
  o: FilaOrder;
  active: boolean;
  overdue: boolean;
  onSelect: () => void;
  formatBRDate: Props['formatBRDate'];
}) {
  const prazo = o.expected_completion;
  const meta = [o.vehicle?.plate, typeLabel(o.order_type)].filter((s) => s && s !== '—').join(' · ');
  const defeito = defeitoLine(o.notes);
  return (
    <button
      type="button"
      onClick={onSelect}
      className={cn(
        'w-full rounded-md border px-3 py-2.5 text-left transition-colors',
        active
          ? 'border-primary/60 bg-primary/5 ring-1 ring-primary/30'
          : 'border-border bg-card hover:bg-muted/40',
      )}
    >
      <div className="flex items-center justify-between gap-2">
        <ServiceOrderStatusBadge status={o.status} orderType={o.order_type} isOverdue={overdue} />
        <span className={cn('text-[11px] tabular-nums', overdue ? 'font-medium text-destructive' : 'text-muted-foreground')}>
          {formatBRDate(prazo)}
        </span>
      </div>
      <div className="mt-1.5 truncate text-sm font-medium text-foreground">{vehicleLabel(o)}</div>
      <div className="truncate text-xs text-muted-foreground">
        OS {o.number ?? `#${o.id}`} · {o.contact?.name ?? '—'}
      </div>
      {defeito && <div className="mt-0.5 truncate text-[11px] text-muted-foreground" title={defeito}>{defeito}</div>}
      {meta && <div className="mt-0.5 truncate font-mono text-[11px] text-muted-foreground">{meta}</div>}
    </button>
  );
}

function FilaList({
  orders,
  selectedId,
  onSelect,
  isOverdue,
  formatBRDate,
}: {
  orders: FilaOrder[];
  selectedId: number | null;
  onSelect: (id: number) => void;
  isOverdue: Props['isOverdue'];
  formatBRDate: Props['formatBRDate'];
}) {
  const urgentes = orders.filter((o) => isOverdue(o));
  const demais = orders.filter((o) => !isOverdue(o));

  const Group = ({ title, items }: { title: string; items: FilaOrder[] }) =>
    items.length === 0 ? null : (
      <div className="space-y-1.5">
        <div className="flex items-center gap-2 px-1 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
          {title}
          <span className="rounded-full bg-muted px-1.5 text-[10px] tabular-nums">{items.length}</span>
        </div>
        {items.map((o) => (
          <FilaItem
            key={o.id}
            o={o}
            active={o.id === selectedId}
            overdue={isOverdue(o)}
            onSelect={() => onSelect(o.id)}
            formatBRDate={formatBRDate}
          />
        ))}
      </div>
    );

  return (
    <div className="flex min-h-0 flex-col rounded-lg border bg-muted/20">
      <div className="flex items-center justify-between border-b px-3 py-2.5">
        <b className="text-sm text-foreground">Ordens de serviço</b>
        <span className="text-xs text-muted-foreground">{orders.length} na fila</span>
      </div>
      <div className="min-h-0 flex-1 space-y-3 overflow-y-auto p-2.5">
        <Group title="Urgentes" items={urgentes} />
        <Group title="Demais" items={demais} />
      </div>
    </div>
  );
}

// ──────── detalhe inline (centro) — read-only; edição via "Abrir OS completa" ────────
function OsDetailInline({
  o,
  overdue,
  formatBRDate,
  formatBRL,
  onOpenFull,
}: {
  o: FilaOrder;
  overdue: boolean;
  formatBRDate: Props['formatBRDate'];
  formatBRL: Props['formatBRL'];
  onOpenFull: Props['onOpenFull'];
}) {
  const prazo = o.expected_completion;
  const inicio = o.started_at ?? o.entered_at;
  const defeito = defeitoLine(o.notes);
  return (
    <div className="flex min-h-0 flex-col rounded-lg border bg-card">
      <header className="flex items-start justify-between gap-3 border-b px-4 py-3">
        <div className="min-w-0">
          <div className="flex items-center gap-2 text-xs text-muted-foreground">
            <ServiceOrderStatusBadge status={o.status} orderType={o.order_type} isOverdue={overdue} />
            <span className="truncate">
              OS {o.number ?? `#${o.id}`} · {o.contact?.name ?? '—'}
            </span>
          </div>
          <h2 className="mt-1 truncate text-lg font-semibold text-foreground">{vehicleLabel(o)}</h2>
        </div>
        <button
          type="button"
          onClick={() => onOpenFull(o.id)}
          className="inline-flex shrink-0 items-center gap-1.5 rounded-md bg-primary px-3 py-1.5 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90"
        >
          <ExternalLink className="size-3.5" />
          Abrir OS completa
        </button>
      </header>

      <div className="min-h-0 flex-1 space-y-4 overflow-y-auto p-4">
        {/* Pipeline FSM ao vivo (mesmo componente do drawer) */}
        <ServiceOrderStagePipeline serviceOrderId={o.id} enabled />

        {/* Resumo da OS */}
        <dl className="grid grid-cols-2 gap-x-6 gap-y-2 rounded-md border bg-muted/20 px-4 py-3 text-sm sm:grid-cols-3">
          <div>
            <dt className="text-xs text-muted-foreground">Tipo</dt>
            <dd className="text-foreground">{typeLabel(o.order_type)}</dd>
          </div>
          <div>
            <dt className="text-xs text-muted-foreground">Veículo</dt>
            <dd className="truncate font-mono text-foreground">{vehicleLabel(o)}</dd>
          </div>
          <div>
            <dt className="text-xs text-muted-foreground">Início</dt>
            <dd className="tabular-nums text-foreground">{formatBRDate(inicio)}</dd>
          </div>
          <div>
            <dt className="text-xs text-muted-foreground">Prazo</dt>
            <dd className={cn('tabular-nums', overdue ? 'font-medium text-destructive' : 'text-foreground')}>
              {formatBRDate(prazo)}
              {overdue && ' ⚠'}
            </dd>
          </div>
          <div>
            <dt className="text-xs text-muted-foreground">A receber</dt>
            <dd className={cn('tabular-nums', Number(o.valor_receber ?? 0) > 0 ? 'text-warning' : 'text-foreground')}>
              {formatBRL(o.valor_receber)}
            </dd>
          </div>
          {defeito && (
            <div className="col-span-2 sm:col-span-3">
              <dt className="text-xs text-muted-foreground">Defeito</dt>
              <dd className="text-foreground">{o.notes}</dd>
            </div>
          )}
        </dl>

        {/* Linha do tempo FSM ao vivo (mesmo componente do drawer) */}
        <div>
          <h4 className="mb-1.5 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Linha do tempo</h4>
          <ServiceOrderTimeline serviceOrderId={o.id} enabled />
        </div>
      </div>
    </div>
  );
}

// ──────── rail "Apps vinculados" (direita) — só dados reais ────────
function AppsRail({
  o,
  overdue,
  formatBRDate,
  formatBRL,
  onOpenFull,
}: {
  o: FilaOrder;
  overdue: boolean;
  formatBRDate: Props['formatBRDate'];
  formatBRL: Props['formatBRL'];
  onOpenFull: Props['onOpenFull'];
}) {
  const prazo = o.expected_completion;
  return (
    <aside className="hidden min-h-0 flex-col gap-3 xl:flex">
      <div className="px-1 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Apps vinculados</div>

      {/* Card OS */}
      <div className="rounded-lg border bg-card p-3">
        <div className="mb-2 flex items-center gap-1.5 text-xs font-medium text-foreground">
          <span className="inline-flex items-center gap-1 rounded bg-primary/10 px-1.5 py-0.5 text-primary">
            <Car className="size-3" />
            OS
          </span>
          <b>Ordem {o.number ?? `#${o.id}`}</b>
        </div>
        <dl className="space-y-1 text-xs">
          <div className="flex justify-between gap-2">
            <dt className="text-muted-foreground">Veículo</dt>
            <dd className="truncate text-right text-foreground">{vehicleLabel(o)}</dd>
          </div>
          <div className="flex justify-between gap-2">
            <dt className="text-muted-foreground">Prazo</dt>
            <dd className={cn('tabular-nums', overdue ? 'font-medium text-destructive' : 'text-foreground')}>{formatBRDate(prazo)}</dd>
          </div>
          <div className="flex justify-between gap-2">
            <dt className="text-muted-foreground">A receber</dt>
            <dd className="font-mono tabular-nums text-foreground">{formatBRL(o.valor_receber)}</dd>
          </div>
        </dl>
        <button
          type="button"
          onClick={() => onOpenFull(o.id)}
          className="mt-2.5 inline-flex w-full items-center justify-center gap-1.5 rounded-md border border-border bg-background px-2 py-1.5 text-xs font-medium text-foreground transition-colors hover:bg-muted"
        >
          <ExternalLink className="size-3" />
          Abrir OS completa
        </button>
      </div>

      {/* Card Cliente (CRM real não plugado — sem telefone/WhatsApp mock; US-OFICINA-006) */}
      <div className="rounded-lg border bg-card p-3">
        <div className="mb-2 flex items-center gap-1.5 text-xs font-medium text-foreground">
          <span className="inline-flex items-center gap-1 rounded bg-muted px-1.5 py-0.5 text-muted-foreground">
            <User2 className="size-3" />
            Cliente
          </span>
          <b className="truncate">{o.contact?.name ?? '—'}</b>
        </div>
        <p className="text-xs leading-relaxed text-muted-foreground">
          Histórico de contato e disparo de WhatsApp ainda não disponíveis nesta tela.
        </p>
      </div>
    </aside>
  );
}

// ──────── view ────────
export default function ServiceOrderFila({
  orders,
  isOverdue,
  formatBRDate,
  formatBRL,
  onOpenFull,
}: Props) {
  const [selectedId, setSelectedId] = useState<number | null>(orders[0]?.id ?? null);

  // Seleção resiliente: respeita selectedId se ainda na lista; senão 1º da lista.
  const selected = useMemo(() => {
    return orders.find((o) => o.id === selectedId) ?? orders[0] ?? null;
  }, [orders, selectedId]);

  if (orders.length === 0) {
    return <div className="rounded-lg border bg-card px-4 py-10 text-center text-sm text-muted-foreground">Nenhuma OS no filtro atual.</div>;
  }

  return (
    <div className="grid max-h-[72vh] grid-cols-1 gap-3 md:grid-cols-[280px_minmax(0,1fr)] xl:grid-cols-[280px_minmax(0,1fr)_280px]">
      <FilaList
        orders={orders}
        selectedId={selected?.id ?? null}
        onSelect={setSelectedId}
        isOverdue={isOverdue}
        formatBRDate={formatBRDate}
      />
      {selected ? (
        <OsDetailInline
          o={selected}
          overdue={isOverdue(selected)}
          formatBRDate={formatBRDate}
          formatBRL={formatBRL}
          onOpenFull={onOpenFull}
        />
      ) : (
        <div className="flex items-center justify-center rounded-lg border bg-card text-sm text-muted-foreground">
          Selecione uma OS na fila.
        </div>
      )}
      {selected && (
        <AppsRail
          o={selected}
          overdue={isOverdue(selected)}
          formatBRDate={formatBRDate}
          formatBRL={formatBRL}
          onOpenFull={onOpenFull}
        />
      )}
    </div>
  );
}
