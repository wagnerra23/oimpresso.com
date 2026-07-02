# JANA Pro — Product Plan executivo (32 US, 4 sprints, 90 dias)

> **Status:** Aprovado por Wagner 2026-05-11
> **ADR mãe:** [0140](../../decisions/0140-jana-pro-produto-comercial-saas.md)
> **Pricing:** R$ [redacted Tier 0] (Free) · R$ [redacted Tier 0] (Pro) · R$ [redacted Tier 0] (Enterprise)
> **Meta 12m:** 50 Pro + 5 Enterprise = R$ [redacted Tier 0]k ARR
> **Margem operacional alvo:** ≥ 90%

Este documento é o **roadmap detalhado** do JANA Pro. Cada US listada
aqui vira `US-COPI-NNN` no MCP quando Wagner aprovar a fase (batch
`tasks-create` ou edit direto `memory/requisitos/Jana/SPEC.md` que o
webhook sincroniza pro DB).

---

## Visão geral 4 sprints

| Sprint | Semanas | Goal | Customer alvo | Gate de sucesso |
|---|---|---|---|---|
| **JANA-A** MVP Operacional | 1-2 | Brief diário Wagner pessoal | ROTA LIVRE dogfood | 7d consecutivos sem falha |
| **JANA-B** Beta Pago | 3-4 | 5 clientes pagando R$ [redacted Tier 0] | 5 Officeimpresso + ROTA LIVRE | 3/5 convertem trial→pago |
| **JANA-C** GA + Enterprise | 5-8 | Tier Enterprise + case studies | Top 10% LTV | NPS ≥ 8 |
| **JANA-D** Scale + Marketing | 9-12 | 50 Pro + 5 Enterprise | Marketing tracionado | R$ [redacted Tier 0]k MRR |

---

## Sprint JANA-A — MVP Operacional (semanas 1-2)

### US-COPI-201 — BriefDiarioAgent Vizra + 5 tools internas

**Owner:** wagner · **Priority:** p1 · **Estimate:** 8h

**Visão:** Agent Vizra ADK que lê 5 fontes do business e gera brief
estruturado.

**AC:**
- `Modules/Jana/Agents/BriefDiarioAgent.php` namespace `agents.brief_diario`
- 5 PHP tools em `Modules/Jana/Services/JanaPro/Tools/`:
  - `VendasPeriodoTool` — transactions sell GROUP BY day vs período anterior + ticket médio
  - `InadimplenciaBucketsTool` — buckets 0-30 / 30-60 / 60-90 / >90d com valores
  - `TicketsPriorizadosTool` — reusa skill ticket-triage, top 5 P1/P2
  - `NfeStatusTool` — emissões 30d + rejeições por cstat
  - `OportunidadesUpsellTool` — clientes >3x produto X (sugere combo) + inativos >60d (reativação)
- Output JSON estruturado + markdown formatado pra envio canais
- Multi-tenant Tier 0: agent recebe `$businessId` constructor
- Pest R-JANA-001 a 005 (1 por tool com cross-tenant safety)

**Refs:** ADR 0035, ADR 0093, Skill `ticket-triage` v0.1.0

---

### US-COPI-202 — BriefDiarioJob schedule Horizon CT 100

**Owner:** wagner · **Priority:** p1 · **Estimate:** 2h

**AC:**
- `Modules/Jana/Jobs/BriefDiarioJob.php` queue `jana-pro` Horizon CT 100
- Schedule `app/Console/Kernel.php` cron 8h BRT segunda-sábado (domingo skip por default)
- Itera business com flag `jana_pro_active=true` (US-COPI-204)
- Timeout 60s + retry 3x backoff exponential
- Falha → log + alerta admin via Sentry/Bugsnag

---

### US-COPI-203 — Entrega WhatsApp via chip pessoal Wagner (Suorte)

**Owner:** wagner · **Priority:** p1 · **Estimate:** 1h

**Visão:** Brief enviado 8h BRT pro chip Wagner pessoal (+5548888782087) via
daemon Baileys CT 100 já conectado.

**AC:**
- `Modules/Jana/Services/JanaPro/Delivery/WhatsappBriefDelivery.php`
- Format markdown → texto Whatsapp formatado (lista bullet + bold via *...*)
- Reusa `ChannelDriverFactory` + `BaileysDriver::send()`
- Idempotência: 1 envio por business+date (UNIQUE constraint `mcp_briefs.delivery_key`)
- Pest R-JANA-006 anti-duplicação

---

### US-COPI-204 — Persistência mcp_briefs + namespace memória analises

**Owner:** wagner · **Priority:** p2 · **Estimate:** 2h

