#!/usr/bin/env node
// @ts-check
/**
 * vital-signs.mjs — sinais vitais da frota por módulo (MV1 · espinha dorsal do Módulo Vivo).
 *
 * Contrato-âncora: memory/sessions/2026-07-05-arte-maquina-governanca-telas.md §3.2 (sinais
 * vitais) + §3.4 (frescor por criticidade) + §3.5 (priorização cross-módulo). Stream MV do
 * roadmap SDD (memory/requisitos/_Governanca/roadmap/_ROADMAP.md).
 *
 * O que faz (determinístico, zero LLM no caminho crítico — padrão Knowledge Survival ADR 0256):
 *   1. Lê os scorecards de tela (memory/governance/scorecards/screens/*.yaml — nota 16-dim,
 *      graded_at, ratchet) — a fonte de verdade de UX por tela.
 *   2. Cruza com o universo real de telas (resources/js/Pages/ recursivo, só .tsx de tela) e com a presença de
 *      contrato (<Tela>.charter.md / <Tela>.casos.md ao lado do .tsx).
 *   3. AGREGA por módulo — regra "pior tela puxa": exibe mín(telas) AO LADO da média
 *      (média esconde; mín é o sinal vital). Arte §3.2.
 *   4. FRESCOR que degrada: idade do scorecard mais velho; stale se >30d (caminho do
 *      dinheiro/fiscal) ou >60d (resto). Nota velha ≠ nota confiável — anti verde-stale.
 *   5. PRIORIDADE por tela = peso_criticidade × (100 − nota) × penalidade_frescor.
 *      Tela sem scorecard entra na fila com nota 0 (pior caso honesto: não medido ≠ bom).
 *
 * Saídas:
 *   - stdout: prontuário por módulo (sempre) + fila de prioridade (top 20)
 *   - --json:    grava memory/governance/vital-signs.json (snapshot estável, sorted)
 *   - --history: apenda 1 linha em memory/governance/vital-signs-history.jsonl (append-only —
 *                trend da frota; NUNCA reescrever linhas antigas)
 *
 * NÃO é gate: advisory por lei (ADR 0314 — gates novos nascem advisory). O cron-metabolismo
 * (MV2) LÊ este snapshot pra priorizar; catracas continuam nos gates existentes
 * (screen-grades-ratchet, screen-coverage-map --check).
 *
 * Uso:
 *   node scripts/qa/vital-signs.mjs                  # só relatório
 *   node scripts/qa/vital-signs.mjs --json           # + grava snapshot
 *   node scripts/qa/vital-signs.mjs --json --history # + apenda trend
 */

import { readFileSync, readdirSync, statSync, writeFileSync, appendFileSync, existsSync } from 'node:fs';
import { join, sep } from 'node:path';

const ROOT = process.cwd();
const PAGES_DIR = join(ROOT, 'resources', 'js', 'Pages');
const SCORECARD_DIR = join(ROOT, 'memory', 'governance', 'scorecards', 'screens');
const SNAPSHOT = join(ROOT, 'memory', 'governance', 'vital-signs.json');
const HISTORY = join(ROOT, 'memory', 'governance', 'vital-signs-history.jsonl');

// ── Criticidade por módulo (arte §3.4 — batimento proporcional) ──────────────────────────
// dinheiro/fiscal: erro custa dinheiro real (incidente num_uf) ou multa (SEFAZ).
// vertical_prod: cliente real em produção (ROTA LIVRE biz=4; Repair shared).
// Ajustes aqui = decisão de domínio → citar ADR/arte no diff.
export const CRITICIDADE = {
  dinheiro_fiscal: ['Sells', 'Financeiro', 'RecurringBilling', 'NfeBrasil', 'Fiscal', 'Nfse', 'PaymentGateway'],
  vertical_prod: ['Vestuario', 'Repair', 'OficinaAuto', 'Ponto'],
};
export const PESO = { dinheiro_fiscal: 4, vertical_prod: 2, resto: 1 };
export const STALE_DIAS = { dinheiro_fiscal: 30, vertical_prod: 60, resto: 60 };
const PENALIDADE_STALE = 1.5; // arte §3.5 — scorecard stale multiplica (nota velha sobe na fila)

/** Classe de criticidade de um módulo. */
export function classeDoModulo(mod) {
  if (CRITICIDADE.dinheiro_fiscal.includes(mod)) return 'dinheiro_fiscal';
  if (CRITICIDADE.vertical_prod.includes(mod)) return 'vertical_prod';
  return 'resto';
}

/** Scalar YAML top-level (mesma heurística leve do screen-grades-ratchet — formato controlado pelo seed). */
export function yamlScalar(text, key) {
  const m = text.match(new RegExp(`^${key}:\\s*"?([^"\\n#]+)"?\\s*(#.*)?$`, 'm'));
  return m ? m[1].trim() : null;
}

