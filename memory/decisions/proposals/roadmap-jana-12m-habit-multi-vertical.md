---
title: Roadmap Jana 12m — habit-forming + multi-vertical — jul/2026 → jun/2027
status: proposed (Wagner valida — DIVERGE de roadmap-tecnico-12m em premissas)
date: 2026-05-09
author: Claude Opus 4.7 (VP product/Jana roleplay)
type: roadmap proposta
relates:
  - proposals/roadmap-tecnico-12m-2026-2027.md (R25 — roadmap ERP base, com.visual focused)
  - proposals/auto-vertical-strategy.md (R26 — STAY-FOCUSED recomendado)
  - proposals/feature-financial-snapshot-multi-cliente.md (DaaS Snapshot wedge)
  - decisions/0035-stack-ai-canonica-wagner-2026-04-26.md (laravel/ai + Agents próprios)
  - decisions/0094-constituicao-v2-7-camadas-8-principios.md (P4 Loop fechado por métrica, P6 Multi-tenant)
  - decisions/0105-cliente-como-sinal-guiar-sem-mandar.md (cliente sinal qualificado)
  - decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md (10x IA-pair)
  - decisions/0093-multi-tenant-isolation-tier-0.md (business_id Tier 0)
---

# Roadmap Jana 12m — habit-forming + multi-vertical — 2026-05-09

> **AVISO DE TENSÃO COM R25 + R26.** Este roadmap propõe transformar Jana em produto habit-forming multi-vertical com benchmark setorial e usage-based pricing. Ele **assume premissas mais agressivas** que `roadmap-tecnico-12m-2026-2027.md` (que prevê 5-7 clientes ERP em 12m, ARR ~R$ [redacted Tier 0]k) e que `auto-vertical-strategy.md` (que recomendou STAY-FOCUSED em com.visual). Se o roadmap ERP-base (R25) entregar 5 clientes em mai/2027, **DAU/MAU >50% e R$ [redacted Tier 0]-2M ARR não são plausíveis**: math abaixo mostra teto realista ~R$ [redacted Tier 0]-400k ARR. Wagner precisa reconciliar: aceitar Jana como camada premium SOBRE o roadmap base R25 (recomendado) OU substituir R25 por este (não recomendado — quebra ADR 0105).

---

## Visão de produto (1 frase)

> *"Jana é o IA companion do dono de pequena empresa BR — sabe seu negócio, lembra suas metas, alerta antes do problema."*

Jana é a **camada de retenção** do oimpresso. ERP entrega valor transacional (NFC-e, OS, financeiro); Jana entrega valor relacional (memória, alerta, insight, ritual diário). Sem Jana, ERP é commodity. Com Jana, oimpresso vira utility diária — cliente abre todo dia mesmo quando não precisa fazer nada operacional.

---

## Premissas (validar com Wagner)

- **Time fixo de 5 pessoas** (W/F/M/L/E). Sem hire planejado.
- **Fator 10x IA-pair em codáveis** (ADR 0106). NÃO se aplica a wallclock cliente, smoke fiscal, vendas, voice training real, integração Meta WhatsApp Business.
- **Roadmap ERP base (R25) executa em paralelo.** Jana M1-M6 são **incrementos sobre R25** — não substituem. Capacidade Jana ≤30% do total dev (Felipe ~25% + Luiz IA-pair em backend Jana). 70% restante segue R25 (smoke SEFAZ, 2º-7º cliente ERP, mwart-gate, ABICOMV).
- **Cliente como sinal qualificado (ADR 0105) IRREVOGÁVEL.** Toda feature Jana M1-M6 deve ter sinal antes de virar US ativa. Lista atual de sinais qualificados (mai/2026): Larissa pergunta faturamento (CYCLE-01 validado em prod). Restante = ADR feature-wish até cliente real pedir.
- **Brain B (Sonnet/Opus) custo controlado.** Toda chamada Brain B passa pelo `decide()` do ADS Universal (skill `ads-route` Tier A, ativa S5 ~jul/2026). Política default: ALLOW_BRAIN_A pra perguntas simples, REQUIRE_BRAIN_B só pra reasoning multi-step ou voice longform.
- **Multi-tenant Tier 0 (ADR 0093) IRREVOGÁVEL.** Toda feature Jana respeita `business_id` global scope. Push notification, voice, benchmark — todos passam por filtro tenant.
- **Cycle = 2 semanas, 6 milestones × 2 cycles.** Total 12 cycles dedicados a Jana (de 24 cycles totais ano).

