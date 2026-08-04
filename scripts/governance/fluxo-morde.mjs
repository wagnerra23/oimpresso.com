#!/usr/bin/env node
// @ts-check
/**
 * fluxo-morde.mjs — EXERCÍCIO DE FOGO DO FLUXO: o método detém um defeito, ou só o comenta?
 *
 * ## A pergunta que este script responde, e que nenhum outro responde
 *
 * O `gate-selftest.mjs` (required) já prova, por catraca, que o SCRIPT morde: fixture boa
 * sai 0, fixture ruim sai 1. Ele responde *"a catraca funciona?"* — e responde bem, para
 * 36 catracas.
 *
 * Só que morder não é deter. Entre o `exit 1` do script e o merge bloqueado existe uma
 * CADEIA DE TRANSMISSÃO com três elos, e o selftest só olha o primeiro:
 *
 *      [A] o script ACUSA        → o defeito é detectado e nomeado
 *      [B] o CI TRANSMITE        → o CI roda o MODO que morde, e não engole o exit
 *      [C] o required BLOQUEIA   → o job está no baseline que bloqueia merge
 *
 * Um elo rompido em B ou C produz o pior estado possível: um gate que **parece** cobertura.
 * Ele acusa no log, fica verde no PR, e o defeito entra. Não é hipótese — é o estado medido
 * de duas máquinas deste repo, e o motivo de este script existir.
 *
 * ## Por que isto NÃO duplica o gate-selftest (§5 2026-07-09 "duplica régua consolidada")
 *
 *   | | gate-selftest | fluxo-morde |
 *   |---|---|---|
 *   | unidade | a catraca | a ETAPA do ciclo |
 *   | elos    | [A]       | [A] + [B] + [C]  |
 *   | corpus  | 36 catracas | as do miolo, que ele NÃO cobre |
 *
 * Medido em 2026-08-04: das 6 máquinas do miolo do ciclo (`feature-lint`, `plan-health`,
 * `plans-index`, `casos-coverage-guard`, `screen-coverage`, `ancora-codigo-sync`),
 * **0 estão no gate-selftest** — inclusive uma que é required. O espaço é real, não inventado.
 *
 * ## O que faz dele um TESTE e não um painel
 *
 * Todo caso carrega o par obrigatório, e os dois têm que dar o resultado esperado:
 *   BITE    — fixture COM o defeito  → o script tem que acusar (exit ≠ 0 + código nomeado)
 *   RELEASE — a MESMA fixture curada → o script tem que soltar (exit 0)
 * Sem o RELEASE, um script que reprova tudo passaria por "vigilante". O RELEASE é o
 * controle de falso-positivo, e ele roda sempre.
 *
 * ## Honestidade de escopo (o denominador vem junto — §5 2026-08-04 `n/a`)
 *
 * Só entram etapas exercitáveis de forma HERMÉTICA: Node puro, sandbox em tmp, sem rede,
 * sem DB, sem PHP (Pest/artisan só rodam no CT 100 — proibicoes.md §Ambiente). As demais
 * ficam listadas como NÃO-COBERTAS com o motivo, e o relatório imprime a razão coberto/total.
 * Etapa fora do corpus é lacuna declarada, nunca 🟢.
 *
 * ## O que este script NÃO afirma (§5 2026-07-16 — LC-10)
 *
 * Ele não declara o próprio enforcement nem o dos outros. O elo [C] é LIDO de
 * `governance/required-checks-baseline.json` — o dono de "o que bloqueia merge" — e o
 * relatório diz qual chave leu. Nasce ADVISORY: promoção exige mordida provada (ADR 0336).
 *
 * USO (na raiz do repo):
 *   node scripts/governance/fluxo-morde.mjs             # relatório (exit 0 — advisory)
 *   node scripts/governance/fluxo-morde.mjs --check     # exit 1 se alguma etapa regredir
 *   node scripts/governance/fluxo-morde.mjs --json
 *   node scripts/governance/fluxo-morde.mjs --selftest  # prova que ESTE script mede certo
 *   node scripts/governance/fluxo-morde.mjs --only E4-contrato-feature
 */

