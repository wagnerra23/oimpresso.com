---
title: oimpresso Insights — Master Product Spec
status: proposed v2 (Wagner valida)
date: 2026-05-09
revised: 2026-05-10 (Pilar 5 DaaS externo descartado, DPO Eliana adiado)
author: Claude Opus 4.7 (sub-agent autônomo, 13 frentes paralelas)
synthesizes: F9-F21 (catalog, DaaS, benchmark, API, newsletter, performance fee, gap schema, state-of-art, Jana habit, multi-vertical, Jana roadmap, pricing usage, network effect)
---

> ⚠️ **REVISÃO v3 — 2026-05-10**: Wagner decidiu:
> - ✅ **Produto ACEITO** como linha de negócio formal
> - ✅ **Eliana cuida do jurídico** — sem counsel externo agora
> - ✅ **Grandfather 12m** dos 41 clientes atuais
> - ✅ **ROTA LIVRE caso público anonimizado OK** (loja de roupa em Gravatal/SC)
> - ❌ **Pilar 5 (DaaS externo) DESCARTADO** — *"não vou vender dados"*
> - ⏸️ **DPO formal Eliana adiado** — estuda LGPD primeiro
> - 🎯 **Hipótese D ESCOLHIDA — modular especializado por vertical** ([ADR 0121](../0121-oimpresso-modular-especializado-por-vertical.md)). Wagner: *"acho que vou fazer módulos especializados"*. Núcleo comum + `Modules/<Vertical>` profundos.
> - **ROTA LIVRE = caso piloto Modules/Vestuario** (não exceção, não gráfica). Outros módulos (ComunicacaoVisual, OficinaAuto) em construção/aguardando sinal.
> - ARR 24m: ~R$ [redacted Tier 0]-1.6M (sem Pilar 5).

# 🎯 oimpresso Insights — Master Product Spec

> **"O ERP+IA brasileiro que sabe seu negócio, lembra suas metas e te alerta antes do problema — porque aprendeu com 50+ empresas como a sua."**

## ⭐ Visão em 1 parágrafo

oimpresso Insights é o **único SaaS B2B brasileiro vertical que combina ERP operacional + IA conversacional persistente (Jana) + benchmark setorial anônimo network-effect**. Cliente paga proporcional ao valor extraído. Cada cliente novo melhora insights pra todos. Schema atual já cobre 70% — falta apenas adicionar classificação vertical + k-anonymity guard. Concorrente leva 24-36m pra alcançar.

---

## 🏛️ 5 pilares do produto

### Pilar 1 — Schema multi-vertical com classificação CNAE
- 52 verticais mapeados (gráfica, oficina, salão, farmácia, contabilidade, etc) — cobertura 1.330 CNAEs IBGE
- Tabelas: `verticals`, `cnae_codigos`, `business_attributes` (JSON flex), `vertical_kpi_definitions`
- Esforço: **18h IA-pair = 2-3 dias Felipe** (gap analysis F15 + schema F18)

### Pilar 2 — Jana habit-forming (cliente abre Jana TODO DIA)
- 5 features Fase 1 (8 semanas): Boa Sexta WhatsApp · Meta Mensal · Ensina Jana · Resumo 18h · Achado da Semana
- Hook Model (Nir Eyal) ético: trigger → action → variable reward → investment
- Anti-padrão duro: máximo **1 push proativo/dia + 1 alerta event-driven**. Nunca guilt-driven.
- KPIs 90d: DAU/MAU >50% · Retention 30d >75% · Streak médio >7d · Churn <3%/m

### Pilar 3 — Pricing usage-based (cliente paga proporcional)
- 5 tiers + Free + Power (R$ [redacted Tier 0] → R$ [redacted Tier 0]/m)
- **Sweet spot**: Pro R$ [redacted Tier 0] (gráfica média) · Premium R$ [redacted Tier 0] (cresce + voice)
- Overage transparente com **4 alertas (50%/80%/100%/150%) + cap suave + upgrade 1-clique**
- **Modelo híbrido GMV opt-in**: 0,5-1% do GMV = unlimited (alinha crescimento)
- Margem 76% (range SaaS 70-85%)