---

## 6 milestones × 2 cycles cada

### M1 (jul/2026) — Foundation: schema multi-vertical + sinal qualificado

> Pré-requisito: R25 M1 entregue (smoke SEFAZ verde, mwart-gate enforce). Jana M1 depende de saúde tech base.

**Cycle J1 (14-27 jul/26)**
- **Goal primary:** Schema multi-vertical — tabela `verticals` (CNAE → vertical_id) + `business_attributes` (key/value flexível por business)
- **Goal secondary:** Migration backfill — atribuir `vertical_id` aos businesses ativos (1 ROTA LIVRE com.visual + 2º cliente se M2 R25 fechou)
- **Owner:** [F] (schema), [W] (validar taxonomia)
- **Esforço:** ~16h codáveis (1.6h IA-pair) + 4h backfill manual com Wagner
- **KPI exit:** schema migrado em prod, 100% businesses ativos têm `vertical_id` setado

**Cycle J2 (28 jul-10 ago/26)**
- **Goal primary:** Jana sabe vertical do cliente — `ContextoNegocio` injeta `vertical_id` em todo prompt Brain A/B (ADR 0035 stack)
- **Goal secondary:** Endpoint `GET /jana/business-profile` retorna vertical + 5 atributos chave (faturamento_medio, ticket_medio, has_nfe_auto, has_recurring, primary_persona)
- **Owner:** [F] (ContextoNegocio extension), [L] (endpoint + tests)
- **Esforço:** ~12h codáveis (1.2h IA-pair) + Pest tenancy local (Wagner exige ADR 0119 stale)
- **KPI exit:** Jana responde "qual é o vertical do meu negócio?" corretamente em ROTA LIVRE biz=4

**KPI M1:**
- 100% businesses ativos com `vertical_id` (hoje: 0%)
- Jana responde 3 perguntas vertical-aware em biz=4 sem alucinação
- Schema doc atualizado em `memory/requisitos/Jana/SCHEMA-multi-vertical.md`

**Capacidade Jana M1:** ~3h IA-pair = ~1 dia útil Felipe (resto cycle = R25 M1+M2)

---

### M2 (ago-set/2026) — Habit Triggers: WhatsApp digest + push opt-in

> **Sinal qualificado pré-requisito:** Larissa (biz=4) usou Jana ≥3x/semana últimos 30d. Se NÃO, M2 vira ADR feature-wish e cycles reabsorvem em R25.

**Cycle J3 (11-24 ago/26)**
- **Goal primary:** Integração WhatsApp Business API oficial Meta (onboarding Meta ~14d wallclock — começar EM cycle J1 pra estar pronto J3)
- **Goal secondary:** FCM web push (gratuito) — opt-in granular (3 categorias: alerta_caixa, alerta_estoque, digest_semanal)
- **Owner:** [E] (Meta onboarding wallclock), [F] (FCM + opt-in UI), [L] (WhatsApp send service)
- **Esforço:** Meta wallclock 14d (humano-limitado), FCM ~16h codáveis (1.6h IA-pair), opt-in UI ~8h (0.8h IA-pair)
- **KPI exit:** Larissa recebe 1 WhatsApp test "Boa tarde Larissa, faturamento hoje R$ X" em prod

**Cycle J4 (25 ago-7 set/26)**
- **Goal primary:** "Boa Sexta" weekly digest — segunda-feira 8h BRT envia top 3 fatos da semana (faturamento, top cliente, alerta)
- **Goal secondary:** Alertas inteligentes contextuais (déficit caixa, ticket abaixo do médio histórico, cliente VIP sem compra 30d)
- **Owner:** [F] (digest service + scheduler), [L] (regras alerta), [W] (validar copy PT-BR com Larissa)
- **Esforço:** digest ~20h codáveis (2h IA-pair) + alertas ~16h (1.6h IA-pair)
- **KPI exit:** 3 segundas seguidas Larissa abre digest em <2h após envio (tap-rate ≥66%)

