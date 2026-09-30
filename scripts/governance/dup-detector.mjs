#!/usr/bin/env node
// @ts-check
/**
 * dup-detector.mjs — L3 (keystone) da trava anti-duplicação de trabalho entre sessões
 * paralelas (proposta memory/decisions/proposals/anti-duplicacao-work-claim-gate.md).
 *
 * No PR: se ele toca um arquivo HOT-PATH que JÁ está sendo tocado por outro PR ABERTO,
 * exige um marcador `Dedup-ack: <justificativa>` no corpo — senão sinaliza (exit 1).
 * Pega o caso real que reincidiu 3× (handoff #3092 + Onda 0): 2 sessões editando o
 * MESMO arquivo de governança. Compara ARQUIVO EXATO (não pasta) → baixo falso-positivo.
 *
 * Advisory de nascença (ADR 0271/0275) — o workflow NÃO entra em branch protection;
 * o check fica vermelho/visível mas não bloqueia o merge. Promove a required pelo
 * calendário de 14d + 0 falso-positivo. Núcleo imutável: não afrouxar no PR que ele barraria.
 *
 * Hot-paths + exclusões: governance/dup-hot-paths.json (fonte única configurável).
 * Funções puras exportadas → testáveis sem rede (dup-detector.test.mjs).
 *
 * Uso CI: node scripts/governance/dup-detector.mjs --pr=<N> [--repo=owner/name]
 *
 * Modo --path (sob demanda, ANTES de abrir PR — §5 2026-09-30, LC-19):
 *   node scripts/governance/dup-detector.mjs --path=<arquivo>[,<arquivo>...] [--self=<N>] [--self-branch=<branch>] [--repo=owner/name]
 *   `--self-branch` exclui o PR aberto DESTE branch — é o que o hook de `gh pr create` passa, porque
 *   ali o número do PR ainda não existe (ou o PR já existe e seria acusado contra si mesmo).
 * Responde "algum PR ABERTO já toca este arquivo?" casando o ARQUIVO do diff (não texto de
 * busca), sem precisar de PR próprio. Não filtra por hot-path: quem pergunta nomeou o arquivo.
 * Exit: 0 livre · 1 tocado (lista os PRs) · 2 NÃO MEDI. O 2 é separado de propósito (§5
 * 2026-07-29): gh falhando, lista possivelmente cortada (devolveu == limite) ou PR no teto de
 * `files` (100, o GraphQL corta) sem o arquivo — nesses casos "ninguém toca" seria afirmação
 * sem medição. PR que toca o arquivo é evidência positiva e vale mesmo com lista cortada.
 * O que ele NÃO responde: sessão viva sem PR (`whats-active`) e trabalho já mergeado
 * (`git log HEAD..origin/main -- <arquivo>`). Casa path, não tema.
 * Refs: proposta anti-duplicacao-work-claim-gate · ADR 0070/0256/0275 · ZELADOR.
 */
import { execFileSync } from 'node:child_process';
import { readFileSync, existsSync } from 'node:fs';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';

export const HOT_PATHS_FILE = 'governance/dup-hot-paths.json';
export const ACK_RE = /^Dedup-ack:\s*\S+/m;

export function loadHotPaths(root) {
  const p = join(root, HOT_PATHS_FILE);
  if (!existsSync(p)) return { hot: [], exclude: [] };
  try { const j = JSON.parse(readFileSync(p, 'utf8')); return { hot: j.hot || [], exclude: j.exclude || [] }; }
  catch { return { hot: [], exclude: [] }; }
}

/** arquivo está sob um hot-prefix E não na lista de exclusão? */
export function isHot(file, hot, exclude) {
  if (exclude.some((e) => file === e || file.startsWith(e))) return false;
  return hot.some((h) => file.startsWith(h));
}

export function hasAck(body) { return ACK_RE.test(body || ''); }