### Pilar 4 — Network effect via benchmark anônimo
- 8 mecanismos (Benchmark · Forecast colaborativo · Recomendação · Pricing index · Churn predict · Lead matching · Feedback fornecedor · Trend regional)
- k-anonymity ≥5 mandatory (LGPD)
- **Defensibilidade 24-36 meses** — concorrente não consegue replicar em <2 anos
- Top 3 a implementar Q1: Benchmark Setorial · Pricing Index · Recomendação Cross-Cliente

### ~~Pilar 5 — DaaS (Data-as-a-Service externa)~~ ❌ DESCARTADO 2026-05-10
- ~~API "Score Gráfica BR" pra fintechs/seguradoras/fornecedores~~
- ~~Performance fee (Recovery 5-10% · Migration 20-30% incremento · Acquisition referral)~~

✅ **Mantido (não-Pilar 5, são marketing/lead gen, não venda de dado externa)**:
- Newsletter+webinar setorial mensal (lead magnet B2B com agregados k-anonymous publicados)
- Relatórios setoriais agregados públicos (não vendidos a terceiros — usados como autoridade)

**Razão da decisão**: Wagner *"não vou vender dados"*. Foco vira manter clientes pagantes felizes com Pilares 1-4. Performance fee Recovery pode voltar como Pilar 6 futuro com sinal qualificado (1 cliente pedir explicitamente).

---

## 💰 Tiers comerciais (clientes oimpresso)

| Tier | R$/m | Brain A queries | Brain B | Voice | Multi-business | Quem |
|------|----:|--------:|--------:|------:|---------------:|------|
| **Free** | 0 | 30 | 0 | — | — | acquisition (trial 90d) |
| **Starter** | 99 | 200 | 0 | — | 1 | gráfica solo / oficina pequena |
| **Pro** | 299 | 2.000 | 50 | — | 1 | PME média + 1 módulo vertical (ex: ROTA LIVRE = núcleo + Modules/Vestuario) |
| **Premium** | 599 | unlim. | 200 | ✅ | 2 | gráfica grande (Vargas/Extreme/Gold-tipo) |
| **Enterprise** | 1.499 | unlim. | unlim. | ✅ | 5 | rede com.visual + SLA |
| **Power** | 2.999 | unlim. + Opus | unlim. | ✅ | unlim. | parceiro/agência/white-label |

**Add-ons modulares**: NFSe (R$ [redacted Tier 0]), Forecast Pro (R$ [redacted Tier 0]), Lead Intelligence Fornecedores (R$ [redacted Tier 0]-20k pra fornecedor pagar).

**Insight crítico**: 6 saudáveis hoje pagam R$ [redacted Tier 0]/m (0,17% GMV deles). Pricing por GMV proposto = **R$ [redacted Tier 0]-5k/m em Enterprise**. Grandfather 12m + upgrade voluntário com features novas.

---

## ~~🔄 Tiers DaaS externa~~ ❌ DESCARTADO 2026-05-10

Wagner: *"não vou vender dados"*. Tabela mantida abaixo apenas pra registro histórico. **Não está no escopo do produto v2.**

| ~~Customer~~ | ~~Produto~~ | ~~Pricing~~ |
|----------|---------|--------:|
| ~~Fintech (Asaas/Iugu/Stone)~~ | ~~API Score Gráfica BR (consulta)~~ | ~~R$ [redacted Tier 0]-50/consulta + base R$ [redacted Tier 0]-4.999/m~~ |
| ~~Seguradora (Porto/Tokio)~~ | ~~Risk score equipamento~~ | ~~R$ [redacted Tier 0]/consulta~~ |
| ~~Fornecedor máquina (HP/Roland/Mimaki)~~ | ~~Demand intelligence~~ | ~~R$ [redacted Tier 0]-1.000/m~~ |
| ~~Fornecedor insumo (3M/Avery/Heytex)~~ | ~~Lead+demand mapping~~ | ~~R$ [redacted Tier 0]-2.000/m~~ |
| ~~Associação setorial (ABIGRAF/ABICOMV)~~ | ~~Relatório anual setorial~~ | ~~R$ [redacted Tier 0]k/ano~~ |
| ~~Investidor/M&A~~ | ~~Setor research bespoke~~ | ~~R$ [redacted Tier 0]-50k/relatório~~ |

