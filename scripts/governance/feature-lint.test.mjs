#!/usr/bin/env node
// Self-test feature-lint — prova o contrato do trio (requirements/plan/tasks) contra fixtures
// em memória + fixtures de disco (tmp). Os dois buracos-alvo: (1) acceptance sem task,
// (2) blocked_by irresolvível/cíclico. Roda: node scripts/governance/feature-lint.test.mjs
import { mkdtempSync, writeFileSync, mkdirSync, rmSync } from 'node:fs';
import { join } from 'node:path';
import { tmpdir } from 'node:os';
import { parseFrontmatter, parseAcs, parseTaskMeta, parseTasks, detectCycle, lintFeature } from './feature-lint.mjs';

let fails = 0;
const check = (n, c, extra = '') => { console.log(`${c ? '[OK]' : '[FAIL]'} ${n}${c ? '' : '  → ' + extra}`); if (!c) fails++; };
const codes = (r) => r.issues.map((i) => i.code);

// 1. parseFrontmatter — extrai us: mesmo com comentário HTML antes do ---.
const FM = '<!-- comentário -->\n---\nfeature: x-y\nmodule: Mod\nus: ["US-RB-052", "US-RB-055"]\n---\n# corpo';
check('frontmatter extrai us[]', JSON.stringify(parseFrontmatter(FM).us) === '["US-RB-052","US-RB-055"]');
check('frontmatter sem us → []', parseFrontmatter('---\nfeature: x\n---\n').us.length === 0);

// 2. parseAcs — só linhas de definição `- **AC-N**`; referência no texto não conta.
const REQ = '- **AC-1** — QUANDO x, O SISTEMA DEVE y.\n- **AC-2** — SE z. (ver AC-9)\ntexto AC-3 solto\n- **AC-1** — duplicada';
check('ACs definidos = 1,2 (ref solta não conta)', JSON.stringify(parseAcs(REQ).ids) === '["AC-1","AC-2"]');
check('AC duplicado detectado', parseAcs(REQ).dups.includes('AC-1'));

// 3. parseTaskMeta — campos segmentados por ·; covers não vaza pra blocked_by.
const meta = parseTaskMeta('> blocked_by: T-01, T-02 · covers: AC-1, AC-3 · us: US-RB-052 · estimate: 2h');
check('deps = T-01,T-02', JSON.stringify(meta.deps) === '["T-01","T-02"]');
check('covers = AC-1,AC-3', JSON.stringify(meta.covers) === '["AC-1","AC-3"]');
check('raiz — → deps vazio', parseTaskMeta('> blocked_by: — · covers: AC-1').deps.length === 0);
check('blocked_by lixo → depsUnparsed', parseTaskMeta('> blocked_by: depois do deploy · covers: AC-1').depsUnparsed !== null);

// 4. detectCycle — acíclico ok · ciclo A→B→A pego · auto-referência pega · ref quebrada NÃO é ciclo.
const t = (id, deps) => ({ id, meta: { deps } });
check('cadeia linear sem ciclo', detectCycle([t('T-01', []), t('T-02', ['T-01']), t('T-03', ['T-02'])]) === null);
check('ciclo T-01↔T-02 detectado', (detectCycle([t('T-01', ['T-02']), t('T-02', ['T-01'])]) || []).length > 0);
check('auto-referência é ciclo', (detectCycle([t('T-01', ['T-01'])]) || []).join('→') === 'T-01→T-01');
check('dep quebrada não vira falso-ciclo', detectCycle([t('T-01', ['T-99'])]) === null);

