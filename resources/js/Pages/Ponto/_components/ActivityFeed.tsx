import * as React from 'react';
import { Link } from '@inertiajs/react';
import { LogIn, LogOut, Coffee, Clock } from 'lucide-react';
import { cn } from '@/Lib/utils';

/**
 * ActivityFeed — timeline vertical das marcações do dia.
 *
 * Formato cronológico inverso (mais recente no topo). Cada entrada:
 *   [icon tipo] colaborador  TIPO     H:M   (há 5min) · REP-P
 *
 * Uso:
 *   <ActivityFeed marcacoes={atividade_recente} />
 *
 * Dados vem do DashboardController (últimas 20 marcações de hoje).
 */
interface Marcacao {
  id: number;
  tipo: string;
  momento: string | null; // H:i
  momento_completo?: string | null;
  origem: string;
  tempo?: string | null; // "5min" "2h" diffForHumans short
  colaborador: {
    id: number | null;
    nome: string;
    matricula: string | null;
  };
  rep: {
    identificador: string | null;
    tipo: string | null;
  };
}

interface Props {
  marcacoes: Marcacao[];
  title?: string;
  emptyLabel?: string;
  className?: string;
}

const tipoConfig: Record<
  string,
  { label: string; Icon: React.ComponentType<{ size?: number; className?: string }>; colorClass: string; bgClass: string }
> = {
  ENTRADA: {
    label: 'Entrada',
    Icon: LogIn,
    colorClass: 'text-emerald-600 dark:text-emerald-400',
    bgClass: 'bg-emerald-500/10',
  },
  SAIDA: {
    label: 'Saída',
    Icon: LogOut,
    colorClass: 'text-muted-foreground',
    bgClass: 'bg-muted',
  },
  INTERVALO_INICIO: {
    label: 'Intervalo',
    Icon: Coffee,
    colorClass: 'text-amber-600 dark:text-amber-400',
    bgClass: 'bg-amber-500/10',
  },
  INTERVALO_FIM: {
    label: 'Retorno',
    Icon: Coffee,
    colorClass: 'text-emerald-600 dark:text-emerald-400',
    bgClass: 'bg-emerald-500/10',
  },
};

export default function ActivityFeed({ marcacoes, title = 'Atividade de hoje', emptyLabel = 'Nenhuma marcação ainda hoje.', className }: Props) {
  return (
    <div
      data-slot="activity-feed"
      className={cn('rounded-xl border border-border bg-card p-4', className)}
    >
      <div className="flex items-center justify-between mb-3">
        <h3 className="text-sm font-semibold text-foreground">{title}</h3>
        {marcacoes.length > 0 && (
          <span className="text-[10px] uppercase tracking-wider text-muted-foreground">
            {marcacoes.length} evento{marcacoes.length !== 1 ? 's' : ''}
          </span>
        )}
      </div>

      {marcacoes.length === 0 ? (
        <div className="flex items-center justify-center py-8 text-sm text-muted-foreground gap-2">
          <Clock size={14} />
          {emptyLabel}
        </div>
      ) : (
        <ol className="relative space-y-3">
          {/* Linha vertical conectando os pontos */}
          <div className="absolute left-[18px] top-3 bottom-3 w-px bg-border" aria-hidden />

          {marcacoes.map((m) => {
            const cfg = tipoConfig[m.tipo] ?? {
              label: m.tipo.replace(/_/g, ' ').toLowerCase(),
              Icon: Clock,
              colorClass: 'text-muted-foreground',
              bgClass: 'bg-muted',
            };
            const Icon = cfg.Icon;
            return (
              <li key={m.id} className="relative flex items-start gap-3 pl-0">
                <div
                  className={cn(
                    'relative z-10 flex h-9 w-9 shrink-0 items-center justify-center rounded-full ring-4 ring-card',
                    cfg.bgClass,
                  )}
                >
                  <Icon size={14} className={cfg.colorClass} />
                </div>
                <div className="flex-1 min-w-0 pt-1.5">
                  <div className="flex items-baseline justify-between gap-2">
                    <span className="text-sm font-medium text-foreground truncate">
                      {m.colaborador.nome}
                    </span>
                    <span className="text-xs font-mono tabular-nums text-muted-foreground shrink-0">
                      {m.momento ?? '—'}
                    </span>
                  </div>
                  <div className="flex items-baseline justify-between gap-2 text-xs text-muted-foreground">
                    <span className="truncate">
                      <span className={cfg.colorClass}>{cfg.label}</span>
                      {m.rep.identificador && (
                        <span className="ml-1.5 opacity-70">· {m.rep.identificador}</span>
                      )}
                    </span>
                    {m.tempo && <span className="shrink-0">há {m.tempo}</span>}
                  </div>
                </div>
              </li>
            );
          })}
        </ol>
      )}
    </div>
  );
}
