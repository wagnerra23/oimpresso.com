# Modules/Superadmin — Backoffice Wagner-only

Painel admin self-hosted pra criar packages comerciais + vender subscription a múltiplos businesses. Herdado de UltimatePOS v6.

⚠️ **Cross-tenant intencional** ([ADR 0093](../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) §exceções) — Superadmin é Wagner-only, opera com `withoutGlobalScopes` consciente. Auditoria via Spatie `LogsActivity` (D7.b LGPD) é append-only e cobre drift.

## Estado atual

- ✅ 4 entities: `Package`, `Subscription`, `SuperadminCommunicatorLog`, `SuperadminFrontendPage`
- ✅ 14 controllers (Business, Communicator, Package, Subscription, Pricing, PesaPal, etc.)
- ✅ LGPD audit trail Spatie em Package + Subscription + CommunicatorLog (Wave 11)
- ✅ `na_justified` D5/D4.c/D8.b declarado no SPEC (Wave 11)
- ✅ Services extraídos: `PackageManagerService`, `SubscriptionLifecycleService` (Wave 18 RETRY)
- ✅ FSM N/A declarado (Wave 18 RETRY) — Subscription status linear, sem multi-stage Sells/Repair
- ✅ Pest: `CrossTenantSuperadminTest`, `SuperadminCrossTenantPolicyTest`, `SuperadminGateTest`, `Lgpd/SuperadminLgpdComplianceTest`

## Docs canônicas

- [BRIEFING](../../memory/requisitos/Superadmin/BRIEFING.md) — estado consolidado + score ~85-90/100 (rubrica v3 ADR 0155)
- [SPEC](../../memory/requisitos/Superadmin/SPEC.md) — user stories + `na_justified` declarados
- [CHANGELOG](CHANGELOG.md) — append-only por PR
- [ADR 0093](../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) — Multi-tenant Tier 0 (exceções Superadmin)

## Permissions

Spatie middleware `superadmin` gate — checagem em `BaseController`.

## Pré-flight obrigatório antes de editar

1. Ler [BRIEFING](../../memory/requisitos/Superadmin/BRIEFING.md)
2. Confirmar com Wagner se mudança comercial (package novo, gateway novo) tem ADR
3. ⛔ NUNCA editar audit logs `superadmin_communicator_logs` direto — append-only LGPD
4. Pest cross-tenant: docs comentam que biz=99 lookup retorna `403/404` mas Superadmin pode `withoutGlobalScopes` ler — comentar `// SUPERADMIN: <razão>` sempre

## Tests local

```bash
php artisan test --filter=Modules\\\\Superadmin
```

## Gaps reconhecidos

- 🟡 PIX BR não nativo (Asaas/Inter integrados via `Modules/RecurringBilling`)
- 🟡 Sem dashboard de receita consolidada por package/cohort (P2 backlog)
- 🟡 Sem export CSV audit Communicator (LGPD opt-in)

## Não inventar

- ⛔ Cross-tenant `withoutGlobalScopes` sem comentário `// SUPERADMIN: <razão>`
- ⛔ DELETE em audit logs (`superadmin_communicator_logs`) — append-only
- ⛔ Gateway novo (Stripe/PIX/PagSeguro) sem ADR + RFC + counsel approval Eliana
