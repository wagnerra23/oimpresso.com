#!/usr/bin/env node
// @ts-check
// SELF-TEST PERSISTENTE — o rc que decide um alarme NÃO pode vir de um pipe.
//
// POR QUE EXISTE (incidente 2026-08-14, auditoria do ciclo pedida por [W]):
// `protection-drift.yml` fazia
//
//     node scripts/governance/protection-drift.mjs | tee /tmp/drift.txt
//     rc=$?                       # <- rc do TEE, não do node
//     if [ $rc -ne 0 ]; then echo "::warning::..." ; fi
//     exit $rc
//
// `tee` sai 0 quase sempre → rc era SEMPRE 0 → o `::warning::` NUNCA disparava e o
// `exit $rc` sempre saía 0. O detector achava o 🔴 (e havia um real: o required
// `PHP / Pest (KB · MySQL)` sumido do vivo) e ninguém era avisado. Gate mudo com cara
// de cobertura — §5 2026-08-13 ("num pipeline o rc é do ÚLTIMO comando").
//
// O conserto sem este teste seria "escrito+lembrado" (ADR 0256): nada impede a próxima
// sessão de reintroduzir `rc=$?` depois de um pipe. Este arquivo é a defesa persistente.
//
// CASOS (o "caso de uso" desta máquina — cada um tem controle-negativo pareado):
//   UC-1 RUIM  pipe + `rc=$?` cru                    → DEVE detectar
//   UC-2 BOM   pipe + `rc=${PIPESTATUS[0]}`          → NÃO detecta (o conserto canônico)
//   UC-3 BOM   `set -o pipefail` antes do pipe       → NÃO detecta (o outro conserto válido)
//   UC-4 BOM   REDIRECT (`> f 2>&1`) + `rc=$?`       → NÃO detecta  ← evita o FP real:
//              o step "Ratchet" do sdd-scorecard.yml usa redirect e está CORRETO.
//   UC-5 BOM   `a || b` (não é pipe)                 → NÃO detecta
//   UC-6 CORPUS o repo inteiro                       → 0 ocorrências (senão falha)
//
// FP MEDIDO ANTES DE ARMAR (regra canon "ligue a máquina" §4): no corpus real havia
// 1 única ocorrência de `rc=$?` e 0 falso-positivo — o detector não acusa nenhum step
// legítimo. Por isso ele pode ser HARD (falha o job) em vez de advisory.
//
// Rodar: node scripts/governance/workflow-pipe-rc.test.mjs — exit 0 = passa.

import { readFileSync, readdirSync, existsSync } from 'node:fs';
import { join, dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const HERE = dirname(fileURLToPath(import.meta.url));
const ROOT = resolve(HERE, '..', '..');
const WF_DIR = join(ROOT, '.github', 'workflows');

// ── LÓGICA PURA (testável com fixtures, sem tocar o disco) ───────────────────
/**
 * Acha `rc=$?` cuja linha anterior significativa é um PIPE, sem pipefail no bloco.
 * Devolve [{linha, anterior}] — vazio = nenhum rc mascarado.
 * @param {string} texto conteúdo de um workflow (ou fixture)
 */
export function acharRcMascarado(texto) {
  const L = String(texto).split(/\r?\n/);
  const achados = [];
  for (let i = 0; i < L.length; i++) {
    if (!/^\s*rc=\$\?\s*$/.test(L[i])) continue;
    // 1ª linha anterior que não seja vazia nem comentário
    let j = i - 1;
    while (j >= 0 && (!L[j].trim() || /^\s*#/.test(L[j]))) j--;
    const ant = L[j] || '';
    // pipe REAL: `|` seguido de comando — exclui `||` (OR) e `|` de bloco YAML
    const ehPipe = /\|\s*[a-zA-Z_./$]/.test(ant) && !/\|\|/.test(ant);
    if (!ehPipe) continue; // redirect (`> f 2>&1`) e `a || b` saem aqui — os FPs conhecidos
    // pipefail declarado no bloco acima neutraliza o problema
    const janela = L.slice(Math.max(0, i - 20), i).join('\n');
    if (/set\s+-o\s+pipefail|set\s+-eo\s+pipefail|set\s+-euo\s+pipefail/.test(janela)) continue;
    achados.push({ linha: i + 1, anterior: ant.trim() });
  }
  return achados;
}

// ── fixtures herméticas ──────────────────────────────────────────────────────
const FIX = {
  'UC-1 RUIM  pipe + rc=$? cru': {
    yml: '        run: |\n          node script.mjs | tee /tmp/x.txt\n          rc=$?\n          exit $rc\n',
    esperado: 1,
  },
  'UC-2 BOM   pipe + ${PIPESTATUS[0]}': {
    yml: '        run: |\n          node script.mjs | tee /tmp/x.txt\n          rc=${PIPESTATUS[0]}\n          exit $rc\n',
    esperado: 0,
  },
  'UC-3 BOM   set -o pipefail antes do pipe': {
    yml: '        run: |\n          set -o pipefail\n          node script.mjs | tee /tmp/x.txt\n          rc=$?\n',
    esperado: 0,
  },
  'UC-4 BOM   REDIRECT + rc=$? (o step Ratchet real do sdd-scorecard)': {
    yml: '        run: |\n          set +e\n          node scripts/governance/sdd-scorecard.mjs --ratchet > /tmp/r.txt 2>&1\n          rc=$?\n          cat /tmp/r.txt\n',
    esperado: 0,
  },
  'UC-5 BOM   `a || b` não é pipe': {
    yml: '        run: |\n          node script.mjs || echo "falhou"\n          rc=$?\n',
    esperado: 0,
  },
  'UC-1b RUIM comentário entre o pipe e o rc não esconde': {
    yml: '        run: |\n          node script.mjs | tee /tmp/x.txt\n          # comentário no meio\n          rc=$?\n',
    esperado: 1,
  },
};

let fails = 0;
const check = (nome, cond, extra = '') => {
  console.log(`${cond ? '[OK]' : '[FAIL]'} ${nome}${extra ? ' — ' + extra : ''}`);
  if (!cond) fails++;
};

console.log('workflow-pipe-rc — o rc que decide alarme não pode vir de pipe (§5 2026-08-13)\n');

for (const [nome, { yml, esperado }] of Object.entries(FIX)) {
  const got = acharRcMascarado(yml).length;
  check(nome, got === esperado, `detectou ${got}, esperado ${esperado}`);
}

// ── UC-6: o corpus real ──────────────────────────────────────────────────────
if (!existsSync(WF_DIR)) {
  check('UC-6 corpus: .github/workflows existe', false, 'diretório ausente — teste não pode medir');
} else {
  const arquivos = readdirSync(WF_DIR).filter((f) => /\.ya?ml$/.test(f));
  check('UC-6 corpus: há workflows pra varrer', arquivos.length > 0, `${arquivos.length} arquivo(s)`);
  const ofensores = [];
  for (const f of arquivos) {
    for (const a of acharRcMascarado(readFileSync(join(WF_DIR, f), 'utf8'))) {
      ofensores.push(`${f}:${a.linha} <- ${a.anterior.slice(0, 70)}`);
    }
  }
  check('UC-6 corpus: nenhum rc mascarado por pipe', ofensores.length === 0,
    ofensores.length ? `\n     ${ofensores.join('\n     ')}` : `${arquivos.length} workflows limpos`);
}

console.log(fails
  ? `\nFALHOU (${fails}) — use \${PIPESTATUS[0]} ou 'set -o pipefail' antes do pipe.`
  : '\nOK — fixtures boa/ruim discriminam e o corpus está limpo.');
process.exit(fails ? 1 : 0);
