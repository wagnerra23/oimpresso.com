# Especificação funcional — Whatsapp

> Convenção do ID: `US-WA-NNN` para user stories, `R-WA-NNN` para regras Gherkin.
> Decisão arquitetural mãe: [ADR 0096](../../decisions/0096-modulo-whatsapp-meta-cloud-api-direto.md) — **Z-API/Baileys driver default + Meta Cloud fallback obrigatório (Evolution PROIBIDO Tier 0)**.

## 1. Glossário rápido

- **Cloud API** — Meta Cloud API (oficial, hospedada Meta). Endpoint: `graph.facebook.com/v21.0`
- **HSM (Highly Structured Message)** — template aprovado Meta pra mensagens fora da janela 24h
- **Janela 24h** — após cliente enviar mensagem, business pode responder freeform por 24h sem HSM
- **`phone_number_id`** — ID Meta do número Whatsapp Business (não é o telefone, é o ID interno)
- **`access_token`** — token Meta long-lived (60 dias) ou system-user (eterno) — preferir system-user
- **HMAC** — assinatura webhook, header `X-Hub-Signature-256` calculado com `app_secret`
- **HITL** — Human In The Loop (handoff bot→humano quando PolicyEngine retorna `REQUIRE_HUMAN_REVIEW`)
- **Deflection** — % de conversas resolvidas sem intervenção humana
- **Conversation** — janela 24h Meta (cobrada uma vez); várias mensagens dentro = 1 conversa
- **Driver** — abstração `ZapiDriver` (default Sprint 1) / `MetaCloudDriver` (fallback obrigatório Sprint 1) / `BaileysDriver` (custom oimpresso Sprint 3) / `NullDriver`
- **Z-API (default Sprint 1)** — SaaS BR (`api.z-api.io`) baseado em Whatsapp Web/Baileys. Onboarding 5 min (scan QR). **Risco ban Meta MUITO ALTO** (mitigado por fallback obrigatório + termo LGPD)
- **Meta Cloud (fallback obrigatório Sprint 1)** — oficial Meta. Free 1k conv/mês BR. Sem risco ban. Cadastro paralelo gating duro do Z-API.
- **BaileysDriver custom (Sprint 3)** — daemon Node CT 100 próprio rodando lib `@whiskeysockets/baileys` direto. Schema, logs OTel, métricas e health check sob nosso controle total. Justificativa: dor de observabilidade no Evolution. Container Docker compose-managed `whatsapp-baileys` no CT 100 (ADR 0058 + skill `proxmox-docker-host`). Detalhes em ARCHITECTURE.md §16.
- **Evolution API** — **PROIBIDO permanente** ([ADR 0096 emenda 4](../../decisions/0096-modulo-whatsapp-meta-cloud-api-direto.md)). Razões concretas: bans recorrentes em produção Wagner + schema não atende + falta de observabilidade.
- **Driver Health Check** — job 6h em 6h envia ping pra detectar ban Z-API (Sprint 2). Estende pra BaileysDriver no Sprint 3.
- **Fallback automático** — quando driver não-oficial (`zapi`, `baileys`) `driver_health` ≥ degraded, sistema troca pra Meta Cloud sem intervenção

## 2. User Stories — Sub-módulo Core (Sprint 1)

### US-WA-001 · Wizard 2 passos — Z-API hoje + Meta Cloud em paralelo

> owner: wagner · sprint: 1 · priority: p2 · status: done

> **Área:** Settings
> **Rota:** `GET/PUT /whatsapp/settings`
> **Controller/ação:** `BusinessSettingsController@show` / `update`
> **Permissão Spatie:** `whatsapp.settings.manage`

**Como** Wagner (admin business)
**Quero** ativar Z-API em 5 min hoje + iniciar processo Meta Cloud em paralelo (que aprova em 1-3 dias)
**Para** ter Whatsapp funcionando hoje + rede de segurança operacional ativa quando Meta aprovar

**DoD:**
- [ ] UI mostra wizard 2 passos: **Passo 1 — Liga Z-API hoje** + **Passo 2 — Cadastra Meta Cloud (1-3 dias)**
- [ ] Passo 1 obrigatório (não pode pular pra Passo 2 e ficar sem Whatsapp ativo)
- [ ] Passo 2 obrigatório como gating (não deixa salvar `driver=zapi` sem `meta_*` campos preenchidos — princípio Tier 0 fallback)
- [ ] Form Passo 1 (Z-API):
  - `zapi_instance_id`, `zapi_instance_token`, `zapi_client_token` (todos cifrados em DB)
  - Botão "Testar conexão" → `ZapiDriver::ping()` → mostra QR Code se aguardando, ou "Conectado, número +5511..."
- [ ] Form Passo 2 (Meta Cloud):
  - `meta_phone_number_id`, `meta_access_token`, `meta_app_secret`, `meta_webhook_verify_token` (todos cifrados)
  - Onboarding guide com link `business.facebook.com` + screenshots
  - Status: "aguardando você completar Meta Business Manager"
- [ ] Tokens (todos) cifrados em DB via `encrypted` cast Laravel
- [ ] FormRequest cross-field: `driver=zapi` (default) → exige `meta_*` cadastrados como fallback
- [ ] Webhook URLs exibidas em cada passo:
  - Passo 1 Z-API: `https://oimpresso.com/api/whatsapp/webhook/zapi/{business_uuid}` (cola no painel Z-API → Webhooks)
  - Passo 2 Meta: `https://oimpresso.com/api/whatsapp/webhook/meta/{business_uuid}` + `webhook_verify_token` (cola na Meta App → Webhooks)
- [ ] Badge UI permanente quando `driver=zapi`: 🔴 "Provedor não-oficial — risco ban Meta. Fallback Meta Cloud ativo." (vermelho, sempre visível)
- [ ] Termo LGPD obrigatório (modal) quando salva `driver=zapi`: "Estou ciente que Z-API é provedor não-oficial baseado em Whatsapp Web e que existe risco de bloqueio Meta. Configurei Meta Cloud como fallback pra mitigar interrupção." → registra `lgpd_acknowledged_at`
- [ ] Toggle "Forçar Meta Cloud como driver primário" (pra businesses enterprise compliance) — flipa `driver=meta_cloud`, deixa Z-API dormente
- [ ] Pest: `BusinessSettingsTest` cobrindo (a) cada driver salva credenciais corretas, (b) tokens cifrados em DB, (c) isolamento multi-tenant, (d) **gating: salvar driver=zapi sem meta_* preenchido = 422 ValidationException**, (e) termo LGPD obrigatório, (f) flipar pra meta_cloud preserva Z-API config

### US-WA-002 · Driver Interface + ZapiDriver + MetaCloudDriver + NullDriver

> owner: wagner · sprint: 1 · priority: p2 · status: review

> **Área:** Core
> **Service:** `Modules\Whatsapp\Services\Drivers\DriverInterface`
> **Implementações Sprint 1:** `ZapiDriver` (default), `MetaCloudDriver` (fallback obrigatório), `NullDriver` (dev/CI)
> **EvolutionDriver: PROIBIDO Tier 0** — não vai ser implementado.

**Como** Sistema
**Quero** abstração trocável Z-API ↔ Meta Cloud
**Para** business usar Z-API hoje (5 min) + cair pra Meta Cloud automaticamente em caso de ban

**DoD:**
- [ ] Interface `DriverInterface`:
  - `sendTemplate(WhatsappBusinessConfig $config, string $to, string $templateName, array $params, string $locale='pt_BR'): WhatsappSendResult` — Meta usa HSM; Z-API/Evolution mandam como freeform
  - `sendFreeform(WhatsappBusinessConfig $config, string $to, string $body): WhatsappSendResult`
  - `sendMedia(WhatsappBusinessConfig $config, string $to, string $mediaUrl, string $type, ?string $caption): WhatsappSendResult`
  - `fetchMessageStatus(WhatsappBusinessConfig $config, string $providerMessageId): MessageStatus`
  - `ping(WhatsappBusinessConfig $config): DriverHealthStatus` — retorna nome número, sessão ativa, last_seen
- [ ] `MetaCloudDriver` usa `Http::withToken()` Laravel HTTP client; sem dependência Composer extra
- [ ] `NullDriver` retorna sucesso fake; gera `provider_message_id` UUID; usa `Event::dispatch` pra simular delivery
- [ ] Factory `DriverFactory::make($business)` resolve via `whatsapp_business_configs.driver` (efetivo, considerando driver_health):
  ```php
  // Se driver primário está degraded/banned, usa fallback automaticamente
  $driver = $config->driver_health === 'healthy' ? $config->driver : $config->fallback_driver;
  return match($driver) {
      'zapi' => app(ZapiDriver::class),
      'meta_cloud' => app(MetaCloudDriver::class),
      'null' => app(NullDriver::class),
      // 'evolution' => PROIBIDO Tier 0 — não tem case aqui
  };
  ```
- [ ] Pest: `MetaCloudDriverTest` com `Http::fake()` cobrindo sucesso/4xx/5xx; `ZapiDriverTest`; `NullDriverTest`; `DriverFactoryTest` resolve correto por config + cobre fallback automático em driver_health=degraded

### US-WA-002b · ZapiDriver (driver não-oficial Sprint 1)

> **Área:** Core
> **Service:** `Modules\Whatsapp\Services\Drivers\ZapiDriver`
> **Permissão Spatie:** `whatsapp.send`

**Como** Sistema
**Quero** enviar/receber via Z-API (`api.z-api.io`) com mesma interface DriverInterface
**Para** business com onboarding rápido (5 min scan QR) usar Whatsapp sem aprovação Meta

**DoD:**
- [ ] Implementa `DriverInterface` integralmente — Send: `POST /instances/{id}/token/{token}/send-text` (Z-API REST docs); Media: `/send-image`, `/send-document`
- [ ] Header `Client-Token: {client_token}` (segurança Z-API)
- [ ] `sendTemplate()` em ZapiDriver simplesmente expande template localmente e manda freeform (Z-API não usa HSM)
- [ ] `ping()` chama `GET /instances/{id}/token/{token}/status` retornando `{connected, smartphoneConnected, session, ...}`
- [ ] `fetchMessageStatus()` chama Z-API status endpoint
- [ ] Tratamento erros: 401/403 = sessão caiu (gera evento `WhatsappDriverSessionLost`); 5xx = retry transitório
- [ ] Pest: `ZapiDriverTest` com `Http::fake()` cobrindo (a) send-text sucesso, (b) send-text 401 dispara session lost event, (c) ping conectado/desconectado, (d) sendMedia base64 ou URL
- [ ] **Risco aceito documentado** no class-level docblock: "Driver não-oficial. Risco ban Meta. Use com fallback Meta Cloud configurado. Ver ADR 0096 §Risco aceito conscientemente."

### ❌ US-WA-002c · EvolutionDriver — REMOVIDA (Evolution PROIBIDO permanente)

Anteriormente proposta como driver self-host CT 100. **Removida em 2026-05-07 (emenda 3 ADR 0096; reforçada em emenda 4 com razões concretas Wagner):**

1. Evolution está banindo números reais em produção do Wagner
2. Schema de banco do Evolution não atende a estrutura customizada de atendimento
3. Falta de observabilidade — Wagner sentiu na pele a opacidade quando bans aconteceram

Reabrir só se Evolution mudar substancialmente esses 3 pontos (improvável; não esperar).

### US-WA-002d · BaileysDriver custom (Sprint 3 — autorizado emenda 4)

> **Área:** Core Sprint 3
> **Service:** `Modules\Whatsapp\Services\Drivers\BaileysDriver`
> **Permissão Spatie:** `whatsapp.send`
> **Componente Node:** novo container Docker `whatsapp-baileys` no CT 100

**Como** Sistema (estrutura customizada de atendimento)
**Quero** enviar/receber via daemon Node próprio rodando Baileys lib direto
**Para** ter controle total do schema, logs OTel, métricas e health check (resolver dor de observabilidade do Evolution)

**Decisão arquitetural mãe:** [ADR 0096 emenda 4](../../decisions/0096-modulo-whatsapp-meta-cloud-api-direto.md). Wagner explicitamente reconhece "vai ter código extra por essa decisão" e justifica pela "dor de observabilidade".

