// ServiceOrderStatusBadge — pílula visual do estado da OS
// Combina status (FSM ou string-livre V0) + orderType + flag overdue numa label semântica.
// Cores seguem Cockpit V2 (ADR 0110): emerald=ok, blue=em-andamento, amber=atenção, rose=erro.

import { cn } from '@/Lib/utils';

export interface ServiceOrderStatusBadgeProps {
  status: string;
  orderType?: 'locacao' | 'manutencao' | null;
  isOverdue?: boolean;
  className?: string;
}

interface BadgeStyle {
  label: string;
  classes: string;
}

function resolveBadge({ status, orderType, isOverdue }: ServiceOrderStatusBadgeProps): BadgeStyle {
  // Atrasada tem prioridade visual máxima.
  if (isOverdue && orderType === 'locacao' && !['concluida', 'cancelada'].includes(status)) {
    return {
      label: 'Atrasada · cobrar',
      classes: 'bg-rose-100 text-rose-700 border-rose-300',
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
      classes: 'bg-slate-100 text-slate-600 border-slate-200',
    };
  }
  if (status === 'entregue') {
    return {
      label: 'Entregue',
      classes: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    };
  }

  // Estados ativos — combina com tipo.
  if (orderType === 'locacao') {
    return {
      label: 'Em locação',
      classes: 'bg-blue-50 text-blue-700 border-blue-200',
    };
  }

  // Manutenção ou tipo desconhecido — diferencia pelo status.
  switch (status) {
    case 'aberta':
      return {
        label: 'Aberta',
        classes: 'bg-slate-50 text-slate-700 border-slate-200',
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
        classes: 'bg-slate-100 text-slate-700 border-slate-200',
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
