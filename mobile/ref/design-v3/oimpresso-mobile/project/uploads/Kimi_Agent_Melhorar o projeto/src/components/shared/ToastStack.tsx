import { motion, AnimatePresence } from 'framer-motion';
import { CheckCircle, AlertCircle, AlertTriangle, Info, X } from 'lucide-react';
import type { Toast } from '@/contexts/AppStateContext';

const icons = {
  success: CheckCircle,
  error: AlertCircle,
  warning: AlertTriangle,
  info: Info,
};

const colors = {
  success: 'text-[#30D158]',
  error: 'text-[#FF453A]',
  warning: 'text-[#FF9F0A]',
  info: 'text-[#0A84FF]',
};

interface ToastStackProps {
  toasts: Toast[];
  onRemove: (id: string) => void;
}

export function ToastStack({ toasts, onRemove }: ToastStackProps) {
  return (
    <div className="absolute top-12 left-4 right-4 z-[70] flex flex-col gap-2 pointer-events-none">
      <AnimatePresence>
        {toasts.map(toast => {
          const Icon = icons[toast.type];
          return (
            <motion.div
              key={toast.id}
              initial={{ y: -20, opacity: 0, scale: 0.95 }}
              animate={{ y: 0, opacity: 1, scale: 1 }}
              exit={{ y: -20, opacity: 0, scale: 0.95 }}
              transition={{ type: 'spring', stiffness: 400, damping: 25 }}
              className="pointer-events-auto flex items-center gap-3 bg-[var(--bg-elevated)] border border-[var(--border-color)] rounded-xl px-4 py-3 shadow-lg"
            >
              <Icon size={18} className={colors[toast.type]} />
              <span className="flex-1 text-sm font-medium text-[var(--text-primary)]">{toast.message}</span>
              <button onClick={() => onRemove(toast.id)} className="text-[var(--text-tertiary)] hover:text-[var(--text-primary)]">
                <X size={14} />
              </button>
            </motion.div>
          );
        })}
      </AnimatePresence>
    </div>
  );
}
