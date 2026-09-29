import { motion, AnimatePresence } from 'framer-motion';
import { X } from 'lucide-react';

interface BottomSheetProps {
  open: boolean;
  onClose: () => void;
  title?: string;
  children: React.ReactNode;
}

export function BottomSheet({ open, onClose, title, children }: BottomSheetProps) {
  return (
    <AnimatePresence>
      {open && (
        <>
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            className="absolute inset-0 bg-black/50 z-[60] backdrop-blur-sm"
            onClick={onClose}
          />
          <motion.div
            initial={{ y: '100%' }}
            animate={{ y: 0 }}
            exit={{ y: '100%' }}
            transition={{ type: 'spring', stiffness: 250, damping: 28 }}
            className="absolute bottom-0 left-0 right-0 bg-[var(--bg-surface)] rounded-t-2xl z-[65] max-h-[70%] overflow-y-auto"
          >
            <div className="flex items-center justify-center pt-3 pb-2">
              <div className="w-10 h-1 rounded-full bg-[var(--border-color)]" />
            </div>
            {title && (
              <div className="flex items-center justify-between px-5 pb-3">
                <h3 className="text-lg font-semibold text-[var(--text-primary)]">{title}</h3>
                <button onClick={onClose} className="p-1 rounded-full hover:bg-[var(--bg-elevated)]">
                  <X size={20} className="text-[var(--text-secondary)]" />
                </button>
              </div>
            )}
            <div className="px-5 pb-8">{children}</div>
          </motion.div>
        </>
      )}
    </AnimatePresence>
  );
}
