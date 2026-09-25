---
sessao: "00"
titulo: ALVO financeiro--unificado (seção 07, drawer)
autor: "[CL]"
data: 2026-09-25
base: wagnerra23/oimpresso.com@main 2c115a5ca (import do handoff 40 no PR #7969)
---

# _saida-00 · Alvo medido do drawer do Financeiro/Unificado

## Feito

Entregue **1 de 1** item: `governance/design/targets/financeiro--unificado.alvo.json` existe, foi gerado pela máquina (não transcrito) e o comparador o aceita.

| # | o que | onde |
|---|---|---|
| 1 | `alvo.mjs` ganha `--rota <r>` (grava `oimpresso.route` no `localStorage` antes da navegação — o protótipo roteia por ele, não por URL) e `--clicar <seletor>` (clica no 1º elemento e re-estabiliza antes de medir; seletor que não casa = exit 2 NÃO MEDI). Os dois vão pro JSON só quando usados | `scripts/design-sync/alvo.mjs` |
| 2 | o `secao-check` repassa `rota`/`clicar` que o alvo gravou | `scripts/qa/secao-check.mjs` |
| 3 | 11 seções do drawer (`dw-painel · dw-header · dw-fechar · dw-nav · dw-hero · dw-abas · dw-corpo · dw-veredito · dw-lente · dw-rodape · dw-rodape-botao`), com o bloco `dado` de cada uma lido no `main` (`UnificadoController@index` L303 → `lancamentos`; o drawer é `lancamentos.find(selectedId)`, sem request próprio) | `governance/design/targets/financeiro--unificado.{alvo,secoes}.json` |

Seletores **colhidos** do DOM do espelho (`--mapa ... --rota financeiro --clicar "tbody tr.row-hover" --raiz <região>`), por classe/posição extrema — não por `nth-child` do meio.

## Recibos

- **Valores = §3 da thread 07**, linha a linha: painel 560 · header 56 `0 12px 0 20px` · fechar 28×28 r6 transparente · J/K 28 com borda · hero `18px 20px 16px` `background-image: none` · lente `16px 0` sem fundo · veredito r8 · rodapé 60 · botão 32.
- **Determinismo:** 2 runs do `--alvo` byte-idênticas.
- **Comparador:** `secao-check --tela financeiro--unificado` → 11 conforme. Só é possível se ele repassou `--rota`/`--clicar` — sem eles o drawer nem existe no DOM.
- **Bite-test:** `alvo.mjs --selftest --browser` 25/25, com o controle negativo do `--clicar` (seletor que não casa → NÃO MEDI).
- **`pedido.mjs --secoes`:** 11/11 com bloco B.dados.

## Achado no caminho: a 1ª medição estava errada, e por quê

Servido com `python -m http.server`, o espelho renderizou **sem os tokens do DS** (`--surface` vazio): cores `transparent`/`rgb(0,0,0)`, bordas `0px` e a árvore do drawer com **1 filho a menos** (a barra de abas nem montava). Só pelo `servirEstatico` (`render-proto-baseline.mjs`, dono da rota `/_ds/`) os tokens resolvem. Ficou registrado no `_` do `secoes.json`.

## Dívida de DADO neste índice (corrigir aqui no Cowork)

O placar (`scripts/qa/placar.mjs`) saía NÃO MEDI (exit 2) em **todos** os 12 índices por causa deste `00-INDICE.md`. O dono foi estendido pra tolerar, mas os dados continuam pedindo conserto na fonte:

1. **Prova `arquivo` da thread 00 sem `path`** — só `exige` em prosa. Hoje sai "não medida" e a thread nunca chega a `feito`. Proposta: `{ "tipo": "arquivo", "path": "governance/design/targets/financeiro--unificado.alvo.json" }`.
2. **`variaveis.CSS` e `variaveis.PROTOTIPO` são listas.** O placar agora aceita lista (todos os itens precisam ser paths seguros), mas lista **não pode** compor `${VAR}` num `path` — se alguma prova usar, sai NÃO MEDI de propósito.

## Não feito, e por quê

- **Seções 01–06 e a aba IA:** fora desta thread (a aba IA exige um 2º clique; o índice declara 01–06 não medidas).
- **`unificado.proto-baseline.json` segue STALE:** `render-proto-baseline --gerar` aborta com "esperava 1 design system no shell, achei 3" — a mesma condição que deixa os 9 baselines "render NÃO MEDIDO" no CI. Tratado em tarefa separada. O `unificado.map.json` foi regenerado (`gerar-map --atualizar`, 12 partes preservadas).

Tocado: `scripts/design-sync/alvo.mjs` · `scripts/qa/secao-check.mjs` · `scripts/qa/placar-indice.{mjs,test.mjs}` · `governance/design/targets/financeiro--unificado.{alvo,secoes}.json` · `governance/design/targets/README.md` · `memory/requisitos/Financeiro/unificado.map.json` · este `_saida-00.md`.
