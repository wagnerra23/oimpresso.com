#!/usr/bin/env node
// Valida as sondas JS de `tests/Browser/Repair/ProducaoOficinaIndexTest.php` em jsdom.
//
// POR QUE EXISTE
// -------------------------------------------------------------------------------------
// Pest Browser e CI-only (medido 2026-09-05: o container `oimpresso-staging` do CT 100 tem
// o plugin em vendor mas NAO tem node, npx, ms-playwright nem chromium). Sem isto, a unica
// validacao possivel antes do PR seria `php -l` — que prova sintaxe, nao LOGICA de sonda.
// Aqui as 9 sondas sao EXTRAIDAS DO PROPRIO ARQUIVO PHP (nunca retypadas — copia diverge da
// fonte no primeiro edit) e rodadas contra DOMs que espelham o `.tsx`.
//
// O QUE ESTE HARNESS PROVA — e o que NAO prova
// -------------------------------------------------------------------------------------
//   PROVA  : a logica de selecao/contagem de cada sonda, com controle POSITIVO (o DOM bom
//            da o valor esperado) e NEGATIVO (o DOM quebrado NAO passa por verde, e o DOM
//            vazio devolve sentinela em vez de zero silencioso).
//   NAO PROVA: largura real. jsdom nao tem layout engine — `scrollWidth`/`clientWidth` sao
//            sempre 0 la. A sonda de OVERFLOW e exercitada com valores INJETADOS pra provar
//            a comparacao; a medida de verdade so o Chromium responde (UC-RPO-E2 no CI).
//
// COMO RODAR (sob demanda — nao esta ligado a nenhum job de CI)
//   node scripts/tests/producao-oficina-probes.test.mjs
//
// @see tests/Browser/Repair/ProducaoOficinaIndexTest.php (fonte das sondas)
// @see resources/js/Pages/Repair/ProducaoOficina/Index.tsx (DOM espelhado)

import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const AQUI = dirname(fileURLToPath(import.meta.url));
const RAIZ = resolve(AQUI, '..', '..');

// jsdom pode nao estar no node_modules DESTA worktree (worktree fresca nao roda npm ci).
// Resolve tambem a partir do repo principal — declarado, nunca silencioso.
function carregarJsdom() {
  const candidatos = [join(RAIZ, 'package.json'), 'D:/oimpresso.com/package.json'];
  for (const base of candidatos) {
    try {
      return createRequire(base)('jsdom');
    } catch {
      /* tenta o proximo */
    }
  }
  console.error('jsdom nao encontrado. Rode `npm ci` na raiz do repo, ou rode este harness de la.');
  process.exit(2);
}
const { JSDOM } = carregarJsdom();

let fails = 0;
const ok = (c, m) => {
  if (c) console.log(`  ✓ ${m}`);
  else {
    console.error(`  ✗ ${m}`);
    fails++;
  }
};

// ---------------------------------------------------------------------------
// 1. EXTRACAO das sondas do arquivo PHP (a fonte, nunca uma copia)
// ---------------------------------------------------------------------------
const PHP = readFileSync(join(RAIZ, 'tests/Browser/Repair/ProducaoOficinaIndexTest.php'), 'utf8');

/** Le um nowdoc `const NOME = <<<'JS' ... JS;` do arquivo PHP. */
function sonda(nome) {
  const abre = `const ${nome} = <<<'JS'\n`;
  const i = PHP.indexOf(abre);
  if (i < 0) throw new Error(`sonda ${nome} nao encontrada no PHP`);
  const inicio = i + abre.length;
  const fim = PHP.indexOf('\nJS;', inicio);
  if (fim < 0) throw new Error(`sonda ${nome} sem fechamento`);
  return PHP.slice(inicio, fim);
}

const SONDAS = [
  'PRODUCAO_JS_PRONTO',
  'PRODUCAO_JS_COLUNAS',
  'PRODUCAO_JS_OVERFLOW',
  'PRODUCAO_JS_CONTADOR',
  'PRODUCAO_JS_CLICAR_CHIP',
  'PRODUCAO_JS_LIMPAR',
  'PRODUCAO_JS_LIMPAR_EXISTE',
  'PRODUCAO_JS_CARDS_A11Y',
  'PRODUCAO_JS_NON_GOAL',
];
const JS = Object.fromEntries(SONDAS.map((n) => [n, sonda(n)]));

console.log('\n[0] extracao das sondas do PHP');
ok(SONDAS.length === 9, `9 sondas extraidas do arquivo PHP (achei ${SONDAS.length})`);
ok(
  Object.values(JS).every((s) => s.trim().startsWith('(() =>')),
  'toda sonda e uma IIFE (contrato do script() do pest-plugin-browser)',
);

