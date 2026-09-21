#!/usr/bin/env node
// @ts-check
/**
 * lote-resumo-ci.mjs — o CONSUMIDOR do `design-diff-lote`: transforma as medidas em um
 * resumo legível, com o denominador na frente. Escreve markdown no stdout (o workflow
 * redireciona pro `$GITHUB_STEP_SUMMARY`); não escreve arquivo, não commita, não cria task.
 *
 * Origem: decisão [W] "ligue" em 2026-09-21 (ADR 0408, que emenda a 0290). O lote já media
 * e gravava; o que faltava era alguém LER. Este arquivo é esse alguém.
 *
 * ── AS TRÊS REGRAS QUE ELE EXISTE PRA CUMPRIR ──────────────────────────────────────────
 *
 * 1. DENOMINADOR PRIMEIRO, sempre. O universo do lote são as telas `anchored` do
 *    `application-report.json`, e ele muda por trabalho humano várias vezes ao dia —
 *    medido em 2026-09-18: 5 mudanças no mesmo dia, e o #7518 moveu 59 `anchored` →
 *    `compared` de uma vez. Um número publicado sem o universo faz a série temporal
 *    mentir: tela que SAI do denominador lê como "resolvida" (§5 2026-07-27).
 *
 * 2. A chave é (tela, FONTE), nunca só a tela. No corpus de 2026-09-18, 7 telas aparecem
 *    2× com fontes de design distintas, e 2 delas com vereditos OPOSTOS — `Fiscal/Cockpit`
 *    dá "sem bug" por `fiscal-actions.jsx` e "4 bugs" por `fiscal-page.jsx`. Colapsar por
 *    nome obrigaria a escolher entre os dois, e a escolha seria arbitrária, não medida.
 *
 * 3. Corte por CAUSA, nunca "N telas divergentes". Medido no mesmo corpus: 885 achados,
 *    dos quais 276 (31%) são o MESMO quarteto `shell.*.presenca` repetido em 69 telas.
 *    Isso é uma causa sistemática do shell, não 69 trabalhos. Publicar "52 divergentes"
 *    fabrica 52 itens de dívida onde há ~3 causas e uma cauda. E é por isso que ele NÃO
 *    emite nota/score/percentual de fidelidade: §5 2026-07-17 proíbe agregar estes
 *    vereditos num número único, porque eles não são comensuráveis (bug de prod,
 *    protótipo à frente e ruído de dado apontam pra lados diferentes).
 *
 * ── O QUE ELE NÃO FAZ ──────────────────────────────────────────────────────────────────
 *   • Não cria task no MCP. Quem decide o que vira trabalho é humano (o padrão do
 *     `mv-metabolismo`: o cron propõe, o merge de [W] aprova, a sessão pós-merge cria).
 *   • Não compara com rodada anterior. Baseline congelada está vetada por [W] e o §5 tem
 *     3 lápides de rebake que não fecha (2026-08-24 · 2026-08-26 · 2026-09-02). "Antes"
 *     honesto = o MESMO comando em dois SHAs, nunca diff contra artefato guardado.
 *   • Não afirma "igual". Quem dá veredito é o `design-diff --check`, e ele já escreveu.
 *
 * ── COMO RODAR ─────────────────────────────────────────────────────────────────────────
 *   node scripts/design/lote-resumo-ci.mjs              # markdown no stdout
 *   node scripts/design/lote-resumo-ci.mjs --selftest   # 18 casos, hermético (sem rede/app);
 *                                                        # os 4 últimos exercitam o CLI de fora
 */

import fs from 'node:fs';
import path from 'node:path';
import { spawnSync } from 'node:child_process';

const DIR_MEDIDAS = 'governance/design/targets/medidas';
const REPORT = 'scripts/design-sync/state/application-report.json';

/**
 * Linhas de tela da tabela do RESUMO (descarta cabeçalho e separador).
 * O cabeçalho é reconhecido pela CÉLULA (`Tela` exata), não pelo prefixo da linha: um
 * filtro `startsWith('| Tela')` engoliria uma tela de verdade chamada `Tela/Algo` — foi o
 * que o selftest pegou ao escrever a fixture, e o bug era da função, não do teste.
 */
export function linhasDeTela(md) {
  return md
    .split(/\r?\n/)
    .filter((l) => l.startsWith('| ') && !/^\|\s*-{3}/.test(l) && celulas(l)[0] !== 'Tela');
}

/**
 * Fatia uma linha da tabela em células.
 * NÃO use split por `|` cru num parser de markdown: a célula Motivo carrega pipe ESCAPADO
 * (`\|`) — medido em 2026-09-18: 148 linhas do RESUMO tinham — e um `awk -F'|'` fatiou o
 * campo e deslocou a classificação inteira. O erro só apareceu porque a SOMA não fechou
 * (66 num total de 64), que é o controle barato desta função.
 */