**DoD (Sprint 3):**

#### Componente Node (daemon CT 100)
- [ ] Novo container Docker compose-managed `whatsapp-baileys` em CT 100 (skill `proxmox-docker-host` + ADR 0058)
- [ ] Lib `@whiskeysockets/baileys` versão pinned (não `latest` — ADR 0096 review_trigger registra "Mudança Meta TOS quebra biblioteca")
- [ ] Wrapper HTTP REST minimal (Fastify ou Hono) expondo endpoints:
  - `POST /instances/{instance_id}/text` (sendText)
  - `POST /instances/{instance_id}/media` (sendMedia)
  - `GET /instances/{instance_id}/status` (ping)
  - `GET /instances/{instance_id}/qr` (QR Code pra setup)
- [ ] Auth state (sessão Whatsapp Web) persistido em volume `/srv/docker/whatsapp-baileys/sessions/{instance_id}` (volume mapeado)
- [ ] Header `Authorization: Bearer {api_key}` + IP whitelist (só Hostinger PHP fala com daemon — daemon nunca exposto pra internet)
- [ ] Webhook outbound pro oimpresso PHP em `https://oimpresso.com/api/whatsapp/webhook/baileys/{business_uuid}` quando mensagem chega
- [ ] OTel traces nativos exportando pra Loki CT 100 + métricas Prometheus
- [ ] Health endpoint `/health` retornando lista de instances + estado conexão de cada
- [ ] Repositório separado: `oimpresso/whatsapp-baileys-daemon` (Node) ou submódulo `Modules/Whatsapp/daemon-node/`

#### Componente PHP (BaileysDriver)
- [ ] Implementa `DriverInterface` integralmente
- [ ] `Http::baseUrl(config('whatsapp.baileys.daemon_url'))->withToken(...)` — fala com daemon CT 100
- [ ] Mapeia respostas daemon pra `WhatsappSendResult` / `MessageStatus` / `DriverHealthStatus`
- [ ] Detecção ban: erro `Connection Closed (statusCode: 401)` Baileys = `banDetected=true` no `WhatsappSendResult`
- [ ] Pest: `BaileysDriverTest` com `Http::fake()` cobrindo (a) sendText sucesso, (b) ban detection, (c) ping ativo/QR pendente, (d) connection closed dispara `WhatsappDriverSessionLost`
- [ ] Class-level docblock: "Driver custom Whatsapp Web (Baileys) — daemon Node CT 100 próprio. Risco ban Meta aceito (ADR 0096 emenda 4); fallback Meta Cloud obrigatório."

#### Settings UI (Sprint 3)
- [ ] Wizard ganha 3ª opção "Baileys custom (avançado)" além de Z-API e Meta Cloud
- [ ] Form pede `baileys_instance_id`, `baileys_daemon_url` (default `https://whatsapp-baileys.oimpresso.local`), `baileys_api_key`
- [ ] Mantém gating fallback Meta Cloud obrigatório
- [ ] Termo LGPD igual ao Z-API mas com adendo: "estou ciente que oimpresso assume responsabilidade direta pelo daemon Node + sessão Whatsapp Web"

#### Observabilidade (a "dor" que justifica)
- [ ] OTel traces ponta-a-ponta: oimpresso PHP → daemon Node → Baileys → Whatsapp Web
- [ ] Métricas Prometheus: `whatsapp.baileys.session_state{business_id}`, `whatsapp.baileys.message_lag_ms` (quanto a sessão demora pra processar)
- [ ] Dashboard Grafana dedicado `whatsapp-baileys-daemon`
- [ ] Alarmes específicos: sessão caída > 5min, lag > 2s, container restart > 1×/h

#### Runbooks (Sprint 3)
- [ ] `memory/requisitos/Whatsapp/runbooks/baileys-daemon-deploy-ct100.md` — deploy inicial
- [ ] `memory/requisitos/Whatsapp/runbooks/baileys-troubleshoot-ban.md` — passo-a-passo quando ban acontecer (recuperar número, pivotar pra Meta Cloud temporário, etc)
- [ ] `memory/requisitos/Whatsapp/runbooks/baileys-upgrade-lib.md` — atualizar versão Baileys com cuidado (Meta TOS muda, lib quebra)

### US-WA-003 · Enviar mensagem template (Job assíncrono)

> owner: wagner · sprint: 1 · priority: p2 · status: done

> **Área:** Core
> **Job:** `SendWhatsappMessageJob`
> **Permissão Spatie:** `whatsapp.send`

**Como** Sistema (listener Repair / RecurringBilling) ou usuário (botão UI)
**Quero** enviar mensagem template HSM com retry exponencial
**Para** garantir entrega mesmo com falha temporária Meta API

**DoD:**
- [ ] Job constructor recebe `(int $businessId, string $to, string $templateName, array $params)` — **NUNCA `session()`** (multi-tenant Tier 0)
- [ ] Resolve `WhatsappBusinessConfig` via `WhatsappBusinessConfig::where('business_id', $this->businessId)->firstOrFail()`
- [ ] Cria `WhatsappMessage` em `status=queued` antes de chamar Driver
- [ ] Chama `Driver::sendTemplate()`; atualiza `status=sent` + `meta_message_id` em sucesso, `status=failed` em hard error (4xx)
- [ ] Retry exponencial: tries=5, backoff `[60, 300, 900, 3600, 86400]` (1m → 1d)
- [ ] Eventos: `WhatsappMessageQueued`, `WhatsappMessageSent`, `WhatsappMessageFailed`
- [ ] Job tag: `"business:{$this->businessId}"` (Horizon tagging pra debug)
- [ ] Pest: `SendWhatsappMessageJobTest` com `Bus::fake()` + multi-tenant isolation

### US-WA-004 · Listener Repair: status `ready` dispara WhatsApp

> owner: wagner · sprint: 1 · priority: p2 · status: done

> **Área:** Core (cross-module)
> **Listener:** `Modules\Whatsapp\Listeners\NotifyRepairCustomer`
> **Event:** `Modules\Repair\Events\RepairStatusChanged`

**Como** Sistema
**Quero** quando OS Repair muda pra `ready` ou `waiting_parts`, disparar template Whatsapp
**Para** cumprir ADR Repair tech/0001 sem custo de SMS

**DoD:**
- [ ] Listener registra em `WhatsappEventServiceProvider`; só dispara se `WhatsappBusinessConfig` existe pra business
- [ ] Template name: `repair_status_ready` ou `repair_status_waiting_parts` (configurável via `whatsapp_business_configs.template_repair_ready_name`)
- [ ] Params: `[customer_name, repair_id, ready_at_formatted]`
- [ ] Falha silenciosa se cliente não tem mobile cadastrado (log info; sem exception)
- [ ] Pest: `NotifyRepairCustomerTest` cobrindo (a) com config + cliente válido = job dispatched, (b) sem config = no-op, (c) sem mobile = log + no-op

## 3. User Stories — Sub-módulo Inbox + Webhook (Sprint 2)

### US-WA-010 · Receber webhook Meta + assinatura HMAC

> owner: wagner · sprint: 2 · priority: p2 · status: review

> **Área:** Webhook
> **Rota:** `POST /api/whatsapp/webhook/meta/{business_uuid}` (público, autenticado por HMAC)
> **Controller/ação:** `MetaWebhookController@handle`

**Como** Meta Cloud API
**Quero** entregar evento `messages` ou `statuses` ao webhook do business
**Para** o oimpresso processar mensagens recebidas e atualizações de status

**DoD:**
- [ ] Middleware `VerifyMetaSignature`: lê header `X-Hub-Signature-256`, calcula HMAC SHA-256 do raw body com `business->whatsapp_business_config->app_secret`, rejeita 401 se mismatch
- [ ] GET handler pra Meta verification challenge: retorna `hub.challenge` se `hub.verify_token` bate com `webhook_verify_token` cadastrado
- [ ] POST handler enfileira `ProcessIncomingWebhookJob` — não processa síncrono (resposta < 200ms pra Meta não retentar)
- [ ] Resposta sempre 200 (Meta retenta agressivo se ≠200) — só rejeita 401 em assinatura inválida
- [ ] Log estruturado (Loki/CT100) com `business_id`, `event_type`, `meta_message_id` — telefone redacted
- [ ] Pest: `MetaWebhookSignatureTest` cobrindo (a) HMAC válido = 200, (b) HMAC inválido = 401, (c) verify challenge = retorna challenge string

### US-WA-010b · Receber webhook Z-API

> **Área:** Webhook
> **Rota:** `POST /api/whatsapp/webhook/zapi/{business_uuid}` (público, autenticado por client_token compartilhado)
> **Controller/ação:** `ZapiWebhookController@handle`

**Como** Z-API
**Quero** entregar eventos (`on-message`, `on-message-status`, `on-presence-status`, `on-disconnected`) ao webhook do business
**Para** o oimpresso processar mensagens + detectar sessão caída

**DoD:**
- [ ] Middleware `VerifyZapiSignature`: lê header `Client-Token`, compara com `business->whatsapp_business_config->zapi_client_token` (timing-safe), rejeita 401 se mismatch
- [ ] POST handler enfileira `ProcessIncomingWebhookJob` com `provider='zapi'` no payload
- [ ] Trata evento `on-disconnected`: marca `driver_health=disconnected` + dispara fallback se configurado
- [ ] Resposta 200 mesmo em duplicata (idempotência via `provider_message_id`)
- [ ] Log estruturado com PII redacted
- [ ] Pest: `ZapiWebhookTest` cobrindo (a) Client-Token válido = 200, (b) inválido = 401, (c) on-message dispara job, (d) on-disconnected marca degraded

### US-WA-011 · Processar mensagem recebida (Job)

> owner: wagner · sprint: 2 · priority: p2 · status: done

> **Área:** Webhook
> **Job:** `ProcessIncomingWebhookJob` (CT 100 Horizon)

**Como** Sistema
**Quero** processar payload Meta `messages` criando `WhatsappMessage` direction=inbound + atualizando/criando `WhatsappConversation`
**Para** UI Inbox renderizar conversa em real-time

**DoD:**
- [ ] Idempotência: `meta_message_id` UNIQUE em `whatsapp_messages`; processar mesma mensagem 2× = no-op
- [ ] Match `Contact` existente por mobile (com/sem DDI normalizado); cria provisional se não achou (`is_provisional=true`)
- [ ] Cria/atualiza `WhatsappConversation` (1 por contact+business; updates `last_message_at`, `unread_count++`)
- [ ] Dispara evento `WhatsappMessageReceived` (listener `DispatchToJanaBot` ouve em sprint 3)
- [ ] Centrifugo publish channel `whatsapp:business:{id}` payload `{conversation_id, message_id, preview}` pra UI atualizar live
- [ ] Pest: `ProcessIncomingWebhookJobTest` cobrindo (a) primeira mensagem cria conversation, (b) segunda atualiza last_message_at, (c) duplicata = no-op

### US-WA-012 · Inbox UI (Cockpit pattern)

> owner: wagner · sprint: 2 · priority: p2 · status: done

> **Área:** Inbox
> **Rota:** `GET /whatsapp/conversations`
> **Page:** `resources/js/Pages/Whatsapp/Conversations/Index.tsx`

**Como** Larissa (atendente)
**Quero** ver lista de conversas à esquerda + chat painel à direita (Cockpit pattern ADR 0039)
**Para** atender múltiplos clientes sem trocar de tela

**DoD:**
- [ ] AppShellV2 layout (não Blade legacy — ADR 0094 visual canon)
- [ ] Lista esquerda: avatar contact + último preview + timestamp + badge `unread_count`
- [ ] Tabs filtro: "Não lidas" / "Atribuídas a mim" / "Bot" / "Resolvidas" / "Todas"
- [ ] Chat painel direito: mensagens scroll virtualizado (TanStack Virtual), bolha verde direita pra outbound, cinza esquerda pra inbound — padrão MemCofre ADR ui/0001
- [ ] Input bottom: textarea + botão "Enviar" (só ativa dentro janela 24h ou se selecionou template)
- [ ] Real-time via Centrifugo channel `whatsapp:business:{id}` — nova mensagem aparece sem reload (preserva preference cache-estado preservado)
- [ ] Botão "Atribuir a mim" / "Marcar resolvida" / "Encaminhar pra outro atendente"
- [ ] Pest dusk + browser MCP smoke test obrigatório

