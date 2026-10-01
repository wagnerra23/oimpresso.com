#!/usr/bin/env node
/**
 * visreg-l2-cadeia.test — bite-test do lugar onde o Lint L2 reprova o job `visual-regression`.
 *
 * ── O QUE ELE DEFENDE (medido em 2026-09-28, nao suposto) ─────────────────────
 * No run 36192630403 (PR #7994) o step "Lint L2 — charter states ⇄ manifesto" falhou por um
 * charter com `states:` sem entrada no manifesto. Sem `continue-on-error`, a falha fez todo
 * step com `success()` implicito pular: 44 steps `skipped`, incluindo "Classificar impacto" e
 * "Modo de execucao". O Canario (que tem `always()`) rodou com IMPACT/MODE vazios e reprovou,
 * e o comentario no PR nomeou o Canario, nao o L2. O docblock do lint dizia que o step nascia
 * advisory; o YAML dizia ENFORCING desde 2026-07-06 (decisao [W]). Classe: LC-10/LC-15.
 *
 * O conserto mantem a reprovacao do job e muda o LUGAR: o L2 continua (`continue-on-error`) e
 * o step "Veredito do Lint L2" reprova no fim. Este teste prova as duas metades:
 *   1. o lint REAL reprova um charter com `states:` fora do manifesto (sandbox git);
 *   2. com o L2 falhando, a cadeia segue (classificacao e modo rodam) E o job termina vermelho;
 *   3. controle de mutacao: sem o `continue-on-error`, a cadeia volta a pular — o assert 2 cai.
 *   4. (2026-10-01) setup quebrado (ex. timeout das deps do Playwright) nao executa os passos de
 *      teste com `always()` nem o canario — a falha fica no passo de setup, nao num fluxo visual.
 *
 * A simulacao do (2) e parcial e declarada: modela so as funcoes de status do GitHub
 * (success() implicito, always(), !cancelled(), failure()) e trata toda outra condicao como
 * verdadeira (evento pull_request, diff com UI). E o que basta para a pergunta "a falha do L2
 * pula a cadeia?"; nao e um executor de Actions.
 *
 * Sem deps (a lane governance-script-tests nao roda `npm ci`): parsing textual por indentacao.
 * Uso: node scripts/governance/visreg-l2-cadeia.test.mjs
 */

