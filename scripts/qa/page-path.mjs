// @ts-check
/**
 * Fonte única para distinguir uma Page Inertia executável de arquivos auxiliares
 * co-localizados em resources/js/Pages/**.
 *
 * Regra estrutural: diretórios de componentes/hooks/tipos não são telas. O nome do
 * arquivo não basta; `Pages/Modulo/components/Filtro.tsx` é componente.
 */
export const PAGE_AUX_DIR = /^(?:_.*|components?|partials?|hooks?|utils?|lib|types?|constants?|schemas?|stores?|contexts?)$/i;

export function normalizeRepoPath(path) {
  return String(path || '').replace(/\\/g, '/').replace(/^\.\//, '');
}

/** Recebe path relativo a resources/js/Pages ou path completo do repositório. */
export function isPageScreenPath(rawPath) {
  const path = normalizeRepoPath(rawPath).replace(/^resources\/js\/Pages\//, '');
  if (!path.endsWith('.tsx') || path.endsWith('.charter.tsx') || path.includes('.test.')) return false;
  const parts = path.split('/');
  return parts.length >= 2 && !parts.slice(0, -1).some((part) => PAGE_AUX_DIR.test(part));
}

export function isAuxiliaryPagePath(rawPath) {
  const path = normalizeRepoPath(rawPath).replace(/^resources\/js\/Pages\//, '');
  return path.split('/').slice(0, -1).some((part) => PAGE_AUX_DIR.test(part));
}
