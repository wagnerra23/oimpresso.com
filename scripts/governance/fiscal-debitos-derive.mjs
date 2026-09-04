#!/usr/bin/env node
// scripts/governance/fiscal-debitos-derive.mjs
// =====================================================================================
// DERIVA os "Débitos conhecidos desta tela" (Onda 5 do protótipo Cowork · bloco
// `.fx-debitos` de `prototipo-ui/cowork/fiscal-{page,subpages}.jsx`) a partir da ÚNICA
// fonte que os declara com âncora: os bullets `[BACKLOG]` dos sete
// `resources/js/Pages/Fiscal/<Tela>.casos.md`.
//
// POR QUE DERIVAR, E NÃO ESCREVER A LISTA À MÃO
// -------------------------------------------------------------------------------------
// O protótipo tem a lista escrita à mão (`FX_DEBITOS` em `fiscal-data.jsx`), e ela já
// MENTE — medido em 2026-09-04 contra o `origin/main` d23bc3df34:
//
//   • "Gate fiscal.nfe.view sem teste — nenhum teste o exercita"  -> FALSO desde 2026-09-01.
//     `Nfe.casos.md:298` foi corrigido naquele dia (o caso existe em
//     `GatesPermissaoFiscalTest.php:72`, com controle negativo em `:79`) e o bullet foi
//     TACHADO (`[~~BACKLOG~~ ...]`). O protótipo não soube.
//   • "Gerador não validado no PVA-EFD — nenhum golden file" -> meio FALSO desde 2026-09-03:
//     o golden file existe (`UC-FSF1-05`); o que segue sem prova é a importação no PVA real.
//
// Transcrever aquela constante publicaria em produção afirmações falsas sobre o próprio
// sistema. Derivar fecha a porta: quando alguém paga a dívida e tacha o bullet, o item SOME
// da tela no mesmo commit — e o `--check` no CI reprova quem esquecer de regerar. É a
// lei-mãe do projeto aplicada a uma tela: derivado+enforçado sobrevive, escrito+lembrado
// apodrece (ADR 0256).
//
// O TACHADO É A REGRA QUE FAZ ISSO FUNCIONAR: `[~~BACKLOG~~ ...]` é dívida PAGA e NÃO entra.
// Sem ela o gerador reproduziria a mesma mentira do protótipo, só que automaticamente.
//
// TOM — derivado do marcador literal, nunca de julgamento sobre a gravidade:
//   'sem contrato'          -> danger   (não há nem contrato escrito — o mais grave)
//   'source-grep'           -> warning  (o teste mede o FONTE, não o comportamento — LC-11)
//   'parcialmente coberto'  -> info     (metade defendida)
//   'coberto em outra tela' -> info     (coberto, só não aqui)
//   'sem teste'             -> warning  (contrato declarado, defesa ausente)
//   default                 -> warning
//
// `decisao: true` quando o marcador contém `decisão [W]` — é o subconjunto que o bloco
// `.fx-decisao` do protótipo desenha (item A7). Fica marcado no dado desde já, para que o
// PR seguinte não precise mexer neste gerador.
//
// USO
//   node scripts/governance/fiscal-debitos-derive.mjs --write     # regera o .ts
//   node scripts/governance/fiscal-debitos-derive.mjs --check     # CI: reprova se drifou
//   node scripts/governance/fiscal-debitos-derive.mjs --selftest  # bite-test do parser
// =====================================================================================

import { readFileSync, writeFileSync, existsSync } from 'node:fs';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { dirname, join, resolve } from 'node:path';

const ROOT = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
const PAGES = join(ROOT, 'resources/js/Pages/Fiscal');
const SAIDA = join(PAGES, '_lib/debitos-conhecidos.ts');

// Tela (nome do .casos.md) -> `route` que a tela passa ao FxShell. Não é adivinhado: é o
// valor literal de `route=` em cada `<Tela>.tsx`, e o mesmo id de `_lib/paginas-fiscais`.
export const ROTA_POR_TELA = {
  Cockpit: 'fiscal',
  Nfe: 'nfe',
  Nfse: 'nfse',
  Dfe: 'dfe',
  Eventos: 'fiscal_eventos',
  Config: 'fiscal_config',
  Sped: 'sped',
};

