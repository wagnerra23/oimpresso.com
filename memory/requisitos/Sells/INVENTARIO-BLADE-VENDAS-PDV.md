---
id: requisitos-sells-inventario-blade-vendas-pdv
module: Sells
doc_type: inventario
title: "Inventário Blade — Vendas / PDV / Caixa · base para UC + teste + documentação"
status: draft
owner: wagner
author: "[CC]"
created: "2026-08-18"
related_adrs: [0104, 0264, 0256, 0275]
---

# Inventário Blade — Vendas / PDV / Caixa

> **Para que serve.** Levantar o que existe em Blade no fio da venda para transformar em
> **casos de uso + teste + documentação**. É o eixo de **cobertura**, não o de migração —
> o plano de cutover é o irmão [ONDA-1-VENDAS-PDV-CAIXA-PLANO.md](../Mwart/ONDA-1-VENDAS-PDV-CAIXA-PLANO.md)
> (+ [ledger](../Mwart/ONDA-1-CUTOVER-LEDGER.md), fonte de verdade dos destinos por rota).
> Este doc **não decide destino de rota**; ele diz o que está descoberto de contrato.
>
> **Por que precisou existir.** As portas vivas de cobertura (`npm run casos:report` e
> `npm run screen-coverage:report`) enxergam **só as 206 páginas Inertia** — o escopo declarado
> é `resources/js/Pages/**/<Sub>/<Tela>.tsx`. Nenhuma tela Blade é medida por instrumento nenhum.
> Este inventário preenche esse vão **manualmente e datado**; o durável é virar comando (§6).

---

## 1. Como cada número foi medido

Todo número abaixo sai de comando reproduzível, rodado em `main @ 6b923a26d` nesta sessão
(repo **não** é raso — `git rev-parse --is-shallow-repository` = `false`).

| Número | Comando |
|---|---|
| endpoints do lote | `node scripts/governance/blade-migration-census.mjs --json` filtrado pelos 6 controllers |
| views do lote | `git ls-files "resources/views/**/*.blade.php"` nas 7 pastas |
| tela × fragmento | busca literal `view('<dot>'` (PHP) vs `@include('<dot>'` (Blade) no corpus inteiro |
| UC existentes | contagem de `^## UC-` nos `*.casos.md` ao lado do `.tsx` |

**Escopo (6 controllers):** `SellController` · `SellPosController` · `SellReturnController` ·
`SalesOrderController` · `CashRegisterController` · `ImportSalesController`.

---

## 2. Superfície medida

**91 endpoints** do lote (de 1.753 no censo). Destes, **50 ainda servem Blade** (42 blade + 8 híbridos):

| Controller | blade | híbrido | outro | react | indeterminado |
|---|---:|---:|---:|---:|---:|
| `SellPosController` | 23 | 2 | 11 | — | — |
| `SellController` | 4 | 6 | 14 | 1 | 1 |
| `SellReturnController` | 6 | — | 2 | — | 3 |
| `CashRegisterController` | 5 | — | 2 | — | 3 |
| `SalesOrderController` | 2 | — | 2 | — | — |
| `ImportSalesController` | 2 | — | 2 | — | — |

> Os **7 indeterminados** são do censo — ele declara que não afirma. Não conte como blade nem como react.

**90 views** Blade nas 7 pastas → **48 telas** (12.551 LOC) · **36 fragmentos** (3.757 LOC) · **6 órfãs reais**.

---

## 3. O estado de cobertura — as três faixas

### A. Já tem `casos.md` (base pronta, é só estender) — 4 telas · 16 UC

| Blade | LOC | Twin React | UC | Teste |
|---|---:|---|---:|---|
| `sell/create.blade.php` | 999 | `Sells/Create` | 2 | 2 |
| `sale_pos/show.blade.php` | 437 | `Sells/Show` | 7 | 2 |
| `sell/index.blade.php` | 384 | `Sells/Index` | 5 | — |
| `sale_pos/create.blade.php` | 132 | `Sells/Create` | 2 | 1 |