/** arquivos hot em comum (mesmo path EXATO) entre dois conjuntos. */
export function hotOverlap(selfFiles, otherFiles, hot, exclude) {
  const other = new Set(otherFiles);
  return selfFiles.filter((f) => other.has(f) && isHot(f, hot, exclude));
}

/**
 * Avalia colisões do PR atual vs outros PRs abertos.
 * @returns {{collisions: Array<{pr:number,title:string,files:string[]}>, blocked: boolean}}
 */
export function evaluate(self, others, hot, exclude) {
  const collisions = [];
  for (const o of others) {
    if (o.number === self.number) continue;
    const files = hotOverlap(self.files, o.files, hot, exclude);
    if (files.length) collisions.push({ pr: o.number, title: o.title, files });
  }
  const blocked = collisions.length > 0 && !hasAck(self.body);
  return { collisions, blocked };
}

/** Teto de `files` por PR no `gh pr list --json files` (GraphQL `files(first: 100)`). */
export const FILES_CAP = 100;
/** Quantos PRs abertos o --path pede ao gh. */
export const LIST_LIMIT = 500;

export function normPath(p) { return String(p || '').trim().split(String.fromCharCode(92)).join('/').replace(/^\.\//, ''); }

/**
 * Modo --path: quem, entre os PRs abertos, toca cada arquivo pedido?
 * @param {string[]} paths
 * @param {Array<{number:number,title:string,files:string[]}>} others
 * @param {{self?: number, limit?: number, returned?: number, filesCap?: number}} [opts]
 * @returns {{hits: Array<{path:string, prs:Array<{number:number,title:string}>}>, capped: number[], truncated: boolean, verdict: 'livre'|'tocado'|'nao-medi'}}
 */
export function pathProbe(paths, others, opts = {}) {
  const { self, limit = LIST_LIMIT, returned = others.length, filesCap = FILES_CAP } = opts;
  const alvo = paths.map(normPath).filter(Boolean);
  const outros = others.filter((o) => o.number !== self);
  const hits = alvo.map((path) => ({
    path,
    prs: outros.filter((o) => o.files.map(normPath).includes(path)).map((o) => ({ number: o.number, title: o.title })),
  }));
  const tocado = hits.some((h) => h.prs.length);
  const capped = outros.filter((o) => o.files.length >= filesCap).map((o) => o.number);
  const truncated = returned >= limit;
  const verdict = tocado ? 'tocado' : (truncated || capped.length ? 'nao-medi' : 'livre');
  return { hits, capped, truncated, verdict };
}

function mainPath(pathsArg, repoArgs) {
  const paths = pathsArg.split(',').map(normPath).filter(Boolean);
  if (!paths.length) { console.error('uso: --path=<arquivo>[,<arquivo>...] [--self=<N>]'); process.exit(2); }
  const self = arg('self') ? Number(arg('self')) : undefined;
  const selfBranch = arg('self-branch');
  let openList;
  try {
    const fx = arg('fixture');
    openList = fx
      ? JSON.parse(readFileSync(fx, 'utf8'))
      : JSON.parse(gh(['pr', 'list', '--state', 'open', '--json', 'number,title,files,headRefName', '-L', String(LIST_LIMIT), ...repoArgs]));
  } catch (e) { console.error(`✗ NÃO MEDI: falha ao listar PRs abertos (${e.message.split(/\r?\n/)[0]}). Não conclua "ninguém toca".`); process.exit(2); }
  const others = openList
    .filter((o) => !selfBranch || o.headRefName !== selfBranch)
    .map((o) => ({ number: o.number, title: o.title, files: (o.files || []).map((f) => f.path) }));
  const r = pathProbe(paths, others, { self, returned: openList.length });

  console.log(`dup-detector --path: ${others.length} PR(s) aberto(s) consultado(s)${self ? ` (excluído o #${self})` : ''}.`);
  for (const h of r.hits) {
    if (h.prs.length) { console.log(`  ⚠️ ${h.path}`); for (const p of h.prs) console.log(`      ↔ #${p.number} ${p.title}`); }
    else console.log(`  ✓ ${h.path} — nenhum PR aberto`);
  }
  if (r.verdict === 'tocado') {
    console.log('');
    console.log('TOCADO: há PR aberto no(s) arquivo(s) acima. Leia TODOS antes de abrir o seu (dono-é-sessão-viva, LC-19).');
    process.exit(1);
  }
  if (r.verdict === 'nao-medi') {
    if (r.truncated) console.error(`✗ NÃO MEDI: a lista veio com ${openList.length} PRs, o limite pedido — pode estar cortada.`);
    if (r.capped.length) console.error(`✗ NÃO MEDI: PR(s) no teto de ${FILES_CAP} arquivos, onde o arquivo pode estar escondido: ${r.capped.map((n) => '#' + n).join(' ')}.`);
    process.exit(2);
  }
  console.log('');
  console.log('LIVRE: nenhum PR aberto toca o(s) arquivo(s). Isto não cobre sessão sem PR (whats-active) nem o já mergeado (git log HEAD..origin/main).');
  process.exit(0);
}

// ── CLI (impuro — gh) ──
function arg(n, d = '') { const h = process.argv.find((a) => a.startsWith(`--${n}=`)); return h ? h.slice(n.length + 3) : d; }
function gh(a) { return execFileSync('gh', a, { encoding: 'utf8', maxBuffer: 64 * 1024 * 1024 }); }

function main() {
  const ROOT = process.cwd();
  const PR = arg('pr'); const repo = arg('repo');
  const repoArgs = repo ? ['--repo', repo] : [];
  const pathsArg = arg('path');
  if (pathsArg) return mainPath(pathsArg, repoArgs);
  if (!PR) { console.error('uso: --pr=<N> [--repo=owner/name]  |  --path=<arquivo>[,...] [--self=<N>]'); process.exit(2); }

  const { hot, exclude } = loadHotPaths(ROOT);
  if (!hot.length) { console.log(`ℹ️  ${HOT_PATHS_FILE} ausente/vazio — nada a checar.`); process.exit(0); }

  let self, openList;
  try {
    self = JSON.parse(gh(['pr', 'view', PR, '--json', 'number,title,body,files', ...repoArgs]));
    self.files = (self.files || []).map((f) => f.path);
    openList = JSON.parse(gh(['pr', 'list', '--state', 'open', '--json', 'number,title,files', '-L', '200', ...repoArgs]));
  } catch (e) { console.error('✗ falha gh (não bloqueia):', e.message); process.exit(0); }

  const others = openList.map((o) => ({ number: o.number, title: o.title, files: (o.files || []).map((f) => f.path) }));
  const { collisions, blocked } = evaluate(self, others, hot, exclude);

  if (!collisions.length) { console.log('✓ dup-detector: nenhum arquivo hot-path em comum com PR aberto.'); process.exit(0); }

  console.error(`⚠️ dup-detector: PR #${PR} toca arquivo(s) hot-path JÁ tocado(s) por outro PR aberto:`);
  for (const c of collisions) { console.error(`  ↔ #${c.pr} (${c.title}):`); for (const f of c.files) console.error(`      ${f}`); }
  if (blocked) {
    console.error(`\n✗ Possível DUPLICAÇÃO de trabalho entre sessões paralelas. Se NÃO é duplicata, adicione ao corpo do PR:`);
    console.error(`    Dedup-ack: <por que não é dup / qual PR é o canônico>`);
    console.error(`(proposta anti-duplicacao-work-claim-gate · advisory — não bloqueia o merge, mas registre o ack)`);
    process.exit(1);
  }
  console.log('\n✓ Dedup-ack presente no corpo — colisão reconhecida pelo autor.');
  process.exit(0);
}

if (process.argv[1] && pathToFileURL(process.argv[1]).href === import.meta.url) main();