**AC:**
- Migration `add_jana_pro_columns_to_mcp_briefs` (já existe tabela base):
  - `brief_type ENUM('whatsapp_business','jana_pro','jana_enterprise')`
  - `delivery_key VARCHAR(120) UNIQUE` (business_id + date + type)
  - `delivered_channels JSON` (whatsapp / email / inbox)
- Snapshot imutável (Append-only)
- Cada brief escreve em namespace Vizra `analises.brief_diario` do
  business pra agent aprender padrões mês-a-mês

**Refs:** ADR 0091 Daily Brief

---

### US-COPI-205 — Dashboard /copiloto/admin/jana-pro

**Owner:** wagner · **Priority:** p2 · **Estimate:** 3h

**AC:**
- Inertia page `resources/js/Pages/Copiloto/JanaPro/Admin.tsx`
- Lista briefs últimos 30d com filtros business + date + brief_type
- Click → detalhe (markdown render + JSON snapshot expandível)
- Botão "Reenviar agora" (admin only) — dispara `BriefDiarioJob::dispatchNow($businessId, $date)`
- Permission `jana_pro.admin` (Spatie role superadmin only)

---

## Sprint JANA-B — Beta Pago 5 clientes (semanas 3-4)

### US-COPI-211 — Pricing page /jana-pro Inertia

**Owner:** wagner · **Priority:** p1 · **Estimate:** 4h

**AC:**
- Inertia page `resources/js/Pages/JanaPro/Pricing.tsx`
- 3 tier cards (Free/Pro/Enterprise) com benefits list
- Pricing toggle anual (-15%) / mensal
- CTA "Começar Trial 30d grátis" → Asaas checkout
- Comparison table vs Intercom/Zendesk (BR vantagens)
- A11y WCAG 2.1 AA (skill `design:accessibility-review`)

---

### US-COPI-212 — Asaas subscription integration

**Owner:** wagner · **Priority:** p1 · **Estimate:** 4h

**Visão:** Reusa `Modules/RecurringBilling` ADR 0008 arq — Asaas vira
banco virtual + cobrança recorrente.

**AC:**
- `JanaProSubscription` model + migration `jana_pro_subscriptions`
  (business_id, plan, status, asaas_subscription_id, trial_ends_at, ...)
- Trial 30d grátis sem cartão → vira pago dia 31 (Asaas auto-cobra)
- Webhook Asaas → atualiza status (active/suspended/cancelled)
- Tier downgrade/upgrade auto-prorate
- Permission por tier: `jana_free.*` `jana_pro.*` `jana_enterprise.*`

---

### US-COPI-213 — Onboarding wizard /jana-pro/setup

**Owner:** wagner · **Priority:** p2 · **Estimate:** 3h

**AC:**
- 3 steps wizard:
  1. Escolher horário brief (default 8h BRT)
  2. Canais entrega (WhatsApp / Email / Ambos) + número do chip se WhatsApp
  3. Filtros opcionais: notificar SE inadimplência > X / SE NF-e rejeitada / SE ticket P1
- Salva em `jana_pro_subscriptions.preferences_json`
- Send brief teste imediato pra validar config funciona

---

### US-COPI-214 — Email brief HTML responsivo

**Owner:** wagner · **Priority:** p2 · **Estimate:** 3h

**AC:**
- `Mail/JanaProBriefDiario.php` view Blade responsiva mobile-first
- Subject: "📊 Seu Brief Operacional — {date BR}"
- Logo + cores oimpresso (R-DS-001 design tokens)
- Postmark integration (free tier 100 msgs/dia suficiente pra Beta)
- Tracking pixel open-rate + click-tracking nos botões "Ver detalhes"
- Unsubscribe footer (LGPD obrigatório)

---

### US-COPI-215 — Métricas brief (open-rate, ações, NPS)

**Owner:** wagner · **Priority:** p2 · **Estimate:** 2h

**AC:**
- Tabela `jana_pro_brief_metrics` (brief_id, opened_at, actions_taken, nps_score)
- Brief inclui botão "Foi útil? 👍 👎" → registra NPS rápido
- Dashboard `/copiloto/admin/jana-pro/metricas`:
  - Open-rate semanal/mensal
  - NPS médio
  - Ações geradas (% briefs com ≥1 ação)
- KPI Wagner: NPS médio > 8 = green, 6-8 = yellow, <6 = vermelho (revisar prompt)

---

## Sprint JANA-C — GA + Enterprise (semanas 5-8)

### US-COPI-221 — JanaProEnterpriseAgent event-driven autonomous

**Owner:** wagner · **Priority:** p1 · **Estimate:** 8h

**Visão:** Agent que **roda continuamente** (não só cron diário).
Escuta eventos críticos via Laravel events + decide se vale alertar/agir.