### US-WA-013 · Templates UI (sync Meta Business Manager)

> owner: wagner · sprint: 2 · priority: p2 · status: done

> **Área:** Templates
> **Rota:** `GET /whatsapp/templates`
> **Page:** `resources/js/Pages/Whatsapp/Templates/Index.tsx`

**Como** Wagner (admin)
**Quero** ver templates HSM cadastrados na Meta Business Manager + status (pending/approved/rejected) + body preview
**Para** saber quais posso usar e quais estão em aprovação

**DoD:**
- [ ] Botão "Sincronizar com Meta" — chama `MetaCloudDriver::fetchTemplates()` → upsert `whatsapp_templates`
- [ ] Lista: nome, language (pt_BR), category (UTILITY/MARKETING/AUTHENTICATION), status com badge colorido, body preview com `{{1}}`/`{{2}}` placeholders
- [ ] Filtro por status + category
- [ ] Disabled state com tooltip "Cadastre template na Meta Business Manager primeiro" se vazio (link MBM)
- [ ] Pest + browser MCP smoke

### US-WA-014 · Driver Health Check + fallback automático

> owner: wagner · sprint: 2 · priority: p2 · status: done

> **Área:** Core
> **Job:** `WhatsappDriverHealthCheckJob`
> **Scheduler:** a cada 6h por business com driver não-oficial ativo

**Como** Sistema
**Quero** detectar sessão caída/ban antes do business descobrir do cliente
**Para** trocar pra fallback driver automaticamente e manter operação

**DoD:**
- [ ] Job chama `Driver::ping()` por cada `WhatsappBusinessConfig` com driver != `meta_cloud` e != `null`
- [ ] Estado em `whatsapp_business_configs.driver_health`: `healthy|degraded|disconnected|banned`
- [ ] Estado em `whatsapp_business_configs.last_health_check_at`
- [ ] Falha consecutiva: 1 = warning interno, 5 = `degraded`, 10 = `disconnected`, ban-detected (auth permanent error) = `banned`
- [ ] Quando `degraded` ou pior + `fallback_driver` configurado: troca driver ativo (preserva histórico mensagens), notifica admin business via Centrifugo + email (`mail` queue)
- [ ] OTel metric `whatsapp.driver.health` (gauge per business+driver) e `whatsapp.driver.bans` (counter)
- [ ] Alarme cross-tenant: se ≥3 businesses ficaram `banned` em 24h, notificar Wagner por email/Slack (sinal mudança Meta detection)
- [ ] Pest: `WhatsappDriverHealthCheckJobTest` cobrindo (a) ping ok mantém healthy, (b) 5 falhas = degraded, (c) fallback troca driver, (d) cross-tenant alarme dispara

## 4. User Stories — Sub-módulo Bot Jana + HITL + Métricas (Sprint 3)

### US-WA-020 · Listener DispatchToJanaBot

> owner: wagner · sprint: 3 · priority: p2 · status: done

> **Área:** Bot
> **Listener:** `Modules\Whatsapp\Listeners\DispatchToJanaBot`
> **Event:** `WhatsappMessageReceived`

**Como** Sistema
**Quero** quando mensagem inbound chega + business tem `bot_enabled=true`, encaminhar pro PolicyEngine ADS
**Para** Jana responder automaticamente o que pode + escalar humano onde precisa

**DoD:**
- [ ] Chama `decide('whatsapp', 'reply', {message, conversation, business_id})` (skill `ads-route` Tier A dormente — ativará na S5)
- [ ] PolicyEngine retorna 1 dos 4 outcomes:
  - `ALLOW_BRAIN_A` → Jana responde direto (Brain A gpt-4o-mini); resposta cria `WhatsappMessage` outbound + envia
  - `REQUIRE_BRAIN_B` → escala Brain B (Sonnet); idem
  - `REQUIRE_HUMAN_REVIEW` → marca conversa `status=awaiting_human`; notifica atendentes via Centrifugo
  - `BLOCK_ALWAYS` → log + no-op
- [ ] PII redacted antes de enviar pra Brain (`PiiRedactor`)
- [ ] Pest: `DispatchToJanaBotTest` cobrindo 4 outcomes

### US-WA-021 · Métricas conversation (custo, deflection, tempo resposta)

> owner: wagner · sprint: 3 · priority: p2 · status: todo

> **Área:** Métricas
> **Tabela:** `whatsapp_conversation_metricas`
> **Service:** `WhatsappMetricasService::aggregate()`
> **Job:** scheduler diário 04:00

**Como** Wagner / Larissa
**Quero** métricas por business: custo/mês, deflection bot %, tempo médio resposta, NPS pós-conversa
**Para** justificar ROI Whatsapp e otimizar custos Meta

**DoD:**
- [ ] Schema `whatsapp_conversation_metricas`: `business_id, dia, total_conversas, conversas_iniciadas_negocio, conversas_iniciadas_cliente, custo_centavos, tempo_resposta_p50_segundos, tempo_resposta_p95_segundos, deflection_pct`
- [ ] Job agrega dia D-1 às 04:00 BRT
- [ ] Dashboard tab em UI Inbox ou módulo próprio (definir Sprint 3 detalhamento)
- [ ] OpenTelemetry `whatsapp.*` metrics paralelo (padrão ADR 0051)
- [ ] Pest: `MetricasAggregationTest` cobrindo (a) 0 conversas dia = row 0, (b) 10 conversas com 4 deflected = 40% deflection

### US-WA-022 · UX simplificada onboarding Baileys (1 telefone → QR → conectado)

> owner: wagner · sprint: 3 · priority: p2 · status: done

> **Área:** Settings UX
> **Charter:** [`resources/js/Pages/Whatsapp/Settings.charter.md`](../../../resources/js/Pages/Whatsapp/Settings.charter.md)
> **Decisão mãe:** ADR 0096 emenda 4 + ADR 0058 (Centrifugo)
> **Status:** ✅ entregue em PR #298 (mergeado 2026-05-09 14:18Z) · `review` aguardando smoke fim-a-fim em prod

**Como** admin business
**Quero** conectar Whatsapp via Baileys cadastrando apenas o telefone
**Para** não precisar entender infra (instance_id, daemon URL, API key)

Estado-da-arte SaaS (Z-API, Twilio, Wati pattern): tenant só vê dados de negócio; infra fica server-side.

**DoD:**
- [x] Page Charter `Settings.charter.md` documenta invariantes UX (criado nesta US)
- [x] Migration: `whatsapp_business_configs` remove `baileys_daemon_url` + `baileys_api_key` (vão pra `config/whatsapp.php` global), adiciona `baileys_phone_e164` + `baileys_verified_name` + `baileys_profile_pic_url`
- [x] Índice UNIQUE(business_id, baileys_phone_e164) — anti-duplicate
- [x] `BaileysDriver` lê daemon_url + api_key de config app, instance_id do model
- [x] Novo Job `BaileysConnectJob` provisiona daemon + cria instance com retry exponencial
- [x] `SettingsController@update` quando driver=baileys + phone preenchido + lgpd ok → dispara connect
- [x] `BaileysWebhookController` publica eventos qr_updated/connected/banned/session_lost/disconnected em Centrifugo channel `whatsapp:business:{id}`
- [x] `Settings.tsx` reescrito: 1 input telefone E.164 + estado reativo (connecting → qr_required → connected → banned) com QR display + countdown
- [x] Pest tests: `BaileysDriverTest` adaptado para schema nova + `WhatsappSettingsCharterTest` com 7 invariantes
- [x] Rate limit 3 connect/business/dia (anti-abuse) via Cache facade
- [ ] Smoke fim-a-fim em prod (Wagner) — após deploy daemon CT 100 + setar `WHATSAPP_BAILEYS_API_KEY` em `.env` Hostinger

**Refs:** US-WA-002 (predecessor merged), ADR 0096 emenda 4, ADR 0058 Centrifugo, ADR 0093 multi-tenant Tier 0, ADR 0107 visual-comparison (skipped via mwart-override per ADR 0112 padrão)

## 5. Regras Gherkin (DoD detalhado)

### R-WA-001 · Mensagem outbound nunca cross-tenant

```gherkin
Dado business=4 (ROTA LIVRE) com WhatsappBusinessConfig {phone_number_id=A}
E   business=7 com WhatsappBusinessConfig {phone_number_id=B}
Quando SendWhatsappMessageJob(businessId=4, to="+55119...", template, params) executa
Então usa phone_number_id=A
E   chama Meta API com access_token de business=4
E   NUNCA business=7 vê essa mensagem em sua Inbox
```

### R-WA-002 · Webhook rejeita HMAC inválido (Meta) ou Client-Token inválido (Z-API)

```gherkin
Dado business=4 com driver=meta_cloud, app_secret="abc123"
Quando POST /api/whatsapp/webhook/meta/{business_uuid_4} com header "X-Hub-Signature-256: sha256=WRONG"
Então resposta é 401 Unauthorized
E   nenhum WhatsappMessage é criado
E   log estruturado registra "webhook_signature_invalid" com business_id=4 e provider=meta

Dado business=7 com driver=zapi, zapi_client_token="xyz789"
Quando POST /api/whatsapp/webhook/zapi/{business_uuid_7} com header "Client-Token: WRONG"
Então resposta é 401 Unauthorized
E   nenhum WhatsappMessage é criado
E   log estruturado registra "webhook_signature_invalid" com business_id=7 e provider=zapi
```

### R-WA-002b · Driver health check + fallback automático

```gherkin
Dado business=4 com driver=zapi, fallback_driver=meta_cloud, MetaCloudConfig já cadastrado
E   Z-API API retorna 401 Unauthorized 5 vezes consecutivas
Quando WhatsappDriverHealthCheckJob roda
Então whatsapp_business_configs.driver_health = "degraded"
E   driver ativo é trocado de "zapi" pra "meta_cloud"
E   Centrifugo publish "whatsapp:business:4" com payload {event: "driver_fallback", from: "zapi", to: "meta_cloud"}
E   email enviado pra admin business com assunto "[oimpresso] Whatsapp do seu negócio caiu — fallback ativado"
E   histórico mensagens (whatsapp_messages) preservado intacto
E   próximo SendWhatsappMessageJob usa MetaCloudDriver
```

### R-WA-003 · Idempotência incoming webhook

```gherkin
Dado WhatsappMessage com meta_message_id="wamid.XYZ" já existe (status=received)
Quando POST /api/whatsapp/webhook/{business_uuid} com mesmo wamid.XYZ
Então ProcessIncomingWebhookJob é enfileirado
E   ao executar detecta UNIQUE meta_message_id e faz no-op
E   nenhum evento WhatsappMessageReceived é re-disparado
```

### R-WA-004 · Janela 24h e fallback HSM

```gherkin
Dado conversa com last_inbound_at = agora - 25h
Quando atendente tenta enviar freeform "obrigado pelo retorno"
Então UI bloqueia botão "Enviar" com tooltip "Janela 24h expirada — selecione um template"
E   UI mostra dropdown templates HSM aprovados pra escolher
```

### R-WA-005 · Multi-tenant Tier 0 — global scope

```gherkin
Dado WhatsappMessage e WhatsappConversation usam BusinessIdScope global
Quando Larissa (business=4) faz GET /whatsapp/conversations
Então query gera "SELECT ... WHERE business_id = 4"
E   conversas de outros businesses NUNCA aparecem
E   tentar GET /whatsapp/conversations/{id_de_outro_business} retorna 404
```

### R-WA-006 · PII redacted em logs

```gherkin
Dado log estruturado de qualquer evento Whatsapp
Quando registra payload contendo telefone "+5511987654321"
Então log persiste como "+551198765[REDACTED]" via PiiRedactor
E   commit/PR review nunca mostra telefones reais (skill commit-discipline Tier A)
```

## 6. Métricas de sucesso

