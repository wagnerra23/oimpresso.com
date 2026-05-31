#!/usr/bin/env node
// backlog.mjs — transforma os design-report.json num BACKLOG priorizado de fixes.
// Calcula a ALAVANCAGEM real: quantas telas cruzam 70 só corrigindo as regras mecanizadas,
// e qual codemod tem mais alcance. Agrupa por regra → lote → PR sugerido.
// É a 3ª peça do toolset (consolidate=placar · backlog=plano de ação). Determinístico, zero LLM.
//
// Uso: node prototipo-ui/audit/backlog.mjs

import { readdirSync, readFileSync, writeFileSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const HERE = dirname(fileURLToPath(import.meta.url));
const REPORTS = join(HERE, 'reports');

const WEIGHT = { R1: 3, R2: 3, R3: 1, R4: 1, R6: 1, R7: 2, R9: 2 };
const META = {
  R1: { name: 'cor crua → token', fix: 'codemod hex/oklch/rgb → token DS (roxo 295)', effort: 'M', safe: 'visual · gate' },
  R2: { name: 'elemento nativo → DS', fix: '<select>/<input>/<textarea>/<table> → @/Components/ui', effort: 'M', safe: 'visual · gate' },
  R3: { name: 'localStorage sem prefixo', fix: 'prefixar `oimpresso.<mod>.*`', effort: 'S', safe: '✅ INVISÍVEL' },
  R4: { name: 'ícone não-lucide / svg inline', fix: 'svg/lib → lucide-react', effort: 'S', safe: 'visual leve' },
  R6: { name: 'emoji', fix: 'emoji → ícone lucide', effort: 'S', safe: 'visual leve' },
  R7: { name: 'status bg-fill', fix: 'bg-*-100 → dot+texto (confirmar AP7 com agente)', effort: 'M', safe: 'visual · heurística' },
  R9: { name: '<main> aninhado', fix: '<main> → <div role="region">', effort: 'S', safe: '✅ estrutural' },
};

const reports = readdirSync(REPORTS)
  .filter((f) => f.endsWith('.design-report.json'))
  .map((f) => JSON.parse(readFileSync(join(REPORTS, f), 'utf8')));

const ceilingOf = (r) => 100 - Math.min((r.ds_violations && r.ds_violations.total) || 0, 20);
const mechFails = (r) => r.rules.filter((x) => x.mechanized && x.status === 'fail').map((x) => x.id);

// por regra: telas que falham
const byRule = {};
for (const id of Object.keys(WEIGHT)) byRule[id] = [];
for (const r of reports) for (const id of mechFails(r)) byRule[id].push(r.screen);

// métricas de alavancagem
const below70 = reports.filter((r) => r.nota < 70);
const cross70 = below70.filter((r) => ceilingOf(r) >= 70); // só mecanizado já leva a Advanced
const quickWins = reports.filter((r) => mechFails(r).length === 1); // 1 PR → passa mecanizado
const median = (arr) => { const s = [...arr].sort((a, b) => a - b); return s.length ? s[Math.floor(s.length / 2)] : 0; };

// lotes ordenados por alcance (nº telas) ponderado por (esforço baixo primeiro)
const effRank = { S: 0, M: 1, L: 2 };
const lotes = Object.entries(byRule)
  .map(([id, screens]) => ({ id, ...META[id], n: screens.length, screens }))
  .filter((l) => l.n > 0)
  .sort((a, b) => b.n - a.n || effRank[a.effort] - effRank[b.effort]);

const L = [];
L.push('# BACKLOG de fixes — derivado da worklist (regras mecanizadas)');
L.push('');
L.push('> **GERADO** por `backlog.mjs` — proposta pra Wagner aprovar antes de virar task MCP (publication-policy). Não edita à mão.');
L.push('> A alavancagem abaixo é **só das 7 regras mecanizadas** — as julgadas (R5/R8/R10) entram na Fase 2.');
L.push('');
L.push('## A pergunta "pode melhorar?" em número');
L.push('');
L.push(`- **${reports.length}** telas pontuadas · **${below70.length}** abaixo de 70 na métrica mecanizada (dívida de conformidade-DS).`);
L.push(`- **${quickWins.length}** telas falham **1 única regra** mecanizada → 1 PR cirúrgico fecha cada.`);
L.push('- A maior alavancagem é **lote por regra** (codemod 1× aplica em N telas) — ver tabela abaixo.');
L.push('');
L.push('> ⚠️ **Honestidade:** a nota mecanizada subir não é a nota do **board** (UX holística) subir — o board pesa dimensões (Speed-to-task, hierarquia, affordance) que o mecanizado ignora. Fix mecanizado é **necessário, não suficiente**: telas-stub (ex `Jana/Brief`, `Repair/JobSheet`) precisam da Fase 2 + trabalho de UX, não só codemod. Os lotes abaixo fecham a **conformidade-DS**, que é metade do caminho pro ≥70 do board.');
L.push('');
L.push('## Lotes por regra (codemod 1× aplica em N telas — ordenado por alcance)');
L.push('');
L.push('| Regra | O que é | Telas | Esforço | Risco | PR sugerido (fix) |');
L.push('|---|---|--:|:--:|---|---|');
for (const l of lotes) {
  L.push(`| **${l.id}** | ${l.name} | ${l.n} | ${l.effort} | ${l.safe} | ${l.fix} |`);
}
L.push('');
L.push('## Quick-wins (1 regra só — PR cirúrgico)');
L.push('');
L.push('| Tela | Nota | Regra única | ds/* |');
L.push('|---|--:|:--:|--:|');
for (const r of quickWins.sort((a, b) => a.nota - b.nota)) {
  L.push(`| \`${r.screen}\` | ${r.nota} | ${mechFails(r)[0]} | ${(r.ds_violations || {}).total || 0} |`);
}
L.push('');
L.push('## Telas <70 na métrica mecanizada (dívida de conformidade-DS — atacar primeiro)');
L.push('');
L.push('| Tela | Nota mec | Regras a fechar | ds/* |');
L.push('|---|--:|---|--:|');
for (const r of below70.sort((a, b) => a.nota - b.nota)) {
  L.push(`| \`${r.screen}\` | ${r.nota} | ${mechFails(r).join(' ')} | ${(r.ds_violations || {}).total || 0} |`);
}
L.push('');
L.push('## Sequência recomendada (receita 1× → lote)');
L.push('');
L.push('1. **Lotes seguros primeiro** (✅ R3 localStorage + R9 `<main>`) — invisíveis/estruturais, fecham sem gate visual.');
L.push('2. **R6 emoji + R4 ícone** — visual leve, codemod → lucide.');
L.push('3. **R1 cor crua** — maior alcance, mas visual → passa pelo gate (screenshot Wagner).');
L.push('4. **R7 status-fill** — heurística ampla, agente Fase 2 confirma AP7 real antes do codemod.');
L.push('5. **R2 nativo → DS** — por tela (mapear props), casa com a Matriz `ds/*` já existente.');
L.push('');
L.push('> Cruzar com [PLANO-DESIGN-TELAS-2026-05-31](../../memory/governance/scorecards/PLANO-DESIGN-TELAS-2026-05-31.md): os lotes acima são a **versão mecanizada/medida** dos 9 padrões P1-P9 daquele plano (P1 cor = R1, P5 nativo = R2, P6 emoji = R6...). Telas <70 aqui que NÃO estavam nas 44 do board = candidatas novas (conformidade que a nota holística mascarava).');

const out = L.join('\n');
writeFileSync(join(HERE, 'BACKLOG-FIXES.md'), out, 'utf8');

console.log(`[backlog] ${reports.length} telas · ${below70.length} <70 mec · ${quickWins.length} quick-wins (1 regra)`);
console.log('[backlog] alcance por regra:', lotes.map((l) => `${l.id}=${l.n}`).join(' '));
console.log('[backlog] escrito: BACKLOG-FIXES.md');
