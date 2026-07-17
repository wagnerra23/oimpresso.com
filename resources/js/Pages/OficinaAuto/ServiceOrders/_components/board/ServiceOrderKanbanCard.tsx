// Card do Kanban de OS de mecânica (fluxo real do carro · port Cowork [W] 2026-06-02).
//
// Card ÚNICO do kanban canônico (ADR 0265 — o card de locação morreu junto com a
// tela producao-oficina). Reusa MercosulPlate. Aplica as 3 modificações [W]-aceitas:
//   1. Foto REAL no card (thumb_url) — nunca placeholder de texto. Sem foto → esconde.
//   2. Contador DVI x/y com ícone de checklist (não cadeado) + tooltip.
//   3. Densidade compacta como base; respira em telas largas via @container (NÃO @media —
//      lição Financeiro F3). O card é o mesmo; a densidade do board é controlada no Board.tsx.
//
// Drag canon: useDraggable (@dnd-kit) com data {subjectId, currentColumn, subject}.
// Drag desabilitado quando in_pipeline=false (OS recém-criada sem pipeline iniciado) —
// clicar abre o drawer pra iniciar o pipeline FSM.
//
// CRÍTICO React 19 — memo + handlers estáveis (lição PR #717).

import { memo, useCallback, useEffect, useRef, type CSSProperties } from 'react';
import { useDraggable } from '@dnd-kit/core';
import { CSS } from '@dnd-kit/utilities';
import { ClipboardCheck, AlertTriangle, Clock, Wrench, Camera, Warehouse, ArrowRight, History } from 'lucide-react';
import { Inline } from '@/Components/layout';
import MercosulPlate from '@/Components/shared/MercosulPlate';

// Densidade do card (menu Visão — Onda 1 paridade Cowork): compacto esconde
// sintoma+foto (triagem rápida de fila grande); detalhe respira (foto maior,
// sintoma sem truncar, chip do box). Tipo mora aqui (não no boardTone — intocado).
export type BoardDensity = 'compacto' | 'padrao' | 'detalhe';

/** Última transição FSM auditada (linha "últ." — paridade Cowork). */
export interface LastActivity {
  label: string;
  at: string;
}

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
  box: string | null;
  mechanic_id: number | null;
  mechanic_name: string | null;
  mechanic_initials: string | null;
  km: number | null;
  progress: number | null;
  entered_at: string | null;
  completed_at: string | null;
  expected_completion: string | null;
  is_overdue: boolean;
  last_activity: LastActivity | null;
  notes: string | null;
  urls: { show: string; edit: string };
}