- **Custo Whatsapp/business**: < R$ [redacted Tier 0]/mês pra businesses < 200 conversas/mês (ROTA LIVRE alvo)
- **Tempo entrega**: p95 < 3s do `SendWhatsappMessageJob` queued até `WhatsappMessageSent` event
- **Deflection bot**: ≥ 40% das conversas inbound resolvidas sem humano (Sprint 3 baseline)
- **Tempo resposta humano**: p50 < 5min em horário comercial
- **Webhook uptime**: ≥ 99.9% (alarme se zero eventos em 24h pra business ativo)
- **Multi-tenant violations**: 0 (teste `MultiTenantIsolationTest` no CI bloqueia merge)

### US-WA-040 · Múltiplos números por business — driver + escopo de atendimento per-phone (Sprint 4)

> owner: wagner · sprint: 4 · priority: p2 · status: doing

> **Área:** Settings + Core + Inbox
> **Decisão arquitetural mãe:** [ADR 0117](../../decisions/0117-multiplos-numeros-whatsapp-por-business.md)
> **Charter mãe:** [`Settings.charter.md`](../../../resources/js/Pages/Whatsapp/Settings.charter.md) — vai pra `charter_version: 2` (Non-Goal "1 número/business" removido)
> **Cliente sinal qualificado:** WR2 Sistemas (`business_id=1`) — Comercial + Financeiro com escopos separados
> **Status:** proposto 2026-05-09; aguardando aprovação Wagner

**Como** admin business (Wagner em WR2)
**Quero** cadastrar N números Whatsapp no mesmo business, cada um com driver + LGPD + atendentes + roteamento de eventos automáticos próprios
**Para** separar atendimento Comercial (vendas/leads) do Financeiro (cobrança/recibo) sem cross-talk de Inbox

**DoD (PR 1 — schema + models):**

- [ ] Migration cria `whatsapp_business_phones` (1 row por número) com colunas: `business_id`, `phone_uuid`, `label` (texto livre VARCHAR(80)), `driver`, `fallback_driver`, `display_phone`, `meta_*`, `zapi_*`, `baileys_*` (todas credenciais migradas de `whatsapp_business_configs`), `lgpd_acknowledged_at` + `_by_user_id`, `handles_repair_status`, `handles_billing`, `handles_jana_bot`, `handles_outbound_default`, `template_*`, `driver_health` + helpers
- [ ] `UNIQUE (business_id, baileys_phone_e164)` mantido (anti-duplicate por número Baileys)
- [ ] Migration cria `whatsapp_phone_user_access` — ACL atendente↔número (Q1 + Q5)
- [ ] Migration adiciona `whatsapp_business_phone_id` em `whatsapp_conversations` + `whatsapp_messages` (FK + index)
- [ ] Migration de dados: cada row em `whatsapp_business_configs` vira 1 row em `whatsapp_business_phones` com `label='Comercial'` + `handles_outbound_default=true`. Conversations/messages existentes apontam pro novo phone_id (Q6)
- [ ] Tabela `whatsapp_business_configs` marcada `@deprecated` em docblock; **drop só em PR 5** depois de canary 30d
- [ ] Models: `WhatsappBusinessPhone` (com `HasBusinessScope` trait — Tier 0), `WhatsappPhoneUserAccess`
- [ ] Pest: `MultiTenantIsolationTest` adaptado — phone de biz=4 não aparece em query de biz=7
- [ ] Pest: `MigrationDataTest` — fixture com 3 businesses (cada com 1 config legacy + N conversations) → após `php artisan migrate`, todas conversations apontam pro novo phone com `label='Comercial'`

**DoD (PR 2 — driver factory + send job + listeners):**

- [ ] `DriverFactory::make(WhatsappBusinessPhone $phone)` (era `make(WhatsappBusinessConfig $config)`) — resolve driver via `$phone->driver` + health check
- [ ] `SendWhatsappMessageJob` constructor ganha `int $whatsappBusinessPhoneId` obrigatório (depois de `$businessId`); resolve `WhatsappBusinessPhone::where('business_id', $businessId)->where('id', $phoneId)->firstOrFail()` — defensive multi-tenant
- [ ] `NotifyRepairCustomer` (US-WA-004) resolve phone via `where('handles_repair_status', true)` com fallback `handles_outbound_default`. Falha silenciosa + log info se 0 phones; warning estruturado se >1
- [ ] Listener Billing idem (`handles_billing`)
- [ ] `DispatchToJanaBot` (US-WA-020) idem (`handles_jana_bot`)
- [ ] Pest: `EventRoutingTest` cobrindo (a) `handles_repair_status=true` em phone único = job dispara nele, (b) flag false em todos = log + no-op, (c) flag true em 2 = primeiro id ASC + warning, (d) só `handles_outbound_default` = fallback
- [ ] Pest: `SendWhatsappMessageJobTest` adaptado — assertion que `$job->phoneId` corresponde ao `business_id` correto (Tier 0)

**DoD (PR 3 — Settings UI v2 + Charter v2):**

- [ ] `Charter v2` — Non-Goal "Múltiplas instances Baileys por business — 1 número = 1 sessão" REMOVIDO. Mission atualizada: "conectar N números, 1 driver per número, escopo de atendimento próprio"
- [ ] `resources/js/Pages/Whatsapp/Settings/Index.tsx` (NOVO) — lista de números cadastrados com coluna Label + Driver + Status + Atendentes count + Eventos (badges Repair/Billing/Jana). Botão `+ Adicionar número`
- [ ] `resources/js/Pages/Whatsapp/Settings/Edit.tsx` (NOVO) — form per-phone: input label texto livre + radio driver (Z-API/Meta Cloud/Baileys) + credenciais (mesmo wizard atual) + checkboxes `handles_*` + multi-select atendentes (Spatie users com `whatsapp.send` permission filtrados por business)
- [ ] Estado reativo Centrifugo per-phone: channel `whatsapp:business:{biz}:phone:{phone_uuid}` (granular)
- [ ] Sub-componente `<EventRoutingSection>` com warning UI inline: "⚠️ Repair também está marcado em Financeiro — só este número vai disparar (id menor)"
- [ ] Permissão Spatie nova: `whatsapp.phones.manage` (cadastra/edita/desativa números) — separada de `whatsapp.send` (atendente que só envia mensagens)
- [ ] `WhatsappSettingsCharterTest` invariantes adicionadas: `it_lists_only_phones_of_current_business()`, `it_persists_handles_flags()`, `it_warns_on_overlap_repair_routing()`, `it_acl_filters_attendant_dropdown()`
- [ ] `mwart-comparative` visual artifact gerado em `memory/requisitos/Whatsapp/Settings-Index-visual-comparison.md` + `Settings-Edit-visual-comparison.md` (skill Tier A)

**DoD (PR 4 — Inbox UI ACL + filtro):**

- [ ] `ConversationsController@index` aplica filtro automático: `whereIn('whatsapp_business_phone_id', $userAccessPhoneIds)` exceto se user tem Gate `whatsapp.view-all-phones` (default só `Admin#{biz}`)
- [ ] `Conversations/Index.tsx` ganha tab/dropdown "Número: [Comercial ▾]" (filtro UI explícito) — só mostra opções dos números que o user tem acesso
- [ ] Cada `<ConversationCard>` mostra badge pequeno do label do número (canto superior direito) — ajuda contexto se atendente tem acesso a múltiplos
- [ ] Real-time Centrifugo: subscribe filtrado por `phone_uuid` (não recebe push de número que não tem acesso)
- [ ] Empty state quando user sem `whatsapp_phone_user_access`: "Você não tem acesso a nenhum número Whatsapp neste business. Peça pro admin." + link `/whatsapp/settings`
- [ ] Pest `InboxAclTest`: (a) atendente com acesso só Comercial não vê msg do Financeiro, (b) admin vê todos, (c) atendente sem acesso vê empty state, (d) tentar GET conversation_id de outro phone retorna 404 (defensive)
- [ ] Browser MCP smoke test obrigatório (`mwart-process` F4)

**Pré-requisitos / blockers:**

- ADR 0117 aprovada por Wagner (status `aceito`, `accepted_at` preenchido)
- Charter `Settings.charter.md` v2 aprovado (Non-Goal removido — Wagner aprova explicitamente Non-Goals + Anti-hooks per skill `charter-write`)

**Out of scope (vai em US separadas se acontecer):**

- US-WA-057 — "Mover conversa pra outro número" (admin reclassifica conversa antiga)
- US-WA-058 — Importar números em massa via CSV (50+ businesses migrando manualmente é ruim)
- US-WA-059 — Compartilhar número entre businesses (NÃO permitir; abrir nova ADR se algum cliente pedir)
- US-WA-060 — Spatie permissions parametrizadas per-phone (alternativa Q5-i, ainda rejeitada — reabrir só se Spatie ganhar suporte nativo a scope dinâmico)

> _IDs renumerados de 041-044 → 053-056 em 2026-05-10. Renumerados de 053-056 → 057-060 em 2026-05-11 (US-WA-053 reusada pra fix UX concreto CYCLE-05)._

## 8. Backlog futuro (não-Sprint 1-4)

- US-WA-030 — Botões interativos (`button` template) — HSM com CTAs
- US-WA-031 — List messages (cardápio gráfica: orçar, acompanhar OS, segunda via)
- US-WA-032 — Mídia outbound (imagem, PDF de boleto/NFe anexado)
- US-WA-033 — Mídia inbound (cliente manda foto do produto pra orçar)
- US-WA-034 — Suporte multi-driver (Twilio, Take Blip) se enterprise pedir
- US-WA-035 — White-label templates (módulo Officeimpresso revenue)
- US-WA-036 — Portal cliente self-service "minhas conversas Whatsapp"
- US-WA-037 — Integração Crm (lead vindo de Whatsapp vira lead no Crm)
- US-WA-038 — Pix Copia-e-Cola via Whatsapp (RecurringBilling US-RB-044 v2)

## 9. Backlog vindo do /comparativo (2026-05-10)

> Seção apendada em 2026-05-10 16:30 BRT pelo skill `comparativo-do-modulo` v2.0 ([ADR 0089](../../decisions/0089-capterra-driven-module-evolution.md)).
> Inventário: [CAPTERRA-INVENTARIO.md](CAPTERRA-INVENTARIO.md) — score 78% top mercado.
> Aprovação Wagner: "aprovo todos" (2026-05-10).
> 12 US criadas via tool MCP `tasks-create` (4 P1 ROI direto + 4 P2 diferenciais + 2 P3 futuro + 2 P0 governança).

### US-WA-041 · Métricas conversation (custo/deflection/tempo) — acelerar US-WA-021

> owner: wagner · priority: p1 · status: todo · type: story

Acelerar US-WA-021 (atualmente [todo]). Gap detectado pelo /comparativo em 2026-05-10 — schema `whatsapp_conversation_metricas` declarado em SPEC §6 mas migration não criada; service `WhatsappMetricasService` não existe. ROI visibility pra Wagner justificar custo Whatsapp por business.

**Evidência:** SPEC §6 + ausência de migration em `Modules/Whatsapp/Database/Migrations/` + ausência de service em `Modules/Whatsapp/Services/`.

**DoD herdado de US-WA-021:**
- Migration `whatsapp_conversation_metricas` (business_id, dia, total_conversas, custo_centavos, p50/p95 segundos, deflection_pct)
- `WhatsappMetricasService::aggregate()` job 04:00 BRT
- Dashboard tab UI ou módulo próprio
- OTel `whatsapp.*` metrics paralelo (ADR 0051)
- Pest `MetricasAggregationTest`

### US-WA-042 · Mídia outbound — anexar imagem/PDF na conversa (UI upload)

> owner: wagner · priority: p1 · status: todo · type: story

Gap detectado pelo /comparativo em 2026-05-10 — `DriverInterface::sendMedia()` existe (`Modules/Whatsapp/Services/Drivers/DriverInterface.php`) mas UI sem upload em `Conversations/Show.tsx`. Bloqueador pra US-RB-044 v2 (boleto auto-anexo).

**DoD:**
- Botão clip 📎 no input freeform → file picker (image/pdf/audio, max 16MB Meta)
- Upload pra storage Hostinger (S3 backend) → URL pública
- Driver `sendMedia(config, to, mediaUrl, type, caption)` chamado
- Preview no chat (thumbnail img / ícone PDF)
- Pest `SendMediaJobTest` + smoke browser MCP

**Evidência baseline:** capacidade C-103 P1 do CAPTERRA-INVENTARIO.md (PARCIAL).

