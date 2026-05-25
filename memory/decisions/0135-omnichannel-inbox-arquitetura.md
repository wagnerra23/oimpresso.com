---
slug: 0135-omnichannel-inbox-arquitetura
number: 135
title: "Omnichannel inbox — schema polimórfico Channel+Driver, 4 fases com gate cliente-sinal"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-05-11"
module: whatsapp
tags: [omnichannel, inbox, channel, driver-pattern, atendimento, whatsapp, instagram, messenger, email, mercadolivre]
supersedes: []
supersedes_partially: []
amends: [0096, 0117]
superseded_by: []
related: ["0093-multi-tenant-isolation-tier-0", "0094-constituicao-v2-7-camadas-8-principios", "0096-modulo-whatsapp-meta-cloud-api-direto", "0105-cliente-como-sinal-guiar-sem-mandar", "0117-multiplos-numeros-whatsapp-por-business"]
pii: false
review_triggers:
  - "≥1 cliente pagante reportar necessidade de Mercado Livre ou Email inbox → ativar Fase 2/3"
  - "Meta deprecar Facebook Messenger API ou Instagram Graph mudar breaking → reavaliar Fase 1"
  - "Take Blip / Octadesk lançarem feature gratuita que substitui valor inbox unificada → reavaliar diferenciação"
  - "Daemon Baileys atingir ≥3 bans consecutivos em 30d → reavaliar Whatsapp não-oficial vs só Meta Cloud"
  - "Volume mensagens >100k/mês por business → reavaliar storage messages.payload_json e mover anexos pra S3 (US-INFRA-012)"
---

# ADR 0135 — Omnichannel inbox arquitetura (Channel polimórfico + Driver pattern)

## Contexto

Wagner pediu 2026-05-11 uma tela "Adicionar canal de atendimento" cobrindo WhatsApp + Facebook + Instagram + Mercado Livre + Email + Baileys. Hoje o módulo `Modules/Whatsapp` já tem 3 drivers (Z-API, Meta Cloud, Baileys) implementados sob `DriverInterface` ([ADR 0096](0096-modulo-whatsapp-meta-cloud-api-direto.md)), e [ADR 0117](0117-whatsapp-multi-phone-per-business.md) introduziu `WhatsappBusinessPhone` (N rows por business). O pattern já é parcialmente polimórfico — falta generalizar pra outros canais.

Concorrentes BR (Take Blip, Botmaker, Octadesk, Movidesk) cobram **R$ 300-1.500/mês** por inbox unificada. Bling/Tiny/Conta Azul **não têm** essa feature — diferenciação real.

## Decisão

**1. Schema polimórfico em 3 entidades canônicas:**

```
channels                                        ← substitui whatsapp_business_phones (long-term)
  id, business_id, type, label, status, config_json, created_at, updated_at
  type IN ('whatsapp_meta', 'whatsapp_zapi', 'whatsapp_baileys',
           'instagram', 'messenger', 'email_imap', 'email_smtp',
           'mercadolivre')

conversations                                    ← substitui whatsapp_conversations (long-term)
  id, business_id, channel_id, customer_external_id (FK polimórfico — phone | fb_user_id | ml_buyer_id | email),
  contact_name, status, assigned_user_id, bot_handling, last_message_at, ...

messages                                         ← substitui whatsapp_messages (long-term)
  id, business_id, conversation_id, direction (in|out), body, status,
  payload_json (canal-específico — meta_message_id, ml_question_id, email_message_id),
  sender_kind, created_at
```

Todas as 3 tabelas mantêm `business_id` com global scope obrigatório ([ADR 0093 Tier 0](0093-multi-tenant-isolation-tier-0.md)).

**2. Driver pattern — extensão do existente:**

```
ChannelDriverInterface
  → sendMessage(channel, conversation, body): SendResult
  → handleInboundWebhook(payload): InboundEvent
  → checkHealth(channel): HealthStatus

Drivers:
  WhatsappMetaCloudDriver    (existe)
  WhatsappZapiDriver         (existe)
  BaileysDriver              (existe — daemon CT 100)
  InstagramGraphDriver       (novo — mesma auth Meta Cloud)
  MessengerGraphDriver       (novo — mesma auth Meta Cloud)
  EmailImapDriver            (novo — Postmark/SES/SendGrid)
  MercadoLivreDriver         (novo — ML Messaging API)
```

