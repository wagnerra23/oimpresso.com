---
last_validated: "2026-09-03"
slug: runbook-manufacturing-report
title: "RUNBOOK — /manufacturing/report (Fabricação · Relatório)"
type: runbook
module: Manufacturing
page: /manufacturing/report
component: resources/js/Pages/Manufacturing/Report.tsx
status: rascunho
updated_at: 2026-09-03
version: 0.1
owner: F
---

# RUNBOOK — `/manufacturing/report` (Fabricação · Relatório)

> **F1 PLAN do MWART** ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)).
> US-MANU-002 (SPEC.md) — segunda das 7 telas do handoff **"PROTÓTIPO OFICIAL - FABRICAÇÃO V1"**,
> a mais barata da fila (decisão [M] 2026-09-02: ordem de custo crescente). Fonte visual:
> `prototipo-ui/cowork/manufacturing-producao.jsx::MfgRelatorio` — o mesmo bundle já aplicado
> inteiro pela Onda 1 (Recipes), então **nenhuma classe CSS nova**: `.mfg-filters`, `.mfg-table.rep`,
> `.mfg-bar-mini`, `.mfg-check` já existem em `cowork-manufacturing-bundle.css`.
>
> **CUTOVER 2026-09-04 — este é o endereço canônico.** Até 2026-09-04 valia o padrão
> aditivo (tela React em `/v2/*`, Blade intocado no endereço de sempre). O pedido [F]
> — *"módulo inteiro em produção, com os links e vínculos reais, sem rotas alternativas"*,
> sobre a aprovação [W] da família — trocou isso: o endereço de sempre passou a servir a
> tela React, `?legacy=1` devolve o Blade no MESMO endereço, e `/v2/*` virou 301.
> **Nenhuma rota foi removida ou renomeada** (Non-Goal vivo do `Recipes.charter.md`).
> Pré-condição medida: a regra F5 (cutover exige aviso a cliente) nomeia a ROTA LIVRE, e
> [F] confirmou 2026-09-04 que ela não usa Fabricação.

## 1. Cálculo — prova de que não inventei fórmula nova

`ProductionService::reportByProduct()` custa cada ordem como
`RecipeBomService::calculateUnitCost($recipe) × quantidade_produzida_na_ordem` — **reusa** o
método já testado (UC-RECIPE-03/04), não reimplementa. Prova algébrica de que isso reproduz
`consumoOP()` do protótipo (`manufacturing-data.jsx:149`) para as 3 fórmulas de
`production_cost_type`:

| Tipo | `calculateCost(recipe)` | `calculateUnitCost = /total_quantity` | `× qtdProduzida` | Prototype (`consumoOP`, fator=qtdProduzida/total_quantity) |
|---|---|---|---|---|
| `percentage` | `ingredientes + ingredientes×extra/100` | `ingredientesUn×(1+extra/100)` | `ingredientesProduzidos×(1+extra/100)` | idêntico — `linhas` já escalam por `fator` |
| `per_unit` | `ingredientes + extra×total_quantity` | `ingredientesUn + extra` | `ingredientesProduzidos + extra×qtd` | idêntico — `r.extra × op.qtd` |
| `fixed` (default) | `ingredientes + extra` (flat) | `ingredientesUn + extra/total_quantity` | `ingredientesProduzidos + extra×fator` | idêntico — `r.extra × fator` |

Os três batem. Pest cobre com número concreto (dupla prova — proibicoes.md §REGRA MESTRE).

**Fora do escopo desta onda:** custo **congelado** (`op.final && custoSnap`) do protótipo — o
campo `custoSnap` não existe hoje em `transactions` (confirmado: só `mfg_production_cost` +
`mfg_production_cost_type`, que são o INSUMO da fórmula, não um total gravado). US-MANU-007
§9 é quem introduz `custoSnap` gravado no servidor. Até lá, todo custo aqui é **live**
(recalculado), igual à tela de Receitas.

## 2. Estrutura de arquivos

| Papel | Arquivo |
|---|---|
| Tela | `resources/js/Pages/Manufacturing/Report.tsx` |
| Charter | `resources/js/Pages/Manufacturing/Report.charter.md` |
| Casos | `resources/js/Pages/Manufacturing/Report.casos.md` |
| Controller | `Modules/Manufacturing/Http/Controllers/ProductionController@reportV2` |
| Service (agregação) | `Modules/Manufacturing/Services/ProductionService::reportByProduct` |
| Service (custo, reusado) | `Modules/Manufacturing/Services/RecipeBomService::calculateUnitCost` |
| Fonte de design | `prototipo-ui/cowork/manufacturing-producao.jsx::MfgRelatorio` |
| Teste | `Modules/Manufacturing/Tests/Feature/Wave30ReportInertiaTest.php` |

## 3. Smoke prod (R1)

```bash
curl -sv https://oimpresso.com/manufacturing/report 2>&1 | grep '^< HTTP'
```

Regressão adjacente — a rota Blade legada não pode mudar:

```bash
curl -sv https://oimpresso.com/manufacturing/report 2>&1 | grep '^< HTTP'
```

Depois do 200, screenshot obrigatório (proibicoes.md §"Claim sem evidência").

## 4. Rollback

Sem deploy: a tela nova é rota **aditiva** — nada aponta pra ela ainda exceto a aba "Relatório"
das telas v2 (Recipes/Index). Reverter o PR remove a rota e a aba volta a apontar pro Blade
legado (`/manufacturing/report`), que nunca saiu do ar. Sem migration — 100% leitura.