### US-WA-043 · Mídia inbound — processar foto/PDF/audio recebido do cliente

> owner: wagner · priority: p1 · status: todo · type: story

Gap detectado pelo /comparativo em 2026-05-10 — webhook recebe payload `image/document/audio/sticker` mas `ProcessIncomingWebhookJob.php` descarta hoje (só processa `text`). Backlog US-WA-033.

**DoD:**
- `ProcessIncomingWebhookJob` baixa media via `Driver::downloadMedia()` (novo método)
- Salva em `whatsapp_messages.media_path` + `media_type` + `media_size_bytes`
- Storage S3/Hostinger com path `whatsapp/business_{id}/inbound/{wamid}.{ext}`
- UI `Conversations/Show.tsx` renderiza thumbnail/ícone clicável
- Pest `ProcessIncomingMediaTest` cobrindo image/pdf/audio
- Idempotência via `meta_message_id` UNIQUE (já existe)

**Evidência baseline:** capacidade C-104 P1 do CAPTERRA-INVENTARIO.md (AUSENTE).

### US-WA-044 · Permissions UI per-phone (multi-select atendentes em Settings/Edit)

> owner: wagner · priority: p1 · status: todo · type: story

Gap detectado por Wagner em prod 2026-05-10 ao olhar `/whatsapp/settings` em https://oimpresso.com — schema `whatsapp_phone_user_access` migrated PR1 (US-WA-040) mas SEM UI dedicada. Wagner não consegue dar acesso só ao "número Comercial" pra Felipe e só "número Financeiro" pra Eliana sem mexer no DB direto.

**DoD:**
- `Settings/Edit.tsx` (já planejada em US-WA-040 PR3) ganha multi-select atendentes (Spatie users com `whatsapp.send` filtrados por business)
- Persistência em `whatsapp_phone_user_access` (insert/delete diff)
- Inbox UI aplica filtro automático: atendente sem acesso a `phone_uuid` não vê conversa nem recebe push Centrifugo
- Permissão Spatie nova: `whatsapp.phones.manage` (separada de `whatsapp.send`)
- Pest `InboxAclTest` + `WhatsappSettingsCharterTest` invariante `it_acl_filters_attendant_dropdown`

**Evidência baseline:** Gap G-1 do CAPTERRA-INVENTARIO.md (governança interna). Pode ser absorvida em US-WA-040 PR3+PR4 ou virar US separada — Wagner decide.

### US-WA-045 · Botões interativos (HSM com CTAs)

> owner: wagner · priority: p2 · status: todo · type: story

Backlog SPEC §8 (era US-WA-030, agora ativada via /comparativo 2026-05-10).

**DoD:**
- Suporte a `interactive.button` no Meta Cloud API
- Templates HSM com `components: [{ type: 'BUTTONS', buttons: [...] }]`
- UI Templates picker mostra botões disponíveis
- Webhook `messages.button.payload` cria `WhatsappMessage` com `button_payload`
- Pest cobertura

**Evidência baseline:** capacidade C-201 P2 do CAPTERRA-INVENTARIO.md (AUSENTE). Diferencial competitivo Take Blip / Wati.

### US-WA-046 · List messages (cardápio: orçar / acompanhar OS / segunda via)

> owner: wagner · priority: p2 · status: todo · type: story

Backlog SPEC §8 (era US-WA-031, agora ativada via /comparativo 2026-05-10). Fit perfeito Modules/ComunicacaoVisual + Modules/Vestuario.

**DoD:**
- Suporte a `interactive.list` no Meta Cloud API + Z-API/Baileys (mapping pra freeform texto se driver não suporta nativo)
- Listener Repair pode mandar list "🛠 Acompanhar OS / 📄 Segunda via NFe / 💬 Falar com humano"
- Webhook `messages.list_reply.id` mapeia pra `WhatsappMessage`
- Linkar list-reply a evento Repair/Billing (decide skill `ads-decision-flow`)

**Evidência baseline:** capacidade C-202 P2 do CAPTERRA-INVENTARIO.md (AUSENTE).

### US-WA-047 · Tags / labels em conversa (classificar por dept/etapa)

> owner: wagner · priority: p2 · status: todo · type: story

Gap detectado pelo /comparativo em 2026-05-10 — sem schema, sem UI, sem código.

**DoD:**
- Migration `whatsapp_conversation_tags` (id, business_id, name, color)
- Pivot `whatsapp_conversation_tag_pivot` (conversation_id, tag_id)
- UI `ConversationSidebar.tsx` ganha tags com chips coloridos + dropdown "Adicionar tag"
- Filtro Inbox por tag
- Pest cobertura multi-tenant (tag de biz=4 não aparece em biz=7)

**Evidência baseline:** capacidade C-106 P1 do CAPTERRA-INVENTARIO.md (AUSENTE). Pattern Take Blip / Wati / Zenvia.

### US-WA-048 · Quick replies / atalhos atendente (respostas pré-definidas)

> owner: wagner · priority: p2 · status: todo · type: story

Gap detectado pelo /comparativo em 2026-05-10 — atendente digita repetidamente "Olá! Em que posso ajudar?", "Ok, recebido!", etc. Pattern Take Blip/Wati: atalhos `/saudacao`, `/aguardar`.

**DoD:**
- Migration `whatsapp_quick_replies` (id, business_id, shortcut, body, created_by)
- UI `Conversations/Show.tsx` autocomplete `/atalho` no textarea
- Tab "Atalhos" em `/whatsapp/settings` pra cadastrar
- Multi-tenant scope per-business
- Pest cobertura

**Evidência baseline:** capacidade C-108 P1 do CAPTERRA-INVENTARIO.md (AUSENTE).

### US-WA-049 · A/B testing templates (variantes A/B com tracking deflection)

> owner: wagner · priority: p3 · status: todo · type: story
> blocked_by: US-WA-041

Backlog SPEC §8 (US-WA-NEW-AB-TEMPLATE, ativada via /comparativo 2026-05-10).

**DoD:**
- Schema `whatsapp_template_variants` (template_id, label, body, weight)
- Job `SendWhatsappMessageJob` random-weighted sorteia variante
- Métricas em `whatsapp_conversation_metricas` quebradas por `variant_id`
- Dashboard tab "A/B" com winner highlight

**Evidência baseline:** capacidade C-209 P2 do CAPTERRA-INVENTARIO.md (AUSENTE). Diferencial enterprise Take Blip / Wati.

### US-WA-050 · Voice transcription inbound (whisper.cpp local CT 100)

> owner: wagner · priority: p3 · status: todo · type: story
> blocked_by: US-WA-043

Backlog SPEC §8 (US-WA-NEW-WHISPER, ativada via /comparativo 2026-05-10).

**DoD:**
- Container Docker compose-managed `whisper-stt` em CT 100 (skill `proxmox-docker-host` + ADR 0058)
- whisper.cpp small/medium model BR Portuguese
- `ProcessIncomingMediaJob` (US-WA-043) detecta `media_type=audio` → enfileira `TranscribeAudioJob` → grava em `whatsapp_messages.transcription`
- UI `Conversations/Show.tsx` renderiza áudio + texto transcrito
- Custo zero (local, sem API externa)
- Pest cobertura mock + 1 smoke real CT 100

**Evidência baseline:** capacidade C-304 P3 do CAPTERRA-INVENTARIO.md (AUSENTE). Diferencial vs BSPs (nenhum oferece nativo).

### US-WA-051 · FICHA v2 — Wagner-curate ux_heuristics + automation_targets

> owner: wagner · priority: p0 · status: todo · type: story

Gap governança G-2 detectado pelo /comparativo em 2026-05-10. CAPTERRA-FICHA.md L196-221 tem `ux_heuristics: []` + `automation_targets: []` (TODO desde 2026-05-07 quando v2 da skill foi extendida — ADR 0101). Próximos `/comparativo Whatsapp` vão pular esses 2 eixos com nota "TODO" até curar.

**DoD (~30min Wagner):**
- Pesquisar 3-5 heurísticas P0 UX no mercado: ex "cliques pra responder ao primeiro inbound" (Take Blip benchmark? Wati?), "tempo médio pra renderizar Inbox com 100 conversas", "recuperação de erro quando QR expira"
- Pesquisar 3-5 automações P0 no mercado: ex "auto-disparar template `repair_status_ready` quando OS muda" (já temos), "auto-fallback driver quando degraded" (já temos), "auto-tagging por keyword inbound" (não temos)
- Apender em `memory/requisitos/Whatsapp/CAPTERRA-FICHA.md` seções v2 com formato YAML declarado nos comentários

**Evidência baseline:** Gap G-2 do CAPTERRA-INVENTARIO.md (governança interna).

### US-WA-052 · AUDIT-LOG.md shell + 1ª entrada audit 2026-05-10

> owner: wagner · priority: p0 · status: todo · type: story

Pré-requisito da skill `module-completeness-audit` Tier B (criada 2026-05-10). Gap governança G-4 detectado pelo /comparativo em 2026-05-10.

**DoD (~5min):**
- Criar `memory/requisitos/Whatsapp/AUDIT-LOG.md` shell com cabeçalho
- Apender entrada `## 2026-05-10 16:00 — Whatsapp — full module audit (via /comparativo)`:
  - Checks rodados: 14 ✅ / 2 🟡 / 8 ❌ in-scope
  - Decisão Wagner: aprovou criar 12 tasks (4 P1 + 4 P2 + 2 P3 + 2 P0)
  - Tasks criadas: US-WA-041..052
  - Próxima ação: aguarda CYCLE-04 alocar P0+P1
- Format declarado na SKILL.md `module-completeness-audit` §7

### US-WA-053 · UX /whatsapp/conversations — composer no rodapé + sidebar colapsável + responsivo monitor pequeno

> owner: wagner · sprint: CYCLE-05 · priority: p1 · estimate: 1h · status: todo · type: story
> blocked_by: —

**Problemas reportados Wagner 2026-05-11:**

1. Composer (input mensagem) flutua no meio da tela em vez do rodapé
2. Sidebar direita (Ações/Janela 24h/Detalhes) não minimiza — toma 288-320px fixos em ≥lg
3. Monitor pequeno (1024-1366px) fica apertado

**Causa raiz:**

1. `Index.tsx:117` usa `h-[calc(100vh-7rem)]` mas pai `.main-body` (cockpit.css) é flex column com overflow-y:auto → quando sobra altura, composer não vai pro fundo
2. `Index.tsx:175-183` renderiza ConversationSidebar com `hidden lg:block` sem toggle pra colapsar
3. Em 1024-1280px lista 384px + sidebar 320px = 704px fixo, sobra pouco

**Fix:**

- `Index.tsx`: altura raiz `flex-1 min-h-0` (em vez de calc); state `sidebarCollapsed` persistido em `LS.SIDEBAR_COLLAPSED`; faixa estreita 32px com chevron quando colapsado
- `ConversationSidebar.tsx`: prop `onCollapse?: () => void`; botão minimizar no header da aside
- `helpers.ts`: adicionar `LS.SIDEBAR_COLLAPSED`

Frontend-only, sem mudança backend/migration. ROTA LIVRE não pega regressão.

---

### US-WA-058 · Inbox omnichannel — envio outbound via Channel (shim Phone, drivers intactos)

> owner: wagner · sprint: CYCLE-05 · priority: p1 · estimate: 3h · status: todo · type: story
> blocked_by: —

**Contexto:** Inbox novo `/atendimento/inbox` (ADR 0135 Fase 0) é GET-only. Composer no UI precisa rota POST que envie via `Channel` polimórfico sem quebrar Z-API/Meta legacy em prod.

**Escopo (shim minimal — refactor drivers profundo fica DORMENTE):**

- Rota `POST /atendimento/inbox/{conversation}/send` permission `whatsapp.send`
- `InboxController::send(SendChannelMessageRequest, Conversation)` — multi-tenant via global scope
- FormRequest `SendChannelMessageRequest` (kind=freeform|template|media, regra janela 24h Meta)
- Job novo `SendChannelMessageJob` (constructor: businessId, channelId, conversationId, to, kind, payload) — append-only cria `Message` status=queued, dispara `ChannelDriverFactory::resolve($channel)`, adapter Channel→Phone shim em memória se driver ainda consome `WhatsappBusinessPhone`
- Frontend: `Atendimento/Inbox/Index.tsx` composer rodapé wire pra rota nova
- Pest: send via Channel baileys cria Message queued + multi-tenant isolation

