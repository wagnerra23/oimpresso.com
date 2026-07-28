#!/usr/bin/env node
// @ts-check
/**
 * fact-anchor.mjs — lógica PURA do Check T de memory-health.mjs (fact-anchor).
 *
 * Ancora o FATO afirmado num doc de entrada (versão de stack · `Modules/<Nome>`)
 * numa FONTE-DE-VERDADE versionada (package.json / composer.json / árvore Modules/)
 * e devolve as CONTRADIÇÕES. Extraído p/ módulo próprio (como document-authority.mjs)
 * pra ser testável HERMÉTICO — fixtures boa/ruim que provam que o gate morde
 * (proibicoes §5 2026-07-09 "fixture boa/ruim") — sem rodar a CLI inteira no import.
 *
 * Determinístico, sem LLM. **major-only de propósito:** a constraint do composer é
 * um FLOOR, não o runtime — "PHP 8.4"/"Laravel 13.6" vs `^8.1`/`^13.0` NÃO devem
 * falsa-positivar (8==8, 13==13). Por isso só o major entra na comparação.
 *
 * Ref: memory/decisions/proposals/2026-07-23-fatos-derivaveis-anti-apodrecimento.md
 *      (Tier 2A · emendas E1 regex `v?` + E2 só o restateado in-scope) · ADR 0256/0275.
 */

/** Extrai o major de um range/constraint semver ("^13.0" → "13"; "React 19" → "19"). */
export function majorFrom(range) {
  const m = String(range).match(/(\d+)/);
  return m ? m[1] : null;
}

/**
 * Tabela VERSIONS a partir de package.json + composer.json JÁ parseados.
 * Só constraints EFETIVAMENTE restateadas como "Nome [v]Major" nos 6 docs in-scope
 * (proposal §4 E2). Ficam DE FORA (residual §6): nWidart `^10` e spatie `^3.13`
 * (constraint copiada, não "Nome Major"); laravel/ai `^0.6.3` e laravel/mcp `^0.7`
 * (major 0 → comparação inútil); Vite/TypeScript (não aparecem em doc in-scope).
 * @param {object} [pkg] package.json parseado
 * @param {object} [comp] composer.json parseado
 */
export function buildVersions(pkg = {}, comp = {}) {
  const dep = { ...(pkg.dependencies || {}), ...(pkg.devDependencies || {}) };
  const req = { ...(comp.require || {}), ...(comp['require-dev'] || {}) };
  return [
    { nome: 'React', re: /React\s+v?(\d+)/g, truth: majorFrom(dep.react || '') },
    { nome: 'Laravel', re: /Laravel\s+v?(\d+)/g, truth: majorFrom(req['laravel/framework'] || '') },
    { nome: 'Inertia', re: /Inertia\s+v?(\d+)/g, truth: majorFrom(dep['@inertiajs/react'] || req['inertiajs/inertia-laravel'] || '') },
    { nome: 'Tailwind', re: /Tailwind\s+v?(\d+)/g, truth: majorFrom(dep.tailwindcss || '') },
    { nome: 'Pest', re: /Pest\s+v?(\d+)/g, truth: majorFrom(req['pestphp/pest'] || '') },
    { nome: 'PHPUnit', re: /PHPUnit\s+v?(\d+)/g, truth: majorFrom(req['phpunit/phpunit'] || '') },
  ];
}

/**
 * Varre docs `{rel,txt}` e devolve as contradições vs a fonte-de-verdade.
 * @param {{ docs: {rel:string,txt:string}[], pkg?: object, comp?: object, moduleExists?: (name:string)=>boolean }} args
 * @returns {{file:string, afirma:string, verdade:string}[]}
 */
export function factAnchorScan({ docs, pkg = {}, comp = {}, moduleExists = () => true }) {
  const VERSIONS = buildVersions(pkg, comp);
  const hits = [];
  for (const { rel, txt } of docs) {
    if (!txt) continue;
    for (const v of VERSIONS) {
      if (!v.truth) continue;
      for (const m of txt.matchAll(v.re)) {
        const after = txt.slice(m.index + m[0].length, m.index + m[0].length + 8);
        if (/^\s*(?:→|->|to|para|a)\s*v?\d/.test(after)) continue; // migração "X → Y" (incl. alvo v-prefixado "v2 → v3"): X é história, ignora
        if (m[1] !== v.truth) hits.push({ file: rel, afirma: `${v.nome} ${m[1]}`, verdade: `${v.nome} ${v.truth}` });
      }
    }
    for (const m of txt.matchAll(/Modules\/([A-Z][A-Za-z0-9]+)/g)) { // [A-Z] exige letra → placeholder Modules/<X> não casa; 0-9 evita truncar PontoWr2
      if (!moduleExists(m[1])) hits.push({ file: rel, afirma: `Modules/${m[1]}`, verdade: 'dir inexistente (renomeado/removido?)' });
    }
  }
  return hits;
}