**KPI M2:**
- Tap-rate notificações ≥20% (compromisso template) — meta stretch 30%+ se digest semanal
- 0 opt-out total (cliente desinstala/silencia 100% das notificações)
- ≥1 alerta inteligente "salvou" cliente (anedota validada qualitativamente — Larissa diz "obrigada, cobrei a tempo")

**Capacidade Jana M2:** ~6h IA-pair codáveis + ~14d wallclock Meta (Eliana drive)

**Risco crítico M2:** Meta WhatsApp Business onboarding pode levar >14d (verificação CNPJ, número, política). **Mitigação:** começar onboarding em cycle J1 (não J3). Fallback: usar ZAPI/Twilio temporário (custo ~R$ [redacted Tier 0]/msg).

---

### M3 (set-out/2026) — Variable Rewards: insights drops + benchmark anônimo

> **Sinal qualificado pré-requisito:** ROTA LIVRE + 2º cliente ERP ativos ≥30d (R25 M2 entregou 2º cliente). Se ainda 1 cliente = SEM benchmark (k-anon ≥5 impossível). Cycle vira "consolidação Jana M1+M2 + Snapshot DaaS MVP" (ver `feature-financial-snapshot-multi-cliente.md`).

**Cycle J5 (8-21 set/26)**
- **Goal primary:** Insights drops surpresa — Jana detecta padrão e envia notificação ad-hoc ("encontrei R$ X em margem perdida no produto Y" / "cliente Z tem ticket 30% abaixo da média histórica")
- **Goal secondary:** Tabela `jana_insights_log` — auditoria de toda insight enviada (timestamp, business_id, type, payload, opened_at, dismissed_at)
- **Owner:** [F] (detector + log), [L] (templates copy + Brain A/B router via `decide()`)
- **Esforço:** ~24h codáveis (2.4h IA-pair) + Pest com fixtures de 6 meses dados ROTA LIVRE
- **KPI exit:** Larissa recebe ≥2 insights/mês com tap-rate >40%

**Cycle J6 (22 set-5 out/26)**
- **Goal primary:** Benchmark setorial anônimo (gated por k-anonymity ≥5 dentro do mesmo `vertical_id`)
- **Goal secondary:** UI "compare-se" — card no dashboard Jana mostrando "seu ticket médio: R$ X / mediana setor: R$ Y / top quartil: R$ Z"
- **Owner:** [F] (k-anon engine + service), [L] (UI card MWART)
- **Esforço:** ~32h codáveis (3.2h IA-pair) + LGPD review obrigatório (ADR canon nova)
- **KPI exit:** se ≥5 clientes com.visual ativos = card live; senão card "ainda coletando dados (faltam X gráficas)"

**KPI M3:**
- DAU/MAU ≥40% (compromisso template) — métrica baseada em login + ≥1 query Jana ou abertura digest no dia
- 0 leaks PII em insights (audit log + LGPD review)
- ≥1 cliente diz "tô viciado" qualitativamente (Wagner pergunta em call discovery)

**Capacidade Jana M3:** ~6h IA-pair codáveis + ~8h LGPD/ADR

**Risco crítico M3:** k-anon ≥5 exige 5 clientes do mesmo vertical com 30d+ histórico. R25 prevê 4 clientes em set/2026. **Mitigação:** se <5, mostrar "ainda coletando" (graceful degradation) + permitir benchmark cross-vertical genérico (ex: "negócios SP do seu porte"). NÃO faker data — viola Constituição.

---

### M4 (nov-dez/2026) — Investment Loops: goals + streaks éticos

> Pré-requisito: M3 entregue insights + benchmark com tap-rate >40%.

**Cycle J7 (6-19 nov/26)**
- **Goal primary:** Goal-setting mensal — cliente define meta ("faturar R$ [redacted Tier 0]k em novembro") via UI Jana, sistema tracka diário
- **Goal secondary:** Daily check-in 8h BRT — Jana envia "Boa terça Larissa, você está em 67% da meta novembro (R$ [redacted Tier 0]k de R$ [redacted Tier 0]k)"
- **Owner:** [F] (goal model + service), [L] (UI + check-in scheduler), [W] (validar copy PT-BR + Larissa onboarding)
- **Esforço:** ~24h codáveis (2.4h IA-pair) + onboarding wallclock 1d Larissa
- **KPI exit:** ≥2 clientes ativos definiram goal mensal em prod

