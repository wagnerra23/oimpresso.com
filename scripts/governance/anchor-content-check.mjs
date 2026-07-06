#!/usr/bin/env node
// @ts-check
/**
 * anchor-content-check.mjs — sentinela de CONTEÚDO da âncora de design.
 *
 * Fecha o buraco que o Wagner pegou no instinto (2026-07-06): o `ancora.mjs` +
 * `block-ancora-no-olho.mjs` provam que a âncora está DECLARADA no charter
 * (proveniência), mas NUNCA abrem o arquivo pra ver se o conteúdo BATE com a tela
 * (correção). Charter apontando pro shell do app ou pra arquivo que sumiu passava limpo.
 * Medido no dia da criação: 2 de 9 âncoras reais estavam podres e nenhum gate pegou —
 * Financeiro/Unificado→oimpresso.com.html (shell) · Financeiro/Fluxo→Financeiro.html (sumiu).
 *
 * "Presença ≠ correção" (L-24 / adversário 2026-07-06). Este sentinela abre a âncora.
 *
 * Classificação (determinística, zero LLM):
 *   MISSING  — o arquivo da âncora não existe (sumiu num refactor). Podre.
 *   SHELL    — .html que linka ≥ SHELL_MIN_CSS stylesheets = índice/container do app
 *              inteiro, não a tela. Podre (deve apontar pro fonte da tela específica).
 *   NO-MODULE— o arquivo existe mas não menciona NENHUMA vez o nome do módulo da tela.
 *              Suspeita (âncora provavelmente errada).
 *   OK       — arquivo existe, é fonte de tela (não-shell) e menciona o módulo.
 *
 * NÃO é gate required (lei ADR 0314 — advisory; report). Com --check sai 1 se houver
 * âncora MISSING/SHELL (o sinal duro); NO-MODULE é warn (pode ser falso-positivo de
 * nomenclatura). Roda no design-memory-gate.yml (advisory) ao lado do ancora selftest.
 *
 * Uso:
 *   node scripts/governance/anchor-content-check.mjs            # relatório
 *   node scripts/governance/anchor-content-check.mjs --check    # exit 1 se MISSING/SHELL
 */

import { readFileSync, readdirSync, statSync, existsSync } from 'node:fs';
import { join } from 'node:path';

const ROOT = process.cwd();
const PAGES = join(ROOT, 'resources', 'js', 'Pages');
const COWORK = join(ROOT, 'prototipo-ui', 'cowork');
export const SHELL_MIN_CSS = 10; // ≥10 <link stylesheet> = índice do app, não uma tela

/** Extrai o caminho de arquivo (.jsx/.html) do valor de related_prototype, ou null se é prosa. */
export function anchorFile(val) {
  if (!val) return null;
  if (/^n\/a\b/i.test(val) || /MIS-ANCHOR|removido/i.test(val)) return null;
  const m = val.match(/([\w.\-]+\.(?:jsx|html))/i); // nome do arquivo (com ou sem dir)
  return m ? m[1] : null;
}

/** Conta <link rel="stylesheet"> num HTML (assinatura de shell/índice do app). */
export function stylesheetCount(text) {
  return (text.match(/<link[^>]+rel=["']stylesheet["']/gi) || []).length;
}

/** Classifica (pura, testável) a partir dos fatos. */
export function classifyAnchor({ exists, isHtml, stylesheetLinks, moduleHits }) {
  if (!exists) return 'MISSING';
  if (isHtml && stylesheetLinks >= SHELL_MIN_CSS) return 'SHELL';
  if (moduleHits === 0) return 'NO-MODULE';
  return 'OK';
}

function walkCharters(dir, acc = []) {
  if (!existsSync(dir)) return acc;
  for (const e of readdirSync(dir)) {
    const f = join(dir, e);
    if (statSync(f).isDirectory()) walkCharters(f, acc);
    else if (f.endsWith('.charter.md')) acc.push(f);
  }
  return acc;
}

function main() {
  const strict = process.argv.includes('--check');
  const rows = [];
  for (const charter of walkCharters(PAGES)) {
    const t = readFileSync(charter, 'utf8');
    const m = t.match(/^related_prototype:\s*(.+)$/m);
    if (!m) continue;
    const file = anchorFile(m[1].trim());
    if (!file) continue; // prosa não-resolvível — fora do escopo deste sentinela
    const rel = charter.slice(PAGES.length + 1).replace(/\.charter\.md$/, '').replace(/\\/g, '/');
    const modulo = rel.split('/')[0].toLowerCase();
    const abs = join(COWORK, file.split('/').pop());
    const exists = existsSync(abs);
    let isHtml = /\.html$/i.test(file), stylesheetLinks = 0, moduleHits = 0;
    if (exists) {
      const body = readFileSync(abs, 'utf8');
      stylesheetLinks = stylesheetCount(body);
      moduleHits = (body.toLowerCase().match(new RegExp(modulo, 'g')) || []).length;
    }
    rows.push({ tela: rel, file, veredito: classifyAnchor({ exists, isHtml, stylesheetLinks, moduleHits }) });
  }

  const podre = rows.filter((r) => r.veredito === 'MISSING' || r.veredito === 'SHELL');
  const suspeita = rows.filter((r) => r.veredito === 'NO-MODULE');
  const ok = rows.filter((r) => r.veredito === 'OK');

  console.log(`\n  ÂNCORA DE DESIGN — checagem de conteúdo (${rows.length} charters com âncora resolvível)\n`);
  for (const r of podre) console.log(`  ⛔ ${r.veredito.padEnd(8)} ${r.tela}  →  ${r.file}`);
  for (const r of suspeita) console.log(`  🟡 ${r.veredito.padEnd(8)} ${r.tela}  →  ${r.file}`);
  console.log(`\n  ⛔ podre (sumiu/shell): ${podre.length} · 🟡 suspeita (0 módulo): ${suspeita.length} · ✓ ok: ${ok.length}\n`);

  if (strict && podre.length) {
    console.error(`✗ ${podre.length} âncora(s) PODRE(s) — charter aponta pro arquivo errado. Corrija o related_prototype pro fonte real da tela.`);
    process.exit(1);
  }
  console.log('✓ sem âncora podre (MISSING/SHELL).');
}

if (process.argv[1] && process.argv[1].endsWith('anchor-content-check.mjs')) main();
