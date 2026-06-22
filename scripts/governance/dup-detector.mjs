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

// ── CLI (impuro — gh) ──
function arg(n, d = '') { const h = process.argv.find((a) => a.startsWith(`--${n}=`)); return h ? h.slice(n.length + 3) : d; }
function gh(a) { return execFileSync('gh', a, { encoding: 'utf8', maxBuffer: 64 * 1024 * 1024 }); }

function main() {
  const ROOT = process.cwd();
  const PR = arg('pr'); const repo = arg('repo');
  const repoArgs = repo ? ['--repo', repo] : [];
  if (!PR) { console.error('uso: --pr=<N> [--repo=owner/name]'); process.exit(2); }

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