**Cycle J8 (20 nov-3 dez/26)**
- **Goal primary:** Streak (dias consecutivos abrindo Jana) + 3 achievements desbloqueáveis ("primeira meta atingida", "30d streak", "1ª NFC-e do mês emitida via auto-emission")
- **Goal secondary:** Ethical guardrails — cooldown notificação noturna (22h-7h silêncio), opt-out 1-click, **NÃO usar dark patterns** (ex: "perdeu seu streak de 47 dias" ❌). Achievement = celebração, não chantagem
- **Owner:** [L] (streak engine), [F] (guardrails + audit), [W] (review ético — Constituição P7 Transparência)
- **Esforço:** ~16h codáveis (1.6h IA-pair) + ADR ética obrigatória ("Jana ethical-by-default")
- **KPI exit:** avg streak ≥7d em ≥1 cliente (compromisso template) + 0 opt-out por "incomodada"

**KPI M4:**
- Avg streak ≥7d em ≥50% clientes ativos
- ≥1 cliente atingiu meta mensal definida via Jana (validação outcome)
- 0 reclamação dark pattern em qualitativo Wagner

**Capacidade Jana M4:** ~4h IA-pair codáveis + ADR ética + buffer férias dez

**Risco crítico M4:** gamificação pode virar predatória mesmo sem intenção. **Mitigação:** ADR canon "Jana ethical-by-default" formaliza limites (sem chantagem por streak perdido, sem urgência fake, opt-out total 1-click). Wagner aprova personalidade Jana.

---

### M5 (jan-fev/2027) — Voice + Mobile PWA

> **Decisão crítica:** voice é **a aposta mais arriscada do roadmap**. Custo Brain B alto, latência >2s mata UX, treino dataset BR-PT-gráfico inexistente. Pré-requisito: M4 entregue avg streak ≥7d (sinal de habit real, justifica investimento voice).

**Cycle J9 (5-18 jan/27)**
- **Goal primary:** Voice MVP via Whisper API (transcribe) + GPT-4o (route via `decide()` ADS) + TTS via ElevenLabs ou OpenAI TTS
- **Goal secondary:** Latência e2e p95 ≤3s (alvo ambicioso). Cache layer (Redis) pra perguntas frequentes ("quanto faturei hoje" cacheado 5min)
- **Owner:** [F] (voice pipeline + cache), [W] (validar latência real BR), [L] (Pest pipeline)
- **Esforço:** ~40h codáveis (4h IA-pair) + ADR voice + custo R$ [redacted Tier 0]-3 por query Brain B (vs R$ [redacted Tier 0] Brain A)
- **KPI exit:** Larissa fala "Jana, quanto faturei?" no carro indo pra gráfica e recebe resposta correta em ≤3s

**Cycle J10 (19 jan-1 fev/27)**
- **Goal primary:** PWA mobile-first — Pages Jana otimizadas pra mobile, install prompt, offline-first cache (queries últimas 24h disponíveis offline)
- **Goal secondary:** Decisão app nativo iOS/Android (sinal? 3+ clientes pedindo OU PWA install rate <30% = sinal pra nativo). Default: **NÃO fazer nativo** (custo alto, PWA suficiente até H2 2027)
- **Owner:** [F] (PWA + service worker), [L] (offline cache + Pest)
- **Esforço:** ~20h codáveis (2h IA-pair) + ADR mobile decision
- **KPI exit:** PWA install rate ≥40% em mobile + voice queries ≥10% do total queries Jana (compromisso template)

**KPI M5:**
- Voice queries ≥10% do total Jana queries
- Voice latência p95 ≤3s (≤2s stretch)
- PWA install rate ≥40% mobile
- Custo Brain B sob controle: ≤R$ [redacted Tier 0]/cliente/mês em voice (alvo R$ [redacted Tier 0])

**Capacidade Jana M5:** ~6h IA-pair codáveis + ADRs

