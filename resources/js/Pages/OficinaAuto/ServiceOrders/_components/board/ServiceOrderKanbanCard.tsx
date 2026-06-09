// Card do Kanban de OS de mecânica (fluxo real do carro · port Cowork [W] 2026-06-02).
//
// NÃO é o card de caçamba (CacambaCard) — vertical diferente (ADR 0194). Reusa
// MercosulPlate. Aplica as 3 modificações [W]-aceitas do protótipo:
//   1. Foto REAL no card (thumb_url) — nunca placeholder de texto. Sem foto → esconde.
//   2. Contador DVI x/y com ícone de checklist (não cadeado) + tooltip.
//   3. Densidade compacta como base; respira em telas largas via @container (NÃO @media —
//      lição Financeiro F3). O card é o mesmo; a densidade do board é controlada no Board.tsx.
//
// Drag canon: useDraggable (@dnd-kit) com data {cacambaId, currentColumn, cacamba}.
// Drag desabilitado quando in_pipeline=false (OS recém-criada sem pipeline iniciado) —
// clicar abre o drawer pra iniciar o pipeline FSM.
//
// CRÍTICO React 19 — memo + handlers estáveis (lição PR #717).

import { memo, type CSSProperties } from 'react';
import { useDraggable } from '@dnd-kit/core';
import { CSS } from '@dnd-kit/utilities';
import { ClipboardCheck, AlertTriangle, Clock, Wrench, Camera } from 'lucide-react';
import MercosulPlate from '@/Pages/OficinaAuto/ProducaoOficina/_components/MercosulPlate';

export interface ServiceOrderCardData {
  id: number;
  number: string;
  in_pipeline: boolean;
  plate: string | null;
  vehicle_type: string | null;
  cliente_nome: string | null;
  thumb_url: string | null;
  dvi_done: number;
  dvi_total: number;
  dvi_critico: number;
  valor: number;
  mechanic_name: string | null;
  mechanic_initials: string | null;
  entered_at: string | null;
  expected_completion: string | null;
  is_overdue: boolean;
  notes: string | null;
  urls: { show: string; edit: string };
}

interface Props {
  card: ServiceOrderCardData;
  /** stage key da coluna (canon FSM oficina_mecanica_os) */
  stageKey: string;
  /** classe Tailwind do border-top colorido por etapa */
  topBorderClass: string;
  onClick: (card: ServiceOrderCardData) => void;
}

const formatBRL = (value: number | null | undefined) =>
  new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL', maximumFractionDigits: 0 }).format(
    Number(value ?? 0),
  );

function relativeDays(iso: string | null): string | null {
  if (!iso) return null;
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return null;
  const days = Math.floor((Date.now() - d.getTime()) / (1000 * 60 * 60 * 24));
  if (days <= 0) return 'hoje';
  if (days === 1) return 'há 1 dia';
  if (days < 30) return `há ${days} dias`;
  const months = Math.floor(days / 30);
  return months === 1 ? 'há 1 mês' : `há ${months} meses`;
}

// Chip de prazo restante — só aparece quando a OS está atrasada (destructive) ou
// vence em < 24h (warning). Estático: recalcula no render, sem countdown animado.
function deadlineChip(
  iso: string | null,
  isOverdue: boolean,
): { label: string; overdue: boolean } | null {
  if (!iso) return null;
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return null;
  const diffMs = d.getTime() - Date.now();

  if (isOverdue || diffMs < 0) {
    const lateMs = Math.abs(diffMs);
    const days = Math.floor(lateMs / (1000 * 60 * 60 * 24));
    const hours = Math.floor(lateMs / (1000 * 60 * 60));
    return { label: days >= 1 ? `vencida há ${days}d` : `vencida há ${Math.max(1, hours)}h`, overdue: true };
  }

  const hoursLeft = diffMs / (1000 * 60 * 60);
  if (hoursLeft < 24) {
    return { label: `vence em ${Math.max(1, Math.ceil(hoursLeft))}h`, overdue: false };
  }
  return null;
}

