# Especificação funcional — Whatsapp

> Convenção do ID: `US-WA-NNN` para user stories, `R-WA-NNN` para regras Gherkin.
> Decisão arquitetural mãe: [ADR 0096](../../decisions/0096-modulo-whatsapp-meta-cloud-api-direto.md) — **2 drivers oficiais (Meta Cloud + Z-API/Baileys) com fallback automático**.

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
- **Driver** — abstração `MetaCloudDriver` / `ZapiDriver` / `EvolutionDriver` / `NullDriver` (padrão canon ADR 0050)
- **Z-API** — SaaS BR (`api.z-api.io`) baseado em Whatsapp Web. Onboarding 5 min (scan QR). Risco ban Meta aceito (mitigado por fallback)
- **Evolution API** — open-source self-host CT 100 (Docker). Mesmo modelo Whatsapp Web. Sprint 2.
- **Driver Health Check** — job 6h em 6h envia mensagem-piloto pra detectar ban (Sprint 2)
- **Fallback driver** — `whatsapp_business_configs.fallback_driver`; troca automaticamente se driver primário ficar `degraded`

## 2. User Stories — Sub-módulo Core (Sprint 1)

### US-WA-001 · Cadastrar configuração Whatsapp do business (escolher driver)

> **Área:** Settings
> **Rota:** `GET/PUT /whatsapp/settings`
> **Controller/ação:** `BusinessSettingsController@show` / `update`
> **Permissão Spatie:** `whatsapp.settings.manage`

**Como** Wagner (admin business)
**Quero** escolher o driver (`meta_cloud`, `zapi`, `evolution`) e cadastrar credenciais correspondentes + opcional `fallback_driver`
**Para** o módulo conseguir enviar/receber mensagens; ter onboarding rápido (Z-API) ou produção formal (Meta Cloud)

**DoD:**
- [ ] Seletor radio com 3 opções de driver na UI Settings, com card explicativo de cada (custo, onboarding, risco ban)
- [ ] Form dinâmico — campos visíveis variam por driver:
  - `meta_cloud`: `phone_number_id`, `access_token`, `app_secret`, `webhook_verify_token`
  - `zapi`: `zapi_instance_id`, `zapi_instance_token`, `zapi_client_token`
  - `evolution`: `evolution_base_url` (CT 100 hostname), `evolution_instance_name`, `evolution_api_key`
- [ ] Tokens (todos) cifrados em DB via `encrypted` cast Laravel
- [ ] FormRequest valida campos obrigatórios por driver
- [ ] Webhook URL exibida na UI conforme driver:
  - `meta_cloud`: `https://oimpresso.com/api/whatsapp/webhook/meta/{business_uuid}` + `webhook_verify_token`
  - `zapi`: `https://oimpresso.com/api/whatsapp/webhook/zapi/{business_uuid}` (Z-API painel)
  - `evolution`: `https://oimpresso.com/api/whatsapp/webhook/evolution/{business_uuid}` (Evolution config)
- [ ] Botão "Testar conexão" — chama `Driver::ping()` → exibe status (nome do número, sessão ativa, etc)
- [ ] Onboarding guide por driver (link Meta Business Manager / Z-API painel / Evolution Docker compose-managed)
- [ ] Campo `fallback_driver` (opcional) — se primário falhar 5×, troca automaticamente
- [ ] Badge UI status driver: ✅ saudável / ⚠️ degradado (warnings) / 🔴 banido/desconectado
- [ ] CTA visível pra drivers não-oficiais: "⚠️ Provedor não-oficial. Recomendamos cadastrar Meta Cloud como fallback agora pra evitar interrupção em caso de ban."
- [ ] Pest: `BusinessSettingsTest` cobrindo (a) cada driver salva credenciais corretas, (b) tokens cifrados em DB, (c) isolamento multi-tenant, (d) fallback config preserva

### US-WA-002 · Driver Interface + MetaCloudDriver + NullDriver

> **Área:** Core
> **Service:** `Modules\Whatsapp\Services\Drivers\DriverInterface`
> **Implementações Sprint 1:** `MetaCloudDriver` (oficial Meta), `ZapiDriver` (oficial Z-API), `NullDriver` (dev/CI)
> **Implementações Sprint 2:** `EvolutionDriver` (self-host CT 100)

