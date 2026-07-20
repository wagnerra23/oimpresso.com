#!/usr/bin/env node
// Teste do PORTE licoes-code-two-strikes.mjs (ex-.ps1). Deriva do CONTRATO (LICOES_CODE.md:
// erro reincidente ≥threshold sem gate = alarme), NÃO do .ps1. Advisory: SEMPRE exit 0.
// Rodar: node .claude/hooks/licoes-code-two-strikes.test.mjs

import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';
import { mkdtempSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { parseLicoes, classificar, semGate, threshold, formatBanner } from './licoes-code-two-strikes.mjs';

const HOOK = join(dirname(fileURLToPath(import.meta.url)), 'licoes-code-two-strikes.mjs');
let fails = 0;
const check = (n, c) => { console.log((c ? '[OK]   ' : '[FAIL] ') + n); if (!c) fails++; };

const MD = `# Licoes
## LC-01 - Erro repetido sem defesa
**Ocorrências:** 3
**Gate:** none
## LC-02 - Ja tem gate
**Ocorrências:** 5
**Gate:** block-foo.mjs
## LC-03 - So uma vez
**Ocorrências:** 1
**Gate:** none
`;

// ── parser + classificador (puros) ───────────────────────────────────────────────
const licoes = parseLicoes(MD);
check('parseLicoes acha 3 licoes', licoes.length === 3);
check('parseLicoes le ocorrencias + gate', licoes[0].ocorr === 3 && licoes[0].gate === 'none' && licoes[1].gate === 'block-foo.mjs');
check('semGate: none/vazio sim, gate real nao', semGate('none') && semGate('') && semGate('-') && !semGate('block-foo.mjs'));
check('threshold default 2', threshold({}) === 2 && threshold({ OIMPRESSO_LICOES_THRESHOLD: '3' }) === 3);
const { alarme, watch } = classificar(licoes, 2);
check('alarme = LC-01 (3x sem gate)', alarme.length === 1 && alarme[0].id === 'LC-01');
check('watch = LC-03 (1x sem gate)', watch.length === 1 && watch[0].id === 'LC-03');
check('LC-02 (tem gate) fica de fora', !alarme.concat(watch).some((l) => l.id === 'LC-02'));
check('formatBanner cita PROMOVER + LC-01', /PROMOVER A DEFESA MECANICA/.test(formatBanner(alarme, watch, 2)) && /LC-01/.test(formatBanner(alarme, watch, 2)));
check('formatBanner vazio quando nada', formatBanner([], [], 2) === '');

// ── E2E: advisory SEMPRE exit 0 ──────────────────────────────────────────────────
const tmp = mkdtempSync(join(tmpdir(), 'licoes-'));
const p = join(tmp, 'LICOES_CODE.md');
writeFileSync(p, MD);
function run(env = {}) {
  return spawnSync(process.execPath, [HOOK], { encoding: 'utf8', env: { ...process.env, OIMPRESSO_LICOES_CODE_PATH: p, ...env } });
}
const r = run();
check('E2E: alarme → exit 0 + banner stdout', r.status === 0 && /LC-01/.test(r.stdout));
check('E2E: arquivo inexistente → exit 0 silencioso', (() => { const x = spawnSync(process.execPath, [HOOK], { encoding: 'utf8', env: { ...process.env, OIMPRESSO_LICOES_CODE_PATH: join(tmp, 'nope.md') } }); return x.status === 0 && !x.stdout.trim(); })());
check('E2E: threshold alto → LC-01 vira watch, exit 0', run({ OIMPRESSO_LICOES_THRESHOLD: '9' }).status === 0);

console.log(fails ? `\nSELFTEST FALHOU (${fails})` : '\nSELFTEST OK — porte .mjs parseia/classifica LICOES, alarma sem gate, advisory exit 0.');
process.exit(fails ? 1 : 0);