**Não escopo:**

- Refactor `DriverInterface::send(Channel,...)` (PR-B separado depois)
- Mídia upload (US-WA-042 separada)

**ADRs:** 0135, 0093, 0096

**Tier 0:** business_id no Job constructor; Pest biz=1.

---

### US-WA-059 · Inbox omnichannel — real-time via Centrifugo (novo schema)

> owner: wagner · sprint: CYCLE-05 · priority: p1 · estimate: 1h · status: todo · type: story
> blocked_by: —

**Contexto:** Inbox novo `/atendimento/inbox` não tem real-time. Schema antigo já tem `PublishMessageReceivedToCentrifugo` listener em `whatsapp:business:{id}`. Replicar pro novo schema sem afetar o antigo.

**Escopo:**

- Eventos novos: `OmnichannelMessageReceived(Message $m)` + `OmnichannelMessageSent(Message $m)`
- Listener `PublishOmnichannelToCentrifugo` — publica em canal `omnichannel:business:{id}` (reusa `CentrifugoPublisher`)
- `MessageObserver` dispatches eventos no `created`/`updated:status` do `Message` (novo schema)
- `InboxController::index` injeta `centrifugoConfig` (wsUrl + token + channel) no payload
- Frontend: `Atendimento/Inbox/Index.tsx` subscribe channel + append message na thread em tempo real (insert + autoscroll)
- Pest: evento dispara listener; CentrifugoPublisher chamado com payload correto; multi-tenant (canal não vaza entre businesses)

**Não escopo:**

- Refactor schema antigo (continua intacto)
- Typing indicators / read receipts

**ADRs:** 0135, 0058

**Tier 0:** canal por business_id; token TTL 1h.

---

### US-WA-060 · Sync daemon-node source do CT 100 pra Modules/Whatsapp/daemon-node/

> owner: wagner · sprint: CYCLE-05 · priority: p2 · estimate: 0.5h · status: todo · type: story
> blocked_by: —

**Contexto:** Daemon Baileys roda em CT 100 (FrankenPHP host, mas daemon é Node.js separado em `/opt/baileys-daemon/` ou similar). Patches feitos remotamente (US-WA-064 fix @lid + push_name) vivem só no servidor — repo local não tem source.

**Escopo:**

- Trazer source Node do CT 100 pra `Modules/Whatsapp/daemon-node/` (gitignored hoje? confirmar)
- Estrutura: `package.json`, `src/server.ts` ou `src/index.js`, `README.md` com path canônico CT 100 + procedimento update (link pra skill `baileys-update-procedure`)
- Confirma versão Baileys atual (6.7.18) e dependencies
- Smoke: `npm install` local funciona; daemon não precisa rodar local (só doc)
- Não-commit: `.env`, creds, session storage

**Não escopo:**

- Empacotar daemon em container Docker
- CI workflow pra daemon

**Decisão pendente nesta US:** daemon-node fica COMMITTED no repo principal ou em repo separado (sub-tree, submodule)?

**Tier 0:** nenhum cred/PII no source committed.

---

### US-WA-061 · Drift webhook legacy Z-API/Meta — observability + cutover plan

> owner: wagner · sprint: CYCLE-05 · priority: p3 · estimate: 1h · status: todo · type: story
> blocked_by: —

**Contexto:** Webhooks legacy `ZapiWebhookController`/`MetaWebhookController` continuam escrevendo em `whatsapp_conversations/messages` (schema antigo). Precisamos detectar quem ainda manda nesses endpoints pra planejar cutover.

**Escopo (paralelo, baixa urgência — observability only, sem mudar comportamento):**

- Adiciona log warning estruturado em cada legacy webhook controller: `Log::warning('webhook_legacy_hit', ['driver' => 'zapi|meta', 'business_id' => ..., 'phone_id' => ...])`
- Métrica counter `whatsapp_legacy_webhook_hits_total` (labels: driver, business_id) — se Telescope ou DB-counter (Hostinger não tem Prometheus exposed; usar tabela `mcp_metrics` ou similar canônica)
- ADR-mini sob `memory/decisions/` ou append em ADR 0135 sobre plano cutover (ativa-se quando hits/24h < threshold por 7d consecutivos)

**Não escopo:**

- Migrar webhooks legacy pra novo schema (Fase 2 do ADR 0135)
- Bloquear endpoint legacy

**ADRs:** 0135 (Fase 0→1 cutover criteria)

**Evidência baseline:** Gap G-4 do CAPTERRA-INVENTARIO.md.

---

### US-WA-067 · Limpar tela Configurações WhatsApp — apagar 7 blocos de driver/LGPD

> owner: wagner · sprint: CYCLE-05 · priority: p1 · estimate: 3h · status: done · type: story
> blocked_by: —

Tela `/whatsapp/settings` ([Whatsapp/Settings.tsx](../../../resources/js/Pages/Whatsapp/Settings.tsx)) está defasada após criação do módulo Canais (ADR 0135). Drivers viraram polimórficos via `Channel.config_json`.

**Apagar:**

- Status do driver (linhas 213-243)
- Aviso risco LGPD (linhas 257-268)
- Passo 1 — Driver primário seletor (linhas 271-302)
- Z-API credenciais (linhas 305-337)
- Baileys telefone + QR panel (linhas 340-380)
- Passo 2 — Meta Cloud (linhas 383-419)
- Termo LGPD (linhas 422-453)

**Manter:**

- Templates + Bot Jana (linhas 456-485) — mas migrar pra `/atendimento/canais/jana-templates` na US-WA-070

**Acceptance:**

- `Settings.tsx` ~150 linhas a menos
- Controller `SettingsController::show()` para de passar props de driver
- Smoke biz=1: tela abre sem erro, sem blocos órfãos
- Pest atualizado se houver assertion nesses blocos

**ADRs:** 0135 Omnichannel

---

### US-WA-068 · Tab "Usuários do canal" dentro de Canais (ACL per-canal visível)

> owner: wagner · sprint: CYCLE-05 · priority: p1 · estimate: 8h · status: done · type: story
> blocked_by: US-WA-067

Detalhe do canal em `/atendimento/canais/{id}` ganha tabs: `Config | Usuários | Histórico`.

**Tab Usuários:**

- Lista usuários com acesso (join `whatsapp_phone_user_access` com `users`)
- Add user: seletor + grant per-channel
- Remove user: soft remove (decisão durante implementação)
- Mostra role atual (superadmin bypassa via gate `whatsapp.view-all-phones`)

**Backend:**

- `ChannelsController::users($channel)` retorna lista
- `ChannelsController::grantUser($channel, $user)` + `revokeUser`
- Reusa permissão `whatsapp.settings.manage` por enquanto

**UI:**

- Componente `ChannelUsersTab.tsx` em `Modules/Whatsapp/resources/js/Pages/Atendimento/Channels/_components/`
- Tabela com `user_name`, `granted_at`, `granted_by`, ação remover

**Acceptance:**

- Smoke biz=1: criar canal, adicionar 2 users, remover 1, listar
- Pest cross-tenant biz=99: user de outro business NÃO aparece nem pode ser added
- AuditLog write em grant/revoke

**ADRs:** 0135, tabela `whatsapp_phone_user_access` (migração 2026_05_09_120100)

---

### US-WA-069 · Validar canal=fila — Suporte não vê inbox do Financeiro

> owner: wagner · sprint: CYCLE-05 · priority: p0 · estimate: 4h · status: done · type: story
> blocked_by: US-WA-068

Modelo confirmado (2026-05-12 Wagner): **Canal = Fila**. ACL per-canal via `whatsapp_phone_user_access` já existe. Esta US é só **validar** que o filtro funciona ponta-a-ponta.

**Smoke biz=1 (manual):**

1. Criar 2 canais: "Suporte" e "Financeiro"
2. Criar 2 users: `user_suporte` e `user_financeiro`
3. Grant `user_suporte` → só canal Suporte
4. Grant `user_financeiro` → só canal Financeiro
5. Login como `user_suporte` → inbox `/atendimento/inbox` mostra SÓ conversas do canal Suporte
6. Login como `user_financeiro` → idem

**Pest cross-tenant biz=99:**

- Cenário cross-canal dentro do mesmo business
- Cenário cross-business (biz=99 não vê canais de biz=1)

**Backend a inspecionar:**

- Query do `InboxController::index()` filtra por canais permitidos do user?
- Cobertura do gate `whatsapp.view-all-phones` (admin bypass)
- Pode precisar ajustar query se hoje filtra por `phone_id` ao invés de `channel_id`

**Acceptance:**

- Pest passa em isolamento
- Smoke manual documentado em comment da US
- Se descobrir bug de scope → vira US separada P0 (vazamento Tier 0)

**ADRs:** 0093 multi-tenant Tier 0, 0135 Omnichannel

---

### US-WA-070 · Sidebar/rotas — Canais vira entrada principal de Atendimento, Settings velha morre

> owner: wagner · sprint: CYCLE-05 · priority: p2 · estimate: 3h · status: done · type: story
> blocked_by: US-WA-067

Após limpeza da Settings velha (US-WA-067), reorganizar navegação.

**Sidebar (DataController do Whatsapp):**

- Remover item "Configurações WhatsApp" (rota `/whatsapp/settings`)
- "Canais" continua como item principal em `/atendimento/canais`
- Adicionar sub-item "Templates Jana" → `/atendimento/canais/jana-templates` (onde bloco Jana foi parar)

**Rotas:**

- `/whatsapp/settings` → 301 redirect pra `/atendimento/canais` (pra não quebrar bookmark)
- Nova rota `/atendimento/canais/jana-templates` renderiza bloco Templates (props: `bot_enabled` + 4 templates)

**Acceptance:**

- Sidebar testada em superadmin + user normal com `whatsapp.settings.manage`
- 301 redirect funciona
- Smoke biz=1 visual: clicar em todos os itens novos, nenhum 404

---

### US-WA-071 · Notas internas (private notes) MVP — toggle Reply/Note estilo Chatwoot

> owner: wagner · sprint: CYCLE-05 · priority: p1 · estimate: 6h · status: done · type: story
> blocked_by: —

Atendentes precisam de canal interno pra coordenar sobre uma conversa sem o cliente ver. Padrão Chatwoot: cada mensagem na timeline ou é "Reply" (vai pro WhatsApp) ou é "Private Note" (fica só no painel).

**Schema (nova coluna ou tabela?):**

Recomendo coluna nova em `whatsapp_messages` (ou tabela equivalente no novo schema Channels):

- `is_internal_note` boolean default false
- `author_user_id` unsignedInteger nullable (quem escreveu — null se for cliente/bot)
- `mentions_user_ids` json nullable (array de user_ids para `@mention`)

Migration idempotente, índice em `(conversation_id, is_internal_note)` pra filtros rápidos.

**Backend (Tier 0 multi-tenant):**

- `InboxController::storeMessage()` aceita flag `is_internal_note` no payload
- Dispatch driver SOMENTE quando `is_internal_note = false` (gate duro — nota interna NUNCA vaza pro WhatsApp)
- `@mention` dispara notificação Centrifugo no canal `user:{mentioned_user_id}` (badge na sidebar)
- AuditLog: nota interna registrada com author + conversation_id

**UI:**

- Toggle "Resposta | Nota interna" acima do campo de mensagem (estado persistido em localStorage por sessão)
- Nota interna renderiza com fundo amarelo claro + ícone cadeado + label "interno"
- `@` no input abre dropdown com users do business que têm `whatsapp.access` (ou `whatsapp.send`)
- Atalho `Ctrl+/` (ou `Cmd+/`) toggle Reply/Note rápido

**Acceptance:**

- Smoke biz=1: criar 2 atendentes, abrir conversa, atendente A escreve nota interna `@user_b lembrar disso` — atendente B recebe notificação Centrifugo, nota fica visível só pros 2 atendentes
- Pest cross-tenant biz=99: nota de biz=1 NUNCA aparece em queries de biz=99
- Pest: tentativa de dispatch driver com `is_internal_note=true` → falha com exception
- AuditLog write em criação

