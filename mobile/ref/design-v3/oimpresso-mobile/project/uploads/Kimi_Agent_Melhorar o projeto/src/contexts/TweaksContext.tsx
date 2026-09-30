import React, { createContext, useContext, useState, useCallback } from 'react';

export interface Tweaks {
  theme: 'light' | 'dark';
  density: 'comfy' | 'normal' | 'compact';
  tenant: string;
  taskState: 'vazio' | 'cheio' | 'urgente';
}

const DEFAULT_TWEAKS: Tweaks = {
  theme: 'dark',
  density: 'comfy',
  tenant: 'wr2',
  taskState: 'urgente',
};

interface TweaksContextType {
  tweaks: Tweaks;
  setTweak: <K extends keyof Tweaks>(key: K, value: Tweaks[K]) => void;
}

const TweaksContext = createContext<TweaksContextType | null>(null);

export function TweaksProvider({ children }: { children: React.ReactNode }) {
  const [tweaks, setTweaks] = useState<Tweaks>(DEFAULT_TWEAKS);

  const setTweak = useCallback(<K extends keyof Tweaks>(key: K, value: Tweaks[K]) => {
    setTweaks(prev => ({ ...prev, [key]: value }));
  }, []);

  return (
    <TweaksContext.Provider value={{ tweaks, setTweak }}>
      {children}
    </TweaksContext.Provider>
  );
}

export function useTweaks() {
  const ctx = useContext(TweaksContext);
  if (!ctx) throw new Error('useTweaks must be used within TweaksProvider');
  return ctx;
}
