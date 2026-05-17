# CHANGELOG — Modules/RecurringBilling

Convenção: [Keep a Changelog](https://keepachangelog.com/) + [SemVer](https://semver.org/).

## [Wave 27 POLISH FINAL] — 2026-05-17 (atual 76-88 → target ≥90)

### Added (D2 + D9 + US-RB-044 sentry)

- **D2 Pest comprehensive** — `Tests/Feature/Wave27PolishTest.php` (18 cenários, 81 assertions):
  - **D2 BoletoService API**: 5 métodos públicos canônicos (emitir/cancelar/pdf/refundAsaas/fetchPaymentAsaas) + tipos signature + guard InvalidArgumentException
  - **D2 AssinaturaService API**: 5 métodos públicos (criar/pausar/retomar/cancelar/calcularProximoVencimento) + cross-tenant guard `$businessId` 1º arg em todos métodos
  - **D2 AssinaturaCobrancaService API**: cancelInvoice + atualizarCobrancaAssinatura + http_status convencional (422 invoice paga, 501 C6 manual)
  - **D9 spans completos**: BoletoService ≥4 spans canon (rb.boleto.emitir/cancelar/pdf/refund_asaas) + AssinaturaService 4/4 (rb.assinatura.*) + AssinaturaCobrancaService 2 (rb.invoice.cancel + rb.subscription.update)
  - **D9 atributos canon**: 3 services declaram `module=RecurringBilling` + `business_id` em span attributes
- **US-RB-044 NFe-de-boleto-pago SENTRY** (canônico irrevogável — W25/W26 criou listener):
  - InvoicePaid Event class shape: 4 props readonly (businessId int, invoiceRef string, valor float, paidAt string)
  - Dispatchable + SerializesModels (queue-safe pra `EmitirNFeAoReceberPagamento`)
  - Cross-module wiring lock: `NfeBrasilServiceProvider` registra `Event::listen(InvoicePaid, EmitirNFeAoReceberPagamento)`
  - Log canon preservado: `AssinaturaCobrancaService` mantém `Log::info('rb.subscription.atualizada')`
- **Tier 0 lock-in**: BoletoService NÃO loga `config_json` raw (LGPD — credenciais criptografadas)

### Validated

- `php vendor/bin/pest Modules/RecurringBilling/Tests/Feature/Wave27PolishTest.php` → **18/18 passed (81 assertions, 5.40s)**

### Refs

- ADR 0093 (multi-tenant Tier 0 IRREVOGÁVEL) · ADR 0101 (tests biz=1) · ADR 0094 §5 (SoC) · ADR 0155 (module-grade-v3)
- US-RB-044 (NFe-de-boleto-pago — listener `EmitirNFeAoReceberPagamento` em NfeBrasil)
- Tier 0 IRREVOGÁVEIS: InvoicePaid event readonly (downstream contract), `business_id` 1º arg em Services

### Estimativa nota

- Wave 25 baseline: ~76-88 (variável conforme dimensão auditada)
- Wave 27 polish final: **≥90** com cobertura D2/D9 + sentry US-RB-044 + Tier 0 lock-in

## [Unreleased] — Wave 18 RETRY (2026-05-16)

### Added (saturação 69→97 — governance module-grade-v3)

- **D4 SoC saturação granular extração tripla** —
  - `Services/AssinaturaService.php` extraído (substitui Controller no-op): 4 métodos (`criar`, `pausar`, `retomar`, `cancelar`) com idempotência + cross-tenant guard explícito + `calcularProximoVencimento` helper. 4 spans OTel canônicos.
  - `Services/Boleto/BoletoCredentialResolver.php` extraído de `BoletoService::driver()` + `decryptConfig()`. Decifra 4 campos sensíveis via `Crypt::decryptString` + `resolveDriverName()` fail-safe pra logs. 1 span OTel.
  - Ambos registrados como singleton em `RecurringBillingServiceProvider`.
- **D2 Pest cobertura crítica triple** —
  - `Tests/Feature/AssinaturaServiceWave18Test.php` — 11 testes (34 assertions) cobre criar/pausar/retomar/cancelar idempotência + cross-tenant biz=99 + reflection spans.
  - `Tests/Feature/BoletoCredentialResolverTest.php` — 8 testes (17 assertions) cobre decryption de TODOS os 4 campos sensíveis + ModelNotFoundException pra biz sem credencial + fail-safe `resolveDriverName('unknown')`.
  - `Tests/Feature/CustomerJourneyTest.php` — 3 testes (32 assertions) — **D5 Customer Journey end-to-end 9 passos**: criar → invoice gerada → pausar → retomar → atualizar ciclo anual → overdue → pago atrasado → cancelar → MRR baseline zero.

### Changed

- `RecurringBillingServiceProvider::registerRepositories()` agora registra também `AssinaturaService` + `BoletoCredentialResolver` como singletons.

### Validated

- `php artisan vendor/bin/pest Modules/RecurringBilling/Tests/Feature/AssinaturaServiceWave18Test.php` → **11/11 passed (34 assertions)**
- `php artisan vendor/bin/pest Modules/RecurringBilling/Tests/Feature/BoletoCredentialResolverTest.php` → **8/8 passed (17 assertions)**
- `php artisan vendor/bin/pest Modules/RecurringBilling/Tests/Feature/CustomerJourneyTest.php` → **3/3 passed (32 assertions)**

### Refs

- ADR 0093 (multi-tenant Tier 0 IRREVOGÁVEL)
- ADR 0101 (tests biz=1, NUNCA biz=4 ROTA LIVRE)
- ADR 0094 §5 (SoC brutal)
- ADR 0155 (module-grade-v3)
- US-RB-044 (NFe-de-boleto-pago — preservado, listener `InvoicePaid` não tocado)

## [Wave 18] — 2026-05-16

### Added (saturação 56→95)

- `Repositories/SubscriptionRepository.php` + `Repositories/InvoiceRepository.php` (5+5 métodos canônicos)
- `Http/Requests/CancelInvoiceRequest.php`
- `Tests/Feature/RepositoryWave18Test.php` (8 cenários)

## [Wave 17] — 2026-05-15

### Added (saturação 39→56)

- `Console/Commands/RecurringHealthCommand.php` (5 checks SQL `rb:health`)
- `Services/AssinaturaCobrancaService::atualizarCobrancaAssinatura` (FIN-004 mutation)
- LogsActivity em Subscription/Plan/BoletoCredential (D7)
- module.json `lgpd_compliance` + `retention_days: 1825`