**Risco crítico M5 (HIGHEST):** voice é a feature mais cara e mais imprevisível. **3 modos de falha:**
1. **Latência >5s** → cliente abandona ("falo com Jana e ela demora") — mitigação: cache + fallback texto se voice falhar
2. **Custo Brain B explode** (R$ [redacted Tier 0]+/cliente/mês) — mitigação: gate `decide()` ALLOW_BRAIN_A em 80% queries simples
3. **Português BR mal compreendido** (sotaque, gírias setoriais "ploter", "calandra", "vinilona") — mitigação: glossário PT-BR-grafico passado como system prompt + fine-tune Brain A em 100 queries reais Larissa

Se M5 falhar (qualquer dos 3 modos), recuar pra "Jana texto-only premium" e adiar voice 6m.

---

### M6 (mar-jun/2027) — Network Effect + DaaS Premium

> Pré-requisito: M5 entregue voice MVP funcional + 5+ clientes ERP ativos (compromisso R25).

**Cycle J11 (2-15 mar/27)**
- **Goal primary:** API pública pra parceiros — `oimpresso.com/api/v1/partners/*` (read-only inicial: business_profile, score saúde financeira, vertical-benchmark anônimo). OAuth2 + rate limit + tier free/paid
- **Goal secondary:** 1º parceiro integrado (target: fintech BR ou fornecedor papel/tinta — "use score oimpresso pra dar crédito melhor pra cliente")
- **Owner:** [F] (API + auth), [W] (parceria comercial — wallclock 30-60d)
- **Esforço:** ~32h codáveis (3.2h IA-pair) + parceria wallclock real
- **KPI exit:** 1 parceiro integrado em prod + 1 chamada API real

**Cycle J12 (16-29 mar/27)**
- **Goal primary:** Benchmark Premium pago — R$ [redacted Tier 0]-299/m extra pra ver ranking detalhado dentro do vertical (top 10% / mediana / bottom 10% em 8 métricas)
- **Goal secondary:** Snapshot DaaS Tier 1 lançado (ver `feature-financial-snapshot-multi-cliente.md` — 5 clientes legacy alvo)
- **Owner:** [F] (Benchmark Premium service), [E] (Snapshot vendas), [W] (pricing review)
- **Esforço:** ~24h codáveis (2.4h IA-pair) + comercial wallclock
- **KPI exit:** ≥2 clientes pagando Benchmark Premium + ≥3 clientes Snapshot Tier 1

**KPI M6:**
- ≥3 parceiros API integrados (compromisso template = 5; realista 3 wallclock)
- ≥R$ [redacted Tier 0]k ARR DaaS combinado (Benchmark Premium + Snapshot — compromisso template R$ [redacted Tier 0]k é stretch)
- ≥5 clientes ERP ativos (compromisso R25)
- Network effect mensurável: cliente novo = melhora benchmark pra todos (k-anon ≥10 em ≥1 vertical)

**Capacidade Jana M6:** ~6h IA-pair codáveis + comercial wallclock

---

## Métricas overarching (target 12m — jun/2027)

| Métrica | Baseline jul/26 | Target template (aspiracional) | Realista R25-aligned |
|---|---|---|---|
| **DAU/MAU** | sem medição (1 cliente) | >50% | >40% (com 5-7 clientes) |
| **Retention 30d** | sem medição | >75% | >70% (5-7 clientes; com 1 cliente é 100% trivial) |
| **ARPU médio** | ~R$ [redacted Tier 0] (Larissa não paga formal) | R$ [redacted Tier 0]+ | R$ [redacted Tier 0]-900 (R25 prevê tier R$ [redacted Tier 0] dominante) |
| **NPS** | sem medição | >40 | >40 (achievable se Jana entregar valor) |
| **Cliente "viciado"** (>5 sessions/sem) | n/a | >30% MAU | >30% MAU em 3-4 clientes (1-2 viciados) |
| **ARR total combinado** (ERP + DaaS) | ~R$ [redacted Tier 0] | R$ [redacted Tier 0]-2M (template) | **R$ [redacted Tier 0]-400k realista** ⚠️ |

