---
last_validated: "2026-11-17"
slug: runbook-manufacturing-producao
title: "RUNBOOK — /manufacturing/production (Fabricação · Ordens de produção)"
type: runbook
module: Manufacturing
page: /manufacturing/production
component: resources/js/Pages/Manufacturing/Index.tsx
status: rascunho
updated_at: 2026-11-17
version: 0.1
owner: F
---

# RUNBOOK — `/manufacturing/production` (Fabricação · Ordens de produção)

> **F1 PLAN do MWART** ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)).
> A tela **já existia** (Wave J, 2026-05) — este RUNBOOK nasce junto com a US-MANU-004, que é
> **emenda**, não tela nova: as 8 colunas do §4.5 do handoff "PROTÓTIPO OFICIAL - FABRICAÇÃO V1"
> (hoje são 5), o sufixo `fix`, o rodapé e o "Só finalizadas" como checkbox.
>
> Fonte visual: `prototipo-ui/cowork/Wagner/manufacturing-producao.jsx::MfgProducaoView`.

## 1. Custo "congelado" — o que ele é HOJE, com o dado que existe

O protótipo distingue `vivo` (recalculado do preço de insumo de hoje) de `congelado`
(`op.custoSnap`, gravado quando a ordem finaliza) e marca o congelado com o sufixo `fix`.

**No app, `custoSnap` NÃO EXISTE** — conferido: `transactions` tem `mfg_production_cost`,
`mfg_production_cost_type`, `mfg_wasted_units` e `mfg_is_final`, nenhum total congelado
separado. A US-MANU-007, que o introduziria, foi cancelada em 2026-10-13 (D1, ADR 0448): o custo
congelado passa a ser da ordem na Produção. Desde o lote 1.5 da auditoria (2026-11-12) o
`final_total` é calculado no servidor no `store` e no `update` (UC-OP-19); antes vinha do navegador.

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
| Fonte de design | `prototipo-ui/cowork/Wagner/manufacturing-producao.jsx::MfgProducaoView` |
| Teste | `Modules/Manufacturing/Tests/Feature/Wave32ProducaoColunasTest.php` |

## 4. Quando esta tela quebra (sintomas)

| Sintoma | Causa provável | Onde olhar |
|---|---|---|
| Coluna Produto vazia (`—`) em toda linha | ordem sem `purchase_line` (dado legado) ou variação sem produto | `ProductionService::listProductions` |
| `N ingredientes` sempre 0 | a variação produzida não tem receita (`mfg_recipes.variation_id`) | ficha da receita |
| "quem lançou" vazio | `transactions.created_by` aponta pra user removido | `users` |
| Custo unit. `R$ 0,00` com total > 0 | `purchase_lines.quantity` zerado — o guard está funcionando, o DADO é que está torto | a ordem legada |

## 5. Painel lateral da ordem (US-MANU-009, UC-OP-07)

Clicar numa ordem abre `_components/OrdemDrawer.tsx`. O detalhe vem da prop opcional
`ordem_detalhe` (`ProductionController@indexV2`, `ProductionService::detalheOrdem`), calculada só
quando a tela a pede. Ordem de outra empresa vem `null`; de local não permitido, 403 (UC-OP-22);
sem `view_purchase_price`, sem custo (`Support/OcultaCusto`).

Pela D1 (ADR 0448) a aba é consulta. O botão "Editar ordem" do rascunho e o "Nova produção" ainda
abrem o formulário Blade, consertado pela B1 do [W] enquanto existir; os botões são do lote 5
da auditoria.

## 6. Smoke prod (R1)

```bash
curl -sv https://oimpresso.com/manufacturing/production 2>&1 | grep '^< HTTP'
```

Sem cookie, `302` para `/login`. Regressão adjacente — a tela antiga no mesmo endereço:

```bash
curl -sv 'https://oimpresso.com/manufacturing/production?legacy=1' 2>&1 | grep '^< HTTP'
```

Logado, screenshot da lista e de um painel aberto.

## 7. Rollback

Reverter o PR devolve as 5 colunas. Sem migration, sem escrita: a tela é 100% leitura e o
`indexV2` só ganhou campos no payload.
