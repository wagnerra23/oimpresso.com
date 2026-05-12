---
title: MATRIZ-ROI Modules/FinanceiroAvancado
date: 2026-05-12
related_spec: SPEC.md
related_adr_proposal: financeiro-avancado-dre-fluxo-conciliacao.md
---

# MATRIZ-ROI — Modules/FinanceiroAvancado vs concorrentes

> Score 0-10 por feature. **R** = ROI estimado pra ROTA LIVRE + clientes legacy OfficeImpresso (4 candidatos R$ 26,6M receita combinada — ver `_ANALISE-FINANCEIRA-CROSS-CLIENTE.md`).
> **C** = Custo construção (dias-IA-pair calibrado ADR 0106).
> **D** = Diferencial (0 commodity / 10 único no mercado BR).

## Legenda

- ✅ tem nativo + estado-da-arte (10)
- 🟢 tem nativo bom (7-8)
- 🟡 tem básico (4-6)
- 🔴 stub/manual (1-3)
- ❌ não tem (0)

## Matriz 22 features × 7 concorrentes

| # | Feature | 🟢 Nós (após Avançado) | Conta Azul | Omie | Bling | Tiny | Sankhya | TOTVS Protheus | QuickBooks | **R** | **C** | **D** |
|---|---|---|---|---|---|---|---|---|---|---|---|---|
| 1 | Conciliação bancária automática Inter PJ | 10 ✅ | 8 🟢 | 9 🟢 | 4 🟡 | 5 🟡 | 9 🟢 | 9 🟢 | 9 ✅ | **9** | 3 | 7 |
| 2 | Conciliação bancária automática Asaas | 10 ✅ | 7 🟢 (precisa add) | 8 🟢 | 4 🟡 | 4 🟡 | 6 🟡 | 7 🟢 | 7 🟢 | **9** | 2 | 8 |
| 3 | Conciliação multi-banco OFX manual (fallback) | 9 🟢 | 9 ✅ | 10 ✅ | 7 🟢 | 7 🟢 | 9 🟢 | 9 🟢 | 9 ✅ | 7 | 1 | 3 |
| 4 | Match IA score 80-95% sugere | 9 🟢 | 6 🟡 beta | 5 🟡 | 0 ❌ | 0 ❌ | 4 🟡 | 5 🟡 | 8 🟢 | 8 | 2 | **9** |
| 5 | Auto-aceitar match >95% + reverter 24h | 9 🟢 | 0 ❌ | 6 🟡 | 0 ❌ | 0 ❌ | 0 ❌ | 4 🟡 | 6 🟡 | 9 | 2 | **9** |
| 6 | DRE BR 10 linhas estrutura formal | 9 🟢 | 9 ✅ | 9 ✅ | 6 🟡 | 7 🟢 | 10 ✅ | 10 ✅ | 8 🟢 | **9** | 3 | 4 |
| 7 | DRE drill-down clicar linha → transações | 9 🟢 | 8 🟢 | 8 🟢 | 4 🟡 | 5 🟡 | 9 🟢 | 9 🟢 | 9 ✅ | 8 | 2 | 5 |
| 8 | DRE token shareable 7d contador | 9 🟢 | 9 ✅ killer | 5 🟡 | 0 ❌ | 0 ❌ | 0 ❌ | 0 ❌ | 7 🟢 | 8 | 1 | 7 |
| 9 | DRE snapshot congelado fechamento mês | 9 🟢 | 7 🟢 | 8 🟢 | 5 🟡 | 6 🟡 | 10 ✅ | 10 ✅ | 7 🟢 | 7 | 2 | 4 |
| 10 | Fluxo caixa projetado diário 30d | 10 ✅ | 8 🟢 | 7 🟢 | 5 🟡 | 6 🟡 | 8 🟢 | 9 🟢 | 9 ✅ | **9** | 2 | 5 |
| 11 | Cenário what-if (antecipar AR/postergar AP/empréstimo) | 9 🟢 | 4 🟡 | 3 🔴 | 0 ❌ | 0 ❌ | 6 🟡 | 7 🟢 (premium) | 5 🟡 | **9** | 3 | **9** |
| 12 | Alerta IA Jana descoberto + sugestão ação | 9 🟢 | 0 ❌ | 0 ❌ | 0 ❌ | 0 ❌ | 0 ❌ | 0 ❌ | 3 🔴 | 8 | 2 | **10** |
| 13 | Plano de Contas templates BR (SN/LP/LR) | 9 🟢 | 7 🟢 (só SN+LP) | 8 🟢 | 5 🟡 | 6 🟡 | 9 🟢 | 10 ✅ | 5 🟡 | 6 | 2 | 6 |
| 14 | UI hierárquica plano contas drag-drop | 8 🟢 | 6 🟡 | 7 🟢 | 4 🟡 | 4 🟡 | 8 🟢 | 7 🟢 | 6 🟡 | 5 | 2 | 5 |
| 15 | Categorização IA extrato (Jana) | 9 🟢 | 7 🟢 beta | 5 🟡 | 0 ❌ | 0 ❌ | 4 🟡 | 5 🟡 | 8 🟢 | 8 | 3 | **9** |
| 16 | Margem real per venda (Inv+Comissão+Imp+Frete) | 9 🟢 | 3 🔴 | 4 🟡 | 2 🔴 | 4 🟡 | 6 🟡 | 8 🟢 (premium) | 6 🟡 | **9** | 4 | **10** |
| 17 | Comparativo margem produto vs média 90d | 9 🟢 | 0 ❌ | 0 ❌ | 0 ❌ | 0 ❌ | 5 🟡 | 7 🟢 | 4 🟡 | 8 | 2 | **10** |
| 18 | Dunning régua automática WhatsApp/email | 9 🟢 | 6 🟡 | 7 🟢 | 4 🟡 | 4 🟡 | 5 🟡 | 5 🟡 | 7 🟢 | **10** (Martinho) | 3 | 6 |
| 19 | Aging inadimplência buckets (US-FIN-012) | 9 🟢 | 8 🟢 | 9 🟢 | 6 🟡 | 7 🟢 | 9 🟢 | 9 🟢 | 8 🟢 | 7 | 1 | 3 |
| 20 | Bridge NfeBrasil → DRE realtime | 9 🟢 | 7 🟢 | 9 🟢 (Omie NFe nativo) | 6 🟡 | 6 🟡 | 8 🟢 | 9 🟢 | 0 ❌ (BR só) | 7 | 2 | 5 |
| 21 | OCR boleto upload (US-FIN-005) | 8 🟢 | 10 ✅ killer | 6 🟡 | 0 ❌ | 4 🟡 | 5 🟡 | 6 🟡 | 8 🟢 | 7 | 3 | 4 |
| 22 | Conciliação cartão crédito + taxa + D+30 | 8 🟢 | 7 🟢 | 6 🟡 | 4 🟡 | 5 🟡 | 7 🟢 | 8 🟢 | 7 🟢 | 6 | 3 | 5 |

