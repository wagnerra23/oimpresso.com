---
sessao: "02"
titulo: "Sonda tema-escuro nas Pages de produção — saída da thread"
autor: "[CL]"
criado: 2026-10-07
base: 836619f64d
thread: 02-producao.md
veredito: "74 de 74 Pages medidas no CI com tema escuro; 19 têm superfície clara fora de papel. Lista abaixo é o insumo da 03. Step advisory, sem baseline de produção."
---

# _saída 02 · Sonda nas Pages de produção

PR [#8888](https://github.com/wagnerra23/oimpresso.com/pull/8888). Run da prova: [37608542269](https://github.com/wagnerra23/oimpresso.com/actions/runs/37608542269) (job `visual-regression`), artefato `tema-escuro-producao-37608542269`.

## 1 · Como mede

- `tests/Browser/TemaEscuro/TemaEscuroProducaoTest.php` (Pest Browser) loga no **biz=1 fictício** do seed do CI pelo `/_visreg-login`, com `ui_theme=dark` (restaurado depois), e visita cada tela de `tests/Browser/visreg-screens.json`. Coleta os fundos de `main.main-body`, também com a 1ª linha da tabela selecionada (barra de lote).
- O veredito sai da sonda da 01: `tema-escuro-probe.mjs --producao` usa o mesmo corte (L > 0,78, croma < 0,1, alfa ≥ 0,5) e a mesma allowlist, lida por `--allowlist`. O PHP não tem cópia do limiar.
- Tela que cai no `/login`, responde ≠ 200 ou não escurece fica registrada com o motivo, nunca como "0 claros". Sanidade: um `div` branco injetado tem de ser julgado claro.

## 2 · Prova

- Pest: `Tests: 74 passed (148 assertions)`, 224,8 s.
- Sonda: `74 Page(s) · 74 medida(s) · 0 sem medida · 19 com superfície clara fora de papel`. Sanidade vista e ok.
- `--selftest` 20/20. CLI de fora em fixtures: bom → 0, sanidade escura → 2, diretório ausente → 2.

## 3 · As 19 Pages com superfície clara (1 linha por Page)

| Page | rota | o que acusa (seletor · fundo · n) |
|---|---|---|
| Compras | `/compras` | `div` branco ×2 · `select` rgb(251,249,243) ×1 |
| Financeiro/Cobranca | `/financeiro/cobranca` | `bg-stone-100/80` ×1 · `text-[10px].tabular-nums` oklch(0.923) ×1 |
| Financeiro/Dre | `/financeiro/dre` | `div` oklch(0.96 0.04 70) ×1 |
| Financeiro/Fluxo | `/financeiro/fluxo` | `div` oklch(0.96 0.04 70) ×1 |
| Financeiro/PlanoContas | `/financeiro/plano-contas` | `fin-filter-cb` branco ×6 · `fin-filter-cb-box` branco ×6 · `div.flex.min-w-0` branco ×4 · `fin-filter-cb.on` ×1 |
| Financeiro/Unificado | `/financeiro/unificado` | `fin-filter-cb.on` oklch(0.96 0.05 145) ×4 · `span.inline-grid` ×1 · `fin-trouble-trigger` oklch(0.97 0.04 60) ×1 |
| Fiscal/Cockpit, Config, Dfe, Eventos, Nfe, Nfse, Sped | `/fiscal/*` (7) | `footer.fx-shell-foot` rgba(255,255,255,0.92) — um defeito só, no shell do Fiscal |
| Governance/Dashboard | `/governance/dashboard` | `span.inline-flex.w-fit` oklch(0.962 0.059 96) ×1 |
| Manufacturing/Settings | `/manufacturing/settings` | `span.pointer-events-none.block` oklch(0.965) ×2 |
| Oficina/OS | `/oficina-auto/ordens-servico` | `span.text-xs.px-1.5` oklch(0.968) ×6 |
| Sells/CreateV3 | `/sells/create-v3` | `div.rounded-lg.bg-foreground` oklch(0.965) ×1 — **a placa "Total da venda" invertida** que o índice previa |
| Sells/Index | `/sells` | `div.os-kpi` branco ×4 · `kbd.kbd-hint` branco ×1 · `table.os-table.vendas-table` branco ×1 |
| StockTransfer/Create | `/stock-transfers/create` | `span.inline-flex.h-5` oklch(0.923) ×2 |

As outras 55 saíram com 0, entre elas `RecurringBilling`, as 4 da `Jana` e as 15 do `Ponto`.

## 4 · Não feito, e por quê

- **Baseline de produção e gate.** Não existe; baseline nova é decisão [W]. O step é advisory e só roda quando o PR toca a sonda ou o teste (o job já fica perto do teto de 25 min). Para a 03 ver a sonda ficar verde, o PR dela precisa tocar `tests/Browser/TemaEscuro/` ou a sonda.
- **`Whatsapp/Inbox`.** Fora de `visreg-screens.json`, então fora desta medição.
- **Universo.** São as 74 telas com rota provada no seed do CI, não as ~240 Pages. Ampliar é ampliar o `visreg-screens.json`, que é o dono.

## 5 · Descobertas que mudam outra sessão

- **Thread 03:** a lista da §3 é o trabalho. Três agrupamentos baratos: o `footer.fx-shell-foot` resolve 7 Pages de uma vez; `fin-filter-cb` aparece no PlanoContas e no Unificado; `Sells/CreateV3 · bg-foreground` é a placa invertida da D1.
- **Sonda:** `swatch` (amostra de cor) precisa entrar na allowlist — pedido no `_saida-04`.

## 6 · Prefixo tocado

`scripts/design/tema-escuro-probe.mjs`, `tests/Browser/TemaEscuro/` e este recibo. Fora do prefixo: `.github/workflows/visual-regression.yml` (o step que gerou a prova).