// ---------------------------------------------------------------------------
// 2. DOMs que espelham o .tsx
// ---------------------------------------------------------------------------
const COLUNAS = ['Recepção', 'Diagnóstico', 'Aguardando peças', 'Em execução', 'Pronto'];

/**
 * Espelha `Index.tsx`: barra de filtro (chips + Limpar + contador), grid de N colunas
 * (`<section><header><h3>`), cards `<article draggable>` e o `<main>` do AppShellV2.
 */
function domTela({
  colunas = COLUNAS,
  cardsPorColuna = [3, 4, 3, 4, 3],
  filtroAtivo = false,
  cardAcessivel = false,
  comKeyshortcuts = false,
  comModal = false,
  comFormNoMain = false,
  semGrid = false,
} = {}) {
  const chips = (label, opcoes) => `
    <div class="flex items-center gap-2">
      <span>${label}</span>
      <div class="flex gap-1">
        ${['Todos', ...opcoes].map((o) => `<button type="button">${o}</button>`).join('')}
      </div>
    </div>`;

  const contador = filtroAtivo
    ? '<div><span>4</span> de 17 OS · <span>0</span> de 3 aguardando aprovação</div>'
    : '<div><span>17</span> OS · <span>3</span> aguardando aprovação</div>';

  const card = (i) => {
    const attrs = cardAcessivel ? ' role="button" tabindex="0"' : '';
    const ks = comKeyshortcuts ? ' aria-keyshortcuts="Alt+ArrowRight"' : '';
    return `<article draggable="true"${attrs}${ks}><span>COD-${i}</span></article>`;
  };

  const secoes = colunas
    .map(
      (label, idx) => `
      <section>
        <header><div><span></span><h3>${label}</h3></div><span>${cardsPorColuna[idx] ?? 0}</span></header>
        <div>${Array.from({ length: cardsPorColuna[idx] ?? 0 }, (_, i) => card(`${idx}-${i}`)).join('')}</div>
      </section>`,
    )
    .join('');

  const grid = semGrid ? '' : `<div class="grid grid-cols-5 gap-4">${secoes}</div>`;

  return new JSDOM(`<!doctype html><html><body>
    <nav><a href="/">Sidebar</a></nav>
    <main>
      <div class="filterbar">
        ${chips('Box', ['B1', 'B2', 'B3', 'B4'])}
        ${chips('Elevador', ['E1', 'E2'])}
        ${filtroAtivo ? '<button type="button">Limpar filtros</button>' : ''}
        ${contador}
      </div>
      ${grid}
      ${comFormNoMain ? '<form><input name="x"></form>' : ''}
    </main>
    ${comModal ? '<div role="dialog">modal</div>' : ''}
  </body></html>`, { runScripts: 'outside-only' });
}

const rodar = (dom, js) => dom.window.eval(js);

// ---------------------------------------------------------------------------
// 3. PRONTO + COLUNAS
// ---------------------------------------------------------------------------
console.log('\n[1] PRONTO / COLUNAS — estrutura do kanban');
{
  const bom = domTela();
  ok(rodar(bom, JS.PRODUCAO_JS_PRONTO) === 'PRONTO', 'DOM bom: prontidao = PRONTO');
  ok(
    rodar(bom, JS.PRODUCAO_JS_COLUNAS) === '5|Recepção|Diagnóstico|Aguardando peças|Em execução|Pronto',
    'DOM bom: COLUNAS devolve as 5 na ordem do charter',
  );

  // NEGATIVO: 4 colunas (alguem removeu uma) — nao pode dar o valor esperado.
  const quatro = domTela({ colunas: COLUNAS.slice(0, 4), cardsPorColuna: [1, 1, 1, 1] });
  ok(rodar(quatro, JS.PRODUCAO_JS_PRONTO) === 'ESPERANDO', 'NEGATIVO 4 colunas: prontidao NAO fica PRONTO');
  ok(
    rodar(quatro, JS.PRODUCAO_JS_COLUNAS).startsWith('4|'),
    'NEGATIVO 4 colunas: COLUNAS denuncia o N errado',
  );

  // NEGATIVO: ordem trocada — a juncao muda, o assert do teste quebra.
  const trocado = domTela({ colunas: ['Diagnóstico', 'Recepção', 'Aguardando peças', 'Em execução', 'Pronto'] });
  ok(
    rodar(trocado, JS.PRODUCAO_JS_COLUNAS) !== '5|Recepção|Diagnóstico|Aguardando peças|Em execução|Pronto',
    'NEGATIVO ordem trocada: COLUNAS difere do esperado',
  );

  // NEGATIVO: sem grid — sentinela, nunca zero silencioso.
  const vazio = domTela({ semGrid: true });
  ok(rodar(vazio, JS.PRODUCAO_JS_COLUNAS) === 'GRID-AUSENTE', 'NEGATIVO sem grid: sentinela GRID-AUSENTE');
  ok(rodar(vazio, JS.PRODUCAO_JS_PRONTO) === 'ESPERANDO', 'NEGATIVO sem grid: prontidao ESPERANDO');
}

