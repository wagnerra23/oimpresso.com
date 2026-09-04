---
id: resources-js-pages-manufacturing-index-charter
page: /manufacturing/v2/production
component: resources/js/Pages/Manufacturing/Index.tsx
related_prototype: n/a (herda PT-01 Lista; segue o Padrão de Tela)
bundle_source: manufacturing-page.jsx
page_id: manufacturing-index
status: draft
owner: wagner
created: 2026-05-16
wave: J
runbook: memory/requisitos/Manufacturing/RUNBOOK-producao.md
casos: resources/js/Pages/Manufacturing/Index.casos.md
related_us: [US-MANU-004]
---

# Charter — Manufacturing/Index.tsx

## Mission
Listar ordens de produção (production_purchase) do business ativo em UX Inertia/React, em coexistência com Blade legacy `/manufacturing/production` durante migração MWART.

## Goals
- G1: PageHeader + summary cards (total / final / pendente / valor)
- ~~G2: Tabela enxuta com 5 colunas (ref, data, local, total, status)~~ → **G2 (US-MANU-004,
  2026-09-04): 8 colunas do §4.5** — Data · Referência · Local · Produto (com
  `N ingredientes · quem lançou`) · Qtd · Custo total (sufixo `fix` na finalizada) ·
  Custo unit. · Situação
- G3: EmptyState honesto quando sem dados
- G4: Multi-tenant Tier 0 — todas queries via ProductionService scoped por business_id
- G5 (US-MANU-004): filtros de **leitura** — local, intervalo de data e o checkbox
  "Só finalizadas", que governa o mesmo filtro do KPI clicável. São controles que only
  **filtram a listagem**; nenhum deles escreve. O Non-Goal de CRUD abaixo segue intacto:
  create/edit/destroy continuam no Blade legado.
- G6 (US-MANU-004): situação vem do `StatusBadge` compartilhado (domínio `producao`) — o
  `StatusPill` local foi removido

## Non-Goals (Wave J)
- Não migrar CRUD completo (create/edit/destroy) — Blade legacy mantém
- Não fazer Kanban de produção — fica pra Wave futura
- Não migrar Recipes nem BOM (RecipeBomService) — escopo separado
- Não tocar rota legacy `/manufacturing/production` — coexistência

## UX targets
- Responsivo 1280px+ (cliente piloto ROTA LIVRE biz=4 monitor pequeno)
- PT-BR em todos labels
- Empty state com link pra rota legacy enquanto migração não termina

## Anti-hooks
- Não usar `withoutGlobalScopes` no Service
- Não permitir UPDATE direto em transactions (FSM trait Sells/Repair não cobre Manufacturing ainda)
- Não duplicar queries do ProductionController existente — Service centraliza
- Não promover charter pra `live` sem Wagner aprovar UX screenshot

## Data flow
```
Controller@indexV2  →  ProductionService::listProductions(biz, filters)   # + eager purchase_lines/location
                    →  ProductionService::enrichProductionRows(ordens, biz)  # produto · nº ingredientes ·
                    →  ProductionService::summary(biz)                       # quem lançou · qtd · custo unit.
                    →  Inertia::render('Manufacturing/Index', {productions, summary})
```

> **Custo (US-MANU-004):** as colunas de dinheiro mostram `transactions.final_total` — o valor
> GRAVADO na criação da ordem —, nunca um recálculo. Recalcular aqui criaria uma segunda
> fórmula de custo na base (a primeira é `RecipeBomService`, usada pelo Relatório). O sufixo
> `fix` marca a ordem finalizada. Detalhe e a razão em `RUNBOOK-producao.md §1`.

## Rota
- `GET /manufacturing/v2/production` — nova (Inertia)
- `GET /manufacturing/production` — legacy Blade preservada (ProductionController@index)

## Próximos passos
- ~~Wire-up filtros (location, date range) com Inertia partial reload~~ — **feito** (o
  `applyFilter` faz partial reload com `only:[productions,summary,filters]`)
- ~~Charter MWART completo com RUNBOOK~~ — **feito em 2026-09-04**:
  `memory/requisitos/Manufacturing/RUNBOOK-producao.md` (o nome citado antes,
  `RUNBOOK-production-index.md`, nunca existiu)
- `Inertia::defer` segue **não aplicado de propósito** — `indexV2` documenta o rollback do
  Wave L/W7 (PR #963), em que defer quebrava o initial render desta tela
- US-MANU-007 introduz o `custoSnap` gravado; quando existir, a coluna de custo desta tela
  pode passar a distinguir congelado de vivo (hoje não dá — o campo não existe)