const TONS = [
  ['sem contrato', 'danger'],
  ['source-grep', 'warning'],
  ['parcialmente coberto', 'info'],
  ['coberto em outra tela', 'info'],
  ['sem teste', 'warning'],
];

export function tomDoMarcador(marcadores) {
  const m = marcadores.toLowerCase();
  for (const [chave, tom] of TONS) if (m.includes(chave)) return tom;
  return 'warning';
}

/**
 * Rótulo curto do item — os segmentos do marcador MENOS `BACKLOG` e menos `decisão [W]`
 * (esse último já vira o campo `decisao`), sem emoji.
 *
 * POR QUE NÃO DERIVAR O RÓTULO DO TOM: `source-grep` e `sem teste` compartilham o tom
 * `warning`, e um rótulo "sem teste" num item `source-grep` seria falso — ali o teste
 * EXISTE, ele é que mede o fonte em vez do comportamento. O rótulo é o marcador; o tom é
 * a leitura de gravidade sobre ele. São coisas diferentes e não se substituem.
 */
export function rotuloDoMarcador(marcadores) {
  return marcadores
    .split('·')
    .map((s) => s.replace(/\p{Extended_Pictographic}/gu, '').trim())
    .filter((s) => s && !/^~?~?BACKLOG~?~?$/.test(s) && !/^decis[ãa]o \[W\]$/i.test(s))
    .join(' · ');
}

