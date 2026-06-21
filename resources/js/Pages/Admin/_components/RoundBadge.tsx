import * as React from 'react';
import { cn } from '@/Lib/utils';
import { Check, X, RefreshCw, Clock } from 'lucide-react';

/**
 * RoundBadge — badge "Round N" com cor por status PDCA.
 *
 * Wave 30 Agent B (W30-B). Reutilizável em ScreenList + ReviewReader + GovernanceV4
 * sidebar accordion. Tokens semânticos Cockpit V2 (NÃO `bg-(red|green)-N` cru).
 */
export type ReviewStatus = 'pending-wagner' | 'approved' | 'rejected' | 'iterate';

interface Props {
  round: number;
  status: ReviewStatus;
  size?: 'sm' | 'md';
  showIcon?: boolean;
  className?: string;
}

const STATUS_META: Record<
  ReviewStatus,
  { label: string; cls: string; Icon: React.ComponentType<{ size?: number; className?: string }> }
> = {
  'pending-wagner': {
    label: 'Pendente Wagner',
    cls: 'border-border bg-muted text-muted-foreground',
    Icon: Clock,
  },
  approved: {
    label: 'Aprovada',
    cls: 'border-success/20 bg-success-soft text-success-fg',
    Icon: Check,
  },
  rejected: {
    label: 'Rejeitada',
    cls: 'border-destructive/40 bg-destructive/10 text-destructive',
    Icon: X,
  },
  iterate: {
    label: 'Iterar',
    cls: 'border-warning/20 bg-warning-soft text-warning-fg',
    Icon: RefreshCw,
  },
};

export default function RoundBadge({
  round,
  status,
  size = 'sm',
  showIcon = true,
  className,
}: Props) {
  const meta = STATUS_META[status];
  const Icon = meta.Icon;
  const sizing =
    size === 'sm'
      ? 'px-1.5 py-0.5 text-[10.5px] gap-0.5'
      : 'px-2 py-1 text-[12px] gap-1';
  const iconSize = size === 'sm' ? 10 : 12;

  return (
    <span
      className={cn(
        'inline-flex items-center rounded-md border font-medium tabular-nums',
        meta.cls,
        sizing,
        className,
      )}
      title={`Round ${round} · ${meta.label}`}
    >
      {showIcon && <Icon size={iconSize} aria-hidden />}
      <span>R{round}</span>
      <span className="opacity-70">·</span>
      <span>{meta.label}</span>
    </span>
  );
}

export { STATUS_META };
