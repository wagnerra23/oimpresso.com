#!/usr/bin/env node
// scripts/typecheck-baseline.mjs — catraca de erros do TypeScript (irmão de eslint-baseline.mjs).
//
// POR QUE EXISTE: `npm run typecheck` (tsc --noEmit) está VERMELHO no main com 298 erros
// (medido 2026-08-24) e NUNCA teve gate — varredura com rc conferido não achou menção a
// `typecheck`/`noEmit` em scripts/ nem em .github/workflows/, e o gates-registry não o lista.
// Sem catraca, os 298 não são teto: nada impede virarem 320. Corrigir tudo de uma vez é
// backfill de legado em massa, que morre no CI (proibicoes §5 2026-07-12) — então o caminho
// é o mesmo que o projeto já usa pro ESLint (2095) e pro Stylelint (419): congela o débito
// atual e falha só em REGRESSÃO, deixando a dívida ser paga quando o arquivo for tocado.
//
// RATCHET por `arquivo|códigoTS → contagem`, igual ao eslint-baseline:
//   - contagem subiu num par existente  → REGRESSÃO (falha)
//   - par novo (arquivo ou código novo) → REGRESSÃO (falha)
//   - contagem caiu / par sumiu         → melhoria (passa, e o --write recolhe o ganho)
//
// Comandos:
//   node scripts/typecheck-baseline.mjs --write   # grava config/typecheck-baseline.json
//   node scripts/typecheck-baseline.mjs           # valida (default): falha só em REGRESSÃO
//
// Flags (mesma convenção do eslint-baseline, pra fixture/self-test usar o MESMO code path):
//   --baseline <path>     baseline JSON alvo (default: config/typecheck-baseline.json)
//   --counts-from <json>  pula o tsc real e usa contagens {"arquivo|TSxxxx": n} pré-computadas.
//                         Existe SÓ pro bite-test provar que o COMPARADOR morde — o tsc é a lib
//                         (não apodrece do nosso lado); o que pode apodrecer é o diff vs baseline.
//
// Refs: ADR 0209 (ratchet gêmeo) · ADR 0271/0275 (gate nasce advisory, forward-only)

import { execSync } from 'node:child_process';
import { readFileSync, writeFileSync, existsSync } from 'node:fs';
import { resolve } from 'node:path';

function argVal(flag, def) {
  const i = process.argv.indexOf(flag);
  return i >= 0 && process.argv[i + 1] && !process.argv[i + 1].startsWith('--') ? process.argv[i + 1] : def;
}
const BASELINE_PATH = resolve(process.cwd(), argVal('--baseline', 'config/typecheck-baseline.json'));
const MODE_WRITE = process.argv.includes('--write');
const COUNTS_FROM = argVal('--counts-from', null);

// `path(linha,col): error TSxxxx: mensagem` — o formato estável do tsc.
// Separador normalizado pra `/`: no Windows o tsc emite `\`, e um baseline gravado
// numa plataforma tem que validar na outra (o CI é Linux). Sem isso, TODO par viraria
// "novo" ao trocar de SO — a classe de bug de §5 2026-08-07 (literal específico de SO).
const LINHA_ERRO = /^(.+?)\((\d+),(\d+)\):\s+error\s+(TS\d+):/;

function runTsc() {
  try {
    execSync('npx --no-install tsc --noEmit', { cwd: process.cwd(), encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'], shell: true });
    return ''; // exit 0 = zero erros
  } catch (e) {
    // tsc sai != 0 QUANDO ACHA ERRO — isso é o caso normal aqui, não falha de execução.
    // Mas "não conseguiu rodar" também cai neste catch, e os dois NÃO podem se confundir
    // (§5 2026-07-29: instrumento não pode afirmar sobre o que não conseguiu medir).
    const saida = `${e.stdout || ''}${e.stderr || ''}`;
    if (!saida.trim()) {
      console.error('✗ tsc não produziu saída — não é "zero erros", é falha de execução.');
      console.error('  (npx --no-install exige node_modules instalado; rode `npm ci` antes.)');
      process.exit(2);
    }
    return saida;
  }
}

function contar(saida) {
  const counts = {};
  for (const linha of saida.split(/\r?\n/)) {
    const m = LINHA_ERRO.exec(linha);
    if (!m) continue;
    const arquivo = m[1].trim().replace(/\\/g, '/');
    const chave = `${arquivo}|${m[4]}`;
    counts[chave] = (counts[chave] || 0) + 1;
  }
  return counts;
}

const counts = COUNTS_FROM
  ? JSON.parse(readFileSync(resolve(process.cwd(), COUNTS_FROM), 'utf8'))
  : contar(runTsc());

const total = Object.values(counts).reduce((a, b) => a + b, 0);

if (MODE_WRITE) {
  const baseline = {
    _meta: {
      gerado_por: 'scripts/typecheck-baseline.mjs --write',
      total,
      pares: Object.keys(counts).length,
      nota: 'Catraca: congela o débito atual de tsc e falha só em REGRESSÃO. Débito cai quando o arquivo for tocado — não fazer backfill em massa (proibicoes §5 2026-07-12).',
    },
    counts: Object.fromEntries(Object.entries(counts).sort(([a], [b]) => a.localeCompare(b))),
  };
  writeFileSync(BASELINE_PATH, `${JSON.stringify(baseline, null, 2)}\n`, 'utf8');
  console.log(`✓ baseline gravado: ${total} erro(s) em ${Object.keys(counts).length} par(es) arquivo|código → ${BASELINE_PATH}`);
  process.exit(0);
}

if (!existsSync(BASELINE_PATH)) {
  console.error(`✗ baseline ausente: ${BASELINE_PATH} — rode com --write primeiro.`);
  process.exit(2);
}

const baseline = JSON.parse(readFileSync(BASELINE_PATH, 'utf8'));
const base = baseline.counts || {};
const baseTotal = Object.values(base).reduce((a, b) => a + b, 0);

const regressoes = [];
for (const [chave, n] of Object.entries(counts)) {
  const antes = base[chave] || 0;
  if (n > antes) regressoes.push({ chave, antes, agora: n, novo: antes === 0 });
}

console.log('TypeScript baseline · VALIDATE mode');
console.log(`Total de erros atual: ${total}`);
console.log(`Total baseline: ${baseTotal} · Delta: ${total - baseTotal >= 0 ? '+' : ''}${total - baseTotal}`);

if (regressoes.length) {
  console.log(`\n✗ ${regressoes.length} regressão(ões) vs baseline:`);
  for (const r of regressoes.slice(0, 30)) {
    const [arquivo, codigo] = r.chave.split('|');
    console.log(`  ${r.novo ? '[NOVO]' : '[SUBIU]'} ${arquivo} — ${codigo}: ${r.antes} → ${r.agora}`);
  }
  if (regressoes.length > 30) console.log(`  … e mais ${regressoes.length - 30}`);
  console.log('\nDívida NOVA de tipos não entra. Corrija no PR, ou justifique e rode --write.');
  process.exit(1);
}

console.log('\n✅ Sem regressões vs baseline');
process.exit(0);
