#!/usr/bin/env node
// block-ancora-no-olho.mjs — PreToolUse(Read): print de auditoria NÃO é âncora de design.
//
// REGISTRADO em .claude/settings.json (PreToolUse, matcher "Read"). O REGISTRO é o que ativa
// (settings-ancora-registration.test.mjs guarda).
//
// Por que existe (incidente #7, 2026-06-30): o agente leu 'audit-financeiro.png' (PRINT DE
// AUDITORIA, estado velho) e o apresentou como "o design". Duas vezes.
//
// HISTÓRIA do critério (3 tentativas — a 3ª é esta):
//  1. DENYLIST de nome (audit-*.png) — furava por rename.
//  2. ALLOWLIST de PASTA (screenshots/_ds/ph-/kpi-) — review adversarial (workflow 2026-06-30,
//     33 modos de falha, 26 alta/crítica) PROVOU que backfira: os 9 charters reais declaram
//     related_prototype em .jsx/.html (ZERO png sob screenshots/), então a allowlist BLOQUEAVA
//     todo design real (falso-positivo) E DEIXAVA PASSAR screenshots/audit-old.png (bypass por
//     pasta) E o código nunca lia charter embora a mensagem prometesse (mentia).
//  3. ESTE — bloqueia só PRINT-SEMÂNTICO (audit/critique/scrap/tribunal/-old/reavalia) que NÃO
//     seja âncora declarada por charter, EM QUALQUER LUGAR (repo + fora). Imagem legítima de
//     design passa (zero backfire — a dor do Wagner). Lê o charter de verdade (a promessa).
//     Self-contained: NÃO importa ancora.mjs (ATAQUE 1: import quebrado = exit 1 ≠ 2 = fail-open).
//
// Residual HONESTO (workflow §4): um print renomeado pra nome inocente (sem termo de auditoria)
// passa; a confiança termina no charter (charter envenenado contorna); o guard só vê Read (Chrome/
// paste escapam). Isso é teto teórico (sem oráculo formal acima do contrato), não preguiça.
//
// Escape: OIMPRESSO_ANCORA_OK=1. Exit: 0 = continua | 2 = bloqueia (stderr vira a razão).

import { stdin, env } from 'node:process';
import { basename, resolve, dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { readdirSync, readFileSync } from 'node:fs';

const HERE = dirname(fileURLToPath(import.meta.url));            // <repo>/.claude/hooks
const PAGES = resolve(HERE, '..', '..', 'resources', 'js', 'Pages');

const IMG = /\.(png|jpe?g|webp|gif|bmp|svg)$/i;
// print-semântico: nomes que denunciam "estado velho sendo criticado" — NÃO é design vigente.
const PRINT_SEMANTICO = /audit|critique|cr[ií]tica|scrap|tribunal|-old|reavalia|antig|adversari/i;
export function ehPrintSemantico(p) { return !!p && IMG.test(p) && PRINT_SEMANTICO.test(basename(p)); }

// ── âncoras DECLARADAS pelos charters (a proveniência de verdade — lida, não adivinhada) ──
// Self-contained + fail-safe: se a leitura quebrar, set vazio → prints seguem bloqueados
// (fail-closed no caminho perigoso), nunca fail-open.
let _cache = null;
export function anchorsDeclaradas() {
  if (_cache) return _cache;
  const set = new Set();
  const walk = (dir) => {
    let ents;
    try { ents = readdirSync(dir, { withFileTypes: true }); } catch { return; }
    for (const e of ents) {
      const full = join(dir, e.name);
      if (e.isDirectory()) walk(full);
      else if (e.name.endsWith('.charter.md')) {
        let src; try { src = readFileSync(full, 'utf8'); } catch { continue; }
        const fm = src.match(/^---\r?\n([\s\S]*?)\r?\n---/);
        if (!fm) continue;
        for (const line of fm[1].split(/\r?\n/)) {
          const mm = line.match(/^(related_prototype|component):\s*(.+)$/i);
          if (!mm) continue;
          for (const tok of mm[2].match(/[\w.\-]+\.(png|jpe?g|webp|gif|svg|html|jsx|tsx)/gi) || [])
            set.add(basename(tok).toLowerCase());
        }
      }
    }
  };
  try { walk(PAGES); } catch { /* fail-safe */ }
  _cache = set;
  return set;
}

// decide: { nivel:'block', msg } | null  (decisão pura, testável)
export function decidir(toolName, toolInput) {
  if (toolName !== 'Read') return null;
  const fp = (toolInput && toolInput.file_path) || '';
  if (!fp || !IMG.test(fp)) return null;                 // não é imagem → segue
  if (!ehPrintSemantico(fp)) return null;                 // imagem de design legítima → PASSA (zero backfire)
  if (anchorsDeclaradas().has(basename(fp).toLowerCase())) return null; // charter declarou → âncora válida
  return {
    nivel: 'block',
    msg:
      `⛔ PRINT DE AUDITORIA NÃO É ÂNCORA: "${basename(fp)}" tem nome de estado-velho-sendo-criticado\n` +
      `   e NÃO é related_prototype de charter nenhum (li os charters em resources/js/Pages/**). Foi o erro #7.\n` +
      `   A âncora é COMPUTADA do charter: node prototipo-ui/ancora.mjs <Mod/Tela>\n` +
      `   Se ESTE arquivo for mesmo o design aprovado: declare-o em related_prototype no charter da tela.\n` +
      `   Escape (investigar o próprio print, não usar como design): OIMPRESSO_ANCORA_OK=1`,
  };
}

// compat: a string de bloqueio (ou null) — usada pelo eval/testes
export function razaoBloqueio(toolName, toolInput) { const d = decidir(toolName, toolInput); return d ? d.msg : null; }

async function readStdin() {
  const chunks = [];
  for await (const c of stdin) chunks.push(c);
  return Buffer.concat(chunks).toString('utf8');
}

async function main() {
  if (env.OIMPRESSO_ANCORA_OK === '1') process.exit(0);
  let raw;
  try { raw = await readStdin(); } catch { process.exit(0); }
  if (!raw) process.exit(0);
  let p;
  try { p = JSON.parse(raw); } catch { process.exit(0); }
  const d = decidir(p.tool_name || '', p.tool_input || {});
  if (d) { console.error(d.msg); process.exit(2); }
  process.exit(0);
}

const invokedDirectly = process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url);
if (invokedDirectly) main();
