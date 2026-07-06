#!/usr/bin/env node
// @ts-check
/**
 * mv-metabolismo.mjs — o batimento do Módulo Vivo (MV2 · stream MV do roadmap SDD).
 *
 * Contrato-âncora: memory/sessions/2026-07-05-arte-maquina-governanca-telas.md
 * §3.3 (loop a→g) + §3.4 (batimento por criticidade) + §3.6 (regras de parada).
 *
 * O que faz (determinístico, zero LLM — a IA só entra DEPOIS, nas sessões de execução):
 *   1. Lê memory/governance/vital-signs.json (espinha dorsal MV1 — rodar vital-signs
 *      --json antes; o workflow nightly encadeia os dois).
 *   2. Aplica as REGRAS DE PARADA (arte §3.6) — nesta ordem:
 *      a. GATE HUMANO PENDENTE: se existe batch `status: proposto|aprovado` em
 *         memory/governance/mv-batches/ → NÃO gera batch novo (não empilha fila
 *         fantasma). Exit 0 com aviso.
 *      b. VERDE+FRESCA PULA: tela com !stale && nota ≥ 80 && casos && charter sai
 *         da fila (regra v1 — endurecer exige atualizar este header + selftest).
 *      c. BATIMENTO POR CLASSE: módulo só re-entra se passaram ≥ N dias do último
 *         batch em que apareceu — dinheiro_fiscal 1d · vertical_prod 3d · resto 7d.
 *      d. BUDGET: no máximo MV_BATCH_MAX_TELAS por batch (default 5).
 *   3. Escreve a PROPOSTA em memory/governance/mv-batches/YYYY-MM-DD.md com
 *      frontmatter `status: proposto` + ação proposta por tela.
 *
 * O GATE WAGNER é o merge do auto-PR que carrega o batch (workflow mv-metabolismo.yml,
 * SEM auto-merge — deliberado): merge = aprovado (a sessão de execução pega o batch,
 * cria as tasks via audit-to-backlog e roda screen-qa por tela, marcando `status:
 * executado`); fechar o PR = rejeitado. Este script NUNCA cria task MCP, NUNCA
 * commita, NUNCA mergeia — só propõe (publication-policy).
 *
 * NÃO é gate CI (lei ADR 0314 — advisory; nunca required).
 *
 * Uso:
 *   node scripts/qa/vital-signs.mjs --json --history   # 1º: refresca a espinha dorsal
 *   node scripts/qa/mv-metabolismo.mjs                 # 2º: propõe o batch (se devido)
 *   MV_BATCH_MAX_TELAS=8 node scripts/qa/mv-metabolismo.mjs   # budget custom
 */

import { readFileSync, readdirSync, writeFileSync, existsSync, mkdirSync } from 'node:fs';
import { join } from 'node:path';

const ROOT = process.cwd();
const SNAPSHOT = join(ROOT, 'memory', 'governance', 'vital-signs.json');
const BATCHES_DIR = join(ROOT, 'memory', 'governance', 'mv-batches');

export const BUDGET_DEFAULT = 5;
// Batimento (arte §3.4): dias mínimos entre aparições do MESMO módulo em batches.
export const BATIMENTO_DIAS = { dinheiro_fiscal: 1, vertical_prod: 3, resto: 7 };

/** Regra "verde+fresca pula o ciclo" (arte §3.6 regra 2 — v1). */
export function verdeFresca(t) {
  return !t.stale && t.nota !== null && t.nota >= 80 && t.casos === true && t.charter === true;
}

/** Frontmatter scalar de um batch .md (formato controlado por este script). */
export function fmScalar(text, key) {
  const m = text.match(new RegExp(`^${key}:\\s*"?([^"\\n]+)"?\\s*$`, 'm'));
  return m ? m[1].trim() : null;
}

/**
 * Estado dos batches existentes: pendência de gate humano + último ciclo por módulo.
 * `arquivos` = [{date, status, modulos[]}] parseado de mv-batches/*.md.
 */
export function estadoBatches(arquivos) {
  const pendente = arquivos.find((b) => b.status === 'proposto' || b.status === 'aprovado') || null;
  const ultimoCiclo = new Map(); // mod → date (YYYY-MM-DD) mais recente em que apareceu
  for (const b of arquivos) {
    for (const mod of b.modulos) {
      const atual = ultimoCiclo.get(mod);
      if (!atual || b.date > atual) ultimoCiclo.set(mod, b.date);
    }
  }
  return { pendente, ultimoCiclo };
}

/** Módulo está devido? (batimento por classe — arte §3.4). */
export function modDevido(mod, classe, ultimoCiclo, hojeStr) {
  const ultimo = ultimoCiclo.get(mod);
  if (!ultimo) return true; // nunca ciclado = devido
  const dias = Math.floor((new Date(`${hojeStr}T00:00:00Z`).getTime() - new Date(`${ultimo}T00:00:00Z`).getTime()) / 86_400_000);
  return dias >= BATIMENTO_DIAS[classe];
}

