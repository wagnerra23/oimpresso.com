#!/usr/bin/env node
// TESTE DE REGRESSÃO — prova que o Caçador de reincidência MORDE ("quem vigia os vigias").
// Controle-negativo: pega C4 órfão/ref-morta, C3 fundido e C5 sem-carimbo INJETADOS, E passa quando
// tudo está limpo / no baseline / abaixo da linha d'água / com o carimbo presente (controle-POSITIVO
// do C5). Fixtures em tmp (hermético) — sem rede, sem DB.
// Rodar: node scripts/reincidencia-guard.test.mjs — exit 0 = todos passam, exit 1 = regressão.

import { spawnSync } from 'node:child_process';
import { mkdtempSync, mkdirSync, writeFileSync, readFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const SCRIPT = join(__dirname, 'reincidencia-guard.mjs');
const MARKER = '<!-- LINHA-DAGUA-HANDOFF -->';

let fails = 0;
const check = (name, cond) => { console.log(`${cond ? '[OK]' : '[FAIL]'} ${name}`); if (!cond) fails++; };
const run = (root, extra = []) => spawnSync('node', [SCRIPT, '--root', root, ...extra], { encoding: 'utf8' });
const out = (r) => (r.stdout || '') + (r.stderr || '');

function makeRoot({ files = [], aboveCites = [], aboveRaw = '', belowRaw = '', baseline = [], marker = true }) {
  const root = mkdtempSync(join(tmpdir(), 'reincid-'));
  mkdirSync(join(root, 'prototipo-ui'), { recursive: true });
  mkdirSync(join(root, 'scripts'), { recursive: true });
  for (const f of files) writeFileSync(join(root, 'prototipo-ui', f), `# ${f}\n`);
  const above = aboveCites.map((c) => `- ativo: ${c}`).join('\n');
  const queue = `# COWORK_NOTES (fixture)\n\n## ATIVOS\n${above}\n${aboveRaw}\n\n${marker ? MARKER : ''}\n\n## HISTÓRICO\n${belowRaw}\n`;
  writeFileSync(join(root, 'prototipo-ui', 'COWORK_NOTES.md'), queue);
  writeFileSync(join(root, 'scripts', 'reincidencia-baseline.json'), JSON.stringify(baseline));
  return root;
}
const drop = (root) => rmSync(root, { recursive: true, force: true });

// 1. BOA: tudo citado, sem fundido, sem item ativo → verde.
{
  const root = makeRoot({ files: ['PROMPT_PARA_CODE_A.md', 'PROMPT_PARA_CODE_B.md'], aboveCites: ['PROMPT_PARA_CODE_A.md', 'PROMPT_PARA_CODE_B.md'] });
  check('boa (tudo limpo) → exit 0', run(root).status === 0);
  drop(root);
}

// 2. C4 órfão injetado: arquivo no dir sem citação → vermelho acusando ÓRFÃO.
{
  const root = makeRoot({ files: ['PROMPT_PARA_CODE_A.md', 'PROMPT_PARA_CODE_ORFAO.md'], aboveCites: ['PROMPT_PARA_CODE_A.md'] });
  const r = run(root);
  check('C4 órfão → exit 1', r.status === 1);
  check('C4 órfão → acusa o arquivo', /PROMPT_PARA_CODE_ORFAO\.md/.test(out(r)));
  check('C4 órfão → rotula ÓRFÃO', /[ÓO]RF[ÃA]O/.test(out(r)));
  drop(root);
}

// 3. C4 ref-morta injetada: citação acima pra arquivo inexistente → vermelho.
{
  const root = makeRoot({ files: ['PROMPT_PARA_CODE_A.md'], aboveCites: ['PROMPT_PARA_CODE_A.md', 'PROMPT_PARA_CODE_FANTASMA.md'] });
  const r = run(root);
  check('C4 ref-morta → exit 1', r.status === 1);
  check('C4 ref-morta → acusa o fantasma', /PROMPT_PARA_CODE_FANTASMA\.md/.test(out(r)));
  check('C4 ref-morta → rotula MORTA', /MORTA/.test(out(r)));
  drop(root);
}

// 4. C3 bloco fundido na fila ATIVA → vermelho acusando C3.
{
  const root = makeRoot({ files: ['PROMPT_PARA_CODE_A.md'], aboveCites: ['PROMPT_PARA_CODE_A.md'], aboveRaw: '**Status:** > **Artefato:** x' });
  const r = run(root);
  check('C3 fundido (ativo) → exit 1', r.status === 1);
  check('C3 fundido → rotula C3/FUNDIDO', /C3|FUNDIDO/.test(out(r)));
  drop(root);
}

// 5. C3 ABAIXO da linha d'água → ignorado (exit 0).
{
  const root = makeRoot({ files: ['PROMPT_PARA_CODE_A.md'], aboveCites: ['PROMPT_PARA_CODE_A.md'], belowRaw: '**Status:** > **Artefato:** x' });
  check('C3 abaixo da linha → exit 0 (ignorado)', run(root).status === 0);
  drop(root);
}

// 6. C5 item ativo SEM carimbo `verificado vs main` → vermelho acusando C5.
{
  const root = makeRoot({ aboveRaw: '> US-FIN lentes → [CL]\nsem prova de conferência aqui, só corpo' });
  const r = run(root);
  check('C5 sem carimbo → exit 1', r.status === 1);
  check('C5 sem carimbo → rotula C5', /C5/.test(out(r)));
  drop(root);
}

// 7. C5 controle-POSITIVO: item ativo COM `verificado vs main` → verde (não falso-positivo).
{
  const root = makeRoot({ aboveRaw: '> US-FIN lentes → [CL]\nverificado vs main @abc1234 — confere, segue pro PR' });
  check('C5 com carimbo → exit 0 (não dispara)', run(root).status === 0);
  drop(root);
}

// 8. Baseline congela: órfão já listado NÃO trava (só NOVO trava).
{
  const root = makeRoot({
    files: ['PROMPT_PARA_CODE_A.md', 'PROMPT_PARA_CODE_DIVIDA.md'], aboveCites: ['PROMPT_PARA_CODE_A.md'],
    baseline: ['PROMPT_PARA_CODE_DIVIDA.md'],
  });
  check('órfão no baseline → exit 0 (dívida congelada)', run(root).status === 0);
  drop(root);
}

// 9. --write congela o estado atual (órfão vira dívida; re-run fica verde).
{
  const root = makeRoot({ files: ['PROMPT_PARA_CODE_A.md', 'PROMPT_PARA_CODE_NOVO.md'], aboveCites: ['PROMPT_PARA_CODE_A.md'] });
  const w = run(root, ['--write']);
  check('--write → exit 0', w.status === 0);
  const bl = JSON.parse(readFileSync(join(root, 'scripts', 'reincidencia-baseline.json'), 'utf8'));
  check('--write → grava o órfão atual', bl.includes('PROMPT_PARA_CODE_NOVO.md'));
  check('após --write → exit 0 (congelado)', run(root).status === 0);
  drop(root);
}

// 10. --json parseável e fiel.
{
  const root = makeRoot({ files: ['PROMPT_PARA_CODE_A.md', 'PROMPT_PARA_CODE_X.md'], aboveCites: ['PROMPT_PARA_CODE_A.md'] });
  const j = JSON.parse(run(root, ['--json']).stdout);
  check('--json: 2 prompts + acusa o X órfão', j.files === 2 && j.novos.some((m) => /PROMPT_PARA_CODE_X\.md/.test(m)));
  drop(root);
}

console.log('');
if (fails === 0) { console.log('[PASS] catraca de reincidência MORDE — C3/C4/C5 novo = vermelho, baseline congela, abaixo-da-linha + carimbo OK ignoram.'); process.exit(0); }
console.log(`[FAIL] ${fails} caso(s) — a catraca NÃO está garantida. NÃO mergear.`);
process.exit(1);
