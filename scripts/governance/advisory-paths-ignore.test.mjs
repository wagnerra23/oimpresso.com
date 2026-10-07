#!/usr/bin/env node
/**
 * advisory-paths-ignore.test — o `paths-ignore` das lanes advisory é FAIL-CLOSED e não muda veredito.
 *
 * ── POR QUE EXISTE (medido em 2026-10-07, não suposto) ─────────────────────────
 * Fila do CI: 653 runs esperando com 18–22 jobs rodando (3 amostras). Um PR que troca 1
 * arquivo JSON (#8816) rodava 117 jobs. Dezenove lanes advisory (`*-pest.yml`,
 * `e2e-gate.yml`, `typecheck-gate.yml`) subiam runner nesse PR só pra o `dorny/paths-filter`
 * interno concluir "nada do domínio mudou" e sair verde: 19 jobs, 803 s de runner, 15% do
 * PR. Nos 300 últimos PRs mergeados, 85 tocavam SÓ `memory/`, `prototipo-ui/` ou
 * `scripts/design-sync/state/`.
 *
 * O CONSERTO: `paths-ignore` com esses 3 prefixos no `pull_request` dessas lanes. O GitHub
 * pula o workflow só quando TODO arquivo do diff casa o ignore — qualquer arquivo fora
 * (inclusive um que ninguém classificou) faz a lane rodar como hoje. É fail-closed por
 * construção: não há detector que possa falhar, e o desconhecido roda.
 *
 * ── O QUE ESTE TESTE GARANTE, por workflow que tenha `paths-ignore` no pull_request ──
 *   V1  só `paths-ignore` — nunca `paths:` (allowlist é fail-OPEN: o não-listado não roda)
 *   V2  o ignore é EXATAMENTE o conjunto inerte abaixo — nada de alargar no calado
 *   V3  nenhum job emite context REQUIRED (required filtrado não nasce → merge trava;
 *       o lint `required-always-run` também morde, aqui é a 2ª camada)
 *   V4  existe `dorny/paths-filter` interno — é ele que prova que o veredito não muda
 *   V5  nenhum padrão do filtro interno alcança um prefixo inerte. Se alcançasse, um PR só
 *       de doc HOJE rodaria a lane, e o ignore estaria escondendo trabalho real
 *       (é o caso do `forja-pest.yml`, que lê `prototipo-ui/cowork/Wagner/**` — fora)
 *
 * ── LIMITES DECLARADOS ─────────────────────────────────────────────────────────
 * - O GitHub avalia filtro de caminho nos primeiros 300 arquivos do diff. Um PR com mais
 *   de 300 arquivos cujos 300 primeiros sejam todos inertes não sobe estas lanes. Só
 *   afeta advisory; o `push` em `main` não tem este ignore e roda com o `paths:` próprio.
 * - Promover uma destas lanes a required exige APAGAR o bloco `paths-ignore` — a mesma
 *   edição que já recoloca `synchronize` no `types:`. O `required-always-run` reprova se
 *   esquecerem.
 *
 * Sem js-yaml de propósito: a lane `governance-script-tests` não roda `npm ci`
 * (proibicoes.md §5 2026-09-22). Parsing textual, CRLF normalizado na borda.
 *
 * Uso: node scripts/governance/advisory-paths-ignore.test.mjs
 */