// 5. lintFeature end-to-end em disco (fixture boa e fixtures ruins).
const tmp = mkdtempSync(join(tmpdir(), 'feature-lint-'));
const mkFeature = (slug, { req, plan = '# plan', tasks }) => {
  const mod = join(tmp, 'memory', 'requisitos', 'Mod');
  const dir = join(mod, 'features', slug);
  mkdirSync(dir, { recursive: true });
  writeFileSync(join(mod, 'SPEC.md'), '### US-MOD-001 · US real do SPEC\n', 'utf8');
  if (req != null) writeFileSync(join(dir, 'requirements.md'), req, 'utf8');
  if (plan != null) writeFileSync(join(dir, 'plan.md'), plan, 'utf8');
  if (tasks != null) writeFileSync(join(dir, 'tasks.md'), tasks, 'utf8');
  return dir;
};
const REQ_OK = '---\nfeature: boa\nmodule: Mod\nus: ["US-MOD-001"]\n---\n- **AC-1** — QUANDO x, DEVE y.\n- **AC-2** — SE z, ENTÃO w.\n';
const TASKS_OK = '### T-01 · Primeira\n> blocked_by: — · covers: AC-1 · us: US-MOD-001\n\n**DoD:** prova.\n\n### T-02 · Segunda\n> blocked_by: T-01 · covers: AC-2 · us: US-MOD-001\n\n**DoD:** prova.\n';

const boa = lintFeature(mkFeature('boa', { req: REQ_OK, tasks: TASKS_OK }));
check('fixture BOA: zero issues', boa.issues.length === 0, JSON.stringify(boa.issues));

const semTask = lintFeature(mkFeature('buraco', { req: REQ_OK, tasks: '### T-01 · Só uma\n> blocked_by: — · covers: AC-1\n\n**DoD:** prova.\n' }));
check('AC-2 sem task → aviso ac-sem-task (buraco nº1)', codes(semTask).includes('ac-sem-task'));
check('buraco é AVISO, não erro (advisory)', !semTask.issues.some((i) => i.code === 'ac-sem-task' && i.level === 'erro'));

const quebrada = lintFeature(mkFeature('quebrada', { req: REQ_OK, tasks: '### T-01 · Dep fantasma\n> blocked_by: T-77 · covers: AC-1, AC-2\n\n**DoD:** prova.\n' }));
check('blocked_by→T-77 inexistente → erro (buraco nº2)', codes(quebrada).includes('blocked-by-quebrado'));

const ciclo = lintFeature(mkFeature('ciclo', { req: REQ_OK, tasks: '### T-01 · A\n> blocked_by: T-02 · covers: AC-1\n\n**DoD:** p.\n\n### T-02 · B\n> blocked_by: T-01 · covers: AC-2\n\n**DoD:** p.\n' }));
check('ciclo T-01↔T-02 → erro ciclo', codes(ciclo).includes('ciclo'));

const usFalsa = lintFeature(mkFeature('us-falsa', { req: REQ_OK.replace('US-MOD-001', 'US-MOD-999'), tasks: TASKS_OK.replaceAll('US-MOD-001', 'US-MOD-999') }));
check('US inexistente no SPEC → erro us-fora-do-spec', codes(usFalsa).includes('us-fora-do-spec'));

const semDod = lintFeature(mkFeature('sem-dod', { req: REQ_OK, tasks: '### T-01 · Sem prova\n> blocked_by: — · covers: AC-1, AC-2\n' }));
check('task sem **DoD:** → erro task-sem-dod', codes(semDod).includes('task-sem-dod'));

const trioIncompleto = lintFeature(mkFeature('sem-plan', { req: REQ_OK, plan: null, tasks: TASKS_OK }));
check('plan.md ausente → erro trio-incompleto', codes(trioIncompleto).includes('trio-incompleto'));

const coversFalso = lintFeature(mkFeature('covers-falso', { req: REQ_OK, tasks: TASKS_OK.replace('covers: AC-2', 'covers: AC-9') }));
check('covers→AC-9 inexistente → erro covers-ac-inexistente', codes(coversFalso).includes('covers-ac-inexistente'));

rmSync(tmp, { recursive: true, force: true });

console.log(fails ? `\n✗ ${fails} falha(s)` : '\n✓ contrato do trio de feature preservado');
process.exit(fails ? 1 : 0);
