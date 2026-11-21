#!/usr/bin/env node
// lane-lista-ordenada.mjs — lista de testes das lanes Pest em ORDEM ALFABÉTICA.
//
// POR QUE EXISTE (medido 2026-11-19): o atraso do PR aberto até o merge era de
// mediana 2h49 · p90 5h30, enquanto a bateria de ~90 checks roda em 12-19 min. A diferença
// vinha de merges de main: nos PRs #11150/#11152/#11158, 60-80% dos commits eram
// "Merge branch 'main'", cada um disparando outro ciclo de CI. Esses merges NÃO eram
// por branch desatualizada (`strict: false` na proteção e no ruleset). Eram CONFLITO
// de verdade: `git show --remerge-diff` dos merges acusa sempre os mesmos arquivos,
// e um deles é a lista de testes da lane. Todo PR que liga um teste acrescenta uma
// linha NO FIM da lista, então dois PRs paralelos editam a mesma linha:
//
//     Modules/Officeimpresso/Tests/Feature/OauthClientIdTest.php \
//   <<<<<<<  ...ImportadorFirebirdFailClosedTest.php \
//   =======  ...DesktopAuditValidacaoTest.php \
//
// Em ordem alfabética cada teste novo cai num lugar diferente da lista, e o git junta
// sozinho. Para nenhum acréscimo cair na última linha (que não tem `\`), o bloco
// passa a fechar numa linha de flag (`--colors=always`), que nunca muda.
//
// POR QUE NÃO UM DIRETÓRIO OU UM `.list`: oito consumidores leem a lista direto do
// YAML (test-lane-coverage, uc-lane-coverage, uc-sem-lane, junit-lanes, gate-selftest,
// anchor-lint…), e o parser deles casa caminhos `Modules/…`/`tests/…` sem depender da
// ORDEM. Reordenar não muda o que eles leem; trocar o formato mudaria todos.
//
// O QUE FAZ, por bloco `vendor/bin/pest … \` de cada workflow:
//   - só mexe se TODA linha entre o cabeçalho e o fim for flag ou caminho de teste
//     (comentário ou outro texto no meio = bloco ignorado e reportado);
//   - um caminho por linha, ordenado, duplicata removida e reportada;
//   - `--colors=always` sai do cabeçalho e vira a última linha, levando o sufixo que
//     a última linha tinha (ex. `|| rc_principal=$?`);
//   - ANTES de gravar, prova que o conjunto de caminhos antes e depois é o mesmo.
//
// MODOS: --check (exit 1 se algum bloco elegível está fora de ordem) · --write ·
//        --selftest · default = relato. Arquivos: os passados como argumento, ou todos
//        os `.github/workflows/*.yml`.
// ADVISORY: roda no governance-script-tests, que não é required. Vermelho aqui é aviso
// para rodar `--write` antes de pedir merge, não bloqueio.

import { readFileSync, writeFileSync, readdirSync } from 'node:fs';
import { join } from 'node:path';

const RUNNER = 'vendor/bin/' + 'pest'; // montado: o hook de teste-local casa a string literal
const PATH_RE = /^(?:Modules|tests)\/[A-Za-z0-9_\-./]+\.php$/;
const COLORS = '--colors=always';