export function celulas(linha) {
  const bruto = linha.split('|').slice(1, -1);
  const out = [];
  for (const parte of bruto) {
    if (out.length && out[out.length - 1].endsWith('\\')) {
      out[out.length - 1] = out[out.length - 1].slice(0, -1) + '|' + parte;
    } else {
      out.push(parte);
    }
  }
  return out.map((s) => s.trim());
}

/** Vereditos contados + par (tela,fonte) distinto. A soma DEVE fechar com o total. */
export function contarVereditos(linhas) {
  const ver = {};
  const pares = new Set();
  const telas = new Map();
  for (const l of linhas) {
    const c = celulas(l);
    ver[c[3]] = (ver[c[3]] || 0) + 1;
    pares.add(`${c[0]} @ ${c[1]}`);
    if (!telas.has(c[0])) telas.set(c[0], new Set());
    telas.get(c[0]).add(c[3]);
  }
  // Tela medida contra mais de uma fonte candidata. `divergentes` = aquelas cujos vereditos
  // se CONTRADIZEM entre as fontes (medido 2026-09-18: Fiscal/Cockpit dá "sem bug" por
  // fiscal-actions.jsx e "4 bugs" por fiscal-page.jsx). Colapsar por nome escolheria um dos
  // dois arbitrariamente — por isso a chave é o par, e a contradição é REPORTADA, não resolvida.
  const repetidas = [...telas.keys()].filter(
    (t) => linhas.filter((l) => celulas(l)[0] === t).length > 1,
  );
  const contraditorias = repetidas.filter((t) => telas.get(t).size > 1);
  const soma = Object.values(ver).reduce((a, b) => a + b, 0);
  return {
    ver,
    pares: pares.size,
    telas: telas.size,
    repetidas,
    contraditorias,
    soma,
    fecha: soma === linhas.length,
  };
}

/** Agrega os achados por CAMPO nos resultado.json — é o corte que aponta conserto. */
export function achadosPorCampo(dir, leitor = fs) {
  const porCampo = {};
  let total = 0;
  if (!leitor.existsSync(dir)) return { porCampo, total };
  for (const d of leitor.readdirSync(dir)) {
    const f = path.posix.join(dir, d, 'resultado.json');
    if (!leitor.existsSync(f)) continue;
    const walk = (o) => {
      if (!o || typeof o !== 'object') return;
      if (Array.isArray(o)) return void o.forEach(walk);
      if (o.campo && (o.veredito || o.tipo)) {
        porCampo[o.campo] = (porCampo[o.campo] || 0) + 1;
        total++;
      }
      for (const k of Object.keys(o)) walk(o[k]);
    };
    try {
      walk(JSON.parse(leitor.readFileSync(f, 'utf8')));
    } catch {
      /* resultado ilegível é ausência de medida daquela tela, não zero achados do corpus */
    }
  }
  return { porCampo, total };
}

