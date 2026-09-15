#!/usr/bin/env node
// @ts-check
// _lib-charter.test.mjs — MORDE/SOLTA da espinha de leitura de charter (read/frontmatter/walk).
//
// POR QUE EXISTE. `_lib-charter.mjs` é a fonte única de `read`/`frontmatter`/`walk`/`SKIP_DIRS`
// para TRÊS scripts (medido 2026-09-13 com `git grep -lF "_lib-charter" origin/main`, filtrado
// por import de fato): ancora.mjs, detectar-telas.mjs, design-diff-lote.mjs. Ela nasceu de uma
// bifurcação — as cópias de `walk` divergiam no skip-set — e até aqui nada provava que ela
// morde: sem `.test.mjs`, sem `--selftest`. Era a lib de maior alcance da cadeia sem prova.
//
// ⚠️ `scripts/design/importar-bundle.mjs` aparece naquele grep mas NÃO importa: a menção é um
// comentário sobre uma RÉPLICA À MÃO do skip-set numa lista PowerShell (`$noise`). Contá-lo como
// consumidor seria o §5 2026-08-10 ("um comentário lido como import"). A paridade que o
// comentário declara é PARCIAL hoje — medido 2026-09-13: o `$noise` traz 6 dos 8 nomes (faltam
// `node_modules` e `.git`) e 2 extras. Este teste NÃO asserta essa paridade: não medi a intenção
// (bundle baixado não costuma trazer `.git`), e assertar o que não medi inventaria contrato —
// anti-padrão inventado é pior que ausente. Quem mantém o importar-bundle decide. Reproduzir:
//   node scripts/design/_lib-charter.test.mjs --replica-importar-bundle
//
// O CONTRATO É O QUE OS CONSUMIDORES ESPERAM, não o que a função parece fazer. Cada caso cita o
// call-site que o exige. Os quatro que mais doem se quebrarem:
//
//   · `read` devolve '' pra arquivo VAZIO e null pra AUSENTE — a diferença é o fail-open que
//     ancora.mjs:647 documenta ("vazio só é evidência quando a leitura aconteceu"). Se `read`
//     passasse a devolver '' no ausente, `defeitosDaAncora` diria "0 fantasmas" pra arquivo que
//     nem existe.
//   · `frontmatter(null)` devolve {} — os call-sites são `frontmatter(await read(cf))`, sem
//     guarda no meio (ancora.mjs:469/901, detectar-telas.mjs:180/246). Lançar ali derruba a varredura.
//   · `frontmatter` NÃO desaspa (ancora.mjs:68, medido 2026-08-25: 4 charters com `"n/a …"` entre
//     aspas escapavam do `ehDeclaracaoNa` e saíam como âncora NÃO MEDIDA). É por isso que
//     `desasparValor` existe do lado do consumidor — se a lib passasse a desaspar, a dupla
//     desasparia duas vezes e a mudança passaria calada.
//   · `walk` em CONCORRÊNCIA não vaza entre chamadas — os dois consumidores fazem
//     `Promise.all(raizes.map((r) => walk(r)))` (ancora.mjs:445/897, detectar-telas.mjs:167/336).
//     Trocar o default `out = []` por um array de módulo deixaria os testes sequenciais verdes e
//     misturaria as raízes em produção.
//
// Par MORDE/SOLTA em cada regra: sem o par, uma guarda que diz "sim" pra tudo passa por
// não-execução (LC-13). Node puro, sem deps/rede/DB. Fixtures no tmpdir do Node — nunca "/tmp"
// literal, que no Windows não é o mesmo diretório pro Bash e pro Node (§5 2026-08-21).
import { mkdtempSync, mkdirSync, writeFileSync, readFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, relative, sep, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { read, frontmatter, walk, SKIP_DIRS } from './_lib-charter.mjs';

const AQUI = dirname(fileURLToPath(import.meta.url));

// Sonda do achado do cabeçalho: quais nomes do SKIP_DIRS o importar-bundle NÃO replica.
// Fica atrás de flag de propósito — é REPORTE, não prova: assertar drift de terceiro faria este
// teste nascer vermelho por dívida que não é dele, e vermelho que não pode ficar verde é ruído.
if (process.argv.includes('--replica-importar-bundle')) {
  const ib = readFileSync(join(AQUI, 'importar-bundle.mjs'), 'utf8');
  const aspa = String.fromCharCode(39);
  const faltando = [...SKIP_DIRS].filter((d) => !ib.includes(aspa + d + aspa));
  console.log(`SKIP_DIRS = ${[...SKIP_DIRS].length} nomes; ausentes do $noise do importar-bundle: ${JSON.stringify(faltando)}`);
  process.exit(0);
}

let fails = 0;
let total = 0; // contado, nunca escrito a mao — numero de doc que nao se deriva apodrece (§5 2026-07-17).
const check = (name, cond, extra = '') => {
  total++;
  console.log(`${cond ? '[OK]' : '[FAIL]'} ${name}${cond ? '' : '  <- ' + extra}`);
  if (!cond) fails++;
};
const j = (v) => JSON.stringify(v);
const LF = String.fromCharCode(10);
const CRLF = String.fromCharCode(13, 10);
const BOM = String.fromCharCode(0xfeff);
/** monta um frontmatter com a quebra de linha pedida (CRLF é o caso real do Windows). */
const fmDoc = (linhas, nl = LF, corpo = '# corpo') => ['---', ...linhas, '---', corpo].join(nl);

const TMP = mkdtempSync(join(tmpdir(), 'lib-charter-test-'));
const criar = (rel_, txt = 'conteudo') => {
  const f = join(TMP, rel_);
  mkdirSync(join(f, '..'), { recursive: true });
  writeFileSync(f, txt);
  return f;
};
const rel = (lista) => lista.map((f) => relative(TMP, f).split(sep).join('/')).sort();

try {
  // ── read: distingue AUSENTE de VAZIO, e nunca lança ────────────────────────────
  {
    const cheio = criar('read/cheio.md', 'abc');
    const vazio = criar('read/vazio.md', '');
    mkdirSync(join(TMP, 'read/umdir'), { recursive: true });

    check('R1 SOLTA: arquivo que existe volta com o conteudo', (await read(cheio)) === 'abc');
    check('R1 MORDE: arquivo AUSENTE volta null (nao string vazia)',
      (await read(join(TMP, 'read/nao-existe.md'))) === null, j(await read(join(TMP, 'read/nao-existe.md'))));
    // o par que fecha o fail-open do ancora.mjs:647 — se vazio virasse null, "arquivo vazio"
    // viraria "nao li", e nao-medi passaria por medi-e-esta-limpo.
    check('R2 MORDE: arquivo VAZIO volta "" — lido-e-vazio != nao-lido',
      (await read(vazio)) === '' && (await read(vazio)) !== null, j(await read(vazio)));
    check('R3 MORDE: DIRETORIO volta null em vez de lancar (EISDIR engolido)',
      (await read(join(TMP, 'read/umdir'))) === null);
  }

  // ── frontmatter: o que os call-sites exigem ────────────────────────────────────
  {
    check('F1 SOLTA: chave de nivel raiz e lida e o valor vem trimado',
      frontmatter(fmDoc(['page:    /financeiro/unificado   '])).page === '/financeiro/unificado');
    // call-site: frontmatter(await read(cf)) — sem guarda entre os dois (ancora.mjs:469).
    check('F1 MORDE: null/undefined/"" viram {} em vez de lancar',
      j(frontmatter(null)) === '{}' && j(frontmatter(undefined)) === '{}' && j(frontmatter('')) === '{}');
    check('F1 MORDE: texto SEM bloco frontmatter vira {}', j(frontmatter('# so corpo')) === '{}');

    // este repo grava .md no Windows; CRLF e o caso comum, nao a excecao.
    const cr = frontmatter(fmDoc(['page: /x', 'related_prototype: a.jsx'], CRLF));
    check('F2 MORDE: CRLF e lido igual a LF (Windows)', cr.page === '/x' && cr.related_prototype === 'a.jsx', j(cr));

    // ancora.mjs:68 — a lib NAO desaspa; quem desaspa e o desasparValor do consumidor.
    check('F3 MORDE: NAO desaspa — aspas voltam dentro do valor',
      frontmatter(fmDoc(['related_prototype: "n/a (DS)"'])).related_prototype === '"n/a (DS)"',
      j(frontmatter(fmDoc(['related_prototype: "n/a (DS)"'])).related_prototype));
    check('F3 SOLTA: valor sem aspas volta cru',
      frontmatter(fmDoc(['related_prototype: n/a (DS)'])).related_prototype === 'n/a (DS)');

    // ancora.mjs:111 — o parser casa ^([a-z_]+): no nivel raiz; a chave INDENTADA nao entra.
    // E por isso que o ancora tem `chaveAninhada` proprio. Se a lib passasse a ler indentada,
    // a dupla leria a mesma chave por dois caminhos com precedencias diferentes.
    const aninhado = frontmatter(fmDoc(['mwart_pattern_reuse:', '  blueprint_cowork: outra-page.jsx']));
    check('F4 MORDE: chave INDENTADA nao e lida (so a chave-pai, com valor vazio)',
      aninhado.blueprint_cowork === undefined && aninhado.mwart_pattern_reuse === '', j(aninhado));
    check('F4 SOLTA: a MESMA chave no nivel raiz e lida',
      frontmatter(fmDoc(['blueprint_cowork: outra-page.jsx'])).blueprint_cowork === 'outra-page.jsx');

    // o regex nao tem flag `m`: o bloco tem que comecar no byte 0. Chave depois do fecho e PROSA.
    check('F5 MORDE: chave DEPOIS do fecho e prosa, nao declaracao',
      frontmatter(['---', 'page: /a', '---', 'corpo', 'bundle_source: PROSA.jsx'].join(LF)).bundle_source === undefined);
    check('F5 SOLTA: a mesma chave DENTRO do bloco e declaracao',
      frontmatter(fmDoc(['page: /a', 'bundle_source: REAL.jsx'])).bundle_source === 'REAL.jsx');

    // BOM UTF-8 e o vetor que este repo ja pagou caro (secao Ambiente das proibicoes: PowerShell
    // 5.1 `Set-Content -Encoding utf8` grava COM BOM). Charter salvo assim fica INVISIVEL: o
    // frontmatter inteiro vira {} calado, e a tela sai do denominador sem nenhum erro.
    check('F6 MORDE: BOM antes do --- derruba o bloco inteiro para {} (contrato, nao bug latente)',
      j(frontmatter(BOM + fmDoc(['page: /x']))) === '{}', j(frontmatter(BOM + fmDoc(['page: /x']))));
    check('F6 SOLTA: o MESMO texto sem BOM e lido', frontmatter(fmDoc(['page: /x'])).page === '/x');
    check('F7 MORDE: prosa antes do --- tambem derruba para {}',
      j(frontmatter('oi' + LF + fmDoc(['page: /x']))) === '{}');

    // forma da chave: [a-z_]+ com flag i. Hifen e digito nao casam — e nao podem derrubar as vizinhas.
    const formas = frontmatter(fmDoc(['related-prototype: hifen', 'field2: digito', 'Page: /MAIUSCULA', 'ok_key: sim']));
    check('F8 MORDE: chave com hifen e chave com digito nao sao lidas',
      formas['related-prototype'] === undefined && formas.field2 === undefined, j(formas));
    check('F8 SOLTA: as vizinhas validas sobrevivem no mesmo bloco',
      formas.page === '/MAIUSCULA' && formas.ok_key === 'sim', j(formas));
    check('F9 SOLTA: chave e normalizada para minuscula (Page -> page)', formas.page === '/MAIUSCULA');
    check('F10 MORDE: valor com ":" dentro sobrevive inteiro (corta so no 1o)',
      frontmatter(fmDoc(['page: a: b'])).page === 'a: b');
    check('F11 SOLTA: chave declarada sem valor vira "" (declarada != ausente)',
      frontmatter(fmDoc(['page:'])).page === '' && frontmatter(fmDoc(['page:'])).outra === undefined);
  }

  // ── SKIP_DIRS: a UNIAO que motivou a fonte unica ───────────────────────────────
  {
    check('S1 MORDE: _BACKUP-NAO-USAR esta no skip-set (a divergencia que criou a lib)',
      SKIP_DIRS.has('_BACKUP-NAO-USAR'), j([...SKIP_DIRS]));
    check('S2 SOLTA: a uniao dos dois consumidores esta inteira',
      ['node_modules', '.git', '_arquivo', '_BACKUP-NAO-USAR', 'scraps', 'screenshots', 'uploads', 'assets']
        .every((d) => SKIP_DIRS.has(d)), j([...SKIP_DIRS]));
  }

  // ── walk: recursao, skip em profundidade e ISOLAMENTO entre chamadas ───────────
  {
    criar('walk/a/raiz.md');
    criar('walk/a/fundo/mais/fundo.md');
    criar('walk/a/_BACKUP-NAO-USAR/escondido.md');
    criar('walk/a/fundo/node_modules/dep.md');
    criar('walk/a/assets', 'ARQUIVO com nome de skip');
    criar('walk/b/so-do-b.md');
    const A = join(TMP, 'walk/a');
    const B = join(TMP, 'walk/b');

    check('W1 SOLTA: recursivo — acha arquivo no fundo da arvore',
      rel(await walk(A)).includes('walk/a/fundo/mais/fundo.md'), j(rel(await walk(A))));
    check('W1 MORDE: diretorio inexistente volta [] em vez de lancar',
      j(await walk(join(TMP, 'walk/nao-existe'))) === '[]');
    check('W1 MORDE: caminho de ARQUIVO tratado como dir volta [] (ENOTDIR engolido)',
      j(await walk(join(TMP, 'walk/a/raiz.md'))) === '[]');

    const comSkip = rel(await walk(A));
    const semSkip = rel(await walk(A, [], new Set()));
    check('W2 MORDE: pula skip em PROFUNDIDADE (na raiz e aninhado)',
      !comSkip.some((f) => f.includes('_BACKUP-NAO-USAR') || f.includes('node_modules')), j(comSkip));
    check('W2 SOLTA: com skip vazio os mesmos arquivos aparecem (o skip e a causa, nao o acaso)',
      semSkip.some((f) => f.includes('_BACKUP-NAO-USAR')) && semSkip.some((f) => f.includes('node_modules')), j(semSkip));
    check('W3 MORDE: o skip casa por NOME — vale tambem pra ARQUIVO chamado "assets"',
      !comSkip.includes('walk/a/assets') && semSkip.includes('walk/a/assets'), j(comSkip));
    check('W4 SOLTA: a saida traz so arquivos, nenhum diretorio',
      !semSkip.includes('walk/a/fundo') && !semSkip.includes('walk/a/fundo/mais'), j(semSkip));

    // o padrao real dos dois consumidores: Promise.all(raizes.map((r) => walk(r))).
    const [ra, rb] = await Promise.all([walk(A), walk(B)]);
    check('W5 MORDE: chamadas CONCORRENTES nao vazam uma na outra',
      rel(rb).join() === 'walk/b/so-do-b.md' && rel(ra).every((f) => f.startsWith('walk/a/')),
      `a=${j(rel(ra))} b=${j(rel(rb))}`);
    const s1 = await walk(B);
    const s2 = await walk(B);
    check('W6 MORDE: chamadas SEQUENCIAIS nao acumulam (o default out=[] e por chamada)',
      s1.length === 1 && s2.length === 1, `${s1.length}/${s2.length}`);
    const acc = [];
    await walk(B, acc);
    await walk(B, acc);
    check('W6 SOLTA: mas o `out` passado explicitamente ACUMULA (contrato do parametro)',
      acc.length === 2, String(acc.length));
  }

  // ── a espinha montada: e assim que os 2 consumidores chamam, sem guarda no meio ─
  {
    const charter = criar('espinha/Index.charter.md', fmDoc(['page: /oficina/os', 'component: resources/js/Pages/X.tsx'], CRLF));
    check('C1 SOLTA: frontmatter(await read(charter)) devolve as chaves',
      frontmatter(await read(charter)).page === '/oficina/os');
    let lancou = null;
    try { frontmatter(await read(join(TMP, 'espinha/NAO-EXISTE.charter.md'))); } catch (e) { lancou = e.message; }
    check('C1 MORDE: a mesma composicao num charter AUSENTE devolve {} sem lancar',
      lancou === null && j(frontmatter(await read(join(TMP, 'espinha/NAO-EXISTE.charter.md')))) === '{}',
      `lancou=${lancou}`);
  }
} finally {
  try { rmSync(TMP, { recursive: true, force: true }); } catch { /* tmp: melhor esforco */ }
}

// Contra a nao-execucao: suite que nao rodou tambem sai com 0 falhas (LC-13). Se o numero de
// provas cair abaixo do que existe hoje, alguem retirou caso — e isso tem que doer, nao passar.
const PISO = 36; // = total desta versao; atualize junto ao adicionar/remover prova.
if (total < PISO) {
  console.log(`[FAIL] PISO: rodaram ${total} provas, o piso e ${PISO} — caso foi retirado ou o arquivo nao executou inteiro.`);
  fails++;
}
console.log(fails
  ? `${LF}${fails} prova(s) caiu(ram) de ${total} — a espinha read/frontmatter/walk mudou de contrato sob os 3 consumidores.`
  : `${LF}_lib-charter morde e solta certo (${total} provas · read/frontmatter/walk/SKIP_DIRS + a composicao real dos call-sites).`);
process.exit(fails ? 1 : 0);