`ChannelDriverFactory` resolve driver pelo `channels.type`.

**3. UI única `/atendimento/inbox` substitui `/whatsapp/conversations`:**

- Mesma 3-pane cockpit (lista | thread | sidebar) — já implementada
- Filtro por canal (chip: Todos / WhatsApp / Instagram / Email / ML)
- Composer detecta canal e roteia pro driver certo
- Sidebar mostra contexto específico do canal (window 24h pra WA, prazo SLA ML, etc.)

**4. Fases com gate de cliente-sinal ([ADR 0105](0105-cliente-como-sinal-guiar-sem-mandar.md)):**

| Fase | Escopo | h IA-pair | Gate pra ativar |
|---|---|---|---|
| **0 — Abstração** | Migration `channels`, `conversations`, `messages` polimórficas. Backfill `whatsapp_business_phones`→`channels` + `whatsapp_conversations`→`conversations`. Whatsapp drivers continuam funcionando sem breaking. UI renomeada de `/whatsapp/conversations` → `/atendimento/inbox`. | ~8h | Aprovado neste ADR — começa próximo cycle |
| **1 — Instagram + Messenger** | Drivers usando Meta Graph (mesma auth Cloud API). Webhook receivers. UI já preparada filtra por type. | ~6h | Cliente pedir OU base instalada mostrar engajamento Insta DM no comportamento |
| **2 — Email inbox** | Inbound IMAP/Postmark webhook + outbound (já existe Laravel Mail). Treat email como conversation longa, não thread curto. | ~12h | ≥10 emails inbound/dia legítimos OU cliente pagante pedir |
| **3 — Mercado Livre** | Driver ML Messaging API + sync produto (perguntas amarradas ao item). | ~16h | **1 cliente pagante** com ML como canal de venda. Sem isso, ADR feature-wish |

Estimates seguem [ADR 0106](0106-recalibracao-velocidade-fator-10x-ia-pair.md) (fator 10× IA-pair + margem 2× = total real ~16h Fase 0).

## Não-objetivos

- ❌ **Twitter/X** — API instável, mudou 3× em 2 anos, paga API (>$100/mês), base BR <5% PME. Rejeitado permanentemente. Reabrir só se ADR explícito.
- ❌ **Telegram** — Bot API estável mas adesão PME BR <5%. Rejeitado por ROI.
- ❌ **SaaS pronto** (Take Blip, Octadesk) — descartado por (a) revenue dual em Modo MoR — quem cobra é o oimpresso; (b) LGPD — dados de mensagens passam por terceiro; (c) lock-in API proprietária.
- ❌ **Reescrever Whatsapp do zero** — refactor incremental preserva 100% do código atual de drivers, webhooks, jobs, templates.
- ❌ **Breaking changes pré-cutover** — Fase 0 mantém tabelas antigas como views ou colunas redundantes até validar com ROTA LIVRE 7d canary.

## Consequências

### Positivas

- Aproveita 80% do trabalho Whatsapp já feito (DriverInterface, webhook pipeline, Centrifugo real-time, AdminLayout cockpit pattern)
- Diferenciação clara vs Bling/Tiny/Conta Azul (não têm inbox unificada)
- Pricing power — feature comparable ao Take Blip a um décimo do custo de manutenção
- Driver pattern incentiva contribuição comunitária / parceiros (qualquer canal vira plugin)

### Negativas / riscos

- **N integrações = N pontos de quebra forever** (Meta Graph muda quebrando ~1×/ano; ML quebra webhook ~1×/2anos)
- Manutenção exige 1 dev sênior alocado parcial em "infra atendimento" — custo recorrente
- Risco de scope creep — cliente vai pedir "tem TikTok também?" — disciplina ADR feature-wish é crítica
- Schema polimórfico tem inerent overhead vs schema dedicado (payload_json JSONB consultas mais lentas que colunas tipadas — mitigado com índices funcionais quando volume justificar)