interface Props {
  card: ServiceOrderCardData;
  /** stage key da coluna (canon FSM oficina_mecanica_os) */
  stageKey: string;
  /** classe Tailwind do border-top colorido por etapa */
  topBorderClass: string;
  /** densidade do menu Visão (default padrao = visual atual) */
  density?: BoardDensity;
  /** anel de foco da navegação por setas (D-07 — teclado-first) */
  focused?: boolean;
  /** desliga o drag (foco Box/Mecânico — colunas não são etapas FSM) */
  dragDisabled?: boolean;
  /** rótulo da ação primária da etapa (botão inline — paridade Cowork). null = sem botão */
  primaryActionLabel?: string | null;
  /** dispara a ação primária (Board abre o confirm FSM ou o drawer) */
  onPrimaryAction?: (card: ServiceOrderCardData) => void;
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

// Tempo relativo curto pra linha "últ." (ex.: "há 2h", "ontem", "há 3d"). Distinto
// de relativeDays (que fala em entrada do veículo) — aqui a granularidade é menor.
function relativeShort(iso: string | null): string | null {
  if (!iso) return null;
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return null;
  const mins = Math.floor((Date.now() - d.getTime()) / 60000);
  if (mins < 1) return 'agora';
  if (mins < 60) return `há ${mins}min`;
  const hours = Math.floor(mins / 60);
  if (hours < 24) return `há ${hours}h`;
  const days = Math.floor(hours / 24);
  if (days === 1) return 'ontem';
  if (days < 30) return `há ${days}d`;
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

function ServiceOrderKanbanCardImpl({ card, stageKey, topBorderClass, density = 'padrao', focused = false, dragDisabled = false, primaryActionLabel = null, onPrimaryAction, onClick }: Props) {
  const canDrag = card.in_pipeline && !dragDisabled;
  const draggable = useDraggable({
    id: `so-${card.id}`,
    disabled: !canDrag,
    data: {
      subjectId: card.id,
      currentColumn: stageKey,
      subject: card,
    },
  });
  const { attributes, listeners, setNodeRef, transform, isDragging } = draggable;

  // Anel de foco da navegação por setas (D-07) — mantém o card focado à vista
  // dentro do scroll da coluna sem roubar o foco DOM da busca.
  const elRef = useRef<HTMLDivElement | null>(null);
  const setRefs = useCallback((node: HTMLDivElement | null) => {
    elRef.current = node;
    setNodeRef(node);
  }, [setNodeRef]);
  useEffect(() => {
    if (focused) elRef.current?.scrollIntoView({ block: 'nearest', inline: 'nearest' });
  }, [focused]);

  const dragStyle: CSSProperties = transform
    ? { transform: CSS.Translate.toString(transform) }
    : {};

  const entered = relativeDays(card.entered_at);
  const deadline = deadlineChip(card.expected_completion, card.is_overdue);
  const hasDvi = card.dvi_total > 0;
  const lastWhen = relativeShort(card.last_activity?.at ?? null);
  // Barra de progresso: % DVI decidido. Só aparece com dado e fora do compacto.
  const showProgress = card.progress !== null && density !== 'compacto';
  // Botão de ação inline (paridade Cowork) — só pra OS em pipeline e fora do drag.
  const showAction = primaryActionLabel !== null && card.in_pipeline && !isDragging;
  // [W] mod #2 — tooltip claro distinguindo o que o contador significa.
  const dviTooltip = hasDvi
    ? `DVI: ${card.dvi_done} de ${card.dvi_total} ${card.dvi_total === 1 ? 'item decidido' : 'itens decididos'} pelo cliente`
      + (card.dvi_critico > 0 ? ` · ${card.dvi_critico} crítico${card.dvi_critico === 1 ? '' : 's'}` : '')
    : undefined;

  return (
    <div
      ref={setRefs}
      style={dragStyle}
      {...(canDrag ? attributes : {})}
      {...(canDrag ? listeners : {})}
      className={
        'relative rounded border border-t-2 border-border ' + topBorderClass + ' '
        // `bg-card`, NÃO `bg-white`: o texto do card usa tokens semânticos
        // (text-foreground/text-muted-foreground), que viram CLAROS no dark — sobre branco fixo
        // ficava claro-sobre-claro, ilegível. Mesmo defeito do #4367 (Board) e do #4373 (tons de
        // KPI); o card escapou dos dois porque a baseline do quadro estava VAZIA e nenhum card
        // renderizava no gate. O drag overlay do mesmo card (Board.tsx:462) já usava `bg-card`.
        + 'bg-card p-2.5 transition-colors '
        + (isDragging
          ? 'opacity-50 cursor-grabbing ring-2 ring-primary/60 ring-offset-1'
          : canDrag
            ? 'cursor-grab active:cursor-grabbing hover:shadow-sm hover:border-muted-foreground/40'
            : 'cursor-pointer hover:border-muted-foreground/30')
        + (focused && !isDragging ? ' ring-2 ring-primary ring-offset-1' : '')
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

      {/* [W] mod #1 — foto REAL (sem placeholder de texto). Sem foto → não renderiza.
          Densidade: compacto esconde a foto; detalhe amplia. */}
      {card.thumb_url && density !== 'compacto' ? (
        <div className="mb-2 rounded overflow-hidden border border-border bg-muted/40">
          <img
            src={card.thumb_url}
            alt={`Foto da OS ${card.number}`}
            className={density === 'detalhe' ? 'w-full h-24 object-cover' : 'w-full h-16 object-cover @[340px]:h-20'}
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
          {/* km de entrada · cliente (paridade Cowork — km é dado real do veículo) */}
          {card.cliente_nome || card.km !== null ? (
            <span className="text-[10.5px] text-muted-foreground truncate">
              {card.km !== null ? `${card.km.toLocaleString('pt-BR')} km` : ''}
              {card.km !== null && card.cliente_nome ? ' · ' : ''}
              {card.cliente_nome ?? ''}
            </span>
          ) : null}
        </div>
      </div>

      {/* Defeito / observação — compacto esconde; detalhe respira (clamp maior) */}
      {card.notes && density !== 'compacto' ? (
        <p
          className={'text-[12px] text-foreground leading-snug mb-2 ' + (density === 'detalhe' ? 'line-clamp-4' : 'line-clamp-2')}
          title={card.notes}
        >
          {card.notes}
        </p>
      ) : null}

      {/* Barra de progresso (paridade Cowork) — % de itens DVI decididos pelo cliente */}
      {showProgress ? (
        <div className="mb-2" title={`${card.progress}% dos itens da vistoria decididos pelo cliente`}>
          <div className="h-1.5 rounded-full bg-muted overflow-hidden" role="progressbar" aria-valuenow={card.progress ?? 0} aria-valuemin={0} aria-valuemax={100} aria-label="Progresso da vistoria">
            <div className="h-full rounded-full bg-primary transition-all" style={{ width: `${card.progress}%` }} />
          </div>
        </div>
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
          {/* Detalhe mostra o box alocado (extra do menu Visão) */}
          {density === 'detalhe' && card.box ? (
            <span className="inline-flex items-center gap-1 text-[10.5px] text-muted-foreground" title={`Box ${card.box}`}>
              <Warehouse size={10} aria-hidden /> {card.box}
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

      {/* Linha "últ." — última transição FSM auditada (paridade Cowork · dado real).
          Compacto esconde pra manter o card enxuto. */}
      {card.last_activity && density !== 'compacto' ? (
        <Inline gap={1} className="mt-1.5 text-[10px] text-muted-foreground min-w-0" title={`${card.last_activity.label}${lastWhen ? ` · ${lastWhen}` : ''}`}>
          <History size={9} className="flex-shrink-0" aria-hidden />
          <span className="font-semibold">últ.</span>
          <span className="truncate">{card.last_activity.label}</span>
          {lastWhen ? <span className="flex-shrink-0 whitespace-nowrap">· {lastWhen}</span> : null}
        </Inline>
      ) : null}

      {/* Botão de ação primária da etapa (paridade Cowork — Triagem→/Concluir→/Entregar→).
          Vai pelo mesmo ExecuteStageActionService do drag (Board abre o confirm).
          stopPropagation pra não abrir o drawer; só pra OS em pipeline. */}
      {showAction ? (
        <Inline justify="end" className="mt-2 pt-2 border-t border-border">
          <button
            type="button"
            className="inline-flex items-center gap-1 text-[11px] font-semibold rounded px-2 py-1 bg-primary text-white hover:bg-primary/90 transition-colors"
            onClick={(e) => { e.stopPropagation(); onPrimaryAction?.(card); }}
            data-testid={`so-card-action-${card.id}`}
          >
            {primaryActionLabel} <ArrowRight size={11} aria-hidden />
          </button>
        </Inline>
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
