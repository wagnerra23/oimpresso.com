import * as React from 'react';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/Components/ui/dialog';
import {
  CheckCircle2,
  Clock,
  Activity,
  AlertTriangle,
  Layers,
  Sparkles,
} from 'lucide-react';
import { cn } from '@/Lib/utils';
import type { HealthSnapshot } from './governanceV4Types';

/**
 * HealthPanelV4 — modal de saúde governance v4 (extends kb/HealthPanel pattern)
 *
 * Wave 29 (W29-C). 4 quadrantes:
 *  - ScorecardSnapshot cron last_run + status
 *  - AI baseline 30d countdown (window vs remaining)
 *  - OTel collector ping (CT 100)
 *  - Buckets com dados (cobertura YAML rubric)
 */
interface Props {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  snapshot: HealthSnapshot | null;
}

function fmtRelative(iso: string | null): string {
  if (!iso) return 'nunca';
  const d = new Date(iso).getTime();
  if (Number.isNaN(d)) return 'nunca';
  const diff = Math.round((Date.now() - d) / 60_000);
  if (diff < 1) return 'agora';
  if (diff < 60) return `${diff}min atrás`;
  if (diff < 1440) return `${Math.round(diff / 60)}h atrás`;
  return `${Math.round(diff / 1440)}d atrás`;
}

export default function HealthPanelV4({ open, onOpenChange, snapshot }: Props) {
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-2xl">
        <DialogHeader>
          <small className="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">
            Diagnóstico
          </small>
          <DialogTitle>Saúde Governance v4</DialogTitle>
          <DialogDescription>
            Cron ScorecardSnapshot, AI baseline 30d e OTel collector CT 100.
          </DialogDescription>
        </DialogHeader>

        {!snapshot ? (
          <div className="rounded-md border border-dashed border-border bg-muted/30 p-6 text-center text-[12px] text-muted-foreground">
            Snapshot pendente — backend W29-B precisa entregar prop{' '}
            <code>healthSnapshot</code>.
          </div>
        ) : (
          <div className="grid gap-3 sm:grid-cols-2">
            <HealthCard
              title="Scorecard cron"
              tone={snapshot.scorecard_cron_ok ? 'ok' : 'bad'}
              icon={
                snapshot.scorecard_cron_ok ? (
                  <CheckCircle2 size={14} />
                ) : (
                  <AlertTriangle size={14} />
                )
              }
              primary={
                snapshot.scorecard_cron_ok ? 'Operacional' : 'Falha last_run'
              }
              detail={`last run ${fmtRelative(snapshot.scorecard_last_run_at)}`}
            />
            <HealthCard
              title="AI baseline 30d"
              tone={snapshot.ai_baseline_remaining_days > 0 ? 'ok' : 'warn'}
              icon={<Sparkles size={14} />}
              primary={
                snapshot.ai_baseline_remaining_days > 0
                  ? `${snapshot.ai_baseline_remaining_days}d restantes`
                  : 'Concluído'
              }
              detail={`window ${snapshot.ai_baseline_window_days}d · observacional`}
            />
            <HealthCard
              title="OTel collector"
              tone={snapshot.otel_collector_up ? 'ok' : 'bad'}
              icon={<Activity size={14} />}
              primary={snapshot.otel_collector_up ? 'Up' : 'Down'}
              detail={`last ping ${fmtRelative(snapshot.otel_collector_last_ping_at)}`}
            />
            <HealthCard
              title="Cobertura buckets"
              tone={snapshot.buckets_with_data >= 4 ? 'ok' : 'warn'}
              icon={<Layers size={14} />}
              primary={`${snapshot.buckets_with_data}/4`}
              detail="buckets canon com dados"
            />
          </div>
        )}
      </DialogContent>
    </Dialog>
  );
}

function HealthCard({
  title,
  tone,
  icon,
  primary,
  detail,
}: {
  title: string;
  tone: 'ok' | 'warn' | 'bad';
  icon: React.ReactNode;
  primary: string;
  detail: string;
}) {
  const toneCls =
    tone === 'ok'
      ? 'border-emerald-300 bg-emerald-50 text-emerald-800 dark:border-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300'
      : tone === 'warn'
        ? 'border-amber-300 bg-amber-50 text-amber-800 dark:border-amber-700 dark:bg-amber-950/40 dark:text-amber-300'
        : 'border-destructive/40 bg-destructive/10 text-destructive';

  return (
    <div className={cn('rounded-md border p-3', toneCls)}>
      <div className="flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wider">
        {icon}
        <span>{title}</span>
      </div>
      <div className="mt-1 text-[14px] font-semibold text-foreground">{primary}</div>
      <div className="text-[10.5px] text-muted-foreground flex items-center gap-1">
        <Clock size={10} />
        {detail}
      </div>
    </div>
  );
}