> **Discrepância ARR.** Template projeta R$ [redacted Tier 0]-2M mas R25 base prevê R$ [redacted Tier 0]k ARR ERP em mai/2027 (5-7 clientes × R$ [redacted Tier 0]-1.499). Pra bater R$ [redacted Tier 0]M precisaria 100+ clientes ERP OU 50 clientes Snapshot DaaS no Tier 3 (R$ [redacted Tier 0]) — ambos violam ADR 0105 (sem sinal hoje pra esse volume). Math realista 12m fim mai/2027:
>
> - ERP base R25: 5-7 clientes × R$ [redacted Tier 0]-1.499 = **R$ [redacted Tier 0]-126k ARR**
> - Snapshot DaaS M6: 3-5 clientes × R$ [redacted Tier 0]-299 = **R$ [redacted Tier 0]-18k ARR**
> - Benchmark Premium M6: 2-5 clientes × R$ [redacted Tier 0]-299 = **R$ [redacted Tier 0]-18k ARR**
> - API parceiros: 1-3 deals × R$ [redacted Tier 0]-50k = **R$ [redacted Tier 0]-150k ARR (alto risco wallclock)**
> - **Total realista: R$ [redacted Tier 0]-312k ARR.** Pra bater R$ [redacted Tier 0]M = **pivot massivo Snapshot multi-cliente como produto principal** (precisa ADR canon nova + revisitar ADR 0105 com sinais novos).

---

## Decisões críticas pra fazer agora (gatilhos M-by-M)

### 1. Aprovar schema multi-vertical (gatilho M1, jul/26)
**O quê:** schema `verticals` + `business_attributes` + ContextoNegocio extension.
**Risco se não aprovar:** Jana fica genérica, não diferencia gráfica vs oficina vs comércio.
**Custo:** 4h IA-pair + 1 ADR canon.
**Bloqueia:** TODOS milestones M2-M6 (insights e benchmark dependem de vertical_id).

### 2. Aprovar Hook Model ético (gatilho M2-M4, ago-dez/26)
**O quê:** ADR canon "Jana ethical-by-default" — define personalidade Jana, limites de notificação, opt-out garantido, anti-dark-pattern explícito.
**Risco se não aprovar:** gamificação vira predatória, cliente sente invadido, churn por desconfiança.
**Custo:** 4h ADR + review trimestral ético Wagner.
**Referência:** Constituição v2 P7 Transparência (ADR 0094 §7).

### 3. Aprovar pricing usage-based híbrido (gatilho M5-M6, jan-mar/27)
**O quê:** ARPU expansion via tiers consumption (X queries Jana/m grátis, Y voice queries, Z benchmark views — overage paga). NÃO substitui assinatura base — adiciona camada premium.
**Risco se não aprovar:** ARPU plana em R$ [redacted Tier 0] médio, ARR teto baixo.
**Bloqueia:** R$ [redacted Tier 0]k+ ARR (sem usage-based, math não fecha mesmo no realista).
**Pré-condição:** ≥5 clientes ERP ativos (R25 M6) + ≥3 clientes pedindo "quero mais Jana" qualitativamente (sinal ADR 0105).

### 4. Aprovar network effect via benchmark (gatilho M3, set/26)
**O quê:** ADR canon "Benchmark setorial anônimo Tier 0" — k-anon ≥5, LGPD-compliant, opt-in granular.
**Risco se não aprovar:** Jana vira "ChatGPT do meu negócio" sem moat. Network effect = único diferencial defensável vs concorrência (Mubisys, Zênite não têm).
**Custo:** ADR canon + LGPD review (~8h Wagner) + 32h codáveis (3.2h IA-pair).

### 5. Decidir voice GO/NO-GO (gatilho M5, dez/26-jan/27)
**O quê:** ADR voice — vai ou não vai. Pré-requisito qualitativo: ≥3 clientes ativos com avg streak ≥7d (M4 KPI).
**Risco se aprovar sem sinal:** voice queima R$ [redacted Tier 0]-50k em compute Brain B + 6h IA-pair sem retorno.
**Risco se não aprovar:** PWA texto-only suficiente, voice posterga 6-12m. Aceitável.

---

## Capacidade time (dedicado a Jana)

