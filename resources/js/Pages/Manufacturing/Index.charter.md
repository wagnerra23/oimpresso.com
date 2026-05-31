---
page_id: manufacturing/index
status: draft
owner: Wagner
created: 2026-05-16
wave: J
---

# Charter — Manufacturing/Index.tsx

## Mission
Listar ordens de produção (production_purchase) do business ativo em UX Inertia/React, em coexistência com Blade legacy `/manufacturing/production` durante migração MWART.

## Goals
- G1: PageHeader (DS) + KPI strip (total / finalizadas / pendentes / valor); "Finalizadas" é card-filtro clicável
- G2: Tabela tokenizada com 5 colunas (ref, data, local, total, status); status dot-style sem bg-fill (PT-01)
- G3: EmptyState honesto (vazio vs busca-sem-resultado) com CTA "Nova produção"
- G4: Multi-tenant Tier 0 — todas queries via ProductionService scoped por business_id
- G5: Montada no AppShellV2 (sidebar/breadcrumb/dark mode) + componentes shared canon (PageHeader/KpiCard/EmptyState)
- G6: CTA "Nova produção" habilitado → rota legacy de create `/manufacturing/production/create`
- G7: Filtros (local + intervalo de data + finalizadas) via Inertia partial reload (`only: [productions, summary, filters]`)

## Non-Goals (Wave J)
- Não migrar CRUD completo (create/edit/destroy) — Blade legacy mantém; CTA aponta pro create legacy
- Não fazer Kanban de produção — fica pra Wave futura
- Não migrar Recipes nem BOM (RecipeBomService) — escopo separado
- Não tocar rota legacy `/manufacturing/production` — coexistência (rota nova é `/manufacturing/v2/production`)
- Não migrar a criação pra Inertia (drawer PT-02) — Slot 6 pendente

## UX targets
- Responsivo 1280px+ (cliente piloto ROTA LIVRE biz=4 monitor pequeno); KPI grid colapsa 4→2→1
- PT-BR em todos labels
- SÓ tokens DS (bg-card / text-foreground / text-muted-foreground / border-border); zero cor crua gray/green/yellow
- Empty state com CTA pra criar; quando filtro ativo, oferece "Limpar filtros"

## Anti-hooks
- Não usar `withoutGlobalScopes` no Service
- Não permitir UPDATE direto em transactions (FSM trait Sells/Repair não cobre Manufacturing ainda)
- Não duplicar queries do ProductionController existente — Service centraliza
- Não promover charter pra `live` sem Wagner aprovar UX screenshot
- Não usar `#hex`/`oklch()` inline nem classes `gray/green/yellow/sky/zinc-NNN` cruas (status usa emerald/amber dot, alinhado a Repair/Index)

## Data flow
```
Controller@indexV2  →  ProductionService::listProductions(biz, filters)
                    →  ProductionService::summary(biz)
                    →  BusinessLocation::forDropdown(biz)            // opções do filtro
                    →  Inertia::render('Manufacturing/Index', {productions, summary, business_locations, filters})
```

## Rota
- `GET /manufacturing/v2/production` — nova (Inertia) — registrada em `Modules/Manufacturing/Routes/web.php` (name `manufacturing.production.v2.index`); fora do `Route::resource` pra não colidir com `/production/{production}`
- `GET /manufacturing/production` — legacy Blade preservada (ProductionController@index)
- `GET /manufacturing/production/create` — legacy Blade (alvo do CTA "Nova produção")

## Estado (board 2026-05-30 — uplift 50 Developing → ≥70)
Esqueleto Wave J (tabela hand-rolled + cores cruas + sem AppShellV2 + CTA disabled) reescrito no padrão PT-01:
AppShellV2 + PageHeader + KpiCard + EmptyState (todos shared), tabela tokenizada, CTA habilitado, filtros wired.
Rota v2 registrada (antes era método órfão) e `indexV2` passou a enviar `business_locations` + `filters`.

## Próximos passos (não Wave J)
- Slot 6 (drawer 760px PT-02) pra criar produção em Inertia em vez de redirecionar pro Blade legacy
- Migrar para `Inertia::defer` quando queries crescerem (ADR runbook-inertia-defer-pattern)
- Charter MWART completo com RUNBOOK em `memory/requisitos/Manufacturing/RUNBOOK-production-index.md`
- Pest GUARD: smoke `/manufacturing/v2/production` + isolation biz=1 vs biz=4 + filtro is_final
