---
module: Whatsapp
alias: whatsapp
status: spec-ready
migration_target: react
migration_priority: alta
risk: medio
problem: "5 módulos do oimpresso (Repair, RecurringBilling, Financeiro, ConsultaOs, Jana) precisam falar Whatsapp transacional. Hoje só temos legacy `whatsapp_text` UltimatePOS que monta link wa.me manual. Cliente esquece OS, boleto vence sem aviso, dunning depende de SMS caro. Sem API real."
persona: "Larissa-financeiro (cobrança+suporte) + técnico-Repair (status OS) + cliente-final (recebe transacional + responde) + bot-Jana (HITL handoff)"
positioning: "Whatsapp transacional dentro do ERP, no canal que o cliente lê. Status de OS, boleto, NFe, lembrete de pagamento, dunning multicanal — tudo via Meta Cloud API oficial, custo ~30× menor que BSP brasileiro."
estimated_effort: "6-8 semanas dev sênior (3 sprints)"
revenue_tier: 2
revenue_pricing:
  free: "Sem Whatsapp — só link wa.me legacy"
  starter: "Incluído — até 1.000 conversas/mês (cobre free tier Meta), 1 número, templates manuais"
  pro: "R$ [redacted Tier 0]/mês — até 5.000 conversas/mês, multi-número, bot Jana com handoff humano, métricas custo/deflection"
  enterprise: "R$ [redacted Tier 0]/mês — ilimitado, multi-driver (Take Blip/Twilio), SLA, white-label templates"
revenue_take_rate: "0% — Whatsapp não é cobrança; valor está no engajamento que destrava take rate de RecurringBilling/Financeiro"
references:
  - https://developers.facebook.com/docs/whatsapp/cloud-api
  - https://developers.facebook.com/docs/whatsapp/business-platform/pricing
  - memory/decisions/0096-modulo-whatsapp-meta-cloud-api-direto.md
  - memory/requisitos/Repair/adr/tech/0001-auto-sms-em-mudanca-de-status-critico.md
  - memory/requisitos/RecurringBilling/SPEC.md (US-RB-044)
related_modules:
  - Repair
  - RecurringBilling
  - Financeiro
  - ConsultaOs
  - Jana
  - Crm
last_generated: 2026-05-07
last_updated: 2026-05-07
---

# Whatsapp

> **Pitch para o tenant:** _Whatsapp transacional no ERP, custo de selo._ Status de OS, boleto pago, NFe, lembrete e dunning entram no canal que o cliente abre 95% das vezes — via Meta Cloud API oficial, sem markup BSP, sem risco ban.

## Propósito

Tornar oimpresso plataforma de **Whatsapp transacional** unificada pro setor de comunicação visual, com:

- **Outbound transacional** — Repair (status OS), RecurringBilling (boleto/NFe), Financeiro (lembrete), ConsultaOs (acompanhamento)
- **Inbound + Inbox** — cliente responde, atendente vê em UI Cockpit (lista esquerda + chat painel direito)
- **Bot Jana com HITL** — Copiloto/Jana responde dentro do PolicyEngine; `REQUIRE_HUMAN_REVIEW` vira ticket humano
- **HSM Templates** — gerenciador de templates aprovados Meta (status pending/approved/rejected, preview)
- **Multi-tenant** — 1 número Meta por business; `access_token` cifrado; webhook autenticado por `business_uuid` no path
- **Real-time UI** — Centrifugo channel `whatsapp:business:{id}` (sem polling)
- **Métricas** — custo/conversa, tempo resposta, deflection bot, NPS pós-conversa

Padrão de mercado BR: Take Blip (enterprise, R$ [redacted Tier 0]+/mês) e Zenvia (mid-market, R$ [redacted Tier 0]+/mês). Inspiração de UX sem copiar custo — Meta Cloud API direto cobre 99% dos casos a fração do preço.

## Posicionamento de mercado

Whatsapp não tem take rate direto. **Valor é destravar take rate dos outros módulos:**

- **RecurringBilling** dunning multicanal (Whatsapp recupera 30% da inadimplência onde email recupera 8%)
- **Financeiro** lembrete de pagamento (ROI direto na inadimplência geral)
- **Repair** reduz ligações inbound em ~60% (técnico volta a produzir)
- **Jana** bot conversacional vira diferencial competitivo vs Iugu/Asaas/Vindi (que só fazem cobrança, não atendimento)

