#!/usr/bin/env node
// placar.test.mjs — bite-test do PR-A6. Exercita o CLI de FORA (subprocesso), nunca só
// helpers puros: assert sobre satélite exportado não prova contrato de pipeline (§5 2026-07-30).
//
// O QUE ESTE TESTE PROVA, e por que cada caso existe:
//   BITE      o placar reprova pelo CONTEÚDO (ausência sem motivo · motivo fora do enum ·
//             declaração podre/órfã/volátil). Se medisse PRESENÇA de comentário, nenhum
//             destes casos seria distinguível — é a prova de que não é presence-gate (LC-11).
//   RELEASE   corpus legítimo NÃO é acusado (controle negativo — sem ele o gate seria carimbo
//             de reprovação, tão inútil quanto o carimbo de aprovação).
//   NÃO MEDI  corpus vazio/ilegível sai 2, NUNCA "0 de 0 = 100%" (§5 2026-08-04 + 2026-07-29).
//   MODOS     `--check` morde, default só reporta — os dois rodados, porque validar um modo
//             do job e chamar de verde é §5 2026-07-28.
//   RETRABALHO fixture git REAL com uma seção que volta a ausente — senão o 3º eixo é carimbo.

import { execFileSync } from 'node:child_process';
import { writeFileSync, mkdirSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const HERE = dirname(fileURLToPath(import.meta.url));
const SCRIPT = join(HERE, 'placar.mjs');
const TMP = join(tmpdir(), `placar-test-${process.pid}`);

let fails = 0;
const ok = (c, m) => { if (c) console.log(`  ✓ ${m}`); else { console.error(`  ✗ ${m}`); fails++; } };

/** Roda o CLI e devolve {rc, out, err} — nunca deixa exceção mascarar o exit code. */
const cli = (args) => {
  try { return { rc: 0, out: execFileSync('node', [SCRIPT, ...args], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] }), err: '' }; }
  catch (e) { return { rc: e.status ?? 1, out: e.stdout || '', err: e.stderr || '' }; }
};

/** Monta um diretório de alvos e devolve o path. `secoes` null = sem arquivo de declaração. */
let n = 0;
const corpus = (alvo, secoes) => {
  const dir = join(TMP, `c${++n}`);
  mkdirSync(dir, { recursive: true });
  writeFileSync(join(dir, 't.alvo.json'), typeof alvo === 'string' ? alvo : JSON.stringify(alvo));
  if (secoes) writeFileSync(join(dir, 't.secoes.json'), JSON.stringify(secoes));
  return dir;
};
const secao = (ausente) => (ausente ? { seletor: '.x', nos: 0, ausente: true } : { seletor: '.x', nos: 1, filhos: 2 });
const ALVO = (aus = {}, extra = {}) => ({
  tela: 'T', ausentes: [],
  secoes: { header: secao(false), kpis: secao(!!aus.kpis), metas: secao(!!aus.metas) },
  ...extra,
});

mkdirSync(TMP, { recursive: true });
console.log('placar.test — bite/release do PR-A6\n');

/* ── 1. CONTROLE NEGATIVO: corpus legítimo não é acusado ─────────────────────────────── */
const sao = corpus(ALVO(), null);
let r = cli(['--dir', sao, '--check']);
ok(r.rc === 0, 'RELEASE: 3 seções presentes, sem declaração → exit 0 (não acusa o legítimo)');
ok(/entregue 3 de 3/.test(r.out), 'RELEASE: reporta o PAR entregue/alvo ("entregue 3 de 3")');

/* ── 2. BITE: ausência SEM motivo declarado ──────────────────────────────────────────── */
r = cli(['--dir', corpus(ALVO({ kpis: true }), null), '--check']);
ok(r.rc === 1, 'BITE: seção ausente e nenhum _ausentes → exit 1');
ok(/sem motivo|não tem motivo/.test(r.out + r.err), 'BITE: a mensagem NOMEIA a ausência sem motivo');

/* ── 3. RELEASE: mesma ausência COM motivo válido ────────────────────────────────────── */
r = cli(['--dir', corpus(ALVO({ kpis: true }), { _ausentes: { kpis: { motivo: 'sem endpoint', nota: 'GET /kpis não existe' } } }), '--check']);
ok(r.rc === 0, 'RELEASE: mesma ausência com motivo válido → exit 0');
ok(/entregue 2 de 3/.test(r.out), 'RELEASE: o placar CAI pra 2 de 3 (o número acompanha o corpus)');

