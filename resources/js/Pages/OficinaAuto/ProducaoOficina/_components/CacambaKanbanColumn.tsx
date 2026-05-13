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
import CacambaCard, { type CacambaCardData, type CacambaStatus } from './CacambaCard';

interface Props {
  status: CacambaStatus;
  label: string;
  cards: CacambaCardData[];
  onCardClick: (cacamba: CacambaCardData) => void;
}

const dotColorMap: Record<CacambaStatus, string> = {
  disponivel: 'bg-slate-400',
  locada: 'bg-blue-400',
  aguardando: 'bg-rose-400',
  manutencao: 'bg-violet-400',
  pronta: 'bg-emerald-400',
};

// V3 — borda topo 2px colorida (espelha canon .prod-col-{slate,blue,rose,violet,emerald})
const topBorderMap: Record<CacambaStatus, string> = {
  disponivel: 'border-t-slate-400',
  locada:     'border-t-blue-400',
  aguardando: 'border-t-rose-400',
  manutencao: 'border-t-violet-400',
  pronta:     'border-t-emerald-400',
};

function CacambaKanbanColumnImpl({ status, label, cards, onCardClick }: Props) {
  // Handler estável — Card memo recebe sempre mesma referência
  const handleClick = useCallback(
    (c: CacambaCardData) => onCardClick(c),
    [onCardClick]
  );

  const isAguardando = status === 'aguardando';

  return (
    <section
      className={
        'rounded-lg border border-t-2 ' +
        topBorderMap[status] + ' ' +
        (isAguardando
          ? 'bg-amber-50/30 border-amber-200'
          : 'bg-white border-slate-200')
      }
      aria-label={`Coluna ${label}`}
    >
      <header className="px-3 py-2.5 border-b border-slate-200 flex items-center justify-between">
        <div className="flex items-center gap-2 min-w-0">
          <span className={`w-2 h-2 rounded-full flex-shrink-0 ${dotColorMap[status]}`} />
          <h3 className="text-sm font-semibold text-slate-900 truncate">{label}</h3>
        </div>
        <span
          className={
            'text-xs px-1.5 py-0.5 rounded tabular-nums flex-shrink-0 ' +
            (isAguardando
              ? 'bg-amber-100 text-amber-800 font-semibold'
              : 'bg-slate-100 text-slate-600')
          }
          aria-label={`${cards.length} caçambas`}
        >
          {cards.length}
        </span>
      </header>

      <div
        className="p-2 space-y-2 max-h-[calc(100vh-220px)] overflow-y-auto"
        style={{ scrollbarWidth: 'thin' }}
      >
        {cards.length === 0 ? (
          <div className="text-center py-8 text-xs text-slate-400 italic">
            nenhuma caçamba
          </div>
        ) : (
          cards.map((c) => (
            <CacambaCard
              key={c.id}
              cacamba={c}
              variant={status}
              onClick={handleClick}
            />
          ))
        )}
      </div>
    </section>
  );
}

export default memo(CacambaKanbanColumnImpl);
