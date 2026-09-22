#!/usr/bin/env node
/**
 * brl-scan-workflow.test — bite-test do CONTRATO DE EXECUCAO dos steps de pipe do brl-scan.
 *
 * ── O QUE ELE DEFENDE (medido em 2026-09-22, nao suposto) ─────────────────────
 * `run:` sem `shell:` roda em `bash -e {0}` — com -e, SEM -o pipefail. Num pipeline o rc
 * e do ULTIMO comando, entao `git log <base>..HEAD | node brl-scan-diff --stdin` com base
 * invalida entregava stdin VAZIO ao scanner: `0 achados`, exit 0, STEP VERDE SEM TER LIDO
 * UMA LINHA. Medido na epoca, com o comando real:
 *     bash -e   -c 'git log ... deadbeef..HEAD | node ... --stdin'  -> rc 0   (verde mudo)
 *     bash -euo pipefail -c '<o mesmo>'                             -> rc 128 (falha visivel)
 * O step `--base` nunca teve o furo: nao e pipe, e o proprio scanner sai 2 quando a base
 * nao existe. Classe: gate mudo por fail-open (LICOES_CODE LC-13/LC-33).
 *
 * ── POR QUE NAO USA js-yaml ───────────────────────────────────────────────────
 * MEDIDO: js-yaml nao esta em dependencies NEM devDependencies do package.json, e a lane
 * `governance-script-tests` nao roda `npm ci`. Importar daria ERR_MODULE_NOT_FOUND no CI —
 * o proprio defeito que este arquivo existe pra impedir. Parsing textual, como o resto do
 * repo ja faz com YAML de workflow.
 *
 * ── POR QUE O VETOR E MONTADO EM RUNTIME ──────────────────────────────────────
 * O teste precisa de uma mensagem de commit que o scanner ACUSE. Escrever o padrao literal
 * aqui plantaria no repo exatamente o que a defesa proibe — e obrigaria a alargar a isencao
 * ARQUIVOS_DA_FERRAMENTA, que se declara "estreita e nomeada". Montado por concatenacao, o
 * arquivo fica varrido como qualquer outro.
 *
 * Uso: node scripts/governance/brl-scan-workflow.test.mjs
 */

import { execFileSync, spawnSync } from 'node:child_process';
import { readFileSync, mkdtempSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..', '..');
const WF = join(ROOT, '.github', 'workflows', 'brl-scan.yml');

let falhas = 0;
const ok = (cond, msg) => {
  console.log(`${cond ? '  ok  ' : '  FALHA'} ${msg}`);
  if (!cond) falhas++;
};

/**
 * Extrai o corpo de `run:` de cada step do job, por indentacao.
 * Se o formato mudar a ponto de nao achar os steps, o teste FALHA (nao passa mudo).
 */
function extrairRuns(texto) {
  const linhas = texto.split(/\r?\n/);
  const out = [];
  for (let i = 0; i < linhas.length; i++) {
    const mName = linhas[i].match(/^(\s*)- name: (.+)$/);
    if (!mName) continue;
    const nome = mName[2].trim();
    for (let j = i + 1; j < linhas.length; j++) {
      if (/^\s*- name: /.test(linhas[j])) break;
      const mRun = linhas[j].match(/^(\s*)run: (\|.*)?$/);
      if (!mRun) continue;
      if (mRun[2]) {
        const ind = mRun[1].length;
        const corpo = [];
        for (let k = j + 1; k < linhas.length; k++) {
          if (linhas[k].trim() === '') { corpo.push(''); continue; }
          const curInd = linhas[k].match(/^(\s*)/)[1].length;
          if (curInd <= ind) break;
          corpo.push(linhas[k].slice(ind + 2));
        }
        out.push({ nome, run: corpo.join('\n') });
      } else {
        out.push({ nome, run: linhas[j].replace(/^\s*run: /, '') });
      }
      break;
    }
  }
  return out;
}

const runs = extrairRuns(readFileSync(WF, 'utf8'));
const pipes = runs.filter((r) => /\|\s*node /.test(r.run));

console.log('== contrato: todo step de PIPE declara pipefail ==');
ok(pipes.length === 2, `achou ${pipes.length} step(s) de pipe (esperado 2 — se 0, o extrator quebrou)`);
for (const p of pipes) ok(/set -euo pipefail/.test(p.run), `"${p.nome}" declara set -euo pipefail`);

const stepMsg = pipes.find((p) => /git log/.test(p.run));
ok(!!stepMsg, 'achou o step que varre as mensagens de commit');
if (!stepMsg) { console.log(`\n${falhas} falha(s)`); process.exit(1); }

// ── sandbox git hermetico: nao depende do checkout (raso ou nao) ───────────────
const box = mkdtempSync(join(tmpdir(), 'brlwf-'));
const git = (...a) => execFileSync('git', ['-C', box, ...a], { encoding: 'utf8' });
git('init', '-q', '-b', 'main');
git('config', 'user.email', 't@t.t');
git('config', 'user.name', 't');
writeFileSync(join(box, 'a.txt'), 'a');
git('add', '-A');
git('commit', '-q', '-m', 'chore: mensagem limpa, sem valor algum');
// vetor montado em runtime (ver cabecalho): prefixo da moeda + numero
const VETOR = 'fix: relatorio mostrava ' + 'R' + String.fromCharCode(36) + ' 1.234,56 no rodape';
writeFileSync(join(box, 'b.txt'), 'b');
git('add', '-A');
git('commit', '-q', '-m', VETOR);
const shaA = git('rev-parse', 'HEAD~1').trim();
const shaB = git('rev-parse', 'HEAD').trim();

/** Roda o run do step como o runner roda, com o git apontado pro sandbox. */
function rodar(runBody, baseSha) {
  const r = spawnSync('bash', ['-e', '-c', runBody], {
    cwd: ROOT,
    encoding: 'utf8',
    env: { ...process.env, BASE_SHA: baseSha, GIT_DIR: join(box, '.git'), GIT_WORK_TREE: box },
  });
  return r.status;
}

console.log('\n== comportamento do step (sandbox hermetico) ==');
ok(rodar(stepMsg.run, shaA) === 1, 'MORDE: mensagem de commit com valor -> rc 1');
ok(rodar(stepMsg.run, shaB) === 0, 'LIBERA: range sem valor -> rc 0');
ok(rodar(stepMsg.run, 'deadbeefdeadbeefdeadbeefdeadbeefdeadbeef') !== 0, 'NAO FICA MUDO: base inexistente -> rc != 0');
ok(rodar(stepMsg.run, '') === 2, 'DECLARA nao-medicao: BASE_SHA vazio -> rc 2');

// ── controle de mutacao: o assert acima mede o PIPEFAIL, nao outra coisa ───────
console.log('\n== mutacao: sem pipefail, o verde mudo VOLTA (prova que o assert mede isso) ==');
const mutado = stepMsg.run.replace(/^\s*set -euo pipefail\s*$/m, 'set -eu');
ok(mutado !== stepMsg.run, 'mutante difere do original');
ok(rodar(mutado, 'deadbeefdeadbeefdeadbeefdeadbeefdeadbeef') === 0, 'sem pipefail: base inexistente -> rc 0 (o furo original)');

rmSync(box, { recursive: true, force: true });
console.log(falhas === 0 ? '\nTUDO OK' : `\n${falhas} falha(s)`);
process.exit(falhas === 0 ? 0 : 1);
