// Coluna do Kanban de OS de mecânica — data-driven pelas etapas reais do FSM
// oficina_mecanica_os (port Cowork do carro · [W] 2026-06-02).
//
// Coluna do kanban canônico de OS (ADR 0265 — colunas data-driven): a cor vem do
// token `color` do SaleProcessStage (gray/blue/amber/violet/indigo/emerald/...),
// então o quadro se adapta se o seeder mudar as etapas.
//
// useMemo/useCallback nos handlers (lição PR #717).

import { memo, useCallback } from 'react';
import { useDroppable } from '@dnd-kit/core';
import ServiceOrderKanbanCard, { type ServiceOrderCardData } from './ServiceOrderKanbanCard';
import { toneForColor, emphasisClass } from './boardTone';

interface Props {
  stageKey: string;
  name: string;
  color: string | null;
  cards: ServiceOrderCardData[];
  /** distingue visualmente a coluna de aguardando-aprovação × aguardando-peças ([W] mod #4) */
  emphasis?: 'aprovacao' | 'pecas' | null;
  onCardClick: (card: ServiceOrderCardData) => void;
}

function ServiceOrderKanbanColumnImpl({ stageKey, name, color, cards, emphasis, onCardClick }: Props) {
  const handleClick = useCallback((c: ServiceOrderCardData) => onCardClick(c), [onCardClick]);

  const { setNodeRef, isOver } = useDroppable({
    id: `column-${stageKey}`,
    data: { columnStatus: stageKey },
  });

  const tone = toneForColor(color);

  // [W] mod #4 — destaque distinto pras duas colunas de espera (peça física × OK do cliente).
  const emphasisBg = emphasisClass(emphasis ?? null);

  const overClasses = isOver ? 'ring-2 ring-primary/60 ring-offset-2 bg-primary/5' : '';

  return (
    <section
      ref={setNodeRef}
      className={'rounded-lg border border-t-2 transition-all ' + tone.topBorder + ' ' + emphasisBg + ' ' + overClasses}
      aria-label={`Coluna ${name}`}
      aria-dropeffect="move"
      data-testid={`board-column-${stageKey}`}
    >
      <header className="px-3 py-2.5 border-b border-border flex items-center justify-between gap-2">
        <div className="flex items-center gap-2 min-w-0">
          <span className={`w-2 h-2 rounded-full flex-shrink-0 ${tone.dot}`} />
          <h3 className="text-sm font-semibold text-foreground truncate">{name}</h3>
        </div>
        <span
          className={'text-xs px-1.5 py-0.5 rounded tabular-nums flex-shrink-0 font-semibold ' + tone.badge}
          aria-label={`${cards.length} ${cards.length === 1 ? 'OS' : 'OS'}`}
          data-testid={`board-count-${stageKey}`}
        >
          {cards.length}
        </span>
      </header>

      <div className="p-2 space-y-2 max-h-[calc(100vh-260px)] overflow-y-auto @[1280px]/board:max-h-[calc(100vh-300px)]" style={{ scrollbarWidth: 'thin' }}>
        {cards.length === 0 ? (
          <div className="text-center py-8 text-xs text-muted-foreground italic">nenhuma OS</div>
        ) : (
          cards.map((c) => (
            <ServiceOrderKanbanCard
              key={c.id}
              card={c}
              stageKey={stageKey}
              topBorderClass={tone.topBorder}
              onClick={handleClick}
            />
          ))
        )}
      </div>
    </section>
  );
}

export default memo(ServiceOrderKanbanColumnImpl);
