import { Home, ClipboardList, ShoppingCart, Factory } from 'lucide-react';
import type { NavTab } from '@/contexts/AppStateContext';

const tabs: { key: NavTab; label: string; icon: typeof Home }[] = [
  { key: 'home', label: 'Início', icon: Home },
  { key: 'tasks', label: 'Tarefas', icon: ClipboardList },
  { key: 'orders', label: 'Pedidos', icon: ShoppingCart },
  { key: 'production', label: 'Produção', icon: Factory },
];

interface IOSTabBarProps {
  activeTab: NavTab;
  onTabChange: (tab: NavTab) => void;
}

export function IOSTabBar({ activeTab, onTabChange }: IOSTabBarProps) {
  return (
    <div
      className="shrink-0 flex items-center justify-around z-40"
      style={{
        height: 83,
        background: 'rgba(28,28,30,0.85)',
        backdropFilter: 'blur(20px)',
        borderTop: '0.5px solid var(--border-color)',
      }}
    >
      {tabs.map(tab => {
        const isActive = activeTab === tab.key;
        const Icon = tab.icon;
        return (
          <button
            key={tab.key}
            onClick={() => onTabChange(tab.key)}
            className="flex flex-col items-center gap-1 pt-2 pb-5 px-3 min-w-[44px]"
          >
            <Icon
              size={24}
              className="transition-colors duration-200"
              style={{ color: isActive ? 'var(--nav-active)' : 'var(--nav-inactive)' }}
            />
            <span
              className="text-[10px] font-medium transition-colors duration-200"
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