/** Idade em dias de um graded_at (YYYY-MM-DD) vs hoje. null se ausente/ilegível. */
export function idadeDias(gradedAt, hoje = new Date()) {
  if (!gradedAt || !/^\d{4}-\d{2}-\d{2}$/.test(gradedAt)) return null;
  const d = new Date(`${gradedAt}T00:00:00Z`);
  if (Number.isNaN(d.getTime())) return null;
  return Math.max(0, Math.floor((hoje.getTime() - d.getTime()) / 86_400_000));
}

/** Stale? (frescor degradado — arte §3.2). Sem graded_at = stale por definição (nunca medido é o pior frescor). */
export function isStale(idade, classe) {
  if (idade === null) return true;
  return idade > STALE_DIAS[classe];
}

/**
 * Prioridade de uma tela na fila do metabolismo (arte §3.5).
 * nota null (sem scorecard) = 0 — não medido conta como pior caso, nunca como verde.
 */
export function prioridade(nota, classe, stale) {
  const n = nota ?? 0;
  return PESO[classe] * (100 - n) * (stale ? PENALIDADE_STALE : 1);
}

/** Agrega telas de um módulo — pior tela puxa (mín ao lado da média). */
export function agregaModulo(telas) {
  const medidas = telas.filter((t) => t.nota !== null);
  const notas = medidas.map((t) => t.nota);
  const idades = medidas.map((t) => t.idade).filter((i) => i !== null);
  const pior = medidas.length ? medidas.reduce((a, b) => (a.nota <= b.nota ? a : b)) : null;
  return {
    telas: telas.length,
    com_scorecard: medidas.length,
    sem_scorecard: telas.length - medidas.length,
    nota_min: notas.length ? Math.min(...notas) : null,
    pior_tela: pior ? pior.screen : null,
    nota_media: notas.length ? Math.round(notas.reduce((a, b) => a + b, 0) / notas.length) : null,
    idade_max_dias: idades.length ? Math.max(...idades) : null,
    charter_pct: telas.length ? Math.round((telas.filter((t) => t.charter).length / telas.length) * 100) : 0,
    casos_pct: telas.length ? Math.round((telas.filter((t) => t.casos).length / telas.length) * 100) : 0,
    stale: telas.some((t) => t.stale),
  };
}

// ── Coleta ───────────────────────────────────────────────────────────────────────────────

function walk(dir, match, acc = []) {
  if (!existsSync(dir)) return acc;
  for (const entry of readdirSync(dir)) {
    const full = join(dir, entry);
    const st = statSync(full);
    if (st.isDirectory()) walk(full, match, acc);
    else if (match(full)) acc.push(full);
  }
  return acc;
}

// Universo de telas (régua do screen-coverage-map, endurecida: QUALQUER pasta iniciada em `_`
// é peça interna — _components/_drawer/_form/_show/_lib/_shared/_Showcase. Verificado 2026-07-05:
// nenhum dos 219 scorecards vive sob pasta `_`, então a exclusão não perde tela medida).
const isScreen = (f) =>
  f.endsWith('.tsx') &&
  !f.includes(`${sep}_`) &&
  !f.includes(`${sep}components${sep}`) &&
  !f.includes(`${sep}Partials${sep}`) &&
  !f.endsWith('.charter.tsx') &&
  !f.includes('.test.');

function coletaFrota(hoje = new Date()) {
  // 1. Scorecards → índice por path relativo do .tsx.
  const porPath = new Map();
  if (existsSync(SCORECARD_DIR)) {
    for (const f of readdirSync(SCORECARD_DIR).filter((x) => x.endsWith('.yaml') || x.endsWith('.yml'))) {
      const text = readFileSync(join(SCORECARD_DIR, f), 'utf8');
      const path = yamlScalar(text, 'path');
      if (!path) continue;
      porPath.set(path.replace(/\//g, sep), {
        screen: yamlScalar(text, 'screen') || path,
        nota: Number.parseInt(yamlScalar(text, 'nota') ?? '', 10) || null,
        graded_at: yamlScalar(text, 'graded_at'),
      });
    }
  }

  // 2. Telas reais → junta scorecard (ou pior-caso) + contrato ao lado.
  const telas = [];
  for (const abs of walk(PAGES_DIR, isScreen)) {
    const rel = abs.slice(ROOT.length + 1);
    const mod = rel.split(sep)[3]; // resources/js/Pages/<Mod>/...
    if (!mod || !mod.length) continue;
    const classe = classeDoModulo(mod);
    const sc = porPath.get(rel) || null;
    const idade = sc ? idadeDias(sc.graded_at, hoje) : null;
    const stale = isStale(idade, classe);
    const nota = sc ? sc.nota : null;
    telas.push({
      screen: sc ? sc.screen : rel.replace(/^resources[\\/]js[\\/]Pages[\\/]/, '').replace(/\.tsx$/, '').replace(/\\/g, '/'),
      mod,
      classe,
      nota,
      idade,
      stale,
      charter: existsSync(abs.replace(/\.tsx$/, '.charter.md')),
      casos: existsSync(abs.replace(/\.tsx$/, '.casos.md')),
      prioridade: Math.round(prioridade(nota, classe, stale)),
    });
  }
  return telas;
}

