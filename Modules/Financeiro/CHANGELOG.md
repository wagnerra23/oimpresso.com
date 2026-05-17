# CHANGELOG — Modules/Financeiro

Convenção: [Keep a Changelog](https://keepachangelog.com/) + [SemVer](https://semver.org/).

## [Unreleased] — Wave 18 RETRY (2026-05-16)

### Added (saturação 68→97 — governance module-grade-v3)

- **D4 SoC saturação granular** — `Repositories/BaixaRepository.php` extraído (4 métodos canônicos: `listarPaginado`, `totaisPorTipoPeriodo`, `historicoRecente`, `acharPorIdempotencyKey`) com type hints `businessId:int` 1º param + singleton no Provider. Consumers futuros: `FluxoCaixaService`, `FinanceiroHealthCommand`, `DashboardController.calcularKpis`, `UnificadoService`.
- **D8 FormRequests 5° + 6°** — `Http/Requests/StoreBaixaRequest.php` (criação manual de baixa via cockpit, `Rule::in` meios pagamento, helpers tipados `meioPagamento()` + `valorEfetivo()`) + `Http/Requests/UpdateAccountRequest.php` (PATCH semantics, `sometimes` em todos campos, suporta wiring `rb_gateway_credential_id`).
- **D1 + D9 Pest cross-tenant 11 datasets** — `Tests/Feature/MultiTenantComprehensiveTest.php` cobre 11 cenários × 2 Repositories com `biz=99` retornando zero (defesa em profundidade Tier 0 IRREVOGÁVEL). +17 testes (41 assertions). In-memory SQLite robusto pra CI.

### Changed

- `FinanceiroServiceProvider::register()` registra `BaixaRepository` como singleton.

### Validated

- `php artisan vendor/bin/pest Modules/Financeiro/Tests/Feature/MultiTenantComprehensiveTest.php` → **17/17 passed (41 assertions)**.

### Refs

- ADR 0093 (multi-tenant Tier 0 IRREVOGÁVEL)
- ADR 0101 (tests biz=1, NUNCA biz=4 ROTA LIVRE)
- ADR 0094 §5 (SoC brutal)
- ADR 0155 (module-grade-v3)

## [Wave 18] — 2026-05-16

### Added (saturação 66→95)

- `Repositories/TituloRepository.php` (5 métodos canônicos)
- `Http/Requests/FluxoFiltroRequest.php` (4° FormRequest)
- `Tests/Feature/TituloRepositoryWave18Test.php`
- BRIEFING canon updated

## [Wave 17] — 2026-05-15

### Added (saturação 51→66)

- `Console/Commands/FinanceiroHealthCommand.php` (5 checks SQL)
- Inertia::defer em `DashboardController`
- `LogsActivity` em Categoria + ExtratoLancamento (D7)
- 3 FormRequests: StoreTransactionRequest, UpdateTransactionRequest, StoreAccountRequest
- `module.json` `lgpd_compliance` + `retention_days: 2555` (CTN Art. 195)
