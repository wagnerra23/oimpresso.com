// ServiceOrderStatusBadge — pílula visual do estado da OS
// Combina status (FSM ou string-livre V0) + orderType + flag overdue numa label semântica.
// Cores seguem Cockpit V2 (ADR 0110): emerald=ok, blue=em-andamento, amber=atenção, rose=erro.

import { cn } from '@/Lib/utils';

export interface ServiceOrderStatusBadgeProps {
  status: string;
  // ADR 0265: domínio = reparo — order_type ∈ {manutencao, mecanica}
  orderType?: 'manutencao' | 'mecanica' | null;
  isOverdue?: boolean;
  className?: string;
}

interface BadgeStyle {
  label: string;
  classes: string;
}

function resolveBadge({ status, isOverdue }: ServiceOrderStatusBadgeProps): BadgeStyle {
  // Atrasada tem prioridade visual máxima (qualquer tipo de reparo ativo).
  if (isOverdue && !['concluida', 'cancelada', 'entregue'].includes(status)) {
    return {
      label: 'Atrasada',
      classes: 'bg-destructive-soft text-destructive-fg border-destructive/20',
    };
  }

  // Estados terminais (independem do tipo).
  if (status === 'concluida') {
    return {
      label: 'Concluída',
      classes: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    };
  }
  if (status === 'cancelada') {
    return {
      label: 'Cancelada',
      classes: 'bg-muted text-muted-foreground border-border',
    };
  }
  if (status === 'entregue') {
    return {
      label: 'Entregue',
      classes: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    };
  }

  // Estados ativos — diferencia pelo status (FSM reparo).
  switch (status) {
    case 'aberta':
      return {
        label: 'Aberta',
        classes: 'bg-muted text-muted-foreground border-border',
      };
    case 'orcamento':
      return {
        label: 'Aguardando aprovação',
        classes: 'bg-amber-50 text-amber-700 border-amber-200',
      };
    case 'aprovada':
      return {
        label: 'Aprovada',
        classes: 'bg-emerald-50 text-emerald-700 border-emerald-200',
      };
    case 'em_servico':
      return {
        label: 'Em serviço',
        classes: 'bg-amber-50 text-amber-700 border-amber-200',
      };
    case 'em_producao':
      return {
        label: 'Em produção',
        classes: 'bg-amber-50 text-amber-700 border-amber-200',
      };
    default:
      return {
        label: status,
        classes: 'bg-muted text-muted-foreground border-border',
      };
  }
}

export default function ServiceOrderStatusBadge(props: ServiceOrderStatusBadgeProps) {
  const { label, classes } = resolveBadge(props);
  return (
    <span
      className={cn(
        'inline-block px-2 py-0.5 text-xs font-medium rounded border whitespace-nowrap',
        classes,
        props.className,
      )}
    >
      {label}
    </span>
  );
}
