import * as React from 'react';
import { Icon } from '@/Components/Icon';
import { cn } from '@/Lib/utils';

/**
 * PageHeader — cabeçalho padronizado de tela operacional.
 *
 * Layout:
 *   [icon] Titulo grande                    [slot action]
 *          Descricao opcional abaixo
 *
 * Wagner 2026-05-17: navegação intra-módulo vive dentro do `action` slot
 * (preferiu botões action sobre chips numerados). Use `PageHeaderActions`
 * pra agrupar items com overflow popup automático quando faltar espaço.
 *
 * Uso:
 *   <PageHeader
 *     icon="calendar-clock"
 *     title="Aprovações pendentes"
 *     description="12 solicitações aguardando revisão"
 *     action={<PageHeaderActions items={[
 *       { label: 'Dashboard', href: '/x/dashboard', icon: 'layout-dashboard' },
 *       { label: 'Triagem', href: '/x', count: 12, active: true },
 *       { label: 'Reload', onClick: () => router.reload(), variant: 'ghost' },
 *     ]} />}
 *   />
 *
 * Regras Design System: R-DS-001 (primitivas), R-DS-002 (tokens), R-DS-003 (lucide).
 */
interface Props {
  title: string;
  description?: string;
  icon?: string;
  action?: React.ReactNode;
  className?: string;
}

export default function PageHeader({ title, description, icon, action, className }: Props) {
  return (
    <div
      data-slot="page-header"
      className={cn(
        'flex flex-col gap-3 pb-4 border-b border-border md:flex-row md:items-center md:justify-between',
        className,
      )}
    >
      <div className="flex items-start gap-3 min-w-0">
        {icon && (
          <div
            aria-hidden
            className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
          >
            <Icon name={icon} size={20} />
          </div>
        )}
        <div className="min-w-0">
          {/* ADR 0110 §Tipografia canon: h1 = text-2xl font-semibold tracking-tight (mobile escala pra text-xl). */}
          <h1 className="text-xl md:text-2xl font-semibold tracking-tight text-foreground leading-tight truncate">
            {title}
          </h1>
          {description && (
            // ADR 0110 §Tipografia canon: subtitle = text-sm text-muted-foreground leading-relaxed.
            <p className="mt-1 text-sm text-muted-foreground leading-relaxed max-w-2xl">{description}</p>
          )}
        </div>
      </div>
      {action && <div data-slot="page-header-action" className="shrink-0">{action}</div>}
    </div>
  );
}
