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
 * Retorna os sub-items de um módulo pra usar no ModuleTopNav do AppShell.
 *
 * Estratégia em camadas (ADR arq/0009):
 *   1. shell.topnavs[moduleName] — declarativo em Modules/<Nome>/Resources/menus/topnav.php
 *   2. Fallback: children do módulo em shell.menu (sidebar nwidart)
 *
 * Primeiro argumento aceita:
 *   - Nome do módulo (pra chave em topnavs): 'PontoWr2', 'Accounting', etc.
 *   - OU label exato/parcial pra match em shell.menu (case-insensitive).
 *
 * Uso típico:
 *   const moduleNav = useModuleNav('PontoWr2');
 *   <AppShell moduleNav={moduleNav}>...</AppShell>
 *
 * Preserva ordem e permissões do backend.
 */
export function useModuleNav(
  moduleKey: string,
): { items: MenuItem[]; moduleLabel: string; moduleIcon?: string } | undefined {
  const { shell } = usePageProps();
  if (!shell) return undefined;

  // 1. Prefere topnav declarativo (Resources/menus/topnav.php)
  const declarative = shell.topnavs?.[moduleKey];
  if (declarative && declarative.items.length > 0) {
    return {
      items: declarative.items,
      moduleLabel: declarative.label,
      moduleIcon: declarative.icon,
    };
  }

  // 2. Fallback: children da sidebar nwidart via label match
  if (!shell.menu) return undefined;
  const target = moduleKey.trim().toLowerCase();
  const match = shell.menu.find((m) => {
    const label = (m.label ?? '').trim().toLowerCase();
    return label === target || label.includes(target) || target.includes(label);
  });

  if (!match || !match.children || match.children.length === 0) return undefined;

  return {
    items: match.children,
    moduleLabel: match.label,
    moduleIcon: match.icon,
  };
}
