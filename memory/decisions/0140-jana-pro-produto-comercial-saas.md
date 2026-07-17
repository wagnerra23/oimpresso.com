---
slug: 0140-jana-pro-produto-comercial-saas
number: 140
title: "JANA Pro — Produto comercial SaaS de IA pra PMEs BR (upsell sobre oimpresso, R$ [redacted Tier 0]-499/mês)"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-05-11"
module: copiloto
tags: [produto-comercial, saas, ia, vizra, jana, atendimento, pricing, go-to-market, monetizacao]
supersedes: []
supersedes_partially: []
amends: [0022]
superseded_by: []
related: [0035-stack-ai-canonica-wagner-2026-04-26, 0048-framework-agentes-laravel-ai-vizra-rejeitada, 0053-mcp-server-governanca-como-produto, 0093-multi-tenant-isolation-tier-0, 0094-constituicao-v2-7-camadas-8-principios, 0096-modulo-whatsapp-meta-cloud-api-direto, 0105-cliente-como-sinal-guiar-sem-mandar, 0135-omnichannel-inbox-arquitetura]
pii: false
review_triggers:
  - "<5 clientes pagantes em 90d pós-GA → reavaliar pricing/posicionamento"
  - "Margem operacional <70% (LLM tokens explodindo) → reavaliar cache/Claude Haiku"
  - "Churn mensal >5% pós-Beta → reavaliar value-prop antes de marketing investido"
  - "Concorrente BR lançar produto equivalente <R$ [redacted Tier 0]/mês → reavaliar diferenciação"
  - "≥3 clientes pedirem feature fora do escopo (CRM completo, helpdesk full) → reavaliar product boundaries"
  - "Volume LLM tokens >R$ [redacted Tier 0]k/mês sem revenue cobrindo → freeze growth + buscar Anthropic SDK partner pricing"
---

# ADR 0140 — JANA Pro: produto comercial SaaS de IA pra PMEs BR

## Status

**Aceito 2026-05-11.** Wagner aprovou estratégia de monetizar a stack
de IA já construída (Vizra ADK + memória persistente + Jana
conversational + skill ticket-triage) como **produto upsell sobre
oimpresso**, não SaaS standalone.

## Contexto

Em 2026-05-11 Wagner reconheceu que tem **vantagem competitiva real**:

- Stack AI canônica funcional ([ADR 0035](0035-stack-ai-canonica-wagner-2026-04-26.md))
  rodando em produção (Vizra ADK + LaravelAiSdkDriver + 4 agents próprios)
- Memória persistente Vizra com namespaces `negocio`/`interacoes`/`analises`
  por business (90% concorrentes esquecem entre sessões)
- WhatsApp Baileys próprio em CT 100 ([ADR 0096](0096-modulo-whatsapp-meta-cloud-api-direto.md))
  — custo marginal zero vs Z-API R$ [redacted Tier 0]/chip/mês
- Multi-tenant Tier 0 IRREVOGÁVEL ([ADR 0093](0093-multi-tenant-isolation-tier-0.md))
  desde day 1 — escala sem rework
- Conhecimento BR profundo (SEFAZ, NFe, LGPD, Whatsapp regulatório)
- 7 clientes ativos ([ref clientes](../knowledge/reference_clientes_ativos.md))
  pra dogfood + base de case studies

E falta concorrente forte específico pro segmento **MEI/PME BR**:
- Intercom Fin AI: USD 99-499/mês, americano, ignora SEFAZ
- Zendesk Advanced AI: enterprise pesado, mín 50 seats
- ChatGPT Custom GPT: sem ERP integration, esquece tudo
- Tiledesk/Crisp: bons mas genéricos, sem WhatsApp BR nativo
- Octadesk/Movidesk: foco em ticket, sem IA generativa

**Janela de oportunidade:** próximos 12-18 meses antes que algum
concorrente nacional vertical aporte.

## Decisão

Lançar **JANA Pro** como produto comercial **upsell sobre oimpresso**
em 4 fases × 90 dias, alvo **50 clientes Pro + 5 Enterprise no Q1 2027** =
**R$ [redacted Tier 0] MRR + R$ [redacted Tier 0]k ARR** em 12 meses de produto isolado (sem contar
upsell do core ERP).