> `sell/create` e `sale_pos/create` **convergem na mesma** `Sells/Create` — dual-response pela
> mesma flag (`SellController:1009` e `SellPosController:281`). São 2 Blades, 1 tela React.

### B. Tem twin React, **sem** `casos.md` — 5 telas

| Blade | LOC | Twin React | Teste existente |
|---|---:|---|---|
| `sell/edit.blade.php` | 900 | `Sells/Edit` | `Wave1EditBaselineTest` + `Wave1EditInertiaTest` |
| `sale_pos/quotations.blade.php` | 172 | `Sells/Quotations` | `Wave1Quotations{Baseline,Inertia}Test` |
| `sale_pos/draft.blade.php` | 167 | `Sells/Drafts` | `Wave1Drafts{Baseline,Inertia}Test` |
| `sale_pos/subscriptions.blade.php` | 55 | `Sells/Subscriptions` | `Wave1Subscriptions{Baseline,Inertia}Test` |
| `cash_register/index.blade.php` | 53 | `Sells/Caixa/Index` | — |

> **Faixa mais barata do inventário.** Já existe twin, charter e um par baseline/inertia de Pest —
> falta só destilar o UC e citá-lo pelo id no título do `it()`. É trabalho de contrato, não de código.

### C. **Sem** twin React — 39 telas · 9.252 LOC

Aqui não há contrato nenhum. Agrupadas por natureza (o agrupamento muda radicalmente o esforço):

| Grupo | Telas | LOC | Observação |
|---|---:|---:|---|
| **Recibos/layout de impressão** (`receipts/{classic,elegant,detailed,slim,slim2,columnize-taxes}`) | 6 | 4.904 | **1 contrato parametrizado por `design`**, não 6 telas — o enum vive em `InvoiceLayoutController:252-257` |
| **Saídas/PDF** (`delivery_note`, `packing_slip`, `sell_return/receipt`, `sells/transcript`) | 4 | 1.236 | contrato de *saída*: layout + campos, não interação |
| **Devolução** (`sell_return/{index,add,show,partials/product_row}`) | 4 | 609 | 🔴 zero twin, zero charter — e mexe em estoque. **Não é zero teste**: ver correção abaixo |
| **Caixa** (`create`, `close_register_modal`, `register_details`) | 3 | 269 | 🔴 mexe em **dinheiro**; o twin React tem o botão mas ele navega pro Blade (`Caixa/Index.tsx:123`) |
| **Fragmentos AJAX de linha** (`product_row`, `payment_row`, `product_list`, `featured_products`, …) | ~14 | ~1.000 | não são telas de usuário — viram UC **dentro** da tela-mãe |
| **PDV/pedidos/importação** (`sale_pos/index`, `sale_pos/edit`, `sales_order/*`, `import_sales/*`) | 8 | 1.234 | `sale_pos/index` é a lacuna "PDV-balcão puro" do ONDA-1 |

### Órfãs reais (código morto — candidatas a deleção, não a UC)

`sale_pos/{create_old,edit_old}` · `sell_return/tmp_create` (**0 citações no repo inteiro**) ·
`cash_register/edit` · `sale_pos/receipts/elegant_modified` · `sale_pos/partials/product_list_paginator`.
As 3 últimas só aparecem em `SUPERFICIE.md` (doc), nunca em código.

> ⚠️ **Correção registrada:** meu primeiro detector marcou **12** órfãs. Seis eram falso-negativo —
> os recibos são resolvidos por concatenação dinâmica (`view('sale_pos.receipts.' . $design)`,
> `SellPosController:901/3128` + `TransactionUtil:6281`), que busca estática não pega. Ficam 6.

