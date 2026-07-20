#!/usr/bin/env node
// licoes-code-two-strikes.mjs — SessionStart (PORTE cross-plataforma do .ps1, advisory).
// Alarma quando uma classe de erro repetiu (Ocorrências ≥ threshold) e ainda NÃO virou
// defesa mecânica (Gate: none) — o gatilho do loop de aprendizado de código.
//
// ── CONTRATO (a âncora — não a implementação) ────────────────────────────────
// memory/LICOES_CODE.md (lista viva LC-*) + ADR 0256 (derivado+enforçado sobrevive):
// erro que reincide sem gate = candidato a virar defesa mecânica. Origem: sessão
// 2026-06-06 (Wagner: "quando deve ser acionado o aprendizado?").
//
// O ledger cobre erro de CÓDIGO **e de PROCESSO/comportamento de agente** (medição,
// derivação, oráculo errado) — o tema é "reincidência→defesa mecânica", processo é
// instância disso igual código (proposal two-strikes-cobre-processo, raio-X 2026-07-20).
// Cobertura só-ADVISORY (nudge/warn que NÃO bloqueia) conta como "sem defesa mecânica":
// a doutrina two-strikes exige defesa MECÂNICA (bloqueia/morde), não nudge que vaza.
// Declare `Gate: advisory — <hooks>` e a classe segue alarmando até virar sonda que morde.
//
// ── POR QUE .mjs (US-GOV-052 — port cross-plataforma dos hooks .ps1) ─────────
// O .ps1 legado SÓ roda no Windows do Wagner; no Mac/Linux do time MCP o alarme
// evapora em silêncio. Supersede licoes-code-two-strikes.ps1 (triagem #13, lote A).
//
// ADVISORY: exit 0 SEMPRE (nunca bloqueia). Fail-open em qualquer erro.
// Env: OIMPRESSO_LICOES_CODE_PATH (override do arquivo), OIMPRESSO_LICOES_THRESHOLD (default 2).
// Selftest: node .claude/hooks/licoes-code-two-strikes.mjs --selftest

import { spawnSync } from 'node:child_process';
import { pathToFileURL, fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';
import { readFileSync, existsSync } from 'node:fs';

/** caminho do LICOES_CODE.md (override por env pra teste). */
export function licoesPath(env = process.env) {
  if (env.OIMPRESSO_LICOES_CODE_PATH) return env.OIMPRESSO_LICOES_CODE_PATH;
  const repo = dirname(dirname(dirname(fileURLToPath(import.meta.url))));
  return join(repo, 'memory', 'LICOES_CODE.md');
}

export function threshold(env = process.env) {
  const v = String(env.OIMPRESSO_LICOES_THRESHOLD || '');
  return /^\d+$/.test(v) ? parseInt(v, 10) : 2;
}

/**
 * "sem defesa MECÂNICA" = vazio, none, nenhum, -, n/a
 * OU a entrada declara EXPLICITAMENTE que a cobertura é só advisory/parcial/insuficiente
 * (nudge/warn não bloqueiam → doutrina two-strikes ainda não satisfeita → segue alarmando).
 * Um nome-de-gate real ("mutation-gate (advisory, ...)") NÃO casa — só o prefixo declarado.
 */
export function semGate(g) {
  if (!g) return true;
  const s = String(g).trim();
  if (/^(none|nenhum|nenhuma|-|n\/a|na)$/i.test(s)) return true;
  if (/^(advisory|parcial|insuficiente)\b/i.test(s)) return true;
  return false;
}

/** parser PURO do markdown → lista de {id, titulo, ocorr, gate}. */
export function parseLicoes(text) {
  const licoes = [];
  let cur = null;
  for (const ln of String(text || '').split('\n')) {
    const h = /^##\s+(LC-\S+)\s*[-—]?\s*(.*)$/.exec(ln);
    if (h) {
      if (cur) licoes.push(cur);
      cur = { id: h[1], titulo: h[2].trim(), ocorr: 0, gate: '' };
      continue;
    }
    if (!cur) continue;
    const oc = /\*\*Ocorr.*?(\d+)/.exec(ln);
    const gt = /\*\*Gate.*?:\s*(.+?)\s*$/.exec(ln);
    if (oc) cur.ocorr = parseInt(oc[1], 10);
    else if (gt) cur.gate = gt[1].replace(/\*\*/g, '').trim();
  }
  if (cur) licoes.push(cur);
  return licoes;
}

/** classifica em alarme (≥threshold sem gate) e watch (<threshold sem gate). */
export function classificar(licoes, th) {
  const alarme = licoes.filter((l) => l.ocorr >= th && semGate(l.gate));
  const watch = licoes.filter((l) => l.ocorr < th && semGate(l.gate));
  return { alarme, watch };
}

const toAscii = (s) => String(s).replace(/[^\x20-\x7E]/g, '.');

export function formatBanner(alarme, watch, th) {
  if (alarme.length === 0 && watch.length === 0) return '';
  const out = ['', '=== LICOES [CODE] - gatilho two-strikes (audit loop de aprendizado) ==='];
  if (alarme.length) {
    out.push(`  [!] ${alarme.length} classe(s) repetiram (>= ${th}x) e NAO tem gate. PROMOVER A DEFESA MECANICA:`);
    for (const a of alarme) out.push(`      ${a.id} - ${toAscii(a.titulo)}  (${a.ocorr}x, sem gate)`);
    out.push('  ACAO: avise o Wagner e proponha o gate/hook/baseline que mata essa classe.');
    out.push("  (Quando criar o gate, troque 'Gate: none' pelo nome dele em LICOES_CODE.md - o alarme some.)");
  }
  if (watch.length) out.push(`  [.] ${watch.length} classe(s) em WATCH (sem gate, < ${th}x). Se reincidirem, viram alarme.`);
  out.push('');
  return out.join('\n');
}

async function main() {
  try {
    const p = licoesPath();
    if (!existsSync(p)) process.exit(0);
    const text = readFileSync(p, 'utf8');
    const th = threshold();
    const { alarme, watch } = classificar(parseLicoes(text), th);
    const banner = formatBanner(alarme, watch, th);
    if (banner) process.stdout.write(banner + '\n');
    process.exit(0);
  } catch { process.exit(0); }
}

// entry-point (pathToFileURL — cross-platform, backslash do Windows não quebra)
if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  if (process.argv.includes('--selftest')) {
    const test = new URL('./licoes-code-two-strikes.test.mjs', import.meta.url);
    const r = spawnSync(process.execPath, [test.pathname], { stdio: 'inherit' });
    process.exit(r.status ?? 1);
  }
  main();
}