/* ── 4. BITE: motivo fora do enum ────────────────────────────────────────────────────── */
r = cli(['--dir', corpus(ALVO({ kpis: true }), { _ausentes: { kpis: { motivo: 'depois eu faço' } } }), '--check']);
ok(r.rc === 1, 'BITE: motivo inventado fora do enum → exit 1');

/* ── 5. BITE: declaração PODRE (motivo pra seção entregue) ───────────────────────────── */
r = cli(['--dir', corpus(ALVO(), { _ausentes: { header: { motivo: 'sem endpoint' } } }), '--check']);
ok(r.rc === 1, 'BITE: motivo declarado pra seção ENTREGUE (ponteiro podre) → exit 1');

/* ── 6. BITE: declaração ÓRFÃ (id que não é seção) ───────────────────────────────────── */
r = cli(['--dir', corpus(ALVO(), { _ausentes: { fantasma: { motivo: 'decisão [W]' } } }), '--check']);
ok(r.rc === 1, 'BITE: _ausentes aponta id inexistente → exit 1');

/* ── 7. BITE: declaração VOLÁTIL (ausentes preenchido no alvo.json) ──────────────────── */
r = cli(['--dir', corpus(ALVO({}, { ausentes: [{ id: 'kpis', motivo: 'sem endpoint' }] }), null), '--check']);
ok(r.rc === 1, 'BITE: "ausentes" no alvo.json (campo que alvo.mjs reescreve) → exit 1');
ok(/REESCREVE|secoes\.json/.test(r.out + r.err), 'BITE: a mensagem ensina o arquivo CERTO da declaração');

/* ── 8. NÃO MEDI: corpus vazio, ilegível, sem seções ─────────────────────────────────── */
const vazio = join(TMP, 'vazio'); mkdirSync(vazio, { recursive: true });
ok(cli(['--dir', vazio, '--check']).rc === 2, 'NÃO MEDI: diretório sem alvo → exit 2 (nunca "0 de 0 = 100%")');
ok(cli(['--dir', corpus('{ isto não é json', null), '--check']).rc === 2, 'NÃO MEDI: alvo ilegível → exit 2, nunca verde');
ok(cli(['--dir', corpus({ tela: 'T', secoes: {} }, null), '--check']).rc === 2, 'NÃO MEDI: alvo com 0 seções → exit 2');
ok(cli(['--dir', join(TMP, 'nao-existe'), '--check']).rc === 2, 'NÃO MEDI: diretório inexistente → exit 2');

/* ── 9. Eixo --render: o alvo é o denominador, o render é o lado medido ──────────────── */
const rend = join(TMP, 'render.json');
writeFileSync(rend, JSON.stringify({ tela: 'T', secoes: { header: secao(false), kpis: secao(true), metas: secao(false) } }));
r = cli(['--dir', corpus(ALVO(), null), '--render', rend, '--check']);
ok(r.rc === 1, 'BITE (render): seção no alvo e AUSENTE no render, sem motivo → exit 1');
ok(/fonte: render|entregue 2 de 3/.test(r.out) || /não está no render/.test(r.out + r.err), 'BITE (render): o placar declara que mediu o RENDER, não o alvo');
r = cli(['--dir', corpus(ALVO(), { _ausentes: { kpis: { motivo: 'campo inexistente' } } }), '--render', rend, '--check']);
ok(r.rc === 0, 'RELEASE (render): mesma ausência com motivo → exit 0');

/* ── 10. MODOS: sem --check reporta e NÃO morde (os dois modos do job, §5 2026-07-28) ── */
const quebrado = corpus(ALVO({ kpis: true }), null);
ok(cli(['--dir', quebrado]).rc === 0, 'MODO: sem --check o script só REPORTA (exit 0) — quem morde é o --check');
const md = cli(['--dir', quebrado, '--md']);
ok(/entregue 2 de 3/.test(md.out), 'MODO --md: o comentário carrega o PAR entregue/alvo (conteúdo, não string fixa)');
ok(/não fecha/i.test(md.out), 'MODO --md: o comentário DIZ que não fecha — o PR não recebe placar mudo');
ok(/entregue 3 de 3/.test(cli(['--dir', sao, '--md']).out), 'CONTROLE POSITIVO --md: o número MUDA com o corpus (o md não é carimbo)');
ok(JSON.parse(cli(['--dir', sao, '--json']).out).somaEntregue === 3, 'MODO --json: expõe o par pra máquina');

