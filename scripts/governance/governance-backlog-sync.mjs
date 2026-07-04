#!/usr/bin/env node
// governance-backlog-sync — fecha o loop memory-health → backlog MCP.
//
// A máquina de saúde (memory-health.mjs, Checks S/T/U/V + B/J/K/O/R) SURFA o drift
// no CI, mas o achado não vira TASK sozinho. Esta máquina mapeia o subconjunto
// ACIONÁVEL dos achados → propostas de task no formato que o hook audit-creates-
// tasks (ADR 0213 Mec.2) entende, com a CONTAGEM viva. Fecha o loop:
//   memory-health (acha) → gov-sync (propõe) → Wagner confirma 1× → tasks-create.
//
// PRINCÍPIOS (anti-spam / anti-teatro, alinhado ADR 0314/0105/publication-policy):
//   1. NÃO auto-cria task. Só PROPÕE (o tasks-create fica humano-gated).
//   2. CURADORIA: só kinds acionáveis-como-1-task. Os de alto volume/pré-existentes
//      (B 221 scorecards, J/K/O/R) NÃO entram — 1 task/achado viraria 221 tasks
//      (teatro). Ficam como sentinela advisory no CI, onde já estão.
//   3. IDEMPOTENTE: 1 standing-task por kind (título estável + marcador gov-sync).
//      Re-rodar não duplica — Claude/Wagner dedupa vs tasks-list antes de criar.
//   4. Some quando zera: kind com count 0 não propõe nada (a task fecha sozinha).
//
// Uso: node scripts/governance/governance-backlog-sync.mjs
// Ref: ADR 0213 (loop fechado audit→backlog) · skill audit-to-backlog · memory-health

import { execSync } from 'node:child_process';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const HERE = dirname(fileURLToPath(import.meta.url));
const HEALTH = join(HERE, 'memory-health.mjs');
const MODULE = 'Governance'; // módulo-alvo: higiene de conhecimento = meta-governança (tem SPEC.md — tasks-create exige)

// Curadoria: kind do memory-health → {título estável, prioridade}. Só acionáveis-1-task.
const ACTIONABLE = {
  'fato-ancora-drift': { title: 'Corrigir fatos que contradizem a fonte-de-verdade (camada de entrada)', prio: 'P1' },
  'link-quebrado':     { title: 'Consertar links internos quebrados na canon', prio: 'P2' },
  'proposta-em-limbo': { title: 'Triar drafts acumulados em decisions/proposals/', prio: 'P2' },
  'dir-homonimo':      { title: 'Desambiguar dirs homônimos sob memory/', prio: 'P3' },
  'entrada-stale':     { title: 'Revisar docs stale da camada de entrada', prio: 'P3' },
};
// Excluídos DE PROPÓSITO (alto volume / já rastreados pela sentinela): scorecard-fantasma (B),
// plan-health (J), session-decisao-sem-ancora (K), morta-mas-canon (O), revisao-vencida (R),
// doc-stale reference (D), licao-sem-assercao (I), doc-cache-stale (H).

function main() {
  let out;
  try {
    out = execSync(`node "${HEALTH}" --json --warn-only`, { encoding: 'utf8', maxBuffer: 1e8 });
  } catch (e) {
    out = (e.stdout || '') + ''; // --warn-only não deve falhar, mas captura mesmo assim
  }
  let data;
  try { data = JSON.parse(out); } catch { console.error('gov-sync: memory-health --json não parseou'); process.exit(1); }
  const warns = data.warns || [];

  const proposals = [];
  for (const w of warns) {
    const spec = ACTIONABLE[w.kind];
    if (!spec) continue;
    const count = w.count ?? (Array.isArray(w.sample) ? w.sample.length : 0);
    if (!count) continue;
    proposals.push({ kind: w.kind, count, title: spec.title, prio: spec.prio });
  }

  if (!proposals.length) {
    console.log('✓ gov-sync: nenhum achado acionável pendente — backlog de governança limpo.');
    process.exit(0);
  }

  // Formato audit-compatível (hook audit-creates-tasks reconhece `- [ ] TASK[owner](Px): ...`)
  console.log(`\n🔗 governance-backlog-sync — ${proposals.length} standing-task(s) proposta(s) (módulo ${MODULE})\n`);
  console.log('> PROPOSTA (humano-gated — NÃO auto-criado). Dedupe vs `tasks-list module:' + MODULE + '` antes de `tasks-create`.\n');
  for (const p of proposals) {
    console.log(`- [ ] TASK[wagner](${p.prio}): ${p.title} — ${p.count} pendente(s) [memory-health ${p.kind}] <!-- gov-sync: ${p.kind} -->`);
  }
  console.log('\n(1 task por kind — idempotente pelo título estável. Kind que zera some da lista = task fecha.)');
}

main();
