---
module: Marketplaces
artefato: MATRIZ-ROI
status: feature-wish
related_spec: SPEC.md
related_proposal: ../../decisions/proposals/drafts/marketplaces-modulo-cross-vertical.md
last_review: 2026-05-12
---

# MATRIZ-ROI Modules/Marketplaces — 25 features × score competitivo

> **Status:** antecipatório (status SPEC `feature-wish`). ROI calculado contra **Tiny ERP / Bling / Conta Azul / Olist / MagaluHub** ([SPEC §9](SPEC.md#9-concorrentes-research-consolidada-discovery)).
>
> **Convenção score:**
> - **Effort**: estimate IA-pair fator 10x ([ADR 0106](../../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md)) em horas Felipe
> - **Value**: 1-5 (5 = critical revenue driver / dor cliente direta)
> - **Diff**: 1-5 (5 = nenhum concorrente entrega — wedge claro)
> - **ROI** = `(Value × Diff) ÷ Effort` × 10 (índice relativo; >5 = priorize)

## Matriz consolidada

| ID | Feature | Prio | Effort (h) | Value | Diff | ROI | Tiny | Bling | Conta Azul | Olist | Magalu |
|---|---|---|---:|---:|---:|---:|:-:|:-:|:-:|:-:|:-:|
| US-MKT-001 | Schema fundação 9 tabelas + Models global scope | P0 | 4 | 5 | 3 | **37.5** | ✅ | ✅ | 🟡 | ✅ | ✅ |
| US-MKT-002 | Catálogo `mkt_marketplaces` seed 3 drivers | P0 | 2 | 4 | 2 | **40.0** | ✅ | ✅ | 🟡 | ✅ | ❌ |
| US-MKT-003 | OAuth2 Mercado Livre + refresh job | P0 | 6 | 5 | 2 | **16.7** | ✅ | ✅ | ✅ | ✅ | n/a |
| US-MKT-004 | OAuth2 Shopee + Amazon BR drivers | P1 | 6 | 4 | 2 | **13.3** | ✅ | ✅ | 🟡 | ✅ | n/a |
| US-MKT-005 | Importar 1 anúncio manual ML (POC) | P0 | 5 | 4 | 1 | **8.0** | ✅ | ✅ | ✅ | ✅ | ✅ |
| US-MKT-006 | Webhook receiver ML + HMAC idempotency | P0 | 5 | 5 | 3 | **30.0** | ✅ | ✅ | 🟡 | ✅ | ✅ |
| US-MKT-007 | Pedido ML → transaction + FSM stage initial | P0 | 8 | 5 | 4 | **25.0** | ✅ | ✅ | ✅ | ✅ | ✅ |
| US-MKT-008 | UI Page Orders Cockpit V2 + drawer FSM | P0 | 6 | 4 | 3 | **20.0** | ✅ | ✅ | 🟡 | ✅ | 🟡 |
| US-MKT-009 | NFe automática CFOP marketplace resolver | P0 | 7 | 5 | 4 | **28.6** | ✅ | ✅ | ✅ | ✅ | ✅ |
| US-MKT-010 | Sync rastreio Correios + auto-fechamento | P0 | 6 | 5 | 3 | **25.0** | ✅ | ✅ | 🟡 | ✅ | 🟡 |
| US-MKT-011 | Sync estoque oimpresso → ML (push) | P1 | 8 | 4 | 2 | **10.0** | ✅ | ✅ | 🟡 | ✅ | ✅ |
| US-MKT-012 | Sync estoque ML → oimpresso (pull) | P1 | 4 | 3 | 2 | **15.0** | ✅ | ✅ | ❌ | ✅ | 🟡 |
| US-MKT-013 | Anúncio em lote (bulk create N produtos) | P1 | 10 | 4 | 2 | **8.0** | ✅ | ✅ | ❌ | ✅ | 🟡 |
| US-MKT-014 | Disputa workflow completo (claims + evidência) | P1 | 12 | 5 | 4 | **16.7** | 🟡 | 🟡 | ❌ | 🟡 | ❌ |
| US-MKT-015 | Reconciliação Mercado Pago split D+X (AR 2 buckets) | P1 | 8 | 5 | 4 | **25.0** | 🟡 | 🟡 | ✅ | 🟡 | ❌ |
| US-MKT-016 | Reputação ML monitoring + alerta SLA | P1 | 5 | 5 | 4 | **40.0** | 🟡 | ❌ | ❌ | 🟡 | ❌ |
| US-MKT-017 | Pricing rules per marketplace (markup) | P1 | 6 | 4 | 3 | **20.0** | ✅ | ✅ | 🟡 | ✅ | 🟡 |
| US-MKT-018 | Jana tool relatórios IA marketplaces | P2 | 4 | 5 | **5** | **62.5** | ❌ | ❌ | ❌ | 🟡 | ❌ |
| US-MKT-019 | Etiquetas envio ML Coletas + bulk print | P2 | 6 | 4 | 2 | **13.3** | ✅ | ✅ | 🟡 | ✅ | ✅ |
| US-MKT-020 | Pipeline produção sob demanda ML (ComVis) | P2 | 8 | 4 | **5** | **25.0** | ❌ | ❌ | ❌ | ❌ | ❌ |
| US-MKT-021 | Shopee orders webhook + NFe completo | P2 | 10 | 4 | 2 | **8.0** | ✅ | ✅ | 🟡 | ✅ | n/a |
| US-MKT-022 | Amazon BR orders webhook + NFe (SP-API) | P2 | 12 | 4 | 2 | **6.7** | ✅ | ✅ | ❌ | ✅ | n/a |
| US-MKT-023 | Pricing dinâmico copilot competitor watch | P3 | 14 | 4 | 4 | **11.4** | ❌ | ❌ | ❌ | 🟡 | ❌ |
| US-MKT-024 | Analytics margem real (CMV + frete + taxa) | P3 | 10 | 5 | 3 | **15.0** | 🟡 | 🟡 | 🟡 | ✅ | ❌ |
| US-MKT-025 | Magalu Hub + Americanas drivers | P3 | 16 | 3 | 2 | **3.8** | ✅ | ✅ | ❌ | ✅ | ✅ |

**Total effort estimado:** **188h** IA-pair (fator 10x) = ~23-24 dias Felipe (vs ~190 dias humano sem IA-pair).

**Distribuição prio:** 11 P0 (62h) · 7 P1 (52h) · 4 P2 (28h) · 3 P3 (46h).

Legenda concorrentes: ✅ entrega · 🟡 parcial/limitado · ❌ não entrega · n/a não aplicável.

## Top 5 features ROI (≥25)

| # | ID | Feature | ROI | Justificativa wedge |
|---|---|---|---:|---|
| 1 | **US-MKT-018** | Jana tool relatórios IA marketplaces | **62.5** | **Nenhum concorrente entrega.** Diff=5. Wedge primário oimpresso — "WhatsApp Larissa: quantos pedidos ML hoje?" → resposta instant. 4h effort = ROI brutal. |
| 2 | **US-MKT-016** | Reputação ML monitoring + alerta SLA | **40.0** | Tiny tem dashboard básico; Bling/Conta Azul/Magalu não têm. Cliente perde venda por reputação cair sem aviso. 5h effort high-value. |
| 3 | **US-MKT-002** | Catálogo `mkt_marketplaces` seed drivers | **40.0** | Fundação técnica baixo effort (2h) — sem ele nada funciona. Effort/value ratio máximo. |
| 4 | **US-MKT-001** | Schema fundação 9 tabelas + Models | **37.5** | Idem — fundação obrigatória. 4h destrava tudo. |
| 5 | **US-MKT-006** | Webhook receiver ML + HMAC idempotency | **30.0** | Sem webhook não tem ingest. ML perde 5-10% webhooks típicos — idempotency-key + fallback poll diferencial implementação. |

## Top 5 features diferenciais únicos (Diff=5 — wedge não-replicado)

| # | ID | Feature | Diff | Comentário |
|---|---|---|---:|---|
| 1 | **US-MKT-018** | Jana tool relatórios IA marketplaces | **5** | Nenhum concorrente brasileiro entrega. Tiny/Bling/Olist mostram dashboards estáticos; oimpresso responde conversacional. |
| 2 | **US-MKT-020** | Pipeline produção sob demanda ML (ComVis) | **5** | Único módulo cross-vertical que conecta ML → produção sob demanda (banner personalizado vendido em ML → FSM stage `pedido_ml_recebido` → produção → envio). Nenhum hub atual entende vertical produtivo. |

## Distribuição por fase roadmap

| Fase | US incluídas | Total effort (h) | Wallclock estimado* |
|---|---|---:|---|
| **Fase 1 — Fundação + OAuth** | US-MKT-001, 002, 003, 005 | **17h** | 2-3 dias Felipe |
| **Fase 2 — Webhooks + NFe + Tracking** | US-MKT-006, 007, 008, 009, 010 | **32h** | 4-5 dias |
| **Fase 3 — Sync + Recon + Reputação** | US-MKT-011, 012, 015, 016, 017 | **31h** | 4-5 dias |
| **Fase 4 — Shopee + Amazon (driver pattern)** | US-MKT-004, 021, 022 | **28h** | 4 dias |
| **Fase 5 — Diferenciais Jana + Disputa + Analytics** | US-MKT-013, 014, 018, 019, 020, 023, 024, 025 | **80h** | 10-12 dias |

\*Wallclock IA-pair com margem 2x ([ADR 0106](../../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md)); humano-limitado (Migration Factory cliente concreto, smoke real, canary 7d) NÃO inclusos.

**MVP entregável (Fase 1+2)**: **49h ≈ 6-8 dias** Felipe pra entregar pipeline funcional ML mostrável Larissa/Vargas.

## Gaps comparativos vs concorrentes

### Tiny ERP (líder marketplace BR)
**Tiny entrega:** ML+Shopee+Amazon+Magalu+B2W+VTEX+Shopify+100+ integrações; NFe; reputação básica; emissão etiquetas; sync estoque.
**Tiny NÃO entrega:** multi-tenant Tier 0 (multi-empresa SQL — risco vazamento); IA Jana conversacional; vertical depth; FSM customizável per business.

### Bling
**Bling entrega:** 250+ marketplaces; pricing R$ [redacted Tier 0] entry; base 300k+ users.
**Bling NÃO entrega:** sync estoque alto volume robusto (bugs reportados); IA conversacional; vertical depth; multi-tenant Tier 0.

### Conta Azul
**Conta Azul entrega:** financeiro forte; integração contador via DRE; NFe automática ML.
**Conta Azul NÃO entrega:** marketplace além ML (raso); bulk operations; multi-canal robusto.

### Olist
**Olist entrega:** AI pricing premium; logística integrada; ecossistema completo enterprise.
**Olist NÃO entrega:** SMB-friendly pricing; vertical depth; tenant isolation Tier 0.

### MagaluHub (cativo Magalu/Casas Bahia)
**MagaluHub entrega:** integração nativa cassia Magalu + Casas Bahia + Extra; gratuito.
**MagaluHub NÃO entrega:** outros marketplaces; NFe externa; ERP completo.

## Recomendação de priorização

Se sinal qualificado ([SPEC §11.1](SPEC.md#111-sinal-qualificado-de-mercado-adr-0105)) materializar:

1. **Executar fase 1+2 (49h)** antes de cobrar 1º cliente — pipeline ML funcional mostrável
2. **US-MKT-018 (Jana IA) prioridade alta logo após fase 2** — diferencial wedge ROI=62.5 destrava marketing
3. **US-MKT-016 (Reputação)** + **US-MKT-015 (Recon split)** antes onboarding cliente real — evita perder reputação no piloto
4. **Fase 4 (Shopee+Amazon)** sob demanda — não building "by default" se cliente piloto só usa ML

## Riscos features alto ROI

- **US-MKT-018 (Jana IA)** pode parecer "AI hype" sem grounding — exige `ContextSnapshotService` ([ADR 0035](../../decisions/0035-stack-ai-canonica-wagner-2026-04-26.md)) com dados reais + PolicyEngine `REQUIRE_HUMAN_REVIEW` em sugestões ação
- **US-MKT-016 (Reputação)** depende API ML estável endpoint `/users/{id}/reputation` — se ML mudar (já mudou 3x histórico), refactor obrigatório
- **US-MKT-001 + 002 (fundação)** schema decisions mal calibradas = refactor caro fase 3+ — Wagner valida schema antes scaffold

---

**Última atualização:** 2026-05-12 — matriz inicial alinhada com SPEC v1 + ADR proposal D1-D8. Recalibrar pricing tiers + score Value após sinal qualificado §11.1 materializar (não antes — viola ADR 0105).
