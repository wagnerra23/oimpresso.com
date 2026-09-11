#!/usr/bin/env node
// Hook SessionStart — compatibilidade para detectar shells antigos que ainda referenciam `_ds/`.
//
// **Cross-platform** (Node.js — Windows desktop / Linux CI / macOS).
//
// ─────────────────────────────────────────────────
// O shell ativo de Wagner referencia `../../design-system/` diretamente. Materializar `_ds/`
// recriaria a duplicata física removida em 2026-09-11; por isso este hook não escreve mais.
// ─────────────────────────────────────────────────
//
// Se um shell antigo ainda contiver `_ds/`, o hook avisa para corrigir a referência na origem.
//
//   Escape valve: env `OIMPRESSO_DS_PREVIEW_OFF=1` → exit 0 imediato e silencioso.
//
// Refs: scripts/design/protocolo.config.mjs · ADR 0374 (espelho read-only).

import { existsSync, readFileSync, realpathSync } from 'node:fs';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';
export const SHELL_REL = join('prototipo-ui', 'cowork', 'Wagner', 'oimpresso.com.html');

/**
 * Deriva do html do shell os paths `_ds/<id>/<arquivo>` referenciados (href/src).
 * PURA (testável sem disco). Mesmo regex de família do `previewDsPlan()` do produtor.
 * @param {string} shellHtml
 * @returns {string[]} paths relativos a `prototipo-ui/cowork/`, únicos, na ordem do html
 */
export function refsDoShell(shellHtml) {
  const out = [];
  for (const m of String(shellHtml).matchAll(/(?:href|src)="(_ds\/[^"?]+)(?:\?[^"]*)?"/g)) {
    if (!out.includes(m[1])) out.push(m[1]);
  }
  return out;
}

/**
 * Decide se o cache precisa ser materializado.
 * PURA: recebe os refs e um predicado de existência.
 * @param {string[]} refs
 * @param {(relPath: string) => boolean} existe
 * @returns {{precisa: boolean, faltam: string[]}}
 */
export function precisaMaterializar(refs, existe) {
  const faltam = refs.filter((p) => !existe(p));
  return { precisa: faltam.length > 0, faltam };
}

export function main(cwd = process.cwd()) {
  if (process.env.OIMPRESSO_DS_PREVIEW_OFF === '1') return 0;
  const shell = join(cwd, SHELL_REL);
  if (!existsSync(shell)) return 0; // worktree sem espelho — nada a fazer
  let html;
  try { html = readFileSync(shell, 'utf8'); } catch { return 0; }
  const refs = refsDoShell(html);
  if (refs.length === 0) return 0;
  console.log(`[ds-preview-materialize] shell legado referencia ${refs.length} arquivo(s) em _ds/. Corrija para ../../design-system/; cache paralelo não será criado.`);
  return 0; // SessionStart nunca bloqueia
}

const ehMain = (() => {
  try { return realpathSync(process.argv[1]) === realpathSync(fileURLToPath(import.meta.url)); } catch { return false; }
})();
if (ehMain) process.exit(main());
