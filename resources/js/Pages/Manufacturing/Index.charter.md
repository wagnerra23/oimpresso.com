---
page: /manufacturing/v2/production
component: resources/js/Pages/Manufacturing/Index.tsx
related_prototype: n/a (herda PT-01 Lista; segue o Padrão de Tela)
page_id: manufacturing-index
status: draft
owner: wagner
created: 2026-05-16
wave: J
---

# Charter — Manufacturing/Index.tsx

## Mission
Listar ordens de produção (production_purchase) do business ativo em UX Inertia/React, em coexistência com Blade legacy `/manufacturing/production` durante migração MWART.

## Goals
- G1: PageHeader + summary cards (total / final / pendente / valor)
- G2: Tabela enxuta com 5 colunas (ref, data, local, total, status)
- G3: EmptyState honesto quando sem dados
- G4: Multi-tenant Tier 0 — todas queries via ProductionService scoped por business_id

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
Controller@indexV2  →  ProductionService::listProductions(biz, filters)
                    →  ProductionService::summary(biz)
                    →  Inertia::render('Manufacturing/Index', {productions, summary})
```

## Rota
- `GET /manufacturing/v2/production` — nova (Inertia)
- `GET /manufacturing/production` — legacy Blade preservada (ProductionController@index)

## Próximos passos (não Wave J)
- Wire-up filtros (location, date range) com Inertia partial reload
- Migrar para `Inertia::defer` quando queries crescerem (ADR runbook-inertia-defer-pattern)
- Charter MWART completo com RUNBOOK em `memory/requisitos/Manufacturing/RUNBOOK-production-index.md`