function ServiceOrderKanbanCardImpl({ card, stageKey, topBorderClass, onClick }: Props) {
  const draggable = useDraggable({
    id: `so-${card.id}`,
    disabled: !card.in_pipeline,
    data: {
      cacambaId: card.id,
      currentColumn: stageKey,
      cacamba: card,
    },
  });
  const { attributes, listeners, setNodeRef, transform, isDragging } = draggable;

  const dragStyle: CSSProperties = transform
    ? { transform: CSS.Translate.toString(transform) }
    : {};

  const entered = relativeDays(card.entered_at);
  const deadline = deadlineChip(card.expected_completion, card.is_overdue);
  const hasDvi = card.dvi_total > 0;
  // [W] mod #2 — tooltip claro distinguindo o que o contador significa.
  const dviTooltip = hasDvi
    ? `DVI: ${card.dvi_done} de ${card.dvi_total} ${card.dvi_total === 1 ? 'item decidido' : 'itens decididos'} pelo cliente`
      + (card.dvi_critico > 0 ? ` · ${card.dvi_critico} crítico${card.dvi_critico === 1 ? '' : 's'}` : '')
    : undefined;

  return (
    <div
      ref={setNodeRef}
      style={dragStyle}
      {...(card.in_pipeline ? attributes : {})}
      {...(card.in_pipeline ? listeners : {})}
      className={
        'relative rounded border border-t-2 border-border ' + topBorderClass + ' '
        + 'bg-white p-2.5 transition-colors '
        + (isDragging
          ? 'opacity-50 cursor-grabbing ring-2 ring-primary/60 ring-offset-1'
          : card.in_pipeline
            ? 'cursor-grab active:cursor-grabbing hover:shadow-sm hover:border-muted-foreground/40'
            : 'cursor-pointer hover:border-muted-foreground/30')
      }
      onClick={(e) => {
        if (isDragging) { e.preventDefault(); return; }
        onClick(card);
      }}
      onKeyDown={(e) => {
        if (e.key === 'Enter') { e.preventDefault(); onClick(card); }
      }}
      role="button"
      tabIndex={0}
      data-testid={`so-card-${card.id}`}
      data-stage={stageKey}
      data-in-pipeline={card.in_pipeline ? '1' : '0'}
      aria-label={`OS ${card.number}${card.plate ? ` — ${card.plate}` : ''}${card.cliente_nome ? ` — ${card.cliente_nome}` : ''}`}
      aria-roledescription={card.in_pipeline ? 'Card arrastável entre etapas' : 'OS sem pipeline — clique pra iniciar'}
    >
      {/* Linha 1: OS# + valor + overdue */}
      <div className="flex items-center justify-between gap-2 mb-1.5">
        <span className="font-mono text-[11px] text-muted-foreground font-medium">{card.number}</span>
        <div className="flex items-center gap-1.5 flex-shrink-0">
          {card.valor > 0 && (
            <span className="text-[11px] font-semibold tabular-nums text-success whitespace-nowrap" title="Total de peças + mão de obra">
              {formatBRL(card.valor)}
            </span>
          )}
          {card.is_overdue && (
            <span className="inline-flex items-center gap-0.5 text-[10px] font-semibold text-destructive bg-destructive/10 border border-destructive/30 rounded px-1 py-0.5" title="Prazo de entrega vencido">
              <AlertTriangle size={10} /> atrasada
            </span>
          )}
        </div>
      </div>

      {/* [W] mod #1 — foto REAL (sem placeholder de texto). Sem foto → não renderiza. */}
      {card.thumb_url ? (
        <div className="mb-2 rounded overflow-hidden border border-border bg-muted/40">
          <img
            src={card.thumb_url}
            alt={`Foto da OS ${card.number}`}
            className="w-full h-16 object-cover @[340px]:h-20"
            loading="lazy"
          />
        </div>
      ) : null}

      {/* Linha 2: placa Mercosul + tipo/cliente */}
      <div className="flex items-center gap-2 mb-1.5">
        {card.plate ? <MercosulPlate plate={card.plate} size="sm" /> : null}
        <div className="flex flex-col gap-0 min-w-0 flex-1">
          <span className="text-[12.5px] font-medium text-foreground truncate" title={card.vehicle_type ?? undefined}>
            {card.vehicle_type ?? 'Veículo'}
          </span>
          {card.cliente_nome ? (
            <span className="text-[10.5px] text-muted-foreground truncate">{card.cliente_nome}</span>
          ) : null}
        </div>
      </div>

      {/* Defeito / observação */}
      {card.notes ? (
        <p className="text-[12px] text-foreground leading-snug mb-2 line-clamp-2" title={card.notes}>
          {card.notes}
        </p>
      ) : null}

      {/* Rodapé: DVI counter + mecânico + entrada */}
      <div className="flex items-center justify-between gap-2 mt-1.5">
        <div className="flex items-center gap-2 min-w-0">
          {/* [W] mod #2 — checklist DVI x/y (não cadeado), tooltip explica. */}
          {hasDvi && (
            <span
              className={
                'inline-flex items-center gap-1 text-[10.5px] font-medium rounded px-1.5 py-0.5 border tabular-nums '
                + (card.dvi_critico > 0
                  ? 'text-destructive bg-destructive/10 border-destructive/30'
                  : 'text-muted-foreground bg-muted/40 border-border')
              }
              title={dviTooltip}
            >
              <ClipboardCheck size={11} aria-hidden />
              DVI {card.dvi_done}/{card.dvi_total}
              {card.dvi_critico > 0 ? (
                <span className="inline-block w-1.5 h-1.5 rounded-full bg-destructive/100" aria-hidden />
              ) : null}
            </span>
          )}
          {card.mechanic_initials ? (
            <span className="inline-flex items-center gap-1 text-[10.5px] text-muted-foreground min-w-0" title={card.mechanic_name ?? undefined}>
              <span className="inline-flex items-center justify-center w-[18px] h-[18px] rounded-full bg-muted text-foreground text-[9px] font-semibold flex-shrink-0">
                {card.mechanic_initials}
              </span>
              <span className="truncate max-w-[80px]">{card.mechanic_name}</span>
            </span>
          ) : null}
        </div>
        {entered ? (
          <span className="text-[10px] text-muted-foreground whitespace-nowrap inline-flex items-center gap-0.5">
            <Clock size={9} /> {entered}
          </span>
        ) : null}
      </div>

      {/* Prazo restante — chip discreto só quando atrasada (destructive) ou < 24h (warning) */}
      {deadline ? (
        <div className="mt-1.5 text-right">
          <span
            className={
              'inline-flex items-center gap-0.5 text-[10px] font-semibold rounded px-1.5 py-0.5 border tabular-nums '
              + (deadline.overdue
                ? 'text-destructive bg-destructive/10 border-destructive/30'
                : 'text-warning-foreground bg-warning/10 border-warning/30')
            }
            title="Prazo de entrega"
          >
            <Clock size={9} aria-hidden /> {deadline.label}
          </span>
        </div>
      ) : null}

      {/* OS sem pipeline iniciado — dica discreta */}
      {!card.in_pipeline && (
        <div className="mt-2 pt-1.5 border-t border-border flex items-center gap-1 text-[10.5px] text-warning-foreground">
          <Wrench size={10} /> clique pra iniciar o pipeline
        </div>
      )}

      {/* Marcador de ausência de foto pra leitura interna (acessível, sem texto "inacabado") */}
      {!card.thumb_url && (
        <span className="sr-only">Sem foto anexada</span>
      )}
      {/* Ícone câmera sutil no canto quando NÃO há foto (sinaliza ação, não placeholder textual) */}
      {!card.thumb_url && (
        <Camera size={12} className="absolute top-2 right-2 text-muted-foreground" aria-hidden />
      )}
    </div>
  );
}

export default memo(ServiceOrderKanbanCardImpl);