/* ── 11. --indice: o PR-A8 pluga aqui, e o eixo de TELA não muda por causa dele ──────── */
// Até 2026-09-17 este caso pinava "--indice sai 2 (não implementado)" — LC-15, não anunciar o
// que não se honra. O A8 foi implementado (scripts/qa/placar-indice.mjs) e o contrato virou
// outro: `--indice` sem playbook algum sai 2 (NÃO MEDI), nunca verde vazio. Os casos do eixo
// de lista vivem em placar-indice.test.mjs — aqui fica só a fronteira entre os dois eixos.
r = cli(['--dir', sao, '--indice', 'nao/existe/00-INDICE.md', '--root', TMP]);
ok(r.rc === 2, '--indice com playbook inexistente → 2 (NÃO MEDI), nunca "0 de 0 = 100%"');
ok(/entregue 3 de 3/.test(cli(['--dir', sao]).out), 'FRONTEIRA: o eixo de TELA segue medindo o alvo, intacto');

/* ── 12. RETRABALHO: fixture git real — seção entregue que volta a ausente ───────────── */
const repo = join(TMP, 'repo');
mkdirSync(join(repo, 'governance', 'design', 'targets'), { recursive: true });
const alvoRel = 'governance/design/targets/t.alvo.json';
const g = (...a) => execFileSync('git', a, { cwd: repo, encoding: 'utf8', env: { ...process.env, MSYS_NO_PATHCONV: '1' } });
try {
  g('init', '-q'); g('config', 'user.email', 't@t'); g('config', 'user.name', 't');
  writeFileSync(join(repo, alvoRel), JSON.stringify(ALVO()));                 // kpis ENTREGUE
  g('add', '-A'); g('commit', '-qm', 'v1');
  writeFileSync(join(repo, alvoRel), JSON.stringify(ALVO({ kpis: true })));   // kpis REABRIU
  g('add', '-A'); g('commit', '-qm', 'v2');
  const { retrabalhoDe, reaberturasEntre } = await import('./placar.mjs?t=' + Date.now());
  const t = retrabalhoDe(alvoRel, { root: repo });
  ok(t.medido === true && t.reaberturas.some((x) => x.id === 'kpis'), `BITE (retrabalho): seção reaberta é detectada (${JSON.stringify(t.reaberturas)})`);
  const t2 = retrabalhoDe('../fora-do-repo.json', { root: repo });
  ok(t2.medido === false, 'NÃO MEDI (retrabalho): alvo fora do repo → "não medi", nunca "0 reaberturas"');
  ok(reaberturasEntre(new Set(['a', 'b']), new Set(['a'])).join() === 'b' && reaberturasEntre(new Set(['a']), new Set(['a', 'b'])).length === 0,
    'CONTROLE: reabertura é entregue→ausente, e ausente→entregue NÃO conta');
} catch (e) { ok(false, `fixture git do retrabalho falhou: ${e.message.slice(0, 160)}`); }

// ── a nota de forma DERIVA do disco, nao afirma (LC-10) ────────────────────────────────
// A frase hardcoded "que ainda nao existe" nasceu verdadeira e virou FALSA no mesmo dia em
// que o secao-check entrou (2026-09-17 20:46Z, #7488), seguindo a ser postada em todo PR.
// Os DOIS ramos sao exercitados: sem os dois, um `return` fixo passaria no teste.
try {
  const { notaDeForma } = await import('./placar.mjs?t=' + Date.now());
  const comA3 = join(TMP, 'raiz-com-a3'); mkdirSync(join(comA3, 'scripts', 'qa'), { recursive: true });
  writeFileSync(join(comA3, 'scripts', 'qa', 'secao-check.mjs'), '// existe');
  const semA3 = join(TMP, 'raiz-sem-a3'); mkdirSync(join(semA3, 'scripts', 'qa'), { recursive: true });
  const nComA3 = notaDeForma(comA3), nSemA3 = notaDeForma(semA3);
  ok(!/ainda não existe/.test(nComA3) && /design-memory-gate/.test(nComA3),
    `DERIVA (com secao-check no disco): nao diz "ainda nao existe" (${nComA3.slice(-70)})`);
  ok(/ainda não existe nesta árvore/.test(nSemA3),
    'DERIVA (sem secao-check no disco): volta a dizer que nao existe');
  ok(nComA3 !== nSemA3, 'CONTROLE: os dois ramos DIFEREM — se fossem iguais, a derivacao seria decorativa');
  ok([nComA3, nSemA3].every((n) => /não afirma fidelidade de forma/.test(n)),
    'DURAVEL: o recorte ("nao afirma forma") e afirmado nos DOIS ramos — so a existencia deriva');
} catch (e) { ok(false, `nota de forma: ${e.message.slice(0, 160)}`); }

rmSync(TMP, { recursive: true, force: true });
console.log(`\n${fails ? `${fails} FALHA(S)` : 'todos os casos passaram'}`);
process.exit(fails ? 1 : 0);
