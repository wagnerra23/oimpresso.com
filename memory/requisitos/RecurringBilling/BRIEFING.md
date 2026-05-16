# BRIEFING — `RecurringBilling` (1-pager canônico)

> **Tipo:** BRIEFING canônico do módulo — 1 página executiva atualizada por PR relevante
> **Refs:** [proibicoes.md §Sempre fazer](../../proibicoes.md), [SPEC.md](SPEC.md), [CAPTERRA-FICHA.md](CAPTERRA-FICHA.md), [RUNBOOK-inter-pj.md](RUNBOOK-inter-pj.md)
> **Skill auto-trigger:** `brief-update` (Tier B) — atualiza este BRIEFING após PR mergeado em `Modules/RecurringBilling/`

---

## 1. O que é

**URL principal:** `https://oimpresso.com/recurring-billing`
**Backend:** `Modules/RecurringBilling/`
**Frontend (parcial):** `resources/js/Pages/RecurringBilling/`

Sub-módulo de assinaturas recorrentes (`rb_plans`, `rb_subscriptions`, `rb_invoices`, `rb_charge_attempts`) com cobrança via 3 drivers boleto (Inter / C6 / Asaas) + dunning + webhook idempotente. Listener `InvoicePaid` emite NFe automática via `NfeBrasil` (US-RB-044). MRR/Churn baseline pra clientes vertical `ComunicacaoVisual`.

## 2. Estado consolidado (boost Wave J 2026-05-16)

| Dimensão | % | Última medição |
|---|---|---|
| Operacional PME (P0+P1 core) | 70% | 2026-05-16 |
| Capterra score (vs Vindi/Iugu/Asaas/RD Recurring) | 56→70 | 2026-05-16 |
| Diferencial competitivo (NFe auto + multi-gateway) | 65% | 2026-05-16 |
| Cobertura SPEC formal (US-RB-NNN done/spec'ado) | ~60% | 2026-05-16 |
| Documentação canon (SPEC + RUNBOOK + CAPTERRA + BRIEFING) | 95% | 2026-05-16 |
| Deploy/ops (prod biz=1) | 50% | 2026-05-16 (canary G1 Martinho pendente) |

## 3. Capacidades hoje

- **Planos**: CRUD `rb_plans` + ciclo/trial/setup_fee + slug único per-tenant
- **Contratos**: `rb_subscriptions` (trialing/active/paused/past_due/canceled) + scope `Ativas`
- **Cobrança**: `BoletoService::driver()` resolve gateway por `BoletoCredential.banco` (inter/c6/asaas)
- **Cancelamento**: `AssinaturaCobrancaService::cancelInvoice()` thin orquestrador (PR Wave J)
- **Refund Asaas**: `RefundCobrancaAsaasJob` gated por `ASAAS_REFUND_ENABLED`
- **Webhooks**: idempotência via `webhook_event_id` + `ProcessAsaasWebhookJob`/`ProcessInterWebhookJob`
- **NFe**: listener `InvoicePaid` → `NfeBrasil` emite automaticamente (US-RB-044)
- **Banking sync**: `SyncBankBalancesJob` + `SyncBankStatementsJob` Inter PJ

## 4. Diferenciais únicos

1. **NFe-de-boleto-pago automática** (US-RB-044) — Vindi/Iugu NÃO emitem NFe, oimpresso sim
2. **Multi-gateway por tenant** (Inter PJ taxa zero / C6 / Asaas) — concorrentes presos a 1 gateway
3. **Boleto registrado Inter sem taxa PJ** — Vindi cobra 1.9% + R$ [redacted Tier 0]
4. **business_id global scope Tier 0** (ADR 0093) — Vindi multi-tenant fraco
5. **Cancelamento idempotente** (skip se `status=canceled`, 422 se `paid`) — pattern hardened

## 5. Gaps remanescentes (próxima onda)

| # | PR alvo | Esforço IA-pair | Score impact |
|---|---|---|---|
| 1 | UI React Pages/RecurringBilling/Plans completa | 4h | +2pp |
| 2 | Dunning automatizado (régua 3/7/15d) | 6h | +3pp |
| 3 | Pix Automático BCB (JRC) | 8h | +2pp |
| 4 | MRR/Churn dashboard real | 4h | +1.5pp |
| 5 | Pix recorrente Asaas | 3h | +1.5pp |

## 6. Bloqueadores manuais Wagner

- Credenciais Inter PJ Martinho Caçambas (mTLS .pem upload Vaultwarden)
- Canary G1 Martinho (CYCLE-06 goal) — sign-off cliente pré-cutover
- Webhook URL produção Asaas (rotação token)

## 7. ROI defendido vs concorrentes

| Concorrente | Como ganhamos | Como perdemos |
|---|---|---|
| Vindi (R$ [redacted Tier 0]+/mês + 1.9%) | NFe auto + ERP nativo + Inter taxa zero | Carteira de cartão tokenizada |
| Iugu (R$ [redacted Tier 0] + 2.99%) | Multi-gateway + taxa zero PJ | Antifraude embutido |
| Asaas (R$ [redacted Tier 0] + 1.99%) | ERP nativo + Inter como alternativa | Onboarding self-service |
| RD Recurring | 360 cliente ERP-nativo | Marketing automation |

## 8. Risks ativos

- 🟡 Inter mTLS expira anual (cert .pem rotação manual) — mitigação: cron `inter:cert-check` alerta D-30
- 🟡 Webhook Asaas sem retry exponencial garantido — mitigação: idempotência via `webhook_event_id` + `webhook_dlq`
- 🔴 Canary G1 Martinho ainda não rodou — mitigação: `bulk-start-pipeline` dry-run primeiro

## 9. Métricas-chave (last 7d)

- MRR ativo: pendente medição pós-G1
- Faturas geradas: ~0 (pré-canary)
- Webhook idempotency rate: 100% (suite Pest verde)
- Cobertura Pest: 14 testes Feature passando

## 10. Cliente piloto / canary

- **Atual:** nenhum em prod ainda — pré-cutover
- **Próximo canary:** **CYCLE-06 G1 Martinho Caçambas Inter PJ** — quando credenciais .pem subirem Vaultwarden + Wagner sign-off

## 11. ADRs centrais do módulo

- [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) — Multi-tenant Tier 0 IRREVOGÁVEL
- [ADR 0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md) — Tests biz=1 nunca biz=cliente
- [ADR 0121](../../decisions/0121-oimpresso-modular-especializado-por-vertical.md) — Modular especializado por vertical
- [ADR 0143](../../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md) — FSM Pipeline LIVE (futuro: assinatura como pipeline)

## 12. Sessões e handoffs relevantes (últimos 30d)

- Wave J 2026-05-16 — boost 56→70: thin `AssinaturaCobrancaService` + BRIEFING + Pest smoke
- CYCLE-06 G1 — Martinho Inter PJ canary (pendente desbloqueio cert)

---

## 13. Último update

**Atualizado:** 2026-05-16 BRT pela Wave J (boost RecurringBilling 56→70)
**Próximo update esperado:** pós-canary G1 Martinho Inter PJ
**Mantenedor:** Claude (auto Wave J) + Wagner (review)
