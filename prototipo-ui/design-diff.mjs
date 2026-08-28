#!/usr/bin/env node
// @ts-check
/**
 * design-diff.mjs — comparador DETERMINÍSTICO design(Cowork vivo) × produção, por MEDIÇÃO.
 *
 * POR QUE EXISTE (Wagner 2026-07-07, strike 2): a comparação design×prod vinha sendo feita
 * "no olho" (screenshot + eu declaro "estruturalmente igual") e ERROU — perdeu o alinhamento
 * dos KPI (center na prod × left no design) e o dark-mode invisível. Essa classe de erro
 * ("comparação rasa / no olho") já tinha acontecido em 06/07 (gerou o PROTOCOLO-COMPARACAO-
 * RUNTIME) — repeti em 07/07. Pela regra two-strikes (LICOES_CODE §two-strikes, LC-06), strike 2
 * = vira DEFESA MECÂNICA. Esta é a defesa: o veredito vem de um DIFF MEDIDO, não de olhar.
 * Realiza o `/design-diff` PREVISTO na ADR 0299. Espelha o split do `cowork-mirror-freshness.mjs`.
 *
 * ── O SPLIT (o node não fala MCP; computed-style precisa de browser) ──────────────
 *   1. PROBE (browser):  `--probe` imprime a sonda JS CANÔNICA. O agente injeta ela via
 *      Chrome MCP `javascript_tool` em CADA aba (prod + design render), passando o mapa de
 *      papéis daquele lado em `window.__DD_ROLES` (as CLASSES diferem — `.fin-stat` na prod,
 *      `.os-stat` no design — mas o PAPEL é o mesmo). A sonda devolve um snapshot medido.
 *   2. COMPARE (node puro): `--compare prod.json design.json [--check]` → veredito POR DIMENSÃO,
 *      determinístico + testável. `--check` sai 1 se houver DIVERGE(bug).
 *
 *   A mesma sonda nos dois lados = ninguém "compara no olho": a régua é idêntica e medida.
 *
 * ── DIMENSÕES (do PROTOCOLO-COMPARACAO-RUNTIME; D8 é a que faltava, o buraco de 07/07) ──
 *   D2 layout      — nº de linhas visuais da barra de filtro · contagem de KPI · overflow-x
 *   D4 tipografia  — font-size/weight do título e do valor do KPI
 *   D6 cor         — bg do primary (accent) · cor do texto do KPI (contraste no tema)
 *   D8 ALINHAMENTO — text-align de label/valor do KPI + a TAG (button↔center-default × div↔left)
 *   D9 TEXTO       — CLASSE DE FORMATO por região [data-contract]: data ISO × dd/mm/aaaa,
 *                    valor cru com separador (hard_delete) impresso como rótulo. Não é diff de
 *                    string (mock × dado real seria ruído puro) — é a FORMA que se compara.
 *                    Adicionada em 2026-08-26: até então só computed-style era diffável, e a
 *                    classe de defeito que mais escapa na travessia é textual.
 *
 *   ── LINHA DA TABELA (2026-08-27) — não é dimensão nova ──────────────────────────
 *   D2/D4/D6/D8 SEMPRE declararam a célula de tabela no protocolo — a D4 lista "…valor do
 *   KPI, LINHA DA TABELA" e a D6 lista "accent, PILLS (radius/border/saturação), estados".
 *   A mecanização implementava MENOS que a dimensão declarava: media só título, KPI e
 *   primary. Resultado medido: link que virou texto morto, sub-linha em mono que sumiu e
 *   pílula sem cor nem dot passavam por baixo do comparador inteiro, com todos os gates
 *   verdes. Reportado por [W] em 2026-08-27 nas colunas "Vinculado a" e "Classificação"
 *   da Arquivos/Index. Agora a sonda mede a linha (papel `tableRow`) e cada sinal sai
 *   rotulado com a dimensão CANÔNICA dele — mono/peso→D4, cor/pílula→D6, tag→D8,
 *   nº de blocos→D2. Compara por ÍNDICE de coluna: a ordem já é lei do contrato-de-tela.
 *
 *   (D1 comportamento/rede, D3 ícones, D5 footer, D7 densidade ficam no protocolo como passos
 *    do agente. Honesto: o tool NÃO substitui o protocolo, MECANIZA a parte medível dele.
 *    E o que a D9 NÃO cobre está declarado nela: copy em inglês — que a ADR 0271 onda 2 deixou
 *    no juiz manual ao deletar o pr-ui-judge.yml — e valor cru de UMA palavra, indistinguível
 *    de palavra legítima por forma. §"não-goals".)
 *
 *   ── SHELL / SIDEBAR (2026-08-28) — escopo NOVO, não dimensão nova ──────────────
 *   Tudo acima mede a TELA. O SHELL (sidebar) ficava fora de TODA máquina de comparação,
 *   e não por esquecimento: o `style-fingerprint.mjs` o EXCLUI de propósito (`window.__ROOT__`,
 *   "mata o ruído de shell/sidebar ~600 miss/célula na Unificado"). Resultado medido em
 *   2026-08-28: o sidebar divergia do design em 19 propriedades e nenhuma máquina disse nada —
 *   3 foram achadas por amostragem no olho, as outras 16 só apareceram quando alguém ENUMEROU.
 *   O valor desta parte é ENUMERAR; amostrar já se sabia fazer.
 *
 *   Config em `window.__SB_ROLES` = { <papel>: '<seletor CSS DESTE lado>' } — mesma premissa
 *   do resto: o SELETOR difere por lado, o PAPEL é o mesmo. `--shell-roles` imprime o mapa
 *   medido pra colar nos dois lados.
 *
 *   ⚠️ O QUE ESTE ESCOPO NÃO FAZ, E É DE PROPÓSITO: ele NÃO diz de quem é a culpa. Diferença
 *   de shell tem três causas e nenhuma é decidível por medição —
 *      DECIDIDA      alguém escolheu (ex.: a prod ter um atalho que o design não tem)
 *      DERIVA        ninguém escolheu; foi acontecendo
 *      DESIGN-ANDOU  o design mudou e a prod ficou na versão anterior
 *   Por isso o veredito é `DIVERGE (a classificar)`, nunca `(bug)`: a máquina REPORTA,
 *   o humano CLASSIFICA. O que já foi classificado como DECIDIDA entra por `--declarado
 *   <arquivo.json>` (allowlist por ENTRADA, com motivo obrigatório — nunca embutida aqui,
 *   senão a régua fica vermelha permanente e vira ruído que se aprende a ignorar).
 *
 *   Enforcement não se declara aqui em tempo presente (apodrece): quem manda em "isto
 *   bloqueia merge?" é a branch protection, e o dono é `governance/required-checks-baseline.json`.
 *   O que este arquivo oferece é a flag `--check-shell`, que existe para quem quiser exit≠0.
 *   O `--check` histórico segue olhando SÓ `DIVERGE (bug)` — shell não avermelha o que já existia.
 *
 * ── TOLERÂNCIA (chip G8, 2026-08-14) ─────────────────────────────────────────────
 *   As bandas destas dimensões estavam inline (±2px de título, ±8° de matiz, ±0,1 de luminância).
 *   Agora vêm de `TOLERANCIAS` (style-fingerprint.mjs) — UM dono para os dois comparadores, senão
 *   os dois números drifam no primeiro ajuste. Cada eixo lá carrega a RAZÃO do valor, e a fronteira
 *   (um caso logo abaixo, um logo acima) está travada no `--selftest` dos dois lados.
 *   Importar o módulo é seguro: ele só roda CLI quando é o entrypoint (guard `ehEntrypoint`).
 *
 * Uso:
 *   node prototipo-ui/design-diff.mjs --probe                       # imprime a sonda pra injetar
 *   node prototipo-ui/design-diff.mjs --compare prod.json design.json          # relatório
 *   node prototipo-ui/design-diff.mjs --compare prod.json design.json --check  # exit 1 se DIVERGE(bug)
 *   node prototipo-ui/design-diff.mjs --compare prod.json design.json --json   # saída JSON
 *   node prototipo-ui/design-diff.mjs --shell-roles                 # mapa de papéis do SHELL, pra colar nos 2 lados
 *   node prototipo-ui/design-diff.mjs --compare p.json d.json --check-shell            # exit 1 se o SHELL divergir
 *   node prototipo-ui/design-diff.mjs --compare p.json d.json --declarado decl.json    # allowlist {campo,motivo}
 *   node prototipo-ui/design-diff.mjs --selftest                    # fixture hermético (reproduz 07/07)
 */

import { readFileSync, existsSync, writeFileSync, rmSync } from 'node:fs';
import { spawnSync } from 'node:child_process';
import { tmpdir } from 'node:os';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { TOLERANCIAS, diffCampo } from './style-fingerprint.mjs';

/**
 * Ledger de frescor do espelho — MESMO caminho que o `cowork-mirror-freshness`
 * exporta em `LEDGER_REL`. Não importo o módulo dele de propósito: ele é pesado
 * (varre a árvore no import) e aqui só preciso ler um JSON. O acoplamento fica
 * no PATH, e o selftest trava que os dois continuam apontando pro mesmo arquivo.
 */
export const LEDGER_FRESCOR_REL = 'scripts/governance/.cowork-freshness-ledger.json';

/* ─────────────────────────────────────────────────────────────────────────────
 * A SONDA CANÔNICA (roda no browser via Chrome MCP javascript_tool).
 * Config por lado em window.__DD_ROLES = { kpi, title, primary, filterControls, tableRow }
 * (seletores CSS). `tableRow` = a PRIMEIRA linha de dados da tabela; as classes diferem
 * entre os lados (ex.: `.arq-lista tbody tr` no protótipo × `table tbody tr` na prod),
 * mas o papel é o mesmo — que é a premissa do split desde o início.
 * Devolve o snapshot medido — MESMA função nos dois lados.
 * Exportada como string pra `--probe` imprimir e o agente injetar igual nos dois.
 * ─────────────────────────────────────────────────────────────────────────── */
