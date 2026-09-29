---
sessao: "09"
titulo: "ALVO financeiro--dre--index — saída da thread"
autor: "[CL]"
data: 2026-09-29
base: wagnerra23/oimpresso.com@main 831461d6a
thread: 09-dre-conta-mono.md
veredito: "entregue — alvo medido (8 seções, 0 ausentes), duas medidas byte-idênticas; nenhum PARAR SE disparou; destrava a 10."
---

# _saida-09 · ALVO da DRE

Entregue **1 de 1**: `governance/design/targets/financeiro--dre--index.alvo.json`, gerado pela máquina, nunca editado à mão.

## O que saiu

| arquivo | origem |
|---|---|
| `governance/design/targets/financeiro--dre--index.secoes.json` | seletores colhidos com `alvo:mapa`, `dado` lido em `DreController@index` |
| `governance/design/targets/financeiro--dre--index.alvo.json` | saída do `alvo:medir` |
| linha na tabela "Alvos exportados" do `README.md` da pasta | — |

## Como foi medido

- Espelho servido por `servirEstatico` (`scripts/design/render-proto-baseline.mjs`), porta 5550 — o mesmo servidor do `secao-check`.
- Rota do protótipo `fin-dre` (`app.jsx:828` → `FinanceiroPage initialTela="dre"` → `TelaDRE`, `financeiro-telas-extras.jsx:436`).
- `alvo:mapa --rota fin-dre --raiz <sel>`: `.fin-root` = `.fin-hero` · `nav.ph-nav` · `.fin-body`; `.fin-body` = `nav.fin-bcrumb` · abas · card da tabela · grade de 2 cards.
- `npm run alvo:medir -- http://127.0.0.1:5550/ --tela financeiro--dre--index --rota fin-dre --secoes governance/design/targets/financeiro--dre--index.secoes.json --quieto-ms 2000`, duas vezes. `cmp` sem saída: **byte-idênticas**, sha256 `ad2da895d698973c…`. Espera por `__oiLazyDone` + contagem estável é do próprio `alvo.mjs`.
- 8 seções, cada seletor casou **1 nó**, 0 ausentes, `nos_totais` 691, viewport 1280×900, tema dark (cor de texto `oklch(0.94 0.005 90)`).

## Provas de efeito

- `secao-check --tela financeiro--dre--index --url http://127.0.0.1:5550/` → **8 seções conforme**, exit 0.
- `pedido.mjs --tela Financeiro/Dre/Index --secoes` → 8/8 `ok`, exit 0. Controle negativo `--tela Financeiro/Fluxo/Index` segue exit 2 (sem alvo).

## PARAR SE

Nenhum disparou: a seção da tabela (`tabela`) resolveu 1 nó e as duas medidas bateram.

## O que a thread 10 precisa saber (medido, não inferido)

- **`conta`** (1ª célula de corpo da coluna Conta) no protótipo: `fontFamily = "IBM Plex Mono", monospace`, 12.5px, peso 500.
- **Mas a célula não tem `font-mono`**: o mono vem da classe `num` no `<table>` (`financeiro-telas-extras.jsx`, `<table className="w-full … num">`), herdado por todas as colunas.
- **`conta_titulo`** (o `<th>` "Conta") mede **IBM Plex Sans**, 10.5px. O cabeçalho **não** é mono no protótipo — a 10 não deve mono-izar o `<th>`.
- A 10 compara `conta.estilo.fontFamily` com o render, não a classe declarada.

## Fora do alvo, de propósito

As abas Balanço e Balancete (`FinBalanco`/`FinBalancete`) exigem clique e não foram medidas.
