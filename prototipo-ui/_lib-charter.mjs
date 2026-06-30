// _lib-charter.mjs — helpers compartilhados de leitura de charter/bundle.
//
// Fonte ÚNICA de `read`/`frontmatter`/`walk` para ancora.mjs + detectar-telas.mjs.
// Eram CÓPIAS divergentes nos dois arquivos (o `walk` tinha skip-sets diferentes:
// detectar-telas pulava `_BACKUP-NAO-USAR`, ancora NÃO). Dívida confessada em
// ancora.mjs ("extrair frontmatter/walk pra prototipo-ui/_lib-charter.mjs").
// Reduzir superfície ANTES de somar catraca (anti-bifurcação: 1 fato = 1 lugar).
//
// NÃO é importado de propósito por:
//   • .claude/hooks/block-ancora-no-olho.mjs — self-contained por design (ATAQUE 1:
//     import quebrado = fail-OPEN; o hook tem leitura de charter própria inline, fail-closed).
//   • prototipo-ui/detectar-telas.test.mjs — as cópias de frontmatter/walk lá são
//     DELIBERADAS (cross-check independente; importar a lib mataria a independência).

import { readFile, readdir } from 'node:fs/promises';
import { join } from 'node:path';

// lê arquivo ou null (nunca lança) — usado pra varrer charters sem try/catch espalhado.
export const read = async (p) => { try { return await readFile(p, 'utf8'); } catch { return null; } };

// extrai o frontmatter YAML-leve (chave: valor de 1 linha) do topo de um .md/.charter.md.
export function frontmatter(src) {
  if (!src) return {};
  const m = src.match(/^---\r?\n([\s\S]*?)\r?\n---/);
  if (!m) return {};
  const fm = {};
  for (const line of m[1].split(/\r?\n/)) {
    const mm = line.match(/^([a-z_]+):\s*(.*)$/i);
    if (mm) fm[mm[1].toLowerCase()] = mm[2].trim();
  }
  return fm;
}

// skip-set = UNIÃO dos dois consumidores. detectar-telas pulava `_BACKUP-NAO-USAR`,
// ancora não — reconciliado pra cá (ancora ganhar o skip é inócuo: só evita andar em
// backup, e nunca há charter canônico vivo lá). Parametrizável por segurança/teste.
export const SKIP_DIRS = new Set(['node_modules', '.git', '_arquivo', '_BACKUP-NAO-USAR', 'scraps', 'screenshots', 'uploads', 'assets']);

// varre `dir` recursivamente e devolve os caminhos de arquivo (pulando SKIP_DIRS).
export async function walk(dir, out = [], skip = SKIP_DIRS) {
  let entries;
  try { entries = await readdir(dir, { withFileTypes: true }); } catch { return out; }
  for (const e of entries) {
    if (skip.has(e.name)) continue;
    const full = join(dir, e.name);
    if (e.isDirectory()) await walk(full, out, skip);
    else out.push(full);
  }
  return out;
}