// ── Relatório ────────────────────────────────────────────────────────────────────────────

function main() {
  const flags = new Set(process.argv.slice(2));
  const hoje = new Date();
  const telas = coletaFrota(hoje);

  const porModulo = new Map();
  for (const t of telas) {
    if (!porModulo.has(t.mod)) porModulo.set(t.mod, []);
    porModulo.get(t.mod).push(t);
  }

  const modulos = [...porModulo.entries()]
    .map(([mod, ts]) => ({ mod, classe: classeDoModulo(mod), ...agregaModulo(ts) }))
    .sort((a, b) => a.mod.localeCompare(b.mod));

  // Prontuário por módulo (pior tela puxa — mín exibido ANTES da média).
  const ICON = { dinheiro_fiscal: '🟥', vertical_prod: '🟨', resto: '⬜' };
  console.log('\n  SINAIS VITAIS DA FROTA — pior tela puxa; média não esconde (arte 2026-07-05 §3.2)\n');
  console.log('  módulo                     telas  s/score  mín  méd  charter  casos  frescor');
  console.log('  ' + '─'.repeat(88));
  for (const m of modulos) {
    const frescor = m.stale ? `⚠ stale (${m.idade_max_dias ?? '—'}d)` : `ok (${m.idade_max_dias ?? '—'}d)`;
    console.log(
      `  ${ICON[m.classe]} ${m.mod.padEnd(24)} ${String(m.telas).padStart(4)} ${String(m.sem_scorecard).padStart(7)}` +
        ` ${String(m.nota_min ?? '—').padStart(4)} ${String(m.nota_media ?? '—').padStart(4)}` +
        ` ${String(m.charter_pct + '%').padStart(7)} ${String(m.casos_pct + '%').padStart(6)}  ${frescor}`,
    );
  }

  // Fila de prioridade (o que o metabolismo atacaria primeiro).
  const fila = [...telas].sort((a, b) => b.prioridade - a.prioridade).slice(0, 20);
  console.log('\n  FILA DE PRIORIDADE (top 20 — criticidade × (100−nota) × frescor; sem scorecard = nota 0):\n');
  for (const t of fila) {
    console.log(
      `  ${String(t.prioridade).padStart(5)}  ${ICON[t.classe]} ${t.screen.padEnd(48)} nota=${t.nota ?? '—'}` +
        `${t.stale ? ' ⚠stale' : ''}${t.casos ? '' : ' sem-casos'}${t.charter ? '' : ' sem-charter'}`,
    );
  }

  const fleet = {
    telas: telas.length,
    com_scorecard: telas.filter((t) => t.nota !== null).length,
    charter: telas.filter((t) => t.charter).length,
    casos: telas.filter((t) => t.casos).length,
    stale: telas.filter((t) => t.stale).length,
  };
  console.log(
    `\n  FROTA: ${fleet.telas} telas · ${fleet.com_scorecard} com scorecard · ` +
      `${fleet.charter} charter · ${fleet.casos} casos.md · ${fleet.stale} stale\n`,
  );

  const generatedAt = hoje.toISOString().slice(0, 10);
  if (flags.has('--json')) {
    const snapshot = {
      generated_at: generatedAt,
      contrato: 'memory/sessions/2026-07-05-arte-maquina-governanca-telas.md §3.2/§3.4/§3.5',
      fleet,
      modulos,
      fila_prioridade: fila.map(({ screen, mod, classe, nota, stale, prioridade: p }) => ({ screen, mod, classe, nota, stale, prioridade: p })),
    };
    writeFileSync(SNAPSHOT, JSON.stringify(snapshot, null, 2) + '\n');
    console.log(`  ✓ snapshot → memory/governance/vital-signs.json`);
  }
  if (flags.has('--history')) {
    // Append-only: 1 linha compacta por run — o trend da frota. NUNCA editar linhas antigas.
    const linha = { at: generatedAt, fleet, modulos: Object.fromEntries(modulos.map((m) => [m.mod, { min: m.nota_min, med: m.nota_media, casos_pct: m.casos_pct, stale: m.stale }])) };
    appendFileSync(HISTORY, JSON.stringify(linha) + '\n');
    console.log(`  ✓ trend (append-only) → memory/governance/vital-signs-history.jsonl`);
  }
}

// Só roda main quando invocado direto (permite import das funções puras no selftest).
if (process.argv[1] && process.argv[1].endsWith('vital-signs.mjs')) main();
