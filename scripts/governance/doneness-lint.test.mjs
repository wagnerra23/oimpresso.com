#!/usr/bin/env node
// Selftest do doneness-lint v2 (P15 · done × DoD checkbox aberto). Cada caso deriva do
// CONTRATO (P15-done-comportamento-evidencia-alvo.md entrega 1 + ADR 0302), não da
// implementação: US `status: done` cujo DoD/aceite tem checkbox `[ ]` desmarcado é
// CONSISTÊNCIA INTERNA quebrada (o done contradiz o próprio DoD) → 🔴 no report.
// Matriz canônica do P15: done+DoD completo = verde · done+DoD aberto = vermelho ·
// review+DoD aberto = verde. Advisory: NUNCA morde em --check sem o opt-in --dod (lei 0314).
// Complementa gate-selftest.mjs (fixtures bite/release do --check v1, que este teste NÃO toca).
//
// Rodar: node scripts/governance/doneness-lint.test.mjs   (exit 0 = passa)

import { mkdtempSync, writeFileSync, mkdirSync, rmSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { tmpdir } from 'node:os';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const SCRIPT = join(dirname(fileURLToPath(import.meta.url)), 'doneness-lint.mjs');
let fails = 0;
const check = (name, cond, extra = '') => { console.log((cond ? '[OK]   ' : '[FAIL] ') + name + (cond ? '' : '  → ' + extra)); if (!cond) fails++; };

// ── fixture: 1 SPEC com a matriz do P15 + 2 armadilhas (checkbox fora do DoD · Non-Goals fecha seção) ──
const tmp = mkdtempSync(join(tmpdir(), 'doneness-lint-v2-'));
mkdirSync(join(tmp, 'memory', 'requisitos', 'Fix'), { recursive: true });
mkdirSync(join(tmp, 'scripts'), { recursive: true });
writeFileSync(join(tmp, 'scripts', 'alvo.mjs'), '// âncora viva\n', 'utf8'); // âncora existe no disco

const SPEC = `# SPEC fixture P15

### US-FIX-001 · done + DoD completo (verde)

> owner: w · status: done · type: story

**Implementado em:** \`scripts/alvo.mjs\`

**Definition of Done:**
- [x] tudo marcado
- [x] segundo item

---

### US-FIX-002 · done + DoD aberto (vermelho — o alvo do P15)

> owner: w · status: done · type: story

**Implementado em:** \`scripts/alvo.mjs\`

**Definition of Done:**
- [x] um marcado
- [ ] um ABERTO — done mente pro próprio DoD
- [ ] outro aberto

**Non-Goals:**
- [ ] checkbox FORA do DoD (seção fechou no bold Non-Goals) — não conta

---

### US-FIX-003 · review + DoD aberto (verde — não declara pronto)

> owner: w · status: review · type: story

**Implementado em:** _pendente_

**Definition of Done:**
- [ ] aberto mas status não é done

---

### US-FIX-004 · done + checklist de plano SEM marker DoD (verde — escopo é DoD/aceite)

> owner: w · status: done · type: story

**Implementado em:** \`scripts/alvo.mjs\`

**Plano:**
- [ ] passo solto fora de seção DoD/aceite
`;
writeFileSync(join(tmp, 'memory', 'requisitos', 'Fix', 'SPEC.md'), SPEC, 'utf8');

const run = (...args) => spawnSync(process.execPath, [SCRIPT, ...args], { cwd: tmp, encoding: 'utf8' });

// ── matriz P15 via --json ─────────────────────────────────────────────────────────
const j = JSON.parse(run('--json').stdout);
const mod = j.modules[0];
check('summary conta exatamente 1 conflito done×DoD (só a US-FIX-002)', j.summary.conflito_done_dod_aberto === 1, JSON.stringify(j.summary));
check('conflito aponta a US-FIX-002', mod.dod_conflicts.length === 1 && mod.dod_conflicts[0].us === 'US-FIX-002', JSON.stringify(mod.dod_conflicts));
check('conflito carrega dod_open=2 / dod_total=3 (Non-Goals não vazou)', mod.dod_conflicts[0].dod_open === 2 && mod.dod_conflicts[0].dod_total === 3, JSON.stringify(mod.dod_conflicts[0]));
check('kind canônico conflito_done_dod_aberto', mod.dod_conflicts[0].kind === 'conflito_done_dod_aberto');
check('done+DoD completo (001) e review+DoD aberto (003) e plano-solto (004) NÃO conflitam', !mod.dod_conflicts.some((c) => c.us !== 'US-FIX-002'));
check('módulo flag 🔴 quando há done×DoD aberto', mod.flag === '🔴', mod.flag);
check('v1 intacto: âncoras vivas → zero conflito status×âncora', j.summary.conflitos_total === 0, String(j.summary.conflitos_total));

// ── advisory vs morder: --check sem --dod = exit 0 · com --dod = exit 1 ──────────
check('--check SEM --dod: exit 0 (advisory — lei ADR 0314, não morde)', run('--check').status === 0);
check('--check --dod: exit 1 (opt-in arma a mordida)', run('--check', '--dod').status === 1);

// ── grandfather: --dod respeita --baseline (no-new-lie, mesmo esquema de chave) ───
writeFileSync(join(tmp, 'baseline.json'), JSON.stringify({ grandfathered: ['conflito_done_dod_aberto:US-FIX-002'] }), 'utf8');
check('--check --dod --baseline com a chave grandfathered: exit 0', run('--check', '--dod', '--baseline', 'baseline.json').status === 0);

// ── emit-baseline: chaves DoD só entram com --dod (não crescer baseline por acidente) ──
const emitPlain = JSON.parse(run('--emit-baseline').stdout).grandfathered;
const emitDod = JSON.parse(run('--emit-baseline', '--dod').stdout).grandfathered;
check('--emit-baseline sem --dod NÃO inclui chave DoD', !emitPlain.some((k) => k.startsWith('conflito_done_dod_aberto:')));
check('--emit-baseline --dod inclui conflito_done_dod_aberto:US-FIX-002', emitDod.includes('conflito_done_dod_aberto:US-FIX-002'), JSON.stringify(emitDod));

// ── report humano cita o conflito ─────────────────────────────────────────────────
const human = run().stdout;
check('tabela humana lista US-FIX-002 com contagem DoD', /US-FIX-002.*DoD 1\/3 marcado \(2 aberto\)/.test(human), human.split('\n').filter((l) => l.includes('US-FIX-002')).join(' | '));

rmSync(tmp, { recursive: true, force: true });
console.log(fails ? `\nSELFTEST FALHOU (${fails})` : '\nSELFTEST OK — done×DoD aberto reporta 🔴, só morde com --dod, baseline no-new-lie preservado (P15 · ADR 0302/0314).');
process.exit(fails ? 1 : 0);
