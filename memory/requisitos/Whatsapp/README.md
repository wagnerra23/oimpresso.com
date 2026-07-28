---
id: requisitos-whatsapp-readme
module: Whatsapp
alias: whatsapp
status: spec-ready
migration_target: react
migration_priority: alta
risk: medio
problem: "5 módulos do oimpresso (Repair, RecurringBilling, Financeiro, ConsultaOs, Jana) precisam falar Whatsapp transacional. Hoje só temos legacy `whatsapp_text` UltimatePOS que monta link wa.me manual. Cliente esquece OS, boleto vence sem aviso, dunning depende de SMS caro. Sem API real."
persona: "Larissa-financeiro (cobrança+suporte) + técnico-Repair (status OS) + cliente-final (recebe transacional + responde) + bot-Jana (HITL handoff)"
positioning: "Whatsapp transacional dentro do ERP, no canal que o cliente lê. Z-API ativa em 5 min (driver default Sprint 1); Meta Cloud aprovado em paralelo (1-3 dias) como rede de segurança obrigatória pra ban Meta. Sprint 3: BaileysDriver custom oimpresso (daemon Node CT 100 próprio) pra resolver dor de observabilidade do Evolution. Evolution PROIBIDO permanente."
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

> **Pitch para o tenant:** _Whatsapp transacional no ERP, ligado em 5 minutos._ Liga via Z-API (R$ [redacted Tier 0]/mês, scan QR Code, freeform sem janela 24h). Em paralelo cadastra Meta Cloud (1-3 dias, free tier) como rede de segurança automática — se Z-API for bloqueado, sistema troca sozinho sem interrupção. Status de OS, boleto pago, NFe, lembrete e dunning entram no canal que o cliente abre 95% das vezes.

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

1. **Core** — Driver abstraction (`ZapiDriver` default + `MetaCloudDriver` fallback obrigatório + `NullDriver` dev), models, jobs, eventos, factory com fallback runtime
2. **Driver Health Check** — job 6h em 6h pinga driver não-oficial; fallback automático → Meta Cloud (Sprint 2)
3. **Inbox** — UI Cockpit conversas + chat real-time Centrifugo (driver-agnóstico)
4. **Templates** — templates locais Z-API/Baileys + HSM Meta Cloud (com contraparte obrigatória pra fallback funcionar)
5. **Settings** — wizard 2 passos (Z-API hoje + Meta Cloud em paralelo), gating duro FormRequest. Sprint 3: 3ª opção `BaileysDriver` (avançado, exige termo LGPD adicional).
6. **Webhook** — 2 receivers Sprint 1 (`/webhook/zapi/{uuid}`, `/webhook/meta/{uuid}`) + 1 Sprint 3 (`/webhook/baileys/{uuid}`)
7. **Bot Jana** — listener `DispatchToJanaBot` + handoff PolicyEngine `REQUIRE_HUMAN_REVIEW`
8. **Métricas** — `whatsapp_conversation_metricas` + `whatsapp.driver.*` OTel
9. **BaileysDriver custom (Sprint 3)** — daemon Node próprio CT 100 + container Docker compose-managed `whatsapp-baileys` + observabilidade rica (OTel + Prometheus + Grafana). Detalhes em `ARCHITECTURE.md §16`. Autorizado emenda 4 ADR 0096 — Wagner ciente do código extra; justifica pela dor de observabilidade Evolution.
10. **❌ Evolution API** — **PROIBIDO permanente** (bans Wagner + schema + observabilidade — emendas 3-4 ADR 0096)

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

### Sprint 1 (S2.6 → S2.7) — Plumbing + 2 drivers (Z-API default + Meta fallback) + Outbound

