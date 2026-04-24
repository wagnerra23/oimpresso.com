import * as React from 'react';
import { Icon } from '@/Components/Icon';
import { cn } from '@/Lib/utils';

/**
 * EmptyState — estado vazio reusável com icone, titulo, descricao e CTA.
 *
 * Para usar em containers de tabela/lista quando não há registros, ou
 * em áreas de busca sem resultado.
 *
 * Uso:
 *   <EmptyState
 *     icon="inbox"
 *     title="Nenhuma aprovação pendente"
 *     description="Todas as solicitações foram processadas. Bom trabalho!"
 *     action={<Button>Ver histórico</Button>}
 *   />
 *
 *   <EmptyState
 *     icon="search-x"
 *     title="Nenhum resultado"
 *     description="Tente ajustar os filtros ou buscar por outro termo."
 *     variant="search"
 *   />
 */
type Variant = 'default' | 'search' | 'error' | 'success';

interface Props {
  icon?: string;
  title: string;
  description?: string;
  action?: React.ReactNode;
  variant?: Variant;
  className?: string;
}

const variantStyles: Record<Variant, { icon: string; iconBg: string }> = {
  default: { icon: 'text-muted-foreground',           iconBg: 'bg-muted' },
  search:  { icon: 'text-blue-600 dark:text-blue-400', iconBg: 'bg-blue-500/10' },
  error:   { icon: 'text-destructive',                 iconBg: 'bg-destructive/10' },
  success: { icon: 'text-emerald-600 dark:text-emerald-400', iconBg: 'bg-emerald-500/10' },
};

export default function EmptyState({
  icon = 'inbox',
  title,
  description,
  action,
  variant = 'default',
  className,
}: Props) {
  const s = variantStyles[variant];
  return (
    <div
      data-slot="empty-state"
      data-variant={variant}
      className={cn(
        'flex flex-col items-center justify-center text-center py-12 px-6 gap-3',
        className,
      )}
    >
      <div className={cn('flex h-14 w-14 items-center justify-center rounded-full', s.iconBg)}>
        <Icon name={icon} size={26} className={s.icon} />
      </div>
      <div className="space-y-1 max-w-sm">
        <h3 className="text-base font-semibold text-foreground">{title}</h3>
        {description && (
          <p className="text-sm text-muted-foreground leading-relaxed">{description}</p>
        )}
      </div>
      {action && <div className="mt-2">{action}</div>}
    </div>
  );
}