**Como** Sistema
**Quero** abstração trocável de provedor (Meta Cloud, Z-API, Evolution, futuros)
**Para** business escolher driver via Settings sem refactor cross-module + permitir fallback

**DoD:**
- [ ] Interface `DriverInterface`:
  - `sendTemplate(WhatsappBusinessConfig $config, string $to, string $templateName, array $params, string $locale='pt_BR'): WhatsappSendResult` — Meta usa HSM; Z-API/Evolution mandam como freeform
  - `sendFreeform(WhatsappBusinessConfig $config, string $to, string $body): WhatsappSendResult`
  - `sendMedia(WhatsappBusinessConfig $config, string $to, string $mediaUrl, string $type, ?string $caption): WhatsappSendResult`
  - `fetchMessageStatus(WhatsappBusinessConfig $config, string $providerMessageId): MessageStatus`
  - `ping(WhatsappBusinessConfig $config): DriverHealthStatus` — retorna nome número, sessão ativa, last_seen
- [ ] `MetaCloudDriver` usa `Http::withToken()` Laravel HTTP client; sem dependência Composer extra
- [ ] `NullDriver` retorna sucesso fake; gera `provider_message_id` UUID; usa `Event::dispatch` pra simular delivery
- [ ] Factory `DriverFactory::make($business)` resolve via `whatsapp_business_configs.driver`:
  ```php
  return match($config->driver) {
      'meta_cloud' => app(MetaCloudDriver::class),
      'zapi' => app(ZapiDriver::class),
      'evolution' => app(EvolutionDriver::class),
      'null' => app(NullDriver::class),
  };
  ```
- [ ] Pest: `MetaCloudDriverTest` com `Http::fake()` cobrindo sucesso/4xx/5xx; `NullDriverTest`; `DriverFactoryTest` resolve correto por config

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

### US-WA-002c · EvolutionDriver (driver self-host Sprint 2)

> **Área:** Core
> **Service:** `Modules\Whatsapp\Services\Drivers\EvolutionDriver`
> **Permissão Spatie:** `whatsapp.send`

**Como** Sistema
**Quero** enviar/receber via Evolution API self-host CT 100 (Docker)
**Para** business com volume alto evitar custo Z-API SaaS, controle total

**DoD:**
- [ ] Implementa `DriverInterface` integralmente — Send: `POST {base_url}/message/sendText/{instance}` (Evolution REST docs)
- [ ] Header `apikey: {api_key}`
- [ ] `ping()` chama `GET {base_url}/instance/connectionState/{instance}`
- [ ] Container Docker compose-managed em CT 100 com Traefik label (padrão `proxmox-docker-host`):
  - `traefik.http.routers.evolution.rule=Host(`evolution.oimpresso.local`)`
  - Persistência dados em `/srv/docker/evolution/data` (sessão Whatsapp Web)
- [ ] Pest: `EvolutionDriverTest` com `Http::fake()`
- [ ] Runbook: `memory/requisitos/Whatsapp/runbooks/evolution-deploy-ct100.md`
- [ ] **Risco aceito documentado** no class-level docblock (mesma nota Z-API)

### US-WA-003 · Enviar mensagem template (Job assíncrono)

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

## 7. Backlog futuro (não-Sprint 1-3)

- US-WA-030 — Botões interativos (`button` template) — HSM com CTAs
- US-WA-031 — List messages (cardápio gráfica: orçar, acompanhar OS, segunda via)
- US-WA-032 — Mídia outbound (imagem, PDF de boleto/NFe anexado)
- US-WA-033 — Mídia inbound (cliente manda foto do produto pra orçar)
- US-WA-034 — Suporte multi-driver (Twilio, Take Blip) se enterprise pedir
- US-WA-035 — White-label templates (módulo Officeimpresso revenue)
- US-WA-036 — Portal cliente self-service "minhas conversas Whatsapp"
- US-WA-037 — Integração Crm (lead vindo de Whatsapp vira lead no Crm)
- US-WA-038 — Pix Copia-e-Cola via Whatsapp (RecurringBilling US-RB-044 v2)