> Total dev capacity time = ~25h dev/sem real × 10x IA-pair = ~250h dev-equiv/sem em codáveis.
> Roadmap R25 base já consome ~150h-equiv/sem. **Jana M1-M6 = ≤30% restante = ~75h-equiv/sem dispponível.**

| Pessoa | % alocado Jana | % alocado R25 base | Notas |
|---|---|---|---|
| **Wagner** [W] | 10% (review ético + ADRs Jana) | 90% (vendas, smoke SEFAZ, ABICOMV) | Bottleneck — Jana NÃO pode virar prioridade Wagner |
| **Felipe** [F] | 25% (backend Jana arquitetura) | 65% (R25), 10% suporte | Owner técnico Jana M1-M6 |
| **Maiara** [M] | 5% (suporte Jana feedback) | 50% suporte clientes, 45% dev outras features | NÃO dev Jana primary — só feedback loop |
| **Luiz** [L] | 60% (IA-pair Jana frontend + Pest) | 40% (outras tasks IA-pair) | Owner frontend Jana + tests Pest |
| **Eliana** [E] | 15% (Meta WhatsApp onboarding + comercial Snapshot) | 50% financeiro, 35% comercial ERP | Drive Meta + drive vendas DaaS |

**Total dev Jana:** ~70h-equiv/sem (Felipe 25% × 250h = 62h + Luiz 60% × 25h × 10 = 150h-equiv... espera, math conservador):
- Felipe 25% de 25h reais = 6.25h reais × 10x = 62.5h-equiv codáveis
- Luiz 60% de 25h reais = 15h reais × 10x = 150h-equiv codáveis (mas Luiz junior, fator real ~3-5x não 10x)
- **Total realista Jana: ~80-100h-equiv/sem** — suficiente pra os ~6h IA-pair/cycle estimados acima.

**Buffer:**
- 20% pra incidentes prod ROTA LIVRE
- 10% pra cliente sinal qualificado novo (ADR 0105) — capacidade pra absorver feature pedida

---

## Riscos top 8

1. **M2 push fadiga** — cliente acha Jana chata, opt-out total → habit quebrado. **Mitigação:** opt-in granular (3 categorias), cooldown noturno, "modo silencioso final-de-semana" default.

2. **M3 cliente sente invadido** ("Jana sabe demais sobre meu negócio") → desconfiança. **Mitigação:** opt-in benchmark explícito + transparência ("seu dado anonimizado contribui pra comparativo de 47 gráficas") + LGPD review formal.

3. **M4 gamificação predatória** — streak vira chantagem, achievement vira dark pattern. **Mitigação:** ADR ética + review trimestral Wagner + zero "perdeu streak X dias" linguagem.

4. **M5 voice falha latência/custo** (descrito acima como risco MAIOR). **Mitigação:** cache + Brain A pra simples + decisão GO/NO-GO formal cycle 37 (dez/26).

5. **M5 voice português BR mal compreendido** (sotaque, gírias). **Mitigação:** glossário PT-BR-gráfico no system prompt + amostras Larissa real.

6. **M6 API parceiros não fecha** (parceria wallclock 30-60d com fintech). **Mitigação:** outreach paralelo 5 parceiros, qualquer 1 fecha = sucesso M6.

7. **R25 atrasa M2 (2º cliente)** — sem 2º cliente até ago/26, **M3 Jana benchmark NÃO pode rodar** (k-anon ≥5 impossível). **Mitigação:** Jana M3 graceful degradation ("ainda coletando") + reabsorver cycles em R25 vendas.

8. **ROTA LIVRE incidente P1** durante M1-M3 (Jana ainda não estável) — Larissa frustrada com Jana = bad PR interno. **Mitigação:** Jana feature flag por business_id (toggle off pra biz=4 se incidente) + canary cycle inteiro antes de rollout.

---

## Dependências externas

