# CHANGELOG — Modules/Superadmin

Append-only. Cada PR mergeado que toca `Modules/Superadmin/` deve adicionar 1 linha na entrada do Wave/data.

## Wave 18 RETRY — 2026-05-16 (governança meta-97)

- `module.json`: declarado `fsm_n_a: true` + razão — Superadmin é backoffice Wagner-only com Subscription status linear (vs Sells/Repair ADR 0143 multi-stage).
- `CHANGELOG.md` (este arquivo) + `README.md` criados — preencher D3 docs internas.
- `Services/PackageManagerService.php` extraído — encapsula listagem/cálculo de packages (D4 boost: extrair lógica `Package::listPackages` + `scopeActive` testável isolado).
- `Services/SubscriptionLifecycleService.php` extraído — encapsula status transitions (waiting_approval → approved → expired) com Spatie audit trail.
- `Tests/Feature/PackageManagerServiceTest.php` criado — cobre listagem + private/public filter.

## Wave 11 — 2026-05-15 (D7 LGPD audit + D5 na_justified)

- `Entities/Package.php`, `Subscription.php`, `SuperadminCommunicatorLog.php`: `LogsActivity` Spatie audit trail (D7.b LGPD — events fiscais append-only).
- SPEC.md: declarado `na_justified` D5/D4.c/D8.b — Superadmin é Wagner-only cross-tenant intencional, blade legacy preservado, sem CSRF except.

## Pré-Wave 11 (UltimatePOS herdado)

- 14 controllers + 6 form requests + entities Package/Subscription/Communicator herdados de UltimatePOS v6.

---

**Como atualizar:** entrada nova no TOPO da seção Wave ativa, NUNCA editar antigas. ⚠️ NUNCA editar `superadmin_communicator_logs` direto — append-only LGPD.
