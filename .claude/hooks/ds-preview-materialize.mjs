#!/usr/bin/env node
// Hook SessionStart — materializa o cache de preview do Design System (`prototipo-ui/cowork/_ds/`).
//
// **Cross-platform** (Node.js — Windows desktop / Linux CI / macOS).
//
// ─────────────────────────────────────────────────
// POR QUE EXISTE ([W] 2026-09-02: "`_ds/` existe mas está vazio (é cache derivado, gitignored).
// tem que resolver isso pois não pode ser assim."):
//   O shell do espelho (`prototipo-ui/cowork/oimpresso.com.html`) linka `_ds/<id>/colors_and_type.css`,
//   `cockpit_domains.css`, `_ds_bundle.js` e 7 fontes. O `.gitignore` do espelho exclui `_ds/`
//   DE PROPÓSITO (é build de preview; a FONTE versionada é `scripts/design-sync/mirror-snapshot/`,
//   README lá). O único produtor é `cowork-mirror-freshness.mjs --preview-ds` — e NINGUÉM o
//   invocava sozinho (medido 2026-09-02: zero ocorrências em .claude/, .github/, package.json).
//   Resultado: todo worktree novo abria o espelho SEM tokens/fontes/bundle — o "falta css"
//   silencioso de 2026-08-13 de novo, agora por construção em cada sessão fresca.
//
//   Regra "LIGUE A MÁQUINA" (proibicoes.md §Sempre fazer): máquina que existe e ninguém invoca
//   é bug. Este hook é só o INVOCADOR do dono; não duplica lógica de materialização.
// ─────────────────────────────────────────────────
//
// O QUE FAZ (SessionStart, exit 0 sempre — nunca bloqueia a sessão):
//   1. Lê o shell do espelho e deriva os arquivos `_ds/<id>/…` que ele referencia (mesma fonte
//      de verdade que o `--preview-ds` usa: o <link>/<script> do html, não uma lista à mão).
//   2. Se TODOS já existem no `_ds/` → SILÊNCIO (zero fricção, ~1ms).
//   3. Se falta algum → roda `node scripts/governance/cowork-mirror-freshness.mjs --preview-ds`
//      (local, ~0,35s, lê só o mirror-snapshot versionado — sem DesignSync, sem rede) e imprime
//      uma linha com o resultado. Fontes `.woff2` referenciadas por `url()` dentro do CSS não
//      aparecem no html; o `--preview-ds` as repõe junto (seu plano é o grafo recursivo).
//   4. Se o shell não existe (worktree sem espelho) → silêncio.
//
//   Escape valve: env `OIMPRESSO_DS_PREVIEW_OFF=1` → exit 0 imediato e silencioso.
//
// Refs: prototipo-ui/protocolo.config.mjs (dono dos comandos; DS_RUNTIME_SNAPSHOT_DIR) ·
//       scripts/design-sync/mirror-snapshot/README.md · ADR 0374 (espelho read-only).

import { spawnSync } from 'node:child_process';
import { existsSync, readFileSync, realpathSync } from 'node:fs';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';

export const SHELL_REL = join('prototipo-ui', 'cowork', 'oimpresso.com.html');
export const DS_DIR_REL = join('prototipo-ui', 'cowork', '_ds');
export const PRODUTOR = ['scripts/governance/cowork-mirror-freshness.mjs', '--preview-ds'];

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
  const { precisa, faltam } = precisaMaterializar(refs, (p) => existsSync(join(cwd, 'prototipo-ui', 'cowork', p)));
  if (!precisa) return 0;

  const r = spawnSync(process.execPath, PRODUTOR, { cwd, encoding: 'utf8', timeout: 30_000 });
  const resumo = (r.stdout || '').split('\n').find((l) => /reposto\(s\)/.test(l))?.trim();
  if (r.status === 0) {
    console.log(`[ds-preview-materialize] _ds/ do espelho estava incompleto (${faltam.length} de ${refs.length} refs do shell ausentes) → ${resumo || 'materializado'} (cache gitignored, fonte = scripts/design-sync/mirror-snapshot/).`);
  } else {
    const erro = ((r.stderr || '') + (r.stdout || '')).trim().split('\n').slice(-3).join(' | ');
    console.log(`[ds-preview-materialize] _ds/ incompleto (${faltam.length} refs) e o produtor FALHOU (exit ${r.status}): ${erro}. Rode: node ${PRODUTOR.join(' ')}`);
  }
  return 0; // SessionStart nunca bloqueia
}

const ehMain = (() => {
  try { return realpathSync(process.argv[1]) === realpathSync(fileURLToPath(import.meta.url)); } catch { return false; }
})();
if (ehMain) process.exit(main());
