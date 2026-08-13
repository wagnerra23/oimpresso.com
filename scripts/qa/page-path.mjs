// @ts-check
import { existsSync, readdirSync } from 'node:fs';

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

/**
 * Raízes onde uma Page pode morar, relativas ao repo.
 *
 * Este módulo já era a fonte única de "isto é uma tela?"; a partir de 2026-08-12 é também a de
 * "ONDE procurar telas". O motivo é medido: 39 scripts enumeravam `resources/js/Pages` cada um por
 * conta própria, então mover uma tela para o módulo dono a fazia sumir do denominador de todos eles
 * de uma vez — catracas de cobertura reprovando não por regressão, mas por cegueira.
 *
 * @param {string} root raiz do repositório
 * @returns {string[]} caminhos ABSOLUTOS das raízes existentes (a do núcleo + uma por módulo)
 */
export function raizesDePages(root) {
  const sep = root.endsWith('/') || root.endsWith('\\') ? '' : '/';
  const nucleo = `${root}${sep}resources/js/Pages`;
  const raizes = existsSync(nucleo) ? [nucleo] : [];
  const modulesDir = `${root}${sep}Modules`;
  if (!existsSync(modulesDir)) return raizes;
  for (const mod of readdirSync(modulesDir, { withFileTypes: true })) {
    if (!mod.isDirectory()) continue;
    // `Resources` é a convenção nWidart deste repo; `resources` aparece no núcleo. Aceitar as
    // duas evita que um casing errado tire a tela do denominador em silêncio.
    for (const r of ['Resources', 'resources']) {
      const dir = `${modulesDir}/${mod.name}/${r}/js/Pages`;
      if (existsSync(dir)) { raizes.push(dir); break; }
    }
  }
  return raizes;
}
