---
last_validated: "2026-09-04"
slug: runbook-manufacturing-insumos
title: "RUNBOOK — /manufacturing/v2/insumos (Fabricação · Insumos)"
type: runbook
module: Manufacturing
page: /manufacturing/v2/insumos
component: resources/js/Pages/Manufacturing/Insumos.tsx
status: rascunho
updated_at: 2026-09-04
version: 0.1
owner: F
---

# RUNBOOK — `/manufacturing/v2/insumos` (Fabricação · Insumos)

> **F1 PLAN do MWART** ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)).
> US-MANU-005 — impacto reverso: quais receitas usam um insumo, e o que acontece com o custo
> delas se o preço de compra mudar. Fonte visual:
> `prototipo-ui/cowork/manufacturing-insumos.jsx::MfgInsumosView`.
>
> **É a única onda da família que nasce com backend NOVO.** O §18.3 do handoff era explícito:
> *"`usosDoInsumo()` é cálculo novo; precisa de um método no `RecipeBomService` com o JOIN de
> tenant e teste. Sem isso, a aba não sai."* O método agora existe.

## 1. A simulação de preço — desvio DECLARADO do protótipo, com razão

O protótipo calcula o custo simulado somando um delta por fora:

```
unitNovo = (custoTotal + qtd × preço × pct/100) / rendimento
```

**Esta tela não faz isso.** Ela troca o preço do insumo na cópia em memória e chama
`calculateCost()` de novo — a MESMA fórmula que a tela de Receitas e o Relatório usam.

Por quê: quando `production_cost_type` é `percentage`, o custo extra da receita é uma % dos
ingredientes. Se o ingrediente sobe, o extra sobe junto. O atalho aditivo do protótipo ignora
isso e **subestima** o novo custo exatamente nas receitas de custo percentual.

| `production_cost_type` | Atalho do protótipo | O que esta tela faz |
|---|---|---|
| `fixed` | igual | igual (o extra não varia) |
| `per_unit` | igual | igual (o extra depende da quantidade, não do preço) |
| `percentage` | **subestima** | recalcula: o extra acompanha o ingrediente |

Isso é desvio de design intencional, declarado no PR (`design-deviation`) e travado por teste
(UC-INS-02). Também é o que evita a armadilha do §5 das proibições: uma SEGUNDA fórmula de
custo na base.

## 2. O que é "insumo" aqui — e o estado que não ocorre

O app **não tem** flag de matéria-prima no produto; o protótipo tem uma lista `INSUMOS` curada
que não existe no dado real. A lista desta tela é derivada: **toda variação que aparece como
ingrediente em alguma receita do tenant**.

Consequência honesta: o estado "sem receita" do protótipo (`—` + não-clicável) **não ocorre**
com esta derivação — todo item tem ≥1 receita por construção. A tela mantém o caminho de
render (não quebra se a origem mudar), mas ele fica inalcançável hoje.

**Se [W] quiser o catálogo inteiro de produtos listado** (aí sim com "sem receita"), é outra
consulta e outra decisão de escopo — não é ajuste de código.

## 3. Estrutura de arquivos

| Papel | Arquivo |
|---|---|
| Tela | `resources/js/Pages/Manufacturing/Insumos.tsx` |
| Charter | `resources/js/Pages/Manufacturing/Insumos.charter.md` |
| Casos | `resources/js/Pages/Manufacturing/Insumos.casos.md` |
| Controller | `Modules/Manufacturing/Http/Controllers/RecipeController@insumos` |
| Service (novo) | `RecipeBomService::{usosDoInsumo,listInsumosComUso}` |
| Fonte de design | `prototipo-ui/cowork/manufacturing-insumos.jsx` |
| Teste | `Modules/Manufacturing/Tests/Feature/Wave33InsumosTest.php` |

## 4. Quando esta tela quebra (sintomas)

| Sintoma | Causa provável | Onde olhar |
|---|---|---|
| Lista vazia num business COM receitas | cadeia de tenant não resolveu (`products.business_id`) | `recipesDoTenantComIngredientes` |
| Estoque sempre 0 | `variation_location_details` sem linha pra essa variação | ficha do produto |
| "Maior peso" 100% em tudo | receita sem custo extra e com 1 ingrediente só — é correto, não é bug | — |
| Simulação não muda nada | `variacaoPct` chegou 0 (slider não propagou pro servidor) | payload do drawer |

## 5. Smoke prod (R1)

```bash
curl -sv https://oimpresso.com/manufacturing/v2/insumos 2>&1 | grep '^< HTTP'
```

Regressão adjacente (as rotas do módulo não mudam):

```bash
curl -sv https://oimpresso.com/manufacturing/recipe 2>&1 | grep '^< HTTP'
```

Depois do 200, screenshot + abrir um insumo e mexer no slider (a simulação é o ponto da tela).

## 6. Rollback

Rota aditiva, 100% leitura, sem migration. Reverter o PR remove a rota e a aba; nada mais no
módulo depende de `usosDoInsumo()`.