**Tier 0 IRREVOGÁVEL:** dispatch driver gateado por `is_internal_note=false` em **2 lugares** (Controller + Job) — defense-in-depth contra vazamento.

**ADRs:** 0093 multi-tenant Tier 0, 0135 Omnichannel

**Não escopo (vai em US separadas):**

- Slash commands `/lembrar`, `/corrigir`, `/lembrete`, `/config` (US-WA-074..077)
- Mídia em notas (US-WA-072)

**Decisão pendente:** schema usa `whatsapp_messages` legacy ou novo schema `omnichannel_messages` do Channels Fase 1? Resolver na implementação cruzando com ADR 0135.

---

### US-WA-072 · Mídia (imagens, áudio, docs) inbound + outbound + Whisper transcrição

> owner: wagner · sprint: CYCLE-05 · priority: p1 · estimate: 12h · status: done · type: story
> blocked_by: —

Inbox hoje só suporta texto. WhatsApp driver entrega image/audio/video/document/sticker via webhook — precisa schema + storage + UI + outbound + ASR pra áudio.

**Schema (`whatsapp_messages` ou `omnichannel_messages`):**

Adicionar:

- `media_url` varchar 500 nullable
- `media_mime` varchar 100 nullable
- `media_size_bytes` unsignedBigInteger nullable
- `media_duration_s` unsignedSmallInteger nullable (só áudio/video)
- `media_thumbnail_url` varchar 500 nullable
- `media_transcription` text nullable (Whisper output pra áudio)
- `media_filename` varchar 255 nullable (docs)

**Storage:**

- Path: `storage/app/public/whatsapp/{business_id}/{yyyy-mm}/{message_uuid}.{ext}`
- URL assinada 24h via `Storage::temporaryUrl()` (driver `s3` ou `local`)
- Antivirus scan opcional (ClamAV) — adiar pra US separada

**Inbound (webhook):**

- `WebhookController` detecta tipo (`image|audio|video|document|sticker`) — driver-específico (Z-API tem url direto, Meta exige fetch via media-id, Baileys envia base64)
- Job assíncrono `DownloadMediaJob` salva no storage, gera thumbnail (imagem), atualiza row
- Pra áudio: dispara `TranscribeAudioJob` com OpenAI API (`gpt-4o-mini-transcribe` ou `whisper-1`), grava `media_transcription`

**Outbound:**

- UI: botão `📎` abre `<input type=file>` ou drag-drop, valida MIME (whitelist) + size (max 16MB WhatsApp Cloud, 100MB Baileys)
- `SendMediaJob` faz upload pro driver correto
- Loading spinner inline na mensagem até confirmar entrega

**UI inbox:**

- Imagem: thumbnail clicável, modal fullscreen
- Áudio: `<audio controls>` HTML5 + texto transcrito abaixo em itálico (cliente vê só áudio; Jana lê texto)
- Documento: ícone tipo MIME + filename + botão download
- Sticker: render PNG direto

**Whisper integração:**

- Service `Modules\Whatsapp\Services\Audio\WhisperTranscriber` com fallback (OpenAI primário; futuro Ollama whisper-local secundário)
- Config `whatsapp.audio.transcription.provider` (default `openai`)
- Custo metering: log custo per minuto em `mcp_usage_costs` (tag: whatsapp_audio)
- Rate limit 100min/business/dia (anti-abuse)

**Acceptance:**

- Smoke biz=1: enviar imagem → cliente recebe; enviar áudio → cliente recebe; receber áudio → transcrição aparece em ≤ 10s
- Pest: `DownloadMediaJob` testado com fake HTTP; `TranscribeAudioJob` testado com OpenAI mock
- Custo per business/mês mostrado no Daily Brief (alerta se > R$ [redacted Tier 0])

**Tier 0:** validar `mime` whitelist no upload (evitar XSS via SVG upload); URL assinada SEMPRE (nunca pública); scope multi-tenant nas queries de media.

**ADRs:** 0093 multi-tenant Tier 0, 0135 Omnichannel

**Decisões pendentes:**

1. Provider Whisper: OpenAI ($0.003/min) ou Ollama whisper-local (CT 100 self-host, latência maior)? Default OpenAI.
2. Storage: `storage/app/public` Hostinger ou S3 desde já? Default Hostinger local até > 10GB.

---

### US-WA-073 · ADR — Notas internas como sinal de treino pra Jana (design 4 slash commands)

> owner: wagner · sprint: CYCLE-05 · priority: p1 · estimate: 2h · status: done · type: story
> blocked_by: US-WA-071

Antes de implementar slash commands (US-WA-074..077), precisa ADR com schema + parser + integração com `copiloto_memoria_facts` (RAG hybrid ADR 0052).

**Escopo do ADR:**

1. **Schema novas tabelas:**
   - `whatsapp_jana_correcoes` (id, business_id, message_id_errada, conversation_id, correcao_texto, atendente_user_id, training_status, created_at)
   - `whatsapp_reminders` (id, business_id, conversation_id, contact_id, atendente_user_id, due_at, body, status `pending|done|cancelled`, created_at)
   - `whatsapp_contact_bot_overrides` (id, business_id, contact_id, bot_enabled boolean, set_by_user_id, set_at) — override per-contact do `bot_enabled` global

2. **Parser slash commands:**
   - Onde roda: `Modules\Whatsapp\Services\Notes\SlashCommandParser` invocado em `InboxController::storeMessage()` quando `is_internal_note=true`
   - 4 comandos suportados: `/lembrar`, `/corrigir`, `/lembrete`, `/config`
   - Sintaxe formal (regex + grammar)
   - Tratamento de erro (comando inválido vira nota normal + warning UI)

3. **Integração `copiloto_memoria_facts`:**
   - `/lembrar` cria fato com `scope='contact:{contact_id}'`, `fact_type='preference'`, `source='human_note'`, `confidence=1.0`
   - Jana ContextSnapshotService inclui facts deste contato no recall (já existe ADR 0052, só validar)

4. **Training signal (`/corrigir`):**
   - Stub agora — registra correção mas não roda fine-tune
   - Plano fase 2: export dataset jsonl semanal pra OpenAI fine-tuning OU usar como few-shot examples em system prompt

5. **Reminder cron:**
   - Job hourly `ProcessRemindersJob` busca `whatsapp_reminders.due_at <= now()` AND `status=pending`
   - Notifica atendente_user_id via Centrifugo + email (se config)
   - Marca como `done` quando atendente clica "OK"

**Acceptance:**

- ADR escrita em `memory/decisions/NNNN-notas-internas-sinal-treino-jana.md`
- Aprovação Wagner explícita (status: accepted)
- 4 US US-WA-074..077 unblocked após aprovação

**Refs:** ADR 0035 Stack IA, ADR 0052 Memória 3 ângulos, ADR 0135 Omnichannel

---

### US-WA-074 · Slash /lembrar — atendente grava fato sobre cliente em copiloto_memoria_facts

> owner: wagner · sprint: CYCLE-05 · priority: p2 · estimate: 4h · status: done · type: story
> blocked_by: US-WA-071, US-WA-073

Atendente escreve em nota interna `/lembrar prefere boleto, recusa cartão` → cria entry em `copiloto_memoria_facts` que Jana usa em recall futuro.

**Behavior:**

- Parser slash detecta `/lembrar <texto>` na nota interna
- Cria row em `copiloto_memoria_facts`:
  - `scope = 'contact:{contact_id}'`
  - `fact_type = 'preference'`
  - `fact_body = <texto>`
  - `source = 'human_note'`
  - `confidence = 1.0`
  - `source_user_id = <atendente>`
  - `source_conversation_id = <conv_id>`
- Embedding gerado via Ollama no CT 100 (já existe pipeline)
- Nota interna na timeline mostra badge "✓ memorizado" + link clicável pra ver o fato

**UI:**

- Sugestão autocomplete quando atendente digita `/` (lista comandos)
- Preview do fato antes de salvar (toggle "memorizar como fato | salvar como nota apenas")
- Editar/deletar fato linka pra `/copiloto/admin/memoria?fact_id={id}`

**Acceptance:**

- Smoke biz=1: 2 fatos diferentes pra mesmo contato → Jana recall puxa ambos quando aciona memoria_facts deste contato
- Pest: cross-tenant biz=99 não vê facts de biz=1
- Pest: `/lembrar` sem texto → falha graceful, mostra ajuda

**ADRs:** 0035, 0052, 0135, ADR slash commands (US-WA-073)

---

### US-WA-075 · Slash /corrigir — marca mensagem do bot como errada (training signal Jana)

> owner: wagner · sprint: CYCLE-05 · priority: p2 · estimate: 6h · status: done · type: story
> blocked_by: US-WA-071, US-WA-073

Atendente vê resposta errada da Jana, clica em "Corrigir" na mensagem do bot, escreve em nota interna `/corrigir Deveria ter dito que entrega é em 3 dias, não 7`. Grava em `whatsapp_jana_correcoes` pra fine-tune/few-shot futuro.

**Behavior:**

- Mensagem do bot tem botão "🛠 Corrigir" → abre input pré-preenchido com `/corrigir ` + ID da msg referenciada
- Parser slash detecta `/corrigir <expected_response>` + `replied_to_message_id` (set automaticamente pelo UI)
- Cria row em `whatsapp_jana_correcoes`:
  - `message_id_errada`, `conversation_id`, `contact_id`, `correcao_texto`, `atendente_user_id`
  - `training_status = 'pending_review'`
- Badge "⚠ corrigida" aparece na msg original do bot

**Dashboard de correções (link na sidebar admin Jana):**

- `/copiloto/admin/correcoes-jana` mostra todas correções
- Filtros: data, atendente, status
- Botão "Exportar JSONL" pra fine-tuning OpenAI
- Sprint atual: só observability + export manual. Fase 2: cron auto-tune.

**Acceptance:**

- Smoke biz=1: correção criada via UI → aparece no dashboard
- Pest: cross-tenant biz=99 não vê correções de biz=1
- AuditLog write na criação

**ADRs:** 0035 Stack IA, 0052 Memória, 0135, ADR slash commands (US-WA-073)

---

### US-WA-076 · Slash /lembrete — cria lembrete agendado pro atendente

> owner: wagner · sprint: CYCLE-05 · priority: p2 · estimate: 4h · status: done · type: story
> blocked_by: US-WA-071, US-WA-073

Atendente escreve em nota interna `/lembrete 2026-05-20 cobrar boleto vencendo` → cria row em `whatsapp_reminders` + cron horário processa e notifica atendente.

**Behavior:**

- Parser detecta `/lembrete <data> <body>`. Data aceita: `YYYY-MM-DD`, `amanhã`, `daqui 3 dias`, `próxima segunda` (chrono-php ou similar).
- Cria row em `whatsapp_reminders`:
  - `due_at`, `body`, `conversation_id`, `contact_id`, `atendente_user_id = quem escreveu`, `status='pending'`
- Cron `ProcessRemindersJob` hourly busca `due_at <= now()` AND `status=pending` → notifica via Centrifugo (popup no painel) + opcional email
- Botão "Concluir" na notificação → status = `done`

**UI:**

- Badge "⏰ lembrete" na nota interna após criar
- Lista `/atendimento/lembretes` (sidebar) com lembretes pendentes/concluídos do atendente
- Pode anexar lembrete a outro atendente: `/lembrete @maria 2026-05-20 ...`

**Acceptance:**

- Smoke biz=1: criar lembrete pra `+10 segundos`, esperar, ver notificação Centrifugo
- Pest: cross-tenant biz=99 não vê reminders de biz=1
- Pest: data inválida → falha graceful com sugestão

**ADRs:** 0135, ADR slash commands (US-WA-073)

---

### US-WA-077 · Slash /config bot=off — toggle Jana per-contact (override global)

> owner: wagner · sprint: CYCLE-05 · priority: p2 · estimate: 3h · status: done · type: story
> blocked_by: US-WA-071, US-WA-073

Cliente reclama que bot é chato. Atendente escreve em nota interna `/config bot=off` → bot Jana fica desligado SÓ pra esse contato. `/config bot=on` reativa.

**Behavior:**

