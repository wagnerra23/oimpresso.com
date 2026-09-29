import { motion } from 'framer-motion';
import { Plus } from 'lucide-react';
import { cn } from '@/lib/utils';

interface FloatingActionButtonProps {
  onClick: () => void;
  className?: string;
  icon?: React.ReactNode;
}

export function FloatingActionButton({ onClick, className, icon }: FloatingActionButtonProps) {
  return (
    <motion.button
      className={cn(
        'w-14 h-14 rounded-full bg-[var(--primary)] text-white flex items-center justify-center shadow-lg shadow-[var(--primary)]/40 z-50',
        className
      )}
      onClick={onClick}
      whileHover={{ scale: 1.05 }}
      whileTap={{ scale: 0.95 }}
      transition={{ duration: 0.2, ease: 'easeOut' }}
    >
      {icon || <Plus size={24} />}
    </motion.button>
  );
}
