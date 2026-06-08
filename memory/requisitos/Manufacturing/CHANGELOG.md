# Changelog

Formato: [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/) · [Semver](https://semver.org/).

## [0.4.0] - 2026-05-16 — Wave 18 RETRY SATURATION

### Added (D8 — FormRequests novas, ratio 4 → 7)

- `StoreIngredientGroupRequest` — primeira FormRequest pra `MfgIngredientGroup` (até Wave 17 só Recipe/Production tinham).
- `UpdateIngredientGroupRequest` — par PATCH.
- `DestroyRecipeRequest` — fecha CRUD Recipe (Store/Update/Destroy completo).

### Added (D1 — trait + Pest cross-tenant cobertos antes em Wave 18 base)

- `Concerns/AssertsBusinessChain` (já existente Wave 18) — Pest reflection + DB tests biz=1 vs biz=99 validados.

### Added (D5 — Customer Journey real biz=1)

- `Wave18ProductionJourneyTest` (já existente) — end-to-end Recipe + 2 Ingredients + BOM resolve + cost calc + summary.

### Added (D8 — tests novos Wave 18 RETRY)

- `Wave18RetryManufacturingSaturationTest` — smoke FormRequests novas + ratio Form/Controller ≥7.

### Changed

- `module.json`: adiciona `fsm_n_a:true` + razão (production_purchase usa flag `mfg_is_final` legacy, não FSM canônico ADR 0143).

## [0.3.0] - 2026-05-16 — Wave 18 SATURATION (base)

### Added

- `Concerns/AssertsBusinessChain` trait + adoção em `MfgRecipe` + `MfgRecipeIngredient`.
- `UpdateRecipeRequest` + `UpdateProductionRequest` FormRequests (ratio 2 → 4).
- Pest: `Wave18BusinessChainTraitTest`, `Wave18ProductionJourneyTest`, `Wave18FormRequestsTest`.

## [0.2.0] - 2026-05-16 — Wave 17 OTel instrumentation

### Added

- `ProductionService` + `RecipeBomService` envelopados em `OtelHelper::spanBiz`.
- `Wave17OtelInstrumentationTest` smoke.

## [0.1.0] - 2026-04-22

### Added

- Documentação inicial consolidada a partir do arquivo plano.
- Migrado para estrutura de pasta (README + ARCHITECTURE + SPEC + CHANGELOG + adr/).
