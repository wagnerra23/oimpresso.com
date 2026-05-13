---
module: Whatsapp
purpose: "Whatsapp transacional unificado (Z-API default + Meta Cloud fallback obrigatório; BaileysDriver custom autorizado Sprint 3; Evolution PROIBIDO permanente) — status OS Repair, boleto/NFe RecurringBilling, lembrete Financeiro, ConsultaOs acompanhamento, bot Jana HITL"
contains:
  # Install (ADR 0024)
  - "InstallController — extends BaseModuleInstallController (rotas install/uninstall/update)"
  - "DataController — 3 hooks UltimatePOS (user_permissions/modifyAdminMenu/superadmin_package)"
  # Admin Inertia (US-WA-001/012/013/057)
  - "Admin/SettingsController — wizard 2 passos Z-API+Meta + gating LGPD (BusinessSettingsRequest)"
  - "Admin/ConversationsController — Inbox Cockpit + send manual + Centrifugo subscribe"
  - "Admin/CsatController — dashboard pesquisa pós-atendimento (CSAT) PR-6 CYCLE-07"
  - "Admin/MacrosController — CRUD respostas prontas (macros) inbox"
  - "Admin/MacroVariantsController — variants A/B/n por macro pra teste"
  - "Admin/MetricsController — KPIs operacionais inbox (volume, TMR, SLA)"
  - "Admin/TemplatesController — sync HSM Meta + criar template LOCAL Z-API/Baileys"
  - "Admin/ChannelsController — CRUD Channel polimórfico /atendimento/canais (ADR 0135 Fase 0, coexiste Settings legacy)"
  - "Admin/InboxController — UI omnichannel /atendimento/inbox lê schema novo (US-WA-067/069 ADR 0135)"
  # API webhooks (US-WA-010/010b/002d)
  - "Api/MetaWebhookController — recebe events Meta Cloud (HMAC SHA-256 verify)"
  - "Api/ZapiWebhookController — recebe events Z-API (Client-Token timing-safe)"
  - "Api/BaileysWebhookController — recebe events daemon Node CT 100 schema legacy (Bearer timing-safe)"
  - "Api/ChannelBaileysWebhookController — recebe events daemon Node CT 100 schema novo via channel_uuid (ADR 0135)"
  # Services / Drivers
  - "Services/Drivers/{ZapiDriver, MetaCloudDriver, BaileysDriver, NullDriver, DriverFactory} — abstração + fallback runtime"
  - "Services/Centrifugo/{CentrifugoPublisher, CentrifugoTokenIssuer} — real-time UI ADR 0058"
  # Jobs / Listeners / Observers
  - "Jobs/{SendWhatsappMessageJob, ProcessIncomingWebhookJob, WhatsappDriverHealthCheckJob}"
  - "Listeners/{NotifyRepairCustomer, PublishMessage{Received,Sent}ToCentrifugo, DispatchToJanaBot}"
  - "Observers/WhatsappMessageObserver — append-only enforcement Tier 0"
  # Console
  - "Console/Commands/DriverHealthCheckAllCommand — schedule cron 6h"
---

# SCOPE — Modules/Whatsapp/

Resumo executivo do escopo deste módulo. Documento curto pra dev novo entender em 2 minutos.

## Decisão arquitetural mãe

[ADR 0096](../../memory/decisions/0096-modulo-whatsapp-meta-cloud-api-direto.md) — **Z-API default + Meta Cloud fallback obrigatório Sprint 1; BaileysDriver custom Sprint 3 (estrutura customizada de atendimento); Evolution PROIBIDO permanente**.

## O que este módulo faz

Whatsapp transacional unificado pro setor comunicação visual (gráficas/printers): status OS Repair, boleto+NFe RecurringBilling, lembrete Financeiro, acompanhamento ConsultaOs, bot conversacional Jana com HITL.

## Drivers

### Sprint 1 (este Lote 2a + Lote 2b/2c próximos)
- **`ZapiDriver`** (default) — Z-API SaaS BR via Baileys. Onboarding 5 min. Risco ban Meta MUITO ALTO mitigado por fallback obrigatório + termo LGPD + health check.
- **`MetaCloudDriver`** (fallback obrigatório) — Oficial Meta. Onboarding 1-3 dias. Free 1k conv/mês.
- **`NullDriver`** (dev/CI) — implementado neste Lote 2a.

