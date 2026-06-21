import * as React from 'react';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/Components/ui/tooltip';
import { cn, formatMinutes } from '@/Lib/utils';

/**
 * MonthHeatmap — calendário denso estilo GitHub mostrando o mês de um colaborador.
 *
 * Layout: grid 7 colunas (seg a dom), N semanas. Cada célula é um dia com:
 *   - cor de fundo por saldo (falta=danger, atraso=warning, HE=success fraca/forte, normal=muted)
 *   - borda âmbar quando tem divergência
 *   - tooltip com detalhes do dia
 *
 * Uso:
 *   <MonthHeatmap mes="2026-04" linhas={linhas} onDayClick={(linha) => scrollToRow(linha.data)} />
 *
 * Entrada:
 *   - mes: 'YYYY-MM' (usado pra calcular o primeiro dia + dias vazios antes)
 *   - linhas: array vindo do backend (Ponto/Espelho/Show.tsx — Linha)
 *   - onDayClick (opcional): callback quando usuário clica numa célula
 */
interface Linha {
  data: string;       // YYYY-MM-DD
  dow: string;        // dom/seg/ter/...
  dia: number;
  is_weekend: boolean;
  trabalhado: number;
  atraso: number;
  falta: number;
  he: number;
  divergencia: boolean;
  marcacoes: Array<{ hora: string; tipo: string; origem: string }>;
}

interface Props {
  mes: string; // YYYY-MM
  linhas: Linha[];
  onDayClick?: (linha: Linha) => void;
  className?: string;
}

type DayState = 'falta' | 'atraso' | 'he_alta' | 'he_media' | 'normal' | 'weekend' | 'vazio';

function classifyDay(l: Linha): DayState {
  if (l.falta > 0) return 'falta';
  if (l.atraso > 60) return 'atraso';
  if (l.he >= 120) return 'he_alta';
  if (l.he > 0) return 'he_media';
  if (l.trabalhado > 0) return 'normal';
  if (l.is_weekend) return 'weekend';
  return 'vazio';
}

const stateStyles: Record<DayState, string> = {
  falta:     'bg-destructive/80 text-destructive-foreground hover:bg-destructive',
  atraso:    'bg-amber-500/70 text-white hover:bg-amber-500',
  he_alta:   'bg-emerald-600 text-white hover:bg-emerald-500',
  he_media:  'bg-emerald-400/60 text-emerald-950 hover:bg-emerald-400 dark:text-emerald-50',
  normal:    'bg-muted-foreground/20 text-foreground hover:bg-muted-foreground/30',
  weekend:   'bg-muted/50 text-muted-foreground/60',
  vazio:     'bg-background text-muted-foreground/40 border border-dashed border-border',
};

const stateLabels: Record<DayState, string> = {
  falta:    'Falta',
  atraso:   'Atraso',
  he_alta:  'HE alta (>2h)',
  he_media: 'HE',
  normal:   'Normal',
  weekend:  'Fim de semana',
  vazio:    'Sem registro',
};