| Plano | Preço/mês | Conversas/mês | Recursos |
|---|---|---|---|
| **Starter** | incluído | até 1.000 (free Meta) | 1 número, templates manuais |
| **Pro** | R$ [redacted Tier 0] | até 5.000 | multi-número, bot Jana com HITL, métricas |
| **Enterprise** | R$ [redacted Tier 0] | ilimitado | multi-driver, SLA, white-label |

## Fora de escopo

- **Marketing em massa** — Whatsapp não é canal de marketing pro oimpresso (margem de ban Meta + UX ruim). Quem precisa: Capterra Marketing tools (RD Station, Active Campaign).
- **Whatsapp pessoal (não-Business)** — só Whatsapp Business via Cloud API.
- **Provedores não-oficiais** (Evolution API, Z-API, Baileys) — PROIBIDOS Tier 0 ([ADR 0096](../../decisions/0096-modulo-whatsapp-meta-cloud-api-direto.md)).
- **Voice / chamadas Whatsapp** — Meta Cloud API ainda não suporta produção (beta).

## Sub-módulos

1. **Core** — Driver abstraction (`MetaCloudDriver` default + `NullDriver` dev), models, jobs, eventos
2. **Inbox** — UI Cockpit conversas + chat real-time Centrifugo
3. **Templates** — gerenciador HSM (sync com Meta Business Manager)
4. **Settings** — config número/token por business (Meta Business Manager onboarding guide)
5. **Webhook** — receiver assinado HMAC (Hostinger HTTP-only)
6. **Bot Jana** — listener `DispatchToJanaBot` + handoff PolicyEngine `REQUIRE_HUMAN_REVIEW`
7. **Métricas** — `whatsapp_conversation_metricas` (custo, tempo resposta, deflection)

## Dependências

- **Stack canônica IA** ([ADR 0035](../../decisions/0035-stack-ai-canonica-wagner-2026-04-26.md)) — bot Jana usa `LaravelAiSdkDriver`
- **Centrifugo CT 100** ([ADR 0058](../../decisions/0058-reverb-substituido-por-centrifugo-frankenphp.md)) — real-time inbox
- **Multi-tenant Tier 0** ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)) — global scope `business_id` obrigatório
- **PolicyEngine ADS** ([ADR ADS-0011]) — handoff bot↔humano com 4 outcomes

## Documentos

- [SPEC.md](SPEC.md) — user stories US-WA-001…NNN + regras Gherkin R-WA-NNN
- [ARCHITECTURE.md](ARCHITECTURE.md) — diagramas + tabelas + jobs + eventos
- [CAPTERRA-FICHA.md](CAPTERRA-FICHA.md) — concorrentes BSP + capacidades baseline
- [ADR 0096](../../decisions/0096-modulo-whatsapp-meta-cloud-api-direto.md) — decisão Meta Cloud API direto

## Roadmap (3 sprints)

### Sprint 1 (S2.6 → S2.7) — Plumbing + Outbound

- Scaffold módulo (8 peças `criar-modulo`)
- `Services/Drivers/{DriverInterface, MetaCloudDriver, NullDriver}`
- Migrations: `whatsapp_business_configs`, `whatsapp_messages`, `whatsapp_conversations`, `whatsapp_templates`
- CRUD `WhatsappBusinessConfig` (Settings page Inertia/React)
- `SendWhatsappMessageJob` (retry exponencial, `$businessId` constructor — multi-tenant)
- Listener Repair: status `ready` dispara mensagem (cumpre ADR Repair tech/0001)
- Pest: `MultiTenantIsolationTest`, `WebhookSignatureTest`, `MetaCloudDriverTest` (com `Http::fake()`)

### Sprint 2 — Inbox + Templates

- `WebhookController` + assinatura HMAC SHA-256
- `ProcessIncomingWebhookJob` (CT 100 Horizon)
- `Pages/Whatsapp/Conversations/Index.tsx` — Cockpit pattern (lista + chat painel)
- `Pages/Whatsapp/Conversations/Show.tsx` — real-time via Centrifugo
- `Pages/Whatsapp/Templates/Index.tsx` — sync HSM Meta Business Manager
- Integração RecurringBilling US-RB-044 (boleto+NFe ao receber pagamento)

### Sprint 3 — Bot Jana + HITL + Métricas

- Listener `DispatchToJanaBot` — encaminha pra Copiloto via `decide(domain, intent, payload)`
- Handoff humano (atribuir conversa a usuário; UI badge "atendendo")
- `whatsapp_conversation_metricas` — custo, tempo resposta, deflection
- Dashboard métricas Whatsapp (Inertia tab em `/copiloto/admin/memoria` ou módulo próprio)
- Pest: `BotHandoffTest`, `MetricasAggregationTest`