### Sprint 3 (autorizado emenda 4 ADR 0096 — anotado pra construir depois)
- **`BaileysDriver` custom** — daemon Node próprio CT 100 rodando lib `@whiskeysockets/baileys`. Estrutura customizada de atendimento (schema, logs OTel, métricas Prometheus, dashboard Grafana próprios). Justificativa: Wagner viu Evolution banir números, schema dele não atender, falta de observabilidade. Plano detalhado em [ARCHITECTURE.md §16](../../memory/requisitos/Whatsapp/ARCHITECTURE.md#16-sprint-3--baileysdriver-custom-estrutura-customizada-de-atendimento).

### PROIBIDOS permanentes
- **`EvolutionDriver`** — ❌ Wagner: bans em produção real + schema não atende + falta observabilidade.
- **`whatsapp-web.js`** — ❌ Sobreposição funcional com BaileysDriver custom.

## Lotes do Sprint 1

| Lote | Conteúdo | Status |
|---|---|---|
| **2a** | Scaffold (este PR) — module.json + Providers + DataController + InstallController + Routes/web.php (3 rotas Install + 3 admin placeholder) + topnav + lang + Driver interface + NullDriver | ✅ scaffold |
| 2b | Migrations 4 tabelas (whatsapp_business_configs/conversations/messages/templates) + Eloquent Models com global scope business_id + ZapiDriver + MetaCloudDriver + DriverFactory + Pest com Http::fake() | pendente |
| 2c | SendWhatsappMessageJob + Listener Repair NotifyRepairCustomer + FormRequest wizard 2 passos + Settings/Templates/Conversations Inertia pages + Webhook controllers (Zapi + Meta) | pendente |

Sprint 2 (após Sprint 1 validado em produção): Inbox UI Cockpit + Health Check + Fallback automático + Bot Jana HITL.

Sprint 3 (estrutura customizada de atendimento): BaileysDriver custom + daemon Node CT 100 + container Docker + observabilidade rica. Anotado, não comece sem Sprint 1+2 validados.

## Não-escopo

- Marketing em massa (UX ruim Whatsapp + risco ban) → outras tools (RD Station, Active Campaign).
- Whatsapp pessoal (não-Business) — só Whatsapp Business Cloud API.
- Voice (chamadas Whatsapp) — beta Meta.
- Evolution API — PROIBIDO permanente (ADR 0096 emenda 4).
- whatsapp-web.js / wrappers Whatsapp Web de terceiros — PROIBIDOS.

## Padrões obrigatórios

- **Multi-tenant Tier 0** ([ADR 0093](../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)) — global scope `business_id` em todas Models; jobs com `$businessId` no constructor; webhook URL com `business_uuid` no path.
- **Hostinger ≠ CT 100** ([ADR 0062](../../memory/decisions/0062-separacao-runtime-hostinger-ct100.md)) — UI + webhook receiver no Hostinger; Job consumer no CT 100 Horizon. Sprint 3: container `whatsapp-baileys` no CT 100 (não no Hostinger).
- **Fallback gating duro** — FormRequest rejeita 422 se `driver` ∈ {`zapi`, `baileys`} sem `meta_*` cadastrado.
- **Termo LGPD obrigatório** quando `driver` ∈ {`zapi`, `baileys`} (registrado em `lgpd_acknowledged_at`).
- **PII redacted** em logs via `App\Support\PiiRedactor` (skill `commit-discipline`).

## Documentos

- [SPEC.md](../../memory/requisitos/Whatsapp/SPEC.md) — US + regras Gherkin (US-WA-002d BaileysDriver Sprint 3)
- [ARCHITECTURE.md](../../memory/requisitos/Whatsapp/ARCHITECTURE.md) — schema DB + fluxos + jobs + middlewares + §16 plano detalhado BaileysDriver custom
- [CAPTERRA-FICHA.md](../../memory/requisitos/Whatsapp/CAPTERRA-FICHA.md) — concorrentes + pricing + por que Z-API default + por que BaileysDriver custom Sprint 3
- [README.md](../../memory/requisitos/Whatsapp/README.md) — pitch + revenue + roadmap 3 sprints
