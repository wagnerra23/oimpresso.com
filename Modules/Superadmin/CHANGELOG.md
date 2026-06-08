# CHANGELOG — Modules/Superadmin

Append-only. Cada PR mergeado que toca `Modules/Superadmin/` deve adicionar 1 linha na entrada do Wave/data.

## Wave 27 polish final — 2026-05-17 (target cross_cutting ≥92)

- `Tests/Feature/Wave27CrossTenantSaturationTest.php` (NOVO) — **50 cenários
  cross-tenant SATURADOS** organizados em 5 blocos:
  - Bloco A SuperadminDashboardService (12 cenários: smoke / cross-session /
    invariants / SUPERADMIN convention / D9 spans)
  - Bloco B BusinessAuditService (10 cenários: canDestroy Wagner-protect /
    self-destroy / aging summary / convention / spans)
  - Bloco C PackageManagerService (9 cenários: smoke / find defesa / catálogo
    cross-tenant / consistência listActive vs countActive)
  - Bloco D SubscriptionLifecycleService (10 cenários: lifecycle approve/expire/
    cancel rejeições + idempotência cross-state)
  - Bloco E Regression guards (9 cenários: signatures estáveis / no withoutGlobalScopes
    sem comment / PII-free attributes / PT-BR markers / meta-test count)
- `Http/Controllers/SuperadminController.php` — **D9 spans** legacy blade
  (`superadmin.legacy.index` + `superadmin.legacy.stats`) — paridade com
  SuperadminDashboardService extraído W18+W23. Cross-tenant intencional
  marcado com `// SUPERADMIN:` em stats() method (ADR 0093).
- Acumulado Pest cross-tenant Superadmin: W23 (14) + W25 (25) + W27 (**50**)
  = **89+ cenários** (target 50+ no W27 alcançado).
- Acumulado OTel spans Superadmin: 4 Services × 3-4 métodos
  (Dashboard 4 + BusinessAudit 3 + PackageManager 2 + SubscriptionLifecycle 3)
  = 12 spans + 2 W27 Controllers legacy = **14 spans canônicos**.

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
