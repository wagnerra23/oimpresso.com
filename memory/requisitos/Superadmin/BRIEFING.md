# BRIEFING — Modules/Superadmin

> **Estado:** ✅ legado UltimatePOS v6 em uso interno Wagner | **Atualizado:** 2026-05-16 | **Owner:** [W]

## O que é

Painel backoffice de **governança interna do oimpresso**. Wagner usa pra criar businesses novos, gerenciar packages (planos comerciais), confirmar pagamentos offline, enviar comunicados cross-tenant, editar páginas do site público e configurar settings globais (SMTP, gateways, cron, backup).

**Cross-tenant intencional** — uma das poucas áreas legítimas que opera sobre TODOS os `business_id` ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) §exceções).

## Por que existe

Sem Superadmin Wagner precisaria SSH + tinker + SQL pra criar cliente novo, alterar plano ou ver consolidado. Centraliza operação comercial+suporte numa UI.

## Capacidades hoje

- ✅ CRUD de businesses + reset de senha owner
- ✅ Packages com `custom_permissions` JSON + limites
- ✅ Subscriptions com 7 gateways (PayPal/Stripe/Razorpay/PesaPal/Paystack/Flutterwave/offline)
- ✅ Communicator com audit log
- ✅ Frontend Pages (Sobre/Contato/Termos)
- ✅ Manage Modules per-business
- ✅ Settings globais (SMTP/Pusher/Cron/Backup/Gateways)
- ✅ Pricing público `/pricing`
- ✅ Usuario 360 (consolidado por cliente)

## Diferencial vs concorrentes

- Vs Bling/Tiny/Omie: ERPs comerciais BR não expõem painel admin self-hosted — Wagner pode rodar próprio MVP cobrando direto via Stripe/PIX, sem intermediário
- Vs SaaS genérico (Cobre/Vindi): Superadmin já vem com multi-tenant + packages + módulos plugáveis integrados ao mesmo banco — sem custo de integração

## Gaps reconhecidos

- 🟡 PIX BR não nativo (gateways herdados são internacionais; Asaas/Inter integrados via `Modules/RecurringBilling`)
- 🟡 Blade legacy não migrado MWART (P3 — uso interno raro, não bloqueia time MCP)
- 🟡 Sem dashboard de receita consolidada por package/cohort (P2 backlog)
- 🟡 Sem export CSV de audit do Communicator (LGPD opt-in)

## Estado de testes (Wave B)

- `Tests/Feature/MultiTenantIsolationTest.php` — bloqueia rotas Superadmin sem role
- `Tests/Feature/ScaffoldTest.php`
- `Tests/Feature/SmokeRoutesTest.php`

## Decisões relacionadas

- [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) — Multi-tenant Tier 0 (exceções superadmin)
- [ADR 0011](../../decisions/0011-alinhamento-padrao-jana.md) — Padrão modular Jana
- [ADR 0022](../../decisions/0022-meta-5mi-ano-financeira.md) — Meta R$ [redacted Tier 0]mi/ano (Superadmin é peça de cobrança)

## Próximo passo sugerido

Não há US ativa. Quando precisar mexer:
1. Ler este BRIEFING + SPEC.md
2. Confirmar com Wagner se mudança comercial (package novo, gateway novo) tem ADR
3. NUNCA editar audit logs (`superadmin_communicator_logs` append-only)