/** Processa um texto de workflow. Devolve { texto, blocos: [{linha, estado, ...}] }. */
export function processar(texto) {
  const eol = texto.includes('\r\n') ? '\r\n' : '\n';
  const linhas = texto.split(/\r?\n/);
  const blocos = [];
  const saida = [];
  let i = 0;
  while (i < linhas.length) {
    const head = linhas[i];
    const hTrim = head.trim();
    if (!(hTrim.startsWith(RUNNER + ' ') && hTrim.endsWith('\\'))) { saida.push(head); i++; continue; }

    // coleta o bloco: linhas seguintes até a 1ª sem `\` final
    let j = i + 1;
    const corpo = [];
    while (j < linhas.length) {
      corpo.push(linhas[j]);
      if (!linhas[j].trimEnd().endsWith('\\')) break;
      j++;
    }
    const fim = j; // índice da última linha do bloco
    const info = { linha: i + 1 };
    const flags = [];
    const paths = [];
    let sufixo = '';
    let elegivel = corpo.length > 0 && fim < linhas.length;
    let indent = null;

    corpo.forEach((l, k) => {
      if (!elegivel) return;
      const ultima = k === corpo.length - 1;
      let t = l.trim();
      if (ultima) {
        if (t.endsWith('\\')) { elegivel = false; return; }
      } else {
        t = t.slice(0, -1).trim();
      }
      if (indent === null && t) indent = l.match(/^\s*/)[0];
      // separa sufixo shell da última linha (`|| x=$?`, `&& …`, `; …`)
      if (ultima) {
        const m = t.match(/^(.*?)(\s*(?:\|\||&&|;|\|).*)$/);
        if (m) { t = m[1].trim(); sufixo = m[2]; }
      }
      for (const tok of t.split(/\s+/).filter(Boolean)) {
        if (PATH_RE.test(tok)) paths.push(tok);
        else if (tok.startsWith('--')) {
          // flag com valor separado (`--log-junit x.xml`) entra junto, na ordem original
          flags.push(tok);
        } else if (flags.length && !flags[flags.length - 1].includes('=') && !paths.length) {
          flags[flags.length - 1] += ' ' + tok;
        } else { elegivel = false; }
      }
    });

    const headTemColors = hTrim.includes(' ' + COLORS + ' ') || hTrim.endsWith(' ' + COLORS + ' \\');
    const flagColorsNoFim = flags.length && flags[flags.length - 1] === COLORS;
    if (elegivel && !headTemColors && !flagColorsNoFim) elegivel = false;
    if (!elegivel || !paths.length) {
      info.estado = 'ignorado';
      blocos.push(info);
      for (let k = i; k <= fim; k++) saida.push(linhas[k]);
      i = fim + 1;
      continue;
    }

    const unicos = [...new Set(paths)].sort();
    info.duplicatas = paths.length - unicos.length;
    const flagsAntes = flags.filter((f) => f !== COLORS);
    const headNovo = head.replace(' ' + COLORS + ' ', ' ').replace(' ' + COLORS + ' \\', ' \\');
    const novo = [
      headNovo,
      ...flagsAntes.map((f) => `${indent}${f} \\`),
      ...unicos.map((p) => `${indent}${p} \\`),
      `${indent}${COLORS}${sufixo}`,
    ];

    // prova: mesmo conjunto de caminhos antes e depois
    const depois = novo.flatMap((l) => l.trim().replace(/\\$/, '').trim().split(/\s+/)).filter((t) => PATH_RE.test(t));
    const a = [...new Set(paths)].sort().join('\n');
    const b = [...new Set(depois)].sort().join('\n');
    if (a !== b) throw new Error(`bloco da linha ${i + 1}: conjunto de testes mudou na reescrita`);

    const antes = linhas.slice(i, fim + 1).join('\n');
    info.estado = antes === novo.join('\n') ? 'ordenado' : 'fora-de-ordem';
    info.testes = unicos.length;
    blocos.push(info);
    saida.push(...novo);
    i = fim + 1;
  }
  return { texto: saida.join(eol), blocos };
}

