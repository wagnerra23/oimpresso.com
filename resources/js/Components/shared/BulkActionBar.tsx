import * as React from 'react';
import { X } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { cn } from '@/Lib/utils';

/**
 * BulkActionBar — barra de ações em lote, aparece fixa no bottom da viewport
 * quando selectedCount > 0. Estilo Gmail/Linear/Notion.
 *
 * Uso:
 *   <BulkActionBar
 *     selectedCount={selectedIds.length}
 *     onClear={() => setSelectedIds([])}
 *   >
 *     <Button variant="default" onClick={approveBatch}>
 *       Aprovar selecionadas
 *     </Button>
 *     <Button variant="destructive" onClick={rejectBatch}>
 *       Rejeitar
 *     </Button>
 *   </BulkActionBar>
 *
 * Acessibilidade: aparece com `role="region"` + aria-label, announce
 * "N item(s) selecionado(s)" quando muda.
 */
interface Props {
  selectedCount: number;
  onClear: () => void;
  children: React.ReactNode;
  className?: string;
  /** Sticky position. Default: bottom do main container. */
  position?: 'bottom' | 'top';
  /** Texto custom do label. Default: "N item(s) selecionado(s)". */
  label?: string;
}

export default function BulkActionBar({
  selectedCount,
  onClear,
  children,
  className,
  position = 'bottom',
  label,
}: Props) {
  if (selectedCount === 0) return null;

  const defaultLabel = selectedCount === 1 ? '1 item selecionado' : `${selectedCount} itens selecionados`;

  return (
    <div
      data-slot="bulk-action-bar"
      role="region"
      aria-label="Ações em lote"
      className={cn(
        'fixed left-1/2 -translate-x-1/2 z-50 flex items-center gap-3 rounded-full border border-border bg-card/95 backdrop-blur px-4 py-2 shadow-lg',
        'animate-in fade-in slide-in-from-bottom-2 duration-200',
        position === 'bottom' ? 'bottom-6' : 'top-6',
        className,
      )}
    >
      <Button
        type="button"
        variant="ghost"
        size="icon"
        onClick={onClear}
        aria-label="Limpar seleção"
        className="h-7 w-7 rounded-full"
      >
        <X size={14} />
      </Button>
      <span aria-live="polite" className="text-sm font-medium text-foreground tabular-nums">
        {label ?? defaultLabel}
      </span>
      <div className="h-5 w-px bg-border" aria-hidden />
      <div className="flex items-center gap-2">{children}</div>
    </div>
  );
}