**AC:**
- `JanaProEnterpriseAgent` listener em eventos:
  - `TransactionPaid` → atualiza LTV cliente, propor upsell se padrão
  - `MessageReceived` → reanalisa sentimento, escalar se "Socorro"/"cancelar"
  - `NfeRejeitada` → notificar Wagner + sugestão de retry
  - `ConversationOpened` → triage automática + tag P1/P2
  - `BusinessActivityDrop` → alertar churn risk
- Output: WhatsApp INSTANTÂNEO pro Wagner (não espera 8h brief)
- Rate limit: max 5 alertas/hora/business (anti-spam)

---

### US-COPI-222 — Event listeners Transaction/Message/NfeEmissao

**Owner:** wagner · **Priority:** p1 · **Estimate:** 4h

**AC:**
- `App\Events\TransactionPaid` (já pode existir — verificar)
- `App\Events\NfeRejeitada` (novo — disparado pelo Modules/NfeBrasil)
- Listeners registrados em `Modules/Jana/Providers/JanaProServiceProvider.php`
- Multi-tenant Tier 0: listeners propagam `$businessId` async

---

### US-COPI-223 — HITL via Modules/ADS Dual Brain

**Owner:** wagner · **Priority:** p1 · **Estimate:** 6h

**Visão:** Ações destrutivas (bloquear cliente, cancelar assinatura,
enviar mensagem cobrança) sempre pedem confirmação humana via
[ADS Dual Brain pattern](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md).

**AC:**
- Agent classifica ação proposta em `Risk::LOW/MEDIUM/HIGH`
- HIGH (bloqueio, cobrança, deletar) → cria registro em
  `mcp_dual_brain_decisions` com `destination='pending_wagner'`
- Wagner aprova/rejeita via WhatsApp inline (botão "Aprovar/Rejeitar")
- Aprovado → action é executada · Rejeitado → memória aprende padrão
- Pest R-JANA-007 a 010 cobrindo 4 outcomes ADS PolicyEngine

**Refs:** ADR 0094 §Constituição, Modules/ADS docs

---

### US-COPI-224 — Slack/Teams integration adicional

**Owner:** wagner · **Priority:** p2 · **Estimate:** 4h

**Visão:** Brief também enviado pra canais Slack/Teams pra mercado
corporate (maioria PME BR não usa, mas Enterprise sim).

**AC:**
- Slack incoming webhook + Teams connector
- Setup wizard adicional no `/jana-pro/setup`
- Format adaptado pra cada plataforma (Slack mrkdwn, Teams adaptive cards)

---

### US-COPI-225 — Case study público com depoimento Larissa

**Owner:** wagner · **Priority:** p2 · **Estimate:** 4h

**AC:**
- Landing case-study Inertia page com depoimento Larissa
- Métricas reais ROTA LIVRE: "JANA Pro identificou X clientes inativos,
  recuperamos R$ Y, reduzimos churn de X% pra Y%"
- Vídeo curto (Wagner grava simples) com Larissa falando
- Compartilhar LinkedIn + grupo MEI WhatsApp + lista oimpresso

---

### US-COPI-226 — Pricing 3 tiers GA + lifecycle Asaas

**Owner:** wagner · **Priority:** p1 · **Estimate:** 3h

**AC:**
- Tier upgrade (Pro → Enterprise) com prorate
- Tier downgrade (Enterprise → Pro) só no próximo ciclo
- Trial→pago automático (dia 31 Asaas cobra)
- Suspended (Asaas falha cobrança) → agent para mas dados ficam
- Cancelled → 90d retenção dados + delete LGPD-compliant

---

## Sprint JANA-D — Scale + Marketing (semanas 9-12)

### US-COPI-231 — Landing page jana.oimpresso.com

**Owner:** wagner · **Priority:** p1 · **Estimate:** 6h

**AC:**
- Subdomínio `jana.oimpresso.com` Traefik route → mesma Laravel app
- Landing page `resources/js/Pages/JanaLanding/Index.tsx` (público)
- Hero: "JANA — Co-piloto IA pra sua empresa, em português."
- Demo vídeo 90s
- Pricing reuso `/jana-pro` page
- Footer: links docs, blog, compliance LGPD

---

### US-COPI-232 — Demo interativo sandbox biz=999

**Owner:** wagner · **Priority:** p2 · **Estimate:** 8h

**AC:**
- Business 999 (sandbox) com dados fake de 6 meses (transactions,
  messages, NFe) gerados por seeder
- Visitor pode logar via `/jana-pro/demo` (token público, read-only)
- Vê brief diário real + Inbox cockpit + dashboard
- CTA "Quero pra minha empresa" → checkout Asaas

---

### US-COPI-233 — API pública POST /api/v1/jana/triage

