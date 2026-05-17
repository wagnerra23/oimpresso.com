# Manufacturing — Changelog

## [Wave 27 — 2026-05-17] POLISH final ≥90 (88 → 90, +2pp)

### D9.a OTel saturation (2 spans novos)
- `RecipeBomService::listForDropdown` — span `manufacturing.recipe.list_for_dropdown`
  (hot-path form Recipe + Production carregam dropdown). Atributo `by_variation_id`.
- `ProductionService::averageProductionCost` — NOVO método com span
  `manufacturing.production.average_cost`. Útil em widgets dashboard.
- Zero-cost OTel quando `otel.enabled=false` (default Hostinger).

### D5 Customer Journey biz=1 (BOM 7 ingredients)
- `Tests/Feature/Wave27ManufacturingPolishTest.php` — receita BOM 7 ingredientes
  (Wave 25 cobria 5). Multi-tenant biz=1 vs biz=99 isolation validado.
- Cobertura `averageProductionCost(1)` retorna float + `averageProductionCost(99)` = 0.0

### D2 Pest expand (8 cenários novos)
- 8 testes Wave 27 cobrindo: spans novos (Reflection source), customer journey real,
  cross-tenant Tier 0 (biz=99 vazio em todos os novos métodos).

### Tier 0 IRREVOGÁVEIS preservadas
- ⛔ Multi-tenant Tier 0 (ADR 0093) — isolation biz=1 vs biz=99 todos novos testes.
- ⛔ NUNCA biz=4 cliente real (ADR 0101). `mfg_recipes/ingredients` sem `business_id`
  direto preservado (chain via JOIN products).
- ⛔ PT-BR em comentários. PHP identifiers em inglês. SQLite skip preservado.
- ⛔ OtelHelper canônico (`App\Util\OtelHelper`) — NUNCA OpenTelemetry vendor direto.

## [Wave 25 — 2026-05-16] POLISH ≥88 (77 → 88, +11pp)

### D1 Entities / trait companion (18 → 23)
- `Concerns/HasManufacturingProductChain.php` — trait companion ao
  `AssertsBusinessChain` (Wave 18). Oferece helpers de **agregação** via chain
  product/variation:
  - `countForBusinessChain(int $businessId): int` — contagem rápida sem hidratar Models
  - `idsForBusinessChain(int $businessId, int $limit=1000): array` — IDs pra bulk ops
  - Detecção automática: Models com `variation_id` direto (MfgRecipe) vs descendant
    via `mfg_recipe_id` (MfgRecipeIngredient)
  - Multi-tenant Tier 0 IRREVOGÁVEL ({@see ADR 0093}). Caller injeta biz_id.
  - Diferente de `AssertsBusinessChain` (filtro/check unitário); foco em **agregação**.

### D2 Pest expand BOM + recipes (Wave 18 inicial → Wave 25 saturado)
- `Tests/Feature/Wave25BomRecipesExpandedTest.php` — Pest robusto:
  - BOM com 5 ingredientes (Wave 18 cobriu só 2)
  - `calculateCost` com `waste_percent=10` aplicado sobre ingredientes
  - Trait `HasManufacturingProductChain` Pest: existence + métodos + biz=1 vs biz=99
  - Cross-tenant Tier 0: count biz=99 = 0 mesmo com data biz=1 persistida
  - 4 cenários adicionais sobre o journey base Wave 18.

### D5 Customer Journey biz=1 receita real
- `Tests/Feature/Wave25CustomerJourneyBiz1Test.php` — receita realista
  "Camiseta Personalizada" (Modules/Vestuario real-world):
  - 3 ingredientes representativos: tecido_dryfit, tinta_silk, etiqueta_marca
  - waste_percent=5 (desperdício tecido típico), final_price=R$ [redacted Tier 0]
  - `ProductionService::summary(1)` valida estrutura agregada coerente
  - Cross-tenant biz=99 zero-vector
  - Append-only cleanup (delete só rows que criamos via marker)
  - 4 cenários cobrindo journey real-world.

### Notas Tier 0 IRREVOGÁVEIS preservadas
- ⛔ Multi-tenant Tier 0 (ADR 0093): isolation biz=1 vs biz=99 validado em todos
  os novos testes. NUNCA biz=4 cliente real ({@see ADR 0101}).
- ⛔ Tabelas `mfg_recipes` / `mfg_recipe_ingredients` continuam SEM coluna
  `business_id` direta — chain via JOIN products preservado.
- ⛔ Pattern `AssertsBusinessChain` mantido; novo trait é COMPANION (não substituto).
- ⛔ PT-BR em comentários. Identificadores PHP em inglês.
- ⛔ SQLite skip preservado (`markTestSkipped` ADR 0101 conforme Wave 18).

## Histórico anterior

Wave 17/18 cobriram: scaffold, multi-tenant base, RecipeBom/Production services,
OtelHelper canon, BusinessChain trait inicial, ProductionJourney Pest (2 ingredients),
LGPD security, FormRequests Store+Update.