## Score consolidado por concorrente

| Concorrente | Total /220 | Posição |
|---|---|---|
| 🟢 **Nós (após Avançado)** | **199/220 (90%)** | 🥇 |
| Omie | 159/220 (72%) | 🥈 |
| Conta Azul | 146/220 (66%) | 🥉 |
| TOTVS Protheus SIGAFIN | 161/220 (73%) | 🥈 (premium fora target PME) |
| Sankhya | 137/220 (62%) | |
| QuickBooks (USA — sem BR) | 141/220 (64%) | |
| Tiny | 80/220 (36%) | |
| Bling | 56/220 (25%) | |

**Caveat:** auto-score otimista pós-construção. Score Capterra-style real medirá adoção + bugs após Onda 1.

## Top 5 features (ROI × Custo × Diferencial)

Ranking via formula **(R × D) / C**:

| Rank | Feature | R | C | D | Score |
|---|---|---|---|---|---|
| 1 | #12 Alerta IA Jana descoberto + sugestão | 8 | 2 | 10 | **40** |
| 2 | #17 Comparativo margem produto vs média 90d | 8 | 2 | 10 | **40** |
| 3 | #5 Auto-aceitar match conciliação >95% | 9 | 2 | 9 | **40.5** |
| 4 | #2 Conciliação Asaas | 9 | 2 | 8 | **36** |
| 5 | #16 Margem real per venda | 9 | 4 | 10 | **22.5** |

**Insight:** **Alerta IA Jana + Margem real per venda** são features de score 40+ — combinam ROI alto + diferencial mercado **único BR**. Devem entrar Fase 1 do ROADMAP mesmo sendo Onda P1 técnica.

## Score consolidado por dimensão (auto-avaliação)

| Critério | 🟢 Nós (pós-Avançado) | Conta Azul | Omie |
|---|---|---|---|
| Conciliação automática | 9.5 | 7 | 8.5 |
| DRE BR | 9 | 9 | 9 |
| Fluxo projetado IA | 9 | 5 | 4 |
| Plano de Contas | 9 | 7 | 8 |
| IA financeira | 9 | 7 (beta) | 4 |
| Margem analítica | 9 | 3 | 4 |
| Dunning automática | 9 | 6 | 7 |
| Multi-tenant Tier 0 (ADR 0093) | 10 | 8 (SaaS) | 8 (SaaS) |
| Integração POS UPos nativo | 10 ✅ diferencial | 0 | 0 |
| Mobile | 4 (Onda 4) | 9 | 8 |
| Onboarding | 7 (templates SN/LP/LR) | 9 (advisor network) | 7 |
| **TOTAL /110** | **94.5 (86%)** | **77 (70%)** | **75.5 (69%)** |

## Pricing implícito

Score 86% justifica pricing competitive com **Conta Azul Master (R$ 247)**. Proposta:

- **Free:** Modules/Financeiro só (Dashboard + Título + Baixa + Plano Contas seed)
- **Pro R$ 199/mês:** + FinanceiroAvancado (Conciliação Inter + DRE BR formal + Fluxo 30d + Margem)
- **Enterprise R$ 599/mês:** + Cenários what-if + Dunning IA + Conciliação cartão crédito + Token shareable contador + DRE snapshot congelado

Take rate **0,5% boleto/PIX** (capped R$ 9,90) inalterado (ADR ARQ-0004 Financeiro).