- Scaffold módulo (8 peças `criar-modulo`)
- `Services/Drivers/{DriverInterface, ZapiDriver, MetaCloudDriver, NullDriver, DriverFactory}` — Factory resolve com fallback runtime
- Migrations: `whatsapp_business_configs` (colunas Z-API + Meta + driver_health + lgpd_acknowledged_at), `whatsapp_messages`, `whatsapp_conversations`, `whatsapp_templates`
- CRUD `WhatsappBusinessConfig` com **wizard 2 passos** (Z-API hoje + Meta Cloud em paralelo) — gating FormRequest
- `SendWhatsappMessageJob` (retry exponencial, `$businessId` constructor, resolve Driver via Factory em runtime — fallback automático)
- Listener Repair: status `ready` dispara mensagem (cumpre ADR Repair tech/0001)
- Pest: `MultiTenantIsolationTest`, `BusinessSettingsTest` (gating fallback obrigatório), `MetaWebhookSignatureTest`, `ZapiWebhookTest`, `ZapiDriverTest`, `MetaCloudDriverTest`, `DriverFactoryTest` (cobre fallback automático)

### Sprint 2 — Inbox + Templates + Health Check + Fallback automático

- `ZapiWebhookController` + `MetaWebhookController` com auth específica
- `ProcessIncomingWebhookJob` (CT 100 Horizon, driver-agnóstico)
- `WhatsappDriverHealthCheckJob` (6h em 6h pinga Z-API)
- Fallback automático Z-API → Meta Cloud quando `driver_health` ≥ degraded
- `Pages/Whatsapp/Conversations/Index.tsx` — Cockpit pattern (lista + chat painel)
- `Pages/Whatsapp/Conversations/Show.tsx` — real-time via Centrifugo
- `Pages/Whatsapp/Templates/Index.tsx` — sync HSM Meta + templates locais Z-API (validação contraparte)
- Runbook `runbooks/migrar-emergencia.md` (Z-API → Meta Cloud manual em caso de catastrophic ban)
- Integração RecurringBilling US-RB-044 (boleto+NFe ao receber pagamento)

### Sprint 3 — BaileysDriver custom + Bot Jana + estrutura customizada de atendimento

> **Autorizado emenda 4 ADR 0096** — Wagner ciente do código extra; justifica pela dor de observabilidade do Evolution.

- **Componente Node** (novo container Docker `whatsapp-baileys` em CT 100):
  - Wrapper HTTP REST minimal sobre `@whiskeysockets/baileys` (Fastify/Hono)
  - Persistência auth state Whatsapp Web em volume mapeado `/srv/docker/whatsapp-baileys/sessions/`
  - OTel SDK Node + métricas Prometheus
  - Webhook outbound pro Hostinger PHP
  - Container compose-managed (skill `proxmox-docker-host`)
  - IP whitelist Traefik (só Hostinger fala com daemon)
- **Componente PHP** `BaileysDriver` (chama daemon via `Http::baseUrl(...)`)
- **Migration** + colunas `baileys_*` em `whatsapp_business_configs`
- `BaileysWebhookController` + middleware `VerifyBaileysSignature`
- Settings UI ganha 3ª opção "Baileys custom (avançado)" no wizard
- Bot Jana: listener `DispatchToJanaBot` + handoff PolicyEngine ADS
- 3 runbooks Sprint 3: `baileys-daemon-deploy-ct100.md`, `baileys-troubleshoot-ban.md`, `baileys-upgrade-lib.md`
- Dashboard Grafana dedicado `whatsapp-baileys-daemon`
- **Plano detalhado:** [ARCHITECTURE.md §16](ARCHITECTURE.md#16-sprint-3--baileysdriver-custom-estrutura-customizada-de-atendimento)

### Sprint 3 — Bot Jana + HITL + Métricas

- Listener `DispatchToJanaBot` — encaminha pra Copiloto via `decide(domain, intent, payload)`
- Handoff humano (atribuir conversa a usuário; UI badge "atendendo")
- `whatsapp_conversation_metricas` — custo, tempo resposta, deflection
- Dashboard métricas Whatsapp (Inertia tab em `/copiloto/admin/memoria` ou módulo próprio)
- Pest: `BotHandoffTest`, `MetricasAggregationTest`