function main() {
  const out = [];
  const say = (s = '') => out.push(s);

  // O `RESUMO.md` é VERSIONADO. Se o step de medição não terminou (falhou, estourou o
  // timeout, ou foi cancelado pela concorrência — aconteceu no dispatch de prova em
  // 2026-09-21, run 35589355425, morta 17s depois de um push em main entrar no mesmo
  // `concurrency.group`), este script leria o arquivo do repo e publicaria dado ANTIGO com
  // cara de rodada nova. Isso é "afirmar sem medir" com passos extras: o leitor não tem como
  // distinguir. Por isso o outcome do step entra por env e é declarado ANTES de qualquer número.
  const outcome = process.env.LOTE_OUTCOME || '';
  if (outcome && outcome !== 'success') {
    say(`## Lote de paridade — NÃO MEDI (step de medição: \`${outcome}\`)`);
    say('');
    say('O step que renderiza e compara não concluiu, então **esta rodada não produziu medida**.');
    say('Os números abaixo seriam os do `RESUMO.md` versionado — de uma rodada anterior — e');
    say('publicá-los aqui os faria passar por novos. Não são, e por isso não vão.');
    say('');
    say('Ver o log do step acima. **Ausência de medida não é "tudo igual"** (§5 2026-07-29).');
    process.stdout.write(out.join('\n') + '\n');
    return;
  }

  if (!fs.existsSync(`${DIR_MEDIDAS}/RESUMO.md`)) {
    say('## Lote de paridade — RESUMO não gerado');
    say('');
    say('O step de medição não produziu `RESUMO.md`. Ver o log do step acima.');
    say('**Ausência de medida não é "tudo igual"** — é ausência de medida (§5 2026-07-29).');
    process.stdout.write(out.join('\n') + '\n');
    return;
  }

  say('## Lote de paridade — protótipo × vivo');
  say('');

  // (1) DENOMINADOR PRIMEIRO
  if (fs.existsSync(REPORT)) {
    const rep = JSON.parse(fs.readFileSync(REPORT, 'utf8'));
    const c = {};
    for (const t of rep.screens || []) c[t.lifecycleState] = (c[t.lifecycleState] || 0) + 1;
    const tot = (rep.screens || []).length;
    say(`**Universo** (\`application-report.json\`, ${tot} telas): ` +
      Object.entries(c).sort((a, b) => b[1] - a[1]).map(([k, v]) => `\`${k}\` ${v}`).join(' · '));
    say('');
    say('> O lote mede as `anchored`. Esse conjunto muda por trabalho humano — tela que sai');
    say('> dele **não foi resolvida, saiu do denominador**. Comparar dois números sem comparar');
    say('> os universos produz série temporal mentirosa (§5 2026-07-27).');
    say('');
  } else {
    say('_(universo não medido: `application-report.json` ausente)_');
    say('');
  }

  // (2) VEREDITOS, com a chave (tela, fonte)
  const linhas = linhasDeTela(fs.readFileSync(`${DIR_MEDIDAS}/RESUMO.md`, 'utf8'));
  const { ver, pares, telas, repetidas, contraditorias, soma, fecha } = contarVereditos(linhas);
  say(`**Medido nesta rodada:** ${linhas.length} linhas · ${pares} pares (tela, fonte) · ${telas} telas distintas`);
  say('');
  say('| veredito | n |');
  say('|---|---|');
  for (const [k, v] of Object.entries(ver).sort((a, b) => b[1] - a[1])) say(`| ${k} | ${v} |`);
  say('');
  say(fecha
    ? `_soma confere (${soma} = ${linhas.length})_`
    : `⚠️ **SOMA NÃO FECHA** (${soma} ≠ ${linhas.length}) — o parser está errado, não confie nos números acima.`);
  say('');

  // Tela com mais de uma fonte candidata. Não é bug de dedup: é medida contra duas âncoras.
  // Quem contar LINHAS conta a mais; quem colapsar por NOME escolhe um veredito no lugar do
  // outro. Por isso o resumo publica os dois totais e NOMEIA as contraditórias.
  if (repetidas.length) {
    say(`**${repetidas.length} tela(s) medidas contra mais de uma fonte** — \`${repetidas.join('`, `')}\`.`);
    if (contraditorias.length) {
      say('');
      say(`⚠️ **${contraditorias.length} com vereditos que se CONTRADIZEM entre as fontes:** ` +
        `\`${contraditorias.join('`, `')}\`. Não colapse por nome — a escolha entre os dois ` +
        'seria arbitrária, não medida. Decidir qual âncora vale é trabalho humano (charter).');
    }
    say('');
  }

  // (3) CORTE POR CAUSA
  const { porCampo, total } = achadosPorCampo(DIR_MEDIDAS);
  say(`**Achados por campo** (total ${total}) — o que consertar, não quantas telas doem:`);
  say('');
  say('```');
  for (const [k, v] of Object.entries(porCampo).sort((a, b) => b[1] - a[1]).slice(0, 12)) {
    say(`${String(v).padStart(5)}  ${k}`);
  }
  say('```');
  say('');
  say('> Campo repetido em N telas é **uma causa sistemática**, não N trabalhos. Sem nota/score');
  say('> de fidelidade aqui de propósito: os vereditos não são comensuráveis — bug de prod,');
  say('> protótipo à frente e ruído de dado apontam para lados diferentes (§5 2026-07-17).');
  say('');
  say('Dado bruto no artifact `paridade-lote-*`. Não há baseline commitada: para comparar');
  say('duas rodadas, rode o **mesmo comando** em dois SHAs.');

  process.stdout.write(out.join('\n') + '\n');
}