import { spawnSync } from 'node:child_process';
import { existsSync, mkdirSync, mkdtempSync, readFileSync, writeFileSync, cpSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { dirname, join, resolve } from 'node:path';

const ROOT = process.cwd();
const argv = process.argv.slice(2);
const JSON_OUT = argv.includes('--json');
const CHECK = argv.includes('--check');
const ONLY = argv.includes('--only') ? argv[argv.indexOf('--only') + 1] : null;
const BASELINE_REL = 'governance/required-checks-baseline.json';

const escreve = (p, txt) => { mkdirSync(dirname(p), { recursive: true }); writeFileSync(p, txt); };
const sandbox = () => mkdtempSync(join(tmpdir(), 'fluxo-morde-'));

/** Roda o script REAL do repo com cwd no sandbox (os gates são cwd-relativos: `ROOT = process.cwd()`). */
function roda(scriptRel, args, cwd) {
  const r = spawnSync(process.execPath, [join(ROOT, scriptRel), ...args], {
    cwd, encoding: 'utf8', env: { ...process.env, GITHUB_STEP_SUMMARY: '' },
  });
  return { code: r.status ?? -1, out: `${r.stdout || ''}${r.stderr || ''}` };
}

// ── [B] parser de workflow — quem roda o script, com que modo, e quem engole o exit ──────
/**
 * PURO + exportado: o `--selftest` exercita ISTO, nunca o console.
 * @param {string} yml
 * @returns {{key:string,name:string|null,coe:boolean,steps:{name:string|null,coe:boolean,run:string}[]}[]}
 */
export function analisaWorkflow(yml) {
  const limpa = (s) => s.trim().replace(/^["']|["']$/g, '');
  const jobs = [];
  let job = null, step = null, runIndent = null, emJobs = false;

  for (const raw of String(yml).split(/\r?\n/)) {
    const l = raw.replace(/\t/g, '  ');
    if (/^jobs:\s*$/.test(l)) { emJobs = true; continue; }
    if (!emJobs) continue;

    // continuação de `run: |` — enquanto a indentação for maior que a do `run:`
    if (runIndent !== null && step) {
      const ind = l.search(/\S/);
      if (l.trim() === '' || ind >= runIndent) { step.run += `\n${l.trim()}`; continue; }
      runIndent = null;
    }

    const mJob = l.match(/^ {2}([A-Za-z0-9_.-]+):\s*$/);
    if (mJob) { job = { key: mJob[1], name: null, coe: false, steps: [] }; jobs.push(job); step = null; continue; }
    if (!job) continue;

    const mStep = l.match(/^ {6}-\s*(.*)$/);
    if (mStep) {
      step = { name: null, coe: false, run: '' };
      job.steps.push(step);
      const inline = mStep[1];
      const mn = inline.match(/^name:\s*(.+)$/);
      if (mn) step.name = limpa(mn[1]);
      const mr = inline.match(/^run:\s*(.+)$/);
      if (mr) step.run = mr[1].trim();
      continue;
    }

    const alvo = step || job;
    const ind = step ? 8 : 4;
    const re = (k) => new RegExp(`^ {${ind}}${k}:\\s*(.*)$`);
    const mName = l.match(re('name'));
    if (mName) { alvo.name = limpa(mName[1]); continue; }
    const mCoe = l.match(re('continue-on-error'));
    if (mCoe) { alvo.coe = /true/i.test(mCoe[1]); continue; }
    if (step) {
      const mRun = l.match(/^ {8}run:\s*(.*)$/);
      if (mRun) {
        const v = mRun[1].trim();
        if (v === '|' || v === '>' || v === '|-' || v === '>-') { step.run = ''; runIndent = 10; }
        else step.run = v;
        continue;
      }
    }
  }
  return jobs;
}

/** Todos os (job, step) de um workflow que executam `scriptBase`. */
export function ondeRoda(jobs, scriptBase) {
  const hits = [];
  for (const job of jobs) {
    for (const step of job.steps) {
      if (step.run && step.run.includes(scriptBase)) hits.push({ job, step });
    }
  }
  return hits;
}

// ── [C] required — LÊ o dono, não afirma nada ────────────────────────────────────────────
export function contextsDoBaseline(txt) {
  const j = JSON.parse(txt);
  const classic = j.classic_protection?.contexts || [];
  const rulesets = j.rulesets?.contexts || [];
  return { classic, rulesets, todos: [...classic, ...rulesets] };
}

/** O `name:` do job vira o context do check. Aceita "Workflow / job" (sufixo). */
export function ehRequired(nomeJob, contexts) {
  if (!nomeJob) return false;
  return contexts.some((c) => c === nomeJob || c.endsWith(` / ${nomeJob}`) || nomeJob.endsWith(` / ${c}`));
}

// ── os casos: 1 defeito canônico por etapa ───────────────────────────────────────────────
const CASOS = [
  {
    id: 'E4-contrato-feature',
    etapa: '4 · contrato da feature',
    defeito: 'trio incompleto + US que não existe no SPEC do módulo',
    script: 'scripts/governance/feature-lint.mjs',
    workflow: '.github/workflows/governance-script-tests.yml',
    modoMorde: '--check',
    acusacao: /\[trio-incompleto\]|\[us-fora-do-spec\]/,
    args: () => ['Teste/feat-x', '--check'],
    ruim(dir) {
      escreve(join(dir, 'memory/requisitos/Teste/SPEC.md'),
        '---\nid: SPEC-TESTE\n---\n\n### US-TST-001 · Uma US\n\n> owner: W · status: todo\n');
      escreve(join(dir, 'memory/requisitos/Teste/features/feat-x/requirements.md'),
        '---\nus: US-TST-999\nmodule: Teste\nfeature: feat-x\n---\n\nsem AC\n');
    },
    boa(dir) {
      // o RELEASE é o trio do GERADOR OFICIAL, curado — não um arquivo inventado por mim
      cpSync(join(ROOT, 'memory/requisitos/_TEMPLATE_FEATURE'), join(dir, 'memory/requisitos/_TEMPLATE_FEATURE'), { recursive: true });
      escreve(join(dir, 'memory/requisitos/Teste/SPEC.md'),
        '---\nid: SPEC-TESTE\n---\n\n### US-TST-001 · Uma US\n\n> owner: W · status: todo\n');
      const g = roda('scripts/governance/feature-lint.mjs', ['--init', 'Teste/feat-x', '--us', 'US-TST-001'], dir);
      if (g.code !== 0) throw new Error(`gerador oficial falhou no RELEASE: ${g.out.slice(0, 300)}`);
      const base = join(dir, 'memory/requisitos/Teste/features/feat-x');
      for (const f of ['requirements.md', 'plan.md', 'tasks.md']) {
        const p = join(base, f);
        writeFileSync(p, readFileSync(p, 'utf8').replace(/\{\{[^}]*\}\}/g, 'curado'));
      }
    },
  },
  {
    id: 'E5-aprovacao',
    etapa: '5 · aprovação',
    defeito: 'plano `em-execução` sem `parent_plan` (ligação fantasma: plano sem task)',
    script: 'scripts/governance/plan-health.mjs',
    workflow: '.github/workflows/plan-health-gate.yml',
    modoMorde: '--check',
    acusacao: /parent_plan/i,
    args: () => ['--check'],
    ruim(dir) { planoFixture(dir, 'status: em-execução\nreviewed_at: HOJE'); },
    boa(dir) { planoFixture(dir, 'status: em-execução\nreviewed_at: HOJE\nparent_plan: COPI-1'); },
  },
  {
    id: 'TR-integridade',
    etapa: 'transversal · integridade referencial',
    defeito: 'link markdown morto dentro de `features/**` (dir VIVO do gate)',
    script: 'scripts/governance/deadlink-gate.mjs',
    workflow: '.github/workflows/deadlink-gate.yml',
    modoMorde: '--check',
    acusacao: /link|dead|morto/i,
    args: (dir) => ['--check', '--root', dir],
    ruim(dir) {
      escreve(join(dir, 'governance/deadlink-baseline.json'), '{}\n');
      escreve(join(dir, 'memory/requisitos/Teste/features/feat-x/requirements.md'),
        '# Req\n\nver [o plano](./nao-existe-nunca.md).\n');
    },
    boa(dir) {
      escreve(join(dir, 'governance/deadlink-baseline.json'), '{}\n');
      escreve(join(dir, 'memory/requisitos/Teste/features/feat-x/plan.md'), '# Plano\n');
      escreve(join(dir, 'memory/requisitos/Teste/features/feat-x/requirements.md'),
        '# Req\n\nver [o plano](./plan.md).\n');
    },
  },
];

function planoFixture(dir, bloco) {
  const hoje = new Date().toISOString().slice(0, 10);
  escreve(join(dir, 'memory/requisitos/_processo/PLANS-INDEX.md'),
    '# Planos\n\n| Plano | x |\n|---|---|\n| [Plano de teste](../Teste/plano-x.md) | y |\n');
  escreve(join(dir, 'memory/requisitos/Teste/plano-x.md'),
    `# Plano de teste\n\n## Status vivo\n\n${bloco.replace('HOJE', hoje)}\n\n## Fim\n`);
}

/** Etapas do ciclo que NÃO entram — com o motivo. O denominador é parte do resultado. */
const FORA = [
  ['1 · pedido', 'entrada humana em linguagem natural — não há artefato a defeituar'],
  ['2 · requisito', 'anchor-lint depende de baseline grandfather + história git real — não hermético'],
  ['3 · design / 7 · contrato da tela', 'casos-coverage lê manifesto de testes escrito por auto-PR diário — entrada não-hermética'],
  ['6 · execução', 'tasks vivem no MCP (DB) — proibido simular; oráculo é o CT 100'],
  ['8 · gates', 'é o gate-selftest (required, 36 catracas) — cobrir aqui seria duplicar o dono'],
  ['9 · deploy', 'exige smoke em prod (R1) — nenhum sandbox prova'],
  ['10 · âncora', 'sha_fora_da_ancestralidade exige história real de squash-merge — não hermético'],
  ['11 · aprendizado', 'ledger §5 é curadoria humana — não há defeito mecânico a injetar'],
];

// ── execução de um caso ──────────────────────────────────────────────────────────────────
function executa(caso) {
  const r = { id: caso.id, etapa: caso.etapa, defeito: caso.defeito, script: caso.script };

  // [A] BITE + RELEASE
  const dirRuim = sandbox(); caso.ruim(dirRuim);
  const ruim = roda(caso.script, caso.args(dirRuim), dirRuim);
  const dirBoa = sandbox(); caso.boa(dirBoa);
  const boa = roda(caso.script, caso.args(dirBoa), dirBoa);

  r.bite = ruim.code !== 0 && caso.acusacao.test(ruim.out);
  r.release = boa.code === 0;
  r.acusa = r.bite && r.release;
  r.detalheA = `bite exit=${ruim.code} acusou=${caso.acusacao.test(ruim.out)} · release exit=${boa.code}`;
  if (!r.release) r.evidenciaRelease = boa.out.split('\n').filter((l) => l.trim()).slice(-6).join('\n');

  // [B] TRANSMITE — o CI roda o modo que morde, e ninguém engole o exit?
  const wf = join(ROOT, caso.workflow);
  r.workflow = caso.workflow;
  if (!existsSync(wf)) {
    r.transmite = false; r.detalheB = 'workflow ausente';
  } else {
    const jobs = analisaWorkflow(readFileSync(wf, 'utf8'));
    const base = caso.script.split('/').pop();
    const hits = ondeRoda(jobs, base).filter((h) => !/--selftest|\.test\.mjs/.test(h.step.run));
    const vivos = hits.filter((h) => h.step.run.includes(caso.modoMorde) && !h.step.coe && !h.job.coe);
    r.transmite = vivos.length > 0;
    r.jobs = [...new Set(hits.map((h) => h.job.name || h.job.key))];
    r.detalheB = hits.length === 0
      ? 'nenhum step do CI executa este script'
      : hits.map((h) => {
        const falta = [];
        if (!h.step.run.includes(caso.modoMorde)) falta.push(`roda SEM ${caso.modoMorde}`);
        if (h.step.coe) falta.push('continue-on-error no step');
        if (h.job.coe) falta.push('continue-on-error no job');
        return `${h.job.name || h.job.key}: ${falta.length ? falta.join(' + ') : 'exit real'}`;
      }).join(' · ');
  }

  // [C] BLOQUEIA — lê o dono
  const bl = join(ROOT, BASELINE_REL);
  if (!existsSync(bl)) { r.bloqueia = false; r.detalheC = `${BASELINE_REL} ausente`; }
  else {
    const ctx = contextsDoBaseline(readFileSync(bl, 'utf8'));
    const casados = (r.jobs || []).filter((n) => ehRequired(n, ctx.todos));
    r.bloqueia = casados.length > 0;
    r.detalheC = r.bloqueia
      ? `job no baseline: ${casados.join(', ')}`
      : `nenhum job deste script consta em ${BASELINE_REL} (chaves lidas: classic_protection.contexts + rulesets.contexts)`;
  }

  r.veredito = !r.release ? 'FALSO-POSITIVO'
    : !r.bite ? 'MUDO'
      : !r.transmite ? 'ACUSA-NAO-TRANSMITE'
        : !r.bloqueia ? 'TRANSMITE-NAO-BLOQUEIA'
          : 'DETIDO';
  return r;
}

// ── selftest — prova que ESTE script mede certo (senão é mais um gate mudo) ───────────────
function selftest() {
  let fails = 0;
  const ok = (n, c) => { console.log(`  ${c ? '[OK]' : '[FAIL]'} ${n}`); if (!c) fails++; };

  const yml = `name: x
on: [pull_request]
jobs:
  bom:
    name: job que morde
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - name: roda com check
        run: node scripts/governance/alvo.mjs --check
  engolido:
    name: job advisory
    continue-on-error: true
    steps:
      - name: roda com check mas o job engole
        run: node scripts/governance/alvo.mjs --check
  semcheck:
    name: job sem o modo
    steps:
      - name: roda sem check
        continue-on-error: true
        run: |
          node scripts/governance/alvo.mjs
          echo fim
`;
  const jobs = analisaWorkflow(yml);
  ok('parser acha os 3 jobs', jobs.length === 3);
  ok('parser lê name do job', jobs[0].name === 'job que morde');
  ok('BITE: continue-on-error no JOB é detectado', jobs[1].coe === true);
  ok('BITE: continue-on-error no STEP é detectado', jobs[2].steps[0].coe === true);
  ok('CONTROLE NEGATIVO: job sem continue-on-error não é marcado', jobs[0].coe === false && jobs[0].steps[1].coe === false);
  ok('parser lê run multiline (`run: |`)', /alvo\.mjs/.test(jobs[2].steps[0].run) && /echo fim/.test(jobs[2].steps[0].run));
  ok('CONTROLE NEGATIVO: `- uses:` não vira step com run', jobs[0].steps[0].run === '');

  const hits = ondeRoda(jobs, 'alvo.mjs');
  ok('ondeRoda acha os 3 steps que executam o script', hits.length === 3);
  const vivos = hits.filter((h) => h.step.run.includes('--check') && !h.step.coe && !h.job.coe);
  ok('só 1 dos 3 transmite o exit de verdade', vivos.length === 1 && vivos[0].job.name === 'job que morde');

  const ctx = contextsDoBaseline('{"classic_protection":{"contexts":["A","B"]},"rulesets":{"contexts":["C"]}}');
  ok('contextsDoBaseline soma classic + rulesets', ctx.todos.length === 3);
  ok('ehRequired casa nome exato', ehRequired('A', ctx.todos) === true);
  ok('ehRequired casa "Workflow / job"', ehRequired('B', ['Wf / B']) === true);
  ok('CONTROLE NEGATIVO: nome fora do baseline não é required', ehRequired('Z', ctx.todos) === false);
  ok('CONTROLE NEGATIVO: job sem name não é required', ehRequired(null, ctx.todos) === false);

  console.log(fails ? `\n  ${fails} FALHA(S) — o medidor do fluxo não está honesto.\n` : '\n  SELFTEST OK — o parser vê o exit engolido e o baseline é lido do dono.\n');
  return fails ? 1 : 0;
}

// ── main ─────────────────────────────────────────────────────────────────────────────────
// Guarda de entrypoint: sem isto, `import { analisaWorkflow }` num .test.mjs EXECUTA o
// exercício inteiro (pego no dogfood 2026-08-04, ao rodar o parser contra o próprio yml).
const isMain = (() => {
  try { return resolve(process.argv[1] || '') === resolve(new URL(import.meta.url).pathname.replace(/^\/([A-Za-z]:)/, '$1')); }
  catch { return true; }
})();
if (!isMain) { /* importado como biblioteca — nada executa */ }
else {

if (argv.includes('--selftest')) process.exit(selftest());

const casos = CASOS.filter((c) => !ONLY || c.id === ONLY);
const res = casos.map(executa);
const detidos = res.filter((r) => r.veredito === 'DETIDO').length;
const fp = res.filter((r) => r.veredito === 'FALSO-POSITIVO');

if (JSON_OUT) {
  console.log(JSON.stringify({
    _meta: {
      pergunta: 'o fluxo DETÉM o defeito, ou só o comenta?',
      elos: { A: 'script acusa', B: 'CI transmite o exit', C: 'required bloqueia merge' },
      fonte_do_elo_C: `${BASELINE_REL} (classic_protection.contexts + rulesets.contexts)`,
      cobertura: `${casos.length} etapa(s) exercitável(is) de ${casos.length + FORA.length} do ciclo`,
      fase: 'ADVISORY — promoção exige mordida provada (ADR 0336)',
    },
    resultados: res, nao_cobertas: FORA.map(([e, m]) => ({ etapa: e, motivo: m })),
  }, null, 2));
} else {
  const ICON = { DETIDO: '🟢', 'TRANSMITE-NAO-BLOQUEIA': '🟡', 'ACUSA-NAO-TRANSMITE': '🔴', MUDO: '⚫', 'FALSO-POSITIVO': '💥' };
  console.log('\n  FLUXO MORDE? — exercício de fogo: 1 defeito por etapa, medido nos 3 elos\n');
  console.log('  elo A = o script acusa · elo B = o CI transmite o exit · elo C = o required bloqueia merge\n');
  for (const r of res) {
    console.log(`  ${ICON[r.veredito] || '?'} ${r.etapa}  —  ${r.veredito}`);
    console.log(`       defeito injetado: ${r.defeito}`);
    console.log(`       [A] acusa .... ${r.acusa ? 'SIM' : 'NÃO'}  (${r.detalheA})`);
    console.log(`       [B] transmite  ${r.transmite ? 'SIM' : 'NÃO'}  (${r.detalheB})`);
    console.log(`       [C] bloqueia . ${r.bloqueia ? 'SIM' : 'NÃO'}  (${r.detalheC})`);
    if (r.evidenciaRelease) console.log(`       ⚠ RELEASE reprovou:\n${r.evidenciaRelease.split('\n').map((l) => `           ${l}`).join('\n')}`);
    console.log('');
  }
  console.log(`  ${detidos}/${casos.length} etapa(s) exercitada(s) DETÊM o defeito.`);
  console.log(`  Cobertura honesta: ${casos.length} de ${casos.length + FORA.length} etapas do ciclo — as outras não são exercitáveis hermeticamente:`);
  for (const [e, m] of FORA) console.log(`     ⊘ ${e} — ${m}`);
  console.log(`\n  Elo [C] lido de ${BASELINE_REL}. Este script é ADVISORY e não afirma o próprio enforcement.\n`);
}

if (fp.length) { console.error(`\n  💥 FALSO-POSITIVO em ${fp.map((r) => r.id).join(', ')} — o caso limpo foi reprovado. Corrija a fixture ANTES de confiar no resultado.\n`); process.exit(1); }
process.exit(CHECK && detidos < casos.length ? 1 : 0);

}