// Markdown -> texto corrido. Determinístico: link vira o rótulo, ênfase some, `snake_case`
// sobrevive (o sublinhado só cai quando NÃO está entre alfanuméricos).
export function limpaMarkdown(s) {
  return String(s)
    .replace(/\[([^\]]+)\]\([^)]*\)/g, '$1')
    .replace(/\*\*/g, '')
    .replace(/~~/g, '')
    .replace(/`/g, '')
    .replace(/(?<![A-Za-z0-9])_|_(?![A-Za-z0-9])/g, '')
    .replace(/\s+/g, ' ')
    .trim();
}

/**
 * Um bullet de débito, ou `null`.
 *
 * NÃO é uma regex de uma linha só, de propósito: o marcador aceita colchete ANINHADO
 * (`[BACKLOG · sem teste · decisão [W]]`), e um `[^\]]*` cortaria no `]` do `[W]`,
 * jogando "W]" para dentro do título. Aqui a profundidade é contada.
 */
export function parseBullet(linha) {
  if (!linha.startsWith('- **[')) return null;

  // 1) marcador: do '[' até o ']' que o fecha, contando profundidade.
  let prof = 0;
  let fimMarcador = -1;
  for (let i = 4; i < linha.length; i++) {
    if (linha[i] === '[') prof++;
    else if (linha[i] === ']' && --prof === 0) { fimMarcador = i; break; }
  }
  if (fimMarcador < 0) return null;
  const marcadores = linha.slice(5, fimMarcador);
  if (!/^~?~?BACKLOG/.test(marcadores)) return null;
  if (marcadores.startsWith('~~BACKLOG~~')) return null; // dívida PAGA — não entra

  // 2) título: do fim do marcador até o '**' que o fecha.
  const fimTitulo = linha.indexOf('**', fimMarcador);
  if (fimTitulo < 0) return null;
  const titulo = limpaMarkdown(linha.slice(fimMarcador + 1, fimTitulo));
  if (!titulo) return null;

  // 3) texto: o resto, sem o travessão de abertura.
  const texto = limpaMarkdown(linha.slice(fimTitulo + 2).replace(/^\s*[—–-]\s*/, ''));

  return {
    tom: tomDoMarcador(marcadores),
    rotulo: rotuloDoMarcador(marcadores),
    titulo,
    texto,
    decisao: /decis[ãa]o \[W\]/i.test(marcadores),
  };
}

export function coletar(lerArquivo = (p) => readFileSync(p, 'utf8'), existe = existsSync) {
  const out = [];
  for (const [tela, rota] of Object.entries(ROTA_POR_TELA)) {
    const caminho = join(PAGES, `${tela}.casos.md`);
    if (!existe(caminho)) continue;
    // A âncora é o ARQUIVO, nunca `arquivo:NNN`. Ref de linha apodrece na primeira edição de
    // prosa — medido aqui mesmo: acrescentar o bloco do UC-FEVT-08 ao `Eventos.casos.md`
    // deslocou 4 âncoras e o `--check` reprovou por uma mudança que não tocou débito nenhum.
    // É a dívida que o `casos-ref-lint` (check C1) já conta no projeto: a âncora durável é o
    // símbolo mais o grep que o re-localiza. Quem re-localiza aqui é o próprio `coletar()`,
    // e o teste da tela o chama para provar que todo item exibido ainda é um bullet vivo.
    for (const linha of lerArquivo(caminho).split(/\r?\n/)) {
      const b = parseBullet(linha);
      if (b) out.push({ ...b, tela: rota, ancora: `${tela}.casos.md` });
    }
  }
  return out;
}

const CABECA = [
  '// resources/js/Pages/Fiscal/_lib/debitos-conhecidos.ts',
  '// GERADO por scripts/governance/fiscal-debitos-derive.mjs — NÃO editar à mão.',
  '// Fonte: os bullets [BACKLOG] dos sete resources/js/Pages/Fiscal/<Tela>.casos.md.',
  '// Pagou a dívida? Tache o bullet ([~~BACKLOG~~ ...]) e rode:',
  '//   node scripts/governance/fiscal-debitos-derive.mjs --write',
  '',
  "export type TomDebito = 'danger' | 'warning' | 'info';",
  '',
  'export interface DebitoConhecido {',
  '  /** A `route` da tela dona (o mesmo id de _lib/paginas-fiscais). */',
  '  tela: string;',
  '  tom: TomDebito;',
  '  /** Os segmentos do marcador do bullet, sem emoji (ex.: `sem teste · débito de schema`). */',
  '  rotulo: string;',
  '  titulo: string;',
  '  texto: string;',
  '  /** true = o item espera decisão [W]; é o subconjunto do bloco de decisão pendente. */',
  '  decisao: boolean;',
  '  /** O `<Tela>.casos.md` de onde este item saiu (arquivo, não `arquivo:linha`). */',
  '  ancora: string;',
  '}',
  '',
].join('\n');

export function renderTs(itens) {
  const linhas = itens.map(
    (d) =>
      `  { tela: ${JSON.stringify(d.tela)}, tom: ${JSON.stringify(d.tom)}, decisao: ${d.decisao}, ancora: ${JSON.stringify(d.ancora)},\n` +
      `    rotulo: ${JSON.stringify(d.rotulo)},\n` +
      `    titulo: ${JSON.stringify(d.titulo)},\n` +
      `    texto: ${JSON.stringify(d.texto)} },`
  );
  return `${CABECA}\nexport const DEBITOS_CONHECIDOS: readonly DebitoConhecido[] = [\n${linhas.join('\n')}\n];\n`;
}

// ───────────────────────────── selftest (bite-test do parser) ─────────────────────────
function selftest() {
  const casos = [
    ['tachado NÃO entra (dívida paga)', '- **[~~BACKLOG~~ · tem teste] Gate x** — texto', null],
    [
      'colchete aninhado não vaza pro título',
      '- **[BACKLOG · sem teste · decisão [W]] A aba de séries mostra séries reais** — hoje é mock.',
      { titulo: 'A aba de séries mostra séries reais', decisao: true, tom: 'warning' },
    ],
    ['sem contrato -> danger', '- **[BACKLOG · sem contrato · decisão [W]] Trocar ambiente** — x', { tom: 'danger', decisao: true }],
    ['source-grep -> warning', '- **[BACKLOG · source-grep] Bloco E consolida** — grep no fonte.', { tom: 'warning', decisao: false }],
    ['coberto em outra tela -> info', '- **[BACKLOG · coberto em outra tela] O bloqueio do SPED** — ver lá.', { tom: 'info' }],
    ['parcialmente coberto -> info', '- **[BACKLOG · parcialmente coberto] Drawer** — x', { tom: 'info' }],
    ['linha de prosa citando BACKLOG NÃO entra', '- 2026-07-27 · o achado ficou como [BACKLOG] + CU-FISC-16.', null],
    ['bullet comum não entra', '- **Negrito qualquer** — texto', null],
    [
      'link vira rótulo, backtick some',
      '- **[BACKLOG · sem teste] X** — tem contrato em [`Sped.casos.md`](Sped.casos.md) hoje.',
      { texto: 'tem contrato em Sped.casos.md hoje.' },
    ],
    [
      'rotulo mantem os segmentos e descarta BACKLOG + decisao [W]',
      '- **[BACKLOG · sem teste · débito de schema] Municipio** — x',
      { rotulo: 'sem teste · débito de schema' },
    ],
    [
      'rotulo de source-grep NAO vira "sem teste" (o teste existe, so mede o fonte)',
      '- **[BACKLOG · source-grep] Bloco H é esqueleto** — grep no fonte.',
      { rotulo: 'source-grep', tom: 'warning' },
    ],
    [
      'snake_case sobrevive à limpeza',
      '- **[BACKLOG · sem teste] Y** — a trava `sped_simples_only_lock` está ativa.',
      { texto: 'a trava sped_simples_only_lock está ativa.' },
    ],
  ];
  let falhas = 0;
  for (const [nome, entrada, esperado] of casos) {
    const got = parseBullet(entrada);
    const ok =
      esperado === null
        ? got === null
        : got !== null && Object.entries(esperado).every(([k, v]) => got[k] === v);
    if (!ok) {
      falhas++;
      console.error(`  x ${nome}\n    esperado ${JSON.stringify(esperado)}\n    obtido   ${JSON.stringify(got)}`);
    } else {
      console.log(`  ok ${nome}`);
    }
  }
  console.log(falhas === 0 ? `\nselftest: ${casos.length}/${casos.length} OK` : `\nselftest: ${falhas} FALHA(S)`);
  return falhas === 0 ? 0 : 1;
}

// ───────────────────────────────────────── CLI ────────────────────────────────────────
// O bloco só roda quando ESTE arquivo é o entry point. Sem a guarda, `import { coletar }`
// executaria a CLI como efeito colateral do import — e o `fiscal-debitos-conhecidos.test.tsx`,
// que importa `coletar()` para re-derivar a lista, despejaria o `.ts` inteiro no stdout do
// vitest. Não quebrava o teste, o que é justamente o problema: passaria despercebido.
if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  const argv = process.argv.slice(2);
  if (argv.includes('--selftest')) process.exit(selftest());

  const itens = coletar();
  const conteudo = renderTs(itens);

  if (argv.includes('--write')) {
    writeFileSync(SAIDA, conteudo, 'utf8');
    const porTela = itens.reduce((a, d) => ({ ...a, [d.tela]: (a[d.tela] || 0) + 1 }), {});
    console.log(`escrito ${SAIDA}`);
    console.log(`${itens.length} débitos · ${itens.filter((d) => d.decisao).length} aguardando decisão [W]`);
    console.log(Object.entries(porTela).map(([t, n]) => `  ${t}: ${n}`).join('\n'));
    process.exit(0);
  }

  if (argv.includes('--check')) {
    const atual = existsSync(SAIDA) ? readFileSync(SAIDA, 'utf8') : '';
    if (atual === conteudo) {
      console.log(`ok debitos-conhecidos.ts em dia (${itens.length} débitos)`);
      process.exit(0);
    }
    console.error('x debitos-conhecidos.ts DRIFOU dos .casos.md do Fiscal.');
    console.error('  Um bullet [BACKLOG] foi criado, editado ou tachado sem regerar o arquivo.');
    console.error('  Rode: node scripts/governance/fiscal-debitos-derive.mjs --write');
    process.exit(1);
  }

  console.log(conteudo);
}