~~**ARR projetado DaaS 24m realista**: R$ [redacted Tier 0]-400k~~ → **REMOVIDO. Foco em Pilares 1-4 (clientes próprios pagantes).**

**Caminho alternativo aberto** (não-DaaS, sem venda de dado externo):
- Newsletter+webinar com agregados k-anonymous = lead gen (gratuito, autoridade)
- Relatório anual público setorial (gratuito, branding)
- Se 1+ cliente atual pedir Performance Fee Recovery, abre Pilar 6 separado

---

## 🛣️ Roadmap 24 meses (jul/2026 → jun/2028)

### Ano 1 — Foundation + Habit + DaaS interno

| M | Trim | Foco | Entregáveis | KPI |
|---|------|------|-------------|-----|
| **M1** | jul/26 | Foundation | Schema multi-vertical + CNAE backfill 41 atuais | 100% biz com vertical_id |
| **M2** | ago-set/26 | Habit Triggers | WhatsApp Business API + Boa Sexta + alerts | tap-rate >20% |
| **M3** | set-out/26 | Variable Rewards | Insights drops + Benchmark anônimo k-anon ≥5 | DAU/MAU >40% |
| **M4** | nov-dez/26 | Investment Loops | Goals + Streaks + achievements (ético) | streak médio >7d |
| **M5** | jan-fev/27 | Voice + PWA | Whisper+GPT-4o+TTS + PWA mobile | voice 10%+ queries |
| **M6** | mar-jun/27 | Network Effect + DaaS interno | API parceiros + Benchmark Premium pago + Snapshot DaaS Tier 1-3 | 5+ clientes Snapshot pagantes |

**ARR fim ano 1 (jun/27)**: R$ [redacted Tier 0]k-1M (vs R$ [redacted Tier 0]k atual = +50-100%)

### Ano 2 — DaaS externo + Network maturity

- Q3-Q4/27: 1 piloto Asaas + 1 fornecedor máquina + ABIGRAF endorsement
- Q1-Q2/28: 5 fintechs + 3 fornecedores + 2 seguradoras
- Multi-vertical scale: 30 clientes oficina_auto + 30 outros verticais

**ARR fim ano 2 (jun/28)**: R$ [redacted Tier 0]-2,5M (cenário realista — 30-50% da meta R$ [redacted Tier 0]M)

---

## 🛠️ Stack técnico (OSS-first, evita reinventar)

### Já existe no oimpresso (70% feito)
- Multi-tenant Tier 0 (`business_id` global scope)
- Jana memória persistente (`copiloto_memoria_facts`)
- MCP governance (14+ tabelas)
- Time-series `transactions` indexada desde 2017
- Skill `officeimpresso-financial-snapshot` (Firebird → relatório auto)

### Adicionar (OSS, baixo custo)
- **Metabase** self-host CT 100 — BI multi-tenant nativo (substitui R$ [redacted Tier 0] vs Looker R$ [redacted Tier 0]k+/m)
- **PostHog** self-host CT 100 — behavioral analytics (substitui Mixpanel R$ [redacted Tier 0]k+/m)
- **dbt Core + DuckDB** — modelagem analítica + OLAP <100ms
- **WhatsApp Business API** (oficial Meta) — habit triggers
- **FCM (Google)** — push gratuito