export const PROBE_SOURCE = /* js */ `(() => {
  const R = window.__DD_ROLES || {};
  const cs = (el) => el ? getComputedStyle(el) : null;
  const q = (sel) => sel ? document.querySelector(sel) : null;
  const qa = (sel) => sel ? [...document.querySelectorAll(sel)] : [];
  const visualRows = (els) => {
    // nº de linhas visuais = grupos distintos de top (arredondado) dos elementos
    const tops = new Set(els.map((e) => Math.round(e.getBoundingClientRect().top / 6) * 6).filter((t) => t >= 0));
    return tops.size;
  };
  // KPI
  const kpiEls = qa(R.kpi).slice(0, 8);
  const kpi = {
    count: kpiEls.length,
    tag: kpiEls[0] ? kpiEls[0].tagName : null,
    overflowX: (() => { const p = kpiEls[0] && kpiEls[0].parentElement; return p ? p.scrollWidth > p.clientWidth + 2 : null; })(),
    items: kpiEls.map((el) => {
      const c = cs(el); const small = el.querySelector('small,[class*="label"]');
      // O VALOR do KPI: tenta a marcação semântica e, se ela não existir, cai no
      // MAIOR texto-folha dentro do card. O fallback existe porque seletor de
      // classe é cego a utility-first: o KpiCard canon marca o valor com
      // 'text-2xl', sem "value" no nome, então [class*="value"] devolvia null e a
      // tipografia do VALOR saía NÃO-MEDIDA — ponto cego silencioso, que é pior
      // que divergência (o relatório fica igual ao de quem mediu e bateu).
      // "O valor é o maior texto do card" vale por construção nos dois lados.
      // Conservador: só roda quando o seletor falha — onde já funcionava, nada muda.
      let b = el.querySelector('b,[class*="value"]');
      if (!b) {
        b = [...el.querySelectorAll("*")]
          .filter((e) => (e.textContent || "").trim() && !e.children.length)
          .sort((x, y) => parseFloat(cs(y).fontSize) - parseFloat(cs(x).fontSize))[0] || null;
      }
      return {
        label: (el.textContent || '').replace(/\\s+/g, ' ').trim().slice(0, 14),
        textAlign: c.textAlign,
        alignItems: c.alignItems,
        textColor: c.color,
        smallAlign: small ? cs(small).textAlign : null,
        valueFontPx: b ? Math.round(parseFloat(cs(b).fontSize)) : null,
      };
    }),
  };
  // título
  const t = q(R.title); const tc = cs(t);
  const title = t ? { fontPx: Math.round(parseFloat(tc.fontSize)), weight: tc.fontWeight, color: tc.color } : null;
  // primary (accent)
  const p = q(R.primary); const pc = cs(p);
  const primary = p ? { bg: pc.backgroundColor, color: pc.color, border: pc.borderTopColor } : null;
  // filtro (barra) — nº de linhas visuais dos controles
  const filterEls = R.filterControls ? qa(R.filterControls) : [];
  // LINHA DA TABELA — âncora que a D4 e a D6 do PROTOCOLO-COMPARACAO-RUNTIME JÁ declaram
  // ("título da página, título da lista, valor do KPI, LINHA DA TABELA" / "accent, PILLS
  // (radius/border/saturação), estados") e que a mecanização nunca implementou: as duas
  // dimensões mediam só título/KPI/primary. O resultado é que divergência de célula —
  // link que virou texto morto, sub-linha em mono que sumiu, pílula que perdeu cor e dot —
  // passava por baixo do comparador inteiro. Reportado por [W] em 2026-08-27 olhando as
  // colunas "Vinculado a" e "Classificação" da Arquivos/Index.
  //
  // Compara POR ÍNDICE de coluna, porque a ordem já é pinada pelo contrato-de-tela.
  // Mede só o que sobrevive à diferença de DOM e de tema entre os dois lados:
  //   · mono       — família monoespaçada é decisão de design, não de tema
  //   · corPropria — a célula pinta o texto, ou herda o da linha? (comparar a cor CRUA
  //                  entre lados seria ruído: temas diferentes, tokens diferentes)
  //   · tag        — BUTTON/A significa "é alcançável"; SPAN significa "virou texto morto"
  //   · pill       — presença + raio + borda + dot: é o vocabulário da D6 pra estado
  //   · blocos     — quantos textos a célula tem (a sub-linha existe ou sumiu?)
  // ⚠️ As duas heurísticas abaixo nasceram ERRADAS e foram consertadas por MEDIÇÃO no
  // protótipo vivo (2026-08-27), antes de qualquer veredito. Ficam registradas porque
  // quem for "simplificar" isto vai reintroduzir os dois:
  //
  //  1. TEXTO por folha (exigir que o elemento nao tenha filhos) perde todo elemento que
  //     tem texto E filho — o caso mais comum. A coluna Disco do protótipo reportava ZERO
  //     blocos tendo texto. Agora conta NÓS DE TEXTO por TreeWalker, como a D9 já fazia.
  //  2. PÍLULA por seletor de CLASSE (badge/pill/chip) é cega no protótipo, onde o
  //     StatusBadge é um span com estilo INLINE e nenhuma classe. Medido: 0 pílulas em 7
  //     colunas que visivelmente têm uma. Agora detecta por FORMA — raio maior ou igual a
  //     metade da altura — que atravessa tanto a classe utilitária do repo quanto o
  //     borderRadius inline do protótipo.
  // DEDUPLICA por elemento-pai. Contar NÓS de texto infla: interpolação JSX parte o texto
  // em vários nós dentro do MESMO elemento — medido em produção 2026-08-27, a célula
  // "Vinculado a" tem um único <span> com {dono_tipo} #{dono_id} e a sonda contava 3.
  // O efeito não era ruído: o veredito saía INVERTIDO (prod "com mais blocos" que o design,
  // quando na verdade ela tinha perdido a sub-linha). Bloco visual = elemento que carrega
  // texto, não nó de texto.
  const textosDe = (raiz) => {
    const w = document.createTreeWalker(raiz, NodeFilter.SHOW_TEXT, {
      acceptNode: (n) => ((n.nodeValue || '').trim() ? NodeFilter.FILTER_ACCEPT : NodeFilter.FILTER_REJECT),
    });
    const vistos = new Set();
    for (let n = w.nextNode(); n; n = w.nextNode()) if (n.parentElement) vistos.add(n.parentElement);
    return [...vistos];
  };
  const celulaInfo = (td, corDaLinha) => {
    const paisDeTexto = textosDe(td);
    const alvo = paisDeTexto[0] || td;
    const ca = cs(alvo);
    const ca0 = cs(td); // a CÉLULA, não o filho que carrega o texto — ver 'align' abaixo
    const pill = [...td.querySelectorAll('*')].find((e) => {
      // Pílula é RÓTULO DE ESTADO, nunca controle. Sem este corte, botão só-ícone redondo
      // casa por forma: medido no protótipo em 2026-08-27, a coluna de ações reportava
      // pílula (1 FP em 7 colunas). O corte é semântico, não sintático — some o FP e as
      // duas pílulas reais (Classificação, Vence em) continuam sendo vistas.
      if (/^(BUTTON|A)$/.test(e.tagName) || e.closest('button,a')) return false;
      const r = e.getBoundingClientRect();
      const raio = parseFloat(cs(e).borderTopLeftRadius) || 0;
      const txt = (e.textContent || '').trim();
      return r.height > 0 && raio >= r.height / 2 - 1 && txt.length > 0 && txt.length <= 24;
    });
    const cp = pill ? cs(pill) : null;
    const dot = pill
      ? [...pill.querySelectorAll('*')].some((e) => {
          const r = e.getBoundingClientRect();
          return !(e.textContent || '').trim() && r.width > 0 && r.width <= 10 && Math.abs(r.width - r.height) <= 2;
        })
      : false;
    // GEOMETRIA (2026-08-27, 2ª leva). O que a 1ª leva desta sonda NÃO via — e por isso
    // atravessou o comparador inteiro: LARGURA, ALINHAMENTO e ESTADO DE LINHA. O protótipo
    // declara 'columns[] = {width, align, mono}' e 'rows[].state'; do trio, só o 'mono'
    // tinha medida aqui, e foi o único que chegou na produção. Reportado por [W].
    //
    // 'align' é medido no PRÓPRIO <td>, não num filho — e essa escolha É o achado: a prod
    // escrevia 'text-right' num <span> DENTRO da célula, o que não alinha a célula nem o
    // cabeçalho. Medir o filho teria dito "tem right" e passado verde.
    //
    // Largura em FRAÇÃO da linha, nunca em px: os dois lados renderizam em containers de
    // largura diferente, e px cru seria ruído garantido. Fração é comparável — mas fica
    // como DADO no relatório, sem veredito próprio: em 'table-layout:auto' a largura é
    // consequência do conteúdo, e emitir bug sobre ela seria acusar o dado. Quem manda é
    // o 'tableLayout' (abaixo), que é decisão de design e não depende de dado nenhum.
    const rTd = td.getBoundingClientRect();
    const rLinha = td.parentElement ? td.parentElement.getBoundingClientRect() : null;
    const alinha = (v) => (v === 'start' ? 'left' : v === 'end' ? 'right' : v);
    return {
      tag: (td.querySelector('button,a') || {}).tagName || null,
      align: alinha(ca0.textAlign),
      larguraPct: rLinha && rLinha.width > 0 ? Math.round((rTd.width / rLinha.width) * 1000) / 10 : null,
      mono: paisDeTexto.some((e) => /mono/i.test(cs(e).fontFamily)),
      corPropria: paisDeTexto.some((e) => cs(e).color !== corDaLinha),
      fontPx: Math.round(parseFloat(ca.fontSize)),
      weight: ca.fontWeight,
      blocos: paisDeTexto.length,
      pill: pill
        ? { radius: cp.borderTopLeftRadius, borda: cp.borderTopWidth !== '0px', bg: cp.backgroundColor, dot }
        : null,
    };
  };
  const linhaEl = R.tableRow ? q(R.tableRow) : null;
  const celulas = linhaEl
    ? [...linhaEl.children].map((td, i) => ({ col: i, ...celulaInfo(td, cs(linhaEl).color) }))
    : null;
  // TABELA — o nível acima da célula, e é dele que sai o único sinal de geometria que NÃO
  // depende de dado. 'table-layout' é decisão de design pura: 'fixed' significa "as larguras
  // são declaradas e mandam"; 'auto' significa "o conteúdo decide". Quando o design declara
  // largura por coluna e a prod está em 'auto', TODA divergência de largura é consequência
  // deste único fato — reportá-lo é apontar a causa, não o sintoma.
  //
  // 'colunasDeclaradas' conta <col> com largura: é a forma canônica de declarar geometria em
  // tabela HTML, e a ausência dela do lado da prod é o que se conserta.
  //
  // 'linhasComEstado' é o 'rows[].state' do protótipo medido por CONSEQUÊNCIA (trilha =
  // box-shadow; apagado = opacity < 1), nunca por nome de classe — classe é cega ao estilo
  // inline do protótipo, que foi o erro já consertado na detecção de pílula. Veredito dele é
  // sempre SINAL, nunca bug: a contagem depende do DADO (o mock vence em 2031, a prod não),
  // e um sinal que não distingue "capacidade ausente" de "nenhuma linha nesse estado hoje"
  // não pode reprovar ninguém. Mesma regra do 'blocos'.
  const tabelaEl = linhaEl ? linhaEl.closest('table') : null;
  const corpo = tabelaEl ? [...tabelaEl.querySelectorAll('tbody tr')] : [];
  const tabela = tabelaEl
    ? {
        tableLayout: cs(tabelaEl).tableLayout,
        colunasDeclaradas: [...tabelaEl.querySelectorAll('colgroup col')].filter((c) => {
          const w = cs(c).width;
          return w && w !== 'auto' && w !== '0px';
        }).length,
        linhas: corpo.length,
        linhasComEstado: corpo.filter((tr) => {
          const c = cs(tr);
          const daCelula = [...tr.children].some((td) => cs(td).boxShadow !== 'none');
          return c.boxShadow !== 'none' || daCelula || parseFloat(c.opacity) < 1;
        }).length,
      }
    : null;
  // D9 — CLASSE DE FORMATO do texto visível, por região [data-contract].
  // NAO e diff de string: o prototipo tem mock e a prod tem dado real, entao comparar
  // texto cru seria 100% ruido. O que se compara e a FORMA — "22/08/2031" e "2026-09-07"
  // sao o mesmo dado em classes diferentes, e e a classe que denuncia.
  // Exclui code/pre/kbd pela MESMA fronteira do ds/no-db-jargon-in-ui: ali nome de coluna
  // ou rota e doc tecnica intencional, nao jargao vazando. O atributo title tambem fica
  // fora por construcao (textContent nao o le) — e onde o canon manda por o tecnico.
  const CLASSES = [
    ['dataIso', /\\b\\d{4}-\\d{2}-\\d{2}\\b/g],
    ['dataBr', /\\b\\d{2}\\/\\d{2}\\/\\d{4}\\b/g],
    ['snakeCru', /\\b[a-z][a-z0-9]*(?:_[a-z0-9]+)+\\b/g],
  ];
  const textoVisivel = (raiz) => {
    const w = document.createTreeWalker(raiz, NodeFilter.SHOW_TEXT, {
      acceptNode: (n) => {
        if (!(n.nodeValue || '').trim()) return NodeFilter.FILTER_REJECT;
        for (let e = n.parentElement; e && e !== raiz.parentElement; e = e.parentElement) {
          if (/^(CODE|PRE|KBD|SCRIPT|STYLE)$/.test(e.tagName)) return NodeFilter.FILTER_REJECT;
          const c2 = getComputedStyle(e);
          if (c2.display === 'none' || c2.visibility === 'hidden') return NodeFilter.FILTER_REJECT;
        }
        return NodeFilter.FILTER_ACCEPT;
      },
    });
    const partes = [];
    for (let n = w.nextNode(); n; n = w.nextNode()) partes.push(n.nodeValue);
    return partes.join(' ').replace(/\\s+/g, ' ').trim();
  };
  const contratos = qa('[data-contract]').map((el) => {
    const t = textoVisivel(el);
    const classes = {}; const exemplos = {};
    for (const par of CLASSES) {
      par[1].lastIndex = 0;
      const m = t.match(par[1]) || [];
      classes[par[0]] = m.length;
      if (m.length) exemplos[par[0]] = [...new Set(m)].slice(0, 3);
    }
    return { nome: el.getAttribute('data-contract'), classes, exemplos };
  });
  // ── SHELL (sidebar) — 2026-08-28 ──────────────────────────────────────────────
  // Config em 'window.__SB_ROLES' = { <papel>: '<seletor CSS DESTE lado>' }. Aceita também
  // '__DD_ROLES.shell' pra quem preferir passar um objeto só. Ausente ⇒ 'shell: null' ⇒ o
  // comparador diz SEM-DADO — nunca "igual" por omissão (§5 2026-07-29).
  //
  // Mede o PRIMEIRO elemento VISÍVEL de cada papel (rect > 0). Item de sidebar é população
  // homogênea por construção: o que diverge do design diverge no conjunto, e medir o primeiro
  // visível evita que um item colapsado (rail fechado, grupo recolhido) dite a medida.
  const S = window.__SB_ROLES || R.shell || null;
  const CSS_SHELL = ['height', 'minHeight', 'padding', 'margin', 'gap', 'display', 'alignItems',
    'fontFamily', 'fontSize', 'fontWeight', 'lineHeight', 'letterSpacing', 'textTransform',
    'color', 'backgroundColor', 'borderRadius', 'borderWidth', 'borderColor', 'opacity', 'width',
    'boxShadow', 'textDecorationLine'];
  const normFam = (v) => String(v || '').replace(/["']/g, '').replace(/,\\s*/g, ',');
  const px1 = (n) => Math.round(n * 10) / 10;
  const papelInfo = (sel) => {
    const els = qa(sel).filter((e) => { const r = e.getBoundingClientRect(); return r.width > 0 && r.height > 0; });
    if (!els.length) return { seletor: sel, n: 0, css: null, caixa: null, icone: null };
    const el = els[0], c = cs(el), r = el.getBoundingClientRect();
    const css = {};
    for (const k of CSS_SHELL) {
      // borderColor: o lado TOPO é o representativo — mesma escolha que o 'primary.border'
      // acima. Shorthand de 4 lados vira string irregular entre engines e forkaria a
      // comparação por NOTAÇÃO, não por design.
      // fontFamily: aspas e espaço depois da vírgula são notação, não decisão — normalizados.
      css[k] = k === 'borderColor' ? c.borderTopColor : k === 'fontFamily' ? normFam(c.fontFamily) : c[k];
    }
    const svg = el.querySelector('svg');
    const rs = svg ? svg.getBoundingClientRect() : null;
    return {
      seletor: sel, n: els.length, css,
      caixa: { w: px1(r.width), h: px1(r.height) },
      icone: svg ? { w: px1(rs.width), h: px1(rs.height), strokeWidth: cs(svg).strokeWidth } : null,
    };
  };
  const shell = S ? (() => {
    const papeis = {};
    for (const nome of Object.keys(S)) papeis[nome] = papelInfo(S[nome]);
    // SEQUÊNCIA dos atalhos de topo. Ordem de navegação é CONTRATO, não estética — e é a
    // única parte do shell que computed-style nenhum alcança: dois sidebars podem ter cada
    // pixel idêntico e a ordem trocada. Rótulo vem do aria-label quando existe (ícone-only
    // não tem texto), senão do textContent.
    const atalhos = S.atalhoTopo
      ? qa(S.atalhoTopo).map((el, i) => {
          const marcado = el.getAttribute('aria-current') || (el.closest('[aria-current]') ? el.closest('[aria-current]').getAttribute('aria-current') : null);
          return {
            i,
            rotulo: (el.getAttribute('aria-label') || el.textContent || '').replace(/\\s+/g, ' ').trim().slice(0, 32),
            atual: marcado || null,
          };
        })
      : null;
    return { papeis, atalhos };
  })() : null;
  return {
    url: location.href,
    theme: document.documentElement.getAttribute('data-theme') || (document.documentElement.classList.contains('dark') ? 'dark' : 'light'),
    roles: { kpi, title, primary, filterRows: filterEls.length ? visualRows(filterEls) : null, contratos, celulas, tabela, shell },
  };
})()`;

/* ─────────────────────────────────────────────────────────────────────────────
 * COMPARADOR (node puro, determinístico). Cada dimensão é uma função pura que
 * recebe (prod.roles, design.roles) e devolve linhas de veredito.
 *   IGUAL            — medida idêntica
 *   DIVERGE (bug)    — medida difere E o design é a referência → prod está errada
 *   DIVERGE (tema)   — difere só por cor de texto que acompanha o tema (não é bug estrutural)
 *   DIVERGE (dado?)  — a medida difere mas o sinal NÃO distingue design de dado (ex.: nº de
 *                      blocos muda com render condicional). Sinal pro humano, nunca bug.
 *   SEM-DADO         — um dos lados não trouxe a medida (não mente por omissão)
 * ─────────────────────────────────────────────────────────────────────────── */

