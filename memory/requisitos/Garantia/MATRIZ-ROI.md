---
slug: garantia-matriz-roi
module: Garantia
type: matriz-roi
status: discovery
created_at: 2026-05-12
updated_at: 2026-05-12
related: [SPEC.md, ROADMAP.md, garantia-cross-vertical-workflow]
---

# MATRIZ-ROI — Modules/Garantia (cross-vertical workflow)

> **Score:** Impacto (1-5) × Esforço inverso (1-5, sendo 5=fácil) = ROI (1-25).
> Categorização ADR 0106 (estimates IA-pair 10x recalibrados).

## Top 5 features ROI (recomendação Wagner priorizar)

| Rank | Feature | Impacto | Esforço↓ | ROI | Veredito |
|---|---|---|---|---|---|
| 1 | **Workflow OS-filha (fundação Fase 1)** US-WARR-001..004 | 5 | 4 | **20** | P0 obrigatório |
| 2 | **UI claim creation drawer** US-WARR-005..006 | 5 | 4 | **20** | P0 — UX-first, sem isso não tem produto |
| 3 | **Ressarcimento fornecedor semi-manual** US-WARR-009..010 | 4 | 4 | **16** | P1 — Vargas Autopecas Bosch R$ 200/mês ressarcimento típico |
| 4 | **NFe substituição CFOP 5.949 automatizada** US-WARR-012 | 4 | 3 | **12** | P1 — diferencial vs concorrentes |
| 5 | **Dashboard custo-garantia % faturamento** US-WARR-014 | 4 | 3 | **12** | P2 — KPI Wagner mais pedido (oficina/balcão) |

## Tabela completa 20 features

| # | Feature | Vertical | Impacto | Esforço↓ | ROI | Fase | Concorrentes BR/USA c/feature | Gap oimpresso? |
|---|---|---|---|---|---|---|---|---|
| 1 | Workflow OS-filha (`parent_*_id` + FSM canon) | cross | 5 | 4 | **20** | F1 | Mitchell1, Tekmetric, Shop-Ware ✅ · Tiny/Bling ❌ | ✅ atual: deve fazer (gap mercado BR PME) |
| 2 | UI drawer "Solicitar garantia" no SaleSheet/Repair/Oficina | cross | 5 | 4 | **20** | F2 | Tekmetric ✅ (digital workflow) | ✅ |
| 3 | Upload foto mobile com compressão client-side | cross | 4 | 5 | **20** | F2 | Shop-Ware ✅ (mobile-first), Tekmetric ✅ | ✅ |
| 4 | Listener `WarrantyEligibilitySnapshotter` auto (no `concluir_producao`) | cross | 5 | 4 | **20** | F1 | Mitchell1 manual config | ✅ — automação é diferencial |
| 5 | Política garantia per-business UI admin (CRUD `warranty_policies`) | cross | 4 | 4 | **16** | F2/F5 | SAP S/4HANA ✅ (caro), Auto Manager 🟡 | ✅ |
| 6 | Workflow ressarcimento fornecedor semi-manual (RMA tracking) | autopecas+oficina | 4 | 4 | **16** | F3 | Mitchell1 (vendor invoice tracking) ✅, SAP WTY ✅, Tiny ❌ | ✅ — gap BR |
| 7 | Job daily prescrição 90d sem resposta fornecedor + flag unreliable | autopecas+oficina | 3 | 5 | **15** | F3 | SAP WTY ✅ (auto-aging), nenhum BR | ✅ — diferencial automação |
| 8 | Termo de Rejeição PDF + assinatura digital cliente | cross | 4 | 4 | **16** | F3 | Mitchell1 templates ✅, Tekmetric ✅ | ✅ — defesa LGPD/processo |
| 9 | NFe substituição CFOP 5.949 + entrada CFOP 1.949 automatizada | autopecas+comvis+oficina | 4 | 3 | **12** | F4 | SAP S/4HANA WTY (não fiscal BR) · nenhum BR PME ✅ | ✅✅ — gap fiscal BR forte |
| 10 | Movimentação Financeiro automática (prejuízo + ressarcimento crédito) | cross | 4 | 4 | **16** | F4 | TOTVS Protheus WTM ✅ (enterprise), nenhum PME | ✅ — diferencial integrado |
| 11 | Dashboard "Custo garantia % faturamento" + top produtos problemáticos | cross | 4 | 3 | **12** | F5 | Tekmetric BI ✅, Shop-Ware analytics ✅, nenhum BR PME | ✅ — Wagner KPI #1 |
| 12 | Notificação WhatsApp cliente per-stage (respeita LGPD ADR 0143) | cross | 3 | 5 | **15** | F5 | Steer CRM ✅ (USA), nenhum BR direto | ✅ — diferencial UX BR |
| 13 | Cliente abusivo flag (`abuse_score` calculado) | cross | 3 | 3 | **9** | F5 | TBF Jupiter ✅ (especialista), Mitchell1 ✅ | 🟡 — útil mas não top-priority |
| 14 | API B2B fornecedor Bosch (RMA auto) | autopecas | 4 | 1 | **4** | F4 (spike) | TBF Jupiter ✅ | 🔒 — só com sinal qualificado paying ADR 0105 |
| 15 | Jana vision (`AnalisarFotoIa`) detecta mau uso via foto | cross | 3 | 1 | **3** | F5 (spike) | Hicron Software (USA AI) ✅, nenhum BR | 🔒 — IA spike (custo Sonnet/Opus alto) |
| 16 | Histórico veículo/cliente garantia timeline (cross-vertical) | cross | 3 | 4 | **12** | F5 | Tekmetric ✅ (tech assignment historical) | ✅ |
| 17 | Lookup garantia ativa por NF + SKU (busca rápida atendente) | autopecas | 4 | 4 | **16** | F2 | Auto Manager 🟡 (simples), Mitchell1 ✅ | ✅ — Vargas balcão crítico |
| 18 | Lembrete cron WhatsApp pré-vencimento garantia (7d antes) | oficina+comvis | 3 | 4 | **12** | F5 | nenhum direto | 🟡 — anti-fraude útil mas baixo volume |
| 19 | FIPE integration (cap garantia % valor veículo) | oficina | 2 | 3 | **6** | backlog | nenhum | ❌ — niche |
| 20 | Termo de Conformidade cliente assinou (cobertura honrada) | comvis | 3 | 4 | **12** | F3 | Neoband 🟡 (PDF manual), nenhum auto | ✅ |

