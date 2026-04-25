---
name: Revenue thesis dos 4 módulos spec-ready
description: Pricing tiers + take rate model + posicionamento dos 4 módulos promovidos em 2026-04-24
type: reference
originSessionId: dbbb392d-952f-4d8d-9a4a-c93f6603c171
---
Os 4 módulos spec-ready têm **revenue model definido** em ADRs ARQ-0004 de cada (quando aplicável). Use esta tabela quando Wagner precisar lembrar qual o pricing/posicionamento de cada um:

| Módulo | Tier | Frase de posicionamento | Pricing | Take rate |
|---|---|---|---|---|
| **Financeiro** | 1A (foundational) | "O caixa do seu negócio em ordem em 5 minutos por dia" | Free / Pro R$ 199 / Enterprise R$ 599 | 0,5% capped R$ 9,90 (gateway próprio só) |
| **NfeBrasil** | 1B (compliance-forced) | "Vender com nota fiscal sem virar contador" | Starter R$ 99 / Pro R$ 299 / Enterprise R$ 599 | n/a (subscription puro) |
| **RecurringBilling** | 2 (volume + take rate) | "Cobre todo mês sozinho, no Pix Automático mais barato" | Starter R$ 149 / Pro R$ 449 / Enterprise R$ 999 | 0,8% capped R$ 19,90 (gateway próprio só) |
| **LaravelAI** | 3 (multiplier add-on) | "Pergunte ao seu ERP em português" | Pro R$ 199 / Enterprise R$ 599 (add-on) | n/a (subscription puro) |

**Modelo dual** (subscription + take rate) só faz sentido em **Modo MoR oimpresso** (quando oimpresso é Merchant of Record, gateway próprio). Em **Modo MoR tenant** (gateway do cliente) → 0% take rate, só subscription. Decisão por business em `pg_credentials.owner` (RecurringBilling) e `fin_business_settings.boleto_strategy` (Financeiro).

**Tickets totais médios estimados** (combinando módulos):
- Tenant Starter: R$ 99-149/mês (1 módulo)
- Tenant Pro: R$ 348-648/mês (3-4 módulos com add-ons)
- Tenant Enterprise: R$ 1.198-1.598/mês (todos os módulos full + take rate marginal)

**Conexão com meta R$ 5mi/ano** (ver `project_meta_5mi_ano.md`):
- Cenário D (recomendado) precisa 50 enterprise + 120 médios + 200 pequenos = R$ 418k/mês
- Sozinho com Wagner + IA, realista 36-48 meses (não 24)
- Roadmap detalhado em `memory/requisitos/_Roadmap_Faturamento.md`

**Onde verificar antes de citar pricing pro cliente:** sempre conferir `requisitos/{Modulo}/README.md` (frontmatter `revenue_pricing`) — pode ter sido reajustado.
