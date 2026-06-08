---
name: Revenue thesis dos módulos spec-ready
description: Pricing tiers + take rate model + posicionamento dos módulos promovidos em 2026-04-24 (4 originais + Copiloto)
type: reference
---
Os módulos spec-ready têm **revenue model definido** em ADRs ARQ-0004 de cada (quando aplicável). Use esta tabela quando Wagner precisar lembrar qual o pricing/posicionamento de cada um:

| Módulo | Tier | Frase de posicionamento | Pricing | Take rate |
|---|---|---|---|---|
| **Financeiro** | 1A (foundational) | "O caixa do seu negócio em ordem em 5 minutos por dia" | Free / Pro R$ [redacted Tier 0] / Enterprise R$ [redacted Tier 0] | 0,5% capped R$ [redacted Tier 0] (gateway próprio só) |
| **NfeBrasil** | 1B (compliance-forced) | "Vender com nota fiscal sem virar contador" | Starter R$ [redacted Tier 0] / Pro R$ [redacted Tier 0] / Enterprise R$ [redacted Tier 0] | n/a (subscription puro) |
| **RecurringBilling** | 2 (volume + take rate) | "Cobre todo mês sozinho, no Pix Automático mais barato" | Starter R$ [redacted Tier 0] / Pro R$ [redacted Tier 0] / Enterprise R$ [redacted Tier 0] | 0,8% capped R$ [redacted Tier 0] (gateway próprio só) |
| **LaravelAI** | 3 (multiplier add-on) | "Pergunte ao seu ERP em português" | Pro R$ [redacted Tier 0] / Enterprise R$ [redacted Tier 0] (add-on) | n/a (subscription puro) |
| **Copiloto** | 3 (multiplier add-on) | "O Copiloto de IA do seu negócio — ele olha os números, sugere metas e te avisa quando algo sai da rota" | Starter R$ [redacted Tier 0] / Pro R$ [redacted Tier 0] / Enterprise R$ [redacted Tier 0] (add-on) | n/a (subscription puro) |

**Modelo dual** (subscription + take rate) só faz sentido em **Modo MoR oimpresso** (quando oimpresso é Merchant of Record, gateway próprio). Em **Modo MoR tenant** (gateway do cliente) → 0% take rate, só subscription. Decisão por business em `pg_credentials.owner` (RecurringBilling) e `fin_business_settings.boleto_strategy` (Financeiro).

**Tickets totais médios estimados** (combinando módulos):
- Tenant Starter: R$ [redacted Tier 0]-149/mês (1 módulo)
- Tenant Pro: R$ [redacted Tier 0]-948/mês (3-5 módulos com add-ons, incluindo Copiloto)
- Tenant Enterprise: R$ [redacted Tier 0]-2.397/mês (todos os módulos full + Copiloto Enterprise + take rate marginal)

**Conexão com meta R$ [redacted Tier 0]mi/ano** (ADR 0022):
- Cenário D (recomendado) precisa 50 enterprise + 120 médios + 200 pequenos = R$ [redacted Tier 0]k/mês
- Sozinho com Wagner + IA, realista 36-48 meses (não 24)
- Roadmap detalhado em `memory/requisitos/_Roadmap_Faturamento.md`
- **Copiloto** é o próprio **orquestrador** dessa meta — ver `memory/requisitos/Copiloto/`. É simultaneamente produto e ferramenta interna (eat-your-own-dog-food: oimpresso usa o Copiloto pra monitorar R$ [redacted Tier 0]mi/ano via meta de plataforma, `business_id = null`).

**Posicionamento comercial do Copiloto** (diferencial vs. LaravelAI):
- **LaravelAI** = engine (knowledge graph + RAG + agent). Compra quem quer "conversar com o ERP".
- **Copiloto** = front de decisão (chat IA orientado a metas + monitoramento + alertas). Compra quem quer "um consultor IA pra bater metas". Pode rodar sem LaravelAI (fallback OpenAI direto).
- Podem vender juntos (Copiloto usa LaravelAI como backend quando presente) ou separados.
- Copiloto surfa brand recognition "Copilot" (GitHub/MS) — ticket premium justificável mesmo no Starter.

**Onde verificar antes de citar pricing pro cliente:** sempre conferir `requisitos/{Modulo}/README.md` (frontmatter) — pode ter sido reajustado.