### Por que NÃO standalone

- Stack precisa de transactions/nfe_emissoes/conversations do ERP — fora
  do oimpresso o produto perde 80% do valor (LTV automático, NFe contexto,
  WhatsApp Baileys já vinculado)
- Cliente standalone = cliente comprando 2 SaaS (oimpresso + JANA) = mais
  fricção de venda + churn maior
- Manter core ERP como **âncora** + JANA como **upgrade** maximiza ARPU

### Posicionamento

> "JANA — Co-piloto IA pra Pequenas Empresas Brasileiras: prioriza
> tickets, detecta churn, sugere upsell, gera brief executivo diário.
> Fala SEFAZ, WhatsApp BR e LGPD nativamente."

**Target persona:** MEI/EI/EPP com 5-50 funcionários, dono operacional
(não tem gerente comercial dedicado), usa WhatsApp business todo dia,
emite NFe regular.

### 3-Tier Pricing

| Tier | Preço/mês | LLM budget | Target | Margem |
|---|---|---|---|---|
| **JANA Free** | R$ [redacted Tier 0] | 50 triages/mês ad-hoc | Clientes oimpresso curiosos | -R$ [redacted Tier 0] (loss leader) |
| **JANA Pro** | **R$ [redacted Tier 0]** | Brief diário + 500 triages | Operador ativo (ROTA LIVRE-like) | **~94%** |
| **JANA Enterprise** | **R$ [redacted Tier 0]** | Autonomous events + Wagner consultoria 1h/mês | Cliente top 10% LTV | **~92%** |