/** @param {any} prod @param {any} design */
function dimAlinhamento(prod, design) { // D8
  const rows = [];
  const pk = prod.kpi, dk = design.kpi;
  if (!pk || !dk) return [{ dim: 'D8', campo: 'kpi', prod: '—', design: '—', veredito: 'SEM-DADO' }];
  // a TAG explica a causa (button=center-default × div=left)
  if (pk.tag !== dk.tag) rows.push({ dim: 'D8', campo: 'kpi.tag', prod: pk.tag, design: dk.tag, veredito: pk.tag === 'BUTTON' && dk.tag !== 'BUTTON' ? 'DIVERGE (bug)' : 'DIVERGE (bug)' });
  const n = Math.max(pk.items.length, dk.items.length);
  let mismatch = 0;
  for (let i = 0; i < n; i++) {
    const a = pk.items[i], b = dk.items[i];
    if (!a || !b) continue;
    const pa = a.textAlign === 'start' ? 'left' : a.textAlign;
    const da = b.textAlign === 'start' ? 'left' : b.textAlign;
    if (pa !== da) mismatch++;
  }
  if (mismatch > 0) rows.push({ dim: 'D8', campo: 'kpi.text-align', prod: (pk.items[0] || {}).textAlign, design: (dk.items[0] || {}).textAlign, veredito: 'DIVERGE (bug)', detalhe: mismatch + '/' + n + ' KPIs desalinhados' });
  if (!rows.length) rows.push({ dim: 'D8', campo: 'kpi align', prod: 'ok', design: 'ok', veredito: 'IGUAL' });
  return rows;
}

/** @param {any} prod @param {any} design */
function dimLayout(prod, design) { // D2
  const rows = [];
  const pk = prod.kpi, dk = design.kpi;
  if (pk && dk && pk.count !== dk.count) rows.push({ dim: 'D2', campo: 'kpi.count', prod: pk.count, design: dk.count, veredito: 'DIVERGE (bug)' });
  if (pk && pk.overflowX === true) rows.push({ dim: 'D2', campo: 'kpi.overflowX', prod: 'estoura viewport', design: 'cabe', veredito: 'DIVERGE (bug)' });
  if (prod.filterRows != null && design.filterRows != null && prod.filterRows !== design.filterRows)
    rows.push({ dim: 'D2', campo: 'filtro linhas', prod: prod.filterRows, design: design.filterRows, veredito: 'DIVERGE (bug)' });
  if (!rows.length) rows.push({ dim: 'D2', campo: 'layout', prod: 'ok', design: 'ok', veredito: 'IGUAL' });
  return rows;
}

/** @param {any} prod @param {any} design */
function dimTipografia(prod, design) { // D4
  const rows = [];
  const a = prod.title, b = design.title;
  if (!a || !b) return [{ dim: 'D4', campo: 'título', prod: '—', design: '—', veredito: 'SEM-DADO' }];
  // banda declarada (TOLERANCIAS.tituloPx): 1px = o artefato máximo do Math.round da própria sonda.
  const dT = Math.abs(a.fontPx - b.fontPx);
  if (dT > TOLERANCIAS.tituloPx.valor) rows.push({ dim: 'D4', campo: 'título font-size', prod: a.fontPx + 'px', design: b.fontPx + 'px', veredito: 'DIVERGE (bug)', detalhe: `Δ ${dT}px · banda tituloPx ±${TOLERANCIAS.tituloPx.valor}px` });
  // O VALOR do KPI — o texto que o usuário de fato lê no card.
  //
  // Este bloco faltava, e a ausência era SILENCIOSA: a sonda MEDIA valueFontPx
  // desde sempre (linha ~96) e o comparador nunca lia o campo. Dado coletado que
  // nenhum consumidor consome não é neutro — é a mesma doutrina do CLAUDE.md
  // ("máquina que existe e ninguém invoca é bug, não neutralidade"), aqui no eixo
  // do CAMPO. Pior que divergir: o relatório saía com a mesma cara de quem mediu
  // e bateu, e a dimensão D4 se apresentava como "tipografia" medindo só o título.
  //
  // Medido em 2026-08-21 no Painel da Jana: prod 24px × design 22px. Sem este
  // bloco, os dois lados apareciam como IGUAL na linha de tipografia.
  const pv = ((prod.kpi && prod.kpi.items) || []).map((i) => i.valueFontPx).filter((x) => x != null);
  const dv = ((design.kpi && design.kpi.items) || []).map((i) => i.valueFontPx).filter((x) => x != null);
  if (pv.length && dv.length) {
    const dV = Math.abs(pv[0] - dv[0]);
    if (dV > TOLERANCIAS.tipografia.valor) {
      rows.push({ dim: 'D4', campo: 'kpi valor font-size', prod: pv[0] + 'px', design: dv[0] + 'px', veredito: 'DIVERGE (bug)', detalhe: 'Δ ' + dV + 'px · banda tipografia ±' + TOLERANCIAS.tipografia.valor + 'px' });
    }
  } else if (pv.length !== dv.length) {
    // Um lado mediu e o outro não: NÃO é igual, é NÃO-MEDIDO. Dizer "IGUAL" aqui
    // seria afirmar sobre o que não se conseguiu ler (§5 2026-07-29).
    rows.push({ dim: 'D4', campo: 'kpi valor font-size', prod: pv.length ? pv[0] + 'px' : 'não medido', design: dv.length ? dv[0] + 'px' : 'não medido', veredito: 'SEM-DADO' });
  }
  if (!rows.length) rows.push({ dim: 'D4', campo: 'tipografia', prod: 'ok', design: 'ok', veredito: 'IGUAL' });
  return rows;
}