### Construir (diferencial vertical)
- Schema multi-vertical CNAE (18h)
- Jana habit features (8 semanas Fase 1)
- Network effect engine (Q1) — apenas benchmark interno (cliente vê, não vendido externamente)
- ~~DaaS API + dashboard parceiros (M6)~~ ❌ removido — sem venda de dado externo

---

## 🎯 ARR consolidado projetado

### Cenário conservador (12m)
| Linha | ARR adicional |
|-------|--------------:|
| Upgrade saudáveis (3 viram Enterprise) | R$ [redacted Tier 0]k |
| Win-back churned (1-2 voltam) | R$ [redacted Tier 0]k |
| Snapshot Tier 1-3 (10 clientes) | R$ [redacted Tier 0]k |
| Performance fee (1-2 contratos Recovery) | R$ [redacted Tier 0]k |
| **Total adicional 12m** | **R$ [redacted Tier 0]k** |
| ARR total ano 1 (R$ [redacted Tier 0]k atual + R$ [redacted Tier 0]k) | **~R$ [redacted Tier 0]k** |

### Cenário realista 24m — REVISADO 2026-05-10 (sem Pilar 5)
| Linha | ARR |
|-------|----:|
| ERP oimpresso (clientes Pro+Premium) | R$ [redacted Tier 0]k-1.2M |
| Snapshot Tier 1-3 + Insights Pro (interno) | R$ [redacted Tier 0]k-400k |
| ~~DaaS externo (API + relatórios)~~ | ~~R$ [redacted Tier 0]k-400k~~ ❌ removido |
| ~~Performance fees~~ | ~~R$ [redacted Tier 0]-300k~~ ⏸️ adiado pra Pilar 6 com sinal |
| **Total ano 2 (jun/28)** | **R$ [redacted Tier 0]-1.6M (20-32% meta R$ [redacted Tier 0]M)** |

### Para chegar em R$ [redacted Tier 0]M sem DaaS externo
- **150+ clientes ERP Pro/Premium = R$ [redacted Tier 0]M** (mais clientes pra compensar)
- **80+ Snapshot Tier 3 = R$ [redacted Tier 0]M**
- Newsletter+webinar setorial = lead gen + autoridade (não receita direta)
- **Total R$ [redacted Tier 0]-5M atingível em ~4 anos** (vs 3 anos com DaaS externo)
- **Tradeoff aceitado por Wagner**: mais lento mas alinhado com valor "não vou vender dados"

---

## 🚨 Riscos + mitigações

| Risco | Probabilidade | Impacto | Mitigação |
|-------|--------------:|--------:|-----------|
| **Cliente reagir mal "dado meu alimenta concorrente"** | Alto | Alto | Opt-in granular + transparência radical + termo v3 jurídico antes de codar |
| **LGPD ANPD multar** | Médio | Alto | Counsel externo + DPO + DPIA + DPA com 41 atuais ANTES de qualquer código DaaS |
| **Wagner bottleneck único** | Alto | Médio | Delegar 80% conteúdo pra Felipe[F]/Eliana[E] |
| **Notification fatigue (Jana habit)** | Médio | Alto | Limite duro 1 push/dia + opt-in granular + escape 1-clique |
| **Voice (M5) latência+custo** | Alto | Médio | Cache Redis + Brain A pra perguntas simples + glossário PT-BR + GO/NO-GO formal pós-M4 |
| **Schema multi-vertical: cliente não preenche atributos** | Alto | Alto | Jana proativa pede em momento contextual + onboarding wizard + gamificação |
| **k-anonymity quebra (n<5 em vertical novo)** | Médio | Médio | Fallback nacional + mensagem honesta "dados insuficientes ainda" |
| **Concorrente copiar pricing GMV** | Baixo | Baixo | Mubisys/Zênite mantêm desktop; pricing público facilmente replicável mas dado acumulado não |

---

## ✅ Próximos passos imediatos (próximas 2 semanas)

