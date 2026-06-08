import * as React from 'react';
import { Link } from '@inertiajs/react';
import { AlertTriangle, Clock, UserX, Bell, CheckCircle2, ArrowRight } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { cn } from '@/Lib/utils';

/**
 * AlertInbox — "O que precisa da sua atenção".
 *
 * Agrupa anomalias do dia: atrasos, aprovações paradas, faltas.
 * Cada alerta tem ação inline pra não precisar navegar várias telas.
 *
 * Severidade determina cor do ícone:
 *   info     → azul
 *   warning  → âmbar
 *   danger   → vermelho
 *
 * Dados vem do DashboardController::coletarAlertas().
 */
type Severidade = 'info' | 'warning' | 'danger';

interface Alerta {
  tipo: string;
  titulo: string;
  subtitulo: string;
  acao_label: string;
  acao_href: string;
  severidade: Severidade;
}

interface Props {
  alertas: Alerta[];
  title?: string;
  className?: string;
}

const tipoIconMap: Record<string, React.ComponentType<{ size?: number; className?: string }>> = {
  atraso: Clock,
  aprovacao_parada: Bell,
  falta: UserX,
};

const severidadeStyles: Record<Severidade, { iconClass: string; bgClass: string; borderClass: string }> = {
  info: {
    iconClass: 'text-blue-600 dark:text-blue-400',
    bgClass: 'bg-blue-500/10',
    borderClass: 'border-blue-500/20',
  },
  warning: {
    iconClass: 'text-amber-600 dark:text-amber-400',
    bgClass: 'bg-amber-500/10',
    borderClass: 'border-amber-500/20',
  },
  danger: {
    iconClass: 'text-destructive',
    bgClass: 'bg-destructive/10',
    borderClass: 'border-destructive/20',
  },
};

export default function AlertInbox({ alertas, title = 'O que precisa da sua atenção', className }: Props) {
  const total = alertas.length;

  return (
    <div
      data-slot="alert-inbox"
      className={cn('rounded-xl border border-border bg-card p-4', className)}
    >
      <div className="flex items-center justify-between mb-3">
        <div className="flex items-center gap-2">
          <AlertTriangle size={14} className="text-muted-foreground" />
          <h3 className="text-sm font-semibold text-foreground">{title}</h3>
        </div>
        {total > 0 && (
          <span className="inline-flex items-center justify-center rounded-full bg-destructive px-2 py-0.5 text-[10px] font-bold text-destructive-foreground">
            {total}
          </span>
        )}
      </div>

      {total === 0 ? (
        <div className="flex flex-col items-center justify-center py-8 text-center gap-2">
          <div className="flex h-10 w-10 items-center justify-center rounded-full bg-emerald-500/10">
            <CheckCircle2 size={18} className="text-emerald-600 dark:text-emerald-400" />
          </div>
          <p className="text-sm font-medium text-foreground">Tudo em dia</p>
          <p className="text-xs text-muted-foreground">Nada pendente no momento.</p>
        </div>
      ) : (
        <ul className="space-y-2">
          {alertas.map((a, i) => {
            const Icon = tipoIconMap[a.tipo] ?? AlertTriangle;
            const s = severidadeStyles[a.severidade];
            return (
              <li
                key={`${a.tipo}-${i}`}
                className={cn(
                  'flex items-center gap-3 rounded-lg border p-2.5 transition-colors hover:bg-accent/30',
                  s.borderClass,
                )}
              >
                <div className={cn('flex h-8 w-8 shrink-0 items-center justify-center rounded-lg', s.bgClass)}>
                  <Icon size={14} className={s.iconClass} />
                </div>
                <div className="flex-1 min-w-0">
                  <p className="text-sm font-medium text-foreground truncate">{a.titulo}</p>
                  <p className="text-xs text-muted-foreground truncate">{a.subtitulo}</p>
                </div>
                <Button asChild variant="outline" size="sm" className="shrink-0 h-7 text-xs">
                  <Link href={a.acao_href} className="gap-1">
                    {a.acao_label} <ArrowRight size={10} />
                  </Link>
                </Button>
              </li>
            );
          })}
        </ul>
      )}
    </div>
  );
}
