#!/usr/bin/env node
// status.test.mjs — BITE-TEST do painel/catraca do design-sync (`status.mjs`).
//
// POR QUE EXISTE. O `status.mjs` é consumido por CÓDIGO REAL — `recibos-ci.mjs` o invoca duas
// vezes (`--refresh` na L129 e `--run-test` na L121) e trata `rc !== 0` como erro — e é o
// comando que o painel do protocolo (`scripts/design/protocolo.config.mjs`) publica em 5 das 6
// fases. Até 2026-09-13 ele não tinha teste nem `--selftest`: as catracas `--check-mapping` e
// `--check-lifecycle` nunca foram exercitadas, e catraca que ninguém exercita é indistinguível
// de catraca que sai 0 sempre (LC-13).
//
// O QUE ELE EXERCITA. O CLI DE FORA, por subprocesso (LC-15 · §5 2026-07-30): o `status.mjs`
// não exporta nada — argv, exit code e o texto do mapa são o contrato inteiro, e todos vivem
// no corpo do módulo.
//
// O QUE ELE **NÃO** EXERCITA, e é declarado de propósito: os caminhos de ESCRITA (`--refresh`,
// `--mark-*`, `--run-test`, `--record-smoke`) delegam ao `bundle-transaction.mjs`, que já tem
// dono e bite-test próprio (`bundle-transaction.test.mjs`, wirado nesta mesma lane). Cobri-los
// aqui duplicaria régua consolidada (§5 2026-07-09 · LC-19). O que é só do `status.mjs` — e
// portanto o que este arquivo prova — é a LEITURA, os EXIT CODES e as duas catracas.
//
//   L1  `--root` obedecido · report ausente = exit 2 (o `recibos-ci` distingue rc)
//   L2  `--json` sai parseável e idêntico · sem ele, mapa humano
//   L3  `--check-mapping` morde em `blocked` e solta sem                → par
//   L4  `--check-lifecycle` sem `--source`/`--module` = exit 2 (legado não é bloqueado global)
//   L5  `--minimum` inválido = exit 2 (uso != veredito)
//   L6  ordem do lifecycle é a do array, não alfabética                → par abaixo/acima
//   L7  `--source` que não casa NINGUÉM morde (typo não vira anistia)  → fail-closed
//   L8  `--module` filtra o escopo
//   L9  mapa humano esconde `unchanged`+`applied` e mostra o resto     → par
//   L10 D2: tela sem `module` aponta o PEDIDO que declara o alvo       → 3 pares
//
// Node puro, sem deps/rede/DB. Fixtures em tmpdir do Node (nunca "/tmp" literal: §5 2026-08-21
// — no Windows o Bash e o Node resolvem esse caminho pra lugares diferentes).
//
// Rodar: node scripts/design-sync/status.test.mjs
//   exit 0 = as catracas mordem e soltam onde devem · exit 1 = alguma prova caiu