import { readFileSync, readdirSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { blocoOn, contextsDoWorkflow } from './required-always-run.mjs';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..', '..');
const nl = (s) => String(s).replace(/\r\n/g, '\n');

export const INERTES = ['memory/**', 'prototipo-ui/**', 'scripts/design-sync/state/**'];

/** corpo do sub-bloco `pull_request:` dentro de `on:`, ou null. */
function corpoPR(src) {
  const on = blocoOn(nl(src));
  const m = on.match(/^([ \t]+)pull_request:[ \t]*$([\s\S]*?)(?=^\1[^\s#]|$(?![\s\S]))/m);
  return m ? m[2] : null;
}

/** itens `- x` logo abaixo de uma chave (pula comentário e linha vazia). */
function listaSob(corpo, chave) {
  const linhas = nl(corpo).split('\n');
  const i = linhas.findIndex((l) => new RegExp(`^[ \\t]+${chave}:[ \\t]*$`).test(l));
  if (i === -1) return null;
  const out = [];
  for (const l of linhas.slice(i + 1)) {
    if (/^[ \t]*(#.*)?$/.test(l)) continue;
    const v = l.match(/^[ \t]+-[ \t]*['"]?([^'"#\n]+?)['"]?[ \t]*(?:#.*)?$/);
    if (!v) break;
    out.push(v[1].trim());
  }
  return out;
}

export const pathsIgnoreDoPR = (src) => { const c = corpoPR(src); return c === null ? null : listaSob(c, 'paths-ignore'); };
export const pathsDoPR = (src) => { const c = corpoPR(src); return c === null ? null : listaSob(c, 'paths'); };

/** padrões de todo bloco `filters: |` do dorny/paths-filter. */
export function filtrosInternos(src) {
  const linhas = nl(src).split('\n');
  const out = [];
  for (let i = 0; i < linhas.length; i++) {
    const m = linhas[i].match(/^([ \t]*)filters:[ \t]*\|[ \t]*$/);
    if (!m) continue;
    const ind = m[1].length;
    for (let j = i + 1; j < linhas.length; j++) {
      const l = linhas[j];
      if (/^[ \t]*$/.test(l)) continue;
      if (l.match(/^[ \t]*/)[0].length <= ind) break;
      const v = l.match(/^[ \t]+-[ \t]*['"]?([^'"#\n]+?)['"]?[ \t]*(?:#.*)?$/);
      if (v) out.push(v[1].trim());
    }
  }
  return out;
}

/** o padrão `pat` pode casar algum caminho sob o diretório `prefixo/`? (conservador) */
export function alcancaPrefixo(pat, prefixo) {
  const q = pat.replace(/^!/, '').replace(/^\.\//, '');
  const lit = q.replace(/[*?[{].*$/s, '');
  if (lit === q) return q.startsWith(prefixo);
  return prefixo.startsWith(lit) || lit.startsWith(prefixo);
}

const globParaRegex = (g) => new RegExp('^' + g
  .replace(/[.+^${}()|[\]\\]/g, '\\$&')
  .replace(/\*\*\/?/g, '\u0000')
  .replace(/\*/g, '[^/]*')
  .replace(/\?/g, '[^/]')
  .replace(/\u0000/g, '.*') + '$');

/** modelo da semântica do GitHub para paths-ignore: roda se ALGUM arquivo escapa do ignore. */
export function rodaComIgnore(arquivos, ignore) {
  const res = ignore.map(globParaRegex);
  return arquivos.some((a) => !res.some((r) => r.test(a)));
}

/** violações V1–V5 de um workflow (vazio = conforme ou sem paths-ignore no PR). */
export function auditarWorkflow(src, required) {
  const ignore = pathsIgnoreDoPR(src);
  if (!ignore) return [];
  const v = [];
  if (pathsDoPR(src)) v.push('V1 allowlist `paths:` no pull_request — fail-open');
  const fora = ignore.filter((p) => !INERTES.includes(p));
  const faltam = INERTES.filter((p) => !ignore.includes(p));
  if (fora.length || faltam.length) v.push(`V2 ignore difere do conjunto inerte (fora: ${fora.join(', ') || '-'} · faltam: ${faltam.join(', ') || '-'})`);
  const req = contextsDoWorkflow(src).map((c) => c.context).filter((c) => required.has(c));
  if (req.length) v.push(`V3 emite context required: ${req.join(', ')}`);
  if (!/dorny\/paths-filter/.test(src)) v.push('V4 sem dorny/paths-filter interno — equivalência não provada');
  const prefixos = INERTES.map((p) => p.replace(/\*\*$/, ''));
  const lidos = filtrosInternos(src).filter((pat) => prefixos.some((pre) => alcancaPrefixo(pat, pre)));
  if (lidos.length) v.push(`V5 filtro interno lê caminho inerte: ${lidos.join(', ')}`);
  return v;
}

// ── harness ──────────────────────────────────────────────────────────────────
let falhas = 0, total = 0;
const ok = (cond, msg) => { total++; if (!cond) { falhas++; console.log(`  ✗ ${msg}`); } else console.log(`  ✓ ${msg}`); };

const BOM = [
  'name: X', '', 'on:', '  workflow_dispatch: {}', '  pull_request:',
  '    types: [opened, reopened, ready_for_review]', '    branches: [main]',
  '    # comentario dentro do bloco', '    paths-ignore:',
  "      - 'memory/**'", "      - 'prototipo-ui/**'", "      - 'scripts/design-sync/state/**'",
  '  push:', '    branches: [main]', '    paths:', "      - 'Modules/X/**'", '',
  'jobs:', '  pest:', '    name: PHP / Pest (X · MySQL)', '    runs-on: ubuntu-latest', '    steps:',
  '      - uses: dorny/paths-filter@v3', '        id: changes', '        with:', '          filters: |',
  '            x:', "              - 'Modules/X/**'", "              - 'database/schema/mysql-schema.sql'",
  '      - run: echo ok', '',
].join('\n');
const SEM_REQ = new Set(['Algum required']);

console.log('\n== modelo da semântica paths-ignore (fail-closed)');
ok(!rodaComIgnore(['memory/a.md'], INERTES), 'só memory/ → não roda');
ok(!rodaComIgnore(['prototipo-ui/x/y.jsx', 'scripts/design-sync/state/enviados-cowork.json'], INERTES), 'só prefixos inertes → não roda');
ok(rodaComIgnore(['memory/a.md', 'Modules/X/Y.php'], INERTES), 'inerte + código → RODA');
ok(rodaComIgnore(['pasta-que-ninguem-classificou/z.txt'], INERTES), 'arquivo não classificado → RODA (fail-closed)');
ok(rodaComIgnore(['scripts/design-sync/aplicar.mjs'], INERTES), 'irmão do diretório ignorado (scripts/design-sync/*.mjs) → RODA');
ok(rodaComIgnore(['memoryx/a.md'], INERTES), 'prefixo parecido (memoryx/) → RODA');

console.log('\n== auditoria do workflow (V1–V5)');
ok(JSON.stringify(pathsIgnoreDoPR(BOM)) === JSON.stringify(INERTES), 'parser lê o paths-ignore do pull_request (não o paths do push)');
ok(auditarWorkflow(BOM, SEM_REQ).length === 0, 'fixture boa: zero violações');
ok(auditarWorkflow(BOM.replace(/\n/g, '\r\n'), SEM_REQ).length === 0 && pathsIgnoreDoPR(BOM.replace(/\n/g, '\r\n')).length === 3, 'fixture boa em CRLF: lida igual');
const SEM_IGNORE = BOM.replace(/    paths-ignore:\n(      - .*\n){3}/, '');
ok(pathsIgnoreDoPR(SEM_IGNORE) === null && auditarWorkflow(SEM_IGNORE, SEM_REQ).length === 0, 'controle: sem paths-ignore no PR não é auditado');
ok(auditarWorkflow(BOM.replace("    paths-ignore:\n", "    paths:\n      - 'Modules/**'\n    paths-ignore:\n"), SEM_REQ).some((x) => x.startsWith('V1')), 'MORDE V1: allowlist paths: no pull_request');
ok(auditarWorkflow(BOM.replace("      - 'memory/**'\n", "      - 'memory/**'\n      - 'Modules/**'\n"), SEM_REQ).some((x) => x.startsWith('V2')), 'MORDE V2: ignore alargado pra código');
ok(auditarWorkflow(BOM.replace("      - 'prototipo-ui/**'\n", ''), SEM_REQ).some((x) => x.startsWith('V2')), 'MORDE V2: ignore incompleto também é divergência');
ok(auditarWorkflow(BOM, new Set(['PHP / Pest (X · MySQL)'])).some((x) => x.startsWith('V3')), 'MORDE V3: job emite context required');
ok(auditarWorkflow(BOM.replace('dorny/paths-filter@v3', 'actions/checkout@v4'), SEM_REQ).some((x) => x.startsWith('V4')), 'MORDE V4: sem filtro interno');
ok(auditarWorkflow(BOM.replace("              - 'Modules/X/**'", "              - 'prototipo-ui/cowork/Wagner/**'"), SEM_REQ).some((x) => x.startsWith('V5')), 'MORDE V5: filtro interno lê prototipo-ui/ (caso forja-pest)');
ok(auditarWorkflow(BOM.replace("              - 'Modules/X/**'", "              - '**/*.md'"), SEM_REQ).some((x) => x.startsWith('V5')), 'MORDE V5: curinga na raiz alcança memory/');
ok(!alcancaPrefixo('Modules/X/**', 'memory/') && !alcancaPrefixo('resources/js/**', 'prototipo-ui/'), 'controle V5: padrão de código não alcança inerte');

console.log('\n== workflows reais em .github/workflows');
const bl = JSON.parse(readFileSync(join(ROOT, 'governance', 'required-checks-baseline.json'), 'utf8'));
const required = new Set([...(bl.classic_protection?.contexts || []), ...(bl.rulesets?.contexts || [])]);
const dir = join(ROOT, '.github', 'workflows');
let comIgnore = 0;
for (const f of readdirSync(dir).filter((x) => /\.ya?ml$/.test(x)).sort()) {
  const src = readFileSync(join(dir, f), 'utf8');
  if (!pathsIgnoreDoPR(src)) continue;
  comIgnore++;
  const v = auditarWorkflow(src, required);
  ok(v.length === 0, `${f}${v.length ? ' — ' + v.join(' | ') : ''}`);
}
// Controle positivo: se o parser ficar cego, nenhum workflow entra e o teste passaria vazio.
ok(comIgnore > 0, `parser enxerga paths-ignore em workflow real (${comIgnore} encontrado(s)) — 0 = cego ou mecanismo removido`);

console.log(`\n${total - falhas}/${total} asserts verdes`);
process.exit(falhas ? 1 : 0);
