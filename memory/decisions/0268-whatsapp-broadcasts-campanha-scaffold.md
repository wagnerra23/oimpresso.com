---
slug: 0268-whatsapp-broadcasts-campanha-scaffold
number: 268
title: "whatsapp_broadcasts — campanha broadcast cross-canal (modelo + pre-flight; disparo é fase 2) (US-WA-306)"
type: adr
status: proposto
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-06-10"
module: whatsapp
quarter: 2026-Q2
tags: [whatsapp, atendimento, caixa-unificada, broadcast, lgpd, per-schema, multi-tenant, scaffold]
supersedes: []
supersedes_partially: []
superseded_by: []
related: ["0093-multi-tenant-isolation-tier-0", "0135-omnichannel-inbox-arquitetura", "0267-whatsapp-queues-tabela-filas-atendimento"]
pii: false
---

# ADR 0268 — `whatsapp_broadcasts`: campanha broadcast (modelo + pre-flight; disparo fase 2)

> ADR per-schema (padrão charter Caixa Unificada §1). Origem: brief [CC]
> 2026-06-10, mandato [W] "aplicar todas" — US-WA-306 (charter §2). O próprio
> brief prevê: "Se o escopo estourar, entregar scaffold honesto (modelo +
> pre-flight sem disparo) e marcar WIP" — este ADR formaliza esse corte.

## Status

Proposto — 2026-06-10 · [CL] sob mandato [W] (brief PR-7/10).

## Contexto

Wagner quer disparar mensagem (HSM Meta ou freeform Baileys) pra N contatos
com 1 click, respeitando janela 24h Meta + opt-in LGPD. Hoje não existe:
1. Modelo de campanha (quem recebeu, quando, com qual template).
2. Campo de opt-in marketing no Contact (LGPD Art. 7º — consentimento).
3. Pipeline de disparo em massa com rate-limit (anti-ban Baileys/Meta).

O pipeline de envio atual (`InboxController::send`) é request-scoped (sessão,
ACL, driver inline) — disparo em massa por Job exige extrair o dispatch pra
Service reusável. Esse refactor é a parte cara e arriscada (anti-ban, retry,
idempotência) → **fase 2** com gate [W].

## Decisão

### Fase 1 (este PR — scaffold honesto)

**Tabela `whatsapp_broadcasts`** (campanha):

| Coluna | Tipo | Notas |
|---|---|---|
| `id` | bigIncrements | |
| `business_id` | unsignedInteger | **Tier 0 ADR 0093** |
| `channel_id` | unsignedBigInteger | conta de envio |
| `created_by_user_id` | unsignedInteger | autor |
| `kind` | string(10) | `freeform` \| `template` |
| `template_name` | string(64) nullable | quando kind=template |
| `body` | text nullable | quando kind=freeform |
| `status` | string(20) default 'draft' | `draft` \| `dispatching` \| `done` \| `failed` — **só `draft` é gravado na fase 1** |
| `audience_snapshot` | json | contagens do pre-flight (total/opt_in/in_window/hsm_only) congeladas no save |
| `recipient_conversation_ids` | json | ids das conversas elegíveis no momento do pre-flight |
| `dispatched_at` | timestamp nullable | fase 2 |
| timestamps | | |

**Coluna `whatsapp_opt_in_at`** (timestamp nullable) em `contacts` —
consentimento de marketing LGPD. NULL = sem opt-in = **fora de qualquer
broadcast**. Quem marca: atendente/cliente em fase própria (backfill e UI de
consentimento = decisão [W]; nesta fase a coluna nasce vazia e o pre-flight
mostra o tamanho real do problema).

**Pre-flight real** (endpoint): pra conta escolhida, calcula sobre as
conversas existentes do canal (não-bloqueadas, com Contact CRM vinculado):
- `total` elegível bruto
- `with_opt_in` (LGPD — só estes entram em lista de disparo)
- `in_window` (last_inbound_at ≥ now-24h → freeform permitido)
- `hsm_only` (fora da janela → só template HSM APPROVED)

**Sem disparo**: botão "Disparar" nasce disabled com aviso explícito de fase 2
(anti M-AP-2 — nada de fingir envio em massa que não roda).

### Fase 2 (US futura — gate [W])

Extração do dispatch do `InboxController::send` pra Service + Job em fila com
rate-limit configurável + retry idempotente por destinatário + relatório de
entrega. Só então `status` transita draft→dispatching→done.

## Consequências

- LGPD primeiro: lista de disparo NUNCA inclui contato sem `whatsapp_opt_in_at`.
- Audience congelada no draft (auditável — quem estaria na lista naquele momento).
- Broadcast vira dado, não ação — dá pra revisar com [W] antes de ligar o motor.

## Alternativas consideradas

1. **Disparo síncrono no request** — rejeitado: N envios num request = timeout
   + ban risk + zero retry.
2. **Sem opt-in (mandar pra todo mundo)** — rejeitado: LGPD Art. 7º; multa.
3. **Opt-in em tabela própria** — rejeitado nesta fase: 1 timestamp no Contact
   resolve e CRM já é a fonte de identidade (ADR 0197).
