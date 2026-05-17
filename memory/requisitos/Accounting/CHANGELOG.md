# Changelog

Formato: [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/) · [Semver](https://semver.org/).

## [Unreleased] - Wave 18 RETRY (2026-05-16)

### Added

- **D1 saturação multi-tenant** (ADR 0093 IRREVOGÁVEL):
  - `HasBusinessScope` aplicada em 4 Entities novas: `ExpenseCategory`, `Discount`, `CashRegister`, `DashboardConfiguration` (todas com `business_id` direto no schema).
  - `HasBusinessScopeAdoptionTest` expandido pra 21 Entities (Waves 12+13+18 RETRY).
  - Novo `MultiTenantComprehensiveTest.php` com Pest datasets — 21 Entities × 3 cenários = 63 assertions estruturais cross-tenant.
- **D7 LGPD/retention** — `Config/retention.php` (já existia desde Wave 11) reconfirmado com 5 categorias (`lancamentos`, `balancetes`, `notas_fiscais`, `logs_audit_contabil`, `clientes_fornecedores`).
- **D9 OTel** — spans `accounting.report.{trial_balance,balance_sheet,profit_and_loss,cash_flow}` + `accounting.journal_entry.create_balanced` (Wave 17, mantidos).

### Notes

- Cobertura PP D1: 9/30 → ~30/30 (saturação completa Entities com business_id direto)
- `EntityBusinessIdConsistencyTest` continua validando schema real
- Próximos passos: aplicar trait nas Entities child-via-parent (PaymentDetail, CashRegisterTransaction, GroupSubTax) usando `BelongsToBusinessViaParent`

## [0.1.0] - 2026-04-22

### Added

- Documentação inicial consolidada a partir do arquivo plano.
- Migrado para estrutura de pasta (README + ARCHITECTURE + SPEC + CHANGELOG + adr/).