> ⚠️ **Segunda correção, mesma família (2026-08-18).** A coluna "Teste" das tabelas §3 procura o nome
> **pontuado da view** (`sell_return.index`) dentro de `tests/**` e `e2e/**`. Ela **não vê** teste que
> exercita o *serviço* sem citar a view — e é assim que os testes de estoque são escritos. Medido depois
> com `git grep -l -E "addSellReturn|sell_return|SellReturn" -- tests/ e2e/` → **10 arquivos**, dos quais
> `EstoqueDevolucaoVendaTest` e `EstoqueDevolucaoVestuarioTest` cobrem a reintegração de saldo
> (`UC-EST-03`/`UC-EST-04`). **Leia a coluna "Teste" como piso, nunca como teto** — ela mede citação da
> view, não cobertura de comportamento. Quando isto virar comando (§6), o cruzamento certo é
> *Controller/Service*, não nome de view.

---

## 4. Ordem de ataque — por risco, não por tamanho

O critério é **onde a ausência de contrato dói**, não onde há mais linhas.

| # | Alvo | Por quê |
|---|---|---|
| 1 | **Devolução** (`sell_return`) | mexe em **estoque**, zero twin, zero UC, zero teste. É o maior buraco de risco do lote. |
| 2 | **Fechar-caixa** | mexe em **dinheiro**; hoje o React entrega o usuário ao Blade sem contrato no meio. |
| 3 | **Faixa B** (5 telas) | mais barata: twin + charter + Pest já existem; falta destilar UC e citar o id no `it()`. |
| 4 | **Recibos** | 1 contrato parametrizado cobre 4.904 LOC — melhor razão cobertura/esforço do inventário. |
| 5 | `sale_pos/index` (PDV-balcão) | lacuna nomeada pelo ONDA-1; construir contrato antes da tela. |

**Regra Tier 0 que atravessa 1, 2 e 4:** tudo aqui toca valor ou estoque → vale a
**REGRA MESTRE** de `proibicoes.md` (dupla confirmação do cálculo + antes→depois apresentado + OK [W]).
UC dessas telas deriva do **SDD/CU e do legado**, nunca do `.tsx` (§5 2026-06-05).

---

## 5. Ordem de fonte para escrever cada UC

Fixa, conforme [how-trabalhar.md](../../how-trabalhar.md):
**(1)** doc canon — [SDD-tela-venda-v1.0.md](SDD-tela-venda-v1.0.md) §6 CU + [SPEC.md](SPEC.md) US +
[CASOS-USO-CREATE-VENDA.md](CASOS-USO-CREATE-VENDA.md) (15 CU) → **(2)** código, só para *confirmar* →
**(3)** Delphi/`ANTI-REGRESSAO-*` quando houver paridade → **(4)** mercado → **(5)** perguntar ao [W].

Carimbar tela nova é `node scripts/governance/criar-tela.mjs` (nasce com trio + stub e2e citando o UC).

---

## 6. O que torna este doc descartável (e isso é bom)

Ele apodrece por construção — é retrato datado, e o §5 é explícito de que *escrito+lembrado apodrece*
([ADR 0256](../../decisions/0256-knowledge-survival-meia-vida-catraca-sentinela.md)). O durável é
**estender o `blade-migration-census.mjs`** (dono do tema, `--json` já existe) com a dimensão de
cobertura: *tem twin? tem casos? quantos UC? quantos testes citam?* — aí o inventário vira
`npm run migracao:report` e nunca mais é escrito à mão.

Isso é proposta, **não feito**: nasce advisory e forward-only ([ADR 0275](../../decisions/0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes.md)),
e exige medir FP antes de virar gate.

---

## Trilha do tempo

- 2026-08-18 · [CC] criado. Medição em `main @ 6b923a26d`: 91 endpoints (50 servindo Blade),
  90 views → 48 telas / 36 fragmentos / 6 órfãs, 16 UC existentes em 4 telas.
  Correção do detector de órfãs (12 → 6) por resolução dinâmica de recibo.
