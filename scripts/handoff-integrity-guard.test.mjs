#!/usr/bin/env node
// TESTE DE REGRESSÃO — prova que a catraca de Integridade do Handoff MORDE ("quem vigia os vigias").
// Controle-negativo: pega órfão injetado E ref-morta injetada, E passa quando tudo casa / está no
// baseline / está abaixo da linha d'água. Fixtures geradas em tmp (hermético) — sem rede, sem DB.
// Rodar: node scripts/handoff-integrity-guard.test.mjs — exit 0 = todos passam, exit 1 = regressão.

import { spawnSync } from 'node:child_process';
import { mkdtempSync, mkdirSync, writeFileSync, readFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const SCRIPT = join(__dirname, 'handoff-integrity-guard.mjs');
const MARKER = '<!-- LINHA-DAGUA-HANDOFF -->';

let fails = 0;
const check = (name, cond) => { console.log(`${cond ? '[OK]' : '[FAIL]'} ${name}`); if (!cond) fails++; };
const run = (root, extra = [], env = {}) =>
  spawnSync('node', [SCRIPT, '--root', root, ...extra], { encoding: 'utf8', env: { ...process.env, GITHUB_STEP_SUMMARY: '', ...env } });
const out = (r) => (r.stdout || '') + (r.stderr || '');

// Monta um root falso: prototipo-ui/{PROMPT_PARA_CODE_*.md, COWORK_NOTES.md} + config/baseline.json
function makeRoot({ files = [], aboveCites = [], belowCites = [], aboveRaw = '', belowRaw = '', baseline = { orphans: [], dead_refs: [] }, marker = true }) {
  const root = mkdtempSync(join(tmpdir(), 'hig-'));
  mkdirSync(join(root, 'prototipo-ui'), { recursive: true });
  mkdirSync(join(root, 'config'), { recursive: true });
  for (const f of files) writeFileSync(join(root, 'prototipo-ui', f), `# ${f}\n`);
  const above = aboveCites.map((c) => `- ativo: [${c}](${c})`).join('\n');
  const below = belowCites.map((c) => `- PROCESSED ${c} → main`).join('\n');
  const queue = `# COWORK_NOTES (fixture)\n\n## ATIVOS\n${above}\n${aboveRaw}\n\n${marker ? MARKER : ''}\n\n## HISTÓRICO\n${below}\n${belowRaw}\n`;
  writeFileSync(join(root, 'prototipo-ui', 'COWORK_NOTES.md'), queue);
  writeFileSync(join(root, 'config', 'handoff-integrity-baseline.json'), JSON.stringify(baseline));
  return root;
}
const drop = (root) => rmSync(root, { recursive: true, force: true });

// 1. BOA: tudo que existe no dir está citado acima da linha → verde.
{
  const root = makeRoot({ files: ['PROMPT_PARA_CODE_A.md', 'PROMPT_PARA_CODE_B.md'], aboveCites: ['PROMPT_PARA_CODE_A.md', 'PROMPT_PARA_CODE_B.md'] });
  check('boa (tudo citado) → exit 0', run(root).status === 0);
  drop(root);
}

// 2. RUIM órfão injetado: arquivo no dir sem citação → vermelho acusando o arquivo.
{
  const root = makeRoot({ files: ['PROMPT_PARA_CODE_A.md', 'PROMPT_PARA_CODE_ORFAO.md'], aboveCites: ['PROMPT_PARA_CODE_A.md'] });
  const r = run(root);
  check('órfão injetado → exit 1', r.status === 1);
  check('órfão injetado → acusa o arquivo', /PROMPT_PARA_CODE_ORFAO\.md/.test(out(r)));
  check('órfão injetado → rotula ÓRFÃO', /[ÓO]RF[ÃA]O/.test(out(r)));
  drop(root);
}

// 3. RUIM ref-morta injetada: citação acima pra arquivo que não existe → vermelho.
{
  const root = makeRoot({ files: ['PROMPT_PARA_CODE_A.md'], aboveCites: ['PROMPT_PARA_CODE_A.md', 'PROMPT_PARA_CODE_FANTASMA.md'] });
  const r = run(root);
  check('ref morta → exit 1', r.status === 1);
  check('ref morta → acusa o fantasma', /PROMPT_PARA_CODE_FANTASMA\.md/.test(out(r)));
  check('ref morta → rotula MORTA', /MORTA/.test(out(r)));
  drop(root);
}

// 4. Baseline congela: órfão já listado no baseline NÃO trava (só NOVO trava).
{
  const root = makeRoot({
    files: ['PROMPT_PARA_CODE_A.md', 'PROMPT_PARA_CODE_DIVIDA.md'], aboveCites: ['PROMPT_PARA_CODE_A.md'],
    baseline: { orphans: ['PROMPT_PARA_CODE_DIVIDA.md'], dead_refs: [] },
  });
  check('órfão no baseline → exit 0 (dívida congelada)', run(root).status === 0);
  drop(root);
}

// 5. Abaixo da linha d'água é ignorado: ref morta no histórico não trava.
{
  const root = makeRoot({ files: ['PROMPT_PARA_CODE_A.md'], aboveCites: ['PROMPT_PARA_CODE_A.md'], belowCites: ['PROMPT_PARA_CODE_HISTORICO-MORTO.md'] });
  check('ref morta ABAIXO da linha → exit 0 (ignorado)', run(root).status === 0);
  drop(root);
}

// 6. --write-baseline congela o estado atual (órfão vira dívida; re-run fica verde).
{
  const root = makeRoot({ files: ['PROMPT_PARA_CODE_A.md', 'PROMPT_PARA_CODE_NOVO.md'], aboveCites: ['PROMPT_PARA_CODE_A.md'] });
  const w = run(root, ['--write-baseline']);
  check('--write-baseline → exit 0', w.status === 0);
  const bl = JSON.parse(readFileSync(join(root, 'config', 'handoff-integrity-baseline.json'), 'utf8'));
  check('--write-baseline → grava o órfão atual', (bl.orphans || []).includes('PROMPT_PARA_CODE_NOVO.md'));
  check('após write-baseline → exit 0 (congelado)', run(root).status === 0);
  drop(root);
}

// 7. --json parseável e fiel.
{
  const root = makeRoot({ files: ['PROMPT_PARA_CODE_A.md', 'PROMPT_PARA_CODE_X.md'], aboveCites: ['PROMPT_PARA_CODE_A.md'] });
  const j = JSON.parse(run(root, ['--json']).stdout);
  check('--json: 2 arquivos + 1 órfão (X)', j.files === 2 && j.orphans.includes('PROMPT_PARA_CODE_X.md'));
  drop(root);
}

// 8. Job summary: tabela com contadores quando GITHUB_STEP_SUMMARY existe.
{
  const root = makeRoot({ files: ['PROMPT_PARA_CODE_A.md', 'PROMPT_PARA_CODE_ORFAO.md'], aboveCites: ['PROMPT_PARA_CODE_A.md'] });
  const tmp = mkdtempSync(join(tmpdir(), 'hig-sum-'));
  const sum = join(tmp, 'summary.md');
  writeFileSync(sum, '');
  run(root, [], { GITHUB_STEP_SUMMARY: sum });
  const md = readFileSync(sum, 'utf8');
  check('summary → tabela com "Integridade do handoff" + órfão novo', /Integridade do handoff/.test(md) && /NOVO órfão/.test(md));
  drop(root); drop(tmp);
}

// 9. C3 — cabeçalho fundido na fila ativa (`:** > **`) → vermelho acusando C3/FUNDIDO.
{
  const root = makeRoot({ files: ['PROMPT_PARA_CODE_A.md'], aboveCites: ['PROMPT_PARA_CODE_A.md'], aboveRaw: '**Status:** > **Artefato:** x' });
  const r = run(root);
  check('fundido na fila ativa → exit 1', r.status === 1);
  check('fundido → rotula FUNDIDO/C3', /FUNDIDO|C3/.test(out(r)));
  drop(root);
}

// 10. C3 ABAIXO da linha d'água → ignorado (exit 0).
{
  const root = makeRoot({ files: ['PROMPT_PARA_CODE_A.md'], aboveCites: ['PROMPT_PARA_CODE_A.md'], belowRaw: '**Status:** > **Artefato:** x' });
  check('fundido ABAIXO da linha → exit 0 (ignorado)', run(root).status === 0);
  drop(root);
}

// 11. C3 no baseline → não trava (dívida congelada).
{
  const root = makeRoot({
    files: ['PROMPT_PARA_CODE_A.md'], aboveCites: ['PROMPT_PARA_CODE_A.md'], aboveRaw: '**Status:** > **Artefato:** x',
    baseline: { orphans: [], dead_refs: [], fused_headers: ['COWORK_NOTES.md::**Status:** > **Artefato:** x'] },
  });
  check('fundido no baseline → exit 0 (congelado)', run(root).status === 0);
  drop(root);
}

console.log('');
if (fails === 0) { console.log('[PASS] catraca de handoff MORDE — órfão/ref-morta/fundido novo = vermelho, baseline congela, abaixo-da-linha ignora.'); process.exit(0); }
console.log(`[FAIL] ${fails} caso(s) — a catraca NÃO está garantida. NÃO mergear.`);
process.exit(1);