// ---------------------------------------------------------------------------
// 4. CONTADOR + chips + Limpar filtros
// ---------------------------------------------------------------------------
console.log('\n[2] CONTADOR / CHIP / LIMPAR — o filtro client-side');
{
  const simples = domTela({ filtroAtivo: false });
  ok(rodar(simples, JS.PRODUCAO_JS_CONTADOR) === 'SIMPLES', 'sem filtro: contador = SIMPLES');
  ok(rodar(simples, JS.PRODUCAO_JS_LIMPAR_EXISTE) === 'AUSENTE', 'sem filtro: Limpar filtros AUSENTE');

  const comFiltro = domTela({ filtroAtivo: true });
  ok(rodar(comFiltro, JS.PRODUCAO_JS_CONTADOR) === 'COMPARATIVO', 'com filtro: contador = COMPARATIVO');
  ok(rodar(comFiltro, JS.PRODUCAO_JS_LIMPAR_EXISTE) === 'EXISTE', 'com filtro: Limpar filtros EXISTE');
  ok(rodar(comFiltro, JS.PRODUCAO_JS_LIMPAR) === 'CLICOU', 'com filtro: LIMPAR acha e clica');

  // O chip clicado tem que ser o 1o NAO-"Todos" — e a sonda deve funcionar pra qualquer
  // slot_config (aqui o default Box B1..B4).
  const paraClique = domTela();
  ok(
    rodar(paraClique, JS.PRODUCAO_JS_CLICAR_CHIP) === 'CLICOU:B1',
    'CHIP: clica o 1o chip que nao e "Todos" (B1 no slot_config default)',
  );

  // POSITIVO de configurabilidade: slot_config de OUTRA vertical (ComVisual) — a sonda nao
  // pode depender de "B1" existir.
  const outraVertical = new JSDOM(`<!doctype html><html><body><main>
    <div><span>Máquina</span><div>
      <button type="button">Todos</button>
      <button type="button">Plotter1</button>
    </div></div>
  </main></body></html>`, { runScripts: 'outside-only' });
  ok(
    rodar(outraVertical, JS.PRODUCAO_JS_CLICAR_CHIP) === 'CLICOU:Plotter1',
    'CHIP: funciona com slot_config de outra vertical (Plotter1)',
  );

  // NEGATIVO: sem chips — sentinela.
  const semChips = new JSDOM('<!doctype html><html><body><main></main></body></html>', { runScripts: 'outside-only' });
  ok(
    rodar(semChips, JS.PRODUCAO_JS_CLICAR_CHIP) === 'CHIP-TODOS-AUSENTE',
    'NEGATIVO sem chips: sentinela CHIP-TODOS-AUSENTE',
  );
  ok(rodar(semChips, JS.PRODUCAO_JS_LIMPAR) === 'LIMPAR-AUSENTE', 'NEGATIVO sem botao: sentinela LIMPAR-AUSENTE');
  ok(
    rodar(semChips, JS.PRODUCAO_JS_CONTADOR) === 'CONTADOR-AUSENTE',
    'NEGATIVO sem contador: sentinela CONTADOR-AUSENTE',
  );
}

// ---------------------------------------------------------------------------
// 5. NON-GOAL (modal / form) — com o controle positivo embutido
// ---------------------------------------------------------------------------
console.log('\n[3] NON-GOAL — modal e form');
{
  ok(
    rodar(domTela(), JS.PRODUCAO_JS_NON_GOAL) === 'dialogs=0 forms=0 sections=5',
    'DOM bom: dialogs=0 forms=0 sections=5',
  );
  ok(
    rodar(domTela({ comModal: true }), JS.PRODUCAO_JS_NON_GOAL) === 'dialogs=1 forms=0 sections=5',
    'NEGATIVO modal: a sonda MORDE (dialogs=1)',
  );
  ok(
    rodar(domTela({ comFormNoMain: true }), JS.PRODUCAO_JS_NON_GOAL) === 'dialogs=0 forms=1 sections=5',
    'NEGATIVO form no main: a sonda MORDE (forms=1)',
  );
  // O controle positivo do proprio assert: numa pagina vazia o valor NAO e o esperado.
  ok(
    rodar(domTela({ semGrid: true }), JS.PRODUCAO_JS_NON_GOAL) !== 'dialogs=0 forms=0 sections=5',
    'NEGATIVO pagina sem kanban: sections=0 impede o "passou por nao achar nada"',
  );
}

