import { useCallback, useEffect, useState } from 'react';
import { useAuth } from '@/Hooks/usePageProps';
import type { ThemeMode } from '@/Types';

/**
 * Hook de tema light/dark com persistência por usuário.
 *
 * Fonte de verdade:
 *   1. User autenticado → `auth.user.ui_theme` (coluna users.ui_theme)
 *   2. Anon/login → localStorage 'oi.theme'
 *   3. Fallback → prefers-color-scheme do SO
 *
 * A classe `dark` no <html> é aplicada ANTES do React hidratar (anti-flash
 * em layouts/inertia.blade.php). Aqui só sincronizamos mudanças em runtime.
 */
export function useTheme() {
  const { user } = useAuth();

  // valor "efetivo" aplicado no DOM agora (light|dark)
  const [effective, setEffective] = useState<'light' | 'dark'>(() => {
    if (typeof document === 'undefined') return 'light';
    return document.documentElement.classList.contains('dark') ? 'dark' : 'light';
  });

  // modo armazenado (null=sistema, light, dark)
  const mode: ThemeMode = user?.ui_theme ?? null;

  // reage à mudança de system preference quando modo = auto
  useEffect(() => {
    if (mode !== null) return; // override explícito → ignora sistema
    const mq = window.matchMedia('(prefers-color-scheme: dark)');
    const sync = () => {
      const next = mq.matches ? 'dark' : 'light';
      applyClass(next);
      setEffective(next);
    };
    sync();
    mq.addEventListener('change', sync);
    return () => mq.removeEventListener('change', sync);
  }, [mode]);

  // aplica classe quando user escolhe explícito
  useEffect(() => {
    if (mode === null) return;
    applyClass(mode);
    setEffective(mode);
  }, [mode]);

  const setTheme = useCallback((next: ThemeMode) => {
    // Optimistic: aplica classe antes do servidor responder
    const applied = next ?? (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    applyClass(applied);
    setEffective(applied);

    // localStorage p/ anon (consistência em telas públicas)
    try {
      if (next === null) localStorage.removeItem('oi.theme');
      else localStorage.setItem('oi.theme', next);
    } catch {
      /* ignore */
    }

    // Persiste no servidor se autenticado — fetch puro, sem acionar Inertia
    // (partial reload pode zerar outras props como shell; persistência aqui
    // não muda a página, só o flag em users.ui_theme).
    if (user) {
      const token =
        (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)
          ?.content ?? '';
      fetch('/user/preferences/theme', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          'X-CSRF-TOKEN': token,
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({ theme: next }),
      }).catch(() => {
        /* silencia — modo já foi aplicado visualmente; falha de rede só
           significa que o tema não persiste entre sessões */
      });
    }
  }, [user]);

  const toggle = useCallback(() => {
    setTheme(effective === 'dark' ? 'light' : 'dark');
  }, [effective, setTheme]);

  return { mode, effective, setTheme, toggle };
}

function applyClass(theme: 'light' | 'dark') {
  const el = document.documentElement;
  if (theme === 'dark') el.classList.add('dark');
  else el.classList.remove('dark');
}
