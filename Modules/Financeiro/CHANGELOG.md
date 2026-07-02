# CHANGELOG — Modules/Financeiro

Convenção: [Keep a Changelog](https://keepachangelog.com/) + [SemVer](https://semver.org/).

## [Wave 28] - 2026-05-17

### Test (D2 — Pest +2 sentry Pluggy W27 + UnificadoService W25)
- `Tests/Feature/Wave28PolishTest.php` — +2 testes sentry Wave 28:
  - UnificadoService::kpis preserva wrap `OtelHelper::spanBiz('financeiro.unificado.kpis')`
    (regression guard W25 D9 — se alguém remover, sentry pega).
  - Pluggy W27 (open banking) connector artifacts sentry tolerante + Services core
    (UnificadoService + FluxoCaixaService) regression guard.
- Tier 0: multi-tenant + zero git ops + OtelHelper canônico + biz=4 intocado.

### Governance
- Saturação 80-95 → 96 (polish final excelência).

## [Wave 27 — Polish final ≥95] — 2026-05-17

### Added — D9 spans + US-FIN sentry + US-RB-044 lock-in
- **D9 W27** — `FinanceiroAuditLogger::redactContext` wrap em `OtelHelper::spanBiz('financeiro.audit.redact_context')` — mensura custo de redação PII em hot-paths (TransactionObserver + TituloAutoService logam por baixa). Atributos: business_id (resolvido do contexto se presente) + keys_count.
- **D2 W27** — Sentinel tests preservation: `BaixaRepository` 4 métodos canônicos + `TituloRepository` 5 métodos canônicos (W18) com assinatura `businessId:int` 1º param validada via Reflection (Tier 0 explícito).
- **D9 W27** — Sentinel spans preservation: `UnificadoService::kpis`, `FluxoCaixaService::projetar`, `TituloService::emitirBoleto`, `TituloAutoService::sincronizarDeTransaction` (lock-in W25/W18/W14).
- **US-FIN sentry** — `Tests/Feature/Wave27PolishTest.php`:
  - US-FIN-013/020: `UnificadoService::kpis(int $businessId)` assinatura estável
  - US-FIN-014: `FluxoCaixaService::projetar(int $businessId, int $dias=35)` assinatura estável (Q2 Wagner aprovou 2026-05-14: 35d fixo configurável)
- **US-RB-044 sentinel** — NFe-de-boleto-pago preservation (Tier 0 IRREVOGÁVEL):
  - `BoletoRemessa::STATUS_PAGO = 'pago'` constante intacta (trigger NFe gateway)
  - `BoletoRemessa` usa `LogsActivity` (audit fiscal CTN Art. 195)
  - `pdf_path` fillable preservado (double-write transição lib laravel-boleto)
  - `getPdfArquivoAttribute` accessor Modules/Arquivos preservado (ADR 0123)

### Validated

- 13 specs novos W27 em `Wave27PolishTest.php` (source-level + Reflection — sem DB).

### Refs

- ADR 0093 multi-tenant Tier 0 IRREVOGÁVEL
- ADR 0094 §4 Constituição v2 (custo IA + audit trail)
- ADR 0123 Modules/Arquivos backbone (US-RB-044 pdf_path transição)
- ADR 0143 FSM canon LIVE prod biz=1

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
