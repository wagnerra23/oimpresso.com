import { usePage } from '@inertiajs/react';
import type { MenuItem, SharedProps } from '@/Types';

export function usePageProps() {
  return usePage<SharedProps>().props;
}

export function useAuth() {
  return usePageProps().auth;
}

export function useBusiness() {
  return usePageProps().business;
}

export function useAiFlags() {
  return usePageProps().ai;
}

export function useFlash() {
  return usePageProps().flash;
}

/**
 * Retorna items do ModuleTopNav pra uma page passar como prop do AppShell.
 * Lê de `shell.topnavs[moduleName]` (alimentado por Resources/menus/topnav.php).
 *
 * Uso:
 *   const moduleNav = useModuleNav('PontoWr2');
 *   <AppShell moduleNav={moduleNav}>...</AppShell>
 */
export function useModuleNav(
  moduleKey: string,
): { items: MenuItem[]; moduleLabel: string; moduleIcon?: string } | undefined {
  const { shell } = usePageProps();
  const entry = shell?.topnavs?.[moduleKey];
  if (!entry || entry.items.length === 0) return undefined;
  return {
    items: entry.items,
    moduleLabel: entry.label,
    moduleIcon: entry.icon,
  };
}