function selftest() {
  let ok = 0;
  let fail = 0;
  const t = (nome, cond) => {
    if (cond) { ok++; console.log('ok  ' + nome); }
    else { fail++; console.log('FAIL ' + nome); }
  };

  // celulas: o caso que quebrou o awk — pipe ESCAPADO dentro do Motivo
  const comEscape = '| Tela/X | f.jsx | rota | NÃO MEDI | — | — | — | — | — | — | — | — | data | motivo a \\| b |';
  const c = celulas(comEscape);
  t('celulas: pipe escapado NAO fatia o campo', c.length === 14);
  t('celulas: motivo preserva o pipe', c[13] === 'motivo a | b');
  t('celulas: veredito na posicao 3', c[3] === 'NÃO MEDI');

  // controle negativo: linha sem escape continua igual
  const semEscape = '| T | f | r | IGUAL | 0 | 0 | ok | ok | ok | ok | ok | ok | d | |';
  t('celulas: linha sem escape intacta', celulas(semEscape).length === 14);

  // linhasDeTela descarta cabecalho/separador
  const md = ['# t', '| Tela | Fonte |', '|---|---|', semEscape, comEscape, ''].join('\n');
  t('linhasDeTela: so as linhas de tela', linhasDeTela(md).length === 2);

  // contarVereditos + a soma como controle
  const r = contarVereditos(linhasDeTela(md));
  t('contarVereditos: soma fecha', r.fecha === true && r.soma === 2);
  t('contarVereditos: par (tela,fonte) distingue', r.pares === 2);

  // a soma DEVE denunciar parser quebrado — controle positivo do proprio controle
  const rQuebrado = contarVereditos([semEscape, semEscape]);
  t('contarVereditos: 2 linhas iguais = 1 par, soma ainda fecha',
    rQuebrado.pares === 1 && rQuebrado.fecha === true);

  // O CASO REAL que motivou a chave (tela,fonte): Fiscal/Cockpit medido contra 2 fontes,
  // com vereditos OPOSTOS. Tem que aparecer em `repetidas` E em `contraditorias`.
  const fonteA = '| Fiscal/Cockpit | fiscal-actions.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | d | m |';
  const fonteB = '| Fiscal/Cockpit | fiscal-page.jsx | fis | DIVERGE (bug) | 4 | 0 | ok | ok | ok | ok | ok | ok | d | |';
  const rc = contarVereditos([fonteA, fonteB]);
  t('contraditoria: 2 fontes, 2 pares, 1 tela', rc.pares === 2 && rc.telas === 1);
  t('contraditoria: nomeada em repetidas', rc.repetidas.length === 1 && rc.repetidas[0] === 'Fiscal/Cockpit');
  t('contraditoria: veredito oposto vira contraditoria', rc.contraditorias.length === 1);

  // CONTROLE NEGATIVO: 2 fontes CONCORDANDO e repetida, mas NAO contraditoria.
  const concA = '| Fiscal/Dfe | a.jsx | x | IGUAL | 0 | 0 | ok | ok | ok | ok | ok | ok | d | |';
  const concB = '| Fiscal/Dfe | b.jsx | x | IGUAL | 0 | 0 | ok | ok | ok | ok | ok | ok | d | |';
  const rn = contarVereditos([concA, concB]);
  t('controle negativo: concordantes sao repetidas mas NAO contraditorias',
    rn.repetidas.length === 1 && rn.contraditorias.length === 0);

  // achadosPorCampo com leitor falso (hermetico, sem tocar disco real)
  const fake = {
    existsSync: (p) => p === 'X' || p.endsWith('resultado.json'),
    readdirSync: () => ['A--B'],
    readFileSync: () => JSON.stringify({ achados: [{ campo: 'cor', veredito: 'bug' }, { campo: 'cor', tipo: 'div' }] }),
  };
  const a = achadosPorCampo('X', fake);
  t('achadosPorCampo: agrega por campo', a.total === 2 && a.porCampo.cor === 2);

  // dir ausente nao explode nem inventa zero-com-cara-de-medido
  const vazio = achadosPorCampo('nao-existe', { existsSync: () => false, readdirSync: () => [], readFileSync: () => '' });
  t('achadosPorCampo: dir ausente = 0 achados sem lancar', vazio.total === 0);

  // GUARD DE OUTCOME — exercita o CLI DE FORA, nao o helper: o guard vive no main(), que nao
  // e exportado, e assert sobre funcao pura nao prova contrato de pipeline (LC-15). Origem:
  // run 35589355425 (2026-09-21), cancelada pela concorrencia — sem o guard, o resumo leria o
  // RESUMO.md versionado e publicaria a rodada anterior como se fosse esta.
  const cli = (env) => spawnSync(process.execPath, [import.meta.filename], {
    env: { ...process.env, ...env }, encoding: 'utf8',
  });
  const mau = cli({ LOTE_OUTCOME: 'cancelled' });
  t('guard: outcome != success declara NAO MEDI', /NÃO MEDI/.test(mau.stdout));
  t('guard: outcome != success NAO publica numero de medida',
    !/\d+\s+linhas|total \d+/.test(mau.stdout));
  const bom = cli({ LOTE_OUTCOME: 'success' });
  // controle positivo: com success, o CLI volta a publicar (se o corpus existir na arvore).
  t('guard: outcome success nao e bloqueado pelo guard', !/NÃO MEDI \(step/.test(bom.stdout));
  const semEnv = cli({ LOTE_OUTCOME: '' });
  t('guard: env ausente nao bloqueia (uso local)', !/NÃO MEDI \(step/.test(semEnv.stdout));

  console.log(`\n${ok}/${ok + fail} ok`);
  process.exitCode = fail ? 1 : 0;
}

if (process.argv.includes('--selftest')) selftest();
else main();
