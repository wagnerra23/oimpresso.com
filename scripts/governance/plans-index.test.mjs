#!/usr/bin/env node
// @ts-check
/**
 * plans-index.test.mjs — TESTE CANÁRIO do gerador de planos (prova que "ficou automático").
 *
 * Caixa-preta (espelha gate-selftest): monta fixtures num cwd temporário, roda
 * plans-index.mjs lá, e prova:
 *   1. plano com `## Status vivo` → REGISTRADO + campos parseados (o canário cai sozinho)
 *   2. arquivo *plan* SEM bloco → PENDENTE (não some, vira backfill dirigido)
 *   3. `## Status vivo` dentro de code-fence (template) → NÃO registra (anti-falso-positivo)
 *   4. --check: gerado==commitado → exit 0 ; depois de mexer → exit 1 (a catraca MORDE)
 *
 * Uso: node scripts/governance/plans-index.test.mjs   (exit 1 se qualquer caso falhar)
 */
import { mkdirSync, writeFileSync, rmSync, readFileSync, existsSync } from 'node:fs';
import { execSync } from 'node:child_process';
import { join, dirname } from 'node:path';
import { tmpdir } from 'node:os';
import { fileURLToPath } from 'node:url';

const GEN = fileURLToPath(new URL('./plans-index.mjs', import.meta.url));
const TMP = join(tmpdir(), 'plans-index-selftest');
const fails = [];
const ok = (cond, msg) => { if (cond) console.log(`  ✓ ${msg}`); else { fails.push(msg); console.error(`  ✗ ${msg}`); } };

function write(rel, content) { const p = join(TMP, rel); mkdirSync(dirname(p), { recursive: true }); writeFileSync(p, content); }
function run(args) {
  try { return { out: execSync(`node "${GEN}" ${args}`, { cwd: TMP, encoding: 'utf8' }), code: 0 }; }
  catch (e) { return { out: (e.stdout || '') + (e.stderr || ''), code: e.status || 1 }; }
}

// ── setup fixtures ───────────────────────────────────────────────────────────
rmSync(TMP, { recursive: true, force: true });
mkdirSync(join(TMP, 'memory/requisitos/_processo'), { recursive: true });

// caso 1: plano registrado (canário)
write('memory/requisitos/Canary/PLANO-CANARY.md', `# Plano Canário de Teste

## Status vivo
<!-- catraca ADR 0294 -->
- **status:** em-execução
- **owner:** W
- **criado:** 2026-06-20 · **reviewed_at:** 2026-06-20 · **próxima-revisão:** 2026-07-20
- **cycle:** CYCLE-08 · **execução:** \`parent_plan=plano-canary\`
- **gate-de-saída (DoD):** quando o gerador provar que cai sozinho
- **kill-condition:** nunca
- **verdade-viva:** este doc

## Corpo
texto.
`);

// caso 2: arquivo *plan* SEM bloco → pendente
write('memory/requisitos/Canary/PLANO-SEM-BLOCO.md', `# Plano sem Status vivo\n\nsó corpo, sem bloco.\n`);

// caso 3: armadilha — \`## Status vivo\` dentro de code-fence (template), NÃO deve registrar
write('memory/requisitos/Canary/PLANO-TEMPLATE-FENCE.md', `# Doc com template em code-fence\n\nExemplo:\n\n\`\`\`\n## Status vivo\n- **status:** ativo\n\`\`\`\n\nfim.\n`);

// ── caso 1/2/3 via --json ─────────────────────────────────────────────────────
console.log('— descoberta (registrado / pendente / anti-fence) —');
const j = JSON.parse(run('--json').out);
const reg = j.registered.find((p) => p.rel.endsWith('PLANO-CANARY.md'));
ok(!!reg, 'plano com ## Status vivo é REGISTRADO (cai sozinho)');
ok(reg && reg.status === 'em-execução', 'status parseado = em-execução');
ok(reg && reg.reviewed_at === '2026-06-20', 'reviewed_at parseado');
ok(reg && reg.parent_plan === 'plano-canary', 'parent_plan parseado do execução=');
ok(j.pending.some((p) => p.rel.endsWith('PLANO-SEM-BLOCO.md')), 'arquivo *plan* sem bloco → PENDENTE');
ok(!j.registered.some((p) => p.rel.endsWith('PLANO-TEMPLATE-FENCE.md')), 'template em code-fence NÃO registra (anti-falso-positivo)');

// ── caso 4: --check morde drift ───────────────────────────────────────────────
console.log('— catraca --check (drift) —');
run('--write');
ok(existsSync(join(TMP, 'memory/requisitos/_processo/PLANS-INDEX-GENERATED.md')), '--write gera o índice');
ok(run('--check').code === 0, '--check passa quando gerado==commitado');
// muta um plano → o gerado fica defasado → --check deve exit 1
const cpath = join(TMP, 'memory/requisitos/Canary/PLANO-CANARY.md');
writeFileSync(cpath, readFileSync(cpath, 'utf8').replace('status:** em-execução', 'status:** pausado'));
ok(run('--check').code === 1, '--check MORDE (exit 1) quando um plano muda e o índice não foi regenerado');

// ── teardown ──────────────────────────────────────────────────────────────────
rmSync(TMP, { recursive: true, force: true });
console.log(`\nplans-index.test: ${fails.length ? fails.length + ' FALHA(S)' : 'TODOS OS CASOS PASSARAM ✓'}`);
process.exit(fails.length ? 1 : 0);
