#!/usr/bin/env node
// @ts-check
/**
 * screen-grade-seed.mjs — materializa os scorecards YAML por tela a partir do
 * baseline canônico screen-grades-baseline-2026-05-30.json (método SCREEN-GRADE,
 * SCREEN-GRADE-METODO.md §5).
 *
 * Por quê: o método prevê 1 YAML por tela em scorecards/screens/<modulo>-<tela>.yaml
 * (com `baseline_anterior` pro ratchet), mas só existe o JSON agregado de 30/mai.
 * Sem os YAMLs individuais, o ratchet (screen-grades-ratchet.mjs) não tem o que
 * comparar. Este seed transforma o agregado → individuais, SEM inventar nota
 * (usa a que o agente LLM-as-judge já produziu).
 *
 * Determinístico: mesma entrada → mesma saída. Idempotente: re-rodar sobrescreve.
 * NÃO recomputa a nota (16 dims subjetivas são do agente, não deste script).
 *
 * Uso:
 *   node scripts/qa/screen-grade-seed.mjs            # gera os YAMLs
 *   node scripts/qa/screen-grade-seed.mjs --dry-run  # só conta, não escreve
 */

import { readFileSync, writeFileSync, mkdirSync, existsSync } from 'node:fs';
import { join } from 'node:path';

const ROOT = process.cwd();
const BASELINE = join(ROOT, 'memory', 'governance', 'scorecards', 'screen-grades-baseline-2026-05-30.json');
const OUT_DIR = join(ROOT, 'memory', 'governance', 'scorecards', 'screens');
const DRY = process.argv.includes('--dry-run');

const ESFORCO = { S: 'baixo', M: 'medio', L: 'alto' };
const q = (s) => JSON.stringify(s ?? ''); // string YAML-safe (aspas duplas escapadas, válido em YAML)

if (!existsSync(BASELINE)) {
  console.error(`✗ baseline não encontrado: ${BASELINE}`);
  process.exit(2);
}

const baseline = JSON.parse(readFileSync(BASELINE, 'utf8'));
const grades = baseline.grades ?? [];
if (!DRY) mkdirSync(OUT_DIR, { recursive: true });

let written = 0;
for (const g of grades) {
  const screen = g.screen;
  const slug = screen.replace(/\//g, '-').toLowerCase();
  const path = `resources/js/Pages/${screen}.tsx`;
  const gaps = (g.top_gaps ?? [])
    .map(
      (gap) =>
        `  - dim: ${q(gap.dim)}\n` +
        `    best_of_class: ${q(gap.best_of_class)}\n` +
        `    fix: ${q(gap.fix)}\n` +
        `    esforco: ${ESFORCO[gap.esforco] ?? 'medio'}\n` +
        `    origin: screen-grades-baseline-2026-05-30`,
    )
    .join('\n');

  const yaml =
    `# Gerado por scripts/qa/screen-grade-seed.mjs a partir do baseline canônico\n` +
    `# screen-grades-baseline-2026-05-30.json (método ${q(baseline.metodo)}).\n` +
    `# A nota 16-dim é LLM-as-judge (agente screen-qa) — NÃO editar à mão.\n` +
    `# Este YAML é o ponto de partida do ratchet: a nota não pode cair abaixo de baseline_anterior.\n` +
    `screen: ${screen}\n` +
    `path: ${path}\n` +
    `archetype: ${g.archetype ?? 'unknown'}\n` +
    `persona: ${g.persona ?? 'unknown'}\n` +
    `nota: ${g.nota}\n` +
    `nivel: ${q(g.nivel)}\n` +
    `baseline_anterior: ${g.nota}   # ratchet — nota não cai abaixo disto sem aprovação\n` +
    `peso_real: 1.0\n` +
    `source: screen-grades-baseline-2026-05-30\n` +
    `graded_at: ${q(baseline.data)}\n` +
    `resumo: ${q(g.resumo)}\n` +
    `dimensoes: {}   # 16 dims individuais: a regradeação pelo agente preenche (baseline só tem agregado)\n` +
    `gaps:\n${gaps || '  []'}\n`;

  if (!DRY) writeFileSync(join(OUT_DIR, `${slug}.yaml`), yaml);
  written++;
}

console.log(`\n${DRY ? '[dry-run] ' : ''}${written} scorecards de tela ${DRY ? 'seriam gerados' : 'gerados'} em memory/governance/scorecards/screens/`);
console.log(`Fonte: baseline ${baseline.data} · método ${baseline.metodo} · média ${baseline.media}`);
console.log(`Distribuição: ${Object.entries(baseline.distribuicao).map(([k, v]) => `${k}=${v}`).join(' · ')}`);
