import React, { createContext, useContext, useState, useCallback } from 'react';

export type ScreenName =
  | 'home'
  | 'tasks'
  | 'taskDetail'
  | 'orders'
  | 'orderDetail'
  | 'newOrder'
  | 'production'
  | 'productionDetail'
  | 'clients'
  | 'clientDetail'
  | 'newClient'
  | 'products'
  | 'newProduct'
  | 'modules'
  | 'login';

export type NavTab = 'home' | 'tasks' | 'orders' | 'production' | 'clients' | 'products' | 'modules';

export interface Toast {
  id: string;
  message: string;
  type: 'success' | 'error' | 'warning' | 'info';
}

interface AppState {
  screen: ScreenName;
  prevScreen: ScreenName | null;
  navTab: NavTab;
  toasts: Toast[];
  selectedId: string | null;
  navDirection: 'push' | 'pop';
}

interface AppStateContextType {
  state: AppState;
  navigate: (screen: ScreenName, selectedId?: string) => void;
  goBack: () => void;
  setNavTab: (tab: NavTab) => void;
  addToast: (message: string, type?: Toast['type']) => void;
  removeToast: (id: string) => void;
}

const TAB_SCREENS: Record<NavTab, ScreenName> = {
  home: 'home',
  tasks: 'tasks',
  orders: 'orders',
  production: 'production',
  clients: 'clients',
  products: 'products',
  modules: 'modules',
};

const INITIAL_STATE: AppState = {
  screen: 'home',
  prevScreen: null,
  navTab: 'home',
  toasts: [],
  selectedId: null,
  navDirection: 'push',
};

const AppStateContext = createContext<AppStateContextType | null>(null);

export function AppStateProvider({ children }: { children: React.ReactNode }) {
  const [state, setState] = useState<AppState>(INITIAL_STATE);

  const navigate = useCallback((screen: ScreenName, selectedId?: string) => {
    setState(prev => ({
      ...prev,
      prevScreen: prev.screen,
      screen,
      selectedId: selectedId ?? prev.selectedId,
      navDirection: 'push' as const,
    }));
  }, []);

  const goBack = useCallback(() => {
    setState(prev => {
      const fallback = TAB_SCREENS[prev.navTab] ?? 'home';
      return {
        ...prev,
        prevScreen: prev.screen,
        screen: prev.prevScreen ?? fallback,
        navDirection: 'pop' as const,
      };
    });
  }, []);

  const setNavTab = useCallback((tab: NavTab) => {
    setState(prev => ({
      ...prev,
      prevScreen: prev.screen,
      screen: TAB_SCREENS[tab],
      navTab: tab,
      navDirection: 'pop' as const,
    }));
  }, []);

  const addToast = useCallback((message: string, type: Toast['type'] = 'info') => {
    const id = Math.random().toString(36).slice(2);
    setState(prev => ({ ...prev, toasts: [...prev.toasts, { id, message, type }] }));
    setTimeout(() => {
      setState(prev => ({ ...prev, toasts: prev.toasts.filter(t => t.id !== id) }));
    }, 3000);
  }, []);

  const removeToast = useCallback((id: string) => {
    setState(prev => ({ ...prev, toasts: prev.toasts.filter(t => t.id !== id) }));
  }, []);

  return (
    <AppStateContext.Provider value={{ state, navigate, goBack, setNavTab, addToast, removeToast }}>
      {children}
    </AppStateContext.Provider>
  );
}

export function useAppState() {
  const ctx = useContext(AppStateContext);
  if (!ctx) throw new Error('useAppState must be used within AppStateProvider');
  return ctx;
}