import { mkdtempSync, mkdirSync, writeFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { spawnSync } from 'node:child_process';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const HERE = dirname(fileURLToPath(import.meta.url));
const ROOT = resolve(HERE, '../..');
const SCRIPT = join(HERE, 'status.mjs');

let fails = 0;
const check = (name, cond, extra = '') => {
  console.log(`${cond ? '[OK]' : '[FAIL]'} ${name}${cond ? '' : '  <- ' + extra}`);
  if (!cond) fails++;
};

const TMP = mkdtempSync(join(tmpdir(), 'ds-status-bite-'));

/** Roda o CLI REAL. Nunca lança: exit 1 e 2 são resultados ESPERADOS na maior parte dos casos. */
function run(raiz, args) {
  const r = spawnSync(process.execPath, [SCRIPT, '--root', raiz, ...args], {
    encoding: 'utf8', cwd: ROOT, stdio: ['ignore', 'pipe', 'pipe'],
  });
  if (r.error) throw r.error;
  // stdout E stderr: o veredito das catracas sai por stderr, e um runner que lê só stdout
  // fica cego justamente no caso que interessa (§5 2026-08-14).
  return { code: r.status ?? 1, out: r.stdout || '', err: r.stderr || '' };
}

const tela = (o) => ({
  source: 'x-page.jsx', bundleChange: 'unchanged', target: 'resources/js/Pages/X/Index.tsx',
  module: 'X', mapping: 'charter.component', comparison: 'SEMANTICO',
  lifecycleState: 'anchored', applicationState: 'pending', applicationEvidence: null,
  nextAction: 'gerar/registrar map.json antes de aplicar', ...o,
});

/** Monta uma raiz com `application-report.json` e, opcionalmente, docs de pedido versionados. */
function raiz(nome, { screens = [], pedidos = {}, semReport = false } = {}) {
  const dir = join(TMP, nome);
  mkdirSync(join(dir, 'scripts', 'design-sync', 'state'), { recursive: true });
  if (!semReport) {
    const report = {
      schema: 1, generatedAt: '2026-09-13T00:00:00.000Z',
      bundle: { id: 'abc123', mode: 'delta', transportComplete: true },
      summary: { transportChanges: 1, screens: screens.length },
      transportChanges: [{ path: 'x-page.jsx', change: 'modified', bytes: 42, role: 'cowork-source' }],
      screens,
    };
    writeFileSync(join(dir, 'scripts/design-sync/state/application-report.json'), JSON.stringify(report), 'utf8');
  }
  for (const [rel, corpo] of Object.entries(pedidos)) {
    mkdirSync(join(dir, dirname(rel)), { recursive: true });
    writeFileSync(join(dir, rel), corpo, 'utf8');
  }
  if (Object.keys(pedidos).length) {
    // `pedidosQueDeclaram` usa `git grep`, que só enxerga arquivo RASTREADO — sem índice, a
    // busca devolveria vazio e o L10 viraria teste de nada (verde por não-execução, LC-13).
    for (const argv of [['init', '-q'], ['add', '-A']]) spawnSync('git', argv, { cwd: dir, encoding: 'utf8' });
  }
  return dir;
}

try {
  // ---- L1 — `--root` obedecido · report ausente = exit 2 ----------------------
  {
    const vazio = raiz('l1-sem', { semReport: true });
    const r = run(vazio, []);
    check('L1a report ausente = exit 2 com motivo (o recibos-ci trata rc != 0 como erro)',
      r.code === 2 && /relatório ausente/.test(r.err), `code=${r.code} err=${JSON.stringify(r.err.slice(0, 120))}`);

    const com = raiz('l1-com', { screens: [tela({})] });
    const ok = run(com, []);
    check('L1b com report, sai 0 e lê do --root (não do repo real)',
      ok.code === 0 && ok.out.includes('bundle abc123'), `code=${ok.code} out=${JSON.stringify(ok.out.slice(0, 140))}`);
  }

  // ---- L2 — `--json` parseável · sem ele, mapa humano -------------------------
  {
    const dir = raiz('l2', { screens: [tela({})] });
    const j = run(dir, ['--json']);
    let parsed = null;
    try { parsed = JSON.parse(j.out); } catch { /* fica null */ }
    check('L2a `--json` emite JSON parseável com as telas',
      j.code === 0 && parsed && parsed.screens.length === 1 && parsed.bundle.id === 'abc123',
      `code=${j.code} out=${JSON.stringify(j.out.slice(0, 140))}`);
    const t = run(dir, []);
    check('L2b sem `--json` é mapa humano, NÃO JSON (os dois modos não se confundem)',
      t.out.includes('APLICAÇÃO NAS TELAS/MÓDULOS') && !t.out.trimStart().startsWith('{'),
      `out=${JSON.stringify(t.out.slice(0, 140))}`);
  }

  // ---- L3 — `--check-mapping` morde em `blocked` e solta sem ------------------
  // Olha `applicationState`, não `lifecycleState`: a segunda tela tem lifecycle `blocked` mas
  // applicationState `pending`, e NÃO pode reprovar. Sem esse controle, um gate que lesse o
  // campo errado passaria no caso positivo e reprovaria trabalho legítimo.
  {
    const limpo = raiz('l3-ok', { screens: [tela({}), tela({ source: 'b.jsx', lifecycleState: 'blocked' })] });
    check('L3a `--check-mapping` SOLTA quando nenhuma tela está `applicationState: blocked`',
      run(limpo, ['--check-mapping']).code === 0);

    const travado = raiz('l3-bad', { screens: [tela({}), tela({ source: 'b.jsx', applicationState: 'blocked' })] });
    const r = run(travado, ['--check-mapping']);
    check('L3b `--check-mapping` MORDE com tela blocked (fail-closed no destino ambíguo)',
      r.code === 1 && /sem destino inequívoco/.test(r.err), `code=${r.code} err=${JSON.stringify(r.err.slice(0, 120))}`);
  }

  // ---- L4/L5 — erro de USO é exit 2, nunca 1 nem 0 ---------------------------
  // O `recibos-ci` e o painel distinguem: 1 = veredito da catraca, 2 = eu chamei errado.
  // Colapsar os dois faria "chamei errado" parecer "o escopo reprovou".
  {
    const dir = raiz('l4', { screens: [tela({})] });
    const semEscopo = run(dir, ['--check-lifecycle']);
    check('L4 `--check-lifecycle` sem --source/--module = exit 2 (legado não é bloqueado global)',
      semEscopo.code === 2 && /exige --source ou --module/.test(semEscopo.err),
      `code=${semEscopo.code} err=${JSON.stringify(semEscopo.err.slice(0, 120))}`);

    const ruim = run(dir, ['--check-lifecycle', '--source', 'x-page.jsx', '--minimum', 'quase-la']);
    check('L5 `--minimum` fora do vocabulário = exit 2 (não vira "passou por default")',
      ruim.code === 2 && /estado mínimo inválido/.test(ruim.err),
      `code=${ruim.code} err=${JSON.stringify(ruim.err.slice(0, 120))}`);
  }

  // ---- L6 — a ordem do lifecycle é a do array, não alfabética -----------------
  // Em ordem alfabética `applied` < `compared`; na ordem REAL do progresso é o contrário.
  // Um `<` sobre string passaria em L6b e reprovaria L6a — por isso o par.
  {
    const dir = raiz('l6', { screens: [tela({ lifecycleState: 'compared' })] });
    const abaixo = run(dir, ['--check-lifecycle', '--source', 'x-page.jsx', '--minimum', 'applied']);
    check('L6a tela `compared` abaixo do mínimo `applied` MORDE',
      abaixo.code === 1 && /abaixo de applied/.test(abaixo.err), `code=${abaixo.code} err=${JSON.stringify(abaixo.err.slice(0, 120))}`);
    check('L6b mesma tela SOLTA no mínimo `compared` (limiar exato, não "sempre reprova")',
      run(dir, ['--check-lifecycle', '--source', 'x-page.jsx', '--minimum', 'compared']).code === 0);
    check('L6c e SOLTA num mínimo anterior (`anchored`) — a ordem é a do progresso',
      run(dir, ['--check-lifecycle', '--source', 'x-page.jsx', '--minimum', 'anchored']).code === 0);
  }

  // ---- L7 — seleção vazia NÃO dá anistia -------------------------------------
  // O caso mais caro: um typo no `--source` faria a catraca não selecionar ninguém. Se isso
  // saísse 0, a catraca ficaria verde por NÃO-EXECUÇÃO — o `0 failed` de suíte que não rodou.
  {
    const dir = raiz('l7', { screens: [tela({ lifecycleState: 'validated' })] });
    const r = run(dir, ['--check-lifecycle', '--source', 'nao-existe-page.jsx', '--minimum', 'anchored']);
    check('L7 `--source` que não casa ninguém MORDE (typo não vira verde · fail-closed)',
      r.code === 1, `code=${r.code} — seleção vazia saindo 0 seria catraca verde por não-execução`);
  }

  // ---- L8 — `--module` filtra o escopo ---------------------------------------
  {
    const dir = raiz('l8', {
      screens: [tela({ module: 'Alfa', lifecycleState: 'validated' }),
        tela({ source: 'b.jsx', module: 'Beta', lifecycleState: 'review' })],
    });
    check('L8a `--module Alfa` só olha o Alfa (a tela atrasada do Beta não contamina)',
      run(dir, ['--check-lifecycle', '--module', 'Alfa', '--minimum', 'applied']).code === 0);
    check('L8b `--module Beta` morde na tela atrasada',
      run(dir, ['--check-lifecycle', '--module', 'Beta', '--minimum', 'applied']).code === 1);
  }

  // ---- L9 — o mapa humano esconde o que não é acionável ----------------------
  {
    const dir = raiz('l9', {
      screens: [tela({ source: 'pronta.jsx', bundleChange: 'unchanged', applicationState: 'applied' }),
        tela({ source: 'pendente.jsx', bundleChange: 'modified', applicationState: 'pending' })],
    });
    const out = run(dir, []).out;
    check('L9a tela `unchanged` + `applied` some da lista acionável', !out.includes('pronta.jsx'), out.slice(0, 200));
    check('L9b tela pendente aparece com alvo e próxima ação', out.includes('pendente.jsx') && out.includes('gerar/registrar map.json'), out.slice(0, 200));
  }

  // ---- L10 — D2: tela sem `module` aponta o PEDIDO que declara o alvo --------
  // A regra nasceu de um erro de leitura (docblock do status.mjs L17-39): o mapa dizia
  // `módulo ?` e a sessão concluiu que faltava decisão do [W], quando o PEDIDO já estava no
  // repo. Os 3 pares abaixo garantem que o ponteiro aparece onde deve e SÓ onde deve.
  {
    const PEDIDO = 'governance/design-requests/PEDIDO-CODE.md';
    const corpo = '# Pedido\n\nA tela `relatorios-page.jsx` mora em Modules/Rel, rota /rel.\n';

    const semDono = raiz('l10-a', {
      screens: [tela({ source: 'relatorios-page.jsx', module: null })],
      pedidos: { [PEDIDO]: corpo },
    });
    const a = run(semDono, []).out;
    check('L10a tela sem `module` aponta o pedido que cita o basename da fonte',
      a.includes('pedido:') && a.includes('PEDIDO-CODE.md'), a.slice(0, 300));

    // MORDE 1 — o mesmo conteúdo num arquivo cujo NOME não casa o padrão de pedido
    // (PEDIDO|COLAR-NO-CODE|PONTE|HANDOFF|cowork-inbox) não vira ponteiro: o grep acha,
    // o filtro de nome descarta. Sem este par, o L10a passaria com o filtro removido.
    const foraDoPadrao = raiz('l10-b', {
      screens: [tela({ source: 'relatorios-page.jsx', module: null })],
      pedidos: { 'governance/design-requests/anotacoes.md': corpo },
    });
    check('L10b doc que cita a fonte mas NÃO é pedido (nome fora do padrão) não vira ponteiro',
      !run(foraDoPadrao, []).out.includes('pedido:'), run(foraDoPadrao, []).out.slice(0, 300));

    // MORDE 2 — tela COM module não paga o grep nem imprime ponteiro. O ponteiro existe pra
    // preencher silêncio; imprimi-lo onde o mapa já sabe seria ruído em 130+ telas.
    const comDono = raiz('l10-c', {
      screens: [tela({ source: 'relatorios-page.jsx', module: 'Rel' })],
      pedidos: { [PEDIDO]: corpo },
    });
    check('L10c tela COM `module` não imprime ponteiro (o mapa já sabe o alvo)',
      !run(comDono, []).out.includes('pedido:'), run(comDono, []).out.slice(0, 300));

    // MORDE 3 — basename curto (< 4 chars) é descartado antes do grep: buscar "ab" no corpus
    // de pedidos casaria qualquer coisa e devolveria ponteiro errado com cara de recibo.
    const curto = raiz('l10-d', {
      screens: [tela({ source: 'ab.jsx', module: null })],
      pedidos: { [PEDIDO]: '# Pedido\n\nab\n' },
    });
    check('L10d basename curto não vira busca (casaria qualquer coisa)',
      !run(curto, []).out.includes('pedido:'), run(curto, []).out.slice(0, 300));
  }
} finally {
  try { rmSync(TMP, { recursive: true, force: true }); } catch { /* tmp: melhor esforço */ }
}

console.log(fails
  ? `\n${fails} prova(s) caiu(ram) — as catracas do status.mjs não mordem/soltam como o contrato manda.`
  : '\nstatus.mjs morde e solta certo (par por regra · leitura, exit codes e as 2 catracas).');
process.exit(fails ? 1 : 0);
