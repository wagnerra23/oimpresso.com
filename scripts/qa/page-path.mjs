// @ts-check
/**
 * Fonte única para distinguir uma Page Inertia executável de arquivos auxiliares
 * co-localizados em resources/js/Pages/**.
 *
 * Regra estrutural: diretórios de componentes/hooks/tipos não são telas. O nome do
 * arquivo não basta; `Pages/Modulo/components/Filtro.tsx` é componente.
 *
 * DUAS RAÍZES desde 2026-08-12: a tela pode morar no núcleo (`resources/js/Pages/**`) ou
 * dentro do módulo dono (`Modules/<X>/resources/js/Pages/**`). O namespace — e portanto a
 * identidade da tela — é o MESMO nos dois casos; só o local do arquivo muda. Por isso a raiz
 * é descascada aqui, num lugar só: quem chama continua perguntando "isto é uma tela?" sem
 * precisar saber onde ela mora. Ver `.claude/rules/pages.md`.
 */
export const PAGE_AUX_DIR = /^(?:_.*|components?|partials?|hooks?|utils?|lib|types?|constants?|schemas?|stores?|contexts?)$/i;

/** Raiz de Pages, no núcleo OU dentro de um módulo. */
// `[Rr]esources` — a convenção nWidart deste repo é `Resources/` maiúsculo (711 arquivos contra
// 12, medido 2026-08-12); o núcleo usa `resources/` minúsculo. As duas grafias contam.
const RAIZ_PAGES = /^(?:Modules\/[^/]+\/)?[Rr]esources\/js\/Pages\//;

export function normalizeRepoPath(path) {
  return String(path || '').replace(/\\/g, '/').replace(/^\.\//, '');
}

/**
 * Caminho da tela relativo à raiz de Pages — o NAMESPACE, que independe de onde o arquivo mora.
 * `Modules/PaymentGateway/Resources/js/Pages/Settings/X.tsx` e `resources/js/Pages/Settings/X.tsx`
 * devolvem ambos `Settings/X.tsx`.
 */
export function pageNamespacePath(rawPath) {
  return normalizeRepoPath(rawPath).replace(RAIZ_PAGES, '');
}

/** Recebe path relativo a resources/js/Pages ou path completo do repositório (núcleo ou módulo). */
export function isPageScreenPath(rawPath) {
  const path = pageNamespacePath(rawPath);
  if (!path.endsWith('.tsx') || path.endsWith('.charter.tsx') || path.includes('.test.')) return false;
  const parts = path.split('/');
  return parts.length >= 2 && !parts.slice(0, -1).some((part) => PAGE_AUX_DIR.test(part));
}

export function isAuxiliaryPagePath(rawPath) {
  const path = pageNamespacePath(rawPath);
  return path.split('/').slice(0, -1).some((part) => PAGE_AUX_DIR.test(part));
}
