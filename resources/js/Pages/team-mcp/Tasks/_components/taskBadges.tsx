// Forja PR-1 — componentes visuais da tela Tasks (lista + quadro + drawer).
// Tokens/helpers puros vivem em ./taskTokens (separação p/ react-refresh).
// DS v6: SEM cor crua. Status estilo Stripe (dot + texto), nunca bg-fill (PT-01).

import { Bot, User } from 'lucide-react';
import { cn } from '@/Lib/utils';
import { PRIO_DOT, PRIO_LABEL, statusMeta, type Priority } from './taskTokens';

export function PriorityDot({ priority, className }: { priority: Priority; className?: string }) {
  const p = PRIO_DOT[priority] ? priority : 'p2';
  return (
    <span
      aria-label={`Prioridade ${PRIO_LABEL[p]}`}
      title={PRIO_LABEL[p]}
      data-testid="prio-dot"
      className={cn('inline-block h-2 w-2 shrink-0 rounded-full', PRIO_DOT[p], className)}
    />
  );
}

/** Status Stripe-style: dot colorido + label neutro (sem bg-fill). Nome único (reuse-gate). */
export function TaskStatusPill({ status, className }: { status: string; className?: string }) {
  const m = statusMeta(status);
  return (
    <span
      data-testid="status-pill"
      className={cn('inline-flex items-center gap-1.5 text-xs text-muted-foreground', className)}
    >
      <span className={cn('inline-block h-1.5 w-1.5 shrink-0 rounded-full', m.dot)} />
      {m.label}
    </span>
  );
}

/** Selo de proveniência: agente (Bot, roxo) vs humano (User, neutro). Transversal §3. */
export function ActorSeal({ owner, agents, className }: { owner: string | null; agents: string[]; className?: string }) {
  if (!owner) return <span className={cn('text-xs text-muted-foreground/60', className)}>—</span>;
  const isAgent = agents.includes(owner.toLowerCase());
  const Icon = isAgent ? Bot : User;
  return (
    <span
      data-testid="actor-seal"
      data-actor={isAgent ? 'agent' : 'human'}
      title={isAgent ? `Agente: ${owner}` : `Humano: ${owner}`}
      className={cn('inline-flex items-center gap-1 text-xs text-muted-foreground', className)}
    >
      <Icon size={12} aria-hidden className={isAgent ? 'text-primary' : 'text-muted-foreground'} />
      <span className="truncate">{owner}</span>
    </span>
  );
}
