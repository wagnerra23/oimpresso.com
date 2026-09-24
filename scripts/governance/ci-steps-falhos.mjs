#!/usr/bin/env node
// @ts-check
/**
 * ci-steps-falhos.mjs — escreve no step summary QUAIS steps do job falharam.
 *
 * POR QUE EXISTE (2026-09-22): o `governance-script-tests.yml` tem ~220 steps num job só, e
 * 216 deles rodam com `if: always() && steps.setup.outcome == 'success'` — um teste vermelho
 * NAO pula os outros. O que faltava era ler o resultado: o check aparece como um vermelho
 * único, e achar qual dos 220 caiu exigia rolar o log. Quebrar o job em varios foi medido e
 * descartado (runner e fila a mais, nenhum ganho de tempo, 34 arquivos leem esse YAML).
 *
 * COMO: consulta a API do proprio run (jobs do attempt corrente), acha o job pelo nome e
 * lista os steps com `conclusion == 'failure'`. O step deste script ainda esta em andamento
 * quando roda (conclusion null), entao ele nao conta a si mesmo.
 *
 * HONESTIDADE DE MEDICAO (§5 2026-07-29): se a API falhar, faltar token ou o job nao for
 * achado, o resumo diz NAO MEDIDO com o motivo. Nunca afirma "nenhum falhou" sem ter lido.
 * Sai 0 sempre: o veredito do job ja foi dado pelos steps; este so descreve.
 *
 * Uso (no workflow):
 *   env: GITHUB_TOKEN, JOB_NAME  (+ GITHUB_REPOSITORY, GITHUB_RUN_ID, GITHUB_RUN_ATTEMPT do runner)
 *   node scripts/governance/ci-steps-falhos.mjs
 *   node scripts/governance/ci-steps-falhos.mjs --selftest
 */

import { appendFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';

/**
 * @param {Array<{name:string, steps?:Array<{name:string, conclusion:string|null}>}>} jobs
 * @param {string} nomeJob
 */
export function resumir(jobs, nomeJob) {
  const job = (jobs || []).find((j) => j && j.name === nomeJob);
  if (!job) return { medido: false, motivo: `job "${nomeJob}" nao encontrado no run` };
  const steps = Array.isArray(job.steps) ? job.steps : [];
  const concluidos = steps.filter((s) => s.conclusion != null);
  return {
    medido: true,
    total: concluidos.length,
    falhos: concluidos.filter((s) => s.conclusion === 'failure').map((s) => s.name),
    pulados: concluidos.filter((s) => s.conclusion === 'skipped').length,
  };
}

/** @param {ReturnType<typeof resumir>} r */
export function markdown(r, nomeJob) {
  const L = [`### Steps que falharam — ${nomeJob}`, ''];
  if (!r.medido) {
    L.push(`**NAO MEDIDO** — ${r.motivo}. Isto nao significa que nada falhou: abra o log do job.`);
    return L.join('\n') + '\n';
  }
  if (r.falhos.length === 0) {
    L.push(`Nenhum step falhou (${r.total} steps concluidos${r.pulados ? `, ${r.pulados} pulados` : ''}).`);
  } else {
    L.push(`**${r.falhos.length} de ${r.total}** steps falharam${r.pulados ? ` (${r.pulados} pulados)` : ''}:`, '');
    for (const n of r.falhos) L.push(`- ${n}`);
  }
  return L.join('\n') + '\n';
}

async function buscarJobs(env) {
  const faltando = ['GITHUB_TOKEN', 'GITHUB_REPOSITORY', 'GITHUB_RUN_ID'].filter((k) => !env[k]);
  if (faltando.length) throw new Error(`variavel ausente: ${faltando.join(', ')}`);
  const api = env.GITHUB_API_URL || 'https://api.github.com';
  const attempt = env.GITHUB_RUN_ATTEMPT || '1';
  const jobs = [];
  for (let page = 1; page <= 10; page++) {
    const url = `${api}/repos/${env.GITHUB_REPOSITORY}/actions/runs/${env.GITHUB_RUN_ID}/attempts/${attempt}/jobs?per_page=100&page=${page}`;
    const res = await fetch(url, { headers: { Authorization: `Bearer ${env.GITHUB_TOKEN}`, Accept: 'application/vnd.github+json' } });
    if (!res.ok) throw new Error(`API respondeu HTTP ${res.status}`);
    const body = await res.json();
    jobs.push(...(body.jobs || []));
    if (jobs.length >= (body.total_count || 0) || !(body.jobs || []).length) break;
  }
  return jobs;
}

function selftest() {
  let falhas = 0;
  const t = (nome, ok) => { console.log((ok ? '[OK]   ' : '[FAIL] ') + nome); if (!ok) falhas++; };
  const J = 'governance script tests (advisory)';
  const jobs = [
    { name: 'outro job', steps: [{ name: 'x', conclusion: 'failure' }] },
    { name: J, steps: [
      { name: 'Set up job', conclusion: 'success' },
      { name: 'teste A', conclusion: 'success' },
      { name: 'teste B', conclusion: 'failure' },
      { name: 'teste C', conclusion: 'skipped' },
      { name: 'teste D', conclusion: 'failure' },
      { name: 'Resumo (este step)', conclusion: null },
    ] },
  ];
  const r = resumir(jobs, J);
  t('lista exatamente os steps com failure, na ordem', r.medido && JSON.stringify(r.falhos) === '["teste B","teste D"]');
  t('nao conta o proprio step em andamento (conclusion null)', r.total === 5);
  t('CONTROLE: falha de OUTRO job nao entra', !r.falhos.includes('x'));
  t('conta os pulados a parte', r.pulados === 1);
  const md = markdown(r, J);
  t('markdown diz "2 de 5" e nomeia cada um', /\*\*2 de 5\*\*/.test(md) && /- teste B/.test(md) && /- teste D/.test(md));

  const limpo = resumir([{ name: J, steps: [{ name: 'a', conclusion: 'success' }] }], J);
  t('sem falha => "Nenhum step falhou"', /Nenhum step falhou \(1 steps/.test(markdown(limpo, J)));

  const sumido = resumir([{ name: 'outro', steps: [] }], J);
  const mdSumido = markdown(sumido, J);
  t('job nao achado => NAO MEDIDO, nunca "nenhum falhou"', !sumido.medido && /NAO MEDIDO/.test(mdSumido) && !/Nenhum step falhou/.test(mdSumido));
  t('lista de jobs vazia/nula => NAO MEDIDO', !resumir(null, J).medido);

  console.log(falhas ? `\nSELFTEST FALHOU (${falhas})` : '\nSELFTEST OK — lista os steps falhos do job certo e declara NAO MEDIDO quando nao consegue ler.');
  return falhas ? 1 : 0;
}

async function main() {
  const env = process.env;
  const nomeJob = env.JOB_NAME || '';
  let r;
  if (!nomeJob) r = { medido: false, motivo: 'JOB_NAME nao definido' };
  else {
    try { r = resumir(await buscarJobs(env), nomeJob); }
    catch (e) { r = { medido: false, motivo: String(e && e.message ? e.message : e) }; }
  }
  const md = markdown(r, nomeJob || '(sem nome)');
  process.stdout.write(md);
  if (env.GITHUB_STEP_SUMMARY) {
    try { appendFileSync(env.GITHUB_STEP_SUMMARY, md); } catch { /* summary e conveniencia; o stdout ja tem */ }
  }
  return 0;
}

if (process.argv[1] && fileURLToPath(import.meta.url) === process.argv[1]) {
  if (process.argv.includes('--selftest')) process.exit(selftest());
  main().then((c) => process.exit(c));
}
