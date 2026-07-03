---
slug: paymentgateway-capterra-inventario
title: "CAPTERRA-INVENTARIO — PaymentGateway"
type: capterra-inventario
module: PaymentGateway
status: ativo
gerado_por: comparativo
gerado_em: "2026-07-03"
fonte_ficha: CAPTERRA-FICHA.md
owner: wagner
---

# CAPTERRA-INVENTARIO — PaymentGateway

> **Passo 2** do [template-onda-modulo](../_Governanca/programa-ondas/template-onda-modulo.md) — buckets ✅🟡❌ derivados da [CAPTERRA-FICHA.md](CAPTERRA-FICHA.md) (nota 67/100) + batch de tasks proposto.
> Base `origin/main`@`7442c27c43`. Cruzado com [SPEC.md](SPEC.md) (US-PG-001..009) pra **não duplicar**.

## Bucket ✅ APROVADO (6) — manter/regressão

| Capacidade | Evidência |
|---|---|
| Boleto registrado via API banco-direto (P0) | 5 drivers `emitirBoleto()` |
| CNAB 240/400 multi-banco remessa/retorno (P1) — **diferencial** | 11 CnabDrivers + adapter + retorno processor |
| Pix cob/cobv (P0) | Inter (cob+cobv) + Asaas/C6/Pagarme/Sicoob |
| Reconciliação auto push+pull single-source (P0) | `ReconciliarCobrancaService` (job + polling) |
| Multi-gateway isolado por `business_id` (P0) — **diferencial** | `payment_gateway_credentials` global scope |
| Credenciais encrypted at rest (P0) | `config_json` `encrypted:array` (US-PG-001) |

## Bucket 🟡 PARCIAL (9) — fechar

| Capacidade | Estado | US-PG existente? |
|---|---|---|
| Refund/estorno API full+partial (P0) | 3/6 drivers; C6/Sicoob/BcbPix NotSupported | **NENHUMA → propor** |
| Webhook HMAC+idempotência (P0) | 3 reais + 4 fixados; hardening pendente | **US-PG-003** (throttle/replay) + **US-PG-006** (Inter mTLS) |
| Pix Automático mandato (P1) | driver pronto, sem homologação | pré-cond. PLANO-ONDA5 §3 + **US-PG-009** (smoke) |
| Cartão token+charge (P1) | só Asaas+Pagarme | — (estratégia, não gap tático) |
| Cartão recorrente+retry (P1) | vive em RB | — (fronteira RB↔PG) |
| Pix devolução (P1) | só Inter | coberto por "refund uniforme" ↓ |
| Webhook orphan retry (P1) | landed, cron OFF | **US-PG-008** (gate cutover) |
| Extrato/saldo + conciliação contábil (P2) | só Inter import | **NENHUMA → propor** |
| Bloqueio inadimplência SaaS (P2) — diferencial | Onda 5 codado, smoke pendente | **US-PG-009** |

## Bucket ❌ AUSENTE (4) — decidir (criar vs descartar)

| Capacidade | Score | Veredito sugerido |
|---|---|---|
| Split de pagamento (múltiplos recebedores) | P2 | **feature-wish** — só com sinal de cliente (ADR 0105); os 6 concorrentes têm |
| 3DS/antifraude próprio | P2 | **não priorizar** — delegação ao PSP é aceitável |
| Régua de dunning (D+1/D+3/D+7) | P1 | **manter em RecurringBilling** — não duplicar no PG |
| Negativação Serasa/SPC | P2 | **feature-wish** — só com sinal |

## Batch de tasks proposto (aguarda OK [W])

> Regra: os gaps **já rastreados** (US-PG-003/006/007/008/009) **não viram task nova** — já estão no SPEC. Proponho só o que **não existe**.

| # | US criada | Score | Esforço | Descrição | REGRA MESTRE? |
|---|---|---|---|---|---|
| T1 | **US-PG-010** Refund uniforme nos 6 drivers | P0 | Médio (12h) | Estender `refund()` real em C6/Sicoob (ou doc TED-reverso p/ boleto) + Pix devolução generalizada + Pest por driver | ✅ toca valor — dry-run+2 caminhos |
| T2 | **US-PG-011** Boleto híbrido (boleto + QR Pix no mesmo doc) | P1 | Médio (8h) | Inter/Asaas suportam; expor no adapter + UI | — |
| T3 | **US-PG-012** Extrato/saldo unificado + conciliação contábil | P2 | Alto (20h) | Integra `fin_contas_bancarias.saldo_cached`; estilo Cielo EDI/Stripe Sigma | ✅ toca valor |
| T4 | **US-PG-013** Split de pagamento (feature-wish) | P2 | Alto (24h) | **Registrada mas dormente** — só ativar com sinal de cliente (ADR 0105) | — |

> **Todas aprovadas [W] "todos" 2026-07-03** — criadas via `tasks-create` MCP (US-PG-010..013) + apendidas ao [SPEC.md](SPEC.md). US-PG-013 (split) fica **registrada dormente** (ADR 0105).

**Já no SPEC (não recriar) — recomendação de priorização pro PLANO-ONDA5:**
- **US-PG-003** (webhook hardening, P0) → executar agora (baixo esforço, alto risco Tier 0)
- **US-PG-008** (cutover + orphan-retry, P0) → gate REGRA MESTRE (dry-run antes→depois + OK [W])
- **US-PG-006/007** (Inter mTLS + URL pública, P1/infra) → CT100
- **US-PG-009** (smokes Onda 5 biz=1 + canary, P1) → humano-limitado (ADR 0106)

## Decisão [W] — RESOLVIDA 2026-07-03

Wagner aprovou **"todos"**. As 4 US foram criadas (US-PG-010..013) e apendidas ao [SPEC.md](SPEC.md). US-PG-013 (split) registrada **dormente** (ADR 0105 — ativar só com sinal de cliente).