### Pre-launch obrigatório (Wagner faz)
1. **Counsel LGPD externo** revisa cláusula contratual + DPA + termo v3 — **gate inegociável** (R$ [redacted Tier 0]-15k one-time)
2. **DPO formalizado** (interno ou retainer R$ [redacted Tier 0]-5k/m)
3. **Aprovar pricing tiers** (Free/Starter/Pro/Premium/Enterprise/Power)
4. **Aprovar Hook Model ético** (regras de push, anti-spam, anti-guilt)
5. **Aprovar 10 verticais prioritários** (lista F18)

### Sprint 1 (semana 1-2 de jul/26)
1. Felipe[F]+Wagner pareados — schema multi-vertical (18h IA-pair)
2. Eliana[E] — opt-in dos 41 clientes atuais (cláusula contratual)
3. Maiara[M] — coletar 5 saudáveis pra piloto Tier 2 Snapshot
4. Wagner — counsel LGPD + DPO

### Sprint 2-3 (M2 ago/26)
1. Setup WhatsApp Business API (KYC Meta 14d)
2. Build "Boa Sexta" digest (4 sem)
3. Beta privado 5 saudáveis

---

## 🔑 Decisões críticas (Wagner valida) — atualização 2026-05-10

- [ ] Aprovar produto v2 (sem Pilar 5) como linha de negócio formal (ADR canon)?
- [x] **Pilar 5 DaaS externo: NÃO** ✅ Wagner decidiu 2026-05-10
- [x] **DPO Eliana: ADIADO** ✅ Eliana estuda LGPD com calma primeiro
- [ ] OK pra investir R$ [redacted Tier 0]-60k em counsel LGPD externo Q3/26 (escopo menor sem Pilar 5)?
- [ ] OK pra grandfather 12m dos 41 clientes atuais (sem upgrade forçado)?
- [ ] OK pra ROTA LIVRE Premium grátis até 2027 (compensação piloto)?
- [ ] Manter ROTA LIVRE como caso público anonimizado nas comunicações?

---

## 📎 Referências (tudo o que sustenta este spec)

- [F9 Catálogo 15 produtos](../sales/2026-05/20-catalogo-produtos-informacao-vendaveis.md)
- [F10 Plano operacional DaaS](daas-oimpresso-plano-operacional.md)
- [F11 Benchmark setorial real (gitignored)](../research/2026-05-receitas-officeimpresso/BENCHMARK-SETORIAL-AMOSTRA.md)
- [F12 API Score Gráfica BR](api-score-grafica-br-data-product.md)
- [F13 Newsletter+webinar 12m](../sales/2026-05/21-newsletter-webinar-setorial-12m.md)
- [F14 Performance fee 3 modelos](modelos-performance-fee-comissionamento.md)
- [F15 Gap schema multi-cliente](gap-schema-oimpresso-multi-cliente-multi-vertical.md)
- [F16 State of art SaaS](../research/2026-05-prospeccao/08-state-of-art-saas-multi-vertical.md)
- [F17 Jana habit-forming Hook Model](jana-habit-forming-hook-model.md)
- [F18 Schema multi-vertical CNAE](schema-multi-vertical-cnae-taxonomia.md)
- [F19 Roadmap Jana 12m](roadmap-jana-12m-habit-multi-vertical.md)
- [F20 Pricing usage-based Jana](pricing-jana-usage-based.md)
- [F21 Network effect engine](network-effect-engine-oimpresso.md)
- [Skill OfficeImpresso financial snapshot](../../../.claude/skills/officeimpresso-financial-snapshot/SKILL.md)
- [Schema Firebird canônico](../../requisitos/Officeimpresso/OFFICEIMPRESSO-FIREBIRD-SCHEMA.md)
- [Foco empresa 4 camadas](foco-empresa-2026-2027-camadas-priorizadas.md)

---

**Este spec é a síntese de 13+ análises paralelas + análise direta no banco produção. Substitui ADRs feature-wish individuais por uma visão coerente. Wagner valida → vira ADR canon (próximo número, ~0125-0130) e desbloqueia execução.**
