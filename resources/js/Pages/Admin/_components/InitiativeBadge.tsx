import * as React from 'react';
import { cn } from '@/Lib/utils';
import { Clock, AlertTriangle, CheckCircle2, CircleDot } from 'lucide-react';
import { INITIATIVE_TONE, type Initiative } from './governanceV4Types';

/**
 * InitiativeBadge — deadline countdown + status badge
 *
 * Wave 29 (W29-C). Renderiza estado da Initiative Cortex-style:
 *  - status badge (open/in_progress/done/overdue)
 *  - countdown relativo até deadline (em D-N dias)
 *  - se vencida (overdue OR deadline < now), aplica tom destructive forte
 *
 * Tokens canon Cockpit V2.
 */
interface Props {
  initiative: Initiative;
  className?: string;
  compact?: boolean;
}

function formatRelative(deadlineIso: string | null): { label: string; overdue: boolean } {
  if (!deadlineIso) return { label: 'sem prazo', overdue: false };
  const now = Date.now();
  const d = new Date(deadlineIso).getTime();
  if (Number.isNaN(d)) return { label: 'sem prazo', overdue: false };

  const diffMs = d - now;
  const diffDays = Math.round(diffMs / 86_400_000);
  if (diffDays < 0) {
    const abs = Math.abs(diffDays);
    return { label: `vencida há ${abs}d`, overdue: true };
  }
  if (diffDays === 0) return { label: 'vence hoje', overdue: false };
  if (diffDays === 1) return { label: 'vence amanhã', overdue: false };
  if (diffDays < 7) return { label: `vence em ${diffDays}d`, overdue: false };
  if (diffDays < 30) return { label: `${Math.round(diffDays / 7)}sem`, overdue: false };
  return { label: `${Math.round(diffDays / 30)}meses`, overdue: false };
}

export default function InitiativeBadge({ initiative, className, compact }: Props) {
  const tone = INITIATIVE_TONE[initiative.status];
  const { label: deadlineLabel, overdue } = formatRelative(initiative.deadline_at);
  const effectiveStatus = initiative.status === 'done' ? 'done' : overdue ? 'overdue' : initiative.status;

  const StatusIcon =
    effectiveStatus === 'done'
      ? CheckCircle2
      : effectiveStatus === 'overdue'
        ? AlertTriangle
        : effectiveStatus === 'in_progress'
          ? CircleDot
          : Clock;

  const toneCls = (() => {
    switch (effectiveStatus) {
      case 'done':
        return 'border-success/20 bg-success-soft text-success-fg';
      case 'overdue':
        return 'border-destructive/40 bg-destructive/10 text-destructive';
      case 'in_progress':
        return 'border-sky-300 bg-sky-50 text-sky-800 dark:border-sky-700 dark:bg-sky-950/40 dark:text-sky-300';
      default:
        return 'border-border bg-muted text-foreground';
    }
  })();

  if (compact) {
    return (
      <span
        className={cn(
          'inline-flex items-center gap-1 rounded-md border px-1.5 py-0.5 text-[10px] font-medium',
          toneCls,
          className,
        )}
        title={initiative.title}
      >
        <StatusIcon size={10} />
        {deadlineLabel}
      </span>
    );
  }

  return (
    <div
      className={cn(
        'flex items-start gap-2 rounded-md border p-2',
        toneCls,
        className,
      )}
    >
      <StatusIcon size={14} className="mt-0.5 shrink-0" />
      <div className="min-w-0 flex-1">
        <div className="text-[12px] font-medium text-foreground truncate" title={initiative.title}>
          {initiative.title}
        </div>
        <div className="mt-0.5 flex items-center gap-2 text-[10.5px] text-muted-foreground">
          <span>{tone.label}</span>
          <span aria-hidden>·</span>
          <span>{deadlineLabel}</span>
          {initiative.owner && (
            <>
              <span aria-hidden>·</span>
              <span title={`Owner: ${initiative.owner}`}>@{initiative.owner}</span>
            </>
          )}
        </div>
      </div>
    </div>
  );
}