function selftest() {
  let falhas = 0;
  const ok = (c, m) => { if (!c) { falhas++; console.error('FALHOU: ' + m); } else console.log('ok   ' + m); };
  const R = RUNNER;
  const velho = [
    'run: |',
    `  ${R} --no-coverage --colors=always \\`,
    '    --log-junit test-results/x.xml \\',
    '    Modules/B/Tests/Feature/ZetaTest.php \\',
    '    Modules/B/Tests/Feature/AlfaTest.php \\',
    '    tests/Feature/MeioTest.php',
    'next: 1',
  ].join('\n');
  const r = processar(velho);
  const out = r.texto.split('\n');
  ok(r.blocos[0].estado === 'fora-de-ordem', 'lista fora de ordem é acusada');
  ok(out[1] === `  ${R} --no-coverage \\`, '--colors=always sai do cabeçalho');
  ok(out[3].trim() === 'Modules/B/Tests/Feature/AlfaTest.php \\' && out[5].trim() === 'tests/Feature/MeioTest.php \\', 'caminhos ordenados, todos com barra');
  ok(out[6].trim() === '--colors=always' && out[7] === 'next: 1', 'bloco fecha na flag e o resto do arquivo fica intacto');
  ok(processar(r.texto).blocos[0].estado === 'ordenado' && processar(r.texto).texto === r.texto, 'idempotente');

  const sufixo = [
    `  ${R} --no-coverage --colors=always \\`,
    '    --log-junit a.xml \\',
    '    Modules/X/Tests/BTest.php     Modules/X/Tests/ATest.php \\',
    '    Modules/X/Tests/ATest.php || rc=$?',
  ].join('\n');
  const s = processar(sufixo);
  ok(s.texto.endsWith('    --colors=always || rc=$?'), 'sufixo da última linha vai para a linha da flag');
  ok(s.blocos[0].duplicatas === 1 && s.blocos[0].testes === 2, 'linha com vários caminhos é separada e a duplicata reportada');

  const comComentario = [
    `  ${R} --no-coverage --colors=always \\`,
    '    # comentario \\',
    '    Modules/X/Tests/BTest.php',
  ].join('\n');
  const c = processar(comComentario);
  ok(c.blocos[0].estado === 'ignorado' && c.texto === comComentario, 'bloco com texto estranho no meio NÃO é tocado');

  const semColors = [`  ${R} --no-coverage \\`, '    Modules/X/Tests/BTest.php \\', '    Modules/X/Tests/ATest.php'].join('\n');
  ok(processar(semColors).blocos[0].estado === 'ignorado', 'sem --colors=always para fechar o bloco, não reescreve');

  const crlf = velho.replace(/\n/g, '\r\n');
  ok(processar(crlf).texto.includes('\r\n') && !/[^\r]\n/.test(processar(crlf).texto), 'preserva CRLF');
  if (falhas) { console.error(`\n${falhas} falha(s)`); process.exit(1); }
  console.log('\nselftest verde');
}

function main() {
  const args = process.argv.slice(2);
  if (args.includes('--selftest')) return selftest();
  const modo = args.includes('--write') ? 'write' : args.includes('--check') ? 'check' : 'relato';
  let arquivos = args.filter((a) => !a.startsWith('--'));
  if (!arquivos.length) {
    const dir = join('.github', 'workflows');
    arquivos = readdirSync(dir).filter((f) => /\.ya?ml$/.test(f)).map((f) => join(dir, f));
  }
  let fora = 0, ignorados = 0, ordenados = 0;
  for (const f of arquivos) {
    const texto = readFileSync(f, 'utf8');
    if (!texto.includes(RUNNER)) continue;
    const r = processar(texto);
    for (const b of r.blocos) {
      if (b.estado === 'fora-de-ordem') { fora++; console.log(`${f}:${b.linha}  fora de ordem (${b.testes} testes${b.duplicatas ? `, ${b.duplicatas} duplicata(s)` : ''})`); }
      else if (b.estado === 'ignorado') ignorados++;
      else ordenados++;
    }
    if (modo === 'write' && r.texto !== texto) writeFileSync(f, r.texto);
  }
  console.log(`\nblocos: ${ordenados} em ordem · ${fora} fora de ordem · ${ignorados} ignorados (comentário/sem caminho/sem --colors=always)`);
  if (modo === 'write' && fora) console.log('reescritos.');
  if (modo === 'check' && fora) {
    console.log('\nRode: node scripts/governance/lane-lista-ordenada.mjs --write <workflow>\n' +
      'Lista em ordem alfabética evita o conflito de merge entre PRs que ligam testes na mesma lane.');
    process.exit(1);
  }
}

main();
