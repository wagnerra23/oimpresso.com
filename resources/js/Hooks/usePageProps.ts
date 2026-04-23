import { usePage } from '@inertiajs/react';
import type { MenuItem, ModuleTopNav, SharedProps } from '@/Types';

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

/**
 * Detecta automaticamente qual topnav mostrar baseado na URL atual.
 * Usado pelo AppShell em persistent layout — não precisa cada page passar
 * moduleNav manualmente.
 *
 * Ex: em `/ponto/espelho`, acha o topnav cujos items começam com `/ponto/`.
 */
export function useAutoModuleNav():
  | { items: MenuItem[]; moduleLabel: string; moduleIcon?: string }
  | undefined {
  const { shell } = usePageProps();
  const page = usePage();
  const currentPath = page.url.split('?')[0]?.split('#')[0] ?? page.url;
  const currentRoot = '/' + (currentPath.split('/')[1] ?? '');

  if (!shell?.topnavs) return undefined;

  for (const entry of Object.values(shell.topnavs as Record<string, ModuleTopNav>)) {
    if (!entry?.items?.length) continue;
    for (const item of entry.items) {
      const href = item.href ?? '';
      if (!href || href === '#') continue;
      const itemRoot = '/' + (href.split('/')[1] ?? '');
      if (itemRoot === currentRoot) {
        return { items: entry.items, moduleLabel: entry.label, moduleIcon: entry.icon };
      }
    }
  }
  return undefined;
}