## Implementação Fase 0 — DoD

**Backend:**
- [ ] Migration `2026_05_XX_create_omnichannel_tables.php` cria `channels`, `conversations`, `messages` com FKs e índices
- [ ] Model `Channel extends Model` com `HasBusinessScope` (Tier 0)
- [ ] Model `Conversation extends Model` polimórfico (channel_id)
- [ ] Model `Message extends Model` polimórfico (conversation_id)
- [ ] Backfill Command `omni:backfill-from-whatsapp` migra `whatsapp_business_phones`→`channels`, `whatsapp_conversations`→`conversations`, `whatsapp_messages`→`messages`. Idempotente, dual-mode SQLite/MySQL.
- [ ] `WhatsappBusinessPhone` continua existindo (views ou tabela espelho) até Fase 0 +30d
- [ ] `ChannelDriverFactory::resolve($channel->type)` retorna driver correto

**Frontend:**
- [ ] Renomear `/whatsapp/conversations` → `/atendimento/inbox` (alias old preservado, redirect 301)
- [ ] Componente `ConversationList` aceita prop `channelFilter: string[]`
- [ ] Componente `ConversationSidebar` mostra contexto canal-aware (slot por tipo)
- [ ] Tela nova `/atendimento/canais` lista channels + add modal + edit (vira US-WA-040 fechada)

**Testes:**
- [ ] Pest cross-tenant biz=99 verifica isolation em `channels`/`conversations`/`messages`
- [ ] Snapshot test confirma `whatsapp.conversations.index` rota redireciona pra `/atendimento/inbox`
- [ ] Backfill test cobre Whatsapp existente → Channel + Conversation + Messages preservados

**Smoke:**
- [ ] ROTA LIVRE (biz=4) usa inbox unificada 7d canary sem regressão antes de remover tabelas legacy

## Alternativas consideradas

1. **Tabelas dedicadas por canal** (`instagram_conversations`, `email_messages`, etc.) — descartado: explode boilerplate, UI tem que conhecer N tabelas, query inbox unificada vira UNION complexo
2. **Channel apenas como enum em `whatsapp_conversations`** — descartado: gerundio de schema; impede Instagram/Email sem table rename
3. **Adotar Take Blip BSP whitelabel** — descartado: LGPD + lock-in + revenue dual quebra
4. **Refactor big-bang** (renomear todas tabelas Whatsapp de uma vez) — descartado: risco regression em prod ROTA LIVRE (99% volume)

## Stories de implementação criadas

- **US-WA-061** — Fase 0 schema polimórfico Channel + Conversation + Message (p1, 8h, cycle CYCLE-06)
- **US-WA-062** — Fase 0 UI `/atendimento/inbox` + `/atendimento/canais` (p1, 8h, depende WA-061)
- **US-WA-063** — Fase 1 Instagram driver (p2, 6h, gate: cliente sinaliza)
- **US-WA-064** — Fase 2 Email inbox (p3 backlog feature-wish até gate)
- **US-WA-065** — Fase 3 Mercado Livre driver (p3 backlog feature-wish até cliente pagante)

## Skill / hook follow-up

- `mwart-quality` já cobre a UI nova (Inertia/React, 9 pré-flight checks)
- Skill nova futura `omnichannel-driver-add` quando Fase 1 entregar — pra ser pattern reusável

## Referências

- [ADR 0096](0096-modulo-whatsapp-meta-cloud-api-direto.md) — Módulo Whatsapp + emenda 4 Baileys
- [ADR 0117](0117-whatsapp-multi-phone-per-business.md) — Multi-phone schema (este ADR amplia pra multi-channel)
- [ADR 0093](0093-multi-tenant-isolation-tier-0.md) — `business_id` global scope IRREVOGÁVEL
- [ADR 0105](0105-cliente-como-sinal-guiar-sem-mandar.md) — Gate cliente-sinal por canal
- [ADR 0106](0106-recalibracao-velocidade-fator-10x-ia-pair.md) — Estimates IA-pair
- Concorrentes: Take Blip (https://take.net), Botmaker, Octadesk, Movidesk
