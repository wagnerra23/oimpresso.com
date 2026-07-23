---
id: requisitos-recurring-billing-capterra-inventario-v2
---

# CAPTERRA-INVENTÁRIO v2 — RecurringBilling

> Gerado por skill `comparativo-do-modulo` v2.0 em **2026-05-08**.
> Fontes: `CAPTERRA-FICHA.md` v2 (14 capacidades + 3 UX heuristics + 5 automation targets) + `Modules/RecurringBilling/` + `resources/js/Pages/Financeiro/` + `Modules/NfeBrasil/`.
> ADR governança: [0089](../../decisions/0089-capterra-driven-module-evolution.md) + [0101](../../decisions/0101-sistema-charter-capterra-governanca-escopo.md) (v2 — 3 eixos).
> Inventário v1 ([CAPTERRA-INVENTARIO.md](CAPTERRA-INVENTARIO.md)) preservado como histórico.

---

## Resumo (3 eixos)

### Eixo 1 — Features (14 capacidades)

| Bucket | Quantidade | % |
|---|---|---|
| ✅ APROVADO | 1 | 7% |
| 🟡 PARCIAL | 4 | 29% |
| ❌ AUSENTE | 9 | 64% |
| **Total** | **14** | 100% |

### Eixo 2 — Usabilidade (3 heurísticas)

| Bucket | Quantidade | % |
|---|---|---|
| ✅ APROVADO | 0 | 0% |
| 🟡 PARCIAL | 1 | 33% |
| ❌ AUSENTE | 2 | 67% |
| **Total** | **3** | 100% |

### Eixo 3 — Automação (5 targets)

| Bucket | Quantidade | % |
|---|---|---|
| ✅ APROVADO | 1 | 20% |
| 🟡 PARCIAL | 2 | 40% |
| ❌ AUSENTE | 2 | 40% |
| **Total** | **5** | 100% |

**Diagnóstico v2:** infra de webhook + multi-gateway é forte (eixo Auto puxa o módulo); lacuna grande em **automações de domínio** (NFe-on-paid, dunning, retry cartão) e em **UX measurada** (mais comparações pendentes vs Iugu/Asaas/Vindi).

---

## Inventário detalhado — Eixo 1 Features

(Idêntico a v1; preservado pra continuidade)

| # | Capacidade | Score | Status | Falta |
|---|---|---|---|---|
| 1 | Boleto bancário registrado via API banco | P0 | 🟡 | Tests/ vazia |
| 2 | Webhook de pagamento idempotente | P0 | 🟡 | Sem teste replay |
| 3 | Multi-gateway por business | P0 | ✅ | — |
| 4 | Cancelamento + estorno via API | P0 | 🟡 | UI + audit + completar drivers |
| 5 | Emissão automática de NFe ao receber | P1 | ❌ | Listener completo |
| 6 | Reconciliação automática (saldo + extrato) | P1 | 🟡 | Matcher transação→fatura |
| 7 | Cartão de crédito recorrente | P1 | ❌ | Tokenização + retry |
| 8 | PIX recorrente (Open Finance) | P1 | ❌ | Endpoint + jornada |
| 9 | Régua de cobrança (dunning) | P1 | ❌ | Configuração + jobs |
| 10 | Tela de assinaturas | P1 | ❌ | Página Inertia + ações |
| 11 | Proration mid-cycle | P2 | ❌ | Calculator + UI |
| 12 | Split payment marketplace | P3 | ❌ | SplitConfig + UI |
| 13 | Métricas SaaS (MRR/Churn/LTV) | P3 | ❌ | Job + dashboard |
| 14 | Cobrança via WhatsApp Business | P3 | ❌ | Integração |

---

## Inventário detalhado — Eixo 2 Usabilidade (NOVO)