**Owner:** wagner · **Priority:** p2 · **Estimate:** 4h

**Visão:** Integradores externos (n8n, Zapier, sistemas terceiros)
podem chamar `JANA triage` via API REST.

**AC:**
- Route `POST /api/v1/jana/triage` JWT auth
- Body: `{ticket_text, customer_phone, customer_email, business_token}`
- Response: JSON contrato fixo da skill `ticket-triage`
- Rate limit por API key (100 req/dia Free, 10k Pro, ilim Enterprise)
- Docs OpenAPI 3.0 em `/api/docs/jana`

---

### US-COPI-234 — LGPD compliance docs + termo digital

**Owner:** wagner+eliana · **Priority:** p0 · **Estimate:** 6h

**AC:**
- Política de privacidade `/privacy/jana-pro` em PT-BR + inglês
- Termo de aceite digital no onboarding (`jana_pro_subscriptions.lgpd_accepted_at`)
- DPO contato (Wagner por enquanto, Eliana se ela decidir formalizar)
- Procedimento delete-on-request (LGPD Art 18)
- Data inventory (quais dados JANA acessa por business)

**Refs:** Eliana estuda LGPD ([regras-time](../../regras-time.md))

---

### US-COPI-235 — Programa afiliados contadores BR 15% recorrente

**Owner:** wagner · **Priority:** p3 · **Estimate:** 6h

**Visão:** Contadores BR (CRC) são canal natural — eles atendem 5-50
MEIs cada. Programa: contador indica → 15% recorrente da assinatura
enquanto cliente ativo.

**AC:**
- Modelo `JanaProAffiliate` + tracking link `?ref=CRC123`
- Dashboard contador `/jana-pro/parceiro` com:
  - Indicações ativas/canceladas
  - Comissão pendente
  - Saque mensal via Asaas
- Marketing: lista CRC contadores SC/SP/RJ + email outreach
- Termo de parceria PDF

---

### US-COPI-236 — Context cache namespace pra reduzir custo LLM

**Owner:** wagner · **Priority:** p2 · **Estimate:** 4h

**Visão:** Brief diário reusa contexto do dia anterior (transactions
não-modificadas, conversations resolvidas). Cache namespace
`analises.cache.{business_id}.{date_key}` reduz tokens 60-80%.

**AC:**
- `JanaProCacheService` invalida quando business sofre write em tabelas
  rastreadas (transactions, conversations, nfe_emissoes)
- TTL 6h
- Métrica: % cache hit vs miss (target ≥ 60%)
- Custo LLM reduz de R$ [redacted Tier 0] → R$ [redacted Tier 0] por brief (margem sobe pra 97%)

**Refs:** Sprint 9 retrieval flow Modules/Jana (ADR 0036)

---

## Total estimativa

| Sprint | US count | Estimativa total |
|---|---|---|
| JANA-A MVP | 5 | 16h |
| JANA-B Beta | 5 | 16h |
| JANA-C GA | 6 | 29h |
| JANA-D Scale | 6 | 34h |
| **Total 32 US** | | **~95h IA-pair** |

Em IA-pair velocity 10x ([ADR 0106](../../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md)) =
**9.5h reais Wagner solo dev**. Com margem 2x = **~20h trabalho**.

Realista em 90 dias se Wagner dedicar 1.5h/dia ao JANA. Sem prejudicar
Inter PJ (US-RB-048) e outros sprints concorrentes.

---

## Aprovação por fase

Cada sprint começa SÓ APÓS gate da anterior passar. Estrutura:

1. **JANA-A passa gate** (brief Wagner pessoal 7d ok) → libera JANA-B
2. **JANA-B passa gate** (3 conversões trial→pago de 5) → libera JANA-C
3. **JANA-C passa gate** (NPS médio ≥ 8) → libera JANA-D
4. **JANA-D passa gate** (R$ [redacted Tier 0]k MRR) → **GA público + scale agressivo**

Se algum gate falhar → **revisão value-prop antes de continuar**
queimando capacidade Wagner.

---

## Refs

- **ADR mãe:** [0140 JANA Pro produto comercial](../../decisions/0140-jana-pro-produto-comercial-saas.md)
- **Stack IA:** ADR 0035 / 0048 / 0053
- **Multi-tenant:** ADR 0093 (Tier 0 IRREVOGÁVEL)
- **WhatsApp:** ADR 0096 + 0135 (omnichannel)
- **Asaas RB:** ADR arq/0008 Asaas como banco virtual
- **Cliente sinal:** ADR 0105 (não construir sem sinal qualificado)
- **Skill:** `ticket-triage` v0.1.0 (`.claude/skills/ticket-triage/SKILL.md`)
