import { motion } from 'framer-motion';
import { cn } from '@/lib/utils';

interface ButtonProps {
  children: React.ReactNode;
  variant?: 'primary' | 'secondary' | 'ghost' | 'danger';
  size?: 'default' | 'small';
  className?: string;
  onClick?: () => void;
  disabled?: boolean;
  fullWidth?: boolean;
  icon?: React.ReactNode;
}

export function Button({
  children,
  variant = 'primary',
  size = 'default',
  className,
  onClick,
  disabled,
  fullWidth,
  icon,
}: ButtonProps) {
  const base = 'inline-flex items-center justify-center gap-2 font-semibold rounded-xl select-none transition-colors';
  const variants = {
    primary: 'bg-[var(--primary)] text-white hover:opacity-90',
    secondary: 'bg-[var(--bg-elevated)] text-[var(--primary)] border border-[var(--primary)]/30 hover:bg-[var(--primary)]/10',
    ghost: 'bg-transparent text-[var(--primary)] hover:bg-[var(--primary)]/10',
    danger: 'bg-[var(--danger)] text-white hover:opacity-90',
  };
  const sizes = {
    default: 'h-[var(--button-height)] px-6 text-base',
    small: 'h-9 px-4 text-sm rounded-lg',
  };

  return (
    <motion.button
      className={cn(base, variants[variant], sizes[size], fullWidth && 'w-full', disabled && 'opacity-50 pointer-events-none', className)}
      onClick={onClick}
      whileTap={{ scale: 0.95, opacity: 0.8 }}
      transition={{ duration: 0.1, ease: 'easeOut' }}
      disabled={disabled}
    >
      {icon}
      {children}
    </motion.button>
  );
}
