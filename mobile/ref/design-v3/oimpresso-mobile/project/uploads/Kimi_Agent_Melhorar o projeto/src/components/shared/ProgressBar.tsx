import { cn } from '@/lib/utils';

interface ProgressBarProps {
  progress: number;
  className?: string;
  color?: string;
  size?: 'sm' | 'md';
}

export function ProgressBar({ progress, className, color, size = 'md' }: ProgressBarProps) {
  return (
    <div className={cn('w-full bg-[var(--progress-track)] rounded-full overflow-hidden', size === 'sm' ? 'h-1.5' : 'h-2', className)}>
      <div
        className="h-full rounded-full transition-all duration-600 ease-out"
        style={{ width: `${Math.min(100, Math.max(0, progress))}%`, backgroundColor: color || 'var(--primary)' }}
      />
    </div>
  );
}
