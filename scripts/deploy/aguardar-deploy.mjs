#!/usr/bin/env node
// aguardar-deploy.mjs — espera o 1º deploy CONCLUÍDO (não cancelado) que contenha um commit.
//
// Uso:
//   node scripts/deploy/aguardar-deploy.mjs <sha-do-merge> [--timeout-min 120] [--intervalo-s 60]
//   node scripts/deploy/aguardar-deploy.mjs --selftest
//
// Saída: uma linha `DEPLOY <run-id> <conclusion> <head-sha>` e exit 0 (success) · 1 (deploy
// concluiu com falha) · 2 (uso, timeout ou sonda sem resposta — NÃO MEDI, nunca "passou").
//
// POR QUE EXISTE (medido em 2026-09-29, sessão das threads 23-26 do Ponto):
//   1. O deploy do PRÓPRIO commit do merge costuma ser CANCELADO: o deploy.yml cancela o
//      anterior quando entra push novo no main. Quem leva o código ao ar é um deploy de um
//      commit POSTERIOR, que contém o merge.
//   2. A listagem `actions/workflows/deploy.yml/runs` e o `gh run list` serviram retratos de
//      semanas atrás (runs de 18/09 e 28/08 respondidos em 29/09) — §5 2026-08-13, leitura
//      única de fonte que pode estar atrasada. A consulta por `actions/runs?head_sha=` veio
//      atual em todas as leituras do dia.
//   3. O vigia improvisado da sessão consultava só o sha do merge e o topo do main. O deploy
//      que passou (36572106632) era de um commit INTERMEDIÁRIO — o vigia nunca o viu.
// Por isso aqui a busca é por SHA, em TODOS os commits do main que contêm o merge
// (`git rev-list --ancestry-path`), e o cancelado é ignorado (não é veredito).
//
// Não é o `screen-smoke-after-merge.yml`: aquele reage por evento no CI e está desligado
// (ativação é [W]). Este é para a sessão que precisa esperar o deploy antes do smoke.

import { execFileSync } from 'node:child_process';

const REPO = 'wagnerra23/oimpresso.com';

/** Escolhe o veredito entre runs de deploy. Função pura — é o que o --selftest trava. */
export function escolher(runs, contemMerge) {
  for (const r of runs) {
    if (r.status !== 'completed') continue;
    if (r.conclusion === 'cancelled' || r.conclusion === 'skipped') continue;
    if (!contemMerge(r.head_sha)) continue;
    return r;
  }
  return null;
}

function sh(cmd, args) {
  return execFileSync(cmd, args, { encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] }).trim();
}

function commitsComOMerge(merge) {
  sh('git', ['fetch', '-q', 'origin', 'main']);
  const depois = sh('git', ['rev-list', '--ancestry-path', `${merge}..origin/main`]).split('\n').filter(Boolean);
  return [...depois, merge]; // mais novo primeiro; o próprio merge por último
}

function deploysDoSha(sha) {
  const out = sh('gh', ['api', `repos/${REPO}/actions/runs?head_sha=${sha}&per_page=30`,
    '--jq', '[.workflow_runs[]|select(.path|test("deploy.yml"))|{id,status,conclusion,head_sha}]']);
  return JSON.parse(out || '[]');
}

async function main() {
  const args = process.argv.slice(2);
  if (args.includes('--selftest')) return selftest();
  const merge = args.find((a) => /^[0-9a-f]{7,40}$/.test(a));
  if (!merge) { console.error('uso: aguardar-deploy.mjs <sha-do-merge> [--timeout-min N] [--intervalo-s N]'); process.exit(2); }
  const val = (k, d) => { const i = args.indexOf(k); return i >= 0 ? Number(args[i + 1]) : d; };
  const limite = Date.now() + val('--timeout-min', 120) * 60_000;
  const intervalo = val('--intervalo-s', 60) * 1000;

  const cheio = sh('git', ['rev-parse', merge]);
  const contem = (sha) => { try { sh('git', ['merge-base', '--is-ancestor', cheio, sha]); return true; } catch { return false; } };

  for (;;) {
    let consultou = 0;
    try {
      for (const sha of commitsComOMerge(cheio)) {
        const r = escolher(deploysDoSha(sha), contem);
        consultou++;
        if (r) {
          console.log(`DEPLOY ${r.id} ${r.conclusion} ${r.head_sha}`);
          process.exit(r.conclusion === 'success' ? 0 : 1);
        }
      }
    } catch (e) {
      console.error(`SONDA FALHOU: ${String(e.message || e).split('\n')[0]}`);
    }
    if (Date.now() > limite) {
      console.log(`NÃO MEDI: nenhum deploy concluído contendo ${cheio.slice(0, 9)} até o timeout (${consultou} commit(s) consultado(s) na última volta).`);
      process.exit(2);
    }
    await new Promise((r) => setTimeout(r, intervalo));
  }
}

function selftest() {
  const contem = (s) => s !== 'antes';
  const casos = [
    ['cancelado não é veredito; pega o concluído posterior',
      [{ id: 1, status: 'completed', conclusion: 'cancelled', head_sha: 'm' }, { id: 2, status: 'completed', conclusion: 'success', head_sha: 'x' }], 2],
    ['em andamento é ignorado', [{ id: 3, status: 'in_progress', conclusion: null, head_sha: 'x' }], null],
    ['falha conta como veredito (não se esconde)', [{ id: 4, status: 'completed', conclusion: 'failure', head_sha: 'x' }], 4],
    ['deploy de commit ANTERIOR ao merge não conta', [{ id: 5, status: 'completed', conclusion: 'success', head_sha: 'antes' }], null],
  ];
  let falhas = 0;
  for (const [nome, runs, esperado] of casos) {
    const r = escolher(runs, contem);
    const ok = (r ? r.id : null) === esperado;
    if (!ok) falhas++;
    console.log(`${ok ? 'ok  ' : 'FALHA'} ${nome}`);
  }
  process.exit(falhas ? 1 : 0);
}

main();