## Análise por vertical

### OficinaAuto (Vargas, futuras)

**Top 3 ROI:**
1. Workflow OS-filha (1) — fundação
2. UI drawer claim (2) — UX recapagem retorno
3. Histórico veículo timeline (16) — KPI veículo cliente fiel

**Concorrentes top:** Ultracar (BR, 30 anos), Auto Manager (BR mid), Mitchell1/Tekmetric (USA — referência funcional).

**Gap oimpresso vs concorrentes BR:** Auto Manager registra prazo mas sem RMA fabricante. Ultracar não publica workflow estruturado. **Diferencial alto** se entregarmos schema+FSM+RMA tracking.

### Autopecas (Vargas balcão, Extreme, Gold se gerar peças)

**Top 3 ROI:**
1. Lookup garantia por NF+SKU rápido (17) — atendente balcão precisa em segundos
2. Ressarcimento fornecedor semi-manual (6) — Bosch R$ 200/mês típico
3. NFe substituição CFOP 5.949 (9) — fiscal BR — gap mercado total

**Concorrentes top:** Lokoz, Auto Manager. Ambos 🟡 — sem workflow RMA estruturado, sem ressarcimento tracking.

**Gap oimpresso:** **alto** — quadrante "balcão autopeças BR PME + workflow warranty estruturado + ressarcimento fabricante + NFe fiscal" essencialmente vazio.

### Repair (Officeimpresso, futuras)

**Top 3 ROI:**
1. Workflow OS-filha (1) — já antecipado SPEC-FSM-WIREUP §2.2
2. UI drawer claim (2)
3. Cliente abusivo flag (13) — eletrônicos têm abuso comum (cliente alega defeito de fábrica)

**Concorrentes top:** RepairShopr, RepairDesk (USA). Inacessível BR fiscal+preço.

