import { cva, type VariantProps } from 'class-variance-authority';

const badge = cva('inline-flex items-center font-semibold rounded-md text-[11px] px-2 py-0.5', {
  variants: {
    variant: {
      urgent: 'bg-[#FF453A] text-white',
      high: 'bg-[#FF9F0A] text-black',
      medium: 'bg-[#5E5CE6] text-white',
      low: 'bg-[#30D158] text-black',
      pending: 'bg-[#FF9F0A]/20 text-[#FF9F0A]',
      approved: 'bg-[#0A84FF]/20 text-[#0A84FF]',
      completed: 'bg-[#30D158]/20 text-[#30D158]',
      cancelled: 'bg-[#FF453A]/20 text-[#FF453A]',
      neutral: 'bg-[#2c2c2e] text-[#8E8E93]',
    },
    shape: {
      rounded: 'rounded-md',
      pill: 'rounded-full px-3 py-1 text-xs font-medium',
    },
  },
  defaultVariants: {
    variant: 'neutral',
    shape: 'rounded',
  },
});

interface BadgeProps extends VariantProps<typeof badge> {
  children: React.ReactNode;
  className?: string;
  pulse?: boolean;
}

export function Badge({ children, variant, shape, className, pulse }: BadgeProps) {
  return (
    <span className={`${badge({ variant, shape })} ${className || ''} ${pulse ? 'animate-pulse' : ''}`}>
      {children}
    </span>
  );
}
