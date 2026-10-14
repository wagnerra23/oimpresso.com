---
sessao: "03"
titulo: "Superfícies claras em produção — saída da thread"
autor: "[CL]"
criado: 2026-10-13
thread: 03-invertidas.md
veredito: "As 19 Pages da _saida-02 zeraram. 10 já tinham sido consertadas antes (#8971 Vendas, #9193/#9201 Compras e outros); as 9 restantes saíram em 8 PRs empilhados. No topo da pilha a sonda mede 75 de 75 Pages com 0 superfície clara. StockTransfer/Create voltou para a baseline (1 Page, 2 selos): consertá-la toca o header antigo, e migrar o header é decisão visual do [W]."
---

# _saída 03 · Superfícies claras em produção

## 1 · Antes desta sessão

A thread 05 ([#9265](https://github.com/wagnerra23/oimpresso.com/pull/9265)) mediu as 19 Pages da `_saida-02` e achou **10 já zeradas**: as 7 do Fiscal (`footer.fx-shell-foot`), `Sells/Index` e a placa do `Sells/CreateV3` ([#8971](https://github.com/wagnerra23/oimpresso.com/pull/8971), D1), e `Financeiro/Cobranca`, mais as `fin-filter-cb`. A baseline nasceu com as 19 e baixou para 9 no próprio #9265.

## 2 · As 9 restantes — 8 PRs, uma Page por PR (empilhados sobre o #9265)

| PR | Page | causa | conserto |
|---|---|---|---|
| [#9289](https://github.com/wagnerra23/oimpresso.com/pull/9289) | Oficina/OS | contador da coluna `bg-<hue>-100` sem par (`boardTone.ts`) | `dark:<hue>-950/40` + `dark:<hue>-300`, o par que o arquivo já usava nos KPIs |
| [#9314](https://github.com/wagnerra23/oimpresso.com/pull/9314) | StockTransfer/Create | selos DE `stone-200` e PARA `emerald-100` | par `dark:` |
| [#9316](https://github.com/wagnerra23/oimpresso.com/pull/9316) | Governance/Dashboard | selo do modo da proteção (3 estados) | par `dark:` |
| [#9317](https://github.com/wagnerra23/oimpresso.com/pull/9317) | Manufacturing/Settings | **não era defeito**: o thumb do `Switch` é claro no escuro de propósito | `[data-slot=switch-thumb]` na allowlist (o mesmo knob que o espelho isenta desde 06/10) |
| [#9318](https://github.com/wagnerra23/oimpresso.com/pull/9318) | Financeiro/Dre + Fluxo | aviso amarelo com cor fixa em `style` inline (5 cópias: DRE, Balancete, Balanço, Fluxo, Relatórios) | `--color-warning-soft` + borda de `--color-warning`; texto `--color-foreground` |
| [#9319](https://github.com/wagnerra23/oimpresso.com/pull/9319) | Financeiro/Unificado | pílula ↘/↗ com cor fixa em `style` inline | classes: no claro os mesmos oklch; no escuro `success`/`destructive` `-soft`/`-fg` |
| [#9320](https://github.com/wagnerra23/oimpresso.com/pull/9320) | Compras | barra e rodapé `#fff`, select `#fbf9f3` em `style` inline | `var(--cmp-paper)` e `var(--cmp-bg-2)`, tokens do bundle de Compras |
| [#9321](https://github.com/wagnerra23/oimpresso.com/pull/9321) | Financeiro/PlanoContas | cartões do `FinStatStrip` com `bg-white` | `bg-[var(--surface)]`; texto pos/neg ganha par `dark:` |

O claro ficou idêntico em quase tudo. Mudaram de leve só o aviso amarelo do Financeiro (para o token de warning) e o select "Mostrar" de Compras (para `--bg-2`). No aviso, o texto ficou `foreground` e não `--color-warning-fg`, por medição: o par warning-fg/warning-soft dá 4,51:1 no claro, contra 8,26:1 de antes.

## 3 · Prova

Cada PR mostra, no próprio run do job `tema-escuro · Pages de produção vs baseline`, a sua entrada caindo a 0:

| run | PR | linha |
|---|---|---|
| 38463141050 | #9289 | `↓ Oficina/OS · span.text-xs.px-1.5 6→0` |
| 38466125399 | #9314 | `↓ StockTransfer/Create · span.inline-flex.h-5 2→0` |
| 38467267774 | #9316 | `↓ Governance/Dashboard · span.inline-flex.w-fit 1→0` |
| 38469639532 | #9317 | `↓ Manufacturing/Settings · span.pointer-events-none.block 2→0` |
| 38472620389 | #9318 | `↓ Financeiro/Dre · div 1→0` · `↓ Financeiro/Fluxo · div 1→0` |
| [38473412958](https://github.com/wagnerra23/oimpresso.com/actions/runs/38473412958) | #9321 (topo) | **`75 medida(s) · 0 com superfície clara fora de papel`** — Compras, PlanoContas e Unificado a 0 |

A baseline foi baixada pela sonda a partir do artefato de cada run (9 → 8 → 7 → 6 → 5) e **esvaziada no #9321**, depois devolvida a 1 Page (StockTransfer/Create, ver §4). Os PRs #9318–#9320 não baixam a baseline por conta própria (contagem que desce só avisa); quem a esvazia é o topo da pilha. Isso poupou chamadas à API, cuja cota estourou no dia.

## 4 · Não feito, e por quê

- **StockTransfer/Create volta para a baseline.** A tela ainda usa o header antigo, e a catraca do PageHeader reprova qualquer PR que a toque (o `ui:lint` também, por 2 hits R1 a mais). O conserto dos selos DE/PARA (#9314) saiu do #9321, o `.tsx` voltou igual ao do `main`, e a baseline guarda a contagem medida no run 38463141050: `span.inline-flex.h-5: 2`. O conserto volta quando a tela migrar o header, o que muda o visual e fica com o [W].
- **Universo.** São as 75 telas de `tests/Browser/visreg-screens.json`. Página fora do manifesto (ex.: `Whatsapp/Inbox`) segue sem medida.
- **Estados escondidos.** A sonda de produção abre a seleção da 1ª linha; hover e foco por teclado não. O defeito de hover em Vendas (#8971) foi achado no olho do [W], não pela sonda.
- **Contraste do texto.** A sonda mede o fundo. O texto pos/neg do `FinStatStrip` e da pílula do Unificado foi tratado por ser vizinho direto do conserto, não por varredura.

## 5 · Prefixo tocado

Pages e componentes das 9 telas, `scripts/design/tema-escuro-probe.mjs` (allowlist do thumb), `scripts/design/tema-escuro-producao-baseline.json` e este recibo.
