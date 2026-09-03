---
id: requisitos-manufacturing-briefing
module: Manufacturing
status: parcial
status_nota: "Legacy UltimatePOS estável (recipes/BOM + ordens de produção) + migração Inertia parcial — 2 páginas: a lista de produções (Wave J) e a consulta de receitas em /manufacturing/recipe (Wave 29). Sem pilot dedicado próprio; provê custeio/BOM."
updated_at: "2026-09-02"
owner: W
related_adrs:
  - 0011-alinhamento-padrao-jana
  - 0093-multi-tenant-isolation-tier-0
  - 0101-tests-business-id-1-nunca-cliente
---

# BRIEFING — Modules/Manufacturing

> **Estado consolidado 1-pager** — atualizado por PR mergeado conforme skill `brief-update` (Tier B).
> Refresh de frescor 2026-07-18 (briefing↔código). Update material anterior: 2026-05-16 (Wave Massive — FSM canon via OficinaAuto).

## O que é

Módulo Manufacturing herdado UltimatePOS — gestão de **receitas/BOM (Bill of Materials)** + ordem de produção (`production_purchase`) + custeio dinâmico via chain `Variation→Product`. Modular monolith nWidart. Multi-tenant indireto (chain `products.business_id`).

**Backend:** `Modules/Manufacturing/` · **Frontend:** Blade legacy + 2 páginas Inertia — `Pages/Manufacturing/Index.tsx` (produções) e `Pages/Manufacturing/Recipes.tsx` (consulta de receitas, servida em `/manufacturing/recipe`).

## Sem nova capacidade desde jun/2026 (honesto)

Nenhuma capacidade **de negócio** nova entrou na janela de refresh. As mudanças foram **cosméticas/estruturais**, não de feature:

- **#4109** — declara o Padrão de Tela (PT-01 Lista) na página v2 (design, não capacidade).
- **#3903** — perf D-14 (partial reload) na lista admin/core existente — filtros re-buscam só o que muda.
- **#2599** — backfill de frontmatter dos charters (docs).
- **#3660** — limpeza de marcadores de conflito em CHANGELOG/README (docs).

A capacidade de negócio real (CRUD receitas, ordens de produção, custeio) segue a mesma de mai/2026.

## Correção de frescor (briefing estava STALE)

A versão anterior afirmava "Frontend Inertia/React ❌ pendente" e "Charter páginas Inertia ❌ N/A". **Ambas ficaram desatualizadas** — a migração MWART Wave J já landou:

- Existe `resources/js/Pages/Manufacturing/Index.tsx` — lista de produções (`production_purchase`) em Inertia/React, padrão PT-01.
- Rota `GET /manufacturing/v2/production` → `ProductionController@indexV2` → `ProductionService` (scoped por `business_id`, Tier 0 ADR 0093), **coexiste** com Blade legacy `/manufacturing/production`.
- Charter existe: `Index.charter.md` (`status: draft`, page_id `manufacturing-index`).

## Onde é usado (reverificado por grep 2026-09-03)

- **Modules/OficinaAuto** e **Modules/ComunicacaoVisual** — **NÃO consomem** `RecipeBomService`/`ProductionService`/`MfgRecipe`/`ManufacturingUtil`. Varredura (`Manufacturing|mfg_recipe|MfgRecipe`, case-insensitive) nos dois módulos: **0 arquivos**. O claim herdado do briefing anterior ("consumo de BOM/custeio afirmado") era falso — corrigido aqui, não repetido.
- Núcleo: qualquer biz com `manufacturing_module` na assinatura (não reverificado — é o pacote UltimatePOS, não um consumo de código).

## Capacidades atuais (estado real — pelo código)

| Capacidade | Status | Onde |
|---|---|---|
| CRUD Recipe + Ingredients + IngredientGroup | ✅ legacy estável | `RecipeController`, `MfgRecipe`, `MfgRecipeIngredient` |
| Production Order (`production_purchase`) | ✅ legacy | `ProductionController` |
| Custo dinâmico (ingredientes + waste% + production cost) | ✅ legacy | `ManufacturingUtil::getRecipeTotal` |
| Custo unitário Service-extracted | ✅ | `Services/RecipeBomService` |
| Lista produções Inertia v2 (MWART Wave J) | 🟢 **presente (era ❌ no briefing)** | `Pages/Manufacturing/Index.tsx` + `ProductionController@indexV2` + `ProductionService::listProductions/summary` |
| KPIs dashboard (window / custo médio) | ✅ código presente | `ProductionService::windowKpis`, `averageProductionCost` |
| Observabilidade OTel (spans por biz) | ✅ | `OtelHelper::spanBiz` em `ProductionService`/`RecipeBomService` |
| Log LGPD com PiiRedactor | ✅ | `ProductionService::logProductionEvent` |
| Multi-tenant isolation Pest | ✅ | `Tests/Feature/MultiTenantIsolationTest` |
| BOM integrity + Smoke routes + Scaffold Pest | ✅ | `RecipeBomIntegrityTest`, `SmokeRoutesTest`, `ScaffoldManufacturingTest` |
| **Consulta de receitas Inertia** (KPIs · busca · drawer de custo · ficha PT-07) | 🟢 **novo (Wave 29)** | `Pages/Manufacturing/Recipes.tsx` + `RecipeController@index` + `RecipeBomService::listRecipesWithCost` |
| Charter páginas Inertia | 🟡 2 em draft (não `live`) | `Index.charter.md` · `Recipes.charter.md` |