### ComunicacaoVisual (Gold, Extreme, Vargas, Zoom, Fixar, Mhundo, Produart, MartinhoCacambas indirect)

**Top 3 ROI:**
1. UI drawer claim com obrigar foto (2 + 3) — banner descolando exige foto
2. Termo Conformidade PDF (20) — cliente assina "loja honrou"
3. NFe substituição (9) — reimpressão sem cobrar fiscal correto

**Concorrentes top:** Neoband (publica termos públicos), Mr.Print, Wind Banner 24HS (USA). Nenhum tem workflow ERP estruturado — apenas página de termos.

**Gap oimpresso vs concorrentes:** **muito alto** — workflow ERP estruturado é diferencial radical. Concorrentes BR ComVis (Mubisys, Zenite, Calcgraf, Visua) historicamente não publicam workflow warranty.

## Comparativo com mercado (resumo discovery)

### Mercado BR PME

| Software | Garantia structure | Workflow RMA | Ressarcimento tracking | Dashboard KPI | NFe substituição |
|---|---|---|---|---|---|
| Tiny ERP | ❌ | ❌ | ❌ | ❌ | ❌ |
| Bling | 🟡 (RMA mencionado) | ❌ | ❌ | ❌ | ❌ |
| Conta Azul | ❌ | ❌ | ❌ | ❌ | ❌ |
| Omie | ❌ | ❌ | ❌ | ❌ | ❌ |
| Auto Manager | 🟡 prazo simples | ❌ | ❌ | ❌ | ❌ |
| Ultracar | 🟡 menciona | ❌ | ❌ | ❌ | ❌ |
| **oimpresso (proposta)** | **✅ schema canon** | **✅ FSM canon** | **✅ semi-auto + auto API B2B V4** | **✅** | **✅ CFOP 5.949+1.949** |

### Mercado USA

| Software | Garantia structure | Workflow RMA | Ressarcimento tracking | Dashboard KPI | NFe substituição |
|---|---|---|---|---|---|
| Mitchell1 Manager SE | ✅ | ✅ | ✅ (vendor invoice) | ✅ | N/A (não fiscal BR) |
| Tekmetric | ✅ | ✅ (technician assignment) | ✅ | ✅ | N/A |
| Shop-Ware | ✅ | ✅ | ✅ | ✅ | N/A |
| TBF Jupiter | ✅ specialista | ✅✅ | ✅✅ (B2B API) | ✅ | N/A |
| SAP S/4HANA WTY | ✅✅ | ✅✅ | ✅✅ | ✅✅ | N/A |

**Implicação:** oimpresso entrega ~70-80% feature parity Mitchell1/Tekmetric **+ fiscal BR** que mercado USA ignora → diferencial competitivo regional + ticket price >> Tiny/Bling.

## Recomendação executiva

**Wagner: priorizar Fase 1+2 (US-WARR-001..008) — 8 US, ~25h IA-pair, ROI bruto 18-20.** Wallclock realista: 2-3 semanas com 1 dev IA-pair + revisões.

**Não fazer Fase 4+5 sem sinal qualificado paying (ADR 0105) — manter como backlog ADR feature-wish.**

**Migração schemas antecipados (`oa_garantias`, `autopecas_garantias`) sai junto da Fase 1 — US-WARR-020 P1.**

## Refs

- SPEC: [SPEC.md](SPEC.md) US-WARR-001..020
- ADR draft: [garantia-cross-vertical-workflow](../../decisions/proposals/drafts/garantia-cross-vertical-workflow.md)
- ROADMAP: [ROADMAP.md](ROADMAP.md)
- Concorrentes discovery 2026-05-12 (sources WebSearch):
  - Ultracar BR · Auto Manager BR · Tiny ERP · Bling
  - Mitchell1 (Manager SE warranty tab + vendor invoice tracking)
  - Tekmetric (tech assignment historical) · Shop-Ware (digital workflow)
  - TBF Jupiter (B2B claim specialist) · SAP S/4HANA WTY · Epicor Warranty
  - Neoband (comvis BR termos) · Mr.Print · Wind Banner 24HS