/**
 * Seleção do batch (pura, testável): fila já vem ordenada por prioridade do MV1.
 * Aplica verde-fresca → batimento → budget. Retorna telas selecionadas.
 */
export function selecionaBatch(fila, ultimoCiclo, hojeStr, budget) {
  const out = [];
  for (const t of fila) {
    if (out.length >= budget) break;
    if (verdeFresca(t)) continue;
    if (!modDevido(t.mod, t.classe, ultimoCiclo, hojeStr)) continue;
    out.push(t);
  }
  return out;
}

/** Ação proposta por tela — deriva dos gaps visíveis nos sinais vitais (contrato-first). */
export function acaoProposta(t) {
  if (t.nota === null) return 'ciclo screen-qa COMPLETO (tela sem prontuário: scorecard 16-dim + charter/casos se faltarem)';
  const partes = [];
  if (!t.casos) partes.push('escrever casos.md (contrato UC) + Pest ancorado no contrato');
  if (!t.charter) partes.push('escrever charter');
  if (t.stale) partes.push('re-grade do scorecard (frescor expirou)');
  if (t.nota < 80) partes.push(`atacar gaps do scorecard (nota ${t.nota})`);
  return partes.join(' · ') || 're-check do ciclo';
}

function main() {
  if (!existsSync(SNAPSHOT)) {
    console.error('✗ memory/governance/vital-signs.json ausente — rode vital-signs.mjs --json primeiro.');
    process.exit(1);
  }
  const snap = JSON.parse(readFileSync(SNAPSHOT, 'utf8'));
  const hojeStr = new Date().toISOString().slice(0, 10);
  const budget = Number.parseInt(process.env.MV_BATCH_MAX_TELAS ?? '', 10) || BUDGET_DEFAULT;

  // Batches existentes (parada a + batimento c).
  mkdirSync(BATCHES_DIR, { recursive: true });
  const arquivos = readdirSync(BATCHES_DIR)
    .filter((f) => f.endsWith('.md'))
    .map((f) => {
      const text = readFileSync(join(BATCHES_DIR, f), 'utf8');
      return {
        file: f,
        date: fmScalar(text, 'date') || f.slice(0, 10),
        status: fmScalar(text, 'status') || 'proposto',
        modulos: (fmScalar(text, 'modulos') || '').replace(/[\[\]]/g, '').split(',').map((s) => s.trim()).filter(Boolean),
      };
    });
  const { pendente, ultimoCiclo } = estadoBatches(arquivos);

  if (pendente) {
    console.log(`⏸ batch ${pendente.file} ainda ${pendente.status} — gate humano pendente, não empilho (arte §3.6 regra 3).`);
    process.exit(0);
  }

  const fila = snap.fila_prioridade || [];
  const batch = selecionaBatch(fila, ultimoCiclo, hojeStr, budget);

  if (!batch.length) {
    console.log('✓ frota sem tela devida hoje (tudo verde+fresco ou fora de batimento) — sem batch.');
    process.exit(0);
  }

  const modulos = [...new Set(batch.map((t) => t.mod))];
  const outFile = join(BATCHES_DIR, `${hojeStr}.md`);
  const linhas = batch
    .map((t) => `| \`${t.screen}\` | ${t.classe} | ${t.nota ?? '—'} | ${t.prioridade} | ${acaoProposta(t)} |`)
    .join('\n');

  writeFileSync(
    outFile,
    `---
date: "${hojeStr}"
status: proposto
modulos: [${modulos.join(', ')}]
budget: ${budget}
snapshot: "${snap.generated_at}"
---
# Batch MV · ${hojeStr} — proposta do metabolismo (aguarda Wagner)

> Gerado por \`scripts/qa/mv-metabolismo.mjs\` (determinístico, zero LLM) a partir de
> [vital-signs.json](../vital-signs.json). Contrato: arte 2026-07-05 §3.3-§3.6.
> **Gate humano:** merge do PR = APROVADO · fechar o PR = rejeitado.
> Após aprovar: sessão de execução cria as tasks (audit-to-backlog) + roda
> screen-qa por tela (sessão limpa/tela) e troca \`status:\` pra \`executado\`.

| Tela | Classe | Nota | Prioridade | Ação proposta |
|---|---|---|---|---|
${linhas}

**Regras aplicadas:** verde+fresca pulou · batimento ${JSON.stringify(BATIMENTO_DIAS)} · budget ${budget}.
`,
  );
  console.log(`✓ batch proposto → memory/governance/mv-batches/${hojeStr}.md (${batch.length} telas · módulos: ${modulos.join(', ')})`);
}

if (process.argv[1] && process.argv[1].endsWith('mv-metabolismo.mjs')) main();
