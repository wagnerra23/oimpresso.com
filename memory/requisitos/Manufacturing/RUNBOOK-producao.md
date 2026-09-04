---
last_validated: "2026-09-04"
slug: runbook-manufacturing-producao
title: "RUNBOOK — /manufacturing/v2/production (Fabricação · Ordens de produção)"
type: runbook
module: Manufacturing
page: /manufacturing/v2/production
component: resources/js/Pages/Manufacturing/Index.tsx
status: rascunho
updated_at: 2026-09-04
version: 0.1
owner: F
---

# RUNBOOK — `/manufacturing/v2/production` (Fabricação · Ordens de produção)

> **F1 PLAN do MWART** ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)).
> A tela **já existia** (Wave J, 2026-05) — este RUNBOOK nasce junto com a US-MANU-004, que é
> **emenda**, não tela nova: as 8 colunas do §4.5 do handoff "PROTÓTIPO OFICIAL - FABRICAÇÃO V1"
> (hoje são 5), o sufixo `fix`, o rodapé e o "Só finalizadas" como checkbox.
>
> Fonte visual: `prototipo-ui/cowork/manufacturing-producao.jsx::MfgProducaoView`.

## 1. Custo "congelado" — o que ele é HOJE, com o dado que existe

O protótipo distingue `vivo` (recalculado do preço de insumo de hoje) de `congelado`
(`op.custoSnap`, gravado quando a ordem finaliza) e marca o congelado com o sufixo `fix`.

**No app, `custoSnap` NÃO EXISTE** — conferido: `transactions` tem `mfg_production_cost`,
`mfg_production_cost_type`, `mfg_wasted_units` e `mfg_is_final`, nenhum total congelado
separado. Quem introduz o `custoSnap` é a **US-MANU-007** (§9 do handoff).

Então esta onda usa o que existe, e declara o que está fazendo:

| O protótipo mostra | O que esta tela mostra | Por quê |
|---|---|---|
| `congelado` quando final | `transactions.final_total` | é o valor **gravado na criação da ordem** — para uma ordem finalizada ele É o custo daquela data |
| `vivo` quando rascunho | `transactions.final_total` também | recalcular aqui seria inventar uma **segunda** fórmula de custo na tela (a 1ª vive em `RecipeBomService`) — §5 das proibições |
| sufixo `fix` quando congelado | sufixo `fix` quando `mfg_is_final=1` | é a marca que o DoD (R-21) pede, com o `title` verbatim |

**Consequência honesta:** o rodapé soma `final_total` das ordens listadas — valor gravado, não
recalculado. Isso é DIFERENTE do Relatório (US-MANU-002), que recalcula pelo preço de hoje. As
duas telas respondem perguntas diferentes e o rodapé de cada uma diz qual.

## 2. Custo unitário — a única conta nova, e o guard

`custo_unitario = final_total / quantidade` da linha de compra, com **guard de divisão por
zero** (quantidade 0 ⇒ 0.0, nunca `INF`/`NaN`). Nenhuma outra conta entra.

## 3. Estrutura de arquivos

| Papel | Arquivo |
|---|---|
| Tela | `resources/js/Pages/Manufacturing/Index.tsx` |
| Charter | `resources/js/Pages/Manufacturing/Index.charter.md` |
| Casos | `resources/js/Pages/Manufacturing/Index.casos.md` |
| Controller | `Modules/Manufacturing/Http/Controllers/ProductionController@indexV2` |
| Service (payload) | `Modules/Manufacturing/Services/ProductionService::listProductions` |
| Badge de situação | `resources/js/Components/shared/StatusBadge.tsx` (domínio `producao`) |
| Fonte de design | `prototipo-ui/cowork/manufacturing-producao.jsx::MfgProducaoView` |
| Teste | `Modules/Manufacturing/Tests/Feature/Wave32ProducaoColunasTest.php` |

## 4. Quando esta tela quebra (sintomas)

| Sintoma | Causa provável | Onde olhar |
|---|---|---|
| Coluna Produto vazia (`—`) em toda linha | ordem sem `purchase_line` (dado legado) ou variação sem produto | `ProductionService::listProductions` |
| `N ingredientes` sempre 0 | a variação produzida não tem receita (`mfg_recipes.variation_id`) | ficha da receita |
| "quem lançou" vazio | `transactions.created_by` aponta pra user removido | `users` |
| Custo unit. `R$ 0,00` com total > 0 | `purchase_lines.quantity` zerado — o guard está funcionando, o DADO é que está torto | a ordem legada |

## 5. Smoke prod (R1)

```bash
curl -sv https://oimpresso.com/manufacturing/v2/production 2>&1 | grep '^< HTTP'
```

Regressão adjacente (Blade legado não muda):

```bash
curl -sv https://oimpresso.com/manufacturing/production 2>&1 | grep '^< HTTP'
```

Depois do 200, screenshot obrigatório — a tela mudou de 5 pra 8 colunas.

## 6. Rollback

Reverter o PR devolve as 5 colunas. Sem migration, sem escrita: a tela é 100% leitura e o
`indexV2` só ganhou campos no payload.
