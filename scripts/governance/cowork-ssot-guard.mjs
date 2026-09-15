#!/usr/bin/env node
// cowork-ssot-guard.mjs — trava a organização física do protótipo de design.
//
// Estrutura aceita:
//   prototipo-ui/
//     cowork/{Wagner,Felipe}/   fontes de tela, separadas pela conta de origem
//     design-system/            espelho canônico do DS
//
// Máquinas ficam em scripts/design/, contratos/alvos em governance/design/, testes em
// tests/Design/ e documentação CANON em memory/reference/prototipo-ui/. O Git guarda histórico;
// nenhuma segunda cópia física é aceita.
//
// ── R3 · por que o `.md` deixou de ser proibido aqui (decisão [W] 2026-09-13) ──────────────
// `cowork/<dono>/` é ESPELHO da conta Cowork daquele dono — e espelho que muda a forma do
// original não é espelho. A redação anterior admitia `.md` só em `handoffs/<nome>.md` (flat),
// e o efeito medido foi: dos 816 arquivos do pacote Cowork de 2026-09-11, **400 pousavam e 416
// eram descartados — 337 deles `.md`**, ou seja a camada inteira de documentação do pacote.
// Pior: o projeto Cowork NÃO TEM `handoffs/` (medido: raiz tem `cowork-inbox/`, `contrato/`,
// `sync/`, `prototipos/`…), então o destino flat era um formato que só existia deste lado.
// Consequência prática: o `cowork-inbox/` — o canal por onde o Cowork manda ordem de serviço —
// chegava pela metade, e o programa de playbooks ficou sem endereço no repo.
//
// A regra que sobra é a que protege algo real: `.md` vive DENTRO de um dono (`cowork/<dono>/`),
// nunca solto em `cowork/`. Forma interna é a do Cowork, não a nossa. O que impede o espelho de
// virar depósito continua sendo R1 (raiz), R2 (donos) e R4 (zero duplicata de bytes) — e a R4
// é justamente quem recusa a duplicata que o próprio pacote traz (medido 2026-09-13: 10 pares
// idênticos entre `cowork-inbox/sidebar/playbook/` e `entrega-sidebar-code/playbook/`).
// Emenda à ADR 0397 D3 registrada em memory/decisions/.
//
// Uso: node scripts/governance/cowork-ssot-guard.mjs [--json]
import { readdirSync, existsSync, readFileSync } from 'node:fs';
import { createHash } from 'node:crypto';
import { join } from 'node:path';

const ROOT = process.cwd();
const COWORK = 'prototipo-ui/cowork';
const DONOS = new Set(['Wagner', 'Felipe']);

const errors = [];

function walk(dir) {
  const abs = join(ROOT, dir);
  if (!existsSync(abs)) return [];
  const out = [];
  for (const e of readdirSync(abs, { withFileTypes: true })) {
    const rel = `${dir}/${e.name}`;
    if (e.isDirectory()) out.push(...walk(rel));
    else out.push(rel);
  }
  return out;
}

// R1 — a raiz do protótipo contém somente as duas áreas autorizadas.
const pu = join(ROOT, 'prototipo-ui');
if (existsSync(pu)) {
  for (const e of readdirSync(pu, { withFileTypes: true })) {
    if (!e.isDirectory() || !['cowork', 'design-system'].includes(e.name)) {
      errors.push(`R1 item proibido na raiz de prototipo-ui/: prototipo-ui/${e.name}`);
    }
  }
}

// R2 — cowork/ contém somente os dois donos e nenhum arquivo solto.
const coworkAbs = join(ROOT, COWORK);
if (existsSync(coworkAbs)) {
  for (const e of readdirSync(coworkAbs, { withFileTypes: true })) {
    if (!e.isDirectory() || !DONOS.has(e.name)) {
      errors.push(`R2 item proibido em cowork/ (donos aceitos: Wagner, Felipe): ${COWORK}/${e.name}`);
    }
  }
}

// R3 — `.md` vive DENTRO de um dono; a forma interna é a do Cowork (ver cabeçalho).
// Não é exportada de propósito: este arquivo EXECUTA no import (walk + process.exit no fim),
// então `import { violaR3 }` rodaria o guard inteiro e mataria o processo do teste. O bite-test
// exercita o CLI de fora, com fixture por cwd — é o que prova o pipeline, não o satélite.
function violaR3(caminhoRelativo) {
  if (typeof caminhoRelativo !== 'string') return false;
  const f = caminhoRelativo.split('\\').join('/');
  if (!f.toLowerCase().endsWith('.md')) return false;
  return !/^prototipo-ui\/cowork\/(Wagner|Felipe)\/.+\.md$/i.test(f);
}
for (const f of walk(COWORK)) {
  if (violaR3(f)) errors.push(`R3 documentação fora de cowork/<dono>/: ${f}`);
}

// R4 — zero conteúdo duplicado em disco dentro de prototipo-ui/, inclusive caches ignorados.
// O Git já preserva o histórico. Uma segunda cópia física no working tree cria dois donos,
// âncoras ambíguas e importações que parecem mover arquivos. A comparação é pelos bytes.
const byHash = new Map();
for (const rel of walk('prototipo-ui')) {
  const hash = createHash('sha256').update(readFileSync(join(ROOT, rel))).digest('hex');
  const paths = byHash.get(hash) || [];
  paths.push(rel);
  byHash.set(hash, paths);
}
for (const paths of byHash.values()) {
  if (paths.length > 1) errors.push(`R4 conteúdo duplicado (mantenha um único dono): ${paths.join(' = ')}`);
}

if (process.argv.includes('--json')) {
  console.log(JSON.stringify({
    ok: errors.length === 0,
    errors,
    donos: [...DONOS],
  }, null, 2));
} else if (errors.length) {
  console.error(`✗ cowork-ssot-guard: ${errors.length} violação(ões) de fonte única:`);
  for (const e of errors) console.error('  - ' + e);
  console.error('\nRegra: prototipo-ui/ só contém cowork/{Wagner,Felipe}/ e design-system/; histórico vive no Git.');
} else {
  console.log('✓ cowork-ssot-guard: estrutura mínima e fonte única OK (Wagner + Felipe + design-system).');
}
process.exit(errors.length ? 1 : 0);
