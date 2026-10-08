---
slug: relatorios-runbook-lucro-prejuizo
title: "Relatórios — Runbook do relatório de lucro e prejuízo"
type: runbook
module: Relatorios
tela: Relatorios/LucroPrejuizo/Index
owner: W
status: ativo
last_validated: "2026-10-08"
related_adrs:
  - '0093-multi-tenant-isolation-tier-0'
  - '0104-processo-mwart-canonico-unico-caminho'
---

# RUNBOOK — Relatório de lucro e prejuízo

> Thread `sistema/playbook/07` · design `prototipo-ui/cowork/Wagner/relatorios-data.jsx` (`profit_loss`).

## 1. Objetivo

`GET /reports/profit-loss?tela=nova` responde Inertia `Relatorios/LucroPrejuizo/Index` com os mesmos números do partial
da Blade (`report/partials/profit_loss_details` — estoque inicial e final, compras, vendas, despesas, fretes, descontos,
linhas de módulos, CMV, lucro bruto e lucro líquido) e o estoque pelo preço de venda. Sem `?tela=nova`, Blade.

## 2. Pré-condições

Permissão `profit_loss_report.view` (a da Blade). Nada de schema.

## 3. Passo-a-passo (F1–F4)

1. **F1** — este RUNBOOK + charter + casos ao lado da Page.
2. **F2** — `tests/Feature/Relatorios/LucroPrejuizoReportPageTest.php`: os números da tela batem com o HTML do partial da
   Blade e com a soma direta no banco; o lucro líquido segue a fórmula; o isolamento 98 × 99.
3. **F3** — a montagem dos números sai do ramo `ajax()` para `dadosDeLucro()`; o corpo de `getStockBySellingPrice()`
   para `estoquePorPrecoDeVenda()`. O partial da Blade e a tela nova chamam os mesmos métodos.
4. **F4** — smoke em biz=1 com `?tela=nova`, comparando com a Blade (período padrão: ano fiscal).

## 4. Divergências declaradas e achados

- As abas "lucro por produto / categoria / marca / local / venda / data / cliente / dia" (`getProfit`) ficam para o PR
  seguinte.
- **Achado (não corrigido, decisão da gerência):** `/reports/get-stock-by-sell-price` (estoque pelo preço de venda) não
  confere permissão nenhuma — a página exige `profit_loss_report.view`. Filtra pelos locais permitidos e pelo negócio.

## 5. Falta para o cutover (F5 — decisão [W])

As abas de lucro (§4), decisão sobre o achado, aprovação do screenshot por [W], o smoke F4 e o aviso ao cliente.
