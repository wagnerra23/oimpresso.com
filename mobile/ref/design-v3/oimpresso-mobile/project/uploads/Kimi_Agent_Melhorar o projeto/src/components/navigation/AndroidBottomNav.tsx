import { Home, ClipboardList, ShoppingCart, Factory } from 'lucide-react';
import { motion } from 'framer-motion';
import type { NavTab } from '@/contexts/AppStateContext';

const tabs: { key: NavTab; label: string; icon: typeof Home }[] = [
  { key: 'home', label: 'Início', icon: Home },
  { key: 'tasks', label: 'Tarefas', icon: ClipboardList },
  { key: 'orders', label: 'Pedidos', icon: ShoppingCart },
  { key: 'production', label: 'Produção', icon: Factory },
];

interface AndroidBottomNavProps {
  activeTab: NavTab;
  onTabChange: (tab: NavTab) => void;
}

export function AndroidBottomNav({ activeTab, onTabChange }: AndroidBottomNavProps) {
  return (
    <div
      className="shrink-0 flex items-center justify-around z-40 relative"
      style={{
        height: 80,
        background: 'var(--bg-surface)',
        borderTop: '1px solid var(--border-color)',
      }}
    >
      {tabs.map(tab => {
        const isActive = activeTab === tab.key;
        const Icon = tab.icon;
        return (
          <button
            key={tab.key}
            onClick={() => onTabChange(tab.key)}
            className="flex flex-col items-center gap-1 py-2 px-4 min-w-[44px] relative"
          >
            {isActive && (
              <motion.div
                layoutId="android-nav-indicator"
                className="absolute -top-0.5 left-1/2 -translate-x-1/2 w-16 h-[3px] rounded-full"
                style={{ background: 'var(--primary)' }}
                transition={{ type: 'spring', stiffness: 400, damping: 30 }}
              />
            )}
            <Icon
              size={24}
              className="transition-colors duration-200"
              style={{ color: isActive ? 'var(--nav-active)' : 'var(--nav-inactive)' }}
            />
            <span
              className="text-[12px] font-medium transition-colors duration-200"
              style={{ color: isActive ? 'var(--nav-active)' : 'var(--nav-inactive)' }}
            >
              {tab.label}
            </span>
          </button>
        );
      })}
    </div>
  );
}
