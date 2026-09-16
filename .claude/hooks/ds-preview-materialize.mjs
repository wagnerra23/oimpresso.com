#!/usr/bin/env node
// Hook SessionStart — reporta quantas refs `_ds/` o shell do espelho carrega.
//
// **Cross-platform** (Node.js — Windows desktop / Linux CI / macOS).
//
// ─────────────────────────────────────────────────
// `_ds/<slug>/` é o DS BOUND do Claude Design, e é o estado CANÔNICO — não legado. A decisão
// está escrita no próprio shell VIVO, assinada: "DS VIVO: os tokens de IDENTIDADE vêm DIRETO do
// design system bound. Linkado, NÃO copiado → nunca mais apodrece com o tempo ([W] 2026-07-10)".
//
// FATO DATADO, porque a linha anterior deste bloco afirmava o contrário em tempo presente
// (LC-10): entre 2026-09-11 (#7224) e 2026-09-14 (#7261) o espelho carregou um shell reescrito
// À MÃO para `../../design-system/`. O #7261 reverteu — editar `prototipo-ui/cowork/**` é
// ilegítimo por construção (espelho de leitura, ADR 0374), e o próximo import desfaz. Medido em
// 2026-09-16: o shell tem 3 refs `_ds/` e ZERO `../../design-system/`.
//
// Por que este hook NÃO materializa cache: a duplicata física viola a D5 da ADR 0397 — medido,
// `cowork-ssot-guard` sai rc=1 com 8 violações (R2 + R4) no instante em que o `_ds/` existe.
// Resolver essas refs no preview local é trabalho do SERVIDOR de preview, não deste hook.
// ─────────────────────────────────────────────────
//
//   Escape valve: env `OIMPRESSO_DS_PREVIEW_OFF=1` → exit 0 imediato e silencioso.
//
// Refs: scripts/design/protocolo.config.mjs · ADR 0374 (espelho read-only) · ADR 0397 D4/D5.

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
  // NÃO instrua reescrever o espelho: a mensagem anterior mandava "Corrija para
  // ../../design-system/", que é exatamente o remendo do #7224 que o #7261 reverteu.
  console.log(`[ds-preview-materialize] shell referencia ${refs.length} arquivo(s) do DS bound em _ds/ — estado canônico ([W] 2026-07-10, "linkado, NÃO copiado"). NÃO reescreva o espelho. Cache paralelo não será criado: duplicata física viola a D5 (cowork-ssot-guard R2/R4).`);
  return 0; // SessionStart nunca bloqueia
}

const ehMain = (() => {
  try { return realpathSync(process.argv[1]) === realpathSync(fileURLToPath(import.meta.url)); } catch { return false; }
})();
if (ehMain) process.exit(main());
