# BRIEFING — Modules/Superadmin

> **Estado:** ✅ legado UltimatePOS v6 em uso interno Wagner | **Atualizado:** 2026-05-16 (pós-PR3 governance-v3-docs `na_justified` declarado) | **Owner:** [W]
> Canon: [SPEC.md](SPEC.md) · Rubrica v3: [ADR 0155](../../decisions/0155-module-grade-v3-anti-injustica-na-justified.md) + [ADR 0156](../../decisions/0156-rubrica-v3-pesos-redistribuidos.md)

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

## Score module-grade

| Versão | Score | Observação |
|---|---|---|
| v2 (pré-PR3) | ~55-60/100 | Penalizava D5 (cross-tenant sem cliente externo), D4.c (Blade legacy intencional), D8.b (CSRF except) — injusto pro design backoffice Wagner-only |
| **v3 (pós-PR3)** | **~85-90/100** (esperado) | `na_justified` D5+D4.c+D8.b declarado no SPEC → rubrica v3 (ADR 0155) redistribui pesos pras dimensões aplicáveis |

**`na_justified` declarado no SPEC:**
- **D5 (cliente externo):** cross-tenant intencional Wagner-only, ADR 0093 §exceções + Constituição Art. 6 — biz=4 ROTA LIVRE não é alvo.
- **D4.c (MWART migração):** Blade legacy preservado por design — herdado UltimatePOS v6 (~50 views), Wagner-only não justifica investimento Inertia (ADR 0104 escopo só fronts cliente).
- **D8.b (CSRF except):** nenhuma rota Superadmin em `VerifyCsrfToken::except` — middleware CSRF padrão sempre aplicado.

## Gaps reconhecidos

- 🟡 PIX BR não nativo (gateways herdados são internacionais; Asaas/Inter integrados via `Modules/RecurringBilling`)
- 🟡 Sem dashboard de receita consolidada por package/cohort (P2 backlog)
- 🟡 Sem export CSV de audit do Communicator (LGPD opt-in)

## Estado de testes (Wave B)

- `Tests/Feature/MultiTenantIsolationTest.php` — bloqueia rotas Superadmin sem role
- `Tests/Feature/ScaffoldTest.php`
- `Tests/Feature/SmokeRoutesTest.php`

## Decisões relacionadas

- [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) — Multi-tenant Tier 0 (exceções superadmin)
- [ADR 0011](../../decisions/0011-alinhamento-padrao-jana.md) — Padrão modular Jana
- [ADR 0022](../../decisions/0022-meta-5mi-ano-financeira.md) — Meta R$ 5mi/ano (Superadmin é peça de cobrança)

## Próximo passo sugerido

Não há US ativa. Quando precisar mexer:
1. Ler este BRIEFING + SPEC.md
2. Confirmar com Wagner se mudança comercial (package novo, gateway novo) tem ADR
3. NUNCA editar audit logs (`superadmin_communicator_logs` append-only)
