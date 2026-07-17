import * as React from 'react';
import { Star } from 'lucide-react';
import { cn } from '@/Lib/utils';

/**
 * KbFavStar — botão estrela de favorito.
 *
 * Port do `kb-images-print.jsx::KBFavStar` (Cowork [CC]).
 * - filled quando active
 * - aria-pressed pra WCAG
 * - stop-propagation pra não disparar onClick da row pai
 */
interface Props {
  active: boolean;
  onClick: () => void;
  size?: number;
  className?: string;
  /** label pt-BR pra screen-reader / tooltip */
  labelOn?: string;
  labelOff?: string;
}

export default function KbFavStar({
  active,
  onClick,
  size = 14,
  className,
  labelOn = 'Remover dos favoritos (B)',
  labelOff = 'Adicionar aos favoritos (B)',
}: Props) {
  return (
    <button
      type="button"
      onClick={(e) => {
        e.stopPropagation();
        onClick();
      }}
      aria-pressed={active}
      title={active ? labelOn : labelOff}
      className={cn(
        'inline-flex h-7 w-7 items-center justify-center rounded-md transition-colors',
        'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',
        active
          ? 'text-warning hover:text-warning-fg'
          : 'text-muted-foreground hover:text-foreground hover:bg-muted',
        className,
      )}
    >
      <Star
        size={size}
        fill={active ? 'currentColor' : 'none'}
        strokeWidth={2}
      />
      <span className="sr-only">{active ? labelOn : labelOff}</span>
    </button>
  );
}