import { spawnSync, execFileSync } from 'node:child_process';
import { readFileSync, mkdtempSync, rmSync, writeFileSync, mkdirSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..', '..');
const WF = join(ROOT, '.github', 'workflows', 'visual-regression.yml');
const L2 = 'Lint L2 — charter states ⇄ manifesto';
const VEREDITO = 'Veredito do Lint L2 — charter states ⇄ manifesto';

let falhas = 0;
const ok = (cond, msg) => {
  console.log(`${cond ? '  ok  ' : '  FALHA'} ${msg}`);
  if (!cond) falhas++;
};

/** Steps do job por indentacao: nome, if, continue-on-error, run. Formato mudou = falha, nao mudo. */
function extrairSteps(texto) {
  const linhas = texto.split(/\r?\n/);
  const steps = [];
  for (let i = 0; i < linhas.length; i++) {
    const m = linhas[i].match(/^(\s*)- name: (.+)$/);
    if (!m) continue;
    const ind = m[1].length;
    const step = { nome: m[2].trim().replace(/^'(.*)'$/, '$1'), id: '', if: '', coe: false, run: '' };
    for (let j = i + 1; j < linhas.length; j++) {
      const l = linhas[j];
      if (/^\s*- (name|uses): /.test(l) && l.match(/^(\s*)/)[1].length <= ind) break;
      const cur = l.match(/^(\s*)/)[1].length;
      if (l.trim() !== '' && cur <= ind) break;
      const mIf = l.match(/^\s*if: (.+)$/);
      if (mIf && cur === ind + 2) step.if = mIf[1].trim();
      const mId = l.match(/^\s*id: (\S+)\s*$/);
      if (mId && cur === ind + 2) step.id = mId[1];
      if (/^\s*continue-on-error: true\s*$/.test(l) && cur === ind + 2) step.coe = true;
      const mRun = l.match(/^(\s*)run: \|\s*$/);
      if (mRun && cur === ind + 2) {
        const corpo = [];
        for (let k = j + 1; k < linhas.length; k++) {
          if (linhas[k].trim() === '') { corpo.push(''); continue; }
          if (linhas[k].match(/^(\s*)/)[1].length <= cur) break;
          corpo.push(linhas[k].slice(cur + 2));
        }
        step.run = corpo.join('\n');
      }
    }
    steps.push(step);
  }
  return steps;
}

/** Simula so as funcoes de status. `falha` = nomes dos steps que falham quando rodam. */
function simular(steps, falha) {
  let jobFalhou = false;
  const rodou = new Map();
  const porId = new Map();
  for (const s of steps) {
    const cond = s.if.replace(/^\$\{\{\s*|\s*\}\}$/g, '');
    let roda;
    if (/always\(\)/.test(cond)) roda = true;
    else if (/!cancelled\(\)/.test(cond)) roda = true;
    else if (/failure\(\)/.test(cond)) roda = jobFalhou;
    else roda = !jobFalhou;
    // condicao de outcome do L2 no veredito: roda so se o L2 nao foi success
    if (roda && /steps\.lint-l2\.outcome != 'success'/.test(cond)) roda = rodou.get(L2) === 'failure';
    // condicao de outcome de OUTRO step (`steps.<id>.outcome == 'success'`): exige que ele tenha passado
    for (const [, id] of cond.matchAll(/steps\.([\w-]+)\.outcome == 'success'/g)) {
      if (roda && porId.get(id) !== 'success') roda = false;
    }
    if (!roda) { rodou.set(s.nome, 'skipped'); if (s.id) porId.set(s.id, 'skipped'); continue; }
    const falhou = falha.has(s.nome);
    rodou.set(s.nome, falhou ? 'failure' : 'success');
    if (s.id) porId.set(s.id, falhou ? 'failure' : 'success');
    if (falhou && !s.coe) jobFalhou = true;
  }
  return { rodou, jobFalhou };
}

const texto = readFileSync(WF, 'utf8');
const steps = extrairSteps(texto);
ok(steps.length > 40, `extraiu ${steps.length} steps do workflow (formato reconhecido)`);
const idx = (nome) => steps.findIndex((s) => s.nome === nome);
const iL2 = idx(L2), iVer = idx(VEREDITO), iCan = idx('Canário anti-verde-vazio');
ok(iL2 >= 0 && iVer >= 0 && iCan >= 0, 'achou L2, Veredito e Canario');

// ── 1. o lint REAL morde: charter com `states:` sem entrada no manifesto ──────────
const sb = mkdtempSync(join(tmpdir(), 'visreg-l2-'));
try {
  mkdirSync(join(sb, 'tests', 'Browser'), { recursive: true });
  mkdirSync(join(sb, 'resources', 'js', 'Pages', 'Ponto'), { recursive: true });
  writeFileSync(join(sb, 'tests', 'Browser', 'visreg-states.json'),
    JSON.stringify({ valid_states: ['default', 'empty'], screens: {} }));
  writeFileSync(join(sb, 'resources', 'js', 'Pages', 'Ponto', 'Conformidade.charter.md'),
    '---\npage: Ponto/Conformidade\nstates: [default, empty]\n---\n# charter de teste\n');
  const g = (...a) => execFileSync('git', a, { cwd: sb, stdio: 'ignore' });
  g('init', '-q'); g('add', '-A');
  const lint = (root) => spawnSync(process.execPath, [join(ROOT, 'scripts', 'visreg-states-lint.mjs'), '--root', root], { encoding: 'utf8' });
  const r = lint(sb);
  ok(r.status > 0 && /NAO esta no manifesto/.test(r.stdout), `lint reprova charter com states: fora do manifesto (exit ${r.status})`);
  // controle: com a entrada no manifesto o mesmo sandbox passa (o assert acima mede o drift, nao outra coisa)
  writeFileSync(join(sb, 'tests', 'Browser', 'visreg-states.json'), JSON.stringify({
    valid_states: ['default', 'empty'],
    screens: { 'ponto-conformidade': { charter: 'resources/js/Pages/Ponto/Conformidade.charter.md', source: 'Ponto/Conformidade', states: ['default', 'empty'] } },
  }));
  const r2 = lint(sb);
  ok(r2.status === 0, `controle: com a entrada no manifesto o lint passa (exit ${r2.status}${r2.status ? ' · ' + r2.stdout.trim().split('\n').pop() : ''})`);
} finally { rmSync(sb, { recursive: true, force: true }); }

// ── 2. com o L2 falhando, a cadeia segue e o job termina vermelho ──────────────────
ok(steps[iL2].coe, 'L2 tem continue-on-error (a falha nao pula a cadeia)');
ok(iVer > iCan, 'Veredito do L2 vem depois do Canario');
const iPrimeiroFalha = steps.findIndex((s) => /failure\(\)/.test(s.if));
ok(iPrimeiroFalha < 0 || iVer < iPrimeiroFalha, 'Veredito vem antes dos steps condicionados a failure() (comentario nomeia o L2)');
ok(/steps\.veredito-l2\.outcome/.test(texto), 'narrativa de falha (PASSOS) inclui o Veredito do L2');

const sim = simular(steps, new Set([L2, VEREDITO]));
ok(sim.rodou.get('Classificar impacto visual do diff') === 'success', `com L2 vermelho, "Classificar impacto" RODA (${sim.rodou.get('Classificar impacto visual do diff')})`);
ok(sim.rodou.get('Modo de execução (ui efetivo)') === 'success', `com L2 vermelho, "Modo de execucao" RODA (${sim.rodou.get('Modo de execução (ui efetivo)')})`);
ok(sim.rodou.get(VEREDITO) === 'failure' && sim.jobFalhou, 'com L2 vermelho, o Veredito roda e o JOB termina vermelho');

const verde = simular(steps, new Set());
ok(verde.rodou.get(VEREDITO) === 'skipped' && !verde.jobFalhou, 'com L2 verde, o Veredito pula e o job nao reprova por ele');

// o `run:` real do Veredito sai 1 e escreve no summary
const sum = join(mkdtempSync(join(tmpdir(), 'visreg-l2-sum-')), 'summary.md');
writeFileSync(sum, '');
const rv = spawnSync('bash', ['-e', '-c', steps[iVer].run], { encoding: 'utf8', env: { ...process.env, L2_OUTCOME: 'failure', GITHUB_STEP_SUMMARY: sum } });
ok(rv.status === 1 && /Lint L2: ❌ `failure`/.test(readFileSync(sum, 'utf8')), `run: do Veredito sai 1 e escreve o motivo no summary (exit ${rv.status})`);

// ── 3. controle de mutacao: sem continue-on-error a cadeia volta a pular ───────────
const mutante = steps.map((s) => (s.nome === L2 ? { ...s, coe: false } : s));
const simM = simular(mutante, new Set([L2]));
ok(simM.rodou.get('Classificar impacto visual do diff') === 'skipped', 'MUTANTE sem continue-on-error: "Classificar impacto" volta a pular (o assert 2 mede o conserto)');

// ── 4. setup quebrado NAO vira regressao visual (2026-10-01) ─────────────────────────
// Run 36733389261 tentativa 1: `Install Playwright system dependencies` estourou o teto, e os
// passos de teste com `always()` rodaram sem Laravel montado, cairam com `Please provide a valid
// cache path` e o comentario culpou "Fluxos visuais Financeiro". Conserto: marca `setup-ok` no
// fim do setup e os passos de teste com `always()` exigem ela.
const DEPS = 'Install Playwright system dependencies';
const testesAlways = steps.filter((s) => /always\(\)/.test(s.if) && /^(Fluxos visuais|E2E de|Contrato do shell)/.test(s.nome));
ok(testesAlways.length >= 15, `achou ${testesAlways.length} passos de teste com always()`);
ok(testesAlways.every((s) => /steps\.setup-ok\.outcome == 'success'/.test(s.if)), 'todo passo de teste com always() exige a marca setup-ok');
const iSetup = steps.findIndex((s) => s.id === 'setup-ok');
const iPrimeiroTeste = steps.findIndex((s) => s.id === 'pest-browser');
ok(iSetup > idx(DEPS) && iSetup < iPrimeiroTeste, 'marca setup-ok fica entre as deps e o primeiro teste');
const simS = simular(steps, new Set([DEPS]));
ok(simS.jobFalhou, 'deps quebradas: o job termina vermelho');
ok(testesAlways.every((s) => simS.rodou.get(s.nome) === 'skipped'), 'deps quebradas: NENHUM passo de teste roda');
ok(simS.rodou.get('Canário anti-verde-vazio') === 'skipped', 'deps quebradas: o canario nao vira a 2a falha falsa');
const simOk = simular(steps, new Set(['Fluxos visuais Compras (ENFORCING — L2.5 · interação × viewport)']));
ok(simOk.rodou.get('Fluxos visuais Sells/Create (ENFORCING — L2.5 · interação × viewport)') === 'success', 'controle: falha de UM teste segue sem pular os irmaos (always() preservado)');
ok(/"tipo":"setup"/.test(texto), 'narrativa de falha (PASSOS) inclui a entrada de setup');
// mutacao: sem a guarda, os fluxos voltam a rodar com o setup quebrado
const mutS = steps.map((s) => ({ ...s, if: s.if.replace(" && steps.setup-ok.outcome == 'success'", '') }));
ok(simular(mutS, new Set([DEPS])).rodou.get('Fluxos visuais Financeiro (ENFORCING — L2.5 · interação × viewport)') === 'success',
  'MUTANTE sem a guarda setup-ok: o fluxo Financeiro volta a rodar com setup quebrado (o assert mede o conserto)');

console.log(falhas ? `\n❌ ${falhas} falha(s)` : '\n✅ L2 reprova o job no fim e a cadeia segue; setup quebrado nao vira regressao visual');
process.exit(falhas ? 1 : 0);
