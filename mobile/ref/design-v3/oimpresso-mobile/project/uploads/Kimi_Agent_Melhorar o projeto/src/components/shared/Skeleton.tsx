import { cn } from '@/lib/utils';

interface SkeletonProps {
  className?: string;
  width?: string | number;
  height?: string | number;
  circle?: boolean;
}

export function Skeleton({ className, width, height, circle }: SkeletonProps) {
  return (
    <div
      className={cn(
        'relative overflow-hidden bg-[var(--skeleton-base)] rounded-lg',
        circle && 'rounded-full',
        className
      )}
      style={{ width, height }}
    >
      <div
        className="absolute inset-0"
        style={{
          background: 'linear-gradient(90deg, transparent, var(--skeleton-highlight), transparent)',
          animation: 'shimmer 1.5s ease infinite',
        }}
      />
    </div>
  );
}

export function SkeletonCard() {
  return (
    <div className="bg-[var(--bg-surface)] border border-[var(--border-color)] rounded-[var(--card-radius)] p-[var(--card-padding)] space-y-3">
      <div className="flex items-center gap-3">
        <Skeleton circle width={44} height={44} />
        <div className="flex-1 space-y-2">
          <Skeleton height={16} className="rounded-md w-3/4" />
          <Skeleton height={12} className="rounded-md w-1/2" />
        </div>
      </div>
      <Skeleton height={12} className="rounded-md w-full" />
      <Skeleton height={12} className="rounded-md w-2/3" />
    </div>
  );
}