Modelo de custo LLM Claude Sonnet 4.5 via [`laravel/ai`](https://laravel-ai.dev/):
- Triage ad-hoc: ~3k tokens = R$ [redacted Tier 0]
- Brief diário 10 tools: ~25k tokens = R$ [redacted Tier 0]
- Brief semanal executivo: ~80k tokens = R$ [redacted Tier 0]
- Event-driven (Enterprise): ~150k tokens/dia = R$ [redacted Tier 0]/dia = R$ [redacted Tier 0]/mês

**Asaas recorrente** ([ADR 0008 arq RecurringBilling](../requisitos/RecurringBilling/adr/arq/0008-asaas-como-conta-bancaria-virtual.md))
já em produção — reusa.

## Consequências

### Positivas

- ARR isolado +R$ [redacted Tier 0]k em 12 meses (50 Pro + 5 Enterprise)
- Margem 92-94% sobre LLM costs — sustentável em escala
- Dogfood interno = produto melhora MAIS RÁPIDO
- Diferencia oimpresso de concorrentes que oferecem "ERP + chat WhatsApp"
  mas sem IA generativa contextual
- Reusa toda stack já construída (Vizra + memória + Baileys + Asaas)
- Case studies (ROTA LIVRE como primeiro depoimento)

### Negativas

- **Capacidade de execução:** Wagner solo dev + 4 pessoas time. Fase 4
  (marketing/sales) exige tempo Wagner que conflita com Inter PJ (US-RB-048).
  Mitigação: contratar 1 vendedor freelance pós-Beta validation.
- **Suporte:** vender SaaS = pegar tickets de cliente sobre JANA. Hoje
  zero infraestrutura suporte formal. Mitigação: SLA P3-only nos primeiros
  90d (resposta em 24h via Inbox próprio do JANA — dogfood).
- **LLM cost explosion:** se uso real ultrapassar budget previsto, margem
  cai. Mitigação: rate-limit por plano (Pro=500/mês, Enterprise=unlim com
  fair-use), cache Vizra agressivo namespace `analises.cache`,
  Anthropic Sonnet → Haiku pra queries baratas.
- **LGPD:** brief diário envia dados financeiros agregados pro Wagner +
  email. Mitigação: anonimização opcional + termo aceite explicito por
  cliente (Eliana estuda LGPD — [regras-time §Eliana](../regras-time.md)).
- **Concorrência reativa:** se Octadesk/Movidesk lançarem feature
  equivalente em 6 meses, perdemos janela. Mitigação: time-to-market
  agressivo (Beta em 30d), case studies imediatos com ROTA LIVRE.

### Neutras

- Roadmap detalhado em [`memory/requisitos/Copiloto/JANA-PRO-PRODUCT-PLAN.md`](../requisitos/Copiloto/JANA-PRO-PRODUCT-PLAN.md)
  — 32 US separadas em 4 sprints (JANA-A/B/C/D), criação via batch
  SPEC.md edit ou MCP tasks-create quando aprovar cada fase
- ADR 0022 (Meta R$ [redacted Tier 0]M/ano financeira) ganha vetor adicional — produto
  JANA Pro como acelerador novo, não substitui o core ERP

## Alternativas consideradas e descartadas

### A — Standalone SaaS jana.ai

❌ Descartada. Sem acesso ao ERP, produto perde 80% do valor. Cliente
precisaria configurar n8n/Zapier pra conectar Stripe/whatsapp/email/CRM
externos — fricção mata adoção.

### B — Vender stack IA pra outros ERPs (B2B2B)

❌ Descartada por ora. Modelo "Anthropic for ERPs" é tentador mas exige
SDK robusto + docs + multi-tenant isolation cross-cliente + suporte 24/7.
Wagner é solo, não escala. Pode reavaliar em 18 meses se mercado pedir.

### C — Open source + cloud hosting

❌ Descartada. Vizra ADK já é open source (foundation). Diferencial do
JANA é a **integração ERP nativa + memória persistente curada**. Open
source canibalizaria o produto sem oferecer vantagem comercial.

### D — White-label pra contadores BR

⏸️ Adiada pra Fase 4+. Contadores BR (CRC) são canal natural — eles
revendem oimpresso pra MEIs deles. Mas exige produto maduro + suporte
escalável. Considerar pós-validation 50 clientes diretos.

## Roadmap 4 fases × 90 dias

### Sprint JANA-A (semanas 1-2) — MVP Operacional

**Goal:** brief diário WhatsApp pro Wagner pessoal (Suorte chip), valida
valor antes de cobrar.

Tasks principais:
- US-COPI-201 BriefDiarioAgent + 5 tools internas Vizra
- US-COPI-202 BriefDiarioJob schedule Horizon CT 100 8h BRT
- US-COPI-203 Entrega WhatsApp pelo Suorte chip Wagner pessoal
- US-COPI-204 Persistência mcp_briefs + namespace memória
- US-COPI-205 Dashboard `/copiloto/admin/jana-pro` histórico briefs

Customer: ROTA LIVRE (Larissa biz=4) recebe brief — Wagner valida fit
antes de qualquer cobrança.

### Sprint JANA-B (semanas 3-4) — Beta Pago 5 clientes

**Goal:** 5 clientes pagando R$ [redacted Tier 0]/mês (Beta).

Tasks principais:
- US-COPI-211 Pricing page `/jana-pro` Inertia + 3 tier cards
- US-COPI-212 Asaas subscription integration (reusa ADR 0008 arq RB)
- US-COPI-213 Onboarding wizard `/jana-pro/setup` (horário + canais)
- US-COPI-214 Email brief HTML (Postmark ou Mailtrap)
- US-COPI-215 Métricas brief: open-rate, ações geradas, NPS

Customer: 5 Officeimpresso legacy escolhidos por Wagner + ROTA LIVRE
recebem 60d grátis → 30d trial pago → assinam ou cancelam.

### Sprint JANA-C (semanas 5-8) — GA + Enterprise

**Goal:** GA público + tier Enterprise lançado.

Tasks principais:
- US-COPI-221 JanaProEnterpriseAgent event-driven autonomous
- US-COPI-222 Event listeners Transaction/Message/NfeEmissao
- US-COPI-223 HITL (Human In The Loop) via Modules/ADS Dual Brain
- US-COPI-224 Slack/Teams integration (mercado corporate)
- US-COPI-225 Case study 3 clientes públicos + depoimento Larissa
- US-COPI-226 Pricing 3 tiers GA + lifecycle Asaas trial→pago→suspend

### Sprint JANA-D (semanas 9-12) — Scale + Marketing

**Goal:** 50 Pro + 5 Enterprise. Marketing tracionado.

Tasks principais:
- US-COPI-231 Landing page jana.oimpresso.com (Pages/JanaLanding/Index.tsx)
- US-COPI-232 Demo interativo sandbox biz=999 dados fake
- US-COPI-233 API pública `POST /api/v1/jana/triage` token JWT
- US-COPI-234 LGPD compliance docs + termo aceite digital
- US-COPI-235 Programa afiliados contadores BR 15% recorrente
- US-COPI-236 Recall flow Vizra Sprint 9 — context cache namespace pra
  reduzir custo LLM (refato pós-validation)

## Projeção financeira 12 meses

Hipóteses conservadoras:
- Conversão Free → Pro: 8%
- Churn mensal Pro: 4%
- Churn mensal Enterprise: 2%
- Upgrade Pro → Enterprise: 5% por trimestre
- CAC orgânico (clientes oimpresso): R$ [redacted Tier 0]
- CAC marketing (Fase 4+): R$ [redacted Tier 0]/cliente

| Mês | Free | Pro | Enterprise | MRR (R$) | ARR (R$) |
|---|---|---|---|---|---|
| 1 (MVP) | 5 | 0 | 0 | 0 | 0 |
| 2 (Beta) | 8 | 5 | 0 | 745 | 8.940 |
| 3 (GA) | 15 | 12 | 1 | 2.287 | 27.444 |
| 6 | 40 | 28 | 4 | 6.168 | 74.016 |
| 9 | 60 | 42 | 6 | 9.252 | 111.024 |
| 12 | 80 | 50 | 5 | 9.945 | **119.340** |

**Margem operacional 12m:** ~93%. LLM tokens ~R$ [redacted Tier 0]/mês, hospedagem
alocada CT 100 ~R$ [redacted Tier 0]/mês.

**Lucro operacional 12m:** ~R$ [redacted Tier 0]k. Realista pra Wagner solo + 4 time
sem contratar comercial dedicado.

## Métricas de sucesso (gates)

- **Mês 1:** Brief diário rodando 7d consecutivos sem falha + Wagner
  reporta "salva tempo X"
- **Mês 2:** 3 dos 5 Beta convertidos pra pago após trial 30d
- **Mês 3:** NPS médio briefs ≥ 8
- **Mês 6:** MRR R$ [redacted Tier 0]k + churn <5% + 1 case study publicado
- **Mês 12:** MRR R$ [redacted Tier 0]k + ARR R$ [redacted Tier 0]k + margem >90%

Se gate Mês 2 falhar (<2 conversões) → **freeze produto + revisão value-prop**.
Se gate Mês 6 falhar → **considerar pivot ou descontinuar** (não
queimar Wagner em produto que mercado não quer).

## Refs

- [ADR 0022](0022-meta-5mi-ano-financeira.md) Meta R$ [redacted Tier 0]M/ano
- [ADR 0035](0035-stack-ai-canonica-wagner-2026-04-26.md) Stack AI canônica
- [ADR 0048](0048-framework-agentes-laravel-ai-vizra-rejeitada.md) Vizra ADK
- [ADR 0053](0053-mcp-server-governanca-como-produto.md) MCP server
- [ADR 0093](0093-multi-tenant-isolation-tier-0.md) Multi-tenant Tier 0
- [ADR 0094](0094-constituicao-v2-7-camadas-8-principios.md) Constituição v2
- [ADR 0096](0096-modulo-whatsapp-meta-cloud-api-direto.md) WhatsApp Baileys próprio
- [ADR 0105](0105-cliente-como-sinal-guiar-sem-mandar.md) Cliente como sinal
- [ADR 0135](0135-omnichannel-inbox-arquitetura.md) Omnichannel Inbox
- Skill `ticket-triage` v0.1.0 (`.claude/skills/ticket-triage/SKILL.md`)
- Product Plan detalhado: [`memory/requisitos/Copiloto/JANA-PRO-PRODUCT-PLAN.md`](../requisitos/Copiloto/JANA-PRO-PRODUCT-PLAN.md)
