// Coluna do Kanban Produção · Oficina — header (dot + label + count) + body scroll.
// Espelha 1:1 protótipo Cowork canônico (prototipo-ui/prototipos/producao-oficina/visual-source.html).
//
// V3 fixes:
//  - Borda superior 2px colorida por coluna (espelha .prod-col-{slate,blue,rose,violet,emerald})
//  - Dot color match com card top-border
//
// 5 variantes (cor do dot + bg/border destaque na variante "aguardando"):
//  - disponivel  → slate-400 dot + border-t slate-400
//  - locada      → blue-400 dot + border-t blue-400
//  - aguardando  → rose-400 dot + border-t rose-400 (destaque coluna inteira amber bg)
//  - manutencao  → violet-400 dot + border-t violet-400
//  - pronta      → emerald-400 dot + border-t emerald-400
//
// useMemo/useCallback nos handlers descendentes (lição PR #717).

import { memo, useCallback } from 'react';
import { useDroppable } from '@dnd-kit/core';
import CacambaCard, { type CacambaCardData, type CacambaStatus } from './CacambaCard';
import { useKanbanDragState } from './kanbanDrag';

interface Props {
  status: CacambaStatus;
  label: string;
  cards: CacambaCardData[];
  onCardClick: (cacamba: CacambaCardData) => void;
  /** D-02 — avançar etapa direto pelo botão do card (mesma porta do arrasto). */
  onCardAdvance?: (cacamba: CacambaCardData, from: CacambaStatus) => void;
}

const dotColorMap: Record<CacambaStatus, string> = {
  disponivel: 'bg-[var(--stage-slate)]',
  locada: 'bg-[var(--stage-blue)]',
  aguardando: 'bg-[var(--stage-rose)]',
  manutencao: 'bg-[var(--stage-violet)]',
  pronta: 'bg-[var(--stage-emerald)]',
};

// V3 — borda topo 2px colorida (espelha canon .prod-col-{slate,blue,rose,violet,emerald})
const topBorderMap: Record<CacambaStatus, string> = {
  disponivel: 'border-t-[var(--stage-slate)]',
  locada:     'border-t-[var(--stage-blue)]',
  aguardando: 'border-t-[var(--stage-rose)]',
  manutencao: 'border-t-[var(--stage-violet)]',
  pronta:     'border-t-[var(--stage-emerald)]',
};

function CacambaKanbanColumnImpl({ status, label, cards, onCardClick, onCardAdvance }: Props) {
  // Handler estável — Card memo recebe sempre mesma referência
  const handleClick = useCallback(
    (c: CacambaCardData) => onCardClick(c),
    [onCardClick]
  );

  const handleAdvance = useCallback(
    (c: CacambaCardData) => onCardAdvance?.(c, status),
    [onCardAdvance, status]
  );

  // Drop zone — id = status (disponivel/locada/aguardando/manutencao/pronta)
  const { setNodeRef, isOver } = useDroppable({
    id: `column-${status}`,
    data: { columnStatus: status },
  });

  const isAguardando = status === 'aguardando';

  // D-01 — feedback preditivo: durante o arrasto a coluna mostra o desfecho de soltar
  // aqui (verde "solte p/ avançar" se o gate libera · âmbar "abre detalhes" se barra).
  const { activeFromColumn, verdictFor } = useKanbanDragState();
  const isDragging = activeFromColumn != null;
  const verdict = isDragging ? verdictFor(status) : null; // null = origem ou mesma coluna
  const isValidTarget = verdict === 'advance' || verdict === 'confirm';

  // Realce: coluna sob o cursor (isOver) ganha realce forte; alvos válidos não-sob-cursor
  // ganham dica sutil; bloqueado fica âmbar discreto. Sem arrasto = neutro (comportamento antigo).
  let overClasses = '';
  if (verdict) {
    if (isOver) {
      // Tokens semânticos success/warning (ADR UI-0013 · não-flagados R1) — verde=avança, âmbar=barra.
      overClasses = isValidTarget
        ? 'ring-2 ring-success/70 ring-offset-2 bg-success/10'
        : 'ring-2 ring-warning/70 ring-offset-2 bg-warning/10';
    } else if (isValidTarget) {
      overClasses = 'ring-1 ring-success/40';
    }
  } else if (isOver) {
    // Sem evaluateDrop (fallback) — realce neutro antigo.
    overClasses = 'ring-2 ring-primary/60 ring-offset-2 bg-primary/5';
  }

  return (
    <section
      ref={setNodeRef}
      className={
        'rounded-lg border border-t-2 transition-all ' +
        topBorderMap[status] + ' ' +
        (isAguardando
          ? 'bg-warning/10 border-warning/30'
          : 'bg-white border-border') + ' ' +
        overClasses
      }
      aria-label={`Coluna ${label}`}
      aria-dropeffect="move"
    >
      <header className="px-3 py-2.5 border-b border-border flex items-center justify-between">
        <div className="flex items-center gap-2 min-w-0">
          <span className={`w-2 h-2 rounded-full flex-shrink-0 ${dotColorMap[status]}`} />
          <h3 className="text-sm font-semibold text-foreground truncate">{label}</h3>
        </div>
        <span
          className={
            'text-xs px-1.5 py-0.5 rounded tabular-nums flex-shrink-0 ' +
            (isAguardando
              ? 'bg-warning/15 text-warning-foreground font-semibold'
              : 'bg-muted text-muted-foreground')
          }
          aria-label={`${cards.length} caçambas`}
        >
          {cards.length}
        </span>
      </header>

      {/* D-01 — dica preditiva na coluna sob o cursor (Linear/Stripe: sem bounce-surpresa) */}
      {isOver && verdict && (
        <div
          className={
            'px-3 py-1 text-[11px] font-medium border-b ' +
            (isValidTarget
              ? 'bg-success/10 text-success-foreground border-success/30'
              : 'bg-warning/10 text-warning-foreground border-warning/30')
          }
          aria-live="polite"
        >
          {verdict === 'advance'
            ? 'Solte p/ avançar'
            : verdict === 'confirm'
              ? 'Solte p/ confirmar avanço'
              : 'Abre os detalhes (não avança aqui)'}
        </div>
      )}

      <div
        className="p-2 space-y-2 max-h-[calc(100vh-220px)] overflow-y-auto"
        style={{ scrollbarWidth: 'thin' }}
      >
        {cards.length === 0 ? (
          <div className="text-center py-8 text-xs text-muted-foreground/60 italic">
            nenhuma caçamba
          </div>
        ) : (
          cards.map((c) => (
            <CacambaCard
              key={c.id}
              cacamba={c}
              variant={status}
              onClick={handleClick}
              onAdvance={onCardAdvance ? handleAdvance : undefined}
            />
          ))
        )}
      </div>
    </section>
  );
}

export default memo(CacambaKanbanColumnImpl);
