import { motion } from 'framer-motion';
import { cn } from '@/lib/utils';

interface CardProps {
  children: React.ReactNode;
  className?: string;
  onClick?: () => void;
  pressable?: boolean;
  padding?: 'default' | 'none';
  style?: React.CSSProperties;
}

export function Card({ children, className, onClick, pressable = false, padding = 'default', style }: CardProps) {
  return (
    <motion.div
      className={cn(
        'bg-[var(--bg-surface)] border border-[var(--border-color)] rounded-[var(--card-radius)] overflow-hidden',
        padding === 'default' && 'p-[var(--card-padding)]',
        pressable && 'cursor-pointer select-none',
        className
      )}
      onClick={onClick}
      whileTap={pressable ? { scale: 0.97, opacity: 0.9 } : undefined}
      style={style}
      transition={{ duration: 0.15, ease: 'easeOut' }}
    >
      {children}
    </motion.div>
  );
}
