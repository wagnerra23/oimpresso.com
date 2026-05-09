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

> owner: wagner · sprint: 1 · priority: p2 · status: review

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

> owner: wagner · sprint: 1 · priority: p2 · status: review

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

> owner: wagner · sprint: 1 · priority: p2 · status: review

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

> owner: wagner · sprint: 2 · priority: p2 · status: review

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

> owner: wagner · sprint: 2 · priority: p2 · status: review

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

> owner: wagner · sprint: 2 · priority: p2 · status: review

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

> owner: wagner · sprint: 2 · priority: p2 · status: review

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

> owner: wagner · sprint: 3 · priority: p2 · status: review

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

> owner: wagner · sprint: 3 · priority: p2 · status: review

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

- US-WA-041 — "Mover conversa pra outro número" (admin reclassifica conversa antiga)
- US-WA-042 — Importar números em massa via CSV (50+ businesses migrando manualmente é ruim)
- US-WA-043 — Compartilhar número entre businesses (NÃO permitir; abrir nova ADR se algum cliente pedir)
- US-WA-044 — Spatie permissions parametrizadas per-phone (alternativa Q5-i, ainda rejeitada — reabrir só se Spatie ganhar suporte nativo a scope dinâmico)

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
