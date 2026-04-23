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
 * Match por label exato (case-insensitive) OU por URL root.
 *
 * Uso típico:
 *   const moduleNav = useModuleNav('Ponto WR2');
 *   <AppShell moduleNav={moduleNav}>...</AppShell>
 *
 * Preserva a ordem do backend (LegacyMenuAdapter já fez usort por order).
 * Preserva permissões (items filtrados no backend antes de chegar aqui).
 */
export function useModuleNav(
  moduleLabel: string,
): { items: MenuItem[]; moduleLabel: string; moduleIcon?: string } | undefined {
  const { shell } = usePageProps();
  if (!shell?.menu) return undefined;

  const target = moduleLabel.trim().toLowerCase();

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
