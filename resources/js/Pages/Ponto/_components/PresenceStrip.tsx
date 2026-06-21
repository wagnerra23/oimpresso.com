import * as React from 'react';
import { Link } from '@inertiajs/react';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/Components/ui/tooltip';
import { cn } from '@/Lib/utils';

/**
 * PresenceStrip — faixa de avatares com status ao vivo dos colaboradores.
 *
 * Status:
 *   presente  → verde (entrou, não saiu)
 *   saiu      → cinza suave (terminou o dia)
 *   atrasado  → âmbar (passou do horário sem entrar)
 *   ausente   → branco/vazio (nenhuma marcação)
 *
 * Cada avatar é clicável → navega para o espelho do colaborador.
 * Hover = tooltip com detalhes (matrícula, entrada, última marcação).
 *
 * Uso:
 *   <PresenceStrip colaboradores={presenca_agora} />
 *
 * Dados populados por DashboardController::calcularPresenca().
 */
type Status = 'presente' | 'saiu' | 'atrasado' | 'ausente';

interface Presenca {
  id: number;
  nome: string;
  matricula: string | null;
  iniciais: string;
  status: Status;
  entrada: string | null;
  saida: string | null;
  ultima: string | null;
  marcacoes: number;
}

interface Props {
  colaboradores: Presenca[];
  className?: string;
}

const statusConfig: Record<Status, { label: string; bgClass: string; ringClass: string; dotClass: string }> = {
  presente: {
    label: 'Presente',
    bgClass: 'bg-emerald-500 text-white',
    ringClass: 'ring-emerald-500/40',
    dotClass: 'bg-emerald-500',
  },
  saiu: {
    label: 'Saiu',
    bgClass: 'bg-muted text-muted-foreground',
    ringClass: 'ring-muted-foreground/20',
    dotClass: 'bg-muted-foreground/40',
  },
  atrasado: {
    label: 'Atrasado',
    bgClass: 'bg-amber-500 text-white',
    ringClass: 'ring-amber-500/40',
    dotClass: 'bg-amber-500',
  },
  ausente: {
    label: 'Ausente',
    bgClass: 'bg-background text-muted-foreground border border-dashed border-muted-foreground/30',
    ringClass: 'ring-transparent',
    dotClass: 'bg-muted-foreground/20',
  },
};

export default function PresenceStrip({ colaboradores, className }: Props) {
  if (!colaboradores || colaboradores.length === 0) {
    return null;
  }

  const presentes = colaboradores.filter((c) => c.status === 'presente').length;
  const atrasados = colaboradores.filter((c) => c.status === 'atrasado').length;
  const ausentes = colaboradores.filter((c) => c.status === 'ausente').length;
  const total = colaboradores.length;

  return (
    <TooltipProvider delayDuration={150}>
      <div
        data-slot="presence-strip"
        className={cn('rounded-xl border border-border bg-card p-4 space-y-3', className)}
      >
        {/* Header com resumo */}
        <div className="flex items-center justify-between gap-3">
          <div>
            <h3 className="text-sm font-semibold text-foreground">Presença ao vivo</h3>
            <p className="text-xs text-muted-foreground">
              {presentes} presente{presentes !== 1 ? 's' : ''} · {atrasados} atrasado{atrasados !== 1 ? 's' : ''} · {ausentes} ausente{ausentes !== 1 ? 's' : ''} · {total} total
            </p>
          </div>
          <div className="flex items-center gap-3 text-[10px] text-muted-foreground">
            {(['presente', 'atrasado', 'saiu', 'ausente'] as const).map((s) => (
              <span key={s} className="inline-flex items-center gap-1">
                <span className={cn('h-2 w-2 rounded-full', statusConfig[s].dotClass)} />
                {statusConfig[s].label}
              </span>
            ))}
          </div>
        </div>

        {/* Faixa de avatares */}
        <div className="flex flex-wrap gap-2">
          {colaboradores.map((c) => {
            const cfg = statusConfig[c.status];
            return (
              <Tooltip key={c.id}>
                <TooltipTrigger asChild>
                  <Link
                    href={`/ponto/espelho/${c.id}`}
                    className={cn(
                      'relative inline-flex items-center justify-center rounded-full h-10 w-10 text-xs font-semibold ring-2 transition-transform hover:scale-110 focus-visible:outline-none focus-visible:ring-offset-2 focus-visible:ring-ring',
                      cfg.bgClass,
                      cfg.ringClass,
                    )}
                    aria-label={`${c.nome} — ${cfg.label}`}
                  >
                    {c.iniciais}
                    <span
                      className={cn(
                        'absolute -bottom-0.5 -right-0.5 h-3 w-3 rounded-full border-2 border-card',
                        cfg.dotClass,
                      )}
                      aria-hidden
                    />
                  </Link>
                </TooltipTrigger>
                <TooltipContent side="bottom" className="text-xs">
                  <div className="font-semibold">{c.nome}</div>
                  {c.matricula && <div className="text-muted-foreground">mat. {c.matricula}</div>}
                  <div className="mt-1 pt-1 border-t border-border/50 space-y-0.5">
                    <div>
                      <span className="text-muted-foreground">Status:</span>{' '}
                      <span className="font-medium">{cfg.label}</span>
                    </div>
                    {c.entrada && (
                      <div>
                        <span className="text-muted-foreground">Entrada:</span>{' '}
                        <span className="font-mono">{c.entrada}</span>
                      </div>
                    )}
                    {c.saida && (
                      <div>
                        <span className="text-muted-foreground">Saída:</span>{' '}
                        <span className="font-mono">{c.saida}</span>
                      </div>
                    )}
                    {!c.saida && c.ultima && c.ultima !== c.entrada && (
                      <div>
                        <span className="text-muted-foreground">Última marcação:</span>{' '}
                        <span className="font-mono">{c.ultima}</span>
                      </div>
                    )}
                    <div>
                      <span className="text-muted-foreground">Marcações hoje:</span>{' '}
                      <span className="font-mono">{c.marcacoes}</span>
                    </div>
                  </div>
                </TooltipContent>
              </Tooltip>
            );
          })}
        </div>
      </div>
    </TooltipProvider>
  );
}