| # | Heurística | Score | Status | Benchmark | Nosso | Gap |
|---|---|---|---|---|---|---|
| U1 | Cliques pra emitir NFe pós-boleto pago | P0 | ❌ | Asaas: **1** (auto) | hoje **manual** (Listener falta — F1 Eixo Auto/A1 cobre) | Bloqueado por A1 |
| U2 | Tempo até ver saldo atualizado no dashboard | P1 | 🟡 | Asaas: **<2s realtime** | ~3-8s (cron D-7 + cache) | OK pra benchmark; medir M1 token economy junto em F4 |
| U3 | Cliques pra cancelar título não pago | P0 | ❌ | Asaas/Iugu: **2** | Sem UI ainda (capacidade #4 do Eixo 1 = 🟡) | Depende #4 Eixo 1 |

---

## Inventário detalhado — Eixo 3 Automação (NOVO)

| # | Target | Score | Status | Evidência | Gap |
|---|---|---|---|---|---|
| A1 | Auto-emitir NFe55 quando boleto recorrente pago | P0 | ❌ | Event `InvoicePaid` existe; **nenhum listener** em `Modules/NfeBrasil/` | Listener completo + autorização SEFAZ + p95 <30s + idempotente. **CARRYOVER** US-RB-044 cycle atual |
| A2 | Sync extrato + saldo diário | P0 | 🟡 | `SyncBankBalancesJob` + `InterExtratoJob` cron 07:00 + `pg_webhook_events` UNIQUE | Falta matcher transação→fatura + ratchet baseline saldo |
| A3 | Dunning régua D+1/D+3/D+7 automática | P1 | ❌ | Sem job/scheduler dedicado | DunningSchedulerJob + SendDunningMessageJob + idempotência (invoice_id, dia) |
| A4 | Webhook replay protection (idempotente) | P0 | ✅ | `pg_webhook_events` UNIQUE(provider, event_id) + `ProcessAsaasWebhookJob` skip duplicate | Cobertura ratchet em F4 (M6 anti-hallucination) |
| A5 | Charge retry exponencial em cartão recusado | P1 | ❌ | Sem `ChargeAttemptJob` ainda | Retry com backoff 1d/3d/7d + `ChargeAttempt::retry_count` |

---

## Tasks propostas (aguardando aprovação Wagner)

> Priorizadas por bloqueio + score. Cada uma indica `module:RecurringBilling priority:P{N}` + eixo.
> Aprovar com `/comparativo aprovar 1,2,4`. **NÃO criadas no MCP ainda.**

### P0 (bloqueador, 4 tasks)

1. **[P0 · Auto]** Listener `InvoicePaid` → emite NFe55 (US-RB-044 já em review — só promover)
2. **[P0 · UX]** UI "Cancelar título" + botão na lista (resolve U3 + completa #4 Eixo 1)
3. **[P0 · Features]** Cobertura Pest dos 3 drivers (resolve #1 Eixo 1)
4. **[P0 · Features]** Test idempotência webhook replay (resolve #2 Eixo 1 + ratchet A4)

### P1 (mercado tem, 6 tasks)

5. **[P1 · Features]** Models Subscription/Plan/Invoice/ChargeAttempt + migrations (fundação P1)
6. **[P1 · Auto]** DunningSchedulerJob + SendDunningMessageJob (A3)
7. **[P1 · Auto]** ChargeAttemptJob retry exponencial cartão (A5, depende #5)
8. **[P1 · Features]** Tela /financeiro/assinaturas (depende #5)
9. **[P1 · Features]** PIX recorrente Open Finance (capacidade 8 Eixo 1)
10. **[P1 · Features]** Matcher reconciliação transação→fatura (resolve A2)

### P2-P3 (5 tasks)

11. **[P2 · Features]** ProrationCalculator + UI preview
12. **[P3 · Features]** SplitConfig marketplace
13. **[P3 · Features]** Dashboard SaaS metrics
14. **[P3 · Features]** WhatsApp Business
15. **[P0 · UX]** Medir cliques real do oimpresso pra U1, U2, U3 (alimentar baseline F4)

---

## Próxima reauditoria sugerida

**2026-08-08** (trimestral) ou após mergear ≥3 das tasks P0.

---

## Diff vs v1

- **Eixo Features:** mantido (14 capacidades, mesmos buckets)
- **Eixo UX:** novo (3 heurísticas, 0 ✅, 1 🟡, 2 ❌)
- **Eixo Automação:** novo (5 targets, 1 ✅ webhook idempotência, 2 🟡, 2 ❌)
- **Tasks propostas:** v1 tinha 14, v2 tem 15 (+1 task de medição UX baseline)

Inventário v1 fica preservado em `CAPTERRA-INVENTARIO.md` pra histórico (git diff vai contar a evolução).
