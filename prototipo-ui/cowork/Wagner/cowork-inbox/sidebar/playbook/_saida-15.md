---
sessao: "15"
titulo: MÁQUINA · alvo.mjs mede os 3 modos da sidebar
autor: "[CL]"
data: 2026-09-25
base: wagnerra23/oimpresso.com@main (depois do #7959, import do handoff 39)
---

# _saida-15 · Alvo do sidebar nos 3 modos

## Feito

Entregue **3 de 3** itens do "Faz".

| # | o que | onde |
|---|---|---|
| 1 | `--viewport <L>x<A>` e `--sb-mode expanded\|rail\|hidden` (no `--mapa` e no `--alvo`). O modo vai pro `localStorage` por `addInitScript`, **antes** da navegação, nas DUAS chaves: `oimpresso.sb.mode` (vivo) e `oimpresso.sidebar.mode` (protótipo, `app.jsx:572`). Forma torta é recusada com exit 2; nunca cai calada no default | `scripts/design-sync/alvo.mjs` |
| 2 | o `secao-check` repassa `viewport`/`sb_mode` que o alvo gravou. Os dois só entram no JSON **quando usados**, então o `jana--index` e o rail ficam com as mesmas chaves de antes | `scripts/qa/secao-check.mjs` |
| 3 | um alvo por modo: `cockpit--sidebar` (rail, **regerado**) · `cockpit--sidebar-expanded` (7 seções: as 5 âncoras + grupo + cabeçalho de grupo) · `cockpit--sidebar-hidden` (afirma `aside` **ausente** e `.sb-reopen-handle` **presente**) | `governance/design/targets/` |

Os seletores do expanded foram **colhidos** do DOM do espelho (`--mapa ... --viewport 1440x900 --sb-mode expanded`: filhos `sb-top · sb-cert · sb-body · sb-user-wrap · sb-collapse-handle`, 9 `.sb-group-h`). Não vieram de lembrança.

## Recibos

- **`secao-check --todos --servir-espelho`** → `conforme` (exit 0). A saída, por alvo:
  - `cockpit--sidebar-expanded`: 7 seções conformes
  - `cockpit--sidebar-hidden`: 2 seções conformes
  - `cockpit--sidebar`: 5 seções conformes
  - **`jana--index`: 10 seções conformes (não regrediu)**
- **Bite-test:** medi o expanded com `--injetar-falha .sb-group-h` (em `--saida`, sem re-baselinar) e comparei com `secao-check --medido`. Deu **exit 1**, com `sb-grupo-h → filhos: esperado 4 · obtido 3` e `ordemClasses` (o chevron `ic` some).
- **Selftests:** `alvo.mjs --selftest` 17/17 (+3 asserts puros: `parseViewport` e `SB_MODOS`) · `alvo.mjs --selftest --browser` 23/23 · `secao-check --selftest` 16/16.

## Achado no caminho: o alvo do rail já estava vermelho no `main`

Antes de qualquer mudança minha, a linha de base do `secao-check` saía **REGREDIU** no `cockpit--sidebar`:
- `sb-rodape` → `aside.sb > .sb-user` **não casava**;
- `sb-modos` → `ordemClasses` com `sb-user-wrap` onde o alvo esperava `sb-user`.

A causa é que o handoff 39 (#7959) trouxe o protótipo já com `.sb-user-wrap` (`sidebar.jsx:741`), igual ao vivo. A diferença que o alvo da thread 07 registrava "não corrigida" fechou do lado do design, e o alvo ficou defasado. Troquei o seletor do rodapé para `.sb-user-wrap`, atualizei a nota e regerei o rail. Isso já era o passo 3 da ficha.

## Não feito, e por quê

- **Prova `revisao` JSON:** o avaliador de recibo não foi portado (ADR 0397). Os recibos acima são a revisão.

## Prefixo: divergência da ficha com o índice

O `prefixo` do índice diz `scripts/design/`, mas o `alvo.mjs` mora em **`scripts/design-sync/`** e o `secao-check` em **`scripts/qa/`**. A própria ficha nomeia os dois arquivos, então segui a ficha e registro a divergência aqui, para o Cowork corrigir o índice. Nada do `nao_toca` foi alterado (`Components/cockpit/` e o espelho).

Tocado: `scripts/design-sync/alvo.mjs` · `scripts/qa/secao-check.mjs` · `governance/design/targets/cockpit--sidebar{,-expanded,-hidden}.{alvo,secoes}.json` · este `_saida-15.md`.
