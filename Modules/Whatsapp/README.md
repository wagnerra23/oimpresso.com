# Modules/Whatsapp

> Omnichannel WhatsApp transacional + atendimento (Caixa Unificada V4) com 3 drivers (Z-API default, Meta Cloud fallback, Baileys 7.x).
> **Status:** Em produção biz=1 (Wagner) + ROTA LIVRE — `biz_1_wagner_active`.
> **Tier 0:** Multi-tenant `business_id` global scope + canal/fila isolation ([ADR 0093](../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)).

## Por que existe

Comunicação cliente (Status OS, boleto/NFe, lembrete, dunning, bot Jana HITL) onde WhatsApp é o **único canal real BR**. Drivers múltiplos pra fallback obrigatório ([ADR 0096](../../memory/decisions/0096-modulo-whatsapp-meta-cloud-api-direto.md) emenda 4).

## Cliente piloto

- **Wagner biz=1** ativo (uso diário Caixa Unificada V4 + slash commands `/lembrete`, `/lembrar`, `/corrigir`, `/config`)
- **ROTA LIVRE biz=4** (Larissa) — potencial dunning + repair customer notifications

## Journey real biz=1 (Wagner dev)

| Passo | Onde | Resultado esperado |
|-------|------|-------------------|
| 1. Webhook entrante Baileys → `/api/whatsapp/baileys/webhook` | `BaileysWebhookController` | Assinatura HMAC validada, idempotência via `wam_id` cached |
| 2. `ProcessIncomingWebhookJob` despacha em fila `whatsapp-{canal_id}` | `Jobs/ProcessIncomingWebhookJob` | Canal/fila isolation Tier 0 — never cross-canal |
| 3. Conversation upsertada + LID/phone resolution | `Services/Contacts/LidPhoneResolver` | Cross-contact P0 incident regression test passa |
| 4. Wagner abre `/whatsapp/inbox` | `CaixaUnificadaController` | Lista threads via `InboxQueryService` |
| 5. Wagner digita `/lembrete amanhã 9h` | `Services/Notes/LembreteHandler` | Scheduled job + side-effect calendário |
| 6. SLA scan diário | `Services/Sla/SlaEnforcer` + `SlaScanCommand` | Conversas estagnadas marcadas `needs_attention` |
| 7. Snapshot agregado pra dashboard observability | `Services/Metrics/MetricsSnapshotBuilder` (Wave 18) | Taxa sucesso outbound + breakdown por driver |
| 8. CSAT enviado após `closed` | `Services/Csat/CsatTriggerService` | Mensagem template + score 1-5 capturado |

## Estrutura

```
Modules/Whatsapp/
├── Config/                        # whatsapp.php, drivers.php
├── Console/Commands/              # observability-health, sla:scan, channels:reconcile, backfill-*, etc
├── Database/Migrations/           # whatsapp_messages, whatsapp_conversations, whatsapp_business_phones, csat_*, ...
├── Entities/                      # WhatsappMessage, WhatsappConversation, BusinessPhone, ...
├── Events/                        # MessageReceived, MessageSent, ...
├── Http/
│   ├── Controllers/               # CaixaUnificadaController, BaileysWebhookController, MetaCloudWebhookController, ...
│   └── Requests/                  # SendMessageRequest, ...
├── Jobs/                          # ProcessIncomingWebhookJob, SendWhatsappMessageJob, NotifyRepairCustomerJob, ...
├── Listeners/                     # PII redactor, auto-prefix sender name, ...
├── Observers/                     # ChannelObserverSyncDaemon, ContactObserverCacheInvalidation, LidBackfillObserver, ...
├── Providers/                     # WhatsappServiceProvider
├── Routes/                        # web.php + api.php
├── Services/
│   ├── Audio/                     # transcription
│   ├── Centrifugo/                # token issuer + publish
│   ├── Contacts/                  # LidPhoneResolver + auto-link
│   ├── Csat/                      # trigger + score
│   ├── CustomerMemory/            # rebuilder + backfill
│   ├── Drivers/                   # ZApiDriver, MetaCloudDriver, BaileysDriver (union type)
│   ├── EmployeePerformance/       # rebuilder
│   ├── InboxQueryService.php
│   ├── Macros/                    # variant picker + CRUD
│   ├── Metrics/                   # MetricsAggregator, MetricsSnapshotBuilder (Wave 18)
│   ├── Notes/                     # SlashCommand parser/registry + 4 handlers (lembrete/lembrar/corrigir/config)
│   ├── Sla/                       # SlaEnforcer
│   └── Webhook/                   # signature, idempotency, backpressure, replay protection
├── Templates/                     # Meta Cloud HSM templates
├── Tests/Feature/                 # 80+ tests Pest (mais saturação Wave 18)
└── daemon-node/                   # Baileys daemon Node.js (CT 100 only — NUNCA Hostinger)
```

## Drivers (ADR 0096 emenda 4)

| Driver | Status | Quando usar |
|--------|--------|-------------|
| **Meta Cloud API** | default fallback obrigatório | Transacional crítico (NFe, boleto) |
| **Z-API** | default operacional | Inbox/atendimento diário |
| **Baileys 7.x** | irreversível ([ADR 0096](../../memory/decisions/0096-modulo-whatsapp-meta-cloud-api-direto.md)) | Self-hosted CT 100 + warmup anti-ban |

⛔ **NUNCA** sugerir Baileys 6.7.9 ou esperar 7.0.0 final — decisão Wagner 3× confirmada.

## Observabilidade D9.a (ADR 0155)

Spans canon (zero-cost se `otel.enabled=false`):
- `whatsapp.webhook.received`
- `whatsapp.message.send.*`
- `whatsapp.observability.snapshot`
- `whatsapp.metrics.snapshot_outbound` (Wave 18)
- `whatsapp.metrics.snapshot_por_driver` (Wave 18)
- `whatsapp.test.span` (smoke teste)

Atributos sempre `business_id` + `module=Whatsapp`. PII redacted via `Modules\Jana\Services\Privacy\PiiRedactor` antes de log.

## LGPD

- Opt-in obrigatório via `Contact::canReceiveWhatsappNotification()` (NULL=back-compat, FALSE=bloqueia + log)
- PII redactor em todo log estruturado
- Retention configurável (default 90d media, 180d messages, 365d conversations)

## Referências

- SPEC: `memory/requisitos/Whatsapp/SPEC.md` (101k linhas)
- BRIEFING: `memory/requisitos/Whatsapp/BRIEFING.md`
- README detalhado: `memory/requisitos/Whatsapp/README.md`
- CAPTERRA: `memory/requisitos/Whatsapp/CAPTERRA-FICHA.md` + `CAPTERRA-INVENTARIO.md`
- Compliance: `memory/requisitos/Whatsapp/COMPLIANCE.md`
- PII-REDACTION: `memory/requisitos/Whatsapp/PII-REDACTION.md`
- Runbook Inbox V4: `memory/requisitos/Whatsapp/RUNBOOK-inbox-caixa-unificada-v4.md`
- Runbook Baileys ban: `memory/requisitos/Whatsapp/runbooks/baileys-troubleshoot-ban.md`
- ADRs: [0058](../../memory/decisions/0058-reverb-substituido-por-centrifugo-frankenphp.md), [0096](../../memory/decisions/0096-modulo-whatsapp-meta-cloud-api-direto.md)
