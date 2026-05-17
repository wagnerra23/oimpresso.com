# CHANGELOG — Modules/Woocommerce

> Integração unidirecional POS oimpresso → WooCommerce externo (categories/products/orders sync).

## [Unreleased] — Wave 25 POLISH (2026-05-16) — Saturação ≥92 D6/D9

### D9 Spans completos (cumulativo Wave 18+25)
- `Tests/Feature/Wave25SaturationTest.php` (13 cenários) — reflection + source-grep + Container resolve, ZERO hit API WooCommerce externa:
  - 6 spans canon `woocommerce.*` declarados em 3 Services (Sync/Reset/Authorization)
  - Span attributes documentados: business_id, user_id, sync_type, limit, offset
  - HealthCommand SYNC_STALE_DAYS=7 (alerta config)
  - HealthCommand 4 checks (schema/DI/creds/last-sync) + 3 exit codes (0/1/2)
  - HealthCommand fail-secure `--business-id` obrigatório (Tier 0 ADR 0093)
  - HealthCommand signature `--detail` (NUNCA `--verbose` Symfony reserved — .claude/rules/commands.md)
  - 6 spans esperados: sync.categories, sync.products, sync.orders, reset.categories, reset.products, auth.pode_executar
  - OtelHelper preserva exception (fail-loud)
  - 3 Sync methods contrato multi-tenant — `$businessId:int` primeiro param (Tier 0)
  - 2 Reset methods contrato multi-tenant — `$businessId:int` primeiro param (Tier 0)
  - 3 Services resolvidos via container (D4 reuse)
  - 3 Services importam `App\Util\OtelHelper` canon (zero duplicação)
  - module.json declara fsm_n_a:true (integração unidirecional preservada)

### D6 HealthCommand checks expandidos (validação Pest cobertura)
- Wave 25 Pest valida 4 checks WoocommerceHealthCommand documentados em PHPDoc:
  1. Schema woocommerce_sync_logs acessível
  2. DI resolve 3 Services canon
  3. Credenciais business.woocommerce_* cadastradas (warning não-bloqueante)
  4. Última sync ≤7 dias (stale alert)
- Exit codes 0=healthy / 1=degraded / 2=error verificados via source-grep

### Tier 0 IRREVOGÁVEIS preservados
- Contract HTTP WooCommerce REST API (Wave 16) — Wave 25 NÃO toca WoocommerceUtil legacy (1541 linhas), só metadata + reflection nos 3 Services novos
- ADR 0093 multi-tenant — `$businessId` explícito em TODO Service method validado via reflection
- ADR 0159 errata meta 97 realismo — Wave 25 mira ≥92 sem overengineering

## [Unreleased] — Wave 18 SATURATION (2026-05-16)

### Adicionado — D4 Services contrato estável
- `WoocommerceAuthorizationService` extraído (Wave 16) — preservado contrato `podeAcessarModulo` /
  `podeExecutarAcao` / `ensureModulo` / `ensureAcao`. Wave 18 envolveu `podeExecutarAcao` em
  `OtelHelper::span` ('woocommerce.auth.pode_executar') — span granular por permission key.
- `WoocommerceSyncService` (Wave 16) — assinatura `sincronizarCategorias`/`sincronizarProdutos`/
  `sincronizarOrders` preservada (zero breaking).
- `WoocommerceResetService` (Wave 16) — assinatura `resetarCategorias`/`resetarProdutos` preservada.

### Adicionado — D9 Observability SATURATION
- `WoocommerceSyncService` envolve 3 sync methods em `OtelHelper::span`:
  - `woocommerce.sync.categories`
  - `woocommerce.sync.products`
  - `woocommerce.sync.orders`
- `WoocommerceResetService` envolve 2 reset methods em `OtelHelper::span`:
  - `woocommerce.reset.categories`
  - `woocommerce.reset.products`
- `WoocommerceAuthorizationService` envolve `podeExecutarAcao` em `OtelHelper::span` —
  observability granular por permission (auditoria de quem pode o quê).
- `WoocommerceHealthCommand` criado: `php artisan woocommerce:health --business-id=N [--detail]`
  - 4 checks: schema `woocommerce_sync_logs`, DI resolve 3 Services, credenciais `business.woocommerce_*`,
    última sync ≤7 dias
  - Exit codes 0/1/2 (healthy/degraded/error)
  - Usa `--detail` (NUNCA `--verbose` — .claude/rules/commands.md)
  - Tier 0 fail-secure: `--business-id` obrigatório, sem fallback session()

### Saturação Pest
- `Modules/Woocommerce/Tests/Feature/Wave18SaturationTest.php` (9 specs):
  - 3 Services × container resolve (D4 contrato)
  - 3 Services × `use App\Util\OtelHelper` import (D9)
  - 3 spans Sync + 2 spans Reset existem no source
  - HealthCommand registrado + `--business-id` + `--detail` validados
  - OtelHelper no-op zero-cost path validado

### Marcado
- `module.json` agora declara `fsm_n_a:true` com razão documentada
  (integração unidirecional — estado vive no WooCommerce externo).

## Referências
- [ADR 0093](../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) Multi-tenant Tier 0
- [ADR 0155](../../memory/decisions/0155-module-grade-v3.md) Module Grade v3 (D4/D9)
- [ADR 0159](../../memory/decisions/0159-module-grade-v3-errata-meta-97-realismo.md) Errata meta 97
- [.claude/rules/commands.md](../../.claude/rules/commands.md) `--detail` NUNCA `--verbose`