| Dependência | Owner | Wallclock | Custo | Backup |
|---|---|---|---|---|
| **WhatsApp Business API oficial Meta** | [E] | 14d onboarding (≥30d realista) | R$ [redacted Tier 0]-0.20/msg após verificação | Twilio/ZAPI temporário |
| **FCM Google Cloud** | [F] | 1d setup | Gratuito até 10M msg/mês | Web Push API direto (sem Google) |
| **Receita Federal CNAE API** | [F] | 1d setup | Gratuito (público) | Tabela CNAE estática (raramente muda) |
| **Brain B Sonnet/Opus** (voice + reasoning complexo) | [F] | imediato | R$ [redacted Tier 0]-3 por query | Brain A (gpt-4o-mini) com prompt mais elaborado |
| **OpenAI Whisper + TTS** (voice) | [F] | 1d setup | ~R$ [redacted Tier 0]/min audio | Google Speech-to-Text + ElevenLabs |
| **MCP server CT 100 disponível** | [F] | já existe | hospedagem CT 100 | local fallback dev |

---

## Não-foco (explícito out-of-scope)

- ❌ **App nativo iOS/Android** — PWA suficiente até H2 2027 (decisão M5 cycle J10). Custo Swift+Kotlin = 3-6 cycles, sem retorno provado.
- ❌ **Brain A custom-trained / fine-tune próprio** — gpt-4o-mini é o suficiente pra MVP. Fine-tune só se Brain B custo virar problema P0 em M5.
- ❌ **Multi-language EN/ES** — BR-PT-only durante todo roadmap. Internacional é H2 2027 ou 2028.
- ❌ **Marketplace de parceiros (B2B2C)** — M6 limita-se a 1-3 parcerias diretas (deal-by-deal). Marketplace pleno = roadmap 2028.
- ❌ **Auto vertical** — explicitamente STAY-FOCUSED até sinal qualificado (ADR `auto-vertical-strategy.md`). Schema multi-vertical (M1) está pronto pra auto **quando** sinal vier — mas roadmap NÃO entrega features auto-específicas.
- ❌ **App separado pra colaborador (employee-facing)** — Jana é para o DONO do negócio. Funcionário usa Ponto/Repair direto, sem Jana.
- ❌ **Bring your own LLM (BYOL)** — cliente não escolhe Brain A vs B. Sistema decide via `decide()`. Reduz complexidade UX.

---

## Lifecycle desta proposta

- **Status atual:** PROPOSTA (não aceita) — `lifecycle: ideation`
- **Tensão a reconciliar com Wagner:** este roadmap propõe ~30% capacidade Jana paralela a R25. R25 hoje é 100% (sem fatia Jana explícita). Wagner precisa decidir:
  - **Opção A (recomendada):** aceitar este roadmap como **adendo a R25** — 30% Jana / 70% ERP base. ARR realista 12m: R$ [redacted Tier 0]-400k combinado.
  - **Opção B:** substituir M3-M6 de R25 por este roadmap Jana — concentrar em produto IA. ARR mais agressivo mas viola ADR 0105 (Jana M3-M6 não tem sinal qualificado pra todas as features).
  - **Opção C:** rejeitar este roadmap inteiramente — manter R25 como única verdade, Jana fica como feature incremental sem milestone próprio. ARR R$ [redacted Tier 0]k.
- **Após aprovação (Opção A):** vira ADR canon `01XX-roadmap-jana-12m-2026-2027.md` com lifecycle `accepted`, **complementar a R25** (não supersedes).
- **Revisão:** review formal a cada 2 milestones (M2 fim ago/26, M4 fim dez/26, M6 fim jun/27). Métricas (DAU/MAU, NPS, ARR Jana) decidem se M+1 executa ou pivota.
- **Mudanças mid-flight:** se cycle Jana entrega <50% goal por 2 cycles consecutivos, abre nova ADR `supersedes:` ou Wagner manda Jana pro freezer (acelera R25 puro).

---

## Próximo passo

1. **Wagner valida** Opção A/B/C acima
2. Se Opção A: criar ADR canon (próximo número, ex 0121) com `status: accepted` + adicionar ao roadmap mestre
3. Spawn schema multi-vertical (M1 cycle J1) com Felipe — bloqueador de tudo
4. Eliana inicia onboarding Meta WhatsApp Business **em cycle J1** (não esperar J3 — wallclock 14-30d)
5. Wagner aprova personalidade Jana (Hook Model ético) ANTES de M2 enviar 1ª notificação real

---

**Última atualização:** 2026-05-09 (sessão Claude — VP product/Jana roleplay).