## Gaps catalogados

- **Charter draft → live** — `Index.charter.md` segue `status: draft`; promover exige Wagner aprovar UX screenshot (anti-hook do charter).
- ~~**Cobertura Spatie permissions** — `R-MANU-001..005` no SPEC ainda com `_lacuna_`~~ — **fechado em 2026-09-03**: `Modules/Manufacturing/Tests/Feature/PermissionsTest.php` criado (R-MANU-002/003/005 por HTTP real; R-MANU-001 já estava em `MultiTenantIsolationTest`, linha do SPEC só desatualizada). **Achado durante o fix:** R-MANU-004 (`manufacturing.edit_recipe`) protege uma rota que não existe — `UpdateRecipeRequest` não está wired a nenhum PUT/PATCH (`Route::resource(...)->except('edit','update')`). Fica registrado no SPEC, não escondido.
- ~~**US-MANU** — SPEC sem user stories escritas~~ — **fechado em 2026-09-02**: `US-MANU-001` foi escrita a partir do handoff "PROTÓTIPO OFICIAL - FABRICAÇÃO V1" (§2 + §17), com DoD e `**Testado em:**` ancorados.
- **MWART parcial** — migraram a lista de produções (Wave J) e a **consulta** de receitas (Wave 29). Seguem Blade: create/edit/destroy da receita, o editor de ingredientes, o formulário de ordem, relatório e configurações. A tela nova aponta pra elas em vez de duplicá-las.
- **Aba Insumos não existe** — o handoff (§18.3) declara que `usosDoInsumo` é cálculo novo sem backend: "sem isso, a aba não sai".
- **Atualizar preço de venda em massa não implementado** — §18.1 proíbe o `custo × 2` do protótipo, e a regra de markup real não foi decidida. É Tier 0 de valor.

## Decisões canônicas relacionadas

- [ADR 0011](../../decisions/0011-alinhamento-padrao-jana.md) Padrão Jana/Repair/Project
- [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) Multi-tenant Tier 0 (chain `products.business_id` aplicável aqui)
- [ADR 0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md) Tests biz=1, nunca biz=cliente real (biz=4 Larissa proibido aqui)

## Próximos passos sugeridos

1. ~~Reverificar (grep) se OficinaAuto/ComunicacaoVisual realmente consomem `RecipeBomService`/`ProductionService`~~ — **fechado 2026-09-03**: reverificado, 0 arquivos, claim removido (ver seção acima).
2. `PermissionsTest` fechando `R-MANU-001..005` (a US-MANU-001 já foi escrita em 2026-09-02).
3. Promover os charters draft → live após screenshot aprovado por Wagner (agora são dois: `Index` e `Recipes`). Smoke autenticado de `/manufacturing/recipe` (nova) e `?legacy=1` (rollback) feito 2026-09-03 — 200, sem erro de console, os dois renderizam a tela certa; falta só o `smoke:` datado no charter + aprovação do screenshot.
4. Migração MWART do CRUD/Recipes avaliada quando OficinaAuto consumir BOM via UI Inertia (segue sem consumo — item 1 acima).

## Nota atual

**?/100 (stale)** — última medição registrada **48/100** em 2026-05-16 (Wave Massive). O código evoluiu depois (Wave J v2 list + Wave 14/17/26/27 observ./dashboard/LGPD), então o 48 já não reflete o estado. **Reavaliar via `php artisan module:grade Manufacturing --detail`** (CT 100 — não medido nesta sessão; anti-fabricação: sem número inventado).

---
**Atualizado:** 2026-09-02 — a consulta de receitas entra em `/manufacturing/recipe` (Wave 29,
porte do handoff "PROTÓTIPO OFICIAL - FABRICAÇÃO V1"). O dono vivo da nota do módulo é o
`Module Grades Gate`, que a publica no corpo de cada PR — este briefing aponta pra ele em vez
de repetir o número, que apodreceria aqui. [CC]
