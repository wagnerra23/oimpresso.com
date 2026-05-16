# BRIEFING — Modules/Manufacturing

> **Estado consolidado 1-pager** — atualizado por PR mergeado conforme skill `brief-update` (Tier B).
> Última atualização: 2026-05-16 (Wave Massive — FSM canon via OficinaAuto).

## O que é

Módulo Manufacturing herdado UltimatePOS — gestão de **receitas/BOM (Bill of Materials)** + ordem de produção (`production_purchase`) + custeio dinâmico via chain `Variation→Product`. Modular monolith nWidart. Multi-tenant indireto (chain `products.business_id`).

## Onde é usado hoje

- **Modules/OficinaAuto** (prod Martinho Caçambas) — pivot FSM canon vincula serviços OS a recipes para custeio peças/insumos
- **Modules/ComunicacaoVisual** (em construção) — produção banner/adesivo com BOM (substrato + tinta + tempo)
- Núcleo: qualquer biz com `manufacturing_module` na assinatura

## Capacidades atuais (estado real)

| Capacidade | Status | Onde |
|---|---|---|
| CRUD Recipe + Ingredients + IngredientGroup | ✅ legacy estável | `RecipeController`, `MfgRecipe`, `MfgRecipeIngredient` |
| Production Order (`production_purchase`) | ✅ legacy | `ProductionController` |
| Custo dinâmico (ingredientes + waste% + production cost) | ✅ legacy | `ManufacturingUtil::getRecipeTotal` |
| Custo unitário Service-extracted | 🟢 **NOVO 2026-05-16** | `Services/RecipeBomService` |
| Multi-tenant isolation Pest | ✅ Wave A | `Tests/Feature/MultiTenantIsolationTest` |
| BOM integrity Pest | ✅ Wave A | `Tests/Feature/RecipeBomIntegrityTest` |
| Smoke routes Pest | ✅ Wave A | `Tests/Feature/SmokeRoutesTest` |
| Scaffold Pest (Module::find + Route::has) | 🟢 **NOVO 2026-05-16** | `Tests/Feature/ScaffoldManufacturingTest` |
| Frontend Inertia/React | ❌ pendente | só Blade legacy hoje |
| Charter páginas Inertia | ❌ N/A enquanto MWART não iniciar | — |

## Gaps catalogados (Wave Massive 2026-05-16)

- **D1.b** — ainda pendente (escopo Wave seguinte)
- **D3 5/15** — coverage tests parcial; foco próximo: produção + custeio per_unit/percentage edge cases
- **D4 3/20** — ratio Service/Controller: 1 Service extraído nesta wave (RecipeBomService), restam ~6 Controllers fat candidatos a Service extract

## Decisões canônicas relacionadas

- [ADR 0011](../../decisions/0011-alinhamento-padrao-jana.md) Padrão Jana/Repair/Project
- [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) Multi-tenant Tier 0 (chain `products.business_id` aplicável aqui)
- [ADR 0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md) Tests biz=1, nunca biz=cliente real (biz=4 Larissa proibido aqui)

## Próximos passos sugeridos

1. Extrair lógica `ProductionController::store` pra `Services/ProductionOrderService` (D4.a +1)
2. Pest coverage `RecipeBomService::calculateCost` + edge cases per_unit/percentage (D3 +3)
3. Migração MWART avaliada quando OficinaAuto consumir BOM via UI Inertia (decisão fora Wave Massive)

## Nota atual

**48/100 (Médio)** — pivot 2026-05-16 (Wave Massive). Esta wave entrega: 1 Service novo + 1 Pest scaffold + BRIEFING. Próxima reavaliação após batch tests + 2º Service extracted.