// ---------------------------------------------------------------------------
// 6. CARDS_A11Y — a sonda do UC-RPO-E6
// ---------------------------------------------------------------------------
console.log('\n[4] CARDS_A11Y — o defeito de teclado capturado');
{
  // ESTADO DE HOJE (o defeito): 17 cards, nenhum operavel, zero keyshortcuts.
  const hoje = domTela();
  ok(rodar(hoje, JS.PRODUCAO_JS_CARDS_A11Y) === '17|0|0', 'defeito atual: 17 cards, 0 operaveis, 0 keyshortcuts');

  // O DIA DO CONSERTO: com role=button + tabindex=0 + aria-keyshortcuts a sonda VE a
  // diferenca — e o caso UC-RPO-E6 fica vermelho, que e o sinal de sucesso.
  const consertado = domTela({ cardAcessivel: true, comKeyshortcuts: true });
  ok(
    rodar(consertado, JS.PRODUCAO_JS_CARDS_A11Y) === '17|17|17',
    'POS-CONSERTO: a sonda detecta 17 operaveis e 17 keyshortcuts (o caso vira vermelho, como projetado)',
  );

  // Conserto PARCIAL (tabindex sem keyshortcuts) tambem tem que ser visivel — senao a
  // sonda esconderia meio conserto.
  const parcial = domTela({ cardAcessivel: true });
  ok(rodar(parcial, JS.PRODUCAO_JS_CARDS_A11Y) === '17|17|0', 'conserto PARCIAL: operaveis sobe, keyshortcuts fica 0');

  // NEGATIVO: sem grid — sentinela, nunca "0|0|0" (que passaria como defeito confirmado).
  ok(
    rodar(domTela({ semGrid: true }), JS.PRODUCAO_JS_CARDS_A11Y) === 'GRID-AUSENTE',
    'NEGATIVO sem grid: sentinela GRID-AUSENTE (nao vira 0|0|0 silencioso)',
  );

  // CONTROLE POSITIVO do teste: numa tela sem card o total e 0, e o assert
  // `expect(total)->toBeGreaterThan(0)` do UC-RPO-E6 reprova — a verdade vazia nao passa.
  const semCards = domTela({ cardsPorColuna: [0, 0, 0, 0, 0] });
  ok(
    rodar(semCards, JS.PRODUCAO_JS_CARDS_A11Y) === '0|0|0',
    'tela sem card devolve total=0 (o controle positivo do UC-RPO-E6 reprova nesse caso)',
  );
}

// ---------------------------------------------------------------------------
// 7. OVERFLOW — logica de comparacao (jsdom nao tem layout engine)
// ---------------------------------------------------------------------------
console.log('\n[5] OVERFLOW — comparacao (largura real so no Chromium)');
{
  const dom = domTela();
  const comLarguras = (scrollWidth, clientWidth) => {
    const d = dom.window.document.documentElement;
    Object.defineProperty(d, 'scrollWidth', { value: scrollWidth, configurable: true });
    Object.defineProperty(d, 'clientWidth', { value: clientWidth, configurable: true });
    return rodar(dom, JS.PRODUCAO_JS_OVERFLOW);
  };

  ok(comLarguras(1280, 1280) === '1280|1280', 'sem overflow: devolve scrollWidth|clientWidth iguais');
  ok(comLarguras(1500, 1280) === '1500|1280', 'COM overflow: devolve o par que faz o assert reprovar');

  // A aritmetica do assert do teste, exercitada aqui.
  const reprova = (s) => {
    const [sw, cw] = s.split('|').map(Number);
    return !(cw > 0 && sw <= cw);
  };
  ok(reprova('1500|1280') === true, 'NEGATIVO: 1500 > 1280 reprova o UC-RPO-E2');
  ok(reprova('1280|1280') === false, 'POSITIVO: 1280 <= 1280 aprova o UC-RPO-E2');
  ok(reprova('0|0') === true, 'NEGATIVO: viewport nao medida (0|0) reprova — nao passa por "sem overflow"');
}

console.log(`\n${fails === 0 ? '✓ TODAS as checagens passaram' : `✗ ${fails} checagem(ns) falharam`}`);
process.exit(fails === 0 ? 0 : 1);
