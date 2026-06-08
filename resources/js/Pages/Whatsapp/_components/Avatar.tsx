import { getInitials, pickColor } from './helpers';

interface Props {
  name: string;
  size?: 'sm' | 'md' | 'lg';
  ring?: boolean;
  className?: string;
}

/**
 * Avatar de pessoa com inicial colorida (hash determinístico do nome).
 *
 * Diferente de origin-badges (R-DS-011 — OS/CRM/FIN/PNT/MFG) que sinalizam
 * MÓDULO de origem. Aqui é avatar de cliente humano (paleta hash de 10 cores).
 */
export default function Avatar({ name, size = 'md', ring = false, className = '' }: Props) {
  const initials = getInitials(name);
  const bg = pickColor(name);
  const dim =
    size === 'lg' ? 'w-16 h-16 text-xl' :
    size === 'sm' ? 'w-8 h-8 text-xs' :
    'w-10 h-10 text-sm';
  return (
    <div
      className={`${dim} ${bg} rounded-full flex items-center justify-center text-white font-semibold shrink-0 select-none ${
        ring ? 'ring-2 ring-primary ring-offset-2 ring-offset-background' : ''
      } ${className}`}
      aria-hidden
    >
      {initials}
    </div>
  );
}
