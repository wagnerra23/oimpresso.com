#!/usr/bin/env node
// loop-fechar-check.mjs — SessionStart (PORTE cross-plataforma do .ps1, advisory).
// Rotina idempotente "Fechar o Loop do IA-OS": lê o manifesto, resolve o estado de cada
// item (feito? por arquivo/flag) e imprime o próximo pendente.
//
// ── CONTRATO (a âncora — não a implementação) ────────────────────────────────
// AUDIT IA-OS 2026-05-29 (Wagner pediu rotina idempotente atrelada ao brief).
// Manifesto = fonte da verdade: .claude/loop-fechar-o-loop.json.
// REGRA DURA: NUNCA toca Brain B / autonomia ADS (decisão Wagner — 2º cérebro off).
//
// ── POR QUE .mjs (US-GOV-052 — port cross-plataforma dos hooks .ps1) ─────────
// O .ps1 legado SÓ roda no Windows do Wagner; no Mac/Linux do time MCP a rotina
// evapora em silêncio. Supersede loop-fechar-check.ps1 (triagem #14, lote A).
//
// ADVISORY: exit 0 SEMPRE. Fail-open em qualquer erro.
// Selftest: node .claude/hooks/loop-fechar-check.mjs --selftest

import { spawnSync } from 'node:child_process';
import { pathToFileURL, fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';
import { readFileSync, existsSync } from 'node:fs';

const HOOK_DIR = dirname(fileURLToPath(import.meta.url));
export const MANIFEST = join(HOOK_DIR, '..', 'loop-fechar-o-loop.json');
const REPO = join(HOOK_DIR, '..', '..');

/** resolve o done-state de UM item (puro se exists injetado). */
export function itemDone(it, repoRoot = REPO, exists = existsSync) {
  if (!it || !it.detect) return false;
  if (it.detect.tipo === 'manual') return Boolean(it.done);
  if (it.detect.tipo === 'file_any' && Array.isArray(it.detect.paths)) {
    return it.detect.paths.some((p) => exists(join(repoRoot, p)));
  }
  return false;
}

/** normaliza a lista de itens do manifesto → [{ordem,gap,titulo,done,prio,custo,aprova,nota}]. */
export function resolverItens(manifest, repoRoot = REPO, exists = existsSync) {
  const itens = (manifest && Array.isArray(manifest.itens) ? manifest.itens : []).map((it) => ({
    ordem: parseInt(it.ordem, 10) || 0,
    gap: it.gap,
    titulo: it.titulo,
    done: itemDone(it, repoRoot, exists),
    prio: it.prioridade,
    custo: it.custo_recorrente,
    aprova: Boolean(it.precisa_aprovacao_wagner),
    nota: it.nota_aprovacao,
  }));
  itens.sort((a, b) => a.ordem - b.ordem);
  return itens;
}

export function formatBanner(itens) {
  if (!itens.length) return '';
  const pendentes = itens.filter((i) => !i.done);
  const out = ['', '=== ROTINA: FECHAR O LOOP DO IA-OS (audit 2026-05-29) ==='];
  for (const i of itens) out.push(`  ${i.done ? '[OK]' : '[--]'} #${i.gap} ${i.prio} - ${i.titulo}`);
  if (pendentes.length === 0) {
    out.push('', '  LOOP FECHADO - nada a fazer. IA-OS com painel + alarme + LGPD no ar.', "  (Para reabrir um item, mude 'done' no manifesto.)");
  } else {
    const next = pendentes[0];
    out.push('', `  PROXIMO PENDENTE: #${next.gap} - ${next.titulo}`, `  Custo recorrente: ${next.custo}`);
    if (next.aprova) out.push('  >> EXIGE APROVACAO DO WAGNER antes de avancar (custo/risco). Nota:', `     ${next.nota}`);
    out.push('', '  ACAO CLAUDE: avise o Wagner que ha item do loop pendente e pergunte',
      `  'quer que eu faca o #${next.gap} agora?'. NAO comece sem ele confirmar.`,
      '  NUNCA inclua Brain B / autonomia ADS nesta rotina (decisao Wagner).');
  }
  out.push('');
  return out.join('\n');
}

async function main() {
  try {
    if (!existsSync(MANIFEST)) process.exit(0);
    let manifest;
    try { manifest = JSON.parse(readFileSync(MANIFEST, 'utf8')); } catch { process.exit(0); }
    if (!manifest || !Array.isArray(manifest.itens)) process.exit(0);
    const banner = formatBanner(resolverItens(manifest));
    if (banner) process.stdout.write(banner + '\n');
    process.exit(0);
  } catch { process.exit(0); }
}

// entry-point (pathToFileURL — cross-platform, backslash do Windows não quebra)
if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  if (process.argv.includes('--selftest')) {
    const test = new URL('./loop-fechar-check.test.mjs', import.meta.url);
    const r = spawnSync(process.execPath, [test.pathname], { stdio: 'inherit' });
    process.exit(r.status ?? 1);
  }
  main();
}