- Parser detecta `/config <key>=<value>`. Por enquanto só `bot` (true/false/on/off).
- Cria/atualiza row em `whatsapp_contact_bot_overrides`:
  - `contact_id`, `business_id`, `bot_enabled`, `set_by_user_id`, `set_at`
- Engine de bot consulta override ANTES do `bot_enabled` global do business
- Badge persistente "🤖 bot desligado" no header da conversa quando override = off

**UI:**

- Confirmação inline antes de aplicar (anti-typo)
- Botão toggle direto no header da conversa também (sem precisar slash)

**Acceptance:**

- Smoke biz=1: `/config bot=off` → próxima mensagem do cliente NÃO dispara bot
- Smoke biz=1: `/config bot=on` reativa
- Pest: cross-tenant biz=99 não consulta override de biz=1
- Pest: engine de bot respeita override > global

**ADRs:** 0135, ADR slash commands (US-WA-073)

---

### US-WA-078 · Fix banDetector falso positivo loggedOut/forbidden vira 'session_expired'

> owner: — · priority: p1 · estimate: 2h · status: todo · type: story
> blocked_by: —

**Contexto:**

Daemon Baileys (`Modules/Whatsapp/daemon-node/src/baileys/banDetector.ts:30`) classifica `DisconnectReason.loggedOut` (401) e `DisconnectReason.forbidden` como `banned: true`. Na prática **401 + `conflict type:device_removed`** acontece quando o usuário simplesmente desloga o WhatsApp Web pelo celular — **não é ban Meta**.

Resultado: UI mostra canal como `banned` (alarmista, sugere número queimado), quando o correto é `session_expired` (basta novo QR).

Caso real 2026-05-13: Channel id=3 "Suporte" biz=1 (uuid `3bcafcfc-...`) ficou `status=banned` na tela. Número 554896486699 estava 100% OK no celular pessoal — falso positivo confirmado.

Skill `baileys-update-procedure` gotcha #2 já documenta o fix.

**Acceptance:**

- [ ] `banDetector.ts` linha 29-32: `loggedOut` e `forbidden` retornam `{ banned: false, reason: 'session_expired', shouldReconnect: false }`
- [ ] `multideviceMismatch` (linha 45-46) revisado: é ban real ou também session_expired? (provavelmente também falso positivo — investigar)
- [ ] Pest test em `Modules/Whatsapp/daemon-node/__tests__/baileys/banDetector.test.ts` cobrindo loggedOut/forbidden retornando `banned:false`
- [ ] Backend Laravel (`BaileysWebhookController` ou similar) lê `reason: 'session_expired'` e seta `channels.status='disconnected'` + `channel_health='degraded'` (NÃO `banned`)
- [ ] UI canal disconnected mostra botão "Re-parear (gerar QR)" — não alarme de ban
- [ ] Build daemon + deploy CT 100 + smoke test pairing OK
- [ ] Documentar no RUNBOOK do Whatsapp: "banned na UI = ban Meta real (raríssimo, número queimado); session_expired = deslogou via celular ou outro device, basta QR"

**Refs:**

- Skill: `.claude/skills/baileys-update-procedure/SKILL.md` (gotcha #2)
- Código: `Modules/Whatsapp/daemon-node/src/baileys/banDetector.ts:29-50`
- Caso real: turno Wagner-Claude 2026-05-13 16:49 UTC (reset manual channel id=3 banned→disconnected)

---

### US-WA-079 · Fix daemon SIGTERM revoga session (sock.logout → sock.end) preserva pareamento em restart

> owner: — · priority: p1 · estimate: 3h · status: todo · type: story
> blocked_by: —

**Contexto:**

`docker stop whatsapp-baileys` (ou qualquer SIGTERM) dispara cadeia:
`server.ts shutdown()` → `InstanceManager.shutdownAll()` → `Instance.disconnect()` → `sock.logout()` no Baileys.

**`sock.logout()` revoga a sessão no WhatsApp Web** (não é graceful pause — é logout permanente, igual desconectar pelo celular). Resultado: toda restart de daemon = todos os canais conectados perdem pareamento + `/srv/docker/whatsapp-baileys/sessions/` fica vazio + precisa QR de novo em N canais.

Isso transforma operação trivial (restart pra update/manutenção) em incidente — cliente vê WhatsApp offline + Wagner precisa scan QR de todos os canais ativos.

Caso real 2026-05-13: stop pedido pelo Wagner causou logout permanente nos 2 canais ativos (id=2 "Suorte" estava `active/healthy` e perdeu pareamento por causa do SIGTERM).

**Acceptance:**

- [ ] `Modules/Whatsapp/daemon-node/src/baileys/Instance.ts:217` (`disconnect()`): trocar `sock.logout()` por `sock.end()` (ou `sock.ws?.close()` — verificar API Baileys 6.7.18)
- [ ] Manter método `logout()` separado pra uso explícito (ex: admin desativa canal de propósito via UI → daí sim revoga)
- [ ] `InstanceManager.shutdownAll()` chama `disconnect()` (encerra socket, mantém auth) — não `logout()`
- [ ] Persistência: confirmar que `/app/sessions/{instance}/auth/` continua intacta pós-shutdown (não é apagada por logout)
- [ ] Boot do daemon detecta sessions existentes e auto-reconnect — ou backend dispara `BaileysConnectJob` no boot pra cada channel com `status=active`
- [ ] Smoke test: docker stop + docker start → channels permanecem conectados sem QR
- [ ] Pest test daemon-node cobrindo o cenário (mock SIGTERM, valida que auth file não é deletado)
- [ ] Documentar diferença `disconnect()` vs `logout()` no comment header do Instance.ts

**Refs:**

- Código: `Modules/Whatsapp/daemon-node/src/baileys/Instance.ts:217`, `InstanceManager.ts:70`, `src/server.ts:55`
- Baileys docs: https://baileys.wiki/docs/api/connecting (sock.end vs sock.logout)
- Caso real: turno Wagner-Claude 2026-05-13 16:43 UTC (logs `Intentional Logout` no SIGTERM)

---

## §10. Onda Arquitetura Técnica 2026-05-14 (pós-dogfood `whatsapp-arch-arte` nota 71/100)

> 9 US criadas via tool MCP `tasks-create` em 14/05/2026 madrugada.
> Detalhes completos em [memory/sessions/2026-05-14-arte-wa-structure.md](../../sessions/2026-05-14-arte-wa-structure.md).
> Consulta backlog: `tasks-list module:Whatsapp status:todo`.

### Onda 1 P0 (3-4 dias, sobe nota 71→80)

- **US-WA-080** Canary 24h `QUEUE_CONNECTION=database` Hostinger (2h) — **DEPLOYED 2026-05-14**, aguarda canary
- **US-WA-081** Dashboard Grafana 11 metrics Prometheus existentes (2h)
- **US-WA-082** Replay protection HMAC + nonce no webhook receiver (3h)

### Onda 2 P1 (1-2 semanas, sobe nota 80→88)

- **US-WA-083** Trace OTel ponta-a-ponta Hostinger↔CT 100 (6h)
- **US-WA-084** Backpressure formal: queue depth limit + drop policy (3h)
- **US-WA-085** Alerting Prometheus zombies + drift + bans cross-tenant (2h) — `blocked_by: US-WA-081`

### Onda 3 P3 — feature-wish ([ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md))

Congelado em backlog até sinal qualificado de dor de cliente:

- **US-WA-086** Horizontal scale daemon CT 100 sharding por business_id (80h) — ativar com >30 canais em prod
- **US-WA-087** Multi-region failover DR (160h) — ativar com cliente enterprise SLA 99.9%
- **US-WA-088** E2E encryption at rest creds Baileys MySQL (40h) — ativar com cliente B2B SOC2/ISO 27001

### Diferenciais oimpresso preservados (nota 9/10)

Documentados pelo dogfood pra não regredir em refactor futuro:

- `MessagePersister` keyed `(business_id, provider_message_id)` UNIQUE + `firstOrCreate` + `wasRecentlyCreated`-aware backdating ([linhas 170-218](../../../Modules/Whatsapp/Services/Webhook/MessagePersister.php))
- `antiBan.ts` Box-Muller Gaussian + circadian quiet hours + warmup quota progressive ([linhas 99-258](../../../Modules/Whatsapp/daemon-node/src/baileys/antiBan.ts))
- ADR 0093 multi-tenant IRREVOGÁVEL com global scope
- 11 métricas Prometheus dedicadas no daemon ([`metrics.ts`](../../../Modules/Whatsapp/daemon-node/src/observability/metrics.ts))

## §14.2 Cloud API canary scope — PR4 PoC (NÃO toca prod biz=1)

**Contexto:** estudo protocol-level WhatsApp 2026 ([memory/sessions/2026-05-15-estudo-whatsapp-protocol-vs-oimpresso.md](../../sessions/2026-05-15-estudo-whatsapp-protocol-vs-oimpresso.md) §7 Opção A) revelou que **BSUID Meta-oficial está LIVE em Cloud API webhooks desde 31-mar-2026** via campo `contacts[].user_id`. Quando users adotarem username (jun/2026 GA), `wa_id` (phone) some — só sobra BSUID como ID estável business-scoped.

Cloud API resolve LID → identidade internamente (sem necessidade do nosso `LidPhoneResolver` que existe pro daemon Baileys). Wagner aprovou **stub canary PoC** pra validar custo Meta + tempo HSM aprovação ANTES de decidir migração wide.

### Escopo PR4 (entregue 2026-05-15)

- ✅ `MetaCloudDriver::parseInboundWebhook(array $payload): array` — método novo no driver existente, extrai 3 identifiers canônicos do payload Meta (PR1 schema `lid`/`phone_e164`/`bsuid`)
- ✅ Fixture canon `Modules/Whatsapp/Tests/Fixtures/meta-cloud-inbound-with-bsuid.json` (payload mar/2026+ com `user_id`)
- ✅ 4 Pest tests `MetaCloudDriverStubTest` — todos com `Http::fake` (zero chamada Meta real)
- ✅ `.env.canary.example` documenta variáveis pra Wagner ativar sandbox manual

### Fora de escopo PR4 (próximas fases)

- ❌ Rota webhook `ChannelMetaWebhookController@cloudInbound` — PR5
- ❌ Integração `ChannelDriverFactory` pra escolher `meta_cloud` por canary flag — PR5+
- ❌ HSM templates aprovação Meta (1-3 dias cada) — fora deste sprint
- ❌ Migração `MessagePersister` pra usar `bsuid` como chave secundária — quando username GA jun/2026

### Como ativar canary (Wagner manual)

```bash
# 1. Copiar template
cp Modules/Whatsapp/.env.canary.example Modules/Whatsapp/.env.canary

# 2. Editar .env.canary com valores reais Meta Business Manager
#    (System User Token, phone_number_id, webhook verify token)

# 3. Validar smoke pelos tests Pest (NÃO chama Meta real)
php artisan test --filter=MetaCloudDriverStub

# 4. Wagner flipa META_CANARY_ENABLED=true APENAS após sign-off
#    business_id=99 sandbox (NUNCA biz=1 ROTA LIVRE)
```

### Tier 0 — restrições canary

- ⛔ **business_id=99 sandbox** — NUNCA tocar biz=1 prod (99% volume ROTA LIVRE)
- ⛔ **Token Meta NUNCA em commit/log/PR** — apenas `.env.canary` local (gitignored)
- ⛔ **Rate limit 50 msg/dia** evita ban Meta por abuso de teste
- ⛔ **Driver `meta_cloud` NÃO bane** (provedor oficial), mas conta abusada perde acesso developer
- ⛔ **Decisão migração wide** depende de ADR mãe nova com dados reais (custo R$/msg + tempo HSM aprovação)

### Áreas cinzentas pra Wagner decidir

1. **BSP intermediário (360dialog/Twilio) vs Meta direto?** Meta direto sem markup BSP é o assumed em `MetaCloudDriver`, mas Twilio facilita certificação inicial. Decisão pré-ativação canary.
2. **Quando username GA jun/2026 chegar**, `phone_e164` pode virar opcional em alguns webhooks. `MessagePersister` precisa estratégia fallback explícita — backlog ADR.
3. **Webhook signature verification Meta** (HMAC SHA-256 com app secret) — não implementado neste PR. Requirement Meta antes de ativar webhook em prod.

