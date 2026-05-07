import { getInitials, pickColor } from './helpers';

interface Props {
  name: string;
  size?: 'sm' | 'md' | 'lg';
  ring?: boolean;
  className?: string;
}

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
        ring ? 'ring-2 ring-blue-400 ring-offset-1 ring-offset-background' : ''
      } ${className}`}
      aria-hidden
    >
      {initials}
    </div>
  );
}