/** oklch/oklab → lightness (1º número) pra comparar sem falso-positivo de tema */
function lightnessOf(color) {
  const m = /ok(?:lch|lab)\(\s*([0-9.]+)/.exec(color || '');
  return m ? parseFloat(m[1]) : null;
}

/** @param {any} prod @param {any} design */
function dimCor(prod, design) { // D6
  const rows = [];
  const a = prod.primary, b = design.primary;
  if (a && b) {
    // compara HUE do accent (o "roxinho"): 3º número do oklch
    const hue = (c) => { const m = /ok(?:lch|lab)\([0-9.]+ [0-9.-]+ ([0-9.]+)/.exec(c || ''); return m ? Math.round(parseFloat(m[1])) : null; };
    const ph = hue(a.bg), dh = hue(b.bg);
    if (ph != null && dh != null && Math.abs(ph - dh) > TOLERANCIAS.matiz.valor) rows.push({ dim: 'D6', campo: 'primary hue', prod: a.bg, design: b.bg, veredito: 'DIVERGE (bug)', detalhe: `Δ ${Math.abs(ph - dh)}° · banda matiz ±${TOLERANCIAS.matiz.valor}°` });
    // lightness do accent (roxo escuro travado × roxinho que clareia no dark)
    const pl = lightnessOf(a.bg), dl = lightnessOf(b.bg);
    const dL = pl != null && dl != null ? Math.abs(pl - dl) : null;
    if (dL != null && dL > TOLERANCIAS.luminancia.valor) rows.push({ dim: 'D6', campo: 'primary lightness', prod: pl, design: dl, veredito: prod.__theme === design.__theme ? 'DIVERGE (bug)' : 'DIVERGE (tema)', detalhe: `Δ ${Number(dL.toFixed(5))} · banda luminancia ±${TOLERANCIAS.luminancia.valor}` });
  }
  // contraste do texto do KPI: lightness do texto vs (heurística) fundo do tema
  const pk = prod.kpi;
  if (pk && pk.items[0]) {
    const tl = lightnessOf(pk.items[0].textColor);
    if (prod.__theme === 'dark' && tl != null && tl < 0.5) rows.push({ dim: 'D6', campo: 'kpi texto (dark)', prod: 'lightness ' + tl + ' (escuro no escuro)', design: '≥0.6', veredito: 'DIVERGE (bug)' });
  }
  if (!rows.length) rows.push({ dim: 'D6', campo: 'cor', prod: 'ok', design: 'ok', veredito: 'IGUAL' });
  return rows;
}

/**
 * D9 — TEXTO, por CLASSE DE FORMATO, região a região ([data-contract]).
 *
 * POR QUE EXISTE (2026-08-26): as 4 dimensões acima são computed-style puro, e o próprio
 * docblock deste arquivo declarava que texto ficava de fora. Só que a classe de defeito que
 * mais escapa na travessia protótipo→tela é TEXTUAL: data em ISO onde o design desenha
 * dd/mm/aaaa, e valor cru de enum/config impresso como rótulo. Dez refinos levantados na
 * `Arquivos/Index` em 2026-08-26 eram disso, com TODOS os gates verdes — porque nenhum
 * media texto. Esta dimensão é o buraco fechado no dono que já existe, não régua nova.
 *
 * NÃO é diff de string, e a distinção é o que torna a dimensão utilizável: o protótipo roda
 * com mock e a produção com dado real, então comparar o texto literal daria ~100% de ruído.
 * O que se compara é a CLASSE DE FORMATO — "22/08/2031" e "2026-09-07" são o mesmo dado em
 * classes diferentes, e é a classe que denuncia, não o valor.
 *
 * VEREDITOS (a direção importa, e não é simétrica):
 *   · prod tem classe crua que o design NÃO tem  → DIVERGE (bug)   — a travessia introduziu
 *   · design tem classe crua que a prod NÃO tem  → DIVERGE (fonte) — o protótipo é que está
 *     errado; a tela não regrediu. NÃO é bug da prod, e chamar de bug mandaria consertar
 *     o lado certo (foi assim que `hard_delete` chegou à tela: veio desenhado).
 *
 * LIMITES DECLARADOS (medidos, não supostos):
 *   · valor cru de UMA palavra (o disco `arquivos`, o bucket `active`) NÃO casa — por forma
 *     ele é indistinguível de palavra legítima. `snakeCru` só vê o que tem separador.
 *   · copy em inglês NÃO casa. É a AP8/R8, que a ADR 0271 onda 2 deixou explicitamente no
 *     juiz manual ao deletar o `pr-ui-judge.yml` por dormência. Fingir cobrir aqui seria
 *     inventar um segundo dono pra uma decisão já tomada.
 *   · code/pre/kbd e o atributo `title` ficam FORA — mesma fronteira do `ds/no-db-jargon-in-ui`:
 *     ali o técnico é intencional, e é onde o canon desta casa manda pô-lo.
 *
 * FP MEDIDO ANTES DE LIGAR (2026-08-26, sonda injetada no protótipo vivo em localhost):
 *   11 regiões em 2 vistas de uma fonte sabidamente correta → 0 falso-positivo, 1 verdadeiro
 *   (`hard_delete` no card Estratégia — defeito real, e do próprio protótipo).
 *
 * @param {any} prod @param {any} design
 */
function dimTexto(prod, design) { // D9
  const rows = [];
  const pc = prod.contratos, dc = design.contratos;
  if (!Array.isArray(pc) || !Array.isArray(dc)) {
    // Snapshot antigo (sonda anterior a esta dimensão). Não mente por omissão.
    return [{ dim: 'D9', campo: 'contratos', prod: Array.isArray(pc) ? 'ok' : 'ausente', design: Array.isArray(dc) ? 'ok' : 'ausente', veredito: 'SEM-DADO', detalhe: 're-injete a sonda atual (--probe) nos DOIS lados' }];
  }
  const idx = (arr) => Object.fromEntries(arr.map((c) => [c.nome, c]));
  const P = idx(pc), D = idx(dc);
  const CRUAS = ['dataIso', 'snakeCru'];
  const nomes = [...new Set([...Object.keys(P), ...Object.keys(D)])].sort();
  for (const nome of nomes) {
    const a = P[nome], b = D[nome];
    if (!a || !b) {
      rows.push({ dim: 'D9', campo: nome, prod: a ? 'presente' : 'ausente', design: b ? 'presente' : 'ausente', veredito: 'SEM-DADO', detalhe: 'região só existe de um lado' });
      continue;
    }
    for (const k of CRUAS) {
      const np = a.classes[k] || 0, nd = b.classes[k] || 0;
      const amostra = (c) => (c.exemplos && c.exemplos[k] ? ' — ' + c.exemplos[k].join(', ') : '');
      if (np > 0 && nd === 0) rows.push({ dim: 'D9', campo: nome + '.' + k, prod: np + '×' + amostra(a), design: '0', veredito: 'DIVERGE (bug)', detalhe: 'a travessia introduziu forma crua que o design não tem' });
      else if (nd > 0 && np === 0) rows.push({ dim: 'D9', campo: nome + '.' + k, prod: '0', design: nd + '×' + amostra(b), veredito: 'DIVERGE (fonte)', detalhe: 'o PROTÓTIPO carrega a forma crua — consertar a fonte, não a tela' });
    }
    // Data: o par (ISO na prod, BR no design) na MESMA região é o caso canônico.
    if ((a.classes.dataIso || 0) > 0 && (b.classes.dataBr || 0) > 0) {
      rows.push({ dim: 'D9', campo: nome + '.formato-de-data', prod: 'ISO', design: 'dd/mm/aaaa', veredito: 'DIVERGE (bug)', detalhe: 'mesma região, formatos opostos' });
    }
  }
  if (!rows.length) rows.push({ dim: 'D9', campo: 'texto', prod: 'ok', design: 'ok', veredito: 'IGUAL' });
  return rows;
}

/**
 * CÉLULA DA TABELA — não é dimensão nova. É a parte de D2/D4/D6/D8 que o PROTOCOLO já
 * declarava e a mecanização nunca implementou:
 *
 *   D4 tipografia — "…valor do KPI, LINHA DA TABELA"    → media só título e valor do KPI
 *   D6 cor/token  — "accent, PILLS (radius/border/…)"   → media só o bg do primary
 *   D8 alinhamento— "comparar tagName (a tag explica)"  → só no KPI
 *   D2 layout     — "nº de LINHAS visuais de cada zona" → só na barra de filtro
 *
 * Por isso as linhas saem rotuladas com a dimensão CANÔNICA de cada sinal, e não com um
 * número novo: quem lê o relatório continua vendo D4/D6/D8/D2, que é o vocabulário do
 * protocolo. Uma função só porque o PAREAMENTO (coluna a coluna) é o mesmo pros quatro.
 *
 * O que fez isso existir: [W], 2026-08-27, olhando "Vinculado a" e "Classificação" da
 * `Arquivos/Index` — link colorido virou texto morto, sub-linha em mono sumiu, pílula
 * perdeu cor e dot. Quatro gates verdes, e nenhum olhava célula de tabela.
 *
 * @param {any} prod @param {any} design
 */
function dimCelulas(prod, design) {
  const rows = [];
  const P = prod.celulas, D = design.celulas;
  if (!Array.isArray(P) || !Array.isArray(D)) {
    return [{ dim: 'D4', campo: 'linha da tabela', prod: Array.isArray(P) ? 'ok' : 'não medido', design: Array.isArray(D) ? 'ok' : 'não medido', veredito: 'SEM-DADO', detalhe: 'passe `tableRow` em __DD_ROLES nos DOIS lados' }];
  }
  const n = Math.min(P.length, D.length);
  if (P.length !== D.length) {
    rows.push({ dim: 'D2', campo: 'nº de colunas', prod: P.length, design: D.length, veredito: 'DIVERGE (bug)', detalhe: 'comparando as ' + n + ' primeiras' });
  }

  // D2 — GEOMETRIA DA TABELA. Vem antes das colunas de propósito: quando o modo de layout
  // difere, cada largura divergente é sintoma deste fato, e ler a causa primeiro evita
  // tratar sete sintomas como sete achados.
  const PT = prod.tabela, DT = design.tabela;
  if (!PT || !DT) {
    rows.push({ dim: 'D2', campo: 'geometria da tabela', prod: PT ? 'ok' : 'não medido', design: DT ? 'ok' : 'não medido', veredito: 'SEM-DADO', detalhe: 're-injete a sonda atual (--probe) nos DOIS lados' });
  } else {
    if (PT.tableLayout !== DT.tableLayout) {
      rows.push({ dim: 'D2', campo: 'table-layout', prod: PT.tableLayout, design: DT.tableLayout, veredito: DT.tableLayout === 'fixed' ? 'DIVERGE (bug)' : 'DIVERGE (fonte)', detalhe: DT.tableLayout === 'fixed' ? 'o design declara larguras e elas mandam; a prod deixa o conteúdo decidir — é a causa de toda largura divergente abaixo' : undefined });
    }
    if (PT.colunasDeclaradas !== DT.colunasDeclaradas) {
      rows.push({ dim: 'D2', campo: 'colunas com largura declarada', prod: PT.colunasDeclaradas, design: DT.colunasDeclaradas, veredito: DT.colunasDeclaradas > PT.colunasDeclaradas ? 'DIVERGE (bug)' : 'DIVERGE (fonte)' });
    }
    // SINAL, nunca bug — a contagem depende do dado dos dois lados. Ver o comentário da sonda.
    if ((PT.linhasComEstado > 0) !== (DT.linhasComEstado > 0)) {
      rows.push({ dim: 'D6', campo: 'estado de linha', prod: PT.linhasComEstado + '/' + PT.linhas, design: DT.linhasComEstado + '/' + DT.linhas, veredito: 'DIVERGE (dado?)', detalhe: 'um lado distingue linha por estado (trilha/apagado) e o outro não — pode ser capacidade ausente OU nenhuma linha nesse estado hoje; confira antes de tratar como defeito' });
    }
  }
  for (let i = 0; i < n; i++) {
    const a = P[i], b = D[i], col = 'col' + i;
    // D4 — família monoespaçada é decisão de design, não de tema.
    if (a.mono !== b.mono) {
      rows.push({ dim: 'D4', campo: col + '.mono', prod: a.mono ? 'mono' : 'sem mono', design: b.mono ? 'mono' : 'sem mono', veredito: b.mono ? 'DIVERGE (bug)' : 'DIVERGE (fonte)' });
    }
    // D6 — a célula PINTA o texto ou herda o da linha? (cor crua entre lados = ruído de tema)
    if (a.corPropria !== b.corPropria) {
      rows.push({ dim: 'D6', campo: col + '.cor', prod: a.corPropria ? 'cor própria' : 'herda a da linha', design: b.corPropria ? 'cor própria' : 'herda a da linha', veredito: b.corPropria ? 'DIVERGE (bug)' : 'DIVERGE (fonte)' });
    }
    // D8 — a TAG explica a causa: BUTTON/A é alcançável, SPAN virou texto morto.
    // O que importa e ALCANCAVEL ou nao. Link real (a href) e navegacao SPA (button onClick)
    // sao as DUAS formas legitimas, e tratar a diferenca de tag como divergencia e falso-positivo.
    // Medido em 2026-08-27: depois do #6345 a prod passou a usar A onde o prototipo usa BUTTON,
    // e a regua acusou 'a prod imprime texto morto' sobre um link que funciona. A tag continua
    // no relatorio como DETALHE — ela explica alinhamento, porque BUTTON herda text-align:center
    // — mas nao emite veredito duro sozinha.
    if (!!a.tag !== !!b.tag) {
      rows.push({ dim: 'D8', campo: col + '.alcancavel', prod: a.tag || 'sem elemento clicavel', design: b.tag || 'sem elemento clicavel', veredito: b.tag ? 'DIVERGE (bug)' : 'DIVERGE (fonte)', detalhe: b.tag ? 'o design alcanca o destino; a prod imprime texto morto' : undefined });
    } else if (a.tag && b.tag && a.tag !== b.tag) {
      rows.push({ dim: 'D8', campo: col + '.tag', prod: a.tag, design: b.tag, veredito: 'DIVERGE (impl)', detalhe: 'os dois sao alcancaveis — formas diferentes. BUTTON herda text-align:center; confira o alinhamento da celula' });
    }

    // D8 — ALINHAMENTO DA CÉLULA. Medido no <td>, e é por isso que morde: a prod escrevia
    // `text-right` num <span> dentro da célula. Visualmente o texto até encosta à direita
    // quando o span ocupa a célula inteira, mas o <td> segue `left` — e o <th> junto, que é
    // o que denuncia (cabeçalho à esquerda sobre número à direita). Alinhamento é decisão de
    // design pura: não depende de dado, então emite veredito duro.
    if (a.align && b.align && a.align !== b.align) {
      rows.push({ dim: 'D8', campo: col + '.align', prod: a.align, design: b.align, veredito: 'DIVERGE (bug)', detalhe: 'o alinhamento é da CÉLULA — classe num filho não alinha o <td> nem o cabeçalho' });
    }
    // D2 — a sub-linha existe ou sumiu?
    if (a.blocos !== b.blocos) {
      // NUNCA `DIVERGE (bug)`. O nº de blocos depende do DADO, não só do design: a célula
      // "Vence em" da prod mostra o contador "11d" porque aquele arquivo vence em 11 dias,
      // e a do protótipo não mostra porque o mock vence em 2031 — o contador só renderiza
      // a ≤90 dias. Medido em 2026-08-27, e foi o que fez este sinal acusar divergência
      // onde havia só data diferente. Um sinal que não distingue "conteúdo perdido" de
      // "dado diferente" não pode emitir veredito duro: vira sinal pro humano ler.
      // Continua valendo a pena existir — foi ele que apontou a sub-linha sumida.
      rows.push({ dim: 'D2', campo: col + '.blocos de texto', prod: a.blocos, design: b.blocos, veredito: 'DIVERGE (dado?)', detalhe: 'pode ser render condicional dirigido por dado — confira a célula antes de tratar como defeito' });
    }
    // D6 — pílula: presença, e depois raio/borda/dot (o vocabulário da D6 pra estado).
    const pa = a.pill, pb = b.pill;
    if (!!pa !== !!pb) {
      rows.push({ dim: 'D6', campo: col + '.pílula', prod: pa ? 'presente' : 'ausente', design: pb ? 'presente' : 'ausente', veredito: pb ? 'DIVERGE (bug)' : 'DIVERGE (fonte)' });
    } else if (pa && pb) {
      if (pa.dot !== pb.dot) rows.push({ dim: 'D6', campo: col + '.pílula.dot', prod: pa.dot ? 'com dot' : 'sem dot', design: pb.dot ? 'com dot' : 'sem dot', veredito: pb.dot ? 'DIVERGE (bug)' : 'DIVERGE (fonte)' });
      if (pa.borda !== pb.borda) rows.push({ dim: 'D6', campo: col + '.pílula.borda', prod: pa.borda ? 'com borda' : 'sem borda', design: pb.borda ? 'com borda' : 'sem borda', veredito: pb.borda ? 'DIVERGE (bug)' : 'DIVERGE (fonte)' });
    }
  }
  if (!rows.length) rows.push({ dim: 'D4', campo: 'linha da tabela', prod: 'ok', design: 'ok', veredito: 'IGUAL' });
  return rows;
}

/* ─────────────────────────────────────────────────────────────────────────────
 * SHELL (sidebar) — escopo novo, 2026-08-28.
 *
 * As dimensões acima medem a TELA. O shell não era medido por máquina nenhuma — e o
 * `style-fingerprint.mjs` o exclui DE PROPÓSITO (`window.__ROOT__`: "mata o ruído de
 * shell/sidebar"). Custo medido: 19 propriedades divergentes que nenhum gate viu; 3 saíram
 * por amostragem no olho, 16 só por enumeração.
 *
 * O veredito aqui é `DIVERGE (a classificar)`, NUNCA `(bug)`. Não é timidez: as três causas
 * possíveis — DECIDIDA · DERIVA · DESIGN-ANDOU — não são distinguíveis por medição, e chamar
 * de bug mandaria consertar o lado errado (foi assim que `hard_delete` chegou à tela na D9:
 * veio desenhado). A máquina REPORTA; o humano CLASSIFICA. O que já foi classificado como
 * DECIDIDA entra por `--declarado`, nunca embutido aqui.
 * ─────────────────────────────────────────────────────────────────────────── */

/**
 * Mapa CAMPO DO SHELL → CAMPO CANÔNICO do dono das bandas (`TOLERANCIAS`, style-fingerprint).
 *
 * Por que mapear em vez de escolher bandas próprias: banda duplicada drifa no primeiro ajuste,
 * e o §5 desta casa já enterrou "abrir régua paralela ao dono do tema". Aqui o eixo é do
 * `style-fingerprint` — se a banda de cor mudar lá, muda aqui junto, de graça.
 *
 * Duas escolhas que merecem estar escritas:
 *  · `borderWidth` → eixo `padding` (listaPx, ±0,5px) e não `borderW` (px simples): o computed
 *    de borda pode voltar com 4 lados ("1px 0px 0px 0px") e o eixo px lê só o primeiro número,
 *    ficando cego pros outros três. A banda numérica é a MESMA (0,5) — muda a leitura, não o rigor.
 *  · `backgroundColor` → `bgProprio` (não `bgEfetivo`): aqui interessa o fundo DECLARADO no
 *    elemento. Fundo herdado é do container, e atribuí-lo ao item mentiria sobre quem pinta.
 *  · `icone.strokeWidth` → eixo `tipografia` (banda 0) e NÃO `borda` (±0,5px). Medido ao
 *    escrever o selftest: o passo real do traço no lucide é 0,5 (1,5px → 2px), então a banda
 *    de `borda` engoliria o passo INTEIRO e o eixo nasceria MUDO — exatamente o defeito que a
 *    lápide do próprio eixo `borda` descreve no style-fingerprint ("o TOL_PX herdado engolia
 *    |0-1|=1 → o eixo era mudo"). Traço de ícone é valor DECLARADO, não medida de rect: não
 *    tem ruído sub-pixel pra absorver, e a régua honesta é igualdade numérica. O detalhe vai
 *    imprimir "banda tipografia ±0" — leia como "banda 0"; o eixo emprestado é o do valor 0.
 */
export const EIXO_SHELL = {
  height: 'h', minHeight: 'h', width: 'w', 'caixa.w': 'w', 'caixa.h': 'h',
  'icone.w': 'w', 'icone.h': 'h', 'icone.strokeWidth': 'fontSize',
  padding: 'padding', margin: 'padding', gap: 'padding',
  borderRadius: 'radius', borderWidth: 'padding',
  fontSize: 'fontSize', lineHeight: 'lineHeight', letterSpacing: 'letterSpacing',
  color: 'color', backgroundColor: 'bgProprio', borderColor: 'borderColor',
  opacity: 'opacity',
  display: 'display', alignItems: 'display', textDecorationLine: 'display',
  fontFamily: 'fontFamily', fontWeight: 'fontWeight', textTransform: 'textTransform',
  boxShadow: 'boxShadow',
};

/**
 * Diverge? Devolve a linha de detalhe (com Δ e banda), ou `null` se está dentro da banda.
 * A CONTA é do `diffCampo` (dono único); aqui só se troca o RÓTULO impresso, pro relatório
 * falar o vocabulário do shell (`minHeight`) e não o do fingerprint (`h`).
 */
export function divergenciaShell(nome, a, b) {
  const canonico = EIXO_SHELL[nome] || nome;
  const linha = diffCampo(canonico, a, b);
  if (!linha) return null;
  return linha.startsWith(canonico + ':') ? nome + linha.slice(canonico.length) : linha;
}

/**
 * Allowlist de divergência DECLARADA — por ENTRADA, nunca embutida no código.
 *
 * Sem isto a régua fica vermelha permanente (a prod tem `Forja` no topo e o design não; isso é
 * DECIDIDO, não deriva) e vermelho permanente é ruído que se aprende a ignorar — o mesmo defeito
 * que este projeto já catalogou em gate que nunca pode ficar verde.
 *
 * `motivo` é OBRIGATÓRIO e a ausência dele REJEITA o arquivo inteiro: allowlist sem razão escrita
 * é silenciamento cego, que é o que ela deveria impedir. Aceita `{ itens: [...] }` ou o array cru.
 */
export function validarDeclaracao(obj) {
  const itens = Array.isArray(obj) ? obj : (obj && Array.isArray(obj.itens) ? obj.itens : null);
  if (!itens) throw new Error('declaração: esperado { "itens": [ { "campo": "...", "motivo": "..." } ] } (ou o array direto)');
  itens.forEach((it, i) => {
    if (!it || typeof it.campo !== 'string' || !it.campo.trim()) throw new Error(`declaração[${i}]: "campo" ausente ou vazio`);
    if (typeof it.motivo !== 'string' || !it.motivo.trim()) {
      throw new Error(`declaração[${i}] (${it.campo}): "motivo" ausente — declarar divergência sem dizer POR QUE é exatamente o vetor que esta exigência fecha`);
    }
  });
  return itens.map((it) => ({ campo: it.campo.trim(), motivo: it.motivo.trim() }));
}

/**
 * Aplica a declaração — SÓ no escopo SHELL, e isso é fail-closed de propósito: silenciar
 * `DIVERGE (bug)` de tela é outra decisão, e não se toma por arquivo de allowlist deste modo.
 * Item que aponta pra fora do shell simplesmente não casa e sai reportado como SEM EFEITO.
 *
 * Casa por igualdade exata, ou por prefixo quando o campo termina em `*`. O relatório imprime
 * QUANTAS linhas cada entrada silenciou — assim declaração ampla demais fica VISÍVEL em vez de
 * virar um cobertor mudo.
 */
export function aplicarDeclaracao(rows, itens = []) {
  const usos = itens.map(() => 0);
  const out = rows.map((r) => {
    if (r.dim !== 'SHELL' || !String(r.veredito).startsWith('DIVERGE')) return r;
    for (let i = 0; i < itens.length; i++) {
      const alvo = String(itens[i].campo);
      const casa = alvo.endsWith('*') ? String(r.campo).startsWith(alvo.slice(0, -1)) : String(r.campo) === alvo;
      if (casa) { usos[i] += 1; return { ...r, veredito: 'DIVERGE (declarada)', detalhe: itens[i].motivo }; }
    }
    return r;
  });
  return {
    rows: out,
    declaradas: usos.reduce((a, b) => a + b, 0),
    porItem: itens.map((it, i) => ({ campo: it.campo, motivo: it.motivo, silenciou: usos[i] })),
    semEfeito: itens.filter((_, i) => usos[i] === 0).map((it) => it.campo),
  };
}

/**
 * Mapa de papéis MEDIDO e validado em 2026-08-28 (fato datado — não é lei).
 *
 * O seletor difere por lado; o PAPEL é o mesmo. Isto é ponto de partida pra colar, não verdade
 * perene: seletor muda com o DOM. Antes de ler qualquer veredito, confira que o papel casou
 * elemento visível dos DOIS lados — a sonda reporta `presenca` como SEM-DADO quando não casa,
 * justamente pra "0 divergências" não poder significar "0 elementos medidos".
 */
export const SB_ROLES_SUGERIDO = {
  design: {
    atalhoTopo: '.sb-menu > .sb-item',
    itemGrupo: '.sb-group .sb-item, .sb-group-body .sb-item',
    grupoHeader: '.sb-group-h',
    containerMenu: '.sb-menu',
  },
  prod: {
    atalhoTopo: '.sb-shortcuts .sb-shortcut',
    itemGrupo: '.sb-item',
    grupoHeader: '.sb-group-h',
    containerMenu: '.sb-menu-grouped',
  },
};

const SEM_DADO_SHELL = (campo, prod, design, detalhe) => ({ dim: 'SHELL', campo, prod, design, veredito: 'SEM-DADO', detalhe });
const A_CLASSIFICAR = (campo, prod, design, detalhe) => ({ dim: 'SHELL', campo, prod, design, veredito: 'DIVERGE (a classificar)', detalhe });

/** @param {any} prod @param {any} design */
function dimShell(prod, design) {
  const P = prod.shell, D = design.shell;
  if (!P || !D) {
    return [SEM_DADO_SHELL('shell', P ? 'ok' : 'não medido', D ? 'ok' : 'não medido',
      'declare window.__SB_ROLES nos DOIS lados — node prototipo-ui/design-diff.mjs --shell-roles imprime o mapa')];
  }
  const rows = [];
  const pp = P.papeis || {}, dp = D.papeis || {};
  const papeis = [...new Set([...Object.keys(pp), ...Object.keys(dp)])].sort();
  if (!papeis.length) rows.push(SEM_DADO_SHELL('shell.papeis', 'vazio', 'vazio', '__SB_ROLES não declarou papel nenhum'));

  for (const papel of papeis) {
    const a = pp[papel], b = dp[papel];
    const cp = 'shell.' + papel;
    if (!a || !b) {
      rows.push(SEM_DADO_SHELL(cp, a ? 'presente' : 'ausente', b ? 'presente' : 'ausente', 'papel declarado só de um lado'));
      continue;
    }
    // Seletor que não casa elemento visível NÃO pode virar "igual": zero medido lido como zero
    // divergente é o mesmo defeito de "0 failed" numa suíte que não rodou.
    if (!a.n || !b.n) {
      rows.push(SEM_DADO_SHELL(cp + '.presenca', `${a.n} el (${a.seletor})`, `${b.n} el (${b.seletor})`,
        'o seletor não casou elemento VISÍVEL deste lado — conserte o mapa de papéis antes de ler o resto'));
      continue;
    }
    if (a.n !== b.n) {
      rows.push(A_CLASSIFICAR(cp + '.n', a.n, b.n, 'quantidade de elementos do papel'));
    }
    const chaves = [...new Set([...Object.keys(a.css || {}), ...Object.keys(b.css || {})])];
    for (const k of chaves) {
      const va = (a.css || {})[k], vb = (b.css || {})[k];
      if (va === undefined || vb === undefined) {
        rows.push(SEM_DADO_SHELL(`${cp}.${k}`, va === undefined ? 'não medido' : va, vb === undefined ? 'não medido' : vb,
          'campo só existe num dos snapshots — re-injete a sonda atual (--probe) nos DOIS lados'));
        continue;
      }
      const det = divergenciaShell(k, va, vb);
      if (det) rows.push(A_CLASSIFICAR(`${cp}.${k}`, va, vb, det));
    }
    // Snapshot é DADO EXTERNO: `caixa` pode vir null (papel sem elemento, sonda antiga, JSON
    // editado à mão). Ler `a.caixa[eixo]` direto derrubava o comparador inteiro com TypeError —
    // pego pelo teste de mutação de 2026-08-28, não por revisão. Instrumento não morre lendo
    // dado incompleto: ele diz que não mediu.
    for (const eixo of ['w', 'h']) {
      const va = a.caixa ? a.caixa[eixo] : undefined;
      const vb = b.caixa ? b.caixa[eixo] : undefined;
      const det = divergenciaShell('caixa.' + eixo, va, vb);
      if (det) rows.push(A_CLASSIFICAR(`${cp}.caixa.${eixo}`, va === undefined ? 'não medido' : va, vb === undefined ? 'não medido' : vb, det));
    }
    if (!!a.icone !== !!b.icone) {
      rows.push(A_CLASSIFICAR(cp + '.icone', a.icone ? 'com svg' : 'sem svg', b.icone ? 'com svg' : 'sem svg', 'presença do ícone'));
    } else if (a.icone && b.icone) {
      for (const eixo of ['w', 'h', 'strokeWidth']) {
        const det = divergenciaShell('icone.' + eixo, a.icone[eixo], b.icone[eixo]);
        if (det) rows.push(A_CLASSIFICAR(`${cp}.icone.${eixo}`, a.icone[eixo], b.icone[eixo], det));
      }
    }
  }

  // SEQUÊNCIA dos atalhos de topo. Ordem de navegação é contrato — e é o único sinal do shell
  // que computed-style nenhum alcança: dois sidebars com cada pixel idêntico podem ter a ordem
  // trocada, e o diff de estilo diria IGUAL.
  const pa = P.atalhos, da = D.atalhos;
  if (!Array.isArray(pa) || !Array.isArray(da)) {
    rows.push(SEM_DADO_SHELL('shell.atalhoTopo.sequencia', Array.isArray(pa) ? 'ok' : 'não medido', Array.isArray(da) ? 'ok' : 'não medido',
      'declare o papel `atalhoTopo` nos dois lados pra medir a ordem'));
  } else {
    const rp = pa.map((x) => x.rotulo), rd = da.map((x) => x.rotulo);
    if (rp.join('|') !== rd.join('|')) {
      rows.push(A_CLASSIFICAR('shell.atalhoTopo.sequencia', '[' + rp.join(', ') + ']', '[' + rd.join(', ') + ']',
        'ordem de navegação é contrato — classifique (DECIDIDA/DERIVA/DESIGN-ANDOU) antes de mexer'));
      const soProd = rp.filter((x) => !rd.includes(x));
      const soDesign = rd.filter((x) => !rp.includes(x));
      if (soProd.length) rows.push(A_CLASSIFICAR('shell.atalhoTopo.so-na-prod', soProd.join(', '), '—', 'candidato típico a DECIDIDA — se for, declare em --declarado'));
      if (soDesign.length) rows.push(A_CLASSIFICAR('shell.atalhoTopo.so-no-design', '—', soDesign.join(', '), 'candidato típico a DESIGN-ANDOU — confira a data das duas fontes'));
      if (!soProd.length && !soDesign.length) rows.push(A_CLASSIFICAR('shell.atalhoTopo.ordem', rp.join(' > '), rd.join(' > '), 'mesmo conjunto, ordem diferente'));
    }
    // `aria-current` depende de QUAL tela está aberta em cada lado — é dado, não design.
    // Sinal pro humano, nunca veredito duro (mesma regra de `blocos` e `estado de linha`).
    const atual = (l) => (l.find((x) => x.atual) || {}).rotulo || null;
    if (atual(pa) !== atual(da)) {
      rows.push({ dim: 'SHELL', campo: 'shell.atalhoTopo.aria-current', prod: atual(pa) || 'nenhum', design: atual(da) || 'nenhum',
        veredito: 'DIVERGE (dado?)', detalhe: 'depende de qual tela está aberta em cada lado — confira antes de tratar como defeito' });
    }
  }
  if (!rows.length) rows.push({ dim: 'SHELL', campo: 'shell', prod: 'ok', design: 'ok', veredito: 'IGUAL' });
  return rows;
}

const DIMENSIONS = [dimLayout, dimTipografia, dimCor, dimAlinhamento, dimTexto, dimCelulas, dimShell];

/**
 * @param {any} prodSnap @param {any} designSnap
 * @param {{declarado?: {campo:string,motivo:string}[]}} [opts]
 *
 * `bugs` conta SÓ `DIVERGE (bug)` — o shell NÃO entra ali de propósito: escopo novo não
 * avermelha o que já existia (`--check` histórico segue com o mesmo significado). A contagem
 * do shell sai separada, em `shell`, e quem quiser exit≠0 nela usa `--check-shell`.
 */
export function compare(prodSnap, designSnap, opts = {}) {
  const prod = { ...prodSnap.roles, __theme: prodSnap.theme };
  const design = { ...designSnap.roles, __theme: designSnap.theme };
  const brutas = DIMENSIONS.flatMap((fn) => fn(prod, design));
  const decl = aplicarDeclaracao(brutas, opts.declarado || []);
  const rows = decl.rows;
  const bugs = rows.filter((r) => r.veredito === 'DIVERGE (bug)');
  const shell = rows.filter((r) => r.dim === 'SHELL' && r.veredito === 'DIVERGE (a classificar)');
  return {
    rows,
    bugs: bugs.length,
    shell: shell.length,
    declaradas: decl.declaradas,
    porDeclaracao: decl.porItem,
    declaracoesSemEfeito: decl.semEfeito,
    sameTheme: prodSnap.theme === designSnap.theme,
  };
}

/* ─────────────────────────────────────────────────────────────────────────────
 * CLI
 * ─────────────────────────────────────────────────────────────────────────── */
function fmt(rows) {
  return rows.map((r) => {
    // `a classificar` tem marca PRÓPRIA (▲) e não reusa a de bug (✗): o relatório não pode
    // sugerir culpa que a medição não estabelece. `declarada` (▫) é divergência já classificada
    // por humano — some do contador, mas continua VISÍVEL na listagem, nunca apagada.
    const mark = r.veredito === 'IGUAL' ? '✓'
      : r.veredito === 'SEM-DADO' ? '⬜'
      : r.veredito === 'DIVERGE (a classificar)' ? '▲'
      : r.veredito === 'DIVERGE (declarada)' ? '▫'
      : /tema|dado?|impl/.test(r.veredito) ? '🟡' : '✗';
    return `  ${mark} [${r.dim}] ${r.campo}: prod=${r.prod} · design=${r.design} → ${r.veredito}${r.detalhe ? ' (' + r.detalhe + ')' : ''}`;
  }).join('\n');
}

/**
 * O lado "design" veio de um render do ESPELHO LOCAL?
 *
 * O probe grava `url: location.href`. Espelho local roda por `file:` ou por um
 * http server em localhost; prod é https num domínio real. Só o caso local
 * depende da fidelidade do espelho — comparar prod×prod (dois ambientes) não
 * depende, e por isso não é gateado.
 */
export function ehEspelhoLocal(url) {
  return /^(?:file:|https?:\/\/(?:localhost|127\.0\.0\.1|\[::1\])(?::\d+)?(?:[/?#]|$))/i.test(String(url || ''));
}

/**
 * A última rodada de frescor cobriu o espelho INTEIRO?
 *
 * `sync > 0` sozinho não basta — é a armadilha que o `--sla` já nomeia: "0 stale"
 * só fala do que foi medido, e o denominador é que conta o resto. Rodada que
 * mediu 1 de 137 não é prova de nada sobre os outros 136.
 *
 * Devolve `{ completa, motivo }` — nunca lança: ledger ausente/ilegível é
 * "não medido", jamais "está tudo bem" (§5 2026-07-29).
 */
export function rodadaDeFrescorCompleta(ledgerPath) {
  let entradas;
  try {
    if (!existsSync(ledgerPath)) return { completa: false, motivo: 'ledger de frescor ausente — nenhuma rodada registrada' };
    entradas = JSON.parse(readFileSync(ledgerPath, 'utf8'));
  } catch (e) {
    return { completa: false, motivo: `ledger de frescor ilegível (${e.message}) — não consegui medir` };
  }
  if (!Array.isArray(entradas) || entradas.length === 0) return { completa: false, motivo: 'ledger de frescor vazio' };

  const u = entradas[entradas.length - 1];
  const total = Number(u.files || 0);
  const semVeredito = Number(u.unchecked || 0);
  if (!total) return { completa: false, motivo: 'última rodada não declara denominador (`files`)' };
  if (semVeredito > 0) {
    return {
      completa: false,
      motivo: `última rodada é PARCIAL (${u.date}): ${total - semVeredito}/${total} medidos · ${semVeredito} sem veredito`,
    };
  }
  if (Number(u.stale || 0) > 0) {
    return { completa: false, motivo: `última rodada acusou ${u.stale} arquivo(s) STALE em ${u.date}` };
  }
  return { completa: true, motivo: `rodada completa em ${u.date} (${total} arquivos)` };
}

/**
 * `--declarado <arquivo>` — o valor NÃO começa com `--`, então cairia no filtro de arquivos
 * posicionais e seria lido como snapshot. Extrai-se o par antes de qualquer outra leitura.
 */
function extrairDeclarado(argv) {
  const i = argv.indexOf('--declarado');
  if (i < 0) return { itens: [], argvLimpo: argv };
  const caminho = argv[i + 1];
  if (!caminho || caminho.startsWith('--')) { console.error('uso: --declarado <arquivo.json>'); process.exit(2); }
  let itens;
  try {
    itens = validarDeclaracao(JSON.parse(readFileSync(caminho, 'utf8')));
  } catch (e) {
    console.error(`\n  ⛔ declaração inválida (${caminho}): ${e.message}\n`);
    process.exit(2);
  }
  return { itens, argvLimpo: argv.filter((_, k) => k !== i && k !== i + 1) };
}

function runCompare(argvBruto) {
  const { itens: declarado, argvLimpo: argv } = extrairDeclarado(argvBruto);
  const files = argv.filter((a) => !a.startsWith('--'));
  if (files.length < 2) { console.error('uso: --compare <prod.json> <design.json>'); process.exit(2); }
  const prodSnap = JSON.parse(readFileSync(files[0], 'utf8'));
  const designSnap = JSON.parse(readFileSync(files[1], 'utf8'));
  const res = compare(prodSnap, designSnap, { declarado });
  if (argv.includes('--json')) { console.log(JSON.stringify(res, null, 2)); }
  else {
    console.log(`\n  DESIGN-DIFF — prod(${prodSnap.theme}) × design(${designSnap.theme})${res.sameTheme ? '' : '  ⚠ TEMAS DIFERENTES — compare no mesmo tema (regra do protocolo)'}\n`);
    console.log(fmt(res.rows));
    console.log(`\n  ✗ DIVERGE(bug): ${res.bugs}`);
    console.log(`  ▲ SHELL a classificar: ${res.shell}${res.declaradas ? `   ▫ declaradas: ${res.declaradas}` : ''}`);
    if (res.shell > 0) {
      console.log(
        '\n  As 3 categorias de divergência de SHELL — a máquina NÃO as distingue, quem classifica é humano:' +
        '\n    DECIDIDA      alguém escolheu (ex.: a prod ter um atalho que o design não tem)' +
        '\n    DERIVA        ninguém escolheu — foi acontecendo' +
        '\n    DESIGN-ANDOU  o design mudou e a prod ficou na versão anterior' +
        '\n  O que for DECIDIDA vira entrada em --declarado <arquivo.json>: { "itens": [ { "campo": "...", "motivo": "..." } ] }',
      );
    }
    for (const d of res.porDeclaracao) {
      if (d.silenciou > 0) console.log(`  ▫ declarado ${d.campo} → silenciou ${d.silenciou} linha(s): ${d.motivo}`);
    }
    if (res.declaracoesSemEfeito.length) {
      console.log(`\n  ⚠ declaração SEM EFEITO (não casou nada — campo errado, ou a divergência já sumiu): ${res.declaracoesSemEfeito.join(', ')}`);
    }
    console.log('');
  }
  if (argv.includes('--check') && res.bugs > 0) process.exit(1);

  // ── NÃO MEDI ≠ MEDI E BATEU (auditoria adversarial 2026-08-28) ────────────
  // `res.shell` conta só `DIVERGE (a classificar)`; `SEM-DADO` não entrava em
  // contador nenhum, então shell ausente saía **exit 0** — o código que um
  // script lê como "pode seguir". E esse era o caminho DEFAULT: nenhum dos três
  // donos do processo (skill `comparar-design-prod`, PROTOCOLO-COMPARACAO-RUNTIME,
  // hook `design-compare-protocol`) ensinava a declarar `__SB_ROLES`, então quem
  // seguisse o protocolo injetava a sonda sem ele → `shell: null` → verde tendo
  // medido NADA. Zero medido lido como zero divergente é o mesmo defeito de
  // "0 failed" numa suíte que não rodou.
  // Sai 2 (não 1) pelo mesmo vocabulário do guarda de proveniência logo abaixo:
  // 1 = medi e divergiu · 2 = não consegui medir.
  if (argv.includes('--check-shell')) {
    const semDadoShell = res.rows.filter((r) => r.dim === 'SHELL' && r.veredito === 'SEM-DADO');
    if (semDadoShell.length) {
      console.error(
        `\n  ⛔ NÃO MEDI — ${semDadoShell.length} linha(s) de shell sem dado. "0 divergências" aqui NÃO significa "igual".` +
        `\n     Declare window.__SB_ROLES nos DOIS lados antes de injetar a sonda:` +
        `\n       node prototipo-ui/design-diff.mjs --shell-roles\n`);
      process.exit(2);
    }
    if (res.shell > 0) process.exit(1);
  }

  // ── PROVENIÊNCIA: "igual" exige saber DE ONDE veio o lado design ───────────
  // Sem isto o `--check` saía 0 (= igual) mesmo com o design vindo de espelho
  // que ninguém verificou — e 0 é o código que um script lê como "pode seguir".
  // Aconteceu em 2026-08-23/24: o espelho tinha `app.jsx` 18k chars atrás do
  // vivo (stub em vez da tela) e a comparação teria carimbado conformidade
  // contra uma fonte velha. Aqui o veredito passa a sair 2 = NÃO MEDI, que é o
  // mesmo vocabulário do `cowork-mirror-freshness` (0 sync · 1 stale · 2 não-medi).
  //
  // Só morde quando o design veio do ESPELHO LOCAL. prod×prod (dois ambientes)
  // não depende da fidelidade do espelho e segue livre — por isso zero FP.
  if ((argv.includes('--check') || argv.includes('--check-shell')) && ehEspelhoLocal(designSnap.url)) {
    const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..');
    const { completa, motivo } = rodadaDeFrescorCompleta(join(ROOT, LEDGER_FRESCOR_REL));
    if (!completa) {
      console.error(
        `\n  ⛔ NÃO MEDI — o lado design veio do espelho local (${designSnap.url}) e a fidelidade dele não está provada.` +
        `\n     ${motivo}` +
        `\n\n  "0 divergências" aqui não significa "igual ao design": significa "igual a uma cópia de frescor desconhecido".` +
        `\n  Feche a rodada antes de concluir:` +
        `\n     node scripts/governance/cowork-mirror-freshness.mjs --ledger        # o que falta buscar` +
        `\n     node scripts/governance/cowork-mirror-freshness.mjs --compare <snap.json> --check\n`,
      );
      process.exit(2);
    }
  }
}

function selftest() {
  // FIXTURE HERMÉTICO — reproduz o incidente 2026-07-07 (center×left) + dark-mode.
  const prod = { theme: 'dark', roles: {
    kpi: { count: 5, tag: 'BUTTON', overflowX: true, items: Array(5).fill(0).map((_, i) => ({ label: 'kpi' + i, textAlign: 'center', alignItems: 'normal', textColor: 'oklch(0.374 0.01 67)', smallAlign: 'center', valueFontPx: 26 })) },
    title: { fontPx: 22, weight: '600', color: 'oklch(0.984 0 0)' },
    primary: { bg: 'oklch(0.55 0.15 295)', color: 'oklch(0.99 0 0)', border: 'oklch(0.45 0.15 295)' },
    filterRows: 1,
  } };
  const design = { theme: 'dark', roles: {
    kpi: { count: 5, tag: 'DIV', overflowX: false, items: Array(5).fill(0).map((_, i) => ({ label: 'kpi' + i, textAlign: 'start', alignItems: 'normal', textColor: 'oklch(0.965 0 0)', smallAlign: 'start', valueFontPx: 22 })) },
    title: { fontPx: 22, weight: '600', color: 'oklch(0.965 0 0)' },
    primary: { bg: 'oklch(0.72 0.15 295)', color: 'oklch(0.99 0 0)', border: 'oklch(0.62 0.15 295)' },
    filterRows: 2,
  } };
  const res = compare(prod, design);
  const has = (dim, campo) => res.rows.some((r) => r.dim === dim && r.campo.includes(campo) && r.veredito.startsWith('DIVERGE'));
  const checks = [
    ['D8 pega center×left (o erro de 07/07)', has('D8', 'text-align')],
    ['D8 pega button×div (a causa)', has('D8', 'tag')],
    ['D2 pega overflow-x (A PAGAR cortado)', has('D2', 'overflowX')],
    ['D2 pega filtro 1×2 linhas', has('D2', 'filtro')],
    ['D6 pega roxo escuro×roxinho (lightness)', has('D6', 'lightness')],
    ['D6 pega texto KPI escuro no dark', has('D6', 'kpi texto')],
    ['D4 pega VALOR do KPI (campo que a sonda media e o compare ignorava)', has('D4', 'kpi valor')],
    ['--check sairia 1 (tem bug)', res.bugs > 0],
  ];
  // controle: dois lados IGUAIS não acusam bug
  const eq = compare(design, design);
  checks.push(['design×design = 0 bug (não mente)', eq.bugs === 0]);

  // ── D9 TEXTO (2026-08-26) — fixture do caso Arquivos/Index ───────────────────
  // Números do fixture = os MEDIDOS na sonda injetada no protótipo vivo naquele dia
  // (acervo: dataBr 10 · dataIso 0 · snakeCru 0 · retencao-regras: snakeCru 1 hard_delete).
  // Fixture próprio (NÃO reusa `base()`, que só é declarado adiante — const em TDZ).
  // As outras 4 dimensões ficam idênticas nos dois lados de propósito: assim qualquer
  // bug que o selftest acusar aqui é da D9, não contaminação de outra dimensão.
  const comTexto = (contratos) => ({ theme: 'dark', roles: {
    kpi: { count: 1, tag: 'DIV', overflowX: false, items: [{ label: 'k', textAlign: 'start', alignItems: 'normal', textColor: 'oklch(0.965 0 0)', smallAlign: 'start', valueFontPx: 22 }] },
    title: { fontPx: 22, weight: '600', color: 'oklch(0.965 0 0)' },
    primary: { bg: 'oklch(0.55 0.15 295)', color: 'oklch(0.99 0 0)', border: 'oklch(0.45 0.15 295)' },
    filterRows: 1,
    contratos,
  } });
  const d9prod = comTexto([
    { nome: 'acervo', classes: { dataIso: 10, dataBr: 0, snakeCru: 0 }, exemplos: { dataIso: ['2026-09-07'] } },
    { nome: 'retencao-regras', classes: { dataIso: 0, dataBr: 0, snakeCru: 1 }, exemplos: { snakeCru: ['hard_delete'] } },
    { nome: 'trilha', classes: { dataIso: 4, dataBr: 0, snakeCru: 0 }, exemplos: { dataIso: ['2026-06-09'] } },
  ]);
  const d9design = comTexto([
    { nome: 'acervo', classes: { dataIso: 0, dataBr: 10, snakeCru: 0 }, exemplos: { dataBr: ['22/08/2031'] } },
    { nome: 'retencao-regras', classes: { dataIso: 0, dataBr: 0, snakeCru: 1 }, exemplos: { snakeCru: ['hard_delete'] } },
    { nome: 'trilha', classes: { dataIso: 0, dataBr: 4, snakeCru: 0 }, exemplos: { dataBr: ['09/06/2026'] } },
  ]);
  const r9 = compare(d9prod, d9design);
  const tem9 = (campo, ver) => r9.rows.some((r) => r.dim === 'D9' && r.campo.includes(campo) && r.veredito === ver);
  checks.push(['D9 pega data ISO na prod onde o design tem dd/mm/aaaa', tem9('acervo.dataIso', 'DIVERGE (bug)')]);
  checks.push(['D9 pega o mesmo par pela regra formato-de-data', tem9('acervo.formato-de-data', 'DIVERGE (bug)')]);
  checks.push(['D9 pega a Trilha (o segundo ISO do relatório)', tem9('trilha.dataIso', 'DIVERGE (bug)')]);
  // O `hard_delete` está nos DOIS lados → não é regressão da travessia. Acusar como bug
  // mandaria consertar a tela, quando quem carrega a forma crua é o protótipo.
  checks.push(['D9 NÃO chama de bug o que os dois lados têm', !tem9('retencao-regras.snakeCru', 'DIVERGE (bug)')]);
  checks.push(['D9 controle: prod×prod não acusa nada', compare(d9prod, d9prod).rows.filter((r) => r.dim === 'D9' && r.veredito.startsWith('DIVERGE')).length === 0]);
  // direção: crua SÓ no design = defeito da FONTE, e o veredito tem que dizer isso.
  const soNaFonte = compare(
    comTexto([{ nome: 'x', classes: { dataIso: 0, dataBr: 0, snakeCru: 0 }, exemplos: {} }]),
    comTexto([{ nome: 'x', classes: { dataIso: 0, dataBr: 0, snakeCru: 2 }, exemplos: { snakeCru: ['hard_delete'] } }]),
  );
  checks.push(['D9 separa "fonte errada" de "prod errada"', soNaFonte.rows.some((r) => r.veredito === 'DIVERGE (fonte)') && soNaFonte.bugs === 0]);
  // snapshot velho (sem a sonda nova) → SEM-DADO, nunca verde por omissão.
  const semSonda = comTexto([]);
  delete semSonda.roles.contratos;
  const velho = compare(semSonda, semSonda);
  checks.push(['D9 com sonda antiga sai SEM-DADO, não IGUAL', velho.rows.some((r) => r.dim === 'D9' && r.veredito === 'SEM-DADO')]);

  // ── CÉLULA DA TABELA (2026-08-27) — o caso que [W] viu na Arquivos/Index ────────
  // O lado DESIGN são os valores MEDIDOS na sonda injetada no protótipo vivo naquele dia
  // (7 colunas; FP 0/7 depois de trocar a detecção de pílula por classe pela detecção por
  // forma + corte de controle). O lado PROD reproduz o que a tela renderiza — é FIXTURE,
  // construído, e não uma medição de produção: o objetivo aqui é pinar a LÓGICA do
  // comparador, não afirmar o estado da prod.
  const comCelulas = (celulas) => ({ theme: 'dark', roles: { ...comTexto([]).roles, celulas } });
  const designCel = comCelulas([
    { col: 0, tag: null, mono: true, corPropria: true, blocos: 5, pill: null },
    { col: 1, tag: 'BUTTON', mono: true, corPropria: true, blocos: 2, pill: null },
    { col: 2, tag: null, mono: true, corPropria: true, blocos: 2, pill: { borda: true, dot: true } },
  ]);
  const prodCel = comCelulas([
    { col: 0, tag: null, mono: true, corPropria: true, blocos: 5, pill: null },
    // "Vinculado a": virou <span> cru — sem link, sem mono, sem cor, sem sub-linha.
    { col: 1, tag: null, mono: false, corPropria: false, blocos: 1, pill: null },
    // "Classificação": tem pílula, mas sem dot e com a sub-linha fora de mono.
    { col: 2, tag: null, mono: false, corPropria: true, blocos: 2, pill: { borda: true, dot: false } },
  ]);
  const rc = compare(prodCel, designCel);
  const temC = (campo, dim) => rc.rows.some((r) => r.dim === dim && r.campo.includes(campo) && r.veredito === 'DIVERGE (bug)');
  checks.push(['célula: pega o link que virou texto morto (D8)', temC('col1.alcancavel', 'D8')]);
  // A e BUTTON sao as duas formas legitimas de alcancar: diferenca de tag NAO e bug.
  const doisAlcancaveis = compare(
    comCelulas([{ col: 0, tag: 'A', mono: true, corPropria: true, blocos: 2, pill: null }]),
    comCelulas([{ col: 0, tag: 'BUTTON', mono: true, corPropria: true, blocos: 2, pill: null }]),
  );
  checks.push(['célula: A x BUTTON (os dois alcançáveis) NÃO é bug', doisAlcancaveis.bugs === 0 && doisAlcancaveis.rows.some((r) => r.veredito === 'DIVERGE (impl)')]);
  checks.push(['célula: pega a sub-linha em mono que sumiu (D4)', temC('col1.mono', 'D4')]);
  checks.push(['célula: pega a cor que a prod deixou de pintar (D6)', temC('col1.cor', 'D6')]);
  const temV = (campo, ver) => rc.rows.some((r) => r.campo.includes(campo) && r.veredito === ver);
  checks.push(['célula: aponta a sub-linha sumida como SINAL, não como bug', temV('col1.blocos', 'DIVERGE (dado?)')]);
  // O blocos NAO pode emitir veredito duro: ele nao distingue conteudo perdido de dado diferente.
  checks.push(['célula: blocos NUNCA vira DIVERGE (bug)', !rc.rows.some((r) => r.campo.includes('blocos') && r.veredito === 'DIVERGE (bug)')]);
  checks.push(['célula: pega a pílula que perdeu o dot (D6)', temC('col2.pílula.dot', 'D6')]);
  // Rotula com a dimensão CANÔNICA do protocolo — nada de número novo.
  // (O escopo SHELL fica FORA deste universo de propósito: ele não é dimensão do protocolo,
  //  é outro objeto medido — o shell, não a tela. A intenção do check é intacta: as linhas de
  //  CÉLULA continuam obrigadas a falar D2/D4/D6/D8/D9.)
  checks.push(['célula: usa D2/D4/D6/D8, não inventa dimensão', rc.rows.filter((r) => r.dim !== 'SHELL').every((r) => ['D2', 'D4', 'D6', 'D8', 'D9'].includes(r.dim))]);
  // Controles negativos: iguais não acusam, e coluna intocada (col0) fica fora do relatório.
  checks.push(['célula CONTROLE: design×design = 0 divergência', compare(designCel, designCel).rows.filter((r) => /^col/.test(r.campo) && r.veredito.startsWith('DIVERGE')).length === 0]);
  checks.push(['célula CONTROLE: prod×prod = 0 divergência', compare(prodCel, prodCel).rows.filter((r) => /^col/.test(r.campo) && r.veredito.startsWith('DIVERGE')).length === 0]);
  checks.push(['célula CONTROLE: coluna idêntica (col0) não vira linha', !rc.rows.some((r) => r.campo.startsWith('col0'))]);
  // Sem `tableRow` nos dois lados → SEM-DADO, nunca IGUAL por omissão.
  const semCel = comTexto([]);
  checks.push(['célula sem tableRow sai SEM-DADO, não IGUAL', compare(semCel, semCel).rows.some((r) => r.campo === 'linha da tabela' && r.veredito === 'SEM-DADO')]);

  // ── GEOMETRIA (2026-08-27, 2ª leva) — largura, alinhamento e estado de linha ────
  // O caso: o protótipo da Arquivos declara 6 larguras fixas + `align:"right"` + `mono` na
  // coluna Tamanho e `rows[].state` (urgent/archived). Do trio, a sonda só via `mono` — e foi
  // o único que chegou na produção. Estes fixtures pinam que os outros dois agora mordem.
  const comGeo = (celulas, tabela) => ({ theme: 'dark', roles: { ...comTexto([]).roles, celulas, tabela } });
  const geoDesign = comGeo(
    [
      { col: 0, tag: null, align: 'left', larguraPct: 38.5, mono: false, corPropria: true, blocos: 2, pill: null },
      { col: 1, tag: null, align: 'right', larguraPct: 8.2, mono: true, corPropria: false, blocos: 1, pill: null },
    ],
    { tableLayout: 'fixed', colunasDeclaradas: 6, linhas: 8, linhasComEstado: 3 },
  );
  const geoProd = comGeo(
    [
      { col: 0, tag: null, align: 'left', larguraPct: 52.1, mono: false, corPropria: true, blocos: 2, pill: null },
      // Tamanho: `text-right` foi parar num <span> DENTRO da célula — o <td> segue `left`.
      { col: 1, tag: null, align: 'left', larguraPct: 6.0, mono: true, corPropria: false, blocos: 1, pill: null },
    ],
    { tableLayout: 'auto', colunasDeclaradas: 0, linhas: 8, linhasComEstado: 0 },
  );
  const rg = compare(geoProd, geoDesign);
  const temG = (campo, ver) => rg.rows.some((r) => r.campo.includes(campo) && r.veredito === ver);
  checks.push(['geometria: pega o table-layout auto contra fixed (D2)', temG('table-layout', 'DIVERGE (bug)')]);
  checks.push(['geometria: pega as larguras que ninguém declarou (D2)', temG('colunas com largura declarada', 'DIVERGE (bug)')]);
  checks.push(['geometria: pega o align que ficou no filho, não na célula (D8)', temG('col1.align', 'DIVERGE (bug)')]);
  checks.push(['geometria: estado de linha sai como SINAL, nunca bug', temG('estado de linha', 'DIVERGE (dado?)') && !rg.rows.some((r) => r.campo.includes('estado de linha') && r.veredito === 'DIVERGE (bug)')]);
  // A largura em si NÃO emite veredito: em `auto` ela é consequência do conteúdo, e acusá-la
  // seria acusar o dado. Ela viaja no payload como número pro humano ler. Se alguém "melhorar"
  // isto emitindo bug por largura, este check cai — de propósito.
  // (o campo "colunas com largura declarada" É de tabela e TEM veredito — o que não pode ter
  //  é a largura POR COLUNA, `colN.largura*`, que é onde o dado mandaria no resultado.)
  checks.push(['geometria: largura por coluna viaja como dado, sem veredito próprio', !rg.rows.some((r) => /^col\d+\.largura/i.test(r.campo))]);
  // CONTROLES NEGATIVOS — sem estímulo, silêncio. Senão a régua mede ruído.
  checks.push(['geometria CONTROLE: design×design = 0 divergência', compare(geoDesign, geoDesign).rows.filter((r) => r.veredito.startsWith('DIVERGE')).length === 0]);
  checks.push(['geometria CONTROLE: prod×prod = 0 divergência', compare(geoProd, geoProd).rows.filter((r) => r.veredito.startsWith('DIVERGE')).length === 0]);
  // Snapshot velho (sonda da 1ª leva, sem `tabela`) → SEM-DADO, nunca verde por omissão.
  checks.push(['geometria com sonda antiga sai SEM-DADO, não IGUAL', compare(prodCel, designCel).rows.some((r) => r.campo === 'geometria da tabela' && r.veredito === 'SEM-DADO')]);

  // ── FRONTEIRA das bandas declaradas (chip G8, 2026-08-14) ────────────────────
  // As bandas destas dimensões vêm de TOLERANCIAS (style-fingerprint.mjs). Sem um par
  // abaixo/acima, o número é decorativo: aqui cada eixo prova que absorve o que deve absorver
  // e morde no primeiro passo além. Fixture MÍNIMO (1 KPI alinhado, mesmo tema) pra isolar o eixo.
  const base = (over = {}) => ({ theme: 'dark', roles: {
    kpi: { count: 1, tag: 'DIV', overflowX: false, items: [{ label: 'k', textAlign: 'start', alignItems: 'normal', textColor: 'oklch(0.965 0 0)', smallAlign: 'start', valueFontPx: 22 }] },
    title: { fontPx: 22, weight: '600', color: 'oklch(0.965 0 0)' },
    primary: { bg: 'oklch(0.55 0.15 295)', color: 'oklch(0.99 0 0)', border: 'oklch(0.45 0.15 295)' },
    filterRows: 1,
    ...over,
  } });
  const titulo = (px) => base({ title: { fontPx: px, weight: '600', color: 'oklch(0.965 0 0)' } });
  const accent = (bg) => base({ primary: { bg, color: 'oklch(0.99 0 0)', border: 'oklch(0.45 0.15 295)' } });
  const acusa = (a, b, campo) => compare(a, b).rows.some((r) => r.campo.includes(campo) && r.veredito.startsWith('DIVERGE'));
  const T = TOLERANCIAS;
  checks.push(
    // controle NEGATIVO do fixture mínimo: sem estímulo, zero bug (senão a fronteira mediria ruído)
    ['fronteira: fixture mínimo × ele mesmo = 0 bug', compare(base(), base()).bugs === 0],
    // D4 título — banda 1px (o artefato máximo do Math.round da própria sonda)
    [`fronteira D4: título Δ${T.tituloPx.valor}px (na banda) → não acusa`, !acusa(titulo(22), titulo(22 + T.tituloPx.valor), 'título')],
    ['fronteira D4: título Δ2px → acusa', acusa(titulo(22), titulo(24), 'título')],
    // D6 matiz — banda 8°
    [`fronteira D6: matiz Δ${T.matiz.valor}° (na banda) → não acusa`, !acusa(accent('oklch(0.55 0.15 295)'), accent(`oklch(0.55 0.15 ${295 + T.matiz.valor})`), 'hue')],
    ['fronteira D6: matiz Δ9° → acusa', acusa(accent('oklch(0.55 0.15 295)'), accent('oklch(0.55 0.15 304)'), 'hue')],
    // D6 luminância — banda 0.1 (sondada em 0.09/0.11: a fronteira nominal cai no binário)
    ['fronteira D6: luminância Δ0.09 (na banda) → não acusa', !acusa(accent('oklch(0.55 0.15 295)'), accent('oklch(0.64 0.15 295)'), 'lightness')],
    ['fronteira D6: luminância Δ0.11 → acusa', acusa(accent('oklch(0.55 0.15 295)'), accent('oklch(0.66 0.15 295)'), 'lightness')],
  );

  // ── PROVENIÊNCIA (2026-08-24) — o guarda tem de MORDER e de LIBERAR ────────
  // Guarda que nunca reprova é carimbo; guarda que reprova sempre trava o certo.
  // Os dois lados abaixo são o bite-test.
  const L = (url) => ehEspelhoLocal(url);
  let seqLedger = 0;
  const tmpLedger = (conteudo) => {
    const p = join(tmpdir(), `dd-ledger-${process.pid}-${++seqLedger}.json`);
    writeFileSync(p, conteudo);
    return p;
  };
  const semLedger = join(tmpdir(), `dd-ledger-inexistente-${process.pid}.json`);
  const parcial = tmpLedger(JSON.stringify([{ date: '2026-08-21T00:00:00Z', files: 137, unchecked: 136, stale: 0 }]));
  const completa = tmpLedger(JSON.stringify([{ date: '2026-08-24T00:00:00Z', files: 137, unchecked: 0, stale: 0 }]));
  const comStale = tmpLedger(JSON.stringify([{ date: '2026-08-24T00:00:00Z', files: 137, unchecked: 0, stale: 3 }]));
  const quebrado = tmpLedger('{ nao é json');

  checks.push(
    // (a) QUEM é espelho local — o gatilho do guarda
    ['proveniência: file: é espelho local', L('file:///C:/x/oimpresso.com.html')],
    ['proveniência: localhost:5588 é espelho local', L('http://localhost:5588/oimpresso.com.html')],
    ['proveniência: 127.0.0.1 é espelho local', L('http://127.0.0.1:8080/x.html')],
    ['CONTROLE: prod https NÃO é espelho local (prod×prod segue livre)', !L('https://oimpresso.com/ponto')],
    ['CONTROLE: domínio que só CONTÉM "localhost" não conta', !L('https://localhost.evil.com/x')],
    // (b) MORDE — rodada que não prova nada não pode virar "igual"
    ['MORDE: rodada PARCIAL (136/137 sem veredito) → não completa', rodadaDeFrescorCompleta(parcial).completa === false],
    ['MORDE: ledger AUSENTE → não completa (ausência ≠ tudo bem)', rodadaDeFrescorCompleta(semLedger).completa === false],
    ['MORDE: ledger ILEGÍVEL → não completa (não medi ≠ sync)', rodadaDeFrescorCompleta(quebrado).completa === false],
    ['MORDE: rodada completa mas com STALE → não completa', rodadaDeFrescorCompleta(comStale).completa === false],
    // (c) LIBERA — senão o guarda trava o caminho certo pra sempre
    ['LIBERA: rodada completa e sem stale → completa', rodadaDeFrescorCompleta(completa).completa === true],
    // (d) o motivo CHEGA a quem lê (verdict mudo é o mesmo defeito de outro jeito)
    ['motivo é específico, não genérico', /PARCIAL/.test(rodadaDeFrescorCompleta(parcial).motivo)],
    // (e) o PATH do ledger é o mesmo que o dono exporta — se um mudar, isto quebra
    ['path do ledger casa o LEDGER_REL do cowork-mirror-freshness',
      LEDGER_FRESCOR_REL === 'scripts/governance/.cowork-freshness-ledger.json'],
  );
  for (const p of [parcial, completa, comStale, quebrado]) { try { rmSync(p, { force: true }); } catch { /* best-effort */ } }

  // ── SHELL / SIDEBAR (2026-08-28) — o escopo que nenhuma máquina media ────────
  // O caso real: o sidebar divergia do design em 19 propriedades e nenhum gate falou. Três
  // saíram por amostragem no olho; as outras 16 só apareceram quando alguém ENUMEROU. O
  // fixture reproduz a FORMA disso — divergência espalhada por muitos eixos ao mesmo tempo,
  // que é exatamente o que a amostragem perde. Os valores são FIXTURE (construídos pra pinar
  // a lógica do comparador), não medição de produção: aqui não se afirma o estado da prod.
  const papelBase = (over = {}) => ({
    seletor: '.x',
    n: over.n != null ? over.n : 1,
    css: {
      height: '36px', minHeight: '0px', padding: '0px 10px', margin: '0px', gap: '10px',
      display: 'flex', alignItems: 'center', fontFamily: 'Inter,sans-serif', fontSize: '13px',
      fontWeight: '500', lineHeight: '20px', letterSpacing: 'normal', textTransform: 'none',
      color: 'rgb(226, 232, 240)', backgroundColor: 'rgba(0, 0, 0, 0)', borderRadius: '8px',
      borderWidth: '0px', borderColor: 'rgb(30, 41, 59)', opacity: '1', width: '220px',
      boxShadow: 'none', textDecorationLine: 'none', ...(over.css || {}),
    },
    caixa: { w: 220, h: 36, ...(over.caixa || {}) },
    icone: over.icone === null ? null : { w: 16, h: 16, strokeWidth: '1.5px', ...(over.icone || {}) },
  });
  const comShell = (shell) => ({ theme: 'dark', roles: { ...comTexto([]).roles, shell } });
  const shellDesign = {
    papeis: {
      atalhoTopo: papelBase(),
      itemGrupo: papelBase(),
      grupoHeader: papelBase({ css: { fontSize: '11px', textTransform: 'uppercase' }, icone: null }),
      containerMenu: papelBase({ icone: null }),
    },
    atalhos: [{ i: 0, rotulo: 'Início', atual: 'page' }, { i: 1, rotulo: 'Jana', atual: null }],
  };
  const shellProd = {
    papeis: {
      atalhoTopo: papelBase({
        css: {
          height: '32px', padding: '0px 8px', gap: '8px', fontSize: '12px', fontWeight: '400',
          color: 'rgb(148, 163, 184)', borderRadius: '6px', letterSpacing: '0.2px',
          textTransform: 'uppercase', boxShadow: '0 1px 2px rgba(0,0,0,.4)', textDecorationLine: 'underline',
        },
        caixa: { w: 200, h: 32 },
        icone: { w: 14, h: 14, strokeWidth: '2px' },
      }),
      itemGrupo: papelBase(),
      grupoHeader: papelBase({ css: { fontSize: '11px', textTransform: 'uppercase' }, icone: null }),
      containerMenu: papelBase({ icone: null }),
    },
    atalhos: [{ i: 0, rotulo: 'Início', atual: 'page' }, { i: 1, rotulo: 'Jana', atual: null }, { i: 2, rotulo: 'Forja', atual: null }],
  };
  const rsh = compare(comShell(shellProd), comShell(shellDesign));
  const linhasShell = (r) => r.rows.filter((x) => x.dim === 'SHELL' && x.veredito === 'DIVERGE (a classificar)');
  const temS = (campo) => linhasShell(rsh).some((r) => r.campo === campo);
  checks.push(
    ['shell: ENUMERA — ≥12 divergências onde a amostragem enxergou 3', linhasShell(rsh).length >= 12],
    ['shell: pega a altura do item', temS('shell.atalhoTopo.height')],
    ['shell: pega a cor do texto', temS('shell.atalhoTopo.color')],
    ['shell: pega tipografia (size + weight + tracking + caixa-alta)',
      temS('shell.atalhoTopo.fontSize') && temS('shell.atalhoTopo.fontWeight')
      && temS('shell.atalhoTopo.letterSpacing') && temS('shell.atalhoTopo.textTransform')],
    ['shell: pega raio, sombra e sublinhado',
      temS('shell.atalhoTopo.borderRadius') && temS('shell.atalhoTopo.boxShadow') && temS('shell.atalhoTopo.textDecorationLine')],
    ['shell: pega espaçamento (padding + gap)', temS('shell.atalhoTopo.padding') && temS('shell.atalhoTopo.gap')],
    ['shell: pega a CAIXA medida (w/h), não só o CSS declarado', temS('shell.atalhoTopo.caixa.w') && temS('shell.atalhoTopo.caixa.h')],
    ['shell: pega o ÍCONE (tamanho + espessura do traço)', temS('shell.atalhoTopo.icone.w') && temS('shell.atalhoTopo.icone.strokeWidth')],
    ['shell: pega a SEQUÊNCIA dos atalhos de topo (ordem é contrato)',
      temS('shell.atalhoTopo.sequencia') && temS('shell.atalhoTopo.so-na-prod')],
    // Sem estímulo, silêncio — senão a régua mede ruído e o relatório vira parede.
    ['shell CONTROLE: papel idêntico (itemGrupo) não vira linha', !linhasShell(rsh).some((r) => r.campo.startsWith('shell.itemGrupo'))],
    ['shell CONTROLE: design×design = 0 divergência', linhasShell(compare(comShell(shellDesign), comShell(shellDesign))).length === 0],
    ['shell CONTROLE: prod×prod = 0 divergência', linhasShell(compare(comShell(shellProd), comShell(shellProd))).length === 0],
    // A máquina REPORTA; quem classifica é humano. Se alguém "promover" isto a bug, cai aqui.
    ['shell: NUNCA emite DIVERGE (bug) — classificar é humano, não medição',
      !rsh.rows.some((r) => r.dim === 'SHELL' && r.veredito === 'DIVERGE (bug)')],
    ['shell: não avermelha o --check histórico (bugs segue contando só a TELA)', rsh.bugs === 0],
    ['shell ausente nos dois lados sai SEM-DADO, não IGUAL',
      compare(comTexto([]), comTexto([])).rows.some((r) => r.dim === 'SHELL' && r.veredito === 'SEM-DADO')],
  );

  // Seletor que não casa elemento: ZERO MEDIDO não pode ser lido como ZERO DIVERGENTE — é o
  // mesmo defeito de "0 failed" numa suíte que não rodou.
  const shellVazio = { papeis: { atalhoTopo: { seletor: '.nao-existe', n: 0, css: null, caixa: null, icone: null } }, atalhos: [] };
  const rvazio = compare(comShell(shellVazio), comShell(shellDesign));
  checks.push(
    ['shell: seletor que não casa sai SEM-DADO (zero medido ≠ zero divergente)',
      rvazio.rows.some((r) => r.campo === 'shell.atalhoTopo.presenca' && r.veredito === 'SEM-DADO')],
    ['shell: papel sem elemento NÃO gera linha de estilo (não compara o que não mediu)',
      !rvazio.rows.some((r) => /^shell\.atalhoTopo\.(height|color|fontSize)$/.test(r.campo))],
    ['shell: papel declarado só de um lado sai SEM-DADO',
      compare(comShell({ papeis: {}, atalhos: [] }), comShell(shellDesign)).rows.some((r) => r.campo === 'shell.atalhoTopo' && r.veredito === 'SEM-DADO')],
  );

  // `aria-current` depende de QUAL tela está aberta em cada lado — é dado, não design.
  const shellOutraPagina = { ...shellDesign, atalhos: [{ i: 0, rotulo: 'Início', atual: null }, { i: 1, rotulo: 'Jana', atual: 'page' }] };
  checks.push(
    ['shell: aria-current sai como SINAL (dado?), nunca "a classificar"',
      compare(comShell(shellOutraPagina), comShell(shellDesign)).rows.some((r) => r.campo === 'shell.atalhoTopo.aria-current' && r.veredito === 'DIVERGE (dado?)')],
    ['shell: mesmo conjunto em ordem diferente é acusado como ordem, não como falta',
      compare(
        comShell({ ...shellDesign, atalhos: [{ i: 0, rotulo: 'Jana', atual: null }, { i: 1, rotulo: 'Início', atual: 'page' }] }),
        comShell(shellDesign),
      ).rows.some((r) => r.campo === 'shell.atalhoTopo.ordem')],
  );

  // BANDA — o dono é `TOLERANCIAS` (style-fingerprint). Aqui só se troca o RÓTULO; se alguém
  // criar banda própria neste arquivo, estes pares caem.
  const dS = divergenciaShell;
  checks.push(
    ['shell banda: 36px × 36.4px (dentro de caixa ±1.5) → não acusa', dS('height', '36px', '36.4px') === null],
    ['shell banda: 36px × 40px → acusa', dS('height', '36px', '40px') !== null],
    ['shell banda: cor igual em NOTAÇÃO diferente não acusa (ΔEOK, não string)',
      dS('color', 'rgb(255,255,255)', 'rgb(255, 255, 255)') === null],
    ['shell banda: cor de fato diferente acusa', dS('color', 'rgb(226, 232, 240)', 'rgb(148, 163, 184)') !== null],
    ['shell: o rótulo impresso é o do SHELL e a banda é a do dono',
      /^minHeight:/.test(String(dS('minHeight', '40px', '48px'))) && /banda caixa/.test(String(dS('minHeight', '40px', '48px')))],
    ['shell: borderWidth de 4 lados é comparado como LISTA (não lê só o 1º número)',
      dS('borderWidth', '0px 0px 0px 0px', '0px 0px 1px 0px') !== null],
    ['shell: valor não-mensurável não é absorvido em silêncio (cai em texto)',
      dS('gap', 'normal', '8px') !== null && dS('gap', 'normal', 'normal') === null],
    // FRONTEIRA do traço de ícone: 1,5px → 2px é o MENOR passo real do lucide. Se alguém
    // remapear este campo pro eixo `borda` (±0,5px), o passo inteiro é engolido e este par cai.
    ['shell: traço de ícone 1.5px → 2px ACUSA (banda de borda engoliria o passo real)',
      dS('icone.strokeWidth', '1.5px', '2px') !== null],
    ['shell CONTROLE: traço de ícone idêntico não acusa', dS('icone.strokeWidth', '1.5px', '1.5px') === null],
  );

  // ── ALLOWLIST DECLARADA ─────────────────────────────────────────────────────
  // Sem ela a régua fica vermelha permanente (a prod TEM `Forja` e isso foi decidido) e
  // vermelho permanente é ruído que se aprende a ignorar.
  const declForja = [{ campo: 'shell.atalhoTopo.so-na-prod', motivo: 'DECIDIDA: Forja é atalho da prod; o design ainda não tem' }];
  const rdecl = compare(comShell(shellProd), comShell(shellDesign), { declarado: declForja });
  checks.push(
    ['allowlist: declarada sai do contador mas CONTINUA visível na listagem',
      rdecl.shell === rsh.shell - 1 && rdecl.rows.some((r) => r.campo === 'shell.atalhoTopo.so-na-prod' && r.veredito === 'DIVERGE (declarada)')],
    ['allowlist: o MOTIVO chega ao relatório (declarar sem razão é o vetor)',
      rdecl.rows.some((r) => r.veredito === 'DIVERGE (declarada)' && /Forja/.test(String(r.detalhe)))],
    ['allowlist: conta quantas linhas cada entrada silenciou (ampla demais fica VISÍVEL)',
      rdecl.porDeclaracao.length === 1 && rdecl.porDeclaracao[0].silenciou === 1],
    ['allowlist: entrada que não casa nada é reportada como SEM EFEITO (allowlist podre)',
      compare(comShell(shellProd), comShell(shellDesign), { declarado: [{ campo: 'shell.inexistente', motivo: 'x' }] }).declaracoesSemEfeito.length === 1],
    ['allowlist: wildcard cobre o papel inteiro',
      compare(comShell(shellProd), comShell(shellDesign), { declarado: [{ campo: 'shell.atalhoTopo.*', motivo: 'tudo decidido' }] }).shell === 0],
    // FAIL-CLOSED: a declaração NÃO alcança a TELA. Silenciar `DIVERGE (bug)` é outra decisão.
    ['allowlist NÃO silencia bug de TELA (escopo travado no shell)',
      compare(prodCel, designCel, { declarado: [{ campo: 'col1.mono', motivo: 'tentativa de silenciar bug de tela' }] }).bugs
      === compare(prodCel, designCel).bugs],
    ['allowlist: item SEM motivo é REJEITADO', (() => { try { validarDeclaracao([{ campo: 'x' }]); return false; } catch { return true; } })()],
    ['allowlist: motivo só de espaço é REJEITADO', (() => { try { validarDeclaracao([{ campo: 'x', motivo: '   ' }]); return false; } catch { return true; } })()],
    ['allowlist: forma válida é aceita (objeto ou array cru)',
      validarDeclaracao({ itens: [{ campo: 'x', motivo: 'y' }] }).length === 1 && validarDeclaracao([{ campo: 'x', motivo: 'y' }]).length === 1],
  );

  // ── BITE-TEST DO CLI ────────────────────────────────────────────────────────
  // §5 2026-07-30: assert sobre helper exportado NÃO prova contrato de pipeline — o CLI tem de
  // ser exercitado DE FORA. É a prova que importa pra quem for ligar isto em algum lugar:
  // fixture BOA → exit 0, fixture RUIM → exit ≠ 0. URLs https de propósito: o guarda de
  // proveniência só morde espelho local, e aqui o alvo do teste é outro.
  const EU = fileURLToPath(import.meta.url);
  const comUrl = (s) => ({ ...s, url: 'https://oimpresso.com/x' });
  const tmpJson = (obj) => { const p = join(tmpdir(), `dd-shell-${process.pid}-${++seqLedger}.json`); writeFileSync(p, JSON.stringify(obj)); return p; };
  const fBoa = tmpJson(comUrl(comShell(shellDesign)));
  const fRuim = tmpJson(comUrl(comShell(shellProd)));
  const fDecl = tmpJson({ itens: [{ campo: 'shell.atalhoTopo.*', motivo: 'DECIDIDA: o atalho Forja e o visual dele são escolha da prod' }] });
  const fSemMotivo = tmpJson({ itens: [{ campo: 'shell.atalhoTopo.*' }] });
  const cli = (args) => spawnSync(process.execPath, [EU, ...args], { encoding: 'utf8' });
  const rBoa = cli(['--compare', fBoa, fBoa, '--check-shell']);
  const rRuim = cli(['--compare', fRuim, fBoa, '--check-shell']);
  const rDeclarada = cli(['--compare', fRuim, fBoa, '--check-shell', '--declarado', fDecl]);
  const rSemMotivo = cli(['--compare', fRuim, fBoa, '--check-shell', '--declarado', fSemMotivo]);
  const rSemFlag = cli(['--compare', fRuim, fBoa]);
  checks.push(
    ['BITE CLI: fixture BOA (shell idêntico) + --check-shell → exit 0', rBoa.status === 0],
    ['BITE CLI: fixture RUIM (shell divergente) + --check-shell → exit ≠ 0', rRuim.status !== 0],
    ['BITE CLI: RUIM com tudo DECLARADO → volta a exit 0', rDeclarada.status === 0],
    ['BITE CLI: declaração SEM motivo é RECUSADA (exit 2) em vez de silenciar', rSemMotivo.status === 2 && /motivo/i.test(String(rSemMotivo.stderr))],
    ['BITE CLI: sem --check-shell, shell divergente NÃO derruba', rSemFlag.status === 0],
    ['BITE CLI: o relatório NOMEIA as 3 categorias pro humano classificar',
      /DECIDIDA/.test(String(rRuim.stdout)) && /DERIVA/.test(String(rRuim.stdout)) && /DESIGN-ANDOU/.test(String(rRuim.stdout))],
    ['BITE CLI: --declarado não é lido como snapshot posicional', !/ENOENT|Unexpected/.test(String(rDeclarada.stderr))],
    ['BITE CLI: a listagem mostra o que foi declarado e por quê', /silenciou/.test(String(rDeclarada.stdout))],
  );
  for (const p of [fBoa, fRuim, fDecl, fSemMotivo]) { try { rmSync(p, { force: true }); } catch { /* best-effort */ } }


  let ok = true;
  for (const [label, pass] of checks) { console.log(`  [${pass ? 'PASS' : 'FAIL'}] ${label}`); if (!pass) ok = false; }
  console.log(ok ? '\nSELFTEST OK — mede o que o olho perdeu em 07/07 (D8 align + D2 overflow + D6 dark), enumera o SHELL que máquina nenhuma media, e recusa veredito de fonte não provada.' : '\nSELFTEST FALHOU');
  process.exit(ok ? 0 : 1);
}

const argv = process.argv.slice(2);
if (argv.includes('--selftest')) selftest();
else if (argv.includes('--probe')) console.log(PROBE_SOURCE);
else if (argv.includes('--shell-roles')) {
  console.log('// cole no console de CADA lado ANTES de injetar a sonda (--probe).');
  console.log('// Mapa MEDIDO em 2026-08-28 — ponto de partida, não lei: seletor muda com o DOM.');
  console.log('// Confira `presenca` no relatório: papel que não casa elemento visível sai SEM-DADO.');
  for (const lado of ['prod', 'design']) {
    console.log(`\n/* ${lado.toUpperCase()} */`);
    console.log('window.__SB_ROLES = ' + JSON.stringify(SB_ROLES_SUGERIDO[lado], null, 2) + ';');
  }
}
else if (argv.includes('--compare')) runCompare(argv);
else { console.error('uso: --probe | --shell-roles | --compare <prod.json> <design.json> [--check|--check-shell|--json|--declarado <f.json>] | --selftest'); process.exit(2); }