export default function MonthHeatmap({ mes, linhas, onDayClick, className }: Props) {
  // Índice rápido por dia do mês
  const byDay = React.useMemo(() => {
    const map = new Map<number, Linha>();
    linhas.forEach((l) => map.set(l.dia, l));
    return map;
  }, [linhas]);

  // Parse YYYY-MM
  const [year, month] = mes.split('-').map(Number);
  const firstDay = new Date(year, month - 1, 1);
  const lastDay = new Date(year, month, 0);

  // Dia da semana do primeiro dia (0=dom, 1=seg, ..., 6=sab)
  // Ajustar pra começar na segunda (0=seg, ..., 6=dom)
  const firstDow = (firstDay.getDay() + 6) % 7;
  const daysInMonth = lastDay.getDate();

  // Gerar células: padding inicial + dias + padding final pra fechar grid
  type Cell = { dia: number | null; linha: Linha | null; state: DayState };
  const cells: Cell[] = [];

  // Padding antes
  for (let i = 0; i < firstDow; i++) {
    cells.push({ dia: null, linha: null, state: 'vazio' });
  }

  // Dias do mês
  for (let d = 1; d <= daysInMonth; d++) {
    const linha = byDay.get(d) ?? null;
    const state = linha ? classifyDay(linha) : 'vazio';
    cells.push({ dia: d, linha, state });
  }

  // Padding depois pra completar última semana
  const trailing = (7 - (cells.length % 7)) % 7;
  for (let i = 0; i < trailing; i++) {
    cells.push({ dia: null, linha: null, state: 'vazio' });
  }

  // Contadores pra rodapé
  const counts = linhas.reduce(
    (acc, l) => {
      const s = classifyDay(l);
      acc[s] = (acc[s] ?? 0) + 1;
      return acc;
    },
    {} as Record<DayState, number>,
  );

  const mesLabel = firstDay.toLocaleDateString('pt-BR', { month: 'long', year: 'numeric' });

  return (
    <TooltipProvider delayDuration={150}>
      <div
        data-slot="month-heatmap"
        className={cn('rounded-xl border border-border bg-card p-4 space-y-3', className)}
      >
        <div className="flex items-center justify-between gap-2">
          <div>
            <h3 className="text-sm font-semibold text-foreground capitalize">{mesLabel}</h3>
            <p className="text-xs text-muted-foreground">
              Calor do mês por dia. Clique num dia para ir na linha da tabela.
            </p>
          </div>
        </div>

        {/* Cabeçalho dias da semana */}
        <div className="grid grid-cols-7 gap-1 text-center">
          {['S', 'T', 'Q', 'Q', 'S', 'S', 'D'].map((d, i) => (
            <div
              key={i}
              className="text-[10px] font-medium uppercase tracking-wider text-muted-foreground pb-1"
            >
              {d}
            </div>
          ))}
        </div>

        {/* Grid de dias */}
        <div className="grid grid-cols-7 gap-1">
          {cells.map((cell, i) => {
            if (cell.dia === null) {
              return <div key={i} aria-hidden className="aspect-square" />;
            }
            const { linha, state } = cell;
            const cellClasses = cn(
              'aspect-square flex flex-col items-center justify-center rounded-md text-xs font-mono transition-transform',
              stateStyles[state],
              linha?.divergencia && 'ring-2 ring-amber-500 ring-offset-1 ring-offset-card',
              onDayClick && linha && 'cursor-pointer hover:scale-110',
            );

            const content = (
              <>
                <span className="text-[11px] leading-none font-semibold">{cell.dia}</span>
                {linha && state !== 'vazio' && state !== 'weekend' && (
                  <span className="text-[8px] leading-none opacity-80 mt-0.5 font-normal">
                    {state === 'he_alta' || state === 'he_media'
                      ? `+${formatMinutes(linha.he)}`
                      : state === 'falta'
                        ? `-${formatMinutes(linha.falta)}`
                        : state === 'atraso'
                          ? formatMinutes(linha.atraso)
                          : formatMinutes(linha.trabalhado)}
                  </span>
                )}
              </>
            );

            if (!linha) {
              return (
                <div key={i} className={cellClasses}>
                  {content}
                </div>
              );
            }

            return (
              <Tooltip key={i}>
                <TooltipTrigger asChild>
                  <button
                    type="button"
                    onClick={onDayClick ? () => onDayClick(linha) : undefined}
                    disabled={!onDayClick}
                    className={cellClasses}
                    aria-label={`Dia ${cell.dia}: ${stateLabels[state]}`}
                  >
                    {content}
                  </button>
                </TooltipTrigger>
                <TooltipContent side="top" className="text-xs">
                  <div className="font-semibold">
                    {String(linha.dia).padStart(2, '0')} · {linha.dow}
                    {linha.divergencia && (
                      <span className="ml-1 text-amber-500">· divergência</span>
                    )}
                  </div>
                  <div className="mt-1 space-y-0.5 min-w-[180px]">
                    <Row label="Trabalhado" value={formatMinutes(linha.trabalhado)} />
                    {linha.atraso > 0 && <Row label="Atraso" value={formatMinutes(linha.atraso)} amber />}
                    {linha.falta > 0 && <Row label="Falta" value={formatMinutes(linha.falta)} danger />}
                    {linha.he > 0 && <Row label="HE" value={formatMinutes(linha.he)} emerald />}
                    <Row label="Marcações" value={String(linha.marcacoes.length)} />
                  </div>
                </TooltipContent>
              </Tooltip>
            );
          })}
        </div>

        {/* Legenda + contadores */}
        <div className="flex flex-wrap items-center justify-between gap-x-3 gap-y-1 pt-2 border-t border-border text-[10px] text-muted-foreground">
          <div className="flex flex-wrap gap-x-2.5 gap-y-1">
            {(['normal', 'he_media', 'he_alta', 'atraso', 'falta', 'weekend'] as DayState[]).map((s) => (
              <span key={s} className="inline-flex items-center gap-1">
                <span className={cn('h-2.5 w-2.5 rounded-sm', stateStyles[s])} />
                {stateLabels[s]}
                {counts[s] ? <span className="font-semibold tabular-nums">· {counts[s]}</span> : null}
              </span>
            ))}
          </div>
        </div>
      </div>
    </TooltipProvider>
  );
}

function Row({
  label,
  value,
  amber,
  danger,
  emerald,
}: {
  label: string;
  value: string;
  amber?: boolean;
  danger?: boolean;
  emerald?: boolean;
}) {
  return (
    <div className="flex items-center justify-between gap-3">
      <span className="text-muted-foreground">{label}:</span>
      <span
        className={cn(
          'font-mono tabular-nums font-medium',
          amber && 'text-amber-500',
          danger && 'text-destructive',
          emerald && 'text-emerald-500',
        )}
      >
        {value}
      </span>
    </div>
  );
}
