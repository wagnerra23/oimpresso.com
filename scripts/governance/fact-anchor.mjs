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
/**
 * Extrai as tabelas AFIRMADAS na §5.2 ("Modelo de dados") de um SDD.
 *
 * Discriminador **ESTRUTURAL, não lexical**: só a coluna cujo cabeçalho é `Tabela`
 * (a forma que o SDD-TEMPLATE prescreve). Medido em 2026-08-03 sobre os 16 SDDs
 * vivos: extração lexical (todo `backtick` da §5.2) devolve 234 "não encontrados"
 * em 333 — e **todos são COLUNAS** (`business_id`, `final_total`…), não tabelas.
 * A coluna-Tabela é o único lugar onde o doc afirma "esta tabela EXISTE".
 * Com o discriminador estrutural: 84 afirmações, 2 contradições, 0 falso-positivo.
 *
 * @param {string} txt conteúdo do SDD
 * @returns {Map<string,string>} base (tabela) → token original citado
 */
export function tabelasAfirmadasNo52(txt) {
  const out = new Map();
  const L = String(txt || '').split(/\r?\n/);
  let ini = -1, niv = 0;
  for (let i = 0; i < L.length; i++) {
    const m = L[i].match(/^(#{2,4})\s*5\.2\b/);
    if (m) { ini = i; niv = m[1].length; break; }
  }
  if (ini < 0) return out;
  let fim = L.length;
  for (let j = ini + 1; j < L.length; j++) {
    const m = L[j].match(/^(#{1,6})\s/);
    if (m && m[1].length <= niv) { fim = j; break; }
  }
  let col = -1;
  for (const ln of L.slice(ini, fim)) {
    if (!ln.trim().startsWith('|')) { col = -1; continue; } // saiu da tabela markdown
    const cels = ln.split('|').slice(1, -1).map((c) => c.trim());
    if (/^[-: ]+$/.test(cels.join(''))) continue;           // linha separadora
    if (col === -1) { // cabeçalho: acha a coluna "Tabela"/"Entidade"
      col = cels.findIndex((c) => /^(tabela|tabelas|entidade)$/i.test(c.replace(/[*`]/g, '').trim()));
      continue;
    }
    if (col < 0 || col >= cels.length) continue;
    for (const m of cels[col].matchAll(/`([^`]+)`/g)) {
      const base = m[1].trim().split('.')[0].split('->')[0]; // `tabela.coluna` → tabela
      if (!/^[a-z][a-z0-9_]{2,}$/.test(base)) continue;
      if (!out.has(base)) out.set(base, m[1].trim());
    }
  }
  return out;
}

/**
 * Ancora as tabelas afirmadas na §5.2 dos SDDs na fonte-de-verdade VERSIONADA
 * (dump `database/schema/mysql-schema.sql` + `Schema::create/rename` das migrations).
 *
 * Por que a fonte é o dump e não `db:show`/`model:show`: o oráculo de **autoria**
 * (o agente que escreve a §5.2 deve perguntar ao banco) não serve de oráculo de
 * **gate** — query de banco roda no CT 100, nunca no CI, e gate que fingisse medir
 * isso lá seria teatro (proibicoes §5 2026-07-17). O dump é versionado e hermético.
 *
 * @param {{ docs: {rel:string,txt:string}[], tableExists?: (t:string)=>boolean }} args
 * @returns {{file:string, afirma:string, verdade:string}[]}
 */
export function factAnchorTabelas({ docs, tableExists = () => true }) {
  const hits = [];
  for (const { rel, txt } of docs) {
    if (!txt) continue;
    for (const [base, token] of tabelasAfirmadasNo52(txt)) {
      if (!tableExists(base)) {
        hits.push({ file: rel, afirma: `tabela \`${token}\``, verdade: 'não existe no schema versionado (renomeada/nome errado?)' });
      }
    }
  }
  return hits;
}

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
