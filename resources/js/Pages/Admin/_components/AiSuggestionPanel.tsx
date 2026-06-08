import * as React from 'react';
import { Sparkles, TrendingUp, TrendingDown } from 'lucide-react';
import { cn } from '@/Lib/utils';
import type { AiSuggestion } from './governanceV4Types';

/**
 * AiSuggestionPanel — top 5 sugestões AI baseline 30d (READ-ONLY)
 *
 * Wave 29 (W29-C). Mostra suggestions agregadas do `mcp_scorecard_ai_suggestions`
 * (Wave 24-B baseline 30d, observacional). Anti-Goodhart: NÃO altera score
 * oficial — só serve como sinal pra Wagner inspecionar drift entre rubrica
 * determinística vs avaliação LLM.
 *
 * Filtra por |avg_delta| > minDelta e mostra top N. Cada item:
 *  - módulo
 *  - delta médio (com seta up/down)
 *  - última justificativa (truncada)
 *  - confidence + count
 */
interface Props {
  suggestions: AiSuggestion[];
  /** filtra módulo único; default = todos */
  module?: string;
  topN?: number;
  minDelta?: number;
  className?: string;
  emptyHint?: string;
}

export default function AiSuggestionPanel({
  suggestions,
  module,
  topN = 5,
  minDelta = 1,
  className,
  emptyHint = 'Sem sinais relevantes nos últimos 30 dias.',
}: Props) {
  const filtered = React.useMemo(() => {
    return suggestions
      .filter((s) => (module ? s.module === module : true))
      .filter((s) => Math.abs(s.avg_delta) >= minDelta)
      .slice(0, topN);
  }, [suggestions, module, topN, minDelta]);

  return (
    <section
      className={cn('rounded-md border border-border bg-card p-3', className)}
      aria-label="AI suggestions baseline 30d"
    >
      <header className="mb-2 flex items-center justify-between gap-2">
        <div className="flex items-center gap-1.5 text-[12px] font-semibold text-foreground">
          <Sparkles size={13} className="text-primary" />
          AI baseline 30d
          <span className="rounded-md border border-border bg-muted px-1 py-0.5 text-[9px] font-medium uppercase tracking-wider text-muted-foreground">
            READ-ONLY
          </span>
        </div>
        <span className="text-[10px] text-muted-foreground">
          observacional · anti-Goodhart
        </span>
      </header>

      {filtered.length === 0 ? (
        <div className="rounded-md border border-dashed border-border bg-muted/40 px-2 py-3 text-center text-[11.5px] text-muted-foreground">
          {emptyHint}
        </div>
      ) : (
        <ul className="space-y-1.5">
          {filtered.map((s) => {
            const isUp = s.avg_delta > 0;
            const Arrow = isUp ? TrendingUp : TrendingDown;
            const toneCls = isUp
              ? 'text-emerald-700 dark:text-emerald-400'
              : 'text-destructive';
            return (
              <li
                key={`${s.module}-${s.last_at ?? 'n'}`}
                className="rounded-md border border-border bg-background px-2 py-1.5"
              >
                <div className="flex items-center justify-between gap-2">
                  <span className="text-[12px] font-medium text-foreground truncate">
                    {s.module}
                  </span>
                  <span className={cn('flex items-center gap-0.5 text-[11.5px] font-semibold tabular-nums', toneCls)}>
                    <Arrow size={11} />
                    {s.avg_delta > 0 ? '+' : ''}
                    {s.avg_delta.toFixed(1)}pts
                  </span>
                </div>
                <p
                  className="mt-0.5 line-clamp-2 text-[10.5px] text-muted-foreground"
                  title={s.last_justificativa}
                >
                  {s.last_justificativa || 'sem justificativa registrada'}
                </p>
                <div className="mt-0.5 flex items-center gap-2 text-[9.5px] text-muted-foreground">
                  <span>n={s.count}</span>
                  <span aria-hidden>·</span>
                  <span>conf={(s.last_confidence * 100).toFixed(0)}%</span>
                </div>
              </li>
            );
          })}
        </ul>
      )}
    </section>
  );
}
