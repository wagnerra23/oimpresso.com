---
date: "2026-07-02"
hour: "22:13 BRT"
topic: "Correção Wagner — custo NÃO é greenfield no Estoque; a afirmação 'custo médio ponderado é gap' está STALE"
authors: [C]
related_adrs:
  - 0105-cliente-como-sinal-guiar-sem-mandar
  - 0121-oimpresso-modular-especializado-por-vertical
---

# Correção — custo do Estoque não é greenfield (2026-07-02)

## TL;DR

Wagner (dono do domínio, soberano — mesmo peso do precedente "locação é alucinação"): **"o custo já tem coisa pronta, estais desatualizado"**. Minha afirmação recorrente — herdada do estudo externo + mapa interno — de que **custo médio ponderado móvel é o "único gap" / "não há weighted-average cost"** está **STALE**. Não repetir. Antes de qualquer design da Onda 1 tratar custo como novo, **reverificar o mecanismo exato com Wagner/código**.

## O que EU verifiquei que já existe (origin/main, docs-only pass)

Cost está tratado em vários lugares — não é campo vazio:

| Onde | O que | Arquivo |
|---|---|---|
| **ComVis** | custo por m² de substrato (`preco_custo_m2` + `preco_venda_m2`) | `Modules/ComunicacaoVisual/Database/Migrations/2026_05_12_000010_create_cv_substratos_table.php` |
| **Compras** | `purchase_price` / `purchase_price_inc_tax` por linha (base do custo de entrada) | `Modules/Compras/Services/ComprasService.php` |
| **Manufacturing** | `averageProductionCost(businessId)` — média ponderada de custo de produção (widget dashboard) | `Modules/Manufacturing/Services/ProductionService.php:130` |
| **OficinaAuto** | `valor_unitario`/`valor_total` por item de OS | `Modules/OficinaAuto/Services/ServiceOrderItemService.php` |
| **UltimatePOS core** | `default_purchase_price`, `dpp_inc_tax`, subquery `last_purchased_price` | `variations` + `app/Utils/ProductUtil.php` |

## O que fica pendente de reverificação (NÃO assumir)

- Se existe (e onde) um **custo médio ponderado móvel unificado por produto** (a peça "Bloco K / CMV" que o plano externo vendeu como gap). Pode já existir num mecanismo que eu não localizei nesta passada, OU o que existe (custo por m²/substrato + purchase_price + média de produção) **já cobre** a necessidade real do Wagner. **Ele sabe; eu não pinei via grep.**
- **Ação obrigatória antes da Onda 1:** perguntar/verificar o mecanismo concreto de custo que o Wagner considera "pronto" ANTES de propor `stock_movements.unit_cost` + recompute de custo médio. Caso contrário eu reconstruo algo que já existe (viola "comparar e não duplicar").

## Onde a afirmação stale vive (corrigir na origem antes de virar plano/US)

- **Plano** `2026-07-02-plano-consolidacao-estoque.md` §3 ("falta custo médio ponderado móvel — único ponto onde oimpresso fica ATRÁS de Tiny/Bling") e §4 Onda 1 — está no branch da [#3672](https://github.com/wagnerra23/oimpresso.com/pull/3672) (NÃO mergeado). **Corrigir lá antes de mergear o plano.**
- **Mapa interno** `2026-07-02-mapa-interno-estoque-verticais.md` Frente A ("Custo 🟡 Parcial ... NÃO há weighted-average cost") — mesmo branch #3672.
- Estes NÃO estão em `main`, então não dá pra consertar num PR desta sessão; ficam anotados aqui como dívida pro dono do #3672.

## Precedente aplicado

Igual a "locação é alucinação" (ADR 0265): quando Wagner crava um fato do domínio que contradiz o que um estudo/agente inferiu, **o veredito dele vence** e o material inferido vira stale-a-corrigir, não fonte.
